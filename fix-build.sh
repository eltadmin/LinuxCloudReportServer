#!/bin/bash
set -e

echo "Поправка на проблем с дублирана функция min за ReportCom сървъра"

# Създаваме резервни копия
cp go_tcp_server.go go_tcp_server.go.bak
cp Dockerfile Dockerfile.bak

echo "Премахване на дублираната функция min..."
sed -i '2985,2990s/^/\/\/ DUPLICATE: /' go_tcp_server.go

echo "Проверка на поправката..."
grep -n "DUPLICATE" go_tcp_server.go

echo "Файлът go_tcp_server.go е коригиран."
echo "Сега може да изпълните './start-reportcom.sh build' отново."
echo "Ако все още имате проблеми, използвайте Dockerfile с вградена поправка:"
echo "cp fixed-dockerfile Dockerfile" 