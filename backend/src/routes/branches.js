const express = require('express');
const Joi = require('joi');
const { query } = require('../../config/database');
const { requireRole } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const createBranchSchema = Joi.object({
    name: Joi.string().required().max(100).messages({
        'string.empty': 'Название филиала обязательно',
        'string.max': 'Название филиала не должно превышать 100 символов',
        'any.required': 'Название филиала обязательно'
    }),
    address: Joi.string().optional().max(500),
    phone: Joi.string().optional().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).messages({
        'string.pattern.base': 'Некорректный формат номера телефона'
    }),
    manager_name: Joi.string().optional().max(100)
});

const updateBranchSchema = createBranchSchema.fork(['name'], (schema) => schema.optional());

// Получение списка филиалов
router.get('/', async (req, res, next) => {
    try {
        const { is_active = true } = req.query;

        const branches = await query(`
            SELECT 
                b.*,
                COUNT(u.id) as user_count,
                COUNT(d.id) as department_count
            FROM branches b
            LEFT JOIN users u ON b.id = u.branch_id AND u.is_active = TRUE
            LEFT JOIN departments d ON b.id = d.branch_id
            WHERE b.is_active = ?
            GROUP BY b.id
            ORDER BY b.name
        `, [is_active === 'true']);

        res.json({
            success: true,
            data: branches
        });

    } catch (error) {
        next(error);
    }
});

// Получение филиала по ID
router.get('/:id', async (req, res, next) => {
    try {
        const { id } = req.params;

        const branches = await query(`
            SELECT 
                b.*,
                COUNT(u.id) as user_count,
                COUNT(d.id) as department_count
            FROM branches b
            LEFT JOIN users u ON b.id = u.branch_id AND u.is_active = TRUE
            LEFT JOIN departments d ON b.id = d.branch_id
            WHERE b.id = ?
            GROUP BY b.id
        `, [id]);

        if (!branches.length) {
            return res.status(404).json({
                success: false,
                message: 'Филиал не найден'
            });
        }

        // Получаем подразделения филиала
        const departments = await query(`
            SELECT id, name, manager_name
            FROM departments
            WHERE branch_id = ?
            ORDER BY name
        `, [id]);

        // Получаем пользователей филиала
        const users = await query(`
            SELECT 
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.phone,
                r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.branch_id = ? AND u.is_active = TRUE
            ORDER BY u.last_name, u.first_name
        `, [id]);

        res.json({
            success: true,
            data: {
                ...branches[0],
                departments,
                users
            }
        });

    } catch (error) {
        next(error);
    }
});

// Создание нового филиала
router.post('/', requireRole('admin'), async (req, res, next) => {
    try {
        const { error, value } = createBranchSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { name, address, phone, manager_name } = value;

        // Проверяем уникальность названия
        const existingBranches = await query('SELECT id FROM branches WHERE name = ?', [name]);
        if (existingBranches.length > 0) {
            return res.status(400).json({
                success: false,
                message: 'Филиал с таким названием уже существует'
            });
        }

        const result = await query(`
            INSERT INTO branches (name, address, phone, manager_name)
            VALUES (?, ?, ?, ?)
        `, [name, address, phone, manager_name]);

        // Логирование создания филиала
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'create_branch', 'branch', result.insertId,
                JSON.stringify({ name, address, phone, manager_name }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Создан новый филиал', {
            branchId: result.insertId,
            name,
            createdBy: req.user.id
        });

        res.status(201).json({
            success: true,
            message: 'Филиал успешно создан',
            data: {
                id: result.insertId,
                name
            }
        });

    } catch (error) {
        next(error);
    }
});

// Обновление филиала
router.put('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { error, value } = updateBranchSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        // Проверяем существование филиала
        const existingBranches = await query('SELECT * FROM branches WHERE id = ?', [id]);
        if (!existingBranches.length) {
            return res.status(404).json({
                success: false,
                message: 'Филиал не найден'
            });
        }

        const existingBranch = existingBranches[0];

        // Проверяем уникальность названия (исключая текущий филиал)
        if (value.name) {
            const duplicateBranches = await query('SELECT id FROM branches WHERE name = ? AND id != ?', [value.name, id]);
            if (duplicateBranches.length > 0) {
                return res.status(400).json({
                    success: false,
                    message: 'Филиал с таким названием уже существует'
                });
            }
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
            UPDATE branches 
            SET ${updateFields.join(', ')}
            WHERE id = ?
        `, updateValues);

        // Логирование обновления филиала
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_branch', 'branch', id,
                JSON.stringify(value),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Филиал обновлен', {
            branchId: id,
            updatedFields: Object.keys(value),
            updatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Филиал успешно обновлен'
        });

    } catch (error) {
        next(error);
    }
});

// Удаление филиала
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверяем существование филиала
        const branches = await query('SELECT * FROM branches WHERE id = ?', [id]);
        if (!branches.length) {
            return res.status(404).json({
                success: false,
                message: 'Филиал не найден'
            });
        }

        const branch = branches[0];

        // Проверяем, есть ли пользователи в филиале
        const usersCount = await query('SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND is_active = TRUE', [id]);
        if (usersCount[0].count > 0) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя удалить филиал с активными пользователями'
            });
        }

        // Проверяем, есть ли подразделения в филиале
        const departmentsCount = await query('SELECT COUNT(*) as count FROM departments WHERE branch_id = ?', [id]);
        if (departmentsCount[0].count > 0) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя удалить филиал с подразделениями'
            });
        }

        // Деактивируем филиал вместо удаления
        await query('UPDATE branches SET is_active = FALSE, updated_at = NOW() WHERE id = ?', [id]);

        // Логирование деактивации филиала
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'deactivate_branch', 'branch', id,
                JSON.stringify({ name: branch.name }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Филиал деактивирован', {
            branchId: id,
            name: branch.name,
            deactivatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Филиал успешно деактивирован'
        });

    } catch (error) {
        next(error);
    }
});

// Получение статистики филиала
router.get('/:id/stats', async (req, res, next) => {
    try {
        const { id } = req.params;
        const { date_from, date_to } = req.query;

        let whereConditions = ['r.branch_id = ?'];
        let queryParams = [id];

        if (date_from) {
            whereConditions.push('DATE(r.registration_date) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(r.registration_date) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = `WHERE ${whereConditions.join(' AND ')}`;

        const stats = await query(`
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN r.status_id = 3 THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN r.status_id IN (1, 2) THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN r.status_id = 4 THEN 1 ELSE 0 END) as rejected,
                COUNT(DISTINCT r.courier_id) as active_couriers,
                COUNT(DISTINCT r.operator_id) as active_operators
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