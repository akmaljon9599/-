/**
 * Модуль для работы с Яндекс.Картами
 * Система управления курьерскими заявками
 */

class CourierMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = {
            center: [55.76, 37.64], // Москва по умолчанию
            zoom: 10,
            controls: ['zoomControl', 'fullscreenControl'],
            ...options
        };
        
        this.map = null;
        this.couriers = new Map(); // Хранилище маркеров курьеров
        this.deliveryPoints = new Map(); // Хранилище точек доставки
        this.isInitialized = false;
        
        this.init();
    }

    /**
     * Инициализация карты
     */
    async init() {
        try {
            // Проверяем, загружен ли API Яндекс.Карт
            if (typeof ymaps === 'undefined') {
                await this.loadYandexMapsAPI();
            }

            ymaps.ready(() => {
                this.createMap();
                this.isInitialized = true;
            });

        } catch (error) {
            console.error('Error initializing map:', error);
        }
    }

    /**
     * Загрузить API Яндекс.Карт
     */
    loadYandexMapsAPI() {
        return new Promise((resolve, reject) => {
            if (typeof ymaps !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://api-maps.yandex.ru/2.1/?apikey=YOUR_API_KEY&lang=ru_RU';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Создать карту
     */
    createMap() {
        this.map = new ymaps.Map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            controls: this.options.controls
        });

        // Добавляем кластеризатор для курьеров
        this.courierClusterer = new ymaps.Clusterer({
            preset: 'islands#invertedVioletClusterIcons',
            groupByCoordinates: false,
            clusterDisableClickZoom: false,
            clusterHideIconOnBalloonOpen: false,
            geoObjectHideIconOnBalloonOpen: false
        });

        this.map.geoObjects.add(this.courierClusterer);

        // Добавляем обработчики событий
        this.setupEventHandlers();
    }

    /**
     * Настроить обработчики событий карты
     */
    setupEventHandlers() {
        // Обработчик клика по карте
        this.map.events.add('click', (e) => {
            const coords = e.get('coords');
            this.onMapClick(coords);
        });

        // Обработчик изменения области просмотра
        this.map.events.add('boundschange', () => {
            this.onBoundsChange();
        });
    }

    /**
     * Обновить позиции курьеров на карте
     */
    updateCouriers(couriersData) {
        if (!this.isInitialized) {
            setTimeout(() => this.updateCouriers(couriersData), 1000);
            return;
        }

        // Очищаем старые маркеры
        this.courierClusterer.removeAll();
        this.couriers.clear();

        couriersData.forEach(courier => {
            const placemark = this.createCourierPlacemark(courier);
            this.couriers.set(courier.user_id, placemark);
            this.courierClusterer.add(placemark);
        });
    }

    /**
     * Создать маркер курьера
     */
    createCourierPlacemark(courier) {
        const coords = [courier.latitude, courier.longitude];
        
        // Определяем иконку в зависимости от статуса
        let preset = 'islands#blueCircleDotIcon';
        let color = '#1e98ff';
        
        switch (courier.status) {
            case 'available':
                preset = 'islands#greenCircleDotIcon';
                color = '#00c851';
                break;
            case 'on_delivery':
                preset = 'islands#orangeCircleDotIcon';
                color = '#ff8800';
                break;
            case 'offline':
                preset = 'islands#grayCircleDotIcon';
                color = '#6c757d';
                break;
        }

        const placemark = new ymaps.Placemark(coords, {
            balloonContentHeader: `<strong>${courier.courier_name}</strong>`,
            balloonContentBody: this.getCourierBalloonContent(courier),
            balloonContentFooter: `<small>Обновлено: ${this.formatDateTime(courier.last_location_update)}</small>`,
            hintContent: `${courier.courier_name} - ${this.getStatusText(courier.status)}`
        }, {
            preset: preset,
            iconColor: color
        });

        // Добавляем обработчики событий для маркера
        placemark.events.add('click', () => {
            this.onCourierClick(courier);
        });

        return placemark;
    }

    /**
     * Получить содержимое балуна курьера
     */
    getCourierBalloonContent(courier) {
        const statusText = this.getStatusText(courier.status);
        const vehicleText = this.getVehicleText(courier.vehicle_type);
        
        return `
            <div class="courier-balloon">
                <p><strong>Статус:</strong> ${statusText}</p>
                <p><strong>Телефон:</strong> ${courier.phone}</p>
                <p><strong>Транспорт:</strong> ${vehicleText}</p>
                <p><strong>Активных заказов:</strong> ${courier.active_orders}</p>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="app.callCourier('${courier.user_id}')">
                        <i class="fas fa-phone"></i> Позвонить
                    </button>
                    <button class="btn btn-sm btn-info" onclick="app.showCourierRoute('${courier.user_id}')">
                        <i class="fas fa-route"></i> Маршрут
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Получить текст статуса курьера
     */
    getStatusText(status) {
        const statuses = {
            'available': 'Доступен',
            'on_delivery': 'На доставке',
            'offline': 'Не в сети'
        };
        return statuses[status] || status;
    }

    /**
     * Получить текст типа транспорта
     */
    getVehicleText(vehicleType) {
        const vehicles = {
            'foot': 'Пешком',
            'bicycle': 'Велосипед',
            'motorcycle': 'Мотоцикл',
            'car': 'Автомобиль'
        };
        return vehicles[vehicleType] || vehicleType;
    }

    /**
     * Добавить точки доставки на карту
     */
    addDeliveryPoints(requests) {
        if (!this.isInitialized) {
            setTimeout(() => this.addDeliveryPoints(requests), 1000);
            return;
        }

        // Очищаем старые точки доставки
        this.deliveryPoints.forEach(placemark => {
            this.map.geoObjects.remove(placemark);
        });
        this.deliveryPoints.clear();

        requests.forEach(request => {
            if (request.delivery_latitude && request.delivery_longitude) {
                const placemark = this.createDeliveryPlacemark(request);
                this.deliveryPoints.set(request.id, placemark);
                this.map.geoObjects.add(placemark);
            }
        });
    }

    /**
     * Создать маркер точки доставки
     */
    createDeliveryPlacemark(request) {
        const coords = [request.delivery_latitude, request.delivery_longitude];
        
        // Определяем иконку в зависимости от статуса заявки
        let preset = 'islands#blueIcon';
        
        switch (request.status) {
            case 'new':
                preset = 'islands#blueIcon';
                break;
            case 'assigned':
                preset = 'islands#yellowIcon';
                break;
            case 'in_progress':
                preset = 'islands#orangeIcon';
                break;
            case 'delivered':
                preset = 'islands#greenIcon';
                break;
            case 'rejected':
                preset = 'islands#redIcon';
                break;
        }

        const placemark = new ymaps.Placemark(coords, {
            balloonContentHeader: `<strong>Заявка ${request.request_number}</strong>`,
            balloonContentBody: this.getDeliveryBalloonContent(request),
            hintContent: `${request.client_full_name} - ${request.delivery_address}`
        }, {
            preset: preset
        });

        placemark.events.add('click', () => {
            this.onDeliveryPointClick(request);
        });

        return placemark;
    }

    /**
     * Получить содержимое балуна точки доставки
     */
    getDeliveryBalloonContent(request) {
        const statusText = this.getRequestStatusText(request.status);
        
        return `
            <div class="delivery-balloon">
                <p><strong>Клиент:</strong> ${request.client_full_name}</p>
                <p><strong>Телефон:</strong> ${request.client_phone}</p>
                <p><strong>Адрес:</strong> ${request.delivery_address}</p>
                <p><strong>Статус:</strong> ${statusText}</p>
                ${request.courier_name ? `<p><strong>Курьер:</strong> ${request.courier_name}</p>` : ''}
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="app.viewRequest(${request.id})">
                        <i class="fas fa-eye"></i> Подробнее
                    </button>
                    ${this.canEditRequest() ? `
                        <button class="btn btn-sm btn-success" onclick="app.editRequest(${request.id})">
                            <i class="fas fa-edit"></i> Изменить
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Получить текст статуса заявки
     */
    getRequestStatusText(status) {
        const statuses = {
            'new': 'Новая',
            'assigned': 'Назначена',
            'in_progress': 'В работе',
            'delivered': 'Доставлено',
            'rejected': 'Отказано',
            'cancelled': 'Отменена'
        };
        return statuses[status] || status;
    }

    /**
     * Построить маршрут между точками
     */
    buildRoute(startCoords, endCoords, options = {}) {
        if (!this.isInitialized) return;

        const routeOptions = {
            balloonContentBodyLayout: ymaps.templateLayoutFactory.createClass(
                '<div>Расстояние: $[properties.distance]</div>' +
                '<div>Время в пути: $[properties.duration]</div>'
            ),
            ...options
        };

        ymaps.route([startCoords, endCoords], routeOptions)
            .then(route => {
                this.map.geoObjects.add(route);
                
                // Подгоняем масштаб карты под маршрут
                this.map.setBounds(route.getBounds(), {
                    checkZoomRange: true
                });
            })
            .catch(error => {
                console.error('Error building route:', error);
            });
    }

    /**
     * Показать маршрут курьера
     */
    showCourierRoute(courierId) {
        // Получаем историю местоположений курьера и строим маршрут
        if (app) {
            app.apiRequest('GET', `/couriers/${courierId}/location-history`)
                .then(response => {
                    if (response.success && response.data.length > 1) {
                        const coordinates = response.data.map(point => [point.latitude, point.longitude]);
                        
                        const polyline = new ymaps.Polyline(coordinates, {}, {
                            strokeColor: '#FF0000',
                            strokeWidth: 3,
                            strokeOpacity: 0.7
                        });

                        this.map.geoObjects.add(polyline);
                        this.map.setBounds(polyline.getBounds());
                    }
                })
                .catch(error => {
                    console.error('Error loading courier route:', error);
                });
        }
    }

    /**
     * Геокодирование адреса
     */
    geocodeAddress(address) {
        return new Promise((resolve, reject) => {
            if (!this.isInitialized) {
                reject(new Error('Map not initialized'));
                return;
            }

            ymaps.geocode(address, {
                results: 1
            }).then(result => {
                const firstGeoObject = result.geoObjects.get(0);
                if (firstGeoObject) {
                    const coords = firstGeoObject.geometry.getCoordinates();
                    resolve({
                        coordinates: coords,
                        address: firstGeoObject.getAddressLine()
                    });
                } else {
                    reject(new Error('Address not found'));
                }
            }).catch(reject);
        });
    }

    /**
     * Центрировать карту на координатах
     */
    centerOn(coords, zoom = null) {
        if (!this.isInitialized) return;
        
        this.map.setCenter(coords);
        if (zoom !== null) {
            this.map.setZoom(zoom);
        }
    }

    /**
     * Подогнать карту под все объекты
     */
    fitBounds() {
        if (!this.isInitialized) return;

        const allObjects = [];
        
        // Добавляем курьеров
        this.couriers.forEach(placemark => {
            allObjects.push(placemark);
        });
        
        // Добавляем точки доставки
        this.deliveryPoints.forEach(placemark => {
            allObjects.push(placemark);
        });

        if (allObjects.length > 0) {
            const group = new ymaps.GeoObjectCollection({}, {});
            allObjects.forEach(obj => group.add(obj));
            
            this.map.setBounds(group.getBounds(), {
                checkZoomRange: true,
                zoomMargin: 20
            });
        }
    }

    /**
     * Обработчик клика по карте
     */
    onMapClick(coords) {
        // Можно добавить функциональность для клика по карте
        console.log('Map clicked at:', coords);
    }

    /**
     * Обработчик изменения области просмотра
     */
    onBoundsChange() {
        // Можно добавить логику для загрузки данных при изменении области просмотра
    }

    /**
     * Обработчик клика по курьеру
     */
    onCourierClick(courier) {
        console.log('Courier clicked:', courier);
        // Дополнительная логика обработки клика
    }

    /**
     * Обработчик клика по точке доставки
     */
    onDeliveryPointClick(request) {
        console.log('Delivery point clicked:', request);
        // Дополнительная логика обработки клика
    }

    /**
     * Проверить права на редактирование заявок
     */
    canEditRequest() {
        return app && app.user && ['admin', 'operator', 'senior_courier'].includes(app.user.role);
    }

    /**
     * Форматировать дату и время
     */
    formatDateTime(dateTime) {
        if (!dateTime) return 'Неизвестно';
        
        const date = new Date(dateTime);
        return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Очистить карту
     */
    clear() {
        if (!this.isInitialized) return;
        
        this.courierClusterer.removeAll();
        this.deliveryPoints.forEach(placemark => {
            this.map.geoObjects.remove(placemark);
        });
        
        this.couriers.clear();
        this.deliveryPoints.clear();
    }

    /**
     * Уничтожить карту
     */
    destroy() {
        if (this.map) {
            this.map.destroy();
            this.map = null;
            this.isInitialized = false;
        }
    }
}

// Экспортируем класс для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CourierMap;
}