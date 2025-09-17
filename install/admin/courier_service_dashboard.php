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

$APPLICATION->SetTitle(Loc::getMessage('COURIER_SERVICE_DASHBOARD_TITLE'));

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
                    <a href="/bitrix/admin/courier_service_dashboard.php" class="active">
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
                <h5>Панель управления</h5>
                
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
                    <h3 id="inProgressRequests">-</h3>
                    <p>В процессе</p>
                </div>
                <div class="stat-card fade-in">
                    <h3 id="rejectedRequests">-</h3>
                    <p>Отказано</p>
                </div>
            </div>

            <!-- Карта курьеров -->
            <div class="card fade-in">
                <div class="card-header">
                    <span>Карта курьеров в реальном времени</span>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary me-2" id="refreshMap">
                            <i class="fas fa-sync-alt me-1"></i>Обновить
                        </button>
                        <button class="btn btn-sm btn-outline-primary" id="fullscreenMap">
                            <i class="fas fa-expand me-1"></i>На весь экран
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="map-container" id="couriersMap">
                        <div class="text-center">
                            <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                            <p>Интеграция с Яндекс.Картами</p>
                            <p>Отслеживание местоположения курьеров</p>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Курьер</th>
                                    <th>Статус</th>
                                    <th>Филиал</th>
                                    <th>Текущее местоположение</th>
                                    <th>Последняя активность</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="couriersTableBody">
                                <!-- Заполняется через AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Последние заявки -->
            <div class="card fade-in">
                <div class="card-header">
                    <span>Последние заявки</span>
                    <div>
                        <a href="/bitrix/admin/courier_service_requests.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>Все заявки
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Номер</th>
                                    <th>Клиент</th>
                                    <th>Статус</th>
                                    <th>Курьер</th>
                                    <th>Дата</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody id="recentRequestsTableBody">
                                <!-- Заполняется через AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для карты на весь экран -->
<div class="modal fade" id="fullscreenMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Карта курьеров</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="fullscreenMapContainer" style="height: 100vh;">
                    <!-- Здесь будет карта -->
                </div>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/bitrix/css/courier_service/admin.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://api-maps.yandex.ru/2.1/?apikey=YOUR_API_KEY&lang=ru_RU" type="text/javascript"></script>
<script src="/bitrix/js/courier_service/admin.js"></script>
<script src="/bitrix/js/courier_service/dashboard.js"></script>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>