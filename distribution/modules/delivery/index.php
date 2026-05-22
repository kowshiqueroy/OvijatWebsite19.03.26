<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);

$loads = fetch_all("SELECT tl.*, u.username as creator_name, (SELECT COUNT(*) FROM truck_load_items WHERE truck_load_id = tl.id) as invoice_count FROM truck_loads tl JOIN users u ON tl.created_by = u.id WHERE tl.isDelete = 0 ORDER BY tl.created_at DESC");
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Truck Loads / Deliveries</h3>
        <a href="../sales/index.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> New Load from Sales</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Load #</th>
                        <th>Truck No</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Invoices</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loads as $l): ?>
                    <tr>
                        <td><strong>#<?php echo $l['id']; ?></strong></td>
                        <td><?php echo $l['truck_no']; ?></td>
                        <td><?php echo $l['driver_name']; ?></td>
                        <td>
                            <?php 
                                $status_color = [
                                    'Draft' => 'bg-secondary',
                                    'Loaded' => 'bg-info',
                                    'Departed' => 'bg-primary',
                                    'Completed' => 'bg-success'
                                ][$l['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $status_color; ?>"><?php echo strtoupper($l['status']); ?></span>
                        </td>
                        <td><span class="badge bg-dark"><?php echo $l['invoice_count']; ?> Invoices</span></td>
                        <td><?php echo date('d M Y', strtotime($l['created_at'])); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i> View & Update</a>
                            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                                <a href="../admin/delete_record.php?table=truck_loads&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Delete this truck load? Linked invoices will return to PENDING status.')"><i class="fas fa-trash"></i></a>
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
