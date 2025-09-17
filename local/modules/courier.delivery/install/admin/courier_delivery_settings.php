<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Courier\Delivery\Api\AbsGateway;
use Courier\Delivery\Api\YandexMaps;
use Courier\Delivery\Api\SmsService;

// Проверяем права доступа
if (!$USER->IsAdmin() && !$USER->CanDoOperation('courier_delivery_settings'))
{
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

// Подключаем модуль
if (!Loader::includeModule('courier.delivery'))
{
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    ShowError("Модуль курьерской службы не установлен");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$APPLICATION->SetTitle("Настройки модуля курьерской службы");

$request = Application::getInstance()->getContext()->getRequest();
$moduleId = 'courier.delivery';

// Обработка сохранения настроек
if ($request->isPost() && check_bitrix_sessid()) {
    $action = $request->get('action');
    
    if ($action === 'save_settings') {
        // АБС настройки
        Option::set($moduleId, 'abs_api_url', $request->get('abs_api_url'));
        Option::set($moduleId, 'abs_api_key', $request->get('abs_api_key'));
        
        // Яндекс.Карты настройки
        Option::set($moduleId, 'yandex_maps_api_key', $request->get('yandex_maps_api_key'));
        
        // SMS сервис настройки
        Option::set($moduleId, 'sms_service_url', $request->get('sms_service_url'));
        Option::set($moduleId, 'sms_service_login', $request->get('sms_service_login'));
        Option::set($moduleId, 'sms_service_password', $request->get('sms_service_password'));
        
        // Общие настройки
        Option::set($moduleId, 'location_update_interval', (int)$request->get('location_update_interval'));
        Option::set($moduleId, 'notification_email', $request->get('notification_email'));
        Option::set($moduleId, 'admin_phones', $request->get('admin_phones'));
        Option::set($moduleId, 'max_file_size', (int)$request->get('max_file_size'));
        Option::set($moduleId, 'allowed_file_types', $request->get('allowed_file_types'));
        
        LocalRedirect($APPLICATION->GetCurPageParam("success=Y", array("success")));
    } elseif ($action === 'test_abs_connection') {
        $absGateway = new AbsGateway();
        $testResult = $absGateway->testConnection();
        
        if ($testResult['success']) {
            ShowNote("Соединение с АБС успешно установлено");
        } else {
            ShowError("Ошибка подключения к АБС: " . $testResult['message']);
        }
    } elseif ($action === 'test_yandex_maps') {
        $yandexMaps = new YandexMaps();
        $testResult = $yandexMaps->testApiKey();
        
        if ($testResult['success']) {
            ShowNote("API ключ Яндекс.Карт работает корректно");
        } else {
            ShowError("Ошибка API ключа Яндекс.Карт: " . $testResult['message']);
        }
    } elseif ($action === 'test_sms_service') {
        $smsService = new SmsService();
        $testResult = $smsService->testConnection();
        
        if ($testResult['success']) {
            ShowNote("SMS-сервис подключен успешно: " . $testResult['message']);
        } else {
            ShowError("Ошибка подключения к SMS-сервису: " . $testResult['message']);
        }
    }
}

// Получаем текущие настройки
$settings = [
    'abs_api_url' => Option::get($moduleId, 'abs_api_url', ''),
    'abs_api_key' => Option::get($moduleId, 'abs_api_key', ''),
    'yandex_maps_api_key' => Option::get($moduleId, 'yandex_maps_api_key', ''),
    'sms_service_url' => Option::get($moduleId, 'sms_service_url', ''),
    'sms_service_login' => Option::get($moduleId, 'sms_service_login', ''),
    'sms_service_password' => Option::get($moduleId, 'sms_service_password', ''),
    'location_update_interval' => Option::get($moduleId, 'location_update_interval', '60'),
    'notification_email' => Option::get($moduleId, 'notification_email', ''),
    'admin_phones' => Option::get($moduleId, 'admin_phones', ''),
    'max_file_size' => Option::get($moduleId, 'max_file_size', '10485760'),
    'allowed_file_types' => Option::get($moduleId, 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx')
];

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($request->get('success') === 'Y') {
    ShowNote("Настройки успешно сохранены");
}

?>

<div class="courier-delivery-settings">
    <form method="POST" class="adm-detail-form">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="save_settings">
        
        <!-- Вкладки -->
        <div class="adm-detail-tabs">
            <span class="adm-detail-tab adm-detail-tab-active" onclick="showTab('abs-settings')">АБС интеграция</span>
            <span class="adm-detail-tab" onclick="showTab('maps-settings')">Яндекс.Карты</span>
            <span class="adm-detail-tab" onclick="showTab('sms-settings')">SMS-сервис</span>
            <span class="adm-detail-tab" onclick="showTab('general-settings')">Общие</span>
            <span class="adm-detail-tab" onclick="showTab('system-info')">Информация</span>
        </div>

        <!-- АБС настройки -->
        <div id="abs-settings" class="adm-detail-content">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l">
                        <label for="abs_api_url">URL API АБС:</label>
                    </td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <input type="url" id="abs_api_url" name="abs_api_url" 
                               value="<?= htmlspecialchars($settings['abs_api_url']) ?>" 
                               size="50" placeholder="https://api.bank.local/gateway">
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="abs_api_key">API ключ АБС:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="password" id="abs_api_key" name="abs_api_key" 
                               value="<?= htmlspecialchars($settings['abs_api_key']) ?>" 
                               size="50" placeholder="Введите API ключ">
                        <br><small>API ключ для авторизации в системе АБС</small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"></td>
                    <td class="adm-detail-content-cell-r">
                        <input type="submit" name="test_abs" value="Проверить соединение" 
                               class="adm-btn" onclick="testConnection('abs')">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Яндекс.Карты настройки -->
        <div id="maps-settings" class="adm-detail-content" style="display: none;">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l">
                        <label for="yandex_maps_api_key">API ключ Яндекс.Карт:</label>
                    </td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <input type="text" id="yandex_maps_api_key" name="yandex_maps_api_key" 
                               value="<?= htmlspecialchars($settings['yandex_maps_api_key']) ?>" 
                               size="50" placeholder="Введите API ключ Яндекс.Карт">
                        <br><small>
                            Получить ключ можно в <a href="https://developer.tech.yandex.ru/" target="_blank">консоли разработчика Яндекс</a>
                        </small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"></td>
                    <td class="adm-detail-content-cell-r">
                        <input type="submit" name="test_yandex" value="Проверить API ключ" 
                               class="adm-btn" onclick="testConnection('yandex_maps')">
                    </td>
                </tr>
            </table>
        </div>

        <!-- SMS-сервис настройки -->
        <div id="sms-settings" class="adm-detail-content" style="display: none;">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l">
                        <label for="sms_service_url">URL SMS-сервиса:</label>
                    </td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <input type="url" id="sms_service_url" name="sms_service_url" 
                               value="<?= htmlspecialchars($settings['sms_service_url']) ?>" 
                               size="50" placeholder="https://sms.provider.com/api">
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="sms_service_login">Логин SMS-сервиса:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="text" id="sms_service_login" name="sms_service_login" 
                               value="<?= htmlspecialchars($settings['sms_service_login']) ?>" 
                               size="30" placeholder="Логин">
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="sms_service_password">Пароль SMS-сервиса:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="password" id="sms_service_password" name="sms_service_password" 
                               value="<?= htmlspecialchars($settings['sms_service_password']) ?>" 
                               size="30" placeholder="Пароль">
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"></td>
                    <td class="adm-detail-content-cell-r">
                        <input type="submit" name="test_sms" value="Проверить соединение" 
                               class="adm-btn" onclick="testConnection('sms_service')">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Общие настройки -->
        <div id="general-settings" class="adm-detail-content" style="display: none;">
            <table class="adm-detail-content-table edit-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l">
                        <label for="location_update_interval">Интервал обновления геолокации (сек):</label>
                    </td>
                    <td width="60%" class="adm-detail-content-cell-r">
                        <input type="number" id="location_update_interval" name="location_update_interval" 
                               value="<?= htmlspecialchars($settings['location_update_interval']) ?>" 
                               min="30" max="300" size="10">
                        <br><small>Как часто курьеры отправляют свое местоположение (30-300 секунд)</small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="notification_email">Email для уведомлений:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="email" id="notification_email" name="notification_email" 
                               value="<?= htmlspecialchars($settings['notification_email']) ?>" 
                               size="40" placeholder="admin@bank.com">
                        <br><small>Email администратора для получения уведомлений</small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="admin_phones">Телефоны администраторов:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="text" id="admin_phones" name="admin_phones" 
                               value="<?= htmlspecialchars($settings['admin_phones']) ?>" 
                               size="50" placeholder="+7(xxx)xxx-xx-xx,+7(xxx)xxx-xx-xx">
                        <br><small>Телефоны через запятую для SMS-уведомлений о проблемах</small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="max_file_size">Максимальный размер файла (байт):</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="number" id="max_file_size" name="max_file_size" 
                               value="<?= htmlspecialchars($settings['max_file_size']) ?>" 
                               min="1048576" size="15">
                        <br><small>Максимальный размер загружаемых файлов (по умолчанию 10MB = 10485760 байт)</small>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l">
                        <label for="allowed_file_types">Разрешенные типы файлов:</label>
                    </td>
                    <td class="adm-detail-content-cell-r">
                        <input type="text" id="allowed_file_types" name="allowed_file_types" 
                               value="<?= htmlspecialchars($settings['allowed_file_types']) ?>" 
                               size="50">
                        <br><small>Расширения файлов через запятую (например: jpg,png,pdf,doc)</small>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Системная информация -->
        <div id="system-info" class="adm-detail-content" style="display: none;">
            <table class="adm-detail-content-table">
                <tr>
                    <td width="40%" class="adm-detail-content-cell-l"><strong>Версия модуля:</strong></td>
                    <td width="60%" class="adm-detail-content-cell-r">1.0.0</td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Версия PHP:</strong></td>
                    <td class="adm-detail-content-cell-r"><?= PHP_VERSION ?></td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Версия Битрикс:</strong></td>
                    <td class="adm-detail-content-cell-r"><?= SM_VERSION ?></td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Статус АБС:</strong></td>
                    <td class="adm-detail-content-cell-r">
                        <span id="abs-status">Проверка...</span>
                        <button type="button" onclick="checkServiceStatus('abs')" class="adm-btn">Обновить</button>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Статус Яндекс.Карт:</strong></td>
                    <td class="adm-detail-content-cell-r">
                        <span id="maps-status">Проверка...</span>
                        <button type="button" onclick="checkServiceStatus('maps')" class="adm-btn">Обновить</button>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Статус SMS-сервиса:</strong></td>
                    <td class="adm-detail-content-cell-r">
                        <span id="sms-status">Проверка...</span>
                        <button type="button" onclick="checkServiceStatus('sms')" class="adm-btn">Обновить</button>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Директория загрузок:</strong></td>
                    <td class="adm-detail-content-cell-r">
                        <?= $_SERVER['DOCUMENT_ROOT'] ?>/upload/courier_delivery/
                        <?php if (is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/courier_delivery/')): ?>
                            <span class="text-success">✓ Доступна для записи</span>
                        <?php else: ?>
                            <span class="text-danger">✗ Недоступна для записи</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="adm-detail-content-cell-l"><strong>Последняя очистка логов:</strong></td>
                    <td class="adm-detail-content-cell-r">
                        <?= Option::get($moduleId, 'last_log_cleanup', 'Никогда') ?>
                        <button type="button" onclick="cleanupLogs()" class="adm-btn">Очистить сейчас</button>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Кнопки сохранения -->
        <div class="adm-detail-content-btns">
            <input type="submit" value="Сохранить" class="adm-btn-save">
            <input type="reset" value="Сбросить" class="adm-btn">
        </div>
    </form>
</div>

<script>
function showTab(tabId) {
    // Скрываем все вкладки
    const contents = document.querySelectorAll('.adm-detail-content');
    contents.forEach(content => content.style.display = 'none');
    
    // Убираем активный класс со всех вкладок
    const tabs = document.querySelectorAll('.adm-detail-tab');
    tabs.forEach(tab => tab.classList.remove('adm-detail-tab-active'));
    
    // Показываем выбранную вкладку
    document.getElementById(tabId).style.display = 'block';
    event.target.classList.add('adm-detail-tab-active');
}

function testConnection(service) {
    event.preventDefault();
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="test_${service}_connection">
        <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
    `;
    
    // Добавляем текущие значения полей для тестирования
    if (service === 'abs') {
        form.innerHTML += `
            <input type="hidden" name="abs_api_url" value="${document.getElementById('abs_api_url').value}">
            <input type="hidden" name="abs_api_key" value="${document.getElementById('abs_api_key').value}">
        `;
    } else if (service === 'yandex_maps') {
        form.innerHTML += `
            <input type="hidden" name="yandex_maps_api_key" value="${document.getElementById('yandex_maps_api_key').value}">
        `;
    } else if (service === 'sms_service') {
        form.innerHTML += `
            <input type="hidden" name="sms_service_url" value="${document.getElementById('sms_service_url').value}">
            <input type="hidden" name="sms_service_login" value="${document.getElementById('sms_service_login').value}">
            <input type="hidden" name="sms_service_password" value="${document.getElementById('sms_service_password').value}">
        `;
    }
    
    document.body.appendChild(form);
    form.submit();
}

function checkServiceStatus(service) {
    // AJAX проверка статуса сервисов
    const statusElement = document.getElementById(service + '-status');
    statusElement.innerHTML = 'Проверка...';
    
    fetch('courier_delivery_ajax.php?action=check_service_status&service=' + service)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusElement.innerHTML = '<span class="text-success">✓ ' + data.message + '</span>';
            } else {
                statusElement.innerHTML = '<span class="text-danger">✗ ' + data.message + '</span>';
            }
        })
        .catch(error => {
            statusElement.innerHTML = '<span class="text-danger">✗ Ошибка проверки</span>';
        });
}

function cleanupLogs() {
    if (confirm('Вы уверены, что хотите очистить старые логи?')) {
        fetch('courier_delivery_ajax.php?action=cleanup_logs', {method: 'POST'})
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Логи успешно очищены');
                    location.reload();
                } else {
                    alert('Ошибка очистки логов: ' + data.error);
                }
            });
    }
}

// Проверяем статус сервисов при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    checkServiceStatus('abs');
    checkServiceStatus('maps');
    checkServiceStatus('sms');
});
</script>

<style>
.courier-delivery-settings .adm-detail-tabs {
    border-bottom: 1px solid #e0e0e0;
    margin-bottom: 20px;
}

.courier-delivery-settings .adm-detail-tab {
    display: inline-block;
    padding: 10px 20px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-right: 10px;
}

.courier-delivery-settings .adm-detail-tab-active {
    border-bottom-color: #2fc6f6;
    color: #2fc6f6;
    font-weight: bold;
}

.courier-delivery-settings .adm-detail-content {
    margin-bottom: 20px;
}

.courier-delivery-settings .text-success {
    color: #28a745;
}

.courier-delivery-settings .text-danger {
    color: #dc3545;
}

.courier-delivery-settings .adm-detail-content-btns {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}
</style>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>