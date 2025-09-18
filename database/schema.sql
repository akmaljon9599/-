-- Создание базы данных для системы управления курьерскими заявками
-- Версия: 1.0
-- Дата: 2025-09-17

CREATE DATABASE IF NOT EXISTS courier_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE courier_system;

-- Таблица ролей пользователей
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица филиалов
CREATE TABLE branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    manager_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица подразделений
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    branch_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Таблица пользователей
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    phone VARCHAR(20),
    role_id INT NOT NULL,
    branch_id INT,
    department_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Таблица курьеров (расширенная информация)
CREATE TABLE couriers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    vehicle_type ENUM('foot', 'bicycle', 'motorcycle', 'car') DEFAULT 'foot',
    license_number VARCHAR(20),
    is_online BOOLEAN DEFAULT FALSE,
    current_latitude DECIMAL(10, 8),
    current_longitude DECIMAL(11, 8),
    last_location_update TIMESTAMP NULL,
    max_orders_per_day INT DEFAULT 10,
    rating DECIMAL(3, 2) DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица типов карт
CREATE TABLE card_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица заявок на доставку
CREATE TABLE delivery_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(20) NOT NULL UNIQUE,
    abs_id VARCHAR(50), -- ID из АБС банка
    client_full_name VARCHAR(200) NOT NULL,
    client_phone VARCHAR(20) NOT NULL,
    client_pan VARCHAR(19), -- PAN карты
    delivery_address TEXT NOT NULL,
    delivery_latitude DECIMAL(10, 8),
    delivery_longitude DECIMAL(11, 8),
    status ENUM('new', 'assigned', 'in_progress', 'delivered', 'rejected', 'cancelled') DEFAULT 'new',
    call_status ENUM('not_called', 'successful', 'failed', 'busy', 'no_answer') DEFAULT 'not_called',
    card_type_id INT,
    branch_id INT NOT NULL,
    department_id INT NOT NULL,
    operator_id INT, -- Оператор, создавший заявку
    courier_id INT, -- Назначенный курьер
    senior_courier_id INT, -- Старший курьер, назначивший курьера
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    delivery_date DATE,
    delivery_time_from TIME,
    delivery_time_to TIME,
    notes TEXT,
    rejection_reason TEXT,
    delivery_photos JSON, -- Пути к фотографиям доставки
    courier_phone VARCHAR(20), -- Телефон курьера для связи
    contract_signed BOOLEAN DEFAULT FALSE,
    signature_path VARCHAR(255), -- Путь к файлу с подписью
    documents JSON, -- Пути к документам (паспорт и др.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL, -- Дата обработки заявки
    delivered_at TIMESTAMP NULL, -- Дата доставки
    FOREIGN KEY (card_type_id) REFERENCES card_types(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (operator_id) REFERENCES users(id),
    FOREIGN KEY (courier_id) REFERENCES users(id),
    FOREIGN KEY (senior_courier_id) REFERENCES users(id)
);

-- Таблица истории изменений статусов заявок
CREATE TABLE request_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    old_status ENUM('new', 'assigned', 'in_progress', 'delivered', 'rejected', 'cancelled'),
    new_status ENUM('new', 'assigned', 'in_progress', 'delivered', 'rejected', 'cancelled') NOT NULL,
    changed_by INT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES delivery_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Таблица для отслеживания местоположения курьеров
CREATE TABLE courier_location_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    courier_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2), -- Точность в метрах
    speed DECIMAL(8, 2), -- Скорость в км/ч
    heading DECIMAL(5, 2), -- Направление движения в градусах
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (courier_id) REFERENCES couriers(id) ON DELETE CASCADE,
    INDEX idx_courier_created (courier_id, created_at),
    INDEX idx_created (created_at)
);

-- Таблица документов
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    document_type ENUM('passport', 'contract', 'photo', 'signature', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES delivery_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Таблица системных настроек
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Таблица логов действий пользователей
CREATE TABLE user_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50), -- Тип сущности (request, user, etc.)
    entity_id INT, -- ID сущности
    details JSON, -- Дополнительные детали действия
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_created (created_at)
);

-- Вставка базовых данных

-- Роли пользователей
INSERT INTO roles (name, description, permissions) VALUES 
('admin', 'Администратор', '["all"]'),
('senior_courier', 'Старший курьер', '["view_requests", "assign_courier", "print_contract", "manage_couriers", "change_status"]'),
('courier', 'Курьер', '["view_assigned_requests", "update_delivery_status", "upload_photos"]'),
('operator', 'Оператор', '["create_request", "edit_request", "view_requests", "export_data"]');

-- Типы карт
INSERT INTO card_types (name, description) VALUES 
('Visa', 'Карты Visa'),
('MasterCard', 'Карты MasterCard'),
('Мир', 'Карты национальной платежной системы Мир'),
('UnionPay', 'Карты UnionPay');

-- Филиалы
INSERT INTO branches (name, address, phone, email) VALUES 
('Центральный филиал', 'г. Москва, ул. Центральная, д. 1', '+7 (495) 123-45-67', 'central@bank.ru'),
('Северный филиал', 'г. Москва, ул. Северная, д. 15', '+7 (495) 234-56-78', 'north@bank.ru'),
('Южный филиал', 'г. Москва, ул. Южная, д. 25', '+7 (495) 345-67-89', 'south@bank.ru');

-- Подразделения
INSERT INTO departments (branch_id, name, description) VALUES 
(1, 'Подразделение 1', 'Основное подразделение Центрального филиала'),
(1, 'Подразделение 2', 'Дополнительное подразделение Центрального филиала'),
(2, 'Подразделение 1', 'Основное подразделение Северного филиала'),
(3, 'Подразделение 1', 'Основное подразделение Южного филиала');

-- Системные настройки
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES 
('location_update_interval', '60', 'number', 'Интервал обновления геолокации курьеров (секунды)'),
('max_delivery_photos', '5', 'number', 'Максимальное количество фотографий при доставке'),
('min_rejection_comment_length', '100', 'number', 'Минимальная длина комментария при отказе'),
('yandex_maps_api_key', '', 'string', 'API ключ для Яндекс.Карт'),
('abs_api_endpoint', '', 'string', 'Endpoint API АБС банка'),
('file_upload_max_size', '10485760', 'number', 'Максимальный размер загружаемого файла (байты)');

-- Создание пользователя администратора (пароль: admin123)
INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, branch_id, department_id) VALUES 
('admin', 'admin@bank.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'Системы', 1, 1, 1);

-- Создание индексов для оптимизации запросов
CREATE INDEX idx_delivery_requests_status ON delivery_requests(status);
CREATE INDEX idx_delivery_requests_courier ON delivery_requests(courier_id);
CREATE INDEX idx_delivery_requests_branch ON delivery_requests(branch_id);
CREATE INDEX idx_delivery_requests_created ON delivery_requests(created_at);
CREATE INDEX idx_delivery_requests_delivery_date ON delivery_requests(delivery_date);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_branch ON users(branch_id);
CREATE INDEX idx_couriers_online ON couriers(is_online);
CREATE INDEX idx_couriers_location ON couriers(current_latitude, current_longitude);