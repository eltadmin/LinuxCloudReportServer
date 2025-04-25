#!/bin/bash
set -e

echo "Поправка на дублирана функция min в go_tcp_server.go"

# Създаваме резервно копие на оригиналния файл
cp go_tcp_server.go go_tcp_server.go.bak

# Коментираме дублираната функция min (около ред 2985)
sed -i '2985,2990s/^/\/\/ DUPLICATE: /' go_tcp_server.go

echo "Готово! Дублираната функция min беше коментирана."
echo "Сега може да изпълните './start-reportcom.sh build' отново." 