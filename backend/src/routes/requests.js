const express = require('express');
const Joi = require('joi');
const { query, transaction } = require('../../config/database');
const { requirePermission, requireBranchAccess } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const createRequestSchema = Joi.object({
    client_name: Joi.string().required().max(150).messages({
        'string.empty': 'ФИО клиента обязательно',
        'string.max': 'ФИО клиента не должно превышать 150 символов',
        'any.required': 'ФИО клиента обязательно'
    }),
    client_phone: Joi.string().required().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).messages({
        'string.empty': 'Номер телефона клиента обязателен',
        'string.pattern.base': 'Некорректный формат номера телефона',
        'any.required': 'Номер телефона клиента обязателен'
    }),
    pan: Joi.string().optional().length(16).pattern(/^\d+$/).messages({
        'string.length': 'PAN должен содержать 16 цифр',
        'string.pattern.base': 'PAN должен содержать только цифры'
    }),
    delivery_address: Joi.string().required().max(500).messages({
        'string.empty': 'Адрес доставки обязателен',
        'string.max': 'Адрес доставки не должен превышать 500 символов',
        'any.required': 'Адрес доставки обязателен'
    }),
    card_type_id: Joi.number().integer().positive().optional(),
    courier_id: Joi.number().integer().positive().optional(),
    branch_id: Joi.number().integer().positive().required().messages({
        'any.required': 'Филиал обязателен'
    }),
    department_id: Joi.number().integer().positive().required().messages({
        'any.required': 'Подразделение обязательно'
    }),
    notes: Joi.string().optional().max(1000)
});

const updateRequestSchema = createRequestSchema.fork(['client_name', 'client_phone', 'delivery_address', 'branch_id', 'department_id'], (schema) => schema.optional());

const changeStatusSchema = Joi.object({
    status_id: Joi.number().integer().positive().required().messages({
        'any.required': 'Статус обязателен'
    }),
    rejection_reason: Joi.string().when('status_id', {
        is: Joi.number().valid(4), // ID статуса "Отказано"
        then: Joi.string().min(100).required().messages({
            'string.min': 'Причина отказа должна содержать минимум 100 символов',
            'any.required': 'Причина отказа обязательна при отказе'
        }),
        otherwise: Joi.string().optional()
    }),
    courier_phone: Joi.string().when('status_id', {
        is: Joi.number().valid(3), // ID статуса "Доставлено"
        then: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).required().messages({
            'string.pattern.base': 'Некорректный формат номера телефона курьера',
            'any.required': 'Телефон курьера обязателен при доставке'
        }),
        otherwise: Joi.string().optional()
    })
});

