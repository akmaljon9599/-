const express = require('express');
const Joi = require('joi');
const { query } = require('../../config/database');
const { requireRole } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Схемы валидации
const updateSettingSchema = Joi.object({
    setting_value: Joi.string().required().messages({
        'string.empty': 'Значение настройки обязательно',
        'any.required': 'Значение настройки обязательно'
    })
});

// Получение всех настроек системы
router.get('/', requireRole('admin'), async (req, res, next) => {
    try {
        const settings = await query(`
            SELECT 
                s.*,
                CONCAT(u.first_name, ' ', u.last_name) as updated_by_name
            FROM system_settings s
            LEFT JOIN users u ON s.updated_by = u.id
            ORDER BY s.setting_key
        `);

        res.json({
            success: true,
            data: settings
        });

    } catch (error) {
        next(error);
    }
});

// Получение настройки по ключу
router.get('/:key', requireRole('admin'), async (req, res, next) => {
    try {
        const { key } = req.params;

        const settings = await query(`
            SELECT 
                s.*,
                CONCAT(u.first_name, ' ', u.last_name) as updated_by_name
            FROM system_settings s
            LEFT JOIN users u ON s.updated_by = u.id
            WHERE s.setting_key = ?
        `, [key]);

        if (!settings.length) {
            return res.status(404).json({
                success: false,
                message: 'Настройка не найдена'
            });
        }

        res.json({
            success: true,
            data: settings[0]
        });

    } catch (error) {
        next(error);
    }
});

// Обновление настройки
router.put('/:key', requireRole('admin'), async (req, res, next) => {
    try {
        const { key } = req.params;
        const { error, value } = updateSettingSchema.validate(req.body);
        
        if (error) {
            return res.status(400).json({
                success: false,
                message: 'Ошибка валидации данных',
                errors: error.details
            });
        }

        const { setting_value } = value;

        // Проверяем существование настройки
        const existingSettings = await query('SELECT * FROM system_settings WHERE setting_key = ?', [key]);
        if (!existingSettings.length) {
            return res.status(404).json({
                success: false,
                message: 'Настройка не найдена'
            });
        }

        const oldValue = existingSettings[0].setting_value;

        // Обновляем настройку
        await query(`
            UPDATE system_settings 
            SET setting_value = ?, updated_by = ?, updated_at = NOW()
            WHERE setting_key = ?
        `, [setting_value, req.user.id, key]);

        // Логирование изменения настройки
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'update_setting', 'system_setting', key,
                JSON.stringify({ 
                    setting_key: key, 
                    old_value: oldValue, 
                    new_value: setting_value 
                }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Настройка системы обновлена', {
            settingKey: key,
            oldValue,
            newValue: setting_value,
            updatedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Настройка успешно обновлена'
        });

    } catch (error) {
        next(error);
    }
});

// Создание новой настройки
router.post('/', requireRole('admin'), async (req, res, next) => {
    try {
        const { setting_key, setting_value, description } = req.body;

        if (!setting_key || !setting_value) {
            return res.status(400).json({
                success: false,
                message: 'Ключ и значение настройки обязательны'
            });
        }

        // Проверяем уникальность ключа
        const existingSettings = await query('SELECT id FROM system_settings WHERE setting_key = ?', [setting_key]);
        if (existingSettings.length > 0) {
            return res.status(400).json({
                success: false,
                message: 'Настройка с таким ключом уже существует'
            });
        }

        const result = await query(`
            INSERT INTO system_settings (setting_key, setting_value, description, updated_by)
            VALUES (?, ?, ?, ?)
        `, [setting_key, setting_value, description || null, req.user.id]);

        // Логирование создания настройки
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'create_setting', 'system_setting', result.insertId,
                JSON.stringify({ 
                    setting_key, 
                    setting_value, 
                    description 
                }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Создана новая настройка системы', {
            settingId: result.insertId,
            settingKey: setting_key,
            settingValue: setting_value,
            createdBy: req.user.id
        });

        res.status(201).json({
            success: true,
            message: 'Настройка успешно создана',
            data: {
                id: result.insertId,
                setting_key
            }
        });

    } catch (error) {
        next(error);
    }
});

