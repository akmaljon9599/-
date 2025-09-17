<?php
namespace CourierService\Api;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Config\Option;

class AbsGateway
{
    private $httpClient;
    private $apiUrl;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->apiUrl = Option::get('courier_service', 'abs_api_url', '');
        $this->apiKey = Option::get('courier_service', 'abs_api_key', '');
        $this->timeout = 30;
        
        $this->httpClient->setTimeout($this->timeout);
        $this->httpClient->setHeader('Content-Type', 'application/json');
        $this->httpClient->setHeader('Authorization', 'Bearer ' . $this->apiKey);
    }

    /**
     * Получение данных клиента из АБС
     */
    public function getClientData($clientId)
    {
        try {
            $response = $this->httpClient->get($this->apiUrl . '/clients/' . $clientId);
            
            if ($this->httpClient->getStatus() === 200) {
                return json_decode($response, true);
            } else {
                throw new \Exception('Ошибка получения данных клиента: ' . $this->httpClient->getStatus());
            }
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'getClientData', $e->getMessage());
            return false;
        }
    }

    /**
     * Создание заявки в АБС
     */
    public function createRequest($requestData)
    {
        try {
            $response = $this->httpClient->post(
                $this->apiUrl . '/requests',
                json_encode($requestData)
            );
            
            if ($this->httpClient->getStatus() === 201) {
                return json_decode($response, true);
            } else {
                throw new \Exception('Ошибка создания заявки: ' . $this->httpClient->getStatus());
            }
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'createRequest', $e->getMessage());
            return false;
        }
    }

    /**
     * Обновление статуса заявки в АБС
     */
    public function updateRequestStatus($requestId, $status, $additionalData = [])
    {
        try {
            $data = array_merge([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], $additionalData);

            $response = $this->httpClient->put(
                $this->apiUrl . '/requests/' . $requestId,
                json_encode($data)
            );
            
            if ($this->httpClient->getStatus() === 200) {
                return json_decode($response, true);
            } else {
                throw new \Exception('Ошибка обновления статуса: ' . $this->httpClient->getStatus());
            }
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'updateRequestStatus', $e->getMessage());
            return false;
        }
    }

    /**
     * Получение списка заявок из АБС
     */
    public function getRequests($filters = [])
    {
        try {
            $queryString = http_build_query($filters);
            $response = $this->httpClient->get($this->apiUrl . '/requests?' . $queryString);
            
            if ($this->httpClient->getStatus() === 200) {
                return json_decode($response, true);
            } else {
                throw new \Exception('Ошибка получения заявок: ' . $this->httpClient->getStatus());
            }
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'getRequests', $e->getMessage());
            return false;
        }
    }

    /**
     * Проверка связи с АБС
     */
    public function checkConnection()
    {
        try {
            $response = $this->httpClient->get($this->apiUrl . '/health');
            return $this->httpClient->getStatus() === 200;
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'checkConnection', $e->getMessage());
            return false;
        }
    }

    /**
     * Синхронизация данных с АБС
     */
    public function syncData()
    {
        try {
            $response = $this->httpClient->post($this->apiUrl . '/sync');
            
            if ($this->httpClient->getStatus() === 200) {
                $data = json_decode($response, true);
                
                // Обновляем локальные данные
                $this->updateLocalData($data);
                
                return true;
            } else {
                throw new \Exception('Ошибка синхронизации: ' . $this->httpClient->getStatus());
            }
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('ABS Gateway', 'syncData', $e->getMessage());
            return false;
        }
    }

    private function updateLocalData($data)
    {
        // Логика обновления локальных данных на основе полученных из АБС
        if (isset($data['requests'])) {
            foreach ($data['requests'] as $request) {
                // Обновляем или создаем заявку
                $this->updateOrCreateRequest($request);
            }
        }
    }

    private function updateOrCreateRequest($requestData)
    {
        // Поиск существующей заявки по ABS_ID
        $existingRequest = \CourierService\Main\RequestTable::getList([
            'filter' => ['ABS_ID' => $requestData['id']],
            'select' => ['ID']
        ])->fetch();

        if ($existingRequest) {
            // Обновляем существующую заявку
            \CourierService\Main\RequestTable::update($existingRequest['ID'], [
                'STATUS' => $requestData['status'],
                'DATE_MODIFY' => new \Bitrix\Main\Type\DateTime()
            ]);
        } else {
            // Создаем новую заявку
            \CourierService\Main\RequestTable::add([
                'REQUEST_NUMBER' => \CourierService\Main\RequestTable::generateRequestNumber(),
                'ABS_ID' => $requestData['id'],
                'CLIENT_NAME' => $requestData['client_name'],
                'CLIENT_PHONE' => $requestData['client_phone'],
                'CLIENT_ADDRESS' => $requestData['client_address'],
                'PAN' => $requestData['pan'],
                'STATUS' => $requestData['status'],
                'BRANCH_ID' => 1, // По умолчанию
                'CREATED_DATE' => new \Bitrix\Main\Type\DateTime(),
                'DATE_CREATE' => new \Bitrix\Main\Type\DateTime(),
                'CREATED_BY' => 1 // Системный пользователь
            ]);
        }
    }
}