<?php
include 'header.php';

// --- 1. FETCH GLOBAL DATA FOR DROPDOWNS (Performance Optimization) ---
// We fetch these once to use in the form later.
$companies = $conn->query("SELECT id, name FROM companies")->fetch_all(MYSQLI_ASSOC);
$users     = $conn->query("SELECT id, username FROM users")->fetch_all(MYSQLI_ASSOC);
$routes    = $conn->query("SELECT id, route_name FROM routes")->fetch_all(MYSQLI_ASSOC);
$shops     = $conn->query("SELECT id, shop_name FROM shops")->fetch_all(MYSQLI_ASSOC);
$items     = $conn->query("SELECT id, item_name, price FROM items")->fetch_all(MYSQLI_ASSOC);

// --- 2. HANDLE FORM SUBMISSION (SYNC PROCESS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_confirm'])) {
    
    $offline_id = $_POST['offline_id'];
    
    // Start Transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // A. Insert into ORDERS table
        $stmt = $conn->prepare("INSERT INTO orders (route_id, shop_id, company_id, order_date, delivery_date, created_by, status,order_status) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
        $stmt->bind_param("iiissi", 
            $_POST['route_id'], 
            $_POST['shop_id'], 
            $_POST['company_id'], 
            $_POST['order_date'], 
            $_POST['delivery_date'],
            $_POST['user_id']
        );
        $stmt->execute();
        $new_order_id = $conn->insert_id;

        // B. Insert into ORDER_ITEMS table
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
        
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                // $item['id'] comes from the dropdown, $item['qty'] and $item['price'] from inputs
                $stmt_item->bind_param("iiid", $new_order_id, $item['item_id'], $item['qty'], $item['price']);
                $stmt_item->execute();
            }
        }

        // C. Update OFFLINE_ORDERS table (Mark as synced)
        $stmt_update = $conn->prepare("UPDATE offline_orders SET synced=1, admin_approval_timedate=NOW(), admin_approval_id=?, note=? WHERE id=?");
        // Assuming admin_approval_id is the currently logged in admin. Using $_POST['user_id'] as fallback or 1.
        $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; 
        $stmt_update->bind_param("isi", $admin_id, $new_order_id, $offline_id);
        $stmt_update->execute();

        // Commit Transaction
        $conn->commit();
        
        echo '<div class="alert alert-success">Order successfully synced! ID: ' . $new_order_id . '</div>';
        
        // Refresh page to list view
        echo '<script>window.setTimeout(function(){ window.location.href = "offline.php"; }, 2000);</script>';

    } catch (Exception $e) {
        $conn->rollback();
        echo '<div class="alert alert-danger">Error syncing order: ' . $e->getMessage() . '</div>';
    }
}

// --- 3. DETERMINE VIEW MODE ---
$view_mode = 'list'; // Default to list view
$current_order = null;
$decoded_items = [];

