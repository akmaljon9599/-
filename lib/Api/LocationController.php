<?php

namespace CourierService\Api;

use Bitrix\Main\Web\Json;
use CourierService\Service\LocationService;

class LocationController extends BaseController
{
    private $locationService;

    public function __construct()
    {
        parent::__construct();
        $this->locationService = new LocationService();
    }

    public function updateAction()
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $data = Json::decode($request->getInput());

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $accuracy = $data['accuracy'] ?? null;

        if (!$latitude || !$longitude) {
            $this->sendErrorResponse('Latitude and longitude are required', 400);
            return;
        }

        $courierId = $this->currentUser['ID'];
        $result = $this->locationService->updateCourierLocation($courierId, $latitude, $longitude, $accuracy);

        if ($result['success']) {
            $this->sendSuccessResponse($result['data'], 'Location updated successfully');
        } else {
            $this->sendErrorResponse($result['message'], 500, $result['errors'] ?? []);
        }
    }

    public function getLastAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $courierId = $this->currentUser['ID'];
        $location = $this->locationService->getCourierLastLocation($courierId);

        if ($location) {
            $this->sendSuccessResponse($location);
        } else {
            $this->sendErrorResponse('Location not found', 404);
        }
    }

    public function getHistoryAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $limit = (int)$request->get('limit') ?: 100;

        $courierId = $this->currentUser['ID'];
        $locations = $this->locationService->getCourierLocations($courierId, $limit);

        $this->sendSuccessResponse(['locations' => $locations]);
    }

    public function getAllCouriersAction()
    {
        if (!$this->validatePermission('read', 'couriers')) {
            return;
        }

        $couriersLocations = $this->locationService->getAllActiveCouriersLocations();
        $this->sendSuccessResponse(['couriers' => $couriersLocations]);
    }

    public function getCouriersInRadiusAction()
    {
        if (!$this->validatePermission('read', 'couriers')) {
            return;
        }

        $request = $this->getRequest();
        $latitude = (float)$request->get('latitude');
        $longitude = (float)$request->get('longitude');
        $radius = (float)$request->get('radius') ?: 5;

        if (!$latitude || !$longitude) {
            $this->sendErrorResponse('Latitude and longitude are required', 400);
            return;
        }

        $couriers = $this->locationService->getCouriersInRadius($latitude, $longitude, $radius);
        $this->sendSuccessResponse(['couriers' => $couriers]);
    }

    public function getRouteAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $fromLat = (float)$request->get('from_lat');
        $fromLon = (float)$request->get('from_lon');
        $toLat = (float)$request->get('to_lat');
        $toLon = (float)$request->get('to_lon');

        if (!$fromLat || !$fromLon || !$toLat || !$toLon) {
            $this->sendErrorResponse('All coordinates are required', 400);
            return;
        }

        $result = $this->locationService->getRoute($fromLat, $fromLon, $toLat, $toLon);

        if ($result['success']) {
            $this->sendSuccessResponse($result['data']);
        } else {
            $this->sendErrorResponse($result['message'], 500);
        }
    }

    public function geocodeAction()
    {
        if (!$this->validatePermission('read', 'requests')) {
            return;
        }

        $request = $this->getRequest();
        $address = $request->get('address');
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');

        if ($address) {
            // Геокодирование адреса в координаты
            $coordinates = $this->locationService->getCoordinatesByAddress($address);
            if ($coordinates) {
                $this->sendSuccessResponse($coordinates);
            } else {
                $this->sendErrorResponse('Failed to geocode address', 500);
            }
        } elseif ($latitude && $longitude) {
            // Обратное геокодирование координат в адрес
            $address = $this->locationService->getAddressByCoordinates($latitude, $longitude);
            if ($address) {
                $this->sendSuccessResponse(['address' => $address]);
            } else {
                $this->sendErrorResponse('Failed to reverse geocode coordinates', 500);
            }
        } else {
            $this->sendErrorResponse('Address or coordinates are required', 400);
        }
    }

    public function startTrackingAction()
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $courierId = $this->currentUser['ID'];
        $this->locationService->startLocationTracking($courierId);

        $this->sendSuccessResponse([], 'Location tracking started');
    }

    public function stopTrackingAction()
    {
        if (!$this->validatePermission('update', 'requests')) {
            return;
        }

        $courierId = $this->currentUser['ID'];
        $this->locationService->stopLocationTracking($courierId);

        $this->sendSuccessResponse([], 'Location tracking stopped');
    }
}