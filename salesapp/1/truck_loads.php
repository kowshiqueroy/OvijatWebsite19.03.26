<?php
$pageTitle = 'Truck Loads';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

/* ══════════════════════════════════════════════════════════
   HELPER: log a status change
══════════════════════════════════════════════════════════ */
function logStatus($conn, $load_id, $from, $to, $by, $notes = '', $type = 'manual') {
    $stmt = $conn->prepare(
        "INSERT INTO truck_load_status_log (truck_load_id, from_status, to_status, changed_by, notes, action_type)
         VALUES (?,?,?,?,?,?)"
    );
    $stmt->bind_param("ississ", $load_id, $from, $to, $by, $notes, $type);
    $stmt->execute(); $stmt->close();
}

/* ══════════════════════════════════════════════════════════
   HELPER: recalculate total orders + value for a load
══════════════════════════════════════════════════════════ */
function recalcLoad($conn, $load_id) {
    $stmt = $conn->prepare(
        "UPDATE truck_loads tl SET
           tl.total_orders = (SELECT COUNT(*) FROM truck_load_orders WHERE truck_load_id=? AND is_active=1),
           tl.total_value  = COALESCE((
               SELECT SUM(oi.quantity*oi.price)
               FROM truck_load_orders tlo
               JOIN order_items oi ON oi.order_id=tlo.order_id
               WHERE tlo.truck_load_id=? AND tlo.is_active=1
           ), 0)
         WHERE tl.id=?"
    );
    $stmt->bind_param("iii", $load_id, $load_id, $load_id);
    $stmt->execute(); $stmt->close();
}

/* ══════════════════════════════════════════════════════════
   AUTO-GENERATE load name: TL-2026-0042
══════════════════════════════════════════════════════════ */
function nextLoadName($conn, $cid) {
    $y = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=? AND YEAR(created_at)=?");
    $stmt->bind_param("ii", $cid, $y); $stmt->execute();
    $n = (int)$stmt->get_result()->fetch_assoc()['c'] + 1; $stmt->close();
    return "TL-$y-" . str_pad($n, 4, '0', STR_PAD_LEFT);
}

/* ══════════════════════════════════════════════════════════
   STATUS TRANSITION RULES
══════════════════════════════════════════════════════════ */
$allowed_transitions = [
    // from_status => [who => [allowed next statuses]]
    'draft'      => ['sr'=>['submitted'], 'manager'=>['approved','cancelled']],
    'submitted'  => ['manager'=>['approved','cancelled']],
    'approved'   => ['manager'=>['loading','cancelled']],
    'loading'    => ['manager'=>['ready','cancelled']],
    'ready'      => ['manager'=>['in_transit','cancelled']],
    'in_transit' => ['manager'=>['delivered','returned']],
];

$role_key = $is_manager ? 'manager' : 'sr';

