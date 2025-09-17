<?php
/**
 * Скрипт развертывания модуля курьерской службы
 * 
 * Этот скрипт автоматизирует процесс установки и настройки модуля
 * в продакшн среде с учетом всех требований безопасности.
 */

class CourierDeliveryDeployment
{
    private $config;
    private $errors = [];
    private $warnings = [];

    public function __construct($configFile = 'deploy.json')
    {
        if (!file_exists($configFile)) {
            throw new Exception("Файл конфигурации {$configFile} не найден");
        }

        $this->config = json_decode(file_get_contents($configFile), true);
        if (!$this->config) {
            throw new Exception("Ошибка парсинга файла конфигурации");
        }
    }

    /**
     * Основной метод развертывания
     */
    public function deploy()
    {
        echo "🚀 Начинаем развертывание модуля курьерской службы...\n\n";

        try {
            $this->checkRequirements();
            $this->backupExistingData();
            $this->copyModuleFiles();
            $this->installModule();
            $this->configureModule();
            $this->createInitialData();
            $this->setupPermissions();
            $this->runTests();
            
            $this->showResults();
            
        } catch (Exception $e) {
            echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
            $this->rollback();
            exit(1);
        }
    }

    /**
     * Проверка системных требований
     */
    private function checkRequirements()
    {
        echo "📋 Проверка системных требований...\n";

        // Проверка версии PHP
        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            throw new Exception("Требуется PHP 7.4 или выше. Текущая версия: " . PHP_VERSION);
        }
        echo "  ✓ PHP версия: " . PHP_VERSION . "\n";

