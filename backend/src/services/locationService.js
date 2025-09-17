const { query } = require('../../config/database');
const logger = require('../utils/logger');

class LocationService {
    // Сохранение местоположения курьера
    async saveLocation(courierId, latitude, longitude, accuracy = null, address = null) {
        try {
            // Проверяем, что курьер существует и активен
            const couriers = await query(`
                SELECT u.id, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND r.name = 'courier' AND u.is_active = TRUE
            `, [courierId]);

            if (!couriers.length) {
                throw new Error('Курьер не найден или неактивен');
            }

            // Сохраняем местоположение
            const result = await query(`
                INSERT INTO courier_locations (courier_id, latitude, longitude, accuracy, address)
                VALUES (?, ?, ?, ?, ?)
            `, [courierId, latitude, longitude, accuracy, address]);

            logger.debug('Местоположение курьера сохранено', {
                courierId,
                latitude,
                longitude,
                accuracy,
                locationId: result.insertId
            });

            return result.insertId;

        } catch (error) {
            logger.error('Ошибка сохранения местоположения', {
                courierId,
                latitude,
                longitude,
                error: error.message
            });
            throw error;
        }
    }

    // Получение последнего местоположения курьера
    async getLastLocation(courierId) {
        try {
            const locations = await query(`
                SELECT latitude, longitude, accuracy, address, timestamp
                FROM courier_locations
                WHERE courier_id = ?
                ORDER BY timestamp DESC
                LIMIT 1
            `, [courierId]);

            return locations[0] || null;

        } catch (error) {
            logger.error('Ошибка получения местоположения курьера', {
                courierId,
                error: error.message
            });
            throw error;
        }
    }

    // Получение истории местоположений курьера
    async getLocationHistory(courierId, options = {}) {
        try {
            const {
                dateFrom,
                dateTo,
                limit = 100,
                offset = 0
            } = options;

            let whereConditions = ['courier_id = ?'];
            let queryParams = [courierId];

            if (dateFrom) {
                whereConditions.push('DATE(timestamp) >= ?');
                queryParams.push(dateFrom);
            }

            if (dateTo) {
                whereConditions.push('DATE(timestamp) <= ?');
                queryParams.push(dateTo);
            }

            const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

            const locations = await query(`
                SELECT latitude, longitude, accuracy, address, timestamp
                FROM courier_locations
                ${whereClause}
                ORDER BY timestamp DESC
                LIMIT ? OFFSET ?
            `, [...queryParams, limit, offset]);

            return locations;

        } catch (error) {
            logger.error('Ошибка получения истории местоположений', {
                courierId,
                error: error.message
            });
            throw error;
        }
    }

    // Получение всех активных курьеров с их местоположениями
    async getActiveCouriers(branchId = null) {
        try {
            let whereClause = 'WHERE r.name = "courier" AND u.is_active = TRUE';
            let queryParams = [];

            if (branchId) {
                whereClause += ' AND u.branch_id = ?';
                queryParams.push(branchId);
            }

            const couriers = await query(`
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    b.name as branch_name,
                    d.name as department_name,
                    cl.latitude,
                    cl.longitude,
                    cl.accuracy,
                    cl.address,
                    cl.timestamp as last_location_time,
                    ca.activity_type as current_activity
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN courier_locations cl ON u.id = cl.courier_id
                LEFT JOIN courier_activities ca ON u.id = ca.courier_id AND ca.end_time IS NULL
                ${whereClause}
                ORDER BY cl.timestamp DESC
            `, queryParams);

            // Группируем по курьерам, оставляя только последнее местоположение
            const courierMap = new Map();
            couriers.forEach(courier => {
                if (!courierMap.has(courier.id)) {
                    courierMap.set(courier.id, {
                        ...courier,
                        has_location: !!(courier.latitude && courier.longitude)
                    });
                }
            });

            return Array.from(courierMap.values());

        } catch (error) {
            logger.error('Ошибка получения активных курьеров', {
                branchId,
                error: error.message
            });
            throw error;
        }
    }

    // Получение курьеров в радиусе от точки
    async getCouriersInRadius(latitude, longitude, radiusKm = 5) {
        try {
            // Используем формулу гаверсинуса для расчета расстояния
            const couriers = await query(`
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    b.name as branch_name,
                    cl.latitude,
                    cl.longitude,
                    cl.address,
                    cl.timestamp as last_location_time,
                    (
                        6371 * acos(
                            cos(radians(?)) * cos(radians(cl.latitude)) * 
                            cos(radians(cl.longitude) - radians(?)) + 
                            sin(radians(?)) * sin(radians(cl.latitude))
                        )
                    ) AS distance_km
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                LEFT JOIN courier_locations cl ON u.id = cl.courier_id
                WHERE r.name = 'courier' 
                    AND u.is_active = TRUE
                    AND cl.latitude IS NOT NULL 
                    AND cl.longitude IS NOT NULL
                    AND cl.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                HAVING distance_km <= ?
                ORDER BY distance_km ASC
            `, [latitude, longitude, latitude, radiusKm]);

            return couriers;

        } catch (error) {
            logger.error('Ошибка получения курьеров в радиусе', {
                latitude,
                longitude,
                radiusKm,
                error: error.message
            });
            throw error;
        }
    }

    // Очистка старых записей местоположений
    async cleanupOldLocations(daysToKeep = 30) {
        try {
            const result = await query(`
                DELETE FROM courier_locations 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            `, [daysToKeep]);

            logger.info('Очищены старые записи местоположений', {
                deletedCount: result.affectedRows,
                daysToKeep
            });

            return result.affectedRows;

        } catch (error) {
            logger.error('Ошибка очистки старых записей местоположений', {
                daysToKeep,
                error: error.message
            });
            throw error;
        }
    }

    // Получение статистики местоположений
    async getLocationStats(courierId = null, dateFrom = null, dateTo = null) {
        try {
            let whereConditions = [];
            let queryParams = [];

            if (courierId) {
                whereConditions.push('courier_id = ?');
                queryParams.push(courierId);
            }

            if (dateFrom) {
                whereConditions.push('DATE(timestamp) >= ?');
                queryParams.push(dateFrom);
            }

            if (dateTo) {
                whereConditions.push('DATE(timestamp) <= ?');
                queryParams.push(dateTo);
            }

            const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

            const stats = await query(`
                SELECT 
                    COUNT(*) as total_locations,
                    COUNT(DISTINCT courier_id) as active_couriers,
                    MIN(timestamp) as first_location,
                    MAX(timestamp) as last_location,
                    AVG(accuracy) as avg_accuracy
                FROM courier_locations
                ${whereClause}
            `, queryParams);

            return stats[0];

        } catch (error) {
            logger.error('Ошибка получения статистики местоположений', {
                courierId,
                dateFrom,
                dateTo,
                error: error.message
            });
            throw error;
        }
    }

    // Обновление адреса по координатам (интеграция с Яндекс.Картами)
    async updateAddressFromCoordinates(latitude, longitude) {
        try {
            // Здесь должна быть интеграция с API Яндекс.Карт для получения адреса
            // Пока возвращаем заглушку
            return `Координаты: ${latitude}, ${longitude}`;

        } catch (error) {
            logger.error('Ошибка получения адреса по координатам', {
                latitude,
                longitude,
                error: error.message
            });
            return null;
        }
    }
}

module.exports = new LocationService();