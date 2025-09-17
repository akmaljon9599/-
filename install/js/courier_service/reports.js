/**
 * JavaScript для страницы отчетов курьерской службы
 */

class CourierServiceReports {
    constructor() {
        this.statusChart = null;
        this.couriersChart = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStatistics();
        this.loadLogs();
        this.initCharts();
    }

    bindEvents() {
        document.getElementById('generateReport')?.addEventListener('click', () => this.generateReport());
        document.getElementById('exportReport')?.addEventListener('click', () => this.exportReport());
        document.getElementById('refreshReport')?.addEventListener('click', () => this.refreshReport());
        document.getElementById('clearLogs')?.addEventListener('click', () => this.clearOldLogs());
    }

    async loadStatistics() {
        try {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            const response = await fetch(`/bitrix/admin/courier_service_api.php?action=get_statistics&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('totalRequests').textContent = data.data.total;
                document.getElementById('deliveredRequests').textContent = data.data.delivered;
                
                const successRate = data.data.total > 0 ? Math.round((data.data.delivered / data.data.total) * 100) : 0;
                document.getElementById('successRate').textContent = successRate + '%';
                
                // Здесь можно добавить расчет среднего времени доставки
                document.getElementById('avgDeliveryTime').textContent = '2.5 ч';
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    async loadLogs() {
        try {
            const response = await fetch('/bitrix/admin/courier_service_api.php?action=get_logs&limit=50');
            const data = await response.json();
            
            if (data.success) {
                this.renderLogs(data.data.logs);
            }
        } catch (error) {
            console.error('Error loading logs:', error);
        }
    }

    renderLogs(logs) {
        const tbody = document.getElementById('logsTableBody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-list"></i>
                            <h4>Логи не найдены</h4>
                            <p>Нет записей в логах системы</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = logs.map(log => `
            <tr>
                <td>${this.formatDateTime(log.created_at)}</td>
                <td>Пользователь #${log.user_id}</td>
                <td><span class="badge bg-info">${log.action_text}</span></td>
                <td>${log.entity_type_text} #${log.entity_id}</td>
                <td>${log.ip_address}</td>
            </tr>
        `).join('');
    }

    async generateReport() {
        const reportType = document.getElementById('reportType').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        try {
            this.showLoading();
            
            const response = await fetch('/bitrix/admin/courier_service_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'generate_report',
                    report_type: reportType,
                    date_from: dateFrom,
                    date_to: dateTo
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.renderReport(data.data);
                this.updateCharts(data.data);
                this.showNotification('Отчет сформирован', 'success');
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error generating report:', error);
            this.showNotification('Ошибка формирования отчета', 'error');
        } finally {
            this.hideLoading();
        }
    }

    renderReport(reportData) {
        const header = document.getElementById('reportsTableHeader');
        const tbody = document.getElementById('reportsTableBody');
        
        if (!header || !tbody) return;

        // Очищаем таблицу
        header.innerHTML = '';
        tbody.innerHTML = '';

        if (reportData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="100%" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h4>Данные не найдены</h4>
                            <p>Нет данных для выбранного периода</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        // Создаем заголовки таблицы
        const headers = Object.keys(reportData[0]);
        headers.forEach(headerText => {
            const th = document.createElement('th');
            th.textContent = this.translateHeader(headerText);
            header.appendChild(th);
        });

        // Заполняем данными
        tbody.innerHTML = reportData.map(row => `
            <tr>
                ${headers.map(header => `<td>${row[header] || '-'}</td>`).join('')}
            </tr>
        `).join('');
    }

    translateHeader(header) {
        const translations = {
            'id': 'ID',
            'request_number': 'Номер заявки',
            'client_name': 'Клиент',
            'client_phone': 'Телефон',
            'status': 'Статус',
            'courier_name': 'Курьер',
            'branch_name': 'Филиал',
            'registration_date': 'Дата регистрации',
            'delivery_date': 'Дата доставки',
            'total_requests': 'Всего заявок',
            'delivered_requests': 'Доставлено',
            'rejected_requests': 'Отказано',
            'success_rate': 'Процент успеха',
            'avg_delivery_time': 'Среднее время доставки'
        };
        return translations[header] || header;
    }

    updateCharts(reportData) {
        this.updateStatusChart(reportData);
        this.updateCouriersChart(reportData);
    }

    updateStatusChart(reportData) {
        if (!this.statusChart) return;

        // Подсчитываем статистику по статусам
        const statusCounts = {};
        reportData.forEach(row => {
            const status = row.status || 'unknown';
            statusCounts[status] = (statusCounts[status] || 0) + 1;
        });

        const labels = Object.keys(statusCounts).map(status => this.getStatusText(status));
        const data = Object.values(statusCounts);
        const colors = Object.keys(statusCounts).map(status => this.getStatusColor(status));

        this.statusChart.data.labels = labels;
        this.statusChart.data.datasets[0].data = data;
        this.statusChart.data.datasets[0].backgroundColor = colors;
        this.statusChart.update();
    }

    updateCouriersChart(reportData) {
        if (!this.couriersChart) return;

        // Подсчитываем статистику по курьерам
        const courierStats = {};
        reportData.forEach(row => {
            const courier = row.courier_name || 'Не назначен';
            if (!courierStats[courier]) {
                courierStats[courier] = { total: 0, delivered: 0 };
            }
            courierStats[courier].total++;
            if (row.status === 'delivered') {
                courierStats[courier].delivered++;
            }
        });

        const labels = Object.keys(courierStats);
        const deliveredData = labels.map(courier => courierStats[courier].delivered);
        const totalData = labels.map(courier => courierStats[courier].total);

        this.couriersChart.data.labels = labels;
        this.couriersChart.data.datasets[0].data = deliveredData;
        this.couriersChart.data.datasets[1].data = totalData;
        this.couriersChart.update();
    }

    initCharts() {
        // Инициализируем график статусов
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            this.statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: []
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Инициализируем график курьеров
        const couriersCtx = document.getElementById('couriersChart');
        if (couriersCtx) {
            this.couriersChart = new Chart(couriersCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Доставлено',
                        data: [],
                        backgroundColor: '#28a745'
                    }, {
                        label: 'Всего заявок',
                        data: [],
                        backgroundColor: '#6c757d'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    async exportReport() {
        const reportType = document.getElementById('reportType').value;
        const exportFormat = document.getElementById('exportFormat').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        try {
            const params = new URLSearchParams({
                action: 'export_report',
                report_type: reportType,
                format: exportFormat,
                date_from: dateFrom,
                date_to: dateTo
            });

            const response = await fetch(`/bitrix/admin/courier_service_api.php?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `report_${reportType}_${dateFrom}_${dateTo}.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showNotification('Отчет экспортирован', 'success');
            } else {
                this.showNotification('Ошибка экспорта отчета', 'error');
            }
        } catch (error) {
            console.error('Error exporting report:', error);
            this.showNotification('Ошибка экспорта отчета', 'error');
        }
    }

    async refreshReport() {
        await this.generateReport();
        await this.loadStatistics();
        await this.loadLogs();
        this.showNotification('Отчет обновлен', 'success');
    }

    async clearOldLogs() {
        if (confirm('Вы уверены, что хотите очистить старые логи (старше 90 дней)?')) {
            try {
                const response = await fetch('/bitrix/admin/courier_service_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_old_logs'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showNotification(`Удалено ${data.data.deleted_count} записей`, 'success');
                    this.loadLogs();
                } else {
                    this.showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error clearing logs:', error);
                this.showNotification('Ошибка очистки логов', 'error');
            }
        }
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

    getStatusColor(status) {
        const colors = {
            new: '#17a2b8',
            waiting_delivery: '#ffc107',
            in_delivery: '#007bff',
            delivered: '#28a745',
            rejected: '#dc3545'
        };
        return colors[status] || '#6c757d';
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

    showLoading() {
        document.getElementById('reportsTableBody').innerHTML = `
            <tr>
                <td colspan="100%" class="text-center py-4">
                    <div class="spinner"></div>
                    <p>Формирование отчета...</p>
                </td>
            </tr>
        `;
    }

    hideLoading() {
        // Загрузка скрывается при обновлении данных
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
    window.reports = new CourierServiceReports();
});