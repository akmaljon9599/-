<?php
namespace Courier\Delivery\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Courier\Delivery\Util\Logger;

/**
 * Класс для интеграции с Яндекс.Картами API
 */
class YandexMaps
{
    private $apiKey;
    private $httpClient;
    private $baseUrl = 'https://api-maps.yandex.ru';

    public function __construct()
    {
        $this->apiKey = Option::get('courier.delivery', 'yandex_maps_api_key', '');
        
        $this->httpClient = new HttpClient([
            'timeout' => 15,
            'socketTimeout' => 15,
            'streamTimeout' => 15
        ]);
    }

    /**
     * Геокодирование адреса (получение координат по адресу)
     */
    public function geocode($address)
    {
        try {
            $url = $this->baseUrl . '/1.x/';
            $params = [
                'apikey' => $this->apiKey,
                'geocode' => $address,
                'format' => 'json',
                'results' => 1,
                'lang' => 'ru_RU'
            ];

            $response = $this->httpClient->get($url . '?' . http_build_query($params));
            $data = Json::decode($response);

            if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                $geoObject = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'];
                $coordinates = explode(' ', $geoObject['Point']['pos']);
                
                return [
                    'success' => true,
                    'data' => [
                        'latitude' => (float)$coordinates[1],
                        'longitude' => (float)$coordinates[0],
                        'formatted_address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'],
                        'precision' => $geoObject['metaDataProperty']['GeocoderMetaData']['precision']
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Адрес не найден'
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (geocode): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка геокодирования адреса'
            ];
        }
    }

