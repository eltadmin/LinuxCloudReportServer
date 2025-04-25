package server

import (
	"fmt"
	"net"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/eltrade/reportcom/internal/crypto"
	"github.com/eltrade/reportcom/internal/logging"
)

// ConnectionInfo holds information about the client connection
type ConnectionInfo struct {
	RemoteHost     string
	RemoteIP       string
	RemotePort     int
	LocalPort      int
	ConnectTime    time.Time
	DisconnectTime time.Time
	LastAction     time.Time
}

// TCPConnection represents a client connection to the TCP server
type TCPConnection struct {
	conn              net.Conn
	connectionInfo    ConnectionInfo
	clientID          string
	timeDiffSec       int
	serverKey         string
	cryptoKey         string
	clientHost        string
	clientName        string
	appType           string
	appVersion        string
	dbType            string
	expireDate        time.Time
	busy              bool
	requestCount      int
	lastRequest       string
	lastResponse      string
	lastError         string
	waitResponse      chan bool
	closing           bool
	mu                sync.Mutex
	logger            *logging.Logger
	keyLength         int
	useSpecialKey     bool
	specialKey        string
	alternativeKeys   []string
}

// NewTCPConnection creates a new TCP connection from a net.Conn
func NewTCPConnection(conn net.Conn, logger *logging.Logger, serverKey string) *TCPConnection {
	// Extract remote and local info
	remoteAddr := conn.RemoteAddr().(*net.TCPAddr)
	localAddr := conn.LocalAddr().(*net.TCPAddr)

	// Generate server key if not provided
	if serverKey == "" {
		// Use a random hex string like D5F2
		serverKey = fmt.Sprintf("%02X%02X", randomInt(0, 255), randomInt(0, 255))
	}

	now := time.Now()
	
	// Create the connection
	return &TCPConnection{
		conn: conn,
		connectionInfo: ConnectionInfo{
			RemoteHost:     remoteAddr.IP.String(),
			RemoteIP:       remoteAddr.IP.String(),
			RemotePort:     remoteAddr.Port,
			LocalPort:      localAddr.Port,
			ConnectTime:    now,
			LastAction:     now,
			DisconnectTime: time.Time{},
		},
		serverKey:       serverKey,
		waitResponse:    make(chan bool, 1),
		logger:          logger,
		alternativeKeys: make([]string, 0),
	}
}

// ClientID gets the client ID
func (c *TCPConnection) ClientID() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.clientID
}

// SetClientID sets the client ID
func (c *TCPConnection) SetClientID(id string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.clientID = id
}

// ClientHost gets the client hostname
func (c *TCPConnection) ClientHost() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.clientHost
}

// SetClientHost sets the client hostname
func (c *TCPConnection) SetClientHost(host string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.clientHost = host
}

// ClientName gets the client name
func (c *TCPConnection) ClientName() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.clientName
}

// SetClientName sets the client name
func (c *TCPConnection) SetClientName(name string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.clientName = name
}

// CryptoKey gets the crypto key
func (c *TCPConnection) CryptoKey() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.cryptoKey
}

// SetCryptoKey sets the crypto key
func (c *TCPConnection) SetCryptoKey(key string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.cryptoKey = key
}

// ServerKey gets the server key
func (c *TCPConnection) ServerKey() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.serverKey
}

// SetSpecialKey sets the special key parameters
func (c *TCPConnection) SetSpecialKey(key string, length int) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.specialKey = key
	c.keyLength = length
	c.useSpecialKey = true
}

// IdleTime returns the number of seconds since the last activity
func (c *TCPConnection) IdleTime() time.Duration {
	c.mu.Lock()
	defer c.mu.Unlock()
	return time.Since(c.connectionInfo.LastAction)
}

// ConnectedTime returns the duration the connection has been active
func (c *TCPConnection) ConnectedTime() time.Duration {
	c.mu.Lock()
	defer c.mu.Unlock()
	return time.Since(c.connectionInfo.ConnectTime)
}

// LastError gets the last error
func (c *TCPConnection) LastError() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.lastError
}

// UpdateLastActivity updates the last activity timestamp
func (c *TCPConnection) UpdateLastActivity() {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.connectionInfo.LastAction = time.Now()
}

