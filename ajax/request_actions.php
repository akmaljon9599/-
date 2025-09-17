<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use CourierService\Main\RequestTable;
use CourierService\Security\PermissionManager;
use CourierService\Security\AuditLogger;
use CourierService\Utils\SignatureHandler;
use CourierService\Utils\PdfGenerator;

if (!Loader::includeModule('courier_service')) {
    Json::encode(['success' => false, 'error' => 'Модуль не установлен']);
    exit;
}

// Проверка CSRF токена
if (!check_bitrix_sessid()) {
    Json::encode(['success' => false, 'error' => 'Неверный CSRF токен']);
    exit;
}

$action = $_POST['action'] ?? '';
$requestId = intval($_POST['request_id'] ?? 0);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'update_status':
            handleUpdateStatus($requestId);
            break;
            
        case 'assign_courier':
            handleAssignCourier($requestId);
            break;
            
        case 'save_signature':
            handleSaveSignature($requestId);
            break;
            
        case 'upload_photos':
            handleUploadPhotos($requestId);
            break;
            
        case 'generate_contract':
            handleGenerateContract($requestId);
            break;
            
        default:
            echo Json::encode(['success' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    AuditLogger::logError('Request Actions', $action, $e->getMessage());
    echo Json::encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleUpdateStatus($requestId)
{
    if (!$requestId) {
        throw new Exception('Не указан ID заявки');
    }
    
    if (!PermissionManager::checkRequestAccess($requestId, 'edit_status')) {
        throw new Exception('Доступ запрещен');
    }
    
    $newStatus = $_POST['status'] ?? '';
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    $courierPhone = $_POST['courier_phone'] ?? '';
    
    // Получаем текущие данные заявки
    $request = RequestTable::getList([
        'filter' => ['ID' => $requestId],
        'select' => ['*']
    ])->fetch();
    
    if (!$request) {
        throw new Exception('Заявка не найдена');
    }
    
    $oldData = $request;
    $updateData = [
        'STATUS' => $newStatus,
        'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime(),
        'MODIFIED_BY' => $GLOBALS['USER']->GetID()
    ];
    
    if ($newStatus === 'delivered') {
        $updateData['DELIVERY_DATE'] = new \Bitrix\Main\Type\DateTime();
        $updateData['COURIER_PHONE'] = $courierPhone;
    } elseif ($newStatus === 'rejected') {
        $updateData['REJECTION_REASON'] = $rejectionReason;
    }
    
    $result = RequestTable::update($requestId, $updateData);
    
    if ($result->isSuccess()) {
        AuditLogger::logRequestChange(
            $GLOBALS['USER']->GetID(),
            $requestId,
            'status_update',
            $oldData,
            array_merge($request, $updateData)
        );
        
        echo Json::encode(['success' => true, 'message' => 'Статус обновлен']);
    } else {
        throw new Exception('Ошибка обновления статуса: ' . implode(', ', $result->getErrorMessages()));
    }
}

function handleAssignCourier($requestId)
{
    if (!$requestId) {
        throw new Exception('Не указан ID заявки');
    }
    
    if (!PermissionManager::checkRequestAccess($requestId, 'assign')) {
        throw new Exception('Доступ запрещен');
    }
    
    $courierId = intval($_POST['courier_id'] ?? 0);
    
    if (!$courierId) {
        throw new Exception('Не указан ID курьера');
    }
    
    // Получаем текущие данные заявки
    $request = RequestTable::getList([
        'filter' => ['ID' => $requestId],
        'select' => ['*']
    ])->fetch();
    
    if (!$request) {
        throw new Exception('Заявка не найдена');
    }
    
    $oldData = $request;
    $updateData = [
        'COURIER_ID' => $courierId,
        'STATUS' => 'waiting',
        'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime(),
        'MODIFIED_BY' => $GLOBALS['USER']->GetID()
    ];
    
    $result = RequestTable::update($requestId, $updateData);
    
    if ($result->isSuccess()) {
        AuditLogger::logRequestChange(
            $GLOBALS['USER']->GetID(),
            $requestId,
            'assign_courier',
            $oldData,
            array_merge($request, $updateData)
        );
        
        echo Json::encode(['success' => true, 'message' => 'Курьер назначен']);
    } else {
        throw new Exception('Ошибка назначения курьера: ' . implode(', ', $result->getErrorMessages()));
    }
}

function handleSaveSignature($requestId)
{
    if (!$requestId) {
        throw new Exception('Не указан ID заявки');
    }
    
    if (!PermissionManager::checkRequestAccess($requestId, 'edit')) {
        throw new Exception('Доступ запрещен');
    }
    
    $signatureData = $_POST['signature_data'] ?? '';
    $signatureFormat = $_POST['signature_format'] ?? 'base64';
    
    if (!$signatureData) {
        throw new Exception('Данные подписи не переданы');
    }
    
    $signatureHandler = new SignatureHandler();
    
    // Валидируем подпись
    if (!$signatureHandler->validateSignature($signatureData, $signatureFormat)) {
        throw new Exception('Неверный формат подписи');
    }
    
    // Сохраняем подпись
    $result = $signatureHandler->saveSignature($requestId, $signatureData, $signatureFormat);
    
    if ($result['success']) {
        // Обновляем заявку
        RequestTable::update($requestId, [
            'SIGNATURE_DATA' => $signatureData,
            'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime(),
            'MODIFIED_BY' => $GLOBALS['USER']->GetID()
        ]);
        
        AuditLogger::logRequestChange(
            $GLOBALS['USER']->GetID(),
            $requestId,
            'signature_saved',
            null,
            ['signature_file' => $result['filename']]
        );
        
        echo Json::encode([
            'success' => true,
            'message' => 'Подпись сохранена',
            'signature_url' => $result['url']
        ]);
    } else {
        throw new Exception($result['error']);
    }
}

function handleUploadPhotos($requestId)
{
    if (!$requestId) {
        throw new Exception('Не указан ID заявки');
    }
    
    if (!PermissionManager::checkRequestAccess($requestId, 'upload_photos')) {
        throw new Exception('Доступ запрещен');
    }
    
    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        throw new Exception('Фотографии не загружены');
    }
    
    $uploadedFiles = [];
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/courier_service/delivery_photos/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    foreach ($_FILES['photos']['name'] as $key => $filename) {
        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = 'delivery_' . $requestId . '_' . time() . '_' . $key . '.' . $extension;
            $filepath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $filepath)) {
                // Сохраняем информацию о файле в базе данных
                \CourierService\Main\DocumentTable::add([
                    'REQUEST_ID' => $requestId,
                    'TYPE' => 'delivery_photo',
                    'FILE_PATH' => $filepath,
                    'FILE_NAME' => $newFilename,
                    'FILE_SIZE' => $_FILES['photos']['size'][$key],
                    'MIME_TYPE' => $_FILES['photos']['type'][$key],
                    'UPLOADED_BY' => $GLOBALS['USER']->GetID(),
                    'UPLOAD_DATE' => new \Bitrix\Main\Type\DateTime()
                ]);
                
                $uploadedFiles[] = [
                    'filename' => $newFilename,
                    'url' => '/upload/courier_service/delivery_photos/' . $newFilename
                ];
            }
        }
    }
    
    if (!empty($uploadedFiles)) {
        // Обновляем заявку
        $request = RequestTable::getList([
            'filter' => ['ID' => $requestId],
            'select' => ['DELIVERY_PHOTOS']
        ])->fetch();
        
        $existingPhotos = $request['DELIVERY_PHOTOS'] ? json_decode($request['DELIVERY_PHOTOS'], true) : [];
        $allPhotos = array_merge($existingPhotos, $uploadedFiles);
        
        RequestTable::update($requestId, [
            'DELIVERY_PHOTOS' => json_encode($allPhotos),
            'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime(),
            'MODIFIED_BY' => $GLOBALS['USER']->GetID()
        ]);
        
        AuditLogger::logRequestChange(
            $GLOBALS['USER']->GetID(),
            $requestId,
            'photos_uploaded',
            null,
            ['uploaded_files' => $uploadedFiles]
        );
        
        echo Json::encode([
            'success' => true,
            'message' => 'Фотографии загружены',
            'uploaded_files' => $uploadedFiles
        ]);
    } else {
        throw new Exception('Не удалось загрузить фотографии');
    }
}

