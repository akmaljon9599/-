const jwt = require('jsonwebtoken');
const { query } = require('../config/database');

// Middleware для проверки JWT токена
const authenticateToken = async (req, res, next) => {
    try {
        const authHeader = req.headers['authorization'];
        const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

        if (!token) {
            return res.status(401).json({ 
                error: 'Токен доступа не предоставлен' 
            });
        }

        // Проверка токена
        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        
        // Проверка существования пользователя и его активности
        const users = await query(
            'SELECT id, username, email, first_name, last_name, role_id, branch_id, department_id, is_active FROM users WHERE id = ? AND is_active = 1',
            [decoded.userId]
        );

        if (users.length === 0) {
            return res.status(401).json({ 
                error: 'Пользователь не найден или неактивен' 
            });
        }

        // Получение роли пользователя
        const roles = await query(
            'SELECT name, permissions FROM roles WHERE id = ?',
            [users[0].role_id]
        );

        if (roles.length === 0) {
            return res.status(401).json({ 
                error: 'Роль пользователя не найдена' 
            });
        }

        // Добавление информации о пользователе в запрос
        req.user = {
            ...users[0],
            role: roles[0].name,
            permissions: JSON.parse(roles[0].permissions || '{}')
        };

        next();
    } catch (error) {
        if (error.name === 'JsonWebTokenError') {
            return res.status(401).json({ 
                error: 'Недействительный токен' 
            });
        }
        if (error.name === 'TokenExpiredError') {
            return res.status(401).json({ 
                error: 'Токен истек' 
            });
        }
        
        console.error('Auth middleware error:', error);
        return res.status(500).json({ 
            error: 'Ошибка аутентификации' 
        });
    }
};

// Middleware для проверки ролей
const requireRole = (allowedRoles) => {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({ 
                error: 'Пользователь не аутентифицирован' 
            });
        }

        if (Array.isArray(allowedRoles)) {
            if (!allowedRoles.includes(req.user.role)) {
                return res.status(403).json({ 
                    error: 'Недостаточно прав доступа' 
                });
            }
        } else {
            if (req.user.role !== allowedRoles) {
                return res.status(403).json({ 
                    error: 'Недостаточно прав доступа' 
                });
            }
        }

        next();
    };
};

// Middleware для проверки разрешений
const requirePermission = (permission) => {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({ 
                error: 'Пользователь не аутентифицирован' 
            });
        }

        // Администратор имеет все права
        if (req.user.role === 'admin' || req.user.permissions.all) {
            return next();
        }

        // Проверка конкретного разрешения
        if (!req.user.permissions[permission]) {
            return res.status(403).json({ 
                error: `Недостаточно прав: требуется разрешение '${permission}'` 
            });
        }

        next();
    };
};

// Middleware для проверки доступа к филиалу/подразделению
const requireBranchAccess = async (req, res, next) => {
    try {
        if (!req.user) {
            return res.status(401).json({ 
                error: 'Пользователь не аутентифицирован' 
            });
        }

        // Администратор имеет доступ ко всем филиалам
        if (req.user.role === 'admin') {
            return next();
        }

        // Получение ID филиала из параметров запроса
        const branchId = req.params.branchId || req.body.branch_id || req.query.branch_id;
        
        if (!branchId) {
            return next(); // Если ID филиала не указан, пропускаем проверку
        }

        // Проверка доступа к филиалу
        if (req.user.branch_id && req.user.branch_id != branchId) {
            return res.status(403).json({ 
                error: 'Доступ к данному филиалу запрещен' 
            });
        }

        next();
    } catch (error) {
        console.error('Branch access middleware error:', error);
        return res.status(500).json({ 
            error: 'Ошибка проверки доступа к филиалу' 
        });
    }
};

module.exports = {
    authenticateToken,
    requireRole,
    requirePermission,
    requireBranchAccess
};