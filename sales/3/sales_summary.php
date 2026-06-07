<?php
include 'header.php';

// ==========================================
// 1. SECURE SESSION LOCK
// ==========================================
$session_company_id = $_SESSION['company_id'];
$session_user_id = $_SESSION['user_id'];
?>

<div class="container">
    <div class="form-section glass-panel" style="margin-bottom: 20px;">
        <h2 style="margin-top:0;"><i class="fa-solid fa-file-invoice-dollar"></i> My Net Sales & Returns</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">View your itemized orders with their associated returns deducted. Group your data visually to see adjusted sub-totals.</p>
        
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div><label>Date From</label><input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-01'); ?>"></div>
                <div><label>Date To</label><input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-t'); ?>"></div>

                <div>
                    <label>Route</label>
                    <select name="route_id">
                        <option value="">-- All Routes --</option>
                        <?php
                        // Filter routes by the session company
                        $q = mysqli_query($conn, "SELECT id, route_name FROM routes WHERE status=1 AND company_id='$session_company_id'");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['route_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['route_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>Shop</label>
                    <select name="shop_id">
                        <option value="">-- All Shops --</option>
                        <?php
                        // Filter shops by the session company
                        $q = mysqli_query($conn, "SELECT id, shop_name FROM shops WHERE status=1 AND company_id='$session_company_id'");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['shop_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['shop_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>Specific Product</label>
                    <select name="item_id">
                        <option value="">-- All Products --</option>
                        <?php
                        // Filter items by the session company
                        $q = mysqli_query($conn, "SELECT id, item_name FROM items WHERE status=1 AND company_id='$session_company_id'");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['item_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['item_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="background: #eef2f5; padding: 10px; border-radius: 8px;">
                    <label style="color: var(--primary); font-weight: bold;"><i class="fa-solid fa-layer-group"></i> Group By:</label>
                    <select name="group_by" style="border-color: var(--primary);">
                        <option value="none" <?php echo ($_GET['group_by'] ?? '') == 'none' ? 'selected' : ''; ?>>List All Orders</option>
                        <option value="date" <?php echo ($_GET['group_by'] ?? '') == 'date' ? 'selected' : ''; ?>>Order Date</option>
                        <option value="route" <?php echo ($_GET['group_by'] ?? '') == 'route' ? 'selected' : ''; ?>>Route</option>
                        <option value="shop" <?php echo ($_GET['group_by'] ?? '') == 'shop' ? 'selected' : ''; ?>>Shop</option>
                    </select>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" name="generate_report" class="btn btn-green"><i class="fa-solid fa-filter"></i> Run Adjusted Report</button>
                <a href="sales_summary.php" class="btn btn-dark" style="text-decoration: none;"><i class="fa-solid fa-rotate-right"></i> Reset</a>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['generate_report'])): ?>
    
    <?php
    // 1. Sorting based on Grouping
    $group_by_input = $_GET['group_by'] ?? 'none';
    $order_by_sql = "o.id DESC"; 
    
    if ($group_by_input == 'date') $order_by_sql = "DATE(o.created_at) DESC, o.id DESC";
    if ($group_by_input == 'route') $order_by_sql = "r.route_name ASC, o.id DESC";
    if ($group_by_input == 'shop') $order_by_sql = "s.shop_name ASC, o.id DESC";

    // 2. Build the Advanced SQL Query
    // NOTE: Hardcoded o.company_id and o.created_by to the secure Session IDs
    $query = "SELECT 
                o.id AS order_id, o.created_at, o.order_status,
                r.route_name, s.shop_name, 
                i.item_name, oi.quantity AS gross_qty, oi.price, 
                (oi.quantity * oi.price) AS gross_total,
                COALESCE((
                    SELECT SUM(ori.return_qty) 
                    FROM order_return_items ori 
                    WHERE ori.order_id = o.id AND ori.item_id = oi.item_id
                ), 0) AS return_qty
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              LEFT JOIN items i ON oi.item_id = i.id
              LEFT JOIN routes r ON o.route_id = r.id
              LEFT JOIN shops s ON o.shop_id = s.id
              WHERE o.order_status = 1 
              AND o.company_id = '$session_company_id' 
              AND o.created_by = '$session_user_id'";

    // Add dynamic filters
    if (!empty($_GET['route_id'])) $query .= " AND o.route_id='" . mysqli_real_escape_string($conn, $_GET['route_id']) . "'";
    if (!empty($_GET['shop_id'])) $query .= " AND o.shop_id='" . mysqli_real_escape_string($conn, $_GET['shop_id']) . "'";
    if (!empty($_GET['item_id'])) $query .= " AND oi.item_id='" . mysqli_real_escape_string($conn, $_GET['item_id']) . "'";
    if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
        $from = mysqli_real_escape_string($conn, $_GET['date_from']) . " 00:00:00";
        $to = mysqli_real_escape_string($conn, $_GET['date_to']) . " 23:59:59";
        $query .= " AND o.created_at BETWEEN '{$from}' AND '{$to}'";
    }
    
    $query .= " ORDER BY {$order_by_sql}";
    $result = mysqli_query($conn, $query);

    // 3. Process Data into Array Structure
    $reportData = [];
    $overall_gross = 0; $overall_return = 0; $overall_net = 0;

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Group Name
            $groupVal = "All My Orders";
            if ($group_by_input == 'date') $groupVal = date('d F Y', strtotime($row['created_at']));
            elseif ($group_by_input == 'route') $groupVal = $row['route_name'] ? "Route: {$row['route_name']}" : "Unassigned Route";
            elseif ($group_by_input == 'shop') $groupVal = $row['shop_name'] ? "Shop: {$row['shop_name']}" : "Unassigned Shop";

            $oid = $row['order_id'];

            if (!isset($reportData[$groupVal][$oid])) {
                $reportData[$groupVal][$oid] = [
                    'date' => $row['created_at'],
                    'route' => $row['route_name'],
                    'shop' => $row['shop_name'],
                    'items' => [],
                    'order_gross' => 0,
                    'order_return' => 0,
                    'order_net' => 0
                ];
            }

            if ($row['item_name']) {
                $return_total = $row['return_qty'] * $row['price'];
                $net_qty = $row['gross_qty'] - $row['return_qty'];
                $net_total = $row['gross_total'] - $return_total;

                $reportData[$groupVal][$oid]['items'][] = [
                    'name' => $row['item_name'],
                    'gross_qty' => $row['gross_qty'],
                    'return_qty' => $row['return_qty'],
                    'net_qty' => $net_qty,
                    'price' => $row['price'],
                    'gross_total' => $row['gross_total'],
                    'return_total' => $return_total,
                    'net_total' => $net_total
                ];

                $reportData[$groupVal][$oid]['order_gross'] += $row['gross_total'];
                $reportData[$groupVal][$oid]['order_return'] += $return_total;
                $reportData[$groupVal][$oid]['order_net'] += $net_total;

                $overall_gross += $row['gross_total'];
                $overall_return += $return_total;
                $overall_net += $net_total;
            }
        }
    }
    ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div style="background: #fff; padding: 20px; border-radius: 10px; border-left: 5px solid #007bff; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h4 style="margin:0 0 10px 0; color:#666;">My Gross Sales</h4>
            <h2 style="margin:0; color:#333;">$<?php echo number_format($overall_gross, 2); ?></h2>
        </div>
        <div style="background: #fff; padding: 20px; border-radius: 10px; border-left: 5px solid #dc3545; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h4 style="margin:0 0 10px 0; color:#666;">My Returns (-)</h4>
            <h2 style="margin:0; color:#dc3545;">-$<?php echo number_format($overall_return, 2); ?></h2>
        </div>
        <div style="background: #fff; padding: 20px; border-radius: 10px; border-left: 5px solid #28a745; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h4 style="margin:0 0 10px 0; color:#666;">My Final Net Sales</h4>
            <h2 style="margin:0; color:#28a745;">$<?php echo number_format($overall_net, 2); ?></h2>
        </div>
    </div>

    <div class="glass-panel printable">
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <span class="section-title" style="margin:0;">Itemized Transactions (Adjusted)</span>
            <button onclick="window.print()" class="btn btn-dark"><i class="fa-solid fa-print"></i> Print</button>
        </div>

        <style>
            .item-list { margin: 0; padding: 0; list-style: none; font-size: 0.85rem; }
            .item-list li { border-bottom: 1px dashed #eee; padding: 8px 0; display: flex; justify-content: space-between; align-items: center; }
            .item-list li:last-child { border-bottom: none; }
            .group-header { background-color: var(--primary); color: white; padding: 12px; font-size: 1.1rem; font-weight: bold; }
            .order-row td { vertical-align: top; padding: 12px; border-bottom: 2px solid #ddd; }
        </style>

        <?php if (!empty($reportData)): ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f4f4f4; text-align: left;">
                        <tr>
                            <th style="padding: 10px; width: 15%;">Order Info</th>
                            <th style="padding: 10px; width: 15%;">Location</th>
                            <th style="padding: 10px; width: 45%;">Itemized Breakdown (Gross - Return = Net)</th>
                            <th style="padding: 10px; width: 25%; text-align: right;">Order Adjustments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $groupName => $orders): ?>
                            
                            <?php if ($group_by_input != 'none'): ?>
                                <tr>
                                    <td colspan="4" class="group-header">
                                        <i class="fa-solid fa-folder-open"></i> <?php echo $groupName; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php 
                            $g_gross = 0; $g_ret = 0; $g_net = 0;
                            foreach ($orders as $oid => $ord): 
                                $g_gross += $ord['order_gross'];
                                $g_ret += $ord['order_return'];
                                $g_net += $ord['order_net'];
                            ?>
                                <tr class="order-row">
                                    <td>
                                        <strong>#<?php echo $oid; ?></strong><br>
                                        <small style="color:#666;"><?php echo date('d M Y, h:i A', strtotime($ord['date'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo $ord['shop'] ?: 'N/A'; ?></strong><br>
                                        <small style="color:#666;"><i class="fa-solid fa-route"></i> <?php echo $ord['route'] ?: 'N/A'; ?></small>
                                    </td>
                                    
                                    <td style="background: #fafafa;">
                                        <ul class="item-list">
                                            <?php foreach($ord['items'] as $item): ?>
                                                <li>
                                                    <div>
                                                        <strong><?php echo $item['name']; ?></strong><br>
                                                        <span style="color:#888;">Gross: <?php echo $item['gross_qty']; ?></span> | 
                                                        <span style="color:#dc3545;">Ret: <?php echo $item['return_qty']; ?></span> | 
                                                        <span style="color:#28a745; font-weight:bold;">Net: <?php echo $item['net_qty']; ?></span> 
                                                        <small>(@ $<?php echo number_format($item['price'], 2); ?>)</small>
                                                    </div>
                                                    <div style="text-align: right;">
                                                        <small style="color:#888;">$<?php echo number_format($item['gross_total'], 2); ?></small><br>
                                                        <small style="color:#dc3545;">-$<?php echo number_format($item['return_total'], 2); ?></small><br>
                                                        <strong style="color:#28a745;">$<?php echo number_format($item['net_total'], 2); ?></strong>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    
                                    <td style="text-align: right; background: #fdfdfd;">
                                        <div style="margin-bottom: 5px;"><span style="color:#888;">Gross:</span> $<?php echo number_format($ord['order_gross'], 2); ?></div>
                                        <div style="margin-bottom: 5px; border-bottom: 1px solid #ddd; padding-bottom: 5px;"><span style="color:#dc3545;">Return:</span> <span style="color:#dc3545;">-$<?php echo number_format($ord['order_return'], 2); ?></span></div>
                                        <div style="font-size: 1.1rem; color: #28a745;"><strong>Net: $<?php echo number_format($ord['order_net'], 2); ?></strong></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($group_by_input != 'none'): ?>
                                <tr style="background-color: #f1f5f9;">
                                    <td colspan="3" style="text-align: right; padding: 12px; font-weight: bold; color: #555;">Subtotal for <?php echo $groupName; ?>:</td>
                                    <td style="text-align: right; padding: 12px;">
                                        <span style="color:#888; font-size:0.9rem;">Gross: $<?php echo number_format($g_gross, 2); ?></span><br>
                                        <span style="color:#dc3545; font-size:0.9rem;">Ret: -$<?php echo number_format($g_ret, 2); ?></span><br>
                                        <strong style="color: var(--primary); font-size: 1.1rem;">Net: $<?php echo number_format($g_net, 2); ?></strong>
                                    </td>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #2c3e50; color: white;">
                            <th colspan="3" style="text-align:right; padding: 15px; font-size: 1.2rem;">Final Summary:</th>
                            <th style="padding: 15px; text-align: right;">
                                <div style="font-size: 1rem; color: #ccc;">Gross: $<?php echo number_format($overall_gross, 2); ?></div>
                                <div style="font-size: 1rem; color: #ff9999; border-bottom: 1px solid #555; padding-bottom: 5px; margin-bottom: 5px;">Return: -$<?php echo number_format($overall_return, 2); ?></div>
                                <div style="font-size: 1.3rem; color: #85e085;">Net: $<?php echo number_format($overall_net, 2); ?></div>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding: 40px; color:#999;">
                <i class="fa-solid fa-box-open fa-3x" style="margin-bottom:10px;"></i>
                <p>No orders found for the selected filters.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>