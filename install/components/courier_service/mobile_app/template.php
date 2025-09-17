<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$this->setFrameMode(true);
?>

<div id="courier-mobile-app" class="courier-mobile-app">
    <!-- Заголовок -->
    <div class="app-header">
        <div class="header-content">
            <h1>Курьерская служба</h1>
            <div class="user-info">
                <span class="user-name">Курьер #<?= $arResult['USER']['USER_ID'] ?></span>
                <span class="status-indicator online" id="statusIndicator"></span>
            </div>
        </div>
    </div>

    <!-- Навигация -->
    <div class="app-nav">
        <button class="nav-btn active" data-tab="requests">
            <i class="fas fa-list"></i>
            <span>Заявки</span>
        </button>
        <button class="nav-btn" data-tab="map">
            <i class="fas fa-map"></i>
            <span>Карта</span>
        </button>
        <button class="nav-btn" data-tab="profile">
            <i class="fas fa-user"></i>
            <span>Профиль</span>
        </button>
    </div>

    <!-- Контент -->
    <div class="app-content">
        <!-- Вкладка заявок -->
        <div class="tab-content active" id="requests-tab">
            <div class="requests-list">
                <?php if (empty($arResult['REQUESTS'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Нет активных заявок</h3>
                        <p>Ожидайте назначения новых заявок</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($arResult['REQUESTS'] as $request): ?>
                        <div class="request-card" data-request-id="<?= $request['id'] ?>">
                            <div class="request-header">
                                <h3>Заявка #<?= $request['request_number'] ?></h3>
                                <span class="status-badge <?= $request['status'] ?>"><?= $request['status_text'] ?></span>
                            </div>
                            <div class="request-info">
                                <div class="info-row">
                                    <i class="fas fa-user"></i>
                                    <span><?= htmlspecialchars($request['client_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-phone"></i>
                                    <a href="tel:<?= $request['client_phone'] ?>"><?= $request['client_phone'] ?></a>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($request['client_address']) ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <span><?= date('d.m.Y H:i', strtotime($request['registration_date'])) ?></span>
                                </div>
                            </div>
                            <div class="request-actions">
                                <button class="btn btn-primary" onclick="startDelivery(<?= $request['id'] ?>)">
                                    <i class="fas fa-play"></i> Начать доставку
                                </button>
                                <button class="btn btn-outline-primary" onclick="showOnMap(<?= $request['id'] ?>)">
                                    <i class="fas fa-map-marker-alt"></i> На карте
                                </button>
                                <button class="btn btn-outline-secondary" onclick="callClient('<?= $request['client_phone'] ?>')">
                                    <i class="fas fa-phone"></i> Позвонить
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Вкладка карты -->
        <div class="tab-content" id="map-tab">
            <div class="map-container" id="courierMap">
                <div class="map-loading">
                    <div class="spinner"></div>
                    <p>Загрузка карты...</p>
                </div>
            </div>
            <div class="map-controls">
                <button class="btn btn-primary" id="updateLocationBtn">
                    <i class="fas fa-location-arrow"></i> Обновить местоположение
                </button>
                <button class="btn btn-outline-primary" id="centerMapBtn">
                    <i class="fas fa-crosshairs"></i> Центрировать
                </button>
            </div>
        </div>

        <!-- Вкладка профиля -->
        <div class="tab-content" id="profile-tab">
            <div class="profile-info">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3>Курьер #<?= $arResult['USER']['USER_ID'] ?></h3>
                <p>Роль: <?= $arResult['USER']['ROLE_TEXT'] ?></p>
                
                <?php if ($arResult['LAST_LOCATION']): ?>
                    <div class="location-info">
                        <h4>Последнее местоположение</h4>
                        <p><strong>Адрес:</strong> <?= htmlspecialchars($arResult['LAST_LOCATION']['address']) ?></p>
                        <p><strong>Время:</strong> <?= date('d.m.Y H:i', strtotime($arResult['LAST_LOCATION']['created_at'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="profile-actions">
                    <button class="btn btn-outline-primary" onclick="toggleLocationTracking()">
                        <i class="fas fa-location-arrow"></i> 
                        <span id="trackingStatus">Включить отслеживание</span>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно доставки -->
<div class="modal" id="deliveryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Завершение доставки</h3>
            <button class="close-btn" onclick="closeDeliveryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="deliveryForm">
                <input type="hidden" id="deliveryRequestId">
                
                <div class="form-group">
                    <label>Телефон курьера</label>
                    <input type="tel" class="form-control" id="courierPhone" placeholder="+7 (xxx) xxx-xx-xx" required>
                </div>
                
                <div class="form-group">
                    <label>Фотографии доставки (минимум 2)</label>
                    <div class="photo-upload">
                        <input type="file" id="deliveryPhotos" multiple accept="image/*" capture="environment">
                        <div class="photo-preview" id="photoPreview"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Комментарий</label>
                    <textarea class="form-control" id="deliveryComment" rows="3" placeholder="Дополнительная информация о доставке"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeliveryModal()">Отмена</button>
            <button class="btn btn-primary" onclick="completeDelivery()">Завершить доставку</button>
        </div>
    </div>
</div>

<script>
let currentRequestId = null;
let isLocationTracking = false;
let map = null;
let userLocation = null;

document.addEventListener('DOMContentLoaded', function() {
    initApp();
    initMap();
    startLocationTracking();
});

function initApp() {
    // Навигация по вкладкам
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            switchTab(tab);
        });
    });
    
    // Обновление местоположения
    document.getElementById('updateLocationBtn')?.addEventListener('click', updateLocation);
    document.getElementById('centerMapBtn')?.addEventListener('click', centerMap);
}

function switchTab(tabName) {
    // Скрываем все вкладки
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Убираем активный класс с кнопок
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Показываем нужную вкладку
    document.getElementById(tabName + '-tab').classList.add('active');
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    
    // Если переключились на карту, обновляем её
    if (tabName === 'map' && map) {
        setTimeout(() => {
            map.container.fitToViewport();
        }, 100);
    }
}

function initMap() {
    if (typeof ymaps === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://api-maps.yandex.ru/2.1/?apikey=<?= $arResult['API_KEY'] ?>&lang=ru_RU';
        script.onload = function() {
            createMap();
        };
        document.head.appendChild(script);
    } else {
        createMap();
    }
}

function createMap() {
    ymaps.ready(function() {
        map = new ymaps.Map('courierMap', {
            center: [55.7558, 37.6176], // Москва по умолчанию
            zoom: 15,
            controls: ['zoomControl', 'fullscreenControl']
        });
        
        // Получаем текущее местоположение
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const coords = [position.coords.latitude, position.coords.longitude];
                map.setCenter(coords);
                userLocation = coords;
                
                // Добавляем маркер текущего местоположения
                const userMarker = new ymaps.Placemark(coords, {
                    balloonContent: 'Ваше местоположение'
                }, {
                    preset: 'islands#blueCircleDotIcon'
                });
                map.geoObjects.add(userMarker);
            });
        }
        
        // Добавляем маркеры заявок
        <?php foreach ($arResult['REQUESTS'] as $request): ?>
            <?php if ($request['coordinates']): ?>
            const requestMarker<?= $request['id'] ?> = new ymaps.Placemark(
                [<?= $request['coordinates']['lat'] ?>, <?= $request['coordinates']['lon'] ?>],
                {
                    balloonContent: `
                        <div>
                            <h4>Заявка #<?= $request['request_number'] ?></h4>
                            <p><strong>Клиент:</strong> <?= htmlspecialchars($request['client_name']) ?></p>
                            <p><strong>Телефон:</strong> <?= $request['client_phone'] ?></p>
                            <p><strong>Адрес:</strong> <?= htmlspecialchars($request['client_address']) ?></p>
                        </div>
                    `
                },
                {
                    preset: 'islands#redCircleDotIcon'
                }
            );
            map.geoObjects.add(requestMarker<?= $request['id'] ?>);
            <?php endif; ?>
        <?php endforeach; ?>
        
        // Скрываем индикатор загрузки
        document.querySelector('.map-loading').style.display = 'none';
    });
}

function startDelivery(requestId) {
    currentRequestId = requestId;
    document.getElementById('deliveryRequestId').value = requestId;
    document.getElementById('deliveryModal').style.display = 'block';
}

function closeDeliveryModal() {
    document.getElementById('deliveryModal').style.display = 'none';
    document.getElementById('deliveryForm').reset();
    document.getElementById('photoPreview').innerHTML = '';
}

function completeDelivery() {
    const form = document.getElementById('deliveryForm');
    const formData = new FormData(form);
    
    // Проверяем количество фотографий
    const photos = document.getElementById('deliveryPhotos').files;
    if (photos.length < 2) {
        alert('Необходимо загрузить минимум 2 фотографии');
        return;
    }
    
    // Добавляем фотографии к форме
    for (let i = 0; i < photos.length; i++) {
        formData.append('photos[]', photos[i]);
    }
    
    formData.append('action', 'complete_delivery');
    formData.append('request_id', currentRequestId);
    
    fetch('/bitrix/admin/courier_service_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Доставка завершена успешно');
            closeDeliveryModal();
            location.reload();
        } else {
            alert('Ошибка: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка при завершении доставки');
    });
}

