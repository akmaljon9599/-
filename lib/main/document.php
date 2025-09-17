<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;

class DocumentTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_documents';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\IntegerField('REQUEST_ID', [
                'required' => true
            ]),
            new Entity\EnumField('TYPE', [
                'values' => ['passport', 'contract', 'signature', 'delivery_photo', 'other'],
                'required' => true
            ]),
            new Entity\StringField('FILE_PATH', [
                'required' => true
            ]),
            new Entity\StringField('FILE_NAME', [
                'required' => true
            ]),
            new Entity\IntegerField('FILE_SIZE'),
            new Entity\StringField('MIME_TYPE'),
            new Entity\IntegerField('UPLOADED_BY', [
                'required' => true
            ]),
            new Entity\DatetimeField('UPLOAD_DATE', [
                'required' => true
            ])
        ];
    }

    public static function getDocumentTypes()
    {
        return [
            'passport' => 'Паспорт',
            'contract' => 'Договор',
            'signature' => 'Подпись',
            'delivery_photo' => 'Фото доставки',
            'other' => 'Прочее'
        ];
    }

    public static function getDocumentsByRequest($requestId)
    {
        return self::getList([
            'filter' => ['REQUEST_ID' => $requestId],
            'order' => ['UPLOAD_DATE' => 'DESC']
        ]);
    }
}