// InitCryptoKey initializes the crypto key from an INIT command
func (c *TCPConnection) InitCryptoKey(params map[string]string, specialKeys map[int]struct {
	Key      string
	Length   int
	Override bool
}) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	
	// Required parameters
	hostName := params["HST"]
	appType := params["ATP"]
	appVersion := params["AVR"]
	
	// Check required parameters
	if hostName == "" {
		return fmt.Errorf("missing client hostname (HST)")
	}
	
	if appType == "" {
		return fmt.Errorf("missing application type (ATP)")
	}
	
	if appVersion == "" {
		return fmt.Errorf("missing application version (AVR)")
	}
	
	// Get the client ID
	idStr := params["ID"]
	id, err := strconv.Atoi(idStr)
	if err != nil || id < 1 || id > 10 {
		return fmt.Errorf("invalid client ID: %s", idStr)
	}
	
	// Handle date and time for time difference calculation
	if dateStr, ok := params["DT"]; ok {
		if timeStr, ok := params["TM"]; ok {
			c.setTimeDiff(dateStr, timeStr)
		}
	}
	
	// Store client information
	c.clientHost = hostName
	c.appType = appType
	c.appVersion = appVersion
	
	// Check for special key configuration
	if specialKey, exists := specialKeys[id]; exists && specialKey.Override {
		// Use the special key and length
		c.specialKey = specialKey.Key
		c.keyLength = specialKey.Length
		c.useSpecialKey = true
		
		// Generate the crypto key
		if c.useSpecialKey {
			c.cryptoKey = c.specialKey
		} else {
			c.cryptoKey = crypto.GenerateCryptoKey(c.serverKey, id, c.keyLength, c.clientHost)
		}
		
		// Generate alternative keys for fallback
		if c.useSpecialKey {
			// Add standard key as alternative
			stdKey := crypto.GenerateCryptoKey(c.serverKey, id, 1, c.clientHost)
			c.alternativeKeys = append(c.alternativeKeys, stdKey)
		}
		
		// For ID=2, add additional alternative keys
		if id == 2 {
			c.alternativeKeys = append(c.alternativeKeys, "D5F2aRD-")
		}
		
		c.logger.Debug("Generated crypto key for client ID %d: %s", id, c.cryptoKey)
		return nil
	}
	
	// Default key generation
	// For most clients, use LEN=1
	keyLength := 1
	
	// For ID=9, use LEN=2
	if id == 9 {
		keyLength = 2
	}
	
	// For ID=8, use special key D028 and LEN=4
	if id == 8 {
		c.serverKey = "D028"
		keyLength = 4
	}
	
	c.keyLength = keyLength
	c.cryptoKey = crypto.GenerateCryptoKey(c.serverKey, id, keyLength, c.clientHost)
	
	c.logger.Debug("Generated crypto key for client ID %d: %s", id, c.cryptoKey)
	return nil
}

// setTimeDiff calculates the time difference between client and server
func (c *TCPConnection) setTimeDiff(dateStr, timeStr string) {
	// Convert client date/time to a time.Time
	// Date format is YYMMDD
	// Time format is HHMMSS
	if len(dateStr) != 6 || len(timeStr) != 6 {
		return
	}
	
	year, _ := strconv.Atoi("20" + dateStr[:2])
	month, _ := strconv.Atoi(dateStr[2:4])
	day, _ := strconv.Atoi(dateStr[4:6])
	
	hour, _ := strconv.Atoi(timeStr[:2])
	minute, _ := strconv.Atoi(timeStr[2:4])
	second, _ := strconv.Atoi(timeStr[4:6])
	
	clientTime := time.Date(year, time.Month(month), day, hour, minute, second, 0, time.Local)
	serverTime := time.Now()
	
	// Calculate difference in seconds
	c.timeDiffSec = int(serverTime.Sub(clientTime).Seconds())
}

// Send sends a message to the client
func (c *TCPConnection) Send(message string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	
	if c.closing {
		return fmt.Errorf("connection is closing")
	}
	
	// Add CR+LF if not present
	if !strings.HasSuffix(message, "\r\n") {
		message += "\r\n"
	}
	
	c.logger.Trace("Sending: %s", strings.TrimSpace(message))
	
	// Send the message
	_, err := c.conn.Write([]byte(message))
	return err
}

