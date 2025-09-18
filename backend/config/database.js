const mysql = require('mysql2/promise');
const logger = require('../src/utils/logger');

const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'courier_system',
    charset: 'utf8mb4',
    timezone: '+00:00',
    acquireTimeout: 60000,
    timeout: 60000,
    reconnect: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Создание пула соединений
const pool = mysql.createPool(dbConfig);

// Проверка соединения
const testConnection = async () => {
    try {
        const connection = await pool.getConnection();
        logger.info('Подключение к базе данных установлено');
        connection.release();
        return true;
    } catch (error) {
        logger.error('Ошибка подключения к базе данных:', error);
        return false;
    }
};

// Выполнение запроса
const query = async (sql, params = []) => {
    try {
        const [rows] = await pool.execute(sql, params);
        return rows;
    } catch (error) {
        logger.error('Ошибка выполнения запроса:', { sql, params, error: error.message });
        throw error;
    }
};

// Выполнение транзакции
const transaction = async (callback) => {
    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const result = await callback(connection);
        await connection.commit();
        return result;
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
};

// Закрытие пула соединений
const closePool = async () => {
    try {
        await pool.end();
        logger.info('Пул соединений с базой данных закрыт');
    } catch (error) {
        logger.error('Ошибка закрытия пула соединений:', error);
    }
};

module.exports = {
    pool,
    query,
    transaction,
    testConnection,
    closePool
};