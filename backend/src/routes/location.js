const express = require('express');
const Joi = require('joi');
const { query } = require('../config/database');
const { requireRole } = require('../middleware/auth');

const router = express.Router();

// Схемы валидации
const locationUpdateSchema = Joi.object({
    latitude: Joi.number().min(-90).max(90).required(),
    longitude: Joi.number().min(-180).max(180).required(),
    accuracy: Joi.number().min(0).optional()
});

// POST /api/location/update - Обновление местоположения курьера
router.post('/update', async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = locationUpdateSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        const { latitude, longitude, accuracy } = value;

        // Проверка, что пользователь является курьером
        if (req.user.role !== 'courier') {
            return res.status(403).json({
                error: 'Только курьеры могут обновлять местоположение'
            });
        }

        // Сохранение местоположения в базе данных
        await query(`
            INSERT INTO location_tracking (user_id, latitude, longitude, accuracy)
            VALUES (?, ?, ?, ?)
        `, [req.user.id, latitude, longitude, accuracy || null]);

        res.json({
            message: 'Местоположение успешно обновлено',
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/location/couriers - Получение текущего местоположения всех курьеров
router.get('/couriers', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { branch_id } = req.query;

        // Построение условий фильтрации
        let whereConditions = ['u.role_id = (SELECT id FROM roles WHERE name = "courier")', 'u.is_active = 1'];
        let queryParams = [];

        // Фильтрация по филиалу для старших курьеров
        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        if (branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(branch_id);
        }

        const whereClause = whereConditions.join(' AND ');

        // Получение последнего местоположения каждого курьера
        const couriers = await query(`
            SELECT 
                u.id, u.first_name, u.last_name, u.middle_name, u.phone,
                b.name as branch_name, d.name as department_name,
                lt.latitude, lt.longitude, lt.accuracy, lt.timestamp as last_update,
                dr.id as current_request_id, dr.request_number, dr.client_name, dr.delivery_address,
                dr.status as request_status
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN (
                SELECT user_id, latitude, longitude, accuracy, timestamp,
                       ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY timestamp DESC) as rn
                FROM location_tracking
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ) lt ON u.id = lt.user_id AND lt.rn = 1
            LEFT JOIN delivery_requests dr ON u.id = dr.courier_id AND dr.status = 'waiting_delivery'
            WHERE ${whereClause}
            ORDER BY u.last_name, u.first_name
        `, queryParams);

        const formattedCouriers = couriers.map(courier => {
            const isOnline = courier.last_update && 
                           new Date() - new Date(courier.last_update) < 5 * 60 * 1000; // 5 минут

            return {
                id: courier.id,
                name: `${courier.last_name} ${courier.first_name} ${courier.middle_name || ''}`.trim(),
                phone: courier.phone,
                branch: courier.branch_name,
                department: courier.department_name,
                location: courier.latitude ? {
                    latitude: parseFloat(courier.latitude),
                    longitude: parseFloat(courier.longitude),
                    accuracy: courier.accuracy ? parseFloat(courier.accuracy) : null,
                    lastUpdate: courier.last_update
                } : null,
                status: isOnline ? 'online' : 'offline',
                currentRequest: courier.current_request_id ? {
                    id: courier.current_request_id,
                    requestNumber: courier.request_number,
                    clientName: courier.client_name,
                    deliveryAddress: courier.delivery_address,
                    status: courier.request_status
                } : null
            };
        });

        res.json({
            couriers: formattedCouriers,
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/location/history/:courierId - Получение истории перемещений курьера
router.get('/history/:courierId', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { courierId } = req.params;
        const { date_from, date_to, limit = 100 } = req.query;

        // Проверка существования курьера
        const couriers = await query(`
            SELECT u.id, u.first_name, u.last_name, u.middle_name, u.branch_id
            FROM users u
            WHERE u.id = ? AND u.role_id = (SELECT id FROM roles WHERE name = "courier")
        `, [courierId]);

        if (couriers.length === 0) {
            return res.status(404).json({
                error: 'Курьер не найден'
            });
        }

        const courier = couriers[0];

        // Проверка прав доступа к курьеру
        if (req.user.role === 'senior_courier' && req.user.branch_id !== courier.branch_id) {
            return res.status(403).json({
                error: 'Доступ к данному курьеру запрещен'
            });
        }

        // Построение условий фильтрации
        let whereConditions = ['user_id = ?'];
        let queryParams = [courierId];

        if (date_from) {
            whereConditions.push('DATE(timestamp) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(timestamp) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = whereConditions.join(' AND ');

        // Получение истории местоположений
        const locations = await query(`
            SELECT latitude, longitude, accuracy, timestamp
            FROM location_tracking
            WHERE ${whereClause}
            ORDER BY timestamp DESC
            LIMIT ?
        `, [...queryParams, parseInt(limit)]);

        const formattedLocations = locations.map(location => ({
            latitude: parseFloat(location.latitude),
            longitude: parseFloat(location.longitude),
            accuracy: location.accuracy ? parseFloat(location.accuracy) : null,
            timestamp: location.timestamp
        }));

        res.json({
            courier: {
                id: courier.id,
                name: `${courier.last_name} ${courier.first_name} ${courier.middle_name || ''}`.trim()
            },
            locations: formattedLocations,
            total: formattedLocations.length
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/location/route/:requestId - Получение маршрута курьера для конкретной заявки
router.get('/route/:requestId', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { requestId } = req.params;

        // Получение информации о заявке и курьере
        const requests = await query(`
            SELECT dr.id, dr.request_number, dr.delivery_address, dr.courier_id,
                   u.first_name, u.last_name, u.middle_name, u.branch_id
            FROM delivery_requests dr
            LEFT JOIN users u ON dr.courier_id = u.id
            WHERE dr.id = ?
        `, [requestId]);

        if (requests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        const request = requests[0];

        if (!request.courier_id) {
            return res.status(400).json({
                error: 'К курьеру не назначен'
            });
        }

        // Проверка прав доступа
        if (req.user.role === 'senior_courier' && req.user.branch_id !== request.branch_id) {
            return res.status(403).json({
                error: 'Доступ к данной заявке запрещен'
            });
        }

        // Получение местоположений курьера за время работы с заявкой
        const locations = await query(`
            SELECT latitude, longitude, accuracy, timestamp
            FROM location_tracking
            WHERE user_id = ? AND timestamp >= (
                SELECT COALESCE(processing_date, registration_date)
                FROM delivery_requests
                WHERE id = ?
            )
            ORDER BY timestamp ASC
        `, [request.courier_id, requestId]);

        const formattedLocations = locations.map(location => ({
            latitude: parseFloat(location.latitude),
            longitude: parseFloat(location.longitude),
            accuracy: location.accuracy ? parseFloat(location.accuracy) : null,
            timestamp: location.timestamp
        }));

        res.json({
            request: {
                id: request.id,
                requestNumber: request.request_number,
                deliveryAddress: request.delivery_address
            },
            courier: {
                id: request.courier_id,
                name: `${request.last_name} ${request.first_name} ${request.middle_name || ''}`.trim()
            },
            route: formattedLocations
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/location/stats - Получение статистики по местоположениям
router.get('/stats', requireRole('admin'), async (req, res, next) => {
    try {
        const { date_from, date_to } = req.query;

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(lt.timestamp) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(lt.timestamp) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Статистика по активным курьерам
        const activeCouriers = await query(`
            SELECT COUNT(DISTINCT lt.user_id) as active_couriers
            FROM location_tracking lt
            JOIN users u ON lt.user_id = u.id
            WHERE u.role_id = (SELECT id FROM roles WHERE name = "courier")
            AND u.is_active = 1
            AND lt.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ${whereClause}
        `, queryParams);

        // Общее количество курьеров
        const totalCouriers = await query(`
            SELECT COUNT(*) as total_couriers
            FROM users u
            WHERE u.role_id = (SELECT id FROM roles WHERE name = "courier")
            AND u.is_active = 1
        `);

        // Статистика по филиалам
        const branchStats = await query(`
            SELECT 
                b.name as branch_name,
                COUNT(DISTINCT u.id) as total_couriers,
                COUNT(DISTINCT CASE 
                    WHEN lt.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
                    THEN lt.user_id 
                END) as active_couriers
            FROM branches b
            LEFT JOIN users u ON b.id = u.branch_id AND u.role_id = (SELECT id FROM roles WHERE name = "courier") AND u.is_active = 1
            LEFT JOIN location_tracking lt ON u.id = lt.user_id
            GROUP BY b.id, b.name
            ORDER BY b.name
        `);

        res.json({
            stats: {
                totalCouriers: totalCouriers[0].total_couriers,
                activeCouriers: activeCouriers[0].active_couriers,
                activityRate: totalCouriers[0].total_couriers > 0 
                    ? (activeCouriers[0].active_couriers / totalCouriers[0].total_couriers * 100).toFixed(2)
                    : 0
            },
            branches: branchStats.map(branch => ({
                name: branch.branch_name,
                totalCouriers: branch.total_couriers,
                activeCouriers: branch.active_couriers,
                activityRate: branch.total_couriers > 0 
                    ? (branch.active_couriers / branch.total_couriers * 100).toFixed(2)
                    : 0
            })),
            timestamp: new Date().toISOString()
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;