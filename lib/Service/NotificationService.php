<?php

namespace CourierService\Service;

use Bitrix\Main\Config\Option;
use CourierService\Entity\RequestTable;
use CourierService\Entity\UserTable;

class NotificationService
{
    public static function sendRequestCreatedNotification($requestId)
    {
        $request = RequestTable::getById($requestId)->fetch();
        if (!$request) {
            return false;
        }

        // Получаем администраторов и старших курьеров
        $users = UserTable::getList([
            'filter' => [
                'ROLE' => ['admin', 'senior_courier'],
                'IS_ACTIVE' => true
            ],
            'select' => ['USER_ID', 'ROLE']
        ]);

        $message = "Создана новая заявка #{$request['REQUEST_NUMBER']} для клиента {$request['CLIENT_NAME']}";
        
        while ($user = $users->fetch()) {
            self::sendNotification($user['USER_ID'], $message, [
                'type' => 'request_created',
                'request_id' => $requestId,
                'request_number' => $request['REQUEST_NUMBER']
            ]);
        }

        return true;
    }

    public static function sendRequestUpdatedNotification($requestId)
    {
        $request = RequestTable::getById($requestId)->fetch();
        if (!$request) {
            return false;
        }

        $message = "Заявка #{$request['REQUEST_NUMBER']} была обновлена";
        
        // Уведомляем курьера если он назначен
        if ($request['COURIER_ID']) {
            self::sendNotification($request['COURIER_ID'], $message, [
                'type' => 'request_updated',
                'request_id' => $requestId,
                'request_number' => $request['REQUEST_NUMBER']
            ]);
        }

        // Уведомляем оператора
        if ($request['OPERATOR_ID']) {
            self::sendNotification($request['OPERATOR_ID'], $message, [
                'type' => 'request_updated',
                'request_id' => $requestId,
                'request_number' => $request['REQUEST_NUMBER']
            ]);
        }

        return true;
    }

    public static function sendStatusChangedNotification($requestId, $newStatus)
    {
        $request = RequestTable::getById($requestId)->fetch();
        if (!$request) {
            return false;
        }

        $statusText = RequestTable::getStatuses()[$newStatus] ?? $newStatus;
        $message = "Статус заявки #{$request['REQUEST_NUMBER']} изменен на: {$statusText}";

        // Уведомляем всех заинтересованных пользователей
        $users = UserTable::getList([
            'filter' => [
                'ROLE' => ['admin', 'senior_courier', 'operator'],
                'IS_ACTIVE' => true
            ],
            'select' => ['USER_ID', 'ROLE']
        ]);

        while ($user = $users->fetch()) {
            self::sendNotification($user['USER_ID'], $message, [
                'type' => 'status_changed',
                'request_id' => $requestId,
                'request_number' => $request['REQUEST_NUMBER'],
                'new_status' => $newStatus,
                'status_text' => $statusText
            ]);
        }

        // Отправляем SMS клиенту при доставке
        if ($newStatus === 'delivered') {
            self::sendSmsToClient($request['CLIENT_PHONE'], 
                "Ваша карта доставлена. Заявка #{$request['REQUEST_NUMBER']}");
        }

        return true;
    }

    public static function sendCourierAssignedNotification($requestId, $courierId)
    {
        $request = RequestTable::getById($requestId)->fetch();
        if (!$request) {
            return false;
        }

        $message = "Вам назначена заявка #{$request['REQUEST_NUMBER']} для доставки клиенту {$request['CLIENT_NAME']}";
        
        self::sendNotification($courierId, $message, [
            'type' => 'courier_assigned',
            'request_id' => $requestId,
            'request_number' => $request['REQUEST_NUMBER'],
            'client_name' => $request['CLIENT_NAME'],
            'client_address' => $request['CLIENT_ADDRESS']
        ]);

        return true;
    }

    public static function sendLocationUpdateNotification($courierId, $location)
    {
        // Уведомляем старших курьеров и администраторов о местоположении
        $users = UserTable::getList([
            'filter' => [
                'ROLE' => ['admin', 'senior_courier'],
                'IS_ACTIVE' => true
            ],
            'select' => ['USER_ID', 'ROLE']
        ]);

        $message = "Курьер обновил свое местоположение";
        
        while ($user = $users->fetch()) {
            self::sendNotification($user['USER_ID'], $message, [
                'type' => 'location_update',
                'courier_id' => $courierId,
                'location' => $location
            ]);
        }

        return true;
    }

    private static function sendNotification($userId, $message, $data = [])
    {
        // Отправка уведомления через Bitrix24
        if (class_exists('\Bitrix\Main\EventManager')) {
            \Bitrix\Main\EventManager::getInstance()->addEventHandler(
                'courier_service',
                'OnNotificationSend',
                'CourierService\\EventHandlers\\Notification',
                'onNotificationSend'
            );

            \Bitrix\Main\EventManager::getInstance()->send(
                new \Bitrix\Main\Event('courier_service', 'OnNotificationSend', [
                    'user_id' => $userId,
                    'message' => $message,
                    'data' => $data
                ])
            );
        }

        // Отправка через внутреннюю систему уведомлений
        self::saveNotificationToDatabase($userId, $message, $data);
    }

    private static function saveNotificationToDatabase($userId, $message, $data)
    {
        global $DB;

        $DB->Query("
            INSERT INTO `courier_service_notifications` 
            (`USER_ID`, `MESSAGE`, `DATA`, `IS_READ`, `CREATED_AT`) 
            VALUES 
            ('" . intval($userId) . "', '" . $DB->ForSql($message) . "', '" . $DB->ForSql(json_encode($data)) . "', 0, NOW())
        ");
    }

    private static function sendSmsToClient($phone, $message)
    {
        // Интеграция с SMS-сервисом
        $smsApiUrl = Option::get('courier_service', 'sms_api_url', '');
        $smsApiKey = Option::get('courier_service', 'sms_api_key', '');

        if (!$smsApiUrl || !$smsApiKey) {
            return false;
        }

        $data = [
            'phone' => $phone,
            'message' => $message,
            'api_key' => $smsApiKey
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $smsApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public static function sendEmailNotification($email, $subject, $message, $attachments = [])
    {
        // Отправка email уведомлений
        $mail = new \Bitrix\Main\Mail\Event();
        $mail->setEventType('COURIER_SERVICE_NOTIFICATION');
        $mail->setFields([
            'EMAIL' => $email,
            'SUBJECT' => $subject,
            'MESSAGE' => $message,
            'ATTACHMENTS' => $attachments
        ]);
        
        return $mail->send();
    }
}