if (isset($_GET['id'])) {
    $view_mode = 'review';
    $stmt = $conn->prepare("SELECT * FROM offline_orders WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $current_order = $stmt->get_result()->fetch_assoc();
    
    if($current_order) {
        $decoded_items = json_decode($current_order['items'], true); // Decode JSON to array
    }
}
?>

<div class="container mt-4">
    <h2>Offline Orders Manager</h2>
    <hr>

    <?php if ($view_mode == 'list'): ?>
        <?php
        $result = $conn->query("SELECT * FROM offline_orders WHERE synced=1 ORDER BY id DESC");
        ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>User (Offline)</th>
                        <th>Shop (Offline)</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= htmlspecialchars($row['shop_details']) ?> <br> <small class="text_muted"><?= htmlspecialchars($row['route_name']) ?></small></td>
                            <td><?= $row['order_date'] ?></td>
                            <td><?= $row['total'] ?></td>
                            <td>

                            <?php
                                if ($row['admin_approval_id'] != NULL) {
                                    $search_url = "orders.php?search_id=" . $row['note'];
                                    echo '<span >Added '.$row['admin_approval_timedate']. ' by '.$row['admin_approval_id'].' OrderID: '.$row['note'].'</span>';
                                    echo '<span><a href="' . $search_url . '">View Order Details</a></span>';
                                } else {
                                    ?>
<a href="offline.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                    Review & Sync
                                </a>
                                    <?php
                                        
                                  
                                }
                            ?>
                                
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($view_mode == 'review' && $current_order): ?>
        <form method="POST" action="">
            <input type="hidden" name="offline_id" value="<?= $current_order['id'] ?>">
            <input type="hidden" name="sync_confirm" value="1">

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">Order Details</div>
                        <div class="card-body">
                            
                            <div class="form-group mb-2">
                                <label>Company (Offline Note: <b><?= $current_order['note'] ?></b>)</label>
                                <select name="company_id" class="form-control" required>
                                    <?php foreach ($companies as $c) { ?>
                                        <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label>User (Offline: <b><?= $current_order['user_name'] ?></b>)</label>
                                <select name="user_id" class="form-control" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $u) { 
                                        // Auto-select if name matches
                                        $selected = (strcasecmp($u['username'], $current_order['user_name']) == 0) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $u['id'] ?>" <?= $selected ?>><?= $u['username'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label>Route (Offline: <b><?= $current_order['route_name'] ?></b>)</label>
                                <select name="route_id" class="form-control" required>
                                    <option value="">Select Route</option>
                                    <?php foreach ($routes as $r) { 
                                        $selected = (strcasecmp($r['route_name'], $current_order['route_name']) == 0) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $r['id'] ?>" <?= $selected ?>><?= $r['route_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label>Shop (Offline: <b><?= $current_order['shop_details'] ?></b>)</label>
                                <select name="shop_id" class="form-control" required>
                                    <option value="">Select Shop</option>
                                    <?php foreach ($shops as $s) { 
                                        $selected = (strcasecmp($s['shop_name'], $current_order['shop_details']) == 0) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $s['id'] ?>" <?= $selected ?>><?= $s['shop_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label>Dates</label>
                                <input type="date" name="order_date" class="form-control mb-1" value="<?= $current_order['order_date'] ?>" required>
                                <input type="date" name="delivery_date" class="form-control" value="<?= $current_order['delivery_date'] ?>" required>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">Map Items</div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th width="30%">Offline Item Name</th>
                                        <th width="40%">Map to System Item</th>
                                        <th width="15%">Qty</th>
                                        <th width="15%">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($decoded_items) {
                                        $i = 0;
                                        foreach ($decoded_items as $off_item) { 
                                            // Handle case where json keys might be slightly different
                                            $o_name = isset($off_item['name']) ? $off_item['name'] : 'Unknown';
                                            $o_qty = isset($off_item['qty']) ? $off_item['qty'] : 0;
                                            $o_price = isset($off_item['price']) ? $off_item['price'] : 0;
                                    ?>
                                        <tr>
                                            <td class="align-middle">
                                                <strong><?= $o_name ?></strong>
                                            </td>
                                            <td>
                                                <select name="items[<?= $i ?>][item_id]" class="form-control form-control-sm" required>
                                                    <option value="">Select Item...</option>
                                                    <?php foreach ($items as $sys_item) { 
                                                        // Simple check if offline name is inside system name or vice versa
                                                        $selected = (stripos($sys_item['item_name'], $o_name) !== false) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $sys_item['id'] ?>" <?= $selected ?>>
                                                            <?= $sys_item['item_name'] ?> (<?= $sys_item['price'] ?>)
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="items[<?= $i ?>][qty]" class="form-control form-control-sm" value="<?= $o_qty ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="items[<?= $i ?>][price]" class="form-control form-control-sm" value="<?= $o_price ?>" required>
                                            </td>
                                        </tr>
                                    <?php 
                                            $i++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center text-danger'>No items found in JSON data.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-right">
                        <a href="offline.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success btn-lg">Confirm & Sync Order</button>
                    </div>
                </div>
            </div>
        </form>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>