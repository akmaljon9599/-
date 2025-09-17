<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\Validator;
use Bitrix\Main\Type\DateTime;

class UserTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_users';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new IntegerField('USER_ID', [
                'required' => true,
                'title' => 'ID пользователя Bitrix'
            ]),
            new StringField('ROLE', [
                'required' => true,
                'values' => ['admin', 'senior_courier', 'courier', 'operator'],
                'default_value' => 'operator',
                'title' => 'Роль пользователя'
            ]),
            new IntegerField('BRANCH_ID', [
                'title' => 'ID филиала'
            ]),
            new IntegerField('DEPARTMENT_ID', [
                'title' => 'ID подразделения'
            ]),
            new StringField('PHONE', [
                'title' => 'Телефон'
            ]),
            new BooleanField('IS_ACTIVE', [
                'default_value' => true,
                'title' => 'Активен'
            ]),
            new DatetimeField('CREATED_AT', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
            new DatetimeField('UPDATED_AT', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата обновления'
            ])
        ];
    }

    public static function getRoles()
    {
        return [
            'admin' => 'Администратор',
            'senior_courier' => 'Старший курьер',
            'courier' => 'Курьер',
            'operator' => 'Оператор'
        ];
    }

    public static function getRolePermissions($role)
    {
        $permissions = [
            'admin' => [
                'requests' => ['create', 'read', 'update', 'delete'],
                'couriers' => ['create', 'read', 'update', 'delete'],
                'branches' => ['create', 'read', 'update', 'delete'],
                'departments' => ['create', 'read', 'update', 'delete'],
                'reports' => ['read'],
                'settings' => ['read', 'update']
            ],
            'senior_courier' => [
                'requests' => ['read', 'update'],
                'couriers' => ['read', 'update'],
                'branches' => ['read'],
                'departments' => ['read'],
                'reports' => ['read']
            ],
            'courier' => [
                'requests' => ['read', 'update'],
                'branches' => ['read'],
                'departments' => ['read']
            ],
            'operator' => [
                'requests' => ['create', 'read', 'update'],
                'branches' => ['read'],
                'departments' => ['read']
            ]
        ];

        return $permissions[$role] ?? [];
    }
}