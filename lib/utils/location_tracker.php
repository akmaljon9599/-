<?php
namespace CourierService\Utils;

use Bitrix\Main\Type\DateTime;

class LocationTracker
{
    private $updateInterval = 60; // секунды
    private $maxDistance = 100; // метры для определения движения

    public function __construct()
    {
        $this->updateInterval = \CourierService\Main\SettingTable::get('location_update_interval', 60);
        $this->maxDistance = \CourierService\Main\SettingTable::get('location_max_distance', 100);
    }

    /**
     * Обновление местоположения курьера
     */
    public function updateCourierLocation($courierId, $latitude, $longitude, $accuracy = null)
    {
        try {
            // Получаем текущее местоположение курьера
            $currentLocation = \CourierService\Main\CourierTable::getList([
                'filter' => ['ID' => $courierId],
                'select' => ['LAST_LOCATION_LAT', 'LAST_LOCATION_LON', 'LAST_ACTIVITY']
            ])->fetch();

            // Проверяем, изменилось ли местоположение значительно
            if ($currentLocation) {
                $distance = $this->calculateDistance(
                    $currentLocation['LAST_LOCATION_LAT'],
                    $currentLocation['LAST_LOCATION_LON'],
                    $latitude,
                    $longitude
                );

                // Если курьер не сдвинулся значительно, не обновляем
                if ($distance < $this->maxDistance) {
                    return [
                        'success' => true,
                        'updated' => false,
                        'message' => 'Местоположение не изменилось значительно'
                    ];
                }
            }

            // Обновляем местоположение
            $result = \CourierService\Main\CourierTable::updateLocation($courierId, $latitude, $longitude);

            if ($result->isSuccess()) {
                // Логируем изменение местоположения
                \CourierService\Main\LogTable::logAction(
                    $GLOBALS['USER']->GetID(),
                    'location_update',
                    'courier',
                    $courierId,
                    $currentLocation,
                    ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => $accuracy]
                );

                return [
                    'success' => true,
                    'updated' => true,
                    'distance' => $distance ?? 0,
                    'message' => 'Местоположение обновлено'
                ];
            } else {
                throw new \Exception('Ошибка обновления местоположения: ' . implode(', ', $result->getErrorMessages()));
            }

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'updateCourierLocation', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение активных курьеров с их местоположением
     */
    public function getActiveCouriersLocations()
    {
        try {
            $couriers = \CourierService\Main\CourierTable::getActiveCouriers();
            $locations = [];

            while ($courier = $couriers->fetch()) {
                if ($courier['LAST_LOCATION_LAT'] && $courier['LAST_LOCATION_LON']) {
                    $locations[] = [
                        'id' => $courier['ID'],
                        'name' => $courier['NAME'],
                        'latitude' => $courier['LAST_LOCATION_LAT'],
                        'longitude' => $courier['LAST_LOCATION_LON'],
                        'status' => $courier['STATUS'],
                        'last_activity' => $courier['LAST_ACTIVITY']->format('Y-m-d H:i:s'),
                        'branch' => $courier['BRANCH_NAME'] ?? ''
                    ];
                }
            }

            return [
                'success' => true,
                'couriers' => $locations,
                'count' => count($locations)
            ];

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'getActiveCouriersLocations', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение маршрута курьера за период
     */
    public function getCourierRoute($courierId, $startDate, $endDate)
    {
        try {
            // Получаем логи местоположения курьера
            $logs = \CourierService\Main\LogTable::getList([
                'filter' => [
                    'USER_ID' => $courierId,
                    'ACTION' => 'location_update',
                    'ENTITY_TYPE' => 'courier',
                    'ENTITY_ID' => $courierId,
                    '>=CREATED_DATE' => $startDate,
                    '<=CREATED_DATE' => $endDate
                ],
                'order' => ['CREATED_DATE' => 'ASC']
            ]);

            $route = [];
            while ($log = $logs->fetch()) {
                $newData = json_decode($log['NEW_DATA'], true);
                if ($newData && isset($newData['latitude'], $newData['longitude'])) {
                    $route[] = [
                        'latitude' => $newData['latitude'],
                        'longitude' => $newData['longitude'],
                        'timestamp' => $log['CREATED_DATE']->format('Y-m-d H:i:s'),
                        'accuracy' => $newData['accuracy'] ?? null
                    ];
                }
            }

            return [
                'success' => true,
                'route' => $route,
                'points_count' => count($route)
            ];

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'getCourierRoute', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Поиск ближайших курьеров к точке
     */
    public function findNearestCouriers($latitude, $longitude, $radius = 5000, $limit = 5)
    {
        try {
            $couriers = \CourierService\Main\CourierTable::getList([
                'filter' => [
                    'STATUS' => 'active',
                    'IS_ONLINE' => 'Y'
                ],
                'select' => ['*', 'BRANCH']
            ]);

            $nearestCouriers = [];
            while ($courier = $couriers->fetch()) {
                if ($courier['LAST_LOCATION_LAT'] && $courier['LAST_LOCATION_LON']) {
                    $distance = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $courier['LAST_LOCATION_LAT'],
                        $courier['LAST_LOCATION_LON']
                    );

                    if ($distance <= $radius) {
                        $nearestCouriers[] = [
                            'id' => $courier['ID'],
                            'name' => $courier['NAME'],
                            'phone' => $courier['PHONE'],
                            'latitude' => $courier['LAST_LOCATION_LAT'],
                            'longitude' => $courier['LAST_LOCATION_LON'],
                            'distance' => round($distance),
                            'status' => $courier['STATUS'],
                            'branch' => $courier['BRANCH_NAME'] ?? ''
                        ];
                    }
                }
            }

            // Сортируем по расстоянию
            usort($nearestCouriers, function($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            // Ограничиваем количество
            $nearestCouriers = array_slice($nearestCouriers, 0, $limit);

            return [
                'success' => true,
                'couriers' => $nearestCouriers,
                'count' => count($nearestCouriers)
            ];

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'findNearestCouriers', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Расчет расстояния между двумя точками (формула Хаверсинуса)
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Радиус Земли в метрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Проверка активности курьера
     */
    public function checkCourierActivity($courierId)
    {
        try {
            $courier = \CourierService\Main\CourierTable::getList([
                'filter' => ['ID' => $courierId],
                'select' => ['LAST_ACTIVITY', 'IS_ONLINE']
            ])->fetch();

            if (!$courier) {
                return [
                    'success' => false,
                    'error' => 'Курьер не найден'
                ];
            }

            $lastActivity = $courier['LAST_ACTIVITY'];
            $isOnline = $courier['IS_ONLINE'] === 'Y';

            if ($lastActivity) {
                $timeDiff = time() - $lastActivity->getTimestamp();
                $isActive = $timeDiff < ($this->updateInterval * 3); // 3 интервала неактивности

                // Обновляем статус онлайн/оффлайн
                if ($isActive !== $isOnline) {
                    \CourierService\Main\CourierTable::update($courierId, [
                        'IS_ONLINE' => $isActive ? 'Y' : 'N'
                    ]);
                }

                return [
                    'success' => true,
                    'is_online' => $isActive,
                    'last_activity' => $lastActivity->format('Y-m-d H:i:s'),
                    'time_since_activity' => $timeDiff
                ];
            }

            return [
                'success' => true,
                'is_online' => false,
                'last_activity' => null,
                'time_since_activity' => null
            ];

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'checkCourierActivity', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получение статистики по местоположению курьеров
     */
    public function getLocationStats()
    {
        try {
            $totalCouriers = \CourierService\Main\CourierTable::getList([
                'select' => ['ID']
            ])->getSelectedRowsCount();

            $onlineCouriers = \CourierService\Main\CourierTable::getList([
                'filter' => ['IS_ONLINE' => 'Y'],
                'select' => ['ID']
            ])->getSelectedRowsCount();

            $activeCouriers = \CourierService\Main\CourierTable::getList([
                'filter' => ['STATUS' => 'active'],
                'select' => ['ID']
            ])->getSelectedRowsCount();

            $onDeliveryCouriers = \CourierService\Main\CourierTable::getList([
                'filter' => ['STATUS' => 'on_delivery'],
                'select' => ['ID']
            ])->getSelectedRowsCount();

            return [
                'success' => true,
                'stats' => [
                    'total_couriers' => $totalCouriers,
                    'online_couriers' => $onlineCouriers,
                    'active_couriers' => $activeCouriers,
                    'on_delivery_couriers' => $onDeliveryCouriers,
                    'offline_couriers' => $totalCouriers - $onlineCouriers
                ]
            ];

        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Location Tracker', 'getLocationStats', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}