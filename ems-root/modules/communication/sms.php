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

$templates  = $pdo->query('SELECT * FROM sms_templates WHERE status=1 ORDER BY template_name')->fetchAll();
$gateway    = setting('sms_gateway',   'smsnetbd');
$api_key    = setting('sms_api_key',   '64v0VK2aq7AddQlE40Oh4T4oXpkgL3VBXxlc4W6l');
$sender_id  = setting('sms_sender_id', '');
$smsEnabled = setting('sms_enabled',   '1');
$mgmtNums   = $pdo->query('SELECT phone FROM sms_management_numbers WHERE is_active=1')->fetchAll(PDO::FETCH_COLUMN);

// ── SMS gateway function (sms.net.bd + legacy) ─────────────────────────────
function send_sms_gateway(string $gateway, string $apiKey, string $senderId, string $phone, string $message): array {
    if (!$apiKey) return ['success'=>false,'msg'=>'No API key','request_id'=>null];
    try {
        switch ($gateway) {
            case 'smsnetbd':
                $params = http_build_query(['api_key'=>$apiKey,'msg'=>$message,'to'=>$phone]);
                if ($senderId) $params .= '&sender_id='.urlencode($senderId);
                $url = 'https://api.sms.net.bd/sendsms?' . $params;
                $ctx = stream_context_create(['http'=>['timeout'=>15,'method'=>'GET']]);
                $raw = @file_get_contents($url, false, $ctx);
                if ($raw === false) return ['success'=>false,'msg'=>'Connection failed','request_id'=>null];
                $data = json_decode($raw, true);
                $ok   = isset($data['error']) && $data['error'] == 0;
                return ['success'=>$ok,'msg'=>$data['msg']??'','request_id'=>$data['data']['request_id']??null,'raw'=>$raw];

            case 'greenweb':
                $url = 'http://api.greenweb.com.bd/api.php?token='.urlencode($apiKey)
                     . '&to='.urlencode($phone).'&message='.urlencode($message);
                $raw = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>10]]));
                $ok  = $raw !== false && (str_contains($raw,'success') || str_contains($raw,'200'));
                return ['success'=>$ok,'raw'=>$raw??'','request_id'=>null,'msg'=>''];

            case 'ssl':
                $url = 'https://bulksmsbd.net/api/smsapi?api_key='.urlencode($apiKey)
                     . '&type=text&number='.urlencode($phone)
                     . '&senderid='.urlencode($senderId).'&message='.urlencode($message);
                $raw = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>10]]));
                $ok  = $raw !== false;
                return ['success'=>$ok,'raw'=>$raw??'','request_id'=>null,'msg'=>''];

            default:
                return ['success'=>false,'msg'=>'Unknown gateway','request_id'=>null];
        }
    } catch (Exception $e) {
        return ['success'=>false,'msg'=>$e->getMessage(),'request_id'=>null];
    }
}

