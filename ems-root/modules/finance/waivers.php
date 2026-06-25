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

<h1 class="page-title"><i class="bi bi-tag-fill me-2 text-primary"></i>Fee Waivers & Scholarships</h1>

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

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
