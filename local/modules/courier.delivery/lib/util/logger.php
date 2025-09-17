<?php
namespace Courier\Delivery\Util;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;

/**
 * Класс для логирования действий в системе
 */
class Logger
{
    const LOG_LEVEL_ERROR = 'ERROR';
    const LOG_LEVEL_WARNING = 'WARNING';
    const LOG_LEVEL_INFO = 'INFO';
    const LOG_LEVEL_DEBUG = 'DEBUG';

    private static $logFile = '/upload/logs/courier_delivery.log';
    private static $maxFileSize = 10485760; // 10MB
    private static $maxFiles = 5;

    /**
     * Записать сообщение в лог
     */
    public static function log($message, $type = 'INFO', $userId = null, $context = [])
    {
        global $USER;

        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'message' => $message,
            'user_id' => $userId,
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'context' => $context
        ];

        self::writeToFile($logEntry);
        
        // Дублируем критические ошибки в системный лог Битрикса
        if ($type === self::LOG_LEVEL_ERROR) {
            \AddMessage2Log($message, 'courier.delivery');
        }
    }

    /**
     * Логировать ошибку
     */
    public static function error($message, $context = [], $userId = null)
    {
        self::log($message, self::LOG_LEVEL_ERROR, $userId, $context);
    }

    /**
     * Логировать предупреждение
     */
    public static function warning($message, $context = [], $userId = null)
    {
        self::log($message, self::LOG_LEVEL_WARNING, $userId, $context);
    }

    /**
     * Логировать информационное сообщение
     */
    public static function info($message, $context = [], $userId = null)
    {
        self::log($message, self::LOG_LEVEL_INFO, $userId, $context);
    }

    /**
     * Логировать отладочное сообщение
     */
    public static function debug($message, $context = [], $userId = null)
    {
        // Отладочные сообщения пишем только в режиме разработки
        if (defined('BX_DEBUG') && BX_DEBUG) {
            self::log($message, self::LOG_LEVEL_DEBUG, $userId, $context);
        }
    }

    /**
     * Логировать действие пользователя
     */
    public static function logUserAction($action, $details = [], $userId = null)
    {
        global $USER;

        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $message = "User action: {$action}";
        if (!empty($details)) {
            $message .= " - " . Json::encode($details);
        }

        self::log($message, 'USER_ACTION', $userId, $details);
    }

    /**
     * Логировать API запрос
     */
    public static function logApiRequest($method, $endpoint, $requestData = [], $responseData = [], $httpCode = 200)
    {
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'http_code' => $httpCode,
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];

        $logLevel = $httpCode >= 400 ? self::LOG_LEVEL_ERROR : self::LOG_LEVEL_INFO;
        $message = "API Request: {$method} {$endpoint} - HTTP {$httpCode}";

        self::log($message, $logLevel, null, $context);
    }

    /**
     * Логировать изменение статуса заявки
     */
    public static function logStatusChange($requestId, $oldStatus, $newStatus, $comment = '', $userId = null)
    {
        $context = [
            'request_id' => $requestId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment
        ];

        $message = "Status changed for request #{$requestId}: {$oldStatus} -> {$newStatus}";
        if ($comment) {
            $message .= " ({$comment})";
        }

        self::log($message, 'STATUS_CHANGE', $userId, $context);
    }

    /**
     * Логировать ошибку интеграции
     */
    public static function logIntegrationError($service, $operation, $error, $requestData = [])
    {
        $context = [
            'service' => $service,
            'operation' => $operation,
            'error' => $error,
            'request_data' => $requestData
        ];

        $message = "Integration error with {$service}: {$operation} - {$error}";

        self::log($message, self::LOG_LEVEL_ERROR, null, $context);
    }

    /**
     * Записать в файл
     */
    private static function writeToFile($logEntry)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . self::$logFile;
        $logDir = dirname($logFile);

        // Создаем директорию если не существует
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Ротация логов при превышении размера
        if (file_exists($logFile) && filesize($logFile) > self::$maxFileSize) {
            self::rotateLogs($logFile);
        }

        // Форматируем запись
        $formattedEntry = self::formatLogEntry($logEntry);

        // Записываем в файл
        file_put_contents($logFile, $formattedEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Форматировать запись лога
     */
    private static function formatLogEntry($entry)
    {
        $formatted = "[{$entry['timestamp']}] [{$entry['type']}]";
        
        if ($entry['user_id']) {
            $formatted .= " [User:{$entry['user_id']}]";
        }
        
        $formatted .= " {$entry['message']}";
        
        if (!empty($entry['context'])) {
            $formatted .= " | Context: " . Json::encode($entry['context']);
        }
        
        if ($entry['ip']) {
            $formatted .= " | IP: {$entry['ip']}";
        }

        return $formatted;
    }

    /**
     * Ротация логов
     */
    private static function rotateLogs($logFile)
    {
        $logDir = dirname($logFile);
        $logName = basename($logFile, '.log');

        // Сдвигаем существующие файлы
        for ($i = self::$maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $logDir . '/' . $logName . '.' . $i . '.log';
            $newFile = $logDir . '/' . $logName . '.' . ($i + 1) . '.log';
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // Переименовываем текущий файл
        $archiveFile = $logDir . '/' . $logName . '.1.log';
        rename($logFile, $archiveFile);

        // Удаляем старые файлы
        $oldFile = $logDir . '/' . $logName . '.' . (self::$maxFiles + 1) . '.log';
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    /**
     * Получить IP адрес клиента
     */
    private static function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Обрабатываем случай с несколькими IP через запятую
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Валидируем IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Получить записи из лога
     */
    public static function getLogEntries($limit = 100, $offset = 0, $level = null, $dateFrom = null, $dateTo = null)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . self::$logFile;
        
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            $entry = self::parseLogEntry($line);
            
            if (!$entry) {
                continue;
            }

            // Фильтрация по уровню
            if ($level && $entry['level'] !== $level) {
                continue;
            }

            // Фильтрация по дате
            if ($dateFrom && $entry['timestamp'] < $dateFrom) {
                continue;
            }

            if ($dateTo && $entry['timestamp'] > $dateTo) {
                continue;
            }

            $entries[] = $entry;

            // Ограничение количества
            if (count($entries) >= ($offset + $limit)) {
                break;
            }
        }

        return array_slice($entries, $offset, $limit);
    }

    /**
     * Парсить запись лога
     */
    private static function parseLogEntry($line)
    {
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\](?: \[User:(\d+)\])? (.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            $message = $matches[4];
            $context = null;
            $ip = null;

            // Извлекаем контекст и IP если есть
            if (strpos($message, ' | Context: ') !== false) {
                $parts = explode(' | Context: ', $message, 2);
                $message = $parts[0];
                
                $contextPart = $parts[1];
                if (strpos($contextPart, ' | IP: ') !== false) {
                    $contextParts = explode(' | IP: ', $contextPart, 2);
                    $contextJson = $contextParts[0];
                    $ip = $contextParts[1];
                } else {
                    $contextJson = $contextPart;
                }

                try {
                    $context = Json::decode($contextJson);
                } catch (\Exception $e) {
                    // Игнорируем ошибки парсинга контекста
                }
            } elseif (strpos($message, ' | IP: ') !== false) {
                $parts = explode(' | IP: ', $message, 2);
                $message = $parts[0];
                $ip = $parts[1];
            }

            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'user_id' => $matches[3] ?? null,
                'message' => $message,
                'context' => $context,
                'ip' => $ip
            ];
        }

        return null;
    }

    /**
     * Очистить старые логи
     */
    public static function cleanOldLogs($daysToKeep = 30)
    {
        $logDir = $_SERVER['DOCUMENT_ROOT'] . dirname(self::$logFile);
        $cutoffDate = time() - ($daysToKeep * 24 * 60 * 60);

        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffDate) {
                    unlink($file);
                }
            }
        }

        self::log("Old logs cleaned (older than {$daysToKeep} days)", self::LOG_LEVEL_INFO);
    }

    /**
     * Получить статистику логов
     */
    public static function getLogStatistics($dateFrom = null, $dateTo = null)
    {
        $entries = self::getLogEntries(10000, 0, null, $dateFrom, $dateTo);
        
        $stats = [
            'total' => 0,
            'by_level' => [
                self::LOG_LEVEL_ERROR => 0,
                self::LOG_LEVEL_WARNING => 0,
                self::LOG_LEVEL_INFO => 0,
                self::LOG_LEVEL_DEBUG => 0
            ],
            'by_type' => [],
            'by_user' => [],
            'by_date' => []
        ];

        foreach ($entries as $entry) {
            $stats['total']++;
            
            // По уровням
            if (isset($stats['by_level'][$entry['level']])) {
                $stats['by_level'][$entry['level']]++;
            }
            
            // По типам (извлекаем из сообщения)
            if (preg_match('/^([A-Z_]+):/', $entry['message'], $matches)) {
                $type = $matches[1];
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            }
            
            // По пользователям
            if ($entry['user_id']) {
                $stats['by_user'][$entry['user_id']] = ($stats['by_user'][$entry['user_id']] ?? 0) + 1;
            }
            
            // По датам
            $date = substr($entry['timestamp'], 0, 10);
            $stats['by_date'][$date] = ($stats['by_date'][$date] ?? 0) + 1;
        }

        return $stats;
    }
}