<?php
/**
 * modules/reports/sales.php
 */
include '../../includes/header.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? 'approved';

$query = "SELECT s.*, c.name as customer_name 
          FROM sales s 
          JOIN customers c ON s.customer_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ?";
$params = [$from_date, $to_date];

if ($status !== 'all') {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$total_amount = 0;
foreach ($sales as $s) $total_amount += $s['total_amount'];
?>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending_approval" <?php echo $status == 'pending_approval' ? 'selected' : ''; ?>>Pending</option>
                    <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-bold">Sales Report Summary</h5>
        <h5 class="mb-0 fw-bold text-success">Total: <?php echo formatCurrency($total_amount); ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th>Invoice ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Discount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td class="fw-bold">#INV-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $sale['customer_name']; ?></td>
                        <td><?php echo formatDate($sale['created_at']); ?></td>
                        <td class="fw-bold"><?php echo formatCurrency($sale['total_amount']); ?></td>
                        <td class="text-danger"><?php echo formatCurrency($sale['discount_amount']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $sale['status'] == 'approved' ? 'success' : 
                                    ($sale['status'] == 'pending_approval' ? 'warning text-dark' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($sale['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sales)): ?>
                    <tr><td colspan="6" class="text-center p-5">No sales found for the selected criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
