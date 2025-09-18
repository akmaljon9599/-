/**
 * Основной JavaScript модуль приложения
 * Система управления курьерскими заявками
 */

class CourierApp {
    constructor() {
        this.API_BASE = '/backend/api';
        this.token = localStorage.getItem('auth_token');
        this.user = null;
        this.currentPage = 'dashboard';
        
        this.init();
    }

    async init() {
        // Проверяем авторизацию
        if (this.token) {
            try {
                await this.loadCurrentUser();
                this.showApp();
            } catch (error) {
                this.showLogin();
            }
        } else {
            this.showLogin();
        }

        this.setupEventListeners();
        this.startLocationTracking();
    }

    /**
     * Загрузить данные текущего пользователя
     */
    async loadCurrentUser() {
        const response = await this.apiRequest('GET', '/auth/me');
        if (response.success) {
            this.user = response.user;
            this.updateUserInterface();
        } else {
            throw new Error('Failed to load user');
        }
    }

    /**
     * Показать форму авторизации
     */
    showLogin() {
        document.body.innerHTML = `
            <div class="login-container">
                <div class="login-form">
                    <div class="text-center mb-4">
                        <i class="fas fa-box-open fa-3x text-primary mb-3"></i>
                        <h3>Курьерская служба</h3>
                        <p class="text-muted">Войдите в систему для продолжения</p>
                    </div>
                    
                    <form id="loginForm">
                        <div class="mb-3">
                            <label class="form-label">Логин или Email</label>
                            <input type="text" class="form-control" id="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Войти</button>
                    </form>
                    
                    <div id="loginError" class="alert alert-danger mt-3" style="display: none;"></div>
                </div>
            </div>
            
            <style>
                .login-container {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                .login-form {
                    background: white;
                    padding: 2rem;
                    border-radius: 10px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
            </style>
        `;

        document.getElementById('loginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
    }

    /**
     * Обработать авторизацию
     */
    async handleLogin() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('loginError');

        try {
            const response = await this.apiRequest('POST', '/auth/login', {
                username,
                password
            });

            if (response.success) {
                this.token = response.token;
                this.user = response.user;
                localStorage.setItem('auth_token', this.token);
                this.showApp();
            } else {
                errorDiv.textContent = response.error || 'Ошибка авторизации';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            errorDiv.textContent = 'Ошибка соединения с сервером';
            errorDiv.style.display = 'block';
        }
    }

    /**
     * Показать основное приложение
     */
    showApp() {
        // Загружаем основной интерфейс из существующего HTML
        fetch('/фронт тест.html')
            .then(response => response.text())
            .then(html => {
                // Извлекаем содержимое body
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const appContainer = doc.querySelector('.app-container');
                
                document.body.innerHTML = '';
                document.body.appendChild(appContainer);
                
                this.updateUserInterface();
                this.loadDashboard();
                this.setupNavigationListeners();
            });
    }

    /**
     * Обновить интерфейс под текущего пользователя
     */
    updateUserInterface() {
        if (!this.user) return;

        // Обновляем информацию о пользователе в шапке
        const userNameElement = document.querySelector('#userDropdown');
        if (userNameElement) {
            userNameElement.innerHTML = `
                <i class="fas fa-user-circle me-1"></i>${this.user.first_name} ${this.user.last_name}
            `;
        }

        const roleElement = document.querySelector('#roleDropdown');
        if (roleElement) {
            const roleNames = {
                'admin': 'Администратор',
                'senior_courier': 'Старший курьер',
                'courier': 'Курьер',
                'operator': 'Оператор'
            };
            roleElement.innerHTML = `
                <i class="fas fa-user me-1"></i>${roleNames[this.user.role] || this.user.role}
            `;
        }

        // Скрываем недоступные пункты меню
        this.updateMenuVisibility();
    }

    /**
     * Обновить видимость пунктов меню в зависимости от роли
     */
    updateMenuVisibility() {
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        
        menuItems.forEach(item => {
            const text = item.querySelector('.menu-text').textContent;
            let visible = true;

            switch (this.user.role) {
                case 'courier':
                    visible = ['Дашборд', 'Заявки'].includes(text);
                    break;
                case 'operator':
                    visible = ['Дашборд', 'Заявки', 'Отчеты'].includes(text);
                    break;
                case 'senior_courier':
                    visible = !['Настройки'].includes(text);
                    break;
                case 'admin':
                    visible = true;
                    break;
            }

            item.parentElement.style.display = visible ? 'block' : 'none';
        });
    }

    /**
     * Настроить обработчики навигации
     */
    setupNavigationListeners() {
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        
        menuItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Убираем активный класс у всех пунктов
                menuItems.forEach(i => i.classList.remove('active'));
                
                // Добавляем активный класс текущему пункту
                item.classList.add('active');
                
                const text = item.querySelector('.menu-text').textContent;
                this.navigateTo(text.toLowerCase());
            });
        });

        // Обработчик выхода
        const logoutBtn = document.querySelector('a[href="#"]:last-child');
        if (logoutBtn && logoutBtn.textContent.includes('Выйти')) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }
    }

    /**
     * Навигация по разделам
     */
    async navigateTo(section) {
        this.currentPage = section;
        
        switch (section) {
            case 'дашборд':
                await this.loadDashboard();
                break;
            case 'заявки':
                await this.loadRequests();
                break;
            case 'курьеры':
                await this.loadCouriers();
                break;
            case 'карта':
                await this.loadMap();
                break;
            case 'договоры':
                await this.loadContracts();
                break;
            case 'отчеты':
                await this.loadReports();
                break;
            case 'настройки':
                await this.loadSettings();
                break;
        }
    }

    /**
     * Загрузить дашборд
     */
    async loadDashboard() {
        try {
            const response = await this.apiRequest('GET', '/dashboard');
            
            if (response.success) {
                this.updateDashboardStats(response.data);
                this.updateCouriersMap(response.data.couriers);
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    }

    /**
     * Обновить статистику на дашборде
     */
    updateDashboardStats(data) {
        const stats = data.requests.total;
        
        const statCards = document.querySelectorAll('.stat-card');
        if (statCards.length >= 4) {
            statCards[0].querySelector('h3').textContent = stats.total;
            statCards[1].querySelector('h3').textContent = stats.delivered;
            statCards[2].querySelector('h3').textContent = stats.in_progress + stats.assigned;
            statCards[3].querySelector('h3').textContent = stats.rejected;
        }
    }

    /**
     * Загрузить заявки
     */
    async loadRequests() {
        try {
            const response = await this.apiRequest('GET', '/requests');
            
            if (response.success) {
                this.updateRequestsTable(response.data);
                this.updatePagination(response.pagination);
            }
        } catch (error) {
            console.error('Error loading requests:', error);
        }
    }

    /**
     * Обновить таблицу заявок
     */
    updateRequestsTable(requests) {
        const tbody = document.querySelector('.table tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        requests.forEach(request => {
            const row = document.createElement('tr');
            
            const statusBadge = this.getStatusBadge(request.status);
            const courierStatus = request.courier_online ? 'online' : 'offline';
            const courierName = request.courier_name || 'Не назначен';
            
            row.innerHTML = `
                <td><input class="form-check-input" type="checkbox"></td>
                <td>${request.request_number}</td>
                <td>${request.abs_id || '-'}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="courier-status ${courierStatus} me-2"></span>
                        ${courierName}
                    </div>
                </td>
                <td>${this.formatDateTime(request.created_at)}</td>
                <td>${request.client_full_name}</td>
                <td>${request.client_phone}</td>
                <td>${statusBadge}</td>
                <td>${request.branch_name}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="app.viewRequest(${request.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${this.canEditRequest() ? `
                            <button class="btn btn-sm btn-outline-success btn-action" onclick="app.editRequest(${request.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.canPrintContract() ? `
                            <button class="btn btn-sm btn-outline-info btn-action" onclick="app.printContract(${request.id})">
                                <i class="fas fa-print"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    /**
     * Получить badge для статуса
     */
    getStatusBadge(status) {
        const badges = {
            'new': '<span class="badge bg-info status-badge">Новая</span>',
            'assigned': '<span class="badge bg-warning status-badge">Назначена</span>',
            'in_progress': '<span class="badge bg-primary status-badge">В работе</span>',
            'delivered': '<span class="badge bg-success status-badge">Доставлено</span>',
            'rejected': '<span class="badge bg-danger status-badge">Отказано</span>',
            'cancelled': '<span class="badge bg-secondary status-badge">Отменена</span>'
        };
        
        return badges[status] || `<span class="badge bg-secondary status-badge">${status}</span>`;
    }

    /**
     * Проверить права на редактирование заявок
     */
    canEditRequest() {
        return ['admin', 'operator', 'senior_courier'].includes(this.user.role);
    }

    /**
     * Проверить права на печать договоров
     */
    canPrintContract() {
        return ['admin', 'senior_courier'].includes(this.user.role);
    }

    /**
     * Форматировать дату и время
     */
    formatDateTime(dateTime) {
        if (!dateTime) return '-';
        
        const date = new Date(dateTime);
        return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        // Обработчик для модального окна добавления заявки
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-bs-toggle="modal"]')) {
                const modalId = e.target.getAttribute('data-bs-target');
                if (modalId === '#addRequestModal') {
                    this.prepareAddRequestModal();
                }
            }
        });

        // Обработчик применения фильтров
        document.addEventListener('click', (e) => {
            if (e.target.matches('.filter-actions .btn-primary')) {
                this.applyFilters();
            }
        });

        // Обработчик очистки фильтров
        document.addEventListener('click', (e) => {
            if (e.target.matches('.filter-actions .btn-outline-secondary')) {
                this.clearFilters();
            }
        });
    }

    /**
     * Подготовить модальное окно добавления заявки
     */
    async prepareAddRequestModal() {
        // Здесь можно загрузить справочники для селектов
        // Например, список филиалов, подразделений, типов карт
    }

    /**
     * Применить фильтры
     */
    async applyFilters() {
        const filters = this.getFiltersFromForm();
        
        try {
            const response = await this.apiRequest('GET', '/requests', filters);
            
            if (response.success) {
                this.updateRequestsTable(response.data);
                this.updatePagination(response.pagination);
                this.showNotification('Фильтры применены успешно', 'success');
            }
        } catch (error) {
            this.showNotification('Ошибка применения фильтров', 'error');
        }
    }

    /**
     * Получить фильтры из формы
     */
    getFiltersFromForm() {
        const filters = {};
        const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
        
        filterInputs.forEach(input => {
            if (input.value && input.value !== 'Все') {
                filters[input.name || input.id] = input.value;
            }
        });
        
        return filters;
    }

    /**
     * Очистить фильтры
     */
    clearFilters() {
        const filterInputs = document.querySelectorAll('.filters-panel input, .filters-panel select');
        
        filterInputs.forEach(input => {
            if (input.type === 'text' || input.type === 'tel' || input.type === 'date') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });
        
        this.showNotification('Фильтры очищены', 'success');
        this.loadRequests(); // Перезагружаем данные без фильтров
    }

    /**
     * Начать отслеживание местоположения для курьеров
     */
    startLocationTracking() {
        if (this.user && this.user.role === 'courier' && navigator.geolocation) {
            // Отправляем местоположение каждые 60 секунд
            setInterval(() => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.sendLocation(position.coords);
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                );
            }, 60000);

            // Отправляем сразу при загрузке
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.sendLocation(position.coords);
                },
                (error) => {
                    console.error('Geolocation error:', error);
                }
            );
        }
    }

    /**
     * Отправить местоположение на сервер
     */
    async sendLocation(coords) {
        try {
            await this.apiRequest('POST', `/couriers/${this.user.id}/location`, {
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy: coords.accuracy,
                speed: coords.speed,
                heading: coords.heading
            });
        } catch (error) {
            console.error('Error sending location:', error);
        }
    }

    /**
     * Выйти из системы
     */
    async logout() {
        try {
            await this.apiRequest('POST', '/auth/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            localStorage.removeItem('auth_token');
            this.token = null;
            this.user = null;
            this.showLogin();
        }
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Выполнить API запрос
     */
    async apiRequest(method, endpoint, data = null) {
        const url = this.API_BASE + endpoint;
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        if (this.token) {
            options.headers['Authorization'] = `Bearer ${this.token}`;
        }

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        } else if (data && method === 'GET') {
            const params = new URLSearchParams(data);
            url += '?' + params.toString();
        }

        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }

    /**
     * Действия с заявками
     */
    async viewRequest(id) {
        // Реализация просмотра заявки
        console.log('View request:', id);
    }

    async editRequest(id) {
        // Реализация редактирования заявки
        console.log('Edit request:', id);
    }

    async printContract(id) {
        // Реализация печати договора
        console.log('Print contract:', id);
    }
}

// Инициализируем приложение
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new CourierApp();
});