package server

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"strings"
	"sync"
	"time"

	"github.com/eltrade/reportcom/internal/config"
	"github.com/eltrade/reportcom/internal/crypto"
	"github.com/eltrade/reportcom/internal/logging"
)

// TCPServer is the main server that handles TCP connections
type TCPServer struct {
	cfg                 *config.Config
	listener            net.Listener
	activeConnections   map[string]*TCPConnection
	pendingConnections  map[string]*TCPConnection
	pendingRequests     map[string]map[int]string
	pendingResponses    map[string]map[int]string
	logger              *logging.Logger
	stopping            bool
	mu                  sync.RWMutex
	idleCheckInterval   time.Duration
	idleCheckTimer      *time.Timer
}

// NewTCPServer creates a new TCP server instance
func NewTCPServer(cfg *config.Config, logger *logging.Logger) (*TCPServer, error) {
	// Enable trace logging if configured
	if cfg.Server.TraceLogEnabled {
		if err := logger.EnableTrace(); err != nil {
			logger.Warning("Failed to enable trace logging: %v", err)
		}
	}
	
	server := &TCPServer{
		cfg:                cfg,
		activeConnections:  make(map[string]*TCPConnection),
		pendingConnections: make(map[string]*TCPConnection),
		pendingRequests:    make(map[string]map[int]string),
		pendingResponses:   make(map[string]map[int]string),
		logger:             logger,
		idleCheckInterval:  time.Second * 30, // Check for idle connections every 30 seconds
	}
	
	return server, nil
}

// Start starts the TCP server
func (s *TCPServer) Start() error {
	s.logger.Info("Starting TCP server on %s:%d", s.cfg.TCP.Interface, s.cfg.TCP.Port)
	
	addr := fmt.Sprintf("%s:%d", s.cfg.TCP.Interface, s.cfg.TCP.Port)
	listener, err := net.Listen("tcp", addr)
	if err != nil {
		return fmt.Errorf("failed to start TCP server: %v", err)
	}
	
	s.listener = listener
	
	// Start the idle connection checker
	s.startIdleChecker()
	
	// Accept connections
	for {
		conn, err := listener.Accept()
		if err != nil {
			if s.stopping {
				return nil // Server is shutting down
			}
			s.logger.Error("Failed to accept connection: %v", err)
			continue
		}
		
		// Handle the connection in a new goroutine
		go s.handleConnection(conn)
	}
}

// Stop stops the TCP server
func (s *TCPServer) Stop() {
	s.mu.Lock()
	s.stopping = true
	s.mu.Unlock()
	
	// Stop the idle checker
	if s.idleCheckTimer != nil {
		s.idleCheckTimer.Stop()
	}
	
	// Close the listener
	if s.listener != nil {
		s.listener.Close()
	}
	
	// Close all connections
	s.mu.Lock()
	defer s.mu.Unlock()
	
	// Close pending connections
	for _, conn := range s.pendingConnections {
		conn.Close()
	}
	
	// Close active connections
	for _, conn := range s.activeConnections {
		conn.Close()
	}
	
	s.logger.Info("TCP server stopped")
}

// GetActiveConnections returns a list of active connection information
func (s *TCPServer) GetActiveConnections() []map[string]interface{} {
	s.mu.RLock()
	defer s.mu.RUnlock()
	
	result := make([]map[string]interface{}, 0, len(s.activeConnections))
	
	for _, conn := range s.activeConnections {
		result = append(result, conn.GetDetailedInfo())
	}
	
	return result
}

// GetConnection gets a connection by client ID
func (s *TCPServer) GetConnection(clientID string) *TCPConnection {
	s.mu.RLock()
	defer s.mu.RUnlock()
	
	return s.activeConnections[clientID]
}

