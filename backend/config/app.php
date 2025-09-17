<?php
/**
 * Основная конфигурация приложения
 * Система управления курьерскими заявками
 */

return [
    'name' => 'Courier Management System',
    'version' => '1.0.0',
    'debug' => $_ENV['APP_DEBUG'] ?? true,
    'timezone' => 'Europe/Moscow',
    'locale' => 'ru_RU',
    
    // Настройки безопасности
    'security' => [
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production',
        'jwt_expire' => 3600 * 24, // 24 часа
        'password_min_length' => 6,
        'session_lifetime' => 3600 * 8, // 8 часов
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 минут
    ],
    
    // Настройки файлов
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_photo_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_document_types' => ['application/pdf', 'image/jpeg', 'image/png'],
        'photo_path' => '/uploads/photos/',
        'document_path' => '/uploads/documents/',
        'signature_path' => '/uploads/signatures/',
    ],
    
    // Настройки геолокации
    'geolocation' => [
        'update_interval' => 60, // секунды
        'accuracy_threshold' => 100, // метры
        'history_retention_days' => 30,
    ],
    
    // API ключи и внешние сервисы
    'services' => [
        'yandex_maps_api_key' => $_ENV['YANDEX_MAPS_API_KEY'] ?? '',
        'abs_api_endpoint' => $_ENV['ABS_API_ENDPOINT'] ?? '',
        'abs_api_key' => $_ENV['ABS_API_KEY'] ?? '',
    ],
    
    // Настройки уведомлений
    'notifications' => [
        'email_enabled' => $_ENV['EMAIL_ENABLED'] ?? false,
        'sms_enabled' => $_ENV['SMS_ENABLED'] ?? false,
        'push_enabled' => $_ENV['PUSH_ENABLED'] ?? false,
    ],
    
    // CORS настройки
    'cors' => [
        'allowed_origins' => ['http://localhost', 'https://localhost'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ]
];