<?php
// ============================================================
// modules/pos/pos.php — POS selling interface
// ============================================================

// ── AJAX endpoints ────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action === 'get_variants') {
    header('Content-Type: application/json');
    // Support comma-separated product IDs for same-name merging
    $rawIds = $_GET['product_ids'] ?? ($_GET['product_id'] ?? '0');
    $pids   = array_filter(array_map('intval', explode(',', $rawIds)));
    if (!$pids) { echo json_encode([]); exit; }
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $rows = dbFetchAll(
        "SELECT v.id AS variant_id, p.name, v.variant_name, v.size, v.color, v.price, v.regular, v.quantity, v.barcode
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.product_id IN ($placeholders) AND v.quantity > 0
         ORDER BY v.variant_name, v.size, v.color, v.price",
        $pids
    );
    echo json_encode($rows); exit;
}

if ($action === 'barcode_lookup') {
    header('Content-Type: application/json');
    $bc  = trim($_GET['barcode'] ?? '');
    $row = dbFetch(
        "SELECT v.id AS variant_id, p.name, v.variant_name, v.size, v.color, v.price, v.regular, v.quantity
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id = ? OR v.barcode = ?",
        [(int)$bc, $bc]
    );
    echo json_encode($row ?: (object)[]); exit;
}

if ($action === 'lookup_customer') {
    header('Content-Type: application/json');
    $phone = trim($_GET['phone'] ?? '');
    $c = dbFetch('SELECT id, name, points FROM customers WHERE phone = ?', [$phone]);
    echo json_encode($c ?: (object)[]); exit;
}

if ($action === 'suggest_customers') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $rows = dbFetchAll(
        "SELECT id, name, phone, points FROM customers
         WHERE phone LIKE ? OR name LIKE ?
         ORDER BY name LIMIT 10",
        ["%$q%", "%$q%"]
    );
    echo json_encode($rows); exit;
}

// ── Process sale ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_sale') {
    requireLogin();
    verify_csrf();
    $cartItems = json_decode($_POST['cart_json'] ?? '[]', true) ?: [];
    if (empty($cartItems)) { flash('error', 'Cart is empty.'); redirect('pos'); }

    $editSaleId    = (int)($_POST['edit_sale_id'] ?? 0); // For draft editing
    $customerId    = (int)($_POST['customer_id'] ?? 0);
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerName  = trim($_POST['customer_name'] ?? '');

    // Auto-create new customer if phone is provided but no ID matches
    if (!$customerId && $customerPhone) {
        $existing = dbFetch("SELECT id FROM customers WHERE phone = ?", [$customerPhone]);
        if ($existing) {
            $customerId = $existing['id'];
        } else {
            $customerId = dbInsert('customers', [
                'name'       => $customerName ?: 'Walk-in Customer',
                'phone'      => $customerPhone,
                'points'     => 0,
                'created_at' => now()
            ]);
        }
    }
    // Update customer name if it changed
    if ($customerId && $customerName) {
        $existingName = dbFetch('SELECT name FROM customers WHERE id = ?', [$customerId])['name'] ?? '';
        if ($existingName !== $customerName) {
            dbUpdate('customers', ['name' => $customerName], 'id = ?', [$customerId]);
        }
    }

    $status     = ($_POST['submit_type'] ?? '') === 'draft' ? 'draft' : 'completed';
    $discType   = $_POST['discount_type']   ?? 'percent';
    $discVal    = (float)($_POST['discount_val']  ?? 0);
    $vatType    = $_POST['vat_type']        ?? 'percent';
    $vatVal     = (float)($_POST['vat_val']       ?? 0);
    $pointsUsed = (int)($_POST['points_used']     ?? 0);
    $notes      = trim($_POST['notes']            ?? '');
    $sms        = isset($_POST['sms']) ? 1 : 0;

    $payMethods = (array)($_POST['payment_methods'] ?? ['cash']);
    $payMethods = array_values(array_filter($payMethods, fn($m) => in_array($m, ['cash','card','transfer'])));
    $payMethod  = implode(',', $payMethods) ?: 'cash';

    $S          = getAllSettings();
    $ptRate     = (float)($S['points_redeem_rate'] ?? 0.01);
    $subtotal   = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cartItems));

    $discVal = max(0, $discVal);
    if ($discType === 'percent' && $discVal > 100) $discVal = 100;

    if ($customerId && $pointsUsed > 0) {
        $actualCustomer = dbFetch('SELECT points FROM customers WHERE id = ?', [$customerId]);
        $actualPoints = $actualCustomer['points'] ?? 0;
        if ($pointsUsed > $actualPoints) $pointsUsed = $actualPoints;
    } else {
        $pointsUsed = 0;
    }

    $discAmt    = $discType === 'percent' ? $subtotal * ($discVal / 100) : min($discVal, $subtotal);
    $ptVal      = $pointsUsed * $ptRate;
    $afterDisc  = max(0, $subtotal - $discAmt - $ptVal);
    $vatAmt     = $vatType === 'percent' ? $afterDisc * ($vatVal / 100) : $vatVal;
    $total      = max(0, $afterDisc + $vatAmt);

    $userId     = currentUser()['id'];

    // ── Draft edit mode: update existing draft ────────────────
    if ($editSaleId) {
        $existingSale = dbFetch("SELECT * FROM sales WHERE id = ? AND status = 'draft'", [$editSaleId]);
        if (!$existingSale) {
            flash('error', 'Draft not found or already finalized.');
            redirect('pos');
        }
        db()->beginTransaction();
        try {
            // Restore stock from old items before re-saving
            $oldItems = dbFetchAll('SELECT * FROM sale_items WHERE sale_id = ?', [$editSaleId]);
            foreach ($oldItems as $oi) {
                dbQuery('UPDATE product_variants SET quantity = quantity + ? WHERE id = ?', [$oi['qty'], $oi['variant_id']]);
            }
            dbQuery('DELETE FROM sale_items WHERE sale_id = ?', [$editSaleId]);

            dbUpdate('sales', [
                'customer_id'     => $customerId ?: null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discAmt,
                'discount_pct'    => $discType === 'percent' ? $discVal : 0,
                'points_used'     => $pointsUsed,
                'points_value'    => $ptVal,
                'vat_rate'        => $vatType === 'percent' ? $vatVal : 0,
                'vat_amount'      => $vatAmt,
                'total'           => $total,
                'payment_method'  => $payMethod,
                'status'          => $status,
                'notes'           => $notes,
            ], 'id = ?', [$editSaleId]);

            foreach ($cartItems as $item) {
                dbInsert('sale_items', [
                    'sale_id'      => $editSaleId,
                    'variant_id'   => $item['variant_id'],
                    'product_name' => $item['name'],
                    'size'         => $item['size']  ?? '',
                    'color'        => $item['color'] ?? '',
                    'notes'        => $item['notes'] ?? '',
                    'qty'          => $item['qty'],
                    'unit_price'   => $item['price'],
                    'total_price'  => $item['price'] * $item['qty'],
                ]);
                dbQuery('UPDATE product_variants SET quantity = quantity - ? WHERE id = ?', [$item['qty'], $item['variant_id']]);
            }

            if ($customerId && $status === 'completed') {
                $earnRate  = (float)($S['points_earn_rate'] ?? 1);
                $earnedPts = (int)floor($total * $earnRate);
                dbQuery('UPDATE customers SET points = GREATEST(0, points + ? - ?) WHERE id = ?', [$earnedPts, $pointsUsed, $customerId]);
            }

            if ($status === 'completed') {
                // Remove any existing finance entry for this draft
                dbQuery("DELETE FROM finance_entries WHERE ref_sale_id = ?", [$editSaleId]);
                dbInsert('finance_entries', [
                    'type'        => 'income',
                    'category'    => 'Sale',
                    'account'     => 'shop',
                    'sub_account' => 'sales',
                    'amount'      => $total,
                    'description' => 'Sale ' . $existingSale['invoice_no'] . ' ' . $notes,
                    'ref_sale_id' => $editSaleId,
                    'party'       => 'shop',
                    'user_id'     => $userId,
                    'entry_date'  => today(),
                    'created_at'  => now(),
                ]);
            }

            db()->commit();
            logAction('SALE_UPDATE', 'pos', $editSaleId, "Updated " . $existingSale['invoice_no'] . " — $status");

            if ($status === 'completed') {

           
                // SMS
                if ($sms && ($S['api_key_sms'] ?? '') != '' && ($S['sms_enabled'] ?? '') === '1' && preg_match('/^01\d{9}$/', $customerPhone)) {
                    $points = dbFetch('SELECT points FROM customers WHERE id = ?', [$customerId])['points'] ?? 0;
                    $url = 'https://api.sms.net.bd/sendsms';
                    $params = [
                        'api_key' => $S['api_key_sms'],
                        'msg' => 'Thank you for shopping at ' . $S['shop_name'] . ' Total: ' . $total . ' BDT ' . (intval($points) > 0 ? ' Got ' . $points . ' Points ' : '') . ' Contact: ' . ($S['shop_phone'] ?? ''),
                        'to' => $customerPhone,
                    ];
                    if (($S['sms_balance'] ?? 0) >= 1) {
                        $response = json_decode(sendHttpPost($url, $params), true);
                        if (isset($response['error']) && $response['error'] === 0) {
                            $rate = strlen($params['msg']) <= 160 ? 1 : ceil(strlen($params['msg']) / 160);
                            logAction('SINGLE_SMS', 'SMS', $rate, "To: {$params['to']}");
                            dbQuery('UPDATE settings SET value = value - ? WHERE `key` = ?', [$rate, 'sms_balance']);
                        }
                    }
                }
                redirect('invoice', ['id' => $editSaleId]);
            } else {
                flash('success', 'Draft updated.');
                redirect('pos');
            }
        } catch (Exception $e) {
            db()->rollBack();
            flash('error', 'Error: ' . $e->getMessage());
            redirect('pos');
        }
    }

    // ── New sale ───────────────────────────────────────────────
    $recentSale = dbFetch("SELECT id FROM sales WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 20 SECOND) LIMIT 1", [$userId]);
    if ($recentSale) {
        flash('error', 'You made a sale within the last 20 seconds. Please wait before making another sale.');
        redirect('pos');
    }

    $invoiceNo  = generateInvoiceNo();
    db()->beginTransaction();
    try {
        $saleId = dbInsert('sales', [
            'invoice_no'      => $invoiceNo,
            'customer_id'     => $customerId ?: null,
            'user_id'         => $userId,
            'subtotal'        => $subtotal,
            'discount_amount' => $discAmt,
            'discount_pct'    => $discType === 'percent' ? $discVal : 0,
            'points_used'     => $pointsUsed,
            'points_value'    => $ptVal,
            'vat_rate'        => $vatType === 'percent' ? $vatVal : 0,
            'vat_amount'      => $vatAmt,
            'total'           => $total,
            'payment_method'  => $payMethod,
            'status'          => $status,
            'notes'           => $notes,
            'created_at'      => now(),
        ]);
        foreach ($cartItems as $item) {
            dbInsert('sale_items', [
                'sale_id'      => $saleId,
                'variant_id'   => $item['variant_id'],
                'product_name' => $item['name'],
                'size'         => $item['size']  ?? '',
                'color'        => $item['color'] ?? '',
                'notes'        => $item['notes'] ?? '',
                'qty'          => $item['qty'],
                'unit_price'   => $item['price'],
                'total_price'  => $item['price'] * $item['qty'],
            ]);
            dbQuery('UPDATE product_variants SET quantity = quantity - ? WHERE id = ?', [$item['qty'], $item['variant_id']]);
        }

        if ($customerId && $status === 'completed') {
            $earnRate  = (float)($S['points_earn_rate'] ?? 1);
            $earnedPts = (int)floor($total * $earnRate);
            dbQuery('UPDATE customers SET points = GREATEST(0, points + ? - ?) WHERE id = ?', [$earnedPts, $pointsUsed, $customerId]);
        }

        if ($status === 'completed') {
            dbInsert('finance_entries', [
                'type'        => 'income',
                'category'    => 'Sale',
                'account'     => 'shop',
                'sub_account' => 'sales',
                'amount'      => $total,
                'description' => 'Sale ' . $invoiceNo . ' ' . $notes,
                'ref_sale_id' => $saleId,
                'party'       => 'shop',
                'user_id'     => $userId,
                'entry_date'  => today(),
                'created_at'  => now(),
            ]);
        }
        db()->commit();
        logAction('SALE', 'pos', $saleId, "Invoice $invoiceNo — $status");

        if ($status === 'completed') {
            if ($sms && ($S['api_key_sms'] ?? '') != '' && ($S['sms_enabled'] ?? '') === '1' && preg_match('/^01\d{9}$/', $customerPhone)) {
                $points = dbFetch('SELECT points FROM customers WHERE id = ?', [$customerId])['points'] ?? 0;
                $url = 'https://api.sms.net.bd/sendsms';
                $params = [
                    'api_key' => $S['api_key_sms'],
                    'msg' => 'Thank you for shopping at ' . $S['shop_name'] . ' Total: ' . $total . ' BDT ' . (intval($points) > 0 ? ' Got ' . $points . ' Points ' : '') . ' Contact: ' . ($S['shop_phone'] ?? ''),
                    'to' => $customerPhone,
                ];
                if (($S['sms_balance'] ?? 0) >= 1) {
                    $response = json_decode(sendHttpPost($url, $params), true);
                    if (isset($response['error']) && $response['error'] === 0) {
                        $rate = strlen($params['msg']) <= 160 ? 1 : ceil(strlen($params['msg']) / 160);
                        logAction('SINGLE_SMS', 'SMS', $rate, "To: {$params['to']} | Msg: {$params['msg']}");
                        dbQuery('UPDATE settings SET value = value - ? WHERE `key` = ?', [$rate, 'sms_balance']);
                    }
                }
            }
            redirect('invoice', ['id' => $saleId]);
        } else {
            flash('success', 'Draft saved.');
            redirect('pos');
        }
    } catch (Exception $e) {
        db()->rollBack();
        flash('error', 'Error: ' . $e->getMessage());
        redirect('pos');
    }
}

