#!/bin/bash

echo "Стартиране на EBO Cloud Report Server..."

# Спиране на съществуващи контейнери (ако има)
docker-compose down

# Стартиране на контейнерите
docker-compose up -d

echo ""
echo "Системата е стартирана успешно!"
echo "Достъпни са следните услуги:"
echo "- TCP сървър: порт 8016"
echo "- HTTP API: http://localhost:8080"
echo "- Уеб интерфейс: http://localhost/dreport/"
echo ""
echo "За преглед на логовете използвайте:"
echo "docker-compose logs -f"
echo ""
echo "За спиране на услугите:"
echo "docker-compose down" 