/* ══════════════════════════════════════════════════════════
   POST: CREATE TRUCK LOAD (SR)
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create_load'])) {
        $order_ids_raw = trim($_POST['order_ids'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');
        $name          = nextLoadName($conn, $cid);

        $order_ids = array_unique(array_filter(array_map('intval', explode(',', $order_ids_raw))));
        if (empty($order_ids)) { $error = 'Add at least one order.'; goto render; }

        /* Validate all orders belong to this company and are not already active in a load */
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $types        = str_repeat('i', count($order_ids));
        $valid_stmt   = $conn->prepare(
            "SELECT id FROM orders WHERE id IN ($placeholders) AND company_id=? AND status=1"
        );
        $valid_params = array_merge($order_ids, [$cid]);
        $valid_types  = $types . 'i';
        $valid_stmt->bind_param($valid_types, ...$valid_params);
        $valid_stmt->execute(); $valid_stmt->store_result();
        if ($valid_stmt->num_rows !== count($order_ids)) {
            $error = 'Some order IDs are invalid or belong to another company.';
            $valid_stmt->close(); goto render;
        }
        $valid_stmt->close();

        /* Check for orders already in an active non-cancelled load */
        $busy_stmt = $conn->prepare(
            "SELECT tlo.order_id FROM truck_load_orders tlo
             JOIN truck_loads tl ON tl.id=tlo.truck_load_id
             WHERE tlo.order_id IN ($placeholders) AND tlo.is_active=1
               AND tl.status NOT IN ('cancelled','returned') AND tl.company_id=?"
        );
        $busy_params = array_merge($order_ids, [$cid]);
        $busy_stmt->bind_param($types . 'i', ...$busy_params);
        $busy_stmt->execute(); $busy_stmt->store_result();
        if ($busy_stmt->num_rows > 0) {
            $error = 'One or more orders are already in another active truck load.';
            $busy_stmt->close(); goto render;
        }
        $busy_stmt->close();

        /* Insert load */
        $insert = $conn->prepare(
            "INSERT INTO truck_loads (load_name, company_id, assigned_sr_id, status, notes, created_by)
             VALUES (?,?,?,'draft',?,?)"
        );
        $insert->bind_param("siiis", $name, $cid, $uid, $notes, $uid);
        $insert->execute();
        $load_id = (int)$conn->insert_id;
        $insert->close();

        /* Insert order junction rows */
        foreach ($order_ids as $oid) {
            $ins = $conn->prepare("INSERT INTO truck_load_orders (truck_load_id, order_id, added_by) VALUES (?,?,?)");
            $ins->bind_param("iii", $load_id, $oid, $uid);
            $ins->execute(); $ins->close();
        }
        recalcLoad($conn, $load_id);
        logStatus($conn, $load_id, null, 'draft', $uid, 'Load created');

        header("Location: truck_loads.php?view=$load_id&msg=created"); exit;
    }

    /* ── Status transition ── */
    if (isset($_POST['change_status'])) {
        $load_id    = (int)$_POST['load_id'];
        $new_status = $_POST['new_status'];
        $notes      = trim($_POST['status_notes'] ?? '');

        /* Fetch current status */
        $stmt = $conn->prepare("SELECT status FROM truck_loads WHERE id=? AND company_id=?");
        $stmt->bind_param("ii", $load_id, $cid); $stmt->execute();
        $cur = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$cur) { header("Location: truck_loads.php"); exit; }

        $cur_status = $cur['status'];
        $allowed    = $allowed_transitions[$cur_status][$role_key] ?? [];

        if (!in_array($new_status, $allowed, true)) {
            $error = "You cannot change from '$cur_status' to '$new_status'.";
            goto render;
        }

        /* Build update columns by new status */
        $ts_map = [
            'submitted'  => ['submitted_by'         => 'submitted_at'],
            'approved'   => ['approved_by'           => 'approved_at'],
            'loading'    => ['loading_started_by'    => 'loading_started_at'],
            'ready'      => ['ready_marked_by'       => 'ready_marked_at'],
            'in_transit' => ['dispatched_by'         => 'dispatched_at'],
            'delivered'  => ['delivered_by'          => 'delivered_at'],
            'cancelled'  => ['cancelled_by'          => 'cancelled_at'],
        ];
        $by_col = array_key_first($ts_map[$new_status] ?? []);
        $at_col = $by_col ? $ts_map[$new_status][$by_col] : null;

        $extra_set = $by_col ? ", $by_col=$uid, $at_col=NOW()" : '';
        $extra_set .= ($new_status === 'cancelled') ? ", cancel_reason=?" : '';

        $stmt = $conn->prepare("UPDATE truck_loads SET status=? $extra_set WHERE id=? AND company_id=?");
        if ($new_status === 'cancelled') {
            $stmt->bind_param("ssii", $new_status, $notes, $load_id, $cid);
        } else {
            $stmt->bind_param("sii", $new_status, $load_id, $cid);
        }
        $stmt->execute(); $stmt->close();
        logStatus($conn, $load_id, $cur_status, $new_status, $uid, $notes);

        header("Location: truck_loads.php?view=$load_id&msg=status_updated"); exit;
    }

    /* ── Remove order from DRAFT load ── */
    if (isset($_POST['remove_order'])) {
        $load_id  = (int)$_POST['load_id'];
        $order_id = (int)$_POST['order_id'];
        $stmt     = $conn->prepare(
            "UPDATE truck_load_orders SET is_active=0, removed_by=?, removed_at=NOW()
             WHERE truck_load_id=? AND order_id=? AND is_active=1"
        );
        $stmt->bind_param("iii", $uid, $load_id, $order_id); $stmt->execute(); $stmt->close();
        recalcLoad($conn, $load_id);
        header("Location: truck_loads.php?view=$load_id&msg=order_removed"); exit;
    }

    /* ── Submit load (SR) ── */
    if (isset($_POST['submit_load'])) {
        $load_id = (int)$_POST['load_id'];
        $stmt    = $conn->prepare(
            "UPDATE truck_loads SET status='submitted', submitted_by=?, submitted_at=NOW()
             WHERE id=? AND company_id=? AND status='draft'"
        );
        $stmt->bind_param("iii", $uid, $load_id, $cid); $stmt->execute(); $stmt->close();
        logStatus($conn, $load_id, 'draft', 'submitted', $uid, 'SR submitted for manager approval');
        header("Location: truck_loads.php?view=$load_id&msg=submitted"); exit;
    }
}

