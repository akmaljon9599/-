<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

if (!check_bitrix_sessid()) return;
?>

<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="courier.delivery">
    <input type="hidden" name="install" value="Y">
    <input type="hidden" name="step" value="2">
    
    <div class="adm-info-message-wrap">
        <div class="adm-info-message">
            <div class="adm-info-message-title">Модуль "Курьерская служба" успешно установлен!</div>
            <div class="adm-info-message-body">
                <p>Модуль курьерской службы для управления доставкой банковских карт был успешно установлен.</p>
                
                <h4>Что было создано:</h4>
                <ul>
                    <li>Таблицы базы данных для хранения заявок, курьеров и документов</li>
                    <li>Группы пользователей для разных ролей системы</li>
                    <li>Административные страницы для управления модулем</li>
                    <li>REST API для интеграции с внешними системами</li>
                    <li>Агенты для фоновых задач</li>
                </ul>
                
                <h4>Следующие шаги:</h4>
                <ol>
                    <li>Перейдите в <a href="/bitrix/admin/courier_delivery_settings.php">настройки модуля</a> для конфигурации</li>
                    <li>Настройте интеграцию с АБС банка</li>
                    <li>Добавьте API ключ для Яндекс.Карт</li>
                    <li>Настройте SMS-сервис для уведомлений</li>
                    <li>Назначьте пользователей в соответствующие группы</li>
                </ol>
                
                <div class="adm-info-message-icon"></div>
            </div>
        </div>
    </div>
    
    <p>
        <input type="submit" name="inst" value="Перейти к настройкам" class="adm-btn-save">
        <input type="button" value="Закрыть" onclick="window.close();" class="adm-btn">
    </p>
</form>