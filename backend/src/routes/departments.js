const express = require('express');
const Joi = require('joi');
const { query } = require('../../config/database');
const { requireRole } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const createDepartmentSchema = Joi.object({
    name: Joi.string().required().max(100).messages({
        'string.empty': 'Название подразделения обязательно',
        'string.max': 'Название подразделения не должно превышать 100 символов',
        'any.required': 'Название подразделения обязательно'
    }),
    branch_id: Joi.number().integer().positive().required().messages({
        'any.required': 'Филиал обязателен'
    }),
    manager_name: Joi.string().optional().max(100)
});

const updateDepartmentSchema = createDepartmentSchema.fork(['name', 'branch_id'], (schema) => schema.optional());

// Получение списка подразделений
router.get('/', async (req, res, next) => {
    try {
        const { branch_id } = req.query;

        let whereClause = '';
        let queryParams = [];

        if (branch_id) {
            whereClause = 'WHERE d.branch_id = ?';
            queryParams.push(branch_id);
        }

        const departments = await query(`
            SELECT 
                d.*,
                b.name as branch_name,
                COUNT(u.id) as user_count
            FROM departments d
            LEFT JOIN branches b ON d.branch_id = b.id
            LEFT JOIN users u ON d.id = u.department_id AND u.is_active = TRUE
            ${whereClause}
            GROUP BY d.id
            ORDER BY b.name, d.name
        `, queryParams);

        res.json({
            success: true,
            data: departments
        });

    } catch (error) {
        next(error);
    }
});

// Получение подразделения по ID
router.get('/:id', async (req, res, next) => {
    try {
        const { id } = req.params;

        const departments = await query(`
            SELECT 
                d.*,
                b.name as branch_name
            FROM departments d
            LEFT JOIN branches b ON d.branch_id = b.id
            WHERE d.id = ?
        `, [id]);

        if (!departments.length) {
            return res.status(404).json({
                success: false,
                message: 'Подразделение не найдено'
            });
        }

        // Получаем пользователей подразделения
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
            WHERE u.department_id = ? AND u.is_active = TRUE
            ORDER BY u.last_name, u.first_name
        `, [id]);

        res.json({
            success: true,
            data: {
                ...departments[0],
                users
            }
        });

    } catch (error) {
        next(error);
    }
});

// Создание нового подразделения
router.post('/', requireRole('admin'), async (req, res, next) => {
    try {
        const { error, value } = createDepartmentSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { name, branch_id, manager_name } = value;

        // Проверяем существование филиала
        const branches = await query('SELECT id FROM branches WHERE id = ? AND is_active = TRUE', [branch_id]);
        if (!branches.length) {
            return res.status(400).json({
                success: false,
                message: 'Филиал не найден или неактивен'
            });
        }

        // Проверяем уникальность названия в рамках филиала
        const existingDepartments = await query('SELECT id FROM departments WHERE name = ? AND branch_id = ?', [name, branch_id]);
        if (existingDepartments.length > 0) {
            return res.status(400).json({
                success: false,
                message: 'Подразделение с таким названием уже существует в данном филиале'
            });
        }

        const result = await query(`
            INSERT INTO departments (name, branch_id, manager_name)
            VALUES (?, ?, ?)
        `, [name, branch_id, manager_name]);

        // Логирование создания подразделения
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'create_department', 'department', result.insertId,
                JSON.stringify({ name, branch_id, manager_name }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Создано новое подразделение', {
            departmentId: result.insertId,
            name,
            branchId: branch_id,
            createdBy: req.user.id
        });

        res.status(201).json({
            success: true,
            message: 'Подразделение успешно создано',
            data: {
                id: result.insertId,
                name
            }
        });

    } catch (error) {
        next(error);
    }
});

// Обновление подразделения
router.put('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { error, value } = updateDepartmentSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        // Проверяем существование подразделения
        const existingDepartments = await query('SELECT * FROM departments WHERE id = ?', [id]);
        if (!existingDepartments.length) {
            return res.status(404).json({
                success: false,
                message: 'Подразделение не найдено'
            });
        }

        const existingDepartment = existingDepartments[0];

        // Проверяем существование филиала (если указан)
        if (value.branch_id) {
            const branches = await query('SELECT id FROM branches WHERE id = ? AND is_active = TRUE', [value.branch_id]);
            if (!branches.length) {
                return res.status(400).json({
                    success: false,
                    message: 'Филиал не найден или неактивен'
                });
            }
        }

        // Проверяем уникальность названия в рамках филиала
        if (value.name) {
            const duplicateDepartments = await query(
                'SELECT id FROM departments WHERE name = ? AND branch_id = ? AND id != ?',
                [value.name, value.branch_id || existingDepartment.branch_id, id]
            );
            if (duplicateDepartments.length > 0) {
                return res.status(400).json({
                    success: false,
                    message: 'Подразделение с таким названием уже существует в данном филиале'
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
            UPDATE departments 
            SET ${updateFields.join(', ')}
            WHERE id = ?
        `, updateValues);

        // Логирование обновления подразделения
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_department', 'department', id,
                JSON.stringify(value),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Подразделение обновлено', {
            departmentId: id,
            updatedFields: Object.keys(value),
            updatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Подразделение успешно обновлено'
        });

    } catch (error) {
        next(error);
    }
});

// Удаление подразделения
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверяем существование подразделения
        const departments = await query('SELECT * FROM departments WHERE id = ?', [id]);
        if (!departments.length) {
            return res.status(404).json({
                success: false,
                message: 'Подразделение не найдено'
            });
        }

        const department = departments[0];

        // Проверяем, есть ли пользователи в подразделении
        const usersCount = await query('SELECT COUNT(*) as count FROM users WHERE department_id = ? AND is_active = TRUE', [id]);
        if (usersCount[0].count > 0) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя удалить подразделение с активными пользователями'
            });
        }

        await query('DELETE FROM departments WHERE id = ?', [id]);

        // Логирование удаления подразделения
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'delete_department', 'department', id,
                JSON.stringify({ name: department.name, branch_id: department.branch_id }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Подразделение удалено', {
            departmentId: id,
            name: department.name,
            deletedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Подразделение успешно удалено'
        });

    } catch (error) {
        next(error);
    }
});

// Получение статистики подразделения
router.get('/:id/stats', async (req, res, next) => {
    try {
        const { id } = req.params;
        const { date_from, date_to } = req.query;

        let whereConditions = ['r.department_id = ?'];
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