<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use CourierService\Service\AuthService;

Loader::includeModule('courier_service');

$authService = new AuthService();
if ($authService->isAuthenticated()) {
    // Логируем выход
    \CourierService\Service\LogService::log(
        'logout',
        'system',
        0,
        null,
        $authService->getUserId()
    );
    
    $authService->logout();
}

// Перенаправляем на главную страницу
LocalRedirect('/bitrix/admin/');
exit;