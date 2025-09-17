-- Создание базы данных
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
    manager_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица подразделений
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    branch_id INT,
    manager_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
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
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Таблица типов карт
CREATE TABLE card_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица статусов заявок
CREATE TABLE request_statuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6c757d',
    is_final BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица статусов звонков
CREATE TABLE call_statuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица заявок
CREATE TABLE requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_number VARCHAR(20) NOT NULL UNIQUE,
    abs_id VARCHAR(50),
    client_name VARCHAR(150) NOT NULL,
    client_phone VARCHAR(20) NOT NULL,
    pan VARCHAR(20),
    delivery_address TEXT NOT NULL,
    status_id INT NOT NULL,
    call_status_id INT,
    card_type_id INT,
    courier_id INT,
    operator_id INT,
    branch_id INT,
    department_id INT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processing_date TIMESTAMP NULL,
    delivery_date TIMESTAMP NULL,
    rejection_reason TEXT,
    courier_phone VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES request_statuses(id),
    FOREIGN KEY (call_status_id) REFERENCES call_statuses(id),
    FOREIGN KEY (card_type_id) REFERENCES card_types(id),
    FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Таблица файлов заявок
CREATE TABLE request_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    file_category ENUM('delivery_photo', 'passport_scan', 'contract', 'signature', 'other') DEFAULT 'other',
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Таблица местоположений курьеров
CREATE TABLE courier_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    courier_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2),
    address TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_courier_timestamp (courier_id, timestamp)
);

-- Таблица активности курьеров
CREATE TABLE courier_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    courier_id INT NOT NULL,
    activity_type ENUM('online', 'offline', 'on_delivery', 'break') NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Таблица логов системы
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created_at (created_at)
);

-- Таблица настроек системы
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Индексы для оптимизации
CREATE INDEX idx_requests_status ON requests(status_id);
CREATE INDEX idx_requests_courier ON requests(courier_id);
CREATE INDEX idx_requests_date ON requests(registration_date);
CREATE INDEX idx_requests_client ON requests(client_name, client_phone);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_branch ON users(branch_id);
CREATE INDEX idx_users_department ON users(department_id);