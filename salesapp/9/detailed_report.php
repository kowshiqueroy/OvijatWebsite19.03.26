<?php
include 'header.php';
?>

<div class="container">
    <div class="form-section glass-panel" style="margin-bottom: 20px;">
        <h2 style="margin-top:0;"><i class="fa-solid fa-layer-group"></i> Advanced Grouped Report</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">Filter your data and choose how you want to group the results visually.</p>
        
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div><label>Date From</label><input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-d'); ?>"></div>
                <div><label>Date To</label><input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>"></div>

                <div>
                    <label>Company</label>
                    <select name="company_id">
                        <option value="">-- All Companies --</option>
                        <?php
                        $q = mysqli_query($conn, "SELECT id, name FROM companies ORDER BY name ASC");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['company_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>User (Sales Rep)</label>
                    <select name="user_id">
                        <option value="">-- All Users --</option>
                        <?php
                        $q = mysqli_query($conn, "SELECT id, username FROM users WHERE status=1");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['user_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['username']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>Route</label>
                    <select name="route_id">
                        <option value="">-- All Routes --</option>
                        <?php
                        $q = mysqli_query($conn, "SELECT id, route_name FROM routes WHERE status=1");
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
                        $q = mysqli_query($conn, "SELECT id, shop_name FROM shops WHERE status=1");
                        if ($q) while ($row = mysqli_fetch_assoc($q)) {
                            $sel = ($_GET['shop_id'] ?? '') == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['shop_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="grid-column: span 2; background: #eef2f5; padding: 10px; border-radius: 8px;">
                    <label style="color: var(--primary); font-weight: bold;"><i class="fa-solid fa-object-group"></i> Group Results By:</label>
                    <select name="group_by" style="border-color: var(--primary);">
                        <option value="none" <?php echo ($_GET['group_by'] ?? '') == 'none' ? 'selected' : ''; ?>>No Grouping (List All Orders)</option>
                        <option value="date" <?php echo ($_GET['group_by'] ?? '') == 'date' ? 'selected' : ''; ?>>Order Date</option>
                        <option value="route" <?php echo ($_GET['group_by'] ?? '') == 'route' ? 'selected' : ''; ?>>Route</option>
                        <option value="shop" <?php echo ($_GET['group_by'] ?? '') == 'shop' ? 'selected' : ''; ?>>Shop</option>
                        <option value="user" <?php echo ($_GET['group_by'] ?? '') == 'user' ? 'selected' : ''; ?>>User / Sales Rep</option>
                        <option value="company" <?php echo ($_GET['group_by'] ?? '') == 'company' ? 'selected' : ''; ?>>Company</option>
                    </select>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" name="generate_detailed" class="btn btn-green"><i class="fa-solid fa-filter"></i> Apply Filters</button>
                <a href="detailed_report.php" class="btn btn-dark" style="text-decoration: none;"><i class="fa-solid fa-rotate-right"></i> Reset</a>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['generate_detailed'])): ?>
    <div class="glass-panel printable">
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <span class="section-title" style="margin:0;">Report Results</span>
            <button onclick="window.print()" class="btn btn-dark"><i class="fa-solid fa-print"></i> Print Report</button>
        </div>

        <?php
        // 1. Determine Sorting based on Grouping
        $group_by_input = $_GET['group_by'] ?? 'none';
        $order_by_sql = "o.id DESC"; // Default
        
        if ($group_by_input == 'date') $order_by_sql = "DATE(o.created_at) DESC, o.id DESC";
        if ($group_by_input == 'route') $order_by_sql = "r.route_name ASC, o.id DESC";
        if ($group_by_input == 'shop') $order_by_sql = "s.shop_name ASC, o.id DESC";
        if ($group_by_input == 'user') $order_by_sql = "u.username ASC, o.id DESC";
        if ($group_by_input == 'company') $order_by_sql = "c.name ASC, o.id DESC";

        // 2. Build the SQL Query
        $query = "SELECT 
                    o.id AS order_id, o.created_at, o.order_status,
                    c.name AS company_name, u.username, r.route_name, s.shop_name, 
                    i.item_name, oi.quantity, oi.price, (oi.quantity * oi.price) AS line_total
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  LEFT JOIN items i ON oi.item_id = i.id
                  LEFT JOIN companies c ON o.company_id = c.id
                  LEFT JOIN users u ON o.created_by = u.id
                  LEFT JOIN routes r ON o.route_id = r.id
                  LEFT JOIN shops s ON o.shop_id = s.id
                  WHERE 1=1";

        if (!empty($_GET['company_id'])) $query .= " AND o.company_id='" . mysqli_real_escape_string($conn, $_GET['company_id']) . "'";
        if (!empty($_GET['user_id'])) $query .= " AND o.created_by='" . mysqli_real_escape_string($conn, $_GET['user_id']) . "'";
        if (!empty($_GET['route_id'])) $query .= " AND o.route_id='" . mysqli_real_escape_string($conn, $_GET['route_id']) . "'";
        if (!empty($_GET['shop_id'])) $query .= " AND o.shop_id='" . mysqli_real_escape_string($conn, $_GET['shop_id']) . "'";
        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
            $from = mysqli_real_escape_string($conn, $_GET['date_from']) . " 00:00:00";
            $to = mysqli_real_escape_string($conn, $_GET['date_to']) . " 23:59:59";
            $query .= " AND o.created_at BETWEEN '{$from}' AND '{$to}'";
        }
        
        $query .= " ORDER BY {$order_by_sql}";
        $result = mysqli_query($conn, $query);

        // 3. Process Data into a Grouped Array Structure
        $reportData = [];
        $overall_grand_total = 0;

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Determine the Group Name
                $groupVal = "All Orders";
                if ($group_by_input == 'date') $groupVal = date('d F Y', strtotime($row['created_at']));
                elseif ($group_by_input == 'route') $groupVal = $row['route_name'] ? "Route: {$row['route_name']}" : "Unassigned Route";
                elseif ($group_by_input == 'shop') $groupVal = $row['shop_name'] ? "Shop: {$row['shop_name']}" : "Unassigned Shop";
                elseif ($group_by_input == 'user') $groupVal = $row['username'] ? "Rep: {$row['username']}" : "Unassigned User";
                elseif ($group_by_input == 'company') $groupVal = $row['company_name'] ? "Company: {$row['company_name']}" : "Unassigned Company";

                $oid = $row['order_id'];

                // Initialize Order if it doesn't exist in the array yet
                if (!isset($reportData[$groupVal][$oid])) {
                    $reportData[$groupVal][$oid] = [
                        'date' => $row['created_at'],
                        'status' => $row['order_status'],
                        'company' => $row['company_name'],
                        'user' => $row['username'],
                        'route' => $row['route_name'],
                        'shop' => $row['shop_name'],
                        'items' => [],
                        'order_total' => 0
                    ];
                }

                // Append items to the order
                if ($row['item_name']) {
                    $reportData[$groupVal][$oid]['items'][] = [
                        'name' => $row['item_name'],
                        'qty' => $row['quantity'],
                        'price' => $row['price'],
                        'total' => $row['line_total']
                    ];
                    $reportData[$groupVal][$oid]['order_total'] += $row['line_total'];
                    $overall_grand_total += $row['line_total'];
                }
            }
        }
        ?>

        <style>
            .item-list { margin: 0; padding: 0; list-style: none; font-size: 0.85rem; }
            .item-list li { border-bottom: 1px dashed #ccc; padding: 4px 0; display: flex; justify-content: space-between; }
            .item-list li:last-child { border-bottom: none; }
            .group-header { background-color: var(--primary); color: white; padding: 12px; font-size: 1.1rem; font-weight: bold; }
            .order-row td { vertical-align: top; padding: 12px; border-bottom: 1px solid #ddd; }
        </style>

        <?php if (!empty($reportData)): ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f4f4f4; text-align: left;">
                    <tr>
                        <th style="padding: 10px;">Order # & Date</th>
                        <th style="padding: 10px;">Network (User/Co)</th>
                        <th style="padding: 10px;">Location (Route/Shop)</th>
                        <th style="padding: 10px; width: 35%;">Items Sold</th>
                        <th style="padding: 10px; text-align: right;">Order Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $groupName => $orders): ?>
                        
                        <?php if ($group_by_input != 'none'): ?>
                            <tr>
                                <td colspan="5" class="group-header">
                                    <i class="fa-solid fa-folder-open"></i> <?php echo $groupName; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php 
                        $group_subtotal = 0;
                        foreach ($orders as $oid => $ord): 
                            $group_subtotal += $ord['order_total'];
                            $status_color = $ord['status'] == 1 ? "green" : "orange";
                        ?>
                            <tr class="order-row">
                                <td>
                                    <strong>#<?php echo $oid; ?></strong><br>
                                    <small style="color:#666;"><?php echo date('d M Y, h:i A', strtotime($ord['date'])); ?></small><br>
                                    <span style='font-size:0.75rem; color:<?php echo $status_color; ?>; border:1px solid <?php echo $status_color; ?>; padding:1px 4px; border-radius:3px;'>
                                        <?php echo $ord['status'] == 1 ? 'Confirmed' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $ord['user'] ?: 'N/A'; ?></strong><br>
                                    <small style="color:#666;"><?php echo $ord['company'] ?: 'N/A'; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo $ord['shop'] ?: 'N/A'; ?></strong><br>
                                    <small style="color:#666;"><i class="fa-solid fa-route"></i> <?php echo $ord['route'] ?: 'N/A'; ?></small>
                                </td>
                                
                                <td>
                                    <ul class="item-list">
                                        <?php if(empty($ord['items'])) echo "<li>No items found.</li>"; ?>
                                        <?php foreach($ord['items'] as $item): ?>
                                            <li>
                                                <span><?php echo $item['name']; ?> <em>(<?php echo $item['qty']; ?> x $<?php echo number_format($item['price'], 2); ?>)</em></span>
                                                <strong>$<?php echo number_format($item['total'], 2); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td style="text-align: right; font-weight: bold; font-size: 1.1rem; color: #333;">
                                    $<?php echo number_format($ord['order_total'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($group_by_input != 'none'): ?>
                            <tr style="background-color: #fcfcfc;">
                                <td colspan="4" style="text-align: right; padding: 10px; font-weight: bold; color: #555;">Subtotal for <?php echo $groupName; ?>:</td>
                                <td style="text-align: right; padding: 10px; font-weight: bold; color: var(--primary);">$<?php echo number_format($group_subtotal, 2); ?></td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #2c3e50; color: white;">
                        <th colspan="4" style="text-align:right; padding: 15px; font-size: 1.2rem;">Final Grand Total:</th>
                        <th style="padding: 15px; text-align: right; font-size: 1.2rem;">$<?php echo number_format($overall_grand_total, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
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