// SendRequest sends a request to a client and waits for response
func (s *TCPServer) SendRequest(clientID string, data string, timeout time.Duration) (string, error) {
	// Find the connection
	s.mu.RLock()
	conn, ok := s.activeConnections[clientID]
	s.mu.RUnlock()
	
	if !ok {
		return "", fmt.Errorf("client not found: %s", clientID)
	}
	
	// Check if client is busy
	if conn.busy {
		return "", fmt.Errorf("client is busy")
	}
	
	// Mark client as busy
	conn.mu.Lock()
	conn.busy = true
	requestID := conn.requestCount + 1
	conn.requestCount = requestID
	conn.mu.Unlock()
	
	// Ensure client is marked as not busy when we're done
	defer func() {
		conn.mu.Lock()
		conn.busy = false
		conn.mu.Unlock()
	}()
	
	// Encrypt the data
	encryptedData, err := conn.EncryptData(data)
	if err != nil {
		return "", fmt.Errorf("failed to encrypt data: %v", err)
	}
	
	// Store the request
	s.mu.Lock()
	if _, ok := s.pendingRequests[clientID]; !ok {
		s.pendingRequests[clientID] = make(map[int]string)
	}
	s.pendingRequests[clientID][requestID] = encryptedData
	s.mu.Unlock()
	
	// Create a channel to wait for response
	responseChan := make(chan struct{})
	
	// Create a goroutine to check for response
	var response string
	var responseErr error
	
	go func() {
		// Wait for the response to be stored in pendingResponses
		for {
			s.mu.RLock()
			if respMap, ok := s.pendingResponses[clientID]; ok {
				if resp, ok := respMap[requestID]; ok {
					response = resp
					delete(respMap, requestID)
					s.mu.RUnlock()
					close(responseChan)
					return
				}
			}
			s.mu.RUnlock()
			
			// Sleep for a short time before checking again
			time.Sleep(100 * time.Millisecond)
		}
	}()
	
	// Wait for either the response or timeout
	select {
	case <-responseChan:
		// Response received
		return response, nil
	case <-time.After(timeout):
		responseErr = fmt.Errorf("request timed out")
		close(responseChan)
		return "", responseErr
	}
}

// startIdleChecker starts the idle connection checker
func (s *TCPServer) startIdleChecker() {
	s.idleCheckTimer = time.AfterFunc(s.idleCheckInterval, func() {
		s.checkIdleConnections()
		
		// Reschedule if not stopping
		s.mu.RLock()
		stopping := s.stopping
		s.mu.RUnlock()
		
		if !stopping {
			s.startIdleChecker()
		}
	})
}

// checkIdleConnections checks for and removes idle connections
func (s *TCPServer) checkIdleConnections() {
	s.mu.Lock()
	defer s.mu.Unlock()
	
	now := time.Now()
	
	// Check pending connections
	for id, conn := range s.pendingConnections {
		idleTime := conn.IdleTime()
		connectedTime := conn.ConnectedTime()
		
		// Drop connections without client ID after timeout
		if connectedTime > time.Duration(s.cfg.TimeOut.DropNoSerial)*time.Second {
			s.logger.Info("Dropping pending connection %s due to inactivity (%s)", id, connectedTime)
			conn.Close()
			delete(s.pendingConnections, id)
		}
	}
	
	// Check active connections
	for id, conn := range s.activeConnections {
		idleTime := conn.IdleTime()
		
		// Drop idle connections after timeout
		if idleTime > time.Duration(s.cfg.TimeOut.DropNoActivity)*time.Second {
			s.logger.Info("Dropping client %s due to inactivity (%s)", id, idleTime)
			conn.Close()
			delete(s.activeConnections, id)
			delete(s.pendingRequests, id)
			delete(s.pendingResponses, id)
		}
	}
}

