// Глобальный обработчик ошибок
const errorHandler = (err, req, res, next) => {
    console.error('Error:', err);

    // Ошибки валидации Joi
    if (err.isJoi) {
        return res.status(400).json({
            error: 'Ошибка валидации данных',
            details: err.details.map(detail => ({
                field: detail.path.join('.'),
                message: detail.message
            }))
        });
    }

    // Ошибки базы данных MySQL
    if (err.code) {
        switch (err.code) {
            case 'ER_DUP_ENTRY':
                return res.status(409).json({
                    error: 'Запись с такими данными уже существует',
                    field: err.sqlMessage.includes('username') ? 'username' : 
                           err.sqlMessage.includes('email') ? 'email' : 'unknown'
                });
            
            case 'ER_NO_REFERENCED_ROW_2':
                return res.status(400).json({
                    error: 'Ссылка на несуществующую запись',
                    details: err.sqlMessage
                });
            
            case 'ER_ROW_IS_REFERENCED_2':
                return res.status(400).json({
                    error: 'Невозможно удалить запись, так как на неё есть ссылки',
                    details: err.sqlMessage
                });
            
            case 'ECONNREFUSED':
                return res.status(503).json({
                    error: 'Сервис временно недоступен',
                    details: 'Ошибка соединения с базой данных'
                });
            
            case 'ER_ACCESS_DENIED_ERROR':
                return res.status(503).json({
                    error: 'Ошибка доступа к базе данных',
                    details: 'Проверьте настройки подключения'
                });
        }
    }

    // Ошибки файловой системы
    if (err.code === 'ENOENT') {
        return res.status(404).json({
            error: 'Файл не найден'
        });
    }

    if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(413).json({
            error: 'Файл слишком большой',
            details: 'Превышен максимальный размер файла'
        });
    }

    // Ошибки Multer
    if (err.code === 'LIMIT_UNEXPECTED_FILE') {
        return res.status(400).json({
            error: 'Неожиданное поле файла',
            details: err.message
        });
    }

    // Ошибки JWT
    if (err.name === 'JsonWebTokenError') {
        return res.status(401).json({
            error: 'Недействительный токен'
        });
    }

    if (err.name === 'TokenExpiredError') {
        return res.status(401).json({
            error: 'Токен истек'
        });
    }

    // Ошибки валидации
    if (err.name === 'ValidationError') {
        return res.status(400).json({
            error: 'Ошибка валидации данных',
            details: err.message
        });
    }

    // Ошибки синтаксиса JSON
    if (err instanceof SyntaxError && err.status === 400 && 'body' in err) {
        return res.status(400).json({
            error: 'Неверный формат JSON'
        });
    }

    // Ошибки внешних API
    if (err.response) {
        const status = err.response.status || 500;
        return res.status(status).json({
            error: 'Ошибка внешнего сервиса',
            details: err.response.data?.message || err.message
        });
    }

    // Ошибки по умолчанию
    const status = err.status || err.statusCode || 500;
    const message = err.message || 'Внутренняя ошибка сервера';

    res.status(status).json({
        error: message,
        ...(process.env.NODE_ENV === 'development' && {
            stack: err.stack,
            details: err
        })
    });
};

// Middleware для обработки 404 ошибок
const notFoundHandler = (req, res) => {
    res.status(404).json({
        error: 'Маршрут не найден',
        path: req.originalUrl,
        method: req.method
    });
};

// Middleware для логирования ошибок
const errorLogger = (err, req, res, next) => {
    const timestamp = new Date().toISOString();
    const method = req.method;
    const url = req.originalUrl;
    const ip = req.ip || req.connection.remoteAddress;
    const userAgent = req.get('User-Agent');

    console.error(`[${timestamp}] ERROR: ${method} ${url}`);
    console.error(`IP: ${ip}, User-Agent: ${userAgent}`);
    console.error(`Error: ${err.message}`);
    console.error(`Stack: ${err.stack}`);

    next(err);
};

module.exports = {
    errorHandler,
    notFoundHandler,
    errorLogger
};