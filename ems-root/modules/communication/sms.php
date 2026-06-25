<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Send SMS';
$breadcrumbs = ['Communication' => null, 'Send SMS' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['sms.send']);

$pdo    = db();
$errors = [];
$sent   = 0;

// Load SMS templates
$templates = $pdo->query('SELECT * FROM sms_templates WHERE status=1 ORDER BY template_name')->fetchAll();

// Get gateway config
$gateway    = setting('sms_gateway', 'greenweb');
$api_key    = setting('sms_api_key', '');
$sender_id  = setting('sms_sender_id', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action     = $_POST['action'] ?? '';
    $recipients = array_filter(array_map('trim', explode("\n", $_POST['recipients'] ?? '')));
    $message    = trim($_POST['message'] ?? '');

    if ($action === 'send') {
        if (empty($recipients)) $errors[] = 'No recipient numbers provided.';
        if (!$message)          $errors[] = 'Message body is required.';
        if (!$api_key)          $errors[] = 'SMS gateway not configured. Set API key in System Settings.';

        if (empty($errors)) {
            $logStmt = $pdo->prepare(
                'INSERT INTO sms_logs (recipient_phone, message, sent_by, status, gateway_response) VALUES (?,?,?,?,?)'
            );

            foreach ($recipients as $phone) {
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                if (strlen($phone) < 10) continue;

                // Attempt send via configured gateway
                $response = send_sms($gateway, $api_key, $sender_id, $phone, $message);
                $status   = $response['success'] ? 'sent' : 'failed';
                $logStmt->execute([$phone, $message, current_user_id(), $status, json_encode($response)]);
                if ($response['success']) $sent++;
            }
            flash('success', "SMS sent to $sent of " . count($recipients) . " recipients.");
            header('Location: sms.php');
            exit;
        }
    } elseif ($action === 'save_template') {
        $tname = trim($_POST['tpl_name'] ?? '');
        $tbody = trim($_POST['tpl_body'] ?? '');
        $ttype = $_POST['tpl_type'] ?? 'custom';
        if ($tname && $tbody) {
            $pdo->prepare('INSERT INTO sms_templates (template_name,template_body,trigger_type) VALUES (?,?,?)')->execute([$tname,$tbody,$ttype]);
            flash('success', "Template '$tname' saved.");
            header('Location: sms.php');
            exit;
        }
    }
}

// SMS sender helper
function send_sms(string $gateway, string $apiKey, string $senderId, string $phone, string $message): array {
    // Format: [success, message, raw response]
    if (!$apiKey) return ['success' => false, 'msg' => 'No API key'];

    try {
        switch ($gateway) {
            case 'greenweb':
                $url = 'http://api.greenweb.com.bd/api.php?token=' . urlencode($apiKey)
                     . '&to=' . urlencode($phone)
                     . '&message=' . urlencode($message);
                break;
            case 'ssl':
                $url = 'https://bulksmsbd.net/api/smsapi?api_key=' . urlencode($apiKey)
                     . '&type=text&number=' . urlencode($phone)
                     . '&senderid=' . urlencode($senderId)
                     . '&message=' . urlencode($message);
                break;
            default:
                return ['success' => false, 'msg' => 'Unknown gateway'];
        }

        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $ctx);
        $success  = $response !== false && (str_contains($response, 'success') || str_contains($response, '200'));
        return ['success' => $success, 'raw' => $response ?: 'No response'];
    } catch (Exception $e) {
        return ['success' => false, 'msg' => $e->getMessage()];
    }
}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Send SMS</h1>

