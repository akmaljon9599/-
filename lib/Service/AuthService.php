<?php

namespace CourierService\Service;

use Bitrix\Main\Context;
use Bitrix\Main\Web\Cookie;
use CourierService\Entity\UserTable;

class AuthService
{
    private $currentUser = null;

    public function getCurrentUser()
    {
        if ($this->currentUser === null) {
            $this->currentUser = $this->loadCurrentUser();
        }

        return $this->currentUser;
    }

    public function isAuthenticated()
    {
        return $this->getCurrentUser() !== null;
    }

    public function hasPermission($permission, $entity = null)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        $permissions = UserTable::getRolePermissions($user['ROLE']);
        
        if ($entity === null) {
            return !empty($permissions);
        }

        return isset($permissions[$entity]) && in_array($permission, $permissions[$entity]);
    }

    public function getRole()
    {
        $user = $this->getCurrentUser();
        return $user ? $user['ROLE'] : null;
    }

    public function getUserId()
    {
        $user = $this->getCurrentUser();
        return $user ? $user['USER_ID'] : null;
    }

    public function getBranchId()
    {
        $user = $this->getCurrentUser();
        return $user ? $user['BRANCH_ID'] : null;
    }

    public function getDepartmentId()
    {
        $user = $this->getCurrentUser();
        return $user ? $user['DEPARTMENT_ID'] : null;
    }

    public function login($login, $password)
    {
        global $USER;

        if ($USER->Login($login, $password, 'Y') === true) {
            $userId = $USER->GetID();
            $user = UserTable::getList([
                'filter' => [
                    'USER_ID' => $userId,
                    'IS_ACTIVE' => true
                ]
            ])->fetch();

            if ($user) {
                $this->setSessionData($user);
                return [
                    'success' => true,
                    'user' => $this->formatUser($user)
                ];
            } else {
                $USER->Logout();
                return [
                    'success' => false,
                    'message' => 'User not found in courier service'
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Invalid credentials'
        ];
    }

    public function logout()
    {
        global $USER;
        $USER->Logout();
        $this->clearSessionData();
        $this->currentUser = null;
    }

    public function registerUser($userId, $role, $branchId = null, $departmentId = null, $phone = null)
    {
        // Проверяем, не зарегистрирован ли уже пользователь
        $existingUser = UserTable::getList([
            'filter' => ['USER_ID' => $userId]
        ])->fetch();

        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'User already registered'
            ];
        }

        $userData = [
            'USER_ID' => $userId,
            'ROLE' => $role,
            'BRANCH_ID' => $branchId,
            'DEPARTMENT_ID' => $departmentId,
            'PHONE' => $phone,
            'IS_ACTIVE' => true,
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime()
        ];

        $result = UserTable::add($userData);
        if ($result->isSuccess()) {
            return [
                'success' => true,
                'id' => $result->getId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to register user',
                'errors' => $result->getErrorMessages()
            ];
        }
    }

    public function updateUser($id, $data)
    {
        $data['UPDATED_AT'] = new \Bitrix\Main\Type\DateTime();
        
        $result = UserTable::update($id, $data);
        if ($result->isSuccess()) {
            // Обновляем данные в сессии если это текущий пользователь
            if ($this->currentUser && $this->currentUser['ID'] == $id) {
                $this->currentUser = array_merge($this->currentUser, $data);
                $this->setSessionData($this->currentUser);
            }
            
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update user',
                'errors' => $result->getErrorMessages()
            ];
        }
    }

    public function deactivateUser($id)
    {
        return $this->updateUser($id, ['IS_ACTIVE' => false]);
    }

    public function activateUser($id)
    {
        return $this->updateUser($id, ['IS_ACTIVE' => true]);
    }

    public function getUsersByRole($role)
    {
        $result = UserTable::getList([
            'filter' => [
                'ROLE' => $role,
                'IS_ACTIVE' => true
            ],
            'order' => ['CREATED_AT' => 'DESC']
        ]);

        $users = [];
        while ($row = $result->fetch()) {
            $users[] = $this->formatUser($row);
        }

        return $users;
    }

    public function getCouriers()
    {
        return $this->getUsersByRole('courier');
    }

    public function getOperators()
    {
        return $this->getUsersByRole('operator');
    }

    public function getSeniorCouriers()
    {
        return $this->getUsersByRole('senior_courier');
    }

    public function getAdmins()
    {
        return $this->getUsersByRole('admin');
    }

    private function loadCurrentUser()
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            return null;
        }

        $userId = $USER->GetID();
        $user = UserTable::getList([
            'filter' => [
                'USER_ID' => $userId,
                'IS_ACTIVE' => true
            ]
        ])->fetch();

        if ($user) {
            $this->setSessionData($user);
        }

        return $user;
    }

    private function setSessionData($user)
    {
        $_SESSION['COURIER_SERVICE_USER'] = $user;
    }

    private function clearSessionData()
    {
        unset($_SESSION['COURIER_SERVICE_USER']);
    }

    private function formatUser($user)
    {
        return [
            'id' => $user['ID'],
            'user_id' => $user['USER_ID'],
            'role' => $user['ROLE'],
            'role_text' => UserTable::getRoles()[$user['ROLE']] ?? $user['ROLE'],
            'branch_id' => $user['BRANCH_ID'],
            'department_id' => $user['DEPARTMENT_ID'],
            'phone' => $user['PHONE'],
            'is_active' => $user['IS_ACTIVE'],
            'created_at' => $user['CREATED_AT']->format('Y-m-d H:i:s'),
            'updated_at' => $user['UPDATED_AT']->format('Y-m-d H:i:s')
        ];
    }
}