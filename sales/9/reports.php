<?php
include 'header.php';
?>

<div class="container">
    <div class="form-section glass-panel" style="margin-bottom: 20px;">
        <h2 style="margin-top:0;"><i class="fa-solid fa-file-invoice-dollar"></i> Sales Report</h2>
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div><label>Date From</label><input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-d'); ?>"></div>
                <div><label>Date To</label><input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>"></div>
                <div>
                    <label>Company</label>
                    <select name="company_id">
                        <option value="">All Companies</option>
                        <?php
                        // FIXED: Removed 'WHERE status = 1'
                        $comp_q = mysqli_query($conn, "SELECT id, name FROM companies ORDER BY name ASC");
                        if ($comp_q) {
                            while ($comp = mysqli_fetch_assoc($comp_q)) {
                                $sel = ($_GET['company_id'] ?? '') == $comp['id'] ? 'selected' : '';
                                echo "<option value='{$comp['id']}' $sel>{$comp['name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label>User</label>
                    <select name="user_id">
                        <option value="">All Users</option>
                        <?php
                        $user_q = mysqli_query($conn, "SELECT id, username FROM users WHERE status=1");
                        if ($user_q) {
                            while ($u = mysqli_fetch_assoc($user_q)) {
                                $sel = ($_GET['user_id'] ?? '') == $u['id'] ? 'selected' : '';
                                echo "<option value='{$u['id']}' $sel>{$u['username']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 15px;">
                <button type="submit" name="generate_report" class="btn btn-primary"><i class="fa-solid fa-gears"></i> Generate</button>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['generate_report'])): ?>
    <div class="glass-panel printable">
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <span class="section-title" style="margin:0;">Report Results</span>
            <button onclick="window.print()" class="btn btn-dark"><i class="fa-solid fa-print"></i> Print</button>
        </div>

        <table class="table-simple" style="width: 100%; text-align: left; border-collapse: collapse;">
            <thead style="background: #f4f4f4;">
                <tr>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Order #</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Date</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Company</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Created By</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Total Value</th>
                    <th style="padding: 10px; border-bottom: 2px solid #ddd;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // We use a subquery to calculate total value from order_items
                $query = "SELECT o.id, o.created_at, o.order_status, c.name as company_name, u.username, 
                                 COALESCE((SELECT SUM(quantity * price) FROM order_items WHERE order_id = o.id), 0) as total_amount
                          FROM orders o 
                          LEFT JOIN companies c ON o.company_id = c.id
                          LEFT JOIN users u ON o.created_by = u.id 
                          WHERE 1=1";

                if (!empty($_GET['company_id'])) $query .= " AND o.company_id='" . mysqli_real_escape_string($conn, $_GET['company_id']) . "'";
                if (!empty($_GET['user_id'])) $query .= " AND o.created_by='" . mysqli_real_escape_string($conn, $_GET['user_id']) . "'";
                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $query .= " AND o.created_at BETWEEN '" . mysqli_real_escape_string($conn, $_GET['date_from']) . " 00:00:00' AND '" . mysqli_real_escape_string($conn, $_GET['date_to']) . " 23:59:59'";
                }
                $query .= " ORDER BY o.created_at DESC";

                $result = mysqli_query($conn, $query);
                $grand_total = 0;

                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $val = $row['total_amount']; 
                        $grand_total += $val;
                        $status = $row['order_status'] == 1 ? "<span style='color:green;'>Confirmed</span>" : "<span style='color:orange;'>Pending</span>";
                        
                        echo "<tr>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>#{$row['id']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$row['created_at']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$row['company_name']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$row['username']}</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>$" . number_format($val, 2) . "</td>
                                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$status}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No records found.</td></tr>";
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" style="text-align:right; padding: 10px; border-top: 2px solid #ddd;">Grand Total:</th>
                    <th colspan="2" style="padding: 10px; border-top: 2px solid #ddd;">$<?php echo number_format($grand_total, 2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>