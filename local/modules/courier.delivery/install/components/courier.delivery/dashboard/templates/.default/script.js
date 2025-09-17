/**
 * JavaScript для дашборда курьерской службы
 */

class CourierDashboard {
    constructor() {
        this.map = null;
        this.markers = [];
        this.refreshInterval = null;
        this.isFullscreen = false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startAutoRefresh();
        
        // Инициализация карты если доступна
        if (typeof ymaps !== 'undefined') {
            ymaps.ready(() => this.initMap());
        }
    }
    
    bindEvents() {
        // Обработчики событий для интерфейса
        document.addEventListener('click', this.handleGlobalClick.bind(this));
        
        // Обработчик изменения размера окна
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Обработчик видимости страницы
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }
    
    handleGlobalClick(event) {
        // Закрытие выпадающих меню при клике вне них
        if (!event.target.closest('.dashboard-notifications')) {
            const dropdown = document.getElementById('notificationsDropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }
        
        // Закрытие модальных окон при клике на фон
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    
    handleResize() {
        // Переинициализация карты при изменении размера
        if (this.map) {
            setTimeout(() => {
                this.map.container.fitToViewport();
            }, 100);
        }
    }
    
    handleVisibilityChange() {
        // Приостановка/возобновление обновлений при смене вкладки
        if (document.hidden) {
            this.stopAutoRefresh();
        } else {
            this.startAutoRefresh();
            this.refreshData();
        }
    }
    
    startAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        // Обновляем данные каждые 30 секунд
        this.refreshInterval = setInterval(() => {
            this.refreshData();
        }, 30000);
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    async refreshData() {
        try {
            const response = await fetch('/local/modules/courier.delivery/ajax/dashboard_data.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateStatistics(data.statistics);
                this.updateCouriers(data.couriers);
                this.updateRecentRequests(data.requests);
                this.updateMapMarkers(data.couriers);
                this.updateNotifications(data.notifications);
            }
        } catch (error) {
            console.error('Ошибка обновления данных дашборда:', error);
        }
    }
    
    updateStatistics(stats) {
        if (!stats) return;
        
        // Обновляем числовые значения в статистических карточках
        const statCards = document.querySelectorAll('.stat-card');
        
        statCards.forEach((card, index) => {
            const numberElement = card.querySelector('.stat-number');
            const changeElement = card.querySelector('.stat-change');
            
            if (numberElement && changeElement) {
                let totalValue, todayValue;
                
                switch (index) {
                    case 0: // Всего заявок
                        totalValue = stats.total.total;
                        todayValue = stats.today.total;
                        break;
                    case 1: // Доставлено
                        totalValue = stats.total.delivered;
                        todayValue = stats.today.delivered;
                        break;
                    case 2: // В процессе
                        totalValue = stats.total.in_delivery + stats.total.assigned;
                        todayValue = stats.today.in_delivery + stats.today.assigned;
                        break;
                    case 3: // Активных курьеров
                        totalValue = stats.couriers.online;
                        changeElement.textContent = `из ${stats.couriers.total} всего`;
                        break;
                }
                
                if (totalValue !== undefined) {
                    this.animateNumber(numberElement, totalValue);
                }
                
                if (todayValue !== undefined && index < 3) {
                    changeElement.textContent = `+${todayValue} сегодня`;
                }
            }
        });
    }
    
    animateNumber(element, targetValue) {
        const currentValue = parseInt(element.textContent) || 0;
        const increment = Math.ceil((targetValue - currentValue) / 10);
        
        if (increment === 0) return;
        
        const timer = setInterval(() => {
            const current = parseInt(element.textContent) || 0;
            const next = current + increment;
            
            if ((increment > 0 && next >= targetValue) || 
                (increment < 0 && next <= targetValue)) {
                element.textContent = targetValue;
                clearInterval(timer);
            } else {
                element.textContent = next;
            }
        }, 50);
    }
    
    updateCouriers(couriers) {
        if (!couriers) return;
        
        const courierGrid = document.querySelector('.couriers-grid');
        if (!courierGrid) return;
        
        courierGrid.innerHTML = '';
        
        couriers.forEach(courier => {
            const courierCard = this.createCourierCard(courier);
            courierGrid.appendChild(courierCard);
        });
    }
    
    createCourierCard(courier) {
        const card = document.createElement('div');
        card.className = 'courier-card';
        
        const lastUpdate = courier.LAST_LOCATION_UPDATE ? 
            new Date(courier.LAST_LOCATION_UPDATE).toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            }) : 'Неизвестно';
        
        card.innerHTML = `
            <div class="courier-status courier-status-${courier.STATUS.toLowerCase()}"></div>
            <div class="courier-info">
                <div class="courier-name">${this.escapeHtml(courier.FULL_NAME)}</div>
                <div class="courier-branch">${this.escapeHtml(courier.BRANCH_NAME || '')}</div>
                <div class="courier-last-update">Обновлено: ${lastUpdate}</div>
            </div>
            <div class="courier-actions">
                <button class="btn btn-sm" onclick="courierDashboard.showCourierOnMap(${courier.ID})" title="Показать на карте">
                    <i class="fas fa-location-arrow"></i>
                </button>
            </div>
        `;
        
        return card;
    }
    
    updateRecentRequests(requests) {
        if (!requests) return;
        
        const tbody = document.querySelector('.requests-table tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        requests.forEach(request => {
            const row = this.createRequestRow(request);
            tbody.appendChild(row);
        });
    }
    
