<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\FloatField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Type\DateTime;

class LocationTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_locations';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new IntegerField('COURIER_ID', [
                'required' => true,
                'title' => 'ID курьера'
            ]),
            new FloatField('LATITUDE', [
                'required' => true,
                'title' => 'Широта'
            ]),
            new FloatField('LONGITUDE', [
                'required' => true,
                'title' => 'Долгота'
            ]),
            new FloatField('ACCURACY', [
                'title' => 'Точность'
            ]),
            new StringField('ADDRESS', [
                'title' => 'Адрес'
            ]),
            new DatetimeField('CREATED_AT', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ])
        ];
    }
}