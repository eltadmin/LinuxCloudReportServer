# Linux Cloud Report Server

Linux-съвместима версия на EBO Cloud Report Server, която предоставя TCP/IP сървър за комуникация с касови апарати EBO.

## Функционалности

- Съвместимост с оригиналния Windows TCP сървър
- Поддръжка на всички команди: INIT, INFO, PING, AUTH, SEND, UPDATE, GET
- HTTP REST API за интеграция с уеб интерфейс
- Съхранение на данни в MySQL база данни
- Пълна контейнеризация с Docker

## Инсталация

### С Docker (препоръчително)

1. Уверете се, че имате инсталиран Docker и docker-compose

2. Стартирайте сървъра с командата:
   ```
   docker-compose up -d
   ```

3. Сървърът ще бъде достъпен на:
   - TCP порт: 8016
   - HTTP API: http://localhost:8080
   - Уеб интерфейс: http://localhost/dreport/

### Конфигурация

Основните настройки се съдържат в `eboCloudReportServer.ini`:

```ini
[SRV_1_COMMON]
TraceLogEnabled=1
UpdateFolder=Updates

[SRV_1_HTTP]
HTTP_IPInterface=0.0.0.0
HTTP_Port=8080

[SRV_1_TCP]
TCP_IPInterface=0.0.0.0
TCP_Port=8016

[SRV_1_AUTHSERVER]
REST_URL=http://localhost/dreport/api.php
```

## Използване

Сървърът приема следните TCP команди:

- `INIT` - Инициализиране на връзката
- `INFO` - Информация за сървъра
- `PING` - Проверка на връзката
- `AUTH deviceId objectId password` - Автентикация на устройство
- `SEND data` - Изпращане на данни
- `UPDATE` - Проверка за обновления
- `GET` - Получаване на данни
- `EXIT` - Затваряне на връзката

## Поддръжка

За въпроси и проблеми, моля свържете се с ekassa.bg
