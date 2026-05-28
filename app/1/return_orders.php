<?php
include 'header.php';

// Ensure user has a company session
$company_id = $_SESSION['company_id'] ?? 1; // Fallback to 1 if not set
$user_id = $_SESSION['user_id'] ?? 1;

// ==========================================
// 1. AUTO-CREATE RETURN TABLES IF MISSING
// ==========================================
$conn->query("CREATE TABLE IF NOT EXISTS order_returns (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ids VARCHAR(255) NOT NULL,
    route_id INT(11) UNSIGNED NOT NULL,
    shop_id INT(11) UNSIGNED NOT NULL,
    user_id INT(11) UNSIGNED NOT NULL,
    company_id INT(11) UNSIGNED NOT NULL,
    total_return_value DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT(11) UNSIGNED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS order_return_items (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_id INT(11) UNSIGNED NOT NULL,
    order_id INT(11) UNSIGNED NOT NULL,
    item_id INT(11) UNSIGNED NOT NULL,
    return_qty INT(11) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES order_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");


// ==========================================
// 2. HANDLE RETURN SUBMISSION & DELETION
// ==========================================
$msg = "";

// Handle Deletion (within 3 days constraint)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_return_id'])) {
    $del_id = intval($_POST['delete_return_id']);
    
    // Start transaction
    $conn->begin_transaction();
    try {
        // Check if it exists and get value/shop info
        $check_q = $conn->prepare("SELECT total_return_value, shop_id FROM order_returns WHERE id = ? AND company_id = ? AND created_at >= (NOW() - INTERVAL 3 DAY) FOR UPDATE");
        $check_q->bind_param("ii", $del_id, $company_id);
        $check_q->execute();
        $res = $check_q->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $refund_value = $row['total_return_value'];
            $shop_id = $row['shop_id'];

            // 1. Revert balance (Deduct the refund we previously added)
            $update_stmt = $conn->prepare("UPDATE shops SET balance = balance - ? WHERE id = ?");
            $update_stmt->bind_param("di", $refund_value, $shop_id);
            $update_stmt->execute();
            $update_stmt->close();

            // 2. Delete the record (items cascade delete)
            $del_stmt = $conn->prepare("DELETE FROM order_returns WHERE id = ?");
            $del_stmt->bind_param("i", $del_id);
            $del_stmt->execute();
            $del_stmt->close();

            $conn->commit();
            $msg .= "<div class='alert' style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:15px;'>✅ Return #$del_id deleted and shop balance adjusted.</div>";
        } else {
            $conn->rollback();
            $msg .= "<div class='alert' style='background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px;'>❌ Action Denied: Return does not exist, or it is past the 3-day deletion window.</div>";
        }
        $check_q->close();
    } catch (Exception $e) {
        $conn->rollback();
        $msg .= "<div class='alert' style='background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px;'>❌ Error during deletion transaction.</div>";
    }
}

// Handle Return Processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_return'])) {
    $route_id = $_POST['route_id'];
    $shop_id = $_POST['shop_id'];
    $sales_user_id = $_POST['user_id'];
    $order_ids_str = $_POST['order_ids'];
    $returns = $_POST['returns'] ?? [];
    
    $total_return_value = 0;
    
    $conn->begin_transaction();
    try {
        // Create the main return record
        $stmt = $conn->prepare("INSERT INTO order_returns (order_ids, route_id, shop_id, user_id, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiii", $order_ids_str, $route_id, $shop_id, $sales_user_id, $company_id, $user_id);
        $stmt->execute();
        $return_id = $stmt->insert_id;
        $stmt->close();
        
        // Loop through submitted returns and insert items
        $item_stmt = $conn->prepare("INSERT INTO order_return_items (return_id, order_id, item_id, return_qty, price) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($returns as $order_id => $items) {
            foreach ($items as $item_id => $qty) {
                $qty = intval($qty);
                if ($qty > 0) {
                    // Fetch original price to be secure
                    $price_q = $conn->prepare("SELECT price FROM order_items WHERE order_id=? AND item_id=?");
                    $price_q->bind_param("ii", $order_id, $item_id);
                    $price_q->execute();
                    $price = $price_q->get_result()->fetch_assoc()['price'] ?? 0;
                    $price_q->close();
                    
                    $item_stmt->bind_param("iiiid", $return_id, $order_id, $item_id, $qty, $price);
                    $item_stmt->execute();
                    
                    $total_return_value += ($qty * $price);
                }
            }
        }
        $item_stmt->close();
        
        // Update the total value of this return
        $update_ret = $conn->prepare("UPDATE order_returns SET total_return_value = ? WHERE id = ?");
        $update_ret->bind_param("di", $total_return_value, $return_id);
        $update_ret->execute();
        $update_ret->close();

        // Update the shop balance (ADD back the money for returned goods)
        if ($total_return_value > 0) {
            $update_shop = $conn->prepare("UPDATE shops SET balance = balance + ? WHERE id = ?");
            $update_shop->bind_param("di", $total_return_value, $shop_id);
            $update_shop->execute();
            $update_shop->close();
        }

        $conn->commit();
        $msg .= "<div class='alert' style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:15px;'>✅ Return successfully processed! Total Refund: $" . number_format($total_return_value, 2) . "</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $msg .= "<div class='alert' style='background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px;'>❌ Error processing return transaction.</div>";
    }
}
?>

