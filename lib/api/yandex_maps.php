<?php
namespace CourierService\Api;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Config\Option;

class YandexMaps
{
    private $httpClient;
    private $apiKey;
    private $baseUrl = 'https://geocode-maps.yandex.ru/1.x/';

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->apiKey = Option::get('courier_service', 'yandex_maps_api_key', '');
        $this->httpClient->setTimeout(10);
    }

    /**
     * Геокодирование адреса
     */
    public function geocodeAddress($address)
    {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'geocode' => $address,
                'format' => 'json',
                'results' => 1
            ];

            $url = $this->baseUrl . '?' . http_build_query($params);
            $response = $this->httpClient->get($url);
            
            if ($this->httpClient->getStatus() === 200) {
                $data = json_decode($response, true);
                
                if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                    $geoObject = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'];
                    $coordinates = explode(' ', $geoObject['Point']['pos']);
                    
                    return [
                        'latitude' => (float)$coordinates[1],
                        'longitude' => (float)$coordinates[0],
                        'address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'],
                        'precision' => $geoObject['metaDataProperty']['GeocoderMetaData']['precision']
                    ];
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Yandex Maps', 'geocodeAddress', $e->getMessage());
            return false;
        }
    }

    /**
     * Обратное геокодирование координат
     */
    public function reverseGeocode($latitude, $longitude)
    {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'geocode' => $longitude . ',' . $latitude,
                'format' => 'json',
                'results' => 1
            ];

            $url = $this->baseUrl . '?' . http_build_query($params);
            $response = $this->httpClient->get($url);
            
            if ($this->httpClient->getStatus() === 200) {
                $data = json_decode($response, true);
                
                if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                    $geoObject = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'];
                    
                    return [
                        'address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'],
                        'components' => $geoObject['metaDataProperty']['GeocoderMetaData']['Address']['Components']
                    ];
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Yandex Maps', 'reverseGeocode', $e->getMessage());
            return false;
        }
    }

    /**
     * Построение маршрута между точками
     */
    public function buildRoute($fromLat, $fromLon, $toLat, $toLon, $mode = 'driving')
    {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'waypoints' => $fromLon . ',' . $fromLat . '|' . $toLon . ',' . $toLat,
                'mode' => $mode,
                'format' => 'json'
            ];

            $url = 'https://api.routing.yandex.net/v2/route?' . http_build_query($params);
            $response = $this->httpClient->get($url);
            
            if ($this->httpClient->getStatus() === 200) {
                $data = json_decode($response, true);
                
                if (isset($data['route']['legs'][0])) {
                    $leg = $data['route']['legs'][0];
                    
                    return [
                        'distance' => $leg['distance']['value'], // в метрах
                        'duration' => $leg['duration']['value'], // в секундах
                        'geometry' => $leg['geometry']
                    ];
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Yandex Maps', 'buildRoute', $e->getMessage());
            return false;
        }
    }

    /**
     * Поиск ближайших объектов
     */
    public function searchNearby($latitude, $longitude, $type = 'all', $radius = 1000)
    {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'text' => $type,
                'll' => $longitude . ',' . $latitude,
                'spn' => '0.01,0.01',
                'results' => 10,
                'format' => 'json'
            ];

            $url = 'https://search-maps.yandex.ru/v1/?' . http_build_query($params);
            $response = $this->httpClient->get($url);
            
            if ($this->httpClient->getStatus() === 200) {
                $data = json_decode($response, true);
                
                if (isset($data['features'])) {
                    $results = [];
                    foreach ($data['features'] as $feature) {
                        $coordinates = $feature['geometry']['coordinates'];
                        $results[] = [
                            'name' => $feature['properties']['name'],
                            'latitude' => $coordinates[1],
                            'longitude' => $coordinates[0],
                            'address' => $feature['properties']['description'] ?? '',
                            'distance' => $this->calculateDistance($latitude, $longitude, $coordinates[1], $coordinates[0])
                        ];
                    }
                    
                    // Сортируем по расстоянию
                    usort($results, function($a, $b) {
                        return $a['distance'] <=> $b['distance'];
                    });
                    
                    return $results;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \CourierService\Security\AuditLogger::logError('Yandex Maps', 'searchNearby', $e->getMessage());
            return false;
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
     * Получение JavaScript кода для инициализации карты
     */
    public function getMapInitScript($containerId, $options = [])
    {
        $defaultOptions = [
            'center' => [55.7558, 37.6176], // Москва по умолчанию
            'zoom' => 10,
            'controls' => ['zoomControl', 'fullscreenControl'],
            'behaviors' => ['drag', 'scrollZoom', 'dblClickZoom']
        ];

        $options = array_merge($defaultOptions, $options);
        
        return "
        <script src='https://api-maps.yandex.ru/2.1/?apikey={$this->apiKey}&lang=ru_RU' type='text/javascript'></script>
        <script>
            ymaps.ready(function () {
                var map = new ymaps.Map('{$containerId}', {
                    center: [{$options['center'][0]}, {$options['center'][1]}],
                    zoom: {$options['zoom']},
                    controls: " . json_encode($options['controls']) . ",
                    behaviors: " . json_encode($options['behaviors']) . "
                });
                
                // Глобальная переменная для доступа к карте
                window.courierMap = map;
            });
        </script>";
    }
}