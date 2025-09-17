<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Type\DateTime;

class BranchTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_branches';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new StringField('NAME', [
                'required' => true,
                'title' => 'Название филиала'
            ]),
            new TextField('ADDRESS', [
                'required' => true,
                'title' => 'Адрес филиала'
            ]),
            new StringField('COORDINATES', [
                'title' => 'Координаты'
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
}