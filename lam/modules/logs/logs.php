<?php
// ============================================================
// modules/logs/logs.php — Action log viewer (admin only)
// ============================================================
requireRole(ROLE_ADMIN);

$search = trim($_GET['q'] ?? '');
$module = $_GET['module'] ?? '';
$from   = $_GET['from']   ?? date('Y-m-d', strtotime('-7 days'));
$to     = $_GET['to']     ?? today();

$where  = 'l.created_at BETWEEN ? AND ?';
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($search) { $where .= ' AND (l.note LIKE ? OR l.action LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($module) { $where .= ' AND l.module = ?'; $params[] = $module; }

$page    = max(1, (int)($_GET['p'] ?? 1));
$paged   = paginate(
    "SELECT l.*, u.full_name, u.username FROM action_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.id DESC",
    $params, $page, 50
);

$modules = dbFetchAll('SELECT DISTINCT module FROM action_logs ORDER BY module');

$pageTitle = 'Action Logs';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2">
  <h1>📋 Action Logs</h1>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="logs">
  <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search…" style="max-width:180px">
  <select name="module" class="form-control" style="max-width:140px">
    <option value="">All Modules</option>
    <?php foreach ($modules as $m): ?>
      <option value="<?= e($m['module']) ?>" <?= $module === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option>
    <?php endforeach ?>
  </select>
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:150px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:150px">
  <button type="submit" class="btn btn-ghost">Filter</button>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Note</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-size:.8rem"><?= fmtDateTime($log['created_at']) ?></td>
          <td><?= e($log['full_name'] ?? $log['username'] ?? 'System') ?></td>
          <td><span class="badge badge-info"><?= e($log['action']) ?></span></td>
          <td><?= e($log['module']) ?></td>
          <td><?= $log['record_id'] ?: '—' ?></td>
          <td style="font-size:.82rem"><?= e($log['note']) ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= e($log['ip']) ?></td>
        </tr>
        <?php endforeach ?>
        <?php if (!$paged['rows']): ?>
          <tr><td colspan="7" class="text-muted text-center">No logs found.</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($paged['last_page'] > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=logs&p=<?= $i ?>&q=<?= urlencode($search) ?>&module=<?= urlencode($module) ?>&from=<?= $from ?>&to=<?= $to ?>"
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
