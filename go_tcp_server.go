package main

import (
	"bufio"
	"bytes"
	"crypto/aes"
	"crypto/cipher"
	"crypto/sha1"
	"encoding/base64"
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
		
		// Determine command type
		cmdParts := strings.Split(line, " ")
		if len(cmdParts) == 0 {
			continue
		}
		
		cmd := strings.ToUpper(cmdParts[0])
		
		// Handle the command
		response, err := s.handleCommand(conn, line)
		if err != nil {
			log.Printf("Error handling command: %v", err)
			break
		}
		
		if response != "" {
			// For INIT command, send exact byte-for-byte response with CRLF
			if cmd == CMD_INIT {
				log.Printf("====== SENDING INIT RESPONSE ======")
				
				// НЕ добавлять 0x00 в конец ответа - Delphi этого не ожидает
				responseBytes := []byte(response)
				
				log.Printf("Raw response: '%s'", response)
				log.Printf("Response bytes (hex): % x", responseBytes)
				log.Printf("Response length: %d bytes", len(responseBytes))
				
				_, err = conn.conn.Write(responseBytes)
				
				if err != nil {
					log.Printf("ERROR sending response: %v", err)
					break
				}
				
				log.Printf("INIT response sent successfully")
				log.Printf("==================================")
			} else {
				// For all other commands, add newline
				if !strings.HasSuffix(response, "\r\n") {
					response += "\r\n"
				}
				log.Printf("Sending non-INIT response: '%s'", response)
				_, err = conn.conn.Write([]byte(response))
				
				if err != nil {
					log.Printf("Error sending response: %v", err)
					break
				}
			}
			
			log.Printf("Response sent: %s", response)
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
		return "ERROR Unknown command", nil
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
	log.Printf("Client hostname: %s, ID: %s", hostname, idValue)
	
	// HARDCODED KEYS BASED ON ID
	var serverKey string
	var lenValue int
	
	// Directly use hardcoded responses to ensure exact format
	switch idValue {
	case "3":
		serverKey = "D5F2"
		lenValue = 1
	case "6":
		serverKey = "F156"
		lenValue = 2
	case "7":
		serverKey = "77BE"
		lenValue = 6
	case "9":
		serverKey = "D5F2" // Use same format as ID=3
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
	
	// Вземи само първите N символа, където N е стойността на LEN, който изпращаме
	cryptoDictPart := dictEntry
	if len(dictEntry) > lenValue {
		cryptoDictPart = dictEntry[:lenValue]
	}
	
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

// Handle the INFO command - placeholder
func (s *TCPServer) handleInfo(conn *TCPConnection, parts []string) (string, error) {
	// Для команды INFO нам нужно:
	// 1. Расшифровать данные от клиента
	// 2. Проанализировать запрос
	// 3. Зашифровать и отправить ответ
	
	if len(parts) < 2 {
		return "ERROR Invalid INFO command", nil
	}
	
	// Извлечь зашифрованные данные
	var encryptedData string
	for _, part := range parts[1:] {
		if strings.HasPrefix(part, "DATA=") {
			encryptedData = part[5:] // все после "DATA="
			break
		}
	}
	
	if encryptedData == "" {
		return "ERROR No DATA parameter in INFO command", nil
	}
	
	log.Printf("Received encrypted data: %s", encryptedData)
	log.Printf("Using crypto key: %s", conn.cryptoKey)
	
	// Пытаемся расшифровать данные
	decryptedData := decompressData(encryptedData, conn.cryptoKey)
	log.Printf("Decrypted data: %s", decryptedData)
	
	// Извлекаем параметры из расшифрованных данных
	params := make(map[string]string)
	if decryptedData != "" {
		lines := strings.Split(decryptedData, "\r\n")
		for _, line := range lines {
			if strings.Contains(line, "=") {
				parts := strings.SplitN(line, "=", 2)
				if len(parts) == 2 {
					params[parts[0]] = parts[1]
				}
			}
		}
		log.Printf("Parsed parameters: %v", params)
	}
	
	// Создаем ответ с указанием, что операция прошла успешно
	// Добавим информацию из запроса
	responseData := "RESP=OK\r\nUSRID=1\r\nINFO=Server received credentials\r\n"
	
	// Если запрос был на подключение/проверку учетных данных
	if _, ok := params["USR"]; ok {
		responseData += "CREDS=VALID\r\n"
		
		// Если в запросе было имя пользователя, вернем его для подтверждения
		if username, ok := params["USR"]; ok {
			responseData += fmt.Sprintf("USR=%s\r\n", username)
		}
	}
	
	// Создаем зашифрованный ответ по тому же формату, который ожидает клиент
	encrypted := compressData(responseData, conn.cryptoKey)
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
	// Ведем подробный лог процесса шифрования для отладки
	log.Printf("Encrypting data: '%s' with key: '%s'", data, key)
	
	// Проверяем длину ключа и дополняем, если короткий (как в Delphi)
	if len(key) >= 1 && len(key) <= 5 {
		key = key + "123456"
		log.Printf("Key is too short, padded to: %s", key)
	}
	
	// 1. Вычисляем SHA1 хеш ключа (именно так делает Delphi в DCPcrypt.pas)
	// Delphi: Hash.Update(Key[1],Length(Key)); Hash.Final(KeyHash^);
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 использует 16 байт
	
	// 2. Сжимаем данные с помощью zlib
	var compressed bytes.Buffer
	w := zlib.NewWriter(&compressed)
	_, err := w.Write([]byte(data))
	if err != nil {
		log.Printf("Error compressing data: %v", err)
		return ""
	}
	err = w.Close()
	if err != nil {
		log.Printf("Error closing zlib writer: %v", err)
		return ""
	}
	
	// 3. Шифруем данные с помощью AES-CBC
	// Создаем блок шифрования
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// Выравниваем данные до размера блока AES (16 байт)
	plaintext := pkcs7Pad(compressed.Bytes(), aes.BlockSize)
	
	// Инициализируем IV (вектор инициализации) нулями, как в Delphi
	iv := make([]byte, aes.BlockSize)
	
	// Создаем шифратор AES-CBC
	mode := cipher.NewCBCEncrypter(block, iv)
	
	// Шифруем данные
	ciphertext := make([]byte, len(plaintext))
	mode.CryptBlocks(ciphertext, plaintext)
	
	// 4. Кодируем в base64
	encoded := base64.StdEncoding.EncodeToString(ciphertext)
	
	// 5. Удаляем '=' в конце (так делает Delphi)
	encoded = strings.TrimRight(encoded, "=")
	
	log.Printf("Encryption steps:")
	log.Printf("1. Original data (%d bytes): %s", len(data), data)
	log.Printf("2. Key SHA1: %x", keyHash)
	log.Printf("3. AES key (16 bytes): %x", aesKey)
	log.Printf("4. Compressed (%d bytes)", compressed.Len())
	log.Printf("5. Padded for AES (%d bytes)", len(plaintext))
	log.Printf("6. Encrypted with AES-CBC (%d bytes)", len(ciphertext))
	log.Printf("7. Base64 encoded (%d bytes): %s", len(encoded), encoded)
	
	return encoded
}

// Helper function to decompress data
func decompressData(data string, key string) string {
	// Ведем подробный лог процесса расшифровки для отладки
	log.Printf("Decrypting data: '%s' with key: '%s'", data, key)
	
	// Проверяем длину ключа и дополняем, если короткий (как в Delphi)
	if len(key) >= 1 && len(key) <= 5 {
		key = key + "123456"
		log.Printf("Key is too short, padded to: %s", key)
	}
	
	// Проверяваме дали низът е валиден Base64 и допълваме с padding, ако е необходимо
	// Delphi Base64 може да не използва padding, затова трябва да го добавим
	inputLength := len(data)
	paddingNeeded := inputLength % 4
	if paddingNeeded > 0 {
		padding := strings.Repeat("=", 4-paddingNeeded)
		data += padding
		log.Printf("Added %d padding chars to Base64, new length: %d", 4-paddingNeeded, len(data))
	}

	// 1. Декодируем base64
	ciphertext, err := base64.StdEncoding.DecodeString(data)
	if err != nil {
		log.Printf("Error decoding base64: %v", err)
		
		// Пробваме алтернативен подход - заместваме символи, които биха могли да причинят грешки
		// Някои Base64 имплементации използват различни символи за '+' и '/'
		altData := strings.ReplaceAll(data, "-", "+")
		altData = strings.ReplaceAll(altData, "_", "/")
		
		ciphertext, err = base64.StdEncoding.DecodeString(altData)
		if err != nil {
			log.Printf("Error decoding base64 (even with substitutions): %v", err)
			return ""
		}
		log.Printf("Succeeded decoding base64 with URL-safe character substitutions")
	}
	
	// 2. Вычисляем SHA1 хеш ключа (именно так делает Delphi в DCPcrypt.pas)
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 использует 16 байт
	
	// 3. Проверяем размер данных и выравниваем до блока AES если необходимо
	blockSize := aes.BlockSize // 16 bytes
	remainder := len(ciphertext) % blockSize
	
	if remainder != 0 {
		log.Printf("Ciphertext size is not a multiple of AES block size: %d bytes (remainder: %d)", 
			len(ciphertext), remainder)
		
		// Добавляем padding до размера блока (в отличие от PKCS7, просто заполняем нулями)
		padding := make([]byte, blockSize-remainder)
		ciphertext = append(ciphertext, padding...)
		log.Printf("Padded ciphertext to %d bytes to make it a multiple of AES block size", len(ciphertext))
	}
	
	// 4. Расшифровываем с помощью AES-CBC
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// Проверяем, что данные имеют допустимый размер для AES
	if len(ciphertext) < aes.BlockSize || len(ciphertext)%aes.BlockSize != 0 {
		log.Printf("Ciphertext size is invalid: %d bytes", len(ciphertext))
		return ""
	}
	
	// Инициализируем IV нулями, как в Delphi
	iv := make([]byte, aes.BlockSize)
	
	// Создаем дешифратор AES-CBC
	mode := cipher.NewCBCDecrypter(block, iv)
	
	// Расшифровываем данные
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)
	
	// 5. Убираем PKCS7 padding - с обработкой ошибок
	unpaddedData, err := pkcs7Unpad(plaintext, aes.BlockSize)
	if err != nil {
		log.Printf("Error removing padding: %v - will try to proceed anyway", err)
		// Пытаемся использовать данные без удаления padding
		unpaddedData = plaintext
	}
	
	// 6. Распаковываем с помощью zlib - с обработкой ошибок
	zlibReader, err := zlib.NewReader(bytes.NewReader(unpaddedData))
	if err != nil {
		log.Printf("Error creating zlib reader: %v - data might not be compressed with zlib", err)
		// Возвращаем данные как есть, без распаковки
		return string(unpaddedData)
	}
	defer zlibReader.Close()
	
	// Читаем распакованные данные
	decompressed, err := io.ReadAll(zlibReader)
	if err != nil {
		log.Printf("Error decompressing data: %v - returning uncompressed data", err)
		return string(unpaddedData)
	}
	
	result := string(decompressed)
	
	log.Printf("Decryption steps:")
	log.Printf("1. Base64 encoded input (%d bytes): %s", inputLength, data[:min(inputLength, 100)]+"...")
	log.Printf("2. Decoded base64 (%d bytes)", len(ciphertext))
	log.Printf("3. Key SHA1: %x", keyHash)
	log.Printf("4. AES key (16 bytes): %x", aesKey)
	log.Printf("5. Decrypted with AES-CBC (%d bytes)", len(plaintext))
	log.Printf("6. Removed padding (%d bytes)", len(unpaddedData))
	if err == nil {
		log.Printf("7. Decompressed (%d bytes): %s", len(decompressed), result)
	} else {
		log.Printf("7. Decompression failed - using raw data (%d bytes): %s", len(unpaddedData), string(unpaddedData))
	}
	
	return result
}

// PKCS7 Padding helper functions
func pkcs7Pad(data []byte, blockSize int) []byte {
	padding := blockSize - len(data)%blockSize
	padtext := bytes.Repeat([]byte{byte(padding)}, padding)
	return append(data, padtext...)
}

func pkcs7Unpad(data []byte, blockSize int) ([]byte, error) {
	length := len(data)
	if length == 0 {
		return nil, fmt.Errorf("empty data")
	}
	if length%blockSize != 0 {
		return nil, fmt.Errorf("data is not block-aligned: %d", length)
	}
	
	padding := int(data[length-1])
	if padding > blockSize || padding == 0 {
		return nil, fmt.Errorf("invalid padding value: %d", padding)
	}
	
	// Check padding
	for i := length - padding; i < length; i++ {
		if int(data[i]) != padding {
			return nil, fmt.Errorf("invalid padding")
		}
	}
	
	return data[:length-padding], nil
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