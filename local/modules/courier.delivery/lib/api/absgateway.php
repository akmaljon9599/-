<?php
namespace Courier\Delivery\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Courier\Delivery\Util\Logger;

/**
 * Класс для интеграции с АБС банка через API Gateway
 */
class AbsGateway
{
    private $apiUrl;
    private $apiKey;
    private $httpClient;

    public function __construct()
    {
        $this->apiUrl = Option::get('courier.delivery', 'abs_api_url', '');
        $this->apiKey = Option::get('courier.delivery', 'abs_api_key', '');
        
        $this->httpClient = new HttpClient([
            'timeout' => 30,
            'socketTimeout' => 30,
            'streamTimeout' => 30,
            'version' => HttpClient::HTTP_1_1
        ]);

        // Устанавливаем заголовки аутентификации
        $this->httpClient->setHeader('Authorization', 'Bearer ' . $this->apiKey);
        $this->httpClient->setHeader('Content-Type', 'application/json');
        $this->httpClient->setHeader('Accept', 'application/json');
    }

    /**
     * Получить информацию о клиенте по PAN карты
     */
    public function getClientByPan($pan)
    {
        try {
            $endpoint = '/api/v1/client/by-pan';
            $data = ['pan' => $pan];

            $response = $this->makeRequest('POST', $endpoint, $data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => [
                        'client_id' => $response['data']['client_id'],
                        'full_name' => $response['data']['full_name'],
                        'phone' => $response['data']['phone'],
                        'address' => $response['data']['address'],
                        'card_type' => $response['data']['card_type'],
                        'branch_code' => $response['data']['branch_code'],
                        'department_code' => $response['data']['department_code']
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Клиент не найден'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (getClientByPan): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при получении данных клиента'
            ];
        }
    }

    /**
     * Создать заявку на доставку в АБС
     */
    public function createDeliveryRequest($requestData)
    {
        try {
            $endpoint = '/api/v1/delivery/request';
            
            $data = [
                'client_pan' => $requestData['client_pan'],
                'delivery_address' => $requestData['delivery_address'],
                'client_phone' => $requestData['client_phone'],
                'branch_code' => $requestData['branch_code'],
                'department_code' => $requestData['department_code'],
                'operator_id' => $requestData['operator_id'],
                'notes' => $requestData['notes'] ?? ''
            ];

            $response = $this->makeRequest('POST', $endpoint, $data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => [
                        'abs_id' => $response['data']['request_id'],
                        'status' => $response['data']['status'],
                        'estimated_delivery_date' => $response['data']['estimated_delivery_date']
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка создания заявки в АБС'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (createDeliveryRequest): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при создании заявки в АБС'
            ];
        }
    }

    /**
     * Обновить статус заявки в АБС
     */
    public function updateRequestStatus($absId, $status, $comment = '', $documents = [])
    {
        try {
            $endpoint = '/api/v1/delivery/request/' . $absId . '/status';
            
            $data = [
                'status' => $status,
                'comment' => $comment,
                'updated_at' => date('Y-m-d H:i:s'),
                'documents' => $documents
            ];

            $response = $this->makeRequest('PUT', $endpoint, $data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Статус обновлен в АБС'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка обновления статуса в АБС'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (updateRequestStatus): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при обновлении статуса в АБС'
            ];
        }
    }

    /**
     * Загрузить документ в АБС
     */
    public function uploadDocument($absId, $documentType, $fileData, $signatureData = null)
    {
        try {
            $endpoint = '/api/v1/delivery/request/' . $absId . '/document';
            
            // Кодируем файл в base64
            $fileContent = base64_encode(file_get_contents($fileData['tmp_name']));
            
            $data = [
                'document_type' => $documentType,
                'file_name' => $fileData['name'],
                'file_content' => $fileContent,
                'mime_type' => $fileData['type'],
                'file_size' => $fileData['size']
            ];

            if ($signatureData) {
                $data['signature_data'] = $signatureData;
                $data['is_signed'] = true;
            }

            $response = $this->makeRequest('POST', $endpoint, $data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => [
                        'document_id' => $response['data']['document_id'],
                        'url' => $response['data']['url'] ?? null
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка загрузки документа в АБС'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (uploadDocument): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при загрузке документа в АБС'
            ];
        }
    }

    /**
     * Получить список филиалов из АБС
     */
    public function getBranches()
    {
        try {
            $endpoint = '/api/v1/branches';
            $response = $this->makeRequest('GET', $endpoint);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']['branches']
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка получения списка филиалов'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (getBranches): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при получении списка филиалов'
            ];
        }
    }

    /**
     * Получить список подразделений филиала
     */
    public function getDepartments($branchCode)
    {
        try {
            $endpoint = '/api/v1/branches/' . $branchCode . '/departments';
            $response = $this->makeRequest('GET', $endpoint);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']['departments']
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка получения списка подразделений'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (getDepartments): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при получении списка подразделений'
            ];
        }
    }

    /**
     * Синхронизировать заявки с АБС
     */
    public function syncRequests($dateFrom, $dateTo = null)
    {
        try {
            $endpoint = '/api/v1/delivery/requests/sync';
            
            $data = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo ?? date('Y-m-d H:i:s')
            ];

            $response = $this->makeRequest('POST', $endpoint, $data);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data']['requests']
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Ошибка синхронизации заявок'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Error (syncRequests): ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка при синхронизации заявок'
            ];
        }
    }

    /**
     * Выполнить запрос к API
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;
        
        Logger::log("ABS API Request: {$method} {$url}", 'ABS_API_REQUEST');

        switch (strtoupper($method)) {
            case 'GET':
                $response = $this->httpClient->get($url);
                break;
                
            case 'POST':
                $response = $this->httpClient->post($url, $data ? Json::encode($data) : null);
                break;
                
            case 'PUT':
                $response = $this->httpClient->query('PUT', $url, $data ? Json::encode($data) : null);
                break;
                
            case 'DELETE':
                $response = $this->httpClient->query('DELETE', $url);
                break;
                
            default:
                throw new \Exception("Unsupported HTTP method: {$method}");
        }

        $httpCode = $this->httpClient->getStatus();
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = Json::decode($response);
            
            Logger::log("ABS API Response: HTTP {$httpCode}", 'ABS_API_RESPONSE');
            
            return $responseData;
        } else {
            $error = "HTTP Error {$httpCode}";
            
            try {
                $errorData = Json::decode($response);
                if (isset($errorData['error'])) {
                    $error = $errorData['error'];
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки парсинга JSON для ошибок
            }
            
            Logger::log("ABS API Error: HTTP {$httpCode} - {$error}", 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Проверить соединение с АБС
     */
    public function testConnection()
    {
        try {
            $endpoint = '/api/v1/health';
            $response = $this->makeRequest('GET', $endpoint);

            return [
                'success' => isset($response['status']) && $response['status'] === 'ok',
                'message' => $response['message'] ?? 'Соединение установлено'
            ];

        } catch (\Exception $e) {
            Logger::log('ABS API Connection Test Failed: ' . $e->getMessage(), 'ABS_API_ERROR');
            
            return [
                'success' => false,
                'message' => 'Не удается подключиться к АБС: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Получить конфигурацию API
     */
    public function getApiConfig()
    {
        return [
            'url' => $this->apiUrl,
            'has_key' => !empty($this->apiKey),
            'key_length' => strlen($this->apiKey)
        ];
    }
}