<?php
namespace Courier\Delivery\Util;

use Bitrix\Main\Config\Option;
use Bitrix\Main\UserTable;
use Bitrix\Main\GroupTable;

/**
 * Класс для управления ролями и правами доступа
 */
class RoleManager
{
    // Константы ролей
    const ROLE_ADMIN = 'COURIER_ADMIN';
    const ROLE_SENIOR = 'COURIER_SENIOR';
    const ROLE_OPERATOR = 'COURIER_OPERATOR';
    const ROLE_COURIER = 'COURIER_DELIVERY';

    // Права доступа
    const PERMISSION_VIEW_ALL = 'view_all';
    const PERMISSION_EDIT_ALL = 'edit_all';
    const PERMISSION_DELETE = 'delete';
    const PERMISSION_ASSIGN_COURIER = 'assign_courier';
    const PERMISSION_CHANGE_STATUS = 'change_status';
    const PERMISSION_VIEW_DOCUMENTS = 'view_documents';
    const PERMISSION_UPLOAD_DOCUMENTS = 'upload_documents';
    const PERMISSION_VIEW_REPORTS = 'view_reports';
    const PERMISSION_MANAGE_COURIERS = 'manage_couriers';
    const PERMISSION_MANAGE_SETTINGS = 'manage_settings';
    const PERMISSION_VIEW_LOCATION = 'view_location';
    const PERMISSION_UPDATE_LOCATION = 'update_location';

    private $rolePermissions = [
        self::ROLE_ADMIN => [
            self::PERMISSION_VIEW_ALL,
            self::PERMISSION_EDIT_ALL,
            self::PERMISSION_DELETE,
            self::PERMISSION_ASSIGN_COURIER,
            self::PERMISSION_CHANGE_STATUS,
            self::PERMISSION_VIEW_DOCUMENTS,
            self::PERMISSION_UPLOAD_DOCUMENTS,
            self::PERMISSION_VIEW_REPORTS,
            self::PERMISSION_MANAGE_COURIERS,
            self::PERMISSION_MANAGE_SETTINGS,
            self::PERMISSION_VIEW_LOCATION,
            self::PERMISSION_UPDATE_LOCATION
        ],
        
        self::ROLE_SENIOR => [
            self::PERMISSION_VIEW_ALL,
            self::PERMISSION_EDIT_ALL,
            self::PERMISSION_ASSIGN_COURIER,
            self::PERMISSION_CHANGE_STATUS,
            self::PERMISSION_VIEW_DOCUMENTS,
            self::PERMISSION_UPLOAD_DOCUMENTS,
            self::PERMISSION_VIEW_REPORTS,
            self::PERMISSION_MANAGE_COURIERS,
            self::PERMISSION_VIEW_LOCATION
        ],
        
        self::ROLE_OPERATOR => [
            self::PERMISSION_VIEW_ALL,
            self::PERMISSION_EDIT_ALL,
            self::PERMISSION_CHANGE_STATUS,
            self::PERMISSION_VIEW_DOCUMENTS,
            self::PERMISSION_UPLOAD_DOCUMENTS,
            self::PERMISSION_VIEW_LOCATION
        ],
        
        self::ROLE_COURIER => [
            self::PERMISSION_CHANGE_STATUS,
            self::PERMISSION_VIEW_DOCUMENTS,
            self::PERMISSION_UPLOAD_DOCUMENTS,
            self::PERMISSION_UPDATE_LOCATION
        ]
    ];

