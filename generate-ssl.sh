#!/bin/bash

# Создание директории для SSL сертификатов
mkdir -p ssl

# Генерация самоподписанного сертификата для разработки
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout ssl/key.pem \
    -out ssl/cert.pem \
    -subj "/C=RU/ST=Moscow/L=Moscow/O=CourierSystem/OU=IT/CN=localhost"

echo "SSL сертификаты созданы в директории ssl/"
echo "Для продакшена используйте сертификаты от Let's Encrypt или другого CA"