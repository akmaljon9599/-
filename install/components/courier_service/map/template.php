<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$this->setFrameMode(true);
?>

<div id="courier-map-<?= $this->randString() ?>" class="courier-map-container" 
     style="width: <?= $arResult['WIDTH'] ?>; height: <?= $arResult['HEIGHT'] ?>;">
    <div class="map-loading">
        <div class="spinner"></div>
        <p>Загрузка карты...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('courier-map-<?= $this->randString() ?>');
    const mapId = 'courier-map-<?= $this->randString() ?>';
    
    // Загружаем API Яндекс.Карт
    if (typeof ymaps === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://api-maps.yandex.ru/2.1/?apikey=<?= $arResult['API_KEY'] ?>&lang=ru_RU';
        script.onload = function() {
            initMap();
        };
        document.head.appendChild(script);
    } else {
        initMap();
    }
    
    function initMap() {
        ymaps.ready(function() {
            const map = new ymaps.Map(mapId, {
                center: [<?= $arResult['CENTER']['lat'] ?>, <?= $arResult['CENTER']['lon'] ?>],
                zoom: <?= $arResult['ZOOM'] ?>,
                controls: ['zoomControl', 'fullscreenControl', 'typeSelector']
            });
            
            // Добавляем слой трафика
            <?php if ($arResult['SHOW_TRAFFIC']): ?>
            map.controls.add(new ymaps.control.TrafficControl({
                state: {
                    providerKey: 'traffic#actual'
                }
            }));
            <?php endif; ?>
            
            const couriersCollection = new ymaps.GeoObjectCollection();
            const branchesCollection = new ymaps.GeoObjectCollection();
            
            // Добавляем филиалы
            <?php foreach ($arResult['BRANCHES'] as $branch): ?>
                <?php if ($branch['coordinates']): ?>
                const branchMarker<?= $branch['id'] ?> = new ymaps.Placemark(
                    [<?= $branch['coordinates']['lat'] ?>, <?= $branch['coordinates']['lon'] ?>],
                    {
                        balloonContent: `
                            <div>
                                <h4><?= htmlspecialchars($branch['name']) ?></h4>
                                <p><strong>Адрес:</strong> <?= htmlspecialchars($branch['address']) ?></p>
                            </div>
                        `,
                        iconCaption: '<?= htmlspecialchars($branch['name']) ?>'
                    },
                    {
                        preset: 'islands#blueCircleDotIcon',
                        iconColor: '#0066cc'
                    }
                );
                branchesCollection.add(branchMarker<?= $branch['id'] ?>);
                <?php endif; ?>
            <?php endforeach; ?>
            
            map.geoObjects.add(branchesCollection);
            
            // Функция обновления курьеров
            function updateCouriers() {
                fetch('/bitrix/admin/courier_service_api.php?action=get_couriers_locations')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Очищаем старые маркеры курьеров
                            couriersCollection.removeAll();
                            
                            // Добавляем новые маркеры
                            data.data.couriers.forEach(courier => {
                                if (courier.location && courier.location.latitude && courier.location.longitude) {
                                    const statusClass = getCourierStatusClass(courier.location);
                                    const statusText = getCourierStatusText(courier.location);
                                    const markerColor = getCourierMarkerColor(courier.location);
                                    
                                    const courierMarker = new ymaps.Placemark(
                                        [courier.location.latitude, courier.location.longitude],
                                        {
                                            balloonContent: `
                                                <div>
                                                    <h4>Курьер #${courier.user_id}</h4>
                                                    <p><strong>Статус:</strong> ${statusText}</p>
                                                    <p><strong>Адрес:</strong> ${courier.location.address || 'Не определено'}</p>
                                                    <p><strong>Последняя активность:</strong> ${formatDateTime(courier.location.created_at)}</p>
                                                </div>
                                            `,
                                            iconCaption: `Курьер #${courier.user_id}`
                                        },
                                        {
                                            preset: 'islands#circleDotIcon',
                                            iconColor: markerColor
                                        }
                                    );
                                    
                                    couriersCollection.add(courierMarker);
                                }
                            });
                            
                            map.geoObjects.add(couriersCollection);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating couriers:', error);
                    });
            }
            
            // Обновляем курьеров при загрузке
            <?php if ($arResult['SHOW_COURIERS']): ?>
            updateCouriers();
            
            // Автообновление
            <?php if ($arResult['AUTO_UPDATE']): ?>
            setInterval(updateCouriers, <?= $arResult['UPDATE_INTERVAL'] * 1000 ?>);
            <?php endif; ?>
            <?php endif; ?>
            
            // Скрываем индикатор загрузки
            mapContainer.querySelector('.map-loading').style.display = 'none';
        });
    }
    
    // Вспомогательные функции
    function getCourierStatusClass(location) {
        if (!location) return 'offline';
        
        const now = new Date();
        const lastUpdate = new Date(location.created_at);
        const diffMinutes = (now - lastUpdate) / (1000 * 60);
        
        if (diffMinutes <= 5) {
            return 'online';
        } else if (diffMinutes <= 30) {
            return 'on-delivery';
        } else {
            return 'offline';
        }
    }
    
    function getCourierStatusText(location) {
        if (!location) return 'Неактивен';
        
        const now = new Date();
        const lastUpdate = new Date(location.created_at);
        const diffMinutes = (now - lastUpdate) / (1000 * 60);
        
        if (diffMinutes <= 5) {
            return 'Активен';
        } else if (diffMinutes <= 30) {
            return 'На доставке';
        } else {
            return 'Неактивен';
        }
    }
    
    function getCourierMarkerColor(location) {
        const statusClass = getCourierStatusClass(location);
        const colors = {
            'online': '#28a745',
            'on-delivery': '#ffc107',
            'offline': '#6c757d'
        };
        return colors[statusClass] || '#6c757d';
    }
    
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
});
</script>

<style>
.courier-map-container {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.map-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: #f8f9fa;
    z-index: 1000;
}

.map-loading .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2c6bed;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

.map-loading p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>