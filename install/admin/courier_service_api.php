<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Bitrix\Main\HttpResponse;
use CourierService\Service\AuthService;
use CourierService\Api\RequestController;
use CourierService\Api\LocationController;
use CourierService\Service\DocumentService;
use CourierService\Service\AbsIntegrationService;

Loader::includeModule('courier_service');

$authService = new AuthService();
if (!$authService->isAuthenticated()) {
    http_response_code(401);
    echo Json::encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request = Context::getCurrent()->getRequest();
$action = $request->get('action');

try {
    switch ($action) {
        case 'get_requests':
            $controller = new RequestController();
            $controller->indexAction();
            break;

        case 'get_request':
            $id = $request->get('id');
            if (!$id) {
                throw new Exception('Request ID is required');
            }
            $controller = new RequestController();
            $controller->showAction($id);
            break;

        case 'create_request':
            $controller = new RequestController();
            $controller->createAction();
            break;

        case 'update_request':
            $id = $request->get('id');
            if (!$id) {
                throw new Exception('Request ID is required');
            }
            $controller = new RequestController();
            $controller->updateAction($id);
            break;

        case 'delete_request':
            $id = $request->get('id');
            if (!$id) {
                throw new Exception('Request ID is required');
            }
            $controller = new RequestController();
            $controller->deleteAction($id);
            break;

        case 'update_status':
            $data = Json::decode($request->getInput());
            $id = $data['request_id'] ?? null;
            if (!$id) {
                throw new Exception('Request ID is required');
            }
            $controller = new RequestController();
            $controller->updateStatusAction($id);
            break;

        case 'upload_document':
            $id = $request->get('id');
            if (!$id) {
                throw new Exception('Request ID is required');
            }
            $controller = new RequestController();
            $controller->uploadDocumentAction($id);
            break;

        case 'export_requests':
            $controller = new RequestController();
            $controller->exportAction();
            break;

        case 'get_branches':
            $branches = \CourierService\Entity\BranchTable::getList([
                'filter' => ['IS_ACTIVE' => true],
                'order' => ['NAME' => 'ASC']
            ])->fetchAll();
            
            $response = [
                'success' => true,
                'data' => array_map(function($branch) {
                    return [
                        'id' => $branch['ID'],
                        'name' => $branch['NAME'],
                        'address' => $branch['ADDRESS']
                    ];
                }, $branches)
            ];
            
            echo Json::encode($response);
            break;

        case 'get_couriers':
            $couriers = \CourierService\Entity\UserTable::getList([
                'filter' => [
                    'ROLE' => 'courier',
                    'IS_ACTIVE' => true
                ],
                'order' => ['USER_ID' => 'ASC']
            ])->fetchAll();
            
            $response = [
                'success' => true,
                'data' => array_map(function($courier) {
                    return [
                        'id' => $courier['ID'],
                        'name' => 'Курьер #' . $courier['USER_ID'],
                        'user_id' => $courier['USER_ID']
                    ];
                }, $couriers)
            ];
            
            echo Json::encode($response);
            break;

        case 'get_departments':
            $branchId = $request->get('branch_id');
            $filter = ['IS_ACTIVE' => true];
            if ($branchId) {
                $filter['BRANCH_ID'] = $branchId;
            }
            
            $departments = \CourierService\Entity\DepartmentTable::getList([
                'filter' => $filter,
                'order' => ['NAME' => 'ASC']
            ])->fetchAll();
            
            $response = [
                'success' => true,
                'data' => array_map(function($department) {
                    return [
                        'id' => $department['ID'],
                        'name' => $department['NAME'],
                        'branch_id' => $department['BRANCH_ID']
                    ];
                }, $departments)
            ];
            
            echo Json::encode($response);
            break;

        case 'generate_contract':
            $data = Json::decode($request->getInput());
            $requestId = $data['request_id'] ?? null;
            
            if (!$requestId) {
                throw new Exception('Request ID is required');
            }
            
            $documentService = new DocumentService();
            $result = $documentService->generateContract($requestId);
            
            echo Json::encode($result);
            break;

        case 'upload_signature':
            $data = Json::decode($request->getInput());
            $documentId = $data['document_id'] ?? null;
            $signatureData = $data['signature'] ?? null;
            
            if (!$documentId || !$signatureData) {
                throw new Exception('Document ID and signature data are required');
            }
            
            $documentService = new DocumentService();
            $result = $documentService->addSignatureToContract($documentId, $signatureData);
            
            echo Json::encode($result);
            break;

        case 'get_documents':
            $requestId = $request->get('request_id');
            $type = $request->get('type');
            
            if (!$requestId) {
                throw new Exception('Request ID is required');
            }
            
            $documentService = new DocumentService();
            $documents = $documentService->getDocuments($requestId, $type);
            
            echo Json::encode([
                'success' => true,
                'data' => $documents
            ]);
            break;

        case 'delete_document':
            $documentId = $request->get('document_id');
            
            if (!$documentId) {
                throw new Exception('Document ID is required');
            }
            
            $documentService = new DocumentService();
            $result = $documentService->deleteDocument($documentId);
            
            echo Json::encode($result);
            break;

        case 'update_location':
            $controller = new LocationController();
            $controller->updateAction();
            break;

        case 'get_couriers_locations':
            $controller = new LocationController();
            $controller->getAllCouriersAction();
            break;

        case 'get_route':
            $controller = new LocationController();
            $controller->getRouteAction();
            break;

        case 'geocode':
            $controller = new LocationController();
            $controller->geocodeAction();
            break;

        case 'sync_with_abs':
            $data = Json::decode($request->getInput());
            $requestId = $data['request_id'] ?? null;
            
            if (!$requestId) {
                throw new Exception('Request ID is required');
            }
            
            $absService = new AbsIntegrationService();
            $result = $absService->createRequest(['id' => $requestId]);
            
            echo Json::encode($result);
            break;

        case 'validate_client':
            $data = Json::decode($request->getInput());
            
            if (empty($data['client_name']) || empty($data['client_phone'])) {
                throw new Exception('Client name and phone are required');
            }
            
            $absService = new AbsIntegrationService();
            $result = $absService->validateClientData($data);
            
            echo Json::encode($result);
            break;

        case 'get_statistics':
            $dateFrom = $request->get('date_from') ?: date('Y-m-01');
            $dateTo = $request->get('date_to') ?: date('Y-m-d');
            
            // Получаем статистику по заявкам
            $totalRequests = \CourierService\Entity\RequestTable::getList([
                'filter' => [
                    '>=REGISTRATION_DATE' => $dateFrom,
                    '<=REGISTRATION_DATE' => $dateTo
                ],
                'select' => ['CNT'],
                'runtime' => [
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ]
            ])->fetch()['CNT'];
            
            $deliveredRequests = \CourierService\Entity\RequestTable::getList([
                'filter' => [
                    'STATUS' => 'delivered',
                    '>=REGISTRATION_DATE' => $dateFrom,
                    '<=REGISTRATION_DATE' => $dateTo
                ],
                'select' => ['CNT'],
                'runtime' => [
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ]
            ])->fetch()['CNT'];
            
            $inProgressRequests = \CourierService\Entity\RequestTable::getList([
                'filter' => [
                    'STATUS' => ['waiting_delivery', 'in_delivery'],
                    '>=REGISTRATION_DATE' => $dateFrom,
                    '<=REGISTRATION_DATE' => $dateTo
                ],
                'select' => ['CNT'],
                'runtime' => [
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ]
            ])->fetch()['CNT'];
            
            $rejectedRequests = \CourierService\Entity\RequestTable::getList([
                'filter' => [
                    'STATUS' => 'rejected',
                    '>=REGISTRATION_DATE' => $dateFrom,
                    '<=REGISTRATION_DATE' => $dateTo
                ],
                'select' => ['CNT'],
                'runtime' => [
                    new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
                ]
            ])->fetch()['CNT'];
            
            echo Json::encode([
                'success' => true,
                'data' => [
                    'total' => $totalRequests,
                    'delivered' => $deliveredRequests,
                    'in_progress' => $inProgressRequests,
                    'rejected' => $rejectedRequests
                ]
            ]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo Json::encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}