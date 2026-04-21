<?php
/**
 * modules/customers/list.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant', 'Manager']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT * FROM customers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$customers = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Customer Management</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-plus me-1"></i> Add Customer
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Current Balance</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td class="fw-bold"><?php echo $c['name']; ?></td>
                        <td><span class="badge bg-info text-dark"><?php echo $c['type']; ?></span></td>
                        <td class="fw-bold <?php echo $c['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($c['balance']); ?>
                        </td>
                        <td class="text-end">
                            <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (hasRole(['Admin', 'Accountant'])): ?>
                            <a href="edit.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="Edit"><i class="fas fa-edit"></i></a>
                            <button class="btn btn-sm btn-outline-danger delete-customer" data-id="<?php echo $c['id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="addCustomerForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer Type</label>
                        <select name="type" class="form-select">
                            <option value="Retail">Retail</option>
                            <option value="TP">TP (Trade Price)</option>
                            <option value="DP">DP (Dealer Price)</option>
                        </select>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addCustomerForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/customers.php?action=add', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('.delete-customer').on('click', function() {
        if (confirm('Are you sure you want to delete this customer?')) {
            const id = $(this).data('id');
            $.post('../../actions/customers.php?action=delete', {id: id}, function(res) {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
