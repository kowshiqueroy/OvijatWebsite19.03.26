<?php
/**
 * Advanced Inventory Receiving & Lot Tracking Dashboard
 */
restrict_to(['admin', 'manager', 'warehouse']);

$success = '';
$error = '';

// Handle Incoming Stock Logging (with LOT details & Item Selfie)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_in'])) {
    $product_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $lot_number = trim($_POST['lot_number'] ?? '');
    $expiry_date = $_POST['expiry_date'] ?: null;
    $shelf_location = trim($_POST['shelf_location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $selfie_base64 = $_POST['batch_selfie'] ?? '';

    if ($product_id && $qty > 0 && $lot_number) {
        $pdo->beginTransaction();
        try {
            // Check if product exists
            $stmt = $pdo->prepare("SELECT name, stock_qty FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if ($product) {
                $batch_photo_path = null;

                // Handle Item Selfie (Base64 Canvas Resized)
                if ($selfie_base64) {
                    $upload_dir = "public/uploads/lots/";
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                    if (preg_match('/^data:image\/(\w+);base64,/', $selfie_base64, $type)) {
                        $data = substr($selfie_base64, strpos($selfie_base64, ',') + 1);
                        $data = base64_decode($data);
                        
                        if ($data !== false) {
                            $filename = "lot_" . time() . "_" . uniqid() . ".jpg";
                            $filepath = $upload_dir . $filename;
                            if (file_put_contents($filepath, $data)) {
                                $batch_photo_path = "/bolakausa/" . $filepath;
                            }
                        }
                    }
                }

                // Insert into inventory_lots
                $stmt = $pdo->prepare("INSERT INTO inventory_lots (product_id, lot_number, expiry_date, shelf_location, qty_received, qty_remaining, status, batch_photo) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$product_id, $lot_number, $expiry_date, $shelf_location, $qty, $qty, $batch_photo_path]);

                // Update product main stock
                $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                $stmt->execute([$qty, $product_id]);

                // Log audit action
                $log_detail = "LOT: $lot_number, Qty: $qty, Shelf: $shelf_location, Exp: " . ($expiry_date ?: 'None') . ". Notes: $notes";
                log_action($pdo, $_SESSION['user_id'], "Inventory Received", $product['name'], $log_detail);

                $pdo->commit();
                $success = "Successfully received LOT '$lot_number' for " . e($product['name']) . ".";
            } else {
                $error = "Product not found.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to record incoming inventory: " . $e->getMessage();
        }
    } else {
        $error = "Product, Quantity, and LOT number are required.";
    }
}

// Handle LOT Status Adjustments (Expired / Damaged / Returned)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_lot'])) {
    $lot_id = (int)$_POST['lot_id'];
    $new_status = $_POST['lot_status']; // 'expired', 'damaged', 'returned'

    $stmt = $pdo->prepare("SELECT l.*, p.name as prod_name FROM inventory_lots l JOIN products p ON l.product_id = p.id WHERE l.id = ?");
    $stmt->execute([$lot_id]);
    $lot = $stmt->fetch();

    if ($lot && $lot['status'] === 'active' && $lot['qty_remaining'] > 0) {
        $qty_to_deduct = $lot['qty_remaining'];
        $product_id = $lot['product_id'];

        $pdo->beginTransaction();
        try {
            // Update lot status and set remaining qty to 0
            $stmt = $pdo->prepare("UPDATE inventory_lots SET status = ?, qty_remaining = 0 WHERE id = ?");
            $stmt->execute([$new_status, $lot_id]);

            // Deduct the inventory from products main stock
            $stmt = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?");
            $stmt->execute([$qty_to_deduct, $product_id]);

            // Log action
            log_action($pdo, $_SESSION['user_id'], "Inventory Lot Adjusted", "Lot: {$lot['lot_number']}, Status: $new_status", "Deducted: $qty_to_deduct units of {$lot['prod_name']}");

            $pdo->commit();
            $success = "Lot '{$lot['lot_number']}' marked as " . strtoupper($new_status) . ". Main stock adjusted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to adjust lot: " . $e->getMessage();
        }
    }
}

// Fetch general products for dropdown
$products = $pdo->query("SELECT id, name, stock_qty FROM products ORDER BY name ASC")->fetchAll();

// Date Range Filter
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$show_all = isset($_GET['show_all']);

$query_lots = "SELECT l.*, p.name as prod_name, DATEDIFF(l.expiry_date, CURRENT_DATE) as days_left FROM inventory_lots l JOIN products p ON l.product_id = p.id";
$lots_params = [];
if (!$show_all) {
    $query_lots .= " WHERE DATE(l.received_at) BETWEEN ? AND ?";
    $lots_params[] = $date_from;
    $lots_params[] = $date_to;
}
$query_lots .= " ORDER BY l.expiry_date ASC, l.received_at DESC";

$stmt_lots = $pdo->prepare($query_lots);
$stmt_lots->execute($lots_params);
$lots = $stmt_lots->fetchAll();

// Advanced Stock Level Overview Analysis
$low_stock = [];
$high_stock = [];
$suggestions = [];

foreach ($products as $p) {
    if ($p['stock_qty'] <= 10) {
        $low_stock[] = $p;
        $suggestions[] = [
            'name' => $p['name'],
            'action' => 'RESTOCK REQUIRED',
            'qty' => 50,
            'reason' => 'Inventory is critical (<= 10 units remaining).'
        ];
    } elseif ($p['stock_qty'] >= 500) {
        $high_stock[] = $p;
        $suggestions[] = [
            'name' => $p['name'],
            'action' => 'LIQUIDATE / DISCOUNTS',
            'qty' => 0,
            'reason' => 'Overstock level (>= 500 units). Consider bulk coupons.'
        ];
    }
}
?>

<div class="section-title">
    <i class="fas fa-dolly" style="color: var(--primary);"></i>
    Advanced Inventory & LOT Control Center
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
    </div>
<?php endif; ?>

<!-- Upper Grid: Add Stock Inbound & Stock Suggestions -->
<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2.5rem; margin-bottom: 3rem; align-items: flex-start; flex-wrap: wrap;">
    <!-- 1. Log Inbound Form -->
    <div class="card">
        <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus-circle" style="color: var(--primary);"></i> Log Inbound LOT Shipment
        </h3>
        <form method="POST" id="inbound-form">
            <input type="hidden" name="stock_in" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Select Product *</label>
                    <select name="product_id" required style="border-radius: 6px;">
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo e($p['name']); ?> (Current: <?php echo $p['stock_qty']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Quantity Received *</label>
                    <input type="number" name="qty" min="1" required placeholder="e.g. 50" style="border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label>LOT Identifier / ID *</label>
                    <input type="text" name="lot_number" required placeholder="e.g. LOT-2026-A" style="border-radius: 6px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" style="border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin: 0;">
                    <label>Shelf Location (Aisle/Shelf) *</label>
                    <input type="text" name="shelf_location" required placeholder="e.g. Aisle 3, Shelf B" style="border-radius: 6px;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label>Batch Condition Photo / Item Selfie</label>
                    <input type="file" id="batch_photo_input" accept="image/*" style="padding: 0.4rem; border-radius: 6px;">
                    <div id="selfie-preview-container" style="margin-top: 0.5rem;"></div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>BOL Notes / Reception Comments</label>
                <textarea name="notes" rows="2" placeholder="Describe package condition, seals status, etc." style="border-radius: 6px;"></textarea>
            </div>

            <button type="submit" class="btn btn-green" style="width: 100%; justify-content: center; padding: 0.95rem; border-radius: 8px;">
                <i class="fas fa-check-double"></i> File & Verify Inbound Stock
            </button>
        </form>
    </div>

    <!-- 2. Stock Health & Restock Advice -->
    <div>
        <div class="card" style="margin-bottom: 2rem; background: rgba(99,102,241,0.02); border-color: rgba(99,102,241,0.15);">
            <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--accent); margin-bottom: 1.25rem;">
                <i class="fas fa-brain"></i> Intelligence Stock Suggestions
            </h3>
            <?php if (empty($suggestions)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Inventory quantities are balanced correctly across all products.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <?php foreach ($suggestions as $sug): ?>
                        <div style="background: white; border: 1px solid var(--border-light); padding: 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                            <div>
                                <strong style="color: var(--secondary); font-size: 0.95rem;"><?php echo e($sug['name']); ?></strong>
                                <span style="display: block; font-size: 0.775rem; color: var(--text-muted); margin-top: 0.15rem;"><?php echo $sug['reason']; ?></span>
                            </div>
                            <span style="padding: 4px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; background: <?php echo ($sug['qty'] > 0) ? 'rgba(244,63,94,0.08); color: var(--rose)' : 'rgba(99,102,241,0.08); color: var(--accent)'; ?>;">
                                <?php echo $sug['action']; ?> <?php echo ($sug['qty'] > 0) ? "(+{$sug['qty']})" : ''; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <!-- Low Alert Card -->
            <div class="card" style="padding: 1.25rem; border-color: rgba(244,63,94,0.15); background: rgba(244,63,94,0.02); margin:0;">
                <div style="font-size: 0.75rem; font-weight:800; color: var(--rose); text-transform: uppercase;">Low Stock Warning</div>
                <strong style="font-size: 1.85rem; color: var(--secondary); display:block; margin-top:0.25rem;"><?php echo count($low_stock); ?></strong>
            </div>
            <!-- High Alert Card -->
            <div class="card" style="padding: 1.25rem; border-color: rgba(16,185,129,0.15); background: rgba(16,185,129,0.02); margin:0;">
                <div style="font-size: 0.75rem; font-weight:800; color: var(--primary-dark); text-transform: uppercase;">Overstock items</div>
                <strong style="font-size: 1.85rem; color: var(--secondary); display:block; margin-top:0.25rem;"><?php echo count($high_stock); ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Lower Grid: LOT Aging & Expiry Tracking Matrix -->
<div class="card">
    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-history" style="color: var(--accent);"></i> Inbound LOT Ageing & Expiry Matrix
    </h3>
    
    <!-- Date Filter Form -->
    <div style="margin-bottom: 2rem; padding: 1rem; border: 1px solid var(--border-light); border-radius: 12px; background: #f8fafc;">
        <form method="GET" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
            <input type="hidden" name="url" value="manager/stock">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
                <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">From Date</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
            </div>
            <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
                <label style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: var(--secondary); display: block;">To Date</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="padding: 0.5rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid #cbd5e1; width: 100%;">
            </div>
            <div style="display: flex; gap: 0.5rem; margin-top: auto;">
                <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.25rem;"><i class="fas fa-filter"></i> Filter Lots</button>
                <a href="/bolakausa/manager/stock?show_all=1" class="btn btn-outline" style="padding: 0.6rem 1.25rem; font-weight: 700; border-radius: 8px; text-decoration: none;">Show All</a>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>LOT Details</th>
                    <th>Product</th>
                    <th>Shelf Location</th>
                    <th>Selfie</th>
                    <th>Fulfillment Stock</th>
                    <th>Expiry status / Days Remaining</th>
                    <th style="text-align: right;">Lot Action / Adjustments</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$lots): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">No inventory lots logged in this date range. <a href="/bolakausa/manager/stock?show_all=1" style="color:var(--primary); font-weight:700;">Show All History</a></td></tr>
                <?php endif; ?>
                <?php foreach ($lots as $lot): ?>
                <tr>
                    <td>
                        <strong style="color: var(--secondary); font-size: 1.05rem;"><?php echo e($lot['lot_number']); ?></strong><br>
                        <small style="color: var(--text-muted);"><i class="far fa-clock"></i> Recd: <?php echo date('M d, Y', strtotime($lot['received_at'])); ?></small>
                    </td>
                    <td style="font-weight: 700; color: var(--secondary);"><?php echo e($lot['prod_name']); ?></td>
                    <td>
                        <span style="background: rgba(15,23,42,0.05); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; color: var(--secondary);"><i class="fas fa-map-marker-alt"></i> <?php echo e($lot['shelf_location'] ?: 'Unassigned'); ?></span>
                    </td>
                    <td>
                        <?php if ($lot['batch_photo']): ?>
                            <a href="<?php echo $lot['batch_photo']; ?>" target="_blank"><img src="<?php echo $lot['batch_photo']; ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-light);"></a>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">No Photo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color: var(--secondary);"><?php echo $lot['qty_remaining']; ?></strong> <span style="font-size:0.8rem; color: var(--text-muted);">/ <?php echo $lot['qty_received']; ?> units</span>
                    </td>
                    <td>
                        <?php
                            $days = $lot['days_left'];
                            if ($lot['status'] !== 'active') {
                                echo '<span style="padding: 3px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; background: #e2e8f0; color: #475569;">' . strtoupper($lot['status']) . '</span>';
                            } elseif ($days === null) {
                                echo '<span style="color: var(--text-muted); font-size:0.85rem;"><i class="fas fa-infinity"></i> No Expiry</span>';
                            } elseif ($days < 0) {
                                echo '<span style="padding: 3px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; background: rgba(244,63,94,0.08); color: var(--rose);">EXPIRED (' . abs($days) . ' days ago)</span>';
                            } elseif ($days <= 30) {
                                echo '<span style="padding: 3px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; background: rgba(245,158,11,0.08); color: #d97706; border: 1px solid rgba(245,158,11,0.15);"><i class="fas fa-exclamation-triangle"></i> expiring (' . $days . ' days left)</span>';
                            } else {
                                echo '<span style="color: var(--primary-dark); font-weight: 700; font-size:0.85rem;"><i class="fas fa-check-circle"></i> safe (' . $days . ' days left)</span>';
                            }
                        ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($lot['status'] === 'active' && $lot['qty_remaining'] > 0): ?>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="adjust_lot" value="1">
                                <input type="hidden" name="lot_id" value="<?php echo $lot['id']; ?>">
                                <select name="lot_status" required style="padding: 0.35rem 0.5rem; font-size: 0.75rem; border-radius: 6px; margin-right: 0.4rem; background: #f8fafc; border-color: var(--border-light);">
                                    <option value="expired">Mark Expired</option>
                                    <option value="damaged">Mark Damaged</option>
                                    <option value="returned">Mark Returned</option>
                                </select>
                                <button type="submit" class="btn btn-red" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;" onclick="return confirm('Deduct remaining lot stock from inventory?')">Apply</button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">Locked / Depleted</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selfieInput = document.getElementById('batch_photo_input');
    const selfiePreview = document.getElementById('selfie-preview-container');
    const form = document.getElementById('inbound-form');

    if (selfieInput) {
        selfieInput.addEventListener('change', (e) => {
            selfiePreview.innerHTML = '';
            
            const oldSelfie = form.querySelector('input[name="batch_selfie"]');
            if (oldSelfie) oldSelfie.remove();
            
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;
                        const max = 600;
                        
                        if (width > height) {
                            if (width > max) {
                                height *= max / width;
                                width = max;
                            }
                        } else {
                            if (height > max) {
                                width *= max / height;
                                height = max;
                            }
                        }
                        
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        
                        const base64 = canvas.toDataURL('image/jpeg', 0.80);
                        
                        const pImg = document.createElement('img');
                        pImg.src = base64;
                        pImg.style.width = '60px';
                        pImg.style.height = '60px';
                        pImg.style.objectFit = 'cover';
                        pImg.style.borderRadius = '6px';
                        pImg.style.border = '1px solid var(--border-light)';
                        
                        selfiePreview.appendChild(pImg);
                        
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'batch_selfie';
                        hidden.value = base64;
                        form.appendChild(hidden);
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/manager" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
