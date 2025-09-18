<?php
/**
 * Модель пользователя
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../config/Database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать нового пользователя
     */
    public function create($userData) {
        // Хешируем пароль
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        }

        return $this->db->insert('users', $userData);
    }

    /**
     * Получить пользователя по ID
     */
    public function findById($id) {
        $sql = "
            SELECT u.*, r.name as role_name, r.permissions, 
                   b.name as branch_name, d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = :id AND u.is_active = 1
        ";
        
        return $this->db->selectOne($sql, ['id' => $id]);
    }

    /**
     * Получить пользователя по email
     */
    public function findByEmail($email) {
        $sql = "
            SELECT u.*, r.name as role_name, r.permissions,
                   b.name as branch_name, d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.email = :email AND u.is_active = 1
        ";
        
        return $this->db->selectOne($sql, ['email' => $email]);
    }

    /**
     * Получить пользователя по username
     */
    public function findByUsername($username) {
        $sql = "
            SELECT u.*, r.name as role_name, r.permissions,
                   b.name as branch_name, d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.username = :username AND u.is_active = 1
        ";
        
        return $this->db->selectOne($sql, ['username' => $username]);
    }

    /**
     * Получить список пользователей с фильтрацией
     */
    public function getList($filters = [], $limit = 50, $offset = 0) {
        $where = ['u.is_active = 1'];
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = :role_id';
            $params['role_id'] = $filters['role_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 'u.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['department_id'])) {
            $where[] = 'u.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.middle_name,
                   u.phone, u.last_login, u.created_at,
                   r.name as role_name, b.name as branch_name, d.name as department_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->db->select($sql, $params);
    }

    /**
     * Получить количество пользователей
     */
    public function getCount($filters = []) {
        $where = ['is_active = 1'];
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[] = 'role_id = :role_id';
            $params['role_id'] = $filters['role_id'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM users WHERE {$whereClause}";

        $result = $this->db->selectOne($sql, $params);
        return $result['total'] ?? 0;
    }

    /**
     * Обновить пользователя
     */
    public function update($id, $userData) {
        // Если передан новый пароль, хешируем его
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        }

        return $this->db->update('users', $userData, 'id = :id', ['id' => $id]);
    }

    /**
     * Деактивировать пользователя (мягкое удаление)
     */
    public function deactivate($id) {
        return $this->db->update('users', ['is_active' => 0], 'id = :id', ['id' => $id]);
    }

    /**
     * Проверить пароль пользователя
     */
    public function verifyPassword($user, $password) {
        return password_verify($password, $user['password_hash']);
    }

    /**
     * Обновить время последнего входа
     */
    public function updateLastLogin($id) {
        return $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /**
     * Получить курьеров по филиалу
     */
    public function getCouriersByBranch($branchId) {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.phone,
                   c.is_online, c.vehicle_type, c.rating
            FROM users u
            INNER JOIN couriers c ON u.id = c.user_id
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.branch_id = :branch_id AND r.name = 'courier' AND u.is_active = 1
            ORDER BY u.last_name, u.first_name
        ";

        return $this->db->select($sql, ['branch_id' => $branchId]);
    }

    /**
     * Получить операторов по филиалу
     */
    public function getOperatorsByBranch($branchId) {
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.branch_id = :branch_id AND r.name IN ('operator', 'senior_courier', 'admin') AND u.is_active = 1
            ORDER BY u.last_name, u.first_name
        ";

        return $this->db->select($sql, ['branch_id' => $branchId]);
    }

    /**
     * Проверить права пользователя
     */
    public function hasPermission($userId, $permission) {
        $user = $this->findById($userId);
        if (!$user) return false;

        $permissions = json_decode($user['permissions'], true);
        
        // Если есть право 'all', то доступны все действия
        if (in_array('all', $permissions)) return true;
        
        return in_array($permission, $permissions);
    }

    /**
     * Получить статистику пользователей
     */
    public function getStatistics() {
        $sql = "
            SELECT 
                r.name as role_name,
                COUNT(*) as count
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.is_active = 1
            GROUP BY r.name
        ";

        return $this->db->select($sql);
    }
}