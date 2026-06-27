<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'SMS Logs';
$breadcrumbs = ['Communication' => 'sms.php', 'SMS Logs' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['sms.send']);

$pdo = db();

// Resend action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'resend') {
    csrf_check();
    $logId = int_param('log_id', 0, $_POST);
    $log = $pdo->prepare('SELECT * FROM sms_logs WHERE id=?');
    $log->execute([$logId]);
    $log = $log->fetch();
    if ($log) {
        $gateway   = setting('sms_gateway','smsnetbd');
        $apiKey    = setting('sms_api_key','');
        $senderId  = setting('sms_sender_id','');
        $smsEnabled= setting('sms_enabled','1');
        require_once EMS_ROOT . '/modules/communication/sms.php';
        $result = ($smsEnabled === '1')
            ? send_sms_gateway($gateway, $apiKey, $senderId, $log['recipient_phone'], $log['message'])
            : ['success'=>false,'msg'=>'SMS disabled','request_id'=>null];
        $pdo->prepare('UPDATE sms_logs SET status=?, gateway_response=?, resend_count=resend_count+1, gateway_request_id=? WHERE id=?')
            ->execute([$result['success']?'sent':'failed', json_encode($result), $result['request_id']??null, $logId]);
        flash($result['success'] ? 'success' : 'warning', $result['success'] ? 'SMS resent successfully.' : 'Resend attempted but failed: ' . ($result['msg']??''));
    }
    header('Location: sms_logs.php');
    exit;
}

$status_f = $_GET['status'] ?? '';
$feature_f= $_GET['feature'] ?? '';
$from     = $_GET['from']   ?? date('Y-m-d', strtotime('-7 days'));
$to       = $_GET['to']     ?? date('Y-m-d');
$page     = max(1, int_param('page', 1, $_GET));

$where  = ['sl.sent_at BETWEEN :f AND :t'];
$params = [':f' => $from.' 00:00:00', ':t' => $to.' 23:59:59'];
if ($status_f)  { $where[] = 'sl.status=:st';      $params[':st']  = $status_f; }
if ($feature_f) { $where[] = 'sl.feature_key=:fk'; $params[':fk']  = $feature_f; }
$whereStr = implode(' AND ', $where);

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM sms_logs sl WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pg    = paginate($total, $page);

$logs = $pdo->prepare("SELECT sl.*,u.full_name as sent_by_name FROM sms_logs sl LEFT JOIN users u ON u.id=sl.sent_by WHERE $whereStr ORDER BY sl.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$logs->execute($params);
$logs = $logs->fetchAll();

$sentCount   = (int)$pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status='sent'")->fetchColumn();
$failedCount = (int)$pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status='failed'")->fetchColumn();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-chat-left-text-fill me-2 text-primary"></i>SMS Logs</h1>
  <a href="sms.php" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send SMS</a>
</div>
<div class="row g-3 mb-3">
  <div class="col-sm-4"><div class="stat-card success"><div class="stat-value"><?= $sentCount ?></div><div class="stat-label">Sent (All Time)</div><i class="bi bi-check-circle stat-icon"></i></div></div>
  <div class="col-sm-4"><div class="stat-card danger"><div class="stat-value"><?= $failedCount ?></div><div class="stat-label">Failed</div><i class="bi bi-x-circle stat-icon"></i></div></div>
</div>
<div class="card mb-3"><div class="card-body py-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><label class="form-label small">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        <option value="sent" <?= $status_f==='sent'?'selected':'' ?>>Sent</option>
        <option value="failed" <?= $status_f==='failed'?'selected':'' ?>>Failed</option>
        <option value="pending" <?= $status_f==='pending'?'selected':'' ?>>Pending</option>
      </select></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></div>
  </form>
</div></div>
<div class="card table-card">
  <div class="card-header py-3 px-4"><span class="card-title">SMS Log <span class="badge bg-secondary"><?= $total ?></span></span></div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 small">
      <thead>
        <tr>
          <th>Time</th>
          <th>Recipient</th>
          <th>Message</th>
          <th>Feature</th>
          <th>Status</th>
          <th>Resends</th>
          <th>Sent By</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($logs)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-chat-left-x"></i><p>No SMS logs in this period.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach($logs as $log): ?>
        <tr>
          <td class="text-muted small"><?= fmt_date($log['sent_at'],'d M Y H:i') ?></td>
          <td class="fw-600 small"><?= e($log['recipient_phone']) ?></td>
          <td class="small" style="max-width:220px;"><?= e(str_cutoff($log['message'],70)) ?></td>
          <td><span class="badge bg-light text-dark small"><?= e(str_replace('_',' ',$log['feature_key']??'manual')) ?></span></td>
          <td>
            <?php $sc = ['sent'=>'success','failed'=>'danger','pending'=>'warning','queued'=>'secondary']; ?>
            <span class="badge bg-<?= $sc[$log['status']] ?? 'secondary' ?>"><?= ucfirst($log['status']) ?></span>
          </td>
          <td class="text-center small text-muted"><?= (int)($log['resend_count']??0) ?></td>
          <td class="small"><?= e($log['sent_by_name']??'System') ?></td>
          <td>
            <?php if ($log['status'] !== 'sent'): ?>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="resend">
              <input type="hidden" name="log_id"  value="<?= $log['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-warning" style="font-size:.72rem;padding:.15rem .4rem;"
                      data-confirm="Resend this SMS to <?= e($log['recipient_phone']) ?>?">
                <i class="bi bi-arrow-repeat"></i> Resend
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
