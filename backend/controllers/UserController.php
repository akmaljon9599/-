<?php
/**
 * Контроллер пользователей
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Courier.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UserController {
    private $userModel;
    private $courierModel;

    public function __construct() {
        $this->userModel = new User();
        $this->courierModel = new Courier();
    }

    /**
     * Получить список пользователей
     */
    public function index() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = $_GET;

            // Ограничиваем по филиалу для старших курьеров
            if ($user['role_name'] === 'senior_courier') {
                $filters['branch_id'] = $user['branch_id'];
            }

            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $users = $this->userModel->getList($filters, $limit, $offset);
            $total = $this->userModel->getCount($filters);

            $this->response([
                'success' => true,
                'data' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            error_log('Get users error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения пользователей'], 500);
        }
    }

    /**
     * Получить пользователя по ID
     */
    public function show($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $user = $this->userModel->findById($id);
            
            if (!$user) {
                $this->response(['error' => 'Пользователь не найден'], 404);
                return;
            }

            $currentUser = AuthMiddleware::getCurrentUser();
            
            // Проверяем доступ для старшего курьера
            if ($currentUser['role_name'] === 'senior_courier') {
                if (!AuthMiddleware::checkBranchAccess($user['branch_id'])) {
                    $this->response(['error' => 'Нет доступа к пользователю'], 403);
                    return;
                }
            }

            $this->response([
                'success' => true,
                'data' => $user
            ]);

        } catch (Exception $e) {
            error_log('Get user error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения пользователя'], 500);
        }
    }

    /**
     * Создать пользователя
     */
    public function create() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole('admin')) {
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация обязательных полей
            $required = ['username', 'email', 'password', 'first_name', 'last_name', 'role_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->response(['error' => "Поле {$field} обязательно для заполнения"], 400);
                    return;
                }
            }

            // Проверяем уникальность username и email
            if ($this->userModel->findByUsername($input['username'])) {
                $this->response(['error' => 'Пользователь с таким логином уже существует'], 400);
                return;
            }

            if ($this->userModel->findByEmail($input['email'])) {
                $this->response(['error' => 'Пользователь с таким email уже существует'], 400);
                return;
            }

            // Подготавливаем данные для создания
            $userData = [
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'middle_name' => $input['middle_name'] ?? null,
                'phone' => $input['phone'] ?? null,
                'role_id' => $input['role_id'],
                'branch_id' => $input['branch_id'] ?? null,
                'department_id' => $input['department_id'] ?? null
            ];

            $userId = $this->userModel->create($userData);

            // Если создаем курьера, создаем запись в таблице курьеров
            if ($input['role_id'] == 3) { // ID роли курьера
                $courierData = [
                    'user_id' => $userId,
                    'vehicle_type' => $input['vehicle_type'] ?? 'foot',
                    'license_number' => $input['license_number'] ?? null,
                    'max_orders_per_day' => $input['max_orders_per_day'] ?? 10
                ];
                $this->courierModel->create($courierData);
            }

            AuthMiddleware::logActivity('create_user', 'user', $userId, $userData);

            $this->response([
                'success' => true,
                'message' => 'Пользователь создан успешно',
                'id' => $userId
            ]);

        } catch (Exception $e) {
            error_log('Create user error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка создания пользователя'], 500);
        }
    }

    /**
     * Обновить пользователя
     */
    public function update($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $user = $this->userModel->findById($id);
            
            if (!$user) {
                $this->response(['error' => 'Пользователь не найден'], 404);
                return;
            }

            $currentUser = AuthMiddleware::getCurrentUser();
            
            // Проверяем доступ для старшего курьера
            if ($currentUser['role_name'] === 'senior_courier') {
                if (!AuthMiddleware::checkBranchAccess($user['branch_id'])) {
                    $this->response(['error' => 'Нет доступа к пользователю'], 403);
                    return;
                }
                
                // Старший курьер не может изменять администраторов
                if ($user['role_name'] === 'admin') {
                    $this->response(['error' => 'Нет прав на изменение администратора'], 403);
                    return;
                }
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // Разрешенные поля для обновления
            $allowedFields = ['first_name', 'last_name', 'middle_name', 'phone', 'email'];
            
            if ($currentUser['role_name'] === 'admin') {
                $allowedFields = array_merge($allowedFields, ['username', 'role_id', 'branch_id', 'department_id']);
            }

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }

            // Проверяем уникальность при изменении username или email
            if (isset($updateData['username']) && $updateData['username'] !== $user['username']) {
                if ($this->userModel->findByUsername($updateData['username'])) {
                    $this->response(['error' => 'Пользователь с таким логином уже существует'], 400);
                    return;
                }
            }

            if (isset($updateData['email']) && $updateData['email'] !== $user['email']) {
                if ($this->userModel->findByEmail($updateData['email'])) {
                    $this->response(['error' => 'Пользователь с таким email уже существует'], 400);
                    return;
                }
            }

            if (empty($updateData)) {
                $this->response(['error' => 'Нет данных для обновления'], 400);
                return;
            }

            $this->userModel->update($id, $updateData);

            AuthMiddleware::logActivity('update_user', 'user', $id, $updateData);

            $this->response([
                'success' => true,
                'message' => 'Пользователь обновлен успешно'
            ]);

        } catch (Exception $e) {
            error_log('Update user error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка обновления пользователя'], 500);
        }
    }

    /**
     * Деактивировать пользователя
     */
    public function delete($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole('admin')) {
                return;
            }

            $user = $this->userModel->findById($id);
            
            if (!$user) {
                $this->response(['error' => 'Пользователь не найден'], 404);
                return;
            }

            $currentUser = AuthMiddleware::getCurrentUser();
            
            // Нельзя деактивировать самого себя
            if ($id == $currentUser['id']) {
                $this->response(['error' => 'Нельзя деактивировать самого себя'], 400);
                return;
            }

            $this->userModel->deactivate($id);

            AuthMiddleware::logActivity('deactivate_user', 'user', $id);

            $this->response([
                'success' => true,
                'message' => 'Пользователь деактивирован успешно'
            ]);

        } catch (Exception $e) {
            error_log('Delete user error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка деактивации пользователя'], 500);
        }
    }

    /**
     * Сбросить пароль пользователя
     */
    public function resetPassword($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole('admin')) {
                return;
            }

            $user = $this->userModel->findById($id);
            
            if (!$user) {
                $this->response(['error' => 'Пользователь не найден'], 404);
                return;
            }

            // Генерируем временный пароль
            $tempPassword = $this->generateTempPassword();
            
            $this->userModel->update($id, ['password' => $tempPassword]);

            AuthMiddleware::logActivity('reset_password', 'user', $id);

            $this->response([
                'success' => true,
                'message' => 'Пароль сброшен успешно',
                'temp_password' => $tempPassword
            ]);

        } catch (Exception $e) {
            error_log('Reset password error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка сброса пароля'], 500);
        }
    }

    /**
     * Получить роли пользователей
     */
    public function getRoles() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $sql = "SELECT id, name, description FROM roles ORDER BY name";
            $roles = Database::getInstance()->select($sql);

            $this->response([
                'success' => true,
                'data' => $roles
            ]);

        } catch (Exception $e) {
            error_log('Get roles error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения ролей'], 500);
        }
    }

    /**
     * Получить филиалы
     */
    public function getBranches() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $sql = "SELECT id, name, address FROM branches WHERE is_active = 1 ORDER BY name";
            $branches = Database::getInstance()->select($sql);

            $this->response([
                'success' => true,
                'data' => $branches
            ]);

        } catch (Exception $e) {
            error_log('Get branches error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения филиалов'], 500);
        }
    }

    /**
     * Получить подразделения филиала
     */
    public function getDepartments($branchId) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $sql = "SELECT id, name, description FROM departments WHERE branch_id = :branch_id AND is_active = 1 ORDER BY name";
            $departments = Database::getInstance()->select($sql, ['branch_id' => $branchId]);

            $this->response([
                'success' => true,
                'data' => $departments
            ]);

        } catch (Exception $e) {
            error_log('Get departments error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения подразделений'], 500);
        }
    }

    /**
     * Генерировать временный пароль
     */
    private function generateTempPassword($length = 8) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }

    /**
     * Отправить HTTP ответ
     */
    private function response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}