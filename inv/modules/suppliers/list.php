<?php
/**
 * modules/suppliers/list.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant', 'Manager']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$suppliers = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Supplier Management</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fas fa-plus me-1"></i> Add Supplier
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Current Balance</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td class="fw-bold"><?php echo $s['name']; ?></td>
                        <td><?php echo $s['contact_person']; ?></td>
                        <td><?php echo $s['phone']; ?></td>
                        <td class="fw-bold <?php echo $s['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($s['balance']); ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info me-1 edit-supplier" data-id='<?php echo json_encode($s); ?>' title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-supplier" data-id="<?php echo $s['id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="supplierForm">
            <input type="hidden" name="id" id="supplier_id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#supplierForm').on('submit', function(e) {
        e.preventDefault();
        const action = $('#supplier_id').val() ? 'edit' : 'add';
        $.post('../../actions/suppliers.php?action=' + action, $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('.edit-supplier').on('click', function() {
        const data = $(this).data('id');
        $('#supplier_id').val(data.id);
        $('#name').val(data.name);
        $('#contact_person').val(data.contact_person);
        $('#phone').val(data.phone);
        $('#address').val(data.address);
        $('#modalTitle').text('Edit Supplier');
        $('#addSupplierModal').modal('show');
    });

    $('#addSupplierModal').on('hidden.bs.modal', function () {
        $('#supplierForm')[0].reset();
        $('#supplier_id').val('');
        $('#modalTitle').text('Add New Supplier');
    });

    $('.delete-supplier').on('click', function() {
        if (confirm('Are you sure you want to delete this supplier?')) {
            const id = $(this).data('id');
            $.post('../../actions/suppliers.php?action=delete', {id: id}, function(res) {
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
