<?php
namespace CourierService\Security;

use Bitrix\Main\Type\DateTime;

class AuditLogger
{
    /**
     * Логирование действия пользователя
     */
    public static function logAction($userId, $action, $entityType, $entityId, $oldData = null, $newData = null, $additionalInfo = [])
    {
        try {
            $ipAddress = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $logData = [
                'USER_ID' => $userId,
                'ACTION' => $action,
                'ENTITY_TYPE' => $entityType,
                'ENTITY_ID' => $entityId,
                'OLD_DATA' => $oldData ? json_encode($oldData) : null,
                'NEW_DATA' => $newData ? json_encode($newData) : null,
                'IP_ADDRESS' => $ipAddress,
                'USER_AGENT' => $userAgent,
                'CREATED_DATE' => new DateTime(),
                'ADDITIONAL_INFO' => !empty($additionalInfo) ? json_encode($additionalInfo) : null
            ];

            \CourierService\Main\LogTable::add($logData);

        } catch (\Exception $e) {
            // Логируем ошибку в системный лог
            error_log('AuditLogger error: ' . $e->getMessage());
        }
    }

    /**
     * Логирование ошибки
     */
    public static function logError($module, $action, $errorMessage, $additionalData = [])
    {
        try {
            $userId = $GLOBALS['USER']->GetID() ?? 0;

            $logData = [
                'USER_ID' => $userId,
                'ACTION' => 'error',
                'ENTITY_TYPE' => $module,
                'ENTITY_ID' => 0,
                'OLD_DATA' => null,
                'NEW_DATA' => json_encode([
                    'error_message' => $errorMessage,
                    'action' => $action,
                    'additional_data' => $additionalData
                ]),
                'IP_ADDRESS' => self::getClientIp(),
                'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'CREATED_DATE' => new DateTime()
            ];

            \CourierService\Main\LogTable::add($logData);

        } catch (\Exception $e) {
            error_log('AuditLogger error logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Логирование входа в систему
     */
    public static function logLogin($userId, $success = true, $reason = null)
    {
        $action = $success ? 'login_success' : 'login_failed';
        
        self::logAction(
            $userId,
            $action,
            'user',
            $userId,
            null,
            ['reason' => $reason],
            ['timestamp' => time()]
        );
    }

    /**
     * Логирование выхода из системы
     */
    public static function logLogout($userId)
    {
        self::logAction(
            $userId,
            'logout',
            'user',
            $userId,
            null,
            null,
            ['timestamp' => time()]
        );
    }

    /**
     * Логирование изменения заявки
     */
    public static function logRequestChange($userId, $requestId, $action, $oldData = null, $newData = null)
    {
        self::logAction(
            $userId,
            $action,
            'request',
            $requestId,
            $oldData,
            $newData
        );
    }

    /**
     * Логирование изменения курьера
     */
    public static function logCourierChange($userId, $courierId, $action, $oldData = null, $newData = null)
    {
        self::logAction(
            $userId,
            $action,
            'courier',
            $courierId,
            $oldData,
            $newData
        );
    }

    /**
     * Логирование загрузки документа
     */
    public static function logDocumentUpload($userId, $requestId, $documentType, $filename, $fileSize)
    {
        self::logAction(
            $userId,
            'document_upload',
            'document',
            0,
            null,
            [
                'request_id' => $requestId,
                'document_type' => $documentType,
                'filename' => $filename,
                'file_size' => $fileSize
            ]
        );
    }

    /**
     * Логирование экспорта данных
     */
    public static function logDataExport($userId, $exportType, $filters = [], $recordCount = 0)
    {
        self::logAction(
            $userId,
            'data_export',
            'export',
            0,
            null,
            [
                'export_type' => $exportType,
                'filters' => $filters,
                'record_count' => $recordCount
            ]
        );
    }

    /**
     * Логирование изменения настроек
     */
    public static function logSettingsChange($userId, $settingKey, $oldValue, $newValue)
    {
        self::logAction(
            $userId,
            'settings_change',
            'setting',
            0,
            ['key' => $settingKey, 'value' => $oldValue],
            ['key' => $settingKey, 'value' => $newValue]
        );
    }

    /**
     * Получение IP адреса клиента
     */
    private static function getClientIp()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Получение логов с фильтрацией
     */
    public static function getLogs($filters = [], $limit = 100, $offset = 0)
    {
        try {
            $query = \CourierService\Main\LogTable::getList([
                'filter' => $filters,
                'order' => ['CREATED_DATE' => 'DESC'],
                'limit' => $limit,
                'offset' => $offset
            ]);

            $logs = [];
            while ($log = $query->fetch()) {
                $logs[] = [
                    'id' => $log['ID'],
                    'user_id' => $log['USER_ID'],
                    'action' => $log['ACTION'],
                    'entity_type' => $log['ENTITY_TYPE'],
                    'entity_id' => $log['ENTITY_ID'],
                    'old_data' => $log['OLD_DATA'] ? json_decode($log['OLD_DATA'], true) : null,
                    'new_data' => $log['NEW_DATA'] ? json_decode($log['NEW_DATA'], true) : null,
                    'ip_address' => $log['IP_ADDRESS'],
                    'user_agent' => $log['USER_AGENT'],
                    'created_date' => $log['CREATED_DATE']->format('Y-m-d H:i:s')
                ];
            }

            return [
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Очистка старых логов
     */
    public static function cleanOldLogs($daysToKeep = 90)
    {
        try {
            $cutoffDate = new DateTime();
            $cutoffDate->add('-P' . $daysToKeep . 'D');

            $result = \CourierService\Main\LogTable::getList([
                'filter' => ['<CREATED_DATE' => $cutoffDate],
                'select' => ['ID']
            ]);

            $deletedCount = 0;
            while ($log = $result->fetch()) {
                \CourierService\Main\LogTable::delete($log['ID']);
                $deletedCount++;
            }

            return [
                'success' => true,
                'deleted_count' => $deletedCount
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение статистики по логам
     */
    public static function getLogStats($period = 30)
    {
        try {
            $startDate = new DateTime();
            $startDate->add('-P' . $period . 'D');

            $stats = [];

            // Общее количество действий
            $totalActions = \CourierService\Main\LogTable::getList([
                'filter' => ['>=CREATED_DATE' => $startDate],
                'select' => ['ID']
            ])->getSelectedRowsCount();

            $stats['total_actions'] = $totalActions;

            // Действия по типам
            $actionTypes = \CourierService\Main\LogTable::getList([
                'filter' => ['>=CREATED_DATE' => $startDate],
                'select' => ['ACTION'],
                'group' => ['ACTION']
            ]);

            $stats['actions_by_type'] = [];
            while ($action = $actionTypes->fetch()) {
                $count = \CourierService\Main\LogTable::getList([
                    'filter' => [
                        '>=CREATED_DATE' => $startDate,
                        'ACTION' => $action['ACTION']
                    ],
                    'select' => ['ID']
                ])->getSelectedRowsCount();

                $stats['actions_by_type'][$action['ACTION']] = $count;
            }

            // Активные пользователи
            $activeUsers = \CourierService\Main\LogTable::getList([
                'filter' => ['>=CREATED_DATE' => $startDate],
                'select' => ['USER_ID'],
                'group' => ['USER_ID']
            ])->getSelectedRowsCount();

            $stats['active_users'] = $activeUsers;

            return [
                'success' => true,
                'stats' => $stats,
                'period' => $period
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}