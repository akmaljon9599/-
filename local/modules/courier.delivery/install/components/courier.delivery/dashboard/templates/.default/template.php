<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Web\Json;

// Подключаем стили и скрипты
$APPLICATION->SetAdditionalCSS($templateFolder . '/style.css');
$APPLICATION->AddHeadScript($templateFolder . '/script.js');

// Проверяем ошибки
if (!empty($arResult['ERROR'])) {
    ShowError($arResult['ERROR']);
    return;
}

// Данные для JavaScript
$jsData = [
    'userRoles' => $arResult['USER_ROLES'],
    'mapData' => $arResult['MAP_DATA'] ?? [],
    'statistics' => $arResult['STATISTICS'] ?? []
];
?>

<div class="courier-dashboard" id="courierDashboard">
    <!-- Заголовок дашборда -->
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1>Панель управления курьерской службы</h1>
            <div class="dashboard-user-info">
                <span class="user-role">
                    <?php if (in_array('COURIER_ADMIN', $arResult['USER_ROLES'])): ?>
                        Администратор
                    <?php elseif (in_array('COURIER_SENIOR', $arResult['USER_ROLES'])): ?>
                        Старший курьер
                    <?php elseif (in_array('COURIER_OPERATOR', $arResult['USER_ROLES'])): ?>
                        Оператор
                    <?php elseif (in_array('COURIER_DELIVERY', $arResult['USER_ROLES'])): ?>
                        Курьер
                    <?php endif; ?>
                </span>
                <span class="current-time" id="currentTime"></span>
            </div>
        </div>
        
        <!-- Уведомления -->
        <?php if (!empty($arResult['NOTIFICATIONS'])): ?>
            <div class="dashboard-notifications">
                <div class="notifications-toggle" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-count"><?= count($arResult['NOTIFICATIONS']) ?></span>
                </div>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <?php foreach ($arResult['NOTIFICATIONS'] as $notification): ?>
                        <div class="notification-item notification-<?= $notification['type'] ?>">
                            <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                            <div class="notification-text"><?= htmlspecialchars($notification['text']) ?></div>
                            <?php if (!empty($notification['url'])): ?>
                                <a href="<?= htmlspecialchars($notification['url']) ?>" class="notification-link">
                                    Подробнее
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Статистика -->
    <?php if (!empty($arResult['STATISTICS'])): ?>
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $arResult['STATISTICS']['TOTAL']['total'] ?></div>
                    <div class="stat-label">Всего заявок</div>
                    <div class="stat-change">
                        +<?= $arResult['STATISTICS']['TODAY']['total'] ?> сегодня
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $arResult['STATISTICS']['TOTAL']['delivered'] ?></div>
                    <div class="stat-label">Доставлено</div>
                    <div class="stat-change">
                        +<?= $arResult['STATISTICS']['TODAY']['delivered'] ?> сегодня
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $arResult['STATISTICS']['TOTAL']['in_delivery'] + $arResult['STATISTICS']['TOTAL']['assigned'] ?></div>
                    <div class="stat-label">В процессе</div>
                    <div class="stat-change">
                        +<?= $arResult['STATISTICS']['TODAY']['in_delivery'] + $arResult['STATISTICS']['TODAY']['assigned'] ?> сегодня
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?= $arResult['STATISTICS']['COURIERS']['online'] ?></div>
                    <div class="stat-label">Активных курьеров</div>
                    <div class="stat-change">
                        из <?= $arResult['STATISTICS']['COURIERS']['total'] ?> всего
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-content">
        <!-- Карта курьеров -->
        <?php if (!empty($arResult['MAP_DATA']) && $arParams['SHOW_MAP'] === 'Y'): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Карта курьеров в реальном времени</h2>
                    <div class="section-actions">
                        <button class="btn btn-outline" onclick="refreshMap()">
                            <i class="fas fa-sync-alt"></i> Обновить
                        </button>
                        <button class="btn btn-outline" onclick="toggleFullscreenMap()">
                            <i class="fas fa-expand"></i> На весь экран
                        </button>
                    </div>
                </div>
                
                <div class="map-container" id="courierMap">
                    <div class="map-placeholder">
                        <i class="fas fa-map-marked-alt fa-3x"></i>
                        <p>Загрузка карты...</p>
                    </div>
                </div>

                <!-- Список курьеров -->
                <div class="couriers-list">
                    <h3>Активные курьеры</h3>
                    <div class="couriers-grid">
                        <?php foreach ($arResult['ACTIVE_COURIERS'] as $courier): ?>
                            <div class="courier-card">
                                <div class="courier-status courier-status-<?= strtolower($courier['STATUS']) ?>"></div>
                                <div class="courier-info">
                                    <div class="courier-name"><?= htmlspecialchars($courier['FULL_NAME']) ?></div>
                                    <div class="courier-branch"><?= htmlspecialchars($courier['BRANCH_NAME']) ?></div>
                                    <div class="courier-last-update">
                                        <?php if ($courier['LAST_LOCATION_UPDATE']): ?>
                                            Обновлено: <?= $courier['LAST_LOCATION_UPDATE']->format('H:i') ?>
                                        <?php else: ?>
                                            Местоположение неизвестно
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="courier-actions">
                                    <button class="btn btn-sm" onclick="showCourierOnMap(<?= $courier['ID'] ?>)">
                                        <i class="fas fa-location-arrow"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Последние заявки -->
        <?php if (!empty($arResult['RECENT_REQUESTS']) && $arParams['SHOW_RECENT_REQUESTS'] === 'Y'): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Последние заявки</h2>
                    <div class="section-actions">
                        <a href="/bitrix/admin/courier_delivery_requests.php" class="btn btn-primary">
                            Все заявки
                        </a>
                    </div>
                </div>

                <div class="requests-table-wrapper">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>№</th>
                                <th>Клиент</th>
                                <th>Телефон</th>
                                <th>Статус</th>
                                <th>Курьер</th>
                                <th>Создано</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arResult['RECENT_REQUESTS'] as $request): ?>
                                <tr>
                                    <td>
                                        <a href="/bitrix/admin/courier_delivery_request_edit.php?id=<?= $request['ID'] ?>" 
                                           class="request-link">
                                            #<?= $request['ID'] ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($request['CLIENT_NAME']) ?></td>
                                    <td><?= htmlspecialchars($request['CLIENT_PHONE']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($request['STATUS']) ?>">
                                            <?= htmlspecialchars($request['STATUS_TEXT'] ?? $request['STATUS']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($request['COURIER_NAME'])): ?>
                                            <div class="courier-info-inline">
                                                <span class="courier-status-indicator courier-status-online"></span>
                                                <?= htmlspecialchars($request['COURIER_NAME']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Не назначен</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="date-time">
                                            <?= $request['CREATED_DATE_FORMATTED'] ?? $request['CREATED_DATE'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline" 
                                                    onclick="viewRequest(<?= $request['ID'] ?>)"
                                                    title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (in_array('COURIER_ADMIN', $arResult['USER_ROLES']) || in_array('COURIER_SENIOR', $arResult['USER_ROLES'])): ?>
                                                <button class="btn btn-sm btn-outline" 
                                                        onclick="editRequest(<?= $request['ID'] ?>)"
                                                        title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Быстрые действия -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Быстрые действия</h2>
            </div>

            <div class="quick-actions">
                <?php if (in_array('COURIER_ADMIN', $arResult['USER_ROLES']) || in_array('COURIER_OPERATOR', $arResult['USER_ROLES'])): ?>
                    <div class="quick-action-card" onclick="location.href='/bitrix/admin/courier_delivery_request_edit.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="quick-action-text">
                            <div class="quick-action-title">Новая заявка</div>
                            <div class="quick-action-desc">Создать заявку на доставку</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array('COURIER_ADMIN', $arResult['USER_ROLES']) || in_array('COURIER_SENIOR', $arResult['USER_ROLES'])): ?>
                    <div class="quick-action-card" onclick="location.href='/bitrix/admin/courier_delivery_couriers.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-action-text">
                            <div class="quick-action-title">Управление курьерами</div>
                            <div class="quick-action-desc">Назначение и контроль</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="quick-action-card" onclick="location.href='/bitrix/admin/courier_delivery_map.php'">
                    <div class="quick-action-icon">
                        <i class="fas fa-map"></i>
                    </div>
                    <div class="quick-action-text">
                        <div class="quick-action-title">Карта доставок</div>
                        <div class="quick-action-desc">Отслеживание в реальном времени</div>
                    </div>
                </div>

                <div class="quick-action-card" onclick="location.href='/bitrix/admin/courier_delivery_reports.php'">
                    <div class="quick-action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="quick-action-text">
                        <div class="quick-action-title">Отчеты</div>
                        <div class="quick-action-desc">Аналитика и статистика</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<div id="requestModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Информация о заявке</h3>
            <button class="modal-close" onclick="closeModal('requestModal')">&times;</button>
        </div>
        <div class="modal-body" id="requestModalBody">
            <!-- Содержимое загружается через AJAX -->
        </div>
    </div>
</div>

<script>
// Передаем данные в JavaScript
window.CourierDashboard = <?= Json::encode($jsData) ?>;

// Инициализация дашборда
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    
    // Обновляем данные каждые 30 секунд
    setInterval(refreshDashboardData, 30000);
});

