<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;

class SettingTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_settings';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('SETTING_KEY', [
                'required' => true,
                'unique' => true
            ]),
            new Entity\TextField('SETTING_VALUE'),
            new Entity\TextField('DESCRIPTION'),
            new Entity\DatetimeField('CREATED_DATE', [
                'required' => true
            ]),
            new Entity\DatetimeField('MODIFIED_DATE')
        ];
    }

    public static function get($key, $default = null)
    {
        $setting = self::getList([
            'filter' => ['SETTING_KEY' => $key],
            'select' => ['SETTING_VALUE']
        ])->fetch();

        return $setting ? $setting['SETTING_VALUE'] : $default;
    }

    public static function set($key, $value, $description = null)
    {
        $existing = self::getList([
            'filter' => ['SETTING_KEY' => $key],
            'select' => ['ID']
        ])->fetch();

        if ($existing) {
            return self::update($existing['ID'], [
                'SETTING_VALUE' => $value,
                'DESCRIPTION' => $description,
                'MODIFIED_DATE' => new \Bitrix\Main\Type\DateTime()
            ]);
        } else {
            return self::add([
                'SETTING_KEY' => $key,
                'SETTING_VALUE' => $value,
                'DESCRIPTION' => $description,
                'CREATED_DATE' => new \Bitrix\Main\Type\DateTime()
            ]);
        }
    }
}