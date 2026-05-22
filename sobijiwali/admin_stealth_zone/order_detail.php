<?php
/**
 * Admin Order Detail & Messaging
 */
$pageTitle = 'Order Details';
require_once 'layout_header.php';

$db = Database::getInstance();
$logger = new Logger();
$orderId = (int)$_GET['order_id'];

// Handle New Message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF invalid.");
    $msg = trim($_POST['message']);
    if ($msg) {
        $db->query("INSERT INTO order_messages (order_id, sender_id, sender_type, message) VALUES (?, ?, 'admin', ?)", 
                   [$orderId, $_SESSION['user_id'], $msg]);
        $logger->log('send_order_message', 'order', $orderId, "Sent message to customer");
    }
}

// Fetch Order
$order = $db->query("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?", [$orderId])->fetch();
if (!$order) die("Order not found.");

// Fetch Items
$items = $db->query("SELECT oi.*, p.name, v.name_modifier FROM order_items oi JOIN product_variations v ON oi.product_variation_id = v.id JOIN products p ON v.product_id = p.id WHERE oi.order_id = ?", [$orderId])->fetchAll();

// Fetch Messages
$messages = $db->query("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC", [$orderId])->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Order #<?php echo $orderId; ?></h1>
    <div style="display: flex; gap: 10px;">
        <a href="dashboard.php" class="btn btn-outline">&larr; Back to List</a>
        <a href="order_edit.php?order_id=<?php echo $orderId; ?>" class="btn btn-outline">Edit Order</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start;">
    <div>
        <div class="card">
            <h3 style="margin-bottom: 1.5rem; color: var(--primary);">Items & Pricing</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                <div style="font-size: 0.7rem; opacity: 0.5;"><?php echo htmlspecialchars($item['name_modifier']); ?></div>
                            </td>
                            <td>× <?php echo $item['quantity']; ?></td>
                            <td style="text-align: right; font-weight: 700;">$<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="padding-top: 2rem; font-weight: 800; font-size: 1rem;">GRAND TOTAL</td>
                        <td style="padding-top: 2rem; text-align: right; font-weight: 800; font-size: 1.2rem; color: var(--primary);">$<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 1.5rem; color: var(--primary);">Delivery Information</h3>
            <p><strong>Recipient:</strong> <?php echo htmlspecialchars($order['guest_name'] ?: 'Member Account'); ?></p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['user_email'] ?: $order['guest_email']); ?> / <?php echo htmlspecialchars($order['guest_phone']); ?></p>
            <div style="margin-top: 1rem; padding: 1rem; background: var(--bg); border-radius: 8px; font-size: 0.9rem;">
                <strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="card" style="height: 600px; display: flex; flex-direction: column;">
            <h3 style="margin-bottom: 1.5rem; color: var(--primary);">B2B Communications</h3>
            <div style="flex: 1; overflow-y: auto; background: var(--bg); border-radius: 8px; padding: 1rem; display: flex; flex-direction: column; gap: 10px;" id="chat-box">
                <?php foreach ($messages as $m): ?>
                    <div style="padding: 10px 15px; border-radius: 12px; max-width: 85%; font-size: 0.8rem; line-height: 1.4; <?php echo $m['sender_type'] === 'admin' ? 'align-self: flex-end; background: var(--primary); color: white;' : 'align-self: flex-start; background: white; border: 1px solid #ddd;'; ?>">
                        <?php echo htmlspecialchars($m['message']); ?>
                        <div style="font-size: 0.6rem; opacity: 0.6; margin-top: 5px;"><?php echo date('M d, H:i', strtotime($m['created_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <textarea name="message" placeholder="Type message to customer..." style="height: 80px; margin-bottom: 0.5rem;" required></textarea>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem;">Send Message</button>
            </form>
        </div>
    </aside>
</div>

<script>const cb = document.getElementById('chat-box'); cb.scrollTop = cb.scrollHeight;</script>

<?php require_once 'layout_footer.php'; ?>
