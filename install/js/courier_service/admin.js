/**
 * JavaScript для административного интерфейса курьерской службы
 */

class CourierServiceAdmin {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.filters = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Фильтры
        document.getElementById('applyFilters')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('clearFilters')?.addEventListener('click', () => this.clearFilters());
        
        // Добавление заявки
        document.getElementById('addRequestBtn')?.addEventListener('click', () => this.showAddRequestModal());
        document.getElementById('saveRequestBtn')?.addEventListener('click', () => this.saveRequest());
        
        // Изменение статуса
        document.getElementById('newStatus')?.addEventListener('change', () => this.toggleStatusFields());
        document.getElementById('saveStatusBtn')?.addEventListener('click', () => this.saveStatus());
        
        // Экспорт
        document.getElementById('exportBtn')?.addEventListener('click', () => this.exportData());
        
        // Выбор всех элементов
        document.getElementById('selectAll')?.addEventListener('change', (e) => this.toggleSelectAll(e.target.checked));
    }

    async loadInitialData() {
        await Promise.all([
            this.loadBranches(),
            this.loadCouriers(),
            this.loadRequests()
        ]);
    }

    async loadBranches() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_branches');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('branchFilter');
                const modalSelect = document.querySelector('#addRequestModal select[name="branch_id"]');
                
                data.data.forEach(branch => {
                    const option = new Option(branch.name, branch.id);
                    const modalOption = new Option(branch.name, branch.id);
                    
                    select?.add(option);
                    modalSelect?.add(modalOption);
                });
            }
        } catch (error) {
            console.error('Error loading branches:', error);
        }
    }

    async loadCouriers() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_couriers');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('courierFilter');
                
                data.data.forEach(courier => {
                    const option = new Option(courier.name, courier.id);
                    select?.add(option);
                });
            }
        } catch (error) {
            console.error('Error loading couriers:', error);
        }
    }

    async loadRequests(page = 1) {
        try {
            this.showLoading();
            
            const params = new URLSearchParams({
                action: 'get_requests',
                page: page,
                limit: this.itemsPerPage,
                ...this.filters
            });

            const response = await fetch(`/bitrix/admin/courier_service_api.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderRequests(data.data.requests);
                this.renderPagination(data.data.pagination);
                this.currentPage = page;
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading requests:', error);
            this.showNotification('Ошибка загрузки данных', 'error');
        } finally {
            this.hideLoading();
        }
    }

    renderRequests(requests) {
        const tbody = document.getElementById('requestsTableBody');
        if (!tbody) return;

        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>Заявки не найдены</h4>
                            <p>Попробуйте изменить фильтры поиска</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = requests.map(request => `
            <tr>
                <td><input class="form-check-input request-checkbox" type="checkbox" value="${request.id}"></td>
                <td>#${request.request_number}</td>
                <td>${request.abs_id || '-'}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="courier-status ${this.getCourierStatusClass(request.courier_status)}"></span>
                        ${request.courier_name || 'Не назначен'}
                    </div>
                </td>
                <td>${this.formatDateTime(request.registration_date)}</td>
                <td>${request.client_name}</td>
                <td>${request.client_phone}</td>
                <td><span class="badge ${this.getStatusClass(request.status)} status-badge">${request.status_text}</span></td>
                <td>${request.branch_name || '-'}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary btn-action" onclick="courierAdmin.viewRequest(${request.id})" title="Просмотр">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success btn-action" onclick="courierAdmin.editRequest(${request.id})" title="Редактировать">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning btn-action" onclick="courierAdmin.changeStatus(${request.id}, '${request.status}')" title="Изменить статус">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info btn-action" onclick="courierAdmin.printContract(${request.id})" title="Печать договора">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderPagination(pagination) {
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationNav = document.getElementById('pagination');
        
        if (paginationInfo) {
            paginationInfo.textContent = `Показано ${((pagination.page - 1) * pagination.limit) + 1}-${Math.min(pagination.page * pagination.limit, pagination.total)} из ${pagination.total} заявок`;
        }

        if (paginationNav) {
            let paginationHTML = '';
            
            // Предыдущая страница
            paginationHTML += `
                <li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="courierAdmin.loadRequests(${pagination.page - 1})">Предыдущая</a>
                </li>
            `;
            
            // Страницы
            const startPage = Math.max(1, pagination.page - 2);
            const endPage = Math.min(pagination.pages, pagination.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <li class="page-item ${i === pagination.page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="courierAdmin.loadRequests(${i})">${i}</a>
                    </li>
                `;
            }
            
            // Следующая страница
            paginationHTML += `
                <li class="page-item ${pagination.page === pagination.pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="courierAdmin.loadRequests(${pagination.page + 1})">Следующая</a>
                </li>
            `;
            
            paginationNav.innerHTML = paginationHTML;
        }
    }

    applyFilters() {
        this.filters = {
            date_from: document.getElementById('dateFrom')?.value || '',
            date_to: document.getElementById('dateTo')?.value || '',
            status: document.getElementById('statusFilter')?.value || '',
            call_status: document.getElementById('callStatusFilter')?.value || '',
            card_type: document.getElementById('cardTypeFilter')?.value || '',
            branch_id: document.getElementById('branchFilter')?.value || '',
            courier_id: document.getElementById('courierFilter')?.value || '',
            client_name: document.getElementById('clientNameFilter')?.value || ''
        };

        // Удаляем пустые фильтры
        Object.keys(this.filters).forEach(key => {
            if (!this.filters[key]) {
                delete this.filters[key];
            }
        });

        this.loadRequests(1);
        this.showNotification('Фильтры применены', 'success');
    }

    clearFilters() {
        // Очищаем все поля фильтров
        document.querySelectorAll('.filters-panel input, .filters-panel select').forEach(element => {
            if (element.type === 'text' || element.type === 'tel') {
                element.value = '';
            } else if (element.tagName === 'SELECT') {
                element.selectedIndex = 0;
            }
        });

        this.filters = {};
        this.loadRequests(1);
        this.showNotification('Фильтры очищены', 'success');
    }

    showAddRequestModal() {
        const modal = new bootstrap.Modal(document.getElementById('addRequestModal'));
        modal.show();
    }

    async saveRequest() {
        const form = document.getElementById('addRequestForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create_request',
                    ...data
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Заявка создана успешно', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addRequestModal')).hide();
                form.reset();
                this.loadRequests(this.currentPage);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error saving request:', error);
            this.showNotification('Ошибка создания заявки', 'error');
        }
    }

    changeStatus(requestId, currentStatus) {
        document.getElementById('statusRequestId').value = requestId;
        document.getElementById('currentStatus').textContent = this.getStatusText(currentStatus);
        document.getElementById('newStatus').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    toggleStatusFields() {
        const newStatus = document.getElementById('newStatus').value;
        const deliveredFields = document.getElementById('deliveredFields');
        const rejectedFields = document.getElementById('rejectedFields');

        // Скрываем все дополнительные поля
        deliveredFields?.classList.add('d-none');
        rejectedFields?.classList.add('d-none');

        // Показываем нужные поля в зависимости от статуса
        if (newStatus === 'delivered') {
            deliveredFields?.classList.remove('d-none');
        } else if (newStatus === 'rejected') {
            rejectedFields?.classList.remove('d-none');
        }
    }

    async saveStatus() {
        const form = document.getElementById('statusForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_status',
                    ...data
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Статус обновлен успешно', 'success');
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                this.loadRequests(this.currentPage);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error updating status:', error);
            this.showNotification('Ошибка обновления статуса', 'error');
        }
    }

    async exportData() {
        try {
            const params = new URLSearchParams({
                action: 'export_requests',
                ...this.filters
            });

            const response = await fetch(`/bitrix/admin/courier_service_api.php?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `requests_export_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showNotification('Данные экспортированы', 'success');
            } else {
                this.showNotification('Ошибка экспорта данных', 'error');
            }
        } catch (error) {
            console.error('Error exporting data:', error);
            this.showNotification('Ошибка экспорта данных', 'error');
        }
    }

    viewRequest(requestId) {
        // Открываем модальное окно с детальной информацией о заявке
        window.open(`/bitrix/admin/courier_service_request_detail.php?id=${requestId}`, '_blank');
    }

    editRequest(requestId) {
        // Открываем модальное окно редактирования заявки
        window.open(`/bitrix/admin/courier_service_request_edit.php?id=${requestId}`, '_blank');
    }

    async printContract(requestId) {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'generate_contract',
                    request_id: requestId
                })
            });

            const result = await response.json();
            
            if (result.success) {
                window.open(result.data.download_url, '_blank');
                this.showNotification('Договор сгенерирован', 'success');
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error generating contract:', error);
            this.showNotification('Ошибка генерации договора', 'error');
        }
    }

    toggleSelectAll(checked) {
        document.querySelectorAll('.request-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
    }

    showLoading() {
        document.getElementById('requestsTableBody')?.classList.add('loading');
    }

    hideLoading() {
        document.getElementById('requestsTableBody')?.classList.remove('loading');
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

    getStatusText(status) {
        const texts = {
            new: 'Новая',
            waiting_delivery: 'Ожидает доставки',
            in_delivery: 'В доставке',
            delivered: 'Доставлено',
            rejected: 'Отказано'
        };
        return texts[status] || status;
    }

    getCourierStatusClass(status) {
        const classes = {
            online: 'online',
            offline: 'offline',
            on_delivery: 'on-delivery'
        };
        return classes[status] || 'offline';
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
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    window.courierAdmin = new CourierServiceAdmin();
});