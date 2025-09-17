<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Type\DateTime;

class LogTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_logs';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new IntegerField('USER_ID', [
                'required' => true,
                'title' => 'ID пользователя'
            ]),
            new StringField('ACTION', [
                'required' => true,
                'title' => 'Действие'
            ]),
            new StringField('ENTITY_TYPE', [
                'required' => true,
                'title' => 'Тип сущности'
            ]),
            new IntegerField('ENTITY_ID', [
                'required' => true,
                'title' => 'ID сущности'
            ]),
            new TextField('DATA', [
                'title' => 'Данные'
            ]),
            new StringField('IP_ADDRESS', [
                'title' => 'IP адрес'
            ]),
            new TextField('USER_AGENT', [
                'title' => 'User Agent'
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

    public static function getActions()
    {
        return [
            'create' => 'Создание',
            'read' => 'Просмотр',
            'update' => 'Обновление',
            'delete' => 'Удаление',
            'login' => 'Вход в систему',
            'logout' => 'Выход из системы',
            'update_status' => 'Изменение статуса',
            'upload_document' => 'Загрузка документа',
            'export' => 'Экспорт данных',
            'update_location' => 'Обновление местоположения',
            'complete_delivery' => 'Завершение доставки',
            'assign_courier' => 'Назначение курьера',
            'sync_abs' => 'Синхронизация с АБС'
        ];
    }

    public static function getEntityTypes()
    {
        return [
            'request' => 'Заявка',
            'user' => 'Пользователь',
            'courier' => 'Курьер',
            'branch' => 'Филиал',
            'department' => 'Подразделение',
            'document' => 'Документ',
            'location' => 'Местоположение',
            'system' => 'Система'
        ];
    }
}