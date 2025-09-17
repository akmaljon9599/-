<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CourierService\Service\AuthService;

Loader::includeModule('courier_service');

Loc::loadMessages(__FILE__);

$authService = new AuthService();
if (!$authService->isAuthenticated() || !$authService->hasPermission('read', 'requests')) {
    $APPLICATION->AuthForm('Access denied');
}

$APPLICATION->SetTitle(Loc::getMessage('COURIER_SERVICE_REQUESTS_TITLE'));

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
                    <a href="/bitrix/admin/courier_service_dashboard.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="menu-text">Дашборд</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_requests.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_requests.php' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i>
                        <span class="menu-text">Заявки</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_couriers.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_couriers.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span class="menu-text">Курьеры</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_map.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_map.php' ? 'active' : '' ?>">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="menu-text">Карта</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_reports.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="menu-text">Отчеты</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_settings.php" class="<?= $APPLICATION->GetCurPage() === '/bitrix/admin/courier_service_settings.php' ? 'active' : '' ?>">
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
                <h5>Управление заявками</h5>
                
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

            <!-- Панель фильтров -->
            <div class="filters-panel">
                <div class="row filter-row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Дата регистрации от</label>
                        <input type="date" class="form-control" id="dateFrom" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Дата регистрации до</label>
                        <input type="date" class="form-control" id="dateTo" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Статус</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">Все</option>
                            <option value="new">Новая</option>
                            <option value="waiting_delivery">Ожидает доставки</option>
                            <option value="in_delivery">В доставке</option>
                            <option value="delivered">Доставлено</option>
                            <option value="rejected">Отказано</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Статус звонка</label>
                        <select class="form-select" id="callStatusFilter">
                            <option value="">Все</option>
                            <option value="not_called">Не звонили</option>
                            <option value="successful">Успешный</option>
                            <option value="failed">Не удался</option>
                        </select>
                    </div>
                </div>
                
                <div class="row filter-row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Тип карты</label>
                        <select class="form-select" id="cardTypeFilter">
                            <option value="">Все</option>
                            <option value="Visa">Visa</option>
                            <option value="MasterCard">MasterCard</option>
                            <option value="Мир">Мир</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Филиал</label>
                        <select class="form-select" id="branchFilter">
                            <option value="">Все</option>
                            <!-- Заполняется через AJAX -->
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Курьер</label>
                        <select class="form-select" id="courierFilter">
                            <option value="">Все</option>
                            <!-- Заполняется через AJAX -->
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">ФИО клиента</label>
                        <input type="text" class="form-control" id="clientNameFilter" placeholder="Введите ФИО">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="btn btn-outline-secondary me-2" id="clearFilters">
                        <i class="fas fa-times me-1"></i>Очистить
                    </button>
                    <button class="btn btn-primary" id="applyFilters">
                        <i class="fas fa-filter me-1"></i>Применить фильтры
                    </button>
                </div>
            </div>

            <!-- Таблица заявок -->
            <div class="card">
                <div class="card-header">
                    <span>Список заявок</span>
                    <div>
                        <button class="btn btn-success me-2" id="exportBtn">
                            <i class="fas fa-file-excel me-1"></i>Выгрузить
                        </button>
                        <?php if ($authService->hasPermission('create', 'requests')): ?>
                        <button class="btn btn-primary" id="addRequestBtn">
                            <i class="fas fa-plus me-1"></i>Добавить заявку
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="requestsTable">
                            <thead class="table-light">
                                <tr>
                                    <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                                    <th>Номер</th>
                                    <th>ID</th>
                                    <th>Курьер</th>
                                    <th>Дата регистрации</th>
                                    <th>Клиент</th>
                                    <th>Номер клиента</th>
                                    <th>Статус</th>
                                    <th>Филиал</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTableBody">
                                <!-- Заполняется через AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div id="paginationInfo">Загрузка...</div>
                    <nav>
                        <ul class="pagination mb-0" id="pagination">
                            <!-- Заполняется через AJAX -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления заявки -->
<div class="modal fade" id="addRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавление заявки на доставку карты</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addRequestForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ФИО клиента *</label>
                            <input type="text" class="form-control" name="client_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Номер телефона клиента *</label>
                            <input type="tel" class="form-control" name="client_phone" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PAN *</label>
                            <input type="text" class="form-control" name="pan" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Адрес доставки *</label>
                            <input type="text" class="form-control" name="client_address" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Тип карты</label>
                            <select class="form-select" name="card_type">
                                <option value="">Выберите тип</option>
                                <option value="Visa">Visa</option>
                                <option value="MasterCard">MasterCard</option>
                                <option value="Мир">Мир</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Филиал *</label>
                            <select class="form-select" name="branch_id" required>
                                <option value="">Выберите филиал</option>
                                <!-- Заполняется через AJAX -->
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveRequestBtn">Добавить заявку</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно изменения статуса -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Изменение статуса заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="statusRequestId" name="request_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Текущий статус</label>
                        <div class="fw-bold" id="currentStatus"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Новый статус</label>
                        <select class="form-select" id="newStatus" name="status">
                            <option value="">Выберите статус</option>
                            <option value="waiting_delivery">Ожидает доставки</option>
                            <option value="in_delivery">В доставке</option>
                            <option value="delivered">Доставлено</option>
                            <option value="rejected">Отказано</option>
                        </select>
                    </div>
                    
                    <div id="deliveredFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Телефон курьера</label>
                            <input type="tel" class="form-control" name="courier_phone" placeholder="+7 (xxx) xxx-xx-xx">
                        </div>
                    </div>
                    
                    <div id="rejectedFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Причина отказа</label>
                            <textarea class="form-control" name="rejection_reason" rows="3" placeholder="Подробно опишите причину отказа..." minlength="100"></textarea>
                            <div class="form-text">Минимум 100 символов</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveStatusBtn">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/bitrix/css/courier_service/admin.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/bitrix/js/courier_service/admin.js"></script>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>