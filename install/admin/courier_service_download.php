<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\IO\File;
use CourierService\Service\AuthService;
use CourierService\Entity\DocumentTable;

Loader::includeModule('courier_service');

$authService = new AuthService();
if (!$authService->isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

$documentId = (int)$_GET['id'];
if (!$documentId) {
    http_response_code(400);
    die('Document ID is required');
}

try {
    $document = DocumentTable::getById($documentId)->fetch();
    if (!$document) {
        http_response_code(404);
        die('Document not found');
    }

    // Проверяем права доступа
    $request = \CourierService\Entity\RequestTable::getById($document['REQUEST_ID'])->fetch();
    if (!$request) {
        http_response_code(404);
        die('Request not found');
    }

    // Проверяем права пользователя на доступ к заявке
    $currentUser = $authService->getCurrentUser();
    $hasAccess = false;

    switch ($currentUser['ROLE']) {
        case 'admin':
            $hasAccess = true;
            break;
        case 'senior_courier':
            $hasAccess = true;
            break;
        case 'courier':
            $hasAccess = ($request['COURIER_ID'] == $currentUser['ID']);
            break;
        case 'operator':
            $hasAccess = ($request['OPERATOR_ID'] == $currentUser['USER_ID']);
            break;
    }

    if (!$hasAccess) {
        http_response_code(403);
        die('Access denied');
    }

    $filePath = $document['FILE_PATH'];
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }

    $file = new File($filePath);
    $fileName = $document['FILE_NAME'];
    $fileSize = $file->getSize();
    $mimeType = $document['MIME_TYPE'];

    // Логируем скачивание
    \CourierService\Service\LogService::log(
        'download_document',
        'document',
        $documentId,
        ['file_name' => $fileName, 'file_size' => $fileSize],
        $currentUser['USER_ID']
    );

    // Отправляем файл
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}