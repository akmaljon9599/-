<?php
namespace Courier\Delivery\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Courier\Delivery\Util\Logger;

/**
 * Класс для интеграции с SMS-сервисом
 */
class SmsService
{
    private $serviceUrl;
    private $login;
    private $password;
    private $httpClient;

    public function __construct()
    {
        $this->serviceUrl = Option::get('courier.delivery', 'sms_service_url', '');
        $this->login = Option::get('courier.delivery', 'sms_service_login', '');
        $this->password = Option::get('courier.delivery', 'sms_service_password', '');
        
        $this->httpClient = new HttpClient([
            'timeout' => 15,
            'socketTimeout' => 15,
            'streamTimeout' => 15
        ]);
    }

    /**
     * Отправить SMS-сообщение
     */
    public function sendSms($phone, $message, $sender = null)
    {
        try {
            // Нормализуем номер телефона
            $phone = $this->normalizePhone($phone);
            
            if (!$this->validatePhone($phone)) {
                return [
                    'success' => false,
                    'error' => 'Некорректный номер телефона'
                ];
            }

            $url = rtrim($this->serviceUrl, '/') . '/api/sms/send';
            
            $data = [
                'login' => $this->login,
                'password' => $this->password,
                'phone' => $phone,
                'message' => $message,
                'sender' => $sender ?: 'BANK'
            ];

            $response = $this->httpClient->post($url, Json::encode($data));
            $responseData = Json::decode($response);

            if (isset($responseData['success']) && $responseData['success']) {
                Logger::log("SMS sent to {$phone}: {$message}", 'SMS_SENT');
                
                return [
                    'success' => true,
                    'message_id' => $responseData['message_id'] ?? null,
                    'cost' => $responseData['cost'] ?? null
                ];
            }

            $error = $responseData['error'] ?? 'Неизвестная ошибка SMS-сервиса';
            Logger::log("SMS send failed to {$phone}: {$error}", 'SMS_ERROR');

            return [
                'success' => false,
                'error' => $error
            ];

        } catch (\Exception $e) {
            Logger::log('SMS Service Error: ' . $e->getMessage(), 'SMS_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка отправки SMS'
            ];
        }
    }