    createRequestRow(request) {
        const row = document.createElement('tr');
        
        const courierInfo = request.COURIER_NAME ? 
            `<div class="courier-info-inline">
                <span class="courier-status-indicator courier-status-online"></span>
                ${this.escapeHtml(request.COURIER_NAME)}
            </div>` : 
            '<span class="text-muted">Не назначен</span>';
        
        row.innerHTML = `
            <td>
                <a href="/bitrix/admin/courier_delivery_request_edit.php?id=${request.ID}" class="request-link">
                    #${request.ID}
                </a>
            </td>
            <td>${this.escapeHtml(request.CLIENT_NAME)}</td>
            <td>${this.escapeHtml(request.CLIENT_PHONE)}</td>
            <td>
                <span class="status-badge status-${request.STATUS.toLowerCase()}">
                    ${this.escapeHtml(request.STATUS_TEXT || request.STATUS)}
                </span>
            </td>
            <td>${courierInfo}</td>
            <td>
                <span class="date-time">
                    ${request.CREATED_DATE_FORMATTED || request.CREATED_DATE}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline" onclick="courierDashboard.viewRequest(${request.ID})" title="Просмотр">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="courierDashboard.editRequest(${request.ID})" title="Редактировать">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        `;
        
        return row;
    }
    
    updateNotifications(notifications) {
        if (!notifications) return;
        
        const toggle = document.querySelector('.notifications-toggle');
        const dropdown = document.getElementById('notificationsDropdown');
        const countElement = document.querySelector('.notification-count');
        
        if (!toggle || !dropdown || !countElement) return;
        
        // Обновляем счетчик
        countElement.textContent = notifications.length;
        
        // Скрываем/показываем индикатор уведомлений
        toggle.style.display = notifications.length > 0 ? 'block' : 'none';
        
        // Обновляем содержимое выпадающего меню
        dropdown.innerHTML = '';
        
        notifications.forEach(notification => {
            const item = document.createElement('div');
            item.className = `notification-item notification-${notification.type}`;
            
            item.innerHTML = `
                <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                <div class="notification-text">${this.escapeHtml(notification.text)}</div>
                ${notification.url ? `<a href="${notification.url}" class="notification-link">Подробнее</a>` : ''}
            `;
            
            dropdown.appendChild(item);
        });
    }
    
    // Методы для работы с картой
    initMap() {
        const mapContainer = document.getElementById('courierMap');
        if (!mapContainer || !window.CourierDashboard.mapData) return;
        
        // Создаем карту
        this.map = new ymaps.Map(mapContainer, {
            center: [55.751574, 37.573856], // Москва по умолчанию
            zoom: 10,
            controls: ['zoomControl', 'searchControl', 'typeSelector', 'fullscreenControl']
        });
        
        // Добавляем маркеры курьеров
        this.updateMapMarkers(window.CourierDashboard.mapData.couriers);
        
        // Убираем плейсхолдер
        const placeholder = mapContainer.querySelector('.map-placeholder');
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }
    
    updateMapMarkers(couriers) {
        if (!this.map || !couriers) return;
        
        // Очищаем существующие маркеры
        this.clearMarkers();
        
        couriers.forEach(courier => {
            if (courier.lat && courier.lng) {
                const marker = this.createCourierMarker(courier);
                this.map.geoObjects.add(marker);
                this.markers.push(marker);
            }
        });
        
        // Подгоняем карту под все маркеры
        if (this.markers.length > 0) {
            this.map.setBounds(this.map.geoObjects.getBounds(), {
                checkZoomRange: true,
                zoomMargin: 50
            });
        }
    }
    
