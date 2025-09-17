<?php
namespace Courier\Delivery\Util;

use Bitrix\Main\Security\Sign\Signer;
use Bitrix\Main\Web\Json;

/**
 * Класс для обеспечения безопасности
 */
class Security
{
    private static $salt = 'courier_delivery_security_salt';

    /**
     * Генерировать токен CSRF
     */
    public static function generateCsrfToken($userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $data = [
            'user_id' => $userId,
            'timestamp' => time(),
            'session_id' => session_id()
        ];

        $signer = new Signer();
        return $signer->sign(Json::encode($data), self::$salt);
    }

    /**
     * Проверить токен CSRF
     */
    public static function validateCsrfToken($token, $userId = null, $maxAge = 3600)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            $signer = new Signer();
            $dataJson = $signer->unsign($token, self::$salt);
            $data = Json::decode($dataJson);

            // Проверяем пользователя
            if ($data['user_id'] != $userId) {
                return false;
            }

            // Проверяем время жизни токена
            if (time() - $data['timestamp'] > $maxAge) {
                return false;
            }

            // Проверяем сессию
            if ($data['session_id'] !== session_id()) {
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Logger::warning('CSRF token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Хэшировать пароль
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Проверить пароль
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Генерировать случайную строку
     */
    public static function generateRandomString($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Шифрование данных
     */
    public static function encrypt($data, $key = null)
    {
        if (!$key) {
            $key = self::getEncryptionKey();
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Расшифровка данных
     */
    public static function decrypt($encryptedData, $key = null)
    {
        if (!$key) {
            $key = self::getEncryptionKey();
        }

        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Получить ключ шифрования
     */
    private static function getEncryptionKey()
    {
        // В продакшене ключ должен храниться в безопасном месте
        return hash('sha256', self::$salt . 'encryption_key');
    }

    /**
     * Санитизация входных данных
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        switch ($type) {
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
                
            case 'phone':
                return preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
                
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Валидация входных данных
     */
    public static function validateInput($input, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;

            // Проверка обязательности
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = 'Поле обязательно для заполнения';
                continue;
            }

            if (empty($value)) {
                continue;
            }

            // Проверка типа
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Некорректный email адрес';
                        }
                        break;
                        
                    case 'phone':
                        if (!preg_match('/^\+7\s?\(\d{3}\)\s?\d{3}-\d{2}-\d{2}$/', $value)) {
                            $errors[$field] = 'Некорректный номер телефона';
                        }
                        break;
                        
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = 'Значение должно быть числом';
                        }
                        break;
                        
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = 'Значение должно быть числом';
                        }
                        break;
                }
            }

            // Проверка длины
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "Минимальная длина: {$rule['min_length']} символов";
            }

            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "Максимальная длина: {$rule['max_length']} символов";
            }

            // Проверка регулярным выражением
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = $rule['pattern_message'] ?? 'Некорректный формат данных';
            }

            // Кастомная валидация
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customResult = $rule['custom']($value);
                if ($customResult !== true) {
                    $errors[$field] = $customResult;
                }
            }
        }

        return $errors;
    }

    /**
     * Проверка лимита запросов (rate limiting)
     */
    public static function checkRateLimit($key, $maxRequests = 100, $timeWindow = 3600)
    {
        $cacheKey = 'rate_limit_' . md5($key);
        $cache = \Bitrix\Main\Application::getInstance()->getCache();
        
        if ($cache->startDataCache($timeWindow, $cacheKey)) {
            $requests = 1;
            $cache->endDataCache($requests);
            return true;
        } else {
            $requests = $cache->getVars();
            $requests++;
            
            if ($requests > $maxRequests) {
                Logger::warning("Rate limit exceeded for key: {$key}");
                return false;
            }
            
            $cache->abortDataCache();
            $cache->startDataCache($timeWindow, $cacheKey);
            $cache->endDataCache($requests);
            return true;
        }
    }

    /**
     * Логирование подозрительной активности
     */
    public static function logSuspiciousActivity($type, $details = [], $userId = null)
    {
        $context = array_merge([
            'type' => $type,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ], $details);

        Logger::warning("Suspicious activity: {$type}", $context, $userId);

        // Дополнительные действия при критических нарушениях
        if (in_array($type, ['SQL_INJECTION', 'XSS_ATTEMPT', 'UNAUTHORIZED_ACCESS'])) {
            // Можно добавить блокировку IP, отправку уведомлений и т.д.
            self::handleCriticalSecurity($type, $context, $userId);
        }
    }

    /**
     * Обработка критических нарушений безопасности
     */
    private static function handleCriticalSecurity($type, $context, $userId)
    {
        // Блокировка IP на время
        $ip = $context['ip'];
        if ($ip !== 'unknown') {
            self::blockIpTemporarily($ip, 3600); // Блокировка на час
        }

        // Принудительный выход пользователя
        if ($userId) {
            global $USER;
            if (is_object($USER) && $USER->GetID() == $userId) {
                $USER->Logout();
            }
        }

        // Отправка уведомления администраторам
        self::notifyAdminsAboutSecurity($type, $context);
    }

    /**
     * Временная блокировка IP
     */
    private static function blockIpTemporarily($ip, $duration)
    {
        $cacheKey = 'blocked_ip_' . md5($ip);
        $cache = \Bitrix\Main\Application::getInstance()->getCache();
        
        $cache->startDataCache($duration, $cacheKey);
        $cache->endDataCache(time());
    }

    /**
     * Проверка блокировки IP
     */
    public static function isIpBlocked($ip = null)
    {
        if (!$ip) {
            $ip = self::getClientIp();
        }

        $cacheKey = 'blocked_ip_' . md5($ip);
        $cache = \Bitrix\Main\Application::getInstance()->getCache();
        
        return $cache->initCache(3600, $cacheKey) && $cache->getVars();
    }

    /**
     * Уведомление администраторов о нарушении безопасности
     */
    private static function notifyAdminsAboutSecurity($type, $context)
    {
        // Получаем email администраторов
        $adminEmail = \Bitrix\Main\Config\Option::get('courier.delivery', 'notification_email', '');
        
        if ($adminEmail) {
            $subject = "Нарушение безопасности в системе курьерской доставки";
            $message = "Обнаружено нарушение безопасности типа: {$type}\n\n";
            $message .= "Детали:\n" . print_r($context, true);
            
            mail($adminEmail, $subject, $message);
        }
    }

    /**
     * Получение IP адреса клиента
     */
    private static function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Создание подписи для данных
     */
    public static function signData($data, $secret = null)
    {
        if (!$secret) {
            $secret = self::$salt;
        }

        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Проверка подписи данных
     */
    public static function verifySignature($data, $signature, $secret = null)
    {
        if (!$secret) {
            $secret = self::$salt;
        }

        $expectedSignature = hash_hmac('sha256', $data, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Безопасное сравнение строк
     */
    public static function safeStringCompare($str1, $str2)
    {
        return hash_equals($str1, $str2);
    }

    /**
     * Проверка на SQL инъекции
     */
    public static function checkSqlInjection($input)
    {
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\'|\")(\s*)(OR|AND)(\s*)(\'|\")/i',
            '/(\-\-|\#|\/\*)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSuspiciousActivity('SQL_INJECTION', ['input' => $input]);
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка на XSS
     */
    public static function checkXss($input)
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<\w+[^>]*\s+on\w+\s*=/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSuspiciousActivity('XSS_ATTEMPT', ['input' => $input]);
                return true;
            }
        }

        return false;
    }

    /**
     * Очистка входных данных от потенциально опасного контента
     */
    public static function cleanInput($input)
    {
        // Проверяем на SQL инъекции и XSS
        if (self::checkSqlInjection($input) || self::checkXss($input)) {
            return '';
        }

        // Базовая очистка
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
}