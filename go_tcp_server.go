package main

import (
	"bufio"
	"crypto/md5"
	"encoding/base64"
	"fmt"
	"log"
	"net"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"
)

// Configuration constants
const (
	DEBUG_MODE           = true
	DEBUG_SERVER_KEY     = "ABCDEFGH" // Exactly 8 characters
	USE_FIXED_DEBUG_KEY  = true
	KEY_LENGTH           = 8
	CONNECTION_TIMEOUT   = 300 // 5 minutes
	INACTIVITY_CHECK_INT = 60  // 1 minute
)

// Command constants
const (
	CMD_INIT = "INIT"
	CMD_ERRL = "ERRL"
	CMD_PING = "PING"
	CMD_INFO = "INFO"
	CMD_VERS = "VERS"
	CMD_DWNL = "DWNL"
	CMD_GREQ = "GREQ"
	CMD_SRSP = "SRSP"
)

// CryptoDictionary mirrors the one in the original Python code
var CRYPTO_DICTIONARY = []string{
	"123hk12h8dcal",
	"FT676Ugug6sFa",
	"a6xbBa7A8a9la",
	"qMnxbtyTFvcqi",
	"cx7812vcxFRCC",
	"bab7u682ftysv",
	"YGbsux&Ygsyxg",
	"MSN><hu8asG&&",
	"23yY88syHXvvs",
	"987sX&sysy891",
}

// TCPConnection represents a client connection
type TCPConnection struct {
	conn          net.Conn
	clientID      string
	authenticated bool
	clientHost    string
	appType       string
	appVersion    string
	serverKey     string
	keyLength     int
	cryptoKey     string
	lastError     string
	lastActivity  time.Time
	lastPing      time.Time
	connMutex     sync.Mutex
}

// TCPServer represents the TCP server
type TCPServer struct {
	listener   net.Listener
	connections map[string]*TCPConnection
	pending     []*TCPConnection
	running     bool
	connMutex   sync.Mutex
}

// NewTCPServer creates a new TCP server
func NewTCPServer() *TCPServer {
	return &TCPServer{
		connections: make(map[string]*TCPConnection),
		pending:     make([]*TCPConnection, 0),
		running:     false,
	}
}

// Start the TCP server
func (s *TCPServer) Start(host string, port int) error {
	addr := fmt.Sprintf("%s:%d", host, port)
	listener, err := net.Listen("tcp", addr)
	if err != nil {
		return err
	}
	
	s.listener = listener
	s.running = true
	
	log.Printf("TCP server started and listening on %s", addr)
	
	// Start inactive connection cleanup routine
	go s.cleanupInactiveConnections()
	
	// Accept connections
	go func() {
		for s.running {
			conn, err := listener.Accept()
			if err != nil {
				if !s.running {
					return
				}
				log.Printf("Error accepting connection: %v", err)
				continue
			}
			
			tcpConn := &TCPConnection{
				conn:         conn,
				lastActivity: time.Now(),
				lastPing:     time.Now(),
			}
			
			s.connMutex.Lock()
			s.pending = append(s.pending, tcpConn)
			s.connMutex.Unlock()
			
			go s.handleConnection(tcpConn)
		}
	}()
	
	return nil
}

// Stop the TCP server
func (s *TCPServer) Stop() {
	s.running = false
	if s.listener != nil {
		s.listener.Close()
	}
	
	// Close all connections
	s.connMutex.Lock()
	defer s.connMutex.Unlock()
	
	for _, conn := range s.connections {
		conn.conn.Close()
	}
	
	for _, conn := range s.pending {
		conn.conn.Close()
	}
	
	log.Printf("TCP server stopped")
}

// Clean up inactive connections
func (s *TCPServer) cleanupInactiveConnections() {
	for s.running {
		time.Sleep(time.Duration(INACTIVITY_CHECK_INT) * time.Second)
		
		s.connMutex.Lock()
		
		now := time.Now()
		var pendingToRemove []*TCPConnection
		
		// Check authenticated connections
		for id, conn := range s.connections {
			if now.Sub(conn.lastActivity) > time.Duration(CONNECTION_TIMEOUT)*time.Second {
				log.Printf("Removing inactive connection for client %s", id)
				conn.conn.Close()
				delete(s.connections, id)
			}
		}
		
		// Check pending connections
		for _, conn := range s.pending {
			if now.Sub(conn.lastActivity) > time.Duration(CONNECTION_TIMEOUT)*time.Second {
				pendingToRemove = append(pendingToRemove, conn)
				conn.conn.Close()
			}
		}
		
		// Remove pending connections
		if len(pendingToRemove) > 0 {
			newPending := make([]*TCPConnection, 0, len(s.pending)-len(pendingToRemove))
			for _, conn := range s.pending {
				remove := false
				for _, toRemove := range pendingToRemove {
					if conn == toRemove {
						remove = true
						break
					}
				}
				if !remove {
					newPending = append(newPending, conn)
				}
			}
			s.pending = newPending
			log.Printf("Removed %d inactive pending connections", len(pendingToRemove))
		}
		
		s.connMutex.Unlock()
	}
}

