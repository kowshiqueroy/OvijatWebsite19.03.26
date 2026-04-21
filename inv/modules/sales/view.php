<?php
/**
 * modules/sales/view.php
 */
include '../../includes/header.php';

$sale_id = (int)$_GET['id'];

// Fetch config settings
$settings = [];
$stmtS = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($s = $stmtS->fetch()) $settings[$s['setting_key']] = $s['setting_value'];

$company_name = $settings['company_name'] ?? 'Company';
$company_address = $settings['company_address'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$company_email = $settings['company_email'] ?? '';
$company_logo = $settings['company_logo'] ?? '';
$verify_url = $settings['verify_url'] ?? '';
$currency = $settings['currency'] ?? 'BDT';

// Fetch Sale Header
$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.type as customer_type, u.username as creator_name, app.username as approver_name
    FROM sales s 
    JOIN customers c ON s.customer_id = c.id 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN users app ON s.approved_by = app.id
WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    echo "<div class='alert alert-danger'>Invoice not found.</div>";
    include '../../includes/footer.php';
    exit;
}

// Fetch Sale Items
$stmtItems = $pdo->prepare("
    SELECT si.*, p.name as product_name, p.unit_name, p.conversion_ratio
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?");
$stmtItems->execute([$sale_id]);
$items = $stmtItems->fetchAll();

// Calculate free items value
$free_amount = 0;
foreach ($items as $item) {
    if ($item['is_free']) $free_amount += $item['subtotal'];
}

// Discount from sale + free items value
$total_discount = $sale['discount_amount'] + $free_amount;

function formatCurr($amount) {
    global $currency;
    return $currency . ' ' . number_format($amount, 2);
}
?>

<div class="d-print-none mb-3 d-flex justify-content-between">
    <a href="list.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print me-1"></i> Print</button>
</div>

<div class="card shadow-sm border-0" id="invoice-card">
    <div class="card-body p-4">
        <!-- Header -->
        <div class="row mb-4 align-items-center">
            <div class="col-sm-7">
                <?php if ($company_logo): ?>
                <img src="<?php echo $company_logo; ?>" alt="Logo" style="max-height:50px" class="mb-2">
                <?php else: ?>
                <h4 class="fw-bold text-primary mb-1"><?php echo $company_name; ?></h4>
                <?php endif; ?>
                <p class="mb-0 small"><?php echo $company_address; ?></p>
                <p class="mb-0 small">Ph: <?php echo $company_phone; ?> | Email: <?php echo $company_email; ?></p>
            </div>
            <div class="col-sm-3 text-sm-end">
                <h5 class="fw-bold text-uppercase mb-1">Invoice</h5>
                <h6 class="text-muted">#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></h6>
                <p class="mb-0 small">Date: <?php echo date('d-M-Y', strtotime($sale['created_at'])); ?></p>
                <p class="small <?php echo $sale['status'] == 'approved' ? 'text-success' : 'text-warning'; ?> fw-bold"><?php echo strtoupper($sale['status']); ?></p>
            </div>
            <div class="col-sm-2 text-end">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode(BASE_URL . 'verify.php?id=' . $sale['id']); ?>" alt="QR">
            </div>
        </div>

        <hr class="my-3">

        <!-- Customer -->
        <div class="row mb-3">
            <div class="col-sm-8">
                <p class="mb-0 small"><strong>Customer:</strong> <?php echo $sale['customer_name']; ?></p>
                <p class="mb-0 small"><strong>Type:</strong> <?php echo $sale['customer_type']; ?></p>
            </div>
            <div class="col-sm-4 text-sm-end">
                <p class="small text-muted">Prepared: <?php echo $sale['creator_name']; ?></p>
                <?php if ($sale['approved_by']): ?>
                <p class="small text-muted">Approved: <?php echo $sale['approver_name']; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="table table-sm table-bordered mb-3">
            <thead class="bg-light small">
                <tr>
                    <th class="col-5">Product</th>
                    <th class="col-2 text-center">Qty</th>
                    <th class="col-2 text-end">Price</th>
                    <th class="col-3 text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_pcs = 0;
                foreach ($items as $item): 
                    $pcs = ($item['unit_type'] == 'pack') ? ($item['quantity'] * $item['conversion_ratio']) : $item['quantity'];
                    $total_pcs += $pcs;
                ?>
                <tr>
                    <td>
                        <div><?php echo $item['product_name']; ?>
                            <?php if ($item['is_free'] == 1 || $item['is_free'] == '1'): ?>
                            <span class="badge bg-success ms-1">FREE</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?php echo $item['unit_name']; ?> X <?php echo $item['conversion_ratio']; ?></small>
                    </td>
                    <td class="text-center">
                        <?php $is_free = ($item['is_free'] == 1 || $item['is_free'] == '1'); ?>
                        <?php if ($is_free): ?>
                        <span class="text-success fw-bold">FREE</span>
                        <?php else: ?>
                        <?php echo $item['quantity'] . ' ' . $item['unit_type']; ?>
                        <div class="small text-muted"><?php echo $pcs; ?> pcs</div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo $is_free ? '-' : formatCurr($item['unit_price']); ?></td>
                    <td class="text-end fw-bold"><?php echo $is_free ? 'FREE' : formatCurr($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="row justify-content-end">
            <div class="col-sm-5">
                <table class="table table-sm table-borderless">
                    <?php if ($total_discount > 0): ?>
                    <tr>
                        <td class="text-muted">Gross:</td>
                        <td class="text-end"><?php echo formatCurr($sale['total_amount'] + $total_discount); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Discount<?php echo $free_amount > 0 ? ' (Inc. FREE)' : ''; ?>:</td>
                        <td class="text-end text-danger">-<?php echo formatCurr($total_discount); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td class="fw-bold">Total:</td>
                        <td class="text-end fw-bold text-primary h5 mb-0"><?php echo formatCurr($sale['total_amount']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Total Pcs:</td>
                        <td class="text-end small"><?php echo $total_pcs; ?> pcs</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-4 pt-3 border-top">
            <div class="col-4 text-center">
                <div class="small text-muted">Customer</div>
            </div>
            <div class="col-4 text-center">
                <div class="small text-muted">Prepared: <?php echo $sale['creator_name']; ?></div>
            </div>
            <div class="col-4 text-center">
                <div class="small text-muted"><?php echo $sale['approver_name'] ?: 'Pending'; ?></div>
            </div>
        </div>

        <!-- Verification removed - QR shows on right side now -->
    </div>
</div>

<style>
@media print {
    .sidebar, #sidebar, .d-print-none, .navbar, #sidebarCollapse { display: none !important; }
    #content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    #invoice-card { border: none !important; box-shadow: none !important; }
    body { background-color: white !important; }
}
</style>

<?php include '../../includes/footer.php'; ?>