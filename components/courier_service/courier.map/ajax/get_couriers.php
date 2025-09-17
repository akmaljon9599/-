<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use CourierService\Utils\LocationTracker;

if (!Loader::includeModule('courier_service')) {
    echo json_encode(['success' => false, 'error' => 'Модуль не установлен']);
    exit;
}

// Проверка прав доступа
if (!\CourierService\Security\PermissionManager::checkPermission('couriers', 'view')) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}

$locationTracker = new LocationTracker();
$result = $locationTracker->getActiveCouriersLocations();

header('Content-Type: application/json');
echo json_encode($result);
exit;