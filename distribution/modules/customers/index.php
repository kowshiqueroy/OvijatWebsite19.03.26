<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

if (isset($_POST['add_customer'])) {
    check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $type = sanitize($_POST['type']);

    // Start Transaction
    $conn = get_db_connection();
    $conn->begin_transaction();

    try {
        $opening_bal = isset($_POST['opening_balance']) ? floatval($_POST['opening_balance']) : 0.00;

        // Create User Account
        $password = password_hash($phone, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, phone, role, force_password_change) VALUES (?, ?, ?, 'Customer', 1)");
        $stmt->bind_param("sss", $phone, $password, $phone);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Create Customer Record
        $stmt = $conn->prepare("INSERT INTO customers (user_id, name, phone, address, type, opening_balance, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssdd", $user_id, $name, $phone, $address, $type, $opening_bal, $opening_bal);
        $stmt->execute();
        $customer_id = $conn->insert_id;

        // Log Opening Balance Transaction if > 0
        if ($opening_bal != 0) {
            $type = ($opening_bal > 0) ? 'Debit' : 'Credit';
            $abs_bal = abs($opening_bal);
            $stmt_trans = $conn->prepare("INSERT INTO transactions (customer_id, type, amount, description) VALUES (?, ?, ?, 'Opening Balance Adjustment')");
            $stmt_trans->bind_param("isd", $customer_id, $type, $abs_bal);
            $stmt_trans->execute();
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Added customer: $name");
        redirect('modules/customers/index.php', 'Customer added successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

$customers = fetch_all("SELECT c.*, u.is_active as user_active FROM customers c JOIN users u ON c.user_id = u.id WHERE c.isDelete = 0 AND u.isDelete = 0");
?>

<div class="row">
    <div class="col-12 d-flex justify-content-between align-items-center mb-4">
        <h3>Customer Management</h3>
        <?php if ($_SESSION['role'] != ROLE_VIEWER): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-plus me-2"></i> Add New Customer
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Phone / Username</th>
                        <th>Address</th>
                        <th>Type</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?php echo $c['name']; ?></strong></td>
                        <td><?php echo $c['phone']; ?></td>
                        <td><?php echo $c['address']; ?></td>
                        <td><span class="badge bg-secondary"><?php echo $c['type']; ?></span></td>
                        <td class="<?php echo $c['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo format_currency($c['balance']); ?>
                        </td>
                        <td>
                            <?php if ($c['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <?php if (($_SESSION['role'] == ROLE_ACCOUNTANT || $_SESSION['role'] == ROLE_ADMIN) && $_SESSION['role'] != ROLE_VIEWER): ?>
                                <a href="toggle_status.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-warning"><i class="fas fa-power-off"></i></a>
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
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number (Will be Username/Password)</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Customer Type</label>
                    <select name="type" class="form-control" required>
                        <option value="TP">TP Rate</option>
                        <option value="DP">DP Rate</option>
                        <option value="Retail">Retail Rate</option>
                    </select>
                </div>
                <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
                <div class="mb-3">
                    <label class="form-label">Opening Balance (Debit)</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                    <small class="text-muted">Enter positive amount if customer already owes money.</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_customer" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
