// Сервис для отслеживания местоположения
class LocationService {
    constructor() {
        this.watchId = null;
        this.isTracking = false;
        this.updateInterval = 60000; // 60 секунд
        this.lastPosition = null;
        this.init();
    }

    // Инициализация сервиса
    init() {
        // Проверка поддержки геолокации
        if (!navigator.geolocation) {
            console.warn('Геолокация не поддерживается браузером');
            return;
        }

        // Запуск отслеживания для курьеров
        if (authService.isAuthenticated && authService.hasRole('courier')) {
            this.startTracking();
        }
    }

    // Запуск отслеживания местоположения
    startTracking() {
        if (this.isTracking) {
            return;
        }

        this.isTracking = true;
        console.log('Запуск отслеживания местоположения');

        // Получение текущего местоположения
        this.getCurrentPosition();

        // Установка интервала для периодического обновления
        this.watchId = setInterval(() => {
            this.getCurrentPosition();
        }, this.updateInterval);
    }

    // Остановка отслеживания местоположения
    stopTracking() {
        if (!this.isTracking) {
            return;
        }

        this.isTracking = false;
        console.log('Остановка отслеживания местоположения');

        if (this.watchId) {
            clearInterval(this.watchId);
            this.watchId = null;
        }
    }

    // Получение текущего местоположения
    getCurrentPosition() {
        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.handleLocationSuccess(position);
            },
            (error) => {
                this.handleLocationError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000
            }
        );
    }

    // Обработка успешного получения местоположения
    async handleLocationSuccess(position) {
        const { latitude, longitude, accuracy } = position.coords;
        
        // Проверка, изменилось ли местоположение значительно
        if (this.lastPosition) {
            const distance = this.calculateDistance(
                this.lastPosition.latitude,
                this.lastPosition.longitude,
                latitude,
                longitude
            );

            // Отправляем только если переместились более чем на 10 метров
            if (distance < 10) {
                return;
            }
        }

        this.lastPosition = { latitude, longitude, accuracy };

        try {
            await apiService.updateLocation(latitude, longitude, accuracy);
            console.log('Местоположение обновлено:', { latitude, longitude, accuracy });
        } catch (error) {
            console.error('Ошибка обновления местоположения:', error);
        }
    }

    // Обработка ошибки получения местоположения
    handleLocationError(error) {
        let message = 'Ошибка получения местоположения: ';
        
        switch (error.code) {
            case error.PERMISSION_DENIED:
                message += 'Доступ к геолокации запрещен';
                break;
            case error.POSITION_UNAVAILABLE:
                message += 'Местоположение недоступно';
                break;
            case error.TIMEOUT:
                message += 'Время ожидания истекло';
                break;
            default:
                message += 'Неизвестная ошибка';
                break;
        }

        console.error(message);
        
        // Показываем уведомление только для критических ошибок
        if (error.code === error.PERMISSION_DENIED) {
            authService.showNotification(
                'Для работы приложения необходимо разрешить доступ к местоположению',
                'warning'
            );
        }
    }

    // Вычисление расстояния между двумя точками (в метрах)
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // Радиус Земли в метрах
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c;
    }

    // Запрос разрешения на геолокацию
    async requestPermission() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Геолокация не поддерживается'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve(position);
                },
                (error) => {
                    reject(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                }
            );
        });
    }

    // Получение местоположения курьеров для карты
    async getCouriersLocation(branchId = null) {
        try {
            const response = await apiService.getCouriersLocation(branchId);
            return response.couriers;
        } catch (error) {
            console.error('Ошибка получения местоположения курьеров:', error);
            throw error;
        }
    }

    // Получение истории перемещений курьера
    async getCourierHistory(courierId, params = {}) {
        try {
            const response = await apiService.getCourierHistory(courierId, params);
            return response;
        } catch (error) {
            console.error('Ошибка получения истории курьера:', error);
            throw error;
        }
    }

    // Получение маршрута курьера для заявки
    async getCourierRoute(requestId) {
        try {
            const response = await apiService.getCourierRoute(requestId);
            return response;
        } catch (error) {
            console.error('Ошибка получения маршрута курьера:', error);
            throw error;
        }
    }

    // Инициализация карты (заглушка для Яндекс.Карт)
    initMap(containerId, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Контейнер карты не найден:', containerId);
            return null;
        }

        // Заглушка для карты
        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100">
                <div class="text-center">
                    <i class="fas fa-map-marked-alt fa-3x mb-3 text-muted"></i>
                    <h5 class="text-muted">Интеграция с Яндекс.Картами</h5>
                    <p class="text-muted">Отслеживание местоположения курьеров</p>
                    <button class="btn btn-primary" onclick="locationService.loadCouriersOnMap()">
                        <i class="fas fa-sync-alt me-1"></i>Загрузить курьеров
                    </button>
                </div>
            </div>
        `;

        return {
            container,
            addMarker: (lat, lng, title) => {
                console.log('Добавление маркера:', { lat, lng, title });
            },
            setCenter: (lat, lng) => {
                console.log('Установка центра карты:', { lat, lng });
            },
            clear: () => {
                console.log('Очистка карты');
            }
        };
    }

    // Загрузка курьеров на карту
    async loadCouriersOnMap() {
        try {
            const couriers = await this.getCouriersLocation();
            console.log('Курьеры загружены:', couriers);
            
            // Здесь будет логика отображения курьеров на карте
            authService.showNotification(`Загружено ${couriers.length} курьеров`, 'success');
        } catch (error) {
            authService.showNotification('Ошибка загрузки курьеров', 'error');
        }
    }

    // Обновление интервала отслеживания
    setUpdateInterval(interval) {
        this.updateInterval = interval;
        
        if (this.isTracking) {
            this.stopTracking();
            this.startTracking();
        }
    }

    // Получение статуса отслеживания
    getTrackingStatus() {
        return {
            isTracking: this.isTracking,
            updateInterval: this.updateInterval,
            lastPosition: this.lastPosition
        };
    }
}

// Создание глобального экземпляра сервиса местоположения
window.locationService = new LocationService();