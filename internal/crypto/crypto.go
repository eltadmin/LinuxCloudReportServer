package crypto

import (
	"bytes"
	"compress/zlib"
	"crypto/aes"
	"crypto/cipher"
	"crypto/md5"
	"encoding/base64"
	"fmt"
	"io"
	"strings"
)

// CryptoDictionary contains predefined crypto dictionary entries
var CryptoDictionary = []string{
	"",                // index 0 is empty, dictionary is 1-based
	"123hk12h8dcal",  // 1
	"FT676Ugug6sFa",  // 2
	"a6xbBa7A8a9la",  // 3
	"qMnxbtyTFvcqi",  // 4
	"cx7812vcxFRCC",  // 5
	"bab7u682ftysv",  // 6
	"YGbsux&Ygsyxg",  // 7
	"MSN><hu8asG&&",  // 8
	"23yY88syHXvvs",  // 9
	"987sX&sysy891",  // 10
}

// DataCompressor handles the encryption and decryption of data
type DataCompressor struct {
	CryptoKey string
	LastError string
}

// NewDataCompressor creates a new data compressor with the specified crypto key
func NewDataCompressor(cryptoKey string) *DataCompressor {
	return &DataCompressor{
		CryptoKey: cryptoKey,
	}
}

// CompressData compresses and encrypts the input data using AES with the crypto key
func (dc *DataCompressor) CompressData(data string) (string, error) {
	if data == "" {
		dc.LastError = "Input data is empty"
		return "", fmt.Errorf(dc.LastError)
	}

	// 1. Compress the data using zlib
	var compressedBuffer bytes.Buffer
	zlibWriter, err := zlib.NewWriterLevel(&compressedBuffer, zlib.BestCompression)
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to create zlib writer: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	_, err = zlibWriter.Write([]byte(data))
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to compress data: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	err = zlibWriter.Close()
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to close zlib writer: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	compressedData := compressedBuffer.Bytes()

	// 2. Encrypt the compressed data using AES (Rijndael) in CBC mode
	// Hash the key with MD5 (compatible with Delphi's DCPcrypt)
	hasher := md5.New()
	hasher.Write([]byte(dc.CryptoKey))
	md5Key := hasher.Sum(nil)

	// Create AES cipher with MD5 hash of key
	block, err := aes.NewCipher(md5Key)
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to create AES cipher: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	// Ensure data is a multiple of the block size
	paddingNeeded := aes.BlockSize - (len(compressedData) % aes.BlockSize)
	if paddingNeeded < aes.BlockSize {
		// Add PKCS#7 padding
		paddingBytes := bytes.Repeat([]byte{byte(paddingNeeded)}, paddingNeeded)
		compressedData = append(compressedData, paddingBytes...)
	}

	// Use zero IV (16 bytes of zeros) as per original implementation
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCEncrypter(block, iv)

	// Encrypt data in-place
	encryptedData := make([]byte, len(compressedData))
	mode.CryptBlocks(encryptedData, compressedData)

	// 3. Base64 encode the encrypted data
	base64Data := base64.StdEncoding.EncodeToString(encryptedData)

	// Remove padding characters as per original implementation
	base64Data = strings.TrimRight(base64Data, "=")

	return base64Data, nil
}

// DecompressData decrypts and decompresses the input data using the crypto key
func (dc *DataCompressor) DecompressData(data string) (string, error) {
	if data == "" {
		dc.LastError = "Input data is empty"
		return "", fmt.Errorf(dc.LastError)
	}

	// 1. Restore Base64 padding if needed
	paddingNeeded := len(data) % 4
	if paddingNeeded > 0 {
		data += strings.Repeat("=", 4-paddingNeeded)
	}

	// 2. Base64 decode the data
	encryptedData, err := base64.StdEncoding.DecodeString(data)
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to decode Base64 data: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	// Check if data length is valid for AES decryption
	if len(encryptedData)%aes.BlockSize != 0 {
		dc.LastError = fmt.Sprintf("Invalid data length for AES decryption: %d", len(encryptedData))
		return "", fmt.Errorf(dc.LastError)
	}

	// 3. Decrypt the data using AES (Rijndael) in CBC mode
	// Hash the key with MD5 (compatible with Delphi's DCPcrypt)
	hasher := md5.New()
	hasher.Write([]byte(dc.CryptoKey))
	md5Key := hasher.Sum(nil)

	// Create AES cipher with MD5 hash of key
	block, err := aes.NewCipher(md5Key)
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to create AES cipher: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	// Use zero IV (16 bytes of zeros) as per original implementation
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCDecrypter(block, iv)

	// Decrypt data in-place
	decryptedData := make([]byte, len(encryptedData))
	mode.CryptBlocks(decryptedData, encryptedData)

	// 4. Remove PKCS#7 padding
	paddingLen := int(decryptedData[len(decryptedData)-1])
	if paddingLen > 0 && paddingLen <= aes.BlockSize {
		decryptedData = decryptedData[:len(decryptedData)-paddingLen]
	}

	// 5. Decompress the data using zlib
	zlibReader, err := zlib.NewReader(bytes.NewReader(decryptedData))
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to create zlib reader: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}
	defer zlibReader.Close()

	decompressedData, err := io.ReadAll(zlibReader)
	if err != nil {
		dc.LastError = fmt.Sprintf("Failed to decompress data: %v", err)
		return "", fmt.Errorf(dc.LastError)
	}

	return string(decompressedData), nil
}

