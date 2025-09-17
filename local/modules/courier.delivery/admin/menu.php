<?php
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Loader;

if (!Loader::includeModule('courier.delivery')) {
    return [];
}

// Проверяем права доступа
global $USER;
if (!$USER->IsAdmin() && !$USER->CanDoOperation('courier_delivery_view')) {
    return [];
}

return [
    'parent_menu' => 'global_menu_services',
    'section' => 'courier_delivery',
    'sort' => 1000,
    'text' => 'Курьерская служба',
    'title' => 'Управление курьерской доставкой',
    'icon' => 'courier_delivery_menu_icon',
    'page_icon' => 'courier_delivery_page_icon',
    'items_id' => 'courier_delivery_items',
    'items' => [
        [
            'text' => 'Заявки',
            'title' => 'Управление заявками на доставку',
            'url' => 'courier_delivery_requests.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_requests_icon',
            'page_icon' => 'courier_delivery_requests_page_icon',
            'items_id' => 'courier_delivery_requests_items',
            'items' => [
                [
                    'text' => 'Все заявки',
                    'url' => 'courier_delivery_requests.php?lang=' . LANGUAGE_ID,
                    'title' => 'Просмотр всех заявок'
                ],
                [
                    'text' => 'Новые заявки',
                    'url' => 'courier_delivery_requests.php?status=NEW&lang=' . LANGUAGE_ID,
                    'title' => 'Новые заявки'
                ],
                [
                    'text' => 'В доставке',
                    'url' => 'courier_delivery_requests.php?status=IN_DELIVERY&lang=' . LANGUAGE_ID,
                    'title' => 'Заявки в доставке'
                ],
                [
                    'text' => 'Добавить заявку',
                    'url' => 'courier_delivery_request_edit.php?lang=' . LANGUAGE_ID,
                    'title' => 'Добавить новую заявку'
                ]
            ]
        ],
        [
            'text' => 'Курьеры',
            'title' => 'Управление курьерами',
            'url' => 'courier_delivery_couriers.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_couriers_icon',
            'page_icon' => 'courier_delivery_couriers_page_icon',
            'items_id' => 'courier_delivery_couriers_items',
            'items' => [
                [
                    'text' => 'Список курьеров',
                    'url' => 'courier_delivery_couriers.php?lang=' . LANGUAGE_ID,
                    'title' => 'Просмотр всех курьеров'
                ],
                [
                    'text' => 'Активные курьеры',
                    'url' => 'courier_delivery_couriers.php?status=ONLINE&lang=' . LANGUAGE_ID,
                    'title' => 'Активные курьеры'
                ],
                [
                    'text' => 'Добавить курьера',
                    'url' => 'courier_delivery_courier_edit.php?lang=' . LANGUAGE_ID,
                    'title' => 'Добавить нового курьера'
                ]
            ]
        ],
        [
            'text' => 'Карта',
            'title' => 'Отслеживание на карте',
            'url' => 'courier_delivery_map.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_map_icon',
            'page_icon' => 'courier_delivery_map_page_icon'
        ],
        [
            'text' => 'Документы',
            'title' => 'Управление документами',
            'url' => 'courier_delivery_documents.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_documents_icon',
            'page_icon' => 'courier_delivery_documents_page_icon'
        ],
        [
            'text' => 'Отчеты',
            'title' => 'Отчеты и аналитика',
            'url' => 'courier_delivery_reports.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_reports_icon',
            'page_icon' => 'courier_delivery_reports_page_icon',
            'items_id' => 'courier_delivery_reports_items',
            'items' => [
                [
                    'text' => 'Общая статистика',
                    'url' => 'courier_delivery_reports.php?type=general&lang=' . LANGUAGE_ID,
                    'title' => 'Общая статистика по заявкам'
                ],
                [
                    'text' => 'По курьерам',
                    'url' => 'courier_delivery_reports.php?type=couriers&lang=' . LANGUAGE_ID,
                    'title' => 'Статистика по курьерам'
                ],
                [
                    'text' => 'По филиалам',
                    'url' => 'courier_delivery_reports.php?type=branches&lang=' . LANGUAGE_ID,
                    'title' => 'Статистика по филиалам'
                ],
                [
                    'text' => 'Экспорт данных',
                    'url' => 'courier_delivery_export.php?lang=' . LANGUAGE_ID,
                    'title' => 'Экспорт данных в Excel'
                ]
            ]
        ],
        [
            'text' => 'Настройки',
            'title' => 'Настройки модуля',
            'url' => 'courier_delivery_settings.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_delivery_settings_icon',
            'page_icon' => 'courier_delivery_settings_page_icon',
            'more_url' => [
                'courier_delivery_settings.php'
            ]
        ]
    ]
];