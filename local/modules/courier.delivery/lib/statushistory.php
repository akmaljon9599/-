<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с историей статусов заявок
 */
class StatusHistoryTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_status_history';
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
            
            new Entity\StringField('OLD_STATUS', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50)
                    ];
                }
            ]),
            
            new Entity\StringField('NEW_STATUS', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50)
                    ];
                }
            ]),
            
            new Entity\TextField('COMMENT'),
            
            new Entity\DatetimeField('CREATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            new Entity\IntegerField('CREATED_BY', [
                'required' => true
            ]),
            
            // Связи
            new Entity\ReferenceField(
                'REQUEST',
                'Courier\Delivery\DeliveryTable',
                ['=this.REQUEST_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'CREATOR',
                'Bitrix\Main\UserTable',
                ['=this.CREATED_BY' => 'ref.ID']
            )
        ];
    }

    /**
     * Получить историю статусов для заявки
     */
    public static function getHistoryByRequest($requestId)
    {
        return static::getList([
            'select' => [
                'ID', 'OLD_STATUS', 'NEW_STATUS', 'COMMENT', 'CREATED_DATE',
                'CREATOR.NAME' => 'CREATOR_NAME',
                'CREATOR.LAST_NAME' => 'CREATOR_LAST_NAME'
            ],
            'filter' => ['REQUEST_ID' => $requestId],
            'order' => ['CREATED_DATE' => 'DESC']
        ]);
    }
}