// ── Load page data ────────────────────────────────────────────
$S          = getAllSettings();
$categories = dbFetchAll('SELECT * FROM categories ORDER BY name');
$brands     = dbFetchAll('SELECT * FROM brands ORDER BY name');

// Products — merge same-name products into one tile
$rawProducts = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, p.category_id, p.brand_id,
            MIN(v.price) AS min_price, MIN(v.regular) AS min_regular, SUM(v.quantity) AS total_stock
     FROM products p LEFT JOIN product_variants v ON v.product_id = p.id
     WHERE p.active = 1 GROUP BY p.id ORDER BY p.name"
);
// Group by name: same-name products share one tile; store all product ids
$productsByName = [];
foreach ($rawProducts as $p) {
    $key = strtolower(trim($p['name']));
    if (!isset($productsByName[$key])) {
        $productsByName[$key] = $p;
        $productsByName[$key]['product_ids']  = [$p['id']];
        $productsByName[$key]['total_stock']  = (int)$p['total_stock'];
    } else {
        $productsByName[$key]['product_ids'][] = $p['id'];
        $productsByName[$key]['total_stock']  += (int)$p['total_stock'];
        if ((float)$p['min_price'] < (float)$productsByName[$key]['min_price'])
            $productsByName[$key]['min_price'] = $p['min_price'];
    }
}
$products = array_values($productsByName);

// Previous notes from sale_items for autocomplete
$prevNotes = dbFetchAll(
    "SELECT DISTINCT notes FROM sale_items WHERE notes IS NOT NULL AND notes != '' ORDER BY id DESC LIMIT 60"
);

// ── Load Existing Sale Data (draft edit) ───────────────────────
$loadSaleData = null;
$editSaleId   = 0;
if (!empty($_GET['id'])) {
    $saleId = (int)$_GET['id'];
    $sale = dbFetch('SELECT * FROM sales WHERE id = ?', [$saleId]);

    if ($sale) {
        $editSaleId = $saleId;
        if ($sale['customer_id']) {
            $customer = dbFetch('SELECT name, phone, points FROM customers WHERE id = ?', [$sale['customer_id']]);
            if ($customer) {
                $sale['customer_name']   = $customer['name'];
                $sale['customer_phone']  = $customer['phone'];
                $sale['customer_points'] = $customer['points'];
            }
        }
        $items = dbFetchAll(
            'SELECT si.*, v.quantity AS stock_remaining, v.regular, v.barcode
             FROM sale_items si
             LEFT JOIN product_variants v ON v.id = si.variant_id
             WHERE si.sale_id = ?',
            [$saleId]
        );
        // max_qty = stock_remaining + qty already in the draft (since stock was already deducted)
        foreach ($items as &$it) {
            $it['max_qty'] = ($it['stock_remaining'] ?? 0) + $it['qty'];
        }
        unset($it);
        $sale['items'] = $items;
        $loadSaleData  = $sale;
    }
}

