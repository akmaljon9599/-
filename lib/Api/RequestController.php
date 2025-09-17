<?php

namespace CourierService\Api;

use Bitrix\Main\Web\Json;
use Bitrix\Main\Type\DateTime;
use CourierService\Entity\RequestTable;
use CourierService\Entity\DocumentTable;
use CourierService\Service\DocumentService;
use CourierService\Service\NotificationService;

class RequestController extends BaseController
{
    public function indexAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $page = (int)$request->get('page') ?: 1;
        $limit = (int)$request->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;

        // Получаем фильтры
        $filters = $this->getFilters();
        
        // Получаем заявки
        $result = RequestTable::getList([
            'filter' => $filters,
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
            'select' => ['*']
        ]);

        $requests = [];
        while ($row = $result->fetch()) {
            $requests[] = $this->formatRequest($row);
        }

        // Получаем общее количество
        $countResult = RequestTable::getList([
            'filter' => $filters,
            'select' => ['CNT'],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);
        $totalCount = $countResult->fetch()['CNT'];

        $this->sendSuccessResponse([
            'requests' => $requests,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    }

    public function showAction($id)
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $request = RequestTable::getById($id)->fetch();
        if (!$request) {
            $this->sendErrorResponse('Request not found', 404);
            return;
        }

        $this->sendSuccessResponse($this->formatRequest($request));
    }

    public function createAction()
    {
        if (!$this->validatePermission('create', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $data = Json::decode($request->getInput());

        // Валидация данных
        $validation = $this->validateRequestData($data);
        if (!$validation['valid']) {
            $this->sendErrorResponse('Validation failed', 400, $validation['errors']);
            return;
        }

        // Генерируем номер заявки
        $data['REQUEST_NUMBER'] = RequestTable::generateRequestNumber();
        $data['OPERATOR_ID'] = $this->currentUser['USER_ID'];
        $data['CREATED_AT'] = new DateTime();
        $data['UPDATED_AT'] = new DateTime();

        $result = RequestTable::add($data);
        if ($result->isSuccess()) {
            $requestId = $result->getId();
            $this->logAction('create', 'request', $requestId, $data);
            
            // Отправляем уведомление
            NotificationService::sendRequestCreatedNotification($requestId);
            
            $this->sendSuccessResponse(['id' => $requestId], 'Request created successfully');
        } else {
            $this->sendErrorResponse('Failed to create request', 500, $result->getErrorMessages());
        }
    }

    public function updateAction($id)
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $data = Json::decode($request->getInput());

        // Проверяем существование заявки
        $existingRequest = RequestTable::getById($id)->fetch();
        if (!$existingRequest) {
            $this->sendErrorResponse('Request not found', 404);
            return;
        }

        // Валидация данных
        $validation = $this->validateRequestData($data, $id);
        if (!$validation['valid']) {
            $this->sendErrorResponse('Validation failed', 400, $validation['errors']);
            return;
        }

        $data['UPDATED_AT'] = new DateTime();

        $result = RequestTable::update($id, $data);
        if ($result->isSuccess()) {
            $this->logAction('update', 'request', $id, $data);
            
            // Отправляем уведомление об изменении
            NotificationService::sendRequestUpdatedNotification($id);
            
            $this->sendSuccessResponse([], 'Request updated successfully');
        } else {
            $this->sendErrorResponse('Failed to update request', 500, $result->getErrorMessages());
        }
    }

    public function deleteAction($id)
    {
        if (!$this->validatePermission('delete', 'requests')) {
            return;
        }

        $request = RequestTable::getById($id)->fetch();
        if (!$request) {
            $this->sendErrorResponse('Request not found', 404);
            return;
        }

        $result = RequestTable::delete($id);
        if ($result->isSuccess()) {
            $this->logAction('delete', 'request', $id);
            $this->sendSuccessResponse([], 'Request deleted successfully');
        } else {
            $this->sendErrorResponse('Failed to delete request', 500, $result->getErrorMessages());
        }
    }

    public function updateStatusAction($id)
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $data = Json::decode($request->getInput());
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            $this->sendErrorResponse('Status is required', 400);
            return;
        }

        $existingRequest = RequestTable::getById($id)->fetch();
        if (!$existingRequest) {
            $this->sendErrorResponse('Request not found', 404);
            return;
        }

        $updateData = [
            'STATUS' => $newStatus,
            'UPDATED_AT' => new DateTime()
        ];

        // Дополнительные поля в зависимости от статуса
        switch ($newStatus) {
            case 'delivered':
                $updateData['DELIVERY_DATE'] = new DateTime();
                $updateData['COURIER_PHONE'] = $data['courier_phone'] ?? null;
                break;
            case 'rejected':
                $updateData['REJECTION_REASON'] = $data['rejection_reason'] ?? null;
                break;
            case 'in_delivery':
                $updateData['PROCESSING_DATE'] = new DateTime();
                break;
        }

        $result = RequestTable::update($id, $updateData);
        if ($result->isSuccess()) {
            $this->logAction('update_status', 'request', $id, ['status' => $newStatus]);
            
            // Отправляем уведомление об изменении статуса
            NotificationService::sendStatusChangedNotification($id, $newStatus);
            
            $this->sendSuccessResponse([], 'Status updated successfully');
        } else {
            $this->sendErrorResponse('Failed to update status', 500, $result->getErrorMessages());
        }
    }

