<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$as_of = $_GET['as_of'] ?? date('Y-m-d');
$as_of_ts = strtotime($as_of);

// All active customers with outstanding balance
$customers = fetch_all(
    "SELECT c.*, u.username FROM customers c LEFT JOIN users u ON c.user_id = u.id WHERE c.isDelete=0 AND c.balance != 0 ORDER BY c.name"
);

// For each customer, find unpaid invoices and their age
$buckets = ['current' => 0, 'b30' => 0, 'b60' => 0, 'b90' => 0, 'b90plus' => 0];
$rows = [];

foreach ($customers as $cust) {
    // Get confirmed, unfully-paid invoices for this customer
    $invoices = fetch_all(
        "SELECT id, grand_total, confirmed_at FROM sales_drafts WHERE customer_id=? AND status='Confirmed' AND isDelete=0 ORDER BY confirmed_at ASC",
        [$cust['id']]
    );

    $cust_current = $cust_30 = $cust_60 = $cust_90 = $cust_90plus = 0;

    foreach ($invoices as $inv) {
        $age = floor(($as_of_ts - strtotime($inv['confirmed_at'])) / 86400);
        $amt = floatval($inv['grand_total']);
        if ($age <= 0)        $cust_current += $amt;
        elseif ($age <= 30)   $cust_30      += $amt;
        elseif ($age <= 60)   $cust_60      += $amt;
        elseif ($age <= 90)   $cust_90      += $amt;
        else                   $cust_90plus  += $amt;
    }

    $total_outstanding = $cust_current + $cust_30 + $cust_60 + $cust_90 + $cust_90plus;
    if ($total_outstanding == 0) continue;

    $rows[] = [
        'name'    => $cust['name'],
        'phone'   => $cust['phone'],
        'balance' => $cust['balance'],
        'current' => $cust_current,
        'b30'     => $cust_30,
        'b60'     => $cust_60,
        'b90'     => $cust_90,
        'b90plus' => $cust_90plus,
        'total'   => $total_outstanding,
    ];

    $buckets['current'] += $cust_current;
    $buckets['b30']     += $cust_30;
    $buckets['b60']     += $cust_60;
    $buckets['b90']     += $cust_90;
    $buckets['b90plus'] += $cust_90plus;
}

$grand_total = array_sum($buckets);
?>

<style>@media print { .no-print { display:none!important; } }</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col"><h3><i class="fa-solid fa-hourglass-half me-2"></i>AR Aging</h3></div>
    <div class="col-auto d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="as_of" class="form-control form-control-sm" value="<?php echo $as_of; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Load</button>
        </form>
        <button onclick="copyArAgingWhatsApp(this)" class="btn btn-outline-success btn-sm"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</button>
        <button onclick="downloadTableCSV('ar_aging_<?php echo $as_of; ?>.csv', '#ar-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Summary buckets -->
<div class="row g-3 mb-4">
    <?php
    $bucket_labels = ['current' => 'Current', 'b30' => '1–30 Days', 'b60' => '31–60 Days', 'b90' => '61–90 Days', 'b90plus' => '90+ Days'];
    $bucket_colors = ['current' => 'success', 'b30' => 'info', 'b60' => 'warning', 'b90' => 'orange', 'b90plus' => 'danger'];
    foreach ($buckets as $key => $val):
        $color = $bucket_colors[$key];
    ?>
    <div class="col">
        <div class="card border-0 shadow-sm bg-<?php echo $color; ?> text-white">
            <div class="card-body py-2">
                <div class="small"><?php echo $bucket_labels[$key]; ?></div>
                <div class="fw-bold"><?php echo format_currency($val); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header"><strong>Customer-wise Aging — As of <?php echo date('d M Y', strtotime($as_of)); ?></strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 export-table" id="ar-table">
                <thead class="table-dark">
                    <tr>
                        <th>Customer</th>
                        <th class="text-center">Current</th>
                        <th class="text-center">1–30 Days</th>
                        <th class="text-center">31–60 Days</th>
                        <th class="text-center">61–90 Days</th>
                        <th class="text-center">90+ Days</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <div class="small text-muted"><?php echo $r['phone']; ?></div>
                        </td>
                        <td class="text-center"><?php echo $r['current'] > 0 ? number_format($r['current'], 2) : '—'; ?></td>
                        <td class="text-center <?php echo $r['b30'] > 0 ? 'text-info fw-bold' : ''; ?>"><?php echo $r['b30'] > 0 ? number_format($r['b30'], 2) : '—'; ?></td>
                        <td class="text-center <?php echo $r['b60'] > 0 ? 'text-warning fw-bold' : ''; ?>"><?php echo $r['b60'] > 0 ? number_format($r['b60'], 2) : '—'; ?></td>
                        <td class="text-center <?php echo $r['b90'] > 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo $r['b90'] > 0 ? number_format($r['b90'], 2) : '—'; ?></td>
                        <td class="text-center <?php echo $r['b90plus'] > 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo $r['b90plus'] > 0 ? number_format($r['b90plus'], 2) : '—'; ?></td>
                        <td class="text-end fw-bold"><?php echo format_currency($r['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No outstanding receivables.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-center"><?php echo number_format($buckets['current'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($buckets['b30'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($buckets['b60'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($buckets['b90'], 2); ?></td>
                        <td class="text-center"><?php echo number_format($buckets['b90plus'], 2); ?></td>
                        <td class="text-end"><?php echo format_currency($grand_total); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
const AR_ROWS = <?php echo json_encode($rows); ?>;
function copyArAgingWhatsApp(btn) {
    const asOf = '<?php echo date('d M Y', strtotime($as_of)); ?>';
    let text = '*AR Aging — As of ' + asOf + '*\n' + '─'.repeat(32) + '\n';
    let total = 0;
    AR_ROWS.forEach(r => {
        const t = parseFloat(r.total) || 0;
        text += '• ' + r.name + ':  ৳' + t.toLocaleString('en-IN', {minimumFractionDigits:2});
        if ((parseFloat(r.b90plus)||0) > 0) text += '  ⚠90d+';
        text += '\n';
        total += t;
    });
    text += '─'.repeat(32) + '\n*Total: ৳' + total.toLocaleString('en-IN', {minimumFractionDigits:2}) + '*';
    copyText(text, btn);
}
</script>
<?php require_once '../../templates/footer.php'; ?>
