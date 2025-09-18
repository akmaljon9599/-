-- Заполнение начальными данными
USE courier_system;

-- Роли пользователей
INSERT INTO roles (name, description, permissions) VALUES
('admin', 'Администратор системы', '{"all": true}'),
('senior_courier', 'Старший курьер', '{"view_requests": true, "assign_couriers": true, "print_contracts": true, "manage_couriers": true, "change_status": true}'),
('courier', 'Курьер', '{"view_assigned_requests": true, "change_delivery_status": true, "upload_photos": true, "add_comments": true}'),
('operator', 'Оператор', '{"add_requests": true, "edit_requests": true, "view_requests": true, "export_data": true}');

-- Статусы заявок
INSERT INTO request_statuses (name, description, color, is_final) VALUES
('new', 'Новая заявка', '#17a2b8', FALSE),
('waiting_delivery', 'Ожидает доставки', '#ffc107', FALSE),
('delivered', 'Доставлено', '#28a745', TRUE),
('rejected', 'Отказано', '#dc3545', TRUE),
('cancelled', 'Отменено', '#6c757d', TRUE);

-- Статусы звонков
INSERT INTO call_statuses (name, description) VALUES
('successful', 'Успешный звонок'),
('failed', 'Не удался'),
('not_called', 'Не звонили'),
('busy', 'Занято'),
('no_answer', 'Нет ответа');

-- Типы карт
INSERT INTO card_types (name, description) VALUES
('Visa', 'Банковская карта Visa'),
('MasterCard', 'Банковская карта MasterCard'),
('Мир', 'Банковская карта Мир'),
('UnionPay', 'Банковская карта UnionPay');

-- Филиалы
INSERT INTO branches (name, address, phone, manager_name) VALUES
('Центральный', 'ул. Центральная, д. 1, г. Москва', '+7 (495) 123-45-67', 'Иванов Иван Иванович'),
('Северный', 'пр. Северный, д. 10, г. Москва', '+7 (495) 234-56-78', 'Петров Петр Петрович'),
('Южный', 'ул. Южная, д. 20, г. Москва', '+7 (495) 345-67-89', 'Сидоров Сидор Сидорович'),
('Западный', 'ул. Западная, д. 30, г. Москва', '+7 (495) 456-78-90', 'Кузнецов Кузьма Кузьмич');

-- Подразделения
INSERT INTO departments (name, branch_id, manager_name) VALUES
('Подразделение 1', 1, 'Менеджер 1'),
('Подразделение 2', 1, 'Менеджер 2'),
('Подразделение 3', 2, 'Менеджер 3'),
('Подразделение 4', 2, 'Менеджер 4'),
('Подразделение 5', 3, 'Менеджер 5'),
('Подразделение 6', 4, 'Менеджер 6');

-- Тестовые пользователи (пароли: admin123, courier123, operator123)
INSERT INTO users (username, email, password_hash, first_name, last_name, middle_name, phone, role_id, branch_id, department_id) VALUES
('admin', 'admin@bank.ru', '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4J/8K.5K2O', 'Админ', 'Админов', 'Админович', '+7 (495) 000-00-01', 1, 1, 1),
('senior1', 'senior1@bank.ru', '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4J/8K.5K2O', 'Старший', 'Курьер', 'Первый', '+7 (495) 000-00-02', 2, 1, 1),
('courier1', 'courier1@bank.ru', '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4J/8K.5K2O', 'Иван', 'Иванов', 'Александрович', '+7 (495) 000-00-03', 3, 1, 1),
('courier2', 'courier2@bank.ru', '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4J/8K.5K2O', 'Петр', 'Петров', 'Сергеевич', '+7 (495) 000-00-04', 3, 2, 3),
('operator1', 'operator1@bank.ru', '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4J/8K.5K2O', 'Мария', 'Операторова', 'Владимировна', '+7 (495) 000-00-05', 4, 1, 1);

-- Тестовые заявки
INSERT INTO requests (request_number, abs_id, client_name, client_phone, pan, delivery_address, status_id, call_status_id, card_type_id, courier_id, operator_id, branch_id, department_id, registration_date) VALUES
('REQ-001', 'ABS10001', 'Петров Иван Сергеевич', '+7 (900) 123-45-67', '1234567890123456', 'ул. Пушкина, д. 15, кв. 10, г. Москва', 2, 1, 1, 3, 5, 1, 1, '2024-01-15 14:30:00'),
('REQ-002', 'ABS10002', 'Сидорова Мария Ивановна', '+7 (900) 765-43-21', '2345678901234567', 'пр. Ленина, д. 42, кв. 5, г. Москва', 3, 1, 2, 4, 5, 2, 3, '2024-01-15 15:45:00'),
('REQ-003', 'ABS10003', 'Кузнецов Олег Петрович', '+7 (900) 555-44-33', '3456789012345678', 'ул. Гагарина, д. 8, кв. 12, г. Москва', 1, 3, 3, NULL, 5, 3, 5, '2024-01-16 09:15:00'),
('REQ-004', 'ABS10004', 'Смирнова Анна Владимировна', '+7 (900) 777-88-99', '4567890123456789', 'ул. Мира, д. 25, кв. 7, г. Москва', 2, 1, 1, 3, 5, 1, 1, '2024-01-16 10:20:00'),
('REQ-005', 'ABS10005', 'Волков Дмитрий Александрович', '+7 (900) 111-22-33', '5678901234567890', 'пр. Победы, д. 100, кв. 15, г. Москва', 4, 2, 2, 4, 5, 2, 3, '2024-01-16 11:30:00');

-- Настройки системы
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('location_update_interval', '60000', 'Интервал обновления местоположения курьеров (мс)'),
('max_photos_per_delivery', '5', 'Максимальное количество фотографий при доставке'),
('min_rejection_comment_length', '100', 'Минимальная длина комментария при отказе'),
('session_timeout', '3600000', 'Время жизни сессии (мс)'),
('file_upload_max_size', '10485760', 'Максимальный размер загружаемого файла (байт)'),
('yandex_maps_enabled', 'true', 'Включить интеграцию с Яндекс.Картами'),
('abs_bank_integration_enabled', 'true', 'Включить интеграцию с АБС банка');