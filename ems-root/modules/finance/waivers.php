<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Fee Waivers';
$breadcrumbs = ['Finance' => null, 'Waivers' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['waivers.request']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ── Admin: Void an approved waiver ───────────────────────────────────────
    if ($action === 'void_waiver' && has_permission('waivers.approve')) {
        $waiver_id  = int_param('waiver_id', 0, $_POST);
        $void_reason= trim($_POST['void_reason'] ?? '');
        if ($waiver_id && $void_reason) {
            $wv = $pdo->prepare('SELECT * FROM waivers WHERE id=? AND status="approved"');
            $wv->execute([$waiver_id]);
            $wv = $wv->fetch();
            if ($wv) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE waivers SET status="void",voided_by=?,void_reason=?,voided_at=NOW() WHERE id=?')
                        ->execute([current_user_id(), $void_reason, $waiver_id]);
                    // Reverse the waiver_amount on the ledger
                    $pdo->prepare('UPDATE fee_ledgers SET waiver_amount=GREATEST(0,waiver_amount-?), waiver_status="none", status=CASE WHEN amount_paid>=(amount_due-GREATEST(0,waiver_amount-?)) THEN "paid" WHEN amount_paid>0 THEN "partial" ELSE "unpaid" END WHERE id=?')
                        ->execute([$wv['requested_amount'], $wv['requested_amount'], $wv['ledger_id']]);
                    $pdo->prepare('INSERT INTO payment_void_logs (entity_type,entity_id,action,old_status,new_status,amount_affected,performed_by,reason) VALUES ("waiver",?,?,?,?,?,?,?)')
                        ->execute([$waiver_id, 'void', 'approved', 'void', $wv['requested_amount'], current_user_id(), $void_reason]);
                    $pdo->commit();
                    log_activity('void_waiver', 'finance', $waiver_id, 'approved', "Reason:$void_reason");
                    flash('success', 'Waiver voided and ledger balance restored.');
                } catch (Exception $e) { $pdo->rollBack(); flash('error', $e->getMessage()); }
            }
        }
        header('Location: waivers.php'); exit;
    }

    // ── Admin: Bulk session-wide waiver ──────────────────────────────────────
    if ($action === 'bulk_waiver' && has_permission('waivers.approve')) {
        $sess_id    = int_param('bulk_session_id', 0, $_POST);
        $class_id   = int_param('bulk_class_id', 0, $_POST);   // 0 = all classes
        $student_id = int_param('bulk_student_id', 0, $_POST); // 0 = all students in class
        $cat_id     = int_param('bulk_cat_id', 0, $_POST);     // 0 = all fee types
        $pct        = min(100, max(0, (int)($_POST['waiver_pct'] ?? 100)));
        $reason     = trim($_POST['bulk_reason'] ?? '');

        if (!$sess_id || !$reason) {
            flash('error', 'Session and reason are required for bulk waiver.');
            header('Location: waivers.php'); exit;
        }

        $bWhere  = 'fl.session_id=? AND fl.status != "paid" AND (fl.amount_due - fl.amount_paid - fl.waiver_amount) > 0.01';
        $bParams = [$sess_id];
        if ($class_id) {
            $bWhere .= ' AND se.class_id=?'; $bParams[] = $class_id;
        }
        if ($student_id) {
            $bWhere .= ' AND fl.student_id=?'; $bParams[] = $student_id;
        }
        if ($cat_id) {
            $bWhere .= ' AND fl.fee_category_id=?'; $bParams[] = $cat_id;
        }

        $ledgers = $pdo->prepare("
            SELECT fl.id, fl.student_id, fl.amount_due, fl.amount_paid, fl.waiver_amount,
                   (fl.amount_due - fl.amount_paid - fl.waiver_amount) AS balance
            FROM fee_ledgers fl
            JOIN student_enrollments se ON se.student_id=fl.student_id AND se.session_id=fl.session_id AND se.status='active'
            WHERE $bWhere
        ");
        $ledgers->execute($bParams);
        $ledgers = $ledgers->fetchAll();

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($ledgers as $l) {
                $waiveAmt = round($l['balance'] * $pct / 100, 2);
                if ($waiveAmt <= 0) continue;
                // Insert a waiver record (auto-approved)
                $pdo->prepare('INSERT INTO waivers (student_id,ledger_id,requested_amount,waiver_reason,waiver_type,requested_by,reviewed_by,review_date,status) VALUES (?,?,?,?,"session_bulk",?,?,NOW(),"approved")')
                    ->execute([$l['student_id'], $l['id'], $waiveAmt, $reason, current_user_id(), current_user_id()]);
                // Apply to ledger
                $newWaiver = round($l['waiver_amount'] + $waiveAmt, 2);
                $newBalance= round($l['amount_due'] - $l['amount_paid'] - $newWaiver, 2);
                $newStatus = $newBalance <= 0.01 ? 'paid' : ($l['amount_paid'] > 0 ? 'partial' : 'unpaid');
                $pdo->prepare('UPDATE fee_ledgers SET waiver_amount=?, waiver_status="approved", status=? WHERE id=?')
                    ->execute([$newWaiver, $newStatus, $l['id']]);
                $count++;
            }
            $pdo->commit();
            log_activity('bulk_waiver', 'finance', $sess_id, '', "Waived $pct% for $count ledgers — $reason");
            flash('success', "Bulk waiver applied: $count fee entries waived ({$pct}% each). Reason: $reason");
        } catch (Exception $e) { $pdo->rollBack(); flash('error', 'Bulk waiver failed: '.$e->getMessage()); }
        header('Location: waivers.php'); exit;
    }

    if ($action === 'request' && has_permission('waivers.request')) {
        $student_id = int_param('student_id', 0, $_POST);
        $ledger_id  = int_param('ledger_id', 0, $_POST);
        $amount     = (float)($_POST['requested_amount'] ?? 0);
        $reason     = trim($_POST['waiver_reason'] ?? '');

        if ($student_id && $ledger_id && $amount > 0 && $reason) {
            $pdo->prepare('INSERT INTO waivers (student_id,ledger_id,requested_amount,waiver_reason,requested_by) VALUES (?,?,?,?,?)')
                ->execute([$student_id,$ledger_id,$amount,$reason,current_user_id()]);
            $pdo->prepare("UPDATE fee_ledgers SET waiver_status='pending' WHERE id=?")->execute([$ledger_id]);
            flash('success', 'Waiver request submitted for admin approval.');
        }
    } elseif (in_array($action, ['approve','reject']) && has_permission('waivers.approve')) {
        $waiver_id = int_param('waiver_id', 0, $_POST);
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $w = $pdo->prepare('SELECT * FROM waivers WHERE id=:id');
        $w->execute([':id' => $waiver_id]);
        $wv = $w->fetch();

        if ($wv) {
            $pdo->prepare('UPDATE waivers SET status=?,reviewed_by=?,review_date=NOW() WHERE id=?')
                ->execute([$newStatus, current_user_id(), $waiver_id]);

            if ($newStatus === 'approved') {
                $pdo->prepare("UPDATE fee_ledgers SET waiver_status='approved', waiver_amount=waiver_amount+? WHERE id=?")
                    ->execute([$wv['requested_amount'], $wv['ledger_id']]);
                // Recalculate ledger status
                $pdo->prepare(
                    "UPDATE fee_ledgers SET status = CASE
                        WHEN amount_paid >= (amount_due - waiver_amount) THEN 'paid'
                        WHEN amount_paid > 0 THEN 'partial'
                        ELSE 'unpaid' END
                    WHERE id=?"
                )->execute([$wv['ledger_id']]);
            } else {
                $pdo->prepare("UPDATE fee_ledgers SET waiver_status='none' WHERE id=?")->execute([$wv['ledger_id']]);
            }

            log_activity($newStatus . '_waiver', 'finance', $waiver_id);
            flash('success', "Waiver " . ($newStatus === 'approved' ? 'approved' : 'rejected') . ".");
        }
    }
    header('Location: waivers.php');
    exit;
}

