<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Courier\Delivery\Service\DeliveryService;
use Courier\Delivery\Service\CourierService;
use Courier\Delivery\Util\RoleManager;
use Courier\Delivery\DeliveryTable;
use Courier\Delivery\CourierTable;

class CourierDeliveryDashboardComponent extends CBitrixComponent
{
    private $deliveryService;
    private $courierService;
    private $roleManager;

    public function onPrepareComponentParams($arParams)
    {
        // Параметры по умолчанию
        $arParams['CACHE_TIME'] = isset($arParams['CACHE_TIME']) ? (int)$arParams['CACHE_TIME'] : 300;
        $arParams['SHOW_STATS'] = isset($arParams['SHOW_STATS']) ? $arParams['SHOW_STATS'] : 'Y';
        $arParams['SHOW_MAP'] = isset($arParams['SHOW_MAP']) ? $arParams['SHOW_MAP'] : 'Y';
        $arParams['SHOW_RECENT_REQUESTS'] = isset($arParams['SHOW_RECENT_REQUESTS']) ? $arParams['SHOW_RECENT_REQUESTS'] : 'Y';
        
        return $arParams;
    }

    public function executeComponent()
    {
        global $USER;

        // Проверяем авторизацию
        if (!$USER->IsAuthorized()) {
            $this->arResult['ERROR'] = 'Необходима авторизация';
            $this->includeComponentTemplate();
            return;
        }

        // Подключаем модуль
        if (!Loader::includeModule('courier.delivery')) {
            $this->arResult['ERROR'] = 'Модуль курьерской службы не установлен';
            $this->includeComponentTemplate();
            return;
        }

        // Инициализация сервисов
        $this->deliveryService = new DeliveryService();
        $this->roleManager = new RoleManager();

        // Проверяем права доступа
        $userRoles = $this->roleManager->getUserRoles($USER->GetID());
        if (empty($userRoles)) {
            $this->arResult['ERROR'] = 'Недостаточно прав для доступа к системе';
            $this->includeComponentTemplate();
            return;
        }

        // Кэширование
        if ($this->startResultCache()) {
            $this->prepareResult();
            $this->endResultCache();
        }

        $this->includeComponentTemplate();
    }

    private function prepareResult()
    {
        global $USER;

        $this->arResult['USER_ROLES'] = $this->roleManager->getUserRoles($USER->GetID());
        $this->arResult['SECURITY_CONTEXT'] = $this->roleManager->createSecurityContext($USER->GetID());

        // Статистика заявок
        if ($this->arParams['SHOW_STATS'] === 'Y') {
            $this->arResult['STATISTICS'] = $this->getStatistics();
        }

        // Список активных курьеров
        $this->arResult['ACTIVE_COURIERS'] = $this->getActiveCouriers();

        // Последние заявки
        if ($this->arParams['SHOW_RECENT_REQUESTS'] === 'Y') {
            $this->arResult['RECENT_REQUESTS'] = $this->getRecentRequests();
        }

        // Данные для карты
        if ($this->arParams['SHOW_MAP'] === 'Y') {
            $this->arResult['MAP_DATA'] = $this->getMapData();
        }

        // Уведомления
        $this->arResult['NOTIFICATIONS'] = $this->getNotifications();
    }

    private function getStatistics()
    {
        $dateFrom = date('Y-m-d 00:00:00');
        $dateTo = date('Y-m-d 23:59:59');

        // Статистика заявок за сегодня
        $todayStats = DeliveryTable::getStatistics($dateFrom, $dateTo);

        // Общая статистика
        $totalStats = DeliveryTable::getStatistics();

        // Статистика курьеров
        $courierStats = CourierTable::getCourierStatistics();

        return [
            'TODAY' => $todayStats,
            'TOTAL' => $totalStats,
            'COURIERS' => $courierStats
        ];
    }

