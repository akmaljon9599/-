<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Type\DateTime;

class DocumentTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_documents';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new IntegerField('REQUEST_ID', [
                'required' => true,
                'title' => 'ID заявки'
            ]),
            new StringField('TYPE', [
                'required' => true,
                'values' => ['contract', 'passport', 'delivery_photo', 'signature'],
                'title' => 'Тип документа'
            ]),
            new StringField('FILE_PATH', [
                'required' => true,
                'title' => 'Путь к файлу'
            ]),
            new StringField('FILE_NAME', [
                'required' => true,
                'title' => 'Имя файла'
            ]),
            new IntegerField('FILE_SIZE', [
                'required' => true,
                'title' => 'Размер файла'
            ]),
            new StringField('MIME_TYPE', [
                'required' => true,
                'title' => 'MIME тип'
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

    public static function getDocumentTypes()
    {
        return [
            'contract' => 'Договор',
            'passport' => 'Паспорт',
            'delivery_photo' => 'Фото доставки',
            'signature' => 'Подпись'
        ];
    }
}