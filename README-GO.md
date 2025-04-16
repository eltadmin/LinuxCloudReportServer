# Go TCP Server для LinuxCloudReportServer

Это улучшенная реализация TCP сервера на Go, которая заменяет оригинальный Python TCP сервер.

## Особенности

- Улучшенная обработка криптографических ключей (исправлена проблема с ID=9)
- Поддержка всех команд оригинального сервера
- Корректное форматирование ответов для совместимости с Delphi клиентом
- SQLite база данных для хранения словарей
- Подробное логирование для упрощения отладки

## Зависимости

- Go 1.21 или новее
- SQLite

## Сборка и запуск

### Локальный запуск

1. Установите Go (https://go.dev/doc/install)
2. Клонируйте репозиторий
3. Перейдите в директорию LinuxCloudReportServer
4. Инициализируйте модули Go:
   ```bash
   go mod tidy
   ```
5. Соберите и запустите сервер:
   ```bash
   go build -o tcp_server go_tcp_server.go
   ./tcp_server
   ```

### Docker

Для запуска в Docker:

```bash
# Только Go TCP сервер
docker-compose -f docker-compose.go.yml build --no-cache go_tcp_server
docker-compose -f docker-compose.go.yml up -d go_tcp_server

# В составе полного стека
docker-compose build --no-cache go_tcp_server
docker-compose up -d
```

## Переменные окружения

- `TCP_HOST` - хост для прослушивания (по умолчанию "0.0.0.0")
- `TCP_PORT` - порт для прослушивания (по умолчанию "8016")
- `DB_PATH` - путь к файлу SQLite базы данных (по умолчанию "./dictionary.db")
- `DEBUG_MODE` - включение режима отладки (по умолчанию "true")

## Устранение проблем

### Проблема с SQLite

Если при сборке возникает ошибка с SQLite (`undefined: db`), убедитесь, что:

1. Установлены необходимые пакеты разработки:
   ```bash
   sudo apt-get install gcc libc6-dev
   ```

2. Импортирован драйвер SQLite:
   ```go
   import _ "github.com/mattn/go-sqlite3"
   ```

3. Проверьте, что база данных создается при первом запуске.

### Криптографические ключи

Для клиентов с ID=9 используется жёстко закодированный ключ `D5F22NE-`, что обеспечивает 
совместимость с Delphi клиентом. 