$status_f = $_GET['status'] ?? 'pending';
$waivers = $pdo->prepare(
    "SELECT w.*, sp.first_name, sp.last_name, sp.student_id_no,
            fc.category_name, fl.amount_due,
            req.full_name as requested_by_name,
            rev.full_name as reviewed_by_name
     FROM waivers w
     JOIN fee_ledgers fl ON fl.id=w.ledger_id
     JOIN fee_categories fc ON fc.id=fl.fee_category_id
     JOIN student_profiles sp ON sp.user_id=w.student_id
     JOIN users req ON req.id=w.requested_by
     LEFT JOIN users rev ON rev.id=w.reviewed_by
     WHERE w.status=:st
     ORDER BY w.request_date DESC"
);
$waivers->execute([':st' => $status_f]);
$waivers = $waivers->fetchAll();

// Counts for tabs
$counts = [];
foreach (['pending','approved','rejected'] as $st) {
    $c = $pdo->prepare('SELECT COUNT(*) FROM waivers WHERE status=:s');
    $c->execute([':s' => $st]);
    $counts[$st] = (int)$c->fetchColumn();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-tag-fill me-2 text-primary"></i>Fee Waivers & Scholarships</h1>
  <?php if (has_permission('waivers.approve')): ?>
  <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkWaiverModal">
    <i class="bi bi-stars me-1"></i>Bulk / Session Waiver
  </button>
  <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3">
  <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
  <li class="nav-item">
    <a class="nav-link <?= $status_f === $k ? 'active' : '' ?>" href="?status=<?= $k ?>">
      <?= $v ?>
      <span class="badge bg-<?= $k === 'pending' ? 'warning' : ($k === 'approved' ? 'success' : 'danger') ?> ms-1"><?= $counts[$k] ?></span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="card table-card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Student</th><th>Fee Category</th><th>Original Due</th><th>Waiver Requested</th><th>Reason</th><th>Requested By</th><th>Date</th>
          <?php if ($status_f === 'pending'): ?><th>Action</th><?php else: ?><th>Reviewed By</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($waivers)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="bi bi-tag"></i><p>No <?= $status_f ?> waiver requests</p></div></td></tr>
        <?php else: foreach ($waivers as $wv): ?>
        <tr>
          <td>
            <div class="fw-600"><?= e($wv['first_name'] . ' ' . $wv['last_name']) ?></div>
            <small class="text-muted"><?= e($wv['student_id_no'] ?? '') ?></small>
          </td>
          <td><?= e($wv['category_name']) ?></td>
          <td><?= money($wv['amount_due']) ?></td>
          <td class="fw-700 text-warning"><?= money($wv['requested_amount']) ?></td>
          <td class="text-muted small" style="max-width:200px;"><?= e(substr($wv['waiver_reason'],0,80)) ?><?= strlen($wv['waiver_reason']) > 80 ? '…' : '' ?></td>
          <td><?= e($wv['requested_by_name']) ?></td>
          <td><?= fmt_date($wv['request_date'], 'd M Y') ?></td>
          <td>
            <?php if ($status_f === 'pending' && has_permission('waivers.approve')): ?>
            <div class="d-flex gap-1">
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="waiver_id" value="<?= $wv['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success" data-confirm="Approve this waiver?"><i class="bi bi-check-lg me-1"></i>Approve</button>
              </form>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="waiver_id" value="<?= $wv['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Reject this waiver?"><i class="bi bi-x-lg"></i></button>
              </form>
            </div>
            <?php elseif ($status_f === 'approved' && has_permission('waivers.approve')): ?>
            <div class="d-flex gap-1 align-items-center">
              <span class="text-muted small"><?= e($wv['reviewed_by_name'] ?? '—') ?></span>
              <button class="btn btn-xs btn-outline-danger ms-1"
                      onclick="openVoidWaiver(<?= $wv['id'] ?>,'<?= e(addslashes($wv['first_name'].' '.$wv['last_name'])) ?>')"
                      title="Void this waiver">
                <i class="bi bi-x-circle"></i>
              </button>
            </div>
            <?php elseif ($status_f !== 'pending'): ?>
            <span class="text-muted small"><?= e($wv['reviewed_by_name'] ?? '—') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Fetch data needed by both modals
$sessions_list   = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes_list    = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();
$fee_cats_list   = $pdo->query('SELECT id,category_name FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();
$curr_sess       = (int)setting('current_session_id', 0);
?>

<!-- Bulk Waiver Modal -->
<?php if (has_permission('waivers.approve')): ?>
<div class="modal fade" id="bulkWaiverModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" onsubmit="return confirm('Apply bulk waiver to all matching fee ledgers? This action will be logged and can be voided per-entry later.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_waiver">
        <div class="modal-header bg-warning py-2">
          <h5 class="modal-title fw-600"><i class="bi bi-stars me-2"></i>Bulk / Session-Wide Waiver</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            This will auto-approve a waiver for all matching <strong>unpaid</strong> fee entries. Each ledger gets its own waiver record. Waivers can be individually voided later by admin.
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-600">Session <span class="text-danger">*</span></label>
              <select name="bulk_session_id" class="form-select form-select-sm" required>
                <?php foreach ($sessions_list as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $curr_sess==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">Class <small class="text-muted">(leave blank = all)</small></label>
              <select name="bulk_class_id" class="form-select form-select-sm">
                <option value="0">— All Classes —</option>
                <?php foreach ($classes_list as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">Fee Type <small class="text-muted">(blank = all types)</small></label>
              <select name="bulk_cat_id" class="form-select form-select-sm">
                <option value="0">— All Fee Types —</option>
                <?php foreach ($fee_cats_list as $fc): ?>
                  <option value="<?= $fc['id'] ?>"><?= e($fc['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-600">Waiver Percentage</label>
              <div class="input-group input-group-sm">
                <input type="number" name="waiver_pct" class="form-control" value="100" min="1" max="100" step="1" required>
                <span class="input-group-text">% of balance</span>
              </div>
              <small class="text-muted">100% = full waive, 50% = half waive</small>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-600">Reason / Justification <span class="text-danger">*</span></label>
              <input type="text" name="bulk_reason" class="form-control form-control-sm" required placeholder="e.g. Scholarship award, COVID relief, Financial hardship — Session 2024">
            </div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-check-lg me-1"></i>Apply Bulk Waiver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Void Waiver Modal -->
<div class="modal fade" id="voidWaiverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="void_waiver">
        <input type="hidden" name="waiver_id" id="void-waiver-id">
        <div class="modal-header bg-danger text-white py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-x-circle me-2"></i>Void Approved Waiver</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Student: <strong id="void-waiver-student" class="text-dark"></strong></p>
          <div class="alert alert-danger py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Voiding will reverse the waiver amount on the student's ledger. Their outstanding balance will be restored. This is permanent and logged.
          </div>
          <div class="mb-0">
            <label class="form-label small fw-600">Reason for Void <span class="text-danger">*</span></label>
            <input type="text" name="void_reason" class="form-control form-control-sm" required placeholder="e.g. Data entry error, Scholarship cancelled">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Void Waiver</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openVoidWaiver(id, studentName) {
  document.getElementById('void-waiver-id').value = id;
  document.getElementById('void-waiver-student').textContent = studentName;
  new bootstrap.Modal(document.getElementById('voidWaiverModal')).show();
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
