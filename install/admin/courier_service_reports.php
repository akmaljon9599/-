<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CourierService\Service\AuthService;
use CourierService\Service\LogService;

Loader::includeModule('courier_service');

Loc::loadMessages(__FILE__);

$authService = new AuthService();
if (!$authService->isAuthenticated() || !$authService->hasPermission('read', 'reports')) {
    $APPLICATION->AuthForm('Access denied');
}

$APPLICATION->SetTitle(Loc::getMessage('COURIER_SERVICE_REPORTS_TITLE'));

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
                    <a href="/bitrix/admin/courier_service_reports.php" class="active">
                        <i class="fas fa-chart-bar"></i>
                        <span class="menu-text">Отчеты</span>
                    </a>
                </li>
                <li>
                    <a href="/bitrix/admin/courier_service_settings.php">
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
                <h5>Отчеты и аналитика</h5>
                
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

            <!-- Фильтры отчетов -->
            <div class="filters-panel">
                <div class="row filter-row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Период от</label>
                        <input type="date" class="form-control" id="dateFrom" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Период до</label>
                        <input type="date" class="form-control" id="dateTo" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Тип отчета</label>
                        <select class="form-select" id="reportType">
                            <option value="requests">По заявкам</option>
                            <option value="couriers">По курьерам</option>
                            <option value="branches">По филиалам</option>
                            <option value="activity">Активность пользователей</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Формат экспорта</label>
                        <select class="form-select" id="exportFormat">
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button class="btn btn-primary" id="generateReport">
                        <i class="fas fa-chart-bar me-1"></i>Сформировать отчет
                    </button>
                    <button class="btn btn-success" id="exportReport">
                        <i class="fas fa-download me-1"></i>Экспортировать
                    </button>
                </div>
            </div>

            <!-- Статистика -->
            <div class="dashboard-stats">
                <div class="stat-card fade-in">
                    <h3 id="totalRequests">-</h3>
                    <p>Всего заявок</p>
                </div>
                <div class="stat-card fade-in">
                    <h3 id="deliveredRequests">-</h3>
                    <p>Доставлено</p>
                </div>
                <div class="stat-card fade-in">
                    <h3 id="successRate">-</h3>
                    <p>Процент успеха</p>
                </div>
                <div class="stat-card fade-in">
                    <h3 id="avgDeliveryTime">-</h3>
                    <p>Среднее время доставки</p>
                </div>
            </div>

            <!-- Графики -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span>Статистика по статусам</span>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span>Активность курьеров</span>
                        </div>
                        <div class="card-body">
                            <canvas id="couriersChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Таблица отчетов -->
            <div class="card">
                <div class="card-header">
                    <span>Детальный отчет</span>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" id="refreshReport">
                            <i class="fas fa-sync-alt me-1"></i>Обновить
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="reportsTable">
                            <thead class="table-light">
                                <tr id="reportsTableHeader">
                                    <!-- Заголовки заполняются динамически -->
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody">
                                <!-- Данные заполняются динамически -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Логи системы -->
            <div class="card">
                <div class="card-header">
                    <span>Логи системы</span>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" id="clearLogs">
                            <i class="fas fa-trash me-1"></i>Очистить старые логи
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Дата</th>
                                    <th>Пользователь</th>
                                    <th>Действие</th>
                                    <th>Сущность</th>
                                    <th>IP адрес</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Заполняется через AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/bitrix/css/courier_service/admin.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/bitrix/js/courier_service/admin.js"></script>
<script src="/bitrix/js/courier_service/reports.js"></script>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>