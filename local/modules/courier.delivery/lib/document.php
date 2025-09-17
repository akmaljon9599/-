<?php
namespace Courier\Delivery;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для работы с документами
 */
class DocumentTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'courier_delivery_documents';
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
            
            new Entity\EnumField('DOCUMENT_TYPE', [
                'values' => [
                    'PASSPORT_SCAN', 'CONTRACT', 'DELIVERY_PHOTO', 
                    'SIGNATURE', 'OTHER'
                ],
                'required' => true
            ]),
            
            new Entity\StringField('FILE_NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255)
                    ];
                }
            ]),
            
            new Entity\StringField('FILE_PATH', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 500)
                    ];
                }
            ]),
            
            new Entity\IntegerField('FILE_SIZE', [
                'required' => true
            ]),
            
            new Entity\StringField('MIME_TYPE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 100)
                    ];
                }
            ]),
            
            new Entity\BooleanField('IS_SIGNED', [
                'values' => ['N', 'Y'],
                'default_value' => 'N'
            ]),
            
            new Entity\TextField('SIGNATURE_DATA'),
            
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
     * Получить документы по заявке
     */
    public static function getDocumentsByRequest($requestId, $documentType = null)
    {
        $filter = ['REQUEST_ID' => $requestId];
        
        if ($documentType) {
            $filter['DOCUMENT_TYPE'] = $documentType;
        }

        return static::getList([
            'select' => [
                'ID', 'DOCUMENT_TYPE', 'FILE_NAME', 'FILE_PATH', 
                'FILE_SIZE', 'MIME_TYPE', 'IS_SIGNED', 'CREATED_DATE',
                'CREATOR.NAME' => 'CREATOR_NAME',
                'CREATOR.LAST_NAME' => 'CREATOR_LAST_NAME'
            ],
            'filter' => $filter,
            'order' => ['CREATED_DATE' => 'DESC']
        ]);
    }

    /**
     * Добавить документ
     */
    public static function addDocument($requestId, $documentType, $fileData, $userId = null, $signatureData = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $fields = [
            'REQUEST_ID' => $requestId,
            'DOCUMENT_TYPE' => $documentType,
            'FILE_NAME' => $fileData['name'],
            'FILE_PATH' => $fileData['path'],
            'FILE_SIZE' => $fileData['size'],
            'MIME_TYPE' => $fileData['type'],
            'CREATED_BY' => $userId
        ];

        if ($signatureData) {
            $fields['IS_SIGNED'] = 'Y';
            $fields['SIGNATURE_DATA'] = $signatureData;
        }

        $result = static::add($fields);

        if ($result->isSuccess()) {
            // Логируем добавление документа
            \Courier\Delivery\Util\Logger::log(
                "Document added to request #{$requestId}: {$documentType}",
                'DOCUMENT_ADD',
                $userId
            );

            return $result->getId();
        }

        return false;
    }

    /**
     * Добавить подпись к документу
     */
    public static function addSignature($documentId, $signatureData, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $updateResult = static::update($documentId, [
            'IS_SIGNED' => 'Y',
            'SIGNATURE_DATA' => $signatureData
        ]);

        if ($updateResult->isSuccess()) {
            // Логируем добавление подписи
            \Courier\Delivery\Util\Logger::log(
                "Signature added to document #{$documentId}",
                'DOCUMENT_SIGN',
                $userId
            );

            return true;
        }

        return false;
    }

    /**
     * Получить статистику по документам
     */
    public static function getDocumentStatistics($dateFrom = null, $dateTo = null)
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
                'DOCUMENT_TYPE',
                'IS_SIGNED',
                'CNT'
            ],
            'filter' => $filter,
            'group' => ['DOCUMENT_TYPE', 'IS_SIGNED'],
            'runtime' => [
                new Entity\ExpressionField('CNT', 'COUNT(*)')
            ]
        ]);

        $stats = [
            'total' => 0,
            'signed' => 0,
            'by_type' => []
        ];

        while ($row = $result->fetch()) {
            $type = $row['DOCUMENT_TYPE'];
            $isSigned = $row['IS_SIGNED'] === 'Y';
            $count = (int)$row['CNT'];
            
            $stats['total'] += $count;
            
            if ($isSigned) {
                $stats['signed'] += $count;
            }
            
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = [
                    'total' => 0,
                    'signed' => 0
                ];
            }
            
            $stats['by_type'][$type]['total'] += $count;
            if ($isSigned) {
                $stats['by_type'][$type]['signed'] += $count;
            }
        }

        return $stats;
    }

    /**
     * Проверить права доступа к документу
     */
    public static function checkAccess($documentId, $userId)
    {
        $document = static::getList([
            'select' => [
                'ID', 'REQUEST_ID', 'DOCUMENT_TYPE',
                'REQUEST.COURIER_ID' => 'COURIER_ID',
                'REQUEST.CREATED_BY' => 'REQUEST_CREATOR',
                'REQUEST.OPERATOR_ID' => 'OPERATOR_ID'
            ],
            'filter' => ['ID' => $documentId]
        ])->fetch();

        if (!$document) {
            return false;
        }

        // Проверяем роли пользователя
        $roleManager = new \Courier\Delivery\Util\RoleManager();
        $userRoles = $roleManager->getUserRoles($userId);

        // Администраторы имеют доступ ко всем документам
        if (in_array('COURIER_ADMIN', $userRoles)) {
            return true;
        }

        // Старшие курьеры имеют доступ ко всем документам своего филиала
        if (in_array('COURIER_SENIOR', $userRoles)) {
            // Здесь нужна дополнительная проверка филиала
            return true;
        }

        // Курьер имеет доступ к документам своих заявок
        if (in_array('COURIER_DELIVERY', $userRoles) && $document['COURIER_ID'] == $userId) {
            return true;
        }

        // Оператор имеет доступ к документам созданных им заявок
        if (in_array('COURIER_OPERATOR', $userRoles) && 
            ($document['REQUEST_CREATOR'] == $userId || $document['OPERATOR_ID'] == $userId)) {
            return true;
        }

        return false;
    }

    /**
     * Получить путь к файлу документа
     */
    public static function getDocumentPath($documentId)
    {
        $document = static::getById($documentId)->fetch();
        
        if ($document && file_exists($_SERVER['DOCUMENT_ROOT'] . $document['FILE_PATH'])) {
            return $_SERVER['DOCUMENT_ROOT'] . $document['FILE_PATH'];
        }

        return false;
    }

    /**
     * Удалить документ
     */
    public static function deleteDocument($documentId, $userId = null)
    {
        global $USER;
        
        if (!$userId && is_object($USER)) {
            $userId = $USER->GetID();
        }

        $document = static::getById($documentId)->fetch();
        if (!$document) {
            return false;
        }

        // Удаляем файл с диска
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $document['FILE_PATH'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Удаляем запись из БД
        $deleteResult = static::delete($documentId);

        if ($deleteResult->isSuccess()) {
            // Логируем удаление
            \Courier\Delivery\Util\Logger::log(
                "Document deleted: #{$documentId} ({$document['DOCUMENT_TYPE']})",
                'DOCUMENT_DELETE',
                $userId
            );

            return true;
        }

        return false;
    }

    /**
     * Валидация файла перед загрузкой
     */
    public static function validateFile($fileData, $documentType)
    {
        $errors = [];

        // Проверяем размер файла
        $maxSize = \Bitrix\Main\Config\Option::get('courier.delivery', 'max_file_size', 10485760); // 10MB по умолчанию
        if ($fileData['size'] > $maxSize) {
            $errors[] = 'Размер файла превышает допустимый лимит (' . round($maxSize / 1048576, 1) . ' МБ)';
        }

        // Проверяем тип файла
        $allowedTypes = \Bitrix\Main\Config\Option::get('courier.delivery', 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx');
        $allowedTypes = explode(',', $allowedTypes);
        
        $fileExtension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'Недопустимый тип файла. Разрешены: ' . implode(', ', $allowedTypes);
        }

        // Специальные проверки для разных типов документов
        switch ($documentType) {
            case 'DELIVERY_PHOTO':
                if (!in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                    $errors[] = 'Для фотографий доставки разрешены только изображения (JPG, PNG)';
                }
                break;
                
            case 'SIGNATURE':
                if (!in_array($fileExtension, ['png', 'jpg', 'jpeg'])) {
                    $errors[] = 'Для подписи разрешены только изображения (PNG, JPG)';
                }
                break;
        }

        return $errors;
    }
}