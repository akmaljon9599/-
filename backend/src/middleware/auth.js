const jwt = require('jsonwebtoken');
const { query } = require('../../config/database');
const logger = require('../utils/logger');

const authMiddleware = async (req, res, next) => {
    try {
        const token = req.header('Authorization')?.replace('Bearer ', '');
        
        if (!token) {
            return res.status(401).json({
                success: false,
                message: 'Токен доступа не предоставлен'
            });
        }

        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        
        // Получаем актуальную информацию о пользователе
        const user = await query(
            `SELECT u.*, r.name as role_name, r.permissions, b.name as branch_name, d.name as department_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             LEFT JOIN branches b ON u.branch_id = b.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = ? AND u.is_active = TRUE`,
            [decoded.userId]
        );

        if (!user.length) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не найден или деактивирован'
            });
        }

        req.user = {
            ...user[0],
            permissions: JSON.parse(user[0].permissions || '{}')
        };

        // Логируем активность пользователя
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
            [req.user.id, 'api_access', 'system', req.ip, req.get('User-Agent')]
        );

        next();
    } catch (error) {
        logger.error('Ошибка аутентификации:', error);
        
        if (error.name === 'JsonWebTokenError') {
            return res.status(401).json({
                success: false,
                message: 'Недействительный токен'
            });
        }
        
        if (error.name === 'TokenExpiredError') {
            return res.status(401).json({
                success: false,
                message: 'Токен истек'
            });
        }

        return res.status(500).json({
            success: false,
            message: 'Ошибка сервера при аутентификации'
        });
    }
};

// Middleware для проверки ролей
const requireRole = (roles) => {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не аутентифицирован'
            });
        }

        const userRole = req.user.role_name;
        const allowedRoles = Array.isArray(roles) ? roles : [roles];

        if (!allowedRoles.includes(userRole)) {
            logger.warn('Попытка доступа с недостаточными правами', {
                userId: req.user.id,
                userRole,
                requiredRoles: allowedRoles,
                path: req.path
            });

            return res.status(403).json({
                success: false,
                message: 'Недостаточно прав для выполнения операции'
            });
        }

        next();
    };
};

// Middleware для проверки разрешений
const requirePermission = (permission) => {
    return (req, res, next) => {
        if (!req.user) {
            return res.status(401).json({
                success: false,
                message: 'Пользователь не аутентифицирован'
            });
        }

        const permissions = req.user.permissions;

        // Администратор имеет все права
        if (permissions.all || permissions[permission]) {
            return next();
        }

        logger.warn('Попытка доступа без необходимого разрешения', {
            userId: req.user.id,
            requiredPermission: permission,
            userPermissions: permissions,
            path: req.path
        });

        return res.status(403).json({
            success: false,
            message: 'Недостаточно прав для выполнения операции'
        });
    };
};

// Middleware для проверки доступа к филиалу/подразделению
const requireBranchAccess = (req, res, next) => {
    if (!req.user) {
        return res.status(401).json({
            success: false,
            message: 'Пользователь не аутентифицирован'
        });
    }

    // Администратор имеет доступ ко всем филиалам
    if (req.user.role_name === 'admin') {
        return next();
    }

    const requestedBranchId = req.params.branchId || req.body.branch_id || req.query.branch_id;
    const requestedDepartmentId = req.params.departmentId || req.body.department_id || req.query.department_id;

    // Проверяем доступ к филиалу
    if (requestedBranchId && req.user.branch_id && req.user.branch_id != requestedBranchId) {
        return res.status(403).json({
            success: false,
            message: 'Доступ к данному филиалу запрещен'
        });
    }

    // Проверяем доступ к подразделению
    if (requestedDepartmentId && req.user.department_id && req.user.department_id != requestedDepartmentId) {
        return res.status(403).json({
            success: false,
            message: 'Доступ к данному подразделению запрещен'
        });
    }

    next();
};

module.exports = {
    authMiddleware,
    requireRole,
    requirePermission,
    requireBranchAccess
};