const express = require('express');
const Joi = require('joi');
const { query } = require('../config/database');
const { requireRole, requirePermission, requireBranchAccess } = require('../middleware/auth');

const router = express.Router();

// Схемы валидации
const createRequestSchema = Joi.object({
    client_name: Joi.string().min(2).max(150).required(),
    client_phone: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).required(),
    pan: Joi.string().length(16).pattern(/^\d+$/).optional(),
    delivery_address: Joi.string().min(10).max(500).required(),
    card_type_id: Joi.number().integer().positive().optional(),
    courier_id: Joi.number().integer().positive().optional(),
    branch_id: Joi.number().integer().positive().optional(),
    department_id: Joi.number().integer().positive().optional(),
    notes: Joi.string().max(1000).optional()
});

const updateRequestSchema = Joi.object({
    client_name: Joi.string().min(2).max(150).optional(),
    client_phone: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).optional(),
    pan: Joi.string().length(16).pattern(/^\d+$/).optional(),
    delivery_address: Joi.string().min(10).max(500).optional(),
    card_type_id: Joi.number().integer().positive().optional(),
    courier_id: Joi.number().integer().positive().optional(),
    branch_id: Joi.number().integer().positive().optional(),
    department_id: Joi.number().integer().positive().optional(),
    notes: Joi.string().max(1000).optional()
});

const changeStatusSchema = Joi.object({
    status: Joi.string().valid('new', 'waiting_delivery', 'delivered', 'rejected', 'cancelled').required(),
    rejection_reason: Joi.string().min(100).when('status', {
        is: 'rejected',
        then: Joi.required(),
        otherwise: Joi.optional()
    }),
    courier_phone: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).when('status', {
        is: 'delivered',
        then: Joi.required(),
        otherwise: Joi.optional()
    })
});

