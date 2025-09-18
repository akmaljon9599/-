const express = require('express');
const bcrypt = require('bcryptjs');
const Joi = require('joi');
const { query } = require('../../config/database');
const { requireRole, requirePermission } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const createUserSchema = Joi.object({
    username: Joi.string().required().min(3).max(50).alphanum().messages({
        'string.empty': 'Имя пользователя обязательно',
        'string.min': 'Имя пользователя должно содержать минимум 3 символа',
        'string.max': 'Имя пользователя не должно превышать 50 символов',
        'string.alphanum': 'Имя пользователя должно содержать только буквы и цифры',
        'any.required': 'Имя пользователя обязательно'
    }),
    email: Joi.string().required().email().messages({
        'string.empty': 'Email обязателен',
        'string.email': 'Некорректный формат email',
        'any.required': 'Email обязателен'
    }),
    password: Joi.string().required().min(6).messages({
        'string.empty': 'Пароль обязателен',
        'string.min': 'Пароль должен содержать минимум 6 символов',
        'any.required': 'Пароль обязателен'
    }),
    first_name: Joi.string().required().max(50).messages({
        'string.empty': 'Имя обязательно',
        'string.max': 'Имя не должно превышать 50 символов',
        'any.required': 'Имя обязательно'
    }),
    last_name: Joi.string().required().max(50).messages({
        'string.empty': 'Фамилия обязательна',
        'string.max': 'Фамилия не должна превышать 50 символов',
        'any.required': 'Фамилия обязательна'
    }),
    middle_name: Joi.string().optional().max(50),
    phone: Joi.string().optional().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).messages({
        'string.pattern.base': 'Некорректный формат номера телефона'
    }),
    role_id: Joi.number().integer().positive().required().messages({
        'any.required': 'Роль обязательна'
    }),
    branch_id: Joi.number().integer().positive().optional(),
    department_id: Joi.number().integer().positive().optional()
});

const updateUserSchema = createUserSchema.fork(['username', 'email', 'password', 'first_name', 'last_name', 'role_id'], (schema) => schema.optional());