render:
/* ══════════════════════════════════════════════════════════
   VIEW SINGLE LOAD
══════════════════════════════════════════════════════════ */
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $stmt    = $conn->prepare(
        "SELECT tl.*, u.username AS sr_name
         FROM truck_loads tl JOIN users u ON u.id=tl.assigned_sr_id
         WHERE tl.id=? AND tl.company_id=?"
    );
    $stmt->bind_param("ii", $view_id, $cid); $stmt->execute();
    $load = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$load) { header("Location: truck_loads.php"); exit; }

    /* Orders in this load */
    $orders_stmt = $conn->prepare(
        "SELECT o.id, o.order_date, o.delivery_date, o.order_status,
                s.shop_name, r.route_name,
                COALESCE(SUM(oi.quantity*oi.price),0) AS total,
                tlo.id AS tlo_id, tlo.is_active
         FROM truck_load_orders tlo
         JOIN orders o ON o.id=tlo.order_id
         JOIN shops s ON s.id=o.shop_id
         JOIN routes r ON r.id=o.route_id
         LEFT JOIN order_items oi ON oi.order_id=o.id
         WHERE tlo.truck_load_id=?
         GROUP BY o.id, tlo.id ORDER BY tlo.is_active DESC, o.order_date"
    );
    $orders_stmt->bind_param("i", $view_id); $orders_stmt->execute();
    $orders_res = $orders_stmt->get_result();

    /* Status log */
    $log_stmt = $conn->prepare(
        "SELECT sl.*, u.username FROM truck_load_status_log sl
         LEFT JOIN users u ON u.id=sl.changed_by
         WHERE sl.truck_load_id=? ORDER BY sl.changed_at DESC"
    );
    $log_stmt->bind_param("i", $view_id); $log_stmt->execute();
    $log_res = $log_stmt->get_result();

    /* Allowed next statuses for current user */
    $next_allowed = $allowed_transitions[$load['status']][$role_key] ?? [];

    /* SR requests for this load */
    $req_stmt = $conn->prepare(
        "SELECT scr.*, u.username AS req_by_name FROM status_change_requests scr
         JOIN users u ON u.id=scr.requested_by
         WHERE scr.truck_load_id=? ORDER BY scr.created_at DESC LIMIT 5"
    );
    $req_stmt->bind_param("i", $view_id); $req_stmt->execute();
    $req_res = $req_stmt->get_result();

    $status_badge = ['draft'=>'badge-gray','submitted'=>'badge-blue','approved'=>'badge-teal',
        'loading'=>'badge-yellow','ready'=>'badge-orange','in_transit'=>'badge-purple',
        'delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-brown'];

    if (isset($_GET['msg'])):
        $msgs = ['created'=>'Truck load created.','submitted'=>'Submitted for manager approval.',
                 'status_updated'=>'Status updated.','order_removed'=>'Order removed.'];
    endif;
    ?>

<div class="page-header">
    <div>
        <div class="page-title"><?= htmlspecialchars($load['load_name']) ?></div>
        <div class="page-subtitle">
            <span class="badge <?= $status_badge[$load['status']] ?>"><?= ucfirst(str_replace('_',' ',$load['status'])) ?></span>
            &nbsp;&bull;&nbsp; SR: <?= htmlspecialchars($load['sr_name']) ?>
            &nbsp;&bull;&nbsp; <?= $load['total_orders'] ?> orders &nbsp;&bull;&nbsp; <?= number_format($load['total_value'],0) ?> BDT
        </div>
    </div>
    <a href="truck_loads.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <a href="invoices.php?truck_load_id=<?= $view_id ?>" target="_blank" class="btn btn-dark btn-sm"><i class="fa-solid fa-file-invoice"></i> Print Invoice</a>
