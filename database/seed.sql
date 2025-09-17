-- Заполнение базы данных начальными данными
USE courier_system;

-- Вставка ролей
INSERT INTO roles (name, description, permissions) VALUES
('admin', 'Администратор', '{"all": true}'),
('senior_courier', 'Старший курьер', '{"view_requests": true, "assign_couriers": true, "print_contracts": true, "manage_couriers": true, "change_status": true}'),
('courier', 'Курьер', '{"view_assigned_requests": true, "change_status": true, "upload_photos": true, "add_comments": true}'),
('operator', 'Оператор', '{"add_requests": true, "edit_requests": true, "view_requests": true, "export_data": true}');

-- Вставка филиалов
INSERT INTO branches (name, address, phone, manager_name) VALUES
('Центральный', 'ул. Центральная, д. 1, г. Москва', '+7 (495) 123-45-67', 'Иванов Иван Иванович'),
('Северный', 'пр. Северный, д. 10, г. Москва', '+7 (495) 234-56-78', 'Петров Петр Петрович'),
('Южный', 'ул. Южная, д. 20, г. Москва', '+7 (495) 345-67-89', 'Сидоров Сидор Сидорович');

-- Вставка подразделений
INSERT INTO departments (name, branch_id, manager_name) VALUES
('Подразделение 1', 1, 'Козлов Козел Козлович'),
('Подразделение 2', 1, 'Волков Волк Волкович'),
('Подразделение 3', 2, 'Медведев Медведь Медведевич'),
('Подразделение 4', 3, 'Орлов Орел Орлович');

-- Вставка типов карт
INSERT INTO card_types (name, description) VALUES
('Visa Classic', 'Классическая карта Visa'),
('Visa Gold', 'Золотая карта Visa'),
('MasterCard Standard', 'Стандартная карта MasterCard'),
('MasterCard Gold', 'Золотая карта MasterCard'),
('Мир Классическая', 'Классическая карта Мир'),
('Мир Премиум', 'Премиум карта Мир');

-- Вставка пользователей (пароли зашифрованы с помощью bcrypt)
-- Пароль для всех тестовых пользователей: password123
INSERT INTO users (username, email, password_hash, first_name, last_name, middle_name, phone, role_id, branch_id, department_id) VALUES
('admin', 'admin@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Админ', 'Админов', 'Админович', '+7 (495) 000-00-01', 1, 1, 1),
('senior1', 'senior1@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Старший', 'Курьер', 'Первый', '+7 (495) 000-00-02', 2, 1, 1),
('courier1', 'courier1@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Иван', 'Иванов', 'Александрович', '+7 (495) 000-00-03', 3, 1, 1),
('courier2', 'courier2@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Владимир', 'Смирнов', 'Викторович', '+7 (495) 000-00-04', 3, 2, 3),
('courier3', 'courier3@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Сергей', 'Петров', 'Сергеевич', '+7 (495) 000-00-05', 3, 3, 4),
('operator1', 'operator1@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Мария', 'Операторова', 'Ивановна', '+7 (495) 000-00-06', 4, 1, 1),
('operator2', 'operator2@bank.ru', '$2a$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Анна', 'Клиентова', 'Петровна', '+7 (495) 000-00-07', 4, 2, 3);

-- Вставка тестовых заявок
INSERT INTO delivery_requests (request_number, abs_id, client_name, client_phone, pan, delivery_address, status, call_status, card_type_id, courier_id, operator_id, branch_id, department_id, registration_date, processing_date) VALUES
('REQ-2024-001', 'ABS10001', 'Петров Иван Сергеевич', '+7 (900) 123-45-67', '1234567890123456', 'ул. Пушкина, д. 15, кв. 10, г. Москва', 'waiting_delivery', 'successful', 1, 3, 6, 1, 1, '2024-01-15 14:30:00', '2024-01-15 15:00:00'),
('REQ-2024-002', 'ABS10002', 'Сидорова Мария Ивановна', '+7 (900) 765-43-21', '2345678901234567', 'пр. Ленина, д. 42, кв. 5, г. Москва', 'delivered', 'successful', 2, 4, 7, 2, 3, '2024-01-15 15:45:00', '2024-01-15 16:00:00'),
('REQ-2024-003', 'ABS10003', 'Кузнецов Олег Петрович', '+7 (900) 555-44-33', '3456789012345678', 'ул. Гагарина, д. 8, кв. 12, г. Москва', 'new', 'not_called', 3, NULL, 6, 3, 4, '2024-01-16 09:15:00', NULL),
('REQ-2024-004', 'ABS10004', 'Морозова Елена Владимировна', '+7 (900) 111-22-33', '4567890123456789', 'ул. Мира, д. 25, кв. 7, г. Москва', 'rejected', 'successful', 4, 5, 7, 3, 4, '2024-01-16 10:30:00', '2024-01-16 11:00:00'),
('REQ-2024-005', 'ABS10005', 'Новиков Дмитрий Александрович', '+7 (900) 999-88-77', '5678901234567890', 'пр. Победы, д. 33, кв. 15, г. Москва', 'waiting_delivery', 'successful', 5, 3, 6, 1, 1, '2024-01-16 11:45:00', '2024-01-16 12:00:00');

-- Обновление заявки с отказом
UPDATE delivery_requests SET 
    rejection_reason = 'Клиент отказался от получения карты, так как передумал оформлять кредит. Требуется дополнительное время для размышлений.',
    delivery_date = '2024-01-16 14:00:00'
WHERE request_number = 'REQ-2024-004';

-- Обновление доставленной заявки
UPDATE delivery_requests SET 
    delivery_date = '2024-01-15 18:30:00',
    courier_phone = '+7 (495) 000-00-04'
WHERE request_number = 'REQ-2024-002';

-- Вставка настроек системы
INSERT INTO system_settings (setting_key, setting_value, description, updated_by) VALUES
('location_update_interval', '60000', 'Интервал обновления местоположения курьеров в миллисекундах', 1),
('max_file_size', '10485760', 'Максимальный размер загружаемого файла в байтах', 1),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', 'Разрешенные типы файлов для загрузки', 1),
('min_rejection_comment_length', '100', 'Минимальная длина комментария при отказе в символах', 1),
('min_delivery_photos', '2', 'Минимальное количество фотографий при доставке', 1),
('yandex_maps_api_key', '', 'API ключ для Яндекс.Карт', 1),
('abs_bank_api_url', '', 'URL API АБС банка', 1),
('abs_bank_api_key', '', 'API ключ АБС банка', 1);