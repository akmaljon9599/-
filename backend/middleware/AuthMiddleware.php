<?php
/**
 * Middleware для аутентификации
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/User.php';

class AuthMiddleware {
    private static $currentUser = null;
    private static $config;

    public static function init() {
        self::$config = require_once __DIR__ . '/../config/app.php';
    }

    /**
     * Проверить аутентификацию пользователя
     */
    public static function authenticate() {
        if (!self::$config) {
            self::init();
        }

        $token = self::getBearerToken();
        
        if (!$token) {
            self::unauthorized('Токен не предоставлен');
            return false;
        }

        $user = self::validateToken($token);
        
        if (!$user) {
            self::unauthorized('Недействительный токен');
            return false;
        }

        self::$currentUser = $user;
        return true;
    }

    /**
     * Проверить права доступа
     */
    public static function authorize($requiredPermission) {
        if (!self::$currentUser) {
            self::forbidden('Пользователь не аутентифицирован');
            return false;
        }

        $userModel = new User();
        
        if (!$userModel->hasPermission(self::$currentUser['id'], $requiredPermission)) {
            self::forbidden('Недостаточно прав доступа');
            return false;
        }

        return true;
    }

    /**
     * Проверить роль пользователя
     */
    public static function requireRole($requiredRole) {
        if (!self::$currentUser) {
            self::forbidden('Пользователь не аутентифицирован');
            return false;
        }

        if (is_array($requiredRole)) {
            if (!in_array(self::$currentUser['role_name'], $requiredRole)) {
                self::forbidden('Недостаточно прав доступа');
                return false;
            }
        } else {
            if (self::$currentUser['role_name'] !== $requiredRole) {
                self::forbidden('Недостаточно прав доступа');
                return false;
            }
        }

        return true;
    }

    /**
     * Получить текущего пользователя
     */
    public static function getCurrentUser() {
        return self::$currentUser;
    }

    /**
     * Получить Bearer токен из заголовков
     */
    private static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Получить заголовок Authorization
     */
    private static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * Валидировать JWT токен
     */
    private static function validateToken($token) {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return false;
            }

            list($header, $payload, $signature) = $parts;

            // Проверяем подпись
            $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], 
                base64_encode(hash_hmac('sha256', $header . "." . $payload, self::$config['security']['jwt_secret'], true)));

            if ($signature !== $expectedSignature) {
                return false;
            }

            // Декодируем payload
            $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

            if (!$payloadData) {
                return false;
            }

            // Проверяем срок действия
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return false;
            }

            // Получаем актуальные данные пользователя
            $userModel = new User();
            $user = $userModel->findById($payloadData['user_id']);

            if (!$user) {
                return false;
            }

            return $user;

        } catch (Exception $e) {
            error_log('Token validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверить доступ к филиалу
     */
    public static function checkBranchAccess($branchId) {
        if (!self::$currentUser) {
            return false;
        }

        // Администратор имеет доступ ко всем филиалам
        if (self::$currentUser['role_name'] === 'admin') {
            return true;
        }

        // Остальные пользователи имеют доступ только к своему филиалу
        return self::$currentUser['branch_id'] == $branchId;
    }

    /**
     * Проверить доступ к подразделению
     */
    public static function checkDepartmentAccess($departmentId) {
        if (!self::$currentUser) {
            return false;
        }

        // Администратор имеет доступ ко всем подразделениям
        if (self::$currentUser['role_name'] === 'admin') {
            return true;
        }

        // Старший курьер имеет доступ к подразделениям своего филиала
        if (self::$currentUser['role_name'] === 'senior_courier') {
            // Здесь нужно проверить, что подразделение относится к филиалу пользователя
            // Для упрощения пока возвращаем true
            return true;
        }

        // Остальные пользователи имеют доступ только к своему подразделению
        return self::$currentUser['department_id'] == $departmentId;
    }

    /**
     * Проверить, может ли пользователь работать с заявкой
     */
    public static function canAccessRequest($request) {
        if (!self::$currentUser || !$request) {
            return false;
        }

        $userRole = self::$currentUser['role_name'];

        switch ($userRole) {
            case 'admin':
                return true;

            case 'senior_courier':
                return self::checkBranchAccess($request['branch_id']);

            case 'courier':
                return $request['courier_id'] == self::$currentUser['id'];

            case 'operator':
                return self::checkBranchAccess($request['branch_id']);

            default:
                return false;
        }
    }

    /**
     * Отправить ответ 401 Unauthorized
     */
    private static function unauthorized($message = 'Unauthorized') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Отправить ответ 403 Forbidden
     */
    private static function forbidden($message = 'Forbidden') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Middleware для CORS
     */
    public static function handleCORS() {
        if (!self::$config) {
            self::init();
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, self::$config['cors']['allowed_origins']) || in_array('*', self::$config['cors']['allowed_origins'])) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header("Access-Control-Allow-Methods: " . implode(', ', self::$config['cors']['allowed_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', self::$config['cors']['allowed_headers']));
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Логирование активности пользователя
     */
    public static function logActivity($action, $entityType = null, $entityId = null, $details = null) {
        if (!self::$currentUser) {
            return;
        }

        try {
            $db = Database::getInstance();
            
            $logData = [
                'user_id' => self::$currentUser['id'],
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
}