<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CourierService\Main\RequestTable;
use CourierService\Main\CourierTable;
use CourierService\Main\BranchTable;
use CourierService\Security\PermissionManager;
use CourierService\Utils\LocationTracker;

Loader::includeModule('courier_service');
Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Курьерская служба - Панель управления');

// Проверка прав доступа
if (!PermissionManager::checkPermission('requests', 'view')) {
    $APPLICATION->ThrowException('Доступ запрещен');
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Получение статистики
$userRole = PermissionManager::getUserRole();
$requestFilter = PermissionManager::getRequestFilter();

$totalRequests = RequestTable::getList([
    'filter' => $requestFilter,
    'select' => ['ID']
])->getSelectedRowsCount();

$deliveredRequests = RequestTable::getList([
    'filter' => array_merge($requestFilter, ['STATUS' => 'delivered']),
    'select' => ['ID']
])->getSelectedRowsCount();

$inProgressRequests = RequestTable::getList([
    'filter' => array_merge($requestFilter, ['STATUS' => 'waiting']),
    'select' => ['ID']
])->getSelectedRowsCount();

$rejectedRequests = RequestTable::getList([
    'filter' => array_merge($requestFilter, ['STATUS' => 'rejected']),
    'select' => ['ID']
])->getSelectedRowsCount();

// Получение активных курьеров
$locationTracker = new LocationTracker();
$couriersResult = $locationTracker->getActiveCouriersLocations();
$activeCouriers = $couriersResult['success'] ? $couriersResult['couriers'] : [];

// Получение последних заявок
$recentRequests = RequestTable::getList([
    'filter' => $requestFilter,
    'select' => ['*', 'COURIER.NAME', 'BRANCH.NAME'],
    'order' => ['DATE_CREATE' => 'DESC'],
    'limit' => 10
])->fetchAll();

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $APPLICATION->GetTitle() ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/bitrix/css/courier_service/main.css">
</head>
<body>
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
                    <a href="/bitrix/admin/courier_service_requests.php" class="active">
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
                <?php if (PermissionManager::checkPermission('couriers', 'view')): ?>
                <li>
                    <a href="/bitrix/admin/courier_service_couriers.php">
                        <i class="fas fa-users"></i>
                        <span class="menu-text">Курьеры</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="/bitrix/admin/courier_service_map.php">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="menu-text">Карта</span>
                    </a>
                </li>
                <?php if (PermissionManager::checkPermission('reports', 'view')): ?>
                <li>
                    <a href="/bitrix/admin/courier_service_reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span class="menu-text">Отчеты</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($userRole === 'COURIER_ADMIN'): ?>
                <li>
                    <a href="/bitrix/admin/courier_service_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="menu-text">Настройки</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Основной контент -->
        <div class="main-content">
            <!-- Навигационная панель -->
            <div class="navbar">
                <h5>Панель управления</h5>
                
                <div class="user-menu">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="roleDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($userRole) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><span class="dropdown-item-text">Роль: <?= htmlspecialchars($userRole) ?></span></li>
                        </ul>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($USER->GetFullName()) ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/bitrix/admin/user_edit.php?ID=<?= $USER->GetID() ?>"><i class="fas fa-cog me-2"></i>Настройки</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/bitrix/admin/index.php?logout=yes"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Статистика -->
            <div class="dashboard-stats">
                <div class="stat-card fade-in">
                    <h3><?= $totalRequests ?></h3>
                    <p>Всего заявок</p>
                </div>
                <div class="stat-card fade-in">
                    <h3><?= $deliveredRequests ?></h3>
                    <p>Доставлено</p>
                </div>
                <div class="stat-card fade-in">
                    <h3><?= $inProgressRequests ?></h3>
                    <p>В процессе</p>
                </div>
                <div class="stat-card fade-in">
                    <h3><?= $rejectedRequests ?></h3>
                    <p>Отказано</p>
                </div>
            </div>

            <div class="row">
                <!-- Карта курьеров -->
                <div class="col-lg-8">
                    <div class="card fade-in">
                        <div class="card-header">
                            <span>Карта курьеров в реальном времени</span>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="refreshMap()">
                                    <i class="fas fa-sync-alt me-1"></i>Обновить
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleFullscreen()">
                                    <i class="fas fa-expand me-1"></i>На весь экран
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="courier-map" class="map-container">
                                <div class="text-center">
                                    <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                                    <p>Загрузка карты...</p>
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
                                    <tbody>
                                        <?php foreach ($activeCouriers as $courier): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="courier-status <?= $courier['status'] === 'active' ? 'online' : ($courier['status'] === 'on_delivery' ? 'on-delivery' : 'offline') ?> me-2"></span>
                                                    <?= htmlspecialchars($courier['name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $courier['status'] === 'active' ? 'success' : ($courier['status'] === 'on_delivery' ? 'warning' : 'secondary') ?> status-badge">
                                                    <?= $courier['status'] === 'active' ? 'Активен' : ($courier['status'] === 'on_delivery' ? 'На доставке' : 'Неактивен') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($courier['branch']) ?></td>
                                            <td>
                                                <?php if ($courier['latitude'] && $courier['longitude']): ?>
                                                    <a href="#" onclick="showOnMap(<?= $courier['latitude'] ?>, <?= $courier['longitude'] ?>)">
                                                        <i class="fas fa-map-marker-alt"></i> Показать на карте
                                                    </a>
                                                <?php else: ?>
                                                    Не доступно
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $courier['last_activity'] ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="trackCourier(<?= $courier['id'] ?>)">
                                                    <i class="fas fa-location-arrow"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Последние заявки -->
                <div class="col-lg-4">
                    <div class="card fade-in">
                        <div class="card-header">
                            <span>Последние заявки</span>
                            <a href="/bitrix/admin/courier_service_requests.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>Все заявки
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentRequests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($request['REQUEST_NUMBER']) ?></h6>
                                        <small><?= $request['DATE_CREATE']->format('d.m H:i') ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($request['CLIENT_NAME']) ?></p>
                                    <small>
                                        <span class="badge bg-<?= $request['STATUS'] === 'delivered' ? 'success' : ($request['STATUS'] === 'waiting' ? 'warning' : ($request['STATUS'] === 'rejected' ? 'danger' : 'info')) ?>">
                                            <?= RequestTable::getStatusList()[$request['STATUS']] ?? $request['STATUS'] ?>
                                        </span>
                                        <?php if ($request['COURIER_NAME']): ?>
                                            <br>Курьер: <?= htmlspecialchars($request['COURIER_NAME']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://api-maps.yandex.ru/2.1/?apikey=<?= \CourierService\Main\SettingTable::get('yandex_maps_api_key', '') ?>&lang=ru_RU" type="text/javascript"></script>
    <script src="/bitrix/js/courier_service/main.js"></script>
    <script>
        // Инициализация карты
        document.addEventListener('DOMContentLoaded', function() {
            initCourierMap();
        });

        function initCourierMap() {
            if (typeof ymaps === 'undefined') {
                console.error('Yandex Maps API не загружен');
                return;
            }

            ymaps.ready(function() {
                var map = new ymaps.Map('courier-map', {
                    center: [55.7558, 37.6176],
                    zoom: 10,
                    controls: ['zoomControl', 'fullscreenControl']
                });

                // Добавляем маркеры курьеров
                <?php foreach ($activeCouriers as $courier): ?>
                    <?php if ($courier['latitude'] && $courier['longitude']): ?>
                    var marker = new ymaps.Placemark([<?= $courier['latitude'] ?>, <?= $courier['longitude'] ?>], {
                        balloonContentHeader: '<?= htmlspecialchars($courier['name']) ?>',
                        balloonContentBody: 'Статус: <?= $courier['status'] ?><br>Филиал: <?= htmlspecialchars($courier['branch']) ?>',
                        hintContent: '<?= htmlspecialchars($courier['name']) ?>'
                    }, {
                        preset: 'islands#circleIcon',
                        iconColor: '<?= $courier['status'] === 'active' ? '#28a745' : ($courier['status'] === 'on_delivery' ? '#ffc107' : '#6c757d') ?>'
                    });
                    map.geoObjects.add(marker);
                    <?php endif; ?>
                <?php endforeach; ?>

                window.courierMap = map;
            });
        }

        function refreshMap() {
            location.reload();
        }

        function toggleFullscreen() {
            var mapContainer = document.getElementById('courier-map');
            if (mapContainer.requestFullscreen) {
                mapContainer.requestFullscreen();
            }
        }

        function showOnMap(lat, lon) {
            if (window.courierMap) {
                window.courierMap.setCenter([lat, lon], 15);
            }
        }

        function trackCourier(courierId) {
            // Логика отслеживания курьера
            console.log('Отслеживание курьера:', courierId);
        }
    </script>
</body>
</html>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>