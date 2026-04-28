<?php
require_once 'templates/header.php';
check_login();

// Fetch summary data based on role
$user_count = fetch_one("SELECT COUNT(id) as total FROM users WHERE isDelete = 0")['total'];
$customer_count = fetch_one("SELECT COUNT(id) as total FROM customers WHERE isDelete = 0")['total'];
$product_count = fetch_one("SELECT COUNT(id) as total FROM products WHERE isDelete = 0")['total'];
$draft_count = fetch_one("SELECT COUNT(id) as total FROM sales_drafts WHERE status = 'Draft' AND isDelete = 0")['total'];
?>

<div class="row">
    <div class="col-12 mb-4">
        <h2>Dashboard</h2>
        <p class="text-muted">Overview of your distribution business.</p>
    </div>
</div>

<div class="row g-3">
    <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER])): ?>
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title text-uppercase small">Total Customers</h6>
                <h2 class="mb-0"><?php echo $customer_count; ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-end">
                <a href="modules/customers/index.php" class="text-white text-decoration-none small">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title text-uppercase small">Active Products</h6>
                <h2 class="mb-0"><?php echo $product_count; ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-end">
                <a href="modules/products/index.php" class="text-white text-decoration-none small">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER, ROLE_SR])): ?>
    <div class="col-md-3">
        <div class="card bg-warning text-dark shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title text-uppercase small">Pending Drafts</h6>
                <h2 class="mb-0"><?php echo $draft_count; ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-end">
                <a href="modules/sales/index.php" class="text-dark text-decoration-none small">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
    <div class="col-md-3">
        <div class="card bg-info text-white shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title text-uppercase small">System Users</h6>
                <h2 class="mb-0"><?php echo $user_count; ?></h2>
            </div>
            <div class="card-footer bg-transparent border-0 text-end">
                <a href="modules/users/index.php" class="text-white text-decoration-none small">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($_SESSION['role'] == ROLE_CUSTOMER): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>My Account Summary</strong></div>
            <div class="card-body">
                <?php
                $cust = fetch_one("SELECT * FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Account Name:</span>
                    <strong><?php echo $cust['name']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Customer Type:</span>
                    <span class="badge bg-secondary"><?php echo $cust['type']; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Current Balance:</span>
                    <h4 class="text-primary"><?php echo format_currency($cust['balance']); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'templates/footer.php'; ?>
