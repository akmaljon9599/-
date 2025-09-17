<?php

namespace CourierService\Service;

use Bitrix\Main\Web\Json;
use CourierService\Entity\LogTable;

class LogService
{
    public static function log($action, $entityType, $entityId, $data = null, $userId = null)
    {
        global $USER;
        
        if (!$userId) {
            $userId = $USER->GetID();
        }

        $logData = [
            'USER_ID' => $userId,
            'ACTION' => $action,
            'ENTITY_TYPE' => $entityType,
            'ENTITY_ID' => $entityId,
            'DATA' => $data ? Json::encode($data) : null,
            'IP_ADDRESS' => self::getClientIpAddress(),
            'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
        ];

        try {
            LogTable::add($logData);
        } catch (\Exception $e) {
            // Логируем ошибку в системный лог
            error_log('Courier Service Log Error: ' . $e->getMessage());
        }
    }

    public static function getLogs($filter = [], $limit = 100, $offset = 0)
    {
        $result = LogTable::getList([
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset
        ]);

        $logs = [];
        while ($row = $result->fetch()) {
            $logs[] = [
                'id' => $row['ID'],
                'user_id' => $row['USER_ID'],
                'action' => $row['ACTION'],
                'action_text' => LogTable::getActions()[$row['ACTION']] ?? $row['ACTION'],
                'entity_type' => $row['ENTITY_TYPE'],
                'entity_type_text' => LogTable::getEntityTypes()[$row['ENTITY_TYPE']] ?? $row['ENTITY_TYPE'],
                'entity_id' => $row['ENTITY_ID'],
                'data' => $row['DATA'] ? Json::decode($row['DATA']) : null,
                'ip_address' => $row['IP_ADDRESS'],
                'user_agent' => $row['USER_AGENT'],
                'created_at' => $row['CREATED_AT']->format('Y-m-d H:i:s')
            ];
        }

        return $logs;
    }

    public static function getLogsByUser($userId, $limit = 50)
    {
        return self::getLogs(['USER_ID' => $userId], $limit);
    }

    public static function getLogsByEntity($entityType, $entityId, $limit = 50)
    {
        return self::getLogs([
            'ENTITY_TYPE' => $entityType,
            'ENTITY_ID' => $entityId
        ], $limit);
    }

    public static function getLogsByAction($action, $limit = 50)
    {
        return self::getLogs(['ACTION' => $action], $limit);
    }

    public static function getLogsByDateRange($dateFrom, $dateTo, $limit = 100)
    {
        return self::getLogs([
            '>=CREATED_AT' => $dateFrom,
            '<=CREATED_AT' => $dateTo
        ], $limit);
    }

    public static function getLogsStatistics($dateFrom = null, $dateTo = null)
    {
        $filter = [];
        if ($dateFrom) {
            $filter['>=CREATED_AT'] = $dateFrom;
        }
        if ($dateTo) {
            $filter['<=CREATED_AT'] = $dateTo;
        }

        // Общее количество логов
        $totalResult = LogTable::getList([
            'filter' => $filter,
            'select' => ['CNT'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);
        $total = $totalResult->fetch()['CNT'];

        // Статистика по действиям
        $actionsResult = LogTable::getList([
            'filter' => $filter,
            'select' => ['ACTION', 'CNT'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ],
            'group' => ['ACTION']
        ]);

        $actionsStats = [];
        while ($row = $actionsResult->fetch()) {
            $actionsStats[$row['ACTION']] = $row['CNT'];
        }

        // Статистика по пользователям
        $usersResult = LogTable::getList([
            'filter' => $filter,
            'select' => ['USER_ID', 'CNT'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ],
            'group' => ['USER_ID'],
            'order' => ['CNT' => 'DESC'],
            'limit' => 10
        ]);

        $usersStats = [];
        while ($row = $usersResult->fetch()) {
            $usersStats[$row['USER_ID']] = $row['CNT'];
        }

        return [
            'total' => $total,
            'actions' => $actionsStats,
            'users' => $usersStats
        ];
    }

    public static function cleanOldLogs($days = 90)
    {
        $dateThreshold = new \Bitrix\Main\Type\DateTime();
        $dateThreshold->add('-P' . $days . 'D');

        $result = LogTable::getList([
            'filter' => ['<CREATED_AT' => $dateThreshold],
            'select' => ['ID']
        ]);

        $deletedCount = 0;
        while ($row = $result->fetch()) {
            LogTable::delete($row['ID']);
            $deletedCount++;
        }

        return $deletedCount;
    }

    public static function exportLogs($filter = [], $format = 'json')
    {
        $logs = self::getLogs($filter, 10000); // Максимум 10000 записей

        switch ($format) {
            case 'json':
                return Json::encode($logs);
            case 'csv':
                return self::convertToCsv($logs);
            default:
                return $logs;
        }
    }

    private static function convertToCsv($logs)
    {
        if (empty($logs)) {
            return '';
        }

        $csv = '';
        $headers = array_keys($logs[0]);
        $csv .= implode(',', $headers) . "\n";

        foreach ($logs as $log) {
            $row = [];
            foreach ($headers as $header) {
                $value = $log[$header] ?? '';
                if (is_array($value)) {
                    $value = Json::encode($value);
                }
                $row[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csv .= implode(',', $row) . "\n";
        }

        return $csv;
    }

    private static function getClientIpAddress()
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
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}