<?php
/**
 * Manager Dashboard - Premium Redesign
 */
restrict_to(['admin', 'manager']);

// Quick stats for manager
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5")->fetchColumn();
?>

<div class="section-title">
    <i class="fas fa-tasks" style="color: var(--primary);"></i>
    Operations Center
</div>

<div class="stat-grid">
    <div class="stat-box bg-blue-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Inventory</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $total_products; ?> Items</div>
        <i class="fas fa-boxes"></i>
    </div>
    
    <div class="stat-box bg-red-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Low Stock Warning</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $low_stock; ?> Alerts</div>
        <i class="fas fa-exclamation-circle"></i>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem;">
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-plus-circle" style="color: var(--primary);"></i> Stock Inbound
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Record new shipments and update inventory levels for existing wholesale products.</p>
        <a href='/bolakausa/manager/stock' class="btn btn-green">
            <i class="fas fa-dolly"></i> Stock Management
        </a>
    </div>
    
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-edit" style="color: #3b82f6;"></i> Product Catalog
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Add new products to the catalog or update pricing and descriptions for partners.</p>
        <a href='/bolakausa/manager/products' class="btn btn-blue">
            <i class="fas fa-box-open"></i> Update Catalog
        </a>
    </div>
</div>
