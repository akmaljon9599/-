<?php

namespace CourierService\Service;

use Bitrix\Main\Config\Option;
use CourierService\Entity\LocationTable;
use CourierService\Entity\UserTable;
use CourierService\Service\NotificationService;

class LocationService
{
    private $yandexMapsApiKey;
    private $updateInterval;

    public function __construct()
    {
        $this->yandexMapsApiKey = Option::get('courier_service', 'yandex_maps_api_key', '');
        $this->updateInterval = (int)Option::get('courier_service', 'location_update_interval', 60);
    }

    public function updateCourierLocation($courierId, $latitude, $longitude, $accuracy = null)
    {
        try {
            // Получаем адрес по координатам
            $address = $this->getAddressByCoordinates($latitude, $longitude);

            $locationData = [
                'COURIER_ID' => $courierId,
                'LATITUDE' => $latitude,
                'LONGITUDE' => $longitude,
                'ACCURACY' => $accuracy,
                'ADDRESS' => $address,
                'CREATED_AT' => new \Bitrix\Main\Type\DateTime()
            ];

            $result = LocationTable::add($locationData);
            if ($result->isSuccess()) {
                // Отправляем уведомление о обновлении местоположения
                NotificationService::sendLocationUpdateNotification($courierId, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $address,
                    'accuracy' => $accuracy
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'id' => $result->getId(),
                        'address' => $address
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to save location',
                    'errors' => $result->getErrorMessages()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Location update failed: ' . $e->getMessage()
            ];
        }
    }

    public function getCourierLastLocation($courierId)
    {
        $result = LocationTable::getList([
            'filter' => ['COURIER_ID' => $courierId],
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => 1
        ]);

        $location = $result->fetch();
        if ($location) {
            return [
                'latitude' => $location['LATITUDE'],
                'longitude' => $location['LONGITUDE'],
                'address' => $location['ADDRESS'],
                'accuracy' => $location['ACCURACY'],
                'created_at' => $location['CREATED_AT']->format('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    public function getCourierLocations($courierId, $limit = 100)
    {
        $result = LocationTable::getList([
            'filter' => ['COURIER_ID' => $courierId],
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => $limit
        ]);

        $locations = [];
        while ($row = $result->fetch()) {
            $locations[] = [
                'latitude' => $row['LATITUDE'],
                'longitude' => $row['LONGITUDE'],
                'address' => $row['ADDRESS'],
                'accuracy' => $row['ACCURACY'],
                'created_at' => $row['CREATED_AT']->format('Y-m-d H:i:s')
            ];
        }

        return $locations;
    }

    public function getAllActiveCouriersLocations()
    {
        // Получаем всех активных курьеров
        $couriers = UserTable::getList([
            'filter' => [
                'ROLE' => 'courier',
                'IS_ACTIVE' => true
            ],
            'select' => ['ID', 'USER_ID']
        ]);

        $couriersLocations = [];
        while ($courier = $couriers->fetch()) {
            $lastLocation = $this->getCourierLastLocation($courier['ID']);
            if ($lastLocation) {
                $couriersLocations[] = [
                    'courier_id' => $courier['ID'],
                    'user_id' => $courier['USER_ID'],
                    'location' => $lastLocation
                ];
            }
        }

        return $couriersLocations;
    }

    public function getCouriersInRadius($latitude, $longitude, $radiusKm = 5)
    {
        $couriers = $this->getAllActiveCouriersLocations();
        $couriersInRadius = [];

        foreach ($couriers as $courier) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $courier['location']['latitude'],
                $courier['location']['longitude']
            );

            if ($distance <= $radiusKm) {
                $courier['distance'] = $distance;
                $couriersInRadius[] = $courier;
            }
        }

        // Сортируем по расстоянию
        usort($couriersInRadius, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $couriersInRadius;
    }

    public function getRoute($fromLat, $fromLon, $toLat, $toLon)
    {
        if (!$this->yandexMapsApiKey) {
            return [
                'success' => false,
                'message' => 'Yandex Maps API key not configured'
            ];
        }

        $url = "https://api.routing.yandex.net/v2/route";
        $params = [
            'waypoints' => "{$fromLon},{$fromLat}|{$toLon},{$toLat}",
            'mode' => 'driving',
            'apikey' => $this->yandexMapsApiKey
        ];

        $response = $this->makeHttpRequest($url, $params);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['route']['legs'][0])) {
                $leg = $data['route']['legs'][0];
                return [
                    'success' => true,
                    'data' => [
                        'distance' => $leg['distance']['value'], // в метрах
                        'duration' => $leg['duration']['value'], // в секундах
                        'geometry' => $leg['geometry']
                    ]
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to get route'
        ];
    }

    public function getAddressByCoordinates($latitude, $longitude)
    {
        if (!$this->yandexMapsApiKey) {
            return null;
        }

        $url = "https://geocode-maps.yandex.ru/1.x/";
        $params = [
            'geocode' => "{$longitude},{$latitude}",
            'apikey' => $this->yandexMapsApiKey,
            'format' => 'json',
            'results' => 1
        ];

        $response = $this->makeHttpRequest($url, $params);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                $feature = $data['response']['GeoObjectCollection']['featureMember'][0];
                return $feature['GeoObject']['metaDataProperty']['GeocoderMetaData']['text'];
            }
        }

        return null;
    }

    public function getCoordinatesByAddress($address)
    {
        if (!$this->yandexMapsApiKey) {
            return null;
        }

        $url = "https://geocode-maps.yandex.ru/1.x/";
        $params = [
            'geocode' => $address,
            'apikey' => $this->yandexMapsApiKey,
            'format' => 'json',
            'results' => 1
        ];

        $response = $this->makeHttpRequest($url, $params);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                $feature = $data['response']['GeoObjectCollection']['featureMember'][0];
                $coordinates = $feature['GeoObject']['Point']['pos'];
                list($longitude, $latitude) = explode(' ', $coordinates);
                
                return [
                    'latitude' => (float)$latitude,
                    'longitude' => (float)$longitude
                ];
            }
        }

        return null;
    }

    public function startLocationTracking($courierId)
    {
        // Запускаем фоновое отслеживание местоположения
        // В реальном проекте это может быть реализовано через cron или очереди
        $this->scheduleLocationUpdate($courierId);
    }

    public function stopLocationTracking($courierId)
    {
        // Останавливаем отслеживание местоположения
        // Удаляем запланированные задачи
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Радиус Земли в километрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    private function makeHttpRequest($url, $params = [])
    {
        $ch = curl_init();
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: CourierService/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return $response;
        }

        return false;
    }

    private function scheduleLocationUpdate($courierId)
    {
        // Здесь должна быть логика планирования обновления местоположения
        // Например, через cron или систему очередей
    }
}