<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use CourierService\Service\AuthService;
use CourierService\Service\LocationService;

class CourierServiceMobileAppComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['API_KEY'] = $arParams['API_KEY'] ?: Option::get('courier_service', 'yandex_maps_api_key', '');
        $arParams['UPDATE_INTERVAL'] = (int)$arParams['UPDATE_INTERVAL'] ?: 60;
        $arParams['REQUIRE_AUTH'] = $arParams['REQUIRE_AUTH'] !== 'N';

        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('courier_service')) {
            $this->abortResultCache();
            ShowError('Модуль курьерской службы не установлен');
            return;
        }

        $authService = new AuthService();
        
        if ($this->arParams['REQUIRE_AUTH'] && !$authService->isAuthenticated()) {
            $this->abortResultCache();
            ShowError('Требуется авторизация');
            return;
        }

        if ($this->startResultCache()) {
            $this->arResult = $this->prepareData();
            $this->includeComponentTemplate();
        }
    }

    private function prepareData()
    {
        $authService = new AuthService();
        $locationService = new LocationService();
        
        $currentUser = $authService->getCurrentUser();
        
        $result = [
            'API_KEY' => $this->arParams['API_KEY'],
            'UPDATE_INTERVAL' => $this->arParams['UPDATE_INTERVAL'],
            'USER' => $currentUser,
            'REQUESTS' => [],
            'LAST_LOCATION' => null
        ];

        if ($currentUser && $currentUser['ROLE'] === 'courier') {
            // Получаем заявки курьера
            $requests = \CourierService\Entity\RequestTable::getList([
                'filter' => [
                    'COURIER_ID' => $currentUser['ID'],
                    'STATUS' => ['waiting_delivery', 'in_delivery']
                ],
                'order' => ['REGISTRATION_DATE' => 'ASC']
            ])->fetchAll();

            foreach ($requests as $request) {
                $result['REQUESTS'][] = [
                    'id' => $request['ID'],
                    'request_number' => $request['REQUEST_NUMBER'],
                    'client_name' => $request['CLIENT_NAME'],
                    'client_phone' => $request['CLIENT_PHONE'],
                    'client_address' => $request['CLIENT_ADDRESS'],
                    'status' => $request['STATUS'],
                    'status_text' => \CourierService\Entity\RequestTable::getStatuses()[$request['STATUS']] ?? $request['STATUS'],
                    'registration_date' => $request['REGISTRATION_DATE']->format('Y-m-d H:i:s'),
                    'coordinates' => $locationService->getCoordinatesByAddress($request['CLIENT_ADDRESS'])
                ];
            }

            // Получаем последнее местоположение курьера
            $result['LAST_LOCATION'] = $locationService->getCourierLastLocation($currentUser['ID']);
        }

        return $result;
    }
}