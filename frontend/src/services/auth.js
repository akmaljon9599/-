// Сервис для управления аутентификацией
class AuthService {
    constructor() {
        this.currentUser = null;
        this.isAuthenticated = false;
        this.init();
    }

    // Инициализация сервиса
    async init() {
        const token = localStorage.getItem('authToken');
        if (token) {
            try {
                const response = await apiService.getCurrentUser();
                this.currentUser = response.user;
                this.isAuthenticated = true;
                this.updateUI();
            } catch (error) {
                console.error('Failed to get current user:', error);
                this.logout();
            }
        }
    }

    // Вход в систему
    async login(username, password) {
        try {
            const response = await apiService.login(username, password);
            this.currentUser = response.user;
            this.isAuthenticated = true;
            this.updateUI();
            this.showNotification('Успешный вход в систему', 'success');
            return response;
        } catch (error) {
            this.showNotification(error.message || 'Ошибка входа в систему', 'error');
            throw error;
        }
    }

    // Выход из системы
    async logout() {
        try {
            await apiService.logout();
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.currentUser = null;
            this.isAuthenticated = false;
            apiService.setToken(null);
            this.updateUI();
            this.showLoginModal();
        }
    }

    // Проверка прав доступа
    hasRole(role) {
        if (!this.isAuthenticated || !this.currentUser) {
            return false;
        }
        return this.currentUser.role.name === role;
    }

    hasAnyRole(roles) {
        if (!this.isAuthenticated || !this.currentUser) {
            return false;
        }
        return roles.includes(this.currentUser.role.name);
    }

    hasPermission(permission) {
        if (!this.isAuthenticated || !this.currentUser) {
            return false;
        }
        return this.currentUser.role.permissions.all || 
               this.currentUser.role.permissions[permission];
    }

    // Обновление UI в зависимости от роли
    updateUI() {
        this.updateUserMenu();
        this.updateSidebar();
        this.updatePageContent();
    }

    // Обновление меню пользователя
    updateUserMenu() {
        const userMenuButton = document.querySelector('#userDropdown');
        const roleDropdown = document.querySelector('#roleDropdown');
        
        if (userMenuButton && this.currentUser) {
            const userName = `${this.currentUser.firstName} ${this.currentUser.lastName}`;
            userMenuButton.innerHTML = `
                <i class="fas fa-user-circle me-1"></i>${userName}
            `;
        }

        if (roleDropdown && this.currentUser) {
            const roleName = this.getRoleDisplayName(this.currentUser.role.name);
            roleDropdown.innerHTML = `
                <i class="fas fa-user me-1"></i>${roleName}
            `;
        }
    }

    // Обновление боковой панели
    updateSidebar() {
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            const isVisible = this.isMenuItemVisible(href);
            
            if (isVisible) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Обновление содержимого страницы
    updatePageContent() {
        // Обновление заголовка страницы
        const pageTitle = document.querySelector('.navbar h5');
        if (pageTitle && this.currentUser) {
            const roleName = this.getRoleDisplayName(this.currentUser.role.name);
            pageTitle.textContent = `Панель ${roleName.toLowerCase()}`;
        }

        // Скрытие/показ элементов в зависимости от роли
        this.toggleElementsByRole();
    }

    // Проверка видимости пункта меню
    isMenuItemVisible(href) {
        if (!this.isAuthenticated) return false;

        const menuPermissions = {
            '#dashboard': ['admin', 'senior_courier'],
            '#requests': ['admin', 'senior_courier', 'courier', 'operator'],
            '#couriers': ['admin', 'senior_courier'],
            '#map': ['admin', 'senior_courier'],
            '#contracts': ['admin', 'senior_courier'],
            '#reports': ['admin', 'senior_courier', 'operator'],
            '#settings': ['admin']
        };

        const allowedRoles = menuPermissions[href];
        return allowedRoles ? allowedRoles.includes(this.currentUser.role.name) : true;
    }

    // Переключение элементов по роли
    toggleElementsByRole() {
        // Кнопки действий в зависимости от роли
        const addRequestBtn = document.querySelector('[data-bs-target="#addRequestModal"]');
        const exportBtn = document.querySelector('.btn-success');
        const settingsBtn = document.querySelector('a[href="#settings"]');

        if (addRequestBtn) {
            addRequestBtn.style.display = this.hasPermission('add_requests') ? 'inline-block' : 'none';
        }

        if (exportBtn) {
            exportBtn.style.display = this.hasPermission('export_data') ? 'inline-block' : 'none';
        }

        if (settingsBtn) {
            settingsBtn.style.display = this.hasRole('admin') ? 'flex' : 'none';
        }
    }

    // Получение отображаемого названия роли
    getRoleDisplayName(roleName) {
        const roleNames = {
            'admin': 'Администратор',
            'senior_courier': 'Старший курьер',
            'courier': 'Курьер',
            'operator': 'Оператор'
        };
        return roleNames[roleName] || roleName;
    }

    // Показ модального окна входа
    showLoginModal() {
        // Создание модального окна входа, если его нет
        let loginModal = document.getElementById('loginModal');
        if (!loginModal) {
            loginModal = this.createLoginModal();
            document.body.appendChild(loginModal);
        }

        // Показ модального окна
        const modal = new bootstrap.Modal(loginModal);
        modal.show();
    }

    // Создание модального окна входа
    createLoginModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'loginModal';
        modal.setAttribute('data-bs-backdrop', 'static');
        modal.setAttribute('data-bs-keyboard', 'false');
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Вход в систему</h5>
                    </div>
                    <div class="modal-body">
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">Имя пользователя</label>
                                <input type="text" class="form-control" id="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" class="form-control" id="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">Запомнить меня</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="loginBtn">Войти</button>
                    </div>
                </div>
            </div>
        `;

        // Обработчик входа
        const loginBtn = modal.querySelector('#loginBtn');
        const loginForm = modal.querySelector('#loginForm');

        loginBtn.addEventListener('click', async () => {
            const username = modal.querySelector('#username').value;
            const password = modal.querySelector('#password').value;

            if (!username || !password) {
                this.showNotification('Заполните все поля', 'warning');
                return;
            }

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Вход...';

            try {
                await this.login(username, password);
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
            } catch (error) {
                // Ошибка уже обработана в методе login
            } finally {
                loginBtn.disabled = false;
                loginBtn.innerHTML = 'Войти';
            }
        });

        // Обработчик Enter в форме
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            loginBtn.click();
        });

        return modal;
    }

    // Показ уведомлений
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

        // Удаление уведомления через 5 секунд
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    // Получение иконки для уведомления
    getNotificationIcon(type) {
        const icons = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }
}

// Создание глобального экземпляра сервиса аутентификации
window.authService = new AuthService();