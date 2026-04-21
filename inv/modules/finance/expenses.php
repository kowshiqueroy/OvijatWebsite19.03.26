<?php
/**
 * modules/finance/expenses.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT e.*, u.username FROM expenses e JOIN users u ON e.user_id = u.id WHERE e.branch_id = ? ORDER BY e.expense_date DESC");
$stmt->execute([$branch_id]);
$expenses = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Expense Tracking</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fas fa-plus me-1"></i> Record Expense
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total = 0;
                    foreach ($expenses as $e): 
                        $total += $e['amount'];
                    ?>
                    <tr>
                        <td><?php echo date('d M, Y', strtotime($e['expense_date'])); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $e['category']; ?></span></td>
                        <td class="fw-bold text-danger"><?php echo formatCurrency($e['amount']); ?></td>
                        <td><?php echo $e['description']; ?></td>
                        <td class="small text-muted"><?php echo $e['username']; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger delete-expense" data-id="<?php echo $e['id']; ?>"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-light">
                    <tr>
                        <th colspan="2" class="text-end">Total Expenses:</th>
                        <th class="text-danger"><?php echo formatCurrency($total); ?></th>
                        <th colspan="3"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="expenseForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="Rent">Rent</option>
                            <option value="Electricity">Electricity</option>
                            <option value="Salary">Salary</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Office Supplies">Office Supplies</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" step="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/finance.php?action=add_expense', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('.delete-expense').on('click', function() {
        if (confirm('Are you sure you want to delete this expense record?')) {
            const id = $(this).data('id');
            $.post('../../actions/finance.php?action=delete_expense', {id: id}, function(res) {
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