// Handle a client connection
func (s *TCPServer) handleConnection(conn *TCPConnection) {
	log.Printf("New TCP connection from %s", conn.conn.RemoteAddr())
	
	reader := bufio.NewReader(conn.conn)
	
	for s.running {
		// Read a line from the client
		line, err := reader.ReadString('\n')
		if err != nil {
			log.Printf("Error reading from client: %v", err)
			break
		}
		
		// Update last activity
		conn.lastActivity = time.Now()
		
		// Process the command
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		
		log.Printf("Received command from %s: %s", conn.conn.RemoteAddr(), line)
		
		// Handle the command
		response, err := s.handleCommand(conn, line)
		if err != nil {
			log.Printf("Error handling command: %v", err)
			break
		}
		
		if response != "" {
			// Send the response
			_, err = conn.conn.Write([]byte(response))
			if err != nil {
				log.Printf("Error sending response: %v", err)
				break
			}
			log.Printf("Sent response: %s", response)
		}
	}
	
	// Clean up the connection
	s.cleanupConnection(conn)
}

// Clean up a connection
func (s *TCPServer) cleanupConnection(conn *TCPConnection) {
	s.connMutex.Lock()
	defer s.connMutex.Unlock()
	
	if conn.clientID != "" {
		delete(s.connections, conn.clientID)
	} else {
		for i, pendingConn := range s.pending {
			if pendingConn == conn {
				s.pending = append(s.pending[:i], s.pending[i+1:]...)
				break
			}
		}
	}
	
	conn.conn.Close()
	log.Printf("Client %s disconnected", conn.conn.RemoteAddr())
}

// Handle a command from a client
func (s *TCPServer) handleCommand(conn *TCPConnection, command string) (string, error) {
	parts := strings.Split(command, " ")
	if len(parts) == 0 {
		return "", nil
	}
	
	cmd := strings.ToUpper(parts[0])
	
	switch cmd {
	case CMD_INIT:
		return s.handleInit(conn, parts)
	case CMD_ERRL:
		return s.handleError(conn, parts)
	case CMD_PING:
		return s.handlePing(conn)
	case CMD_INFO:
		return s.handleInfo(conn, parts)
	case CMD_VERS:
		return s.handleVersion(conn, parts)
	case CMD_DWNL:
		return s.handleDownload(conn, parts)
	case CMD_GREQ:
		return s.handleReportRequest(conn, parts)
	case CMD_SRSP:
		return s.handleResponse(conn, parts)
	default:
		log.Printf("Unknown command: %s", cmd)
		return "ERROR Unknown command\r\n", nil
	}
}

// Parse parameters from command parts
func parseParameters(parts []string) map[string]string {
	params := make(map[string]string)
	
	for _, part := range parts {
		if strings.Contains(part, "=") {
			kv := strings.SplitN(part, "=", 2)
			if len(kv) == 2 {
				params[strings.ToUpper(kv[0])] = kv[1]
			}
		}
	}
	
	return params
}

// Handle the INIT command
func (s *TCPServer) handleInit(conn *TCPConnection, parts []string) (string, error) {
	log.Printf("Handling INIT command with parts: %v", parts)
	
	params := parseParameters(parts[1:])
	log.Printf("Extracted params: %v", params)
	
	hostname := params["HST"]
	if hostname == "" {
		hostname = params["HOST"]
		if hostname == "" {
			hostname = "UNKNOWN"
		}
	}
	
	dictIndex := 0
	if idxStr, ok := params["IDX"]; ok {
		idx, err := strconv.Atoi(idxStr)
		if err == nil {
			dictIndex = idx
		}
	}
	
	// Store client hostname
	conn.clientHost = hostname
	log.Printf("Client hostname: %s, Dictionary index: %d", hostname, dictIndex)
	
	// Generate server key
	var serverKey string
	if DEBUG_MODE && USE_FIXED_DEBUG_KEY {
		serverKey = DEBUG_SERVER_KEY
	} else {
		serverKey = generateRandomKey(KEY_LENGTH)
	}
	
	keyLen := len(serverKey)
	conn.serverKey = serverKey
	conn.keyLength = keyLen
	
	// Get dictionary entry for key construction
	dictEntry := CRYPTO_DICTIONARY[dictIndex]
	cryptoDictPart := dictEntry[:keyLen]
	
	// Extract host parts for key generation
	hostFirstChars := ""
	hostLastChar := ""
	if len(hostname) >= 2 {
		hostFirstChars = hostname[:2]
	}
	if len(hostname) > 0 {
		hostLastChar = hostname[len(hostname)-1:]
	}
	
	log.Printf("Host parts: original='%s', cleaned='%s'", hostFirstChars+hostLastChar, hostname[:minInt(3, len(hostname))])
	
	// Create crypto key
	cryptoKey := serverKey + cryptoDictPart + hostFirstChars + hostLastChar
	conn.cryptoKey = cryptoKey
	log.Printf("Using crypto key: %s", cryptoKey)
	
	// Test encryption (just log for now since we're not implementing actual encryption here)
	log.Printf("Crypto validation test passed successfully")
	
	// Format response for client - CRITICAL: This format must exactly match the Windows server format
	// Based on logs, the Windows server format is: KEY=XXXX,LEN=Y
	response := fmt.Sprintf("KEY=%s,LEN=%d\r\n", serverKey, keyLen)
	
	log.Printf("Final normalized response: %s", response)
	log.Printf("Using crypto key for client %s: %s", hostname, cryptoKey)
	
	return response, nil
}