$discEnabled  = ($S['discount_enabled']  ?? '') == '1';
$vatEnabled   = ($S['vat_enabled']       ?? '') == '1';
$pointsEnabled= ($S['points_enabled']    ?? '') == '1';
$discType     = $S['discount_type']     ?? 'percent';
$discDefault  = $S['discount_default']  ?? 0;
$vatDefault   = $S['vat_default']       ?? 0;
$cur          = $S['currency_symbol']   ?? '৳';

// All customers for suggestion
$allCustomers = dbFetchAll('SELECT id, name, phone, points FROM customers ORDER BY name');

$pageTitle = $editSaleId ? 'Edit Draft #' . $editSaleId : 'Point of Sale';
require_once BASE_PATH . '/includes/header.php';
?>

<style>
/* ── Modern POS 3-Column Theme ── */
:root {
  --pos-bg: #121212;
  --pos-panel: #1e1e1e;
  --pos-panel-alt: #252525;
  --pos-border: #333333;
  --pos-text: #e0e0e0;
  --pos-text-muted: #888888;
  --pos-accent: #6c5ce7;
  --pos-accent-hover: #5a4bcf;
  --pos-danger: #ff4757;
  --pos-success: #2ed573;
  --pos-warning: #ffa502;
  --pos-gold: #ffd700;
  --pos-radius: 10px;
}
body, html { background-color: var(--pos-bg) !important; color: var(--pos-text) !important; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #666; }

.pos-wrapper {
  display: flex; gap: 15px;
  height: calc(100vh - 90px);
  margin-top: 10px; overflow: hidden;
}
.pos-col {
  background: var(--pos-panel); border-radius: var(--pos-radius);
  border: 1px solid var(--pos-border);
  display: flex; flex-direction: column; overflow: hidden;
}
.col-products { flex: 2.2; min-width: 0; }

.pos-filters {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 8px !important;
  background-color: #1a1a1a !important;
  border-bottom: 1px solid var(--pos-border) !important;
  padding: 10px;
}
.pos-filters .pos-input { flex: none !important; min-width: 0 !important; width: 100% !important; }
.pos-filters #productSearch { grid-column: 1 / -1 !important; }
.pos-filters #barcodeInput { grid-column: 1 / -1 !important; }
.pos-filters input[type="text"], .pos-filters select, .pos-filters .pos-input {
  background-color: #2a2a2a !important; color: #ffffff !important;
  border: 1px solid var(--pos-border) !important; border-radius: 6px !important;
  -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important;
  padding: 10px;
}
.pos-filters input::placeholder { color: #aaaaaa !important; opacity: 1 !important; }
.pos-filters select option { background-color: #2a2a2a !important; color: #ffffff !important; }
.pos-filters input:focus, .pos-filters select:focus {
  border-color: var(--pos-accent) !important;
  box-shadow: 0 0 0 2px rgba(108,92,231,0.2) !important; outline: none !important;
}

.col-cart { flex: 1.5; min-width: 0; }
.col-checkout {
  flex: 1.2; min-width: 310px;
  display: grid !important;
  grid-template-rows: auto 1fr auto !important;
  height: 100% !important; overflow: hidden !important;
  background: var(--pos-panel); border-radius: var(--pos-radius);
  border: 1px solid var(--pos-border);
}

.pos-input-sm {
  background-color: #2a2a2a !important; color: #fff !important;
  border: 1px solid var(--pos-border) !important;
  border-radius: 4px; padding: 4px 8px; width: 100%;
  font-size: 0.8rem; height: 28px;
}
.pos-input-sm:focus { border-color: var(--pos-accent) !important; outline: none; }

.checkout-section { padding: 8px 10px; border-bottom: 1px solid var(--pos-border); }
.checkout-section:last-child { border-bottom: none; }
.checkout-body { overflow-y: auto !important; min-height: 0 !important; }
.checkout-footer { padding: 10px; background: #1a1a1a; border-top: 1px solid var(--pos-border); }

.compact-label { display: block; font-size: 0.65rem; color: var(--pos-text-muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.summary-row { display: flex; justify-content: space-between; margin-bottom: 3px; font-size: 0.8rem; color: #ccc; }
.summary-total-compact { display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: bold; color: #fff; margin-bottom: 8px; }

.input-group-tight { display: flex; align-items: stretch; height: 28px; }
.input-group-tight .pos-input-sm:first-child { border-radius: 4px 0 0 4px; border-right: none; width: 40px; text-align: center; padding: 4px 0; }
.input-group-tight .pos-input-sm:last-child { border-radius: 0 4px 4px 0; flex: 1; }

.pay-opt-wrap { padding: 4px; border-radius: 4px; font-size: 0.75rem; flex: 1; text-align: center; border: 1px solid var(--pos-border); cursor: pointer; background: var(--pos-panel-alt); color: var(--pos-text); transition: 0.2s; }
.pay-opt-wrap.pay-selected { background: rgba(108,92,231,0.15); border-color: var(--pos-accent); color: #fff; }
.btn-action-sm { width: 100%; padding: 6px; border: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem; cursor: pointer; color: #fff; height: 32px; }

.product-grid {
  flex: 1; overflow-y: auto;
  display: grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
  gap: 12px; padding: 15px; align-content: start;
}
.product-tile {
  background: var(--pos-panel-alt); border: 1px solid var(--pos-border);
  border-radius: var(--pos-radius); padding: 12px; cursor: pointer;
  text-align: center; user-select: none; transition: all 0.15s ease;
  display: flex; flex-direction: column; justify-content: space-between; min-height: 110px;
}
.product-tile:hover { border-color: var(--pos-accent); background: #2c2c2c; transform: translateY(-2px); }
.product-tile:active { transform: scale(0.96); }
.product-tile.out-of-stock { opacity: 0.4; cursor: not-allowed; filter: grayscale(1); }
.tile-name { font-weight: 600; font-size: 0.88rem; margin-bottom: 6px; color: #fff; line-height: 1.3; }
.tile-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
.tile-price { color: var(--pos-success); font-weight: bold; font-size: 0.95rem; }
.tile-stock { font-size: 0.7rem; color: var(--pos-text-muted); background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; }

/* Cart */
.cart-header-bar {
  padding: 8px 12px; border-bottom: 1px solid var(--pos-border);
  display: flex; justify-content: space-between; align-items: center; background: #1a1a1a;
  flex-shrink: 0;
}
.cart-warning {
  margin: 6px 10px; padding: 8px 12px;
  background: rgba(255,71,87,0.12); border: 1px solid rgba(255,71,87,0.3);
  border-radius: 6px; color: var(--pos-danger); font-size: 0.8rem;
  display: none;
}

/* Global notes autofill bar */
.global-notes-bar {
  padding: 5px 10px; border-bottom: 1px solid var(--pos-border);
  background: #1a1a1a; display: flex; gap: 6px; align-items: center; flex-shrink: 0;
}

.cart-items { flex: 1; overflow-y: auto; padding: 8px 10px; }
.cart-item-row {
  padding: 10px; border: 1px solid var(--pos-border);
  border-radius: 8px; margin-bottom: 8px;
  background: var(--pos-panel-alt); font-size: 0.85rem;
}
.cart-item-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-title { font-weight: 600; color: #fff; display: block; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-item-meta { font-size: 0.72rem; color: var(--pos-text-muted); }
.cart-item-controls { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.qty-input { width: 42px; text-align: center; padding: 4px; background: #333; border: 1px solid #555; color: #fff; border-radius: 4px; font-weight: bold; }
.cart-item-price { color: var(--pos-success); font-weight: bold; font-size: 0.9rem; white-space: nowrap; }

/* Price vs Regular display */
.cart-price-block { margin-top: 5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cart-regular-price { text-decoration: line-through; color: var(--pos-text-muted); font-size: 0.75rem; }
.cart-sale-badge { font-size: 0.68rem; background: rgba(255,71,87,0.15); color: #ff6b7a; border: 1px solid rgba(255,71,87,0.25); padding: 1px 6px; border-radius: 10px; white-space: nowrap; }

/* Item notes */
.cart-item-notes { margin-top: 5px; }
.cart-item-notes input {
  width: 100%; background: #2a2a2a; border: 1px solid #3a3a3a;
  border-radius: 4px; color: #ccc; font-size: 0.75rem;
  padding: 3px 7px; outline: none;
}
.cart-item-notes input:focus { border-color: var(--pos-accent); }
.cart-item-notes input::placeholder { color: #555; }

.btn-remove {
  background: rgba(255,71,87,0.1); color: var(--pos-danger);
  border: none; border-radius: 4px; width: 24px; height: 24px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: 0.2s; font-size: 0.85rem; flex-shrink: 0;
}
.btn-remove:hover { background: var(--pos-danger); color: #fff; }

/* Variant Modal */
.pos-modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center;
  z-index: 9999; opacity: 0; visibility: hidden; transition: 0.2s;
}
.pos-modal-overlay.active { opacity: 1; visibility: visible; }
.pos-modal {
  background: var(--pos-panel); border: 1px solid var(--pos-border);
  border-radius: var(--pos-radius); width: 90%; max-width: 440px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; flex-direction: column;
}
.pos-modal-header { padding: 15px; border-bottom: 1px solid var(--pos-border); display: flex; justify-content: space-between; align-items: center; }
.pos-modal-title { font-size: 1.1rem; font-weight: bold; color: #fff; margin: 0; }
.pos-modal-close { background: transparent; border: none; color: var(--pos-text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1; }
.pos-modal-body { padding: 15px; max-height: 60vh; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; }
.variant-btn {
  background: var(--pos-panel-alt); border: 1px solid var(--pos-border);
  border-radius: 6px; padding: 10px 12px; color: var(--pos-text);
  display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; text-align: left; transition: 0.1s; width: 100%;
}
.variant-btn:hover { border-color: var(--pos-accent); background: #2a2a2a; }
.variant-btn-right { text-align: right; flex-shrink: 0; }
.variant-btn-price { color: var(--pos-success); font-weight: bold; font-size: 0.95rem; }
.variant-btn-regular { text-decoration: line-through; color: var(--pos-text-muted); font-size: 0.75rem; }
.variant-btn-badge { font-size: 0.65rem; background: rgba(255,71,87,0.15); color: #ff6b7a; padding: 1px 5px; border-radius: 8px; display: inline-block; margin-top: 2px; }
.variant-qty-badge { font-size: 0.7rem; color: var(--pos-text-muted); background: rgba(255,255,255,0.06); padding: 1px 5px; border-radius: 4px; }

/* Customer autocomplete */
.customer-suggest-wrap { position: relative; }
.customer-suggest-dropdown {
  position: absolute; top: 100%; left: 0; right: 0; z-index: 999;
  background: #2a2a2a; border: 1px solid var(--pos-border);
  border-radius: 0 0 6px 6px; max-height: 180px; overflow-y: auto;
  display: none; box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}
.customer-suggest-item {
  padding: 7px 10px; cursor: pointer; font-size: 0.8rem;
  border-bottom: 1px solid #333; transition: 0.1s;
}
.customer-suggest-item:last-child { border-bottom: none; }
.customer-suggest-item:hover { background: #363636; }
.customer-suggest-item .sug-name { color: #fff; font-weight: 600; }
.customer-suggest-item .sug-phone { color: var(--pos-text-muted); font-size: 0.72rem; }
.customer-suggest-item .sug-pts { color: var(--pos-gold); font-size: 0.7rem; }

/* Draft edit banner */
.draft-edit-banner {
  background: linear-gradient(90deg, rgba(255,165,2,0.15), rgba(255,165,2,0.05));
  border: 1px solid rgba(255,165,2,0.4);
  border-radius: 6px; padding: 6px 12px; margin-bottom: 10px;
  font-size: 0.82rem; color: var(--pos-warning); display: flex; align-items: center; gap: 8px;
}

.pos-header {
  padding: 10px 15px; border-bottom: 1px solid var(--pos-border);
  display: flex; justify-content: space-between; align-items: center;
  background: #1a1a1a; font-weight: 600; font-size: 0.9rem;
}

@media (max-width: 992px) {
  body, html { overflow: auto !important; height: auto !important; }
  .pos-wrapper { flex-direction: column; height: auto !important; max-height: none !important; overflow: visible !important; margin-bottom: 40px !important; }
  .pos-col { flex: none !important; width: 100% !important; height: auto !important; max-height: none !important; overflow: visible !important; }
  .product-grid { max-height: 50vh; overflow-y: auto !important; }
  .cart-items { max-height: 40vh; overflow-y: auto !important; }
  .col-checkout { display: flex !important; flex-direction: column !important; }
  .checkout-body { overflow-y: visible !important; min-height: auto !important; }
}
</style>

<div class="pos-wrapper" id="posContainer">

  <!-- ── COLUMN 1: Products ─────────────────────────────────── -->
  <div class="pos-col col-products">
    <div class="pos-filters">
      <input type="text" id="productSearch" class="pos-input" placeholder="🔍 Search products...">
      <select id="categoryFilter" class="pos-input">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach ?>
      </select>
      <select id="brandFilter" class="pos-input">
        <option value="">All Brands</option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
        <?php endforeach ?>
      </select>
      <input type="text" id="barcodeInput" class="pos-input" placeholder="||| Barcode / ID">
    </div>

    <div class="product-grid" id="productGrid">
      <?php foreach ($products as $p): ?>
      <?php $count = count($p['product_ids']); $idsJson = htmlspecialchars(json_encode($p['product_ids']), ENT_QUOTES); ?>
      <div class="product-tile <?= $p['total_stock'] <= 0 ? 'out-of-stock' : '' ?>"
           data-name="<?= e(strtolower($p['name'])) ?>"
           data-category="<?= $p['category_id'] ?>"
           data-brand="<?= $p['brand_id'] ?>"
           onclick="<?= $p['total_stock'] > 0 ? "fetchVariantsAndAdd({$idsJson})" : '' ?>">
        <div class="tile-name">
          <?= e($p['name']) ?>
          <?php if ($count > 1): ?>
            <span style="font-size:0.65rem;background:rgba(108,92,231,0.25);color:var(--pos-accent);padding:1px 5px;border-radius:8px;margin-left:3px;font-weight:700;"><?= $count ?></span>
          <?php endif ?>
        </div>
        <div class="tile-meta">
          <span class="tile-price"><?= $cur . number_format((float)$p['min_price'], 2) ?></span>
          <span class="tile-stock"><?= (int)$p['total_stock'] ?></span>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- ── COLUMN 2: Cart ─────────────────────────────────────── -->
  <div class="pos-col col-cart">
    <div class="cart-header-bar">
      <span style="color:var(--pos-accent);font-weight:600;">🛒 Cart</span>
      <span id="cartCount" style="background:var(--pos-accent);color:#fff;padding:2px 10px;border-radius:12px;font-size:0.8rem;">0</span>
    </div>

    <!-- Barcode warning -->
    <div class="cart-warning" id="barcodeWarning">⚠️ <span id="barcodeWarningText">Barcode not found or out of stock.</span></div>

    <!-- Global notes autofill bar -->
    <div class="global-notes-bar" id="globalNotesBar" style="display:none;">
      <span style="font-size:0.68rem;color:var(--pos-text-muted);padding:2px 4px;white-space:nowrap;">Fill all:</span>
      <div style="position:relative;flex:1;">
        <input type="text" id="globalItemNoteInput" class="pos-input-sm" placeholder="Type note to fill all items…"
               autocomplete="off" oninput="showNoteSuggestions(this.value)" onblur="hideNoteSuggest()"
               style="width:100%;padding-right:28px;">
        <div id="noteSuggestDrop" style="position:absolute;top:100%;left:0;right:0;z-index:999;background:#2a2a2a;border:1px solid var(--pos-border);border-radius:0 0 6px 6px;max-height:140px;overflow-y:auto;display:none;"></div>
      </div>
      <button type="button" onclick="applyGlobalNote()" style="background:var(--pos-accent);border:none;color:#fff;border-radius:4px;padding:0 10px;font-size:0.75rem;cursor:pointer;height:28px;white-space:nowrap;">Apply</button>
    </div>

    <div class="cart-items" id="cartItemsContainer">
      <div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--pos-text-muted);">
        Scan a barcode or select a product
      </div>
    </div>
  </div>

  <!-- ── COLUMN 3: Checkout ────────────────────────────────── -->
  <div class="pos-col col-checkout">
    <div class="pos-header" style="padding:8px 10px;">
      <span>📄 <?= $editSaleId ? 'Edit Draft #' . $editSaleId : 'Checkout' ?></span>
    </div>

    <div class="checkout-body">

      <?php if ($editSaleId): ?>
      <div style="padding:6px 10px;">
        <div class="draft-edit-banner">✏️ Editing Draft — saving will update, not duplicate.</div>
      </div>
      <?php endif ?>

      <!-- Customer -->
      <div class="checkout-section">
        <label class="compact-label">Customer</label>
        <div style="display:flex;gap:6px;margin-bottom:6px;">
          <div class="customer-suggest-wrap" style="flex:1;">
            <input type="text" id="customerPhone" class="pos-input-sm" placeholder="Phone" autocomplete="off"
                   oninput="suggestCustomers('phone', this.value)" onblur="delayHideSuggest('phone')">
            <div class="customer-suggest-dropdown" id="suggestPhone"></div>
          </div>
          <div class="customer-suggest-wrap" style="flex:1.5;">
            <input type="text" id="customerName" class="pos-input-sm" placeholder="Name..." autocomplete="off"
                   oninput="suggestCustomers('name', this.value)" onblur="delayHideSuggest('name')">
            <div class="customer-suggest-dropdown" id="suggestName"></div>
          </div>
        </div>
        <input type="hidden" id="customerId">

        <?php if ($pointsEnabled): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,215,0,0.05);padding:4px 8px;border-radius:4px;border:1px solid rgba(255,215,0,0.1);">
          <span style="color:var(--pos-gold);font-size:0.75rem;">⭐ <strong id="pointsBadge">0</strong></span>
          <div style="display:flex;align-items:center;gap:6px;">
            <label style="cursor:pointer;font-size:0.7rem;color:#ccc;display:flex;align-items:center;gap:2px;margin:0;">
              <input type="checkbox" id="usePointsToggle" onchange="togglePoints(this.checked)"> Redeem
            </label>
            <input type="number" id="pointsUsed" class="pos-input-sm" placeholder="Pts" min="0"
                   oninput="liveValidatePoints()" style="width:50px;display:none;text-align:center;">
          </div>
        </div>
        <?php endif ?>
      </div>

      <!-- Discount / VAT -->
      <?php if ($discEnabled || $vatEnabled): ?>
      <div class="checkout-section">
        <div style="display:flex;gap:8px;">
          <?php if ($discEnabled): ?>
          <div style="flex:1;margin:0;">
            <label class="compact-label">Discount</label>
            <div class="input-group-tight">
              <?php if ($discType === 'both'): ?>
              <select id="discountType" class="pos-input-sm" onchange="liveValidateDiscount()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <?php else: ?>
              <input type="hidden" id="discountType" value="<?= e($discType) ?>">
              <span class="pos-input-sm" style="background:#222;width:30px;text-align:center;border-right:none;border-radius:4px 0 0 4px;color:#aaa;"><?= $discType==='percent'?'%':$cur ?></span>
              <?php endif ?>
              <input type="number" id="discountPct" class="pos-input-sm" value="<?= $discDefault ?>" min="0" step="0.01" oninput="liveValidateDiscount()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="discountType" value="percent"><input type="hidden" id="discountPct" value="0">
          <?php endif ?>

          <?php if ($vatEnabled): ?>
          <div style="flex:1;margin:0;">
            <label class="compact-label">VAT</label>
            <div class="input-group-tight">
              <select id="vatType" class="pos-input-sm" onchange="liveValidateVAT()">
                <option value="percent">%</option><option value="amount"><?= $cur ?></option>
              </select>
              <input type="number" id="vatRate" class="pos-input-sm" value="<?= $vatDefault ?>" min="0" step="0.01" oninput="liveValidateVAT()">
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" id="vatType" value="percent"><input type="hidden" id="vatRate" value="0">
          <?php endif ?>
        </div>
      </div>
      <?php endif ?>

      <!-- Summary -->
      <div class="checkout-section" style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;">
        <div class="summary-row"><span>Subtotal</span><span id="summarySubtotal"><?= $cur ?>0.00</span></div>
        <?php if ($discEnabled): ?><div class="summary-row" id="rowDiscount" style="color:var(--pos-danger);display:none;"><span>Discount</span><span id="summaryDiscount"></span></div><?php endif ?>
        <?php if ($pointsEnabled): ?><div class="summary-row" id="rowPoints" style="color:var(--pos-gold);display:none;"><span>Points</span><span id="summaryPoints"></span></div><?php endif ?>
        <?php if ($vatEnabled): ?><div class="summary-row" id="rowVat" style="color:var(--pos-success);display:none;"><span>VAT</span><span id="summaryVat"></span></div><?php endif ?>
      </div>
    </div>

    <!-- Footer -->
    <div class="checkout-footer">
      <div class="summary-total-compact">
        <span>TOTAL</span><span id="summaryTotal"><?= $cur ?>0.00</span>
      </div>

      <form method="POST" id="checkoutForm" style="margin:0;">
        <input type="hidden" name="action" value="finalize_sale">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="edit_sale_id" value="<?= $editSaleId ?>">
        <input type="hidden" name="customer_id" id="hdCustomerId">
        <input type="hidden" name="customer_phone" id="hdCustomerPhone">
        <input type="hidden" name="customer_name" id="hdCustomerName">
        <input type="hidden" name="discount_type" id="hdDiscType">
        <input type="hidden" name="discount_val" id="hdDiscVal">
        <input type="hidden" name="vat_type" id="hdVatType">
        <input type="hidden" name="vat_val" id="hdVatVal">
        <input type="hidden" name="points_used" id="hdPointsUsed">
        <input type="hidden" name="cart_json" id="hiddenCartJson">

        <div style="display:flex;gap:4px;margin-bottom:6px;">
          <?php foreach(['cash'=>'💵 Cash','card'=>'💳 Card','transfer'=>'🏦 Bank'] as $pv=>$pl): ?>
          <label class="pay-opt-wrap <?= $pv==='cash'?'pay-selected':'' ?>">
            <input type="checkbox" name="payment_methods[]" value="<?= $pv ?>" class="pay-check" style="display:none;" <?= $pv==='cash'?'checked':'' ?>
                   onchange="this.closest('.pay-opt-wrap').classList.toggle('pay-selected',this.checked)">
            <?= $pl ?>
          </label>
          <?php endforeach ?>
        </div>

        <input type="text" name="notes" id="globalNotesInput" class="pos-input-sm" placeholder="Order note..." style="margin-bottom:6px;">

        <div style="display:flex;gap:6px;">
          <!-- SMS toggle styled select -->
          <div style="position:relative;flex-shrink:0;">
            <select id="smsSelect" style="background:#1a1a1a;border:1px solid var(--pos-border);color:var(--pos-text-muted);border-radius:4px;padding:0 20px 0 8px;height:32px;font-size:0.7rem;cursor:pointer;appearance:none;-webkit-appearance:none;outline:none;min-width:80px;" onchange="updateSmsStyle(this)">
              <option value="1">📱 <?= intval($S['sms_balance'] ?? 0) ?> SMS</option>
              <option value="0">📵 No SMS</option>
            </select>
            <span style="position:absolute;right:4px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:0.6rem;color:var(--pos-text-muted);">▾</span>
            <input type="hidden" name="sms" id="hdSms" value="1">
          </div>
          <button type="submit" name="submit_type" value="draft" class="btn-action-sm" style="background:#e67e22;flex:1;" onclick="return processCheckout()">📋</button>
          <button type="submit" name="submit_type" value="complete" class="btn-action-sm" style="background:var(--pos-success);flex:2;" onclick="return processCheckout()">✅ <?= $editSaleId ? 'Update' : 'Finalize' ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Variant Modal -->
  <div id="variantModalOverlay" class="pos-modal-overlay">
    <div class="pos-modal">
      <div class="pos-modal-header">
        <h3 class="pos-modal-title" id="variantModalTitle">Select Variant</h3>
        <button class="pos-modal-close" onclick="closeVariantModal()">×</button>
      </div>
      <div class="pos-modal-body" id="variantModalBody"></div>
    </div>
  </div>
</div>

<script>
// ============================================================================
// CONFIG
// ============================================================================
const CURRENCY        = '<?= addslashes($cur) ?>';
const POINTS_RATE     = <?= (float)($S['points_redeem_rate'] ?? 0.01) ?>;
const POINTS_MIN      = <?= (int)($S['points_min_redeem'] ?? 0) ?>;
const MAX_REDEEM_PCT  = <?= (float)($S['points_max_redeem_pct'] ?? 100) ?>;
const DISC_MAX_PCT    = <?= (float)($S['discount_max_percent'] ?? 100) ?>;
const DISC_MAX_AMT    = <?= (float)($S['discount_max_amount'] ?? 999999) ?>;
const IS_DRAFT_EDIT   = <?= $editSaleId ? 'true' : 'false' ?>;

const loadedSale = <?= $loadSaleData ? json_encode($loadSaleData) : 'null' ?>;
const ALL_CUSTOMERS = <?= json_encode($allCustomers) ?>;
const PREV_NOTES = <?= json_encode(array_column($prevNotes, 'notes')) ?>;

// ============================================================================
// CART ENGINE
// ============================================================================
const Cart = {
  items: [],

  add(variant) {
    // Find existing by variant_id
    let existing = this.items.find(i => i.variant_id == variant.variant_id);
    if (existing) {
      if (existing.qty < existing.max_qty) {
        existing.qty++;
      } else {
        alert("Not enough stock available!");
        return;
      }
    } else {
      this.items.push({
        variant_id:   variant.variant_id,
        name:         variant.name,
        variant_name: variant.variant_name || '',
        price:        parseFloat(variant.price),
        regular:      parseFloat(variant.regular || 0),
        qty:          parseInt(variant.qty) || 1,
        max_qty:      parseInt(variant.max_qty || variant.quantity || 999),
        size:         variant.size  || '',
        color:        variant.color || '',
        notes:        variant.notes || '',
      });
    }
    this.render();
  },

  updateQty(index, qty) {
    qty = parseInt(qty) || 1;
    if (qty > this.items[index].max_qty) {
      alert("Only " + this.items[index].max_qty + " in stock!");
      qty = this.items[index].max_qty;
    }
    if (qty <= 0) { this.remove(index); return; }
    this.items[index].qty = qty;
    this.render();
  },

  updateNotes(index, notes) {
    this.items[index].notes = notes;
    // no re-render needed (input is live)
  },

  remove(index) {
    this.items.splice(index, 1);
    this.render();
  },

  getAll()      { return this.items; },
  getSubtotal() { return this.items.reduce((s, i) => s + i.price * i.qty, 0); },

  render() {
    const container = document.getElementById('cartItemsContainer');
    document.getElementById('cartCount').textContent = this.items.length;

    // Show/hide global notes bar
    const notesBar = document.getElementById('globalNotesBar');
    notesBar.style.display = this.items.length > 0 ? 'flex' : 'none';

    if (this.items.length === 0) {
      container.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--pos-text-muted);">Cart is empty</div>';
    } else {
      container.innerHTML = this.items.map((item, index) => {
        const attrs = [item.variant_name, item.size, item.color].filter(Boolean).join(' · ');
        const hasSale = item.regular > 0 && item.regular > item.price;
        const takaOff = hasSale ? (item.regular - item.price) : 0;
        const pctOff  = hasSale && item.regular > 0 ? Math.round((takaOff / item.regular) * 100) : 0;

        return `
        <div class="cart-item-row">
          <div class="cart-item-top">
            <div class="cart-item-info">
              <span class="cart-item-title" title="${esc(item.name)}">${esc(item.name)}</span>
              ${attrs ? `<span class="cart-item-meta">${esc(attrs)}</span>` : ''}
            </div>
            <div class="cart-item-controls">
              <span class="cart-item-price">${CURRENCY}${item.price.toFixed(2)}</span>
              <input type="number" class="qty-input" value="${item.qty}" min="1" max="${item.max_qty}"
                     onchange="Cart.updateQty(${index}, this.value)">
              <button type="button" class="btn-remove" onclick="Cart.remove(${index})">×</button>
            </div>
          </div>
          ${hasSale ? `
          <div class="cart-price-block">
            <span class="cart-regular-price">${CURRENCY}${item.regular.toFixed(2)}</span>
            <span class="cart-sale-badge">${pctOff}% · ${CURRENCY}${takaOff.toFixed(2)} off</span>
          </div>` : ''}
          <div class="cart-item-notes">
            <input type="text" placeholder="Item note…" value="${esc(item.notes)}"
                   onchange="Cart.updateNotes(${index}, this.value)"
                   oninput="Cart.updateNotes(${index}, this.value)">
          </div>
        </div>`;
      }).join('');
    }

    liveValidateDiscount();
    liveValidateVAT();
    liveValidatePoints();
    this.updateTotals();
  },

  updateTotals() {
    const subtotal = this.getSubtotal();
    const discType = document.getElementById('discountType')?.value || 'percent';
    const discVal  = parseFloat(document.getElementById('discountPct')?.value) || 0;
    const discAmt  = discType === 'percent' ? subtotal * (discVal / 100) : Math.min(discVal, subtotal);
    const ptsUsed  = parseInt(document.getElementById('pointsUsed')?.value) || 0;
    const ptsValue = ptsUsed * POINTS_RATE;
    const afterDisc = Math.max(0, subtotal - discAmt - ptsValue);
    const vatType  = document.getElementById('vatType')?.value || 'percent';
    const vatVal   = parseFloat(document.getElementById('vatRate')?.value) || 0;
    const vatAmt   = vatType === 'percent' ? afterDisc * (vatVal / 100) : vatVal;
    const total    = afterDisc + vatAmt;

    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    const setRow  = (rowId, textId, prefix, val) => {
      const row = document.getElementById(rowId);
      const el  = document.getElementById(textId);
      if (!row || !el) return;
      if (val > 0.001) {
        el.textContent = prefix + CURRENCY + val.toFixed(2);
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    };

    setText('summarySubtotal', CURRENCY + subtotal.toFixed(2));
    setRow('rowDiscount', 'summaryDiscount', '-', discAmt);
    setRow('rowPoints',   'summaryPoints',   '-', ptsValue);
    setRow('rowVat',      'summaryVat',      '+', vatAmt);
    setText('summaryTotal', CURRENCY + total.toFixed(2));
  }
};

// ============================================================================
// BARCODE
// ============================================================================
const barcodeInput = document.getElementById('barcodeInput');
let barcodeTimer;

function searchByBarcode(bc) {
  fetch(`?page=pos&action=barcode_lookup&barcode=${encodeURIComponent(bc)}`)
    .then(r => r.json())
    .then(data => {
      if (data.variant_id) {
        showBarcodeWarning(false);
        Cart.add(data);
      } else {
        showBarcodeWarning(true, `Barcode "${bc}" not found or out of stock.`);
      }
    });
}

function showBarcodeWarning(show, msg) {
  const el = document.getElementById('barcodeWarning');
  const txt = document.getElementById('barcodeWarningText');
  if (show) {
    txt.textContent = msg || 'Barcode not found.';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
  } else {
    el.style.display = 'none';
  }
}

barcodeInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const val = barcodeInput.value.trim();
    if (val) { searchByBarcode(val); barcodeInput.value = ''; }
  }
});
barcodeInput.addEventListener('input', () => {
  clearTimeout(barcodeTimer);
  barcodeTimer = setTimeout(() => {
    const val = barcodeInput.value.trim();
    if (val) { searchByBarcode(val); barcodeInput.value = ''; }
  }, 3000);
});

// ============================================================================
// PRODUCT VARIANTS — fetch & merge identical combos
// ============================================================================
function fetchVariantsAndAdd(productIds) {
  // Accept single id or array
  if (!Array.isArray(productIds)) productIds = [productIds];
  const idsParam = productIds.join(',');
  fetch(`?page=pos&action=get_variants&product_ids=${encodeURIComponent(idsParam)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.length) { alert("Out of stock or no variants."); return; }

      // Merge rows where variant_name + size + color + price are identical
      const merged = [];
      data.forEach(v => {
        const key = [v.variant_name||'', v.size||'', v.color||'', parseFloat(v.price).toFixed(2)].join('||');
        const ex  = merged.find(m => m._key === key);
        if (ex) {
          ex.quantity    += parseInt(v.quantity);
          ex.variant_ids  = (ex.variant_ids||[v.variant_id]).concat([v.variant_id]);
        } else {
          merged.push({ ...v, _key: key, variant_ids: [v.variant_id] });
        }
      });

      if (merged.length === 1) {
        Cart.add(merged[0]);
      } else {
        openVariantModal(merged);
      }
    })
    .catch(err => { console.error(err); alert("Failed to load product details."); });
}

function openVariantModal(variants) {
  const overlay = document.getElementById('variantModalOverlay');
  const body    = document.getElementById('variantModalBody');
  const title   = document.getElementById('variantModalTitle');

  title.textContent = variants[0].name;
  body.innerHTML    = '';

  variants.forEach(variant => {
    const parts = [];
    if (variant.variant_name) parts.push(variant.variant_name);
    if (variant.size)         parts.push(`Size: ${variant.size}`);
    if (variant.color)        parts.push(`Color: ${variant.color}`);
    const label  = parts.join(' · ') || 'Default';

    const hasSale  = parseFloat(variant.regular||0) > parseFloat(variant.price);
    const takaOff  = hasSale ? (parseFloat(variant.regular) - parseFloat(variant.price)) : 0;
    const pctOff   = hasSale && parseFloat(variant.regular) > 0
                      ? Math.round((takaOff / parseFloat(variant.regular)) * 100) : 0;

    const btn = document.createElement('button');
    btn.className = 'variant-btn';
    btn.innerHTML = `
      <div style="flex:1;min-width:0;">
        <span style="font-weight:600;color:#fff;display:block;margin-bottom:2px;">${esc(label)}</span>
        <span class="variant-qty-badge">Stock: ${variant.quantity}</span>
      </div>
      <div class="variant-btn-right">
        <div class="variant-btn-price">${CURRENCY}${parseFloat(variant.price).toFixed(2)}</div>
        ${hasSale ? `
        <div class="variant-btn-regular">${CURRENCY}${parseFloat(variant.regular).toFixed(2)}</div>
        <div class="variant-btn-badge">${pctOff}% off</div>` : ''}
      </div>`;
    btn.onclick = () => { Cart.add(variant); closeVariantModal(); };
    body.appendChild(btn);
  });

  overlay.classList.add('active');
}

function closeVariantModal() {
  document.getElementById('variantModalOverlay').classList.remove('active');
}
document.getElementById('variantModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeVariantModal();
});

// ============================================================================
// CUSTOMER AUTOCOMPLETE
// ============================================================================
let suggestHideTimers = {};

function suggestCustomers(field, value) {
  const q = value.trim().toLowerCase();
  const phoneEl = document.getElementById('customerPhone');
  const nameEl  = document.getElementById('customerName');
  const dropPhone = document.getElementById('suggestPhone');
  const dropName  = document.getElementById('suggestName');

  if (q.length < 2) {
    dropPhone.style.display = 'none';
    dropName.style.display  = 'none';
    return;
  }

  const matches = ALL_CUSTOMERS.filter(c =>
    (field === 'phone' ? (c.phone||'').toLowerCase().includes(q) : (c.name||'').toLowerCase().includes(q))
  ).slice(0, 8);

  const drop = field === 'phone' ? dropPhone : dropName;
  if (!matches.length) { drop.style.display = 'none'; return; }

  drop.innerHTML = matches.map(c => `
    <div class="customer-suggest-item" onclick="selectCustomer(${JSON.stringify(c).replace(/"/g, '&quot;')})">
      <div class="sug-name">${esc(c.name)}</div>
      <div style="display:flex;gap:8px;margin-top:1px;">
        <span class="sug-phone">${esc(c.phone||'')}</span>
        ${c.points > 0 ? `<span class="sug-pts">⭐ ${c.points} pts</span>` : ''}
      </div>
    </div>`).join('');
  drop.style.display = 'block';
}

function selectCustomer(c) {
  document.getElementById('customerId').value    = c.id;
  document.getElementById('customerPhone').value = c.phone || '';
  document.getElementById('customerName').value  = c.name  || '';
  const badge = document.getElementById('pointsBadge');
  if (badge) badge.textContent = c.points || 0;
  document.getElementById('suggestPhone').style.display = 'none';
  document.getElementById('suggestName').style.display  = 'none';
  liveValidatePoints();
}

function delayHideSuggest(field) {
  const id = field === 'phone' ? 'suggestPhone' : 'suggestName';
  suggestHideTimers[field] = setTimeout(() => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  }, 200);
}

// ============================================================================
// VALIDATION
// ============================================================================
function liveValidatePoints() {
  const el = document.getElementById('pointsUsed');
  if (!el) return;
  let val = parseInt(el.value) || 0;
  if (val < 0) val = 0;
  const customerPts = parseInt(document.getElementById('pointsBadge')?.textContent) || 0;
  const maxRedeemableCash = Cart.getSubtotal() * (MAX_REDEEM_PCT / 100);
  const maxRedeemablePts  = Math.floor(maxRedeemableCash / POINTS_RATE);
  const absoluteMax = Math.min(customerPts, maxRedeemablePts);
  if (val > absoluteMax) { el.value = absoluteMax; }
  Cart.updateTotals();
}

function liveValidateDiscount() {
  const el = document.getElementById('discountPct');
  const typeEl = document.getElementById('discountType');
  if (!el || !typeEl) return;
  let val = parseFloat(el.value) || 0;
  if (val < 0) val = 0;
  const type = typeEl.value;
  if (type === 'percent' && val > DISC_MAX_PCT) el.value = DISC_MAX_PCT;
  else if (type === 'amount' && DISC_MAX_AMT > 0 && val > DISC_MAX_AMT) el.value = DISC_MAX_AMT;
  Cart.updateTotals();
}

function liveValidateVAT() {
  const el = document.getElementById('vatRate');
  const typeEl = document.getElementById('vatType');
  if (!el || !typeEl) return;
  let val = parseFloat(el.value) || 0;
  if (val < 0) val = 0;
  if (typeEl.value === 'percent' && val > 100) el.value = 100;
  Cart.updateTotals();
}

function togglePoints(on) {
  const el = document.getElementById('pointsUsed');
  if (!el) return;
  el.style.display = on ? 'block' : 'none';
  if (!on) { el.value = ''; liveValidatePoints(); }
}

// ============================================================================
// CHECKOUT SUBMIT
// ============================================================================
function processCheckout() {
  if (!Cart.getAll().length) { alert("Cart is empty!"); return false; }

  const ptsEl = document.getElementById('pointsUsed');
  if (ptsEl && parseInt(ptsEl.value) > 0 && parseInt(ptsEl.value) < POINTS_MIN) {
    alert(`Minimum ${POINTS_MIN} points to redeem.`); ptsEl.focus(); return false;
  }

  const phoneEl = document.getElementById('customerPhone');
  if (phoneEl && phoneEl.value.trim()) {
    if (!/^01\d{9}$/.test(phoneEl.value.trim())) {
      alert("Please enter a valid Bangladeshi phone number starting with '01' (11 digits).");
      phoneEl.focus(); return false;
    }
  }

  // Build cart JSON with per-item notes
  const cartData = Cart.getAll().map(item => ({
    variant_id: item.variant_id,
    name:       item.name,
    price:      item.price,
    qty:        item.qty,
    size:       item.size,
    color:      item.color,
    notes:      item.notes,
  }));

  document.getElementById('hdCustomerId').value    = document.getElementById('customerId')?.value || '';
  document.getElementById('hdCustomerPhone').value = phoneEl?.value || '';
  document.getElementById('hdCustomerName').value  = document.getElementById('customerName')?.value || '';
  document.getElementById('hdDiscType').value      = document.getElementById('discountType')?.value || 'percent';
  document.getElementById('hdDiscVal').value       = document.getElementById('discountPct')?.value || 0;
  document.getElementById('hdVatType').value       = document.getElementById('vatType')?.value || 'percent';
  document.getElementById('hdVatVal').value        = document.getElementById('vatRate')?.value || 0;
  document.getElementById('hdPointsUsed').value    = document.getElementById('pointsUsed')?.value || 0;
  // SMS value is kept in sync by updateSmsStyle via the change event
  document.getElementById('hiddenCartJson').value  = JSON.stringify(cartData);
  return true;
}

// ============================================================================
// GLOBAL ITEM NOTES — autofill all cart items + DB suggestions
// ============================================================================
function applyGlobalNote() {
  const val = document.getElementById('globalItemNoteInput').value.trim();
  if (!val) return;
  Cart.items.forEach(item => { item.notes = val; });
  Cart.render();
  setTimeout(() => {
    const inp = document.getElementById('globalItemNoteInput');
    if (inp) inp.value = val;
  }, 0);
}

function showNoteSuggestions(q) {
  const drop = document.getElementById('noteSuggestDrop');
  if (!q || q.length < 1) { drop.style.display = 'none'; return; }
  const matches = PREV_NOTES.filter(n => n.toLowerCase().includes(q.toLowerCase())).slice(0, 8);
  if (!matches.length) { drop.style.display = 'none'; return; }
  drop.innerHTML = matches.map(n =>
    `<div onclick="pickNoteSuggest(${JSON.stringify(n).replace(/"/g,'&quot;')})"
          style="padding:6px 10px;cursor:pointer;font-size:0.78rem;color:#ddd;border-bottom:1px solid #333;"
          onmouseover="this.style.background='#363636'" onmouseout="this.style.background=''">${esc(n)}</div>`
  ).join('');
  drop.style.display = 'block';
}

function pickNoteSuggest(val) {
  document.getElementById('globalItemNoteInput').value = val;
  document.getElementById('noteSuggestDrop').style.display = 'none';
}

function hideNoteSuggest() {
  setTimeout(() => {
    const d = document.getElementById('noteSuggestDrop');
    if (d) d.style.display = 'none';
  }, 180);
}

// ============================================================================
// SMS SELECT HANDLER
// ============================================================================
function updateSmsStyle(sel) {
  const hd = document.getElementById('hdSms');
  if (sel.value === '1') {
    sel.style.color = '#2ed573';
    sel.style.borderColor = 'rgba(46,213,115,0.4)';
    if (hd) hd.value = '1';
  } else {
    sel.style.color = '#888';
    sel.style.borderColor = 'var(--pos-border)';
    if (hd) hd.value = '0';
  }
}

// ============================================================================
// KEYBOARD NAVIGATION
// ============================================================================
document.addEventListener('keydown', function(e) {
  const active = document.activeElement;
  if (e.key === 'Enter') {
    if (active.id === 'customerPhone') {
      e.preventDefault(); document.getElementById('customerName').focus();
    } else if (active.id === 'customerName') {
      e.preventDefault(); document.getElementById('discountPct')?.focus();
    
    }
    //discount 
    else if (active.id === 'discountPct') {
      e.preventDefault(); document.getElementById('vatRate')?.focus();
    }
    else if (active.id === 'vatRate') {
      e.preventDefault(); document.getElementById('globalNotesInput')?.focus();
    }

    
    
    else if (active.id === 'globalNotesInput') {
      e.preventDefault();
      document.querySelector('button[name="submit_type"][value="complete"]')?.click();
    }
  }
});

// ============================================================================
// PRODUCT SEARCH / FILTER
// ============================================================================
function filterProducts() {
  const q    = document.getElementById('productSearch').value.toLowerCase();
  const cat  = document.getElementById('categoryFilter').value;
  const brand= document.getElementById('brandFilter').value;
  document.querySelectorAll('.product-tile').forEach(tile => {
    const ok = (!q || (tile.dataset.name||'').includes(q))
            && (!cat || tile.dataset.category === cat)
            && (!brand || tile.dataset.brand === brand);
    tile.style.display = ok ? 'flex' : 'none';
  });
}

// ============================================================================
// INIT
// ============================================================================
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', () => {
  // Init SMS select style
  const smsSelect = document.getElementById('smsSelect');
  if (smsSelect) {
    updateSmsStyle(smsSelect);
    smsSelect.addEventListener('change', function() {
      updateSmsStyle(this);
      document.getElementById('hdSms').value = this.value;
    });
  }

  // Prevent negative / e in number inputs
  ['pointsUsed','discountPct','vatRate'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('keydown', e => { if (['-','e','E','+'].includes(e.key)) e.preventDefault(); });
  });

  // Filter events
  document.getElementById('productSearch').addEventListener('input', filterProducts);
  document.getElementById('categoryFilter').addEventListener('change', filterProducts);
  document.getElementById('brandFilter').addEventListener('change', filterProducts);

  // Persist discount/vat type
  const discTypeEl = document.getElementById('discountType');
  const vatTypeEl  = document.getElementById('vatType');
  if (discTypeEl && discTypeEl.tagName === 'SELECT') {
    discTypeEl.value = localStorage.getItem('discountType') || discTypeEl.value;
    discTypeEl.addEventListener('change', () => localStorage.setItem('discountType', discTypeEl.value));
  }
  if (vatTypeEl && vatTypeEl.tagName === 'SELECT') {
    vatTypeEl.value = localStorage.getItem('vatType') || vatTypeEl.value;
    vatTypeEl.addEventListener('change', () => localStorage.setItem('vatType', vatTypeEl.value));
  }

  // Preload draft
  if (loadedSale && loadedSale.items && loadedSale.items.length > 0) {
    loadedSale.items.forEach(item => {
      Cart.add({
        variant_id:   item.variant_id,
        name:         item.product_name,
        variant_name: item.variant_name || '',
        price:        parseFloat(item.unit_price),
        regular:      parseFloat(item.regular || 0),
        qty:          parseInt(item.qty),
        max_qty:      parseInt(item.max_qty || item.qty),
        size:         item.size  || '',
        color:        item.color || '',
        notes:        item.notes || '',
      });
    });

    if (loadedSale.customer_id) {
      document.getElementById('customerId').value    = loadedSale.customer_id;
      document.getElementById('customerPhone').value = loadedSale.customer_phone || '';
      document.getElementById('customerName').value  = loadedSale.customer_name  || '';
      const ptsBadge = document.getElementById('pointsBadge');
      if (ptsBadge) ptsBadge.textContent = loadedSale.customer_points || 0;
    }

    if (parseInt(loadedSale.points_used) > 0) {
      const pToggle = document.getElementById('usePointsToggle');
      const pInput  = document.getElementById('pointsUsed');
      if (pToggle && pInput) { pToggle.checked = true; togglePoints(true); pInput.value = loadedSale.points_used; }
    }

    const dType = document.getElementById('discountType');
    const dPct  = document.getElementById('discountPct');
    if (dType && dPct) {
      if (parseFloat(loadedSale.discount_pct) > 0)    { if (dType.tagName==='SELECT') dType.value = 'percent'; dPct.value = parseFloat(loadedSale.discount_pct); }
      else if (parseFloat(loadedSale.discount_amount) > 0) { if (dType.tagName==='SELECT') dType.value = 'amount'; dPct.value = parseFloat(loadedSale.discount_amount); }
    }

    const vType = document.getElementById('vatType');
    const vRate = document.getElementById('vatRate');
    if (vType && vRate) {
      if (parseFloat(loadedSale.vat_rate) > 0)    { vType.value = 'percent'; vRate.value = parseFloat(loadedSale.vat_rate); }
      else if (parseFloat(loadedSale.vat_amount) > 0) { vType.value = 'amount'; vRate.value = parseFloat(loadedSale.vat_amount); }
    }

    const notesInput = document.getElementById('globalNotesInput') || document.querySelector('input[name="notes"]');
    if (notesInput) notesInput.value = loadedSale.notes || '';
  }

  Cart.render();

  // Default focus
  document.getElementById('barcodeInput')?.focus();

  // Mobile nav
  const navToggle  = document.getElementById('navToggle');
  const sideNav    = document.getElementById('sideNav');
  const navOverlay = document.getElementById('navOverlay');
  const toggleMenu = () => { sideNav?.classList.toggle('open'); navOverlay?.classList.toggle('open'); };
  const closeMenu  = () => { sideNav?.classList.remove('open'); navOverlay?.classList.remove('open'); };
  navToggle?.addEventListener('click', e => { e.preventDefault(); toggleMenu(); });
  navOverlay?.addEventListener('click', closeMenu);
  document.querySelectorAll('.nav-link').forEach(l => l.addEventListener('click', closeMenu));
});

document.addEventListener('wheel', function(e) {
  if (document.activeElement.type === 'number') document.activeElement.blur();
}, { passive: false });
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>