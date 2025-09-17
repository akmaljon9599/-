<?php
namespace CourierService\Security;

use Bitrix\Main\Loader;

class PermissionManager
{
    private static $permissions = [
        'COURIER_ADMIN' => [
            'requests' => ['view', 'create', 'edit', 'delete', 'export'],
            'couriers' => ['view', 'create', 'edit', 'delete', 'manage'],
            'branches' => ['view', 'create', 'edit', 'delete'],
            'departments' => ['view', 'create', 'edit', 'delete'],
            'reports' => ['view', 'export'],
            'settings' => ['view', 'edit'],
            'logs' => ['view'],
            'documents' => ['view', 'create', 'edit', 'delete', 'download']
        ],
        'COURIER_SENIOR' => [
            'requests' => ['view', 'create', 'edit', 'assign', 'export'],
            'couriers' => ['view', 'edit', 'manage'],
            'branches' => ['view'],
            'departments' => ['view'],
            'reports' => ['view', 'export'],
            'settings' => ['view'],
            'logs' => ['view'],
            'documents' => ['view', 'create', 'edit', 'download']
        ],
        'COURIER_DELIVERY' => [
            'requests' => ['view', 'edit_status', 'upload_photos'],
            'couriers' => ['view_own'],
            'branches' => ['view'],
            'departments' => ['view'],
            'reports' => ['view_own'],
            'settings' => [],
            'logs' => [],
            'documents' => ['view', 'upload']
        ],
        'COURIER_OPERATOR' => [
            'requests' => ['view', 'create', 'edit'],
            'couriers' => ['view'],
            'branches' => ['view'],
            'departments' => ['view'],
            'reports' => ['view'],
            'settings' => [],
            'logs' => [],
            'documents' => ['view', 'create']
        ]
    ];

