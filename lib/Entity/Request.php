<?php

namespace CourierService\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Type\DateTime;

class RequestTable extends DataManager
{
    public static function getTableName()
    {
        return 'courier_service_requests';
    }

    public static function getMap()
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new StringField('REQUEST_NUMBER', [
                'required' => true,
                'title' => 'Номер заявки'
            ]),
            new StringField('ABS_ID', [
                'title' => 'ID в АБС'
            ]),
            new StringField('CLIENT_NAME', [
                'required' => true,
                'title' => 'ФИО клиента'
            ]),
            new StringField('CLIENT_PHONE', [
                'required' => true,
                'title' => 'Телефон клиента'
            ]),
            new TextField('CLIENT_ADDRESS', [
                'required' => true,
                'title' => 'Адрес доставки'
            ]),
            new StringField('PAN', [
                'required' => true,
                'title' => 'PAN карты'
            ]),
            new StringField('CARD_TYPE', [
                'title' => 'Тип карты'
            ]),
            new StringField('STATUS', [
                'required' => true,
                'values' => ['new', 'waiting_delivery', 'in_delivery', 'delivered', 'rejected'],
                'default_value' => 'new',
                'title' => 'Статус заявки'
            ]),
            new StringField('CALL_STATUS', [
                'required' => true,
                'values' => ['not_called', 'successful', 'failed'],
                'default_value' => 'not_called',
                'title' => 'Статус звонка'
            ]),
            new IntegerField('COURIER_ID', [
                'title' => 'ID курьера'
            ]),
            new IntegerField('BRANCH_ID', [
                'required' => true,
                'title' => 'ID филиала'
            ]),
            new IntegerField('DEPARTMENT_ID', [
                'title' => 'ID подразделения'
            ]),
            new IntegerField('OPERATOR_ID', [
                'title' => 'ID оператора'
            ]),
            new DatetimeField('REGISTRATION_DATE', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата регистрации'
            ]),
            new DatetimeField('PROCESSING_DATE', [
                'title' => 'Дата обработки'
            ]),
            new DatetimeField('DELIVERY_DATE', [
                'title' => 'Дата доставки'
            ]),
            new TextField('REJECTION_REASON', [
                'title' => 'Причина отказа'
            ]),
            new StringField('COURIER_PHONE', [
                'title' => 'Телефон курьера'
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

    public static function getStatuses()
    {
        return [
            'new' => 'Новая',
            'waiting_delivery' => 'Ожидает доставки',
            'in_delivery' => 'В доставке',
            'delivered' => 'Доставлено',
            'rejected' => 'Отказано'
        ];
    }

    public static function getCallStatuses()
    {
        return [
            'not_called' => 'Не звонили',
            'successful' => 'Успешный',
            'failed' => 'Не удался'
        ];
    }

    public static function getCardTypes()
    {
        return [
            'Visa' => 'Visa',
            'MasterCard' => 'MasterCard',
            'Мир' => 'Мир'
        ];
    }

    public static function generateRequestNumber()
    {
        $prefix = 'REQ';
        $date = date('Ymd');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $date . $random;
    }
}