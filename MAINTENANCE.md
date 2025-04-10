# Поддръжка на EBO Cloud Report Server

Този документ съдържа инструкции за поддръжка и отстраняване на проблеми с EBO Cloud Report Server.

## Ежедневни операции

### Проверка на състоянието
```bash
# Проверка на всички контейнери
docker-compose ps

# Проверка на логовете
docker-compose logs --tail=100

# Проверка на логовете на конкретен контейнер
docker-compose logs -f report-server
docker-compose logs -f web-interface
docker-compose logs -f db
```

### Рестартиране на услугите
```bash
# Рестартиране на всички контейнери
docker-compose restart

# Рестартиране на отделен контейнер
docker-compose restart report-server
docker-compose restart web-interface
docker-compose restart db
```

## Обновяване на система

### Обновяване на конфигурацията
Ако промените конфигурационни файлове, можете да ги приложите без рестартиране на цялата система:

```bash
# Обновяване на nginx конфигурация
./update-configs.sh

# Обновяване на MySQL конфигурация
docker cp my.cnf ebo-report-db:/etc/mysql/conf.d/
docker-compose restart db
```

### Обновяване на кода на сървъра
Ако актуализирате кода на сървъра, трябва да построите отново контейнера:

```bash
# Обновяване на контейнера на report-server
docker-compose build report-server
docker-compose up -d report-server
```

## Резервни копия

### Създаване на резервно копие на базата данни
```bash
# Създаване на резервно копие на базата данни
docker exec ebo-report-db sh -c 'exec mysqldump -u dreports -p"ftUk58_HoRs3sAzz8jk" dreports' > backup-$(date +%Y%m%d).sql
```

### Възстановяване от резервно копие
```bash
# Спиране на контейнерите
docker-compose down

# Изтриване на volume с данните
docker volume rm linuxcloudreportserver_mysql-data

# Стартиране на системата с ново резервно копие
cp your-backup.sql dreports\(8\).sql
docker-compose up -d
```

## Отстраняване на проблеми

### Проблеми с Web интерфейса
Ако уеб интерфейсът не работи:
```bash
# Проверка на логовете
docker-compose logs -f web-interface

# Рестартиране на web-interface контейнера
./restart-web.sh
```

### Проблеми с Report Server
Ако TCP или HTTP сървърът не работи:
```bash
# Проверка на логовете
docker-compose logs -f report-server

# Рестартиране на report-server контейнера
docker-compose restart report-server
```

### Проблеми с базата данни
Ако базата данни не стартира или не е достъпна:
```bash
# Проверка на логовете
docker-compose logs -f db

# Проверка на статуса
docker exec ebo-report-db mysqladmin -u dreports -p"ftUk58_HoRs3sAzz8jk" status

# Рестартиране на db контейнера
docker-compose restart db
```

## Наблюдение на системата

### Наблюдение на ресурсите
```bash
# Проверка на използваните ресурси
docker stats

# Проверка на дисковото пространство
df -h
```

### Проверка на свързаните клиенти
```bash
# Проверка на TCP връзките към сървъра
netstat -anp | grep 8016

# Проверка на HTTP връзките
netstat -anp | grep 8080

# Проверка на връзките към уеб интерфейса
netstat -anp | grep 8015
``` 