    /**
     * Проверка прав доступа
     */
    public static function checkPermission($module, $action, $userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        $userGroups = self::getUserGroups($userId);
        
        foreach ($userGroups as $groupCode) {
            if (isset(self::$permissions[$groupCode][$module])) {
                if (in_array($action, self::$permissions[$groupCode][$module])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Получение групп пользователя
     */
    public static function getUserGroups($userId)
    {
        $groups = [];
        
        if (Loader::includeModule('main')) {
            $user = new \CUser();
            $userGroups = $user->GetUserGroup($userId);
            
            foreach ($userGroups as $groupId) {
                $group = \CGroup::GetByID($groupId)->Fetch();
                if ($group && isset($group['STRING_ID'])) {
                    $groups[] = $group['STRING_ID'];
                }
            }
        }

        return $groups;
    }

    /**
     * Получение роли пользователя
     */
    public static function getUserRole($userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        $userGroups = self::getUserGroups($userId);
        
        // Определяем приоритет ролей
        $rolePriority = [
            'COURIER_ADMIN' => 4,
            'COURIER_SENIOR' => 3,
            'COURIER_DELIVERY' => 2,
            'COURIER_OPERATOR' => 1
        ];

        $highestRole = null;
        $highestPriority = 0;

        foreach ($userGroups as $groupCode) {
            if (isset($rolePriority[$groupCode]) && $rolePriority[$groupCode] > $highestPriority) {
                $highestPriority = $rolePriority[$groupCode];
                $highestRole = $groupCode;
            }
        }

        return $highestRole;
    }

    /**
     * Получение доступных действий для модуля
     */
    public static function getAvailableActions($module, $userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        $userGroups = self::getUserGroups($userId);
        $availableActions = [];

        foreach ($userGroups as $groupCode) {
            if (isset(self::$permissions[$groupCode][$module])) {
                $availableActions = array_merge($availableActions, self::$permissions[$groupCode][$module]);
            }
        }

        return array_unique($availableActions);
    }

    /**
     * Проверка доступа к заявке
     */
    public static function checkRequestAccess($requestId, $action, $userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        // Администратор имеет доступ ко всем заявкам
        if (self::checkPermission('requests', $action, $userId)) {
            $userRole = self::getUserRole($userId);
            
            if ($userRole === 'COURIER_ADMIN') {
                return true;
            }

            // Получаем информацию о заявке
            $request = \CourierService\Main\RequestTable::getList([
                'filter' => ['ID' => $requestId],
                'select' => ['COURIER_ID', 'BRANCH_ID', 'DEPARTMENT_ID', 'CREATED_BY']
            ])->fetch();

            if (!$request) {
                return false;
            }

            // Старший курьер имеет доступ к заявкам своего филиала
            if ($userRole === 'COURIER_SENIOR') {
                $courier = \CourierService\Main\CourierTable::getList([
                    'filter' => ['USER_ID' => $userId],
                    'select' => ['BRANCH_ID']
                ])->fetch();

                if ($courier && $courier['BRANCH_ID'] == $request['BRANCH_ID']) {
                    return true;
                }
            }

            // Курьер имеет доступ только к своим заявкам
            if ($userRole === 'COURIER_DELIVERY') {
                $courier = \CourierService\Main\CourierTable::getList([
                    'filter' => ['USER_ID' => $userId],
                    'select' => ['ID']
                ])->fetch();

                if ($courier && $courier['ID'] == $request['COURIER_ID']) {
                    return true;
                }
            }

            // Оператор имеет доступ к заявкам, которые он создал
            if ($userRole === 'COURIER_OPERATOR' && $request['CREATED_BY'] == $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка доступа к курьеру
     */
    public static function checkCourierAccess($courierId, $action, $userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        if (!self::checkPermission('couriers', $action, $userId)) {
            return false;
        }

        $userRole = self::getUserRole($userId);

        // Администратор имеет доступ ко всем курьерам
        if ($userRole === 'COURIER_ADMIN') {
            return true;
        }

        // Получаем информацию о курьере
        $courier = \CourierService\Main\CourierTable::getList([
            'filter' => ['ID' => $courierId],
            'select' => ['USER_ID', 'BRANCH_ID']
        ])->fetch();

        if (!$courier) {
            return false;
        }

        // Старший курьер имеет доступ к курьерам своего филиала
        if ($userRole === 'COURIER_SENIOR') {
            $seniorCourier = \CourierService\Main\CourierTable::getList([
                'filter' => ['USER_ID' => $userId],
                'select' => ['BRANCH_ID']
            ])->fetch();

            if ($seniorCourier && $seniorCourier['BRANCH_ID'] == $courier['BRANCH_ID']) {
                return true;
            }
        }

        // Курьер имеет доступ только к своей записи
        if ($userRole === 'COURIER_DELIVERY' && $courier['USER_ID'] == $userId) {
            return true;
        }

        return false;
    }

    /**
     * Получение фильтра для запросов в зависимости от роли
     */
    public static function getRequestFilter($userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        $userRole = self::getUserRole($userId);
        $filter = [];

        switch ($userRole) {
            case 'COURIER_ADMIN':
                // Администратор видит все заявки
                break;

            case 'COURIER_SENIOR':
                // Старший курьер видит заявки своего филиала
                $courier = \CourierService\Main\CourierTable::getList([
                    'filter' => ['USER_ID' => $userId],
                    'select' => ['BRANCH_ID']
                ])->fetch();

                if ($courier) {
                    $filter['BRANCH_ID'] = $courier['BRANCH_ID'];
                }
                break;

            case 'COURIER_DELIVERY':
                // Курьер видит только свои заявки
                $courier = \CourierService\Main\CourierTable::getList([
                    'filter' => ['USER_ID' => $userId],
                    'select' => ['ID']
                ])->fetch();

                if ($courier) {
                    $filter['COURIER_ID'] = $courier['ID'];
                }
                break;

            case 'COURIER_OPERATOR':
                // Оператор видит заявки, которые он создал
                $filter['CREATED_BY'] = $userId;
                break;
        }

        return $filter;
    }

    /**
     * Проверка доступа к документу
     */
    public static function checkDocumentAccess($documentId, $action, $userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        if (!self::checkPermission('documents', $action, $userId)) {
            return false;
        }

        // Получаем информацию о документе
        $document = \CourierService\Main\DocumentTable::getList([
            'filter' => ['ID' => $documentId],
            'select' => ['REQUEST_ID', 'UPLOADED_BY']
        ])->fetch();

        if (!$document) {
            return false;
        }

        // Проверяем доступ к заявке
        return self::checkRequestAccess($document['REQUEST_ID'], 'view', $userId);
    }

    /**
     * Получение списка доступных филиалов для пользователя
     */
    public static function getAvailableBranches($userId = null)
    {
        if ($userId === null) {
            global $USER;
            $userId = $USER->GetID();
        }

        $userRole = self::getUserRole($userId);

        if ($userRole === 'COURIER_ADMIN') {
            // Администратор видит все филиалы
            return \CourierService\Main\BranchTable::getActiveBranches();
        } else {
            // Остальные роли видят только свой филиал
            $courier = \CourierService\Main\CourierTable::getList([
                'filter' => ['USER_ID' => $userId],
                'select' => ['BRANCH_ID']
            ])->fetch();

            if ($courier) {
                return \CourierService\Main\BranchTable::getList([
                    'filter' => ['ID' => $courier['BRANCH_ID']]
                ]);
            }
        }

        return [];
    }
}