// GenerateCryptoKey generates a crypto key based on server key, dictionary entry, and hostname
func GenerateCryptoKey(serverKey string, keyID int, length int, hostname string) string {
	// Check validity of keyID
	if keyID < 1 || keyID > len(CryptoDictionary)-1 {
		return ""
	}

	// Extract parts from hostname
	var hostFirstChars, hostLastChar string
	if len(hostname) > 0 {
		if len(hostname) >= 2 {
			hostFirstChars = hostname[:2]
		} else {
			hostFirstChars = hostname
		}
		hostLastChar = string(hostname[len(hostname)-1])
	}

	// Get dictionary part based on length
	dictEntry := CryptoDictionary[keyID]
	dictPart := ""
	if len(dictEntry) >= length {
		dictPart = dictEntry[:length]
	} else {
		dictPart = dictEntry
	}

	// Combine all parts to form the crypto key
	return serverKey + dictPart + hostFirstChars + hostLastChar
}

// ValidateKey checks if a registration key is valid for the given serial number
func ValidateKey(serialNumber, key string) bool {
	// Base64 decode the key
	keyBytes, err := base64.StdEncoding.DecodeString(key)
	if err != nil {
		return false
	}

	// Hash the serial number to create AES key
	hasher := md5.New()
	hasher.Write([]byte(serialNumber))
	md5Key := hasher.Sum(nil)

	// Create AES cipher
	block, err := aes.NewCipher(md5Key)
	if err != nil {
		return false
	}

	// Use zero IV (16 bytes of zeros)
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCDecrypter(block, iv)

	// Ensure data is a multiple of the block size
	if len(keyBytes)%aes.BlockSize != 0 {
		return false
	}

	// Decrypt data in-place
	decryptedData := make([]byte, len(keyBytes))
	mode.CryptBlocks(decryptedData, keyBytes)

	// Check if the decrypted data is the expected string
	return string(decryptedData) == "ElCloudRepSrv"
}

// GenerateKey generates a new registration key for the given serial number
func GenerateKey(serialNumber string) (string, error) {
	// The string to encrypt
	const elCloudRepSrv = "ElCloudRepSrv"

	// Hash the serial number to create AES key
	hasher := md5.New()
	hasher.Write([]byte(serialNumber))
	md5Key := hasher.Sum(nil)

	// Create AES cipher
	block, err := aes.NewCipher(md5Key)
	if err != nil {
		return "", fmt.Errorf("failed to create AES cipher: %v", err)
	}

	// Ensure data is a multiple of the block size
	paddingNeeded := aes.BlockSize - (len(elCloudRepSrv) % aes.BlockSize)
	if paddingNeeded < aes.BlockSize {
		// Add PKCS#7 padding
		paddingBytes := bytes.Repeat([]byte{byte(paddingNeeded)}, paddingNeeded)
		elCloudRepSrv += string(paddingBytes)
	}

	// Use zero IV (16 bytes of zeros)
	iv := make([]byte, aes.BlockSize)
	mode := cipher.NewCBCEncrypter(block, iv)

	// Encrypt data
	encryptedData := make([]byte, len(elCloudRepSrv))
	mode.CryptBlocks(encryptedData, []byte(elCloudRepSrv))

	// Base64 encode the encrypted data
	return base64.StdEncoding.EncodeToString(encryptedData), nil
} 