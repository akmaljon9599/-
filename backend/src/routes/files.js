const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs').promises;
const { query } = require('../config/database');
const { requirePermission } = require('../middleware/auth');

const router = express.Router();

// Настройка multer для загрузки файлов
const storage = multer.diskStorage({
    destination: async (req, file, cb) => {
        const uploadDir = path.join(__dirname, '../../uploads');
        try {
            await fs.mkdir(uploadDir, { recursive: true });
            cb(null, uploadDir);
        } catch (error) {
            cb(error);
        }
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        const ext = path.extname(file.originalname);
        cb(null, `${file.fieldname}-${uniqueSuffix}${ext}`);
    }
});

const fileFilter = (req, file, cb) => {
    const allowedTypes = process.env.ALLOWED_FILE_TYPES?.split(',') || ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    const fileExt = path.extname(file.originalname).toLowerCase().substring(1);
    
    if (allowedTypes.includes(fileExt)) {
        cb(null, true);
    } else {
        cb(new Error(`Недопустимый тип файла. Разрешенные типы: ${allowedTypes.join(', ')}`), false);
    }
};

const upload = multer({
    storage: storage,
    limits: {
        fileSize: parseInt(process.env.MAX_FILE_SIZE) || 10 * 1024 * 1024, // 10MB по умолчанию
        files: 10 // максимум 10 файлов за раз
    },
    fileFilter: fileFilter
});

