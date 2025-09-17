<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use CourierService\Main\CourierTable;
use CourierService\Api\YandexMaps;
use CourierService\Utils\LocationTracker;

class CourierServiceCourierMapComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['MAP_WIDTH'] = $arParams['MAP_WIDTH'] ?? '100%';
        $arParams['MAP_HEIGHT'] = $arParams['MAP_HEIGHT'] ?? '400px';
        $arParams['SHOW_COURIERS'] = $arParams['SHOW_COURIERS'] !== 'N';
        $arParams['AUTO_REFRESH'] = $arParams['AUTO_REFRESH'] !== 'N';
        $arParams['REFRESH_INTERVAL'] = intval($arParams['REFRESH_INTERVAL'] ?? 30);
        $arParams['DEFAULT_CENTER_LAT'] = floatval($arParams['DEFAULT_CENTER_LAT'] ?? 55.7558);
        $arParams['DEFAULT_CENTER_LON'] = floatval($arParams['DEFAULT_CENTER_LON'] ?? 37.6176);
        $arParams['DEFAULT_ZOOM'] = intval($arParams['DEFAULT_ZOOM'] ?? 10);

        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('courier_service')) {
            $this->abortResultCache();
            ShowError('Модуль курьерской службы не установлен');
            return;
        }

        if ($this->startResultCache()) {
            $this->prepareData();
            $this->includeComponentTemplate();
        }
    }

    protected function prepareData()
    {
        $this->arResult['MAP_ID'] = 'courier_map_' . $this->randString();
        $this->arResult['MAP_WIDTH'] = $this->arParams['MAP_WIDTH'];
        $this->arResult['MAP_HEIGHT'] = $this->arParams['MAP_HEIGHT'];
        $this->arResult['AUTO_REFRESH'] = $this->arParams['AUTO_REFRESH'];
        $this->arResult['REFRESH_INTERVAL'] = $this->arParams['REFRESH_INTERVAL'] * 1000; // в миллисекундах
        $this->arResult['DEFAULT_CENTER'] = [
            'lat' => $this->arParams['DEFAULT_CENTER_LAT'],
            'lon' => $this->arParams['DEFAULT_CENTER_LON']
        ];
        $this->arResult['DEFAULT_ZOOM'] = $this->arParams['DEFAULT_ZOOM'];

        // Получаем данные курьеров
        if ($this->arParams['SHOW_COURIERS']) {
            $locationTracker = new LocationTracker();
            $couriersResult = $locationTracker->getActiveCouriersLocations();
            
            if ($couriersResult['success']) {
                $this->arResult['COURIERS'] = $couriersResult['couriers'];
            } else {
                $this->arResult['COURIERS'] = [];
                $this->arResult['ERROR'] = $couriersResult['error'];
            }
        } else {
            $this->arResult['COURIERS'] = [];
        }

        // Получаем настройки Яндекс.Карт
        $yandexMaps = new YandexMaps();
        $this->arResult['YANDEX_MAPS_API_KEY'] = \CourierService\Main\SettingTable::get('yandex_maps_api_key', '');
        
        // Генерируем JavaScript для инициализации карты
        $this->arResult['MAP_INIT_SCRIPT'] = $this->generateMapInitScript();
    }

    protected function generateMapInitScript()
    {
        $mapId = $this->arResult['MAP_ID'];
        $center = $this->arResult['DEFAULT_CENTER'];
        $zoom = $this->arResult['DEFAULT_ZOOM'];
        $couriers = $this->arResult['COURIERS'];
        $autoRefresh = $this->arResult['AUTO_REFRESH'];
        $refreshInterval = $this->arResult['REFRESH_INTERVAL'];

        $script = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ymaps === 'undefined') {
                console.error('Yandex Maps API не загружен');
                return;
            }

            ymaps.ready(function() {
                // Создаем карту
                var map = new ymaps.Map('{$mapId}', {
                    center: [{$center['lat']}, {$center['lon']}],
                    zoom: {$zoom},
                    controls: ['zoomControl', 'fullscreenControl', 'typeSelector']
                });

                // Массив маркеров курьеров
                var courierMarkers = [];

                // Функция создания маркера курьера
                function createCourierMarker(courier) {
                    var statusColors = {
                        'active': '#28a745',
                        'on_delivery': '#ffc107',
                        'inactive': '#6c757d'
                    };

                    var color = statusColors[courier.status] || '#6c757d';
                    
                    var marker = new ymaps.Placemark([courier.latitude, courier.longitude], {
                        balloonContentHeader: courier.name,
                        balloonContentBody: 'Статус: ' + courier.status + '<br>' +
                                         'Филиал: ' + courier.branch + '<br>' +
                                         'Последняя активность: ' + courier.last_activity,
                        balloonContentFooter: '<a href=\"#\" onclick=\"showCourierDetails(' + courier.id + ')\">Подробнее</a>',
                        hintContent: courier.name
                    }, {
                        preset: 'islands#circleIcon',
                        iconColor: color,
                        iconImageSize: [30, 30],
                        iconImageOffset: [-15, -15]
                    });

                    return marker;
                }

                // Функция обновления маркеров курьеров
                function updateCourierMarkers(couriers) {
                    // Удаляем старые маркеры
                    courierMarkers.forEach(function(marker) {
                        map.geoObjects.remove(marker);
                    });
                    courierMarkers = [];

                    // Добавляем новые маркеры
                    couriers.forEach(function(courier) {
                        var marker = createCourierMarker(courier);
                        map.geoObjects.add(marker);
                        courierMarkers.push(marker);
                    });
                }

                // Инициализация маркеров
                updateCourierMarkers(" . json_encode($couriers) . ");

                // Автообновление
                " . ($autoRefresh ? "
                setInterval(function() {
                    fetch('" . $this->getPath() . "/ajax/get_couriers.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateCourierMarkers(data.couriers);
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка обновления данных курьеров:', error);
                        });
                }, {$refreshInterval});
                " : "") . "

                // Глобальная функция для показа деталей курьера
                window.showCourierDetails = function(courierId) {
                    // Здесь можно добавить логику показа детальной информации о курьере
                    console.log('Показать детали курьера:', courierId);
                };

                // Сохраняем ссылку на карту для внешнего доступа
                window.courierMap = map;
            });
        });
        </script>";

        return $script;
    }
}