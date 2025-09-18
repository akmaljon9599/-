# Инструкция по развертыванию системы

## Подготовка сервера

### Требования к серверу
- **ОС**: Ubuntu 20.04+ / CentOS 8+ / Debian 10+
- **PHP**: 7.4 или выше
- **MySQL**: 5.7 или выше
- **Веб-сервер**: Apache 2.4+ или Nginx 1.18+
- **Память**: минимум 2GB RAM
- **Диск**: минимум 10GB свободного места

### Установка зависимостей

#### Ubuntu/Debian
```bash
# Обновляем систему
sudo apt update && sudo apt upgrade -y

# Устанавливаем PHP и расширения
sudo apt install php php-fpm php-mysql php-gd php-curl php-json php-mbstring php-xml php-zip -y

# Устанавливаем MySQL
sudo apt install mysql-server -y

# Устанавливаем Apache
sudo apt install apache2 -y

# Включаем необходимые модули Apache
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo a2enmod deflate
```

#### CentOS/RHEL
```bash
# Обновляем систему
sudo yum update -y

# Устанавливаем репозиторий EPEL и Remi
sudo yum install epel-release -y
sudo yum install https://rpms.remirepo.net/enterprise/remi-release-8.rpm -y

# Включаем PHP 7.4
sudo yum module enable php:remi-7.4 -y

# Устанавливаем PHP и расширения
sudo yum install php php-fpm php-mysql php-gd php-curl php-json php-mbstring php-xml php-zip -y

# Устанавливаем MySQL
sudo yum install mysql-server -y

# Устанавливаем Apache
sudo yum install httpd -y
```

## Настройка базы данных

### 1. Настройка MySQL
```bash
# Запускаем MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Настраиваем безопасность
sudo mysql_secure_installation
```

### 2. Создание базы данных и пользователя
```sql
-- Подключаемся к MySQL как root
mysql -u root -p

-- Создаем базу данных
CREATE DATABASE courier_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создаем пользователя
CREATE USER 'courier_user'@'localhost' IDENTIFIED BY 'secure_password_here';

-- Даем права доступа
GRANT ALL PRIVILEGES ON courier_system.* TO 'courier_user'@'localhost';
FLUSH PRIVILEGES;

-- Выходим
EXIT;
```

### 3. Импорт схемы базы данных
```bash
mysql -u courier_user -p courier_system < database/schema.sql
```

## Развертывание приложения

### 1. Загрузка кода
```bash
# Переходим в директорию веб-сервера
cd /var/www/html

# Клонируем проект (или загружаем архив)
sudo git clone <repository-url> courier-system
# или
sudo unzip courier-system.zip

# Устанавливаем права доступа
sudo chown -R www-data:www-data courier-system/
sudo chmod -R 755 courier-system/
sudo chmod -R 775 courier-system/uploads/
sudo chmod -R 775 courier-system/logs/
```

### 2. Настройка конфигурации
```bash
cd courier-system

# Копируем файл конфигурации
sudo cp .env.example .env

# Редактируем конфигурацию
sudo nano .env
```

Пример настройки `.env`:
```env
# Настройки приложения
APP_DEBUG=false
APP_ENV=production

# Настройки базы данных
DB_HOST=localhost
DB_PORT=3306
DB_NAME=courier_system
DB_USER=courier_user
DB_PASS=secure_password_here

# Настройки безопасности
JWT_SECRET=your-very-secure-secret-key-here

# API ключи
YANDEX_MAPS_API_KEY=your-yandex-maps-api-key
```

### 3. Настройка веб-сервера

#### Apache
```bash
# Создаем виртуальный хост
sudo nano /etc/apache2/sites-available/courier-system.conf
```

Содержимое файла:
```apache
<VirtualHost *:80>
    ServerName courier.yourdomain.com
    DocumentRoot /var/www/html/courier-system
    
    <Directory /var/www/html/courier-system>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Логи
    ErrorLog ${APACHE_LOG_DIR}/courier_error.log
    CustomLog ${APACHE_LOG_DIR}/courier_access.log combined
    
    # Безопасность
    <Files ".env">
        Require all denied
    </Files>
    
    <Files "*.sql">
        Require all denied
    </Files>
</VirtualHost>
```

