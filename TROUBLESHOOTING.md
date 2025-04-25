# ReportCom Server - Отстраняване на проблеми

Това ръководство описва често срещани проблеми при инсталацията и стартирането на ReportCom сървъра и как да ги разрешите.

## Проблем с липсващ go.mod файл

**Проблем**: При изграждане на Docker образа получавате грешка от типа:
```
failed to solve: failed to compute cache key: failed to calculate checksum of ref xxx::yyyy: "/go.mod": not found
```

**Решение**: 
Последната версия на Dockerfile автоматично създава go.mod файла по време на изграждането. Уверете се, че използвате последната версия на Dockerfile.

## Проблем с достъпа до файлове

**Проблем**: Docker контейнерът няма достъп до конфигурационните файлове или директориите.

**Решение**:
1. Уверете се, че директориите `config`, `logs`, `updates` и `keys` съществуват и имат правилните права за достъп.
2. Изпълнете следните команди:
```bash
mkdir -p config logs updates keys
chmod -R 755 config logs updates keys
```

## Проблем със стартирането на контейнера

**Проблем**: Контейнерът се изгражда успешно, но не се стартира или се рестартира непрекъснато.

**Решение**:
1. Проверете логовете с командата:
```bash
docker logs reportcom-server
```
2. Уверете се, че `config.ini` файлът е правилно конфигуриран.
3. Проверете дали портовете 8016 и 8080 не се използват от други услуги.

## Проблем с Docker Compose

**Проблем**: Командата `docker-compose` връща грешка или не е налична.

**Решение**:
1. Уверете се, че Docker Compose е инсталиран:
```bash
docker-compose --version
```
2. Ако не е инсталиран, инсталирайте го:
```bash
# За Ubuntu/Debian
sudo apt-get update
sudo apt-get install docker-compose

# За CentOS/RHEL
sudo yum install docker-compose
```
3. Алтернативно, можете да използвате Docker директно:
```bash
docker build -t reportcom-server:latest .
docker run -d --name reportcom-server -p 8016:8016 -p 8080:8080 -v $(pwd)/config:/app/config -v $(pwd)/logs:/app/logs -v $(pwd)/updates:/app/updates -v $(pwd)/keys:/app/keys reportcom-server:latest
```

## Проблем с Docker Engine

**Проблем**: Docker командите връщат грешки или Docker не е стартиран.

**Решение**:
1. Проверете дали Docker е стартиран:
```bash
sudo systemctl status docker
```
2. Ако не е стартиран, стартирайте го:
```bash
sudo systemctl start docker
```
3. Уверете се, че текущият потребител е в Docker групата:
```bash
sudo usermod -aG docker $USER
newgrp docker
```

## Проблем с мрежовата конфигурация

**Проблем**: Клиентите не могат да се свържат с ReportCom сървъра.

**Решение**:
1. Уверете се, че портовете 8016 и 8080 са достъпни отвън (ако използвате защитна стена):
```bash
# За UFW (Ubuntu)
sudo ufw allow 8016/tcp
sudo ufw allow 8080/tcp

# За firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-port=8016/tcp
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```
2. Проверете дали контейнерът е стартиран и слуша на правилните портове:
```bash
docker ps
```
3. Тествайте връзката локално:
```bash
telnet localhost 8016
```

## Проблем с конфигурационния файл

**Проблем**: Сървърът не може да зареди конфигурационния файл или връща грешки за липсващи настройки.

**Решение**:
1. Уверете се, че `config.ini` файлът съществува в директорията `config/`.
2. Проверете дали файлът има правилния формат и съдържание:
```bash
cat config/config.ini
```
3. Рестартирайте контейнера след промяна на конфигурацията:
```bash
./start-reportcom.sh down
./start-reportcom.sh up
```

## Проверка на инсталацията

За да проверите текущото състояние на инсталацията, можете да използвате скрипта `verify-build.sh`:
```bash
chmod +x verify-build.sh
./verify-build.sh
``` 