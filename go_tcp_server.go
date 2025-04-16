package main

import (
	"bufio"
	"bytes"
	"crypto/aes"
	"crypto/cipher"
	"crypto/sha1"
	"encoding/base64"
	"encoding/hex"
	"fmt"
	"io"
	"log"
	"net"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"
	"compress/zlib"
)

// Configuration constants
const (
	DEBUG_MODE           = true
	DEBUG_SERVER_KEY     = "D5F2" // 4-character key like original Windows server
	USE_FIXED_DEBUG_KEY  = true
	KEY_LENGTH           = 4      // 4 characters like in logs
	CONNECTION_TIMEOUT   = 300    // 5 minutes
	INACTIVITY_CHECK_INT = 60     // 1 minute
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

// Handle a TCP connection
func (s *TCPServer) handleConnection(conn *TCPConnection) {
	defer s.cleanupConnection(conn)
	log.Printf("New TCP connection from %s", conn.conn.RemoteAddr())
	
	reader := bufio.NewReader(conn.conn)
	
	// Set a deadline for the first command to prevent hanging connections
	conn.conn.SetReadDeadline(time.Now().Add(120 * time.Second))
	
	for {
		// Проверка дали връзката е прекъсната
		if conn.conn == nil {
			log.Printf("Connection is closed")
			return
		}
		
		// Подготовка за четене на команда
		line, err := reader.ReadString('\n')
		if err != nil {
			if err == io.EOF {
				log.Printf("Client %s disconnected", conn.conn.RemoteAddr())
			} else {
				log.Printf("Error reading from client: %v", err)
			}
			return
		}
		
		// Премахване на trailing newlines/whitespace
		command := strings.TrimSpace(line)
		
		// Обработка на командата
		response, err := s.handleCommand(conn, command)
		if err != nil {
			log.Printf("Error handling command: %v", err)
			response = fmt.Sprintf("ERROR %v", err)
		}
		
		// Изпращане на отговора към клиента
		if response != "" {
			// Дали това е INIT отговор?
			isInit := strings.HasPrefix(command, "INIT ")
			
			// За не-INIT отговори добавяме \r\n накрая ако липсва
			if !isInit && !strings.HasSuffix(response, "\r\n") {
				response += "\r\n"
			}
			
			// Изпращане на отговора
			responseBytes := []byte(response)
			log.Printf("Response (bytes): % x", responseBytes)
			_, err := conn.conn.Write(responseBytes)
			if err != nil {
				log.Printf("Error sending response: %v", err)
				return
			}
			
			// Записване на отговора в лога
			if len(response) > 100 {
				log.Printf("Response sent: %s...", response[:100])
			} else {
				log.Printf("Response sent: %s", response)
			}
		}
		
		// Задаване на timeout за следващото четене
		conn.conn.SetReadDeadline(time.Now().Add(300 * time.Second))
		
		// Ако командата е EXIT, прекъсваме връзката
		if strings.HasPrefix(strings.ToUpper(command), "EXIT") {
			log.Printf("Client %s requested EXIT, closing connection", conn.conn.RemoteAddr())
			return
		}
	}
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

// Handle a command from the client
func (s *TCPServer) handleCommand(conn *TCPConnection, command string) (string, error) {
	// Trim any whitespace and check for emptiness
	command = strings.TrimSpace(command)
	if command == "" {
		return "", nil
	}
	
	// Log the incoming command
	log.Printf("Received command from %s: %s", conn.conn.RemoteAddr(), command)
	
	// Split the command into parts for processing
	parts := strings.Split(command, " ")
	cmd := strings.ToUpper(parts[0])
	
	// Store the last activity time
	conn.lastActivity = time.Now()
	
	// Handle different command types
	var response string
	var err error
	
	switch cmd {
	case "INIT":
		log.Printf("Handling INIT command from %s", conn.conn.RemoteAddr())
		response, err = s.handleInit(conn, parts)
		if err != nil {
			log.Printf("Error handling INIT command: %v", err)
			return fmt.Sprintf("ERROR %v", err), nil
		}
		log.Printf("INIT command processed successfully")
	case "ERRL":
		log.Printf("Handling ERROR command from %s", conn.conn.RemoteAddr())
		response, err = s.handleError(conn, parts)
	case "PING":
		log.Printf("Handling PING command from %s", conn.conn.RemoteAddr())
		response, err = s.handlePing(conn)
	case "INFO":
		log.Printf("Handling INFO command from %s", conn.conn.RemoteAddr())
		response, err = s.handleInfo(conn, parts)
	case "VERS":
		log.Printf("Handling VERSION command from %s", conn.conn.RemoteAddr())
		response, err = s.handleVersion(conn, parts)
	case "DWNL":
		log.Printf("Handling DOWNLOAD command from %s", conn.conn.RemoteAddr())
		response, err = s.handleDownload(conn, parts)
	case "GREQ":
		log.Printf("Handling REPORT REQUEST command from %s", conn.conn.RemoteAddr())
		response, err = s.handleReportRequest(conn, parts)
	case "SRSP":
		log.Printf("Handling RESPONSE command from %s", conn.conn.RemoteAddr())
		response, err = s.handleResponse(conn, parts)
	case "EXIT":
		log.Printf("Client %s requested disconnect", conn.conn.RemoteAddr())
		response = "OK"
		// Allowing the connection to close naturally after sending the response
	default:
		log.Printf("Unknown command '%s' from %s", cmd, conn.conn.RemoteAddr())
		response = fmt.Sprintf("ERROR Unknown command: %s", cmd)
	}
	
	if err != nil {
		log.Printf("Error processing %s command: %v", cmd, err)
		return fmt.Sprintf("ERROR %v", err), nil
	}
	
	// Log the response being sent (truncate if too long)
	if len(response) > 100 {
		log.Printf("Sending response to %s: %s...", conn.conn.RemoteAddr(), response[:100])
	} else {
		log.Printf("Sending response to %s: %s", conn.conn.RemoteAddr(), response)
	}
	
	// Check if this is a non-INIT response (those have special formatting)
	if cmd != "INIT" {
		log.Printf("Sending non-INIT response: '%s'", response)
	} else {
		log.Printf("Sending INIT response: raw bytes=%x", []byte(response))
	}
	
	return response, nil
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
	log.Printf("-------------- DEBUGGING INIT COMMAND --------------")
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
	
	// Get ID from params
	idValue := "0"
	if id, ok := params["ID"]; ok {
		idValue = id
	}
	
	// Store client hostname
	conn.clientHost = hostname
	conn.clientID = idValue
	log.Printf("Client hostname: %s, ID: %s", hostname, idValue)
	
	// HARDCODED KEYS BASED ON ID
	var serverKey string
	var lenValue int
	
	// Directly use hardcoded responses to ensure exact format
	switch idValue {
	case "3":
		serverKey = "D5F2"
		lenValue = 1
	case "4":
		serverKey = "D5F2"
		lenValue = 1
	case "5":
		serverKey = "D5F2"
		lenValue = 1
	case "6":
		serverKey = "F156"
		lenValue = 2
	case "7":
		serverKey = "77BE"
		lenValue = 6
	case "8":
		serverKey = "D5F2"
		lenValue = 1
	case "9":
		serverKey = "D5F2"
		lenValue = 1
	default:
		serverKey = "D5F2" 
		lenValue = 1
	}
	
	conn.serverKey = serverKey
	conn.keyLength = lenValue
	
	// Get dictionary entry - ВАЖНО: Delphi индексира от 1, не от 0
	dictIndex := 1 // Default value if ID parsing fails
	if id, err := strconv.Atoi(idValue); err == nil {
		// В Delphi масивите се индексират от 1, затова няма нужда от -1
		dictIndex = id
		
		// Ensure the index is within bounds
		if dictIndex < 1 {
			dictIndex = 1
		} else if dictIndex > len(CRYPTO_DICTIONARY) {
			dictIndex = dictIndex % len(CRYPTO_DICTIONARY)
			if dictIndex == 0 { // Защита срещу модул 0
				dictIndex = 1
			}
		}
	}
	
	// Вземи елемента от речника (коригирай индекса за Go, който индексира от 0)
	dictEntry := CRYPTO_DICTIONARY[dictIndex-1]
	
	// Използваме само стойността на ID като част от ключа, а не първите N символа от речника
	// Причината е, че много е вероятно Delphi кода да използва стойността на ID директно
	cryptoDictPart := idValue
	
	// Extract host parts for key generation
	hostFirstChars := ""
	hostLastChar := ""
	if len(hostname) >= 2 {
		hostFirstChars = hostname[:2]
	}
	if len(hostname) > 0 {
		hostLastChar = hostname[len(hostname)-1:]
	}
	
	// Create crypto key точно като в Delphi кода
	cryptoKey := serverKey + cryptoDictPart + hostFirstChars + hostLastChar
	conn.cryptoKey = cryptoKey
	
	// Форматираме отговора точно според Wireshark записа на оригиналния сървър
	// Формат: "200-KEY=xxx\r\n200 LEN=y\r\n"
	response := fmt.Sprintf("200-KEY=%s\r\n200 LEN=%d\r\n", serverKey, lenValue)
	
	// DEBUG PRINT DETAILED INFORMATION
	log.Printf("=========== INIT RESPONSE DETAILS ===========")
	log.Printf("ID Value: '%s' => Dictionary Index: %d", idValue, dictIndex)
	log.Printf("Crypto ID Part Used: '%s'", cryptoDictPart)
	log.Printf("Dictionary Entry [%d]: '%s' (not used directly)", dictIndex, dictEntry)
	log.Printf("Server Key: '%s'", serverKey)
	log.Printf("LEN Value: %d", lenValue)
	log.Printf("Response (text): '%s'", response)
	log.Printf("Response (bytes): % x", []byte(response))
	log.Printf("Dictionary Entry [%d]: '%s'", dictIndex, dictEntry)
	log.Printf("Dictionary Part Used: '%s'", cryptoDictPart)
	log.Printf("Host First Chars: '%s'", hostFirstChars)
	log.Printf("Host Last Char: '%s'", hostLastChar)
	log.Printf("Final Crypto Key: '%s'", cryptoKey)
	log.Printf("===========================================")
	
	// Return the exact response without any line endings
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
	
	// Original Windows server returns "OK" without newlines
	return "OK", nil
}

// Handle the PING command
func (s *TCPServer) handlePing(conn *TCPConnection) (string, error) {
	conn.lastPing = time.Now()
	return "PONG", nil
}

// Handle the INFO command
func (s *TCPServer) handleInfo(conn *TCPConnection, parts []string) (string, error) {
	// Проверка за валидност на командата
	if len(parts) < 2 {
		return "ERROR Invalid INFO command", nil
	}
	
	// Извличане на DATA параметъра
	var encryptedData string
	for _, part := range parts[1:] {
		if strings.HasPrefix(part, "DATA=") {
			encryptedData = part[5:] // всичко след "DATA="
			break
		}
	}
	
	if encryptedData == "" {
		return "ERROR No DATA parameter in INFO command", nil
	}
	
	// Подробна информация за извлечените данни и криптиращия ключ
	log.Printf("Received encrypted data: %s", encryptedData)
	log.Printf("Using crypto key: %s", conn.cryptoKey)
	
	// Разширен дебъг
	log.Printf("==== CRYPTO DETAILS ====")
	log.Printf("Client ID: %s", conn.clientID)
	log.Printf("Client host: %s", conn.clientHost)
	log.Printf("Server key: %s", conn.serverKey)
	log.Printf("Key length: %d", conn.keyLength)
	log.Printf("Final crypto key: %s", conn.cryptoKey)
	log.Printf("======================")
	
	// Опит за дешифриране с фокус върху съвместимост с Delphi
	decryptedData := decompressData(encryptedData, conn.cryptoKey)
	log.Printf("Decrypted data: %s", decryptedData)
	
	// Извличане на параметри от дешифрираните данни
	params := make(map[string]string)
	if decryptedData != "" {
		// Опит за намиране на валидни ключове/стойности
		for _, line := range strings.Split(decryptedData, "\r\n") {
			if pos := strings.Index(line, "="); pos > 0 {
				key := line[:pos]
				value := line[pos+1:]
				params[key] = value
				log.Printf("Extracted param: %s = %s", key, value)
			}
		}
	}
	
	// Ако проблемите продължават, опитайте с твърдо зададени данни
	log.Printf("Parsed parameters: %v", params)
	
	// Създаваме отговор базиран на параметрите
	responseData := "RESP=OK\r\nUSRID=1\r\nINFO=Server received credentials\r\n"
	
	// Добавяме специфична информация ако е налична
	if username, ok := params["USR"]; ok {
		responseData += fmt.Sprintf("USR=%s\r\n", username)
	}
	
	if _, ok := params["PWD"]; ok {
		responseData += "CREDS=VALID\r\n"
	}
	
	// Създаваме криптиран отговор
	encrypted := compressData(responseData, conn.cryptoKey)
	if encrypted == "" {
		log.Printf("ERROR: Failed to encrypt response data")
		return "ERROR Failed to encrypt response", nil
	}
	
	// Форматираме отговора
	response := fmt.Sprintf("DATA=%s", encrypted)
	
	log.Printf("Response data (plain): %s", responseData)
	log.Printf("Sending encrypted response: %s", response)
	
	return response, nil
}

// Handle the VERSION command - placeholder
func (s *TCPServer) handleVersion(conn *TCPConnection, parts []string) (string, error) {
	return "C=0", nil
}

// Handle the DOWNLOAD command - placeholder
func (s *TCPServer) handleDownload(conn *TCPConnection, parts []string) (string, error) {
	return "OK", nil
}

// Handle the REPORT REQUEST command - placeholder
func (s *TCPServer) handleReportRequest(conn *TCPConnection, parts []string) (string, error) {
	return "OK", nil
}

// Handle the RESPONSE command - placeholder
func (s *TCPServer) handleResponse(conn *TCPConnection, parts []string) (string, error) {
	return "OK", nil
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

// Helper function to compress data using zlib and encrypt with AES
func compressData(data string, key string) string {
	log.Printf("Encrypting data: '%s' with key: '%s'", data, key)
	
	// 1. Проверка за къс ключ - увеличаване дължината ако е нужно
	if len(key) >= 1 && len(key) <= 5 {
		key = key + "123456"
		log.Printf("Key is too short, padded to: %s", key)
	}
	
	// 2. Изчисляване на SHA1 хеш за AES ключа - точно както в Delphi's DCPcrypt
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 ключ
	
	// 3. Компресиране с zlib
	var compressedBuf bytes.Buffer
	zw, err := zlib.NewWriterLevel(&compressedBuf, zlib.BestCompression)
	if err != nil {
		log.Printf("Error creating zlib writer: %v", err)
		return ""
	}
	
	_, err = zw.Write([]byte(data))
	if err != nil {
		log.Printf("Error compressing data: %v", err)
		zw.Close()
		return ""
	}
	
	err = zw.Close()
	if err != nil {
		log.Printf("Error closing zlib writer: %v", err)
		return ""
	}
	
	compressed := compressedBuf.Bytes()
	log.Printf("Compressed data length: %d bytes", len(compressed))
	
	// 4. PKCS7 padding за AES блоковете
	blockSize := aes.BlockSize
	padding := blockSize - (len(compressed) % blockSize)
	if padding == 0 {
		padding = blockSize // Ако дължината е кратна на блок размера, добавяме пълен блок padding
	}
	
	paddingBytes := bytes.Repeat([]byte{byte(padding)}, padding)
	padded := append(compressed, paddingBytes...)
	
	log.Printf("Data padded to %d bytes (added %d bytes)", len(padded), padding)
	
	// 5. Шифроване с AES-CBC с нулев IV вектор
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// Нулев IV вектор - точно както в Delphi
	iv := make([]byte, aes.BlockSize)
	
	// Шифроване
	ciphertext := make([]byte, len(padded))
	mode := cipher.NewCBCEncrypter(block, iv)
	mode.CryptBlocks(ciphertext, padded)
	
	// 6. Base64 кодиране
	encoded := base64.StdEncoding.EncodeToString(ciphertext)
	
	// 7. Премахване на '=' символите от края - както прави Delphi
	encoded = strings.TrimRight(encoded, "=")
	
	// Подробен лог за отстраняване на грешки
	log.Printf("===== Encryption Steps =====")
	log.Printf("1. Original data: %d bytes", len(data))
	log.Printf("2. SHA1 hash of key: %x", keyHash)
	log.Printf("3. AES key (first 16 bytes): %x", aesKey)
	log.Printf("4. Compressed data: %d bytes", len(compressed))
	log.Printf("5. Padded data (PKCS7): %d bytes (padding: %d)", len(padded), padding)
	log.Printf("6. Encrypted data: %d bytes", len(ciphertext))
	log.Printf("7. Base64 encoded: %d bytes", len(encoded))
	log.Printf("8. Final result: %s", encoded)
	log.Printf("===========================")
	
	return encoded
}

// Helper function to decompress data
func decompressData(data string, key string) string {
	log.Printf("Decrypting data: '%s' with key: '%s'", data, key)
	
	// Проверка за къс ключ и допълване
	if len(key) >= 1 && len(key) <= 5 {
		key = key + "123456"
		log.Printf("Key is too short, padded to: %s", key)
	}
	
	// Дешифриране - стъпка по стъпка като Delphi
	
	// 1. Base64 декодиране с добавяне на padding
	var ciphertext []byte
	var err error
	
	// Добавяне на padding символи ако е нужно
	paddedData := data
	for i := 0; i < 4; i++ {
		ciphertext, err = base64.StdEncoding.DecodeString(paddedData)
		if err == nil {
			log.Printf("Successfully decoded Base64 data with %d padding chars", i)
			break
		}
		paddedData += "="
	}
	
	if err != nil {
		log.Printf("Error decoding Base64: %v", err)
		return ""
	}
	
	// 2. Генериране на AES ключ от криптиращия ключ
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 ключ
	
	// 3. Подготовка на данните за дешифриране - добавяне на padding ако е нужно
	blockSize := aes.BlockSize // 16 bytes
	if len(ciphertext) % blockSize != 0 {
		padding := make([]byte, blockSize - (len(ciphertext) % blockSize))
		ciphertext = append(ciphertext, padding...)
		log.Printf("Added %d bytes padding to make ciphertext a multiple of AES block size", len(padding))
	}
	
	// 4. Дешифриране с AES-CBC
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// Нулев IV вектор - точно както в Delphi имплементацията
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCDecrypter(block, iv)
	
	// Дешифриране
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)
	
	// 5. Премахване на PKCS7 padding
	// В някои случаи може padding да е невалиден, така че го правим внимателно
	var unpadded []byte
	paddingLen := int(plaintext[len(plaintext)-1])
	
	// Проверка за валиден padding
	if paddingLen > 0 && paddingLen <= aes.BlockSize {
		// Ако последният байт е валиден padding размер, проверяваме останалите
		valid := true
		for i := len(plaintext) - paddingLen; i < len(plaintext); i++ {
			if int(plaintext[i]) != paddingLen {
				valid = false
				break
			}
		}
		
		if valid {
			unpadded = plaintext[:len(plaintext)-paddingLen]
			log.Printf("Successfully removed %d bytes of PKCS7 padding", paddingLen)
		} else {
			// Невалиден padding - използваме данните без промяна
			unpadded = plaintext
			log.Printf("Invalid padding detected, using full plaintext")
		}
	} else {
		// Размерът на padding е извън валидния диапазон
		unpadded = plaintext
		log.Printf("Unusual padding value (%d), using full plaintext", paddingLen)
	}
	
	// 6. Декомпресиране със zlib
	// Пробваме декомпресия, но ако не сработи, връщаме данните както са
	zlibReader, err := zlib.NewReader(bytes.NewReader(unpadded))
	if err == nil {
		var decompressed bytes.Buffer
		_, err = io.Copy(&decompressed, zlibReader)
		zlibReader.Close()
		
		if err == nil {
			log.Printf("Successfully decompressed zlib data")
			return decompressed.String()
		} else {
			log.Printf("Error during zlib decompression: %v", err)
		}
	} else {
		log.Printf("Data is not zlib compressed: %v", err)
	}
	
	// Ако данните не са компресирани или има проблем с декомпресията,
	// опитваме да ги интерпретираме като текст
	// Търсим само печатните ASCII символи
	var result strings.Builder
	for _, b := range unpadded {
		if b >= 32 && b <= 126 {
			result.WriteByte(b)
		}
	}
	
	if result.Len() > 0 {
		log.Printf("Extracted %d printable ASCII characters", result.Len())
		return result.String()
	}
	
	// Ако всичко друго не сработи, връщаме хексадецимален стринг
	log.Printf("No printable characters found, returning hex representation")
	return hex.EncodeToString(unpadded)
}

// PKCS7 Padding helper functions
func pkcs7Pad(data []byte, blockSize int) []byte {
	padding := blockSize - len(data)%blockSize
	if padding == 0 {
		padding = blockSize
	}
	padtext := bytes.Repeat([]byte{byte(padding)}, padding)
	return append(data, padtext...)
}

// Helper function for min of two ints
func min(a, b int) int {
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