const express = require('express');
const ExcelJS = require('exceljs');
const { query } = require('../../config/database');
const { requirePermission } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Экспорт заявок в Excel
router.get('/requests/export', requirePermission('export_data'), async (req, res, next) => {
    try {
        const {
            date_from,
            date_to,
            status_id,
            branch_id,
            department_id,
            courier_id,
            operator_id
        } = req.query;

        let whereConditions = [];
        let queryParams = [];

        // Фильтрация по правам доступа
        if (req.user.role_name === 'senior_courier') {
            whereConditions.push('(r.branch_id = ? OR r.department_id = ?)');
            queryParams.push(req.user.branch_id, req.user.department_id);
        } else if (req.user.role_name === 'courier') {
            whereConditions.push('r.courier_id = ?');
            queryParams.push(req.user.id);
        }

        // Применение фильтров
        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        if (status_id) {
            whereConditions.push('r.status_id = ?');
            queryParams.push(status_id);
        }

        if (branch_id) {
            whereConditions.push('r.branch_id = ?');
            queryParams.push(branch_id);
        }

        if (department_id) {
            whereConditions.push('r.department_id = ?');
            queryParams.push(department_id);
        }

        if (courier_id) {
            whereConditions.push('r.courier_id = ?');
            queryParams.push(courier_id);
        }

        if (operator_id) {
            whereConditions.push('r.operator_id = ?');
            queryParams.push(operator_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const requests = await query(`
            SELECT 
                r.request_number,
                r.abs_id,
                r.client_name,
                r.client_phone,
                r.pan,
                r.delivery_address,
                rs.name as status_name,
                cs.name as call_status_name,
                ct.name as card_type_name,
                CONCAT(u.first_name, ' ', u.last_name) as courier_name,
                CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                b.name as branch_name,
                d.name as department_name,
                r.registration_date,
                r.processing_date,
                r.delivery_date,
                r.rejection_reason,
                r.courier_phone,
                r.notes
            FROM requests r
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN call_statuses cs ON r.call_status_id = cs.id
            LEFT JOIN card_types ct ON r.card_type_id = ct.id
            LEFT JOIN users u ON r.courier_id = u.id
            LEFT JOIN users op ON r.operator_id = op.id
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN departments d ON r.department_id = d.id
            ${whereClause}
            ORDER BY r.registration_date DESC
        `, queryParams);

        // Создание Excel файла
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet('Заявки');

        // Настройка колонок
        worksheet.columns = [
            { header: 'Номер заявки', key: 'request_number', width: 15 },
            { header: 'ID АБС', key: 'abs_id', width: 15 },
            { header: 'ФИО клиента', key: 'client_name', width: 25 },
            { header: 'Телефон клиента', key: 'client_phone', width: 18 },
            { header: 'PAN', key: 'pan', width: 20 },
            { header: 'Адрес доставки', key: 'delivery_address', width: 40 },
            { header: 'Статус', key: 'status_name', width: 15 },
            { header: 'Статус звонка', key: 'call_status_name', width: 15 },
            { header: 'Тип карты', key: 'card_type_name', width: 15 },
            { header: 'Курьер', key: 'courier_name', width: 20 },
            { header: 'Оператор', key: 'operator_name', width: 20 },
            { header: 'Филиал', key: 'branch_name', width: 20 },
            { header: 'Подразделение', key: 'department_name', width: 20 },
            { header: 'Дата регистрации', key: 'registration_date', width: 20 },
            { header: 'Дата обработки', key: 'processing_date', width: 20 },
            { header: 'Дата доставки', key: 'delivery_date', width: 20 },
            { header: 'Причина отказа', key: 'rejection_reason', width: 30 },
            { header: 'Телефон курьера', key: 'courier_phone', width: 18 },
            { header: 'Примечания', key: 'notes', width: 30 }
        ];

        // Стилизация заголовков
        worksheet.getRow(1).font = { bold: true };
        worksheet.getRow(1).fill = {
            type: 'pattern',
            pattern: 'solid',
            fgColor: { argb: 'FFE0E0E0' }
        };

        // Добавление данных
        requests.forEach(request => {
            worksheet.addRow({
                ...request,
                registration_date: request.registration_date ? new Date(request.registration_date).toLocaleString('ru-RU') : '',
                processing_date: request.processing_date ? new Date(request.processing_date).toLocaleString('ru-RU') : '',
                delivery_date: request.delivery_date ? new Date(request.delivery_date).toLocaleString('ru-RU') : ''
            });
        });

        // Автофильтр
        worksheet.autoFilter = 'A1:S1';

        // Логирование экспорта
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'export_requests', 'report',
                JSON.stringify({ 
                    filters: { date_from, date_to, status_id, branch_id, department_id, courier_id, operator_id },
                    records_count: requests.length 
                }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Экспорт заявок в Excel', {
            userId: req.user.id,
            recordsCount: requests.length,
            filters: { date_from, date_to, status_id, branch_id, department_id, courier_id, operator_id }
        });

        // Отправка файла
        res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        res.setHeader('Content-Disposition', `attachment; filename="requests_${new Date().toISOString().split('T')[0]}.xlsx"`);

        await workbook.xlsx.write(res);
        res.end();

    } catch (error) {
        next(error);
    }
});

// Отчет по курьерам
router.get('/couriers/performance', requirePermission('view_reports'), async (req, res, next) => {
    try {
        const { date_from, date_to, branch_id, department_id } = req.query;

        let whereConditions = ['r.courier_id IS NOT NULL'];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        if (branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(branch_id);
        }

        if (department_id) {
            whereConditions.push('u.department_id = ?');
            queryParams.push(department_id);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const courierStats = await query(`
            SELECT 
                u.id as courier_id,
                CONCAT(u.first_name, ' ', u.last_name) as courier_name,
                u.phone as courier_phone,
                b.name as branch_name,
                d.name as department_name,
                COUNT(r.id) as total_requests,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN r.status_id = 4 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress,
                ROUND(
                    (SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(r.id)), 2
                ) as success_rate,
                AVG(
                    CASE 
                        WHEN r.delivery_date IS NOT NULL AND r.registration_date IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date)
                        ELSE NULL 
                    END
                ) as avg_delivery_time_hours
            FROM users u
            LEFT JOIN roles r_role ON u.role_id = r_role.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN requests r ON u.id = r.courier_id
            ${whereClause}
            AND r_role.name = 'courier'
            GROUP BY u.id, u.first_name, u.last_name, u.phone, b.name, d.name
            ORDER BY success_rate DESC, total_requests DESC
        `, queryParams);

        res.json({
            success: true,
            data: courierStats
        });

    } catch (error) {
        next(error);
    }
});

// Отчет по филиалам
router.get('/branches/performance', requirePermission('view_reports'), async (req, res, next) => {
    try {
        const { date_from, date_to } = req.query;

        let whereConditions = [];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const branchStats = await query(`
            SELECT 
                b.id as branch_id,
                b.name as branch_name,
                b.address as branch_address,
                b.manager_name,
                COUNT(r.id) as total_requests,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN r.status_id = 4 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress,
                ROUND(
                    (SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(r.id)), 2
                ) as success_rate,
                COUNT(DISTINCT r.courier_id) as active_couriers,
                COUNT(DISTINCT r.operator_id) as active_operators,
                COUNT(DISTINCT d.id) as departments_count
            FROM branches b
            LEFT JOIN requests r ON b.id = r.branch_id
            LEFT JOIN departments d ON b.id = d.branch_id
            ${whereClause}
            GROUP BY b.id, b.name, b.address, b.manager_name
            ORDER BY success_rate DESC, total_requests DESC
        `, queryParams);

        res.json({
            success: true,
            data: branchStats
        });

    } catch (error) {
        next(error);
    }
});

// Отчет по времени доставки
router.get('/delivery/timing', requirePermission('view_reports'), async (req, res, next) => {
    try {
        const { date_from, date_to, branch_id } = req.query;

        let whereConditions = ['r.status_id = 3', 'r.delivery_date IS NOT NULL'];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        if (branch_id) {
            whereConditions.push('r.branch_id = ?');
            queryParams.push(branch_id);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const timingStats = await query(`
            SELECT 
                DATE(r.registration_date) as delivery_date,
                COUNT(r.id) as total_deliveries,
                AVG(TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date)) as avg_delivery_time_hours,
                MIN(TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date)) as min_delivery_time_hours,
                MAX(TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date)) as max_delivery_time_hours,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date) <= 24 THEN 1 END) as delivered_within_24h,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date) <= 48 THEN 1 END) as delivered_within_48h
            FROM requests r
            ${whereClause}
            GROUP BY DATE(r.registration_date)
            ORDER BY delivery_date DESC
        `, queryParams);

        res.json({
            success: true,
            data: timingStats
        });

    } catch (error) {
        next(error);
    }
});

// Общая статистика системы
router.get('/system/overview', requirePermission('view_reports'), async (req, res, next) => {
    try {
        const { date_from, date_to } = req.query;

        let whereConditions = [];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const systemStats = await query(`
            SELECT 
                COUNT(r.id) as total_requests,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN r.status_id = 4 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress,
                ROUND(
                    (SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) * 100.0 / COUNT(r.id)), 2
                ) as success_rate,
                COUNT(DISTINCT r.courier_id) as active_couriers,
                COUNT(DISTINCT r.operator_id) as active_operators,
                COUNT(DISTINCT r.branch_id) as active_branches,
                AVG(
                    CASE 
                        WHEN r.delivery_date IS NOT NULL AND r.registration_date IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, r.registration_date, r.delivery_date)
                        ELSE NULL 
                    END
                ) as avg_delivery_time_hours
            FROM requests r
            ${whereClause}
        `, queryParams);

        const dailyStats = await query(`
            SELECT 
                DATE(r.registration_date) as date,
                COUNT(r.id) as requests_count,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered_count
            FROM requests r
            ${whereClause}
            GROUP BY DATE(r.registration_date)
            ORDER BY date DESC
            LIMIT 30
        `, queryParams);

        res.json({
            success: true,
            data: {
                overview: systemStats[0],
                daily_stats: dailyStats
            }
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;