// ── Helper: send + log ──────────────────────────────────────────────────────
function dispatch_sms(PDO $pdo, string $phone, string $message, string $gateway, string $apiKey,
                      string $senderId, bool $smsEnabled, string $featureKey = ''): array {
    if ($smsEnabled === '1') {
        $result = send_sms_gateway($gateway, $apiKey, $senderId, $phone, $message);
    } else {
        $result = ['success'=>false,'msg'=>'SMS disabled (log-only mode)','request_id'=>null,'raw'=>''];
    }

    $pdo->prepare('INSERT INTO sms_logs (recipient_phone, message, feature_key, gateway, gateway_request_id, sent_by, status, gateway_response)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([
            $phone, $message, $featureKey ?: null, $gateway,
            $result['request_id'] ?? null,
            $_SESSION['user_id'] ?? null,
            $smsEnabled === '1' ? ($result['success'] ? 'sent' : 'failed') : 'queued',
            json_encode($result),
        ]);

    return $result;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $rawRecipients = array_filter(array_map('trim', explode("\n", $_POST['recipients'] ?? '')));
        $message       = trim($_POST['message'] ?? '');
        $sendToMgmt    = isset($_POST['send_to_mgmt']) ? true : false;

        if (empty($rawRecipients)) $errors[] = 'No recipient numbers provided.';
        if (!$message)             $errors[] = 'Message body is required.';

        if (empty($errors)) {
            $allNumbers = [];
            foreach ($rawRecipients as $phone) {
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                if (strlen($phone) >= 10) $allNumbers[] = $phone;
            }
            // Add management numbers if requested
            if ($sendToMgmt || isset($_POST['always_mgmt'])) {
                foreach ($mgmtNums as $mp) {
                    if (!in_array($mp, $allNumbers)) $allNumbers[] = $mp;
                }
            }
            foreach ($allNumbers as $phone) {
                $result = dispatch_sms($pdo, $phone, $message, $gateway, $api_key, $sender_id, $smsEnabled, 'manual');
                if ($result['success']) $sent++;
            }
            flash('success', "SMS processed for ".count($allNumbers)." recipient(s). $sent sent successfully." . ($smsEnabled !== '1' ? ' (Log-only mode — not actually sent)' : ''));
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

// Balance check
$balance_info = null;
if ($api_key && $gateway === 'smsnetbd') {
    $balRaw = @file_get_contents('https://api.sms.net.bd/user/balance/?api_key=' . urlencode($api_key));
    if ($balRaw) {
        $balData = json_decode($balRaw, true);
        if (isset($balData['data']['balance'])) $balance_info = $balData['data']['balance'];
    }
}

require_once EMS_ROOT . '/includes/header.php';
$session_id = (int)setting('current_session_id',0);
$classes    = $pdo->query('SELECT id, class_name FROM classes WHERE deleted_at IS NULL AND status=1 ORDER BY display_order')->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Send SMS</h1>
  <div class="d-flex gap-2 align-items-center">
    <?php if ($smsEnabled !== '1'): ?>
      <span class="badge bg-warning text-dark"><i class="bi bi-bell-slash me-1"></i>Log-Only Mode</span>
    <?php endif; ?>
    <?php if ($balance_info !== null): ?>
      <span class="badge bg-success"><i class="bi bi-wallet2 me-1"></i>Balance: ৳<?= e($balance_info) ?></span>
    <?php endif; ?>
    <a href="sms_logs.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-journal-text me-1"></i>SMS Logs
    </a>
    <a href="../setup/index.php?tab=sms" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-gear me-1"></i>SMS Settings
    </a>
  </div>
</div>

<?php if (!$api_key): ?>
<div class="alert alert-warning d-flex gap-2">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div>SMS gateway not configured. <a href="../setup/index.php#tab-sms">Go to System Settings → SMS</a> to enter API credentials.</div>
</div>
<?php endif; ?>

<?php if ($smsEnabled !== '1'): ?>
<div class="alert alert-info d-flex gap-2 small">
  <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
  <div>SMS is currently in <strong>Log-Only mode</strong>. Messages will be saved to the log but NOT actually sent. Enable sending in <a href="../setup/index.php#tab-sms">Settings</a>.</div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Compose -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h6 class="card-title mb-0"><i class="bi bi-pencil-square me-1"></i>Compose SMS</h6></div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send">

          <div class="mb-3">
            <label class="form-label fw-bold">Quick Select Recipients</label>
            <div class="d-flex gap-2 flex-wrap mb-1">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadRecipients('all_guardian')">
                <i class="bi bi-people me-1"></i>All Guardians
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadRecipients('all_staff')">
                <i class="bi bi-person-workspace me-1"></i>All Staff
              </button>
              <?php foreach ($classes as $cls): ?>
                <button type="button" class="btn btn-sm btn-outline-primary btn-xs"
                        onclick="loadRecipients('class_<?= $cls['id'] ?>')">
                  <?= e($cls['class_name']) ?>
                </button>
              <?php endforeach; ?>
            </div>
            <small class="text-muted">Or enter phone numbers manually below (one per line)</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone Numbers <small class="text-muted">(one per line, starting with 01 or 880)</small></label>
            <textarea name="recipients" id="recipients" class="form-control font-monospace" rows="5"
                      placeholder="01711000000&#10;01811000000"
                      oninput="updateCounts()"></textarea>
            <small class="text-muted"><span id="rec-count">0</span> number(s) entered</small>
          </div>

          <div class="mb-3">
            <label class="form-label d-flex align-items-center justify-content-between">
              <span>Message Body</span>
              <span class="text-muted small"><span id="char-count">0</span> chars / <span id="sms-parts">1</span> SMS</span>
            </label>
            <textarea name="message" id="sms-message" class="form-control" rows="4"
                      maxlength="640" oninput="updateCounts()"
                      placeholder="Type your message…"></textarea>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="send_to_mgmt" id="sendMgmt"
                   <?= count($mgmtNums) > 0 ? 'checked' : '' ?>>
            <label class="form-check-label" for="sendMgmt">
              Also send to management numbers
              <small class="text-muted">(<?= count($mgmtNums) ?> configured)</small>
            </label>
          </div>

          <button type="submit" class="btn btn-primary" <?= !$api_key ? 'disabled' : '' ?>>
            <i class="bi bi-send me-1"></i><?= $smsEnabled !== '1' ? 'Log SMS (not sending)' : 'Send SMS' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Templates & Stats sidebar -->
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><h6 class="card-title mb-0">SMS Templates</h6></div>
      <div class="card-body p-0">
        <?php if (empty($templates)): ?>
        <div class="p-3 text-muted small">No templates yet.</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($templates as $tpl): ?>
          <button type="button" class="list-group-item list-group-item-action py-2 px-3 text-start"
                  onclick="document.getElementById('sms-message').value=<?= json_encode($tpl['template_body']) ?>;updateCounts()">
            <div class="fw-600 small"><?= e($tpl['template_name']) ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= e(str_cutoff($tpl['template_body'], 60)) ?></div>
          </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h6 class="card-title mb-0">Management Numbers</h6></div>
      <div class="card-body p-0">
        <?php
        $mgmtFull = $pdo->query('SELECT * FROM sms_management_numbers WHERE is_active=1 ORDER BY id')->fetchAll();
        if (empty($mgmtFull)): ?>
        <div class="p-3 small text-muted">No management numbers. <a href="../setup/index.php#tab-sms">Add in Settings</a>.</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($mgmtFull as $mn): ?>
          <div class="list-group-item py-1 px-3 small">
            <span class="fw-600"><?= e($mn['name']) ?></span>
            <span class="text-muted ms-2"><?= e($mn['phone']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="card-title mb-0">Add Template</h6>
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_template">
          <div class="mb-2">
            <input type="text" name="tpl_name" class="form-control form-control-sm" placeholder="Template name" required>
          </div>
          <div class="mb-2">
            <textarea name="tpl_body" class="form-control form-control-sm" rows="3"
                      placeholder="Message body. Use {{student_name}}, {{guardian_name}}, {{amount}} etc." required></textarea>
          </div>
          <div class="mb-2">
            <select name="tpl_type" class="form-select form-select-sm">
              <?php foreach (['custom'=>'Custom','attendance'=>'Attendance','absent_student'=>'Student Absent',
                              'fee_due'=>'Fee Due','fee_collection'=>'Fee Collection','result'=>'Result Published',
                              'emergency'=>'Emergency'] as $k=>$v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">
            <i class="bi bi-plus-lg me-1"></i>Save Template
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function updateCounts() {
  const msg  = document.getElementById('sms-message').value;
  const recs = document.getElementById('recipients').value
               .split('\n').filter(l => l.trim().replace(/[^0-9]/g,'').length >= 10);
  document.getElementById('char-count').textContent = msg.length;
  document.getElementById('sms-parts').textContent  = Math.ceil(msg.length / 160) || 1;
  document.getElementById('rec-count').textContent  = recs.length;
}
updateCounts();

function loadRecipients(type) {
  const ta = document.getElementById('recipients');
  ta.value = 'Loading…';
  const sessId = <?= (int)setting('current_session_id',0) ?>;
  fetch(`../communication/ajax.php?action=sms_recipients&type=${type}&session_id=${sessId}`)
    .then(r => r.json())
    .then(data => {
      ta.value = data.phones ? data.phones.join('\n') : '';
      updateCounts();
    })
    .catch(() => { ta.value = ''; });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
