<?php
namespace CourierService\EventHandlers;

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;

class Main
{
    /**
     * Добавление пункта в главное меню
     */
    public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        global $USER;

        if (!\CourierService\Security\PermissionManager::checkPermission('requests', 'view')) {
            return;
        }

        $aModuleMenu[] = [
            'parent_menu' => 'global_menu_services',
            'section' => 'courier_service',
            'sort' => 100,
            'text' => Loc::getMessage('COURIER_SERVICE_MENU_TITLE'),
            'title' => Loc::getMessage('COURIER_SERVICE_MENU_TITLE'),
            'url' => 'courier_service_requests.php?lang=' . LANGUAGE_ID,
            'icon' => 'courier_service_menu_icon',
            'page_icon' => 'courier_service_page_icon',
            'items_id' => 'menu_courier_service',
            'items' => [
                [
                    'text' => Loc::getMessage('COURIER_SERVICE_MENU_REQUESTS'),
                    'title' => Loc::getMessage('COURIER_SERVICE_MENU_REQUESTS'),
                    'url' => 'courier_service_requests.php?lang=' . LANGUAGE_ID,
                    'more_url' => [
                        'courier_service_request_edit.php',
                        'courier_service_request_view.php'
                    ]
                ],
                [
                    'text' => Loc::getMessage('COURIER_SERVICE_MENU_COURIERS'),
                    'title' => Loc::getMessage('COURIER_SERVICE_MENU_COURIERS'),
                    'url' => 'courier_service_couriers.php?lang=' . LANGUAGE_ID,
                    'more_url' => [
                        'courier_service_courier_edit.php',
                        'courier_service_courier_view.php'
                    ]
                ],
                [
                    'text' => Loc::getMessage('COURIER_SERVICE_MENU_MAP'),
                    'title' => Loc::getMessage('COURIER_SERVICE_MENU_MAP'),
                    'url' => 'courier_service_map.php?lang=' . LANGUAGE_ID
                ],
                [
                    'text' => Loc::getMessage('COURIER_SERVICE_MENU_REPORTS'),
                    'title' => Loc::getMessage('COURIER_SERVICE_MENU_REPORTS'),
                    'url' => 'courier_service_reports.php?lang=' . LANGUAGE_ID
                ]
            ]
        ];