// handleConnection handles a new client connection
func (s *TCPServer) handleConnection(conn net.Conn) {
	// Create a new connection object
	tcpConn := NewTCPConnection(conn, s.logger, "D5F2") // Default server key
	
	remoteAddr := conn.RemoteAddr().(*net.TCPAddr)
	connID := fmt.Sprintf("%s:%d", remoteAddr.IP.String(), remoteAddr.Port)
	
	s.logger.Info("New connection from %s", connID)
	
	// Add to pending connections
	s.mu.Lock()
	s.pendingConnections[connID] = tcpConn
	s.mu.Unlock()
	
	// Create a reader for the connection
	reader := bufio.NewReader(conn)
	
	// Handle messages
	for {
		// Read a line from the connection
		line, err := reader.ReadString('\n')
		if err != nil {
			s.logger.Info("Connection closed: %s", connID)
			
			// Remove from connections
			s.mu.Lock()
			if clientID := tcpConn.ClientID(); clientID != "" {
				// Was an active connection with clientID
				delete(s.activeConnections, clientID)
				delete(s.pendingRequests, clientID)
				delete(s.pendingResponses, clientID)
			} else {
				// Was a pending connection
				delete(s.pendingConnections, connID)
			}
			s.mu.Unlock()
			
			tcpConn.Close()
			return
		}
		
		// Update last activity time
		tcpConn.UpdateLastActivity()
		
		// Parse command and parameters
		command, params := parseCommand(line)
		if command == "" {
			continue
		}
		
		// Log the command
		s.logger.Trace("Received: %s", strings.TrimSpace(line))
		
		// Handle the command
		response, err := s.handleCommand(tcpConn, command, params)
		if err != nil {
			s.logger.Error("Error handling command %s: %v", command, err)
			tcpConn.Send(fmt.Sprintf("%d %s", 503, err.Error()))
			continue
		}
		
		// Send the response
		if response != "" {
			if err := tcpConn.Send(response); err != nil {
				s.logger.Error("Error sending response: %v", err)
			}
		}
		
		// If client is now authenticated, move from pending to active
		if tcpConn.ClientID() != "" {
			s.mu.Lock()
			
			clientID := tcpConn.ClientID()
			
			// Check for duplicate client ID
			if existing, ok := s.activeConnections[clientID]; ok {
				// Duplicate client ID found
				s.logger.Warning("Duplicate client ID detected: %s", clientID)
				
				// Close the existing connection
				s.logger.Info("Closing existing connection for client ID: %s", clientID)
				existing.Close()
				
				// Remove references to the old connection
				delete(s.activeConnections, clientID)
				delete(s.pendingRequests, clientID)
				delete(s.pendingResponses, clientID)
			}
			
			// Move from pending to active connections
			s.activeConnections[clientID] = tcpConn
			delete(s.pendingConnections, connID)
			
			// Initialize request and response maps
			s.pendingRequests[clientID] = make(map[int]string)
			s.pendingResponses[clientID] = make(map[int]string)
			
			s.mu.Unlock()
		}
	}
}

