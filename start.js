#!/usr/bin/env node

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

console.log('🚀 Запуск системы управления курьерскими заявками...\n');

// Проверка наличия .env файла
const envPath = path.join(__dirname, '.env');
if (!fs.existsSync(envPath)) {
    console.log('⚠️  Файл .env не найден. Копирую .env.example...');
    try {
        fs.copyFileSync(path.join(__dirname, '.env.example'), envPath);
        console.log('✅ Файл .env создан. Пожалуйста, настройте переменные окружения.\n');
    } catch (error) {
        console.error('❌ Ошибка создания .env файла:', error.message);
        process.exit(1);
    }
}

// Проверка наличия node_modules
const nodeModulesPath = path.join(__dirname, 'node_modules');
if (!fs.existsSync(nodeModulesPath)) {
    console.log('📦 Установка зависимостей...');
    const install = spawn('npm', ['install'], { 
        stdio: 'inherit',
        shell: true 
    });
    
    install.on('close', (code) => {
        if (code === 0) {
            console.log('✅ Зависимости установлены.\n');
            startServer();
        } else {
            console.error('❌ Ошибка установки зависимостей');
            process.exit(1);
        }
    });
} else {
    startServer();
}

function startServer() {
    console.log('🔧 Запуск сервера...');
    
    // Проверка режима запуска
    const isDev = process.argv.includes('--dev') || process.env.NODE_ENV === 'development';
    const script = isDev ? 'dev' : 'start';
    
    console.log(`📡 Режим: ${isDev ? 'разработки' : 'продакшн'}`);
    console.log(`🌐 Сервер будет доступен по адресу: http://localhost:${process.env.PORT || 3000}`);
    console.log(`📊 API: http://localhost:${process.env.PORT || 3000}/api\n`);
    
    const server = spawn('npm', ['run', script], { 
        stdio: 'inherit',
        shell: true 
    });
    
    server.on('close', (code) => {
        console.log(`\n🛑 Сервер остановлен с кодом ${code}`);
    });
    
    // Обработка сигналов завершения
    process.on('SIGINT', () => {
        console.log('\n🛑 Получен сигнал завершения...');
        server.kill('SIGINT');
    });
    
    process.on('SIGTERM', () => {
        console.log('\n🛑 Получен сигнал завершения...');
        server.kill('SIGTERM');
    });
}