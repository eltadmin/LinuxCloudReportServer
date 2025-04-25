#!/bin/bash
set -e

# Цветове за терминалния изход
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}ReportCom Server - Верификация на Docker образ${NC}"
echo

# Проверка дали Docker е инсталиран
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Грешка: Docker не е инсталиран на тази система.${NC}"
    echo "Моля, инсталирайте Docker преди да продължите."
    exit 1
fi

# Проверка дали docker-compose е инсталиран
if ! command -v docker-compose &> /dev/null; then
    echo -e "${YELLOW}Предупреждение: docker-compose не е инсталиран. Ще използваме само docker.${NC}"
    USE_COMPOSE=false
else
    USE_COMPOSE=true
fi

# Проверка дали образът съществува
if [ "$USE_COMPOSE" = true ]; then
    echo -e "${YELLOW}Проверка на Docker образа със docker-compose...${NC}"
    IMAGES=$(docker-compose -f reportcom-compose.yml images -q)
    if [ -z "$IMAGES" ]; then
        echo -e "${RED}Грешка: Docker образът не е изграден.${NC}"
        echo "Изпълнете './start-reportcom.sh build' за да изградите образа."
        exit 1
    else
        echo -e "${GREEN}Docker образът е изграден успешно!${NC}"
    fi
else
    echo -e "${YELLOW}Проверка на Docker образа с docker...${NC}"
    IMAGE_ID=$(docker images -q reportcom-server:latest)
    if [ -z "$IMAGE_ID" ]; then
        echo -e "${RED}Грешка: Docker образът не е изграден.${NC}"
        echo "Изпълнете 'docker build -t reportcom-server:latest .' за да изградите образа."
        exit 1
    else
        echo -e "${GREEN}Docker образът е изграден успешно!${NC}"
    fi
fi

# Проверка дали директорията config съществува
if [ ! -d "config" ]; then
    echo -e "${RED}Грешка: Директорията 'config' не съществува.${NC}"
    echo "Създайте директория 'config' със следната команда:"
    echo "mkdir -p config"
    exit 1
fi

# Проверка дали конфигурационният файл съществува
if [ ! -f "config.ini" ]; then
    echo -e "${RED}Грешка: Конфигурационният файл 'config.ini' не съществува.${NC}"
    exit 1
fi

# Проверка дали необходимите директории съществуват
for dir in logs updates keys; do
    if [ ! -d "$dir" ]; then
        echo -e "${YELLOW}Предупреждение: Директорията '$dir' не съществува. Ще бъде създадена при стартиране.${NC}"
    fi
done

echo -e "${GREEN}Всички проверки са успешни!${NC}"
echo -e "Можете да стартирате сървъра със следната команда:"
if [ "$USE_COMPOSE" = true ]; then
    echo -e "${YELLOW}./start-reportcom.sh up${NC}"
else
    echo -e "${YELLOW}docker run -d --name reportcom-server -p 8016:8016 -p 8080:8080 -v $(pwd)/config:/app/config -v $(pwd)/logs:/app/logs -v $(pwd)/updates:/app/updates -v $(pwd)/keys:/app/keys reportcom-server:latest${NC}"
fi 