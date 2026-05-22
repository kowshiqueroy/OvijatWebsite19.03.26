<?php
/**
 * Passwordless Order Tracking
 */
require_once 'config/config.php';
require_once 'includes/Database.php';

$db = Database::getInstance();
$hash = $_GET['id'] ?? '';

$order = null;
if ($hash) {
    $order = $db->query("SELECT * FROM orders WHERE order_hash = ?", [$hash])->fetch();
}

// Handle Manual Search by ID or Email
if (!$order && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = $_POST['order_query'] ?? '';
    $order = $db->query("SELECT * FROM orders WHERE order_hash = ? OR id = ?", [$search, $search])->fetch();
}

$pageTitle = $order ? 'Track Order #' . $order['id'] : 'Order Tracking';
include 'templates/header.php';

if (!$order): ?>
    <div style="max-width: 500px; margin: 6rem auto; text-align: center;">
        <div style="background: white; padding: 3rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h1 style="font-weight: 800; color: var(--primary); margin-bottom: 1rem;">Track Your Order</h1>
            <p style="opacity: 0.6; margin-bottom: 2rem;">Enter your Order ID or the tracking hash found in your email.</p>
            
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="order_query" placeholder="Order # or Tracking ID" required style="text-align: center;">
                </div>
                <button type="submit" class="btn-harvest" style="width: 100%; padding: 1.2rem;">Track Shipment</button>
            </form>
        </div>
    </div>
<?php else:
    $steps = ['pending', 'processing', 'shipped', 'delivered'];
    $currentIdx = array_search($order['status'], $steps);
    if ($currentIdx === false) $currentIdx = 0; // fallback
?>

<div style="max-width: 800px; margin: 4rem auto;">
    <h1 style="color: var(--primary); font-weight: 800; margin-bottom: 3rem; text-align: center;">Order Journey</h1>

    <!-- Visual Timeline -->
    <div style="display: flex; justify-content: space-between; position: relative; margin-bottom: 4rem; padding: 0 2rem;">
        <div style="position: absolute; top: 15px; left: 10%; right: 10%; height: 4px; background: #eee; z-index: 0;">
            <div style="height: 100%; background: var(--primary); width: <?php echo ($currentIdx / (count($steps)-1)) * 100; ?>%; transition: width 1s;"></div>
        </div>

        <?php foreach ($steps as $idx => $step): ?>
            <div style="text-align: center; z-index: 1; width: 80px;">
                <div style="width: 35px; height: 35px; border-radius: 50%; background: <?php echo ($idx <= $currentIdx) ? 'var(--primary)' : '#eee'; ?>; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem; font-weight: 800; border: 4px solid var(--white); box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <?php echo ($idx <= $currentIdx) ? '✓' : ($idx + 1); ?>
                </div>
                <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: <?php echo ($idx <= $currentIdx) ? 'var(--primary)' : '#ccc'; ?>;">
                    <?php echo $step; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="background: var(--white); padding: 3rem; border-radius: 24px; box-shadow: var(--card-shadow); border: 1px solid #f0f0f0;">
        <h3 style="color: var(--primary); margin-bottom: 1.5rem; font-weight: 800;">Order Details</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; font-size: 0.9rem;">
            <div>
                <p style="opacity: 0.5; font-weight: 700; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.5rem;">Ship To</p>
                <strong><?php echo htmlspecialchars($order['guest_name'] ?: 'Member'); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
            </div>
            <div>
                <p style="opacity: 0.5; font-weight: 700; text-transform: uppercase; font-size: 0.7rem; margin-bottom: 0.5rem;">Status Update</p>
                <span style="padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.7rem; font-weight: 800; background: var(--bg); color: var(--primary); text-transform: uppercase;"><?php echo $order['status']; ?></span>
                <p style="margin-top: 1rem; opacity: 0.7;">Last updated: <?php echo $order['created_at']; ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
