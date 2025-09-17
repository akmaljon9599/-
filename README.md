# Система управления курьерскими заявками

Полнофункциональная система для управления заявками на доставку банковских карт с разграничением прав доступа по ролям пользователей.

## 🚀 Возможности системы

### Роли пользователей
- **Администратор** - полный доступ ко всем функциям
- **Старший курьер** - управление заявками своего филиала, назначение курьеров
- **Курьер** - просмотр назначенных заявок, изменение статусов, загрузка фото
- **Оператор** - добавление и редактирование заявок, выгрузка данных

### Основной функционал
- ✅ Управление заявками (создание, редактирование, изменение статусов)
- ✅ Система ролей и прав доступа
- ✅ Отслеживание местоположения курьеров в реальном времени
- ✅ Загрузка и управление файлами (фото доставки, документы)
- ✅ Фильтрация и поиск заявок
- ✅ Дашборд с аналитикой
- ✅ Управление пользователями и настройками системы
- ✅ Адаптивный дизайн для всех устройств

## 🛠 Технологический стек

### Backend
- **Node.js** с Express.js
- **MySQL** база данных
- **JWT** аутентификация
- **Multer** для загрузки файлов
- **Joi** валидация данных

### Frontend
- **HTML5, CSS3, JavaScript**
- **Bootstrap 5** для UI
- **FontAwesome** иконки
- **Vanilla JavaScript** (без фреймворков)

### Интеграции
- **Яндекс.Карты** (готово к интеграции)
- **АБС банка** (готово к интеграции)
- **Bitrix24** (опционально)

## 📋 Требования

- Node.js 16+ 
- MySQL 8.0+
- npm или yarn

## 🚀 Установка и запуск

### 1. Клонирование и установка зависимостей

```bash
# Установка зависимостей
npm install
```

### 2. Настройка базы данных

```bash
# Создание базы данных
mysql -u root -p < database/schema.sql

# Заполнение тестовыми данными
mysql -u root -p < database/seed.sql
```

### 3. Настройка переменных окружения

```bash
# Копирование файла конфигурации
cp .env.example .env

# Редактирование настроек
nano .env
```

Обязательно настройте:
- `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` - настройки MySQL
- `JWT_SECRET` - секретный ключ для JWT токенов
- `PORT` - порт сервера (по умолчанию 3000)

### 4. Запуск сервера

```bash
# Режим разработки
npm run dev

# Продакшн режим
npm start
```

### 5. Открытие приложения

Откройте браузер и перейдите по адресу: `http://localhost:3000`

## 👥 Тестовые пользователи

После заполнения базы данных тестовыми данными доступны следующие пользователи:

| Роль | Логин | Пароль | Описание |
|------|-------|--------|----------|
| Администратор | admin | password123 | Полный доступ |
| Старший курьер | senior1 | password123 | Управление филиалом |
| Курьер | courier1 | password123 | Доставка карт |
| Оператор | operator1 | password123 | Создание заявок |

## 📁 Структура проекта

```
├── backend/                 # Backend приложение
│   ├── src/
│   │   ├── controllers/     # Контроллеры
│   │   ├── models/         # Модели данных
│   │   ├── routes/         # API маршруты
│   │   ├── middleware/     # Middleware функции
│   │   ├── services/       # Бизнес-логика
│   │   └── utils/          # Утилиты
│   └── config/             # Конфигурация
├── frontend/               # Frontend приложение
│   ├── src/
│   │   ├── services/       # API сервисы
│   │   ├── components/     # Компоненты
│   │   └── utils/          # Утилиты
│   └── public/             # Статические файлы
├── database/               # База данных
│   ├── schema.sql          # Схема БД
│   └── seed.sql           # Тестовые данные
└── docs/                  # Документация
```

## 🔧 API Endpoints

### Аутентификация
- `POST /api/auth/login` - Вход в систему
- `POST /api/auth/logout` - Выход из системы
- `GET /api/auth/me` - Информация о текущем пользователе

### Заявки
- `GET /api/requests` - Список заявок с фильтрацией
- `POST /api/requests` - Создание заявки
- `GET /api/requests/:id` - Получение заявки
- `PUT /api/requests/:id` - Обновление заявки
- `PUT /api/requests/:id/status` - Изменение статуса
- `PUT /api/requests/:id/assign-courier` - Назначение курьера

### Файлы
- `POST /api/files/upload` - Загрузка файлов
- `GET /api/files/:requestId` - Список файлов заявки
- `GET /api/files/download/:fileId` - Скачивание файла
- `DELETE /api/files/:fileId` - Удаление файла

### Местоположение
- `POST /api/location/update` - Обновление местоположения
- `GET /api/location/couriers` - Местоположение курьеров
- `GET /api/location/history/:courierId` - История курьера

### Дашборд
- `GET /api/dashboard/stats` - Статистика
- `GET /api/dashboard/recent-activity` - Последняя активность
- `GET /api/dashboard/performance` - Показатели эффективности

## 🔒 Безопасность

- JWT токены для аутентификации
- Хеширование паролей с bcrypt
- Валидация всех входных данных
- Разграничение прав доступа по ролям
- Rate limiting для API
- CORS настройки

## 📱 Адаптивность

Система полностью адаптивна и работает на:
- 🖥️ Десктопах
- 📱 Планшетах  
- 📱 Мобильных устройствах

## 🚀 Развертывание в продакшн

### 1. Подготовка сервера

```bash
# Установка Node.js и MySQL
sudo apt update
sudo apt install nodejs npm mysql-server

# Создание пользователя для приложения
sudo mysql -e "CREATE USER 'courier_app'@'localhost' IDENTIFIED BY 'secure_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON courier_system.* TO 'courier_app'@'localhost';"
```

### 2. Настройка Nginx (опционально)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

### 3. Настройка PM2 для автозапуска

```bash
# Установка PM2
npm install -g pm2

# Запуск приложения
pm2 start backend/src/app.js --name "courier-system"

# Сохранение конфигурации
pm2 save
pm2 startup
```

## 🔧 Настройка интеграций

### Яндекс.Карты
1. Получите API ключ на [developer.tech.yandex.ru](https://developer.tech.yandex.ru)
2. Добавьте ключ в `.env`: `YANDEX_MAPS_API_KEY=your_key`
3. Раскомментируйте код интеграции в `frontend/src/services/location.js`

### АБС банка
1. Получите доступ к API банка
2. Настройте переменные в `.env`:
   ```
   ABS_BANK_API_URL=https://api.bank.com
   ABS_BANK_API_KEY=your_api_key
   ```

## 🐛 Отладка

### Логи
```bash
# Просмотр логов PM2
pm2 logs courier-system

# Логи в режиме разработки
npm run dev
```

### Проверка базы данных
```bash
# Подключение к MySQL
mysql -u courier_app -p courier_system

# Проверка таблиц
SHOW TABLES;
```

## 📞 Поддержка

При возникновении проблем:

1. Проверьте логи приложения
2. Убедитесь в правильности настроек `.env`
3. Проверьте подключение к базе данных
4. Обратитесь к документации API

## 📄 Лицензия

MIT License - см. файл LICENSE для деталей.

---

**Система готова к использованию!** 🎉

Для начала работы войдите в систему с тестовыми данными и изучите функционал.