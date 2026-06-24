<?php
/**
 * Viewer Dashboard - Read-Only Auditing
 */
restrict_to(['viewer', 'admin']);

// Fetch basic financial stats
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('Cancelled', 'Pending Payment')")->fetchColumn() ?: 0;
$total_tax = $pdo->query("SELECT SUM(tax_amount) FROM orders WHERE status NOT IN ('Cancelled', 'Pending Payment')")->fetchColumn() ?: 0;
?>

<div class="section-title">
    <i class="fas fa-chart-line" style="color: #3b82f6;"></i>
    Auditor Overview
</div>

<div class="stat-grid">
    <div class="stat-box bg-green-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Verified Revenue</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($total_revenue, 2); ?></div>
        <i class="fas fa-dollar-sign"></i>
    </div>
    
    <div class="stat-box bg-blue-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Tax Collected</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);">$<?php echo number_format($total_tax, 2); ?></div>
        <i class="fas fa-file-invoice-dollar"></i>
    </div>
</div>

<div class="card">
    <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
        <i class="fas fa-book" style="color: var(--secondary);"></i> Order Ledger
    </h3>
    <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Access the master order list for read-only auditing and reconciliation.</p>
    <a href='/bolakausa/admin/orders' class="btn btn-blue">
        <i class="fas fa-search"></i> View Order Ledger
    </a>
</div>
