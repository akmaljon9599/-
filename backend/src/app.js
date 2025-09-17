const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
require('dotenv').config();

const authRoutes = require('./routes/auth');
const userRoutes = require('./routes/users');
const requestRoutes = require('./routes/requests');
const fileRoutes = require('./routes/files');
const locationRoutes = require('./routes/location');
const dashboardRoutes = require('./routes/dashboard');
const settingsRoutes = require('./routes/settings');

const { errorHandler } = require('./middleware/errorHandler');
const { authenticateToken } = require('./middleware/auth');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware безопасности
app.use(helmet());

// CORS настройки
app.use(cors({
    origin: process.env.NODE_ENV === 'production' 
        ? ['https://yourdomain.com'] 
        : ['http://localhost:3000', 'http://127.0.0.1:3000'],
    credentials: true
}));

// Rate limiting
const limiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 минут
    max: 100, // максимум 100 запросов с одного IP
    message: 'Слишком много запросов с этого IP, попробуйте позже.'
});
app.use('/api/', limiter);

// Парсинг JSON
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Статические файлы
app.use('/uploads', express.static('uploads'));

// API маршруты
app.use('/api/auth', authRoutes);
app.use('/api/users', authenticateToken, userRoutes);
app.use('/api/requests', authenticateToken, requestRoutes);
app.use('/api/files', authenticateToken, fileRoutes);
app.use('/api/location', authenticateToken, locationRoutes);
app.use('/api/dashboard', authenticateToken, dashboardRoutes);
app.use('/api/settings', authenticateToken, settingsRoutes);

// Health check
app.get('/api/health', (req, res) => {
    res.json({ 
        status: 'OK', 
        timestamp: new Date().toISOString(),
        version: '1.0.0'
    });
});

// 404 handler
app.use('*', (req, res) => {
    res.status(404).json({ 
        error: 'Маршрут не найден',
        path: req.originalUrl 
    });
});

// Error handler
app.use(errorHandler);

// Запуск сервера
app.listen(PORT, () => {
    console.log(`🚀 Сервер запущен на порту ${PORT}`);
    console.log(`📊 Режим: ${process.env.NODE_ENV || 'development'}`);
    console.log(`🌐 API доступно по адресу: http://localhost:${PORT}/api`);
});

module.exports = app;