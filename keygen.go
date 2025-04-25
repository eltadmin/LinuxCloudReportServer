package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/md5"
	"encoding/base64"
	"fmt"
	"os"
)

// AES-CFB mode encryption-decryption functions (similar to Delphi DCPcrypt Rijndael)
func createHash(key string) []byte {
	hasher := md5.New()
	hasher.Write([]byte(key))
	return hasher.Sum(nil)
}

func encrypt(data []byte, passphrase string) []byte {
	block, _ := aes.NewCipher(createHash(passphrase))
	cfb := cipher.NewCFBEncrypter(block, []byte{0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0})
	ciphertext := make([]byte, len(data))
	cfb.XORKeyStream(ciphertext, data)
	return ciphertext
}

func decrypt(data []byte, passphrase string) []byte {
	block, _ := aes.NewCipher(createHash(passphrase))
	cfb := cipher.NewCFBDecrypter(block, []byte{0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0})
	plaintext := make([]byte, len(data))
	cfb.XORKeyStream(plaintext, data)
	return plaintext
}

func main() {
	if len(os.Args) < 2 {
		fmt.Println("Usage: keygen.go <serial_number>")
		fmt.Println("Example: keygen.go 141298787")
		os.Exit(1)
	}

	serialNumber := os.Args[1]
	data := []byte("ElCloudRepSrv")

	// Encrypt
	encryptedData := encrypt(data, serialNumber)
	encodedKey := base64.StdEncoding.EncodeToString(encryptedData)
	fmt.Println("Generated Key:", encodedKey)

	// Verify
	decodedData, _ := base64.StdEncoding.DecodeString(encodedKey)
	decryptedData := decrypt(decodedData, serialNumber)
	fmt.Printf("Verification: %s", decryptedData)
	if string(decryptedData) == "ElCloudRepSrv" {
		fmt.Println(" (OK)")
	} else {
		fmt.Println(" (Failed)")
	}
} 