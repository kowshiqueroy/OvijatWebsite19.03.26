<?php
/**
 * modules/branches/list.php
 */
include '../../includes/header.php';
requireRole('Admin');

$branches = $pdo->query("SELECT * FROM branches WHERE is_deleted = 0 ORDER BY name ASC")->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Branch Management</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus me-1"></i> Add Branch
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="branchesTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                        <td><?php echo htmlspecialchars($b['location'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($b['contact'] ?? '-'); ?></td>
                        <td><?php echo date('d-M-Y', strtotime($b['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBranchModal"
                                data-id="<?php echo $b['id']; ?>" data-name="<?php echo htmlspecialchars($b['name']); ?>"
                                data-location="<?php echo htmlspecialchars($b['location'] ?? ''); ?>"
                                data-contact="<?php echo htmlspecialchars($b['contact'] ?? ''); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="addBranchForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Branch</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editBranchForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_branch_id">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_branch_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="edit_branch_location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" name="contact" id="edit_branch_contact" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addBranchForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/branches.php?action=add', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('#editBranchModal').on('show.bs.modal', function(e) {
        const btn = $(e.relatedTarget);
        $('#edit_branch_id').val(btn.data('id'));
        $('#edit_branch_name').val(btn.data('name'));
        $('#edit_branch_location').val(btn.data('location'));
        $('#edit_branch_contact').val(btn.data('contact'));
    });

    $('#editBranchForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/branches.php?action=edit', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>