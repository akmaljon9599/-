const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { query } = require('../../config/database');
const { requirePermission } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Настройка multer для загрузки файлов
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        const uploadPath = path.join(__dirname, '../../uploads');
        if (!fs.existsSync(uploadPath)) {
            fs.mkdirSync(uploadPath, { recursive: true });
        }
        cb(null, uploadPath);
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        const ext = path.extname(file.originalname);
        cb(null, file.fieldname + '-' + uniqueSuffix + ext);
    }
});

const fileFilter = (req, file, cb) => {
    // Разрешенные типы файлов
    const allowedTypes = {
        'image/jpeg': '.jpg',
        'image/jpg': '.jpg',
        'image/png': '.png',
        'image/gif': '.gif',
        'application/pdf': '.pdf',
        'application/msword': '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx'
    };

    if (allowedTypes[file.mimetype]) {
        cb(null, true);
    } else {
        cb(new Error('Неподдерживаемый тип файла'), false);
    }
};

const upload = multer({
    storage: storage,
    fileFilter: fileFilter,
    limits: {
        fileSize: parseInt(process.env.MAX_FILE_SIZE) || 10 * 1024 * 1024, // 10MB по умолчанию
        files: 5 // Максимум 5 файлов за раз
    }
});

// Загрузка файлов
router.post('/upload', requirePermission('upload_files'), upload.array('files', 5), async (req, res, next) => {
    try {
        if (!req.files || req.files.length === 0) {
            return res.status(400).json({
                success: false,
                message: 'Файлы не были загружены'
            });
        }

        const { request_id, file_category = 'other' } = req.body;

        if (!request_id) {
            // Удаляем загруженные файлы, если нет request_id
            req.files.forEach(file => {
                fs.unlinkSync(file.path);
            });
            return res.status(400).json({
                success: false,
                message: 'ID заявки обязателен'
            });
        }

        // Проверяем существование заявки
        const requests = await query('SELECT id FROM requests WHERE id = ?', [request_id]);
        if (!requests.length) {
            // Удаляем загруженные файлы
            req.files.forEach(file => {
                fs.unlinkSync(file.path);
            });
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        const uploadedFiles = [];

        for (const file of req.files) {
            try {
                const result = await query(`
                    INSERT INTO request_files (
                        request_id, file_name, file_path, file_type, file_size, file_category, uploaded_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                `, [
                    request_id,
                    file.originalname,
                    file.path,
                    file.mimetype,
                    file.size,
                    file_category,
                    req.user.id
                ]);

                uploadedFiles.push({
                    id: result.insertId,
                    original_name: file.originalname,
                    file_name: file.filename,
                    file_path: file.path,
                    file_type: file.mimetype,
                    file_size: file.size,
                    file_category
                });

                // Логирование загрузки файла
                await query(
                    'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        req.user.id, 'upload_file', 'request_file', result.insertId,
                        JSON.stringify({ 
                            request_id, 
                            file_name: file.originalname, 
                            file_category,
                            file_size: file.size 
                        }),
                        req.ip, req.get('User-Agent')
                    ]
                );

            } catch (error) {
                // Удаляем файл при ошибке сохранения в БД
                fs.unlinkSync(file.path);
                logger.error('Ошибка сохранения файла в БД', {
                    file: file.originalname,
                    error: error.message
                });
            }
        }

        logger.info('Файлы загружены', {
            requestId: request_id,
            filesCount: uploadedFiles.length,
            uploadedBy: req.user.id
        });

        res.json({
            success: true,
            message: `Успешно загружено ${uploadedFiles.length} файлов`,
            data: uploadedFiles
        });

    } catch (error) {
        // Удаляем все загруженные файлы при ошибке
        if (req.files) {
            req.files.forEach(file => {
                try {
                    fs.unlinkSync(file.path);
                } catch (unlinkError) {
                    logger.error('Ошибка удаления файла', {
                        file: file.path,
                        error: unlinkError.message
                    });
                }
            });
        }
        next(error);
    }
});

// Получение списка файлов заявки
router.get('/request/:request_id', requirePermission('view_files'), async (req, res, next) => {
    try {
        const { request_id } = req.params;

        // Проверяем существование заявки
        const requests = await query('SELECT id FROM requests WHERE id = ?', [request_id]);
        if (!requests.length) {
            return res.status(404).json({
                success: false,
                message: 'Заявка не найдена'
            });
        }

        const files = await query(`
            SELECT 
                rf.*,
                CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
            FROM request_files rf
            LEFT JOIN users u ON rf.uploaded_by = u.id
            WHERE rf.request_id = ?
            ORDER BY rf.created_at DESC
        `, [request_id]);

        res.json({
            success: true,
            data: files
        });

    } catch (error) {
        next(error);
    }
});

