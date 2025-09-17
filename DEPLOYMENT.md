# Инструкция по развертыванию модуля "Курьерская служба"

## Требования к системе

### Минимальные требования
- **Bitrix24** версии 20.0.0 или выше
- **PHP** версии 7.4 или выше
- **MySQL** версии 5.7 или выше
- **Apache/Nginx** с поддержкой mod_rewrite
- **SSL сертификат** (рекомендуется)

### Рекомендуемые требования
- **PHP** версии 8.0 или выше
- **MySQL** версии 8.0 или выше
- **Redis** для кеширования (опционально)
- **Elasticsearch** для поиска (опционально)

## Подготовка к установке

### 1. Создание резервной копии
```bash
# Создание бэкапа базы данных
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Создание бэкапа файлов
tar -czf files_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/bitrix/
```

### 2. Проверка прав доступа
```bash
# Установка прав на папки
chmod -R 755 /path/to/bitrix/modules/
chmod -R 777 /path/to/bitrix/upload/
chmod -R 777 /path/to/bitrix/cache/
```

### 3. Настройка PHP
Убедитесь, что в `php.ini` включены следующие расширения:
```ini
extension=mysqli
extension=gd
extension=mbstring
extension=curl
extension=json
extension=zip
```

## Установка модуля

### 1. Загрузка файлов
```bash
# Копирование модуля в директорию Bitrix
cp -r courier_service /path/to/bitrix/modules/

# Установка прав доступа
chmod -R 755 /path/to/bitrix/modules/courier_service/
```

### 2. Установка через админку
1. Войдите в админку Bitrix24
2. Перейдите: **Настройки** → **Управление структурой** → **Модули**
3. Найдите модуль "Курьерская служба"
4. Нажмите **"Установить"**
5. Дождитесь завершения установки

### 3. Проверка установки
После установки проверьте:
- Создание таблиц в базе данных
- Создание групп пользователей
- Установку прав доступа
- Создание директорий для загрузки файлов

## Настройка модуля

### 1. Базовые настройки
Перейдите в **Настройки** → **Настройки продукта** → **Настройки модулей** → **Курьерская служба**

#### Обязательные настройки:
- **API ключ Яндекс.Карт** - получите на https://developer.tech.yandex.ru/
- **URL АБС банка** - адрес API шлюза банка
- **API ключ АБС** - ключ для доступа к API банка
- **Интервал обновления местоположения** (по умолчанию: 60 секунд)

#### Дополнительные настройки:
- **Максимальное расстояние для обновления** (по умолчанию: 100 метров)
- **Время хранения логов** (по умолчанию: 90 дней)
- **Размер загружаемых файлов** (по умолчанию: 10MB)

### 2. Настройка пользователей
1. Перейдите в **Пользователи** → **Группы пользователей**
2. Найдите созданные группы:
   - `COURIER_ADMIN` - Администраторы курьерской службы
   - `COURIER_SENIOR` - Старшие курьеры
   - `COURIER_DELIVERY` - Курьеры
   - `COURIER_OPERATOR` - Операторы курьерской службы
3. Назначьте пользователей в соответствующие группы

### 3. Создание филиалов и подразделений
1. Перейдите в **Курьерская служба** → **Филиалы**
2. Создайте необходимые филиалы
3. Для каждого филиала создайте подразделения

## Настройка интеграций

### 1. Интеграция с Яндекс.Картами
```php
// Получение API ключа
$apiKey = \CourierService\Main\SettingTable::get('yandex_maps_api_key');

// Инициализация карты
$yandexMaps = new \CourierService\Api\YandexMaps();
$mapScript = $yandexMaps->getMapInitScript('map-container', [
    'center' => [55.7558, 37.6176],
    'zoom' => 10
]);
```

### 2. Интеграция с АБС банка
```php
// Настройка подключения
$absGateway = new \CourierService\Api\AbsGateway();

// Проверка соединения
if ($absGateway->checkConnection()) {
    echo "Соединение с АБС установлено";
} else {
    echo "Ошибка подключения к АБС";
}
```

## Настройка безопасности

