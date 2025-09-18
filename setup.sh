#!/bin/bash

echo "🚀 Настройка системы управления курьерскими заявками"
echo "=================================================="

# Проверка Node.js
if ! command -v node &> /dev/null; then
    echo "❌ Node.js не установлен. Пожалуйста, установите Node.js 16.0.0 или выше"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo "❌ Требуется Node.js версии 16.0.0 или выше. Текущая версия: $(node -v)"
    exit 1
fi

echo "✅ Node.js версия: $(node -v)"

# Проверка MySQL
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL не установлен. Пожалуйста, установите MySQL 8.0 или выше"
    exit 1
fi

echo "✅ MySQL установлен"

# Установка зависимостей
echo "📦 Установка зависимостей..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ Ошибка при установке зависимостей"
    exit 1
fi

echo "✅ Зависимости установлены"

# Создание .env файла если его нет
if [ ! -f .env ]; then
    echo "📝 Создание файла конфигурации..."
    cp .env.example .env
    echo "✅ Файл .env создан. Пожалуйста, отредактируйте его с вашими настройками"
fi

# Создание директорий
echo "📁 Создание необходимых директорий..."
mkdir -p backend/logs
mkdir -p backend/uploads
mkdir -p ssl

echo "✅ Директории созданы"

# Генерация SSL сертификатов для разработки
echo "🔐 Генерация SSL сертификатов..."
./generate-ssl.sh

echo "✅ SSL сертификаты созданы"

echo ""
echo "🎉 Настройка завершена!"
echo ""
echo "Следующие шаги:"
echo "1. Отредактируйте файл .env с вашими настройками базы данных"
echo "2. Создайте базу данных MySQL и выполните:"
echo "   mysql -u root -p < database/schema.sql"
echo "   mysql -u root -p < database/seed.sql"
echo "3. Запустите приложение:"
echo "   npm run dev"
echo ""
echo "Или используйте Docker:"
echo "   docker-compose up -d"
echo ""
echo "🌐 Приложение будет доступно по адресу: https://localhost"
echo "👤 Тестовые пользователи:"
echo "   - Администратор: admin / admin123"
echo "   - Курьер: courier1 / courier123"
echo "   - Оператор: operator1 / operator123"