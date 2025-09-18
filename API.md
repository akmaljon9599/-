# API Документация

## Базовый URL
```
http://localhost:3000/api
```

## Аутентификация

Все API запросы (кроме аутентификации) требуют JWT токен в заголовке:
```
Authorization: Bearer <token>
```

## Общие ответы

### Успешный ответ
```json
{
  "success": true,
  "message": "Описание операции",
  "data": { ... }
}
```

### Ошибка
```json
{
  "success": false,
  "message": "Описание ошибки",
  "errors": [ ... ]
}
```

## Аутентификация

### POST /auth/login
Вход в систему

**Тело запроса:**
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**Ответ:**
```json
{
  "success": true,
  "message": "Успешный вход в систему",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@bank.ru",
      "firstName": "Админ",
      "lastName": "Админов",
      "role": {
        "id": 1,
        "name": "admin",
        "permissions": { "all": true }
      },
      "branch": {
        "id": 1,
        "name": "Центральный"
      }
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }
}
```

### POST /auth/logout
Выход из системы

**Ответ:**
```json
{
  "success": true,
  "message": "Успешный выход из системы"
}
```

### GET /auth/me
Получение информации о текущем пользователе

**Ответ:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@bank.ru",
    "firstName": "Админ",
    "lastName": "Админов",
    "role": {
      "id": 1,
      "name": "admin",
      "permissions": { "all": true }
    }
  }
}
```

## Заявки

### GET /requests
Получение списка заявок с фильтрацией

**Параметры запроса:**
- `page` - номер страницы (по умолчанию 1)
- `limit` - количество записей на странице (по умолчанию 20)
- `status_id` - фильтр по статусу
- `branch_id` - фильтр по филиалу
- `department_id` - фильтр по подразделению
- `courier_id` - фильтр по курьеру
- `date_from` - дата от (YYYY-MM-DD)
- `date_to` - дата до (YYYY-MM-DD)
- `client_name` - поиск по ФИО клиента
- `client_phone` - поиск по телефону клиента

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "request_number": "REQ-001",
      "abs_id": "ABS10001",
      "client_name": "Петров Иван Сергеевич",
      "client_phone": "+7 (900) 123-45-67",
      "delivery_address": "ул. Пушкина, д. 15, кв. 10",
      "status_name": "Ожидает доставки",
      "courier_name": "Иванов А.",
      "branch_name": "Центральный",
      "registration_date": "2024-01-15T14:30:00.000Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 127,
    "pages": 7
  }
}
```

### POST /requests
Создание новой заявки

**Тело запроса:**
```json
{
  "client_name": "Петров Иван Сергеевич",
  "client_phone": "+7 (900) 123-45-67",
  "pan": "1234567890123456",
  "delivery_address": "ул. Пушкина, д. 15, кв. 10",
  "card_type_id": 1,
  "branch_id": 1,
  "department_id": 1,
  "notes": "Дополнительная информация"
}
```

**Ответ:**
```json
{
  "success": true,
  "message": "Заявка успешно создана",
  "data": {
    "id": 123,
    "request_number": "REQ-123"
  }
}
```

### GET /requests/:id
Получение заявки по ID

**Ответ:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "request_number": "REQ-001",
    "client_name": "Петров Иван Сергеевич",
    "client_phone": "+7 (900) 123-45-67",
    "delivery_address": "ул. Пушкина, д. 15, кв. 10",
    "status_name": "Ожидает доставки",
    "files": [
      {
        "id": 1,
        "file_name": "delivery_photo_1.jpg",
        "file_category": "delivery_photo",
        "created_at": "2024-01-15T15:00:00.000Z"
      }
    ]
  }
}
```

### PUT /requests/:id
Обновление заявки

**Тело запроса:**
```json
{
  "client_name": "Петров Иван Сергеевич",
  "delivery_address": "ул. Пушкина, д. 15, кв. 10 (обновленный адрес)",
  "notes": "Обновленная информация"
}
```

### POST /requests/:id/status
Изменение статуса заявки

**Тело запроса:**
```json
{
  "status_id": 3,
  "courier_phone": "+7 (900) 123-45-67"
}
```

Для статуса "Отказано" (status_id: 4):
```json
{
  "status_id": 4,
  "rejection_reason": "Клиент не был дома в указанное время. Повторная попытка доставки не удалась."
}
```

## Курьеры

### GET /couriers
Получение списка курьеров

**Параметры запроса:**
- `branch_id` - фильтр по филиалу
- `department_id` - фильтр по подразделению

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "first_name": "Иван",
      "last_name": "Иванов",
      "phone": "+7 (495) 000-00-03",
      "branch_name": "Центральный",
      "department_name": "Подразделение 1",
      "current_activity": "on_delivery",
      "last_location": {
        "latitude": 55.7558,
        "longitude": 37.6176,
        "timestamp": "2024-01-15T16:30:00.000Z",
        "address": "ул. Тверская, д. 1"
      }
    }
  ]
}
```

### GET /couriers/:id
Получение информации о курьере

