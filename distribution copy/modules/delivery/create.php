<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoice_ids'])) {
    $ids = $_POST['invoice_ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $invoices = fetch_all("SELECT s.*, c.name as customer_name FROM sales_drafts s JOIN customers c ON s.customer_id = c.id WHERE s.isDelete = 0 AND s.id IN ($placeholders) AND s.status = 'Confirmed' AND s.delivery_status = 'Pending'", $ids);
} else {
    redirect('modules/sales/index.php', 'No valid invoices selected.', 'warning');
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3>Create New Truck Load</h3>
        <p class="text-muted">Fill in truck details for the selected <?php echo count($invoices); ?> invoices.</p>
    </div>
</div>

<form action="save_load.php" method="POST">
    <?php csrf_field(); ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Truck Details</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Truck Number / ID</label>
                        <input type="text" name="truck_no" class="form-control" placeholder="e.g. DHAKA-METRO-1234" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Driver Name</label>
                        <input type="text" name="driver_name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Load Date</label>
                        <input type="date" name="load_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source / Warehouse</label>
                        <input type="text" name="source_location" class="form-control" placeholder="e.g. Main Depot">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destination Area</label>
                        <input type="text" name="destination_location" class="form-control" placeholder="e.g. Sector 10, Uttara">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Other Notes / Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Additional instructions..."></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success btn-lg w-100 shadow">
                <i class="fas fa-save me-2"></i> Confirm Truck Load
            </button>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Invoices in this Load</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td>
                                        #<?php echo $inv['id']; ?>
                                        <input type="hidden" name="invoice_ids[]" value="<?php echo $inv['id']; ?>">
                                    </td>
                                    <td><?php echo $inv['customer_name']; ?></td>
                                    <td><?php echo format_currency($inv['grand_total']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm text-danger remove-inv"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.remove-inv').forEach(btn => {
        btn.addEventListener('click', function() {
            if(confirm('Remove this invoice from the load?')) {
                this.closest('tr').remove();
            }
        });
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