// Получение списка заявок с фильтрацией
router.get('/', requirePermission('view_requests'), async (req, res, next) => {
    try {
        const {
            page = 1,
            limit = 20,
            status_id,
            call_status_id,
            card_type_id,
            branch_id,
            department_id,
            courier_id,
            operator_id,
            client_name,
            client_phone,
            pan,
            date_from,
            date_to,
            delivery_date_from,
            delivery_date_to
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
        if (status_id) {
            whereConditions.push('r.status_id = ?');
            queryParams.push(status_id);
        }

        if (call_status_id) {
            whereConditions.push('r.call_status_id = ?');
            queryParams.push(call_status_id);
        }

        if (card_type_id) {
            whereConditions.push('r.card_type_id = ?');
            queryParams.push(card_type_id);
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

        if (client_name) {
            whereConditions.push('r.client_name LIKE ?');
            queryParams.push(`%${client_name}%`);
        }

        if (client_phone) {
            whereConditions.push('r.client_phone LIKE ?');
            queryParams.push(`%${client_phone}%`);
        }

        if (pan) {
            whereConditions.push('r.pan LIKE ?');
            queryParams.push(`%${pan}%`);
        }

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        if (delivery_date_from) {
            whereConditions.push('DATE(r.delivery_date) >= ?');
            queryParams.push(delivery_date_from);
        }

        if (delivery_date_to) {
            whereConditions.push('DATE(r.delivery_date) <= ?');
            queryParams.push(delivery_date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Подсчет общего количества записей
        const countQuery = `
            SELECT COUNT(*) as total
            FROM requests r
            ${whereClause}
        `;
        const countResult = await query(countQuery, queryParams);
        const total = countResult[0].total;

        // Получение данных с пагинацией
        const offset = (page - 1) * limit;
        const dataQuery = `
            SELECT 
                r.*,
                rs.name as status_name,
                rs.color as status_color,
                cs.name as call_status_name,
                ct.name as card_type_name,
                CONCAT(u.first_name, ' ', u.last_name) as courier_name,
                CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                b.name as branch_name,
                d.name as department_name
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
            LIMIT ? OFFSET ?
        `;

        const requests = await query(dataQuery, [...queryParams, parseInt(limit), offset]);

        res.json({
            success: true,
            data: requests,
            pagination: {
                page: parseInt(page),
                limit: parseInt(limit),
                total,
                pages: Math.ceil(total / limit)
            }
        });

    } catch (error) {
        next(error);
    }
});

// Получение заявки по ID
router.get('/:id', requirePermission('view_requests'), async (req, res, next) => {
    try {
        const { id } = req.params;

        const requests = await query(`
            SELECT 
                r.*,
                rs.name as status_name,
                rs.color as status_color,
                cs.name as call_status_name,
                ct.name as card_type_name,
                CONCAT(u.first_name, ' ', u.last_name) as courier_name,
                CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                b.name as branch_name,
                d.name as department_name
            FROM requests r
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN call_statuses cs ON r.call_status_id = cs.id
            LEFT JOIN card_types ct ON r.card_type_id = ct.id
            LEFT JOIN users u ON r.courier_id = u.id
            LEFT JOIN users op ON r.operator_id = op.id
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN departments d ON r.department_id = d.id
            WHERE r.id = ?
        `, [id]);

        if (!requests.length) {
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        const request = requests[0];

        // Проверка прав доступа
        if (req.user.role_name === 'senior_courier' && 
            req.user.branch_id !== request.branch_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к данной заявке запрещен'
            });
        }

        if (req.user.role_name === 'courier' && 
            req.user.id !== request.courier_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к данной заявке запрещен'
            });
        }

        // Получение файлов заявки
        const files = await query(`
            SELECT * FROM request_files WHERE request_id = ? ORDER BY created_at DESC
        `, [id]);

        res.json({
            success: true,
            data: {
                ...request,
                files
            }
        });

    } catch (error) {
        next(error);
    }
});

// Создание новой заявки
router.post('/', requirePermission('add_requests'), async (req, res, next) => {
    try {
        const { error, value } = createRequestSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const {
            client_name,
            client_phone,
            pan,
            delivery_address,
            card_type_id,
            courier_id,
            branch_id,
            department_id,
            notes
        } = value;

        // Генерация номера заявки
        const requestNumber = `REQ-${Date.now()}`;

        const result = await query(`
            INSERT INTO requests (
                request_number, client_name, client_phone, pan, delivery_address,
                status_id, card_type_id, courier_id, operator_id, branch_id, department_id, notes
            ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
        `, [
            requestNumber, client_name, client_phone, pan, delivery_address,
            card_type_id, courier_id, req.user.id, branch_id, department_id, notes
        ]);

        // Логирование создания заявки
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'create_request', 'request', result.insertId,
                JSON.stringify({ request_number: requestNumber, client_name }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Создана новая заявка', {
            requestId: result.insertId,
            requestNumber,
            clientName: client_name,
            operatorId: req.user.id
        });

        res.status(201).json({
            success: true,
            message: 'Заявка успешно создана',
            data: {
                id: result.insertId,
                request_number: requestNumber
            }
        });

    } catch (error) {
        next(error);
    }
});

// Обновление заявки
router.put('/:id', requirePermission('edit_requests'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { error, value } = updateRequestSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        // Проверяем существование заявки
        const existingRequests = await query('SELECT * FROM requests WHERE id = ?', [id]);
        if (!existingRequests.length) {
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        const existingRequest = existingRequests[0];

        // Проверка прав доступа
        if (req.user.role_name === 'senior_courier' && 
            req.user.branch_id !== existingRequest.branch_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к данной заявке запрещен'
            });
        }

        // Подготовка полей для обновления
        const updateFields = [];
        const updateValues = [];

        Object.keys(value).forEach(key => {
            if (value[key] !== undefined) {
                updateFields.push(`${key} = ?`);
                updateValues.push(value[key]);
            }
        });

        if (updateFields.length === 0) {
            return res.status(400).json({
                success: false,
                message: 'Нет данных для обновления'
            });
        }

        updateFields.push('updated_at = NOW()');
        updateValues.push(id);

        await query(`
            UPDATE requests 
            SET ${updateFields.join(', ')}
            WHERE id = ?
        `, updateValues);

        // Логирование обновления заявки
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_request', 'request', id,
                JSON.stringify(value),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Заявка обновлена', {
            requestId: id,
            updatedFields: Object.keys(value),
            userId: req.user.id
        });

        res.json({
            success: true,
            message: 'Заявка успешно обновлена'
        });

    } catch (error) {
        next(error);
    }
});

