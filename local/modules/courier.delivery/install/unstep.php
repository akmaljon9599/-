<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

if (!check_bitrix_sessid()) return;
?>

<form action="<?= $APPLICATION->GetCurPage() ?>">
    <div class="adm-info-message-wrap">
        <div class="adm-info-message">
            <div class="adm-info-message-title">Модуль "Курьерская служба" успешно удален</div>
            <div class="adm-info-message-body">
                <p>Модуль курьерской службы был полностью удален из системы.</p>
                
                <h4>Что было удалено:</h4>
                <ul>
                    <li>Все таблицы базы данных</li>
                    <li>Группы пользователей</li>
                    <li>Административные файлы</li>
                    <li>Настройки модуля</li>
                    <li>Агенты и обработчики событий</li>
                </ul>
                
                <p><strong>Внимание:</strong> Все данные о заявках, курьерах и документах были безвозвратно удалены.</p>
                
                <div class="adm-info-message-icon"></div>
            </div>
        </div>
    </div>
    
    <p>
        <input type="button" value="Закрыть" onclick="window.close();" class="adm-btn">
    </p>
</form>