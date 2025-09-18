const express = require('express');
const { query } = require('../config/database');
const { requireRole } = require('../middleware/auth');

const router = express.Router();

// GET /api/dashboard/stats - Получение общей статистики
router.get('/stats', async (req, res, next) => {
    try {
        // Построение условий фильтрации по филиалу
        let whereConditions = [];
        let queryParams = [];

        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Общее количество заявок
        const totalRequests = await query(`
            SELECT COUNT(*) as total
            FROM delivery_requests
            ${whereClause}
        `, queryParams);

        // Заявки по статусам
        const statusStats = await query(`
            SELECT 
                status,
                COUNT(*) as count
            FROM delivery_requests
            ${whereClause}
            GROUP BY status
        `, queryParams);

        // Заявки за последние 7 дней
        const weeklyStats = await query(`
            SELECT 
                DATE(registration_date) as date,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM delivery_requests
            WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ${whereClause ? 'AND ' + whereConditions.join(' AND ') : ''}
            GROUP BY DATE(registration_date)
            ORDER BY date DESC
        `, queryParams);

        // Статистика по курьерам
        const courierStats = await query(`
            SELECT 
                u.id,
                CONCAT(u.last_name, ' ', u.first_name, ' ', COALESCE(u.middle_name, '')) as name,
                COUNT(dr.id) as total_requests,
                SUM(CASE WHEN dr.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN dr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN dr.status = 'waiting_delivery' THEN 1 ELSE 0 END) as in_progress
            FROM users u
            LEFT JOIN delivery_requests dr ON u.id = dr.courier_id
            WHERE u.role_id = (SELECT id FROM roles WHERE name = 'courier')
            AND u.is_active = 1
            ${req.user.role === 'senior_courier' && req.user.branch_id ? 'AND u.branch_id = ?' : ''}
            GROUP BY u.id, u.last_name, u.first_name, u.middle_name
            ORDER BY delivered DESC, total_requests DESC
        `, req.user.role === 'senior_courier' && req.user.branch_id ? [req.user.branch_id] : []);

        // Форматирование статистики по статусам
        const statusCounts = {
            new: 0,
            waiting_delivery: 0,
            delivered: 0,
            rejected: 0,
            cancelled: 0
        };

        statusStats.forEach(stat => {
            statusCounts[stat.status] = stat.count;
        });

        // Форматирование недельной статистики
        const weeklyData = weeklyStats.map(stat => ({
            date: stat.date,
            total: stat.count,
            delivered: stat.delivered,
            rejected: stat.rejected
        }));

        // Форматирование статистики по курьерам
        const courierData = courierStats.map(courier => ({
            id: courier.id,
            name: courier.name.trim(),
            totalRequests: courier.total_requests,
            delivered: courier.delivered,
            rejected: courier.rejected,
            inProgress: courier.in_progress,
            successRate: courier.total_requests > 0 
                ? ((courier.delivered / courier.total_requests) * 100).toFixed(1)
                : 0
        }));

        res.json({
            stats: {
                total: totalRequests[0].total,
                byStatus: statusCounts,
                delivered: statusCounts.delivered,
                inProgress: statusCounts.waiting_delivery,
                rejected: statusCounts.rejected,
                new: statusCounts.new
            },
            weekly: weeklyData,
            couriers: courierData,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/dashboard/recent-activity - Получение последних активностей
router.get('/recent-activity', async (req, res, next) => {
    try {
        const { limit = 20 } = req.query;

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('dr.branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Получение последних изменений заявок
        const recentActivity = await query(`
            SELECT 
                rh.id,
                rh.action,
                rh.timestamp,
                rh.old_value,
                rh.new_value,
                dr.request_number,
                dr.client_name,
                dr.status,
                u.first_name,
                u.last_name,
                u.middle_name
            FROM request_history rh
            JOIN delivery_requests dr ON rh.request_id = dr.id
            JOIN users u ON rh.user_id = u.id
            ${whereClause}
            ORDER BY rh.timestamp DESC
            LIMIT ?
        `, [...queryParams, parseInt(limit)]);

        // Форматирование активности
        const formattedActivity = recentActivity.map(activity => {
            let actionText = '';
            let actionIcon = '';

            switch (activity.action) {
                case 'created':
                    actionText = 'Создана заявка';
                    actionIcon = 'plus';
                    break;
                case 'updated':
                    actionText = 'Обновлена заявка';
                    actionIcon = 'edit';
                    break;
                case 'status_changed':
                    actionText = 'Изменен статус';
                    actionIcon = 'sync';
                    break;
                case 'courier_assigned':
                    actionText = 'Назначен курьер';
                    actionIcon = 'user-plus';
                    break;
                default:
                    actionText = activity.action;
                    actionIcon = 'info';
            }

            return {
                id: activity.id,
                action: activity.action,
                actionText,
                actionIcon,
                timestamp: activity.timestamp,
                request: {
                    number: activity.request_number,
                    clientName: activity.client_name,
                    status: activity.status
                },
                user: {
                    name: `${activity.last_name} ${activity.first_name} ${activity.middle_name || ''}`.trim()
                },
                oldValue: activity.old_value,
                newValue: activity.new_value
            };
        });

        res.json({
            activities: formattedActivity,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/dashboard/performance - Получение показателей эффективности
router.get('/performance', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { period = '30' } = req.query; // период в днях

        // Построение условий фильтрации
        let whereConditions = [`registration_date >= DATE_SUB(NOW(), INTERVAL ${parseInt(period)} DAY)`];
        let queryParams = [];

        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        const whereClause = whereConditions.join(' AND ');

        // Общие показатели эффективности
        const performanceStats = await query(`
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'waiting_delivery' THEN 1 ELSE 0 END) as in_progress,
                AVG(CASE 
                    WHEN status = 'delivered' AND processing_date IS NOT NULL 
                    THEN TIMESTAMPDIFF(HOUR, registration_date, delivery_date)
                END) as avg_delivery_time_hours
            FROM delivery_requests
            WHERE ${whereClause}
        `, queryParams);

        // Показатели по дням недели
        const dayOfWeekStats = await query(`
            SELECT 
                DAYNAME(registration_date) as day_name,
                DAYOFWEEK(registration_date) as day_number,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM delivery_requests
            WHERE ${whereClause}
            GROUP BY DAYOFWEEK(registration_date), DAYNAME(registration_date)
            ORDER BY day_number
        `, queryParams);

        // Показатели по часам дня
        const hourStats = await query(`
            SELECT 
                HOUR(registration_date) as hour,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered
            FROM delivery_requests
            WHERE ${whereClause}
            GROUP BY HOUR(registration_date)
            ORDER BY hour
        `, queryParams);

        const stats = performanceStats[0];
        const successRate = stats.total_requests > 0 
            ? ((stats.delivered / stats.total_requests) * 100).toFixed(1)
            : 0;

        const rejectionRate = stats.total_requests > 0 
            ? ((stats.rejected / stats.total_requests) * 100).toFixed(1)
            : 0;

        res.json({
            period: `${period} дней`,
            stats: {
                totalRequests: stats.total_requests,
                delivered: stats.delivered,
                rejected: stats.rejected,
                inProgress: stats.in_progress,
                successRate: parseFloat(successRate),
                rejectionRate: parseFloat(rejectionRate),
                avgDeliveryTimeHours: stats.avg_delivery_time_hours 
                    ? parseFloat(stats.avg_delivery_time_hours.toFixed(1))
                    : null
            },
            byDayOfWeek: dayOfWeekStats.map(day => ({
                dayName: day.day_name,
                dayNumber: day.day_number,
                totalRequests: day.total_requests,
                delivered: day.delivered,
                rejected: day.rejected,
                successRate: day.total_requests > 0 
                    ? ((day.delivered / day.total_requests) * 100).toFixed(1)
                    : 0
            })),
            byHour: hourStats.map(hour => ({
                hour: hour.hour,
                totalRequests: hour.total_requests,
                delivered: hour.delivered,
                successRate: hour.total_requests > 0 
                    ? ((hour.delivered / hour.total_requests) * 100).toFixed(1)
                    : 0
            })),
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/dashboard/alerts - Получение уведомлений и предупреждений
router.get('/alerts', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const alerts = [];

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Заявки без назначенного курьера более 2 часов
        const unassignedRequests = await query(`
            SELECT COUNT(*) as count
            FROM delivery_requests
            WHERE courier_id IS NULL 
            AND status = 'new'
            AND registration_date < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ${whereClause}
        `, queryParams);

        if (unassignedRequests[0].count > 0) {
            alerts.push({
                type: 'warning',
                title: 'Неназначенные заявки',
                message: `${unassignedRequests[0].count} заявок ожидают назначения курьера более 2 часов`,
                icon: 'user-plus',
                action: 'assign_couriers'
            });
        }

        // Заявки в процессе доставки более 8 часов
        const longDeliveryRequests = await query(`
            SELECT COUNT(*) as count
            FROM delivery_requests
            WHERE status = 'waiting_delivery'
            AND processing_date < DATE_SUB(NOW(), INTERVAL 8 HOUR)
            ${whereClause}
        `, queryParams);

        if (longDeliveryRequests[0].count > 0) {
            alerts.push({
                type: 'warning',
                title: 'Долгая доставка',
                message: `${longDeliveryRequests[0].count} заявок в процессе доставки более 8 часов`,
                icon: 'clock',
                action: 'check_deliveries'
            });
        }

        // Курьеры без активности более 1 часа
        const inactiveCouriers = await query(`
            SELECT COUNT(DISTINCT u.id) as count
            FROM users u
            LEFT JOIN location_tracking lt ON u.id = lt.user_id 
                AND lt.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            WHERE u.role_id = (SELECT id FROM roles WHERE name = 'courier')
            AND u.is_active = 1
            AND lt.user_id IS NULL
            ${req.user.role === 'senior_courier' && req.user.branch_id ? 'AND u.branch_id = ?' : ''}
        `, req.user.role === 'senior_courier' && req.user.branch_id ? [req.user.branch_id] : []);

        if (inactiveCouriers[0].count > 0) {
            alerts.push({
                type: 'info',
                title: 'Неактивные курьеры',
                message: `${inactiveCouriers[0].count} курьеров не обновляли местоположение более 1 часа`,
                icon: 'user-times',
                action: 'check_couriers'
            });
        }

        // Высокий процент отказов за последние 24 часа
        const rejectionStats = await query(`
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM delivery_requests
            WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ${whereClause}
        `, queryParams);

        if (rejectionStats[0].total > 10) {
            const rejectionRate = (rejectionStats[0].rejected / rejectionStats[0].total) * 100;
            if (rejectionRate > 20) {
                alerts.push({
                    type: 'danger',
                    title: 'Высокий процент отказов',
                    message: `За последние 24 часа процент отказов составил ${rejectionRate.toFixed(1)}%`,
                    icon: 'exclamation-triangle',
                    action: 'analyze_rejections'
                });
            }
        }

        res.json({
            alerts,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;