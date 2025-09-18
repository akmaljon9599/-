<?php
/**
 * Контроллер дашборда
 * Система управления курьерскими заявками
 */

require_once __DIR__ . '/../models/DeliveryRequest.php';
require_once __DIR__ . '/../models/Courier.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DashboardController {
    private $requestModel;
    private $courierModel;
    private $userModel;

    public function __construct() {
        $this->requestModel = new DeliveryRequest();
        $this->courierModel = new Courier();
        $this->userModel = new User();
    }

    /**
     * Получить статистику для дашборда
     */
    public function getStats() {
        try {
            if (!AuthMiddleware::authenticate()) {
                return;
            }

            $user = AuthMiddleware::getCurrentUser();
            $filters = [];

            // Применяем фильтры в зависимости от роли
            $this->applyRoleFilters($filters, $user);

            // Получаем статистику заявок
            $requestStats = $this->getRequestStatistics($filters);
            
            // Получаем статистику курьеров
            $courierStats = $this->getCourierStatistics($user);
            
            // Получаем активность за сегодня
            $todayActivity = $this->getTodayActivity($filters);
            
            // Получаем последние заявки
            $recentRequests = $this->getRecentRequests($filters);

            $this->response([
                'success' => true,
                'data' => [
                    'requests' => $requestStats,
                    'couriers' => $courierStats,
                    'today_activity' => $todayActivity,
                    'recent_requests' => $recentRequests
                ]
            ]);

        } catch (Exception $e) {
            error_log('Get dashboard stats error: ' . $e->getMessage());
            $this->response(['error' => 'Ошибка получения статистики'], 500);
        }
    }

    /**
     * Получить статистику заявок
     */
    private function getRequestStatistics($filters) {
        // Общая статистика
        $totalStats = $this->requestModel->getStatistics($filters);
        
        // Статистика за сегодня
        $todayFilters = array_merge($filters, [
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ]);
        $todayStats = $this->requestModel->getStatistics($todayFilters);

        // Преобразуем в удобный формат
        $total = [
            'new' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'delivered' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'total' => 0
        ];

        $today = [
            'new' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'delivered' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'total' => 0
        ];

        foreach ($totalStats as $stat) {
            $total[$stat['status']] = intval($stat['count']);
            $total['total'] += intval($stat['count']);
        }

        foreach ($todayStats as $stat) {
            $today[$stat['status']] = intval($stat['count']);
            $today['total'] += intval($stat['count']);
        }

        return [
            'total' => $total,
            'today' => $today
        ];
    }

    /**
     * Получить статистику курьеров
     */
    private function getCourierStatistics($user) {
        $branchId = null;
        
        // Ограничиваем по филиалу для не-администраторов
        if ($user['role_name'] !== 'admin') {
            $branchId = $user['branch_id'];
        }

        $filters = [];
        if ($branchId) {
            $filters['branch_id'] = $branchId;
        }

        // Общее количество курьеров
        $totalCouriers = count($this->courierModel->getList($filters, 1000, 0));
        
        // Онлайн курьеры
        $onlineCouriers = $this->courierModel->getOnlineCouriers($branchId);
        $onlineCount = count($onlineCouriers);
        
        // Курьеры на доставке
        $onDeliveryCount = 0;
        foreach ($onlineCouriers as $courier) {
            if ($courier['active_orders'] > 0) {
                $onDeliveryCount++;
            }
        }

        return [
            'total' => $totalCouriers,
            'online' => $onlineCount,
            'on_delivery' => $onDeliveryCount,
            'offline' => $totalCouriers - $onlineCount
        ];
    }

    /**
     * Получить активность за сегодня
     */
    private function getTodayActivity($filters) {
        $todayFilters = array_merge($filters, [
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ]);

        $requests = $this->requestModel->getList($todayFilters, 100, 0);
        
        $activity = [
            'created' => 0,
            'delivered' => 0,
            'rejected' => 0,
            'in_progress' => 0
        ];

        foreach ($requests as $request) {
            if (date('Y-m-d', strtotime($request['created_at'])) === date('Y-m-d')) {
                $activity['created']++;
            }
            
            if ($request['status'] === 'delivered' && 
                $request['delivered_at'] && 
                date('Y-m-d', strtotime($request['delivered_at'])) === date('Y-m-d')) {
                $activity['delivered']++;
            }
            
            if ($request['status'] === 'rejected') {
                $activity['rejected']++;
            }
            
            if ($request['status'] === 'in_progress') {
                $activity['in_progress']++;
            }
        }

        return $activity;
    }

    /**
     * Получить последние заявки
     */
    private function getRecentRequests($filters) {
        return $this->requestModel->getList($filters, 10, 0);
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
            case 'senior_courier':
                if ($user['branch_id']) {
                    $filters['branch_id'] = $user['branch_id'];
                }
                break;

            case 'admin':
                // Администратор видит все данные
                break;
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