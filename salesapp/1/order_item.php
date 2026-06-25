<?php
$pageTitle = 'Order Items';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* ── Validate order_id ── */
$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { header("Location: orders.php"); exit; }

/* ── Load order ── */
$stmt = $conn->prepare(
    "SELECT o.*, s.shop_name, s.balance AS shop_balance,
            r.route_name, u.username AS approved_by_name
     FROM orders o
     JOIN shops s ON s.id=o.shop_id
     JOIN routes r ON r.id=o.route_id
     LEFT JOIN users u ON u.id=o.approved_by
     WHERE o.id=? AND o.company_id=?"
);
$stmt->bind_param("ii", $order_id, $cid); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$order) { header("Location: orders.php"); exit; }

$is_locked = ($order['order_status'] == 1 && !$is_manager);

/* ── Confirm order ── */
if (isset($_GET['confirm']) && $order['order_status'] == 0) {
    /* Must have at least one item */
    $cnt_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM order_items WHERE order_id=?");
    $cnt_stmt->bind_param("i", $order_id); $cnt_stmt->execute();
    $cnt = (int)$cnt_stmt->get_result()->fetch_assoc()['c']; $cnt_stmt->close();
    if ($cnt === 0) {
        $error = "Cannot confirm an order with no items.";
    } else {
        $upd = $conn->prepare("UPDATE orders SET order_status=1, approved_at=NOW(), approved_by=? WHERE id=? AND company_id=?");
        $upd->bind_param("iii", $uid, $order_id, $cid); $upd->execute(); $upd->close();
        header("Location: order_item.php?order_id=$order_id&msg=confirmed"); exit;
    }
}

/* ── Delete item ── */
if (isset($_GET['del']) && !$is_locked) {
    $del_id = (int)$_GET['del'];
    $stmt = $conn->prepare("DELETE FROM order_items WHERE id=? AND order_id=?");
    $stmt->bind_param("ii", $del_id, $order_id); $stmt->execute(); $stmt->close();
    header("Location: order_item.php?order_id=$order_id&msg=removed"); exit;
}

/* ── Add item ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item']) && !$is_locked) {
    $item_id  = (int)$_POST['item_id'];
    $quantity = (float)$_POST['quantity'];
    $price    = (float)$_POST['price'];

    if (!$item_id || $quantity <= 0 || $price <= 0) {
        $error = 'Select an item and enter valid quantity and price.';
    } else {
        /* Check duplicate */
        $chk = $conn->prepare("SELECT id FROM order_items WHERE item_id=? AND order_id=?");
        $chk->bind_param("ii", $item_id, $order_id); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'This item is already in the order. Delete it first to re-add.';
        } else {
            $chk->close();
            $ins = $conn->prepare("INSERT INTO order_items (order_id, item_id, price, quantity) VALUES (?,?,?,?)");
            $ins->bind_param("iidd", $order_id, $item_id, $price, $quantity);
            $ins->execute(); $ins->close();
            header("Location: order_item.php?order_id=$order_id&msg=added"); exit;
        }
        $chk->close();
    }
}

/* ── Items in this order ── */
$items_stmt = $conn->prepare(
    "SELECT oi.*, i.item_name FROM order_items oi
     JOIN items i ON i.id=oi.item_id
     WHERE oi.order_id=? ORDER BY oi.id"
);
$items_stmt->bind_param("i", $order_id); $items_stmt->execute();
$items_res  = $items_stmt->get_result();
$total_amt  = 0; $items_arr = [];
while ($r = $items_res->fetch_assoc()) { $items_arr[] = $r; $total_amt += $r['price'] * $r['quantity']; }
$items_stmt->close();

/* ── Catalogue for dropdown ── */
$cat = $conn->query("SELECT id, item_name, price FROM items WHERE company_id=$cid AND status=1 ORDER BY item_name");
$catalogue = [];
while ($r = $cat->fetch_assoc()) $catalogue[$r['id']] = $r;

/* Messages */
$msgs = ['added'=>'Item added.','removed'=>'Item removed.','confirmed'=>'Order confirmed successfully.'];
$success = $msgs[$_GET['msg'] ?? ''] ?? '';
$error   = $error ?? '';
?>

<div class="page-header">
    <div>
        <div class="page-title">Order #<?= $order_id ?></div>
        <div class="page-subtitle">
            <?= htmlspecialchars($order['route_name']) ?> &rsaquo; <?= htmlspecialchars($order['shop_name']) ?>
            &nbsp;&bull;&nbsp; <?= date('d M Y', strtotime($order['order_date'])) ?>
            &nbsp;&bull;&nbsp; Delivery: <?= date('d M Y', strtotime($order['delivery_date'])) ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <button onclick="window.print()" class="btn btn-ghost btn-sm print-hide"><i class="fa-solid fa-print"></i></button>
        <?php if ($order['order_status'] == 0 && !$is_locked): ?>
            <a href="order_item.php?order_id=<?= $order_id ?>&confirm=1"
               onclick="return confirm('Confirm this order? It cannot be edited after confirmation.')"
               class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Confirm Order</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Order Meta Card -->
