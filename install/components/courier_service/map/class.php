<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use CourierService\Service\LocationService;

class CourierServiceMapComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['API_KEY'] = $arParams['API_KEY'] ?: Option::get('courier_service', 'yandex_maps_api_key', '');
        $arParams['WIDTH'] = $arParams['WIDTH'] ?: '100%';
        $arParams['HEIGHT'] = $arParams['HEIGHT'] ?: '400px';
        $arParams['ZOOM'] = (int)$arParams['ZOOM'] ?: 10;
        $arParams['CENTER_LAT'] = (float)$arParams['CENTER_LAT'] ?: 55.7558;
        $arParams['CENTER_LON'] = (float)$arParams['CENTER_LON'] ?: 37.6176;
        $arParams['SHOW_COURIERS'] = $arParams['SHOW_COURIERS'] !== 'N';
        $arParams['SHOW_TRAFFIC'] = $arParams['SHOW_TRAFFIC'] !== 'N';
        $arParams['SHOW_ROUTES'] = $arParams['SHOW_ROUTES'] !== 'N';
        $arParams['AUTO_UPDATE'] = $arParams['AUTO_UPDATE'] !== 'N';
        $arParams['UPDATE_INTERVAL'] = (int)$arParams['UPDATE_INTERVAL'] ?: 30;

        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('courier_service')) {
            $this->abortResultCache();
            ShowError('Модуль курьерской службы не установлен');
            return;
        }

        if ($this->startResultCache()) {
            $this->arResult = $this->prepareData();
            $this->includeComponentTemplate();
        }
    }

    private function prepareData()
    {
        $locationService = new LocationService();
        
        $result = [
            'API_KEY' => $this->arParams['API_KEY'],
            'WIDTH' => $this->arParams['WIDTH'],
            'HEIGHT' => $this->arParams['HEIGHT'],
            'ZOOM' => $this->arParams['ZOOM'],
            'CENTER' => [
                'lat' => $this->arParams['CENTER_LAT'],
                'lon' => $this->arParams['CENTER_LON']
            ],
            'SHOW_COURIERS' => $this->arParams['SHOW_COURIERS'],
            'SHOW_TRAFFIC' => $this->arParams['SHOW_TRAFFIC'],
            'SHOW_ROUTES' => $this->arParams['SHOW_ROUTES'],
            'AUTO_UPDATE' => $this->arParams['AUTO_UPDATE'],
            'UPDATE_INTERVAL' => $this->arParams['UPDATE_INTERVAL'],
            'COURIERS' => [],
            'BRANCHES' => []
        ];

        if ($this->arParams['SHOW_COURIERS']) {
            $result['COURIERS'] = $locationService->getAllActiveCouriersLocations();
        }

        // Получаем филиалы
        $branches = \CourierService\Entity\BranchTable::getList([
            'filter' => ['IS_ACTIVE' => true],
            'select' => ['*']
        ])->fetchAll();

        foreach ($branches as $branch) {
            $coordinates = null;
            if ($branch['COORDINATES']) {
                $coords = explode(',', $branch['COORDINATES']);
                if (count($coords) === 2) {
                    $coordinates = [
                        'lat' => (float)trim($coords[0]),
                        'lon' => (float)trim($coords[1])
                    ];
                }
            }

            $result['BRANCHES'][] = [
                'id' => $branch['ID'],
                'name' => $branch['NAME'],
                'address' => $branch['ADDRESS'],
                'coordinates' => $coordinates
            ];
        }

        return $result;
    }
}