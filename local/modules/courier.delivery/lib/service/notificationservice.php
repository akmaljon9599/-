<?php
namespace Courier\Delivery\Service;

use Courier\Delivery\Api\SmsService;
use Courier\Delivery\DeliveryTable;
use Courier\Delivery\CourierTable;
use Courier\Delivery\Util\Logger;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;

/**
 * Сервис для отправки уведомлений
 */
class NotificationService
{
    private static $smsService;

    private static function getSmsService()
    {
        if (!self::$smsService) {
            self::$smsService = new SmsService();
        }
        return self::$smsService;
    }

    /**
     * Отправить уведомление о изменении статуса заявки
     */
    public static function sendDeliveryStatusNotification($requestId, $newStatus, $clientPhone = null)
    {
        try {
            // Получаем данные заявки если не переданы
            if (!$clientPhone) {
                $request = DeliveryTable::getById($requestId)->fetch();
                if (!$request) {
                    return false;
                }
                $clientPhone = $request['CLIENT_PHONE'];
            }

            // Отправляем SMS клиенту
            $smsResult = self::getSmsService()->sendDeliveryStatusNotification(
                $clientPhone, 
                $newStatus, 
                $requestId
            );

            if ($smsResult['success']) {
                Logger::info("Status notification sent to client", [
                    'request_id' => $requestId,
                    'status' => $newStatus,
                    'phone' => $clientPhone
                ]);
            } else {
                Logger::warning("Failed to send status notification", [
                    'request_id' => $requestId,
                    'status' => $newStatus,
                    'phone' => $clientPhone,
                    'error' => $smsResult['error']
                ]);
            }

            // Отправляем email администраторам для критических статусов
            if (in_array($newStatus, ['REJECTED', 'CANCELLED'])) {
                self::sendEmailToAdmins('status_change', [
                    'REQUEST_ID' => $requestId,
                    'NEW_STATUS' => $newStatus,
                    'CLIENT_PHONE' => $clientPhone
                ]);
            }

            return $smsResult['success'];

        } catch (\Exception $e) {
            Logger::error('Error sending delivery status notification: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'status' => $newStatus
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление курьеру
     */
    public static function sendCourierNotification($courierPhone, $type, $data = [])
    {
        try {
            $smsResult = self::getSmsService()->sendCourierNotification($courierPhone, $type, $data);

            if ($smsResult['success']) {
                Logger::info("Courier notification sent", [
                    'type' => $type,
                    'phone' => $courierPhone,
                    'data' => $data
                ]);
            } else {
                Logger::warning("Failed to send courier notification", [
                    'type' => $type,
                    'phone' => $courierPhone,
                    'error' => $smsResult['error']
                ]);
            }

            return $smsResult['success'];

        } catch (\Exception $e) {
            Logger::error('Error sending courier notification: ' . $e->getMessage(), [
                'type' => $type,
                'phone' => $courierPhone
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление о назначении курьера
     */
    public static function sendCourierAssignmentNotification($requestId, $courierId)
    {
        try {
            $request = DeliveryTable::getById($requestId)->fetch();
            $courier = CourierTable::getById($courierId)->fetch();

            if (!$request || !$courier) {
                return false;
            }

            // SMS курьеру
            $courierResult = self::sendCourierNotification($courier['PHONE'], 'NEW_DELIVERY', [
                'request_id' => $requestId,
                'address' => $request['DELIVERY_ADDRESS'],
                'client_name' => $request['CLIENT_NAME'],
                'client_phone' => $request['CLIENT_PHONE']
            ]);

            // SMS клиенту о назначении курьера
            $clientMessage = "К вашей заявке №{$requestId} назначен курьер {$courier['FULL_NAME']}. " .
                           "Телефон курьера: {$courier['PHONE']}";
            
            $clientResult = self::getSmsService()->sendSms($request['CLIENT_PHONE'], $clientMessage);

            // Email администраторам
            self::sendEmailToAdmins('courier_assigned', [
                'REQUEST_ID' => $requestId,
                'COURIER_NAME' => $courier['FULL_NAME'],
                'CLIENT_NAME' => $request['CLIENT_NAME'],
                'DELIVERY_ADDRESS' => $request['DELIVERY_ADDRESS']
            ]);

            return $courierResult && $clientResult['success'];

        } catch (\Exception $e) {
            Logger::error('Error sending courier assignment notification: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'courier_id' => $courierId
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление о доставке
     */
    public static function sendDeliveryCompletedNotification($requestId, $documentsUploaded = false)
    {
        try {
            $request = DeliveryTable::getById($requestId)->fetch();
            if (!$request) {
                return false;
            }

            // SMS клиенту
            $clientMessage = "Ваша карта по заявке №{$requestId} успешно доставлена. ";
            if ($documentsUploaded) {
                $clientMessage .= "Документы загружены в систему. ";
            }
            $clientMessage .= "Спасибо за обращение в наш банк!";

            $clientResult = self::getSmsService()->sendSms($request['CLIENT_PHONE'], $clientMessage);

            // Email отчет администраторам
            self::sendEmailToAdmins('delivery_completed', [
                'REQUEST_ID' => $requestId,
                'CLIENT_NAME' => $request['CLIENT_NAME'],
                'DELIVERY_ADDRESS' => $request['DELIVERY_ADDRESS'],
                'DOCUMENTS_UPLOADED' => $documentsUploaded ? 'Да' : 'Нет'
            ]);

            return $clientResult['success'];

        } catch (\Exception $e) {
            Logger::error('Error sending delivery completed notification: ' . $e->getMessage(), [
                'request_id' => $requestId
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление о проблеме с доставкой
     */
    public static function sendDeliveryProblemNotification($requestId, $problem, $courierId = null)
    {
        try {
            $request = DeliveryTable::getById($requestId)->fetch();
            if (!$request) {
                return false;
            }

            $courier = null;
            if ($courierId) {
                $courier = CourierTable::getById($courierId)->fetch();
            }

            // SMS администраторам/старшим курьерам
            $adminMessage = "ПРОБЛЕМА с доставкой №{$requestId}: {$problem}. ";
            $adminMessage .= "Клиент: {$request['CLIENT_NAME']}, тел: {$request['CLIENT_PHONE']}";
            if ($courier) {
                $adminMessage .= ". Курьер: {$courier['FULL_NAME']}";
            }

            // Получаем телефоны администраторов
            $adminPhones = self::getAdminPhones();
            foreach ($adminPhones as $phone) {
                self::getSmsService()->sendSms($phone, $adminMessage);
            }

            // Email уведомление
            self::sendEmailToAdmins('delivery_problem', [
                'REQUEST_ID' => $requestId,
                'PROBLEM' => $problem,
                'CLIENT_NAME' => $request['CLIENT_NAME'],
                'CLIENT_PHONE' => $request['CLIENT_PHONE'],
                'COURIER_NAME' => $courier ? $courier['FULL_NAME'] : 'Не назначен'
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Error sending delivery problem notification: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'problem' => $problem
            ]);
            return false;
        }
    }

    /**
     * Отправить ежедневный отчет
     */
    public static function sendDailyReport($date = null)
    {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }

            // Получаем статистику за день
            $stats = DeliveryTable::getStatistics($date . ' 00:00:00', $date . ' 23:59:59');
            
            // Получаем проблемные заявки
            $problemRequests = DeliveryTable::getList([
                'select' => ['ID', 'CLIENT_NAME', 'STATUS'],
                'filter' => [
                    '>=CREATED_DATE' => $date . ' 00:00:00',
                    '<=CREATED_DATE' => $date . ' 23:59:59',
                    'STATUS' => ['REJECTED', 'CANCELLED']
                ]
            ]);

            $problems = [];
            while ($problem = $problemRequests->fetch()) {
                $problems[] = $problem;
            }

            // Отправляем email отчет
            self::sendEmailToAdmins('daily_report', [
                'DATE' => date('d.m.Y', strtotime($date)),
                'TOTAL_REQUESTS' => $stats['total'],
                'DELIVERED' => $stats['delivered'],
                'IN_PROCESS' => $stats['in_delivery'] + $stats['assigned'],
                'REJECTED' => $stats['rejected'],
                'CANCELLED' => $stats['cancelled'],
                'PROBLEMS' => $problems
            ]);

            Logger::info("Daily report sent for date: {$date}", $stats);
            return true;

        } catch (\Exception $e) {
            Logger::error('Error sending daily report: ' . $e->getMessage(), [
                'date' => $date
            ]);
            return false;
        }
    }

    /**
     * Отправить уведомление о превышении времени доставки
     */
    public static function sendDeliveryTimeoutNotification($requestId)
    {
        try {
            $request = DeliveryTable::getById($requestId)->fetch();
            if (!$request) {
                return false;
            }

            $courier = null;
            if ($request['COURIER_ID']) {
                $courier = CourierTable::getById($request['COURIER_ID'])->fetch();
            }

            // Уведомление администраторам
            $message = "ПРЕВЫШЕНО время доставки для заявки №{$requestId}. ";
            $message .= "Клиент: {$request['CLIENT_NAME']}, адрес: {$request['DELIVERY_ADDRESS']}";
            if ($courier) {
                $message .= ". Курьер: {$courier['FULL_NAME']}, тел: {$courier['PHONE']}";
            }

            // SMS администраторам
            $adminPhones = self::getAdminPhones();
            foreach ($adminPhones as $phone) {
                self::getSmsService()->sendSms($phone, $message);
            }

            // Email
            self::sendEmailToAdmins('delivery_timeout', [
                'REQUEST_ID' => $requestId,
                'CLIENT_NAME' => $request['CLIENT_NAME'],
                'DELIVERY_ADDRESS' => $request['DELIVERY_ADDRESS'],
                'COURIER_NAME' => $courier ? $courier['FULL_NAME'] : 'Не назначен',
                'COURIER_PHONE' => $courier ? $courier['PHONE'] : ''
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Error sending delivery timeout notification: ' . $e->getMessage(), [
                'request_id' => $requestId
            ]);
            return false;
        }
    }

    /**
     * Отправить email администраторам
     */
    private static function sendEmailToAdmins($eventType, $fields = [])
    {
        try {
            $adminEmail = Option::get('courier.delivery', 'notification_email', '');
            if (empty($adminEmail)) {
                return false;
            }

            // Определяем тип почтового события
            $mailEvents = [
                'status_change' => 'COURIER_STATUS_CHANGE',
                'courier_assigned' => 'COURIER_ASSIGNED',
                'delivery_completed' => 'COURIER_DELIVERY_COMPLETED',
                'delivery_problem' => 'COURIER_DELIVERY_PROBLEM',
                'daily_report' => 'COURIER_DAILY_REPORT',
                'delivery_timeout' => 'COURIER_DELIVERY_TIMEOUT'
            ];

            $eventName = $mailEvents[$eventType] ?? 'COURIER_NOTIFICATION';

            // Отправляем email
            Event::send([
                'EVENT_NAME' => $eventName,
                'LID' => SITE_ID,
                'C_FIELDS' => array_merge($fields, [
                    'EMAIL_TO' => $adminEmail,
                    'TIMESTAMP' => date('d.m.Y H:i:s')
                ])
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Error sending email to admins: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'fields' => $fields
            ]);
            return false;
        }
    }

    /**
     * Получить телефоны администраторов
     */
    private static function getAdminPhones()
    {
        // Здесь должна быть логика получения телефонов администраторов из настроек или БД
        $phones = Option::get('courier.delivery', 'admin_phones', '');
        
        if (empty($phones)) {
            return [];
        }

        return array_filter(explode(',', $phones));
    }

    /**
     * Обработать запланированные уведомления
     */
    public static function processScheduledNotifications()
    {
        try {
            // Проверяем заявки с превышением времени доставки
            $timeoutRequests = DeliveryTable::getList([
                'select' => ['ID'],
                'filter' => [
                    'STATUS' => 'IN_DELIVERY',
                    '<PROCESSING_DATE' => date('Y-m-d H:i:s', strtotime('-4 hours'))
                ]
            ]);

            while ($request = $timeoutRequests->fetch()) {
                self::sendDeliveryTimeoutNotification($request['ID']);
            }

            // Проверяем необходимость отправки ежедневного отчета
            $lastReportDate = Option::get('courier.delivery', 'last_daily_report_date', '');
            $today = date('Y-m-d');

            if ($lastReportDate !== $today && date('H') >= 20) { // Отправляем после 20:00
                self::sendDailyReport($today);
                Option::set('courier.delivery', 'last_daily_report_date', $today);
            }

            return true;

        } catch (\Exception $e) {
            Logger::error('Error processing scheduled notifications: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Отправить массовое уведомление курьерам
     */
    public static function sendBulkNotificationToCouriers($message, $branchId = null)
    {
        try {
            $filter = ['IS_ACTIVE' => 'Y', 'STATUS' => 'ONLINE'];
            if ($branchId) {
                $filter['BRANCH_ID'] = $branchId;
            }

            $couriers = CourierTable::getList([
                'select' => ['ID', 'PHONE', 'FULL_NAME'],
                'filter' => $filter
            ]);

            $sentCount = 0;
            while ($courier = $couriers->fetch()) {
                $result = self::getSmsService()->sendSms($courier['PHONE'], $message);
                if ($result['success']) {
                    $sentCount++;
                }
            }

            Logger::info("Bulk notification sent to couriers", [
                'message' => $message,
                'branch_id' => $branchId,
                'sent_count' => $sentCount
            ]);

            return $sentCount;

        } catch (\Exception $e) {
            Logger::error('Error sending bulk notification to couriers: ' . $e->getMessage(), [
                'message' => $message,
                'branch_id' => $branchId
            ]);
            return 0;
        }
    }

    /**
     * Получить статистику отправленных уведомлений
     */
    public static function getNotificationStatistics($dateFrom, $dateTo = null)
    {
        try {
            return self::getSmsService()->getStatistics($dateFrom, $dateTo);
        } catch (\Exception $e) {
            Logger::error('Error getting notification statistics: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Ошибка получения статистики уведомлений'
            ];
        }
    }

    /**
     * Проверить баланс SMS-сервиса
     */
    public static function checkSmsBalance()
    {
        try {
            return self::getSmsService()->getBalance();
        } catch (\Exception $e) {
            Logger::error('Error checking SMS balance: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Ошибка проверки баланса SMS-сервиса'
            ];
        }
    }
}