    /**
     * Получить роли пользователя
     */
    public function getUserRoles($userId)
    {
        $user = UserTable::getById($userId)->fetch();
        if (!$user) {
            return [];
        }

        $userGroups = \CUser::GetUserGroup($userId);
        $roles = [];

        foreach ($userGroups as $groupId) {
            $role = $this->getGroupRole($groupId);
            if ($role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * Проверить наличие роли у пользователя
     */
    public function hasRole($userId, $role)
    {
        $userRoles = $this->getUserRoles($userId);
        return in_array($role, $userRoles);
    }

    /**
     * Проверить права доступа пользователя
     */
    public function hasPermission($userId, $permission)
    {
        $userRoles = $this->getUserRoles($userId);
        
        foreach ($userRoles as $role) {
            if (isset($this->rolePermissions[$role]) && 
                in_array($permission, $this->rolePermissions[$role])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Назначить роль пользователю
     */
    public function assignRole($userId, $role)
    {
        $groupId = $this->getRoleGroupId($role);
        if (!$groupId) {
            return false;
        }

        $user = new \CUser();
        $currentGroups = \CUser::GetUserGroup($userId);
        
        if (!in_array($groupId, $currentGroups)) {
            $currentGroups[] = $groupId;
            $user->SetUserGroup($userId, $currentGroups);
            
            Logger::log("Role {$role} assigned to user #{$userId}", 'ROLE_ASSIGN');
            return true;
        }

        return false;
    }

    /**
     * Отозвать роль у пользователя
     */
    public function revokeRole($userId, $role)
    {
        $groupId = $this->getRoleGroupId($role);
        if (!$groupId) {
            return false;
        }

        $user = new \CUser();
        $currentGroups = \CUser::GetUserGroup($userId);
        
        $key = array_search($groupId, $currentGroups);
        if ($key !== false) {
            unset($currentGroups[$key]);
            $user->SetUserGroup($userId, array_values($currentGroups));
            
            Logger::log("Role {$role} revoked from user #{$userId}", 'ROLE_REVOKE');
            return true;
        }

        return false;
    }

    /**
     * Получить ID группы по роли
     */
    private function getRoleGroupId($role)
    {
        return Option::get('courier.delivery', 'GROUP_' . $role, null);
    }

    /**
     * Получить роль по ID группы
     */
    private function getGroupRole($groupId)
    {
        $roles = [
            self::ROLE_ADMIN,
            self::ROLE_SENIOR,
            self::ROLE_OPERATOR,
            self::ROLE_COURIER
        ];

        foreach ($roles as $role) {
            if ($this->getRoleGroupId($role) == $groupId) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Проверить доступ к заявке
     */
    public function canAccessRequest($userId, $requestId)
    {
        // Администраторы и старшие курьеры имеют доступ ко всем заявкам
        if ($this->hasPermission($userId, self::PERMISSION_VIEW_ALL)) {
            return true;
        }

        // Получаем заявку
        $request = \Courier\Delivery\DeliveryTable::getById($requestId)->fetch();
        if (!$request) {
            return false;
        }

        // Курьер может видеть только свои заявки
        if ($this->hasRole($userId, self::ROLE_COURIER)) {
            $courier = \Courier\Delivery\CourierTable::getList([
                'select' => ['ID'],
                'filter' => ['USER_ID' => $userId]
            ])->fetch();

            return $courier && $courier['ID'] == $request['COURIER_ID'];
        }

        // Оператор может видеть заявки, созданные им или назначенные ему
        if ($this->hasRole($userId, self::ROLE_OPERATOR)) {
            return $request['CREATED_BY'] == $userId || $request['OPERATOR_ID'] == $userId;
        }

        return false;
    }

    /**
     * Проверить доступ к курьеру
     */
    public function canAccessCourier($userId, $courierId)
    {
        // Администраторы и старшие курьеры имеют доступ ко всем курьерам
        if ($this->hasPermission($userId, self::PERMISSION_MANAGE_COURIERS) || 
            $this->hasPermission($userId, self::PERMISSION_VIEW_ALL)) {
            return true;
        }

        // Курьер может видеть только свою информацию
        if ($this->hasRole($userId, self::ROLE_COURIER)) {
            $courier = \Courier\Delivery\CourierTable::getById($courierId)->fetch();
            return $courier && $courier['USER_ID'] == $userId;
        }

        return false;
    }

    /**
     * Проверить доступ к филиалу
     */
    public function canAccessBranch($userId, $branchId)
    {
        // Администраторы имеют доступ ко всем филиалам
        if ($this->hasRole($userId, self::ROLE_ADMIN)) {
            return true;
        }

        // Старшие курьеры имеют доступ к своему филиалу
        if ($this->hasRole($userId, self::ROLE_SENIOR)) {
            // Здесь нужно реализовать логику определения филиала старшего курьера
            return true;
        }

        // Курьеры и операторы имеют доступ к своему филиалу
        $courier = \Courier\Delivery\CourierTable::getList([
            'select' => ['BRANCH_ID'],
            'filter' => ['USER_ID' => $userId]
        ])->fetch();

        return $courier && $courier['BRANCH_ID'] == $branchId;
    }

    /**
     * Получить доступные статусы для роли
     */
    public function getAvailableStatuses($userId)
    {
        $allStatuses = [
            'NEW' => 'Новая',
            'PROCESSING' => 'В обработке',
            'ASSIGNED' => 'Назначена курьеру',
            'IN_DELIVERY' => 'В доставке',
            'DELIVERED' => 'Доставлено',
            'REJECTED' => 'Отказано',
            'CANCELLED' => 'Отменено'
        ];

        if ($this->hasRole($userId, self::ROLE_ADMIN) || 
            $this->hasRole($userId, self::ROLE_SENIOR)) {
            return $allStatuses;
        }

        if ($this->hasRole($userId, self::ROLE_OPERATOR)) {
            return [
                'NEW' => 'Новая',
                'PROCESSING' => 'В обработке',
                'ASSIGNED' => 'Назначена курьеру',
                'REJECTED' => 'Отказано',
                'CANCELLED' => 'Отменено'
            ];
        }

        if ($this->hasRole($userId, self::ROLE_COURIER)) {
            return [
                'IN_DELIVERY' => 'В доставке',
                'DELIVERED' => 'Доставлено',
                'REJECTED' => 'Отказано'
            ];
        }

        return [];
    }

    /**
     * Получить список ролей с описанием
     */
    public function getAllRoles()
    {
        return [
            self::ROLE_ADMIN => [
                'name' => 'Администратор',
                'description' => 'Полный доступ ко всем функциям системы',
                'permissions' => $this->rolePermissions[self::ROLE_ADMIN]
            ],
            self::ROLE_SENIOR => [
                'name' => 'Старший курьер',
                'description' => 'Управление курьерами и заявками в филиале',
                'permissions' => $this->rolePermissions[self::ROLE_SENIOR]
            ],
            self::ROLE_OPERATOR => [
                'name' => 'Оператор',
                'description' => 'Создание и редактирование заявок',
                'permissions' => $this->rolePermissions[self::ROLE_OPERATOR]
            ],
            self::ROLE_COURIER => [
                'name' => 'Курьер',
                'description' => 'Работа с назначенными заявками',
                'permissions' => $this->rolePermissions[self::ROLE_COURIER]
            ]
        ];
    }

    /**
     * Проверить, может ли пользователь изменять статус заявки
     */
    public function canChangeStatus($userId, $requestId, $newStatus)
    {
        if (!$this->hasPermission($userId, self::PERMISSION_CHANGE_STATUS)) {
            return false;
        }

        $availableStatuses = $this->getAvailableStatuses($userId);
        if (!isset($availableStatuses[$newStatus])) {
            return false;
        }

        return $this->canAccessRequest($userId, $requestId);
    }

    /**
     * Создать контекст безопасности для пользователя
     */
    public function createSecurityContext($userId)
    {
        $roles = $this->getUserRoles($userId);
        $permissions = [];

        foreach ($roles as $role) {
            if (isset($this->rolePermissions[$role])) {
                $permissions = array_merge($permissions, $this->rolePermissions[$role]);
            }
        }

        return [
            'user_id' => $userId,
            'roles' => $roles,
            'permissions' => array_unique($permissions),
            'is_admin' => in_array(self::ROLE_ADMIN, $roles),
            'is_senior' => in_array(self::ROLE_SENIOR, $roles),
            'is_operator' => in_array(self::ROLE_OPERATOR, $roles),
            'is_courier' => in_array(self::ROLE_COURIER, $roles)
        ];
    }

    /**
     * Логировать действие с проверкой прав
     */
    public function logSecurityAction($userId, $action, $resourceType, $resourceId, $success = true)
    {
        $context = $this->createSecurityContext($userId);
        
        Logger::log(
            "Security action: {$action} on {$resourceType}#{$resourceId} by user#{$userId} " . 
            ($success ? 'SUCCESS' : 'DENIED'),
            'SECURITY_ACTION',
            $userId,
            [
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'user_roles' => $context['roles'],
                'success' => $success
            ]
        );
    }
}