// Получение списка пользователей
router.get('/', requireRole('admin'), async (req, res, next) => {
    try {
        const { role_id, branch_id, department_id, is_active, page = 1, limit = 20 } = req.query;

        let whereConditions = [];
        let queryParams = [];

        if (role_id) {
            whereConditions.push('u.role_id = ?');
            queryParams.push(role_id);
        }

        if (branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(branch_id);
        }

        if (department_id) {
            whereConditions.push('u.department_id = ?');
            queryParams.push(department_id);
        }

        if (is_active !== undefined) {
            whereConditions.push('u.is_active = ?');
            queryParams.push(is_active === 'true');
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        // Подсчет общего количества
        const countResult = await query(`
            SELECT COUNT(*) as total
            FROM users u
            ${whereClause}
        `, queryParams);
        const total = countResult[0].total;

        // Получение данных с пагинацией
        const offset = (page - 1) * limit;
        const users = await query(`
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.middle_name,
                u.phone,
                u.is_active,
                u.last_login,
                u.created_at,
                r.name as role_name,
                b.name as branch_name,
                d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            ${whereClause}
            ORDER BY u.last_name, u.first_name
            LIMIT ? OFFSET ?
        `, [...queryParams, parseInt(limit), offset]);

        res.json({
            success: true,
            data: users,
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

// Получение пользователя по ID
router.get('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        const users = await query(`
            SELECT 
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.middle_name,
                u.phone,
                u.is_active,
                u.last_login,
                u.created_at,
                r.name as role_name,
                r.permissions,
                b.name as branch_name,
                d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
        `, [id]);

        if (!users.length) {
            return res.status(404).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        const user = users[0];

        res.json({
            success: true,
            data: {
                ...user,
                permissions: JSON.parse(user.permissions || '{}')
            }
        });

    } catch (error) {
        next(error);
    }
});

// Создание нового пользователя
router.post('/', requireRole('admin'), async (req, res, next) => {
    try {
        const { error, value } = createUserSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const {
            username,
            email,
            password,
            first_name,
            last_name,
            middle_name,
            phone,
            role_id,
            branch_id,
            department_id
        } = value;

        // Проверяем уникальность username и email
        const existingUsers = await query(`
            SELECT id FROM users WHERE username = ? OR email = ?
        `, [username, email]);

        if (existingUsers.length > 0) {
            return res.status(400).json({
                success: false,
                message: 'Пользователь с таким именем или email уже существует'
            });
        }

        // Проверяем существование роли
        const roles = await query('SELECT id FROM roles WHERE id = ?', [role_id]);
        if (!roles.length) {
            return res.status(400).json({
                success: false,
                message: 'Роль не найдена'
            });
        }

        // Хешируем пароль
        const saltRounds = parseInt(process.env.BCRYPT_ROUNDS) || 12;
        const passwordHash = await bcrypt.hash(password, saltRounds);

        // Создаем пользователя
        const result = await query(`
            INSERT INTO users (
                username, email, password_hash, first_name, last_name, middle_name,
                phone, role_id, branch_id, department_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            username, email, passwordHash, first_name, last_name, middle_name,
            phone, role_id, branch_id, department_id
        ]);

        // Логирование создания пользователя
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'create_user', 'user', result.insertId,
                JSON.stringify({ username, email, role_id }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Создан новый пользователь', {
            userId: result.insertId,
            username,
            email,
            roleId: role_id,
            createdBy: req.user.id
        });

        res.status(201).json({
            success: true,
            message: 'Пользователь успешно создан',
            data: {
                id: result.insertId,
                username,
                email
            }
        });

    } catch (error) {
        next(error);
    }
});

// Обновление пользователя
router.put('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;
        const { error, value } = updateUserSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        // Проверяем существование пользователя
        const existingUsers = await query('SELECT * FROM users WHERE id = ?', [id]);
        if (!existingUsers.length) {
            return res.status(404).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        const existingUser = existingUsers[0];

        // Проверяем уникальность username и email (исключая текущего пользователя)
        if (value.username || value.email) {
            const duplicateUsers = await query(`
                SELECT id FROM users 
                WHERE (username = ? OR email = ?) AND id != ?
            `, [value.username || existingUser.username, value.email || existingUser.email, id]);

            if (duplicateUsers.length > 0) {
                return res.status(400).json({
                    success: false,
                    message: 'Пользователь с таким именем или email уже существует'
                });
            }
        }

        // Подготовка полей для обновления
        const updateFields = [];
        const updateValues = [];

        Object.keys(value).forEach(key => {
            if (value[key] !== undefined) {
                if (key === 'password') {
                    // Хешируем новый пароль
                    const saltRounds = parseInt(process.env.BCRYPT_ROUNDS) || 12;
                    updateFields.push('password_hash = ?');
                    updateValues.push(bcrypt.hashSync(value[key], saltRounds));
                } else {
                    updateFields.push(`${key} = ?`);
                    updateValues.push(value[key]);
                }
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
            UPDATE users 
            SET ${updateFields.join(', ')}
            WHERE id = ?
        `, updateValues);

        // Логирование обновления пользователя
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_user', 'user', id,
                JSON.stringify(value),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Пользователь обновлен', {
            userId: id,
            updatedFields: Object.keys(value),
            updatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Пользователь успешно обновлен'
        });

    } catch (error) {
        next(error);
    }
});

// Удаление пользователя
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверяем существование пользователя
        const users = await query('SELECT * FROM users WHERE id = ?', [id]);
        if (!users.length) {
            return res.status(404).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        const user = users[0];

        // Нельзя удалить самого себя
        if (parseInt(id) === req.user.id) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя удалить самого себя'
            });
        }

        // Проверяем, есть ли у пользователя активные заявки
        const activeRequests = await query(`
            SELECT COUNT(*) as count FROM requests 
            WHERE (courier_id = ? OR operator_id = ?) AND status_id IN (1, 2)
        `, [id, id]);

        if (activeRequests[0].count > 0) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя удалить пользователя с активными заявками'
            });
        }

        // Деактивируем пользователя вместо удаления
        await query('UPDATE users SET is_active = FALSE, updated_at = NOW() WHERE id = ?', [id]);

        // Логирование деактивации пользователя
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'deactivate_user', 'user', id,
                JSON.stringify({ username: user.username, email: user.email }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Пользователь деактивирован', {
            userId: id,
            username: user.username,
            deactivatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Пользователь успешно деактивирован'
        });

    } catch (error) {
        next(error);
    }
});

// Активация/деактивация пользователя
router.patch('/:id/toggle-status', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверяем существование пользователя
        const users = await query('SELECT * FROM users WHERE id = ?', [id]);
        if (!users.length) {
            return res.status(404).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        const user = users[0];

        // Нельзя изменить статус самого себя
        if (parseInt(id) === req.user.id) {
            return res.status(400).json({
                success: false,
                message: 'Нельзя изменить статус самого себя'
            });
        }

        const newStatus = !user.is_active;
        await query('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?', [newStatus, id]);

        // Логирование изменения статуса
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'toggle_user_status', 'user', id,
                JSON.stringify({ old_status: user.is_active, new_status: newStatus }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Статус пользователя изменен', {
            userId: id,
            oldStatus: user.is_active,
            newStatus,
            changedBy: req.user.id
        });

        res.json({
            success: true,
            message: `Пользователь ${newStatus ? 'активирован' : 'деактивирован'}`
        });

    } catch (error) {
        next(error);
    }
});

// Получение ролей
router.get('/roles/list', requireRole('admin'), async (req, res, next) => {
    try {
        const roles = await query(`
            SELECT id, name, description, permissions
            FROM roles
            ORDER BY name
        `);

        const rolesWithParsedPermissions = roles.map(role => ({
            ...role,
            permissions: JSON.parse(role.permissions || '{}')
        }));

        res.json({
            success: true,
            data: rolesWithParsedPermissions
        });

    } catch (error) {
        next(error);
    }
});

// Получение статистики пользователей
router.get('/stats/overview', requireRole('admin'), async (req, res, next) => {
    try {
        const stats = await query(`
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN is_active = FALSE THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_last_week,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_last_month
            FROM users
        `);

        const roleStats = await query(`
            SELECT 
                r.name as role_name,
                COUNT(u.id) as user_count
            FROM roles r
            LEFT JOIN users u ON r.id = u.role_id AND u.is_active = TRUE
            GROUP BY r.id, r.name
            ORDER BY user_count DESC
        `);

        res.json({
            success: true,
            data: {
                ...stats[0],
                by_role: roleStats
            }
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;