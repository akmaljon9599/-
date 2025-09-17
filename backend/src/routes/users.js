const express = require('express');
const bcrypt = require('bcryptjs');
const Joi = require('joi');
const { query } = require('../config/database');
const { requireRole, requirePermission } = require('../middleware/auth');

const router = express.Router();

// Схемы валидации
const createUserSchema = Joi.object({
    username: Joi.string().alphanum().min(3).max(50).required(),
    email: Joi.string().email().required(),
    password: Joi.string().min(6).required(),
    first_name: Joi.string().min(2).max(50).required(),
    last_name: Joi.string().min(2).max(50).required(),
    middle_name: Joi.string().max(50).optional(),
    phone: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).optional(),
    role_id: Joi.number().integer().positive().required(),
    branch_id: Joi.number().integer().positive().optional(),
    department_id: Joi.number().integer().positive().optional()
});

const updateUserSchema = Joi.object({
    username: Joi.string().alphanum().min(3).max(50).optional(),
    email: Joi.string().email().optional(),
    first_name: Joi.string().min(2).max(50).optional(),
    last_name: Joi.string().min(2).max(50).optional(),
    middle_name: Joi.string().max(50).optional(),
    phone: Joi.string().pattern(/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/).optional(),
    role_id: Joi.number().integer().positive().optional(),
    branch_id: Joi.number().integer().positive().optional(),
    department_id: Joi.number().integer().positive().optional(),
    is_active: Joi.boolean().optional()
});

// GET /api/users - Получение списка пользователей
router.get('/', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { role, branch_id, department_id, is_active } = req.query;

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        // Ограничение доступа для старших курьеров
        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        if (role) {
            whereConditions.push('r.name = ?');
            queryParams.push(role);
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

        const users = await query(`
            SELECT 
                u.id, u.username, u.email, u.first_name, u.last_name, u.middle_name,
                u.phone, u.role_id, u.branch_id, u.department_id, u.is_active,
                u.last_login, u.created_at, u.updated_at,
                r.name as role_name,
                b.name as branch_name,
                d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            ${whereClause}
            ORDER BY u.last_name, u.first_name
        `, queryParams);

        const formattedUsers = users.map(user => ({
            id: user.id,
            username: user.username,
            email: user.email,
            firstName: user.first_name,
            lastName: user.last_name,
            middleName: user.middle_name,
            phone: user.phone,
            role: {
                id: user.role_id,
                name: user.role_name
            },
            branch: user.branch_id ? {
                id: user.branch_id,
                name: user.branch_name
            } : null,
            department: user.department_id ? {
                id: user.department_id,
                name: user.department_name
            } : null,
            isActive: user.is_active,
            lastLogin: user.last_login,
            createdAt: user.created_at,
            updatedAt: user.updated_at
        }));

        res.json({ users: formattedUsers });

    } catch (error) {
        next(error);
    }
});

// GET /api/users/:id - Получение пользователя по ID
router.get('/:id', requireRole(['admin', 'senior_courier']), async (req, res, next) => {
    try {
        const { id } = req.params;

        const users = await query(`
            SELECT 
                u.id, u.username, u.email, u.first_name, u.last_name, u.middle_name,
                u.phone, u.role_id, u.branch_id, u.department_id, u.is_active,
                u.last_login, u.created_at, u.updated_at,
                r.name as role_name, r.permissions,
                b.name as branch_name,
                d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
        `, [id]);

        if (users.length === 0) {
            return res.status(404).json({
                error: 'Пользователь не найден'
            });
        }

        const user = users[0];

        // Проверка прав доступа
        if (req.user.role === 'senior_courier' && req.user.branch_id !== user.branch_id) {
            return res.status(403).json({
                error: 'Доступ к данному пользователю запрещен'
            });
        }

        const formattedUser = {
            id: user.id,
            username: user.username,
            email: user.email,
            firstName: user.first_name,
            lastName: user.last_name,
            middleName: user.middle_name,
            phone: user.phone,
            role: {
                id: user.role_id,
                name: user.role_name,
                permissions: JSON.parse(user.permissions || '{}')
            },
            branch: user.branch_id ? {
                id: user.branch_id,
                name: user.branch_name
            } : null,
            department: user.department_id ? {
                id: user.department_id,
                name: user.department_name
            } : null,
            isActive: user.is_active,
            lastLogin: user.last_login,
            createdAt: user.created_at,
            updatedAt: user.updated_at
        };

        res.json({ user: formattedUser });

    } catch (error) {
        next(error);
    }
});