<div class="container">
    <?php echo $msg; ?>

    <div class="form-section glass-panel" style="margin-bottom: 20px;">
        <h2 style="margin-top:0;"><i class="fa-solid fa-rotate-left"></i> Process Order Returns</h2>
        <p style="color: #666; font-size: 0.9rem;">Enter a single Order ID or multiple IDs separated by commas (e.g., 3, 55, 66).</p>
        
        <form method="GET">
            <div style="display: flex; gap: 15px; align-items: center;">
                <input type="text" name="ref_ids" placeholder="e.g. 12, 14, 15" value="<?php echo htmlspecialchars($_GET['ref_ids'] ?? ''); ?>" required style="flex: 1; padding: 10px; font-size: 1.1rem; border: 2px solid var(--primary); border-radius: 5px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 1.1rem;"><i class="fa-solid fa-magnifying-glass"></i> Validate Orders</button>
            </div>
        </form>
    </div>

    <?php
    if (isset($_GET['ref_ids']) && !empty($_GET['ref_ids'])) {
        $ref_ids_raw = $_GET['ref_ids'];
        // Sanitize and extract integers only
        $ref_ids_arr = array_filter(array_map('intval', explode(',', $ref_ids_raw)));
        $ref_ids_str = implode(',', $ref_ids_arr);

        if (!empty($ref_ids_str)) {
            // Check if orders exist, belong to this company, and get their details
            $val_q = "SELECT id, route_id, shop_id, created_by 
                      FROM orders 
                      WHERE id IN ($ref_ids_str) AND company_id = '$company_id' AND order_status = 1";
            $val_res = mysqli_query($conn, $val_q);
            
            $valid_orders = [];
            $routes = []; $shops = []; $users = [];
            
            while ($r = mysqli_fetch_assoc($val_res)) {
                $valid_orders[] = $r['id'];
                $routes[] = $r['route_id'];
                $shops[] = $r['shop_id'];
                $users[] = $r['created_by'];
            }

            // Validations
            if (count($valid_orders) !== count($ref_ids_arr)) {
                echo "<div class='glass-panel' style='background:#f8d7da; color:#721c24;'>❌ Error: One or more Order IDs are invalid, belong to a different company, or are not confirmed yet.</div>";
            } elseif (count(array_unique($routes)) > 1 || count(array_unique($shops)) > 1 || count(array_unique($users)) > 1) {
                echo "<div class='glass-panel' style='background:#fff3cd; color:#856404;'>⚠️ Error: Multiple orders can only be returned together if they share the exact same Route, Shop, and Sales Rep.</div>";
            } else {
                // ALL VALID! Fetch Order Items
                $shared_route = $routes[0];
                $shared_shop = $shops[0];
                $shared_user = $users[0];
                
                // Get names for UI
                $route_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT route_name FROM routes WHERE id='$shared_route'"))['route_name'] ?? 'Unknown';
                $shop_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT shop_name FROM shops WHERE id='$shared_shop'"))['shop_name'] ?? 'Unknown';
                $user_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id='$shared_user'"))['username'] ?? 'Unknown';

                echo "<div class='glass-panel' style='margin-bottom: 20px;'>
                        <h3 style='margin-top:0; color:var(--primary);'>Orders Validated!</h3>
                        <p><strong>Route:</strong> $route_name | <strong>Shop:</strong> $shop_name | <strong>Rep:</strong> $user_name</p>";
                
                echo "<form method='POST' action=''>
                        <input type='hidden' name='route_id' value='$shared_route'>
                        <input type='hidden' name='shop_id' value='$shared_shop'>
                        <input type='hidden' name='user_id' value='$shared_user'>
                        <input type='hidden' name='order_ids' value='$ref_ids_str'>
                        
                        <table class='table-simple' style='width: 100%;'>
                            <thead style='background: #f4f4f4;'>
                                <tr>
                                    <th>Order #</th>
                                    <th>Product Name</th>
                                    <th style='text-align:center;'>Original Qty</th>
                                    <th style='text-align:center;'>Previously Returned</th>
                                    <th style='text-align:center; width: 150px;'>Qty to Return Now</th>
                                </tr>
                            </thead>
                            <tbody>";
                
                $items_q = "SELECT oi.order_id, oi.item_id, oi.quantity, i.item_name,
                            COALESCE((SELECT SUM(return_qty) FROM order_return_items ori WHERE ori.order_id = oi.order_id AND ori.item_id = oi.item_id), 0) as returned_qty
                            FROM order_items oi
                            JOIN items i ON oi.item_id = i.id
                            WHERE oi.order_id IN ($ref_ids_str)";
                $items_res = mysqli_query($conn, $items_q);
                
                $has_items = false;
                while ($item = mysqli_fetch_assoc($items_res)) {
                    $has_items = true;
                    $max_returnable = $item['quantity'] - $item['returned_qty'];
                    
                    echo "<tr>
                            <td>#{$item['order_id']}</td>
                            <td>{$item['item_name']}</td>
                            <td style='text-align:center;'>{$item['quantity']}</td>
                            <td style='text-align:center; color:red;'>{$item['returned_qty']}</td>
                            <td style='text-align:center;'>";
                    
                    if ($max_returnable > 0) {
                        echo "<input type='number' name='returns[{$item['order_id']}][{$item['item_id']}]' 
                                     min='0' max='{$max_returnable}' value='0' 
                                     style='width:80px; padding:5px; text-align:center; border:1px solid #ccc; border-radius:4px;'>";
                    } else {
                        echo "<span style='color:grey; font-style:italic;'>Fully Returned</span>";
                    }
                    echo "</td></tr>";
                }
                
                echo "      </tbody>
                        </table>";
                
                if ($has_items) {
                    echo "<div style='margin-top: 20px; text-align: right;'>
                            <button type='submit' name='submit_return' class='btn btn-green' style='font-size: 1.1rem;'><i class='fa-solid fa-check-double'></i> Submit Return</button>
                          </div>";
                }
                echo "</form></div>";
            }
        }
    }
    ?>

    <div class="glass-panel printable">
        <h2 style="margin-top:0; border-bottom: 2px solid #eee; padding-bottom: 10px;"><i class="fa-solid fa-clock-rotate-left"></i> Return History</h2>
        
        <?php
        // Default dates to today if not set
        $today = date('Y-m-d');
        $start_date = $_GET['start_date'] ?? $today;
        $end_date = $_GET['end_date'] ?? $today;
        ?>

        <form method="GET" style="margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 8px;">
            <div class="grid-layout desktop-5" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 130px;">
                    <label style="display:block; margin-bottom:5px;">From Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="display:block; margin-bottom:5px;">To Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex: 1; min-width: 110px;">
                    <label style="display:block; margin-bottom:5px;">Orig. Order ID</label>
                    <input type="text" name="h_order_id" placeholder="e.g. 15" value="<?php echo htmlspecialchars($_GET['h_order_id'] ?? ''); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="display:block; margin-bottom:5px;">Route</label>
                    <select name="h_route" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <option value="">All Routes</option>
                        <?php
                        $rq = mysqli_query($conn, "SELECT id, route_name FROM routes WHERE company_id='$company_id'");
                        while ($r = mysqli_fetch_assoc($rq)) {
                            $sel = ($_GET['h_route'] ?? '') == $r['id'] ? 'selected' : '';
                            echo "<option value='{$r['id']}' $sel>{$r['route_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="display:block; margin-bottom:5px;">Shop</label>
                    <select name="h_shop" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <option value="">All Shops</option>
                        <?php
                        $sq = mysqli_query($conn, "SELECT id, shop_name FROM shops WHERE company_id='$company_id'");
                        while ($s = mysqli_fetch_assoc($sq)) {
                            $sel = ($_GET['h_shop'] ?? '') == $s['id'] ? 'selected' : '';
                            echo "<option value='{$s['id']}' $sel>{$s['shop_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="display:block; margin-bottom:5px;">Sales Rep</label>
                    <select name="h_user" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <option value="">All Reps</option>
                        <?php
                        $uq = mysqli_query($conn, "SELECT id, username FROM users WHERE company_id='$company_id'");
                        while ($u = mysqli_fetch_assoc($uq)) {
                            $sel = ($_GET['h_user'] ?? '') == $u['id'] ? 'selected' : '';
                            echo "<option value='{$u['id']}' $sel>{$u['username']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end; flex: 1; min-width: 120px;">
                    <button type="submit" name="search_history" class="btn btn-dark" style="width: 100%; padding:9px;"><i class="fa-solid fa-filter"></i> Filter</button>
                </div>
            </div>
        </form>

        <table class="table-simple" style="width: 100%; text-align: left;">
            <thead style="background: #f4f4f4;">
                <tr>
                    <th>Return ID & Date</th>
                    <th>Orig. Orders</th>
                    <th>Details (Route/Shop/Rep)</th>
                    <th>Items Returned</th>
                    <th>Refund Value</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hist_q = "SELECT r.*, rt.route_name, s.shop_name, u.username 
                           FROM order_returns r
                           LEFT JOIN routes rt ON r.route_id = rt.id
                           LEFT JOIN shops s ON r.shop_id = s.id
                           LEFT JOIN users u ON r.user_id = u.id
                           WHERE r.company_id = '$company_id'";
                
                // Filtering Logic
                
                // 1. Date Range
                $s_date = mysqli_real_escape_string($conn, $start_date) . " 00:00:00";
                $e_date = mysqli_real_escape_string($conn, $end_date) . " 23:59:59";
                $hist_q .= " AND r.created_at BETWEEN '$s_date' AND '$e_date'";
                
                // 2. Other Filters
                if (!empty($_GET['h_order_id'])) {
                    $search_order_id = intval($_GET['h_order_id']);
                    $hist_q .= " AND FIND_IN_SET('$search_order_id', REPLACE(r.order_ids, ' ', '')) > 0";
                }
                if (!empty($_GET['h_route'])) $hist_q .= " AND r.route_id = '" . mysqli_real_escape_string($conn, $_GET['h_route']) . "'";
                if (!empty($_GET['h_shop'])) $hist_q .= " AND r.shop_id = '" . mysqli_real_escape_string($conn, $_GET['h_shop']) . "'";
                if (!empty($_GET['h_user'])) $hist_q .= " AND r.user_id = '" . mysqli_real_escape_string($conn, $_GET['h_user']) . "'";
                
                $hist_q .= " ORDER BY r.created_at DESC LIMIT 50";
                
                $hist_res = mysqli_query($conn, $hist_q);
                
                if ($hist_res && mysqli_num_rows($hist_res) > 0) {
                    $three_days_ago = strtotime('-3 days'); 
                    
                    while ($row = mysqli_fetch_assoc($hist_res)) {
                        // Fetch items for this return
                        $ri_q = mysqli_query($conn, "SELECT ri.return_qty, ri.price, i.item_name FROM order_return_items ri JOIN items i ON ri.item_id = i.id WHERE ri.return_id = '{$row['id']}'");
                        $items_str = "<ul style='margin:0; padding-left:15px; font-size:0.85rem;'>";
                        while($ri = mysqli_fetch_assoc($ri_q)) {
                            $items_str .= "<li>{$ri['item_name']} ({$ri['return_qty']} x $" . number_format($ri['price'], 2) . ")</li>";
                        }
                        $items_str .= "</ul>";

                        // Determine if delete button should be shown
                        $created_time = strtotime($row['created_at']);
                        $action_btn = "<span style='color: #999; font-size: 0.85rem;' title='Older than 3 days'><i class='fa-solid fa-lock'></i> Locked</span>";
                        
                        if ($created_time >= $three_days_ago) {
                            $action_btn = "
                                <form method='POST' style='margin:0;' onsubmit='return confirm(\"Are you sure you want to delete this return? All restored item quantities will need to be re-managed. This action cannot be undone.\");'>
                                    <input type='hidden' name='delete_return_id' value='{$row['id']}'>
                                    <button type='submit' style='background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size:0.85rem;'>
                                        <i class='fa-solid fa-trash'></i> Delete
                                    </button>
                                </form>";
                        }

                        echo "<tr>
                                <td><strong>RET-{$row['id']}</strong><br><small style='color:#666;'>".date('d M Y, h:i A', strtotime($row['created_at']))."</small></td>
                                <td>{$row['order_ids']}</td>
                                <td>
                                    <strong>{$row['shop_name']}</strong><br>
                                    <small style='color:#666;'><i class='fa-solid fa-route'></i> {$row['route_name']} | <i class='fa-regular fa-user'></i> {$row['username']}</small>
                                </td>
                                <td>{$items_str}</td>
                                <td style='color:red; font-weight:bold;'>-$" . number_format($row['total_return_value'], 2) . "</td>
                                <td>{$action_btn}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No returns found for the selected criteria.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>