    /**
     * Обратное геокодирование (получение адреса по координатам)
     */
    public function reverseGeocode($latitude, $longitude)
    {
        try {
            $url = $this->baseUrl . '/1.x/';
            $params = [
                'apikey' => $this->apiKey,
                'geocode' => $longitude . ',' . $latitude,
                'format' => 'json',
                'results' => 1,
                'lang' => 'ru_RU'
            ];

            $response = $this->httpClient->get($url . '?' . http_build_query($params));
            $data = Json::decode($response);

            if (isset($data['response']['GeoObjectCollection']['featureMember'][0])) {
                $geoObject = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'];
                
                return [
                    'success' => true,
                    'data' => [
                        'address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'],
                        'components' => $this->parseAddressComponents($geoObject)
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Адрес не найден'
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (reverseGeocode): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка определения адреса'
            ];
        }
    }

    /**
     * Построение маршрута между точками
     */
    public function buildRoute($fromLatitude, $fromLongitude, $toLatitude, $toLongitude, $mode = 'driving')
    {
        try {
            $url = 'https://api.routing.yandex.net/v2/route';
            $params = [
                'apikey' => $this->apiKey,
                'waypoints' => $fromLongitude . ',' . $fromLatitude . '|' . $toLongitude . ',' . $toLatitude,
                'mode' => $mode,
                'format' => 'json'
            ];

            $response = $this->httpClient->get($url . '?' . http_build_query($params));
            $data = Json::decode($response);

            if (isset($data['route'])) {
                $route = $data['route'];
                
                return [
                    'success' => true,
                    'data' => [
                        'distance' => $route['distance']['value'], // в метрах
                        'duration' => $route['duration']['value'], // в секундах
                        'distance_text' => $route['distance']['text'],
                        'duration_text' => $route['duration']['text'],
                        'polyline' => $route['legs'][0]['steps'] ?? [],
                        'bounds' => $route['boundedBy'] ?? null
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Не удалось построить маршрут'
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (buildRoute): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка построения маршрута'
            ];
        }
    }

    /**
     * Получить расстояние и время поездки между точками
     */
    public function getDistanceMatrix($origins, $destinations, $mode = 'driving')
    {
        try {
            $url = 'https://api.routing.yandex.net/v2/distancematrix';
            
            $originsStr = [];
            foreach ($origins as $origin) {
                $originsStr[] = $origin['longitude'] . ',' . $origin['latitude'];
            }
            
            $destinationsStr = [];
            foreach ($destinations as $destination) {
                $destinationsStr[] = $destination['longitude'] . ',' . $destination['latitude'];
            }

            $params = [
                'apikey' => $this->apiKey,
                'origins' => implode('|', $originsStr),
                'destinations' => implode('|', $destinationsStr),
                'mode' => $mode,
                'format' => 'json'
            ];

            $response = $this->httpClient->get($url . '?' . http_build_query($params));
            $data = Json::decode($response);

            if (isset($data['rows'])) {
                return [
                    'success' => true,
                    'data' => $data['rows']
                ];
            }

            return [
                'success' => false,
                'error' => 'Не удалось получить матрицу расстояний'
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (getDistanceMatrix): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка получения матрицы расстояний'
            ];
        }
    }

    /**
     * Найти ближайшие объекты (организации, адреса)
     */
    public function searchNearby($latitude, $longitude, $query, $radius = 1000, $limit = 10)
    {
        try {
            $url = 'https://search-maps.yandex.ru/v1/';
            $params = [
                'apikey' => $this->apiKey,
                'text' => $query,
                'lang' => 'ru_RU',
                'format' => 'json',
                'll' => $longitude . ',' . $latitude,
                'spn' => '0.01,0.01',
                'results' => $limit
            ];

            $response = $this->httpClient->get($url . '?' . http_build_query($params));
            $data = Json::decode($response);

            if (isset($data['features'])) {
                $results = [];
                
                foreach ($data['features'] as $feature) {
                    $coordinates = $feature['geometry']['coordinates'];
                    
                    $results[] = [
                        'name' => $feature['properties']['name'] ?? '',
                        'description' => $feature['properties']['description'] ?? '',
                        'address' => $feature['properties']['CompanyMetaData']['address'] ?? '',
                        'latitude' => $coordinates[1],
                        'longitude' => $coordinates[0],
                        'distance' => $this->calculateDistance(
                            $latitude, $longitude,
                            $coordinates[1], $coordinates[0]
                        )
                    ];
                }

                // Сортируем по расстоянию
                usort($results, function($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });

                return [
                    'success' => true,
                    'data' => $results
                ];
            }

            return [
                'success' => false,
                'error' => 'Объекты не найдены'
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (searchNearby): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка поиска объектов'
            ];
        }
    }

    /**
     * Получить статическую карту
     */
    public function getStaticMap($latitude, $longitude, $zoom = 15, $width = 600, $height = 400, $markers = [])
    {
        try {
            $url = 'https://static-maps.yandex.ru/1.x/';
            $params = [
                'll' => $longitude . ',' . $latitude,
                'z' => $zoom,
                'size' => $width . ',' . $height,
                'l' => 'map',
                'format' => 'png'
            ];

            // Добавляем маркеры
            if (!empty($markers)) {
                $pts = [];
                foreach ($markers as $marker) {
                    $pt = $marker['longitude'] . ',' . $marker['latitude'];
                    if (isset($marker['color'])) {
                        $pt .= ',pm2' . $marker['color'];
                    }
                    $pts[] = $pt;
                }
                $params['pt'] = implode('~', $pts);
            }

            $mapUrl = $url . '?' . http_build_query($params);

            return [
                'success' => true,
                'data' => [
                    'url' => $mapUrl,
                    'width' => $width,
                    'height' => $height
                ]
            ];

        } catch (\Exception $e) {
            Logger::log('Yandex Maps API Error (getStaticMap): ' . $e->getMessage(), 'YANDEX_API_ERROR');
            
            return [
                'success' => false,
                'error' => 'Ошибка создания статической карты'
            ];
        }
    }

    /**
     * Парсинг компонентов адреса
     */
    private function parseAddressComponents($geoObject)
    {
        $components = [];
        
        if (isset($geoObject['metaDataProperty']['GeocoderMetaData']['Address']['Components'])) {
            foreach ($geoObject['metaDataProperty']['GeocoderMetaData']['Address']['Components'] as $component) {
                $components[$component['kind']] = $component['name'];
            }
        }

        return $components;
    }

    /**
     * Вычисление расстояния между двумя точками
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Радиус Земли в метрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Проверить валидность API ключа
     */
    public function testApiKey()
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API ключ не установлен'
            ];
        }

        // Тестируем ключ простым запросом геокодирования
        $result = $this->geocode('Москва, Красная площадь');
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'API ключ работает корректно'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API ключ недействителен или превышен лимит запросов'
            ];
        }
    }

    /**
     * Получить конфигурацию API
     */
    public function getApiConfig()
    {
        return [
            'has_key' => !empty($this->apiKey),
            'key_length' => strlen($this->apiKey),
            'base_url' => $this->baseUrl
        ];
    }
}