// POST /api/users - Создание нового пользователя
router.post('/', requireRole('admin'), async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = createUserSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка уникальности username и email
        const existingUsers = await query(`
            SELECT id FROM users WHERE username = ? OR email = ?
        `, [value.username, value.email]);

        if (existingUsers.length > 0) {
            return res.status(409).json({
                error: 'Пользователь с таким именем или email уже существует'
            });
        }

        // Хеширование пароля
        const saltRounds = 10;
        const passwordHash = await bcrypt.hash(value.password, saltRounds);

        // Создание пользователя
        const result = await query(`
            INSERT INTO users (
                username, email, password_hash, first_name, last_name, middle_name,
                phone, role_id, branch_id, department_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `, [
            value.username,
            value.email,
            passwordHash,
            value.first_name,
            value.last_name,
            value.middle_name,
            value.phone,
            value.role_id,
            value.branch_id,
            value.department_id
        ]);

        res.status(201).json({
            message: 'Пользователь успешно создан',
            userId: result.insertId
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/users/:id - Обновление пользователя
router.put('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования пользователя
        const existingUsers = await query('SELECT * FROM users WHERE id = ?', [id]);
        if (existingUsers.length === 0) {
            return res.status(404).json({
                error: 'Пользователь не найден'
            });
        }

        // Валидация входных данных
        const { error, value } = updateUserSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка уникальности username и email (исключая текущего пользователя)
        if (value.username || value.email) {
            const checkConditions = [];
            const checkParams = [];

            if (value.username) {
                checkConditions.push('username = ?');
                checkParams.push(value.username);
            }

            if (value.email) {
                checkConditions.push('email = ?');
                checkParams.push(value.email);
            }

            checkParams.push(id);

            const existingUsers = await query(`
                SELECT id FROM users WHERE (${checkConditions.join(' OR ')}) AND id != ?
            `, checkParams);

            if (existingUsers.length > 0) {
                return res.status(409).json({
                    error: 'Пользователь с таким именем или email уже существует'
                });
            }
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

        // Обновление пользователя
        await query(`
            UPDATE users 
            SET ${updateFields.join(', ')}, updated_at = NOW()
            WHERE id = ?
        `, updateValues);

        res.json({
            message: 'Пользователь успешно обновлен'
        });

    } catch (error) {
        next(error);
    }
});

// DELETE /api/users/:id - Удаление пользователя
router.delete('/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования пользователя
        const existingUsers = await query('SELECT id FROM users WHERE id = ?', [id]);
        if (existingUsers.length === 0) {
            return res.status(404).json({
                error: 'Пользователь не найден'
            });
        }

        // Проверка, что пользователь не удаляет сам себя
        if (parseInt(id) === req.user.id) {
            return res.status(400).json({
                error: 'Нельзя удалить самого себя'
            });
        }

        // Мягкое удаление (деактивация)
        await query(`
            UPDATE users 
            SET is_active = 0, updated_at = NOW()
            WHERE id = ?
        `, [id]);

        res.json({
            message: 'Пользователь успешно деактивирован'
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/users/roles - Получение списка ролей
router.get('/roles/list', async (req, res, next) => {
    try {
        const roles = await query(`
            SELECT id, name, description, permissions
            FROM roles
            ORDER BY name
        `);

        const formattedRoles = roles.map(role => ({
            id: role.id,
            name: role.name,
            description: role.description,
            permissions: JSON.parse(role.permissions || '{}')
        }));

        res.json({ roles: formattedRoles });

    } catch (error) {
        next(error);
    }
});

// GET /api/users/couriers - Получение списка курьеров
router.get('/couriers/list', async (req, res, next) => {
    try {
        const { branch_id } = req.query;

        // Построение условий фильтрации
        let whereConditions = ['u.role_id = (SELECT id FROM roles WHERE name = "courier")', 'u.is_active = 1'];
        let queryParams = [];

        if (branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(branch_id);
        }

        // Ограничение доступа для старших курьеров
        if (req.user.role === 'senior_courier' && req.user.branch_id) {
            whereConditions.push('u.branch_id = ?');
            queryParams.push(req.user.branch_id);
        }

        const whereClause = whereConditions.join(' AND ');

        const couriers = await query(`
            SELECT 
                u.id, u.first_name, u.last_name, u.middle_name, u.phone,
                b.name as branch_name, d.name as department_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE ${whereClause}
            ORDER BY u.last_name, u.first_name
        `, queryParams);

        const formattedCouriers = couriers.map(courier => ({
            id: courier.id,
            name: `${courier.last_name} ${courier.first_name} ${courier.middle_name || ''}`.trim(),
            phone: courier.phone,
            branch: courier.branch_name,
            department: courier.department_name
        }));

        res.json({ couriers: formattedCouriers });

    } catch (error) {
        next(error);
    }
});

module.exports = router;