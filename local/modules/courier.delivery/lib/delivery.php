<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с заявками на доставку
 */
class DeliveryTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_requests';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            
            new Entity\StringField('ABS_ID', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50)
                    ];
                }
            ]),
            
            new Entity\StringField('CLIENT_NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255)
                    ];
                }
            ]),
            
            new Entity\StringField('CLIENT_PHONE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 20),
                        new Entity\Validator\RegExp('/^\+7\s?\(\d{3}\)\s?\d{3}-\d{2}-\d{2}$/')
                    ];
                }
            ]),
            
            new Entity\StringField('CLIENT_PAN', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 20)
                    ];
                }
            ]),
            
            new Entity\TextField('DELIVERY_ADDRESS', [
                'required' => true
            ]),
            
            new Entity\EnumField('STATUS', [
                'values' => [
                    'NEW', 'PROCESSING', 'ASSIGNED', 'IN_DELIVERY', 
                    'DELIVERED', 'REJECTED', 'CANCELLED'
                ],
                'default_value' => 'NEW'
            ]),
            
            new Entity\IntegerField('COURIER_ID', [
                'required' => false
            ]),
            
            new Entity\IntegerField('BRANCH_ID', [
                'required' => true
            ]),
            
            new Entity\IntegerField('DEPARTMENT_ID', [
                'required' => true
            ]),
            
            new Entity\IntegerField('OPERATOR_ID', [
                'required' => false
            ]),
            
            new Entity\EnumField('CALL_STATUS', [
                'values' => ['NOT_CALLED', 'SUCCESS', 'FAILED', 'NO_ANSWER'],
                'default_value' => 'NOT_CALLED'
            ]),
            
            new Entity\EnumField('CARD_TYPE', [
                'values' => ['VISA', 'MASTERCARD', 'MIR'],
                'required' => false
            ]),
            
            new Entity\DatetimeField('PROCESSING_DATE'),
            new Entity\DatetimeField('DELIVERY_DATE'),
            
            new Entity\DatetimeField('CREATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            new Entity\DatetimeField('UPDATED_DATE', [
                'default_value' => function() {
                    return new DateTime();
                }
            ]),
            
            new Entity\IntegerField('CREATED_BY', [
                'required' => true
            ]),
            
            new Entity\IntegerField('UPDATED_BY'),
            
            new Entity\TextField('NOTES'),
            
            // Связи с другими таблицами
            new Entity\ReferenceField(
                'COURIER',
                'Courier\Delivery\CourierTable',
                ['=this.COURIER_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'BRANCH',
                'Courier\Delivery\BranchTable',
                ['=this.BRANCH_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'DEPARTMENT',
                'Courier\Delivery\DepartmentTable',
                ['=this.DEPARTMENT_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'CREATOR',
                'Bitrix\Main\UserTable',
                ['=this.CREATED_BY' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'OPERATOR',
                'Bitrix\Main\UserTable',
                ['=this.OPERATOR_ID' => 'ref.ID']
            ),
            
            new Entity\ReferenceField(
                'DOCUMENTS',
                'Courier\Delivery\DocumentTable',
                ['=this.ID' => 'ref.REQUEST_ID']
            ),
            
            new Entity\ReferenceField(
                'STATUS_HISTORY',
                'Courier\Delivery\StatusHistoryTable',
                ['=this.ID' => 'ref.REQUEST_ID']
            )
        ];
    }

    /**
     * Получить заявки с фильтрацией
     */
    public static function getFilteredList($filter = [], $order = [], $limit = null, $offset = null)
    {
        $parameters = [
            'select' => [
                'ID', 'ABS_ID', 'CLIENT_NAME', 'CLIENT_PHONE', 'CLIENT_PAN',
                'DELIVERY_ADDRESS', 'STATUS', 'CALL_STATUS', 'CARD_TYPE',
                'PROCESSING_DATE', 'DELIVERY_DATE', 'CREATED_DATE',
                'COURIER.FULL_NAME' => 'COURIER_NAME',
                'BRANCH.NAME' => 'BRANCH_NAME',
                'DEPARTMENT.NAME' => 'DEPARTMENT_NAME',
                'OPERATOR.NAME' => 'OPERATOR_NAME',
                'OPERATOR.LAST_NAME' => 'OPERATOR_LAST_NAME'
            ],
            'filter' => $filter,
            'order' => $order ?: ['CREATED_DATE' => 'DESC']
        ];

        if ($limit) {
            $parameters['limit'] = $limit;
        }

        if ($offset) {
            $parameters['offset'] = $offset;
        }

        return static::getList($parameters);
    }

    /**
     * Получить статистику заявок
     */
    public static function getStatistics($dateFrom = null, $dateTo = null)
    {
        $filter = [];
        
        if ($dateFrom) {
            $filter['>=CREATED_DATE'] = $dateFrom;
        }
        
        if ($dateTo) {
            $filter['<=CREATED_DATE'] = $dateTo;
        }

        $result = static::getList([
            'select' => [
                'STATUS',
                'CNT'
            ],
            'filter' => $filter,
            'group' => ['STATUS'],
            'runtime' => [
                new Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);

        $stats = [
            'total' => 0,
            'new' => 0,
            'processing' => 0,
            'assigned' => 0,
            'in_delivery' => 0,
            'delivered' => 0,
            'rejected' => 0,
            'cancelled' => 0
        ];

        while ($row = $result->fetch()) {
            $status = strtolower($row['STATUS']);
            $count = (int)$row['CNT'];
            
            $stats['total'] += $count;
            
            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }
        }

        return $stats;
    }

    /**
     * Изменить статус заявки
     */
    public static function changeStatus($id, $newStatus, $comment = '', $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        // Получаем текущую заявку
        $request = static::getById($id)->fetch();
        if (!$request) {
            return false;
        }

        $oldStatus = $request['STATUS'];

        // Обновляем статус
        $updateResult = static::update($id, [
            'STATUS' => $newStatus,
            'UPDATED_DATE' => new DateTime(),
            'UPDATED_BY' => $userId
        ]);

        if ($updateResult->isSuccess()) {
            // Записываем в историю статусов
            StatusHistoryTable::add([
                'REQUEST_ID' => $id,
                'OLD_STATUS' => $oldStatus,
                'NEW_STATUS' => $newStatus,
                'COMMENT' => $comment,
                'CREATED_BY' => $userId
            ]);

            // Генерируем событие
            $event = new \Bitrix\Main\Event('courier.delivery', 'OnDeliveryStatusChange', [
                'DELIVERY_ID' => $id,
                'OLD_STATUS' => $oldStatus,
                'NEW_STATUS' => $newStatus,
                'USER_ID' => $userId,
                'COMMENT' => $comment
            ]);
            $event->send();

            return true;
        }

        return false;
    }

    /**
     * Назначить курьера на заявку
     */
    public static function assignCourier($deliveryId, $courierId, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $updateResult = static::update($deliveryId, [
            'COURIER_ID' => $courierId,
            'STATUS' => 'ASSIGNED',
            'UPDATED_DATE' => new DateTime(),
            'UPDATED_BY' => $userId
        ]);

        if ($updateResult->isSuccess()) {
            // Записываем в историю
            StatusHistoryTable::add([
                'REQUEST_ID' => $deliveryId,
                'OLD_STATUS' => null,
                'NEW_STATUS' => 'ASSIGNED',
                'COMMENT' => "Назначен курьер ID: {$courierId}",
                'CREATED_BY' => $userId
            ]);

            return true;
        }

        return false;
    }

    /**
     * Валидация данных перед сохранением
     */
    public static function onBeforeAdd(Entity\Event $event)
    {
        $result = new Entity\EventResult();
        $data = $event->getParameter('fields');

        // Проверяем уникальность ABS_ID
        if (isset($data['ABS_ID'])) {
            $existing = static::getList([
                'select' => ['ID'],
                'filter' => ['ABS_ID' => $data['ABS_ID']]
            ])->fetch();

            if ($existing) {
                $result->addError(new Entity\FieldError(
                    static::getEntity()->getField('ABS_ID'),
                    'Заявка с таким ABS_ID уже существует'
                ));
            }
        }

        return $result;
    }

    /**
     * Обработка после изменения записи
     */
    public static function onAfterUpdate(Entity\Event $event)
    {
        $primary = $event->getParameter('primary');
        $data = $event->getParameter('fields');

        // Если изменился статус, обновляем соответствующие даты
        if (isset($data['STATUS'])) {
            $updateFields = [];
            
            switch ($data['STATUS']) {
                case 'PROCESSING':
                    $updateFields['PROCESSING_DATE'] = new DateTime();
                    break;
                case 'DELIVERED':
                    $updateFields['DELIVERY_DATE'] = new DateTime();
                    break;
            }

            if (!empty($updateFields)) {
                static::update($primary['ID'], $updateFields);
            }
        }
    }
}