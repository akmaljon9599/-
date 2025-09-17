<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;

class DepartmentTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_departments';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('NAME', [
                'required' => true
            ]),
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            new Entity\IntegerField('MANAGER_ID'),
            new Entity\BooleanField('IS_ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            new Entity\DatetimeField('CREATED_DATE', [
                'required' => true
            ]),
            new Entity\DatetimeField('MODIFIED_DATE'),
            
            // Связи
            new Entity\ReferenceField(
                'BRANCH',
                BranchTable::class,
                ['=this.BRANCH_ID' => 'ref.ID']
            )
        ];
    }

    public static function getDepartmentsByBranch($branchId)
    {
        return self::getList([
            'filter' => [
                'BRANCH_ID' => $branchId,
                'IS_ACTIVE' => 'Y'
            ],
            'order' => ['NAME' => 'ASC']
        ]);
    }
}