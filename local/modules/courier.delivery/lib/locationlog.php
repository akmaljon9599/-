<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с логом геолокации курьеров
 */
class LocationLogTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_location_log';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            
            new Entity\IntegerField('COURIER_ID', [
                'required' => true
            ]),
            
            new Entity\FloatField('LATITUDE', [
                'required' => true,
                'scale' => 8
            ]),
            
            new Entity\FloatField('LONGITUDE', [
                'required' => true,
                'scale' => 8
            ]),
            
            new Entity\FloatField('ACCURACY'),
            
            new Entity\DatetimeField('CREATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            // Связи
            new Entity\ReferenceField(
                'COURIER',
                'Courier\Delivery\CourierTable',
                ['=this.COURIER_ID' => 'ref.ID']
            )
        ];
    }

    /**
     * Получить маршрут курьера за период
     */
    public static function getCourierRoute($courierId, $dateFrom, $dateTo)
    {
        return static::getList([
            'select' => ['LATITUDE', 'LONGITUDE', 'ACCURACY', 'CREATED_DATE'],
            'filter' => [
                'COURIER_ID' => $courierId,
                '>=CREATED_DATE' => $dateFrom,
                '<=CREATED_DATE' => $dateTo
            ],
            'order' => ['CREATED_DATE' => 'ASC']
        ]);
    }

    /**
     * Очистить старые записи геолокации
     */
    public static function cleanOldLocations($daysToKeep = 30)
    {
        $dateThreshold = new DateTime();
        $dateThreshold->add('-' . $daysToKeep . ' days');

        $connection = \Bitrix\Main\Application::getConnection();
        $connection->query(
            "DELETE FROM " . static::getTableName() . " WHERE CREATED_DATE < '" . $dateThreshold->format('Y-m-d H:i:s') . "'"
        );

        return true;
    }
}