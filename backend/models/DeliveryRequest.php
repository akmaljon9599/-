<?php
/**
 * Модель заявки на доставку
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../config/Database.php';

class DeliveryRequest {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать новую заявку
     */
    public function create($requestData) {
        // Генерируем номер заявки
        if (!isset($requestData['request_number'])) {
            $requestData['request_number'] = $this->generateRequestNumber();
        }

        return $this->db->insert('delivery_requests', $requestData);
    }

    /**
     * Получить заявку по ID
     */
    public function findById($id) {
        $sql = "
            SELECT dr.*, 
                   ct.name as card_type_name,
                   b.name as branch_name,
                   d.name as department_name,
                   CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                   CONCAT(c.first_name, ' ', c.last_name) as courier_name,
                   CONCAT(sc.first_name, ' ', sc.last_name) as senior_courier_name,
                   cour.is_online as courier_online,
                   cour.current_latitude as courier_latitude,
                   cour.current_longitude as courier_longitude
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            LEFT JOIN departments d ON dr.department_id = d.id
            LEFT JOIN users op ON dr.operator_id = op.id
            LEFT JOIN users c ON dr.courier_id = c.id
            LEFT JOIN users sc ON dr.senior_courier_id = sc.id
            LEFT JOIN couriers cour ON c.id = cour.user_id
            WHERE dr.id = :id
        ";
        
        return $this->db->selectOne($sql, ['id' => $id]);
    }

    /**
     * Получить заявку по номеру
     */
    public function findByNumber($requestNumber) {
        $sql = "
            SELECT dr.*, 
                   ct.name as card_type_name,
                   b.name as branch_name,
                   d.name as department_name,
                   CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                   CONCAT(c.first_name, ' ', c.last_name) as courier_name
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            LEFT JOIN departments d ON dr.department_id = d.id
            LEFT JOIN users op ON dr.operator_id = op.id
            LEFT JOIN users c ON dr.courier_id = c.id
            WHERE dr.request_number = :request_number
        ";
        
        return $this->db->selectOne($sql, ['request_number' => $requestNumber]);
    }

    /**
     * Получить список заявок с фильтрацией
     */
    public function getList($filters = [], $limit = 50, $offset = 0) {
        $where = ['1=1'];
        $params = [];

        // Фильтры по датам
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(dr.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(dr.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['delivery_date_from'])) {
            $where[] = 'dr.delivery_date >= :delivery_date_from';
            $params['delivery_date_from'] = $filters['delivery_date_from'];
        }

        if (!empty($filters['delivery_date_to'])) {
            $where[] = 'dr.delivery_date <= :delivery_date_to';
            $params['delivery_date_to'] = $filters['delivery_date_to'];
        }

        // Фильтры по статусам
        if (!empty($filters['status'])) {
            $where[] = 'dr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['call_status'])) {
            $where[] = 'dr.call_status = :call_status';
            $params['call_status'] = $filters['call_status'];
        }

        // Фильтры по структуре
        if (!empty($filters['branch_id'])) {
            $where[] = 'dr.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['department_id'])) {
            $where[] = 'dr.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if (!empty($filters['courier_id'])) {
            $where[] = 'dr.courier_id = :courier_id';
            $params['courier_id'] = $filters['courier_id'];
        }

        if (!empty($filters['operator_id'])) {
            $where[] = 'dr.operator_id = :operator_id';
            $params['operator_id'] = $filters['operator_id'];
        }

        // Поиск по клиенту
        if (!empty($filters['client_search'])) {
            $where[] = "(dr.client_full_name LIKE :client_search OR dr.client_phone LIKE :client_search OR dr.client_pan LIKE :client_search)";
            $params['client_search'] = '%' . $filters['client_search'] . '%';
        }

        // Фильтр по типу карты
        if (!empty($filters['card_type_id'])) {
            $where[] = 'dr.card_type_id = :card_type_id';
            $params['card_type_id'] = $filters['card_type_id'];
        }

        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT dr.id, dr.request_number, dr.abs_id, dr.client_full_name, 
                   dr.client_phone, dr.client_pan, dr.status, dr.call_status,
                   dr.delivery_address, dr.delivery_date, dr.priority,
                   dr.created_at, dr.processed_at, dr.delivered_at,
                   ct.name as card_type_name,
                   b.name as branch_name,
                   d.name as department_name,
                   CONCAT(op.first_name, ' ', op.last_name) as operator_name,
                   CONCAT(c.first_name, ' ', c.last_name) as courier_name,
                   cour.is_online as courier_online
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            LEFT JOIN departments d ON dr.department_id = d.id
            LEFT JOIN users op ON dr.operator_id = op.id
            LEFT JOIN users c ON dr.courier_id = c.id
            LEFT JOIN couriers cour ON c.id = cour.user_id
            WHERE {$whereClause}
            ORDER BY dr.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->select($sql, $params);
    }

    /**
     * Получить количество заявок
     */
    public function getCount($filters = []) {
        $where = ['1=1'];
        $params = [];

        // Применяем те же фильтры, что и в getList
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['courier_id'])) {
            $where[] = 'courier_id = :courier_id';
            $params['courier_id'] = $filters['courier_id'];
        }

        if (!empty($filters['client_search'])) {
            $where[] = "(client_full_name LIKE :client_search OR client_phone LIKE :client_search)";
            $params['client_search'] = '%' . $filters['client_search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM delivery_requests WHERE {$whereClause}";

        $result = $this->db->selectOne($sql, $params);
        return $result['total'] ?? 0;
    }

    /**
     * Обновить заявку
     */
    public function update($id, $requestData) {
        return $this->db->update('delivery_requests', $requestData, 'id = :id', ['id' => $id]);
    }

    /**
     * Изменить статус заявки
     */
    public function updateStatus($id, $newStatus, $userId, $comment = null, $additionalData = []) {
        $this->db->beginTransaction();
        
        try {
            // Получаем текущую заявку
            $request = $this->findById($id);
            if (!$request) {
                throw new Exception('Заявка не найдена');
            }

            $oldStatus = $request['status'];
            
            // Обновляем статус заявки
            $updateData = array_merge($additionalData, ['status' => $newStatus]);
            
            // Добавляем временные метки для определенных статусов
            if ($newStatus === 'delivered') {
                $updateData['delivered_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === 'in_progress' && $oldStatus === 'assigned') {
                $updateData['processed_at'] = date('Y-m-d H:i:s');
            }

            $this->update($id, $updateData);

            // Записываем в историю изменений
            $this->addStatusHistory($id, $oldStatus, $newStatus, $userId, $comment);

            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Назначить курьера на заявку
     */
    public function assignCourier($requestId, $courierId, $assignedBy) {
        $updateData = [
            'courier_id' => $courierId,
            'senior_courier_id' => $assignedBy,
            'status' => 'assigned'
        ];

        return $this->updateStatus($requestId, 'assigned', $assignedBy, 'Назначен курьер', $updateData);
    }

    /**
     * Получить заявки для курьера
     */
    public function getCourierRequests($courierId, $status = null) {
        $where = ['dr.courier_id = :courier_id'];
        $params = ['courier_id' => $courierId];

        if ($status) {
            $where[] = 'dr.status = :status';
            $params['status'] = $status;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT dr.*, ct.name as card_type_name, b.name as branch_name
            FROM delivery_requests dr
            LEFT JOIN card_types ct ON dr.card_type_id = ct.id
            LEFT JOIN branches b ON dr.branch_id = b.id
            WHERE {$whereClause}
            ORDER BY dr.delivery_date ASC, dr.created_at ASC
        ";

        return $this->db->select($sql, $params);
    }

    /**
     * Получить статистику заявок
     */
    public function getStatistics($filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                status,
                COUNT(*) as count
            FROM delivery_requests 
            WHERE {$whereClause}
            GROUP BY status
        ";

        return $this->db->select($sql, $params);
    }

    /**
     * Добавить запись в историю изменений статуса
     */
    private function addStatusHistory($requestId, $oldStatus, $newStatus, $changedBy, $comment) {
        $historyData = [
            'request_id' => $requestId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'comment' => $comment
        ];

        return $this->db->insert('request_status_history', $historyData);
    }

    /**
     * Получить историю изменений заявки
     */
    public function getStatusHistory($requestId) {
        $sql = "
            SELECT rsh.*, CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
            FROM request_status_history rsh
            LEFT JOIN users u ON rsh.changed_by = u.id
            WHERE rsh.request_id = :request_id
            ORDER BY rsh.created_at ASC
        ";

        return $this->db->select($sql, ['request_id' => $requestId]);
    }

    /**
     * Генерировать номер заявки
     */
    private function generateRequestNumber() {
        $prefix = date('Ymd');
        
        // Получаем последний номер за сегодня
        $sql = "SELECT request_number FROM delivery_requests WHERE request_number LIKE :prefix ORDER BY id DESC LIMIT 1";
        $result = $this->db->selectOne($sql, ['prefix' => $prefix . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['request_number'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Экспорт заявок в массив для Excel
     */
    public function exportToArray($filters = []) {
        $requests = $this->getList($filters, 10000, 0); // Получаем все записи
        
        $export = [];
        foreach ($requests as $request) {
            $export[] = [
                'Номер заявки' => $request['request_number'],
                'ID АБС' => $request['abs_id'],
                'ФИО клиента' => $request['client_full_name'],
                'Телефон клиента' => $request['client_phone'],
                'PAN' => $request['client_pan'],
                'Адрес доставки' => $request['delivery_address'],
                'Статус' => $this->getStatusText($request['status']),
                'Статус звонка' => $this->getCallStatusText($request['call_status']),
                'Тип карты' => $request['card_type_name'],
                'Филиал' => $request['branch_name'],
                'Подразделение' => $request['department_name'],
                'Оператор' => $request['operator_name'],
                'Курьер' => $request['courier_name'],
                'Дата создания' => $request['created_at'],
                'Дата доставки' => $request['delivery_date'],
                'Приоритет' => $this->getPriorityText($request['priority'])
            ];
        }

        return $export;
    }

    /**
     * Получить текст статуса
     */
    private function getStatusText($status) {
        $statuses = [
            'new' => 'Новая',
            'assigned' => 'Назначена',
            'in_progress' => 'В работе',
            'delivered' => 'Доставлено',
            'rejected' => 'Отказано',
            'cancelled' => 'Отменена'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Получить текст статуса звонка
     */
    private function getCallStatusText($callStatus) {
        $statuses = [
            'not_called' => 'Не звонили',
            'successful' => 'Успешный',
            'failed' => 'Неудачный',
            'busy' => 'Занято',
            'no_answer' => 'Не отвечает'
        ];

        return $statuses[$callStatus] ?? $callStatus;
    }

    /**
     * Получить текст приоритета
     */
    private function getPriorityText($priority) {
        $priorities = [
            'low' => 'Низкий',
            'normal' => 'Обычный',
            'high' => 'Высокий',
            'urgent' => 'Срочный'
        ];

        return $priorities[$priority] ?? $priority;
    }
}