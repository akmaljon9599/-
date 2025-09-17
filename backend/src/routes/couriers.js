const express = require('express');
const { query } = require('../../config/database');
const { requirePermission } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Получение списка курьеров
router.get('/', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { branch_id, department_id, status } = req.query;

        let whereConditions = ['r.name = "courier"', 'u.is_active = TRUE'];
        let queryParams = [];

        // Фильтрация по филиалу
        if (branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(branch_id);
        }

        // Фильтрация по подразделению
        if (department_id) {
            whereConditions.push('u.department_id = ?');
            queryParams.push(department_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const couriers = await query(`
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.middle_name,
                u.phone,
                u.last_login,
                b.name as branch_name,
                d.name as department_name,
                ca.activity_type as current_activity,
                ca.start_time as activity_start_time,
                cl.latitude as last_latitude,
                cl.longitude as last_longitude,
                cl.timestamp as last_location_time
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN courier_activities ca ON u.id = ca.courier_id AND ca.end_time IS NULL
            LEFT JOIN courier_locations cl ON u.id = cl.courier_id
            ${whereClause}
            ORDER BY u.last_name, u.first_name
        `, queryParams);

        // Получаем последнее местоположение для каждого курьера
        const couriersWithLocation = await Promise.all(
            couriers.map(async (courier) => {
                const lastLocation = await query(`
                    SELECT latitude, longitude, timestamp, address
                    FROM courier_locations
                    WHERE courier_id = ?
                    ORDER BY timestamp DESC
                    LIMIT 1
                `, [courier.id]);

                return {
                    ...courier,
                    last_location: lastLocation[0] || null
                };
            })
        );

        res.json({
            success: true,
            data: couriersWithLocation
        });

    } catch (error) {
        next(error);
    }
});

// Получение информации о курьере
router.get('/:id', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;

        const couriers = await query(`
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.middle_name,
                u.phone,
                u.last_login,
                u.created_at,
                b.name as branch_name,
                d.name as department_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.name = 'courier'
        `, [id]);

        if (!couriers.length) {
            return res.status(404).json({
                success: false,
                message: 'Курьер не найден'
            });
        }

        const courier = couriers[0];

        // Получаем последнее местоположение
        const lastLocation = await query(`
            SELECT latitude, longitude, timestamp, address, accuracy
            FROM courier_locations
            WHERE courier_id = ?
            ORDER BY timestamp DESC
            LIMIT 1
        `, [id]);

        // Получаем текущую активность
        const currentActivity = await query(`
            SELECT activity_type, start_time
            FROM courier_activities
            WHERE courier_id = ? AND end_time IS NULL
            ORDER BY start_time DESC
            LIMIT 1
        `, [id]);

        // Получаем статистику заявок курьера
        const requestStats = await query(`
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress
            FROM requests
            WHERE courier_id = ?
        `, [id]);

        res.json({
            success: true,
            data: {
                ...courier,
                last_location: lastLocation[0] || null,
                current_activity: currentActivity[0] || null,
                request_stats: requestStats[0]
            }
        });

    } catch (error) {
        next(error);
    }
});

// Получение истории местоположений курьера
router.get('/:id/locations', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { date_from, date_to, limit = 100 } = req.query;

        let whereConditions = ['courier_id = ?'];
        let queryParams = [id];

        if (date_from) {
            whereConditions.push('DATE(timestamp) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(timestamp) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const locations = await query(`
            SELECT latitude, longitude, timestamp, address, accuracy
            FROM courier_locations
            ${whereClause}
            ORDER BY timestamp DESC
            LIMIT ?
        `, [...queryParams, parseInt(limit)]);

        res.json({
            success: true,
            data: locations
        });

    } catch (error) {
        next(error);
    }
});

// Получение активности курьера
router.get('/:id/activities', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { date_from, date_to, limit = 50 } = req.query;

        let whereConditions = ['courier_id = ?'];
        let queryParams = [id];

        if (date_from) {
            whereConditions.push('DATE(start_time) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(start_time) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const activities = await query(`
            SELECT activity_type, start_time, end_time, notes
            FROM courier_activities
            ${whereClause}
            ORDER BY start_time DESC
            LIMIT ?
        `, [...queryParams, parseInt(limit)]);

        res.json({
            success: true,
            data: activities
        });

    } catch (error) {
        next(error);
    }
});

// Получение заявок курьера
router.get('/:id/requests', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { status_id, limit = 20, offset = 0 } = req.query;

        let whereConditions = ['r.courier_id = ?'];
        let queryParams = [id];

        if (status_id) {
            whereConditions.push('r.status_id = ?');
            queryParams.push(status_id);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const requests = await query(`
            SELECT 
                r.id,
                r.request_number,
                r.client_name,
                r.client_phone,
                r.delivery_address,
                r.registration_date,
                r.delivery_date,
                rs.name as status_name,
                rs.color as status_color
            FROM requests r
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            ${whereClause}
            ORDER BY r.registration_date DESC
            LIMIT ? OFFSET ?
        `, [...queryParams, parseInt(limit), parseInt(offset)]);

        res.json({
            success: true,
            data: requests
        });

    } catch (error) {
        next(error);
    }
});

// Обновление активности курьера
router.post('/:id/activity', requirePermission('manage_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { activity_type, notes } = req.body;

        if (!activity_type) {
            return res.status(400).json({
                success: false,
                message: 'Тип активности обязателен'
            });
        }

        const validActivities = ['online', 'offline', 'on_delivery', 'break'];
        if (!validActivities.includes(activity_type)) {
            return res.status(400).json({
                success: false,
                message: 'Некорректный тип активности'
            });
        }

        // Завершаем текущую активность
        await query(`
            UPDATE courier_activities 
            SET end_time = NOW() 
            WHERE courier_id = ? AND end_time IS NULL
        `, [id]);

        // Создаем новую активность
        await query(`
            INSERT INTO courier_activities (courier_id, activity_type, notes)
            VALUES (?, ?, ?)
        `, [id, activity_type, notes || null]);

        // Логирование
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_courier_activity', 'courier', id,
                JSON.stringify({ activity_type, notes }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Активность курьера обновлена', {
            courierId: id,
            activityType: activity_type,
            updatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Активность курьера обновлена'
        });

    } catch (error) {
        next(error);
    }
});

// Получение курьеров в реальном времени (для карты)
router.get('/realtime/locations', requirePermission('view_couriers'), async (req, res, next) => {
    try {
        const { branch_id } = req.query;

        let whereClause = 'WHERE r.name = "courier" AND u.is_active = TRUE';
        let queryParams = [];

        if (branch_id) {
            whereClause += ' AND u.branch_id = ?';
            queryParams.push(branch_id);
        }

        const couriers = await query(`
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                b.name as branch_name,
                cl.latitude,
                cl.longitude,
                cl.timestamp as last_update,
                ca.activity_type as current_activity
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN courier_locations cl ON u.id = cl.courier_id
            LEFT JOIN courier_activities ca ON u.id = ca.courier_id AND ca.end_time IS NULL
            ${whereClause}
            ORDER BY cl.timestamp DESC
        `, queryParams);

        // Группируем по курьерам, оставляя только последнее местоположение
        const courierMap = new Map();
        couriers.forEach(courier => {
            if (!courierMap.has(courier.id) && courier.latitude && courier.longitude) {
                courierMap.set(courier.id, courier);
            }
        });

        const activeCouriers = Array.from(courierMap.values());

        res.json({
            success: true,
            data: activeCouriers
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;