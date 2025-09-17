const logger = require('../utils/logger');

const errorHandler = (err, req, res, next) => {
    let error = { ...err };
    error.message = err.message;

    // Логируем ошибку
    logger.error('Ошибка API:', {
        message: err.message,
        stack: err.stack,
        url: req.originalUrl,
        method: req.method,
        ip: req.ip,
        userAgent: req.get('User-Agent'),
        userId: req.user?.id
    });

    // Ошибки валидации Joi
    if (err.isJoi) {
        const message = err.details.map(detail => detail.message).join(', ');
        return res.status(400).json({
            success: false,
            message: 'Ошибка валидации данных',
            errors: err.details
        });
    }

    // Ошибки базы данных MySQL
    if (err.code) {
        switch (err.code) {
            case 'ER_DUP_ENTRY':
                return res.status(400).json({
                    success: false,
                    message: 'Запись с такими данными уже существует'
                });
            case 'ER_NO_REFERENCED_ROW_2':
                return res.status(400).json({
                    success: false,
                    message: 'Нарушение целостности данных'
                });
            case 'ER_ROW_IS_REFERENCED_2':
                return res.status(400).json({
                    success: false,
                    message: 'Невозможно удалить запись, так как она используется в других таблицах'
                });
            case 'ECONNREFUSED':
                return res.status(500).json({
                    success: false,
                    message: 'Ошибка подключения к базе данных'
                });
        }
    }

    // Ошибки JWT
    if (err.name === 'JsonWebTokenError') {
        return res.status(401).json({
            success: false,
            message: 'Недействительный токен'
        });
    }

    if (err.name === 'TokenExpiredError') {
        return res.status(401).json({
            success: false,
            message: 'Токен истек'
        });
    }

    // Ошибки загрузки файлов
    if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({
            success: false,
            message: 'Размер файла превышает допустимый лимит'
        });
    }

    if (err.code === 'LIMIT_UNEXPECTED_FILE') {
        return res.status(400).json({
            success: false,
            message: 'Неожиданное поле файла'
        });
    }

    // Ошибки валидации
    if (err.name === 'ValidationError') {
        const message = Object.values(err.errors).map(val => val.message).join(', ');
        return res.status(400).json({
            success: false,
            message: 'Ошибка валидации данных',
            errors: message
        });
    }

    // Ошибки кастинга
    if (err.name === 'CastError') {
        return res.status(400).json({
            success: false,
            message: 'Некорректный формат данных'
        });
    }

    // Ошибки 404
    if (err.statusCode === 404) {
        return res.status(404).json({
            success: false,
            message: err.message || 'Ресурс не найден'
        });
    }

    // Ошибки 403
    if (err.statusCode === 403) {
        return res.status(403).json({
            success: false,
            message: err.message || 'Доступ запрещен'
        });
    }

    // Ошибки 401
    if (err.statusCode === 401) {
        return res.status(401).json({
            success: false,
            message: err.message || 'Не авторизован'
        });
    }

    // Ошибки 400
    if (err.statusCode === 400) {
        return res.status(400).json({
            success: false,
            message: err.message || 'Некорректный запрос'
        });
    }

    // В режиме разработки показываем полную информацию об ошибке
    if (process.env.NODE_ENV === 'development') {
        return res.status(err.statusCode || 500).json({
            success: false,
            message: err.message || 'Внутренняя ошибка сервера',
            stack: err.stack
        });
    }

    // В продакшене скрываем детали ошибки
    res.status(err.statusCode || 500).json({
        success: false,
        message: 'Внутренняя ошибка сервера'
    });
};

module.exports = errorHandler;