<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с филиалами
 */
class BranchTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_branches';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255)
                    ];
                }
            ]),
            
            new Entity\TextField('ADDRESS', [
                'required' => true
            ]),
            
            new Entity\StringField('PHONE', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 20)
                    ];
                }
            ]),
            
            new Entity\IntegerField('MANAGER_ID'),
            
            new Entity\BooleanField('IS_ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            
            new Entity\DatetimeField('CREATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            // Связи
            new Entity\ReferenceField(
                'MANAGER',
                'Bitrix\Main\UserTable',
                ['=this.MANAGER_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'DEPARTMENTS',
                'Courier\Delivery\DepartmentTable',
                ['=this.ID' => 'ref.BRANCH_ID']
            ),
            
            new Entity\ReferenceField(
                'COURIERS',
                'Courier\Delivery\CourierTable',
                ['=this.ID' => 'ref.BRANCH_ID']
            )
        ];
    }

    /**
     * Получить активные филиалы
     */
    public static function getActiveBranches()
    {
        return static::getList([
            'select' => [
                'ID', 'NAME', 'ADDRESS', 'PHONE',
                'MANAGER.NAME' => 'MANAGER_NAME',
                'MANAGER.LAST_NAME' => 'MANAGER_LAST_NAME'
            ],
            'filter' => ['IS_ACTIVE' => 'Y'],
            'order' => ['NAME' => 'ASC']
        ]);
    }
}