<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>

<div class="courier-map-container">
    <div class="courier-map-header">
        <h3><?= Loc::getMessage('COURIER_SERVICE_MAP_TITLE') ?></h3>
        <div class="courier-map-controls">
            <?php if ($arResult['AUTO_REFRESH']): ?>
                <span class="auto-refresh-indicator">
                    <i class="fa fa-sync-alt"></i>
                    <?= Loc::getMessage('COURIER_SERVICE_AUTO_REFRESH') ?>
                </span>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshCourierMap()">
                <i class="fa fa-sync-alt"></i>
                <?= Loc::getMessage('COURIER_SERVICE_REFRESH_MAP') ?>
            </button>
        </div>
    </div>
    
    <div class="courier-map-wrapper">
        <div id="<?= $arResult['MAP_ID'] ?>" 
             class="courier-map" 
             style="width: <?= $arResult['MAP_WIDTH'] ?>; height: <?= $arResult['MAP_HEIGHT'] ?>;">
        </div>
        
        <?php if (!empty($arResult['ERROR'])): ?>
            <div class="courier-map-error">
                <i class="fa fa-exclamation-triangle"></i>
                <?= htmlspecialchars($arResult['ERROR']) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="courier-map-legend">
        <div class="legend-item">
            <span class="legend-color" style="background-color: #28a745;"></span>
            <?= Loc::getMessage('COURIER_SERVICE_STATUS_ACTIVE') ?>
        </div>
        <div class="legend-item">
            <span class="legend-color" style="background-color: #ffc107;"></span>
            <?= Loc::getMessage('COURIER_SERVICE_STATUS_ON_DELIVERY') ?>
        </div>
        <div class="legend-item">
            <span class="legend-color" style="background-color: #6c757d;"></span>
            <?= Loc::getMessage('COURIER_SERVICE_STATUS_INACTIVE') ?>
        </div>
    </div>
    
    <div class="courier-map-stats">
        <div class="stat-item">
            <span class="stat-label"><?= Loc::getMessage('COURIER_SERVICE_TOTAL_COURIERS') ?>:</span>
            <span class="stat-value" id="total-couriers"><?= count($arResult['COURIERS']) ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?= Loc::getMessage('COURIER_SERVICE_ONLINE_COURIERS') ?>:</span>
            <span class="stat-value" id="online-couriers"><?= count(array_filter($arResult['COURIERS'], function($c) { return $c['status'] === 'active'; })) ?></span>
        </div>
    </div>
</div>

<style>
.courier-map-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.courier-map-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.courier-map-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #495057;
}

.courier-map-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.auto-refresh-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #6c757d;
    font-size: 14px;
}

.auto-refresh-indicator i {
    animation: spin 2s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.courier-map-wrapper {
    position: relative;
}

.courier-map {
    border: none;
}

.courier-map-error {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 4px;
    text-align: center;
    z-index: 1000;
}

.courier-map-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #495057;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.courier-map-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    padding: 15px 20px;
    background: #e9ecef;
    border-top: 1px solid #dee2e6;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.stat-label {
    color: #6c757d;
}

.stat-value {
    font-weight: 600;
    color: #495057;
}

@media (max-width: 768px) {
    .courier-map-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .courier-map-legend {
        flex-direction: column;
        gap: 10px;
    }
    
    .courier-map-stats {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
function refreshCourierMap() {
    if (typeof window.courierMap !== 'undefined') {
        // Обновляем данные курьеров
        fetch('<?= $this->getPath() ?>/ajax/get_couriers.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем маркеры
                    if (typeof updateCourierMarkers === 'function') {
                        updateCourierMarkers(data.couriers);
                    }
                    
                    // Обновляем статистику
                    document.getElementById('total-couriers').textContent = data.couriers.length;
                    document.getElementById('online-couriers').textContent = 
                        data.couriers.filter(c => c.status === 'active').length;
                }
            })
            .catch(error => {
                console.error('Ошибка обновления карты:', error);
            });
    }
}
</script>

<?= $arResult['MAP_INIT_SCRIPT'] ?>