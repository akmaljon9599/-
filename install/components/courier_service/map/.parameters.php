<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => [
            'NAME' => 'Настройки карты',
            'SORT' => 100
        ],
        'COURIERS' => [
            'NAME' => 'Настройки курьеров',
            'SORT' => 200
        ],
        'BRANCHES' => [
            'NAME' => 'Настройки филиалов',
            'SORT' => 300
        ]
    ],
    'PARAMETERS' => [
        'API_KEY' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'API ключ Яндекс.Карт',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
            'COLS' => 50
        ],
        'WIDTH' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Ширина карты',
            'TYPE' => 'STRING',
            'DEFAULT' => '100%',
            'COLS' => 20
        ],
        'HEIGHT' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Высота карты',
            'TYPE' => 'STRING',
            'DEFAULT' => '400px',
            'COLS' => 20
        ],
        'ZOOM' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Масштаб карты',
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
            'COLS' => 5
        ],
        'CENTER_LAT' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Широта центра карты',
            'TYPE' => 'STRING',
            'DEFAULT' => '55.7558',
            'COLS' => 20
        ],
        'CENTER_LON' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Долгота центра карты',
            'TYPE' => 'STRING',
            'DEFAULT' => '37.6176',
            'COLS' => 20
        ],
        'SHOW_COURIERS' => [
            'PARENT' => 'COURIERS',
            'NAME' => 'Показывать курьеров',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y'
        ],
        'SHOW_TRAFFIC' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Показывать пробки',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y'
        ],
        'SHOW_ROUTES' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Показывать маршруты',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y'
        ],
        'AUTO_UPDATE' => [
            'PARENT' => 'COURIERS',
            'NAME' => 'Автообновление',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y'
        ],
        'UPDATE_INTERVAL' => [
            'PARENT' => 'COURIERS',
            'NAME' => 'Интервал обновления (секунды)',
            'TYPE' => 'STRING',
            'DEFAULT' => '30',
            'COLS' => 5
        ]
    ]
];