function initializeDashboard() {
    // Инициализация карты если есть данные
    if (window.CourierDashboard.mapData && window.CourierDashboard.mapData.couriers.length > 0) {
        initializeMap();
    }
    
    // Обработчики событий
    setupEventHandlers();
}

function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }
}

function refreshDashboardData() {
    // AJAX обновление данных дашборда
    fetch('/local/modules/courier.delivery/ajax/dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.statistics);
                updateCouriersList(data.couriers);
                updateRecentRequests(data.requests);
            }
        })
        .catch(error => {
            console.error('Error refreshing dashboard data:', error);
        });
}

function viewRequest(requestId) {
    // Загружаем информацию о заявке через AJAX
    fetch(`/local/modules/courier.delivery/ajax/get_request.php?id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('requestModalBody').innerHTML = data.html;
                document.getElementById('requestModal').style.display = 'block';
            } else {
                alert('Ошибка загрузки данных заявки');
            }
        });
}

function editRequest(requestId) {
    location.href = `/bitrix/admin/courier_delivery_request_edit.php?id=${requestId}`;
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Закрытие модальных окон по клику вне их
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
    
    // Закрытие выпадающих меню
    if (!event.target.closest('.dashboard-notifications')) {
        const dropdown = document.getElementById('notificationsDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
}
</script>