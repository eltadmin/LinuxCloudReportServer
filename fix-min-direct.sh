#!/bin/bash
set -e

# Цветове за терминалния изход
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Поправка на дублирана функция min в go_tcp_server.go${NC}"

# Създаваме резервно копие на оригиналния файл
cp go_tcp_server.go go_tcp_server.go.bak

# Използваме временен файл за редактиране
echo -e "Редактирам файла go_tcp_server.go..."

cat > fix_min.go << EOF
package main

/*
IMPROVED TCP SERVER FOR LINUX CLOUD REPORT

Key fixes implemented:
1. INIT Response Format - Ensured exact format matching with "200-KEY=xxx\r\n200 LEN=y\r\n"
2. Crypto Key Generation - Fixed special handling for ID=9 with hardcoded key "D5F22NE-"
3. INFO Command Response - Added proper formatting with ID, expiry date, and validation fields
4. MD5 Hashing - Used MD5 instead of SHA1 for AES key generation to match Delphi's DCPcrypt
5. Base64 Handling - Improved padding handling for Base64 encoding/decoding
6. Enhanced Logging - Added detailed logging at each step of encryption/decryption
7. Validation - Added encryption validation testing with sample data
8. Min Function - Fixed duplicate declaration of min function (removed second instance)

This improved server should correctly handle authentication with the Delphi client.
*/

// Взимаме определени части от оригиналния код
// Import section, constants, etc.

// Функция min - да се уверим, че имаме само една нейна дефиниция
func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// Останалият код без дублираната min функция
EOF

echo -e "${GREEN}Успешно създаден файл fix_min.go с коригирана функция min.${NC}"
echo -e "${YELLOW}ВАЖНО: Този файл е само примерен шаблон. Трябва да поправите ръчно go_tcp_server.go,${NC}"
echo -e "${YELLOW}като изтриете или коментирате втората дефиниция на функцията min около ред 2985.${NC}"
echo -e "Създадено е резервно копие в go_tcp_server.go.bak"
echo -e "${GREEN}Команда за директна поправка:${NC}"
echo -e "sed -i '2985,2990s/^/\\/\\/ COMMENTED: /' go_tcp_server.go" 