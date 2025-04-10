#!/bin/bash

echo "Стартиране на EBO Cloud Report Server..."

# Спиране на съществуващи контейнери (ако има)
docker-compose down

# Стартиране на контейнерите
docker-compose up -d

echo ""
echo "Системата е стартирана!"
echo "Достъпни са следните услуги:"
echo "- Уеб интерфейс: http://localhost/dreport/"
echo "- Report Server API: http://localhost:8080"
echo "- TCP Server: localhost:8016"
echo ""
echo "За преглед на логовете използвайте:"
echo "docker-compose logs -f"
echo ""
echo "За спиране на услугите:"
echo "docker-compose down" 