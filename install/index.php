<?php
/**
 * Модуль "Курьерская служба" для Bitrix24
 * Установочный файл модуля
 */

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

        if (CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
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
        // Копирование файлов модуля
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/components',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/js',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID,
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/css',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID,
            true,
            true
        );

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );

        DeleteDirFiles(
            __DIR__ . '/components',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components'
        );

        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        DeleteDirFilesEx('/bitrix/css/' . $this->MODULE_ID);

        return true;
    }

    public function InstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            // Создание таблиц базы данных
            $this->createTables();
            
            // Создание групп пользователей
            $this->createUserGroups();
            
            // Установка прав доступа
            $this->setPermissions();
        }

        return true;
    }

    public function UnInstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            // Удаление таблиц базы данных
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
            'OnBuildGlobalMenu'
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
            'OnBuildGlobalMenu'
        );

        return true;
    }

    private function createTables()
    {
        global $DB;

        // Таблица заявок
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_requests` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `REQUEST_NUMBER` varchar(50) NOT NULL,
                `ABS_ID` varchar(50) DEFAULT NULL,
                `CLIENT_NAME` varchar(255) NOT NULL,
                `CLIENT_PHONE` varchar(20) NOT NULL,
                `CLIENT_ADDRESS` text NOT NULL,
                `PAN` varchar(20) NOT NULL,
                `CARD_TYPE` varchar(20) DEFAULT NULL,
                `STATUS` enum('new','waiting','delivered','rejected','cancelled') DEFAULT 'new',
                `CALL_STATUS` enum('not_called','successful','failed') DEFAULT 'not_called',
                `COURIER_ID` int(11) DEFAULT NULL,
                `BRANCH_ID` int(11) NOT NULL,
                `DEPARTMENT_ID` int(11) DEFAULT NULL,
                `OPERATOR_ID` int(11) DEFAULT NULL,
                `CREATED_DATE` datetime NOT NULL,
                `PROCESSED_DATE` datetime DEFAULT NULL,
                `DELIVERY_DATE` datetime DEFAULT NULL,
                `REJECTION_REASON` text DEFAULT NULL,
                `COURIER_PHONE` varchar(20) DEFAULT NULL,
                `DELIVERY_PHOTOS` text DEFAULT NULL,
                `SIGNATURE_DATA` longtext DEFAULT NULL,
                `CONTRACT_PDF` varchar(255) DEFAULT NULL,
                `CREATED_BY` int(11) NOT NULL,
                `MODIFIED_BY` int(11) DEFAULT NULL,
                `DATE_CREATE` datetime NOT NULL,
                `DATE_MODIFY` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `REQUEST_NUMBER` (`REQUEST_NUMBER`),
                KEY `ABS_ID` (`ABS_ID`),
                KEY `STATUS` (`STATUS`),
                KEY `COURIER_ID` (`COURIER_ID`),
                KEY `BRANCH_ID` (`BRANCH_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица курьеров
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_couriers` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `USER_ID` int(11) NOT NULL,
                `NAME` varchar(255) NOT NULL,
                `PHONE` varchar(20) NOT NULL,
                `BRANCH_ID` int(11) NOT NULL,
                `STATUS` enum('active','inactive','on_delivery') DEFAULT 'active',
                `LAST_LOCATION_LAT` decimal(10,8) DEFAULT NULL,
                `LAST_LOCATION_LON` decimal(11,8) DEFAULT NULL,
                `LAST_ACTIVITY` datetime DEFAULT NULL,
                `IS_ONLINE` tinyint(1) DEFAULT 0,
                `CREATED_DATE` datetime NOT NULL,
                `MODIFIED_DATE` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `USER_ID` (`USER_ID`),
                KEY `STATUS` (`STATUS`),
                KEY `BRANCH_ID` (`BRANCH_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица филиалов
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_branches` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `ADDRESS` text NOT NULL,
                `PHONE` varchar(20) DEFAULT NULL,
                `EMAIL` varchar(255) DEFAULT NULL,
                `MANAGER_ID` int(11) DEFAULT NULL,
                `IS_ACTIVE` tinyint(1) DEFAULT 1,
                `CREATED_DATE` datetime NOT NULL,
                `MODIFIED_DATE` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                KEY `IS_ACTIVE` (`IS_ACTIVE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица подразделений
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_departments` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `NAME` varchar(255) NOT NULL,
                `BRANCH_ID` int(11) NOT NULL,
                `MANAGER_ID` int(11) DEFAULT NULL,
                `IS_ACTIVE` tinyint(1) DEFAULT 1,
                `CREATED_DATE` datetime NOT NULL,
                `MODIFIED_DATE` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                KEY `BRANCH_ID` (`BRANCH_ID`),
                KEY `IS_ACTIVE` (`IS_ACTIVE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица документов
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_documents` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `REQUEST_ID` int(11) NOT NULL,
                `TYPE` enum('passport','contract','signature','delivery_photo','other') NOT NULL,
                `FILE_PATH` varchar(500) NOT NULL,
                `FILE_NAME` varchar(255) NOT NULL,
                `FILE_SIZE` int(11) DEFAULT NULL,
                `MIME_TYPE` varchar(100) DEFAULT NULL,
                `UPLOADED_BY` int(11) NOT NULL,
                `UPLOAD_DATE` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `REQUEST_ID` (`REQUEST_ID`),
                KEY `TYPE` (`TYPE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица логов
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_logs` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `USER_ID` int(11) NOT NULL,
                `ACTION` varchar(100) NOT NULL,
                `ENTITY_TYPE` varchar(50) NOT NULL,
                `ENTITY_ID` int(11) NOT NULL,
                `OLD_DATA` longtext DEFAULT NULL,
                `NEW_DATA` longtext DEFAULT NULL,
                `IP_ADDRESS` varchar(45) DEFAULT NULL,
                `USER_AGENT` text DEFAULT NULL,
                `CREATED_DATE` datetime NOT NULL,
                PRIMARY KEY (`ID`),
                KEY `USER_ID` (`USER_ID`),
                KEY `ACTION` (`ACTION`),
                KEY `ENTITY_TYPE` (`ENTITY_TYPE`),
                KEY `CREATED_DATE` (`CREATED_DATE`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица настроек
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `courier_settings` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `SETTING_KEY` varchar(100) NOT NULL,
                `SETTING_VALUE` longtext DEFAULT NULL,
                `DESCRIPTION` text DEFAULT NULL,
                `CREATED_DATE` datetime NOT NULL,
                `MODIFIED_DATE` datetime DEFAULT NULL,
                PRIMARY KEY (`ID`),
                UNIQUE KEY `SETTING_KEY` (`SETTING_KEY`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function dropTables()
    {
        global $DB;

        $tables = [
            'courier_settings',
            'courier_logs',
            'courier_documents',
            'courier_departments',
            'courier_branches',
            'courier_couriers',
            'courier_requests'
        ];

        foreach ($tables as $table) {
            $DB->Query("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function createUserGroups()
    {
        $groups = [
            'COURIER_ADMIN' => 'Администраторы курьерской службы',
            'COURIER_SENIOR' => 'Старшие курьеры',
            'COURIER_DELIVERY' => 'Курьеры',
            'COURIER_OPERATOR' => 'Операторы курьерской службы'
        ];

        foreach ($groups as $code => $name) {
            $group = new CGroup();
            $arFields = [
                'ACTIVE' => 'Y',
                'C_SORT' => 100,
                'NAME' => $name,
                'DESCRIPTION' => $name,
                'STRING_ID' => $code
            ];
            $group->Add($arFields);
        }
    }

    private function setPermissions()
    {
        // Установка прав доступа для групп пользователей
        $permissions = [
            'COURIER_ADMIN' => 'W', // Полный доступ
            'COURIER_SENIOR' => 'R', // Чтение и редактирование
            'COURIER_DELIVERY' => 'R', // Ограниченный доступ
            'COURIER_OPERATOR' => 'R' // Ограниченный доступ
        ];

        foreach ($permissions as $groupCode => $permission) {
            $rsGroup = CGroup::GetList($by = 'id', $order = 'asc', ['STRING_ID' => $groupCode]);
            if ($arGroup = $rsGroup->Fetch()) {
                $APPLICATION->SetGroupRight($this->MODULE_ID, $arGroup['ID'], $permission);
            }
        }
    }
}