// handleCommand handles a command from a client
func (s *TCPServer) handleCommand(conn *TCPConnection, command string, params map[string]string) (string, error) {
	switch command {
	case "PING":
		return "200", nil
		
	case "INIT":
		// Check if crypto key can be initialized
		err := conn.InitCryptoKey(params, s.cfg.SpecialKeys)
		if err != nil {
			return "", fmt.Errorf("failed to initialize crypto key: %v", err)
		}
		
		// For ID=8, use KEY=D028 and LEN=4
		if id, ok := params["ID"]; ok && id == "8" {
			return fmt.Sprintf("200-KEY=D028\r\n200 LEN=4"), nil
		}
		
		// For ID=9, use LEN=2
		if id, ok := params["ID"]; ok && id == "9" {
			return fmt.Sprintf("200-KEY=%s\r\n200 LEN=2", conn.ServerKey()), nil
		}
		
		// Default response
		return fmt.Sprintf("200-KEY=%s\r\n200 LEN=1", conn.ServerKey()), nil
		
	case "INFO":
		// Extract encrypted data
		data, ok := params["DATA"]
		if !ok {
			return "", fmt.Errorf("missing DATA parameter")
		}
		
		// Decrypt the data
		decryptedData, err := conn.DecryptData(data)
		if err != nil {
			conn.logger.Error("Failed to decrypt INFO data: %v", err)
			return "", fmt.Errorf("failed to decrypt data: %v", err)
		}
		
		// Parse key-value pairs
		clientInfo := parseKeyValueData(decryptedData)
		
		// Verify the validation token
		if clientInfo["TT"] != "Test" {
			return "", fmt.Errorf("invalid validation token")
		}
		
		// Extract client information
		clientID := clientInfo["ID"]
		if clientID == "" {
			return "", fmt.Errorf("missing client ID")
		}
		
		clientName := clientInfo["FN"] // Company/firm name
		if clientName == "" {
			clientName = clientInfo["ON"] // Office name as fallback
		}
		
		// Update client information
		conn.SetClientID(clientID)
		conn.SetClientName(clientName)
		
		// TODO: Check with REST API for client validation if configured
		
		// Prepare response
		response := map[string]string{
			"TT": "Test",
			"ID": clientID,
			"EN": "1", // Enabled
			"CD": time.Now().Format("02.01.2006"), // Creation date
			"CT": time.Now().Format("15:04:05"),   // Creation time
		}
		
		// Add expiry date if set
		if !conn.expireDate.IsZero() {
			response["EX"] = conn.expireDate.Format("02.01.2006")
		} else {
			// Set a default expiry date 30 days from now
			response["EX"] = time.Now().AddDate(0, 0, 30).Format("02.01.2006")
		}
		
		// Format the response data
		responseData := formatKeyValueData(response)
		
		// Encrypt the response
		encryptedResponse, err := conn.EncryptData(responseData)
		if err != nil {
			return "", fmt.Errorf("failed to encrypt response: %v", err)
		}
		
		// Return the encrypted response
		return fmt.Sprintf("200 DATA=%s", encryptedResponse), nil
		
	case "GREQ":
		// Get a pending request for the client
		clientID := conn.ClientID()
		if clientID == "" {
			return "", fmt.Errorf("client not authenticated")
		}
		
		// Check if there's a pending request
		s.mu.RLock()
		requestMap, ok := s.pendingRequests[clientID]
		if !ok || len(requestMap) == 0 {
			s.mu.RUnlock()
			return "200", nil // No pending requests
		}
		
		// Get the first request
		var requestID int
		var requestData string
		for id, data := range requestMap {
			requestID = id
			requestData = data
			break
		}
		s.mu.RUnlock()
		
		// Remove the request
		s.mu.Lock()
		delete(s.pendingRequests[clientID], requestID)
		s.mu.Unlock()
		
		// Return the request
		return fmt.Sprintf("200 CMD=%d DATA=%s", requestID, requestData), nil
		
	case "SRSP":
		// Handle response to a previous request
		clientID := conn.ClientID()
		if clientID == "" {
			return "", fmt.Errorf("client not authenticated")
		}
		
		// Extract command ID and data
		cmdStr, ok := params["CMD"]
		if !ok {
			return "", fmt.Errorf("missing CMD parameter")
		}
		
		data, ok := params["DATA"]
		if !ok {
			return "", fmt.Errorf("missing DATA parameter")
		}
		
		// Store the response
		s.mu.Lock()
		if _, ok := s.pendingResponses[clientID]; !ok {
			s.pendingResponses[clientID] = make(map[int]string)
		}
		
		cmdID := 0
		fmt.Sscanf(cmdStr, "%d", &cmdID)
		s.pendingResponses[clientID][cmdID] = data
		s.mu.Unlock()
		
		return "200", nil
		
	case "VERS":
		// Return version information
		// TODO: Implement version checking
		return "200", nil
		
	case "DWNL":
		// Handle file download
		// TODO: Implement file download
		return "200", nil
		
	case "ERRL":
		// Log client error
		clientID := conn.ClientID()
		errorMsg := strings.Join([]string{command}, " ")
		for _, v := range params {
			errorMsg += " " + v
		}
		
		// Log the error
		if clientID != "" {
			s.logger.ClientError(clientID, "Client error: %s", errorMsg)
		} else {
			s.logger.Error("Unauthenticated client error: %s", errorMsg)
		}
		
		return "200", nil
	}
	
	return "", fmt.Errorf("unknown command: %s", command)
} 