    private function getActiveCouriers()
    {
        $couriers = CourierTable::getActiveCouriers();
        $result = [];

        while ($courier = $couriers->fetch()) {
            $result[] = [
                'ID' => $courier['ID'],
                'FULL_NAME' => $courier['FULL_NAME'],
                'STATUS' => $courier['STATUS'],
                'BRANCH_NAME' => $courier['BRANCH_NAME'],
                'LAST_LOCATION_UPDATE' => $courier['LAST_LOCATION_UPDATE'],
                'CURRENT_LATITUDE' => $courier['CURRENT_LATITUDE'],
                'CURRENT_LONGITUDE' => $courier['CURRENT_LONGITUDE']
            ];
        }

        return $result;
    }

    private function getRecentRequests()
    {
        global $USER;

        $filter = [];
        $limit = 10;

        // Применяем фильтры безопасности
        if (!$this->roleManager->hasPermission($USER->GetID(), RoleManager::PERMISSION_VIEW_ALL)) {
            // Курьеры видят только свои заявки
            if ($this->roleManager->hasRole($USER->GetID(), RoleManager::ROLE_COURIER)) {
                $courier = CourierTable::getList([
                    'select' => ['ID'],
                    'filter' => ['USER_ID' => $USER->GetID()]
                ])->fetch();

                if ($courier) {
                    $filter['COURIER_ID'] = $courier['ID'];
                } else {
                    return []; // Курьер не найден
                }
            }
        }

        $requests = $this->deliveryService->getRequestsList(
            $filter, 
            ['CREATED_DATE' => 'DESC'], 
            $limit
        );

        return $requests['success'] ? $requests['data'] : [];
    }

    private function getMapData()
    {
        $mapData = [
            'couriers' => [],
            'requests' => []
        ];

        // Координаты активных курьеров
        $couriers = CourierTable::getList([
            'select' => [
                'ID', 'FULL_NAME', 'STATUS', 'CURRENT_LATITUDE', 'CURRENT_LONGITUDE'
            ],
            'filter' => [
                'IS_ACTIVE' => 'Y',
                '!CURRENT_LATITUDE' => null,
                '!CURRENT_LONGITUDE' => null
            ]
        ]);

        while ($courier = $couriers->fetch()) {
            $mapData['couriers'][] = [
                'id' => $courier['ID'],
                'name' => $courier['FULL_NAME'],
                'status' => $courier['STATUS'],
                'lat' => (float)$courier['CURRENT_LATITUDE'],
                'lng' => (float)$courier['CURRENT_LONGITUDE']
            ];
        }

        // Заявки в доставке (если есть координаты адресов)
        // Здесь можно добавить логику геокодирования адресов и отображения на карте

        return $mapData;
    }

    private function getNotifications()
    {
        global $USER;

        $notifications = [];

        // Проверяем критические уведомления
        if ($this->roleManager->hasPermission($USER->GetID(), RoleManager::PERMISSION_VIEW_ALL)) {
            // Заявки с превышением времени доставки
            $timeoutRequests = DeliveryTable::getList([
                'select' => ['ID', 'CLIENT_NAME'],
                'filter' => [
                    'STATUS' => 'IN_DELIVERY',
                    '<PROCESSING_DATE' => date('Y-m-d H:i:s', strtotime('-4 hours'))
                ],
                'limit' => 5
            ]);

            while ($request = $timeoutRequests->fetch()) {
                $notifications[] = [
                    'type' => 'warning',
                    'title' => 'Превышено время доставки',
                    'text' => "Заявка #{$request['ID']} ({$request['CLIENT_NAME']}) в доставке более 4 часов",
                    'url' => "/bitrix/admin/courier_delivery_request_edit.php?id={$request['ID']}"
                ];
            }

            // Неактивные курьеры
            $inactiveCouriers = CourierTable::getList([
                'select' => ['ID', 'FULL_NAME'],
                'filter' => [
                    'STATUS' => 'OFFLINE',
                    'IS_ACTIVE' => 'Y',
                    '<LAST_LOCATION_UPDATE' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                ],
                'limit' => 3
            ]);

            while ($courier = $inactiveCouriers->fetch()) {
                $notifications[] = [
                    'type' => 'info',
                    'title' => 'Курьер неактивен',
                    'text' => "Курьер {$courier['FULL_NAME']} не выходил на связь более 2 часов",
                    'url' => "/bitrix/admin/courier_delivery_courier_edit.php?id={$courier['ID']}"
                ];
            }
        }

        return $notifications;
    }
}