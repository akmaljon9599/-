<?php
namespace Courier\Delivery\Service;

use Courier\Delivery\DeliveryTable;
use Courier\Delivery\CourierTable;
use Courier\Delivery\Api\AbsGateway;
use Courier\Delivery\Api\YandexMaps;
use Courier\Delivery\Util\Logger;
use Courier\Delivery\Util\RoleManager;
use Courier\Delivery\Util\Security;
use Bitrix\Main\Type\DateTime;

/**
 * Сервис для управления заявками на доставку
 */
class DeliveryService
{
    private $absGateway;
    private $yandexMaps;
    private $roleManager;

    public function __construct()
    {
        $this->absGateway = new AbsGateway();
        $this->yandexMaps = new YandexMaps();
        $this->roleManager = new RoleManager();
    }

    /**
     * Создать новую заявку
     */
    public function createRequest($data, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            // Валидация данных
            $validationErrors = $this->validateRequestData($data);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'errors' => $validationErrors
                ];
            }

            // Получаем информацию о клиенте из АБС
            $clientInfo = $this->absGateway->getClientByPan($data['client_pan']);
            if (!$clientInfo['success']) {
                return [
                    'success' => false,
                    'error' => 'Не удалось получить информацию о клиенте из АБС: ' . $clientInfo['error']
                ];
            }

            // Геокодируем адрес доставки
            $geocodeResult = $this->yandexMaps->geocode($data['delivery_address']);
            if ($geocodeResult['success']) {
                $data['delivery_latitude'] = $geocodeResult['data']['latitude'];
                $data['delivery_longitude'] = $geocodeResult['data']['longitude'];
                $data['formatted_address'] = $geocodeResult['data']['formatted_address'];
            }

            // Создаем заявку в АБС
            $absRequest = $this->absGateway->createDeliveryRequest([
                'client_pan' => $data['client_pan'],
                'delivery_address' => $data['delivery_address'],
                'client_phone' => $clientInfo['data']['phone'],
                'branch_code' => $data['branch_code'] ?? $clientInfo['data']['branch_code'],
                'department_code' => $data['department_code'] ?? $clientInfo['data']['department_code'],
                'operator_id' => $userId,
                'notes' => $data['notes'] ?? ''
            ]);

            if (!$absRequest['success']) {
                return [
                    'success' => false,
                    'error' => 'Ошибка создания заявки в АБС: ' . $absRequest['error']
                ];
            }

            // Создаем заявку в локальной БД
            $requestData = [
                'ABS_ID' => $absRequest['data']['abs_id'],
                'CLIENT_NAME' => $clientInfo['data']['full_name'],
                'CLIENT_PHONE' => $clientInfo['data']['phone'],
                'CLIENT_PAN' => $data['client_pan'],
                'DELIVERY_ADDRESS' => $data['delivery_address'],
                'STATUS' => 'NEW',
                'BRANCH_ID' => $this->getBranchIdByCode($data['branch_code'] ?? $clientInfo['data']['branch_code']),
                'DEPARTMENT_ID' => $this->getDepartmentIdByCode($data['department_code'] ?? $clientInfo['data']['department_code']),
                'OPERATOR_ID' => $userId,
                'CARD_TYPE' => $clientInfo['data']['card_type'],
                'CREATED_BY' => $userId,
                'NOTES' => $data['notes'] ?? ''
            ];

            $result = DeliveryTable::add($requestData);

            if ($result->isSuccess()) {
                $requestId = $result->getId();
                
                Logger::logUserAction('CREATE_REQUEST', [
                    'request_id' => $requestId,
                    'abs_id' => $absRequest['data']['abs_id'],
                    'client_name' => $clientInfo['data']['full_name']
                ], $userId);

                // Отправляем SMS уведомление клиенту
                NotificationService::sendDeliveryStatusNotification(
                    $requestId, 
                    'NEW', 
                    $clientInfo['data']['phone']
                );

                return [
                    'success' => true,
                    'request_id' => $requestId,
                    'abs_id' => $absRequest['data']['abs_id']
                ];
            }

            return [
                'success' => false,
                'error' => 'Ошибка сохранения заявки в локальной БД'
            ];

        } catch (\Exception $e) {
            Logger::error('Error creating delivery request: ' . $e->getMessage(), [
                'data' => $data,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при создании заявки'
            ];
        }
    }

    /**
     * Обновить заявку
     */
    public function updateRequest($requestId, $data, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            // Проверяем права доступа
            if (!$this->roleManager->canAccessRequest($userId, $requestId)) {
                return [
                    'success' => false,
                    'error' => 'Недостаточно прав для редактирования заявки'
                ];
            }

            // Получаем текущую заявку
            $currentRequest = DeliveryTable::getById($requestId)->fetch();
            if (!$currentRequest) {
                return [
                    'success' => false,
                    'error' => 'Заявка не найдена'
                ];
            }

            // Валидация данных
            $validationErrors = $this->validateRequestData($data, true);
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'errors' => $validationErrors
                ];
            }

            // Подготавливаем данные для обновления
            $updateData = [
                'UPDATED_DATE' => new DateTime(),
                'UPDATED_BY' => $userId
            ];

            // Обновляемые поля
            $allowedFields = [
                'CLIENT_NAME', 'CLIENT_PHONE', 'DELIVERY_ADDRESS', 
                'COURIER_ID', 'NOTES', 'CALL_STATUS'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[strtolower($field)])) {
                    $updateData[$field] = $data[strtolower($field)];
                }
            }

            // Если изменился адрес, геокодируем его
            if (isset($data['delivery_address']) && $data['delivery_address'] !== $currentRequest['DELIVERY_ADDRESS']) {
                $geocodeResult = $this->yandexMaps->geocode($data['delivery_address']);
                if ($geocodeResult['success']) {
                    // Сохраняем координаты в дополнительной таблице или поле
                    Logger::info("Address geocoded for request #{$requestId}", [
                        'address' => $data['delivery_address'],
                        'coordinates' => $geocodeResult['data']
                    ]);
                }
            }

            $result = DeliveryTable::update($requestId, $updateData);

            if ($result->isSuccess()) {
                Logger::logUserAction('UPDATE_REQUEST', [
                    'request_id' => $requestId,
                    'updated_fields' => array_keys($updateData)
                ], $userId);

                return [
                    'success' => true,
                    'message' => 'Заявка успешно обновлена'
                ];
            }

            return [
                'success' => false,
                'error' => 'Ошибка обновления заявки'
            ];

        } catch (\Exception $e) {
            Logger::error('Error updating delivery request: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'data' => $data,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при обновлении заявки'
            ];
        }
    }

    /**
     * Изменить статус заявки
     */
    public function changeStatus($requestId, $newStatus, $comment = '', $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            // Проверяем права на изменение статуса
            if (!$this->roleManager->canChangeStatus($userId, $requestId, $newStatus)) {
                return [
                    'success' => false,
                    'error' => 'Недостаточно прав для изменения статуса'
                ];
            }

            $request = DeliveryTable::getById($requestId)->fetch();
            if (!$request) {
                return [
                    'success' => false,
                    'error' => 'Заявка не найдена'
                ];
            }

            $oldStatus = $request['STATUS'];

            // Валидация перехода статуса
            if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
                return [
                    'success' => false,
                    'error' => 'Недопустимый переход статуса'
                ];
            }

            // Изменяем статус
            $changeResult = DeliveryTable::changeStatus($requestId, $newStatus, $comment, $userId);

            if ($changeResult) {
                // Обновляем статус в АБС
                $this->absGateway->updateRequestStatus(
                    $request['ABS_ID'], 
                    $newStatus, 
                    $comment
                );

                // Отправляем уведомления
                NotificationService::sendDeliveryStatusNotification(
                    $requestId, 
                    $newStatus, 
                    $request['CLIENT_PHONE']
                );

                // Дополнительная логика для определенных статусов
                $this->handleStatusSpecificActions($requestId, $newStatus, $request);

                return [
                    'success' => true,
                    'message' => 'Статус успешно изменен'
                ];
            }

            return [
                'success' => false,
                'error' => 'Ошибка изменения статуса'
            ];

        } catch (\Exception $e) {
            Logger::error('Error changing request status: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'new_status' => $newStatus,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при изменении статуса'
            ];
        }
    }

    /**
     * Назначить курьера на заявку
     */
    public function assignCourier($requestId, $courierId, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            // Проверяем права
            if (!$this->roleManager->hasPermission($userId, RoleManager::PERMISSION_ASSIGN_COURIER)) {
                return [
                    'success' => false,
                    'error' => 'Недостаточно прав для назначения курьера'
                ];
            }

            // Проверяем существование заявки и курьера
            $request = DeliveryTable::getById($requestId)->fetch();
            $courier = CourierTable::getById($courierId)->fetch();

            if (!$request || !$courier) {
                return [
                    'success' => false,
                    'error' => 'Заявка или курьер не найдены'
                ];
            }

            // Проверяем доступность курьера
            if ($courier['STATUS'] !== 'ONLINE') {
                return [
                    'success' => false,
                    'error' => 'Курьер недоступен'
                ];
            }

            // Назначаем курьера
            $assignResult = DeliveryTable::assignCourier($requestId, $courierId, $userId);

            if ($assignResult) {
                // Отправляем уведомление курьеру
                NotificationService::sendCourierNotification($courier['PHONE'], 'NEW_DELIVERY', [
                    'request_id' => $requestId,
                    'address' => $request['DELIVERY_ADDRESS']
                ]);

                Logger::logUserAction('ASSIGN_COURIER', [
                    'request_id' => $requestId,
                    'courier_id' => $courierId,
                    'courier_name' => $courier['FULL_NAME']
                ], $userId);

                return [
                    'success' => true,
                    'message' => 'Курьер успешно назначен'
                ];
            }

            return [
                'success' => false,
                'error' => 'Ошибка назначения курьера'
            ];

        } catch (\Exception $e) {
            Logger::error('Error assigning courier: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'courier_id' => $courierId,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при назначении курьера'
            ];
        }
    }

    /**
     * Автоматическое назначение ближайшего курьера
     */
    public function autoAssignCourier($requestId, $userId = null)
    {
        try {
            $request = DeliveryTable::getById($requestId)->fetch();
            if (!$request) {
                return [
                    'success' => false,
                    'error' => 'Заявка не найдена'
                ];
            }

            // Геокодируем адрес если координаты не сохранены
            $latitude = null;
            $longitude = null;

            if (!$latitude || !$longitude) {
                $geocodeResult = $this->yandexMaps->geocode($request['DELIVERY_ADDRESS']);
                if ($geocodeResult['success']) {
                    $latitude = $geocodeResult['data']['latitude'];
                    $longitude = $geocodeResult['data']['longitude'];
                }
            }

            if (!$latitude || !$longitude) {
                return [
                    'success' => false,
                    'error' => 'Не удалось определить координаты адреса доставки'
                ];
            }

            // Находим ближайшего доступного курьера
            $nearestCourier = CourierTable::findNearestAvailableCourier(
                $latitude, 
                $longitude, 
                $request['BRANCH_ID']
            );

            if (!$nearestCourier) {
                return [
                    'success' => false,
                    'error' => 'Нет доступных курьеров'
                ];
            }

            // Назначаем найденного курьера
            return $this->assignCourier($requestId, $nearestCourier['ID'], $userId);

        } catch (\Exception $e) {
            Logger::error('Error auto assigning courier: ' . $e->getMessage(), [
                'request_id' => $requestId,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при автоматическом назначении курьера'
            ];
        }
    }

    /**
     * Получить список заявок с фильтрацией
     */
    public function getRequestsList($filter = [], $order = [], $limit = null, $offset = null, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        try {
            // Применяем фильтры безопасности в зависимости от роли
            $filter = $this->applySecurityFilter($filter, $userId);

            $result = DeliveryTable::getFilteredList($filter, $order, $limit, $offset);
            $requests = [];

            while ($request = $result->fetch()) {
                // Дополнительная обработка данных
                $request = $this->enrichRequestData($request);
                $requests[] = $request;
            }

            return [
                'success' => true,
                'data' => $requests,
                'total' => $this->getRequestsCount($filter)
            ];

        } catch (\Exception $e) {
            Logger::error('Error getting requests list: ' . $e->getMessage(), [
                'filter' => $filter,
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка получения списка заявок'
            ];
        }
    }

    /**
     * Валидация данных заявки
     */
    private function validateRequestData($data, $isUpdate = false)
    {
        $rules = [
            'client_pan' => [
                'required' => !$isUpdate,
                'type' => 'string',
                'pattern' => '/^\d{16}$/',
                'pattern_message' => 'PAN должен содержать 16 цифр'
            ],
            'delivery_address' => [
                'required' => !$isUpdate,
                'type' => 'string',
                'min_length' => 10,
                'max_length' => 500
            ],
            'client_name' => [
                'type' => 'string',
                'max_length' => 255
            ],
            'client_phone' => [
                'type' => 'phone'
            ],
            'notes' => [
                'type' => 'string',
                'max_length' => 1000
            ]
        ];

        return Security::validateInput($data, $rules);
    }

    /**
     * Проверка валидности перехода статуса
     */
    private function isValidStatusTransition($oldStatus, $newStatus)
    {
        $validTransitions = [
            'NEW' => ['PROCESSING', 'REJECTED', 'CANCELLED'],
            'PROCESSING' => ['ASSIGNED', 'REJECTED', 'CANCELLED'],
            'ASSIGNED' => ['IN_DELIVERY', 'REJECTED', 'CANCELLED'],
            'IN_DELIVERY' => ['DELIVERED', 'REJECTED'],
            'DELIVERED' => [],
            'REJECTED' => [],
            'CANCELLED' => []
        ];

        return isset($validTransitions[$oldStatus]) && 
               in_array($newStatus, $validTransitions[$oldStatus]);
    }

    /**
     * Обработка специфичных действий для статусов
     */
    private function handleStatusSpecificActions($requestId, $newStatus, $request)
    {
        switch ($newStatus) {
            case 'DELIVERED':
                // Логика для доставленных заявок
                $this->handleDeliveredRequest($requestId, $request);
                break;
                
            case 'REJECTED':
                // Логика для отклоненных заявок
                $this->handleRejectedRequest($requestId, $request);
                break;
                
            case 'IN_DELIVERY':
                // Логика для заявок в доставке
                $this->handleInDeliveryRequest($requestId, $request);
                break;
        }
    }

    /**
     * Обработка доставленной заявки
     */
    private function handleDeliveredRequest($requestId, $request)
    {
        // Освобождаем курьера
        if ($request['COURIER_ID']) {
            CourierTable::changeStatus($request['COURIER_ID'], 'ONLINE');
        }

        // Дополнительная логика...
        Logger::info("Request #{$requestId} delivered successfully");
    }

    /**
     * Обработка отклоненной заявки
     */
    private function handleRejectedRequest($requestId, $request)
    {
        // Освобождаем курьера
        if ($request['COURIER_ID']) {
            CourierTable::changeStatus($request['COURIER_ID'], 'ONLINE');
        }

        Logger::info("Request #{$requestId} rejected");
    }

    /**
     * Обработка заявки в доставке
     */
    private function handleInDeliveryRequest($requestId, $request)
    {
        // Меняем статус курьера
        if ($request['COURIER_ID']) {
            CourierTable::changeStatus($request['COURIER_ID'], 'ON_DELIVERY');
        }

        Logger::info("Request #{$requestId} is now in delivery");
    }

    /**
     * Применение фильтров безопасности
     */
    private function applySecurityFilter($filter, $userId)
    {
        // Администраторы и старшие курьеры видят все заявки
        if ($this->roleManager->hasPermission($userId, RoleManager::PERMISSION_VIEW_ALL)) {
            return $filter;
        }

        // Курьеры видят только свои заявки
        if ($this->roleManager->hasRole($userId, RoleManager::ROLE_COURIER)) {
            $courier = CourierTable::getList([
                'select' => ['ID'],
                'filter' => ['USER_ID' => $userId]
            ])->fetch();

            if ($courier) {
                $filter['COURIER_ID'] = $courier['ID'];
            }
        }

        // Операторы видят заявки своего филиала
        if ($this->roleManager->hasRole($userId, RoleManager::ROLE_OPERATOR)) {
            // Логика определения филиала оператора
            // $filter['BRANCH_ID'] = $operatorBranchId;
        }

        return $filter;
    }

    /**
     * Обогащение данных заявки
     */
    private function enrichRequestData($request)
    {
        // Добавляем дополнительную информацию
        $request['STATUS_TEXT'] = $this->getStatusText($request['STATUS']);
        $request['CALL_STATUS_TEXT'] = $this->getCallStatusText($request['CALL_STATUS']);
        
        // Форматируем даты
        if ($request['CREATED_DATE']) {
            $request['CREATED_DATE_FORMATTED'] = $request['CREATED_DATE']->format('d.m.Y H:i');
        }

        return $request;
    }

    /**
     * Получить текстовое описание статуса
     */
    private function getStatusText($status)
    {
        $statusTexts = [
            'NEW' => 'Новая',
            'PROCESSING' => 'В обработке',
            'ASSIGNED' => 'Назначена курьеру',
            'IN_DELIVERY' => 'В доставке',
            'DELIVERED' => 'Доставлено',
            'REJECTED' => 'Отказано',
            'CANCELLED' => 'Отменено'
        ];

        return $statusTexts[$status] ?? $status;
    }

    /**
     * Получить текстовое описание статуса звонка
     */
    private function getCallStatusText($status)
    {
        $statusTexts = [
            'NOT_CALLED' => 'Не звонили',
            'SUCCESS' => 'Успешный',
            'FAILED' => 'Не удался',
            'NO_ANSWER' => 'Не отвечает'
        ];

        return $statusTexts[$status] ?? $status;
    }

    /**
     * Получить количество заявок по фильтру
     */
    private function getRequestsCount($filter)
    {
        $result = DeliveryTable::getList([
            'select' => ['CNT'],
            'filter' => $filter,
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ])->fetch();

        return (int)($result['CNT'] ?? 0);
    }

    /**
     * Получить ID филиала по коду
     */
    private function getBranchIdByCode($code)
    {
        // Здесь должна быть логика получения ID филиала по коду
        // Пока возвращаем 1 по умолчанию
        return 1;
    }

    /**
     * Получить ID подразделения по коду
     */
    private function getDepartmentIdByCode($code)
    {
        // Здесь должна быть логика получения ID подразделения по коду
        // Пока возвращаем 1 по умолчанию
        return 1;
    }
}