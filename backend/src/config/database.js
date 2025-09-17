const mysql = require('mysql2/promise');
require('dotenv').config();

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
    reconnect: true
};

// Создание пула соединений
const pool = mysql.createPool({
    ...dbConfig,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Функция для выполнения запросов
const query = async (sql, params = []) => {
    try {
        const [rows] = await pool.execute(sql, params);
        return rows;
    } catch (error) {
        console.error('Database query error:', error);
        throw error;
    }
};

// Функция для получения одного соединения
const getConnection = async () => {
    try {
        return await pool.getConnection();
    } catch (error) {
        console.error('Database connection error:', error);
        throw error;
    }
};

// Функция для транзакций
const transaction = async (callback) => {
    const connection = await getConnection();
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

// Проверка соединения с базой данных
const testConnection = async () => {
    try {
        const connection = await getConnection();
        await connection.ping();
        connection.release();
        console.log('✅ Соединение с базой данных установлено');
        return true;
    } catch (error) {
        console.error('❌ Ошибка соединения с базой данных:', error.message);
        return false;
    }
};

module.exports = {
    pool,
    query,
    getConnection,
    transaction,
    testConnection
};