### 1. SSL сертификат
Убедитесь, что сайт работает по HTTPS:
```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2. Настройка файрвола
```bash
# Разрешение доступа только к необходимым портам
ufw allow 80
ufw allow 443
ufw allow 22
ufw enable
```

### 3. Настройка прав доступа к файлам
```bash
# Установка правильных прав
find /path/to/bitrix/upload/courier_service/ -type f -exec chmod 644 {} \;
find /path/to/bitrix/upload/courier_service/ -type d -exec chmod 755 {} \;
```

## Мониторинг и обслуживание

### 1. Настройка логирования
```php
// Включение детального логирования
\CourierService\Main\SettingTable::set('debug_mode', 'Y');
\CourierService\Main\SettingTable::set('log_level', 'debug');
```

### 2. Мониторинг производительности
```sql
-- Проверка размера таблиц
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'your_database'
AND table_name LIKE 'courier_%'
ORDER BY (data_length + index_length) DESC;
```

### 3. Очистка старых данных
```php
// Автоматическая очистка логов старше 90 дней
\CourierService\Security\AuditLogger::cleanOldLogs(90);
```

## Резервное копирование

### 1. Автоматическое резервное копирование
Создайте скрипт для ежедневного бэкапа:
```bash
#!/bin/bash
# backup_courier_service.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/courier_service"

# Создание директории для бэкапа
mkdir -p $BACKUP_DIR

# Бэкап базы данных
mysqldump -u username -p database_name > $BACKUP_DIR/courier_db_$DATE.sql

# Бэкап файлов модуля
tar -czf $BACKUP_DIR/courier_files_$DATE.tar.gz /path/to/bitrix/modules/courier_service/

# Бэкап загруженных файлов
tar -czf $BACKUP_DIR/courier_upload_$DATE.tar.gz /path/to/bitrix/upload/courier_service/

# Удаление старых бэкапов (старше 30 дней)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 2. Настройка cron для автоматического бэкапа
```bash
# Добавление в crontab
crontab -e

# Ежедневный бэкап в 2:00
0 2 * * * /path/to/backup_courier_service.sh
```

## Обновление модуля

### 1. Подготовка к обновлению
```bash
# Создание бэкапа текущей версии
cp -r /path/to/bitrix/modules/courier_service /path/to/backup/courier_service_old
```

### 2. Установка новой версии
```bash
# Остановка сервисов (если необходимо)
systemctl stop apache2

# Замена файлов модуля
rm -rf /path/to/bitrix/modules/courier_service
cp -r courier_service_new /path/to/bitrix/modules/courier_service

# Установка прав доступа
chmod -R 755 /path/to/bitrix/modules/courier_service/

# Запуск сервисов
systemctl start apache2
```

### 3. Обновление базы данных
```php
// Выполнение миграций (если необходимо)
// Код миграций будет добавлен в будущих версиях
```

## Устранение неполадок

### 1. Проблемы с установкой
```bash
# Проверка логов ошибок
tail -f /var/log/apache2/error.log
tail -f /path/to/bitrix/logs/error.log

# Проверка прав доступа
ls -la /path/to/bitrix/modules/courier_service/
```

### 2. Проблемы с производительностью
```sql
-- Проверка медленных запросов
SHOW PROCESSLIST;

-- Анализ индексов
EXPLAIN SELECT * FROM courier_requests WHERE status = 'waiting';
```

### 3. Проблемы с интеграциями
```php
// Проверка API ключей
$yandexApiKey = \CourierService\Main\SettingTable::get('yandex_maps_api_key');
$absApiKey = \CourierService\Main\SettingTable::get('abs_api_key');

// Тестирование подключений
$yandexMaps = new \CourierService\Api\YandexMaps();
$absGateway = new \CourierService\Api\AbsGateway();
```

## Контакты поддержки

При возникновении проблем:
1. Проверьте логи системы
2. Обратитесь к документации
3. Свяжитесь с технической поддержкой

**Email:** support@example.com  
**Телефон:** +7 (XXX) XXX-XX-XX  
**Время работы:** Пн-Пт, 9:00-18:00 (МСК)

---

**Версия документации:** 1.0.0  
**Дата обновления:** 2024-01-15