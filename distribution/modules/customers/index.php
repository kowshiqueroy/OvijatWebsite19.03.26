<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER]);

if (isset($_POST['add_customer'])) {
    check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]);
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        redirect('modules/customers/index.php', 'CSRF Token Validation Failed.', 'danger');
    }
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $type = sanitize($_POST['type']);
    $credit_limit = isset($_POST['credit_limit']) ? floatval($_POST['credit_limit']) : 0.00;

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
        $stmt = $conn->prepare("INSERT INTO customers (user_id, name, phone, address, type, opening_balance, balance, credit_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssddd", $user_id, $name, $phone, $address, $type, $opening_bal, $opening_bal, $credit_limit);
        $stmt->execute();
        $customer_id = $conn->insert_id;

        // Log Opening Balance Transaction if > 0
        if ($opening_bal != 0) {
            $type_trans = ($opening_bal > 0) ? 'Debit' : 'Credit';
            $abs_bal = abs($opening_bal);
            $stmt_trans = $conn->prepare("INSERT INTO transactions (customer_id, type, amount, description) VALUES (?, ?, ?, 'Opening Balance Adjustment')");
            $stmt_trans->bind_param("isd", $customer_id, $type_trans, $abs_bal);
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

// Calculate Company Totals
$total_company_balance = array_sum(array_column($customers, 'balance'));
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

<!-- Company Summary & Filters -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body py-3">
                <div class="small opacity-75">Total Company Balance (Owed)</div>
                <h4 class="mb-0 fw-bold"><?php echo format_currency($total_company_balance); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row g-2 h-100 align-items-end">
            <div class="col-md-5">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="customerSearch" class="form-control border-start-0 ps-0" placeholder="Search name, phone...">
                </div>
            </div>
            <div class="col-md-4">
                <select id="sortOrder" class="form-select shadow-sm">
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
                    <option value="bal_desc">Balance (High to Low)</option>
                    <option value="bal_asc">Balance (Low to High)</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" id="minBalance" class="form-control shadow-sm" placeholder="Min Bal (Owed)">
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="customerTable">
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
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No customers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($customers as $c): ?>
                    <tr class="customer-row" data-name="<?php echo strtolower($c['name']); ?>" data-balance="<?php echo $c['balance']; ?>">
                        <td class="searchable"><strong><?php echo $c['name']; ?></strong></td>
                        <td class="searchable"><?php echo $c['phone']; ?></td>
                        <td class="searchable"><?php echo $c['address']; ?></td>
                        <td class="searchable"><span class="badge bg-secondary"><?php echo $c['type']; ?></span></td>
                        <td class="balance-cell <?php echo $c['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
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
                            <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details"><i class="fas fa-eye"></i></a>
                            <?php if (($_SESSION['role'] == ROLE_ACCOUNTANT || $_SESSION['role'] == ROLE_ADMIN) && $_SESSION['role'] != ROLE_VIEWER): ?>
                                <a href="toggle_status.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-warning" title="Toggle Status"><i class="fas fa-power-off"></i></a>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                                <a href="../admin/delete_record.php?table=customers&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" title="Master Delete" onclick="return confirm('WARNING: This will delete the customer and their user account. Continue?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const searchInput = document.getElementById('customerSearch');
const sortSelect = document.getElementById('sortOrder');
const minBalInput = document.getElementById('minBalance');
const tableBody = document.querySelector('#customerTable tbody');

function filterAndSort() {
    const filter = searchInput.value.toLowerCase();
    const minBal = parseFloat(minBalInput.value) || -Infinity;
    const rows = Array.from(document.querySelectorAll('.customer-row'));

    // 1. Filtering
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const balance = parseFloat(row.dataset.balance);
        const matchesSearch = text.includes(filter);
        const matchesMinBal = balance >= minBal;
        
        row.style.display = (matchesSearch && matchesMinBal) ? '' : 'none';
    });

    // 2. Sorting
    const sortVal = sortSelect.value;
    const sortedRows = rows.sort((a, b) => {
        if (sortVal === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
        if (sortVal === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
        if (sortVal === 'bal_desc') return parseFloat(b.dataset.balance) - parseFloat(a.dataset.balance);
        if (sortVal === 'bal_asc') return parseFloat(a.dataset.balance) - parseFloat(b.dataset.balance);
        return 0;
    });

    // Re-append sorted rows
    sortedRows.forEach(row => tableBody.appendChild(row));
}

if (searchInput) searchInput.addEventListener('keyup', filterAndSort);
if (minBalInput) minBalInput.addEventListener('input', filterAndSort);
if (sortSelect) sortSelect.addEventListener('change', filterAndSort);

// Initial sort
filterAndSort();
</script>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
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
                    <label class="form-label">Credit Limit (0 = Unlimited)</label>
                    <input type="number" step="0.01" name="credit_limit" class="form-control" value="0.00">
                </div>
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
