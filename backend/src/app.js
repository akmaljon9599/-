const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const path = require('path');
const { createServer } = require('http');
const { Server } = require('socket.io');
require('dotenv').config();

const authRoutes = require('./routes/auth');
const userRoutes = require('./routes/users');
const requestRoutes = require('./routes/requests');
const courierRoutes = require('./routes/couriers');
const branchRoutes = require('./routes/branches');
const departmentRoutes = require('./routes/departments');
const fileRoutes = require('./routes/files');
const reportRoutes = require('./routes/reports');
const settingsRoutes = require('./routes/settings');

const authMiddleware = require('./middleware/auth');
const errorHandler = require('./middleware/errorHandler');
const logger = require('./utils/logger');

const app = express();
const server = createServer(app);
const io = new Server(server, {
    cors: {
        origin: process.env.FRONTEND_URL || "http://localhost:3000",
        methods: ["GET", "POST"]
    }
});

// Middleware
app.use(helmet());
app.use(cors({
    origin: process.env.FRONTEND_URL || "http://localhost:3000",
    credentials: true
}));

// Rate limiting
const limiter = rateLimit({
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000, // 15 minutes
    max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100, // limit each IP to 100 requests per windowMs
    message: 'Слишком много запросов с этого IP, попробуйте позже.'
});
app.use('/api/', limiter);

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Статические файлы
app.use('/uploads', express.static(path.join(__dirname, '../uploads')));

// Логирование запросов
app.use((req, res, next) => {
    logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('User-Agent')
    });
    next();
});

// Маршруты API
app.use('/api/auth', authRoutes);
app.use('/api/users', authMiddleware, userRoutes);
app.use('/api/requests', authMiddleware, requestRoutes);
app.use('/api/couriers', authMiddleware, courierRoutes);
app.use('/api/branches', authMiddleware, branchRoutes);
app.use('/api/departments', authMiddleware, departmentRoutes);
app.use('/api/files', authMiddleware, fileRoutes);
app.use('/api/reports', authMiddleware, reportRoutes);
app.use('/api/settings', authMiddleware, settingsRoutes);

// WebSocket для отслеживания местоположения
io.on('connection', (socket) => {
    logger.info('Клиент подключился', { socketId: socket.id });

    socket.on('join-courier-room', (courierId) => {
        socket.join(`courier-${courierId}`);
        logger.info('Курьер присоединился к комнате', { courierId, socketId: socket.id });
    });

    socket.on('location-update', (data) => {
        // Сохраняем местоположение в базу данных
        const locationService = require('./services/locationService');
        locationService.saveLocation(data.courierId, data.latitude, data.longitude, data.accuracy)
            .then(() => {
                // Отправляем обновление всем подписанным на этого курьера
                socket.to(`courier-${data.courierId}`).emit('location-updated', data);
            })
            .catch(error => {
                logger.error('Ошибка сохранения местоположения', error);
            });
    });

    socket.on('disconnect', () => {
        logger.info('Клиент отключился', { socketId: socket.id });
    });
});

// Экспортируем io для использования в других модулях
app.set('io', io);

// Обработка ошибок
app.use(errorHandler);

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({
        success: false,
        message: 'Маршрут не найден'
    });
});

const PORT = process.env.PORT || 3000;

server.listen(PORT, () => {
    logger.info(`Сервер запущен на порту ${PORT}`);
    logger.info(`Окружение: ${process.env.NODE_ENV || 'development'}`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    logger.info('Получен сигнал SIGTERM, завершение работы...');
    server.close(() => {
        logger.info('Сервер закрыт');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    logger.info('Получен сигнал SIGINT, завершение работы...');
    server.close(() => {
        logger.info('Сервер закрыт');
        process.exit(0);
    });
});

module.exports = app;