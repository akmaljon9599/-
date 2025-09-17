/**
 * Основной JavaScript файл для курьерской службы
 */

// Глобальные переменные
window.CourierService = {
    map: null,
    couriers: [],
    refreshInterval: null,
    settings: {
        autoRefresh: true,
        refreshInterval: 30000, // 30 секунд
        mapCenter: [55.7558, 37.6176],
        mapZoom: 10
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    initNotifications();
    initAutoRefresh();
    initSignaturePad();
    initLocationTracking();
});

/**
 * Инициализация tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Инициализация системы уведомлений
 */
function initNotifications() {
    // Создаем контейнер для уведомлений
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1060;';
        document.body.appendChild(container);
    }
}

/**
 * Показ уведомления
 */
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notification-container');
    if (!container) return;

    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = 'margin-bottom: 10px; min-width: 300px;';
    
    const icon = getNotificationIcon(type);
    notification.innerHTML = `
        <i class="${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(notification);
    
    // Автоматическое удаление
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

/**
 * Получение иконки для уведомления
 */
function getNotificationIcon(type) {
    const icons = {
        'success': 'fas fa-check-circle',
        'danger': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    return icons[type] || icons['info'];
}

/**
 * Инициализация автообновления
 */
function initAutoRefresh() {
    if (CourierService.settings.autoRefresh) {
        CourierService.refreshInterval = setInterval(() => {
            refreshCourierData();
        }, CourierService.settings.refreshInterval);
    }
}

/**
 * Обновление данных курьеров
 */
function refreshCourierData() {
    fetch('/bitrix/components/courier_service/courier.map/ajax/get_couriers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                CourierService.couriers = data.couriers;
                updateCourierTable(data.couriers);
                updateCourierMap(data.couriers);
            }
        })
        .catch(error => {
            console.error('Ошибка обновления данных курьеров:', error);
        });
}

/**
 * Обновление таблицы курьеров
 */
function updateCourierTable(couriers) {
    const tbody = document.querySelector('.courier-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '';
    
    couriers.forEach(courier => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <span class="courier-status ${getCourierStatusClass(courier.status)} me-2"></span>
                    ${courier.name}
                </div>
            </td>
            <td>
                <span class="badge bg-${getCourierStatusBadge(courier.status)} status-badge">
                    ${getCourierStatusText(courier.status)}
                </span>
            </td>
            <td>${courier.branch}</td>
            <td>
                ${courier.latitude && courier.longitude ? 
                    `<a href="#" onclick="showOnMap(${courier.latitude}, ${courier.longitude})">
                        <i class="fas fa-map-marker-alt"></i> Показать на карте
                    </a>` : 
                    'Не доступно'
                }
            </td>
            <td>${courier.last_activity}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="trackCourier(${courier.id})">
                    <i class="fas fa-location-arrow"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

/**
 * Обновление карты курьеров
 */
function updateCourierMap(couriers) {
    if (!CourierService.map) return;

    // Очищаем старые маркеры
    CourierService.map.geoObjects.removeAll();

    // Добавляем новые маркеры
    couriers.forEach(courier => {
        if (courier.latitude && courier.longitude) {
            const marker = new ymaps.Placemark([courier.latitude, courier.longitude], {
                balloonContentHeader: courier.name,
                balloonContentBody: `Статус: ${getCourierStatusText(courier.status)}<br>Филиал: ${courier.branch}`,
                hintContent: courier.name
            }, {
                preset: 'islands#circleIcon',
                iconColor: getCourierStatusColor(courier.status)
            });
            CourierService.map.geoObjects.add(marker);
        }
    });
}

/**
 * Получение CSS класса статуса курьера
 */
function getCourierStatusClass(status) {
    const classes = {
        'active': 'online',
        'on_delivery': 'on-delivery',
        'inactive': 'offline'
    };
    return classes[status] || 'offline';
}

/**
 * Получение класса бейджа статуса курьера
 */
function getCourierStatusBadge(status) {
    const badges = {
        'active': 'success',
        'on_delivery': 'warning',
        'inactive': 'secondary'
    };
    return badges[status] || 'secondary';
}

/**
 * Получение текста статуса курьера
 */
function getCourierStatusText(status) {
    const texts = {
        'active': 'Активен',
        'on_delivery': 'На доставке',
        'inactive': 'Неактивен'
    };
    return texts[status] || status;
}

/**
 * Получение цвета маркера для статуса курьера
 */
function getCourierStatusColor(status) {
    const colors = {
        'active': '#28a745',
        'on_delivery': '#ffc107',
        'inactive': '#6c757d'
    };
    return colors[status] || '#6c757d';
}

/**
 * Показать на карте
 */
function showOnMap(lat, lon) {
    if (CourierService.map) {
        CourierService.map.setCenter([lat, lon], 15);
    }
}

/**
 * Отслеживание курьера
 */
function trackCourier(courierId) {
    // Здесь можно добавить логику отслеживания курьера
    showNotification('Функция отслеживания курьера в разработке', 'info');
}

/**
 * Инициализация поля для подписи
 */
function initSignaturePad() {
    const signaturePads = document.querySelectorAll('.signature-pad');
    
    signaturePads.forEach(pad => {
        pad.addEventListener('click', function() {
            openSignatureModal();
        });
    });
}

/**
 * Открытие модального окна для подписи
 */
function openSignatureModal() {
    const modal = new bootstrap.Modal(document.getElementById('signatureModal'));
    modal.show();
}

/**
 * Сохранение подписи
 */
function saveSignature() {
    const signatureData = document.getElementById('signatureData').value;
    const requestId = document.getElementById('requestId').value;
    
    if (!signatureData) {
        showNotification('Пожалуйста, поставьте подпись', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'save_signature');
    formData.append('request_id', requestId);
    formData.append('signature_data', signatureData);
    formData.append('signature_format', 'base64');
    formData.append('sessid', BX.bitrix_sessid());
    
    fetch('/bitrix/ajax/request_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Подпись сохранена успешно', 'success');
            bootstrap.Modal.getInstance(document.getElementById('signatureModal')).hide();
        } else {
            showNotification('Ошибка сохранения подписи: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка сохранения подписи', 'danger');
    });
}

/**
 * Инициализация отслеживания местоположения
 */
function initLocationTracking() {
    if (navigator.geolocation) {
        // Запрашиваем разрешение на отслеживание местоположения
        navigator.geolocation.getCurrentPosition(
            function(position) {
                console.log('Местоположение получено:', position.coords);
                // Здесь можно отправить координаты на сервер
            },
            function(error) {
                console.error('Ошибка получения местоположения:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    }
}

/**
 * Обновление статуса заявки
 */
function updateRequestStatus(requestId, newStatus) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('request_id', requestId);
    formData.append('status', newStatus);
    formData.append('sessid', BX.bitrix_sessid());
    
    // Добавляем дополнительные данные в зависимости от статуса
    if (newStatus === 'rejected') {
        const reason = document.getElementById('rejectionReason').value;
        formData.append('rejection_reason', reason);
    } else if (newStatus === 'delivered') {
        const phone = document.getElementById('courierPhone').value;
        formData.append('courier_phone', phone);
    }
    
    fetch('/bitrix/ajax/request_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Статус обновлен успешно', 'success');
            location.reload();
        } else {
            showNotification('Ошибка обновления статуса: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка обновления статуса', 'danger');
    });
}

/**
 * Назначение курьера
 */
function assignCourier(requestId, courierId) {
    const formData = new FormData();
    formData.append('action', 'assign_courier');
    formData.append('request_id', requestId);
    formData.append('courier_id', courierId);
    formData.append('sessid', BX.bitrix_sessid());
    
    fetch('/bitrix/ajax/request_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Курьер назначен успешно', 'success');
            location.reload();
        } else {
            showNotification('Ошибка назначения курьера: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка назначения курьера', 'danger');
    });
}

/**
 * Загрузка фотографий доставки
 */
function uploadDeliveryPhotos(requestId) {
    const fileInput = document.getElementById('deliveryPhotos');
    const files = fileInput.files;
    
    if (files.length === 0) {
        showNotification('Выберите фотографии для загрузки', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_photos');
    formData.append('request_id', requestId);
    formData.append('sessid', BX.bitrix_sessid());
    
    for (let i = 0; i < files.length; i++) {
        formData.append('photos[]', files[i]);
    }
    
    fetch('/bitrix/ajax/request_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Фотографии загружены успешно', 'success');
            location.reload();
        } else {
            showNotification('Ошибка загрузки фотографий: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка загрузки фотографий', 'danger');
    });
}

/**
 * Генерация договора
 */
function generateContract(requestId) {
    const formData = new FormData();
    formData.append('action', 'generate_contract');
    formData.append('request_id', requestId);
    formData.append('sessid', BX.bitrix_sessid());
    
    fetch('/bitrix/ajax/request_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Договор сгенерирован успешно', 'success');
            // Открываем договор в новом окне
            window.open(data.contract_url, '_blank');
        } else {
            showNotification('Ошибка генерации договора: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showNotification('Ошибка генерации договора', 'danger');
    });
}

/**
 * Экспорт данных в Excel
 */
function exportToExcel() {
    const filters = getCurrentFilters();
    const url = new URL(window.location);
    url.searchParams.set('action', 'export');
    
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            url.searchParams.set(`filter[${key}]`, filters[key]);
        }
    });
    
    window.location.href = url.toString();
}

/**
 * Получение текущих фильтров
 */
function getCurrentFilters() {
    const filters = {};
    const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
    
    filterInputs.forEach(input => {
        if (input.value) {
            filters[input.name] = input.value;
        }
    });
    
    return filters;
}

/**
 * Очистка фильтров
 */
function clearFilters() {
    const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
    
    filterInputs.forEach(input => {
        if (input.type === 'text' || input.type === 'tel' || input.type === 'date') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    
    showNotification('Фильтры очищены', 'success');
}

/**
 * Применение фильтров
 */
function applyFilters() {
    const filters = getCurrentFilters();
    const url = new URL(window.location);
    
    // Очищаем старые параметры фильтра
    Array.from(url.searchParams.keys()).forEach(key => {
        if (key.startsWith('filter[')) {
            url.searchParams.delete(key);
        }
    });
    
    // Добавляем новые фильтры
    Object.keys(filters).forEach(key => {
        url.searchParams.set(key, filters[key]);
    });
    
    window.location.href = url.toString();
}

/**
 * Очистка ресурсов при выгрузке страницы
 */
window.addEventListener('beforeunload', function() {
    if (CourierService.refreshInterval) {
        clearInterval(CourierService.refreshInterval);
    }
});