// GET /api/requests - Получение списка заявок с фильтрацией
router.get('/', async (req, res, next) => {
    try {
        const {
            page = 1,
            limit = 20,
            status,
            call_status,
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
            processing_date_from,
            processing_date_to,
            delivery_date_from,
            delivery_date_to
        } = req.query;

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        // Проверка доступа к филиалам для не-администраторов
        if (req.user.role !== 'admin' && req.user.branch_id) {
            whereConditions.push('dr.branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        if (status) {
            whereConditions.push('dr.status = ?');
            queryParams.push(status);
        }

        if (call_status) {
            whereConditions.push('dr.call_status = ?');
            queryParams.push(call_status);
        }

        if (card_type_id) {
            whereConditions.push('dr.card_type_id = ?');
            queryParams.push(card_type_id);
        }

        if (branch_id) {
            whereConditions.push('dr.branch_id = ?');
            queryParams.push(branch_id);
        }

        if (department_id) {
            whereConditions.push('dr.department_id = ?');
            queryParams.push(department_id);
        }

        if (courier_id) {
            whereConditions.push('dr.courier_id = ?');
            queryParams.push(courier_id);
        }

        if (operator_id) {
            whereConditions.push('dr.operator_id = ?');
            queryParams.push(operator_id);
        }

        if (client_name) {
            whereConditions.push('dr.client_name LIKE ?');
            queryParams.push(`%${client_name}%`);
        }

        if (client_phone) {
            whereConditions.push('dr.client_phone LIKE ?');
            queryParams.push(`%${client_phone}%`);
        }

        if (pan) {
            whereConditions.push('dr.pan LIKE ?');
            queryParams.push(`%${pan}%`);
        }

        if (date_from) {
            whereConditions.push('DATE(dr.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(dr.registration_date) <= ?');
            queryParams.push(date_to);
        }

        if (processing_date_from) {
            whereConditions.push('DATE(dr.processing_date) >= ?');
            queryParams.push(processing_date_from);
        }

        if (processing_date_to) {
            whereConditions.push('DATE(dr.processing_date) <= ?');
            queryParams.push(processing_date_to);
        }

        if (delivery_date_from) {
            whereConditions.push('DATE(dr.delivery_date) >= ?');
            queryParams.push(delivery_date_from);
        }

        if (delivery_date_to) {
            whereConditions.push('DATE(dr.delivery_date) <= ?');
            queryParams.push(delivery_date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Подсчет общего количества записей
        const countQuery = `
            SELECT COUNT(*) as total
            FROM delivery_requests dr
            ${whereClause}
        `;
        const countResult = await query(countQuery, queryParams);
        const total = countResult[0].total;

        // Получение заявок с пагинацией
        const offset = (page - 1) * limit;
        const requestsQuery = `
            SELECT 
                dr.id, dr.request_number, dr.abs_id, dr.client_name, dr.client_phone,
                dr.pan, dr.delivery_address, dr.status, dr.call_status,
                dr.registration_date, dr.processing_date, dr.delivery_date,
                dr.rejection_reason, dr.courier_phone, dr.notes,
                ct.name as card_type_name,
                courier.first_name as courier_first_name,
                courier.last_name as courier_last_name,
                courier.middle_name as courier_middle_name,
                operator.first_name as operator_first_name,
                operator.last_name as operator_last_name,
                operator.middle_name as operator_middle_name,
                b.name as branch_name,
                d.name as department_name
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN users courier ON dr.courier_id = courier.id
            LEFT JOIN users operator ON dr.operator_id = operator.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            LEFT JOIN departments d ON dr.department_id = d.id
            ${whereClause}
            ORDER BY dr.registration_date DESC
            LIMIT ? OFFSET ?
        `;

        const requests = await query(requestsQuery, [...queryParams, parseInt(limit), offset]);

        // Форматирование данных
        const formattedRequests = requests.map(request => ({
            id: request.id,
            requestNumber: request.request_number,
            absId: request.abs_id,
            client: {
                name: request.client_name,
                phone: request.client_phone
            },
            pan: request.pan,
            deliveryAddress: request.delivery_address,
            status: request.status,
            callStatus: request.call_status,
            cardType: request.card_type_name,
            courier: request.courier_first_name ? {
                id: request.courier_id,
                name: `${request.courier_last_name} ${request.courier_first_name} ${request.courier_middle_name || ''}`.trim(),
                phone: request.courier_phone
            } : null,
            operator: request.operator_first_name ? {
                id: request.operator_id,
                name: `${request.operator_last_name} ${request.operator_first_name} ${request.operator_middle_name || ''}`.trim()
            } : null,
            branch: request.branch_name,
            department: request.department_name,
            dates: {
                registration: request.registration_date,
                processing: request.processing_date,
                delivery: request.delivery_date
            },
            rejectionReason: request.rejection_reason,
            notes: request.notes
        }));

        res.json({
            requests: formattedRequests,
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

// GET /api/requests/:id - Получение заявки по ID
router.get('/:id', async (req, res, next) => {
    try {
        const { id } = req.params;

        const requests = await query(`
            SELECT 
                dr.*,
                ct.name as card_type_name,
                courier.first_name as courier_first_name,
                courier.last_name as courier_last_name,
                courier.middle_name as courier_middle_name,
                operator.first_name as operator_first_name,
                operator.last_name as operator_last_name,
                operator.middle_name as operator_middle_name,
                b.name as branch_name,
                d.name as department_name
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN users courier ON dr.courier_id = courier.id
            LEFT JOIN users operator ON dr.operator_id = operator.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            LEFT JOIN departments d ON dr.department_id = d.id
            WHERE dr.id = ?
        `, [id]);

        if (requests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        const request = requests[0];

        // Получение файлов заявки
        const files = await query(`
            SELECT id, file_name, file_path, file_type, file_size, file_category, created_at
            FROM files
            WHERE request_id = ?
            ORDER BY created_at DESC
        `, [id]);

        const formattedRequest = {
            id: request.id,
            requestNumber: request.request_number,
            absId: request.abs_id,
            client: {
                name: request.client_name,
                phone: request.client_phone
            },
            pan: request.pan,
            deliveryAddress: request.delivery_address,
            status: request.status,
            callStatus: request.call_status,
            cardType: {
                id: request.card_type_id,
                name: request.card_type_name
            },
            courier: request.courier_first_name ? {
                id: request.courier_id,
                name: `${request.courier_last_name} ${request.courier_first_name} ${request.courier_middle_name || ''}`.trim(),
                phone: request.courier_phone
            } : null,
            operator: request.operator_first_name ? {
                id: request.operator_id,
                name: `${request.operator_last_name} ${request.operator_first_name} ${request.operator_middle_name || ''}`.trim()
            } : null,
            branch: {
                id: request.branch_id,
                name: request.branch_name
            },
            department: {
                id: request.department_id,
                name: request.department_name
            },
            dates: {
                registration: request.registration_date,
                processing: request.processing_date,
                delivery: request.delivery_date
            },
            rejectionReason: request.rejection_reason,
            notes: request.notes,
            files: files.map(file => ({
                id: file.id,
                fileName: file.file_name,
                filePath: file.file_path,
                fileType: file.file_type,
                fileSize: file.file_size,
                category: file.file_category,
                uploadedAt: file.created_at
            }))
        };

        res.json({ request: formattedRequest });

    } catch (error) {
        next(error);
    }
});

// POST /api/requests - Создание новой заявки
router.post('/', requirePermission('add_requests'), async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = createRequestSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Генерация номера заявки
        const requestNumber = `REQ-${new Date().getFullYear()}-${String(Date.now()).slice(-6)}`;

        // Подготовка данных для вставки
        const requestData = {
            request_number: requestNumber,
            client_name: value.client_name,
            client_phone: value.client_phone,
            pan: value.pan,
            delivery_address: value.delivery_address,
            card_type_id: value.card_type_id,
            courier_id: value.courier_id,
            operator_id: req.user.id,
            branch_id: value.branch_id || req.user.branch_id,
            department_id: value.department_id || req.user.department_id,
            notes: value.notes
        };

        // Вставка заявки
        const result = await query(`
            INSERT INTO delivery_requests (
                request_number, client_name, client_phone, pan, delivery_address,
                card_type_id, courier_id, operator_id, branch_id, department_id, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            requestData.request_number,
            requestData.client_name,
            requestData.client_phone,
            requestData.pan,
            requestData.delivery_address,
            requestData.card_type_id,
            requestData.courier_id,
            requestData.operator_id,
            requestData.branch_id,
            requestData.department_id,
            requestData.notes
        ]);

        // Запись в историю
        await query(`
            INSERT INTO request_history (request_id, user_id, action, new_value)
            VALUES (?, ?, 'created', ?)
        `, [result.insertId, req.user.id, JSON.stringify(requestData)]);

        res.status(201).json({
            message: 'Заявка успешно создана',
            requestId: result.insertId,
            requestNumber: requestNumber
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/requests/:id - Обновление заявки
router.put('/:id', requirePermission('edit_requests'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования заявки
        const existingRequests = await query('SELECT * FROM delivery_requests WHERE id = ?', [id]);
        if (existingRequests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        // Валидация входных данных
        const { error, value } = updateRequestSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Подготовка данных для обновления
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
                error: 'Нет данных для обновления'
            });
        }

        updateValues.push(id);

        // Обновление заявки
        await query(`
            UPDATE delivery_requests 
            SET ${updateFields.join(', ')}, updated_at = NOW()
            WHERE id = ?
        `, updateValues);

        // Запись в историю
        await query(`
            INSERT INTO request_history (request_id, user_id, action, old_value, new_value)
            VALUES (?, ?, 'updated', ?, ?)
        `, [id, req.user.id, JSON.stringify(existingRequests[0]), JSON.stringify(value)]);

        res.json({
            message: 'Заявка успешно обновлена'
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/requests/:id/status - Изменение статуса заявки
router.put('/:id/status', requirePermission('change_status'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Валидация входных данных
        const { error, value } = changeStatusSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка существования заявки
        const existingRequests = await query('SELECT * FROM delivery_requests WHERE id = ?', [id]);
        if (existingRequests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        const oldStatus = existingRequests[0].status;
        const newStatus = value.status;

        // Проверка прав на изменение статуса
        if (req.user.role === 'courier' && oldStatus !== 'waiting_delivery') {
            return res.status(403).json({
                error: 'Курьер может изменять статус только заявок в статусе "Ожидает доставки"'
            });
        }

        // Подготовка данных для обновления
        const updateData = {
            status: newStatus,
            processing_date: newStatus === 'waiting_delivery' ? new Date() : existingRequests[0].processing_date,
            delivery_date: newStatus === 'delivered' ? new Date() : 
                          newStatus === 'rejected' ? new Date() : existingRequests[0].delivery_date,
            rejection_reason: value.rejection_reason || existingRequests[0].rejection_reason,
            courier_phone: value.courier_phone || existingRequests[0].courier_phone
        };

        // Обновление статуса
        await query(`
            UPDATE delivery_requests 
            SET status = ?, processing_date = ?, delivery_date = ?, 
                rejection_reason = ?, courier_phone = ?, updated_at = NOW()
            WHERE id = ?
        `, [
            updateData.status,
            updateData.processing_date,
            updateData.delivery_date,
            updateData.rejection_reason,
            updateData.courier_phone,
            id
        ]);

        // Запись в историю
        await query(`
            INSERT INTO request_history (request_id, user_id, action, old_value, new_value)
            VALUES (?, ?, 'status_changed', ?, ?)
        `, [id, req.user.id, oldStatus, newStatus]);

        res.json({
            message: 'Статус заявки успешно изменен'
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/requests/:id/assign-courier - Назначение курьера
router.put('/:id/assign-courier', requirePermission('assign_couriers'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { courier_id } = req.body;

        if (!courier_id) {
            return res.status(400).json({
                error: 'ID курьера обязателен'
            });
        }

        // Проверка существования заявки
        const existingRequests = await query('SELECT * FROM delivery_requests WHERE id = ?', [id]);
        if (existingRequests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        // Проверка существования курьера
        const couriers = await query('SELECT id, first_name, last_name FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = "courier")', [courier_id]);
        if (couriers.length === 0) {
            return res.status(404).json({
                error: 'Курьер не найден'
            });
        }

        const oldCourierId = existingRequests[0].courier_id;

        // Назначение курьера
        await query(`
            UPDATE delivery_requests 
            SET courier_id = ?, updated_at = NOW()
            WHERE id = ?
        `, [courier_id, id]);

        // Запись в историю
        await query(`
            INSERT INTO request_history (request_id, user_id, action, old_value, new_value)
            VALUES (?, ?, 'courier_assigned', ?, ?)
        `, [id, req.user.id, oldCourierId, courier_id]);

        res.json({
            message: 'Курьер успешно назначен'
        });

    } catch (error) {
        next(error);
    }
});

// DELETE /api/requests/:id - Удаление заявки
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования заявки
        const existingRequests = await query('SELECT * FROM delivery_requests WHERE id = ?', [id]);
        if (existingRequests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        // Удаление заявки (каскадное удаление файлов и истории)
        await query('DELETE FROM delivery_requests WHERE id = ?', [id]);

        res.json({
            message: 'Заявка успешно удалена'
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;