<div class="card mb-20">
    <div class="grid-layout md-4">
        <div>
            <div class="text-muted text-xs">Status</div>
            <span class="badge <?= $order['order_status'] ? 'badge-green' : 'badge-yellow' ?>"><?= $order['order_status'] ? 'Confirmed' : 'Draft' ?></span>
        </div>
        <div>
            <div class="text-muted text-xs">Shop Balance</div>
            <div class="fw-600 <?= $order['shop_balance'] > 0 ? 'text-green' : '' ?>"><?= number_format($order['shop_balance'], 0) ?></div>
        </div>
        <?php if ($order['approved_by_name']): ?>
        <div>
            <div class="text-muted text-xs">Approved By</div>
            <div class="fw-600"><?= htmlspecialchars($order['approved_by_name']) ?></div>
            <div class="text-muted text-xs"><?= date('d M Y h:i a', strtotime($order['approved_at'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($order['remarks']): ?>
        <div>
            <div class="text-muted text-xs">Remarks</div>
            <div class="text-sm"><?= htmlspecialchars($order['remarks']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Print header -->
<div class="print-header">
    <h1><?= APP_NAME ?> &mdash; Order Invoice #<?= $order_id ?></h1>
    <p>
        Route: <?= htmlspecialchars($order['route_name']) ?> | Shop: <?= htmlspecialchars($order['shop_name']) ?><br>
        Order Date: <?= $order['order_date'] ?> | Delivery Date: <?= $order['delivery_date'] ?> | Printed: <?= date('d M Y H:i') ?>
    </p>
</div>

<!-- Add Item Form -->
<?php if (!$is_locked): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Add Item</span></div>
    <form method="POST" action="order_item.php?order_id=<?= $order_id ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Item <span style="color:var(--danger)">*</span></label>
                <select name="item_id" id="item_select" required>
                    <option value="">Select item</option>
                    <?php foreach ($catalogue as $id => $item): ?>
                        <option value="<?= $id ?>" data-price="<?= $item['price'] ?>"><?= htmlspecialchars($item['item_name']) ?> &mdash; <?= number_format($item['price'], 2) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity <span style="color:var(--danger)">*</span></label>
                <input type="number" name="quantity" id="qty_input" step="1" min="1" placeholder="e.g. 10" required>
            </div>
            <div class="form-group">
                <label>Price (BDT) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="price" id="price_input" step="0.01" min="0.01" placeholder="Auto-filled" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="add_item" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Item</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Items Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Items in Order</span>
        <span class="badge badge-blue"><?= count($items_arr) ?> items</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th class="text-right">Total</th>
                    <?php if (!$is_locked): ?><th class="print-hide"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items_arr) > 0): ?>
                    <?php $sn = 0; foreach ($items_arr as $item): $sn++; $line = $item['price'] * $item['quantity']; ?>
                    <tr>
                        <td class="text-muted"><?= $sn ?></td>
                        <td class="fw-600"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= number_format($item['price'], 2) ?></td>
                        <td><?= number_format($item['quantity'], 0) ?></td>
                        <td class="text-right fw-600"><?= number_format($line, 2) ?></td>
                        <?php if (!$is_locked): ?>
                        <td class="print-hide">
                            <a href="order_item.php?order_id=<?= $order_id ?>&del=<?= $item['id'] ?>"
                               onclick="return confirm('Remove this item?')"
                               class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="<?= $is_locked ? 5 : 6 ?>" class="text-center text-muted" style="padding:30px">No items yet. Add items above.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--gray-100)">
                    <td colspan="<?= $is_locked ? 4 : 4 ?>" class="text-right fw-700">Order Total:</td>
                    <td class="text-right fw-700" style="font-size:1.1rem"><?= number_format($total_amt, 2) ?></td>
                    <?php if (!$is_locked): ?><td class="print-hide"></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Last 10 Orders (lazy-loaded) -->
<div class="card print-hide">
    <div class="card-header">
        <span class="card-title">Previous Orders from this Shop</span>
        <button class="btn btn-ghost btn-sm" id="loadPrevBtn" onclick="loadPrevOrders()">
            <i class="fa-solid fa-clock-rotate-left"></i> Load
        </button>
    </div>
    <div id="prevOrdersArea" class="text-muted text-sm" style="padding:12px">
        Click "Load" to see last 10 orders for <strong><?= htmlspecialchars($order['shop_name']) ?></strong>.
    </div>
</div>

<script>
/* Auto-fill price when item is selected */
document.getElementById('item_select').addEventListener('change', function() {
    var price = this.options[this.selectedIndex].getAttribute('data-price');
    document.getElementById('price_input').value = price ? parseFloat(price).toFixed(2) : '';
});

$(document).ready(function() {
    $('#item_select').select2({ width: '100%', placeholder: 'Select Item' });
});

function loadPrevOrders() {
    var area = document.getElementById('prevOrdersArea');
    var btn  = document.getElementById('loadPrevBtn');
    if (btn.classList.contains('loaded')) {
        area.style.display = area.style.display === 'none' ? 'block' : 'none';
        return;
    }
    area.innerHTML = '<span class="text-muted">Loading...</span>';
    fetch('getLast10Orders.php?shop_id=<?= $order['shop_id'] ?>&order_id=<?= $order_id ?>')
        .then(r => r.text())
        .then(html => { area.innerHTML = html; btn.classList.add('loaded'); });
}
</script>

<?php include 'footer.php'; ?>