**Ответ:**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "first_name": "Иван",
    "last_name": "Иванов",
    "phone": "+7 (495) 000-00-03",
    "branch_name": "Центральный",
    "last_location": {
      "latitude": 55.7558,
      "longitude": 37.6176,
      "timestamp": "2024-01-15T16:30:00.000Z"
    },
    "current_activity": {
      "activity_type": "on_delivery",
      "start_time": "2024-01-15T15:00:00.000Z"
    },
    "request_stats": {
      "total_requests": 25,
      "delivered": 20,
      "rejected": 3,
      "in_progress": 2
    }
  }
}
```

### GET /couriers/:id/locations
Получение истории местоположений курьера

**Параметры запроса:**
- `date_from` - дата от (YYYY-MM-DD)
- `date_to` - дата до (YYYY-MM-DD)
- `limit` - количество записей (по умолчанию 100)

### GET /couriers/realtime/locations
Получение курьеров в реальном времени для карты

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "first_name": "Иван",
      "last_name": "Иванов",
      "branch_name": "Центральный",
      "latitude": 55.7558,
      "longitude": 37.6176,
      "last_update": "2024-01-15T16:30:00.000Z",
      "current_activity": "on_delivery"
    }
  ]
}
```

## Файлы

### POST /files/upload
Загрузка файлов

**Тело запроса (multipart/form-data):**
- `files` - массив файлов
- `request_id` - ID заявки
- `file_category` - категория файла (delivery_photo, passport_scan, contract, signature, other)

**Ответ:**
```json
{
  "success": true,
  "message": "Успешно загружено 2 файлов",
  "data": [
    {
      "id": 1,
      "original_name": "delivery_photo_1.jpg",
      "file_name": "delivery_photo-1642248000000-123456789.jpg",
      "file_type": "image/jpeg",
      "file_size": 1024000,
      "file_category": "delivery_photo"
    }
  ]
}
```

### GET /files/request/:request_id
Получение списка файлов заявки

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "file_name": "delivery_photo_1.jpg",
      "file_category": "delivery_photo",
      "file_size": 1024000,
      "uploaded_by_name": "Иванов И.А.",
      "created_at": "2024-01-15T15:00:00.000Z"
    }
  ]
}
```

### GET /files/:id
Получение файла (скачивание)

### DELETE /files/:id
Удаление файла

## Отчеты

### GET /reports/requests/export
Экспорт заявок в Excel

**Параметры запроса:** (те же, что и для GET /requests)

**Ответ:** Excel файл (.xlsx)

### GET /reports/couriers/performance
Отчет по производительности курьеров

**Параметры запроса:**
- `date_from` - дата от (YYYY-MM-DD)
- `date_to` - дата до (YYYY-MM-DD)
- `branch_id` - фильтр по филиалу

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "courier_id": 3,
      "courier_name": "Иванов Иван Александрович",
      "branch_name": "Центральный",
      "total_requests": 25,
      "delivered": 20,
      "rejected": 3,
      "success_rate": 80.0,
      "avg_delivery_time_hours": 4.5
    }
  ]
}
```

### GET /reports/system/overview
Общая статистика системы

**Ответ:**
```json
{
  "success": true,
  "data": {
    "overview": {
      "total_requests": 127,
      "delivered": 89,
      "rejected": 13,
      "success_rate": 70.08,
      "active_couriers": 5,
      "active_branches": 4,
      "avg_delivery_time_hours": 6.2
    },
    "daily_stats": [
      {
        "date": "2024-01-15",
        "requests_count": 15,
        "delivered_count": 12
      }
    ]
  }
}
```

## Настройки

### GET /settings
Получение всех настроек системы (только для администратора)

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "setting_key": "location_update_interval",
      "setting_value": "60000",
      "description": "Интервал обновления местоположения курьеров (мс)",
      "updated_by_name": "Админов А.А.",
      "updated_at": "2024-01-15T10:00:00.000Z"
    }
  ]
}
```

### PUT /settings/:key
Обновление настройки

**Тело запроса:**
```json
{
  "setting_value": "30000"
}
```

### GET /settings/public/list
Получение публичных настроек (без авторизации)

**Ответ:**
```json
{
  "success": true,
  "data": {
    "location_update_interval": "60000",
    "max_photos_per_delivery": "5",
    "min_rejection_comment_length": "100",
    "file_upload_max_size": "10485760",
    "yandex_maps_enabled": "true"
  }
}
```

## WebSocket

### Подключение
```javascript
const socket = io('http://localhost:3000', {
  auth: {
    token: 'your_jwt_token'
  }
});
```

### События

#### location-update
Отправка местоположения курьера
```javascript
socket.emit('location-update', {
  courierId: 3,
  latitude: 55.7558,
  longitude: 37.6176,
  accuracy: 10
});
```

#### location-updated
Получение обновления местоположения
```javascript
socket.on('location-updated', (data) => {
  console.log('Обновление местоположения:', data);
});
```

#### join-courier-room
Присоединение к комнате курьера
```javascript
socket.emit('join-courier-room', courierId);
```

## Коды ошибок

| Код | Описание |
|-----|----------|
| 400 | Некорректный запрос |
| 401 | Не авторизован |
| 403 | Доступ запрещен |
| 404 | Ресурс не найден |
| 429 | Слишком много запросов |
| 500 | Внутренняя ошибка сервера |

## Ограничения

- Максимальный размер файла: 10MB
- Максимальное количество файлов за раз: 5
- Rate limit: 100 запросов в 15 минут
- Минимальная длина комментария при отказе: 100 символов
- Обновление местоположения: каждые 60 секунд