// POST /api/files/upload - Загрузка файлов
router.post('/upload', requirePermission('upload_photos'), upload.array('files', 10), async (req, res, next) => {
    try {
        const { request_id, file_category = 'other' } = req.body;

        if (!request_id) {
            return res.status(400).json({
                error: 'ID заявки обязателен'
            });
        }

        if (!req.files || req.files.length === 0) {
            return res.status(400).json({
                error: 'Файлы не загружены'
            });
        }

        // Проверка существования заявки
        const requests = await query('SELECT id FROM delivery_requests WHERE id = ?', [request_id]);
        if (requests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        const uploadedFiles = [];

        // Сохранение информации о файлах в базе данных
        for (const file of req.files) {
            const fileData = {
                request_id: parseInt(request_id),
                file_name: file.originalname,
                file_path: file.path,
                file_type: file.mimetype,
                file_size: file.size,
                file_category: file_category,
                uploaded_by: req.user.id
            };

            const result = await query(`
                INSERT INTO files (request_id, file_name, file_path, file_type, file_size, file_category, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            `, [
                fileData.request_id,
                fileData.file_name,
                fileData.file_path,
                fileData.file_type,
                fileData.file_size,
                fileData.file_category,
                fileData.uploaded_by
            ]);

            uploadedFiles.push({
                id: result.insertId,
                fileName: fileData.file_name,
                filePath: fileData.file_path,
                fileType: fileData.file_type,
                fileSize: fileData.file_size,
                category: fileData.file_category,
                uploadedAt: new Date()
            });
        }

        res.status(201).json({
            message: 'Файлы успешно загружены',
            files: uploadedFiles
        });

    } catch (error) {
        // Удаление загруженных файлов в случае ошибки
        if (req.files) {
            for (const file of req.files) {
                try {
                    await fs.unlink(file.path);
                } catch (unlinkError) {
                    console.error('Ошибка удаления файла:', unlinkError);
                }
            }
        }
        next(error);
    }
});

// GET /api/files/:requestId - Получение списка файлов заявки
router.get('/:requestId', async (req, res, next) => {
    try {
        const { requestId } = req.params;

        // Проверка существования заявки
        const requests = await query('SELECT id FROM delivery_requests WHERE id = ?', [requestId]);
        if (requests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        const files = await query(`
            SELECT f.id, f.file_name, f.file_path, f.file_type, f.file_size, 
                   f.file_category, f.created_at, f.uploaded_by,
                   u.first_name, u.last_name, u.middle_name
            FROM files f
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.request_id = ?
            ORDER BY f.created_at DESC
        `, [requestId]);

        const formattedFiles = files.map(file => ({
            id: file.id,
            fileName: file.file_name,
            filePath: file.file_path,
            fileType: file.file_type,
            fileSize: file.file_size,
            category: file.file_category,
            uploadedAt: file.created_at,
            uploadedBy: file.first_name ? {
                id: file.uploaded_by,
                name: `${file.last_name} ${file.first_name} ${file.middle_name || ''}`.trim()
            } : null
        }));

        res.json({
            files: formattedFiles
        });

    } catch (error) {
        next(error);
    }
});

// GET /api/files/download/:fileId - Скачивание файла
router.get('/download/:fileId', async (req, res, next) => {
    try {
        const { fileId } = req.params;

        const files = await query(`
            SELECT f.file_name, f.file_path, f.file_type, f.file_size,
                   dr.request_number, dr.client_name
            FROM files f
            JOIN delivery_requests dr ON f.request_id = dr.id
            WHERE f.id = ?
        `, [fileId]);

        if (files.length === 0) {
            return res.status(404).json({
                error: 'Файл не найден'
            });
        }

        const file = files[0];

        // Проверка существования файла на диске
        try {
            await fs.access(file.file_path);
        } catch (error) {
            return res.status(404).json({
                error: 'Файл не найден на диске'
            });
        }

        // Установка заголовков для скачивания
        res.setHeader('Content-Disposition', `attachment; filename="${file.file_name}"`);
        res.setHeader('Content-Type', file.file_type);
        res.setHeader('Content-Length', file.file_size);

        // Отправка файла
        res.sendFile(path.resolve(file.file_path));

    } catch (error) {
        next(error);
    }
});

// GET /api/files/view/:fileId - Просмотр файла в браузере
router.get('/view/:fileId', async (req, res, next) => {
    try {
        const { fileId } = req.params;

        const files = await query(`
            SELECT f.file_name, f.file_path, f.file_type, f.file_size
            FROM files f
            WHERE f.id = ?
        `, [fileId]);

        if (files.length === 0) {
            return res.status(404).json({
                error: 'Файл не найден'
            });
        }

        const file = files[0];

        // Проверка существования файла на диске
        try {
            await fs.access(file.file_path);
        } catch (error) {
            return res.status(404).json({
                error: 'Файл не найден на диске'
            });
        }

        // Проверка типа файла для просмотра в браузере
        const viewableTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!viewableTypes.includes(file.file_type)) {
            return res.status(400).json({
                error: 'Файл не может быть просмотрен в браузере'
            });
        }

        // Установка заголовков для просмотра
        res.setHeader('Content-Type', file.file_type);
        res.setHeader('Content-Length', file.file_size);

        // Отправка файла
        res.sendFile(path.resolve(file.file_path));

    } catch (error) {
        next(error);
    }
});

// DELETE /api/files/:fileId - Удаление файла
router.delete('/:fileId', requirePermission('edit_requests'), async (req, res, next) => {
    try {
        const { fileId } = req.params;

        const files = await query(`
            SELECT f.file_path, f.file_name, f.request_id,
                   dr.request_number, dr.client_name
            FROM files f
            JOIN delivery_requests dr ON f.request_id = dr.id
            WHERE f.id = ?
        `, [fileId]);

        if (files.length === 0) {
            return res.status(404).json({
                error: 'Файл не найден'
            });
        }

        const file = files[0];

        // Проверка прав доступа (только старший курьер и администратор могут удалять файлы)
        if (!['admin', 'senior_courier'].includes(req.user.role)) {
            return res.status(403).json({
                error: 'Недостаточно прав для удаления файла'
            });
        }

        // Удаление файла из базы данных
        await query('DELETE FROM files WHERE id = ?', [fileId]);

        // Удаление файла с диска
        try {
            await fs.unlink(file.file_path);
        } catch (error) {
            console.error('Ошибка удаления файла с диска:', error);
            // Не возвращаем ошибку, так как запись из БД уже удалена
        }

        res.json({
            message: 'Файл успешно удален'
        });

    } catch (error) {
        next(error);
    }
});

// POST /api/files/signature - Загрузка электронной подписи
router.post('/signature', requirePermission('upload_photos'), upload.single('signature'), async (req, res, next) => {
    try {
        const { request_id } = req.body;

        if (!request_id) {
            return res.status(400).json({
                error: 'ID заявки обязателен'
            });
        }

        if (!req.file) {
            return res.status(400).json({
                error: 'Файл подписи не загружен'
            });
        }

        // Проверка существования заявки
        const requests = await query('SELECT id FROM delivery_requests WHERE id = ?', [request_id]);
        if (requests.length === 0) {
            return res.status(404).json({
                error: 'Заявка не найдена'
            });
        }

        // Сохранение информации о подписи в базе данных
        const fileData = {
            request_id: parseInt(request_id),
            file_name: req.file.originalname,
            file_path: req.file.path,
            file_type: req.file.mimetype,
            file_size: req.file.size,
            file_category: 'signature',
            uploaded_by: req.user.id
        };

        const result = await query(`
            INSERT INTO files (request_id, file_name, file_path, file_type, file_size, file_category, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        `, [
            fileData.request_id,
            fileData.file_name,
            fileData.file_path,
            fileData.file_type,
            fileData.file_size,
            fileData.file_category,
            fileData.uploaded_by
        ]);

        res.status(201).json({
            message: 'Электронная подпись успешно загружена',
            file: {
                id: result.insertId,
                fileName: fileData.file_name,
                filePath: fileData.file_path,
                fileType: fileData.file_type,
                fileSize: fileData.file_size,
                category: fileData.file_category,
                uploadedAt: new Date()
            }
        });

    } catch (error) {
        // Удаление загруженного файла в случае ошибки
        if (req.file) {
            try {
                await fs.unlink(req.file.path);
            } catch (unlinkError) {
                console.error('Ошибка удаления файла подписи:', unlinkError);
            }
        }
        next(error);
    }
});

// Middleware для обработки ошибок multer
router.use((error, req, res, next) => {
    if (error instanceof multer.MulterError) {
        if (error.code === 'LIMIT_FILE_SIZE') {
            return res.status(413).json({
                error: 'Файл слишком большой',
                details: `Максимальный размер файла: ${process.env.MAX_FILE_SIZE || '10MB'}`
            });
        }
        if (error.code === 'LIMIT_FILE_COUNT') {
            return res.status(400).json({
                error: 'Слишком много файлов',
                details: 'Максимальное количество файлов: 10'
            });
        }
        if (error.code === 'LIMIT_UNEXPECTED_FILE') {
            return res.status(400).json({
                error: 'Неожиданное поле файла',
                details: error.message
            });
        }
    }
    
    if (error.message.includes('Недопустимый тип файла')) {
        return res.status(400).json({
            error: error.message
        });
    }

    next(error);
});

module.exports = router;