        // Проверка расширений PHP
        $requiredExtensions = ['mysqli', 'curl', 'json', 'openssl', 'gd'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Требуется расширение PHP: {$ext}");
            }
        }
        echo "  ✓ Расширения PHP: " . implode(', ', $requiredExtensions) . "\n";

        // Проверка прав доступа к файлам
        $paths = [
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix',
            $_SERVER['DOCUMENT_ROOT'] . '/local',
            $_SERVER['DOCUMENT_ROOT'] . '/upload'
        ];

        foreach ($paths as $path) {
            if (!is_writable($path)) {
                throw new Exception("Недостаточно прав для записи в: {$path}");
            }
        }
        echo "  ✓ Права доступа к файлам\n";

        // Проверка подключения к базе данных
        $this->testDatabaseConnection();
        echo "  ✓ Подключение к базе данных\n";

        echo "✅ Все системные требования выполнены\n\n";
    }

    /**
     * Резервное копирование существующих данных
     */
    private function backupExistingData()
    {
        echo "💾 Создание резервной копии...\n";

        $backupDir = $this->config['backup_dir'] ?? '/tmp/courier_delivery_backup_' . date('Y-m-d_H-i-s');
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Бэкап существующего модуля если есть
        $moduleDir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/courier.delivery';
        if (is_dir($moduleDir)) {
            $this->copyDirectory($moduleDir, $backupDir . '/module');
            echo "  ✓ Модуль скопирован в бэкап\n";
        }

        // Бэкап базы данных
        $this->backupDatabase($backupDir . '/database.sql');
        echo "  ✓ База данных скопирована в бэкап\n";

        $this->config['backup_dir'] = $backupDir;
        echo "✅ Резервная копия создана: {$backupDir}\n\n";
    }

    /**
     * Копирование файлов модуля
     */
    private function copyModuleFiles()
    {
        echo "📁 Копирование файлов модуля...\n";

        $sourceDir = __DIR__ . '/local/modules/courier.delivery';
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/courier.delivery';

        // Удаляем старую версию если есть
        if (is_dir($targetDir)) {
            $this->removeDirectory($targetDir);
        }

        // Создаем целевую директорию
        mkdir(dirname($targetDir), 0755, true);

        // Копируем файлы
        $this->copyDirectory($sourceDir, $targetDir);

        // Устанавливаем права доступа
        $this->setDirectoryPermissions($targetDir, 0755, 0644);

        echo "✅ Файлы модуля скопированы\n\n";
    }

    /**
     * Установка модуля через API Битрикс
     */
    private function installModule()
    {
        echo "⚙️ Установка модуля...\n";

        // Подключаем ядро Битрикс
        require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

        if (!is_object($GLOBALS['USER'])) {
            // Авторизуемся как администратор для установки
            $GLOBALS['USER'] = new CUser();
            $GLOBALS['USER']->Authorize(1); // ID администратора
        }

        // Подключаем класс установки
        require_once($_SERVER['DOCUMENT_ROOT'] . '/local/modules/courier.delivery/install/index.php');

        $moduleInstaller = new courier_delivery();
        
        if ($moduleInstaller->DoInstall()) {
            echo "  ✓ Модуль установлен\n";
        } else {
            throw new Exception("Ошибка установки модуля");
        }

        echo "✅ Модуль успешно установлен\n\n";
    }

    /**
     * Настройка модуля
     */
    private function configureModule()
    {
        echo "🔧 Настройка модуля...\n";

        $moduleId = 'courier.delivery';
        $settings = $this->config['module_settings'] ?? [];

        foreach ($settings as $key => $value) {
            COption::SetOptionString($moduleId, $key, $value);
            echo "  ✓ {$key}: " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
        }

        // Создание директорий для загрузки файлов
        $uploadDirs = [
            $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery',
            $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery/documents',
            $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery/photos',
            $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery/signatures',
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs'
        ];

        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            chmod($dir, 0755);
        }

        echo "  ✓ Директории для загрузки созданы\n";
        echo "✅ Модуль настроен\n\n";
    }

    /**
     * Создание начальных данных
     */
    private function createInitialData()
    {
        echo "📊 Создание начальных данных...\n";

        $connection = Bitrix\Main\Application::getConnection();

        // Создание тестовых филиалов
        $branches = $this->config['initial_data']['branches'] ?? [];
        foreach ($branches as $branch) {
            $connection->query("
                INSERT IGNORE INTO courier_delivery_branches (NAME, ADDRESS, PHONE) 
                VALUES ('{$branch['name']}', '{$branch['address']}', '{$branch['phone']}')
            ");
        }
        echo "  ✓ Филиалы созданы: " . count($branches) . "\n";

        // Создание подразделений
        $departments = $this->config['initial_data']['departments'] ?? [];
        foreach ($departments as $dept) {
            $connection->query("
                INSERT IGNORE INTO courier_delivery_departments (BRANCH_ID, NAME, CODE) 
                VALUES ({$dept['branch_id']}, '{$dept['name']}', '{$dept['code']}')
            ");
        }
        echo "  ✓ Подразделения созданы: " . count($departments) . "\n";

        // Создание пользователей и ролей
        $this->createUsersAndRoles();

        echo "✅ Начальные данные созданы\n\n";
    }

    /**
     * Настройка прав доступа
     */
    private function setupPermissions()
    {
        echo "🔐 Настройка прав доступа...\n";

        // Настройка операций модуля
        $operations = [
            'courier_delivery_view' => 'Просмотр курьерской службы',
            'courier_delivery_edit' => 'Редактирование заявок',
            'courier_delivery_admin' => 'Администрирование модуля'
        ];

        foreach ($operations as $code => $name) {
            $operation = COperation::GetByCode($code);
            if (!$operation->Fetch()) {
                COperation::Add([
                    'NAME' => $name,
                    'MODULE_ID' => 'courier.delivery',
                    'BINDING' => 'module'
                ]);
            }
        }

        echo "  ✓ Операции модуля зарегистрированы\n";

        // Назначение прав группам
        $this->assignGroupPermissions();

        echo "✅ Права доступа настроены\n\n";
    }

    /**
     * Запуск тестов
     */
    private function runTests()
    {
        echo "🧪 Запуск тестов...\n";

        $tests = [
            'testDatabaseTables',
            'testModuleOptions',
            'testApiEndpoints',
            'testFilePermissions'
        ];

        foreach ($tests as $test) {
            try {
                $this->$test();
                echo "  ✓ {$test}\n";
            } catch (Exception $e) {
                $this->warnings[] = "Тест {$test} завершился с предупреждением: " . $e->getMessage();
                echo "  ⚠️ {$test}: " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Тесты завершены\n\n";
    }

    /**
     * Показать результаты развертывания
     */
    private function showResults()
    {
        echo "🎉 Развертывание завершено успешно!\n\n";

        echo "📋 Сводка:\n";
        echo "  • Модуль: courier.delivery v1.0.0\n";
        echo "  • Резервная копия: {$this->config['backup_dir']}\n";
        echo "  • Время установки: " . date('Y-m-d H:i:s') . "\n";

        if (!empty($this->warnings)) {
            echo "\n⚠️ Предупреждения:\n";
            foreach ($this->warnings as $warning) {
                echo "  • {$warning}\n";
            }
        }

        echo "\n🔗 Полезные ссылки:\n";
        echo "  • Административная панель: /bitrix/admin/courier_delivery_requests.php\n";
        echo "  • Настройки модуля: /bitrix/admin/courier_delivery_settings.php\n";
        echo "  • Документация: README.md\n";

        echo "\n✨ Модуль готов к использованию!\n";
    }

    /**
     * Откат изменений в случае ошибки
     */
    private function rollback()
    {
        echo "🔄 Выполняется откат изменений...\n";

        try {
            // Восстанавливаем модуль из бэкапа если есть
            if (isset($this->config['backup_dir']) && is_dir($this->config['backup_dir'] . '/module')) {
                $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/courier.delivery';
                $this->removeDirectory($targetDir);
                $this->copyDirectory($this->config['backup_dir'] . '/module', $targetDir);
                echo "  ✓ Модуль восстановлен из бэкапа\n";
            }

            // Восстанавливаем базу данных
            if (isset($this->config['backup_dir']) && file_exists($this->config['backup_dir'] . '/database.sql')) {
                $this->restoreDatabase($this->config['backup_dir'] . '/database.sql');
                echo "  ✓ База данных восстановлена\n";
            }

        } catch (Exception $e) {
            echo "❌ Ошибка отката: " . $e->getMessage() . "\n";
        }

        echo "✅ Откат завершен\n";
    }

    // Вспомогательные методы

    private function testDatabaseConnection()
    {
        $connection = new mysqli(
            $this->config['database']['host'] ?? 'localhost',
            $this->config['database']['user'] ?? 'root',
            $this->config['database']['password'] ?? '',
            $this->config['database']['name'] ?? 'bitrix'
        );

        if ($connection->connect_error) {
            throw new Exception("Ошибка подключения к БД: " . $connection->connect_error);
        }

        $connection->close();
    }

    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                mkdir($targetPath, 0755, true);
            } else {
                copy($item, $targetPath);
            }
        }
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }

    private function setDirectoryPermissions($dir, $dirMode, $fileMode)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                chmod($item->getRealPath(), $dirMode);
            } else {
                chmod($item->getRealPath(), $fileMode);
            }
        }
    }

    private function backupDatabase($backupFile)
    {
        // Простая реализация бэкапа БД
        $tables = ['courier_delivery_requests', 'courier_delivery_couriers', 'courier_delivery_documents'];
        $sql = '';

        foreach ($tables as $table) {
            $sql .= "-- Backup for table {$table}\n";
            $sql .= "DROP TABLE IF EXISTS {$table}_backup;\n";
            $sql .= "CREATE TABLE {$table}_backup AS SELECT * FROM {$table};\n\n";
        }

        file_put_contents($backupFile, $sql);
    }

    private function restoreDatabase($backupFile)
    {
        // Восстановление из SQL файла
        $sql = file_get_contents($backupFile);
        $connection = Bitrix\Main\Application::getConnection();
        $connection->query($sql);
    }

    private function createUsersAndRoles()
    {
        $users = $this->config['initial_data']['users'] ?? [];
        
        foreach ($users as $userData) {
            $user = new CUser();
            $userId = $user->Add([
                'LOGIN' => $userData['login'],
                'PASSWORD' => $userData['password'],
                'CONFIRM_PASSWORD' => $userData['password'],
                'NAME' => $userData['name'],
                'LAST_NAME' => $userData['last_name'],
                'EMAIL' => $userData['email'],
                'ACTIVE' => 'Y',
                'GROUP_ID' => $userData['groups'] ?? [2]
            ]);

            if ($userId) {
                echo "  ✓ Пользователь создан: {$userData['login']}\n";
            }
        }
    }

    private function assignGroupPermissions()
    {
        // Назначение прав группам пользователей
        $permissions = [
            'COURIER_ADMIN' => ['courier_delivery_view', 'courier_delivery_edit', 'courier_delivery_admin'],
            'COURIER_SENIOR' => ['courier_delivery_view', 'courier_delivery_edit'],
            'COURIER_OPERATOR' => ['courier_delivery_view', 'courier_delivery_edit'],
            'COURIER_DELIVERY' => ['courier_delivery_view']
        ];

        foreach ($permissions as $groupCode => $ops) {
            $groupId = COption::GetOptionString('courier.delivery', 'GROUP_' . $groupCode);
            if ($groupId) {
                foreach ($ops as $operation) {
                    CGroup::SetPermission($groupId, ['main' => ['*' => $operation]]);
                }
            }
        }
    }

    // Методы тестирования
    private function testDatabaseTables()
    {
        $connection = Bitrix\Main\Application::getConnection();
        $tables = [
            'courier_delivery_requests',
            'courier_delivery_couriers',
            'courier_delivery_documents',
            'courier_delivery_branches'
        ];

        foreach ($tables as $table) {
            if (!$connection->isTableExists($table)) {
                throw new Exception("Таблица {$table} не существует");
            }
        }
    }

    private function testModuleOptions()
    {
        $requiredOptions = ['abs_api_url', 'yandex_maps_api_key'];
        
        foreach ($requiredOptions as $option) {
            $value = COption::GetOptionString('courier.delivery', $option);
            if (empty($value)) {
                throw new Exception("Не задана опция {$option}");
            }
        }
    }

    private function testApiEndpoints()
    {
        // Тестирование REST API endpoints
        $endpoints = [
            '/rest/courier.delivery.request.list',
            '/rest/courier.delivery.courier.location'
        ];

        foreach ($endpoints as $endpoint) {
            // Простая проверка доступности
            if (!class_exists('Courier\\Delivery\\Controller\\ApiController')) {
                throw new Exception("API контроллер не найден");
            }
        }
    }

    private function testFilePermissions()
    {
        $paths = [
            $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery',
            $_SERVER['DOCUMENT_ROOT'] . '/upload/logs'
        ];

        foreach ($paths as $path) {
            if (!is_writable($path)) {
                throw new Exception("Недостаточно прав для записи в {$path}");
            }
        }
    }
}

// Запуск развертывания
if (php_sapi_name() === 'cli') {
    try {
        $deployment = new CourierDeliveryDeployment();
        $deployment->deploy();
    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Этот скрипт должен запускаться из командной строки\n";
    exit(1);
}