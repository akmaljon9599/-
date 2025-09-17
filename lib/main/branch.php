<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;

class BranchTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_branches';
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
            new Entity\TextField('ADDRESS', [
                'required' => true
            ]),
            new Entity\StringField('PHONE'),
            new Entity\StringField('EMAIL'),
            new Entity\IntegerField('MANAGER_ID'),
            new Entity\BooleanField('IS_ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y'
            ]),
            new Entity\DatetimeField('CREATED_DATE', [
                'required' => true
            ]),
            new Entity\DatetimeField('MODIFIED_DATE')
        ];
    }

    public static function getActiveBranches()
    {
        return self::getList([
            'filter' => ['IS_ACTIVE' => 'Y'],
            'order' => ['NAME' => 'ASC']
        ]);
    }
}