// Получение файла
router.get('/:id', requirePermission('view_files'), async (req, res, next) => {
    try {
        const { id } = req.params;

        const files = await query(`
            SELECT rf.*, r.id as request_id
            FROM request_files rf
            LEFT JOIN requests r ON rf.request_id = r.id
            WHERE rf.id = ?
        `, [id]);

        if (!files.length) {
            return res.status(404).json({
                success: false,
                message: 'Файл не найден'
            });
        }

        const file = files[0];

        // Проверяем права доступа к заявке
        if (req.user.role_name === 'senior_courier' && 
            req.user.branch_id !== file.branch_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к файлу запрещен'
            });
        }

        if (req.user.role_name === 'courier' && 
            req.user.id !== file.courier_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к файлу запрещен'
            });
        }

        // Проверяем существование файла на диске
        if (!fs.existsSync(file.file_path)) {
            return res.status(404).json({
                success: false,
                message: 'Файл не найден на диске'
            });
        }

        // Логирование просмотра файла
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'view_file', 'request_file', id,
                JSON.stringify({ file_name: file.file_name }),
                req.ip, req.get('User-Agent')
            ]
        );

        // Отправляем файл
        res.download(file.file_path, file.file_name, (err) => {
            if (err) {
                logger.error('Ошибка отправки файла', {
                    fileId: id,
                    filePath: file.file_path,
                    error: err.message
                });
            }
        });

    } catch (error) {
        next(error);
    }
});

// Удаление файла
router.delete('/:id', requirePermission('delete_files'), async (req, res, next) => {
    try {
        const { id } = req.params;

        const files = await query(`
            SELECT rf.*, r.courier_id, r.branch_id
            FROM request_files rf
            LEFT JOIN requests r ON rf.request_id = r.id
            WHERE rf.id = ?
        `, [id]);

        if (!files.length) {
            return res.status(404).json({
                success: false,
                message: 'Файл не найден'
            });
        }

        const file = files[0];

        // Проверяем права доступа
        if (req.user.role_name === 'senior_courier' && 
            req.user.branch_id !== file.branch_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к файлу запрещен'
            });
        }

        if (req.user.role_name === 'courier' && 
            req.user.id !== file.courier_id) {
            return res.status(403).json({
                success: false,
                message: 'Доступ к файлу запрещен'
            });
        }

        // Удаляем файл из базы данных
        await query('DELETE FROM request_files WHERE id = ?', [id]);

        // Удаляем файл с диска
        if (fs.existsSync(file.file_path)) {
            fs.unlinkSync(file.file_path);
        }

        // Логирование удаления файла
        await query(
            'INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                req.user.id, 'delete_file', 'request_file', id,
                JSON.stringify({ file_name: file.file_name }),
                req.ip, req.get('User-Agent')
            ]
        );

        logger.info('Файл удален', {
            fileId: id,
            fileName: file.file_name,
            deletedBy: req.user.id
        });

        res.json({
            success: true,
            message: 'Файл успешно удален'
        });

    } catch (error) {
        next(error);
    }
});

// Получение статистики файлов
router.get('/stats/overview', requirePermission('view_files'), async (req, res, next) => {
    try {
        const { date_from, date_to } = req.query;

        let whereConditions = [];
        let queryParams = [];

        if (date_from) {
            whereConditions.push('DATE(rf.created_at) >= ?');
            queryParams.push(date_from);
        }

        if (date_to) {
            whereConditions.push('DATE(rf.created_at) <= ?');
            queryParams.push(date_to);
        }

        const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

        const stats = await query(`
            SELECT 
                COUNT(*) as total_files,
                SUM(rf.file_size) as total_size,
                COUNT(DISTINCT rf.request_id) as requests_with_files,
                COUNT(CASE WHEN rf.file_category = 'delivery_photo' THEN 1 END) as delivery_photos,
                COUNT(CASE WHEN rf.file_category = 'passport_scan' THEN 1 END) as passport_scans,
                COUNT(CASE WHEN rf.file_category = 'contract' THEN 1 END) as contracts,
                COUNT(CASE WHEN rf.file_category = 'signature' THEN 1 END) as signatures
            FROM request_files rf
            ${whereClause}
        `, queryParams);

        const fileTypes = await query(`
            SELECT 
                rf.file_type,
                COUNT(*) as count,
                SUM(rf.file_size) as total_size
            FROM request_files rf
            ${whereClause}
            GROUP BY rf.file_type
            ORDER BY count DESC
        `, queryParams);

        res.json({
            success: true,
            data: {
                ...stats[0],
                by_type: fileTypes
            }
        });

    } catch (error) {
        next(error);
    }
});

module.exports = router;