<?php
$pageTitle = 'SR Status Requests';
include 'header.php';

$cid = (int)$_SESSION['company_id'];
$uid = (int)$_SESSION['user_id'];

$success = $error = '';

/* ── SR submits a new request ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $load_id   = (int)$_POST['load_id'];
    $req_status = trim($_POST['requested_status']);
    $reason    = trim($_POST['reason']);
    $urgency   = in_array($_POST['urgency'],['normal','urgent']) ? $_POST['urgency'] : 'normal';

    /* Get current status */
    $stmt = $conn->prepare("SELECT status FROM truck_loads WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $load_id, $cid); $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if (!$cur) { $error = 'Truck load not found.'; }
    else {
        $cur_status = $cur['status'];
        $stmt = $conn->prepare(
            "INSERT INTO status_change_requests
             (truck_load_id, requested_by, current_status, requested_status, reason, urgency)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param("iissss", $load_id, $uid, $cur_status, $req_status, $reason, $urgency);
        $stmt->execute(); $stmt->close();
        header("Location: sr_requests.php?msg=sent"); exit;
    }
}

/* ── Manager resolves a request ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_request'])) {
    if (!$is_manager) { header("Location: sr_requests.php"); exit; }
    $req_id     = (int)$_POST['req_id'];
    $resolution = $_POST['resolution']; // 'approved' or 'rejected'
    $note       = trim($_POST['resolution_note'] ?? '');

    /* Load request details */
    $stmt = $conn->prepare("SELECT * FROM status_change_requests WHERE id=?");
    $stmt->bind_param("i", $req_id); $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if ($req && $req['request_status'] === 'pending') {
        /* Update request */
        $stmt = $conn->prepare(
            "UPDATE status_change_requests SET request_status=?, resolved_by=?, resolved_at=NOW(), resolution_note=? WHERE id=?"
        );
        $stmt->bind_param("sisi", $resolution, $uid, $note, $req_id);
        $stmt->execute(); $stmt->close();

        /* If approved: apply the status change */
        if ($resolution === 'approved') {
            $ts_map = ['submitted'=>['submitted_by'=>'submitted_at'],
                       'approved'=>['approved_by'=>'approved_at'],
                       'loading'=>['loading_started_by'=>'loading_started_at'],
                       'ready'=>['ready_marked_by'=>'ready_marked_at'],
                       'in_transit'=>['dispatched_by'=>'dispatched_at'],
                       'delivered'=>['delivered_by'=>'delivered_at'],
                       'cancelled'=>['cancelled_by'=>'cancelled_at']];
            $ns = $req['requested_status'];
            $by_col = array_key_first($ts_map[$ns] ?? []);
            $at_col = $by_col ? $ts_map[$ns][$by_col] : null;
            $extra  = $by_col ? ", $by_col=$uid, $at_col=NOW()" : '';
            $conn->query("UPDATE truck_loads SET status='$ns' $extra WHERE id={$req['truck_load_id']} AND company_id=$cid");
            /* Log it */
            $stmt2 = $conn->prepare("INSERT INTO truck_load_status_log (truck_load_id,from_status,to_status,changed_by,notes,action_type) VALUES (?,?,?,?,?,?)");
            $log_note = "Approved SR request. " . $note;
            $action   = 'request_approved';
            $stmt2->bind_param("ississ", $req['truck_load_id'], $req['current_status'], $ns, $uid, $log_note, $action);
            $stmt2->execute(); $stmt2->close();
        }
        header("Location: sr_requests.php?msg=resolved"); exit;
    }
}

/* ── Query ── */
$f_status = $_GET['req_status'] ?? 'pending';
$valid_statuses = ['pending','approved','rejected',''];

$where = "scr.truck_load_id IN (SELECT id FROM truck_loads WHERE company_id=$cid)";
if (in_array($f_status, ['pending','approved','rejected'])) {
    $where .= " AND scr.request_status='$f_status'";
}
/* SRs only see their own requests */
if (!$is_manager) $where .= " AND scr.requested_by=$uid";

$reqs_q = $conn->query(
    "SELECT scr.*, tl.load_name, u.username AS req_by_name, r.username AS resolved_by_name
     FROM status_change_requests scr
     JOIN truck_loads tl ON tl.id=scr.truck_load_id
     JOIN users u ON u.id=scr.requested_by
     LEFT JOIN users r ON r.id=scr.resolved_by
     WHERE $where ORDER BY scr.urgency DESC, scr.created_at DESC"
);

