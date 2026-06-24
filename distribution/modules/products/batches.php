<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$product_filter = intval($_GET['product_id'] ?? 0);
$status_filter  = $_GET['status'] ?? '';  // ok, expiring, expired

$products = fetch_all("SELECT id, name FROM products WHERE isDelete = 0 ORDER BY name");

// Build query
$sql = "SELECT pb.*, p.name as product_name, p.unit, cat.name as category_name
        FROM product_batches pb
        JOIN products p ON pb.product_id = p.id
        LEFT JOIN categories cat ON p.category_id = cat.id
        WHERE pb.isDelete = 0 AND p.isDelete = 0";
$params = [];

if ($product_filter) {
    $sql .= " AND pb.product_id = ?";
    $params[] = $product_filter;
}

$today = date('Y-m-d');
$soon  = date('Y-m-d', strtotime('+30 days'));

if ($status_filter === 'expired') {
    $sql .= " AND pb.expiry_date < ?";
    $params[] = $today;
} elseif ($status_filter === 'expiring') {
    $sql .= " AND pb.expiry_date BETWEEN ? AND ?";
    $params[] = $today;
    $params[] = $soon;
} elseif ($status_filter === 'ok') {
    $sql .= " AND (pb.expiry_date IS NULL OR pb.expiry_date > ?)";
    $params[] = $soon;
}

$sql .= " ORDER BY pb.expiry_date ASC, pb.created_at ASC";
$batches = fetch_all($sql, $params);

// Count alerts
$expired_count  = count(fetch_all("SELECT id FROM product_batches WHERE isDelete=0 AND quantity_remaining > 0 AND expiry_date < ?", [$today]));
$expiring_count = count(fetch_all("SELECT id FROM product_batches WHERE isDelete=0 AND quantity_remaining > 0 AND expiry_date BETWEEN ? AND ?", [$today, $soon]));
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h3><i class="fas fa-boxes me-2"></i>Batch / Lot Tracker</h3>
        <p class="text-muted small mb-0">Track inventory batches by lot number, expiry, location, and remaining quantity.</p>
    </div>
    <div class="col-auto">
        <?php if ($expired_count > 0): ?>
            <span class="badge bg-danger fs-6 me-2"><i class="fas fa-skull-crossbones me-1"></i><?php echo $expired_count; ?> Expired</span>
        <?php endif; ?>
        <?php if ($expiring_count > 0): ?>
            <span class="badge bg-warning text-dark fs-6"><i class="fas fa-exclamation-triangle me-1"></i><?php echo $expiring_count; ?> Expiring Soon</span>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Product</label>
                <select name="product_id" class="form-select form-select-sm select2">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="ok"       <?php echo $status_filter=='ok'       ?'selected':''; ?>>OK (not expiring)</option>
                    <option value="expiring" <?php echo $status_filter=='expiring' ?'selected':''; ?>>Expiring Soon (≤ 30 days)</option>
                    <option value="expired"  <?php echo $status_filter=='expired'  ?'selected':''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="batches.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Batch / Lot</th>
                        <th>Location</th>
                        <th>Mfg. Date</th>
                        <th>Expiry Date</th>
                        <th class="text-center">Qty In</th>
                        <th class="text-center">Remaining</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No batches found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($batches as $b):
                        $is_expired  = $b['expiry_date'] && $b['expiry_date'] < $today;
                        $is_expiring = !$is_expired && $b['expiry_date'] && $b['expiry_date'] <= $soon;
                        $row_class   = $is_expired ? 'table-danger' : ($is_expiring ? 'table-warning' : '');
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($b['product_name']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars($b['category_name'] ?? ''); ?></div>
                        </td>
                        <td><code><?php echo htmlspecialchars($b['batch_no']); ?></code></td>
                        <td><?php echo htmlspecialchars($b['location'] ?? '—'); ?></td>
                        <td><?php echo $b['manufacture_date'] ? date('d M Y', strtotime($b['manufacture_date'])) : '—'; ?></td>
                        <td>
                            <?php if ($b['expiry_date']): ?>
                                <?php echo date('d M Y', strtotime($b['expiry_date'])); ?>
                                <?php if ($is_expired): ?>
                                    <span class="badge bg-danger ms-1">EXPIRED</span>
                                <?php elseif ($is_expiring): ?>
                                    <?php $days = (strtotime($b['expiry_date']) - strtotime($today)) / 86400; ?>
                                    <span class="badge bg-warning text-dark ms-1"><?php echo ceil($days); ?>d left</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo number_format($b['quantity_in']); ?></td>
                        <td class="text-center">
                            <span class="badge <?php echo $b['quantity_remaining'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo number_format($b['quantity_remaining']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($b['quantity_remaining'] == 0): ?>
                                <span class="badge bg-secondary">Depleted</span>
                            <?php elseif ($is_expired): ?>
                                <span class="badge bg-danger">Expired</span>
                            <?php elseif ($is_expiring): ?>
                                <span class="badge bg-warning text-dark">Expiring</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
