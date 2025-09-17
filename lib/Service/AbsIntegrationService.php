<?php

namespace CourierService\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\Json;
use CourierService\Entity\RequestTable;

class AbsIntegrationService
{
    private $apiUrl;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->apiUrl = Option::get('courier_service', 'abs_api_url', '');
        $this->apiKey = Option::get('courier_service', 'abs_api_key', '');
        $this->timeout = 30;
    }

    public function createRequest($requestData)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $absData = $this->formatRequestForAbs($requestData);
            
            $response = $this->makeRequest('POST', '/api/requests', $absData);
            
            if ($response['success']) {
                $absId = $response['data']['id'] ?? null;
                
                // Обновляем заявку с ID из АБС
                if ($absId) {
                    RequestTable::update($requestData['id'], [
                        'ABS_ID' => $absId,
                        'UPDATED_AT' => new \Bitrix\Main\Type\DateTime()
                    ]);
                }
                
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'],
                    'errors' => $response['errors'] ?? []
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    public function updateRequest($requestId, $requestData)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $request = RequestTable::getById($requestId)->fetch();
            if (!$request || !$request['ABS_ID']) {
                return [
                    'success' => false,
                    'message' => 'Request not found or not synced with ABS'
                ];
            }

            $absData = $this->formatRequestForAbs($requestData);
            $response = $this->makeRequest('PUT', '/api/requests/' . $request['ABS_ID'], $absData);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'],
                    'errors' => $response['errors'] ?? []
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    public function updateRequestStatus($requestId, $status, $additionalData = [])
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $request = RequestTable::getById($requestId)->fetch();
            if (!$request || !$request['ABS_ID']) {
                return [
                    'success' => false,
                    'message' => 'Request not found or not synced with ABS'
                ];
            }

            $absStatus = $this->mapStatusToAbs($status);
            $data = [
                'status' => $absStatus,
                'updated_at' => (new \Bitrix\Main\Type\DateTime())->format('Y-m-d H:i:s')
            ];

            // Добавляем дополнительные данные в зависимости от статуса
            switch ($status) {
                case 'delivered':
                    $data['delivery_date'] = $additionalData['delivery_date'] ?? null;
                    $data['courier_phone'] = $additionalData['courier_phone'] ?? null;
                    break;
                case 'rejected':
                    $data['rejection_reason'] = $additionalData['rejection_reason'] ?? null;
                    break;
            }

            $response = $this->makeRequest('PATCH', '/api/requests/' . $request['ABS_ID'] . '/status', $data);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'],
                    'errors' => $response['errors'] ?? []
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    public function getRequestFromAbs($absId)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $response = $this->makeRequest('GET', '/api/requests/' . $absId);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $this->formatRequestFromAbs($response['data'])
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    public function syncRequestsFromAbs($dateFrom = null, $dateTo = null)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $params = [];
            if ($dateFrom) {
                $params['date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $params['date_to'] = $dateTo;
            }

            $response = $this->makeRequest('GET', '/api/requests', $params);
            
            if ($response['success']) {
                $syncedCount = 0;
                $errors = [];

                foreach ($response['data'] as $absRequest) {
                    $result = $this->syncSingleRequest($absRequest);
                    if ($result['success']) {
                        $syncedCount++;
                    } else {
                        $errors[] = $result['message'];
                    }
                }

                return [
                    'success' => true,
                    'data' => [
                        'synced_count' => $syncedCount,
                        'errors' => $errors
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    public function validateClientData($clientData)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'ABS integration not configured'
            ];
        }

        try {
            $response = $this->makeRequest('POST', '/api/clients/validate', $clientData);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'],
                    'errors' => $response['errors'] ?? []
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'ABS integration error: ' . $e->getMessage()
            ];
        }
    }

    private function isConfigured()
    {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }

    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'X-Requested-With: CourierService'
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL error: ' . $error
            ];
        }

        $responseData = Json::decode($response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData
            ];
        } else {
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Request failed',
                'errors' => $responseData['errors'] ?? []
            ];
        }
    }

    private function formatRequestForAbs($requestData)
    {
        return [
            'request_number' => $requestData['REQUEST_NUMBER'],
            'client_name' => $requestData['CLIENT_NAME'],
            'client_phone' => $requestData['CLIENT_PHONE'],
            'client_address' => $requestData['CLIENT_ADDRESS'],
            'pan' => $requestData['PAN'],
            'card_type' => $requestData['CARD_TYPE'],
            'status' => $this->mapStatusToAbs($requestData['STATUS']),
            'registration_date' => $requestData['REGISTRATION_DATE']->format('Y-m-d H:i:s'),
            'branch_id' => $requestData['BRANCH_ID'],
            'department_id' => $requestData['DEPARTMENT_ID']
        ];
    }

    private function formatRequestFromAbs($absData)
    {
        return [
            'REQUEST_NUMBER' => $absData['request_number'],
            'ABS_ID' => $absData['id'],
            'CLIENT_NAME' => $absData['client_name'],
            'CLIENT_PHONE' => $absData['client_phone'],
            'CLIENT_ADDRESS' => $absData['client_address'],
            'PAN' => $absData['pan'],
            'CARD_TYPE' => $absData['card_type'],
            'STATUS' => $this->mapStatusFromAbs($absData['status']),
            'REGISTRATION_DATE' => new \Bitrix\Main\Type\DateTime($absData['registration_date']),
            'BRANCH_ID' => $absData['branch_id'],
            'DEPARTMENT_ID' => $absData['department_id']
        ];
    }

    private function mapStatusToAbs($status)
    {
        $mapping = [
            'new' => 'NEW',
            'waiting_delivery' => 'WAITING_DELIVERY',
            'in_delivery' => 'IN_DELIVERY',
            'delivered' => 'DELIVERED',
            'rejected' => 'REJECTED'
        ];

        return $mapping[$status] ?? $status;
    }

    private function mapStatusFromAbs($absStatus)
    {
        $mapping = [
            'NEW' => 'new',
            'WAITING_DELIVERY' => 'waiting_delivery',
            'IN_DELIVERY' => 'in_delivery',
            'DELIVERED' => 'delivered',
            'REJECTED' => 'rejected'
        ];

        return $mapping[$absStatus] ?? $absStatus;
    }

    private function syncSingleRequest($absRequest)
    {
        // Проверяем, существует ли уже заявка с таким ABS_ID
        $existingRequest = RequestTable::getList([
            'filter' => ['ABS_ID' => $absRequest['id']],
            'select' => ['ID']
        ])->fetch();

        $requestData = $this->formatRequestFromAbs($absRequest);
        $requestData['CREATED_AT'] = new \Bitrix\Main\Type\DateTime();
        $requestData['UPDATED_AT'] = new \Bitrix\Main\Type\DateTime();

        if ($existingRequest) {
            // Обновляем существующую заявку
            $result = RequestTable::update($existingRequest['ID'], $requestData);
        } else {
            // Создаем новую заявку
            $result = RequestTable::add($requestData);
        }

        if ($result->isSuccess()) {
            return [
                'success' => true,
                'id' => $result->getId()
            ];
        } else {
            return [
                'success' => false,
                'message' => implode(', ', $result->getErrorMessages())
            ];
        }
    }
}