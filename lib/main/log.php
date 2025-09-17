<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;

class LogTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_logs';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\IntegerField('USER_ID', [
                'required' => true
            ]),
            new Entity\StringField('ACTION', [
                'required' => true
            ]),
            new Entity\StringField('ENTITY_TYPE', [
                'required' => true
            ]),
            new Entity\IntegerField('ENTITY_ID', [
                'required' => true
            ]),
            new Entity\TextField('OLD_DATA'),
            new Entity\TextField('NEW_DATA'),
            new Entity\StringField('IP_ADDRESS'),
            new Entity\TextField('USER_AGENT'),
            new Entity\DatetimeField('CREATED_DATE', [
                'required' => true
            ])
        ];
    }

    public static function logAction($userId, $action, $entityType, $entityId, $oldData = null, $newData = null)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return self::add([
            'USER_ID' => $userId,
            'ACTION' => $action,
            'ENTITY_TYPE' => $entityType,
            'ENTITY_ID' => $entityId,
            'OLD_DATA' => $oldData ? json_encode($oldData) : null,
            'NEW_DATA' => $newData ? json_encode($newData) : null,
            'IP_ADDRESS' => $ipAddress,
            'USER_AGENT' => $userAgent,
            'CREATED_DATE' => new \Bitrix\Main\Type\DateTime()
        ]);
    }
}