    createCourierMarker(courier) {
        const statusColors = {
            'ONLINE': '#28a745',
            'ON_DELIVERY': '#ffc107',
            'OFFLINE': '#6c757d',
            'BREAK': '#17a2b8'
        };
        
        const placemark = new ymaps.Placemark([courier.lat, courier.lng], {
            balloonContentHeader: courier.name,
            balloonContentBody: `Статус: ${courier.status}<br>Последнее обновление: ${new Date().toLocaleTimeString()}`,
            hintContent: courier.name
        }, {
            preset: 'islands#circleIcon',
            iconColor: statusColors[courier.status] || '#6c757d'
        });
        
        return placemark;
    }
    
    clearMarkers() {
        this.markers.forEach(marker => {
            this.map.geoObjects.remove(marker);
        });
        this.markers = [];
    }
    
    showCourierOnMap(courierId) {
        const courier = window.CourierDashboard.mapData.couriers.find(c => c.id === courierId);
        if (!courier || !courier.lat || !courier.lng || !this.map) return;
        
        // Центрируем карту на курьере
        this.map.setCenter([courier.lat, courier.lng], 15);
        
        // Находим маркер курьера и открываем балун
        const marker = this.markers.find(m => {
            const coords = m.geometry.getCoordinates();
            return coords[0] === courier.lat && coords[1] === courier.lng;
        });
        
        if (marker) {
            marker.balloon.open();
        }
        
        // Прокручиваем к карте
        document.getElementById('courierMap').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
    
    toggleFullscreenMap() {
        const mapContainer = document.getElementById('courierMap');
        if (!mapContainer) return;
        
        if (!this.isFullscreen) {
            mapContainer.requestFullscreen();
            this.isFullscreen = true;
        } else {
            document.exitFullscreen();
            this.isFullscreen = false;
        }
    }
    
    refreshMap() {
        if (!this.map) return;
        
        // Показываем индикатор загрузки
        const mapContainer = document.getElementById('courierMap');
        const loader = document.createElement('div');
        loader.className = 'loading';
        loader.innerHTML = '<div class="spinner"></div>';
        mapContainer.appendChild(loader);
        
        // Обновляем данные
        this.refreshData().then(() => {
            loader.remove();
        });
    }
    
    // Методы для работы с заявками
    async viewRequest(requestId) {
        try {
            const response = await fetch(`/local/modules/courier.delivery/ajax/get_request.php?id=${requestId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showModal('requestModal', data.html);
            } else {
                this.showError('Ошибка загрузки данных заявки');
            }
        } catch (error) {
            console.error('Ошибка загрузки заявки:', error);
            this.showError('Произошла ошибка при загрузке заявки');
        }
    }
    
    editRequest(requestId) {
        window.location.href = `/bitrix/admin/courier_delivery_request_edit.php?id=${requestId}`;
    }
    
    // Утилитарные методы
    showModal(modalId, content = '') {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        if (content) {
            const body = modal.querySelector('.modal-body');
            if (body) {
                body.innerHTML = content;
            }
        }
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    showError(message) {
        // Создаем временное уведомление об ошибке
        const notification = document.createElement('div');
        notification.className = 'notification error';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 3000;
            animation: slideIn 0.3s ease-out;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Удаляем через 5 секунд
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Глобальные функции для обратной совместимости
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }
}

function refreshMap() {
    if (window.courierDashboard) {
        window.courierDashboard.refreshMap();
    }
}

function toggleFullscreenMap() {
    if (window.courierDashboard) {
        window.courierDashboard.toggleFullscreenMap();
    }
}

function showCourierOnMap(courierId) {
    if (window.courierDashboard) {
        window.courierDashboard.showCourierOnMap(courierId);
    }
}

function viewRequest(requestId) {
    if (window.courierDashboard) {
        window.courierDashboard.viewRequest(requestId);
    }
}

function editRequest(requestId) {
    if (window.courierDashboard) {
        window.courierDashboard.editRequest(requestId);
    }
}

function closeModal(modalId) {
    if (window.courierDashboard) {
        window.courierDashboard.closeModal(modalId);
    }
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

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Создаем экземпляр дашборда
    window.courierDashboard = new CourierDashboard();
    
    // Запускаем обновление времени
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
});