```bash
# Активируем сайт
sudo a2ensite courier-system.conf
sudo a2dissite 000-default.conf

# Перезапускаем Apache
sudo systemctl restart apache2
```

#### Nginx
```bash
# Создаем конфигурацию сайта
sudo nano /etc/nginx/sites-available/courier-system
```

Содержимое файла:
```nginx
server {
    listen 80;
    server_name courier.yourdomain.com;
    root /var/www/html/courier-system;
    index index.html index.php;

    # Логи
    access_log /var/log/nginx/courier_access.log;
    error_log /var/log/nginx/courier_error.log;

    # Основная локация
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API запросы
    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$query_string;
    }

    # PHP обработка
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Статические файлы
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }

    # Безопасность
    location ~ /\.env {
        deny all;
    }
    
    location ~ \.sql$ {
        deny all;
    }
    
    location /logs/ {
        deny all;
    }
}
```

```bash
# Активируем сайт
sudo ln -s /etc/nginx/sites-available/courier-system /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# Проверяем конфигурацию
sudo nginx -t

# Перезапускаем Nginx
sudo systemctl restart nginx
sudo systemctl restart php7.4-fpm
```

## Настройка SSL (Let's Encrypt)

```bash
# Устанавливаем Certbot
sudo apt install certbot python3-certbot-apache -y  # для Apache
# или
sudo apt install certbot python3-certbot-nginx -y   # для Nginx

# Получаем сертификат
sudo certbot --apache -d courier.yourdomain.com     # для Apache
# или
sudo certbot --nginx -d courier.yourdomain.com      # для Nginx

# Настраиваем автоматическое обновление
sudo crontab -e
# Добавляем строку:
0 12 * * * /usr/bin/certbot renew --quiet
```

## Настройка безопасности

### 1. Настройка файрвола
```bash
# Ubuntu/Debian
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 2. Настройка логирования
```bash
# Создаем директорию для логов
sudo mkdir -p /var/log/courier-system
sudo chown www-data:www-data /var/log/courier-system

# Настраиваем ротацию логов
sudo nano /etc/logrotate.d/courier-system
```

Содержимое файла:
```
/var/www/html/courier-system/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
```

### 3. Настройка мониторинга
```bash
# Устанавливаем инструменты мониторинга
sudo apt install htop iotop nethogs -y

# Настраиваем мониторинг дискового пространства
echo '#!/bin/bash
THRESHOLD=80
USAGE=$(df /var/www/html/courier-system | tail -1 | awk "{print \$5}" | sed "s/%//")
if [ $USAGE -gt $THRESHOLD ]; then
    echo "Disk usage is above $THRESHOLD%: $USAGE%" | mail -s "Disk Space Alert" admin@yourdomain.com
fi' | sudo tee /usr/local/bin/check_disk.sh

sudo chmod +x /usr/local/bin/check_disk.sh

# Добавляем в cron
(crontab -l 2>/dev/null; echo "0 */6 * * * /usr/local/bin/check_disk.sh") | crontab -
```

## Резервное копирование

### 1. Скрипт резервного копирования
```bash
sudo nano /usr/local/bin/backup_courier.sh
```

Содержимое скрипта:
```bash
#!/bin/bash

# Настройки
BACKUP_DIR="/var/backups/courier-system"
DB_NAME="courier_system"
DB_USER="courier_user"
DB_PASS="secure_password_here"
APP_DIR="/var/www/html/courier-system"
DATE=$(date +%Y%m%d_%H%M%S)

# Создаем директорию для бэкапов
mkdir -p $BACKUP_DIR

# Бэкап базы данных
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql

# Бэкап файлов приложения
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz -C $(dirname $APP_DIR) $(basename $APP_DIR)

# Удаляем старые бэкапы (старше 30 дней)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

```bash
# Делаем скрипт исполняемым
sudo chmod +x /usr/local/bin/backup_courier.sh

# Добавляем в cron (ежедневно в 2:00)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/backup_courier.sh") | crontab -
```

