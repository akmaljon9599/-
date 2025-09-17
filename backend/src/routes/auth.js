const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const Joi = require('joi');
const { query } = require('../config/database');
const { authenticateToken } = require('../middleware/auth');

const router = express.Router();

// Схемы валидации
const loginSchema = Joi.object({
    username: Joi.string().alphanum().min(3).max(50).required(),
    password: Joi.string().min(6).required()
});

const changePasswordSchema = Joi.object({
    currentPassword: Joi.string().required(),
    newPassword: Joi.string().min(6).required(),
    confirmPassword: Joi.string().valid(Joi.ref('newPassword')).required()
});

// POST /api/auth/login - Вход в систему
router.post('/login', async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = loginSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        const { username, password } = value;

        // Поиск пользователя
        const users = await query(
            `SELECT u.id, u.username, u.email, u.password_hash, u.first_name, u.last_name, 
                    u.middle_name, u.phone, u.role_id, u.branch_id, u.department_id, u.is_active,
                    r.name as role_name, r.permissions,
                    b.name as branch_name, d.name as department_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             LEFT JOIN branches b ON u.branch_id = b.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.username = ? AND u.is_active = 1`,
            [username]
        );

        if (users.length === 0) {
            return res.status(401).json({
                error: 'Неверное имя пользователя или пароль'
            });
        }

        const user = users[0];

        // Проверка пароля
        const isValidPassword = await bcrypt.compare(password, user.password_hash);
        if (!isValidPassword) {
            return res.status(401).json({
                error: 'Неверное имя пользователя или пароль'
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

        // Подготовка данных пользователя для ответа
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
            } : null
        };

        res.json({
            message: 'Успешный вход в систему',
            token,
            user: userData
        });

    } catch (error) {
        next(error);
    }
});

// POST /api/auth/logout - Выход из системы
router.post('/logout', authenticateToken, async (req, res, next) => {
    try {
        // В реальном приложении здесь можно добавить логику
        // для добавления токена в черный список или удаления из сессии
        
        res.json({
            message: 'Успешный выход из системы'
        });
    } catch (error) {
        next(error);
    }
});

// GET /api/auth/me - Получение информации о текущем пользователе
router.get('/me', authenticateToken, async (req, res, next) => {
    try {
        const userData = {
            id: req.user.id,
            username: req.user.username,
            email: req.user.email,
            firstName: req.user.first_name,
            lastName: req.user.last_name,
            middleName: req.user.middle_name,
            phone: req.user.phone,
            role: {
                name: req.user.role,
                permissions: req.user.permissions
            },
            branch: req.user.branch_id,
            department: req.user.department_id
        };

        res.json({
            user: userData
        });
    } catch (error) {
        next(error);
    }
});

// POST /api/auth/change-password - Смена пароля
router.post('/change-password', authenticateToken, async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = changePasswordSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        const { currentPassword, newPassword } = value;

        // Получение текущего пароля пользователя
        const users = await query(
            'SELECT password_hash FROM users WHERE id = ?',
            [req.user.id]
        );

        if (users.length === 0) {
            return res.status(404).json({
                error: 'Пользователь не найден'
            });
        }

        // Проверка текущего пароля
        const isValidPassword = await bcrypt.compare(currentPassword, users[0].password_hash);
        if (!isValidPassword) {
            return res.status(400).json({
                error: 'Неверный текущий пароль'
            });
        }

        // Хеширование нового пароля
        const saltRounds = 10;
        const newPasswordHash = await bcrypt.hash(newPassword, saltRounds);

        // Обновление пароля в базе данных
        await query(
            'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
            [newPasswordHash, req.user.id]
        );

        res.json({
            message: 'Пароль успешно изменен'
        });

    } catch (error) {
        next(error);
    }
});

// POST /api/auth/refresh - Обновление токена
router.post('/refresh', authenticateToken, async (req, res, next) => {
    try {
        // Создание нового токена
        const newToken = jwt.sign(
            { 
                userId: req.user.id, 
                username: req.user.username,
                role: req.user.role 
            },
            process.env.JWT_SECRET,
            { expiresIn: process.env.JWT_EXPIRES_IN || '24h' }
        );

        res.json({
            message: 'Токен обновлен',
            token: newToken
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;