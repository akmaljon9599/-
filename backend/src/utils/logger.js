const fs = require('fs');
const path = require('path');

// Создаем директорию для логов если её нет
const logDir = path.join(__dirname, '../../logs');
if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
}

const logLevels = {
    ERROR: 0,
    WARN: 1,
    INFO: 2,
    DEBUG: 3
};

const currentLevel = process.env.LOG_LEVEL || 'INFO';

const formatMessage = (level, message, meta = {}) => {
    const timestamp = new Date().toISOString();
    const metaStr = Object.keys(meta).length > 0 ? ` ${JSON.stringify(meta)}` : '';
    return `[${timestamp}] ${level}: ${message}${metaStr}`;
};

const writeToFile = (level, message, meta) => {
    const logFile = path.join(logDir, `${level.toLowerCase()}.log`);
    const formattedMessage = formatMessage(level, message, meta) + '\n';
    
    fs.appendFile(logFile, formattedMessage, (err) => {
        if (err) {
            console.error('Ошибка записи в лог файл:', err);
        }
    });
};

const logger = {
    error: (message, meta = {}) => {
        if (logLevels[currentLevel] >= logLevels.ERROR) {
            console.error(formatMessage('ERROR', message, meta));
            writeToFile('ERROR', message, meta);
        }
    },
    
    warn: (message, meta = {}) => {
        if (logLevels[currentLevel] >= logLevels.WARN) {
            console.warn(formatMessage('WARN', message, meta));
            writeToFile('WARN', message, meta);
        }
    },
    
    info: (message, meta = {}) => {
        if (logLevels[currentLevel] >= logLevels.INFO) {
            console.log(formatMessage('INFO', message, meta));
            writeToFile('INFO', message, meta);
        }
    },
    
    debug: (message, meta = {}) => {
        if (logLevels[currentLevel] >= logLevels.DEBUG) {
            console.log(formatMessage('DEBUG', message, meta));
            writeToFile('DEBUG', message, meta);
        }
    }
};

module.exports = logger;