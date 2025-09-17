<?php
/**
 * Курьерская служба - Модуль для Битрикс 24
 * Основной файл подключения модуля
 * 
 * @version 1.0.0
 * @author Courier Delivery Team
 */

defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

$module_id = 'courier.delivery';

if (!ModuleManager::isModuleInstalled($module_id)) {
    return;
}

// Регистрация автозагрузчика классов
Loader::registerAutoLoadClasses($module_id, [
    // Основные классы
    'Courier\\Delivery\\DeliveryTable' => 'lib/delivery.php',
    'Courier\\Delivery\\CourierTable' => 'lib/courier.php',
    'Courier\\Delivery\\ClientTable' => 'lib/client.php',
    'Courier\\Delivery\\DocumentTable' => 'lib/document.php',
    'Courier\\Delivery\\StatusTable' => 'lib/status.php',
    'Courier\\Delivery\\BranchTable' => 'lib/branch.php',
    'Courier\\Delivery\\DepartmentTable' => 'lib/department.php',
    
    // API классы
    'Courier\\Delivery\\Api\\AbsGateway' => 'lib/api/absgateway.php',
    'Courier\\Delivery\\Api\\YandexMaps' => 'lib/api/yandexmaps.php',
    'Courier\\Delivery\\Api\\SmsService' => 'lib/api/smsservice.php',
    
    // Сервисы
    'Courier\\Delivery\\Service\\DeliveryService' => 'lib/service/deliveryservice.php',
    'Courier\\Delivery\\Service\\CourierService' => 'lib/service/courierservice.php',
    'Courier\\Delivery\\Service\\DocumentService' => 'lib/service/documentservice.php',
    'Courier\\Delivery\\Service\\NotificationService' => 'lib/service/notificationservice.php',
    'Courier\\Delivery\\Service\\GeoLocationService' => 'lib/service/geolocationservice.php',
    
    // Контроллеры
    'Courier\\Delivery\\Controller\\DeliveryController' => 'lib/controller/deliverycontroller.php',
    'Courier\\Delivery\\Controller\\CourierController' => 'lib/controller/couriercontroller.php',
    'Courier\\Delivery\\Controller\\ApiController' => 'lib/controller/apicontroller.php',
    
    // Утилиты
    'Courier\\Delivery\\Util\\RoleManager' => 'lib/util/rolemanager.php',
    'Courier\\Delivery\\Util\\Logger' => 'lib/util/logger.php',
    'Courier\\Delivery\\Util\\Security' => 'lib/util/security.php',
    'Courier\\Delivery\\Util\\FileUpload' => 'lib/util/fileupload.php',
]);

// Подключение обработчиков событий
$eventManager = \Bitrix\Main\EventManager::getInstance();

// Обработчик для отслеживания изменений статусов заявок
$eventManager->addEventHandler($module_id, 'OnDeliveryStatusChange', function($event) {
    $parameters = $event->getParameters();
    $deliveryId = $parameters['DELIVERY_ID'];
    $oldStatus = $parameters['OLD_STATUS'];
    $newStatus = $parameters['NEW_STATUS'];
    $userId = $parameters['USER_ID'];
    
    // Логирование изменения статуса
    \Courier\Delivery\Util\Logger::log("Status changed for delivery #{$deliveryId}: {$oldStatus} -> {$newStatus} by user #{$userId}");
    
    // Отправка уведомлений
    \Courier\Delivery\Service\NotificationService::sendStatusChangeNotification($deliveryId, $oldStatus, $newStatus);
});

// Обработчик для обновления геолокации курьеров
$eventManager->addEventHandler($module_id, 'OnCourierLocationUpdate', function($event) {
    $parameters = $event->getParameters();
    $courierId = $parameters['COURIER_ID'];
    $latitude = $parameters['LATITUDE'];
    $longitude = $parameters['LONGITUDE'];
    
    \Courier\Delivery\Service\GeoLocationService::updateCourierLocation($courierId, $latitude, $longitude);
});

// Подключение REST API
if (Loader::includeModule('rest')) {
    $eventManager->addEventHandler('rest', 'OnRestServiceBuildDescription', function() {
        return [
            'courier.delivery' => [
                'courier.delivery.request.list' => ['Courier\\Delivery\\Controller\\ApiController', 'getRequestsList'],
                'courier.delivery.request.get' => ['Courier\\Delivery\\Controller\\ApiController', 'getRequest'],
                'courier.delivery.request.add' => ['Courier\\Delivery\\Controller\\ApiController', 'addRequest'],
                'courier.delivery.request.update' => ['Courier\\Delivery\\Controller\\ApiController', 'updateRequest'],
                'courier.delivery.status.update' => ['Courier\\Delivery\\Controller\\ApiController', 'updateStatus'],
                'courier.delivery.courier.location' => ['Courier\\Delivery\\Controller\\ApiController', 'updateCourierLocation'],
                'courier.delivery.document.upload' => ['Courier\\Delivery\\Controller\\ApiController', 'uploadDocument'],
            ]
        ];
    });
}

// Подключение агентов для фоновых задач
\CAgent::AddAgent(
    "\\Courier\\Delivery\\Service\\GeoLocationService::cleanOldLocations();",
    $module_id,
    "N",
    86400, // раз в день
    "",
    "Y"
);

\CAgent::AddAgent(
    "\\Courier\\Delivery\\Service\\NotificationService::processScheduledNotifications();",
    $module_id,
    "N",
    300, // каждые 5 минут
    "",
    "Y"
);