// Close closes the connection
func (c *TCPConnection) Close() error {
	c.mu.Lock()
	defer c.mu.Unlock()
	
	if c.closing {
		return nil
	}
	
	c.closing = true
	c.connectionInfo.DisconnectTime = time.Now()
	
	// Signal any waiting goroutines
	select {
	case c.waitResponse <- true:
	default:
	}
	
	// Close connection
	return c.conn.Close()
}

// EncryptData encrypts data using the crypto key
func (c *TCPConnection) EncryptData(data string) (string, error) {
	if data == "" {
		return "", fmt.Errorf("data is empty")
	}
	
	c.mu.Lock()
	cryptoKey := c.cryptoKey
	c.mu.Unlock()
	
	if cryptoKey == "" {
		return "", fmt.Errorf("crypto key not initialized")
	}
	
	compressor := crypto.NewDataCompressor(cryptoKey)
	encryptedData, err := compressor.CompressData(data)
	if err != nil {
		c.mu.Lock()
		c.lastError = fmt.Sprintf("Failed to encrypt data: %v", err)
		c.mu.Unlock()
		return "", err
	}
	
	return encryptedData, nil
}

// DecryptData decrypts data using the crypto key
func (c *TCPConnection) DecryptData(data string) (string, error) {
	if data == "" {
		return "", fmt.Errorf("data is empty")
	}
	
	c.mu.Lock()
	cryptoKey := c.cryptoKey
	alternativeKeys := c.alternativeKeys
	c.mu.Unlock()
	
	if cryptoKey == "" {
		return "", fmt.Errorf("crypto key not initialized")
	}
	
	// First attempt with primary key
	compressor := crypto.NewDataCompressor(cryptoKey)
	decryptedData, err := compressor.DecompressData(data)
	if err == nil {
		// Check if the decrypted data contains the validation token
		if strings.Contains(decryptedData, "TT=Test") {
			return decryptedData, nil
		}
	}
	
	// Try alternative keys if primary key fails
	for _, altKey := range alternativeKeys {
		c.logger.Debug("Trying alternative key: %s", altKey)
		compressor := crypto.NewDataCompressor(altKey)
		decryptedData, err := compressor.DecompressData(data)
		if err == nil && strings.Contains(decryptedData, "TT=Test") {
			// Update main key if alternative worked
			c.mu.Lock()
			c.cryptoKey = altKey
			c.mu.Unlock()
			return decryptedData, nil
		}
	}
	
	// If all keys failed
	c.mu.Lock()
	c.lastError = "Failed to decrypt data with all keys"
	if err != nil {
		c.lastError = fmt.Sprintf("Failed to decrypt data: %v", err)
	}
	c.mu.Unlock()
	
	return "", fmt.Errorf("failed to decrypt data with all keys")
}

// GetConnectionInfo returns a string representation of the connection info
func (c *TCPConnection) GetConnectionInfo() string {
	c.mu.Lock()
	defer c.mu.Unlock()
	
	info := c.connectionInfo
	
	// Format: Host:IP:Port ClientID Connected:Idle
	connectedTime := time.Since(info.ConnectTime).Round(time.Second)
	idleTime := time.Since(info.LastAction).Round(time.Second)
	
	return fmt.Sprintf("%s:%s:%d ID:%s Connected:%s Idle:%s", 
		info.RemoteHost, info.RemoteIP, info.RemotePort, 
		c.clientID, connectedTime, idleTime)
}

// GetDetailedInfo returns detailed information about the connection
func (c *TCPConnection) GetDetailedInfo() map[string]interface{} {
	c.mu.Lock()
	defer c.mu.Unlock()
	
	info := map[string]interface{}{
		"Id":          c.clientID,
		"Host":        c.clientHost,
		"Conn":        c.connectionInfo.ConnectTime.Format("2006-01-02 15:04:05"),
		"Act":         c.connectionInfo.LastAction.Format("2006-01-02 15:04:05"),
		"Name":        c.clientName,
		"AppType":     c.appType,
		"AppVersion":  c.appVersion,
		"RemoteIP":    c.connectionInfo.RemoteIP,
		"RemotePort":  c.connectionInfo.RemotePort,
		"ConnectedTime": time.Since(c.connectionInfo.ConnectTime).String(),
		"IdleTime":    time.Since(c.connectionInfo.LastAction).String(),
		"Busy":        c.busy,
	}
	
	if !c.expireDate.IsZero() {
		info["ExpireDate"] = c.expireDate.Format("2006-01-02")
	}
	
	return info
} 