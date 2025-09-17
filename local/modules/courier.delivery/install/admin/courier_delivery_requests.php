<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Courier\Delivery\Service\DeliveryService;
use Courier\Delivery\Util\RoleManager;
use Courier\Delivery\Util\Security;

// Проверяем права доступа
if (!$USER->IsAdmin() && !$USER->CanDoOperation('courier_delivery_view'))
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

$APPLICATION->SetTitle("Управление заявками курьерской службы");

// Инициализация сервисов
$deliveryService = new DeliveryService();
$roleManager = new RoleManager();
$request = Application::getInstance()->getContext()->getRequest();

// Обработка действий
$action = $request->get('action');
$requestId = (int)$request->get('id');

if ($action && check_bitrix_sessid()) {
    switch ($action) {
        case 'change_status':
            $newStatus = $request->get('status');
            $comment = $request->get('comment');
            
            $result = $deliveryService->changeStatus($requestId, $newStatus, $comment);
            
            if ($result['success']) {
                LocalRedirect($APPLICATION->GetCurPageParam("", array("action", "id", "status", "comment")));
            } else {
                ShowError($result['error']);
            }
            break;
            
        case 'assign_courier':
            $courierId = (int)$request->get('courier_id');
            
            $result = $deliveryService->assignCourier($requestId, $courierId);
            
            if ($result['success']) {
                LocalRedirect($APPLICATION->GetCurPageParam("", array("action", "id", "courier_id")));
            } else {
                ShowError($result['error']);
            }
            break;
            
        case 'auto_assign':
            $result = $deliveryService->autoAssignCourier($requestId);
            
            if ($result['success']) {
                LocalRedirect($APPLICATION->GetCurPageParam("", array("action", "id")));
            } else {
                ShowError($result['error']);
            }
            break;
    }
}

// Получение списка заявок с фильтрацией
$filter = [];
$page = (int)$request->get('page') ?: 1;
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// Применяем фильтры
if ($request->get('status')) {
    $filter['STATUS'] = $request->get('status');
}
if ($request->get('branch_id')) {
    $filter['BRANCH_ID'] = (int)$request->get('branch_id');
}
if ($request->get('courier_id')) {
    $filter['COURIER_ID'] = (int)$request->get('courier_id');
}
if ($request->get('date_from')) {
    $filter['>=CREATED_DATE'] = $request->get('date_from') . ' 00:00:00';
}
if ($request->get('date_to')) {
    $filter['<=CREATED_DATE'] = $request->get('date_to') . ' 23:59:59';
}

$requestsList = $deliveryService->getRequestsList($filter, ['CREATED_DATE' => 'DESC'], $pageSize, $offset);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

// Подключаем стили и скрипты
$APPLICATION->AddHeadScript('/local/modules/courier.delivery/install/js/admin.js');
$APPLICATION->SetAdditionalCSS('/local/modules/courier.delivery/install/css/admin.css');

?>

