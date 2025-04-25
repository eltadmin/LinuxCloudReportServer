#!/bin/bash
set -e

# Цветове за терминалния изход
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Поправка на дублирана функция min в go_tcp_server.go${NC}"

# Търсим втората дефиниция на функцията min
LINE_NUM=$(grep -n "^func min(a, b int) int {$" go_tcp_server.go | tail -n 1 | cut -d: -f1)

if [ -z "$LINE_NUM" ]; then
    echo -e "${RED}Грешка: Не можах да намеря втората дефиниция на функцията min.${NC}"
    exit 1
fi

# Създаваме резервно копие на оригиналния файл
cp go_tcp_server.go go_tcp_server.go.bak

# Номера на редовете за премахване (функцията min обикновено е 5 реда)
START_LINE=$LINE_NUM
END_LINE=$((LINE_NUM + 5))

echo -e "Премахвам дублираната функция min от редове $START_LINE до $END_LINE..."

# Използваме sed за да коментираме редовете с дублираната функция
sed -i "${START_LINE},${END_LINE}s/^/\/\/ COMMENTED OUT: /" go_tcp_server.go

echo -e "${GREEN}Готово! Дублираната функция min беше коментирана.${NC}"
echo -e "Създадено е резервно копие в go_tcp_server.go.bak" 