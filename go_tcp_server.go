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
	// Parse command parts
	if len(parts) < 2 {
		return "ERROR Too few parameters", nil
	}
	
	params := parseParameters(parts[1:])
	
	// Извличане на необходимите параметри
	idValue, ok := params["ID"]
	if !ok {
		return "ERROR Missing ID parameter", nil
	}
	
	hostname, ok := params["HST"]
	if !ok {
		hostname = "UNKNOWN"
	}
	
	// Запазване на клиентската информация
	conn.clientID = idValue
	conn.clientHost = hostname
	
	appType, ok := params["ATP"]
	if ok {
		conn.appType = appType
	}
	
	appVersion, ok := params["AVR"]
	if ok {
		conn.appVersion = appVersion
	}
	
	// Извеждане на подробна информация за отстраняване на грешки
	log.Printf("Handling INIT command with parts: %v", parts)
	log.Printf("Extracted params: %v", params)
	log.Printf("Client hostname: %s, ID: %s", hostname, idValue)
	
	// Генериране на сървърски ключ - консистентен за всички клиенти
	// Server key - винаги същия за всички клиенти с ID=6
	serverKey := "F156"
	// Стойността на LEN винаги е 2 за този ключ
	lenValue := 2
	
	conn.serverKey = serverKey
	conn.keyLength = lenValue
	
	// Извличане на речника за криптиране
	dictIndex := 1 // Default value if ID parsing fails
	if id, err := strconv.Atoi(idValue); err == nil {
		dictIndex = id
		
		// Проверка на индекса
		if dictIndex < 1 {
			dictIndex = 1
		} else if dictIndex > len(CRYPTO_DICTIONARY) {
			dictIndex = dictIndex % len(CRYPTO_DICTIONARY)
			if dictIndex == 0 { 
				dictIndex = 1
			}
		}
	}
	
	// Записваме елемента от речника за референция
	dictEntry := CRYPTO_DICTIONARY[dictIndex-1]
	
	// Използваме стойността на ID директно като част от ключа
	cryptoDictPart := idValue
	
	// Извличане на частите от имената на хоста
	hostFirstChars := ""
	hostLastChar := ""
	if len(hostname) >= 2 {
		hostFirstChars = hostname[:2]
	}
	if len(hostname) > 0 {
		hostLastChar = hostname[len(hostname)-1:]
	}
	
	// Създаване на криптиращия ключ точно както в оригиналния Delphi код
	cryptoKey := serverKey + cryptoDictPart + hostFirstChars + hostLastChar
	conn.cryptoKey = cryptoKey
	
	// Формат на отговора точно както в оригиналния сървър
	response := fmt.Sprintf("200-KEY=%s\r\n200 LEN=%d\r\n", serverKey, lenValue)
	
	// Диагностична информация
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
	
	// Добавяне на допълнителна диагностика за дебъг
	log.Printf("====== SENDING INIT RESPONSE ======")
	log.Printf("Raw response: '%s'", response)
	log.Printf("Response bytes (hex): % x", []byte(response))
	log.Printf("Response length: %d bytes", len(response))
	log.Printf("INIT response sent successfully")
	log.Printf("==================================")
	
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
	// Подробен лог на процеса за дебъг цели
	log.Printf("Encrypting data: '%s' with key: '%s'", data, key)
	
	// Проверка за къс ключ и допълване както в Delphi
	if len(key) >= 1 && len(key) <= 5 {
		key = key + "123456"
		log.Printf("Key is too short, padded to: %s", key)
	}
	
	// 1. Изчисляване на SHA1 хеш на ключа (точно както DCPcrypt.pas в Delphi)
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 ключ
	
	// 2. Компресиране с zlib - точно както в Delphi
	var compressed bytes.Buffer
	w, err := zlib.NewWriterLevel(&compressed, zlib.BestCompression)
	if err != nil {
		log.Printf("Error creating zlib writer: %v", err)
		return ""
	}
	
	_, err = w.Write([]byte(data))
	if err != nil {
		log.Printf("Error compressing data: %v", err)
		w.Close()
		return ""
	}
	
	err = w.Close()
	if err != nil {
		log.Printf("Error closing zlib writer: %v", err)
		return ""
	}
	
	// 3. Шифроване с AES-CBC с PKCS7 padding
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// PKCS7 padding до размера на AES блок
	compressedData := compressed.Bytes()
	padSize := aes.BlockSize - (len(compressedData) % aes.BlockSize)
	if padSize == 0 {
		padSize = aes.BlockSize
	}
	
	padding := bytes.Repeat([]byte{byte(padSize)}, padSize)
	paddedData := append(compressedData, padding...)
	
	// Нулев IV вектор, както в Delphi
	iv := make([]byte, aes.BlockSize)
	
	// Шифроване с AES-CBC
	ciphertext := make([]byte, len(paddedData))
	mode := cipher.NewCBCEncrypter(block, iv)
	mode.CryptBlocks(ciphertext, paddedData)
	
	// 4. Base64 кодиране
	encoded := base64.StdEncoding.EncodeToString(ciphertext)
	
	// Delphi премахва '=' в края
	encoded = strings.TrimRight(encoded, "=")
	
	// Подробен лог за дебъг
	log.Printf("Encryption steps:")
	log.Printf("1. Original data (%d bytes): %s", len(data), data)
	log.Printf("2. Key SHA1: %x", keyHash)
	log.Printf("3. AES key (16 bytes): %x", aesKey)
	log.Printf("4. Compressed (%d bytes)", compressed.Len())
	log.Printf("5. Padded (%d bytes), padding size: %d", len(paddedData), padSize)
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
	
	// Base64 декодиране - опит с различни методи за по-голяма съвместимост
	var ciphertext []byte
	var err error
	var successMethod string
	
	// Метод 1: Стандартен Base64 със добавен padding ако е нужно
	paddedData := data
	for i := 0; i < 4; i++ {
		ciphertext, err = base64.StdEncoding.DecodeString(paddedData)
		if err == nil {
			successMethod = "Standard Base64 with padding"
			break
		}
		paddedData += "="
	}
	
	// Метод 2: URL Safe Base64 ако стандартният не работи
	if err != nil {
		altData := strings.ReplaceAll(data, "-", "+")
		altData = strings.ReplaceAll(altData, "_", "/")
		
		for i := 0; i < 4; i++ {
			ciphertext, err = base64.StdEncoding.DecodeString(altData)
			if err == nil {
				successMethod = "URL-safe Base64"
				break
			}
			altData += "="
		}
	}
	
	// Метод 3: Премахване на нестандартни символи
	if err != nil {
		cleaned := ""
		for _, c := range data {
			if (c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') || c == '+' || c == '/' {
				cleaned += string(c)
			}
		}
		
		// Добавяне на padding
		for i := 0; i < 4; i++ {
			ciphertext, err = base64.StdEncoding.DecodeString(cleaned)
			if err == nil {
				successMethod = "Cleaned non-standard chars"
				break
			}
			cleaned += "="
		}
	}
	
	// Ако всички опити са неуспешни, връщаме грешка
	if err != nil {
		log.Printf("Failed to decode Base64 data: %v", err)
		return ""
	}
	
	log.Printf("Successfully decoded Base64 using method: %s", successMethod)
	
	// Изчисляваме SHA1 хеш на ключа за AES-128
	h := sha1.New()
	h.Write([]byte(key))
	keyHash := h.Sum(nil)
	aesKey := keyHash[:16] // AES-128 използва 16 байта
	
	// Проверка за размера на блока AES
	blockSize := aes.BlockSize // 16 bytes
	if len(ciphertext) % blockSize != 0 {
		padding := make([]byte, blockSize - (len(ciphertext) % blockSize))
		ciphertext = append(ciphertext, padding...)
		log.Printf("Added %d bytes padding to make data AES block size aligned", len(padding))
	}
	
	// Инициализация на AES-CBC режим с нулев IV (като в Delphi)
	block, err := aes.NewCipher(aesKey)
	if err != nil {
		log.Printf("Error creating AES cipher: %v", err)
		return ""
	}
	
	// Създаваме дешифратор
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCDecrypter(block, iv)
	
	// Дешифрираме данните
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)
	
	// Намаляване на риска от грешки чрез по-внимателно премахване на padding
	var unpaddedData []byte
	paddingLen := int(plaintext[len(plaintext)-1])
	if paddingLen > 0 && paddingLen <= aes.BlockSize {
		validPadding := true
		for i := len(plaintext) - paddingLen; i < len(plaintext); i++ {
			if int(plaintext[i]) != paddingLen {
				validPadding = false
				break
			}
		}
		
		if validPadding {
			unpaddedData = plaintext[:len(plaintext)-paddingLen]
			log.Printf("Successfully removed %d bytes of padding", paddingLen)
		} else {
			log.Printf("Invalid padding detected, using full data")
			unpaddedData = plaintext
		}
	} else {
		log.Printf("Unusual padding value (%d), using full data", paddingLen)
		unpaddedData = plaintext
	}
	
	// Опит за Zlib декомпресия - с елегантна обработка на грешки
	var decompressed []byte
	zlibReader, err := zlib.NewReader(bytes.NewReader(unpaddedData))
	if err == nil {
		decompressed, err = io.ReadAll(zlibReader)
		zlibReader.Close()
		if err == nil {
			log.Printf("Successfully decompressed data with zlib")
			return string(decompressed)
		}
		log.Printf("Error during zlib decompression: %v", err)
	} else {
		log.Printf("Data is not zlib compressed: %v", err)
	}
	
	// Опит за четене като обикновен текст, ако не е zlib компресиран
	// Търсим валидни ASCII/UTF-8 символи
	result := ""
	for _, b := range unpaddedData {
		if b >= 32 && b <= 126 {
			result += string(b)
		}
	}
	
	if len(result) > 0 {
		log.Printf("Extracted %d printable ASCII characters", len(result))
		return result
	}
	
	// Последен опит - връщаме данните като hex string
	log.Printf("Unable to decode data, returning raw bytes")
	return hex.EncodeToString(unpaddedData)
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