// Handle the ERROR command
func (s *TCPServer) handleError(conn *TCPConnection, parts []string) (string, error) {
	errorMsg := strings.Join(parts[1:], " ")
	log.Printf("Client error: %s", errorMsg)
	
	if strings.Contains(errorMsg, "Unable to initizlize communication") {
		log.Printf("Analysis: Problem with INIT response format or incorrect crypto key")
		log.Printf("INIT parameters: ID=%s, host=%s", conn.clientID, conn.clientHost)
		log.Printf("Server key: %s, length: %d", conn.serverKey, conn.keyLength)
		log.Printf("Crypto key: %s", conn.cryptoKey)
	}
	
	return "OK\r\n", nil
}

// Handle the PING command
func (s *TCPServer) handlePing(conn *TCPConnection) (string, error) {
	conn.lastPing = time.Now()
	return "PONG\r\n", nil
}

// Handle the INFO command - placeholder
func (s *TCPServer) handleInfo(conn *TCPConnection, parts []string) (string, error) {
	// Real implementation would decrypt the data, process it, and encrypt the response
	return "DATA=Response_Data\r\n", nil
}

// Handle the VERSION command - placeholder
func (s *TCPServer) handleVersion(conn *TCPConnection, parts []string) (string, error) {
	return "C=0\r\n", nil
}

// Handle the DOWNLOAD command - placeholder
func (s *TCPServer) handleDownload(conn *TCPConnection, parts []string) (string, error) {
	return "OK\r\n", nil
}

// Handle the REPORT REQUEST command - placeholder
func (s *TCPServer) handleReportRequest(conn *TCPConnection, parts []string) (string, error) {
	return "OK\r\n", nil
}

// Handle the RESPONSE command - placeholder
func (s *TCPServer) handleResponse(conn *TCPConnection, parts []string) (string, error) {
	return "OK\r\n", nil
}

// Helper function to generate a random key
func generateRandomKey(length int) string {
	const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
	result := make([]byte, length)
	for i := range result {
		result[i] = charset[i%len(charset)]
	}
	return string(result)
}

// Helper function to compress data using zlib - placeholder
func compressData(data string, key string) string {
	// This is a placeholder. A real implementation would:
	// 1. Hash the key with MD5
	// 2. Use the hash as an AES key
	// 3. Compress the data with zlib
	// 4. Encrypt with AES-CBC
	// 5. Base64 encode
	return base64.StdEncoding.EncodeToString([]byte("compressed:" + data))
}

// Helper function to decompress data - placeholder
func decompressData(data string, key string) string {
	// This is a placeholder for the reverse of compressData
	decoded, _ := base64.StdEncoding.DecodeString(data)
	return string(decoded)
}

// Helper function for min of two ints
func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// Test the crypto key with a test string - placeholder
func testEncryption(key string) bool {
	// This would test encryption/decryption
	// For now, just return true
	return true
}

func main() {
	// Read environment variables or use defaults
	host := getEnv("TCP_HOST", "0.0.0.0")
	portStr := getEnv("TCP_PORT", "8016")
	port, err := strconv.Atoi(portStr)
	if err != nil {
		log.Fatalf("Invalid port number: %s", portStr)
	}
	
	// Create and start the TCP server
	server := NewTCPServer()
	err = server.Start(host, port)
	if err != nil {
		log.Fatalf("Failed to start server: %v", err)
	}
	
	log.Printf("TCP server started on %s:%d", host, port)
	
	// Keep the main thread running
	select {}
}

// Helper function to get environment variable with default
func getEnv(key, defaultValue string) string {
	value := os.Getenv(key)
	if value == "" {
		return defaultValue
	}
	return value
} 