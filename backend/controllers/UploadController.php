<?php
/**
 * Контроллер загрузки файлов
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/DeliveryRequest.php';

class UploadController {
    private $config;
    private $db;

    public function __construct() {
        $this->config = require_once __DIR__ . '/../config/app.php';
        $this->db = Database::getInstance();
    }

    /**
     * Загрузить фотографию доставки
     */
    public function uploadPhoto() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $this->response(['error' => 'Файл не загружен'], 400);
                return;
            }

            $file = $_FILES['photo'];
            
            // Проверяем тип файла
            if (!in_array($file['type'], $this->config['upload']['allowed_photo_types'])) {
                $this->response(['error' => 'Недопустимый тип файла'], 400);
                return;
            }

            // Проверяем размер файла
            if ($file['size'] > $this->config['upload']['max_file_size']) {
                $this->response(['error' => 'Файл слишком большой'], 400);
                return;
            }

            $uploadPath = $this->saveFile($file, 'photos');
            
            if ($uploadPath) {
                $this->response([
                    'success' => true,
                    'file_path' => $uploadPath,
                    'message' => 'Фотография загружена успешно'
                ]);
            } else {
                $this->response(['error' => 'Ошибка сохранения файла'], 500);
            }

        } catch (Exception $e) {
            error_log('Upload photo error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка загрузки фотографии'], 500);
        }
    }

    /**
     * Загрузить документ
     */
    public function uploadDocument() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $this->response(['error' => 'Файл не загружен'], 400);
                return;
            }

            $file = $_FILES['document'];
            $requestId = $_POST['request_id'] ?? null;
            $documentType = $_POST['document_type'] ?? 'other';

            if (!$requestId) {
                $this->response(['error' => 'Не указан ID заявки'], 400);
                return;
            }

            // Проверяем тип файла
            if (!in_array($file['type'], $this->config['upload']['allowed_document_types'])) {
                $this->response(['error' => 'Недопустимый тип файла'], 400);
                return;
            }

            // Проверяем размер файла
            if ($file['size'] > $this->config['upload']['max_file_size']) {
                $this->response(['error' => 'Файл слишком большой'], 400);
                return;
            }

            // Проверяем доступ к заявке
            $requestModel = new DeliveryRequest();
            $request = $requestModel->findById($requestId);
            
            if (!$request || !AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            $uploadPath = $this->saveFile($file, 'documents');
            
            if ($uploadPath) {
                // Сохраняем информацию о документе в базе данных
                $user = AuthMiddleware::getCurrentUser();
                $documentData = [
                    'request_id' => $requestId,
                    'document_type' => $documentType,
                    'file_name' => $file['name'],
                    'file_path' => $uploadPath,
                    'file_size' => $file['size'],
                    'mime_type' => $file['type'],
                    'uploaded_by' => $user['id']
                ];

                $documentId = $this->db->insert('documents', $documentData);

                AuthMiddleware::logActivity('upload_document', 'document', $documentId, $documentData);

                $this->response([
                    'success' => true,
                    'document_id' => $documentId,
                    'file_path' => $uploadPath,
                    'message' => 'Документ загружен успешно'
                ]);
            } else {
                $this->response(['error' => 'Ошибка сохранения файла'], 500);
            }

        } catch (Exception $e) {
            error_log('Upload document error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка загрузки документа'], 500);
        }
    }

    /**
     * Загрузить электронную подпись
     */
    public function uploadSignature() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['signature_data']) || empty($input['request_id'])) {
                $this->response(['error' => 'Не переданы данные подписи'], 400);
                return;
            }

            $requestId = $input['request_id'];
            $signatureData = $input['signature_data'];

            // Проверяем доступ к заявке
            $requestModel = new DeliveryRequest();
            $request = $requestModel->findById($requestId);
            
            if (!$request || !AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            // Декодируем base64 изображение
            if (preg_match('/^data:image\/(\w+);base64,/', $signatureData, $matches)) {
                $imageType = $matches[1];
                $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
                $signatureData = base64_decode($signatureData);

                if ($signatureData === false) {
                    $this->response(['error' => 'Некорректные данные подписи'], 400);
                    return;
                }

                // Создаем уникальное имя файла
                $fileName = 'signature_' . $requestId . '_' . time() . '.' . $imageType;
                $uploadDir = __DIR__ . '/../../uploads/signatures/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filePath = $uploadDir . $fileName;
                $relativePath = '/uploads/signatures/' . $fileName;

                if (file_put_contents($filePath, $signatureData)) {
                    // Обновляем заявку с путем к подписи
                    $requestModel->update($requestId, [
                        'signature_path' => $relativePath,
                        'contract_signed' => 1
                    ]);

                    AuthMiddleware::logActivity('upload_signature', 'delivery_request', $requestId);

                    $this->response([
                        'success' => true,
                        'signature_path' => $relativePath,
                        'message' => 'Подпись сохранена успешно'
                    ]);
                } else {
                    $this->response(['error' => 'Ошибка сохранения подписи'], 500);
                }
            } else {
                $this->response(['error' => 'Некорректный формат данных'], 400);
            }

        } catch (Exception $e) {
            error_log('Upload signature error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка загрузки подписи'], 500);
        }
    }

    /**
     * Получить документы заявки
     */
    public function getRequestDocuments($requestId) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            // Проверяем доступ к заявке
            $requestModel = new DeliveryRequest();
            $request = $requestModel->findById($requestId);
            
            if (!$request || !AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            $sql = "
                SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.request_id = :request_id
                ORDER BY d.created_at DESC
            ";

            $documents = $this->db->select($sql, ['request_id' => $requestId]);

            $this->response([
                'success' => true,
                'data' => $documents
            ]);

        } catch (Exception $e) {
            error_log('Get documents error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения документов'], 500);
        }
    }

    /**
     * Удалить документ
     */
    public function deleteDocument($documentId) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            // Получаем информацию о документе
            $sql = "SELECT * FROM documents WHERE id = :id";
            $document = $this->db->selectOne($sql, ['id' => $documentId]);

            if (!$document) {
                $this->response(['error' => 'Документ не найден'], 404);
                return;
            }

            // Проверяем доступ к заявке
            $requestModel = new DeliveryRequest();
            $request = $requestModel->findById($document['request_id']);
            
            if (!$request || !AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            // Удаляем файл с диска
            $filePath = __DIR__ . '/../../' . ltrim($document['file_path'], '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Удаляем запись из базы данных
            $this->db->delete('documents', 'id = :id', ['id' => $documentId]);

            AuthMiddleware::logActivity('delete_document', 'document', $documentId);

            $this->response([
                'success' => true,
                'message' => 'Документ удален успешно'
            ]);

        } catch (Exception $e) {
            error_log('Delete document error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка удаления документа'], 500);
        }
    }

    /**
     * Сохранить файл на диск
     */
    private function saveFile($file, $folder) {
        $uploadDir = __DIR__ . '/../../uploads/' . $folder . '/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;
        $relativePath = '/uploads/' . $folder . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return $relativePath;
        }

        return false;
    }

    /**
     * Получить MIME тип файла
     */
    private function getMimeType($filePath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType;
        }
        
        return 'application/octet-stream';
    }

    /**
     * Отправить HTTP ответ
     */
    private function response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}