    public function uploadDocumentAction($id)
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $files = $request->getFileList();
        $documentType = $request->getPost('type');

        if (!$documentType || !isset($files['document'])) {
            $this->sendErrorResponse('Document type and file are required', 400);
            return;
        }

        $existingRequest = RequestTable::getById($id)->fetch();
        if (!$existingRequest) {
            $this->sendErrorResponse('Request not found', 404);
            return;
        }

        $documentService = new DocumentService();
        $result = $documentService->uploadDocument($id, $files['document'], $documentType);

        if ($result['success']) {
            $this->logAction('upload_document', 'request', $id, ['type' => $documentType]);
            $this->sendSuccessResponse($result['data'], 'Document uploaded successfully');
        } else {
            $this->sendErrorResponse($result['message'], 500, $result['errors'] ?? []);
        }
    }

    public function exportAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $filters = $this->getFilters();
        
        $result = RequestTable::getList([
            'filter' => $filters,
            'order' => ['CREATED_AT' => 'DESC'],
            'select' => ['*']
        ]);

        $requests = [];
        while ($row = $result->fetch()) {
            $requests[] = $this->formatRequestForExport($row);
        }

        $this->logAction('export', 'requests', 0, ['count' => count($requests)]);
        
        $this->sendSuccessResponse([
            'requests' => $requests,
            'exported_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    private function getFilters()
    {
        $request = $this->getRequest();
        $filters = [];

        // Фильтры по датам
        if ($dateFrom = $request->get('date_from')) {
            $filters['>=REGISTRATION_DATE'] = $dateFrom;
        }
        if ($dateTo = $request->get('date_to')) {
            $filters['<=REGISTRATION_DATE'] = $dateTo;
        }

        // Фильтр по статусу
        if ($status = $request->get('status')) {
            $filters['STATUS'] = $status;
        }

        // Фильтр по статусу звонка
        if ($callStatus = $request->get('call_status')) {
            $filters['CALL_STATUS'] = $callStatus;
        }

        // Фильтр по типу карты
        if ($cardType = $request->get('card_type')) {
            $filters['CARD_TYPE'] = $cardType;
        }

        // Фильтр по филиалу
        if ($branchId = $request->get('branch_id')) {
            $filters['BRANCH_ID'] = $branchId;
        }

        // Фильтр по курьеру
        if ($courierId = $request->get('courier_id')) {
            $filters['COURIER_ID'] = $courierId;
        }

        // Фильтр по ФИО клиента
        if ($clientName = $request->get('client_name')) {
            $filters['%CLIENT_NAME'] = $clientName;
        }

        // Фильтр по номеру клиента
        if ($clientPhone = $request->get('client_phone')) {
            $filters['%CLIENT_PHONE'] = $clientPhone;
        }

        // Фильтр по PAN
        if ($pan = $request->get('pan')) {
            $filters['%PAN'] = $pan;
        }

        return $filters;
    }

    private function validateRequestData($data, $id = null)
    {
        $errors = [];

        if (empty($data['CLIENT_NAME'])) {
            $errors[] = 'Client name is required';
        }

        if (empty($data['CLIENT_PHONE'])) {
            $errors[] = 'Client phone is required';
        }

        if (empty($data['CLIENT_ADDRESS'])) {
            $errors[] = 'Client address is required';
        }

        if (empty($data['PAN'])) {
            $errors[] = 'PAN is required';
        }

        if (empty($data['BRANCH_ID'])) {
            $errors[] = 'Branch is required';
        }

        // Проверяем уникальность PAN
        $filter = ['PAN' => $data['PAN']];
        if ($id) {
            $filter['!ID'] = $id;
        }

        $existingRequest = RequestTable::getList([
            'filter' => $filter,
            'select' => ['ID']
        ])->fetch();

        if ($existingRequest) {
            $errors[] = 'Request with this PAN already exists';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function formatRequest($request)
    {
        return [
            'id' => $request['ID'],
            'request_number' => $request['REQUEST_NUMBER'],
            'abs_id' => $request['ABS_ID'],
            'client_name' => $request['CLIENT_NAME'],
            'client_phone' => $request['CLIENT_PHONE'],
            'client_address' => $request['CLIENT_ADDRESS'],
            'pan' => $request['PAN'],
            'card_type' => $request['CARD_TYPE'],
            'status' => $request['STATUS'],
            'status_text' => RequestTable::getStatuses()[$request['STATUS']] ?? $request['STATUS'],
            'call_status' => $request['CALL_STATUS'],
            'call_status_text' => RequestTable::getCallStatuses()[$request['CALL_STATUS']] ?? $request['CALL_STATUS'],
            'courier_id' => $request['COURIER_ID'],
            'branch_id' => $request['BRANCH_ID'],
            'department_id' => $request['DEPARTMENT_ID'],
            'operator_id' => $request['OPERATOR_ID'],
            'registration_date' => $request['REGISTRATION_DATE']->format('Y-m-d H:i:s'),
            'processing_date' => $request['PROCESSING_DATE'] ? $request['PROCESSING_DATE']->format('Y-m-d H:i:s') : null,
            'delivery_date' => $request['DELIVERY_DATE'] ? $request['DELIVERY_DATE']->format('Y-m-d H:i:s') : null,
            'rejection_reason' => $request['REJECTION_REASON'],
            'courier_phone' => $request['COURIER_PHONE'],
            'created_at' => $request['CREATED_AT']->format('Y-m-d H:i:s'),
            'updated_at' => $request['UPDATED_AT']->format('Y-m-d H:i:s')
        ];
    }

    private function formatRequestForExport($request)
    {
        return [
            'Номер заявки' => $request['REQUEST_NUMBER'],
            'ID в АБС' => $request['ABS_ID'],
            'ФИО клиента' => $request['CLIENT_NAME'],
            'Телефон клиента' => $request['CLIENT_PHONE'],
            'Адрес доставки' => $request['CLIENT_ADDRESS'],
            'PAN' => $request['PAN'],
            'Тип карты' => $request['CARD_TYPE'],
            'Статус' => RequestTable::getStatuses()[$request['STATUS']] ?? $request['STATUS'],
            'Статус звонка' => RequestTable::getCallStatuses()[$request['CALL_STATUS']] ?? $request['CALL_STATUS'],
            'Дата регистрации' => $request['REGISTRATION_DATE']->format('d.m.Y H:i'),
            'Дата обработки' => $request['PROCESSING_DATE'] ? $request['PROCESSING_DATE']->format('d.m.Y H:i') : '',
            'Дата доставки' => $request['DELIVERY_DATE'] ? $request['DELIVERY_DATE']->format('d.m.Y H:i') : '',
            'Причина отказа' => $request['REJECTION_REASON']
        ];
    }
}