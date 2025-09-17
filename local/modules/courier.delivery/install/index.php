<?php
/**
 * Класс установки модуля курьерской службы
 */

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class courier_delivery extends CModule
{
    public $MODULE_ID = 'courier.delivery';
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

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Курьерская служба';
        $this->MODULE_DESCRIPTION = 'Модуль для управления курьерской доставкой банковских карт с интеграцией АБС и Битрикс 24';
        $this->PARTNER_NAME = 'Courier Delivery Team';
        $this->PARTNER_URI = 'https://courier-delivery.local';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!$this->checkRequirements()) {
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        $this->createUserGroups();
        $this->setDefaultOptions();

        $APPLICATION->IncludeAdminFile('Установка модуля курьерской службы', __DIR__ . '/step.php');

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->removeUserGroups();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Деинсталляция модуля курьерской службы', __DIR__ . '/unstep.php');

        return true;
    }

    public function InstallDB()
    {
        Loader::includeModule($this->MODULE_ID);

        $connection = Application::getConnection();

        // Создание таблицы заявок на доставку
        if (!$connection->isTableExists('courier_delivery_requests')) {
            $connection->query("
                CREATE TABLE courier_delivery_requests (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    ABS_ID varchar(50) NOT NULL,
                    CLIENT_NAME varchar(255) NOT NULL,
                    CLIENT_PHONE varchar(20) NOT NULL,
                    CLIENT_PAN varchar(20) NOT NULL,
                    DELIVERY_ADDRESS text NOT NULL,
                    STATUS varchar(50) DEFAULT 'NEW',
                    COURIER_ID int(11) NULL,
                    BRANCH_ID int(11) NOT NULL,
                    DEPARTMENT_ID int(11) NOT NULL,
                    OPERATOR_ID int(11) NULL,
                    CALL_STATUS varchar(50) DEFAULT 'NOT_CALLED',
                    CARD_TYPE varchar(50) NULL,
                    PROCESSING_DATE datetime NULL,
                    DELIVERY_DATE datetime NULL,
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    UPDATED_DATE datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CREATED_BY int(11) NOT NULL,
                    UPDATED_BY int(11) NULL,
                    NOTES text NULL,
                    PRIMARY KEY (ID),
                    UNIQUE KEY ABS_ID (ABS_ID),
                    KEY COURIER_ID (COURIER_ID),
                    KEY BRANCH_ID (BRANCH_ID),
                    KEY STATUS (STATUS),
                    KEY CREATED_DATE (CREATED_DATE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы курьеров
        if (!$connection->isTableExists('courier_delivery_couriers')) {
            $connection->query("
                CREATE TABLE courier_delivery_couriers (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    USER_ID int(11) NOT NULL,
                    FULL_NAME varchar(255) NOT NULL,
                    PHONE varchar(20) NOT NULL,
                    STATUS varchar(50) DEFAULT 'OFFLINE',
                    BRANCH_ID int(11) NOT NULL,
                    CURRENT_LATITUDE decimal(10,8) NULL,
                    CURRENT_LONGITUDE decimal(11,8) NULL,
                    LAST_LOCATION_UPDATE datetime NULL,
                    IS_ACTIVE char(1) DEFAULT 'Y',
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    UPDATED_DATE datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (ID),
                    UNIQUE KEY USER_ID (USER_ID),
                    KEY BRANCH_ID (BRANCH_ID),
                    KEY STATUS (STATUS),
                    KEY LOCATION (CURRENT_LATITUDE, CURRENT_LONGITUDE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы документов
        if (!$connection->isTableExists('courier_delivery_documents')) {
            $connection->query("
                CREATE TABLE courier_delivery_documents (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    REQUEST_ID int(11) NOT NULL,
                    DOCUMENT_TYPE varchar(50) NOT NULL,
                    FILE_NAME varchar(255) NOT NULL,
                    FILE_PATH varchar(500) NOT NULL,
                    FILE_SIZE int(11) NOT NULL,
                    MIME_TYPE varchar(100) NOT NULL,
                    IS_SIGNED char(1) DEFAULT 'N',
                    SIGNATURE_DATA text NULL,
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    CREATED_BY int(11) NOT NULL,
                    PRIMARY KEY (ID),
                    KEY REQUEST_ID (REQUEST_ID),
                    KEY DOCUMENT_TYPE (DOCUMENT_TYPE),
                    KEY CREATED_DATE (CREATED_DATE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы филиалов
        if (!$connection->isTableExists('courier_delivery_branches')) {
            $connection->query("
                CREATE TABLE courier_delivery_branches (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    NAME varchar(255) NOT NULL,
                    ADDRESS text NOT NULL,
                    PHONE varchar(20) NULL,
                    MANAGER_ID int(11) NULL,
                    IS_ACTIVE char(1) DEFAULT 'Y',
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (ID),
                    KEY IS_ACTIVE (IS_ACTIVE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы подразделений
        if (!$connection->isTableExists('courier_delivery_departments')) {
            $connection->query("
                CREATE TABLE courier_delivery_departments (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    BRANCH_ID int(11) NOT NULL,
                    NAME varchar(255) NOT NULL,
                    CODE varchar(50) NOT NULL,
                    IS_ACTIVE char(1) DEFAULT 'Y',
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (ID),
                    KEY BRANCH_ID (BRANCH_ID),
                    KEY CODE (CODE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы истории статусов
        if (!$connection->isTableExists('courier_delivery_status_history')) {
            $connection->query("
                CREATE TABLE courier_delivery_status_history (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    REQUEST_ID int(11) NOT NULL,
                    OLD_STATUS varchar(50) NULL,
                    NEW_STATUS varchar(50) NOT NULL,
                    COMMENT text NULL,
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    CREATED_BY int(11) NOT NULL,
                    PRIMARY KEY (ID),
                    KEY REQUEST_ID (REQUEST_ID),
                    KEY CREATED_DATE (CREATED_DATE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Создание таблицы логов геолокации
        if (!$connection->isTableExists('courier_delivery_location_log')) {
            $connection->query("
                CREATE TABLE courier_delivery_location_log (
                    ID int(11) NOT NULL AUTO_INCREMENT,
                    COURIER_ID int(11) NOT NULL,
                    LATITUDE decimal(10,8) NOT NULL,
                    LONGITUDE decimal(11,8) NOT NULL,
                    ACCURACY float NULL,
                    CREATED_DATE datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (ID),
                    KEY COURIER_ID (COURIER_ID),
                    KEY CREATED_DATE (CREATED_DATE),
                    KEY LOCATION (LATITUDE, LONGITUDE)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Вставка начальных данных
        $this->insertInitialData();
    }

    public function UnInstallDB()
    {
        $connection = Application::getConnection();

        $tables = [
            'courier_delivery_location_log',
            'courier_delivery_status_history',
            'courier_delivery_documents',
            'courier_delivery_requests',
            'courier_delivery_couriers',
            'courier_delivery_departments',
            'courier_delivery_branches'
        ];

        foreach ($tables as $table) {
            if ($connection->isTableExists($table)) {
                $connection->query("DROP TABLE {$table}");
            }
        }

        Option::delete($this->MODULE_ID);
    }

    public function InstallEvents()
    {
        // Регистрация событий модуля
        RegisterModuleDependences('main', 'OnAfterUserLogin', $this->MODULE_ID, 'Courier\\Delivery\\Service\\CourierService', 'onUserLogin');
        RegisterModuleDependences('main', 'OnAfterUserLogout', $this->MODULE_ID, 'Courier\\Delivery\\Service\\CourierService', 'onUserLogout');
    }

    public function UnInstallEvents()
    {
        UnRegisterModuleDependences('main', 'OnAfterUserLogin', $this->MODULE_ID, 'Courier\\Delivery\\Service\\CourierService', 'onUserLogin');
        UnRegisterModuleDependences('main', 'OnAfterUserLogout', $this->MODULE_ID, 'Courier\\Delivery\\Service\\CourierService', 'onUserLogout');
    }

    public function InstallFiles()
    {
        // Копирование административных файлов
        CopyDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/',
            true,
            true
        );

        // Создание директории для загрузки файлов
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            mkdir($uploadDir . 'documents/', 0755, true);
            mkdir($uploadDir . 'photos/', 0755, true);
            mkdir($uploadDir . 'signatures/', 0755, true);
        }
    }

    public function UnInstallFiles()
    {
        // Удаление административных файлов
        $adminFiles = [
            'courier_delivery_requests.php',
            'courier_delivery_couriers.php',
            'courier_delivery_settings.php',
            'courier_delivery_reports.php'
        ];

        foreach ($adminFiles as $file) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    private function checkRequirements()
    {
        global $APPLICATION;

        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            $APPLICATION->ThrowException('Для работы модуля требуется PHP версии 7.4 или выше');
            return false;
        }

        if (!Loader::includeModule('main')) {
            $APPLICATION->ThrowException('Не удалось подключить модуль main');
            return false;
        }

        return true;
    }

    private function createUserGroups()
    {
        $groups = [
            'COURIER_ADMIN' => 'Администраторы курьерской службы',
            'COURIER_SENIOR' => 'Старшие курьеры',
            'COURIER_OPERATOR' => 'Операторы курьерской службы',
            'COURIER_DELIVERY' => 'Курьеры'
        ];

        foreach ($groups as $code => $name) {
            $group = new CGroup();
            $fields = [
                'ACTIVE' => 'Y',
                'C_SORT' => 100,
                'NAME' => $name,
                'DESCRIPTION' => 'Группа пользователей модуля курьерской службы',
                'STRING_ID' => $code
            ];

            $groupId = $group->Add($fields);
            if ($groupId) {
                Option::set($this->MODULE_ID, 'GROUP_' . $code, $groupId);
            }
        }
    }

    private function removeUserGroups()
    {
        $groups = ['COURIER_ADMIN', 'COURIER_SENIOR', 'COURIER_OPERATOR', 'COURIER_DELIVERY'];

        foreach ($groups as $code) {
            $groupId = Option::get($this->MODULE_ID, 'GROUP_' . $code);
            if ($groupId) {
                $group = new CGroup();
                $group->Delete($groupId);
            }
        }
    }

    private function setDefaultOptions()
    {
        Option::set($this->MODULE_ID, 'abs_api_url', 'https://api.bank.local/gateway');
        Option::set($this->MODULE_ID, 'abs_api_key', '');
        Option::set($this->MODULE_ID, 'yandex_maps_api_key', '');
        Option::set($this->MODULE_ID, 'sms_service_url', '');
        Option::set($this->MODULE_ID, 'sms_service_login', '');
        Option::set($this->MODULE_ID, 'sms_service_password', '');
        Option::set($this->MODULE_ID, 'location_update_interval', '60');
        Option::set($this->MODULE_ID, 'notification_email', '');
        Option::set($this->MODULE_ID, 'max_file_size', '10485760'); // 10MB
        Option::set($this->MODULE_ID, 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx');
    }

    private function insertInitialData()
    {
        $connection = Application::getConnection();

        // Добавление тестовых филиалов
        $connection->query("
            INSERT IGNORE INTO courier_delivery_branches (ID, NAME, ADDRESS, PHONE) VALUES
            (1, 'Центральный филиал', 'г. Москва, ул. Центральная, д. 1', '+7 (495) 123-45-67'),
            (2, 'Северный филиал', 'г. Москва, ул. Северная, д. 10', '+7 (495) 234-56-78'),
            (3, 'Южный филиал', 'г. Москва, ул. Южная, д. 25', '+7 (495) 345-67-89')
        ");

        // Добавление тестовых подразделений
        $connection->query("
            INSERT IGNORE INTO courier_delivery_departments (ID, BRANCH_ID, NAME, CODE) VALUES
            (1, 1, 'Отдел корпоративных клиентов', 'CORP_01'),
            (2, 1, 'Отдел частных клиентов', 'PRIV_01'),
            (3, 2, 'Отдел корпоративных клиентов', 'CORP_02'),
            (4, 2, 'Отдел частных клиентов', 'PRIV_02'),
            (5, 3, 'Отдел корпоративных клиентов', 'CORP_03'),
            (6, 3, 'Отдел частных клиентов', 'PRIV_03')
        ");
    }
}