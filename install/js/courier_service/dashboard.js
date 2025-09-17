/**
 * JavaScript для дашборда курьерской службы
 */

class CourierServiceDashboard {
    constructor() {
        this.map = null;
        this.couriersMarkers = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStatistics();
        this.loadCouriersLocations();
        this.loadRecentRequests();
        this.initMap();
        
        // Обновляем данные каждые 30 секунд
        setInterval(() => {
            this.loadStatistics();
            this.loadCouriersLocations();
        }, 30000);
    }

    bindEvents() {
        document.getElementById('refreshMap')?.addEventListener('click', () => this.refreshMap());
        document.getElementById('fullscreenMap')?.addEventListener('click', () => this.showFullscreenMap());
    }

    async loadStatistics() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_statistics');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('totalRequests').textContent = data.data.total;
                document.getElementById('deliveredRequests').textContent = data.data.delivered;
                document.getElementById('inProgressRequests').textContent = data.data.in_progress;
                document.getElementById('rejectedRequests').textContent = data.data.rejected;
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    async loadCouriersLocations() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_couriers_locations');
            const data = await response.json();
            
            if (data.success) {
                this.renderCouriersTable(data.data.couriers);
                this.updateMapMarkers(data.data.couriers);
            }
        } catch (error) {
            console.error('Error loading couriers locations:', error);
        }
    }

    async loadRecentRequests() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_requests&limit=10');
            const data = await response.json();
            
            if (data.success) {
                this.renderRecentRequests(data.data.requests);
            }
        } catch (error) {
            console.error('Error loading recent requests:', error);
        }
    }

    renderCouriersTable(couriers) {
        const tbody = document.getElementById('couriersTableBody');
        if (!tbody) return;

        if (couriers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>Курьеры не найдены</h4>
                            <p>Нет активных курьеров в системе</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = couriers.map(courier => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="courier-status ${this.getCourierStatusClass(courier.location)}"></span>
                        Курьер #${courier.user_id}
                    </div>
                </td>
                <td><span class="badge ${this.getCourierStatusBadgeClass(courier.location)} status-badge">${this.getCourierStatusText(courier.location)}</span></td>
                <td>-</td>
                <td>${courier.location?.address || 'Не определено'}</td>
                <td>${courier.location ? this.formatDateTime(courier.location.created_at) : '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="dashboard.showCourierOnMap(${courier.courier_id})" title="Показать на карте">
                        <i class="fas fa-map-marker-alt"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    renderRecentRequests(requests) {
        const tbody = document.getElementById('recentRequestsTableBody');
        if (!tbody) return;

        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>Заявки не найдены</h4>
                            <p>Нет заявок в системе</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = requests.map(request => `
            <tr>
                <td>#${request.request_number}</td>
                <td>${request.client_name}</td>
                <td><span class="badge ${this.getStatusClass(request.status)} status-badge">${request.status_text}</span></td>
                <td>${request.courier_name || 'Не назначен'}</td>
                <td>${this.formatDateTime(request.registration_date)}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="dashboard.viewRequest(${request.id})" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="dashboard.editRequest(${request.id})" title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    initMap() {
        if (typeof ymaps === 'undefined') {
            console.error('Yandex Maps API not loaded');
            return;
        }

        ymaps.ready(() => {
            this.map = new ymaps.Map('couriersMap', {
                center: [55.7558, 37.6176], // Москва
                zoom: 10,
                controls: ['zoomControl', 'fullscreenControl', 'typeSelector']
            });

            // Добавляем слой трафика
            this.map.controls.add(new ymaps.control.TrafficControl({
                state: {
                    providerKey: 'traffic#actual'
                }
            }));
        });
    }

    updateMapMarkers(couriers) {
        if (!this.map) return;

        // Удаляем старые маркеры
        this.couriersMarkers.forEach(marker => {
            this.map.geoObjects.remove(marker);
        });
        this.couriersMarkers = [];

        // Добавляем новые маркеры
        couriers.forEach(courier => {
            if (courier.location && courier.location.latitude && courier.location.longitude) {
                const marker = new ymaps.Placemark(
                    [courier.location.latitude, courier.location.longitude],
                    {
                        balloonContent: `
                            <div>
                                <h4>Курьер #${courier.user_id}</h4>
                                <p><strong>Статус:</strong> ${this.getCourierStatusText(courier.location)}</p>
                                <p><strong>Адрес:</strong> ${courier.location.address || 'Не определено'}</p>
                                <p><strong>Последняя активность:</strong> ${this.formatDateTime(courier.location.created_at)}</p>
                            </div>
                        `,
                        iconCaption: `Курьер #${courier.user_id}`
                    },
                    {
                        preset: this.getCourierMarkerPreset(courier.location),
                        iconColor: this.getCourierMarkerColor(courier.location)
                    }
                );

                this.couriersMarkers.push(marker);
                this.map.geoObjects.add(marker);
            }
        });

        // Подгоняем карту под все маркеры
        if (this.couriersMarkers.length > 0) {
            this.map.setBounds(this.map.geoObjects.getBounds(), {
                checkZoomRange: true
            });
        }
    }

    showCourierOnMap(courierId) {
        const marker = this.couriersMarkers.find(m => 
            m.properties.get('balloonContent').includes(`Курьер #${courierId}`)
        );
        
        if (marker) {
            this.map.setCenter(marker.geometry.getCoordinates());
            this.map.setZoom(15);
            marker.balloon.open();
        }
    }

    refreshMap() {
        this.loadCouriersLocations();
        this.showNotification('Карта обновлена', 'success');
    }

    showFullscreenMap() {
        const modal = new bootstrap.Modal(document.getElementById('fullscreenMapModal'));
        modal.show();
        
        // Инициализируем карту в модальном окне
        setTimeout(() => {
            if (typeof ymaps !== 'undefined') {
                const fullscreenMap = new ymaps.Map('fullscreenMapContainer', {
                    center: this.map ? this.map.getCenter() : [55.7558, 37.6176],
                    zoom: this.map ? this.map.getZoom() : 10,
                    controls: ['zoomControl', 'fullscreenControl', 'typeSelector']
                });

                // Копируем маркеры на полную карту
                this.couriersMarkers.forEach(marker => {
                    const newMarker = new ymaps.Placemark(
                        marker.geometry.getCoordinates(),
                        marker.properties.getAll(),
                        marker.options.getAll()
                    );
                    fullscreenMap.geoObjects.add(newMarker);
                });
            }
        }, 100);
    }

    viewRequest(requestId) {
        window.open(`/bitrix/admin/courier_service_request_detail.php?id=${requestId}`, '_blank');
    }

    editRequest(requestId) {
        window.open(`/bitrix/admin/courier_service_request_edit.php?id=${requestId}`, '_blank');
    }

    getCourierStatusClass(location) {
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

    getCourierStatusText(location) {
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

    getCourierStatusBadgeClass(location) {
        const statusClass = this.getCourierStatusClass(location);
        const classes = {
            'online': 'bg-success',
            'on-delivery': 'bg-warning',
            'offline': 'bg-secondary'
        };
        return classes[statusClass] || 'bg-secondary';
    }

    getCourierMarkerPreset(location) {
        const statusClass = this.getCourierStatusClass(location);
        const presets = {
            'online': 'islands#greenCircleDotIcon',
            'on-delivery': 'islands#yellowCircleDotIcon',
            'offline': 'islands#grayCircleDotIcon'
        };
        return presets[statusClass] || 'islands#grayCircleDotIcon';
    }

    getCourierMarkerColor(location) {
        const statusClass = this.getCourierStatusClass(location);
        const colors = {
            'online': '#28a745',
            'on-delivery': '#ffc107',
            'offline': '#6c757d'
        };
        return colors[statusClass] || '#6c757d';
    }

    getStatusClass(status) {
        const classes = {
            new: 'bg-info',
            waiting_delivery: 'bg-warning',
            in_delivery: 'bg-primary',
            delivered: 'bg-success',
            rejected: 'bg-danger'
        };
        return classes[status] || 'bg-secondary';
    }

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${this.getNotificationIcon(type)} me-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    window.dashboard = new CourierServiceDashboard();
});