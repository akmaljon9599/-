<?php
/**
 * Модель курьера
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../config/Database.php';

class Courier {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать профиль курьера
     */
    public function create($courierData) {
        return $this->db->insert('couriers', $courierData);
    }

    /**
     * Получить курьера по ID пользователя
     */
    public function findByUserId($userId) {
        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone, u.email,
                   b.name as branch_name, d.name as department_name
            FROM couriers c
            INNER JOIN users u ON c.user_id = u.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE c.user_id = :user_id AND u.is_active = 1
        ";
        
        return $this->db->selectOne($sql, ['user_id' => $userId]);
    }

    /**
     * Получить список курьеров
     */
    public function getList($filters = [], $limit = 50, $offset = 0) {
        $where = ['u.is_active = 1'];
        $params = [];

        if (!empty($filters['branch_id'])) {
            $where[] = 'u.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['department_id'])) {
            $where[] = 'u.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if (!empty($filters['is_online'])) {
            $where[] = 'c.is_online = :is_online';
            $params['is_online'] = $filters['is_online'];
        }

        if (!empty($filters['vehicle_type'])) {
            $where[] = 'c.vehicle_type = :vehicle_type';
            $params['vehicle_type'] = $filters['vehicle_type'];
        }

        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone, u.email,
                   b.name as branch_name, d.name as department_name,
                   COUNT(dr.id) as active_orders
            FROM couriers c
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN delivery_requests dr ON c.user_id = dr.courier_id AND dr.status IN ('assigned', 'in_progress')
            WHERE {$whereClause} AND r.name = 'courier'
            GROUP BY c.id
            ORDER BY u.last_name, u.first_name
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->select($sql, $params);
    }

    /**
     * Обновить местоположение курьера
     */
    public function updateLocation($userId, $latitude, $longitude, $accuracy = null, $speed = null, $heading = null) {
        $this->db->beginTransaction();
        
        try {
            // Обновляем текущее местоположение курьера
            $courierData = [
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
                'last_location_update' => date('Y-m-d H:i:s'),
                'is_online' => 1
            ];
            
            $this->db->update('couriers', $courierData, 'user_id = :user_id', ['user_id' => $userId]);

            // Добавляем запись в историю местоположений
            $historyData = [
                'courier_id' => $this->getCourierIdByUserId($userId),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'heading' => $heading
            ];
            
            $this->db->insert('courier_location_history', $historyData);

            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Установить статус онлайн/оффлайн
     */
    public function setOnlineStatus($userId, $isOnline) {
        $updateData = ['is_online' => $isOnline ? 1 : 0];
        
        if (!$isOnline) {
            $updateData['current_latitude'] = null;
            $updateData['current_longitude'] = null;
        }

        return $this->db->update('couriers', $updateData, 'user_id = :user_id', ['user_id' => $userId]);
    }

    /**
     * Получить курьеров онлайн
     */
    public function getOnlineCouriers($branchId = null) {
        $where = ['c.is_online = 1', 'u.is_active = 1'];
        $params = [];

        if ($branchId) {
            $where[] = 'u.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone,
                   b.name as branch_name,
                   COUNT(dr.id) as active_orders
            FROM couriers c
            INNER JOIN users u ON c.user_id = u.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN delivery_requests dr ON c.user_id = dr.courier_id AND dr.status IN ('assigned', 'in_progress')
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.last_location_update DESC
        ";

        return $this->db->select($sql, $params);
    }

    /**
     * Получить историю местоположений курьера
     */
    public function getLocationHistory($courierId, $dateFrom = null, $dateTo = null, $limit = 100) {
        $where = ['courier_id = :courier_id'];
        $params = ['courier_id' => $courierId];

        if ($dateFrom) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT latitude, longitude, accuracy, speed, heading, created_at
            FROM courier_location_history
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $params['limit'] = $limit;

        return $this->db->select($sql, $params);
    }

    /**
     * Получить статистику курьера
     */
    public function getCourierStatistics($userId, $dateFrom = null, $dateTo = null) {
        $where = ['courier_id = :courier_id'];
        $params = ['courier_id' => $userId];

        if ($dateFrom) {
            $where[] = 'DATE(delivered_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $where[] = 'DATE(delivered_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT 
                COUNT(*) as total_deliveries,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as successful_deliveries,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_deliveries,
                AVG(CASE WHEN status = 'delivered' THEN 
                    TIMESTAMPDIFF(HOUR, processed_at, delivered_at) END) as avg_delivery_time_hours
            FROM delivery_requests
            WHERE {$whereClause}
        ";

        return $this->db->selectOne($sql, $params);
    }

    /**
     * Обновить рейтинг курьера
     */
    public function updateRating($userId, $rating) {
        $rating = max(1.0, min(5.0, floatval($rating))); // Ограничиваем рейтинг от 1 до 5
        
        return $this->db->update('couriers', ['rating' => $rating], 'user_id = :user_id', ['user_id' => $userId]);
    }

    /**
     * Получить доступных курьеров для назначения
     */
    public function getAvailableCouriers($branchId, $maxOrders = null) {
        $where = ['u.branch_id = :branch_id', 'c.is_online = 1', 'u.is_active = 1'];
        $params = ['branch_id' => $branchId];

        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.phone,
                   COUNT(dr.id) as current_orders,
                   c.max_orders_per_day
            FROM couriers c
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN delivery_requests dr ON c.user_id = dr.courier_id AND dr.status IN ('assigned', 'in_progress')
            WHERE " . implode(' AND ', $where) . " AND r.name = 'courier'
            GROUP BY c.id
            HAVING (current_orders < c.max_orders_per_day OR c.max_orders_per_day IS NULL)
            ORDER BY current_orders ASC, c.rating DESC
        ";

        return $this->db->select($sql, $params);
    }

    /**
     * Получить ID курьера по ID пользователя
     */
    private function getCourierIdByUserId($userId) {
        $sql = "SELECT id FROM couriers WHERE user_id = :user_id";
        $result = $this->db->selectOne($sql, ['user_id' => $userId]);
        return $result ? $result['id'] : null;
    }

    /**
     * Очистить старые записи местоположений
     */
    public function cleanOldLocationHistory($days = 30) {
        $sql = "DELETE FROM courier_location_history WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        return $this->db->query($sql, ['days' => $days]);
    }

    /**
     * Получить курьеров для карты (только онлайн с координатами)
     */
    public function getCouriersForMap($branchId = null) {
        $where = [
            'c.is_online = 1', 
            'u.is_active = 1',
            'c.current_latitude IS NOT NULL',
            'c.current_longitude IS NOT NULL'
        ];
        $params = [];

        if ($branchId) {
            $where[] = 'u.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT c.user_id, c.current_latitude as latitude, c.current_longitude as longitude,
                   c.last_location_update, c.vehicle_type,
                   CONCAT(u.first_name, ' ', u.last_name) as courier_name,
                   u.phone,
                   COUNT(dr.id) as active_orders,
                   CASE 
                       WHEN COUNT(dr.id) > 0 THEN 'on_delivery'
                       ELSE 'available'
                   END as status
            FROM couriers c
            INNER JOIN users u ON c.user_id = u.id
            LEFT JOIN delivery_requests dr ON c.user_id = dr.courier_id AND dr.status IN ('assigned', 'in_progress')
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.last_location_update DESC
        ";

        return $this->db->select($sql, $params);
    }

    /**
     * Обновить информацию о курьере
     */
    public function update($userId, $courierData) {
        return $this->db->update('couriers', $courierData, 'user_id = :user_id', ['user_id' => $userId]);
    }
}