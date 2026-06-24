<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER]);

$date   = $_GET['date'] ?? date('Y-m-d');
$sr_id  = intval($_GET['sr_id'] ?? 0);
$div_id = intval($_GET['div_id'] ?? 0);

// Build filter for confirmed invoices on this date
$sql  = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, c.type as customer_type,
                u.username as creator_name
         FROM sales_drafts s
         JOIN customers c ON s.customer_id = c.id
         JOIN users u ON s.created_by = u.id
         WHERE s.status = 'Confirmed' AND s.isDelete = 0 AND c.isDelete = 0
           AND DATE(s.confirmed_at) = ?";
$params = [$date];
if ($sr_id)  { $sql .= " AND s.created_by = ?";    $params[] = $sr_id; }
if ($div_id) { $sql .= " AND u.division_id = ?";   $params[] = $div_id; }
$sql .= " ORDER BY c.name ASC";
$invoices = fetch_all($sql, $params);

// Also load payment receipts on this date
$pay_sql = "SELECT t.*, c.name as customer_name FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE t.type='Credit' AND t.isDelete=0 AND DATE(t.created_at) = ? ORDER BY c.name";
$payments = fetch_all($pay_sql, [$date]);

$srs       = fetch_all("SELECT id, username FROM users WHERE role=? AND isDelete=0 ORDER BY username", [ROLE_SR]);
$divisions = fetch_all("SELECT id, name FROM sr_divisions WHERE isDelete=0 ORDER BY name");
$company   = get_company_settings();

$total_invoiced = array_sum(array_column($invoices, 'grand_total'));
$total_collected = array_sum(array_column($payments, 'amount'));
?>

<style>@media print { .no-print{display:none!important;} body{font-size:11px;} }</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h3><i class="fa-solid fa-hand-holding-dollar me-2"></i>Collection Sheet</h3>
    <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
</div>

<!-- Filters -->
<div class="card mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">Date</label><input type="date" name="date" class="form-control form-control-sm" value="<?php echo $date; ?>"></div>
            <div class="col-md-3"><label class="form-label small">SR</label>
                <select name="sr_id" class="form-select form-select-sm">
                    <option value="">All SRs</option>
                    <?php foreach ($srs as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $sr_id==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['username']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label small">Division</label>
                <select name="div_id" class="form-select form-select-sm">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo $div_id==$d['id']?'selected':''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Load</button></div>
        </form>
    </div>
</div>

<!-- Print Header -->
<div class="text-center mb-3 d-none d-print-block">
    <h4><?php echo htmlspecialchars($company['name'] ?? ''); ?></h4>
    <h5>DAILY COLLECTION SHEET — <?php echo date('d M Y', strtotime($date)); ?></h5>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4 no-print">
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #6366f1;">
            <div class="stat-label">Invoices Issued</div>
            <div class="stat-value"><?php echo count($invoices); ?></div>
            <div class="text-muted small"><?php echo format_currency($total_invoiced); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #16a34a;">
            <div class="stat-label">Cash Collected</div>
            <div class="stat-value"><?php echo count($payments); ?></div>
            <div class="text-muted small"><?php echo format_currency($total_collected); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-left:4px solid #ea580c;">
            <div class="stat-label">Net Outstanding</div>
            <div class="stat-value text-danger"><?php echo format_currency(max(0,$total_invoiced-$total_collected)); ?></div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card mb-4">
    <div class="card-header"><i class="fa-solid fa-file-invoice me-2"></i>Invoices Confirmed on <?php echo date('d M Y', strtotime($date)); ?></div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0" style="font-size:12px;">
            <thead class="table-dark"><tr><th>#</th><th>Customer</th><th>Phone</th><th>Type</th><th>SR</th><th class="text-end">Amount</th><th>Delivery</th><th>Collection</th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td>#<?php echo str_pad($inv['id'],6,'0',STR_PAD_LEFT); ?></td>
                    <td><strong><?php echo htmlspecialchars($inv['customer_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($inv['customer_address'] ?? ''); ?></small></td>
                    <td><?php echo htmlspecialchars($inv['customer_phone']); ?></td>
                    <td><span class="badge bg-secondary"><?php echo $inv['customer_type']; ?></span></td>
                    <td class="small"><?php echo htmlspecialchars($inv['creator_name']); ?></td>
                    <td class="text-end fw-bold"><?php echo format_currency($inv['grand_total']); ?></td>
                    <td><span class="badge bg-info"><?php echo $inv['delivery_status']; ?></span></td>
                    <td style="min-width:90px;"></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?><tr><td colspan="8" class="text-center py-4 text-muted">No invoices confirmed on this date.</td></tr><?php endif; ?>
            </tbody>
            <tfoot class="table-secondary fw-bold">
                <tr><td colspan="5" class="text-end">Total</td><td class="text-end"><?php echo format_currency($total_invoiced); ?></td><td colspan="2"></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header"><i class="fa-solid fa-money-bill me-2"></i>Payments Received on <?php echo date('d M Y', strtotime($date)); ?></div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0" style="font-size:12px;">
            <thead class="table-dark"><tr><th>Customer</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['customer_name']); ?></td>
                    <td class="small"><?php echo htmlspecialchars($p['description']); ?></td>
                    <td class="text-end fw-bold text-success"><?php echo format_currency($p['amount']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?><tr><td colspan="3" class="text-center py-4 text-muted">No payments received on this date.</td></tr><?php endif; ?>
            </tbody>
            <tfoot class="table-secondary fw-bold">
                <tr><td colspan="2" class="text-end">Total Collected</td><td class="text-end text-success"><?php echo format_currency($total_collected); ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Signature row -->
<div class="row g-3 mt-4">
    <?php foreach (['Collection Officer','Accounts','Area Manager','Management'] as $l): ?>
    <div class="col-3 text-center">
        <div style="border-top:2px solid #000;padding-top:4px;margin-top:40px;" class="small fw-bold text-uppercase"><?php echo $l; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>
