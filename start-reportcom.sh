#!/bin/bash
set -e

# Цветове за терминалния изход
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}ReportCom Server - Контролен скрипт${NC}"
echo

# Функция за показване на помощна информация
function show_help {
  echo -e "Употреба: $0 [команда]"
  echo
  echo -e "Команди:"
  echo -e "  ${GREEN}build${NC}       Изграждане на Docker образ"
  echo -e "  ${GREEN}up${NC}          Стартиране на контейнера"
  echo -e "  ${GREEN}down${NC}        Спиране на контейнера"
  echo -e "  ${GREEN}logs${NC}        Показване на логове"
  echo -e "  ${GREEN}rebuild${NC}     Повторно изграждане без кеш"
  echo -e "  ${GREEN}stop${NC}        Спиране на контейнера"
  echo -e "  ${GREEN}status${NC}      Проверка на статуса"
  echo -e "  ${GREEN}help${NC}        Тази помощна информация"
  echo
}

# Създаваме необходимите директории ако не съществуват
mkdir -p config logs updates

# Обработка на командите
case "$1" in
  build)
    echo -e "${YELLOW}Изграждане на Docker образ...${NC}"
    docker-compose build reportcom-server
    ;;
  up)
    echo -e "${YELLOW}Стартиране на контейнера...${NC}"
    docker-compose up -d reportcom-server
    echo -e "${GREEN}Контейнерът е стартиран. Използвайте '$0 logs' за да видите логовете.${NC}"
    ;;
  down|stop)
    echo -e "${YELLOW}Спиране на контейнера...${NC}"
    docker-compose down reportcom-server
    ;;
  logs)
    echo -e "${YELLOW}Показване на логове...${NC}"
    docker-compose logs -f reportcom-server
    ;;
  rebuild)
    echo -e "${YELLOW}Повторно изграждане без кеш...${NC}"
    docker-compose build --no-cache reportcom-server
    ;;
  status)
    echo -e "${YELLOW}Проверка на статуса...${NC}"
    docker-compose ps reportcom-server
    ;;
  help|*)
    show_help
    ;;
esac 