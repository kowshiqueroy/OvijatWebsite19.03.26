<?php
/**
 * modules/reports/all.php
 */
include '../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="fas fa-chart-bar fa-4x text-primary mb-3"></i>
                <h5 class="fw-bold">Sales Reports</h5>
                <p class="text-muted small">Daily, weekly, and monthly sales performance analysis.</p>
                <a href="sales.php" class="btn btn-primary w-100">View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="fas fa-box-open fa-4x text-success mb-3"></i>
                <h5 class="fw-bold">Inventory Reports</h5>
                <p class="text-muted small">Current stock status, low stock alerts, and movement history.</p>
                <a href="../inventory/stock.php" class="btn btn-success w-100">View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="fas fa-file-invoice-dollar fa-4x text-info mb-3"></i>
                <h5 class="fw-bold">Financial Reports</h5>
                <p class="text-muted small">Customer ledgers, payment history, and outstanding balances.</p>
                <a href="../finance/ledger.php" class="btn btn-info text-white w-100">View Report</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
