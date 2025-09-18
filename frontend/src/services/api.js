// API сервис для взаимодействия с бэкендом
class ApiService {
    constructor() {
        this.baseURL = 'http://localhost:3000/api';
        this.token = localStorage.getItem('authToken');
    }

    // Установка токена авторизации
    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('authToken', token);
        } else {
            localStorage.removeItem('authToken');
        }
    }

    // Получение заголовков для запросов
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json'
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        return headers;
    }

    // Базовый метод для выполнения запросов
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            headers: this.getHeaders(),
            ...options
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    // GET запрос
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    // POST запрос
    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    // PUT запрос
    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    // DELETE запрос
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // Загрузка файлов
    async uploadFiles(endpoint, formData) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = {};

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('File upload failed:', error);
            throw error;
        }
    }

    // Аутентификация
    async login(username, password) {
        const response = await this.post('/auth/login', { username, password });
        if (response.token) {
            this.setToken(response.token);
        }
        return response;
    }

    async logout() {
        try {
            await this.post('/auth/logout');
        } finally {
            this.setToken(null);
        }
    }

    async getCurrentUser() {
        return this.get('/auth/me');
    }

    // Заявки
    async getRequests(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.get(`/requests${queryString ? '?' + queryString : ''}`);
    }

    async getRequest(id) {
        return this.get(`/requests/${id}`);
    }

    async createRequest(data) {
        return this.post('/requests', data);
    }

    async updateRequest(id, data) {
        return this.put(`/requests/${id}`, data);
    }

    async changeRequestStatus(id, status, additionalData = {}) {
        return this.put(`/requests/${id}/status`, { status, ...additionalData });
    }

    async assignCourier(requestId, courierId) {
        return this.put(`/requests/${requestId}/assign-courier`, { courier_id: courierId });
    }

    async deleteRequest(id) {
        return this.delete(`/requests/${id}`);
    }

    // Файлы
    async uploadFiles(requestId, files, category = 'other') {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('file_category', category);
        
        files.forEach(file => {
            formData.append('files', file);
        });

        return this.uploadFiles('/files/upload', formData);
    }

    async getRequestFiles(requestId) {
        return this.get(`/files/${requestId}`);
    }

    async downloadFile(fileId) {
        const url = `${this.baseURL}/files/download/${fileId}`;
        const headers = {};

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const response = await fetch(url, { headers });
        if (!response.ok) {
            throw new Error('File download failed');
        }

        return response.blob();
    }

    async deleteFile(fileId) {
        return this.delete(`/files/${fileId}`);
    }

    // Местоположение
    async updateLocation(latitude, longitude, accuracy) {
        return this.post('/location/update', { latitude, longitude, accuracy });
    }

    async getCouriersLocation(branchId = null) {
        const params = branchId ? { branch_id: branchId } : {};
        return this.get('/location/couriers', params);
    }

    async getCourierHistory(courierId, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.get(`/location/history/${courierId}${queryString ? '?' + queryString : ''}`);
    }

    async getCourierRoute(requestId) {
        return this.get(`/location/route/${requestId}`);
    }

    // Дашборд
    async getDashboardStats() {
        return this.get('/dashboard/stats');
    }

    async getRecentActivity(limit = 20) {
        return this.get(`/dashboard/recent-activity?limit=${limit}`);
    }

    async getPerformanceStats(period = 30) {
        return this.get(`/dashboard/performance?period=${period}`);
    }

    async getDashboardAlerts() {
        return this.get('/dashboard/alerts');
    }

    // Пользователи
    async getUsers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return this.get(`/users${queryString ? '?' + queryString : ''}`);
    }

    async getUser(id) {
        return this.get(`/users/${id}`);
    }

    async createUser(data) {
        return this.post('/users', data);
    }

    async updateUser(id, data) {
        return this.put(`/users/${id}`, data);
    }

    async deleteUser(id) {
        return this.delete(`/users/${id}`);
    }

    async getRoles() {
        return this.get('/users/roles/list');
    }

    async getCouriers(branchId = null) {
        const params = branchId ? { branch_id: branchId } : {};
        return this.get('/users/couriers/list', params);
    }

    // Настройки
    async getSettings() {
        return this.get('/settings');
    }

    async updateSetting(key, value, description) {
        return this.put(`/settings/${key}`, { value, description });
    }

    async getBranches() {
        return this.get('/settings/branches');
    }

    async createBranch(data) {
        return this.post('/settings/branches', data);
    }

    async updateBranch(id, data) {
        return this.put(`/settings/branches/${id}`, data);
    }

    async deleteBranch(id) {
        return this.delete(`/settings/branches/${id}`);
    }

    async getDepartments(branchId = null) {
        const params = branchId ? { branch_id: branchId } : {};
        return this.get('/settings/departments', params);
    }

    async createDepartment(data) {
        return this.post('/settings/departments', data);
    }

    async updateDepartment(id, data) {
        return this.put(`/settings/departments/${id}`, data);
    }

    async deleteDepartment(id) {
        return this.delete(`/settings/departments/${id}`);
    }

    async getCardTypes() {
        return this.get('/settings/card-types');
    }

    async createCardType(data) {
        return this.post('/settings/card-types', data);
    }

    async updateCardType(id, data) {
        return this.put(`/settings/card-types/${id}`, data);
    }

    async deleteCardType(id) {
        return this.delete(`/settings/card-types/${id}`);
    }
}

// Создание глобального экземпляра API сервиса
window.apiService = new ApiService();