</div>

<?php if (isset($msgs[$_GET['msg'] ?? ''])): ?>
<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $msgs[$_GET['msg']] ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid-layout md-2">
    <!-- Orders in load -->
    <div class="card" style="grid-column:1/-1">
        <div class="card-header">
            <span class="card-title">Orders in this Load</span>
            <span class="badge badge-blue"><?= $load['total_orders'] ?> active</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Shop</th><th>Route</th><th>Order Date</th><th>Delivery</th><th>Value</th><th>Status</th>
                    <?php if ($load['status'] === 'draft' && ($is_manager || $uid == $load['assigned_sr_id'])): ?><th></th><?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php if ($orders_res->num_rows > 0): ?>
                        <?php while ($ord = $orders_res->fetch_assoc()): if (!$ord['is_active']) continue; ?>
                        <tr>
                            <td class="text-muted"><?= $ord['id'] ?></td>
                            <td class="fw-600 text-sm"><?= htmlspecialchars($ord['shop_name']) ?></td>
                            <td class="text-muted text-sm"><?= htmlspecialchars($ord['route_name']) ?></td>
                            <td class="text-sm"><?= $ord['order_date'] ?></td>
                            <td class="text-sm"><?= $ord['delivery_date'] ?></td>
                            <td class="text-sm fw-600"><?= number_format($ord['total'],0) ?></td>
                            <td><span class="badge <?= $ord['order_status'] ? 'badge-green' : 'badge-yellow' ?>"><?= $ord['order_status'] ? 'Confirmed' : 'Draft' ?></span></td>
                            <?php if ($load['status'] === 'draft' && ($is_manager || $uid == $load['assigned_sr_id'])): ?>
                            <td>
                                <form method="POST" style="display:inline">
        <?= csrf_field() ?>
                                    <input type="hidden" name="load_id"  value="<?= $view_id ?>">
                                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                    <button type="submit" name="remove_order" class="btn btn-danger btn-sm btn-icon"
                                            onclick="return confirm('Remove this order from the load?')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding:20px">No orders in this load.</td></tr>
                    <?php endif; ?>
                    <tr style="background:var(--gray-100)">
                        <td colspan="5" class="text-right fw-600">Total:</td>
                        <td class="fw-700"><?= number_format($load['total_value'],0) ?> BDT</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Status Actions -->
    <div class="card">
        <div class="card-header"><span class="card-title">Actions</span></div>

        <?php if (!empty($next_allowed)): ?>
        <form method="POST">
        <?= csrf_field() ?>
            <input type="hidden" name="load_id" value="<?= $view_id ?>">
            <div class="form-group mb-8">
                <label>Change Status To</label>
                <select name="new_status">
                    <?php foreach ($next_allowed as $ns): ?>
                        <option value="<?= $ns ?>"><?= ucfirst(str_replace('_',' ',$ns)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-8">
                <label>Notes (optional<?= $load['status']!=='cancelled'?'':' / reason required' ?>)</label>
                <textarea name="status_notes" rows="2" placeholder="Add a note..."></textarea>
            </div>
            <button type="submit" name="change_status" class="btn btn-primary btn-block">
                <i class="fa-solid fa-arrow-right"></i> Update Status
            </button>
        </form>
        <?php elseif ($load['status'] === 'draft' && ($uid == $load['assigned_sr_id'])): ?>
        <form method="POST">
        <?= csrf_field() ?>
            <input type="hidden" name="load_id" value="<?= $view_id ?>">
            <button type="submit" name="submit_load" class="btn btn-primary btn-block"
                    onclick="return confirm('Submit this load to manager for approval?')">
                <i class="fa-solid fa-paper-plane"></i> Submit to Manager
            </button>
        </form>
        <?php else: ?>
            <p class="text-muted text-sm">No further actions available for this status.</p>
        <?php endif; ?>

        <?php if (!$is_manager && !in_array($load['status'], ['delivered','cancelled','returned'])): ?>
        <hr style="margin:16px 0;border-color:var(--border)">
        <p class="text-sm fw-600 mb-8">Request Status Change</p>
        <a href="sr_requests.php?load=<?= $view_id ?>" class="btn btn-ghost btn-block btn-sm">
            <i class="fa-solid fa-bell"></i> Send Request to Manager
        </a>
        <?php endif; ?>
    </div>

    <!-- Status Log -->
    <div class="card">
        <div class="card-header"><span class="card-title">Status History</span></div>
        <?php if ($log_res->num_rows > 0): ?>
            <?php while ($log = $log_res->fetch_assoc()): ?>
            <div style="display:flex;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)">
                <div style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px"></div>
                <div>
                    <div class="text-sm fw-600">
                        <?= $log['from_status'] ? htmlspecialchars(ucfirst($log['from_status'])) . ' &rarr; ' : '' ?>
                        <span style="color:var(--primary)"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$log['to_status']))) ?></span>
                    </div>
                    <div class="text-muted text-xs"><?= date('d M Y, h:i a', strtotime($log['changed_at'])) ?> by <?= htmlspecialchars($log['username'] ?? '—') ?></div>
                    <?php if ($log['notes']): ?><div class="text-xs mt-4" style="color:var(--gray-700)"><?= htmlspecialchars($log['notes']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-muted text-sm">No history yet.</p>
        <?php endif; ?>
    </div>

    <!-- SR Requests -->
    <?php if ($req_res->num_rows > 0): ?>
    <div class="card" style="grid-column:1/-1">
        <div class="card-header"><span class="card-title">SR Status Change Requests</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Requested By</th><th>Current</th><th>Wants</th><th>Reason</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php while ($req = $req_res->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($req['req_by_name']) ?></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars($req['current_status']) ?></span></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($req['requested_status']) ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($req['reason'] ?? '—') ?></td>
                        <td><span class="badge <?= $req['urgency']==='urgent'?'badge-red':'badge-gray' ?>"><?= $req['urgency'] ?></span></td>
                        <td><span class="badge <?= ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'][$req['request_status']] ?>"><?= $req['request_status'] ?></span></td>
                        <td class="text-muted text-sm"><?= date('d M', strtotime($req['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

    <?php
    $orders_stmt->close(); $log_stmt->close(); $req_stmt->close();
    include 'footer.php'; exit;
}

/* ══════════════════════════════════════════════════════════
   LIST VIEW
══════════════════════════════════════════════════════════ */

/* Filters */
$f_status = $_GET['status'] ?? '';
$f_sr     = (int)($_GET['sr'] ?? 0);
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = ["tl.company_id=$cid"];
if ($f_status) $where[] = "tl.status='".mysqli_real_escape_string($conn, $f_status)."'";
if ($f_sr)     $where[] = "tl.assigned_sr_id=$f_sr";
/* SRs only see their own loads */
if (!$is_manager) $where[] = "tl.assigned_sr_id=$uid";
$w = 'WHERE ' . implode(' AND ', $where);

$total_q = $conn->query("SELECT COUNT(*) AS c FROM truck_loads tl $w");
$total   = (int)$total_q->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total / $per_page));

$loads_q = $conn->query(
    "SELECT tl.*, u.username AS sr_name
     FROM truck_loads tl JOIN users u ON u.id=tl.assigned_sr_id
     $w ORDER BY tl.created_at DESC LIMIT $per_page OFFSET $offset"
);

/* SR list for filter */
$srs_q = $conn->query("SELECT id, username FROM users WHERE company_id=$cid AND role IN (2,3) AND status=1 ORDER BY username");

$status_badge = ['draft'=>'badge-gray','submitted'=>'badge-blue','approved'=>'badge-teal',
    'loading'=>'badge-yellow','ready'=>'badge-orange','in_transit'=>'badge-purple',
    'delivered'=>'badge-green','cancelled'=>'badge-red','returned'=>'badge-brown'];

if (!empty($error)) echo '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> '.htmlspecialchars($error).'</div>';
?>

<div class="page-header">
    <div><div class="page-title">Truck Loads</div><div class="page-subtitle">Full delivery lifecycle management</div></div>
    <?php if (!$is_manager): ?>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('createForm').style.display = document.getElementById('createForm').style.display==='none' ? 'block' : 'none'">
        <i class="fa-solid fa-plus"></i> New Load
    </button>
    <?php endif; ?>
</div>

<!-- Create form (SR only) -->
<?php if (!$is_manager): ?>
<div class="card" id="createForm" style="display:none">
    <div class="card-header"><span class="card-title">Create New Truck Load</span></div>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="grid-layout md-2">
            <div class="form-group">
                <label>Order IDs <span style="color:var(--danger)">*</span></label>
                <input type="text" name="order_ids" placeholder="e.g. 12,15,22,30" required>
                <div class="text-muted text-xs mt-4">Enter confirmed order IDs separated by commas</div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Optional notes for manager">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="create_load" class="btn btn-primary"><i class="fa-solid fa-truck"></i> Create Load</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Pipeline Summary -->
<?php if ($is_manager): ?>
<div class="pipeline-grid mb-20">
<?php
$all_statuses = ['draft','submitted','approved','loading','ready','in_transit','delivered','cancelled'];
$pl_labels = ['draft'=>'Draft','submitted'=>'Submitted','approved'=>'Approved','loading'=>'Loading',
              'ready'=>'Ready','in_transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled'];
$pl_colors = ['draft'=>'var(--gray-500)','submitted'=>'var(--info)','approved'=>'#0f766e',
              'loading'=>'var(--warning)','ready'=>'#c2410c','in_transit'=>'#6d28d9',
              'delivered'=>'var(--primary)','cancelled'=>'var(--danger)'];
foreach ($all_statuses as $s):
    $cnt_q = $conn->query("SELECT COUNT(*) AS c FROM truck_loads WHERE company_id=$cid AND status='$s'");
    $cnt   = (int)$cnt_q->fetch_assoc()['c'];
?>
    <a href="truck_loads.php?status=<?= $s ?>" class="pipeline-col" style="text-decoration:none">
        <div class="pipeline-col-status" style="color:<?= $pl_colors[$s] ?>"><?= $pl_labels[$s] ?></div>
        <div class="pipeline-col-count"><?= $cnt ?></div>
        <div class="pipeline-col-label">loads</div>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="truck_loads.php">
    <div class="filter-bar">
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['draft','submitted','approved','loading','ready','in_transit','delivered','cancelled','returned'] as $s): ?>
                    <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($is_manager): ?>
        <div class="form-group">
            <label>SR</label>
            <select name="sr">
                <option value="">All SRs</option>
                <?php if ($srs_q) while ($sr = $srs_q->fetch_assoc()): ?>
                    <option value="<?= $sr['id'] ?>" <?= $f_sr==$sr['id']?'selected':'' ?>><?= htmlspecialchars($sr['username']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([10,25,50] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="truck_loads.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<!-- Loads table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Truck Loads</span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Load Name</th><th>SR</th><th>Status</th><th>Orders</th><th>Value</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($loads_q && $loads_q->num_rows > 0): ?>
                    <?php while ($ld = $loads_q->fetch_assoc()): ?>
                    <tr>
                        <td><a href="truck_loads.php?view=<?= $ld['id'] ?>" style="color:var(--primary);font-weight:700"><?= htmlspecialchars($ld['load_name']) ?></a></td>
                        <td class="text-sm"><?= htmlspecialchars($ld['sr_name']) ?></td>
                        <td><span class="badge <?= $status_badge[$ld['status']] ?>"><?= ucfirst(str_replace('_',' ',$ld['status'])) ?></span></td>
                        <td><?= $ld['total_orders'] ?></td>
                        <td class="fw-600"><?= number_format($ld['total_value'],0) ?></td>
                        <td class="text-muted text-sm"><?= date('d M Y', strtotime($ld['created_at'])) ?></td>
                        <td>
                            <a href="truck_loads.php?view=<?= $ld['id'] ?>" class="btn btn-info btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No truck loads found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "truck_loads.php?status=$f_status&sr=$f_sr&per_page=$per_page&page="; ?>
        <a href="<?=$base?>1"                  class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?=$base.max(1,$page-1)?>"    class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
            <a href="<?=$base.$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <a href="<?=$base.min($total_pages,$page+1)?>" class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?=$base.$total_pages?>"              class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angles-right"></i></a>
        <span class="text-muted text-sm" style="margin-left:8px">Page <?=$page?> of <?=$total_pages?></span>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
