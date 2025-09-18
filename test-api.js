#!/usr/bin/env node

const axios = require('axios');

const API_BASE_URL = 'http://localhost:3000/api';

// Тестовые данные
const testUsers = {
    admin: { username: 'admin', password: 'password123' },
    courier: { username: 'courier1', password: 'password123' },
    operator: { username: 'operator1', password: 'password123' }
};

let authToken = null;

// Функция для выполнения HTTP запросов
async function makeRequest(method, endpoint, data = null, token = null) {
    try {
        const config = {
            method,
            url: `${API_BASE_URL}${endpoint}`,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }

        if (data) {
            config.data = data;
        }

        const response = await axios(config);
        return { success: true, data: response.data, status: response.status };
    } catch (error) {
        return { 
            success: false, 
            error: error.response?.data?.error || error.message,
            status: error.response?.status 
        };
    }
}

// Тест аутентификации
async function testAuthentication() {
    console.log('🔐 Тестирование аутентификации...');
    
    // Тест входа администратора
    const loginResult = await makeRequest('POST', '/auth/login', testUsers.admin);
    if (loginResult.success) {
        console.log('✅ Вход администратора успешен');
        authToken = loginResult.data.token;
        
        // Тест получения информации о пользователе
        const meResult = await makeRequest('GET', '/auth/me', null, authToken);
        if (meResult.success) {
            console.log('✅ Получение информации о пользователе успешно');
            console.log(`   Пользователь: ${meResult.data.user.firstName} ${meResult.data.user.lastName}`);
            console.log(`   Роль: ${meResult.data.user.role.name}`);
        } else {
            console.log('❌ Ошибка получения информации о пользователе:', meResult.error);
        }
    } else {
        console.log('❌ Ошибка входа администратора:', loginResult.error);
        return false;
    }
    
    return true;
}

// Тест заявок
async function testRequests() {
    console.log('\n📋 Тестирование заявок...');
    
    // Получение списка заявок
    const requestsResult = await makeRequest('GET', '/requests', null, authToken);
    if (requestsResult.success) {
        console.log('✅ Получение списка заявок успешно');
        console.log(`   Найдено заявок: ${requestsResult.data.requests.length}`);
        
        if (requestsResult.data.requests.length > 0) {
            const firstRequest = requestsResult.data.requests[0];
            console.log(`   Первая заявка: ${firstRequest.requestNumber} (${firstRequest.client.name})`);
            
            // Получение деталей заявки
            const detailResult = await makeRequest('GET', `/requests/${firstRequest.id}`, null, authToken);
            if (detailResult.success) {
                console.log('✅ Получение деталей заявки успешно');
            } else {
                console.log('❌ Ошибка получения деталей заявки:', detailResult.error);
            }
        }
    } else {
        console.log('❌ Ошибка получения списка заявок:', requestsResult.error);
    }
}

// Тест дашборда
async function testDashboard() {
    console.log('\n📊 Тестирование дашборда...');
    
    // Получение статистики
    const statsResult = await makeRequest('GET', '/dashboard/stats', null, authToken);
    if (statsResult.success) {
        console.log('✅ Получение статистики успешно');
        console.log(`   Всего заявок: ${statsResult.data.stats.total}`);
        console.log(`   Доставлено: ${statsResult.data.stats.delivered}`);
        console.log(`   В процессе: ${statsResult.data.stats.inProgress}`);
        console.log(`   Отказано: ${statsResult.data.stats.rejected}`);
    } else {
        console.log('❌ Ошибка получения статистики:', statsResult.error);
    }
    
    // Получение последней активности
    const activityResult = await makeRequest('GET', '/dashboard/recent-activity', null, authToken);
    if (activityResult.success) {
        console.log('✅ Получение последней активности успешно');
        console.log(`   Активностей: ${activityResult.data.activities.length}`);
    } else {
        console.log('❌ Ошибка получения активности:', activityResult.error);
    }
}

// Тест пользователей
async function testUsers() {
    console.log('\n👥 Тестирование пользователей...');
    
    // Получение списка пользователей
    const usersResult = await makeRequest('GET', '/users', null, authToken);
    if (usersResult.success) {
        console.log('✅ Получение списка пользователей успешно');
        console.log(`   Найдено пользователей: ${usersResult.data.users.length}`);
        
        // Получение курьеров
        const couriersResult = await makeRequest('GET', '/users/couriers/list', null, authToken);
        if (couriersResult.success) {
            console.log('✅ Получение списка курьеров успешно');
            console.log(`   Найдено курьеров: ${couriersResult.data.couriers.length}`);
        } else {
            console.log('❌ Ошибка получения курьеров:', couriersResult.error);
        }
    } else {
        console.log('❌ Ошибка получения пользователей:', usersResult.error);
    }
}

// Тест настроек
async function testSettings() {
    console.log('\n⚙️ Тестирование настроек...');
    
    // Получение филиалов
    const branchesResult = await makeRequest('GET', '/settings/branches', null, authToken);
    if (branchesResult.success) {
        console.log('✅ Получение филиалов успешно');
        console.log(`   Найдено филиалов: ${branchesResult.data.branches.length}`);
    } else {
        console.log('❌ Ошибка получения филиалов:', branchesResult.error);
    }
    
    // Получение типов карт
    const cardTypesResult = await makeRequest('GET', '/settings/card-types', null, authToken);
    if (cardTypesResult.success) {
        console.log('✅ Получение типов карт успешно');
        console.log(`   Найдено типов карт: ${cardTypesResult.data.cardTypes.length}`);
    } else {
        console.log('❌ Ошибка получения типов карт:', cardTypesResult.error);
    }
}

// Тест health check
async function testHealthCheck() {
    console.log('\n🏥 Тестирование health check...');
    
    const healthResult = await makeRequest('GET', '/health');
    if (healthResult.success) {
        console.log('✅ Health check успешен');
        console.log(`   Статус: ${healthResult.data.status}`);
        console.log(`   Версия: ${healthResult.data.version}`);
    } else {
        console.log('❌ Ошибка health check:', healthResult.error);
    }
}

// Основная функция тестирования
async function runTests() {
    console.log('🧪 Запуск тестов API...\n');
    
    try {
        // Проверка доступности сервера
        await testHealthCheck();
        
        // Тест аутентификации
        const authSuccess = await testAuthentication();
        if (!authSuccess) {
            console.log('\n❌ Тестирование прервано из-за ошибки аутентификации');
            return;
        }
        
        // Остальные тесты
        await testRequests();
        await testDashboard();
        await testUsers();
        await testSettings();
        
        console.log('\n🎉 Все тесты завершены!');
        
    } catch (error) {
        console.error('\n💥 Критическая ошибка:', error.message);
    }
}

// Запуск тестов
if (require.main === module) {
    runTests();
}

module.exports = { runTests, makeRequest };