function showOnMap(requestId) {
    switchTab('map');
    // Здесь можно добавить логику для центрирования карты на заявке
}

function callClient(phone) {
    window.location.href = 'tel:' + phone;
}

function updateLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            // Отправляем координаты на сервер
            fetch('/bitrix/admin/courier_service_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_location',
                    latitude: latitude,
                    longitude: longitude,
                    accuracy: accuracy
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Местоположение обновлено');
                    if (map) {
                        const coords = [latitude, longitude];
                        map.setCenter(coords);
                        userLocation = coords;
                    }
                } else {
                    alert('Ошибка обновления местоположения');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Произошла ошибка при обновлении местоположения');
            });
        });
    } else {
        alert('Геолокация не поддерживается браузером');
    }
}

function centerMap() {
    if (map && userLocation) {
        map.setCenter(userLocation);
    }
}

function startLocationTracking() {
    if (navigator.geolocation) {
        setInterval(function() {
            navigator.geolocation.getCurrentPosition(function(position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                
                // Отправляем координаты на сервер
                fetch('/bitrix/admin/courier_service_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_location',
                        latitude: latitude,
                        longitude: longitude,
                        accuracy: accuracy
                    })
                });
            });
        }, <?= $arResult['UPDATE_INTERVAL'] * 1000 ?>);
    }
}

