<?php
/**
 * Главный API роутер
 * Система управления курьерскими заявками
 */

// Включаем отображение ошибок для разработки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Устанавливаем заголовки
header('Content-Type: application/json; charset=utf-8');

// Подключаем необходимые файлы
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/DeliveryRequestController.php';

// Обрабатываем CORS
AuthMiddleware::handleCORS();

// Получаем метод запроса и URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Убираем query string из URI
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}

// Убираем базовый путь API
$basePath = '/backend/api';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Разбиваем URI на части
$uriParts = explode('/', trim($uri, '/'));
$endpoint = $uriParts[0] ?? '';

try {
    switch ($endpoint) {
        case 'auth':
            handleAuthRoutes($method, $uriParts);
            break;

        case 'requests':
            handleRequestRoutes($method, $uriParts);
            break;

        case 'couriers':
            handleCourierRoutes($method, $uriParts);
            break;

        case 'users':
            handleUserRoutes($method, $uriParts);
            break;

        case 'dashboard':
            handleDashboardRoutes($method, $uriParts);
            break;

        case 'upload':
            handleUploadRoutes($method, $uriParts);
            break;

        default:
            sendResponse(['error' => 'Endpoint not found'], 404);
            break;
    }

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    sendResponse(['error' => 'Internal server error'], 500);
}

/**
 * Обработка маршрутов аутентификации
 */
function handleAuthRoutes($method, $uriParts) {
    $controller = new AuthController();
    $action = $uriParts[1] ?? '';

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'login':
                    $controller->login();
                    break;
                case 'logout':
                    $controller->logout();
                    break;
                case 'change-password':
                    $controller->changePassword();
                    break;
                default:
                    sendResponse(['error' => 'Invalid auth endpoint'], 404);
            }
            break;

        case 'GET':
            switch ($action) {
                case 'me':
                    $controller->me();
                    break;
                default:
                    sendResponse(['error' => 'Invalid auth endpoint'], 404);
            }
            break;

        case 'PUT':
            switch ($action) {
                case 'profile':
                    $controller->updateProfile();
                    break;
                default:
                    sendResponse(['error' => 'Invalid auth endpoint'], 404);
            }
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Обработка маршрутов заявок
 */
function handleRequestRoutes($method, $uriParts) {
    $controller = new DeliveryRequestController();
    $id = $uriParts[1] ?? null;
    $action = $uriParts[2] ?? '';

    switch ($method) {
        case 'GET':
            if ($id === 'export') {
                $controller->export();
            } elseif ($id === 'statistics') {
                $controller->getStatistics();
            } elseif ($id === 'courier') {
                $controller->getCourierRequests();
            } elseif ($id) {
                $controller->show($id);
            } else {
                $controller->index();
            }
            break;

        case 'POST':
            if ($id && $action === 'assign-courier') {
                $controller->assignCourier($id);
            } elseif ($id && $action === 'update-status') {
                $controller->updateStatus($id);
            } else {
                $controller->create();
            }
            break;

        case 'PUT':
            if ($id) {
                $controller->update($id);
            } else {
                sendResponse(['error' => 'ID required'], 400);
            }
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Обработка маршрутов курьеров
 */
function handleCourierRoutes($method, $uriParts) {
    require_once __DIR__ . '/../controllers/CourierController.php';
    $controller = new CourierController();
    $id = $uriParts[1] ?? null;
    $action = $uriParts[2] ?? '';

    switch ($method) {
        case 'GET':
            if ($id === 'online') {
                $controller->getOnlineCouriers();
            } elseif ($id === 'map') {
                $controller->getCouriersForMap();
            } elseif ($id && $action === 'location-history') {
                $controller->getLocationHistory($id);
            } elseif ($id && $action === 'statistics') {
                $controller->getStatistics($id);
            } elseif ($id) {
                $controller->show($id);
            } else {
                $controller->index();
            }
            break;

        case 'POST':
            if ($id && $action === 'location') {
                $controller->updateLocation($id);
            } elseif ($id && $action === 'status') {
                $controller->updateOnlineStatus($id);
            } else {
                sendResponse(['error' => 'Invalid courier endpoint'], 404);
            }
            break;

        case 'PUT':
            if ($id) {
                $controller->update($id);
            } else {
                sendResponse(['error' => 'ID required'], 400);
            }
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Обработка маршрутов пользователей
 */
function handleUserRoutes($method, $uriParts) {
    require_once __DIR__ . '/../controllers/UserController.php';
    $controller = new UserController();
    $id = $uriParts[1] ?? null;

    switch ($method) {
        case 'GET':
            if ($id) {
                $controller->show($id);
            } else {
                $controller->index();
            }
            break;

        case 'POST':
            $controller->create();
            break;

        case 'PUT':
            if ($id) {
                $controller->update($id);
            } else {
                sendResponse(['error' => 'ID required'], 400);
            }
            break;

        case 'DELETE':
            if ($id) {
                $controller->delete($id);
            } else {
                sendResponse(['error' => 'ID required'], 400);
            }
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Обработка маршрутов дашборда
 */
function handleDashboardRoutes($method, $uriParts) {
    require_once __DIR__ . '/../controllers/DashboardController.php';
    $controller = new DashboardController();

    switch ($method) {
        case 'GET':
            $controller->getStats();
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Обработка загрузки файлов
 */
function handleUploadRoutes($method, $uriParts) {
    require_once __DIR__ . '/../controllers/UploadController.php';
    $controller = new UploadController();
    $type = $uriParts[1] ?? '';

    switch ($method) {
        case 'POST':
            switch ($type) {
                case 'photo':
                    $controller->uploadPhoto();
                    break;
                case 'document':
                    $controller->uploadDocument();
                    break;
                case 'signature':
                    $controller->uploadSignature();
                    break;
                default:
                    sendResponse(['error' => 'Invalid upload type'], 400);
            }
            break;

        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Отправить JSON ответ
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}