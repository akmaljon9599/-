<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class CourierTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_couriers';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\IntegerField('USER_ID', [
                'required' => true,
                'unique' => true
            ]),
            new Entity\StringField('NAME', [
                'required' => true
            ]),
            new Entity\StringField('PHONE', [
                'required' => true
            ]),
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            new Entity\EnumField('STATUS', [
                'values' => ['active', 'inactive', 'on_delivery'],
                'default_value' => 'active'
            ]),
            new Entity\FloatField('LAST_LOCATION_LAT'),
            new Entity\FloatField('LAST_LOCATION_LON'),
            new Entity\DatetimeField('LAST_ACTIVITY'),
            new Entity\BooleanField('IS_ONLINE', [
                'values' => ['N', 'Y'],
                'default_value' => 'N'
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

    public static function getStatusList()
    {
        return [
            'active' => 'Активен',
            'inactive' => 'Неактивен',
            'on_delivery' => 'На доставке'
        ];
    }

    public static function updateLocation($courierId, $latitude, $longitude)
    {
        $result = self::update($courierId, [
            'LAST_LOCATION_LAT' => $latitude,
            'LAST_LOCATION_LON' => $longitude,
            'LAST_ACTIVITY' => new DateTime(),
            'IS_ONLINE' => 'Y'
        ]);

        return $result;
    }

    public static function getActiveCouriers()
    {
        return self::getList([
            'filter' => [
                'STATUS' => 'active',
                'IS_ONLINE' => 'Y'
            ],
            'select' => ['*', 'BRANCH']
        ]);
    }

    public static function getCouriersByBranch($branchId)
    {
        return self::getList([
            'filter' => ['BRANCH_ID' => $branchId],
            'select' => ['*', 'BRANCH']
        ]);
    }
}