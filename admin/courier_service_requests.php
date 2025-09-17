<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CourierService\Main\RequestTable;
use CourierService\Security\PermissionManager;
use CourierService\Utils\ExcelExporter;

Loader::includeModule('courier_service');

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle(Loc::getMessage('COURIER_SERVICE_REQUESTS_TITLE'));

// Проверка прав доступа
if (!PermissionManager::checkPermission('requests', 'view')) {
    $APPLICATION->ThrowException(Loc::getMessage('COURIER_SERVICE_ACCESS_DENIED'));
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Обработка действий
$action = $_REQUEST['action'] ?? '';
$requestId = intval($_REQUEST['id'] ?? 0);

if ($action === 'delete' && $requestId > 0) {
    if (PermissionManager::checkPermission('requests', 'delete')) {
        if (PermissionManager::checkRequestAccess($requestId, 'delete')) {
            $result = RequestTable::delete($requestId);
            if ($result->isSuccess()) {
                \CourierService\Security\AuditLogger::logRequestChange(
                    $USER->GetID(),
                    $requestId,
                    'delete'
                );
                CAdminMessage::ShowNote(Loc::getMessage('COURIER_SERVICE_REQUEST_DELETED'));
            } else {
                CAdminMessage::ShowMessage([
                    'TYPE' => 'ERROR',
                    'MESSAGE' => Loc::getMessage('COURIER_SERVICE_REQUEST_DELETE_ERROR'),
                    'DETAILS' => implode(', ', $result->getErrorMessages())
                ]);
            }
        } else {
            CAdminMessage::ShowMessage([
                'TYPE' => 'ERROR',
                'MESSAGE' => Loc::getMessage('COURIER_SERVICE_ACCESS_DENIED')
            ]);
        }
    }
}

if ($action === 'export' && PermissionManager::checkPermission('requests', 'export')) {
    $filters = $_REQUEST['filter'] ?? [];
    $requests = RequestTable::getList([
        'filter' => array_merge(PermissionManager::getRequestFilter(), $filters),
        'select' => ['*', 'COURIER.NAME', 'BRANCH.NAME', 'DEPARTMENT.NAME'],
        'order' => ['DATE_CREATE' => 'DESC']
    ])->fetchAll();

    $exporter = new ExcelExporter();
    $result = $exporter->exportRequests($requests, $filters);

    if ($result['success']) {
        \CourierService\Security\AuditLogger::logDataExport(
            $USER->GetID(),
            'requests',
            $filters,
            count($requests)
        );

        LocalRedirect($result['url']);
    } else {
        CAdminMessage::ShowMessage([
            'TYPE' => 'ERROR',
            'MESSAGE' => Loc::getMessage('COURIER_SERVICE_EXPORT_ERROR'),
            'DETAILS' => $result['error']
        ]);
    }
}

// Получение списка заявок
$filter = PermissionManager::getRequestFilter();
$appliedFilters = $_REQUEST['filter'] ?? [];
$filter = array_merge($filter, $appliedFilters);

$requests = RequestTable::getList([
    'filter' => $filter,
    'select' => ['*', 'COURIER.NAME', 'BRANCH.NAME', 'DEPARTMENT.NAME'],
    'order' => ['DATE_CREATE' => 'DESC'],
    'limit' => 50
]);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

// Форма фильтров
$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('COURIER_SERVICE_FILTERS_TAB'),
        'TITLE' => Loc::getMessage('COURIER_SERVICE_FILTERS_TITLE')
    ]
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$tabControl->Begin();
?>

