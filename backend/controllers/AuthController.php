<?php
/**
 * Контроллер аутентификации
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {
    private $userModel;
    private $config;

    public function __construct() {
        $this->userModel = new User();
        $this->config = require_once __DIR__ . '/../config/app.php';
    }

    /**
     * Авторизация пользователя
     */
    public function login() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['username']) || !isset($input['password'])) {
                return $this->response(['error' => 'Не указаны логин или пароль'], 400);
            }

            // Проверяем блокировку по IP
            if ($this->isBlocked($_SERVER['REMOTE_ADDR'])) {
                return $this->response(['error' => 'Слишком много неудачных попыток входа. Попробуйте позже.'], 429);
            }

            // Ищем пользователя
            $user = $this->userModel->findByUsername($input['username']);
            if (!$user) {
                $user = $this->userModel->findByEmail($input['username']);
            }

            if (!$user || !$this->userModel->verifyPassword($user, $input['password'])) {
                $this->recordFailedAttempt($_SERVER['REMOTE_ADDR']);
                return $this->response(['error' => 'Неверный логин или пароль'], 401);
            }

            // Очищаем неудачные попытки
            $this->clearFailedAttempts($_SERVER['REMOTE_ADDR']);

            // Обновляем время последнего входа
            $this->userModel->updateLastLogin($user['id']);

            // Создаем JWT токен
            $token = $this->createJWT($user);

            // Подготавливаем данные пользователя для ответа
            $userData = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role_name'],
                'permissions' => json_decode($user['permissions'], true),
                'branch_id' => $user['branch_id'],
                'branch_name' => $user['branch_name'],
                'department_id' => $user['department_id'],
                'department_name' => $user['department_name']
            ];

            // Логируем вход
            $this->logActivity($user['id'], 'login', 'user', $user['id']);

            return $this->response([
                'success' => true,
                'token' => $token,
                'user' => $userData
            ]);

        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return $this->response(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Выход из системы
     */
    public function logout() {
        try {
            $user = AuthMiddleware::getCurrentUser();
            
            if ($user) {
                $this->logActivity($user['id'], 'logout', 'user', $user['id']);
            }

            return $this->response(['success' => true, 'message' => 'Выход выполнен успешно']);

        } catch (Exception $e) {
            error_log('Logout error: ' . $e->getMessage());
            return $this->response(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Получить информацию о текущем пользователе
     */
    public function me() {
        try {
            $user = AuthMiddleware::getCurrentUser();
            
            if (!$user) {
                return $this->response(['error' => 'Пользователь не авторизован'], 401);
            }

            $userData = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'phone' => $user['phone'],
                'role' => $user['role_name'],
                'permissions' => json_decode($user['permissions'], true),
                'branch_id' => $user['branch_id'],
                'branch_name' => $user['branch_name'],
                'department_id' => $user['department_id'],
                'department_name' => $user['department_name'],
                'last_login' => $user['last_login']
            ];

            return $this->response(['user' => $userData]);

        } catch (Exception $e) {
            error_log('Me error: ' . $e->getMessage());
            return $this->response(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Обновить профиль пользователя
     */
    public function updateProfile() {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$user) {
                return $this->response(['error' => 'Пользователь не авторизован'], 401);
            }

            // Разрешенные поля для обновления
            $allowedFields = ['first_name', 'last_name', 'phone', 'email'];
            $updateData = [];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }

            // Проверяем уникальность email
            if (isset($updateData['email']) && $updateData['email'] !== $user['email']) {
                $existingUser = $this->userModel->findByEmail($updateData['email']);
                if ($existingUser && $existingUser['id'] !== $user['id']) {
                    return $this->response(['error' => 'Email уже используется другим пользователем'], 400);
                }
            }

            if (empty($updateData)) {
                return $this->response(['error' => 'Нет данных для обновления'], 400);
            }

            $this->userModel->update($user['id'], $updateData);
            $this->logActivity($user['id'], 'update_profile', 'user', $user['id']);

            return $this->response(['success' => true, 'message' => 'Профиль обновлен']);

        } catch (Exception $e) {
            error_log('Update profile error: ' . $e->getMessage());
            return $this->response(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Изменить пароль
     */
    public function changePassword() {
        try {
            $user = AuthMiddleware::getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$user) {
                return $this->response(['error' => 'Пользователь не авторизован'], 401);
            }

            if (!isset($input['current_password']) || !isset($input['new_password'])) {
                return $this->response(['error' => 'Не указаны текущий или новый пароль'], 400);
            }

            // Проверяем текущий пароль
            if (!$this->userModel->verifyPassword($user, $input['current_password'])) {
                return $this->response(['error' => 'Неверный текущий пароль'], 400);
            }

            // Проверяем длину нового пароля
            if (strlen($input['new_password']) < $this->config['security']['password_min_length']) {
                return $this->response(['error' => 'Пароль слишком короткий'], 400);
            }

            // Обновляем пароль
            $this->userModel->update($user['id'], ['password' => $input['new_password']]);
            $this->logActivity($user['id'], 'change_password', 'user', $user['id']);

            return $this->response(['success' => true, 'message' => 'Пароль изменен']);

        } catch (Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            return $this->response(['error' => 'Ошибка сервера'], 500);
        }
    }

    /**
     * Создать JWT токен
     */
    private function createJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'iat' => time(),
            'exp' => time() + $this->config['security']['jwt_expire']
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->config['security']['jwt_secret'], true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * Проверить блокировку IP
     */
    private function isBlocked($ip) {
        $attempts = $this->getFailedAttempts($ip);
        $maxAttempts = $this->config['security']['max_login_attempts'];
        $lockoutDuration = $this->config['security']['lockout_duration'];
        
        return count($attempts) >= $maxAttempts && 
               (time() - max($attempts)) < $lockoutDuration;
    }

    /**
     * Записать неудачную попытку входа
     */
    private function recordFailedAttempt($ip) {
        $file = __DIR__ . '/../../logs/failed_attempts.json';
        $attempts = [];
        
        if (file_exists($file)) {
            $attempts = json_decode(file_get_contents($file), true) ?: [];
        }
        
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = [];
        }
        
        $attempts[$ip][] = time();
        
        // Оставляем только последние попытки за период блокировки
        $lockoutDuration = $this->config['security']['lockout_duration'];
        $attempts[$ip] = array_filter($attempts[$ip], function($time) use ($lockoutDuration) {
            return (time() - $time) < $lockoutDuration;
        });
        
        file_put_contents($file, json_encode($attempts));
    }

    /**
     * Получить неудачные попытки для IP
     */
    private function getFailedAttempts($ip) {
        $file = __DIR__ . '/../../logs/failed_attempts.json';
        
        if (!file_exists($file)) {
            return [];
        }
        
        $attempts = json_decode(file_get_contents($file), true) ?: [];
        return $attempts[$ip] ?? [];
    }

    /**
     * Очистить неудачные попытки для IP
     */
    private function clearFailedAttempts($ip) {
        $file = __DIR__ . '/../../logs/failed_attempts.json';
        
        if (!file_exists($file)) {
            return;
        }
        
        $attempts = json_decode(file_get_contents($file), true) ?: [];
        unset($attempts[$ip]);
        
        file_put_contents($file, json_encode($attempts));
    }

    /**
     * Логирование активности пользователя
     */
    private function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
        try {
            $db = Database::getInstance();
            
            $logData = [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $db->insert('user_activity_logs', $logData);
            
        } catch (Exception $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
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