        // Добавляем пункты для администраторов
        if (\CourierService\Security\PermissionManager::getUserRole() === 'COURIER_ADMIN') {
            $aModuleMenu[count($aModuleMenu) - 1]['items'][] = [
                'text' => Loc::getMessage('COURIER_SERVICE_MENU_BRANCHES'),
                'title' => Loc::getMessage('COURIER_SERVICE_MENU_BRANCHES'),
                'url' => 'courier_service_branches.php?lang=' . LANGUAGE_ID,
                'more_url' => [
                    'courier_service_branch_edit.php'
                ]
            ];

            $aModuleMenu[count($aModuleMenu) - 1]['items'][] = [
                'text' => Loc::getMessage('COURIER_SERVICE_MENU_SETTINGS'),
                'title' => Loc::getMessage('COURIER_SERVICE_MENU_SETTINGS'),
                'url' => 'courier_service_settings.php?lang=' . LANGUAGE_ID
            ];

            $aModuleMenu[count($aModuleMenu) - 1]['items'][] = [
                'text' => Loc::getMessage('COURIER_SERVICE_MENU_LOGS'),
                'title' => Loc::getMessage('COURIER_SERVICE_MENU_LOGS'),
                'url' => 'courier_service_logs.php?lang=' . LANGUAGE_ID
            ];
        }
    }

    /**
     * Обработка события после авторизации пользователя
     */
    public static function OnAfterUserLogin($arUser)
    {
        if (isset($arUser['user_fields']['ID'])) {
            \CourierService\Security\AuditLogger::logLogin($arUser['user_fields']['ID'], true);
        }
    }

    /**
     * Обработка события при неудачной авторизации
     */
    public static function OnUserLogin($arUser)
    {
        if (isset($arUser['LOGIN'])) {
            \CourierService\Security\AuditLogger::logLogin(0, false, 'Invalid credentials for: ' . $arUser['LOGIN']);
        }
    }

    /**
     * Обработка события при выходе пользователя
     */
    public static function OnUserLogout($arUser)
    {
        if (isset($arUser['ID'])) {
            \CourierService\Security\AuditLogger::logLogout($arUser['ID']);
        }
    }

    /**
     * Обработка события изменения пользователя
     */
    public static function OnUserUpdate($arFields)
    {
        if (isset($arFields['ID'])) {
            \CourierService\Security\AuditLogger::logAction(
                $GLOBALS['USER']->GetID(),
                'user_update',
                'user',
                $arFields['ID'],
                null,
                $arFields
            );
        }
    }

    /**
     * Обработка события добавления пользователя
     */
    public static function OnUserAdd($arFields)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'user_add',
            'user',
            0,
            null,
            $arFields
        );
    }

    /**
     * Обработка события удаления пользователя
     */
    public static function OnUserDelete($userId)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'user_delete',
            'user',
            $userId,
            null,
            null
        );
    }

    /**
     * Обработка события изменения группы пользователей
     */
    public static function OnGroupUpdate($arFields)
    {
        if (isset($arFields['ID'])) {
            \CourierService\Security\AuditLogger::logAction(
                $GLOBALS['USER']->GetID(),
                'group_update',
                'group',
                $arFields['ID'],
                null,
                $arFields
            );
        }
    }

    /**
     * Обработка события добавления группы пользователей
     */
    public static function OnGroupAdd($arFields)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'group_add',
            'group',
            0,
            null,
            $arFields
        );
    }

    /**
     * Обработка события удаления группы пользователей
     */
    public static function OnGroupDelete($groupId)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'group_delete',
            'group',
            $groupId,
            null,
            null
        );
    }

    /**
     * Обработка события изменения настроек модуля
     */
    public static function OnModuleSettingsUpdate($moduleId, $arFields)
    {
        if ($moduleId === 'courier_service') {
            \CourierService\Security\AuditLogger::logAction(
                $GLOBALS['USER']->GetID(),
                'module_settings_update',
                'module',
                0,
                null,
                $arFields
            );
        }
    }

    /**
     * Обработка события загрузки файла
     */
    public static function OnFileUpload($arFile)
    {
        // Проверяем, что файл загружается в контексте курьерской службы
        if (strpos($arFile['tmp_name'], 'courier_service') !== false) {
            \CourierService\Security\AuditLogger::logAction(
                $GLOBALS['USER']->GetID(),
                'file_upload',
                'file',
                0,
                null,
                [
                    'filename' => $arFile['name'],
                    'size' => $arFile['size'],
                    'type' => $arFile['type']
                ]
            );
        }
    }

    /**
     * Обработка события удаления файла
     */
    public static function OnFileDelete($filePath)
    {
        if (strpos($filePath, 'courier_service') !== false) {
            \CourierService\Security\AuditLogger::logAction(
                $GLOBALS['USER']->GetID(),
                'file_delete',
                'file',
                0,
                null,
                ['file_path' => $filePath]
            );
        }
    }

    /**
     * Обработка события изменения прав доступа
     */
    public static function OnAccessUpdate($arFields)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'access_update',
            'access',
            0,
            null,
            $arFields
        );
    }

    /**
     * Обработка события ошибки системы
     */
    public static function OnException($exception)
    {
        \CourierService\Security\AuditLogger::logError(
            'System',
            'exception',
            $exception->getMessage(),
            [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        );
    }

    /**
     * Обработка события начала сессии
     */
    public static function OnSessionStart()
    {
        // Инициализация сессии для курьерской службы
        if (!isset($_SESSION['courier_service'])) {
            $_SESSION['courier_service'] = [
                'last_activity' => time(),
                'permissions_checked' => false
            ];
        }
    }

    /**
     * Обработка события окончания сессии
     */
    public static function OnSessionEnd()
    {
        if (isset($_SESSION['courier_service']['user_id'])) {
            \CourierService\Security\AuditLogger::logLogout($_SESSION['courier_service']['user_id']);
        }
    }

    /**
     * Обработка события изменения конфигурации
     */
    public static function OnConfigUpdate($arFields)
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'config_update',
            'config',
            0,
            null,
            $arFields
        );
    }

    /**
     * Обработка события очистки кеша
     */
    public static function OnCacheClear()
    {
        \CourierService\Security\AuditLogger::logAction(
            $GLOBALS['USER']->GetID(),
            'cache_clear',
            'system',
            0,
            null,
            ['timestamp' => time()]
        );
    }
}