<form method="get" action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%"><?= Loc::getMessage('COURIER_SERVICE_FILTER_DATE_FROM') ?>:</td>
        <td width="60%">
            <input type="date" name="filter[>=CREATED_DATE]" value="<?= htmlspecialchars($appliedFilters['>=CREATED_DATE'] ?? '') ?>">
        </td>
    </tr>
    
    <tr>
        <td><?= Loc::getMessage('COURIER_SERVICE_FILTER_DATE_TO') ?>:</td>
        <td>
            <input type="date" name="filter[<=CREATED_DATE]" value="<?= htmlspecialchars($appliedFilters['<=CREATED_DATE'] ?? '') ?>">
        </td>
    </tr>
    
    <tr>
        <td><?= Loc::getMessage('COURIER_SERVICE_FILTER_STATUS') ?>:</td>
        <td>
            <select name="filter[STATUS]">
                <option value=""><?= Loc::getMessage('COURIER_SERVICE_FILTER_ALL') ?></option>
                <?php foreach (RequestTable::getStatusList() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= ($appliedFilters['STATUS'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    
    <tr>
        <td><?= Loc::getMessage('COURIER_SERVICE_FILTER_CLIENT_NAME') ?>:</td>
        <td>
            <input type="text" name="filter[%CLIENT_NAME]" value="<?= htmlspecialchars($appliedFilters['%CLIENT_NAME'] ?? '') ?>">
        </td>
    </tr>
    
    <tr>
        <td><?= Loc::getMessage('COURIER_SERVICE_FILTER_CLIENT_PHONE') ?>:</td>
        <td>
            <input type="text" name="filter[CLIENT_PHONE]" value="<?= htmlspecialchars($appliedFilters['CLIENT_PHONE'] ?? '') ?>">
        </td>
    </tr>
    
    <?php $tabControl->Buttons(['disabled' => false, 'back_url' => false]); ?>
    
    <input type="submit" name="apply" value="<?= Loc::getMessage('COURIER_SERVICE_FILTER_APPLY') ?>">
    <input type="button" name="reset" value="<?= Loc::getMessage('COURIER_SERVICE_FILTER_RESET') ?>" onclick="location.href='<?= $APPLICATION->GetCurPage() ?>'">
    
    <?php $tabControl->End(); ?>
</form>

<?php $tabControl->End(); ?>

<div style="margin: 20px 0;">
    <?php if (PermissionManager::checkPermission('requests', 'create')): ?>
        <a href="courier_service_request_edit.php?lang=<?= LANGUAGE_ID ?>" class="adm-btn adm-btn-add">
            <?= Loc::getMessage('COURIER_SERVICE_ADD_REQUEST') ?>
        </a>
    <?php endif; ?>
    
    <?php if (PermissionManager::checkPermission('requests', 'export')): ?>
        <a href="?action=export&<?= http_build_query(['filter' => $appliedFilters]) ?>" class="adm-btn">
            <?= Loc::getMessage('COURIER_SERVICE_EXPORT_REQUESTS') ?>
        </a>
    <?php endif; ?>
</div>

<table class="adm-list-table">
    <thead>
        <tr class="adm-list-table-header">
            <td><?= Loc::getMessage('COURIER_SERVICE_REQUEST_NUMBER') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_CLIENT_NAME') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_CLIENT_PHONE') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_STATUS') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_COURIER') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_CREATED_DATE') ?></td>
            <td><?= Loc::getMessage('COURIER_SERVICE_ACTIONS') ?></td>
        </tr>
    </thead>
    <tbody>
        <?php while ($request = $requests->fetch()): ?>
            <tr>
                <td><?= htmlspecialchars($request['REQUEST_NUMBER']) ?></td>
                <td><?= htmlspecialchars($request['CLIENT_NAME']) ?></td>
                <td><?= htmlspecialchars($request['CLIENT_PHONE']) ?></td>
                <td>
                    <span class="adm-status-<?= $request['STATUS'] ?>">
                        <?= htmlspecialchars(RequestTable::getStatusList()[$request['STATUS']] ?? $request['STATUS']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($request['COURIER_NAME'] ?? Loc::getMessage('COURIER_SERVICE_NOT_ASSIGNED')) ?></td>
                <td><?= $request['CREATED_DATE']->format('d.m.Y H:i') ?></td>
                <td>
                    <a href="courier_service_request_view.php?id=<?= $request['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                        <?= Loc::getMessage('COURIER_SERVICE_VIEW') ?>
                    </a>
                    
                    <?php if (PermissionManager::checkRequestAccess($request['ID'], 'edit')): ?>
                        <a href="courier_service_request_edit.php?id=<?= $request['ID'] ?>&lang=<?= LANGUAGE_ID ?>">
                            <?= Loc::getMessage('COURIER_SERVICE_EDIT') ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (PermissionManager::checkRequestAccess($request['ID'], 'delete')): ?>
                        <a href="?action=delete&id=<?= $request['ID'] ?>&<?= bitrix_sessid_get() ?>" 
                           onclick="return confirm('<?= Loc::getMessage('COURIER_SERVICE_DELETE_CONFIRM') ?>')">
                            <?= Loc::getMessage('COURIER_SERVICE_DELETE') ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>