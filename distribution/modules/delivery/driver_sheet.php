<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$load_id = intval($_GET['load_id'] ?? 0);

// List of loads if no specific one selected
if (!$load_id) {
    $loads = fetch_all("SELECT tl.*,
        (SELECT COUNT(tli.id) FROM truck_load_items tli WHERE tli.truck_load_id = tl.id AND tli.isDelete=0) as invoice_count,
        (SELECT SUM(s.grand_total) FROM truck_load_items tli JOIN sales_drafts s ON tli.invoice_id = s.id WHERE tli.truck_load_id = tl.id AND tli.isDelete=0) as load_value
        FROM truck_loads tl WHERE tl.isDelete=0 ORDER BY tl.created_at DESC LIMIT 50");
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fa-solid fa-file-lines me-2"></i>Driver Sheet</h3>
        <p class="text-muted small">Select a truck load to generate the driver sheet.</p>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Load #</th><th>Truck</th><th>Driver</th><th>Status</th><th class="text-center">Invoices</th><th class="text-end">Value</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($loads as $l): ?>
                    <tr>
                        <td><strong>#<?php echo $l['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($l['truck_no']); ?></td>
                        <td><?php echo htmlspecialchars($l['driver_name']); ?></td>
                        <td><span class="badge bg-info"><?php echo $l['status']; ?></span></td>
                        <td class="text-center"><?php echo $l['invoice_count']; ?></td>
                        <td class="text-end fw-bold"><?php echo format_currency($l['load_value']); ?></td>
                        <td><a href="?load_id=<?php echo $l['id']; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-lines me-1"></i>View Sheet</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($loads)): ?><tr><td colspan="7" class="text-center py-5 text-muted">No truck loads found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    require_once '../../templates/footer.php';
    exit;
}

// Specific load sheet
$load = fetch_one("SELECT * FROM truck_loads WHERE id = ? AND isDelete = 0", [$load_id]);
if (!$load) redirect('modules/delivery/driver_sheet.php', 'Load not found.', 'danger');

$invoices = fetch_all(
    "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address
     FROM truck_load_items tli
     JOIN sales_drafts s ON tli.invoice_id = s.id
     JOIN customers c ON s.customer_id = c.id
     WHERE tli.truck_load_id = ? AND tli.isDelete = 0
     ORDER BY c.name ASC",
    [$load_id]
);

$total_value = array_sum(array_column($invoices, 'grand_total'));
$company = get_company_settings();
?>

<style>
    @media print {
        .no-print { display:none!important; }
        body { background:#fff!important; font-size:11px; }
        .card { box-shadow:none!important; border:1px solid #ccc!important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <a href="driver_sheet.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fa-solid fa-arrow-left"></i></a>
        <strong>Driver Sheet — Load #<?php echo $load_id; ?></strong>
    </div>
    <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
</div>

<!-- Header -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4 class="fw-bold"><?php echo htmlspecialchars($company['name'] ?? ''); ?></h4>
                <p class="text-muted small mb-0"><?php echo htmlspecialchars($company['address'] ?? ''); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <h5 class="fw-bold">DRIVER DELIVERY SHEET</h5>
                <p class="small mb-0">Load #<?php echo $load_id; ?> &nbsp;|&nbsp; Date: <?php echo date('d M Y', strtotime($load['created_at'])); ?></p>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-sm-4"><span class="text-muted small">Truck No</span><div class="fw-bold"><?php echo htmlspecialchars($load['truck_no']); ?></div></div>
            <div class="col-sm-4"><span class="text-muted small">Driver</span><div class="fw-bold"><?php echo htmlspecialchars($load['driver_name']); ?></div><?php if (!empty($load['driver_phone'])): ?><div class="small"><?php echo $load['driver_phone']; ?></div><?php endif; ?></div>
            <div class="col-sm-4"><span class="text-muted small">Route</span><div class="small"><?php echo htmlspecialchars($load['source_location'] ?? ''); ?> → <?php echo htmlspecialchars($load['destination_location'] ?? ''); ?></div></div>
        </div>
    </div>
</div>

<!-- Delivery list -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <strong><?php echo count($invoices); ?> Deliveries</strong>
        <strong class="text-accent">Total: <?php echo format_currency($total_value); ?></strong>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0" style="font-size:12px;">
            <thead class="table-dark">
                <tr><th>#</th><th>Invoice</th><th>Customer</th><th>Phone</th><th>Address</th><th class="text-end">Amount</th><th>Signature</th></tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $i => $inv): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><strong>#<?php echo str_pad($inv['id'],6,'0',STR_PAD_LEFT); ?></strong></td>
                    <td><?php echo htmlspecialchars($inv['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($inv['customer_phone']); ?></td>
                    <td class="small"><?php echo htmlspecialchars($inv['customer_address'] ?? ''); ?></td>
                    <td class="text-end fw-bold"><?php echo format_currency($inv['grand_total']); ?></td>
                    <td style="min-width:100px;">&nbsp;</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-secondary fw-bold">
                <tr>
                    <td colspan="5" class="text-end">TOTAL</td>
                    <td class="text-end"><?php echo format_currency($total_value); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Signature strip -->
<div class="row g-3 mt-4">
    <?php foreach (['Prepared By','Driver Signature','Warehouse Out','Authorised By'] as $label): ?>
    <div class="col-3 text-center">
        <div style="border-bottom:2px solid #000;height:40px;margin-bottom:6px;"></div>
        <div class="small fw-bold text-uppercase"><?php echo $label; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>
