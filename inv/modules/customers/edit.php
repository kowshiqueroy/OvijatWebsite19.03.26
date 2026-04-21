<?php
/**
 * modules/customers/edit.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant']);

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
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Edit Customer</h5>
            </div>
            <div class="card-body">
                <form id="editCustomerForm">
                    <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Type</label>
                        <select name="type" class="form-select">
                            <option value="Retail" <?php echo $customer['type'] === 'Retail' ? 'selected' : ''; ?>>Retail</option>
                            <option value="TP" <?php echo $customer['type'] === 'TP' ? 'selected' : ''; ?>>TP (Trade Price)</option>
                            <option value="DP" <?php echo $customer['type'] === 'DP' ? 'selected' : ''; ?>>DP (Dealer Price)</option>
                        </select>
                    </div>

                    <div class="text-end">
                        <a href="list.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editCustomerForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/customers.php?action=edit', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'list.php';
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>