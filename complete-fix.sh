#!/bin/bash

# Цветове за изход
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Започва инсталация на EBO Cloud Report Server...${NC}"

# Проверка за Docker и Docker Compose
echo -e "${YELLOW}Проверка за Docker...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker не е инсталиран. Моля, инсталирайте Docker първо.${NC}"
    exit 1
fi

echo -e "${YELLOW}Проверка за Docker Compose...${NC}"
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Docker Compose не е инсталиран. Моля, инсталирайте Docker Compose първо.${NC}"
    exit 1
fi

# Гарантиране че сме в правилната директория
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

echo -e "${YELLOW}Създаване на конфигурационните файлове...${NC}"

# Създаване на директории, ако не съществуват
mkdir -p logs Updates

# Проверка дали SQL файлът съществува
if [ ! -f "../dreports(8).sql" ]; then
    echo -e "${RED}SQL файлът на базата данни не е намерен: ../dreports(8).sql${NC}"
    echo -e "${YELLOW}Проверка в текущата директория...${NC}"
    
    if [ -f "dreports(8).sql" ]; then
        echo -e "${GREEN}SQL файл намерен в текущата директория.${NC}"
    else
        echo -e "${RED}Моля, уверете се, че файлът съществува и е на правилното място.${NC}"
        exit 1
    fi
fi

# Проверка за директорията dreport
if [ ! -d "dreport" ]; then
    echo -e "${RED}Директорията dreport не е намерена.${NC}"
    echo -e "${RED}Тази директория е необходима за уеб интерфейса.${NC}"
    exit 1
fi

# Правим всички скриптове изпълними
chmod +x *.sh

echo -e "${YELLOW}Изграждане и стартиране на Docker контейнерите...${NC}"
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Изчакване контейнерите да стартират
echo -e "${YELLOW}Изчакване услугите да стартират...${NC}"
sleep 15

# Проверка дали уеб интерфейсът е достъпен
echo -e "${YELLOW}Проверка на уеб интерфейса...${NC}"
if curl -s -f http://localhost/dreport/ > /dev/null; then
    echo -e "${GREEN}Уеб интерфейсът е достъпен на http://localhost/dreport/${NC}"
else
    echo -e "${RED}Уеб интерфейсът не е достъпен. Проверете логовете за грешки.${NC}"
    echo -e "${YELLOW}Стартиране на допълнителна диагностика...${NC}"
    
    echo -e "${YELLOW}Проверка на NGINX конфигурацията в контейнера:${NC}"
    docker exec ebo-web-interface cat /etc/nginx/sites-enabled/default
    
    echo -e "${YELLOW}Проверка на логовете на уеб интерфейса:${NC}"
    docker logs ebo-web-interface | tail -n 20
fi

# Проверка дали API на report server-а е достъпно
echo -e "${YELLOW}Проверка на report server API...${NC}"
if curl -s -f http://localhost:8080/health > /dev/null; then
    echo -e "${GREEN}Report server API е достъпно на http://localhost:8080${NC}"
else
    echo -e "${RED}Report server API не е достъпно. Проверете логовете за грешки.${NC}"
    echo -e "${YELLOW}Логове на report-server:${NC}"
    docker logs ebo-cloud-report-server | tail -n 20
fi

echo -e "${GREEN}Конфигурацията е завършена!${NC}"
echo -e "${GREEN}Услуги:${NC}"
echo -e "${GREEN}- Уеб интерфейс: http://localhost/dreport/${NC}"
echo -e "${GREEN}- Report Server API: http://localhost:8080${NC}"
echo -e "${GREEN}- TCP Server: localhost:8016${NC}"
echo -e "${GREEN}- MySQL база данни: localhost:3306${NC}"

echo -e "${YELLOW}За преглед на логовете:${NC}"
echo -e "${YELLOW}docker-compose logs -f${NC}"

echo -e "${YELLOW}За спиране на услугите:${NC}"
echo -e "${YELLOW}docker-compose down${NC}"

echo -e "${GREEN}Инсталацията е завършена успешно!${NC}" 