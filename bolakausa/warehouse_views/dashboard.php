<?php
/**
 * Warehouse Dashboard - Logistics & Inventory
 */
restrict_to(['warehouse', 'admin']);

// Fetch basic logistics stats
$pending_shipments = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('Processing', 'Payment Verified')")->fetchColumn();
?>

<div class="section-title">
    <i class="fas fa-boxes" style="color: var(--primary);"></i>
    Warehouse Logistics
</div>

<div class="stat-grid">
    <div class="stat-box bg-blue-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Pending Shipments</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $pending_shipments; ?></div>
        <i class="fas fa-shipping-fast"></i>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2.5rem;">
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-clipboard-list" style="color: var(--primary);"></i> Fulfillment Queue
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">View verified orders and update their status to "Shipped" or "Delivered".</p>
        <a href='/bolakausa/admin/orders' class="btn btn-green">
            <i class="fas fa-shipping-fast"></i> Ship Orders
        </a>
    </div>

    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-dolly" style="color: #3b82f6;"></i> Stock Inbound
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Log physical inventory receipts into the digital system.</p>
        <a href='/bolakausa/manager/stock' class="btn btn-blue">
            <i class="fas fa-plus"></i> Add Stock
        </a>
    </div>
</div>