if (isset($_GET['msg'])) $success = ['sent'=>'Request sent to manager.','resolved'=>'Request resolved.'][$_GET['msg']] ?? '';

/* Pre-fill load from URL (SR coming from truck load page) */
$prefill_load = (int)($_GET['load'] ?? 0);
?>

<div class="page-header">
    <div><div class="page-title"><?= $is_manager ? 'SR Status Requests' : 'My Status Requests' ?></div>
    <div class="page-subtitle">SR-to-Manager communication for truck load status changes</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- SR: Submit Request -->
<?php if (!$is_manager): ?>
<div class="card">
    <div class="card-header"><span class="card-title">Send Status Change Request</span></div>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="grid-layout md-2">
            <div class="form-group">
                <label>Truck Load ID <span style="color:var(--danger)">*</span></label>
                <input type="number" name="load_id" value="<?= $prefill_load ?: '' ?>" placeholder="e.g. 12" required min="1">
            </div>
            <div class="form-group">
                <label>Request Status Change To <span style="color:var(--danger)">*</span></label>
                <select name="requested_status" required>
                    <option value="">Select desired status</option>
                    <?php foreach (['submitted','approved','loading','ready','in_transit','delivered'] as $s): ?>
                        <option value="<?=$s?>"><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Urgency</label>
                <select name="urgency">
                    <option value="normal">Normal</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason <span style="color:var(--danger)">*</span></label>
                <input type="text" name="reason" placeholder="Why do you need this status change?" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="submit_request" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Send Request
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap">
    <?php foreach ([''=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val => $lbl): ?>
        <a href="sr_requests.php?req_status=<?=$val?>" class="date-preset-btn <?= $f_status===$val?'active':'' ?>"><?=$lbl?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Requests</span>
        <span class="badge badge-blue"><?= $reqs_q ? $reqs_q->num_rows : 0 ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Load</th><th>Requested By</th><th>Current &rarr; Wants</th>
                    <th>Reason</th><th>Urgency</th><th>Status</th><th>Date</th>
                    <?php if ($is_manager): ?><th>Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($reqs_q && $reqs_q->num_rows > 0): ?>
                    <?php while ($req = $reqs_q->fetch_assoc()): ?>
                    <tr>
                        <td><a href="truck_loads.php?view=<?= $req['truck_load_id'] ?>" style="color:var(--primary);font-weight:600"><?= htmlspecialchars($req['load_name']) ?></a></td>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($req['req_by_name']) ?></td>
                        <td>
                            <span class="badge badge-gray"><?= htmlspecialchars($req['current_status']) ?></span>
                            &rarr;
                            <span class="badge badge-blue"><?= htmlspecialchars($req['requested_status']) ?></span>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($req['reason'] ?? '—') ?></td>
                        <td><span class="badge <?= $req['urgency']==='urgent'?'badge-red':'badge-gray' ?>"><?= $req['urgency'] ?></span></td>
                        <td>
                            <span class="badge <?= ['pending'=>'badge-yellow','approved'=>'badge-green','rejected'=>'badge-red'][$req['request_status']] ?>">
                                <?= $req['request_status'] ?>
                            </span>
                        </td>
                        <td class="text-muted text-sm"><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                        <?php if ($is_manager && $req['request_status'] === 'pending'): ?>
                        <td>
                            <form method="POST" style="display:flex;gap:4px;flex-wrap:wrap">
        <?= csrf_field() ?>
                                <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                <input type="text" name="resolution_note" placeholder="Note (optional)" style="width:120px;padding:5px 8px;font-size:0.78rem">
                                <button type="submit" name="resolve_request" value="approved" class="btn btn-primary btn-sm"
                                        onclick="this.form.elements['resolution'].value='approved'"
                                        formnovalidate>Approve</button>
                                <button type="submit" name="resolve_request" value="rejected" class="btn btn-danger btn-sm"
                                        onclick="this.form.elements['resolution'].value='rejected'"
                                        formnovalidate>Reject</button>
                                <input type="hidden" name="resolution" value="approved">
                            </form>
                        </td>
                        <?php elseif ($is_manager): ?>
                        <td class="text-muted text-sm"><?= $req['resolved_by_name'] ? 'by '.$req['resolved_by_name'] : '—' ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