    /**
     * Получить статус SMS-сообщения
     */
    public function getSmsStatus($messageId)
    {
        try {
            $url = rtrim($this->serviceUrl, '/') . '/api/sms/status';
            
            $data = [
                'login' => $this->login,
                'password' => $this->password,
                'message_id' => $messageId
            ];

            $response = $this->httpClient->post($url, Json::encode($data));
            $responseData = Json::decode($response);

            if (isset($responseData['success']) && $responseData['success']) {
                return [
                    'success' => true,
                    'status' => $responseData['status'],
                    'delivered_at' => $responseData['delivered_at'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Не удалось получить статус SMS'
            ];

        } catch (\Exception $e) {
            Logger::log('SMS Status Error: ' . $e->getMessage(), 'SMS_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка получения статуса SMS'
            ];
        }
    }

    /**
     * Отправить уведомление о статусе заявки
     */
    public function sendDeliveryStatusNotification($phone, $status, $requestId, $clientName = '')
    {
        $messages = [
            'NEW' => 'Ваша заявка на доставку карты №{REQUEST_ID} принята в обработку.',
            'PROCESSING' => 'Ваша заявка №{REQUEST_ID} обрабатывается. Ожидайте звонка оператора.',
            'ASSIGNED' => 'К вашей заявке №{REQUEST_ID} назначен курьер. Ожидайте доставку.',
            'IN_DELIVERY' => 'Курьер выехал для доставки вашей карты по заявке №{REQUEST_ID}.',
            'DELIVERED' => 'Ваша карта по заявке №{REQUEST_ID} успешно доставлена. Спасибо за обращение!',
            'REJECTED' => 'К сожалению, заявка №{REQUEST_ID} отклонена. Обратитесь в банк для уточнения.',
            'CANCELLED' => 'Заявка №{REQUEST_ID} отменена по вашему запросу.'
        ];

        if (!isset($messages[$status])) {
            return [
                'success' => false,
                'error' => 'Неизвестный статус заявки'
            ];
        }

        $message = str_replace(
            ['{REQUEST_ID}', '{CLIENT_NAME}'],
            [$requestId, $clientName],
            $messages[$status]
        );

        return $this->sendSms($phone, $message);
    }

    /**
     * Отправить уведомление курьеру
     */
    public function sendCourierNotification($phone, $type, $data = [])
    {
        $messages = [
            'NEW_DELIVERY' => 'Вам назначена новая доставка №{REQUEST_ID}. Адрес: {ADDRESS}',
            'DELIVERY_CANCELLED' => 'Доставка №{REQUEST_ID} отменена.',
            'ROUTE_UPDATED' => 'Маршрут доставки обновлен. Проверьте приложение.',
            'URGENT_DELIVERY' => 'СРОЧНО! Новая приоритетная доставка №{REQUEST_ID}. Адрес: {ADDRESS}'
        ];

        if (!isset($messages[$type])) {
            return [
                'success' => false,
                'error' => 'Неизвестный тип уведомления'
            ];
        }

        $message = $messages[$type];
        
        // Заменяем плейсхолдеры
        foreach ($data as $key => $value) {
            $message = str_replace('{' . strtoupper($key) . '}', $value, $message);
        }

        return $this->sendSms($phone, $message);
    }

    /**
     * Получить баланс SMS-сервиса
     */
    public function getBalance()
    {
        try {
            $url = rtrim($this->serviceUrl, '/') . '/api/account/balance';
            
            $data = [
                'login' => $this->login,
                'password' => $this->password
            ];

            $response = $this->httpClient->post($url, Json::encode($data));
            $responseData = Json::decode($response);

            if (isset($responseData['success']) && $responseData['success']) {
                return [
                    'success' => true,
                    'balance' => $responseData['balance'],
                    'currency' => $responseData['currency'] ?? 'RUB'
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Не удалось получить баланс'
            ];

        } catch (\Exception $e) {
            Logger::log('SMS Balance Error: ' . $e->getMessage(), 'SMS_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка получения баланса SMS-сервиса'
            ];
        }
    }

    /**
     * Нормализация номера телефона
     */
    private function normalizePhone($phone)
    {
        // Удаляем все символы кроме цифр
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Приводим к формату 7XXXXXXXXXX
        if (strlen($phone) === 11 && $phone[0] === '8') {
            $phone = '7' . substr($phone, 1);
        } elseif (strlen($phone) === 10) {
            $phone = '7' . $phone;
        }
        
        return $phone;
    }

    /**
     * Валидация номера телефона
     */
    private function validatePhone($phone)
    {
        return preg_match('/^7\d{10}$/', $phone);
    }

    /**
     * Проверить подключение к SMS-сервису
     */
    public function testConnection()
    {
        if (empty($this->serviceUrl) || empty($this->login) || empty($this->password)) {
            return [
                'success' => false,
                'message' => 'SMS-сервис не настроен'
            ];
        }

        // Проверяем баланс как тест подключения
        $balanceResult = $this->getBalance();
        
        if ($balanceResult['success']) {
            return [
                'success' => true,
                'message' => 'SMS-сервис подключен. Баланс: ' . $balanceResult['balance'] . ' ' . $balanceResult['currency']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка подключения к SMS-сервису: ' . $balanceResult['error']
            ];
        }
    }

    /**
     * Получить конфигурацию SMS-сервиса
     */
    public function getConfig()
    {
        return [
            'has_url' => !empty($this->serviceUrl),
            'has_credentials' => !empty($this->login) && !empty($this->password),
            'url' => $this->serviceUrl
        ];
    }

    /**
     * Получить статистику отправленных SMS
     */
    public function getStatistics($dateFrom, $dateTo = null)
    {
        try {
            $url = rtrim($this->serviceUrl, '/') . '/api/sms/statistics';
            
            $data = [
                'login' => $this->login,
                'password' => $this->password,
                'date_from' => $dateFrom,
                'date_to' => $dateTo ?: date('Y-m-d H:i:s')
            ];

            $response = $this->httpClient->post($url, Json::encode($data));
            $responseData = Json::decode($response);

            if (isset($responseData['success']) && $responseData['success']) {
                return [
                    'success' => true,
                    'data' => $responseData['statistics']
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['error'] ?? 'Не удалось получить статистику'
            ];

        } catch (\Exception $e) {
            Logger::log('SMS Statistics Error: ' . $e->getMessage(), 'SMS_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка получения статистики SMS'
            ];
        }
    }
}