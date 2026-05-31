<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$id = $_GET['id'] ?? 0;
$load = fetch_one("SELECT tl.*, u.username as creator_name FROM truck_loads tl JOIN users u ON tl.created_by = u.id WHERE tl.isDelete = 0 AND tl.id = ?", [$id]);

if (!$load) redirect('modules/delivery/index.php', 'Truck load not found.', 'danger');

$invoices = fetch_all("SELECT s.*, c.name as customer_name FROM truck_load_items tli JOIN sales_drafts s ON tli.invoice_id = s.id JOIN customers c ON s.customer_id = c.id WHERE s.isDelete = 0 AND tli.truck_load_id = ?", [$id]);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3>Truck Load #<?php echo $load['id']; ?></h3>
        <p class="text-muted mb-1">Truck: <strong><?php echo $load['truck_no']; ?></strong> | Driver: <strong><?php echo $load['driver_name']; ?></strong></p>
        <p class="text-muted mb-1">From: <strong><?php echo $load['source_location'] ?: 'N/A'; ?></strong> To: <strong><?php echo $load['destination_location'] ?: 'N/A'; ?></strong></p>
        <?php if($load['remarks']): ?>
            <p class="text-muted small"><em>Note: <?php echo $load['remarks']; ?></em></p>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <span class="badge fs-5 bg-<?php echo ($load['status'] == 'Completed') ? 'success' : 'primary'; ?>"><?php echo strtoupper($load['status']); ?></span>
    </div>
</div>

<div class="row">
    <div class="col-md-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Invoices in this Load</strong>
                <form action="bulk_update_invoices.php" method="POST" class="d-flex gap-2 align-items-center no-print">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="load_id" value="<?php echo $id; ?>">
                    <select name="new_delivery_status" class="form-select form-select-sm" style="width: 200px;" required>
                        <option value="">-- Apply to All --</option>
                        <option value="In Transit">Mark all In Transit</option>
                        <option value="Delivered">Mark all Delivered</option>
                        <option value="Failed">Mark all Failed</option>
                        <option value="Returned">Mark all Returned</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Change status for ALL invoices in this load?')">Apply</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Inv #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Current Status</th>
                                <th style="width: 250px;">Update Individual Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td>#<?php echo $inv['id']; ?></td>
                                <td><?php echo $inv['customer_name']; ?></td>
                                <td><?php echo format_currency($inv['grand_total']); ?></td>
                                <td>
                                    <?php 
                                        $s_color = [
                                            'Pending' => 'bg-secondary',
                                            'Loading' => 'bg-info',
                                            'In Transit' => 'bg-primary',
                                            'Delivered' => 'bg-success',
                                            'Failed' => 'bg-danger',
                                            'Returned' => 'bg-warning text-dark'
                                        ][$inv['delivery_status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $s_color; ?>"><?php echo $inv['delivery_status']; ?></span>
                                </td>
                                <td>
                                    <form action="update_single_invoice_status.php" method="POST" class="d-flex gap-1">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="load_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="In Transit" <?php if($inv['delivery_status'] == 'In Transit') echo 'selected'; ?>>In Transit</option>
                                            <option value="Delivered" <?php if($inv['delivery_status'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                                            <option value="Failed" <?php if($inv['delivery_status'] == 'Failed') echo 'selected'; ?>>Failed (Not Found)</option>
                                            <option value="Returned" <?php if($inv['delivery_status'] == 'Returned') echo 'selected'; ?>>Returned</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                    </form>
                                </td>
                                <td>
                                    <a href="../sales/view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Update Load Status</strong></div>
            <div class="card-body">
                <form action="update_load_status.php" method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="load_id" value="<?php echo $id; ?>">
                    <div class="mb-3">
                        <select name="status" class="form-control" required>
                            <option value="Loaded" <?php if($load['status'] == 'Loaded') echo 'selected'; ?>>Loaded</option>
                            <option value="Departed" <?php if($load['status'] == 'Departed') echo 'selected'; ?>>Departed</option>
                            <option value="Completed" <?php if($load['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
