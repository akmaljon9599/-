<?php
namespace CourierService\Main;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class RequestTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_requests';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\StringField('REQUEST_NUMBER', [
                'required' => true,
                'unique' => true
            ]),
            new Entity\StringField('ABS_ID'),
            new Entity\StringField('CLIENT_NAME', [
                'required' => true
            ]),
            new Entity\StringField('CLIENT_PHONE', [
                'required' => true
            ]),
            new Entity\TextField('CLIENT_ADDRESS', [
                'required' => true
            ]),
            new Entity\StringField('PAN', [
                'required' => true
            ]),
            new Entity\StringField('CARD_TYPE'),
            new Entity\EnumField('STATUS', [
                'values' => ['new', 'waiting', 'delivered', 'rejected', 'cancelled'],
                'default_value' => 'new'
            ]),
            new Entity\EnumField('CALL_STATUS', [
                'values' => ['not_called', 'successful', 'failed'],
                'default_value' => 'not_called'
            ]),
            new Entity\IntegerField('COURIER_ID'),
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            new Entity\IntegerField('DEPARTMENT_ID'),
            new Entity\IntegerField('OPERATOR_ID'),
            new Entity\DatetimeField('CREATED_DATE', [
                'required' => true
            ]),
            new Entity\DatetimeField('PROCESSED_DATE'),
            new Entity\DatetimeField('DELIVERY_DATE'),
            new Entity\TextField('REJECTION_REASON'),
            new Entity\StringField('COURIER_PHONE'),
            new Entity\TextField('DELIVERY_PHOTOS'),
            new Entity\TextField('SIGNATURE_DATA'),
            new Entity\StringField('CONTRACT_PDF'),
            new Entity\IntegerField('CREATED_BY', [
                'required' => true
            ]),
            new Entity\IntegerField('MODIFIED_BY'),
            new Entity\DatetimeField('DATE_CREATE', [
                'required' => true
            ]),
            new Entity\DatetimeField('DATE_MODIFY'),
            
            // Связи
            new Entity\ReferenceField(
                'COURIER',
                CourierTable::class,
                ['=this.COURIER_ID' => 'ref.ID']
            ),
            new Entity\ReferenceField(
                'BRANCH',
                BranchTable::class,
                ['=this.BRANCH_ID' => 'ref.ID']
            ),
            new Entity\ReferenceField(
                'DEPARTMENT',
                DepartmentTable::class,
                ['=this.DEPARTMENT_ID' => 'ref.ID']
            )
        ];
    }

    public static function getStatusList()
    {
        return [
            'new' => 'Новая',
            'waiting' => 'Ожидает доставки',
            'delivered' => 'Доставлено',
            'rejected' => 'Отказано',
            'cancelled' => 'Отменено'
        ];
    }

    public static function getCallStatusList()
    {
        return [
            'not_called' => 'Не звонили',
            'successful' => 'Успешный',
            'failed' => 'Не удался'
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