<?php if (!$api_key): ?>
<div class="alert alert-warning d-flex gap-2">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>SMS gateway not configured. <a href="../setup/index.php">Go to System Settings</a> to enter your API credentials.</div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Compose -->
  <div class="col-md-7">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Compose SMS</span></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send">

          <!-- Quick recipient selector -->
          <div class="mb-3">
            <label class="form-label">Quick Select Recipients</label>
            <div class="d-flex gap-2 flex-wrap">
              <?php
              $session_id = (int)setting('current_session_id',0);
              $classes    = $pdo->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY display_order')->fetchAll();
              ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadRecipients('all_guardian')">All Guardians</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadRecipients('all_staff')">All Staff</button>
              <?php foreach ($classes as $cls): ?>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick="loadRecipients('class_<?= $cls['id'] ?>')">Class <?= e($cls['class_name']) ?></button>
              <?php endforeach; ?>
            </div>
            <small class="text-muted">Or enter numbers manually below</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone Numbers <small class="text-muted">(one per line)</small></label>
            <textarea name="recipients" id="recipients" class="form-control" rows="5"
                      placeholder="01711000000&#10;01811000000"></textarea>
            <small class="text-muted"><span id="rec-count">0</span> numbers entered</small>
          </div>

          <div class="mb-3">
            <label class="form-label d-flex align-items-center justify-content-between">
              Message
              <span class="text-muted small"><span id="char-count">0</span>/160</span>
            </label>
            <textarea name="message" id="sms-message" class="form-control" rows="4"
                      maxlength="640" oninput="updateCounts()"
                      placeholder="Type your message here…"></textarea>
          </div>

          <button type="submit" class="btn btn-primary <?= !$api_key ? 'disabled' : '' ?>">
            <i class="bi bi-send me-1"></i>Send SMS
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Templates sidebar -->
  <div class="col-md-5">
    <div class="card mb-3">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Templates</span>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tplModal">
          <i class="bi bi-plus-lg"></i>
        </button>
      </div>
      <div class="list-group list-group-flush">
        <?php if (empty($templates)): ?>
          <div class="text-muted text-center py-3 small">No templates saved</div>
        <?php else: foreach ($templates as $tpl): ?>
          <div class="list-group-item">
            <div class="d-flex align-items-center justify-content-between">
              <span class="fw-600 small"><?= e($tpl['template_name']) ?></span>
              <button type="button" class="btn btn-xs btn-outline-success" style="font-size:.72rem;padding:.15rem .45rem;"
                      onclick="document.getElementById('sms-message').value=<?= htmlspecialchars(json_encode($tpl['template_body']), ENT_QUOTES) ?>;updateCounts()">
                Use
              </button>
            </div>
            <p class="text-muted small mb-0 mt-1"><?= e(substr($tpl['template_body'], 0, 80)) ?>…</p>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- SMS Log -->
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Recent Logs</span></div>
      <div class="list-group list-group-flush">
        <?php
        $logs = $pdo->query('SELECT * FROM sms_logs ORDER BY id DESC LIMIT 10')->fetchAll();
        foreach ($logs as $log): ?>
        <div class="list-group-item py-2">
          <div class="d-flex align-items-center justify-content-between">
            <span class="fw-600 small"><?= e($log['recipient_phone']) ?></span>
            <span class="badge-status badge-<?= $log['status'] === 'sent' ? 'active' : 'rejected' ?> small"><?= ucfirst(e($log['status'])) ?></span>
          </div>
          <p class="text-muted small mb-0"><?= e(substr($log['message'],0,60)) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?><div class="text-muted text-center py-3 small">No logs yet</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Template Save Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_template">
        <div class="modal-header"><h5 class="modal-title">Save Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Template Name</label>
            <input type="text" name="tpl_name" class="form-control" required placeholder="e.g. Exam Result Alert">
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="tpl_type" class="form-select">
              <option value="custom">Custom</option>
              <option value="attendance">Attendance</option>
              <option value="fee_due">Fee Due</option>
              <option value="result">Result</option>
              <option value="emergency">Emergency</option>
            </select>
          </div>
          <div>
            <label class="form-label">Message Body</label>
            <textarea name="tpl_body" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function updateCounts() {
  const msg = document.getElementById('sms-message').value;
  document.getElementById('char-count').textContent = msg.length;
  const rec = document.getElementById('recipients').value.split('\n').filter(l => l.trim().length >= 10);
  document.getElementById('rec-count').textContent = rec.length;
}

function loadRecipients(type) {
  document.getElementById('recipients').value = 'Loading…';
  fetch(`ajax.php?action=recipients&type=${type}&session_id=<?= (int)setting('current_session_id',0) ?>`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('recipients').value = data.join('\n');
      updateCounts();
    })
    .catch(() => { document.getElementById('recipients').value = ''; });
}

document.getElementById('recipients').addEventListener('input', updateCounts);
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
