<?php
/**
 * modules/customers/view.php
 */
include '../../includes/header.php';

$branch_id = $_SESSION['branch_id'];
$customer_id = (int)$_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND branch_id = ?");
$stmt->execute([$customer_id, $branch_id]);
$customer = $stmt->fetch();

if (!$customer) {
    echo "<div class='alert alert-danger'>Customer not found.</div>";
    include '../../includes/footer.php';
    exit;
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Customer Details</h5>
                <div>
                    <a href="ledger.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-info">View Ledger</a>
                    <a href="list.php" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="35%">Customer Name</th>
                        <td class="fw-bold"><?php echo htmlspecialchars($customer['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td><span class="badge bg-primary"><?php echo $customer['type']; ?></span></td>
                    </tr>
                    <tr>
                        <th>Current Balance</th>
                        <td class="<?php echo $customer['balance'] > 0 ? 'text-danger' : 'text-success'; ?> fw-bold">
                            <?php echo formatCurrency($customer['balance']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?php echo date('d-M-Y', strtotime($customer['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>