<?php
/** @var array $dashboardData */
$insights = $dashboardData['inventory_insights'] ?? [];
?>

<div class="dashboard-panel inventory-insights-panel">
    <div class="chart-header">
        <div class="chart-title">
            <h4>📊 Inventory Insights</h4>
            <p class="chart-subtitle">Quick operational highlights from current inventory activity</p>
        </div>
    </div>

    <div class="inventory-insights-grid">
        <div class="insight-item">
            <span class="insight-icon">🔥</span>
            <div>
                <strong>Best Seller</strong>
                <p><?= htmlspecialchars((string) ($insights['best_seller'] ?? 'None')); ?></p>
            </div>
        </div>

        <div class="insight-item">
            <span class="insight-icon">📦</span>
            <div>
                <strong>Highest Stock</strong>
                <p><?= htmlspecialchars((string) ($insights['highest_stock'] ?? 'None')); ?></p>
            </div>
        </div>

        <div class="insight-item">
            <span class="insight-icon">⚠️</span>
            <div>
                <strong>Low Stock</strong>
                <p><?= htmlspecialchars((string) ($insights['low_stock_product'] ?? 'None')); ?></p>
            </div>
        </div>

        <div class="insight-item">
            <span class="insight-icon">⏰</span>
            <div>
                <strong>Expiring Soon</strong>
                <p><?= htmlspecialchars((string) ($insights['expiring_product'] ?? 'None')); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.inventory-insights-panel { min-height: 0 !important; }
.inventory-insights-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.inventory-insights-grid .insight-item { min-width:0; min-height:90px; margin:0; padding:14px; display:flex; align-items:flex-start; gap:10px; border-left:4px solid #118d6d; background:#f8fafc; border-radius:10px; }
.inventory-insights-grid .insight-icon { font-size:20px; line-height:1; }
.inventory-insights-grid .insight-item strong { display:block; margin:0 0 5px; color:#118d6d; font-size:12px; }
.inventory-insights-grid .insight-item p { margin:0; color:#374151; font-weight:600; font-size:13px; line-height:1.35; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
@media (max-width:1000px) { .inventory-insights-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:600px) { .inventory-insights-grid { grid-template-columns:1fr; } }
</style>
