// Основной JavaScript файл приложения
class CourierApp {
    constructor() {
        this.apiBaseUrl = '/api';
        this.token = localStorage.getItem('authToken');
        this.user = null;
        this.socket = null;
        this.currentPage = 'dashboard';
        
        this.init();
    }

    async init() {
        // Проверяем аутентификацию
        if (this.token) {
            try {
                await this.loadUserData();
                this.setupSocketConnection();
            } catch (error) {
                this.logout();
            }
        } else {
            this.showLoginForm();
        }

        this.setupEventListeners();
        this.setupNavigation();
    }

    // Аутентификация
    async login(username, password) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            });

            const data = await response.json();

            if (data.success) {
                this.token = data.data.token;
                this.user = data.data.user;
                localStorage.setItem('authToken', this.token);
                localStorage.setItem('userData', JSON.stringify(this.user));
                
                this.setupSocketConnection();
                this.showMainInterface();
                this.showNotification('Успешный вход в систему', 'success');
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            this.showNotification('Ошибка подключения к серверу', 'error');
        }
    }

    async logout() {
        try {
            if (this.token) {
                await fetch(`${this.apiBaseUrl}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`
                    }
                });
            }
        } catch (error) {
            console.error('Ошибка при выходе:', error);
        } finally {
            this.token = null;
            this.user = null;
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            
            if (this.socket) {
                this.socket.disconnect();
                this.socket = null;
            }
            
            this.showLoginForm();
        }
    }

    async loadUserData() {
        const response = await fetch(`${this.apiBaseUrl}/auth/me`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });

        const data = await response.json();

        if (data.success) {
            this.user = data.data;
            localStorage.setItem('userData', JSON.stringify(this.user));
        } else {
            throw new Error(data.message);
        }
    }

    // WebSocket соединение
    setupSocketConnection() {
        if (this.socket) {
            this.socket.disconnect();
        }

        this.socket = io({
            auth: {
                token: this.token
            }
        });

        this.socket.on('connect', () => {
            console.log('WebSocket подключен');
            
            // Если пользователь - курьер, присоединяемся к комнате
            if (this.user && this.user.role.name === 'courier') {
                this.socket.emit('join-courier-room', this.user.id);
                this.startLocationTracking();
            }
        });

        this.socket.on('disconnect', () => {
            console.log('WebSocket отключен');
        });

        this.socket.on('location-updated', (data) => {
            this.updateCourierLocation(data);
        });
    }

    // Отслеживание местоположения для курьеров
    startLocationTracking() {
        if (!navigator.geolocation) {
            this.showNotification('Геолокация не поддерживается браузером', 'warning');
            return;
        }

        const updateLocation = () => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const locationData = {
                        courierId: this.user.id,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };

                    this.socket.emit('location-update', locationData);
                },
                (error) => {
                    console.error('Ошибка получения геолокации:', error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        };

        // Обновляем местоположение каждые 60 секунд
        updateLocation();
        setInterval(updateLocation, 60000);
    }

    // Навигация
    setupNavigation() {
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        menuItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.getAttribute('data-page');
                if (page) {
                    this.navigateTo(page);
                }
            });
        });
    }

    navigateTo(page) {
        // Убираем активный класс со всех пунктов меню
        document.querySelectorAll('.sidebar-menu a').forEach(item => {
            item.classList.remove('active');
        });

        // Добавляем активный класс к текущему пункту
        const activeItem = document.querySelector(`[data-page="${page}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }

        this.currentPage = page;
        this.loadPageContent(page);
    }

    async loadPageContent(page) {
        const content = document.querySelector('.main-content');
        
        switch (page) {
            case 'dashboard':
                await this.loadDashboard();
                break;
            case 'requests':
                await this.loadRequests();
                break;
            case 'couriers':
                await this.loadCouriers();
                break;
            case 'map':
                await this.loadMap();
                break;
            case 'contracts':
                await this.loadContracts();
                break;
            case 'reports':
                await this.loadReports();
                break;
            case 'settings':
                await this.loadSettings();
                break;
        }
    }

    // Загрузка дашборда
    async loadDashboard() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/requests/stats`, {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });

            const data = await response.json();

            if (data.success) {
                this.updateDashboardStats(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки статистики:', error);
        }
    }

    updateDashboardStats(stats) {
        const statCards = document.querySelectorAll('.stat-card h3');
        if (statCards.length >= 4) {
            statCards[0].textContent = stats.total || 0;
            statCards[1].textContent = stats.delivered || 0;
            statCards[2].textContent = stats.inProgress || 0;
            statCards[3].textContent = stats.rejected || 0;
        }
    }

    // Загрузка заявок
    async loadRequests(filters = {}) {
        try {
            const queryParams = new URLSearchParams(filters);
            const response = await fetch(`${this.apiBaseUrl}/requests?${queryParams}`, {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });

            const data = await response.json();

            if (data.success) {
                this.renderRequestsTable(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки заявок:', error);
        }
    }

    renderRequestsTable(requests) {
        const tbody = document.querySelector('#requestsTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        requests.forEach(request => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input class="form-check-input" type="checkbox"></td>
                <td>${request.request_number}</td>
                <td>${request.abs_id || '-'}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="courier-status ${request.courier_status || 'offline'} me-2"></span>
                        ${request.courier_name || 'Не назначен'}
                    </div>
                </td>
                <td>${this.formatDate(request.registration_date)}</td>
                <td>${request.client_name}</td>
                <td>${request.client_phone}</td>
                <td><span class="badge bg-${this.getStatusColor(request.status_name)} status-badge">${request.status_name}</span></td>
                <td>${request.branch_name}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="app.viewRequest(${request.id})" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${this.canEditRequest(request) ? `
                            <button class="btn btn-sm btn-outline-success btn-action" onclick="app.editRequest(${request.id})" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.canChangeStatus(request) ? `
                            <button class="btn btn-sm btn-outline-warning btn-action" onclick="app.changeRequestStatus(${request.id})" title="Изменить статус">
                                <i class="fas fa-exchange-alt"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    // Проверка прав доступа
    canEditRequest(request) {
        if (!this.user) return false;
        
        const role = this.user.role.name;
        return role === 'admin' || 
               role === 'operator' || 
               (role === 'senior_courier' && this.user.branch_id === request.branch_id);
    }

    canChangeStatus(request) {
        if (!this.user) return false;
        
        const role = this.user.role.name;
        return role === 'admin' || 
               role === 'senior_courier' || 
               (role === 'courier' && this.user.id === request.courier_id);
    }

    // Утилиты
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ru-RU');
    }

    getStatusColor(status) {
        const colors = {
            'Новая': 'info',
            'Ожидает доставки': 'warning',
            'Доставлено': 'success',
            'Отказано': 'danger',
            'Отменено': 'secondary'
        };
        return colors[status] || 'secondary';
    }

    // Уведомления
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
        return icons[type] || 'fa-info-circle';
    }

    // Обработчики событий
    setupEventListeners() {
        // Обработка формы входа
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                this.login(username, password);
            });
        }

        // Обработка выхода
        const logoutBtn = document.querySelector('[data-action="logout"]');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                this.logout();
            });
        }

        // Обработка фильтров заявок
        const applyFiltersBtn = document.querySelector('.filter-actions .btn-primary');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.applyFilters();
            });
        }

        const clearFiltersBtn = document.querySelector('.filter-actions .btn-outline-secondary');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
    }

    // Фильтры заявок
    applyFilters() {
        const filters = {};
        const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
        
        filterInputs.forEach(input => {
            if (input.value && input.value !== 'Все') {
                filters[input.name || input.id] = input.value;
            }
        });

        this.loadRequests(filters);
        this.showNotification('Фильтры применены', 'success');
    }

    clearFilters() {
        const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
        filterInputs.forEach(input => {
            if (input.type === 'text' || input.type === 'tel') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });

        this.loadRequests();
        this.showNotification('Фильтры очищены', 'success');
    }

    // Отображение интерфейсов
    showLoginForm() {
        document.body.innerHTML = `
            <div class="login-container">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-box-open fa-3x mb-3"></i>
                        <h2>Курьерская служба</h2>
                        <p>Вход в систему</p>
                    </div>
                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <input type="text" class="form-control" id="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Войти</button>
                    </form>
                </div>
            </div>
        `;
    }

    showMainInterface() {
        // Загружаем основной интерфейс из существующего HTML
        window.location.reload();
    }
}

// Инициализация приложения
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new CourierApp();
});