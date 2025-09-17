<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с курьерами
 */
class CourierTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_couriers';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            
            new Entity\IntegerField('USER_ID', [
                'required' => true
            ]),
            
            new Entity\StringField('FULL_NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255)
                    ];
                }
            ]),
            
            new Entity\StringField('PHONE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 20),
                        new Entity\Validator\RegExp('/^\+7\s?\(\d{3}\)\s?\d{3}-\d{2}-\d{2}$/')
                    ];
                }
            ]),
            
            new Entity\EnumField('STATUS', [
                'values' => ['ONLINE', 'OFFLINE', 'ON_DELIVERY', 'BREAK'],
                'default_value' => 'OFFLINE'
            ]),
            
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            
            new Entity\FloatField('CURRENT_LATITUDE', [
                'scale' => 8
            ]),
            
            new Entity\FloatField('CURRENT_LONGITUDE', [
                'scale' => 8
            ]),
            
            new Entity\DatetimeField('LAST_LOCATION_UPDATE'),
            
            new Entity\BooleanField('IS_ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            
            new Entity\DatetimeField('CREATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            new Entity\DatetimeField('UPDATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            // Связи
            new Entity\ReferenceField(
                'USER',
                'Bitrix\Main\UserTable',
                ['=this.USER_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'BRANCH',
                'Courier\Delivery\BranchTable',
                ['=this.BRANCH_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'DELIVERIES',
                'Courier\Delivery\DeliveryTable',
                ['=this.ID' => 'ref.COURIER_ID']
            ),
            
            new Entity\ReferenceField(
                'LOCATION_LOG',
                'Courier\Delivery\LocationLogTable',
                ['=this.ID' => 'ref.COURIER_ID']
            )
        ];
    }

    /**
     * Получить список активных курьеров
     */
    public static function getActiveCouriers($branchId = null)
    {
        $filter = ['IS_ACTIVE' => 'Y'];
        
        if ($branchId) {
            $filter['BRANCH_ID'] = $branchId;
        }

        return static::getList([
            'select' => [
                'ID', 'FULL_NAME', 'PHONE', 'STATUS', 
                'CURRENT_LATITUDE', 'CURRENT_LONGITUDE', 'LAST_LOCATION_UPDATE',
                'BRANCH.NAME' => 'BRANCH_NAME',
                'USER.LOGIN' => 'USER_LOGIN',
                'USER.EMAIL' => 'USER_EMAIL'
            ],
            'filter' => $filter,
            'order' => ['FULL_NAME' => 'ASC']
        ]);
    }

    /**
     * Обновить местоположение курьера
     */
    public static function updateLocation($courierId, $latitude, $longitude, $accuracy = null)
    {
        $updateResult = static::update($courierId, [
            'CURRENT_LATITUDE' => $latitude,
            'CURRENT_LONGITUDE' => $longitude,
            'LAST_LOCATION_UPDATE' => new DateTime(),
            'UPDATED_DATE' => new DateTime()
        ]);

        if ($updateResult->isSuccess()) {
            // Сохраняем в лог местоположений
            LocationLogTable::add([
                'COURIER_ID' => $courierId,
                'LATITUDE' => $latitude,
                'LONGITUDE' => $longitude,
                'ACCURACY' => $accuracy
            ]);

            // Генерируем событие
            $event = new \Bitrix\Main\Event('courier.delivery', 'OnCourierLocationUpdate', [
                'COURIER_ID' => $courierId,
                'LATITUDE' => $latitude,
                'LONGITUDE' => $longitude,
                'ACCURACY' => $accuracy
            ]);
            $event->send();

            return true;
        }

        return false;
    }

    /**
     * Изменить статус курьера
     */
    public static function changeStatus($courierId, $newStatus, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        // Получаем текущего курьера
        $courier = static::getById($courierId)->fetch();
        if (!$courier) {
            return false;
        }

        $oldStatus = $courier['STATUS'];

        $updateResult = static::update($courierId, [
            'STATUS' => $newStatus,
            'UPDATED_DATE' => new DateTime()
        ]);

        if ($updateResult->isSuccess()) {
            // Логируем изменение статуса
            \Courier\Delivery\Util\Logger::log(
                "Courier #{$courierId} status changed: {$oldStatus} -> {$newStatus}",
                'COURIER_STATUS_CHANGE',
                $userId
            );

            return true;
        }

        return false;
    }

    /**
     * Получить курьеров в радиусе от точки
     */
    public static function getCouriersInRadius($latitude, $longitude, $radiusKm = 10, $status = 'ONLINE')
    {
        // Примерные коэффициенты для расчета расстояния
        $latDelta = $radiusKm / 111; // ~111 км на градус широты
        $lonDelta = $radiusKm / (111 * cos(deg2rad($latitude)));

        return static::getList([
            'select' => [
                'ID', 'FULL_NAME', 'PHONE', 'STATUS',
                'CURRENT_LATITUDE', 'CURRENT_LONGITUDE', 'LAST_LOCATION_UPDATE',
                'BRANCH.NAME' => 'BRANCH_NAME'
            ],
            'filter' => [
                'STATUS' => $status,
                'IS_ACTIVE' => 'Y',
                '>=CURRENT_LATITUDE' => $latitude - $latDelta,
                '<=CURRENT_LATITUDE' => $latitude + $latDelta,
                '>=CURRENT_LONGITUDE' => $longitude - $lonDelta,
                '<=CURRENT_LONGITUDE' => $longitude + $lonDelta,
                '!CURRENT_LATITUDE' => null,
                '!CURRENT_LONGITUDE' => null
            ],
            'order' => ['LAST_LOCATION_UPDATE' => 'DESC']
        ]);
    }

    /**
     * Получить статистику по курьерам
     */
    public static function getCourierStatistics($dateFrom = null, $dateTo = null)
    {
        $filter = ['IS_ACTIVE' => 'Y'];
        
        if ($dateFrom) {
            $filter['>=CREATED_DATE'] = $dateFrom;
        }
        
        if ($dateTo) {
            $filter['<=CREATED_DATE'] = $dateTo;
        }

        // Статистика по статусам
        $statusStats = static::getList([
            'select' => [
                'STATUS',
                'CNT'
            ],
            'filter' => $filter,
            'group' => ['STATUS'],
            'runtime' => [
                new Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);

        $stats = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'on_delivery' => 0,
            'on_break' => 0
        ];

        while ($row = $statusStats->fetch()) {
            $status = strtolower($row['STATUS']);
            $count = (int)$row['CNT'];
            
            $stats['total'] += $count;
            
            if ($status === 'on_delivery') {
                $stats['on_delivery'] = $count;
            } elseif ($status === 'break') {
                $stats['on_break'] = $count;
            } elseif (isset($stats[$status])) {
                $stats[$status] = $count;
            }
        }

        return $stats;
    }

    /**
     * Получить рабочую нагрузку курьеров
     */
    public static function getCourierWorkload($branchId = null)
    {
        $filter = ['IS_ACTIVE' => 'Y'];
        
        if ($branchId) {
            $filter['BRANCH_ID'] = $branchId;
        }

        return static::getList([
            'select' => [
                'ID', 'FULL_NAME', 'STATUS',
                'BRANCH.NAME' => 'BRANCH_NAME'
            ],
            'filter' => $filter,
            'runtime' => [
                new Entity\ReferenceField(
                    'ACTIVE_DELIVERIES',
                    'Courier\Delivery\DeliveryTable',
                    [
                        '=this.ID' => 'ref.COURIER_ID',
                        'ref.STATUS' => ['ASSIGNED', 'IN_DELIVERY']
                    ]
                ),
                new Entity\ExpressionField(
                    'ACTIVE_DELIVERIES_COUNT',
                    'COUNT(%s)',
                    'ACTIVE_DELIVERIES.ID'
                )
            ],
            'group' => ['ID'],
            'order' => ['ACTIVE_DELIVERIES_COUNT' => 'ASC', 'FULL_NAME' => 'ASC']
        ]);
    }

    /**
     * Найти ближайшего свободного курьера
     */
    public static function findNearestAvailableCourier($latitude, $longitude, $branchId = null)
    {
        $filter = [
            'STATUS' => 'ONLINE',
            'IS_ACTIVE' => 'Y',
            '!CURRENT_LATITUDE' => null,
            '!CURRENT_LONGITUDE' => null
        ];

        if ($branchId) {
            $filter['BRANCH_ID'] = $branchId;
        }

        $couriers = static::getList([
            'select' => [
                'ID', 'FULL_NAME', 'PHONE', 
                'CURRENT_LATITUDE', 'CURRENT_LONGITUDE',
                'BRANCH.NAME' => 'BRANCH_NAME'
            ],
            'filter' => $filter,
            'runtime' => [
                new Entity\ReferenceField(
                    'ACTIVE_DELIVERIES',
                    'Courier\Delivery\DeliveryTable',
                    [
                        '=this.ID' => 'ref.COURIER_ID',
                        'ref.STATUS' => ['ASSIGNED', 'IN_DELIVERY']
                    ]
                ),
                new Entity\ExpressionField(
                    'ACTIVE_DELIVERIES_COUNT',
                    'COUNT(%s)',
                    'ACTIVE_DELIVERIES.ID'
                )
            ],
            'group' => ['ID'],
            'order' => ['ACTIVE_DELIVERIES_COUNT' => 'ASC']
        ]);

        $nearestCourier = null;
        $minDistance = PHP_FLOAT_MAX;

        while ($courier = $couriers->fetch()) {
            // Вычисляем расстояние по формуле гаверсинуса
            $distance = static::calculateDistance(
                $latitude, $longitude,
                $courier['CURRENT_LATITUDE'], $courier['CURRENT_LONGITUDE']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestCourier = $courier;
                $nearestCourier['DISTANCE'] = $distance;
            }
        }

        return $nearestCourier;
    }

    /**
     * Вычисление расстояния между двумя точками по формуле гаверсинуса
     */
    private static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Радиус Земли в километрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Обработка входа пользователя в систему
     */
    public static function onUserLogin($userId)
    {
        // Проверяем, является ли пользователь курьером
        $courier = static::getList([
            'select' => ['ID'],
            'filter' => ['USER_ID' => $userId, 'IS_ACTIVE' => 'Y']
        ])->fetch();

        if ($courier) {
            static::changeStatus($courier['ID'], 'ONLINE', $userId);
        }
    }

    /**
     * Обработка выхода пользователя из системы
     */
    public static function onUserLogout($userId)
    {
        // Проверяем, является ли пользователь курьером
        $courier = static::getList([
            'select' => ['ID'],
            'filter' => ['USER_ID' => $userId, 'IS_ACTIVE' => 'Y']
        ])->fetch();

        if ($courier) {
            static::changeStatus($courier['ID'], 'OFFLINE', $userId);
        }
    }
}