### 2. Восстановление из резервной копии
```bash
# Восстановление базы данных
mysql -u courier_user -p courier_system < /var/backups/courier-system/db_backup_YYYYMMDD_HHMMSS.sql

# Восстановление файлов
cd /var/www/html
sudo tar -xzf /var/backups/courier-system/app_backup_YYYYMMDD_HHMMSS.tar.gz
sudo chown -R www-data:www-data courier-system/
```

## Проверка работоспособности

### 1. Проверка веб-сервера
```bash
sudo systemctl status apache2   # или nginx
sudo systemctl status mysql
sudo systemctl status php7.4-fpm
```

### 2. Проверка API
```bash
# Проверяем доступность API
curl -X POST http://courier.yourdomain.com/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

### 3. Проверка логов
```bash
# Логи веб-сервера
sudo tail -f /var/log/apache2/courier_error.log
# или
sudo tail -f /var/log/nginx/courier_error.log

# Логи приложения
sudo tail -f /var/www/html/courier-system/logs/app.log

# Логи MySQL
sudo tail -f /var/log/mysql/error.log
```

## Обновление системы

### 1. Подготовка к обновлению
```bash
# Создаем резервную копию
/usr/local/bin/backup_courier.sh

# Переводим сайт в режим обслуживания
echo '<h1>Сайт временно недоступен</h1><p>Ведутся технические работы</p>' | sudo tee /var/www/html/courier-system/maintenance.html

# Настраиваем редирект на страницу обслуживания
sudo nano /var/www/html/courier-system/.htaccess
# Добавляем в начало файла:
# RewriteRule ^(.*)$ /maintenance.html [R=503,L]
```

### 2. Обновление кода
```bash
cd /var/www/html/courier-system

# Загружаем новую версию
sudo git pull origin main
# или распаковываем новый архив

# Обновляем права доступа
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 uploads/ logs/
```

### 3. Обновление базы данных
```bash
# Применяем миграции (если есть)
mysql -u courier_user -p courier_system < database/migrations/migration_YYYYMMDD.sql
```

### 4. Завершение обновления
```bash
# Убираем режим обслуживания
sudo rm /var/www/html/courier-system/maintenance.html
# Убираем редирект из .htaccess

# Перезапускаем службы
sudo systemctl restart apache2  # или nginx
sudo systemctl restart php7.4-fpm

# Проверяем работоспособность
curl -I http://courier.yourdomain.com
```

## Мониторинг и поддержка

### 1. Скрипт проверки доступности
```bash
sudo nano /usr/local/bin/check_site.sh
```

```bash
#!/bin/bash
URL="http://courier.yourdomain.com"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $URL)

if [ $RESPONSE -ne 200 ]; then
    echo "Site is down! HTTP response: $RESPONSE" | mail -s "Site Down Alert" admin@yourdomain.com
    # Перезапускаем веб-сервер
    sudo systemctl restart apache2
fi
```

```bash
sudo chmod +x /usr/local/bin/check_site.sh
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/check_site.sh") | crontab -
```

### 2. Настройка алертов
```bash
# Устанавливаем mailutils для отправки уведомлений
sudo apt install mailutils -y

# Настраиваем отправку логов критических ошибок
echo '#!/bin/bash
ERROR_LOG="/var/log/apache2/courier_error.log"
LAST_CHECK="/tmp/last_error_check"

if [ ! -f $LAST_CHECK ]; then
    touch $LAST_CHECK
fi

NEW_ERRORS=$(find $ERROR_LOG -newer $LAST_CHECK)
if [ -n "$NEW_ERRORS" ]; then
    tail -n 50 $ERROR_LOG | mail -s "New Errors in Courier System" admin@yourdomain.com
fi

touch $LAST_CHECK' | sudo tee /usr/local/bin/check_errors.sh

sudo chmod +x /usr/local/bin/check_errors.sh
(crontab -l 2>/dev/null; echo "*/10 * * * * /usr/local/bin/check_errors.sh") | crontab -
```

## Контакты технической поддержки

При возникновении проблем обращайтесь к администратору системы или в службу технической поддержки.