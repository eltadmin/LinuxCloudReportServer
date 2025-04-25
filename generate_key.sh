#!/bin/bash

# ServerKeyGen Tool for ReportCom

# Проверяваме дали е подаден сериен номер
if [ -z "$1" ]; then
  # Генерираме машинно-специфичен ID
  SERIAL_NUMBER=$(cat /etc/machine-id 2>/dev/null || hostname | md5sum | cut -d' ' -f1 | tr -d '\n')
  echo "Използване на автоматично генериран сериен номер: $SERIAL_NUMBER"
else
  SERIAL_NUMBER=$1
  echo "Използване на подаден сериен номер: $SERIAL_NUMBER"
fi

# Копилираме и изпълняваме keygen.go
go build -o keygen keygen.go && ./keygen "$SERIAL_NUMBER"

# Създаваме директория за config ако не съществува
mkdir -p config

# Обновяваме config.ini с новия ключ и сериен номер
KEY=$(./keygen "$SERIAL_NUMBER" | grep "Generated Key:" | cut -d':' -f2 | tr -d ' \n')

# Функция за промяна на стойност в ini файл
update_ini() {
  FILE=$1
  SECTION=$2
  KEY=$3
  VALUE=$4
  
  if [ -f "$FILE" ]; then
    # Проверка дали секцията съществува
    if grep -q "^\[$SECTION\]" "$FILE"; then
      # Проверка дали ключът съществува в секцията
      if grep -q "^$KEY=" "$FILE" -A1 -B100 | grep -q "^\[$SECTION\]"; then
        # Обновяваме стойността
        sed -i -e "/^\[$SECTION\]/,/^\[/ s/^$KEY=.*/$KEY=$VALUE/" "$FILE"
      else
        # Добавяме ключа в секцията
        sed -i -e "/^\[$SECTION\]/a $KEY=$VALUE" "$FILE"
      fi
    else
      # Добавяме секцията и ключа
      echo -e "\n[$SECTION]\n$KEY=$VALUE" >> "$FILE"
    fi
  else
    # Създаваме нов файл
    echo -e "[$SECTION]\n$KEY=$VALUE" > "$FILE"
  fi
}

# Обновяваме config.ini
update_ini "config.ini" "REGISTRATION INFO" "SERIAL NUMBER" "$SERIAL_NUMBER"
update_ini "config.ini" "REGISTRATION INFO" "KEY" "$KEY"

echo "Конфигурационният файл config.ini е обновен с новия ключ!"
echo "За да използвате генерирания ключ в Docker, изпълнете:"
echo "docker-compose build --no-cache"
echo "docker-compose up -d reportcom-server" 