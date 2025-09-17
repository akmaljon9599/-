<?php

namespace CourierService\Service;

use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Config\Option;
use CourierService\Entity\DocumentTable;
use CourierService\Entity\RequestTable;

class DocumentService
{
    private $uploadDir;
    private $allowedTypes = [
        'contract' => ['pdf', 'doc', 'docx'],
        'passport' => ['jpg', 'jpeg', 'png', 'pdf'],
        'delivery_photo' => ['jpg', 'jpeg', 'png'],
        'signature' => ['png', 'jpg', 'jpeg']
    ];

    private $maxFileSize = 10 * 1024 * 1024; // 10MB

    public function __construct()
    {
        $this->uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/documents/';
        $this->ensureUploadDirectory();
    }

    public function uploadDocument($requestId, $file, $type)
    {
        try {
            // Проверяем существование заявки
            $request = RequestTable::getById($requestId)->fetch();
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Request not found'
                ];
            }

            // Валидация файла
            $validation = $this->validateFile($file, $type);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'File validation failed',
                    'errors' => $validation['errors']
                ];
            }

            // Генерируем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = $this->generateFileName($requestId, $type, $extension);
            $filePath = $this->uploadDir . $fileName;

            // Сохраняем файл
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to save file'
                ];
            }

            // Сохраняем информацию о документе в БД
            $documentData = [
                'REQUEST_ID' => $requestId,
                'TYPE' => $type,
                'FILE_PATH' => $filePath,
                'FILE_NAME' => $fileName,
                'FILE_SIZE' => $file['size'],
                'MIME_TYPE' => $file['type'],
                'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
            ];

            $result = DocumentTable::add($documentData);
            if (!$result->isSuccess()) {
                // Удаляем файл если не удалось сохранить в БД
                unlink($filePath);
                return [
                    'success' => false,
                    'message' => 'Failed to save document info',
                    'errors' => $result->getErrorMessages()
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $result->getId(),
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    public function getDocuments($requestId, $type = null)
    {
        $filter = ['REQUEST_ID' => $requestId];
        if ($type) {
            $filter['TYPE'] = $type;
        }

        $result = DocumentTable::getList([
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC']
        ]);

        $documents = [];
        while ($row = $result->fetch()) {
            $documents[] = [
                'id' => $row['ID'],
                'type' => $row['TYPE'],
                'type_text' => DocumentTable::getDocumentTypes()[$row['TYPE']] ?? $row['TYPE'],
                'file_name' => $row['FILE_NAME'],
                'file_size' => $row['FILE_SIZE'],
                'mime_type' => $row['MIME_TYPE'],
                'created_at' => $row['CREATED_AT']->format('Y-m-d H:i:s'),
                'download_url' => $this->getDownloadUrl($row['ID'])
            ];
        }

        return $documents;
    }

    public function deleteDocument($documentId)
    {
        $document = DocumentTable::getById($documentId)->fetch();
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Document not found'
            ];
        }

        // Удаляем файл
        if (file_exists($document['FILE_PATH'])) {
            unlink($document['FILE_PATH']);
        }

        // Удаляем запись из БД
        $result = DocumentTable::delete($documentId);
        if ($result->isSuccess()) {
            return [
                'success' => true,
                'message' => 'Document deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to delete document',
                'errors' => $result->getErrorMessages()
            ];
        }
    }

    public function generateContract($requestId)
    {
        $request = RequestTable::getById($requestId)->fetch();
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Request not found'
            ];
        }

        // Получаем шаблон договора
        $templatePath = Option::get('courier_service', 'contract_template_path', '/upload/courier_service/contracts/');
        $templateFile = $_SERVER['DOCUMENT_ROOT'] . $templatePath . 'contract_template.docx';

        if (!file_exists($templateFile)) {
            return [
                'success' => false,
                'message' => 'Contract template not found'
            ];
        }

        // Генерируем PDF из шаблона
        $pdfPath = $this->generatePdfFromTemplate($request, $templateFile);
        if (!$pdfPath) {
            return [
                'success' => false,
                'message' => 'Failed to generate PDF'
            ];
        }

        // Сохраняем как документ
        $documentData = [
            'REQUEST_ID' => $requestId,
            'TYPE' => 'contract',
            'FILE_PATH' => $pdfPath,
            'FILE_NAME' => 'contract_' . $request['REQUEST_NUMBER'] . '.pdf',
            'FILE_SIZE' => filesize($pdfPath),
            'MIME_TYPE' => 'application/pdf',
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
        ];

        $result = DocumentTable::add($documentData);
        if ($result->isSuccess()) {
            return [
                'success' => true,
                'data' => [
                    'id' => $result->getId(),
                    'file_path' => $pdfPath,
                    'download_url' => $this->getDownloadUrl($result->getId())
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save contract',
                'errors' => $result->getErrorMessages()
            ];
        }
    }

    public function addSignatureToContract($documentId, $signatureData)
    {
        $document = DocumentTable::getById($documentId)->fetch();
        if (!$document || $document['TYPE'] !== 'contract') {
            return [
                'success' => false,
                'message' => 'Contract document not found'
            ];
        }

        // Создаем изображение подписи
        $signatureImage = $this->createSignatureImage($signatureData);
        if (!$signatureImage) {
            return [
                'success' => false,
                'message' => 'Failed to create signature image'
            ];
        }

        // Добавляем подпись к PDF
        $signedPdfPath = $this->addSignatureToPdf($document['FILE_PATH'], $signatureImage);
        if (!$signedPdfPath) {
            return [
                'success' => false,
                'message' => 'Failed to add signature to PDF'
            ];
        }

        // Обновляем файл договора
        unlink($document['FILE_PATH']);
        rename($signedPdfPath, $document['FILE_PATH']);

        // Сохраняем подпись как отдельный документ
        $signaturePath = $this->uploadDir . 'signature_' . $document['REQUEST_ID'] . '_' . time() . '.png';
        imagepng($signatureImage, $signaturePath);
        imagedestroy($signatureImage);

        $signatureData = [
            'REQUEST_ID' => $document['REQUEST_ID'],
            'TYPE' => 'signature',
            'FILE_PATH' => $signaturePath,
            'FILE_NAME' => 'signature_' . $document['REQUEST_ID'] . '.png',
            'FILE_SIZE' => filesize($signaturePath),
            'MIME_TYPE' => 'image/png',
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
        ];

        DocumentTable::add($signatureData);

        return [
            'success' => true,
            'message' => 'Signature added successfully'
        ];
    }

    private function validateFile($file, $type)
    {
        $errors = [];

        // Проверяем размер файла
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        // Проверяем тип файла
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($this->allowedTypes[$type]) || !in_array($extension, $this->allowedTypes[$type])) {
            $errors[] = 'File type not allowed for this document type';
        }

        // Проверяем MIME тип
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (isset($allowedMimes[$extension]) && $file['type'] !== $allowedMimes[$extension]) {
            $errors[] = 'File MIME type does not match extension';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function generateFileName($requestId, $type, $extension)
    {
        return $type . '_' . $requestId . '_' . time() . '.' . $extension;
    }

    private function ensureUploadDirectory()
    {
        if (!Directory::isDirectoryExists($this->uploadDir)) {
            Directory::createDirectory($this->uploadDir);
        }
    }

    private function getDownloadUrl($documentId)
    {
        return '/bitrix/admin/courier_service_download.php?id=' . $documentId;
    }

    private function generatePdfFromTemplate($request, $templateFile)
    {
        // Здесь должна быть логика генерации PDF из шаблона
        // Для примера создаем простой PDF
        $pdfPath = $this->uploadDir . 'contract_' . $request['REQUEST_NUMBER'] . '.pdf';
        
        // В реальном проекте здесь будет использоваться библиотека для работы с PDF
        // Например, TCPDF, FPDF или PhpOffice/PhpWord для конвертации DOCX в PDF
        
        return $pdfPath;
    }

    private function createSignatureImage($signatureData)
    {
        // Создаем изображение подписи из данных canvas
        $image = imagecreatefromstring(base64_decode($signatureData));
        if (!$image) {
            return false;
        }

        // Изменяем размер если нужно
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width > 400 || $height > 200) {
            $newWidth = min(400, $width);
            $newHeight = min(200, $height);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        return $image;
    }

    private function addSignatureToPdf($pdfPath, $signatureImage)
    {
        // Здесь должна быть логика добавления подписи к PDF
        // Для примера просто возвращаем путь к PDF
        return $pdfPath;
    }
}