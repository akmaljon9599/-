<?php
/**
 * Контроллер курьеров
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/Courier.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class CourierController {
    private $courierModel;

    public function __construct() {
        $this->courierModel = new Courier();
    }

    /**
     * Получить список курьеров
     */
    public function index() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = $_GET;

            // Применяем фильтры в зависимости от роли
            if ($user['role_name'] !== 'admin') {
                $filters['branch_id'] = $user['branch_id'];
            }

            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $couriers = $this->courierModel->getList($filters, $limit, $offset);

            $this->response([
                'success' => true,
                'data' => $couriers
            ]);

        } catch (Exception $e) {
            error_log('Get couriers error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения курьеров'], 500);
        }
    }

    /**
     * Получить курьера по ID
     */
    public function show($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $courier = $this->courierModel->findByUserId($id);
            
            if (!$courier) {
                $this->response(['error' => 'Курьер не найден'], 404);
                return;
            }

            // Проверяем доступ
            if (!AuthMiddleware::checkBranchAccess($courier['branch_id'])) {
                $this->response(['error' => 'Нет доступа к курьеру'], 403);
                return;
            }

            $this->response([
                'success' => true,
                'data' => $courier
            ]);

        } catch (Exception $e) {
            error_log('Get courier error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения курьера'], 500);
        }
    }

    /**
     * Обновить местоположение курьера
     */
    public function updateLocation($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            
            // Курьер может обновлять только свое местоположение
            if ($user['role_name'] === 'courier' && $user['id'] != $id) {
                $this->response(['error' => 'Нет доступа'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['latitude']) || !isset($input['longitude'])) {
                $this->response(['error' => 'Не указаны координаты'], 400);
                return;
            }

            $this->courierModel->updateLocation(
                $id,
                $input['latitude'],
                $input['longitude'],
                $input['accuracy'] ?? null,
                $input['speed'] ?? null,
                $input['heading'] ?? null
            );

            $this->response([
                'success' => true,
                'message' => 'Местоположение обновлено'
            ]);

        } catch (Exception $e) {
            error_log('Update location error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка обновления местоположения'], 500);
        }
    }

    /**
     * Изменить онлайн статус курьера
     */
    public function updateOnlineStatus($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            
            // Курьер может изменять только свой статус
            if ($user['role_name'] === 'courier' && $user['id'] != $id) {
                $this->response(['error' => 'Нет доступа'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['is_online'])) {
                $this->response(['error' => 'Не указан статус'], 400);
                return;
            }

            $this->courierModel->setOnlineStatus($id, $input['is_online']);

            AuthMiddleware::logActivity('update_online_status', 'courier', $id, ['is_online' => $input['is_online']]);

            $this->response([
                'success' => true,
                'message' => 'Статус обновлен'
            ]);

        } catch (Exception $e) {
            error_log('Update online status error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка обновления статуса'], 500);
        }
    }

    /**
     * Получить онлайн курьеров
     */
    public function getOnlineCouriers() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $branchId = null;

            // Ограничиваем по филиалу для не-администраторов
            if ($user['role_name'] !== 'admin') {
                $branchId = $user['branch_id'];
            }

            $couriers = $this->courierModel->getOnlineCouriers($branchId);

            $this->response([
                'success' => true,
                'data' => $couriers
            ]);

        } catch (Exception $e) {
            error_log('Get online couriers error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения онлайн курьеров'], 500);
        }
    }

    /**
     * Получить курьеров для отображения на карте
     */
    public function getCouriersForMap() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $branchId = null;

            // Ограничиваем по филиалу для не-администраторов
            if ($user['role_name'] !== 'admin') {
                $branchId = $user['branch_id'];
            }

            $couriers = $this->courierModel->getCouriersForMap($branchId);

            $this->response([
                'success' => true,
                'data' => $couriers
            ]);

        } catch (Exception $e) {
            error_log('Get couriers for map error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения курьеров для карты'], 500);
        }
    }

    /**
     * Получить историю местоположений курьера
     */
    public function getLocationHistory($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $courier = $this->courierModel->findByUserId($id);
            
            if (!$courier) {
                $this->response(['error' => 'Курьер не найден'], 404);
                return;
            }

            // Проверяем доступ
            if (!AuthMiddleware::checkBranchAccess($courier['branch_id'])) {
                $this->response(['error' => 'Нет доступа к курьеру'], 403);
                return;
            }

            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $limit = intval($_GET['limit'] ?? 100);

            $history = $this->courierModel->getLocationHistory($courier['id'], $dateFrom, $dateTo, $limit);

            $this->response([
                'success' => true,
                'data' => $history
            ]);

        } catch (Exception $e) {
            error_log('Get location history error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения истории местоположений'], 500);
        }
    }

    /**
     * Получить статистику курьера
     */
    public function getStatistics($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();

            // Курьер может смотреть только свою статистику
            if ($user['role_name'] === 'courier' && $user['id'] != $id) {
                $this->response(['error' => 'Нет доступа'], 403);
                return;
            }

            $courier = $this->courierModel->findByUserId($id);
            
            if (!$courier) {
                $this->response(['error' => 'Курьер не найден'], 404);
                return;
            }

            // Проверяем доступ для других ролей
            if ($user['role_name'] !== 'courier' && !AuthMiddleware::checkBranchAccess($courier['branch_id'])) {
                $this->response(['error' => 'Нет доступа к курьеру'], 403);
                return;
            }

            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            $statistics = $this->courierModel->getCourierStatistics($id, $dateFrom, $dateTo);

            $this->response([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (Exception $e) {
            error_log('Get courier statistics error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения статистики'], 500);
        }
    }

    /**
     * Обновить информацию о курьере
     */
    public function update($id) {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            if (!AuthMiddleware::requireRole(['admin', 'senior_courier'])) {
                return;
            }

            $courier = $this->courierModel->findByUserId($id);
            
            if (!$courier) {
                $this->response(['error' => 'Курьер не найден'], 404);
                return;
            }

            // Проверяем доступ
            if (!AuthMiddleware::checkBranchAccess($courier['branch_id'])) {
                $this->response(['error' => 'Нет доступа к курьеру'], 403);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // Разрешенные поля для обновления
            $allowedFields = ['vehicle_type', 'license_number', 'max_orders_per_day'];
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

            $this->courierModel->update($id, $updateData);

            AuthMiddleware::logActivity('update_courier', 'courier', $id, $updateData);

            $this->response([
                'success' => true,
                'message' => 'Информация о курьере обновлена'
            ]);

        } catch (Exception $e) {
            error_log('Update courier error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка обновления курьера'], 500);
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