<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class courier_service extends CModule
{
    public $MODULE_ID = 'courier_service';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_NAME = Loc::getMessage('COURIER_SERVICE_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('COURIER_SERVICE_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('COURIER_SERVICE_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('COURIER_SERVICE_PARTNER_URI');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (CheckVersion(ModuleManager::getVersion('main'), '20.0.0')) {
            $this->InstallFiles();
            $this->InstallDB();
            $this->InstallEvents();
            ModuleManager::registerModule($this->MODULE_ID);
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('COURIER_SERVICE_INSTALL_TITLE'),
                __DIR__ . '/step.php'
            );
        } else {
            $APPLICATION->ThrowException(
                Loc::getMessage('COURIER_SERVICE_INSTALL_ERROR_VERSION')
            );
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallEvents();
        $this->UnInstallDB();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('COURIER_SERVICE_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/js/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID . '/',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/css/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID . '/',
            true,
            true
        );

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/'
        );

        DeleteDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/'
        );

        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID . '/');
        DeleteDirFilesEx('/bitrix/css/' . $this->MODULE_ID . '/');

        return true;
    }

    public function InstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $this->createTables();
            $this->createDefaultData();
        }

        return true;
    }

    public function UnInstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $this->dropTables();
        }

        return true;
    }

    public function InstallEvents()
    {
        EventManager::getInstance()->addEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'CourierService\\EventHandlers\\Main',
            'onBuildGlobalMenu'
        );

        return true;
    }

    public function UnInstallEvents()
    {
        EventManager::getInstance()->removeEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'CourierService\\EventHandlers\\Main',
            'onBuildGlobalMenu'
        );

        return true;
    }

    private function createTables()
    {
        global $DB;

        // Таблица пользователей системы
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_users` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `USER_ID` int(11) NOT NULL,
                `ROLE` enum('admin','senior_courier','courier','operator') NOT NULL DEFAULT 'operator',
                `BRANCH_ID` int(11) DEFAULT NULL,
                `DEPARTMENT_ID` int(11) DEFAULT NULL,
                `PHONE` varchar(20) DEFAULT NULL,
                `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
                `CREATED_AT` datetime NOT NULL,
                `UPDATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `USER_ID` (`USER_ID`),
                KEY `ROLE` (`ROLE`),
                KEY `BRANCH_ID` (`BRANCH_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица филиалов
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_branches` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `ADDRESS` text NOT NULL,
                `COORDINATES` varchar(100) DEFAULT NULL,
                `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
                `CREATED_AT` datetime NOT NULL,
                `UPDATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица подразделений
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_departments` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `BRANCH_ID` int(11) NOT NULL,
                `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
                `CREATED_AT` datetime NOT NULL,
                `UPDATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `BRANCH_ID` (`BRANCH_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица заявок
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_requests` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `REQUEST_NUMBER` varchar(50) NOT NULL,
                `ABS_ID` varchar(50) DEFAULT NULL,
                `CLIENT_NAME` varchar(255) NOT NULL,
                `CLIENT_PHONE` varchar(20) NOT NULL,
                `CLIENT_ADDRESS` text NOT NULL,
                `PAN` varchar(20) NOT NULL,
                `CARD_TYPE` varchar(50) DEFAULT NULL,
                `STATUS` enum('new','waiting_delivery','in_delivery','delivered','rejected') NOT NULL DEFAULT 'new',
                `CALL_STATUS` enum('not_called','successful','failed') NOT NULL DEFAULT 'not_called',
                `COURIER_ID` int(11) DEFAULT NULL,
                `BRANCH_ID` int(11) NOT NULL,
                `DEPARTMENT_ID` int(11) DEFAULT NULL,
                `OPERATOR_ID` int(11) DEFAULT NULL,
                `REGISTRATION_DATE` datetime NOT NULL,
                `PROCESSING_DATE` datetime DEFAULT NULL,
                `DELIVERY_DATE` datetime DEFAULT NULL,
                `REJECTION_REASON` text DEFAULT NULL,
                `COURIER_PHONE` varchar(20) DEFAULT NULL,
                `CREATED_AT` datetime NOT NULL,
                `UPDATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `REQUEST_NUMBER` (`REQUEST_NUMBER`),
                KEY `STATUS` (`STATUS`),
                KEY `COURIER_ID` (`COURIER_ID`),
                KEY `BRANCH_ID` (`BRANCH_ID`),
                KEY `REGISTRATION_DATE` (`REGISTRATION_DATE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица документов
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_documents` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `REQUEST_ID` int(11) NOT NULL,
                `TYPE` enum('contract','passport','delivery_photo','signature') NOT NULL,
                `FILE_PATH` varchar(500) NOT NULL,
                `FILE_NAME` varchar(255) NOT NULL,
                `FILE_SIZE` int(11) NOT NULL,
                `MIME_TYPE` varchar(100) NOT NULL,
                `CREATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `REQUEST_ID` (`REQUEST_ID`),
                KEY `TYPE` (`TYPE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица геолокации курьеров
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_locations` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `COURIER_ID` int(11) NOT NULL,
                `LATITUDE` decimal(10,8) NOT NULL,
                `LONGITUDE` decimal(11,8) NOT NULL,
                `ACCURACY` decimal(8,2) DEFAULT NULL,
                `ADDRESS` varchar(500) DEFAULT NULL,
                `CREATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `COURIER_ID` (`COURIER_ID`),
                KEY `CREATED_AT` (`CREATED_AT`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица логов действий
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_logs` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `USER_ID` int(11) NOT NULL,
                `ACTION` varchar(100) NOT NULL,
                `ENTITY_TYPE` varchar(50) NOT NULL,
                `ENTITY_ID` int(11) NOT NULL,
                `DATA` text DEFAULT NULL,
                `IP_ADDRESS` varchar(45) DEFAULT NULL,
                `USER_AGENT` text DEFAULT NULL,
                `CREATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `USER_ID` (`USER_ID`),
                KEY `ACTION` (`ACTION`),
                KEY `ENTITY_TYPE` (`ENTITY_TYPE`),
                KEY `CREATED_AT` (`CREATED_AT`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Таблица настроек
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_service_settings` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `SETTING_KEY` varchar(100) NOT NULL,
                `SETTING_VALUE` text DEFAULT NULL,
                `DESCRIPTION` varchar(255) DEFAULT NULL,
                `UPDATED_AT` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `SETTING_KEY` (`SETTING_KEY`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    private function createDefaultData()
    {
        global $DB;

        // Создаем филиалы по умолчанию
        $DB->Query("
            INSERT IGNORE INTO `courier_service_branches` (`NAME`, `ADDRESS`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
            ('Центральный', 'г. Москва, ул. Тверская, д. 1', 1, NOW(), NOW()),
            ('Северный', 'г. Москва, ул. Ленинградский проспект, д. 10', 1, NOW(), NOW()),
            ('Южный', 'г. Москва, ул. Варшавское шоссе, д. 5', 1, NOW(), NOW());
        ");

        // Создаем подразделения по умолчанию
        $DB->Query("
            INSERT IGNORE INTO `courier_service_departments` (`NAME`, `BRANCH_ID`, `IS_ACTIVE`, `CREATED_AT`, `UPDATED_AT`) VALUES
            ('Подразделение 1', 1, 1, NOW(), NOW()),
            ('Подразделение 2', 1, 1, NOW(), NOW()),
            ('Подразделение 3', 2, 1, NOW(), NOW()),
            ('Подразделение 4', 3, 1, NOW(), NOW());
        ");

        // Создаем настройки по умолчанию
        $DB->Query("
            INSERT IGNORE INTO `courier_service_settings` (`SETTING_KEY`, `SETTING_VALUE`, `DESCRIPTION`, `UPDATED_AT`) VALUES
            ('yandex_maps_api_key', '', 'API ключ Яндекс.Карт', NOW()),
            ('abs_api_url', '', 'URL API АБС банка', NOW()),
            ('abs_api_key', '', 'API ключ АБС банка', NOW()),
            ('location_update_interval', '60', 'Интервал обновления геолокации (секунды)', NOW()),
            ('max_delivery_photos', '5', 'Максимальное количество фотографий доставки', NOW()),
            ('contract_template_path', '/upload/courier_service/contracts/', 'Путь к шаблонам договоров', NOW());
        ");
    }

    private function dropTables()
    {
        global $DB;

        $tables = [
            'courier_service_logs',
            'courier_service_documents',
            'courier_service_locations',
            'courier_service_requests',
            'courier_service_departments',
            'courier_service_branches',
            'courier_service_users',
            'courier_service_settings'
        ];

        foreach ($tables as $table) {
            $DB->Query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}