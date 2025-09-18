const express = require('express');
const Joi = require('joi');
const { query } = require('../config/database');
const { requireRole } = require('../middleware/auth');

const router = express.Router();

// Схемы валидации
const settingSchema = Joi.object({
    setting_key: Joi.string().min(1).max(100).required(),
    setting_value: Joi.string().max(1000).optional(),
    description: Joi.string().max(500).optional()
});

const branchSchema = Joi.object({
    name: Joi.string().min(2).max(100).required(),
    address: Joi.string().max(500).optional(),
    phone: Joi.string().max(20).optional(),
    manager_name: Joi.string().max(100).optional()
});

const departmentSchema = Joi.object({
    name: Joi.string().min(2).max(100).required(),
    branch_id: Joi.number().integer().positive().required(),
    manager_name: Joi.string().max(100).optional()
});

const cardTypeSchema = Joi.object({
    name: Joi.string().min(2).max(50).required(),
    description: Joi.string().max(500).optional()
});

// GET /api/settings - Получение всех настроек системы
router.get('/', requireRole('admin'), async (req, res, next) => {
    try {
        const settings = await query(`
            SELECT setting_key, setting_value, description, updated_at, updated_by,
                   u.first_name, u.last_name, u.middle_name
            FROM system_settings ss
            LEFT JOIN users u ON ss.updated_by = u.id
            ORDER BY setting_key
        `);

        const formattedSettings = settings.map(setting => ({
            key: setting.setting_key,
            value: setting.setting_value,
            description: setting.description,
            updatedAt: setting.updated_at,
            updatedBy: setting.first_name ? {
                id: setting.updated_by,
                name: `${setting.last_name} ${setting.first_name} ${setting.middle_name || ''}`.trim()
            } : null
        }));

        res.json({ settings: formattedSettings });

    } catch (error) {
        next(error);
    }
});

