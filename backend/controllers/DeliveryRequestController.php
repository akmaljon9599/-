<?php
/**
 * Контроллер заявок на доставку
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/DeliveryRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Courier.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DeliveryRequestController {
    private $requestModel;
    private $userModel;
    private $courierModel;

    public function __construct() {
        $this->requestModel = new DeliveryRequest();
        $this->userModel = new User();
        $this->courierModel = new Courier();
    }

    /**
     * Получить список заявок
     */
    public function index() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = $_GET;
            
            // Применяем фильтры в зависимости от роли
            $this->applyRoleFilters($filters, $user);

            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $requests = $this->requestModel->getList($filters, $limit, $offset);
            $total = $this->requestModel->getCount($filters);

            AuthMiddleware::logActivity('view_requests_list');

            $this->response([
                'success' => true,
                'data' => $requests,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            error_log('Get requests error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения заявок'], 500);
        }
    }

    /**
     * Получить заявку по ID
     */
    public function show($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $request = $this->requestModel->findById($id);
            
            if (!$request) {
                $this->response(['error' => 'Заявка не найдена'], 404);
                return;
            }

            if (!AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            // Получаем историю изменений статуса
            $history = $this->requestModel->getStatusHistory($id);
            $request['status_history'] = $history;

            AuthMiddleware::logActivity('view_request', 'delivery_request', $id);

            $this->response([
                'success' => true,
                'data' => $request
            ]);

        } catch (Exception $e) {
            error_log('Get request error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения заявки'], 500);
        }
    }

    /**
     * Создать новую заявку
     */
    public function create() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::authorize('create_request')) {
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $user = AuthMiddleware::getCurrentUser();

            // Валидация обязательных полей
            $required = ['client_full_name', 'client_phone', 'delivery_address', 'branch_id', 'department_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->response(['error' => "Поле {$field} обязательно для заполнения"], 400);
                    return;
                }
            }

            // Проверяем доступ к филиалу
            if (!AuthMiddleware::checkBranchAccess($input['branch_id'])) {
                $this->response(['error' => 'Нет доступа к указанному филиалу'], 403);
                return;
            }

            // Подготавливаем данные для создания
            $requestData = [
                'client_full_name' => $input['client_full_name'],
                'client_phone' => $input['client_phone'],
                'client_pan' => $input['client_pan'] ?? null,
                'delivery_address' => $input['delivery_address'],
                'branch_id' => $input['branch_id'],
                'department_id' => $input['department_id'],
                'operator_id' => $user['id'],
                'card_type_id' => $input['card_type_id'] ?? null,
                'delivery_date' => $input['delivery_date'] ?? null,
                'delivery_time_from' => $input['delivery_time_from'] ?? null,
                'delivery_time_to' => $input['delivery_time_to'] ?? null,
                'priority' => $input['priority'] ?? 'normal',
                'notes' => $input['notes'] ?? null
            ];

            $requestId = $this->requestModel->create($requestData);

            AuthMiddleware::logActivity('create_request', 'delivery_request', $requestId, $requestData);

            $this->response([
                'success' => true,
                'message' => 'Заявка создана успешно',
                'id' => $requestId
            ]);

        } catch (Exception $e) {
            error_log('Create request error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка создания заявки'], 500);
        }
    }

    /**
     * Обновить заявку
     */
    public function update($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $request = $this->requestModel->findById($id);
            
            if (!$request) {
                $this->response(['error' => 'Заявка не найдена'], 404);
                return;
            }

            if (!AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $user = AuthMiddleware::getCurrentUser();

            // Разрешенные поля для обновления в зависимости от роли
            $allowedFields = $this->getAllowedUpdateFields($user['role_name']);
            
            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }

            if (empty($updateData)) {
                $this->response(['error' => 'Нет данных для обновления'], 400);
                return;
            }

            $this->requestModel->update($id, $updateData);

            AuthMiddleware::logActivity('update_request', 'delivery_request', $id, $updateData);

            $this->response([
                'success' => true,
                'message' => 'Заявка обновлена успешно'
            ]);

        } catch (Exception $e) {
            error_log('Update request error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка обновления заявки'], 500);
        }
    }

    /**
     * Изменить статус заявки
     */
    public function updateStatus($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $request = $this->requestModel->findById($id);
            
            if (!$request) {
                $this->response(['error' => 'Заявка не найдена'], 404);
                return;
            }

            if (!AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $user = AuthMiddleware::getCurrentUser();

            if (empty($input['status'])) {
                $this->response(['error' => 'Не указан новый статус'], 400);
                return;
            }

            $newStatus = $input['status'];
            $comment = $input['comment'] ?? null;
            $additionalData = [];

            // Проверяем права на изменение статуса
            if (!$this->canChangeStatus($user, $request, $newStatus)) {
                $this->response(['error' => 'Нет прав на изменение статуса'], 403);
                return;
            }

            // Валидация для статуса "доставлено"
            if ($newStatus === 'delivered') {
                if (empty($input['delivery_photos']) || count($input['delivery_photos']) < 2) {
                    $this->response(['error' => 'Необходимо загрузить минимум 2 фотографии'], 400);
                    return;
                }

                if (empty($input['courier_phone'])) {
                    $this->response(['error' => 'Необходимо указать телефон курьера'], 400);
                    return;
                }

                $additionalData['delivery_photos'] = json_encode($input['delivery_photos']);
                $additionalData['courier_phone'] = $input['courier_phone'];
            }

            // Валидация для статуса "отказано"
            if ($newStatus === 'rejected') {
                if (empty($comment) || strlen($comment) < 100) {
                    $this->response(['error' => 'Комментарий должен содержать минимум 100 символов'], 400);
                    return;
                }

                $additionalData['rejection_reason'] = $comment;
            }

            $this->requestModel->updateStatus($id, $newStatus, $user['id'], $comment, $additionalData);

            AuthMiddleware::logActivity('update_request_status', 'delivery_request', $id, [
                'old_status' => $request['status'],
                'new_status' => $newStatus,
                'comment' => $comment
            ]);

            $this->response([
                'success' => true,
                'message' => 'Статус заявки изменен успешно'
            ]);

        } catch (Exception $e) {
            error_log('Update request status error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка изменения статуса'], 500);
        }
    }

    /**
     * Назначить курьера на заявку
     */
    public function assignCourier($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $request = $this->requestModel->findById($id);
            
            if (!$request) {
                $this->response(['error' => 'Заявка не найдена'], 404);
                return;
            }

            if (!AuthMiddleware::canAccessRequest($request)) {
                $this->response(['error' => 'Нет доступа к заявке'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $user = AuthMiddleware::getCurrentUser();

            if (empty($input['courier_id'])) {
                $this->response(['error' => 'Не указан курьер'], 400);
                return;
            }

            // Проверяем, что курьер существует и доступен
            $courier = $this->courierModel->findByUserId($input['courier_id']);
            if (!$courier) {
                $this->response(['error' => 'Курьер не найден'], 404);
                return;
            }

            // Проверяем доступ к курьеру (тот же филиал)
            if (!AuthMiddleware::checkBranchAccess($courier['branch_id'])) {
                $this->response(['error' => 'Нет доступа к указанному курьеру'], 403);
                return;
            }

            $this->requestModel->assignCourier($id, $input['courier_id'], $user['id']);

            AuthMiddleware::logActivity('assign_courier', 'delivery_request', $id, [
                'courier_id' => $input['courier_id'],
                'assigned_by' => $user['id']
            ]);

            $this->response([
                'success' => true,
                'message' => 'Курьер назначен успешно'
            ]);

        } catch (Exception $e) {
            error_log('Assign courier error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка назначения курьера'], 500);
        }
    }

    /**
     * Получить заявки для курьера
     */
    public function getCourierRequests() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole('courier')) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $status = $_GET['status'] ?? null;

            $requests = $this->requestModel->getCourierRequests($user['id'], $status);

            $this->response([
                'success' => true,
                'data' => $requests
            ]);

        } catch (Exception $e) {
            error_log('Get courier requests error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения заявок'], 500);
        }
    }

    /**
     * Экспорт заявок в Excel
     */
    public function export() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::authorize('export_data')) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = $_GET;
            
            // Применяем фильтры в зависимости от роли
            $this->applyRoleFilters($filters, $user);

            $data = $this->requestModel->exportToArray($filters);

            // Создаем CSV файл
            $filename = 'requests_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = __DIR__ . '/../../uploads/' . $filename;

            $file = fopen($filepath, 'w');
            
            // Добавляем BOM для корректного отображения UTF-8 в Excel
            fwrite($file, "\xEF\xBB\xBF");

            if (!empty($data)) {
                // Заголовки
                fputcsv($file, array_keys($data[0]), ';');
                
                // Данные
                foreach ($data as $row) {
                    fputcsv($file, $row, ';');
                }
            }

            fclose($file);

            AuthMiddleware::logActivity('export_requests', null, null, ['filters' => $filters]);

            $this->response([
                'success' => true,
                'download_url' => '/uploads/' . $filename
            ]);

        } catch (Exception $e) {
            error_log('Export requests error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка экспорта данных'], 500);
        }
    }

    /**
     * Получить статистику заявок
     */
    public function getStatistics() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = $_GET;
            
            // Применяем фильтры в зависимости от роли
            $this->applyRoleFilters($filters, $user);

            $statistics = $this->requestModel->getStatistics($filters);

            // Преобразуем в удобный формат
            $stats = [
                'new' => 0,
                'assigned' => 0,
                'in_progress' => 0,
                'delivered' => 0,
                'rejected' => 0,
                'cancelled' => 0,
                'total' => 0
            ];

            foreach ($statistics as $stat) {
                $stats[$stat['status']] = intval($stat['count']);
                $stats['total'] += intval($stat['count']);
            }

            $this->response([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            error_log('Get statistics error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения статистики'], 500);
        }
    }

    /**
     * Применить фильтры в зависимости от роли пользователя
     */
    private function applyRoleFilters(&$filters, $user) {
        switch ($user['role_name']) {
            case 'courier':
                $filters['courier_id'] = $user['id'];
                break;

            case 'operator':
                if ($user['branch_id']) {
                    $filters['branch_id'] = $user['branch_id'];
                }
                break;

            case 'senior_courier':
                if ($user['branch_id']) {
                    $filters['branch_id'] = $user['branch_id'];
                }
                break;

            case 'admin':
                // Администратор видит все заявки
                break;
        }
    }

    /**
     * Получить разрешенные поля для обновления в зависимости от роли
     */
    private function getAllowedUpdateFields($role) {
        $fields = [
            'admin' => [
                'client_full_name', 'client_phone', 'client_pan', 'delivery_address',
                'card_type_id', 'branch_id', 'department_id', 'courier_id',
                'delivery_date', 'delivery_time_from', 'delivery_time_to',
                'priority', 'notes', 'call_status'
            ],
            'senior_courier' => [
                'courier_id', 'delivery_date', 'delivery_time_from', 'delivery_time_to',
                'priority', 'notes', 'call_status'
            ],
            'operator' => [
                'client_full_name', 'client_phone', 'client_pan', 'delivery_address',
                'card_type_id', 'delivery_date', 'delivery_time_from', 'delivery_time_to',
                'notes', 'call_status'
            ],
            'courier' => []
        ];

        return $fields[$role] ?? [];
    }

    /**
     * Проверить, может ли пользователь изменить статус
     */
    private function canChangeStatus($user, $request, $newStatus) {
        $role = $user['role_name'];

        switch ($role) {
            case 'admin':
                return true;

            case 'senior_courier':
                // Может изменять статусы заявок своего филиала, кроме завершающих
                return AuthMiddleware::checkBranchAccess($request['branch_id']) && 
                       !in_array($newStatus, ['delivered', 'rejected']);

            case 'courier':
                // Может изменять только свои заявки со статуса "assigned" на "delivered" или "rejected"
                return $request['courier_id'] == $user['id'] && 
                       $request['status'] === 'assigned' &&
                       in_array($newStatus, ['delivered', 'rejected']);

            case 'operator':
                // Не может изменять статусы доставки
                return false;

            default:
                return false;
        }
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