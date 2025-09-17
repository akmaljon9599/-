<?php
/**
 * Модуль "Курьерская служба"
 * Подключение автозагрузчика классов
 */

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// Подключение автозагрузчика
Loader::registerAutoLoadClasses('courier_service', [
    'CourierService\\Main\\RequestTable' => 'lib/main/request.php',
    'CourierService\\Main\\CourierTable' => 'lib/main/courier.php',
    'CourierService\\Main\\BranchTable' => 'lib/main/branch.php',
    'CourierService\\Main\\DepartmentTable' => 'lib/main/department.php',
    'CourierService\\Main\\DocumentTable' => 'lib/main/document.php',
    'CourierService\\Main\\LogTable' => 'lib/main/log.php',
    'CourierService\\Main\\SettingTable' => 'lib/main/setting.php',
    'CourierService\\Api\\AbsGateway' => 'lib/api/abs_gateway.php',
    'CourierService\\Api\\YandexMaps' => 'lib/api/yandex_maps.php',
    'CourierService\\EventHandlers\\Main' => 'lib/eventhandlers/main.php',
    'CourierService\\Utils\\PdfGenerator' => 'lib/utils/pdf_generator.php',
    'CourierService\\Utils\\ExcelExporter' => 'lib/utils/excel_exporter.php',
    'CourierService\\Utils\\SignatureHandler' => 'lib/utils/signature_handler.php',
    'CourierService\\Utils\\LocationTracker' => 'lib/utils/location_tracker.php',
    'CourierService\\Security\\PermissionManager' => 'lib/security/permission_manager.php',
    'CourierService\\Security\\AuditLogger' => 'lib/security/audit_logger.php',
]);