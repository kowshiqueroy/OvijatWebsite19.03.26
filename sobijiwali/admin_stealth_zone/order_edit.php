<?php
/**
 * Order Editor & Item Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';
require_once __DIR__ . '/../includes/Logger.php';

AuthManager::requireRole('admin', 'gatekeeper.php');

$db = Database::getInstance();
$logger = new Logger();
$orderId = (int)$_GET['order_id'];
$message = '';

// Handle Item Deletion
if (isset($_GET['delete_item_id'])) {
    $itemId = (int)$_GET['delete_item_id'];
    $db->query("DELETE FROM order_items WHERE id = ? AND order_id = ?", [$itemId, $orderId]);
    $db->query("UPDATE orders SET total_amount = (SELECT SUM(total_price) FROM order_items WHERE order_id = ?) WHERE id = ?", [$orderId, $orderId]);
    $logger->log('edit_order_remove_item', 'order', $orderId, "Removed item #$itemId");
    $message = "Item removed.";
}

// Handle Manual Discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discount_amount'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF invalid.");
    $discount = (float)$_POST['discount_amount'];
    $db->query("UPDATE orders SET total_amount = total_amount - ? WHERE id = ?", [$discount, $orderId]);
    $logger->log('edit_order_discount', 'order', $orderId, "Applied manual discount: \$$discount");
    $message = "Discount applied.";
}

// Fetch Order & Items
$order = $db->query("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?", [$orderId])->fetch();
$items = $db->query("SELECT oi.*, p.name, v.name_modifier FROM order_items oi JOIN product_variations v ON oi.product_variation_id = v.id JOIN products p ON v.product_id = p.id WHERE oi.order_id = ?", [$orderId])->fetchAll();

if (!$order) die("Order not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Order #<?php echo $orderId; ?> | Admin</title>
    <style>
        :root { --primary: #2D5A27; --text: #2c3e50; --bg: #f4f7f6; --white: #fff; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; font-size: 14px; }
        .sidebar { width: 220px; background: #2c3e50; color: #fff; height: 100vh; padding: 20px; box-sizing: border-box; position: fixed; }
        .main { margin-left: 220px; flex-grow: 1; padding: 40px; max-width: 900px; }
        .card { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .btn { padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 700; border: none; cursor: pointer; }
        .btn-danger { background: #e53e3e; color: #fff; }
        .btn-save { background: var(--primary); color: #fff; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Sobjiwali</h2>
        <p><a href="dashboard.php" style="color:#fff; text-decoration:none;">&larr; Back to Dashboard</a></p>
    </div>
    <div class="main">
        <h1>Edit Order #<?php echo $orderId; ?></h1>
        
        <?php if ($message): ?><div style="padding:15px; background:#d4edda; color:#155724; border-radius:8px; margin-bottom:20px;"><?php echo $message; ?></div><?php endif; ?>

        <div class="card">
            <h3>Modify Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?php echo $item['name']; ?></strong><br><small><?php echo $item['name_modifier']; ?></small></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                        <td>
                            <a href="?order_id=<?php echo $orderId; ?>&delete_item_id=<?php echo $item['id']; ?>" class="btn btn-danger" onclick="return confirm('Remove this item?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Adjustments</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <div style="display:flex; gap:10px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:0.7rem; font-weight:800; opacity:0.5; margin-bottom:5px;">MANUAL DISCOUNT AMOUNT ($)</label>
                        <input type="number" step="0.01" name="discount_amount" placeholder="0.00" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <button type="submit" class="btn btn-save" style="padding:12px 20px;">Apply Discount</button>
                </div>
            </form>
        </div>

        <div style="text-align:right;">
            <div style="font-size:0.9rem; opacity:0.5; margin-bottom:5px;">NEW ORDER TOTAL</div>
            <div style="font-size:2rem; font-weight:800; color:var(--primary);">$<?php echo number_format($order['total_amount'], 2); ?></div>
        </div>
    </div>
</body>
</html>