// Изменение статуса заявки
router.post('/:id/status', requirePermission('change_status'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { error, value } = changeStatusSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { status_id, rejection_reason, courier_phone } = value;

        // Получаем информацию о заявке
        const requests = await query(`
            SELECT r.*, rs.name as status_name, rs.is_final
            FROM requests r
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            WHERE r.id = ?
        `, [id]);

        if (!requests.length) {
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        const request = requests[0];

        // Проверка прав доступа
        if (req.user.role_name === 'courier' && 
            req.user.id !== request.courier_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к данной заявке запрещен'
            });
        }

        // Проверка, что заявка не в финальном статусе
        if (request.is_final) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя изменить статус заявки в финальном состоянии'
            });
        }

        // Обновление статуса
        const updateData = {
            status_id,
            updated_at: new Date()
        };

        if (status_id === 3) { // Доставлено
            updateData.delivery_date = new Date();
            updateData.processing_date = new Date();
            if (courier_phone) {
                updateData.courier_phone = courier_phone;
            }
        } else if (status_id === 4) { // Отказано
            updateData.processing_date = new Date();
            if (rejection_reason) {
                updateData.rejection_reason = rejection_reason;
            }
        }

        await query(`
            UPDATE requests 
            SET status_id = ?, delivery_date = ?, processing_date = ?, 
                courier_phone = ?, rejection_reason = ?, updated_at = NOW()
            WHERE id = ?
        `, [
            status_id,
            updateData.delivery_date || null,
            updateData.processing_date || null,
            updateData.courier_phone || null,
            updateData.rejection_reason || null,
            id
        ]);

        // Логирование изменения статуса
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'change_request_status', 'request', id,
                JSON.stringify({ old_status: request.status_name, new_status_id: status_id }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Статус заявки изменен', {
            requestId: id,
            oldStatus: request.status_name,
            newStatusId: status_id,
            userId: req.user.id
        });

        res.json({
            success: true,
            message: 'Статус заявки успешно изменен'
        });

    } catch (error) {
        next(error);
    }
});

// Назначение курьера
router.post('/:id/assign-courier', requirePermission('assign_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { courier_id } = req.body;

        if (!courier_id) {
            return res.status(400).json({
                success: false,
                message: 'ID курьера обязателен'
            });
        }

        // Проверяем существование заявки
        const requests = await query('SELECT * FROM requests WHERE id = ?', [id]);
        if (!requests.length) {
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        // Проверяем существование курьера
        const couriers = await query(`
            SELECT u.*, r.name as role_name 
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? AND r.name = 'courier' AND u.is_active = TRUE
        `, [courier_id]);

        if (!couriers.length) {
            return res.status(400).json({
                success: false,
                message: 'Курьер не найден или неактивен'
            });
        }

        // Обновляем заявку
        await query(
            'UPDATE requests SET courier_id = ?, updated_at = NOW() WHERE id = ?',
            [courier_id, id]
        );

        // Логирование назначения курьера
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'assign_courier', 'request', id,
                JSON.stringify({ courier_id }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Курьер назначен на заявку', {
            requestId: id,
            courierId: courier_id,
            assignedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Курьер успешно назначен на заявку'
        });

    } catch (error) {
        next(error);
    }
});

// Получение статистики заявок
router.get('/stats/overview', requirePermission('view_requests'), async (req, res, next) => {
    try {
        let whereClause = '';
        let queryParams = [];

        // Фильтрация по правам доступа
        if (req.user.role_name === 'senior_courier') {
            whereClause = 'WHERE (r.branch_id = ? OR r.department_id = ?)';
            queryParams = [req.user.branch_id, req.user.department_id];
        } else if (req.user.role_name === 'courier') {
            whereClause = 'WHERE r.courier_id = ?';
            queryParams = [req.user.id];
        }

        const stats = await query(`
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN r.status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN r.status_id = 4 THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN r.status_id = 5 THEN 1 ELSE 0 END) as cancelled
            FROM requests r
            ${whereClause}
        `, queryParams);

        res.json({
            success: true,
            data: stats[0]
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;