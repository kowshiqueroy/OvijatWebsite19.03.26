<?php
/**
 * AJAX: returns last 10 confirmed orders for a shop.
 * Called by order_item.php via fetch().
 */
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }

$shop_id    = (int)($_GET['shop_id']  ?? 0);
$exclude_id = (int)($_GET['order_id'] ?? 0);
$cid        = (int)$_SESSION['company_id'];

if (!$shop_id) { echo '<p class="text-muted text-sm">No shop specified.</p>'; exit; }

$stmt = $conn->prepare(
    "SELECT o.id, o.order_date, o.delivery_date
     FROM orders o
     WHERE o.company_id=? AND o.shop_id=? AND o.status=1 AND o.order_status=1 AND o.id!=?
     ORDER BY o.id DESC LIMIT 10"
);
$stmt->bind_param("iii", $cid, $shop_id, $exclude_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

if ($orders->num_rows === 0) {
    echo '<p class="text-muted text-sm">No previous confirmed orders for this shop.</p>';
    exit;
}

echo '<div style="overflow-x:auto">';
echo '<table style="width:100%;font-size:0.78rem;border-collapse:collapse">';
echo '<thead><tr style="background:var(--gray-100)">
        <th style="padding:6px 8px;text-align:left">Order</th>
        <th style="padding:6px 8px;text-align:left">Date</th>
        <th style="padding:6px 8px;text-align:left">Items</th>
        <th style="padding:6px 8px;text-align:right">Total</th>
      </tr></thead><tbody>';

while ($ord = $orders->fetch_assoc()) {
    $oid = (int)$ord['id'];

    /* Fetch items */
    $istmt = $conn->prepare(
        "SELECT i.item_name, oi.quantity, oi.price
         FROM order_items oi JOIN items i ON i.id=oi.item_id
         WHERE oi.order_id=?"
    );
    $istmt->bind_param("i", $oid); $istmt->execute();
    $items_res = $istmt->get_result(); $istmt->close();

    $item_lines = []; $total = 0;
    while ($it = $items_res->fetch_assoc()) {
        $line = $it['quantity'] * $it['price'];
        $total += $line;
        $item_lines[] = htmlspecialchars($it['item_name']) . ' × ' . (int)$it['quantity']
                        . ' @ ' . number_format($it['price'], 0);
    }

    $items_html = implode('<br>', $item_lines) ?: '<em>No items</em>';

    echo '<tr style="border-bottom:1px solid var(--border)">';
    echo '<td style="padding:6px 8px"><a href="order_item.php?order_id='.$oid.'" style="color:var(--primary);font-weight:700">#'.$oid.'</a></td>';
    echo '<td style="padding:6px 8px;white-space:nowrap">'.htmlspecialchars($ord['order_date']).'</td>';
    echo '<td style="padding:6px 8px">'.$items_html.'</td>';
    echo '<td style="padding:6px 8px;text-align:right;font-weight:700">'.number_format($total, 0).'</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';
