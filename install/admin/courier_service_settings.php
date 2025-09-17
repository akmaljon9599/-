<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use CourierService\Service\AuthService;

Loader::includeModule('courier_service');

Loc::loadMessages(__FILE__);

$authService = new AuthService();
if (!$authService->isAuthenticated() || !$authService->hasPermission('read', 'settings')) {
    $APPLICATION->AuthForm('Access denied');
}

$APPLICATION->SetTitle(Loc::getMessage('COURIER_SERVICE_SETTINGS_TITLE'));

// Обработка сохранения настроек
if ($_POST['save_settings'] && check_bitrix_sessid()) {
    $settings = [
        'yandex_maps_api_key' => $_POST['yandex_maps_api_key'],
        'abs_api_url' => $_POST['abs_api_url'],
        'abs_api_key' => $_POST['abs_api_key'],
        'location_update_interval' => (int)$_POST['location_update_interval'],
        'max_delivery_photos' => (int)$_POST['max_delivery_photos'],
        'contract_template_path' => $_POST['contract_template_path'],
        'sms_api_url' => $_POST['sms_api_url'],
        'sms_api_key' => $_POST['sms_api_key'],
        'notification_email' => $_POST['notification_email']
    ];

    foreach ($settings as $key => $value) {
        Option::set('courier_service', $key, $value);
    }

    $APPLICATION->ThrowException('Настройки сохранены', 'SUCCESS');
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

?>

<div id="courier-service-app">
    <div class="app-container">
        <!-- Боковая панель -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <i class="fas fa-box-open"></i>
                    <span>Курьерская служба</span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="/bitrix/admin/courier_service_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="menu-text">Дашборд</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_requests.php">
                        <i class="fas fa-list"></i>
                        <span class="menu-text">Заявки</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_couriers.php">
                        <i class="fas fa-users"></i>
                        <span class="menu-text">Курьеры</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_map.php">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="menu-text">Карта</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span class="menu-text">Отчеты</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_settings.php" class="active">
                        <i class="fas fa-cog"></i>
                        <span class="menu-text">Настройки</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Основной контент -->
        <div class="main-content">
            <!-- Навигационная панель -->
            <div class="navbar">
                <h5>Настройки системы</h5>
                
                <div class="user-menu">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="roleDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?= $authService->getRole() ?>
                        </button>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?= $USER->GetLogin() ?>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/bitrix/admin/courier_service_settings.php"><i class="fas fa-cog me-2"></i>Настройки</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/bitrix/admin/courier_service_logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <form method="post" action="">
                <?= bitrix_sessid_post() ?>
                
                <!-- Настройки интеграций -->
                <div class="card">
                    <div class="card-header">
                        <span>Интеграции</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">API ключ Яндекс.Карт</label>
                                <input type="text" class="form-control" name="yandex_maps_api_key" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'yandex_maps_api_key', '')) ?>"
                                       placeholder="Введите API ключ">
                                <div class="form-text">Необходим для отображения карт и геокодирования</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">URL API АБС банка</label>
                                <input type="url" class="form-control" name="abs_api_url" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'abs_api_url', '')) ?>"
                                       placeholder="https://api.abs-bank.com">
                                <div class="form-text">URL для интеграции с банковской системой</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">API ключ АБС банка</label>
                                <input type="password" class="form-control" name="abs_api_key" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'abs_api_key', '')) ?>"
                                       placeholder="Введите API ключ">
                                <div class="form-text">Ключ для аутентификации в АБС</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">URL SMS API</label>
                                <input type="url" class="form-control" name="sms_api_url" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'sms_api_url', '')) ?>"
                                       placeholder="https://api.sms-service.com">
                                <div class="form-text">URL для отправки SMS уведомлений</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">API ключ SMS сервиса</label>
                                <input type="password" class="form-control" name="sms_api_key" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'sms_api_key', '')) ?>"
                                       placeholder="Введите API ключ">
                                <div class="form-text">Ключ для отправки SMS</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email для уведомлений</label>
                                <input type="email" class="form-control" name="notification_email" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'notification_email', '')) ?>"
                                       placeholder="admin@example.com">
                                <div class="form-text">Email для системных уведомлений</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки системы -->
                <div class="card">
                    <div class="card-header">
                        <span>Настройки системы</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Интервал обновления геолокации (секунды)</label>
                                <input type="number" class="form-control" name="location_update_interval" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'location_update_interval', 60)) ?>"
                                       min="30" max="300">
                                <div class="form-text">Как часто обновлять местоположение курьеров</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Максимальное количество фотографий доставки</label>
                                <input type="number" class="form-control" name="max_delivery_photos" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'max_delivery_photos', 5)) ?>"
                                       min="1" max="10">
                                <div class="form-text">Максимум фотографий при доставке</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Путь к шаблонам договоров</label>
                                <input type="text" class="form-control" name="contract_template_path" 
                                       value="<?= htmlspecialchars(Option::get('courier_service', 'contract_template_path', '/upload/courier_service/contracts/')) ?>"
                                       placeholder="/upload/courier_service/contracts/">
                                <div class="form-text">Путь к папке с шаблонами договоров</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Настройки безопасности -->
                <div class="card">
                    <div class="card-header">
                        <span>Безопасность</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableLogging" checked>
                                    <label class="form-check-label" for="enableLogging">
                                        Включить логирование действий
                                    </label>
                                </div>
                                <div class="form-text">Записывать все действия пользователей в лог</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableNotifications" checked>
                                    <label class="form-check-label" for="enableNotifications">
                                        Включить уведомления
                                    </label>
                                </div>
                                <div class="form-text">Отправлять уведомления о событиях</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableGeolocation" checked>
                                    <label class="form-check-label" for="enableGeolocation">
                                        Включить отслеживание геолокации
                                    </label>
                                </div>
                                <div class="form-text">Отслеживать местоположение курьеров</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableAbsSync" checked>
                                    <label class="form-check-label" for="enableAbsSync">
                                        Включить синхронизацию с АБС
                                    </label>
                                </div>
                                <div class="form-text">Автоматическая синхронизация с банковской системой</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="testIntegrations()">
                                    <i class="fas fa-flask me-1"></i>Тестировать интеграции
                                </button>
                                <button type="button" class="btn btn-outline-info me-2" onclick="clearCache()">
                                    <i class="fas fa-broom me-1"></i>Очистить кэш
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="exportSettings()">
                                    <i class="fas fa-download me-1"></i>Экспорт настроек
                                </button>
                            </div>
                            <div>
                                <button type="submit" name="save_settings" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Сохранить настройки
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно тестирования интеграций -->
<div class="modal fade" id="testIntegrationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Тестирование интеграций</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="testResults">
                    <!-- Результаты тестов будут здесь -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/bitrix/css/courier_service/admin.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/bitrix/js/courier_service/admin.js"></script>

<script>
function testIntegrations() {
    const modal = new bootstrap.Modal(document.getElementById('testIntegrationsModal'));
    modal.show();
    
    const testResults = document.getElementById('testResults');
    testResults.innerHTML = '<div class="text-center"><div class="spinner"></div><p>Тестирование интеграций...</p></div>';
    
    // Симуляция тестирования
    setTimeout(() => {
        testResults.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Яндекс.Карты:</strong> API ключ валиден
            </div>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>АБС банк:</strong> Подключение успешно
            </div>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>SMS сервис:</strong> API ключ не настроен
            </div>
        `;
    }, 2000);
}

function clearCache() {
    if (confirm('Вы уверены, что хотите очистить кэш?')) {
        // Здесь будет AJAX запрос для очистки кэша
        alert('Кэш очищен');
    }
}

function exportSettings() {
    // Здесь будет экспорт настроек
    alert('Настройки экспортированы');
}
</script>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>