// PUT /api/settings/:key - Обновление настройки
router.put('/:key', requireRole('admin'), async (req, res, next) => {
    try {
        const { key } = req.params;
        const { value, description } = req.body;

        if (value === undefined && description === undefined) {
            return res.status(400).json({
                error: 'Необходимо указать значение или описание настройки'
            });
        }

        // Проверка существования настройки
        const existingSettings = await query('SELECT setting_key FROM system_settings WHERE setting_key = ?', [key]);
        
        if (existingSettings.length === 0) {
            return res.status(404).json({
                error: 'Настройка не найдена'
            });
        }

        // Обновление настройки
        const updateFields = [];
        const updateValues = [];

        if (value !== undefined) {
            updateFields.push('setting_value = ?');
            updateValues.push(value);
        }

        if (description !== undefined) {
            updateFields.push('description = ?');
            updateValues.push(description);
        }

        updateFields.push('updated_by = ?');
        updateValues.push(req.user.id);

        updateValues.push(key);

        await query(`
            UPDATE system_settings 
            SET ${updateFields.join(', ')}, updated_at = NOW()
            WHERE setting_key = ?
        `, updateValues);

        res.json({
            message: 'Настройка успешно обновлена'
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/settings/branches - Получение списка филиалов
router.get('/branches', async (req, res, next) => {
    try {
        const branches = await query(`
            SELECT id, name, address, phone, manager_name, created_at, updated_at
            FROM branches
            ORDER BY name
        `);

        res.json({ branches });

    } catch (error) {
        next(error);
    }
});

// POST /api/settings/branches - Создание нового филиала
router.post('/branches', requireRole('admin'), async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = branchSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка уникальности названия филиала
        const existingBranches = await query('SELECT id FROM branches WHERE name = ?', [value.name]);
        if (existingBranches.length > 0) {
            return res.status(409).json({
                error: 'Филиал с таким названием уже существует'
            });
        }

        // Создание филиала
        const result = await query(`
            INSERT INTO branches (name, address, phone, manager_name)
            VALUES (?, ?, ?, ?)
        `, [value.name, value.address, value.phone, value.manager_name]);

        res.status(201).json({
            message: 'Филиал успешно создан',
            branchId: result.insertId
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/settings/branches/:id - Обновление филиала
router.put('/branches/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Валидация входных данных
        const { error, value } = branchSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка существования филиала
        const existingBranches = await query('SELECT id FROM branches WHERE id = ?', [id]);
        if (existingBranches.length === 0) {
            return res.status(404).json({
                error: 'Филиал не найден'
            });
        }

        // Проверка уникальности названия филиала (исключая текущий)
        const duplicateBranches = await query('SELECT id FROM branches WHERE name = ? AND id != ?', [value.name, id]);
        if (duplicateBranches.length > 0) {
            return res.status(409).json({
                error: 'Филиал с таким названием уже существует'
            });
        }

        // Обновление филиала
        await query(`
            UPDATE branches 
            SET name = ?, address = ?, phone = ?, manager_name = ?, updated_at = NOW()
            WHERE id = ?
        `, [value.name, value.address, value.phone, value.manager_name, id]);

        res.json({
            message: 'Филиал успешно обновлен'
        });

    } catch (error) {
        next(error);
    }
});

// DELETE /api/settings/branches/:id - Удаление филиала
router.delete('/branches/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования филиала
        const existingBranches = await query('SELECT id FROM branches WHERE id = ?', [id]);
        if (existingBranches.length === 0) {
            return res.status(404).json({
                error: 'Филиал не найден'
            });
        }

        // Проверка наличия связанных записей
        const relatedUsers = await query('SELECT COUNT(*) as count FROM users WHERE branch_id = ?', [id]);
        const relatedDepartments = await query('SELECT COUNT(*) as count FROM departments WHERE branch_id = ?', [id]);
        const relatedRequests = await query('SELECT COUNT(*) as count FROM delivery_requests WHERE branch_id = ?', [id]);

        if (relatedUsers[0].count > 0 || relatedDepartments[0].count > 0 || relatedRequests[0].count > 0) {
            return res.status(400).json({
                error: 'Невозможно удалить филиал, так как с ним связаны пользователи, подразделения или заявки'
            });
        }

        // Удаление филиала
        await query('DELETE FROM branches WHERE id = ?', [id]);

        res.json({
            message: 'Филиал успешно удален'
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/settings/departments - Получение списка подразделений
router.get('/departments', async (req, res, next) => {
    try {
        const { branch_id } = req.query;

        // Построение условий фильтрации
        let whereConditions = [];
        let queryParams = [];

        if (branch_id) {
            whereConditions.push('d.branch_id = ?');
            queryParams.push(branch_id);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const departments = await query(`
            SELECT d.id, d.name, d.branch_id, d.manager_name, d.created_at, d.updated_at,
                   b.name as branch_name
            FROM departments d
            LEFT JOIN branches b ON d.branch_id = b.id
            ${whereClause}
            ORDER BY b.name, d.name
        `, queryParams);

        res.json({ departments });

    } catch (error) {
        next(error);
    }
});

// POST /api/settings/departments - Создание нового подразделения
router.post('/departments', requireRole('admin'), async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = departmentSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка существования филиала
        const branches = await query('SELECT id FROM branches WHERE id = ?', [value.branch_id]);
        if (branches.length === 0) {
            return res.status(404).json({
                error: 'Филиал не найден'
            });
        }

        // Проверка уникальности названия подразделения в рамках филиала
        const existingDepartments = await query('SELECT id FROM departments WHERE name = ? AND branch_id = ?', [value.name, value.branch_id]);
        if (existingDepartments.length > 0) {
            return res.status(409).json({
                error: 'Подразделение с таким названием уже существует в данном филиале'
            });
        }

        // Создание подразделения
        const result = await query(`
            INSERT INTO departments (name, branch_id, manager_name)
            VALUES (?, ?, ?)
        `, [value.name, value.branch_id, value.manager_name]);

        res.status(201).json({
            message: 'Подразделение успешно создано',
            departmentId: result.insertId
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/settings/departments/:id - Обновление подразделения
router.put('/departments/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Валидация входных данных
        const { error, value } = departmentSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка существования подразделения
        const existingDepartments = await query('SELECT id FROM departments WHERE id = ?', [id]);
        if (existingDepartments.length === 0) {
            return res.status(404).json({
                error: 'Подразделение не найдено'
            });
        }

        // Проверка существования филиала
        const branches = await query('SELECT id FROM branches WHERE id = ?', [value.branch_id]);
        if (branches.length === 0) {
            return res.status(404).json({
                error: 'Филиал не найден'
            });
        }

        // Проверка уникальности названия подразделения в рамках филиала (исключая текущее)
        const duplicateDepartments = await query('SELECT id FROM departments WHERE name = ? AND branch_id = ? AND id != ?', [value.name, value.branch_id, id]);
        if (duplicateDepartments.length > 0) {
            return res.status(409).json({
                error: 'Подразделение с таким названием уже существует в данном филиале'
            });
        }

        // Обновление подразделения
        await query(`
            UPDATE departments 
            SET name = ?, branch_id = ?, manager_name = ?, updated_at = NOW()
            WHERE id = ?
        `, [value.name, value.branch_id, value.manager_name, id]);

        res.json({
            message: 'Подразделение успешно обновлено'
        });

    } catch (error) {
        next(error);
    }
});

// DELETE /api/settings/departments/:id - Удаление подразделения
router.delete('/departments/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования подразделения
        const existingDepartments = await query('SELECT id FROM departments WHERE id = ?', [id]);
        if (existingDepartments.length === 0) {
            return res.status(404).json({
                error: 'Подразделение не найдено'
            });
        }

        // Проверка наличия связанных записей
        const relatedUsers = await query('SELECT COUNT(*) as count FROM users WHERE department_id = ?', [id]);
        const relatedRequests = await query('SELECT COUNT(*) as count FROM delivery_requests WHERE department_id = ?', [id]);

        if (relatedUsers[0].count > 0 || relatedRequests[0].count > 0) {
            return res.status(400).json({
                error: 'Невозможно удалить подразделение, так как с ним связаны пользователи или заявки'
            });
        }

        // Удаление подразделения
        await query('DELETE FROM departments WHERE id = ?', [id]);

        res.json({
            message: 'Подразделение успешно удалено'
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/settings/card-types - Получение списка типов карт
router.get('/card-types', async (req, res, next) => {
    try {
        const cardTypes = await query(`
            SELECT id, name, description, created_at
            FROM card_types
            ORDER BY name
        `);

        res.json({ cardTypes });

    } catch (error) {
        next(error);
    }
});

// POST /api/settings/card-types - Создание нового типа карты
router.post('/card-types', requireRole('admin'), async (req, res, next) => {
    try {
        // Валидация входных данных
        const { error, value } = cardTypeSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка уникальности названия типа карты
        const existingCardTypes = await query('SELECT id FROM card_types WHERE name = ?', [value.name]);
        if (existingCardTypes.length > 0) {
            return res.status(409).json({
                error: 'Тип карты с таким названием уже существует'
            });
        }

        // Создание типа карты
        const result = await query(`
            INSERT INTO card_types (name, description)
            VALUES (?, ?)
        `, [value.name, value.description]);

        res.status(201).json({
            message: 'Тип карты успешно создан',
            cardTypeId: result.insertId
        });

    } catch (error) {
        next(error);
    }
});

// PUT /api/settings/card-types/:id - Обновление типа карты
router.put('/card-types/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Валидация входных данных
        const { error, value } = cardTypeSchema.validate(req.body);
        if (error) {
            return res.status(400).json({
                error: 'Ошибка валидации данных',
                details: error.details.map(detail => detail.message)
            });
        }

        // Проверка существования типа карты
        const existingCardTypes = await query('SELECT id FROM card_types WHERE id = ?', [id]);
        if (existingCardTypes.length === 0) {
            return res.status(404).json({
                error: 'Тип карты не найден'
            });
        }

        // Проверка уникальности названия типа карты (исключая текущий)
        const duplicateCardTypes = await query('SELECT id FROM card_types WHERE name = ? AND id != ?', [value.name, id]);
        if (duplicateCardTypes.length > 0) {
            return res.status(409).json({
                error: 'Тип карты с таким названием уже существует'
            });
        }

        // Обновление типа карты
        await query(`
            UPDATE card_types 
            SET name = ?, description = ?
            WHERE id = ?
        `, [value.name, value.description, id]);

        res.json({
            message: 'Тип карты успешно обновлен'
        });

    } catch (error) {
        next(error);
    }
});

// DELETE /api/settings/card-types/:id - Удаление типа карты
router.delete('/card-types/:id', requireRole('admin'), async (req, res, next) => {
    try {
        const { id } = req.params;

        // Проверка существования типа карты
        const existingCardTypes = await query('SELECT id FROM card_types WHERE id = ?', [id]);
        if (existingCardTypes.length === 0) {
            return res.status(404).json({
                error: 'Тип карты не найден'
            });
        }

        // Проверка наличия связанных записей
        const relatedRequests = await query('SELECT COUNT(*) as count FROM delivery_requests WHERE card_type_id = ?', [id]);

        if (relatedRequests[0].count > 0) {
            return res.status(400).json({
                error: 'Невозможно удалить тип карты, так как с ним связаны заявки'
            });
        }

        // Удаление типа карты
        await query('DELETE FROM card_types WHERE id = ?', [id]);

        res.json({
            message: 'Тип карты успешно удален'
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;