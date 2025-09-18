const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const Joi = require('joi');
const { query } = require('../../config/database');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const loginSchema = Joi.object({
    username: Joi.string().required().messages({
        'string.empty': 'Имя пользователя обязательно',
        'any.required': 'Имя пользователя обязательно'
    }),
    password: Joi.string().required().messages({
        'string.empty': 'Пароль обязателен',
        'any.required': 'Пароль обязателен'
    })
});

const changePasswordSchema = Joi.object({
    currentPassword: Joi.string().required().messages({
        'string.empty': 'Текущий пароль обязателен',
        'any.required': 'Текущий пароль обязателен'
    }),
    newPassword: Joi.string().min(6).required().messages({
        'string.min': 'Новый пароль должен содержать минимум 6 символов',
        'string.empty': 'Новый пароль обязателен',
        'any.required': 'Новый пароль обязателен'
    })
});

// Вход в систему
router.post('/login', async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = loginSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { username, password } = value;

        // Поиск пользователя
        const users = await query(
            `SELECT u.*, r.name as role_name, r.permissions, b.name as branch_name, d.name as department_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             LEFT JOIN branches b ON u.branch_id = b.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.username = ? AND u.is_active = TRUE`,
            [username]
        );

        if (!users.length) {
            logger.warn('Попытка входа с несуществующим пользователем', { username, ip: req.ip });
            return res.status(401).json({
                success: false,
                message: 'Неверное имя пользователя или пароль'
            });
        }

        const user = users[0];

        // Проверка пароля
        const isPasswordValid = await bcrypt.compare(password, user.password_hash);
        if (!isPasswordValid) {
            logger.warn('Попытка входа с неверным паролем', { username, ip: req.ip });
            return res.status(401).json({
                success: false,
                message: 'Неверное имя пользователя или пароль'
            });
        }

        // Обновление времени последнего входа
        await query(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [user.id]
        );

        // Создание JWT токена
        const token = jwt.sign(
            { 
                userId: user.id,
                username: user.username,
                role: user.role_name
            },
            process.env.JWT_SECRET,
            { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
        );

        // Логирование успешного входа
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
            [user.id, 'login', 'user', req.ip, req.get('User-Agent')]
        );

        logger.info('Успешный вход в систему', { userId: user.id, username, role: user.role_name });

        // Возврат данных пользователя (без пароля)
        const userData = {
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
            lastLogin: user.last_login
        };

        res.json({
            success: true,
            message: 'Успешный вход в систему',
            data: {
                user: userData,
                token
            }
        });

    } catch (error) {
        next(error);
    }
});

// Выход из системы
router.post('/logout', async (req, res, next) => {
    try {
        const token = req.header('Authorization')?.replace('Bearer ', '');
        
        if (token) {
            try {
                const decoded = jwt.verify(token, process.env.JWT_SECRET);
                
                // Логирование выхода
                await query(
                    'INSERT INTO system_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
                    [decoded.userId, 'logout', 'user', req.ip, req.get('User-Agent')]
                );

                logger.info('Выход из системы', { userId: decoded.userId });
            } catch (error) {
                // Токен недействителен, но это не критично для выхода
                logger.warn('Попытка выхода с недействительным токеном', { error: error.message });
            }
        }

        res.json({
            success: true,
            message: 'Успешный выход из системы'
        });

    } catch (error) {
        next(error);
    }
});

// Получение информации о текущем пользователе
router.get('/me', async (req, res, next) => {
    try {
        const token = req.header('Authorization')?.replace('Bearer ', '');
        
        if (!token) {
            return res.status(401).json({
                success: false,
                message: 'Токен доступа не предоставлен'
            });
        }

        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        
        const users = await query(
            `SELECT u.*, r.name as role_name, r.permissions, b.name as branch_name, d.name as department_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             LEFT JOIN branches b ON u.branch_id = b.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = ? AND u.is_active = TRUE`,
            [decoded.userId]
        );

        if (!users.length) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        const user = users[0];

        const userData = {
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
            lastLogin: user.last_login,
            createdAt: user.created_at
        };

        res.json({
            success: true,
            data: userData
        });

    } catch (error) {
        next(error);
    }
});

// Смена пароля
router.post('/change-password', async (req, res, next) => {
    try {
        const token = req.header('Authorization')?.replace('Bearer ', '');
        
        if (!token) {
            return res.status(401).json({
                success: false,
                message: 'Токен доступа не предоставлен'
            });
        }

        const decoded = jwt.verify(token, process.env.JWT_SECRET);

        // Валидация входных данных
        const { error, value } = changePasswordSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { currentPassword, newPassword } = value;

        // Получение текущего пароля пользователя
        const users = await query(
            'SELECT password_hash FROM users WHERE id = ? AND is_active = TRUE',
            [decoded.userId]
        );

        if (!users.length) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не найден'
            });
        }

        // Проверка текущего пароля
        const isCurrentPasswordValid = await bcrypt.compare(currentPassword, users[0].password_hash);
        if (!isCurrentPasswordValid) {
            return res.status(400).json({
                success: false,
                message: 'Неверный текущий пароль'
            });
        }

        // Хеширование нового пароля
        const saltRounds = parseInt(process.env.BCRYPT_ROUNDS) || 12;
        const newPasswordHash = await bcrypt.hash(newPassword, saltRounds);

        // Обновление пароля
        await query(
            'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
            [newPasswordHash, decoded.userId]
        );

        // Логирование смены пароля
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
            [decoded.userId, 'change_password', 'user', req.ip, req.get('User-Agent')]
        );

        logger.info('Пароль изменен', { userId: decoded.userId });

        res.json({
            success: true,
            message: 'Пароль успешно изменен'
        });

    } catch (error) {
        next(error);
    }
});

// Обновление токена
router.post('/refresh', async (req, res, next) => {
    try {
        const token = req.header('Authorization')?.replace('Bearer ', '');
        
        if (!token) {
            return res.status(401).json({
                success: false,
                message: 'Токен доступа не предоставлен'
            });
        }

        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        
        // Проверяем, что пользователь все еще активен
        const users = await query(
            'SELECT id, username, role_id FROM users WHERE id = ? AND is_active = TRUE',
            [decoded.userId]
        );

        if (!users.length) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не найден или деактивирован'
            });
        }

        const user = users[0];

        // Создаем новый токен
        const newToken = jwt.sign(
            { 
                userId: user.id,
                username: user.username,
                role: decoded.role
            },
            process.env.JWT_SECRET,
            { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
        );

        res.json({
            success: true,
            data: {
                token: newToken
            }
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;