<div class="courier-delivery-admin">
    <!-- Фильтры -->
    <div class="adm-toolbar-panel">
        <table class="adm-toolbar-panel-table">
            <tr>
                <td class="adm-toolbar-panel-container-left">
                    <div class="adm-toolbar-panel-flexible-space">
                        <form method="GET" class="courier-filter-form">
                            <div class="adm-filter">
                                <table>
                                    <tr>
                                        <td>Статус:</td>
                                        <td>
                                            <select name="status">
                                                <option value="">Все</option>
                                                <option value="NEW" <?= $request->get('status') == 'NEW' ? 'selected' : '' ?>>Новые</option>
                                                <option value="PROCESSING" <?= $request->get('status') == 'PROCESSING' ? 'selected' : '' ?>>В обработке</option>
                                                <option value="ASSIGNED" <?= $request->get('status') == 'ASSIGNED' ? 'selected' : '' ?>>Назначены</option>
                                                <option value="IN_DELIVERY" <?= $request->get('status') == 'IN_DELIVERY' ? 'selected' : '' ?>>В доставке</option>
                                                <option value="DELIVERED" <?= $request->get('status') == 'DELIVERED' ? 'selected' : '' ?>>Доставлены</option>
                                                <option value="REJECTED" <?= $request->get('status') == 'REJECTED' ? 'selected' : '' ?>>Отказано</option>
                                            </select>
                                        </td>
                                        <td>Дата от:</td>
                                        <td>
                                            <input type="date" name="date_from" value="<?= htmlspecialchars($request->get('date_from')) ?>">
                                        </td>
                                        <td>до:</td>
                                        <td>
                                            <input type="date" name="date_to" value="<?= htmlspecialchars($request->get('date_to')) ?>">
                                        </td>
                                        <td>
                                            <input type="submit" value="Найти" class="adm-btn">
                                            <input type="button" value="Очистить" class="adm-btn" onclick="location.href='<?= $APPLICATION->GetCurPage() ?>'">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </form>
                    </div>
                </td>
                <td class="adm-toolbar-panel-container-right">
                    <a href="courier_delivery_request_edit.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn adm-btn-green">
                        Добавить заявку
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Список заявок -->
    <div class="adm-list-table-wrap">
        <table class="adm-list-table" id="tbl_courier_requests">
            <thead>
                <tr class="adm-list-table-header">
                    <td class="adm-list-table-cell">ID</td>
                    <td class="adm-list-table-cell">ABS ID</td>
                    <td class="adm-list-table-cell">Клиент</td>
                    <td class="adm-list-table-cell">Телефон</td>
                    <td class="adm-list-table-cell">Адрес доставки</td>
                    <td class="adm-list-table-cell">Статус</td>
                    <td class="adm-list-table-cell">Курьер</td>
                    <td class="adm-list-table-cell">Филиал</td>
                    <td class="adm-list-table-cell">Создано</td>
                    <td class="adm-list-table-cell">Действия</td>
                </tr>
            </thead>
            <tbody>
                <?php if ($requestsList['success'] && !empty($requestsList['data'])): ?>
                    <?php foreach ($requestsList['data'] as $deliveryRequest): ?>
                        <tr class="adm-list-table-row">
                            <td class="adm-list-table-cell">
                                <a href="courier_delivery_request_edit.php?id=<?= $deliveryRequest['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                                    <?= $deliveryRequest['ID'] ?>
                                </a>
                            </td>
                            <td class="adm-list-table-cell"><?= htmlspecialchars($deliveryRequest['ABS_ID']) ?></td>
                            <td class="adm-list-table-cell"><?= htmlspecialchars($deliveryRequest['CLIENT_NAME']) ?></td>
                            <td class="adm-list-table-cell"><?= htmlspecialchars($deliveryRequest['CLIENT_PHONE']) ?></td>
                            <td class="adm-list-table-cell" title="<?= htmlspecialchars($deliveryRequest['DELIVERY_ADDRESS']) ?>">
                                <?= htmlspecialchars(substr($deliveryRequest['DELIVERY_ADDRESS'], 0, 50)) ?>
                                <?= strlen($deliveryRequest['DELIVERY_ADDRESS']) > 50 ? '...' : '' ?>
                            </td>
                            <td class="adm-list-table-cell">
                                <span class="courier-status courier-status-<?= strtolower($deliveryRequest['STATUS']) ?>">
                                    <?= htmlspecialchars($deliveryRequest['STATUS_TEXT'] ?? $deliveryRequest['STATUS']) ?>
                                </span>
                            </td>
                            <td class="adm-list-table-cell">
                                <?php if ($deliveryRequest['COURIER_NAME']): ?>
                                    <span class="courier-online-indicator"></span>
                                    <?= htmlspecialchars($deliveryRequest['COURIER_NAME']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Не назначен</span>
                                <?php endif; ?>
                            </td>
                            <td class="adm-list-table-cell"><?= htmlspecialchars($deliveryRequest['BRANCH_NAME']) ?></td>
                            <td class="adm-list-table-cell">
                                <?= $deliveryRequest['CREATED_DATE_FORMATTED'] ?? $deliveryRequest['CREATED_DATE'] ?>
                            </td>
                            <td class="adm-list-table-cell">
                                <div class="adm-list-table-popup">
                                    <div class="adm-list-table-popup-btn" tabindex="0"></div>
                                    <div class="adm-list-table-popup-cont">
                                        <div class="adm-list-table-popup-item">
                                            <a href="courier_delivery_request_edit.php?id=<?= $deliveryRequest['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                                                Редактировать
                                            </a>
                                        </div>
                                        <?php if ($roleManager->hasPermission($USER->GetID(), RoleManager::PERMISSION_CHANGE_STATUS)): ?>
                                            <div class="adm-list-table-popup-item">
                                                <a href="javascript:void(0)" onclick="changeRequestStatus(<?= $deliveryRequest['ID'] ?>)">
                                                    Изменить статус
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($roleManager->hasPermission($USER->GetID(), RoleManager::PERMISSION_ASSIGN_COURIER)): ?>
                                            <div class="adm-list-table-popup-item">
                                                <a href="javascript:void(0)" onclick="assignCourier(<?= $deliveryRequest['ID'] ?>)">
                                                    Назначить курьера
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="adm-list-table-popup-item">
                                            <a href="courier_delivery_documents.php?request_id=<?= $deliveryRequest['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                                                Документы
                                            </a>
                                        </div>
                                        <div class="adm-list-table-popup-item">
                                            <a href="courier_delivery_map.php?request_id=<?= $deliveryRequest['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                                                Показать на карте
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="adm-list-table-cell text-center">
                            <?php if (!$requestsList['success']): ?>
                                <span class="text-danger">Ошибка загрузки данных: <?= htmlspecialchars($requestsList['error']) ?></span>
                            <?php else: ?>
                                Заявки не найдены
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <?php if ($requestsList['success'] && $requestsList['total'] > $pageSize): ?>
        <div class="adm-navigation">
            <?php
            $totalPages = ceil($requestsList['total'] / $pageSize);
            $urlTemplate = $APPLICATION->GetCurPageParam("page=#PAGE#", array("page"));
            
            echo BeginNote();
            echo "Показано " . (($page - 1) * $pageSize + 1) . " - " . min($page * $pageSize, $requestsList['total']) . " из " . $requestsList['total'];
            echo EndNote();
            
            // Простая пагинация
            if ($page > 1): ?>
                <a href="<?= str_replace('#PAGE#', $page - 1, $urlTemplate) ?>" class="adm-btn">← Предыдущая</a>
            <?php endif;
            
            if ($page < $totalPages): ?>
                <a href="<?= str_replace('#PAGE#', $page + 1, $urlTemplate) ?>" class="adm-btn">Следующая →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальные окна -->
<div id="status-change-modal" class="courier-modal" style="display: none;">
    <div class="courier-modal-content">
        <div class="courier-modal-header">
            <h3>Изменение статуса заявки</h3>
            <button class="courier-modal-close" onclick="closeModal('status-change-modal')">&times;</button>
        </div>
        <div class="courier-modal-body">
            <form id="status-change-form">
                <input type="hidden" id="status-request-id" name="id">
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
                
                <div class="courier-form-group">
                    <label for="new-status">Новый статус:</label>
                    <select id="new-status" name="status" required>
                        <option value="">Выберите статус</option>
                        <?php foreach ($roleManager->getAvailableStatuses($USER->GetID()) as $status => $text): ?>
                            <option value="<?= $status ?>"><?= htmlspecialchars($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="courier-form-group">
                    <label for="status-comment">Комментарий:</label>
                    <textarea id="status-comment" name="comment" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="courier-modal-footer">
            <button type="button" class="adm-btn" onclick="closeModal('status-change-modal')">Отмена</button>
            <button type="button" class="adm-btn adm-btn-save" onclick="submitStatusChange()">Сохранить</button>
        </div>
    </div>
</div>

<div id="assign-courier-modal" class="courier-modal" style="display: none;">
    <div class="courier-modal-content">
        <div class="courier-modal-header">
            <h3>Назначение курьера</h3>
            <button class="courier-modal-close" onclick="closeModal('assign-courier-modal')">&times;</button>
        </div>
        <div class="courier-modal-body">
            <form id="assign-courier-form">
                <input type="hidden" id="assign-request-id" name="id">
                <input type="hidden" name="action" value="assign_courier">
                <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
                
                <div class="courier-form-group">
                    <label for="courier-select">Курьер:</label>
                    <select id="courier-select" name="courier_id" required>
                        <option value="">Выберите курьера</option>
                        <!-- Курьеры загружаются через AJAX -->
                    </select>
                </div>
                
                <div class="courier-form-group">
                    <button type="button" class="adm-btn" onclick="autoAssignCourier()">
                        Автоматический выбор ближайшего
                    </button>
                </div>
            </form>
        </div>
        <div class="courier-modal-footer">
            <button type="button" class="adm-btn" onclick="closeModal('assign-courier-modal')">Отмена</button>
            <button type="button" class="adm-btn adm-btn-save" onclick="submitCourierAssignment()">Назначить</button>
        </div>
    </div>
</div>

<script>
function changeRequestStatus(requestId) {
    document.getElementById('status-request-id').value = requestId;
    document.getElementById('status-change-modal').style.display = 'block';
}

function assignCourier(requestId) {
    document.getElementById('assign-request-id').value = requestId;
    loadAvailableCouriers();
    document.getElementById('assign-courier-modal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function submitStatusChange() {
    const form = document.getElementById('status-change-form');
    const formData = new FormData(form);
    
    // Отправляем форму
    form.submit();
}

function submitCourierAssignment() {
    const form = document.getElementById('assign-courier-form');
    const formData = new FormData(form);
    
    // Отправляем форму
    form.submit();
}

function autoAssignCourier() {
    const requestId = document.getElementById('assign-request-id').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="id" value="${requestId}">
        <input type="hidden" name="action" value="auto_assign">
        <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

function loadAvailableCouriers() {
    // AJAX загрузка доступных курьеров
    fetch('courier_delivery_ajax.php?action=get_available_couriers')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('courier-select');
            select.innerHTML = '<option value="">Выберите курьера</option>';
            
            if (data.success) {
                data.couriers.forEach(courier => {
                    const option = document.createElement('option');
                    option.value = courier.ID;
                    option.textContent = `${courier.FULL_NAME} (${courier.STATUS_TEXT})`;
                    select.appendChild(option);
                });
            }
        });
}

// Закрытие модальных окон по клику вне их
window.onclick = function(event) {
    if (event.target.classList.contains('courier-modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>