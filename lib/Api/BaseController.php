<?php

namespace CourierService\Api;

use Bitrix\Main\Web\Json;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use CourierService\Service\AuthService;

abstract class BaseController extends Controller
{
    protected $authService;
    protected $currentUser;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
        $this->currentUser = $this->authService->getCurrentUser();
    }

    protected function getRequest(): HttpRequest
    {
        return Context::getCurrent()->getRequest();
    }

    protected function getResponse(): HttpResponse
    {
        return Context::getCurrent()->getResponse();
    }

    protected function sendJsonResponse($data, $statusCode = 200)
    {
        $response = $this->getResponse();
        $response->setStatus($statusCode);
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->flush(Json::encode($data));
    }

    protected function sendErrorResponse($message, $statusCode = 400, $errors = [])
    {
        $data = [
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ];

        $this->sendJsonResponse($data, $statusCode);
    }

    protected function sendSuccessResponse($data = [], $message = 'Success')
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];

        $this->sendJsonResponse($response);
    }

    protected function validatePermission($permission, $entity = null)
    {
        if (!$this->currentUser) {
            $this->sendErrorResponse('Unauthorized', 401);
            return false;
        }

        $userRole = $this->currentUser['ROLE'];
        $permissions = \CourierService\Entity\UserTable::getRolePermissions($userRole);

        if (!isset($permissions[$entity]) || !in_array($permission, $permissions[$entity])) {
            $this->sendErrorResponse('Access denied', 403);
            return false;
        }

        return true;
    }

    protected function logAction($action, $entityType, $entityId, $data = null)
    {
        global $USER;
        
        $logData = [
            'USER_ID' => $USER->GetID(),
            'ACTION' => $action,
            'ENTITY_TYPE' => $entityType,
            'ENTITY_ID' => $entityId,
            'DATA' => $data ? Json::encode($data) : null,
            'IP_ADDRESS' => $this->getRequest()->getRemoteAddress(),
            'USER_AGENT' => $this->getRequest()->getUserAgent(),
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
        ];

        \CourierService\Entity\LogTable::add($logData);
    }

    public function configureActions()
    {
        return [
            'index' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(['GET']),
                ],
            ],
            'create' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(['POST']),
                ],
            ],
            'update' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(['PUT', 'PATCH']),
                ],
            ],
            'delete' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(['DELETE']),
                ],
            ],
        ];
    }
}