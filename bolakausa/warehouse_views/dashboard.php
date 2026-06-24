<?php
/**
 * Warehouse Dashboard - Advanced Picking Lists & Logistics
 */
restrict_to(['warehouse', 'admin']);

// Fetch active fulfillment orders based on fulfillment status
$stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.fulfillment_status IN ('Pending', 'Processing') ORDER BY o.created_at ASC");
$stmt->execute();
$fulfillment_orders = $stmt->fetchAll();

$pending_shipments = count($fulfillment_orders);
?>

<div class="section-title">
    <i class="fas fa-boxes" style="color: var(--primary);"></i>
    Warehouse Logistics & Picking Center
</div>

<div class="stat-grid" style="margin-bottom: 3rem;">
    <div class="stat-box bg-blue-glass">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Active Picking Queue</div>
        <div style="font-size: 2.5rem; font-weight: 900; margin-top: 0.5rem; color: var(--secondary);"><?php echo $pending_shipments; ?> Orders</div>
        <i class="fas fa-shipping-fast"></i>
    </div>
</div>

<!-- Picking Lists Section -->
<div class="card" style="margin-bottom: 2.5rem;">
    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-dolly-flatbed" style="color: var(--accent);"></i> Picking List Generator (Fulfillment Picking Helper)
    </h3>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Retrieve items from designated shelf zones using FIFO (First-In, First-Out) lot allocations.</p>

    <?php if (!$fulfillment_orders): ?>
        <div style="background: rgba(15,23,42,0.02); text-align: center; padding: 3rem; border-radius: 12px; border: 1px dashed var(--border-light); color: var(--text-muted); font-size: 0.95rem;">
            <i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem; display: block;"></i>
            All orders have been shipped. Picking queue is currently empty.
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <?php foreach ($fulfillment_orders as $ord): ?>
                <?php
                    // Fetch order items
                    $itemStmt = $pdo->prepare("SELECT oi.*, p.name as prod_name, p.weight FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
                    $itemStmt->execute([$ord['id']]);
                    $items = $itemStmt->fetchAll();
                ?>
                <div style="border: 1px solid var(--border-light); border-radius: 16px; padding: 1.5rem; background: white; box-shadow: var(--shadow-sm);">
                    <!-- Order Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; background: rgba(99,102,241,0.08); color: var(--accent);"><?php echo htmlspecialchars($ord['fulfillment_status']); ?></span>
                            <strong style="color: var(--secondary); font-size: 1.15rem; margin-left: 0.5rem;">Order Invoice #<?php echo $ord['id']; ?></strong>
                            <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;">Client: <strong>@<?php echo e($ord['username']); ?></strong></span>
                        </div>
                        <a href="/bolakausa/admin/orders" class="btn btn-blue" style="padding: 0.45rem 1rem; font-size: 0.775rem; border-radius: 6px;"><i class="fas fa-truck"></i> Update Status / Ship</a>
                    </div>

                    <!-- Items Picking Breakdown -->
                    <div class="table-wrap" style="margin: 0; box-shadow: none; border: 1px solid #f1f5f9;">
                        <table style="font-size: 0.875rem;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th>Item Name</th>
                                    <th>Quantity to Pack</th>
                                    <th>Unit Weight</th>
                                    <th>Shelf Location Location</th>
                                    <th>Active LOT #</th>
                                    <th style="text-align: right; width: 100px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                        // Retrieve variant if applicable
                                        $variant_desc = '';
                                        if ($item['variant_id']) {
                                            $vstmt = $pdo->prepare("SELECT variant_type, variant_value FROM product_variants WHERE id = ?");
                                            $vstmt->execute([$item['variant_id']]);
                                            $v = $vstmt->fetch();
                                            if ($v) {
                                                $variant_desc = ' (' . $v['variant_type'] . ': ' . $v['variant_value'] . ')';
                                            }
                                        }

                                        // Check if picks exist for this order item
                                        $pickStmt = $pdo->prepare("SELECT p.qty, l.lot_number, l.shelf_location FROM order_item_picks p JOIN inventory_lots l ON p.lot_id = l.id WHERE p.order_item_id = ? AND p.is_deleted = 0");
                                        $pickStmt->execute([$item['id']]);
                                        $picks = $pickStmt->fetchAll();

                                        if (!empty($picks)): 
                                            foreach ($picks as $index => $pick):
                                    ?>
                                                <tr>
                                                    <td>
                                                        <strong style="color: var(--secondary);"><?php echo e($item['prod_name'] . $variant_desc); ?></strong>
                                                        <?php if (count($picks) > 1): ?>
                                                            <small style="color: var(--text-muted); display: block;">(Split Pick Part <?php echo $index + 1; ?>)</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span style="background: rgba(99,102,241,0.08); color: var(--accent); padding: 3px 8px; border-radius: 4px; font-weight: 800;"><?php echo $pick['qty']; ?> units</span></td>
                                                    <td><?php echo $item['weight']; ?> kg</td>
                                                    <td>
                                                        <span style="background: rgba(16,185,129,0.08); color: var(--primary-dark); padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 0.775rem;">
                                                            <i class="fas fa-map-marker-alt"></i> <?php echo e($pick['shelf_location'] ?: 'Unassigned Shelf'); ?>
                                                        </span>
                                                    </td>
                                                    <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.8rem;"><?php echo e($pick['lot_number']); ?></code></td>
                                                    <td style="text-align: right; font-weight: 800; color: #166534;"><i class="fas fa-check-square"></i> Assigned</td>
                                                </tr>
                                    <?php 
                                            endforeach;
                                        else: 
                                            // Retrieve FIFO lot and shelf location
                                            $lotStmt = $pdo->prepare("SELECT lot_number, shelf_location FROM inventory_lots WHERE product_id = ? AND qty_remaining > 0 AND status = 'active' ORDER BY expiry_date ASC, received_at ASC LIMIT 1");
                                            $lotStmt->execute([$item['product_id']]);
                                            $lot = $lotStmt->fetch();
                                            
                                            $shelf = $lot ? $lot['shelf_location'] : 'Unassigned Lot';
                                            $lot_num = $lot ? $lot['lot_number'] : 'N/A';
                                    ?>
                                            <tr>
                                                <td><strong style="color: var(--secondary);"><?php echo e($item['prod_name'] . $variant_desc); ?></strong></td>
                                                <td><span style="background: rgba(15,23,42,0.06); padding: 3px 8px; border-radius: 4px; font-weight: 800;"><?php echo $item['qty']; ?> units</span></td>
                                                <td><?php echo $item['weight']; ?> kg</td>
                                                <td>
                                                    <span style="background: rgba(245,158,11,0.08); color: #92400e; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 0.775rem;">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo e($shelf); ?>
                                                    </span>
                                                </td>
                                                <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.8rem;"><?php echo $lot_num; ?></code></td>
                                                <td style="text-align: right; font-weight: 800; color: var(--primary);"><i class="far fa-square"></i> Pending</td>
                                            </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid-stack-mobile" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem;">
    <!-- Catalog Shortcuts -->
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-shipping-fast" style="color: var(--primary);"></i> Shipment Center
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Proceed to order fulfillment matrix to record freight tracking and complete shipments.</p>
        <a href='/bolakausa/admin/orders' class="btn btn-green">
            <i class="fas fa-shipping-fast"></i> Ship Orders
        </a>
    </div>

    <!-- Stock Receiving -->
    <div class="card">
        <h3 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <i class="fas fa-dolly" style="color: #3b82f6;"></i> Stock Inbound
        </h3>
        <p style="margin-bottom: 2rem; color: var(--text-muted); font-size: 0.9375rem;">Register physical incoming inventory, set LOT dates, and assign warehouse shelf locations.</p>
        <a href='/bolakausa/manager/stock' class="btn btn-blue">
            <i class="fas fa-plus"></i> Add Stock LOT
        </a>
    </div>
</div>