// Удаление настройки
router.delete('/:key', requireRole('admin'), async (req, res, next) => {
    try {
        const { key } = req.params;

        // Проверяем существование настройки
        const settings = await query('SELECT * FROM system_settings WHERE setting_key = ?', [key]);
        if (!settings.length) {
            return res.status(404).json({
                success: false,
                message: 'Настройка не найдена'
            });
        }

        const setting = settings[0];

        // Удаляем настройку
        await query('DELETE FROM system_settings WHERE setting_key = ?', [key]);

        // Логирование удаления настройки
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'delete_setting', 'system_setting', key,
                JSON.stringify({ 
                    setting_key: key, 
                    setting_value: setting.setting_value 
                }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Настройка системы удалена', {
            settingKey: key,
            deletedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Настройка успешно удалена'
        });

    } catch (error) {
        next(error);
    }
});

// Получение настроек для публичного доступа (без чувствительной информации)
router.get('/public/list', async (req, res, next) => {
    try {
        const publicSettings = await query(`
            SELECT setting_key, setting_value
            FROM system_settings
            WHERE setting_key IN (
                'location_update_interval',
                'max_photos_per_delivery',
                'min_rejection_comment_length',
                'file_upload_max_size',
                'yandex_maps_enabled',
                'abs_bank_integration_enabled'
            )
        `);

        const settings = {};
        publicSettings.forEach(setting => {
            settings[setting.setting_key] = setting.setting_value;
        });

        res.json({
            success: true,
            data: settings
        });

    } catch (error) {
        next(error);
    }
});

// Сброс настроек к значениям по умолчанию
router.post('/reset-to-defaults', requireRole('admin'), async (req, res, next) => {
    try {
        const defaultSettings = [
            { key: 'location_update_interval', value: '60000', description: 'Интервал обновления местоположения курьеров (мс)' },
            { key: 'max_photos_per_delivery', value: '5', description: 'Максимальное количество фотографий при доставке' },
            { key: 'min_rejection_comment_length', value: '100', description: 'Минимальная длина комментария при отказе' },
            { key: 'session_timeout', value: '3600000', description: 'Время жизни сессии (мс)' },
            { key: 'file_upload_max_size', value: '10485760', description: 'Максимальный размер загружаемого файла (байт)' },
            { key: 'yandex_maps_enabled', value: 'true', description: 'Включить интеграцию с Яндекс.Картами' },
            { key: 'abs_bank_integration_enabled', value: 'true', description: 'Включить интеграцию с АБС банка' }
        ];

        for (const setting of defaultSettings) {
            await query(`
                INSERT INTO system_settings (setting_key, setting_value, description, updated_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                description = VALUES(description),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            `, [setting.key, setting.value, setting.description, req.user.id]);
        }

        // Логирование сброса настроек
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'reset_settings', 'system_settings',
                JSON.stringify({ settings_count: defaultSettings.length }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Настройки системы сброшены к значениям по умолчанию', {
            settingsCount: defaultSettings.length,
            resetBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Настройки успешно сброшены к значениям по умолчанию'
        });

    } catch (error) {
        next(error);
    }
});

// Экспорт настроек
router.get('/export/backup', requireRole('admin'), async (req, res, next) => {
    try {
        const settings = await query(`
            SELECT setting_key, setting_value, description
            FROM system_settings
            ORDER BY setting_key
        `);

        const backup = {
            export_date: new Date().toISOString(),
            exported_by: req.user.id,
            settings: settings
        };

        // Логирование экспорта настроек
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'export_settings', 'system_settings',
                JSON.stringify({ settings_count: settings.length }),
                req.ip, req.get('User-Agent')
            ]
        );

        res.json({
            success: true,
            data: backup
        });

    } catch (error) {
        next(error);
    }
});

// Импорт настроек
router.post('/import/backup', requireRole('admin'), async (req, res, next) => {
    try {
        const { settings } = req.body;

        if (!Array.isArray(settings)) {
            return res.status(400).json({
                success: false,
                message: 'Некорректный формат данных для импорта'
            });
        }

        let importedCount = 0;

        for (const setting of settings) {
            if (setting.setting_key && setting.setting_value) {
                await query(`
                    INSERT INTO system_settings (setting_key, setting_value, description, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    description = VALUES(description),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
                `, [setting.setting_key, setting.setting_value, setting.description || null, req.user.id]);
                importedCount++;
            }
        }

        // Логирование импорта настроек
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'import_settings', 'system_settings',
                JSON.stringify({ imported_count: importedCount }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Настройки системы импортированы', {
            importedCount,
            importedBy: req.user.id
        });

        res.json({
            success: true,
            message: `Успешно импортировано ${importedCount} настроек`
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;