function toggleLocationTracking() {
    isLocationTracking = !isLocationTracking;
    const statusElement = document.getElementById('trackingStatus');
    
    if (isLocationTracking) {
        statusElement.textContent = 'Отключить отслеживание';
        startLocationTracking();
    } else {
        statusElement.textContent = 'Включить отслеживание';
        // Здесь можно добавить логику для остановки отслеживания
    }
}

function logout() {
    if (confirm('Вы уверены, что хотите выйти?')) {
        window.location.href = '/bitrix/admin/courier_service_logout.php';
    }
}

// Предварительный просмотр фотографий
document.getElementById('deliveryPhotos')?.addEventListener('change', function(e) {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';
    
    Array.from(e.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '100px';
            img.style.height = '100px';
            img.style.objectFit = 'cover';
            img.style.margin = '5px';
            img.style.borderRadius = '5px';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});
</script>

<style>
.courier-mobile-app {
    max-width: 400px;
    margin: 0 auto;
    background: #fff;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.app-header {
    background: #2c6bed;
    color: white;
    padding: 20px;
    text-align: center;
}

.app-header h1 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.user-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #28a745;
}

.app-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.nav-btn {
    flex: 1;
    padding: 15px 10px;
    border: none;
    background: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.nav-btn.active {
    background: #e9ecef;
    color: #2c6bed;
}

.nav-btn i {
    font-size: 20px;
}

.nav-btn span {
    font-size: 12px;
}

.app-content {
    padding: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.request-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.request-header h3 {
    margin: 0;
    font-size: 18px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.waiting_delivery {
    background: #fff3cd;
    color: #856404;
}

.status-badge.in_delivery {
    background: #cce5ff;
    color: #004085;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.info-row i {
    width: 16px;
    color: #6c757d;
}

.request-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: background-color 0.2s;
}

.btn-primary {
    background: #2c6bed;
    color: white;
}

.btn-outline-primary {
    background: transparent;
    color: #2c6bed;
    border: 1px solid #2c6bed;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.map-container {
    height: 300px;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
    position: relative;
}

.map-controls {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.profile-info {
    text-align: center;
}

.profile-avatar {
    font-size: 80px;
    color: #6c757d;
    margin-bottom: 20px;
}

.profile-actions {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    background: white;
    margin: 20px;
    border-radius: 10px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.photo-upload {
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
}

.photo-preview {
    margin-top: 15px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

@media (max-width: 480px) {
    .courier-mobile-app {
        max-width: 100%;
    }
    
    .request-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
}
</style>