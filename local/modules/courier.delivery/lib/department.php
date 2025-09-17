<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с подразделениями
 */
class DepartmentTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_departments';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255)
                    ];
                }
            ]),
            
            new Entity\StringField('CODE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50)
                    ];
                }
            ]),
            
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
                'BRANCH',
                'Courier\Delivery\BranchTable',
                ['=this.BRANCH_ID' => 'ref.ID']
            )
        ];
    }

    /**
     * Получить подразделения по филиалу
     */
    public static function getDepartmentsByBranch($branchId)
    {
        return static::getList([
            'select' => ['ID', 'NAME', 'CODE'],
            'filter' => [
                'BRANCH_ID' => $branchId,
                'IS_ACTIVE' => 'Y'
            ],
            'order' => ['NAME' => 'ASC']
        ]);
    }
}