function handleGenerateContract($requestId)
{
    if (!$requestId) {
        throw new Exception('Не указан ID заявки');
    }
    
    if (!PermissionManager::checkRequestAccess($requestId, 'view')) {
        throw new Exception('Доступ запрещен');
    }
    
    $request = RequestTable::getList([
        'filter' => ['ID' => $requestId],
        'select' => ['*']
    ])->fetch();
    
    if (!$request) {
        throw new Exception('Заявка не найдена');
    }
    
    // Получаем подпись если есть
    $signatureHandler = new SignatureHandler();
    $signatureResult = $signatureHandler->getSignature($requestId);
    $signatureData = $signatureResult['success'] ? file_get_contents($signatureResult['filepath']) : null;
    
    $pdfGenerator = new PdfGenerator();
    $result = $pdfGenerator->generateContract($request, $signatureData);
    
    if ($result['success']) {
        // Обновляем заявку с ссылкой на договор
        RequestTable::update($requestId, [
            'CONTRACT_PDF' => $result['filename'],
            'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime(),
            'MODIFIED_BY' => $GLOBALS['USER']->GetID()
        ]);
        
        AuditLogger::logRequestChange(
            $GLOBALS['USER']->GetID(),
            $requestId,
            'contract_generated',
            null,
            ['contract_file' => $result['filename']]
        );
        
        echo Json::encode([
            'success' => true,
            'message' => 'Договор сгенерирован',
            'contract_url' => $result['url']
        ]);
    } else {
        throw new Exception($result['error']);
    }
}