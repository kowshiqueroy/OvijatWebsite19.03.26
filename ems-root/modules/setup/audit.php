<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Activity Log';
$breadcrumbs = ['Setup' => 'index.php', 'Activity Log' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['setup.view']);

$pdo = db();

$module = trim($_GET['module'] ?? '');
$user_f = int_param('user_id', 0, $_GET);
$from   = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to     = $_GET['to']   ?? date('Y-m-d');
$page   = max(1, int_param('page', 1, $_GET));

$where  = ['al.created_at BETWEEN :f AND :t'];
$params = [':f' => $from.' 00:00:00', ':t' => $to.' 23:59:59'];
if ($module) { $where[] = 'al.module = :mod'; $params[':mod'] = $module; }
if ($user_f) { $where[] = 'al.user_id = :uid'; $params[':uid'] = $user_f; }
$whereStr = implode(' AND ', $where);

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);

$logs = $pdo->prepare(
    "SELECT al.*, u.full_name FROM activity_logs al
     LEFT JOIN users u ON u.id=al.user_id
     WHERE $whereStr
     ORDER BY al.id DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$logs->execute($params);
$logs = $logs->fetchAll();

$modules = $pdo->query('SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);
$users   = $pdo->query('SELECT id, full_name FROM users WHERE status="active" ORDER BY full_name LIMIT 100')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<h1 class="page-title"><i class="bi bi-clock-history me-2 text-primary"></i>Activity Log</h1>

<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><label class="form-label small">Module</label>
      <select name="module" class="form-select form-select-sm">
        <option value="">All Modules</option>
        <?php foreach($modules as $m): ?><option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= ucfirst(e($m)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label small">User</label>
      <select name="user_id" class="form-select form-select-sm">
        <option value="0">All Users</option>
        <?php foreach($users as $u): ?><option value="<?= $u['id'] ?>" <?= $user_f==$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      <a href="audit.php" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div></div>

<div class="card table-card">
  <div class="card-header py-3 px-4">
    <span class="card-title">Log Entries <span class="badge bg-secondary"><?= $total ?></span></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP</th><th>Old Value</th><th>New Value</th></tr></thead>
      <tbody>
        <?php if(empty($logs)): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-clock-history"></i><p>No activity in this period.</p></div></td></tr><?php endif; ?>
        <?php foreach($logs as $log): ?>
        <tr>
          <td class="text-muted"><?= fmt_date($log['created_at'],'d M H:i') ?></td>
          <td class="fw-600"><?= e($log['full_name']??'System') ?></td>
          <td><span class="badge bg-light text-dark"><?= e(str_replace('_',' ',$log['action'])) ?></span></td>
          <td><?= e($log['module']??'—') ?></td>
          <td><?= $log['record_id'] ? '#'.$log['record_id'] : '—' ?></td>
          <td><code><?= e($log['ip_address']??'—') ?></code></td>
          <td class="text-muted" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($log['old_value']??'') ?></td>
          <td class="text-muted" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($log['new_value']??'') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($pg['total_pages']>1): ?>
  <div class="card-footer d-flex align-items-center justify-content-between py-2 px-4">
    <small class="text-muted">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$total) ?> of <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for($p=max(1,$pg['page']-2);$p<=min($pg['total_pages'],$pg['page']+2);$p++): ?>
        <li class="page-item <?= $p===$pg['page']?'active':'' ?>"><a class="page-link" href="?module=<?= urlencode($module) ?>&user_id=<?= $user_f ?>&from=<?= $from ?>&to=<?= $to ?>&page=<?= $p ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
