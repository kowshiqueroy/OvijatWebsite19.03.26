<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Issue Document';
$breadcrumbs = ['Communication' => 'templates.php', 'Issue' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['documents.issue']);

$pdo         = db();
$template_id = int_param('template_id',0,$_GET);
$student_id  = int_param('student_id',0,$_GET);

$tpl = null;
if ($template_id) {
    $t = $pdo->prepare('SELECT * FROM document_templates WHERE id=?');
    $t->execute([$template_id]);
    $tpl = $t->fetch();
}

// Search students
$searchQ    = trim($_GET['q']??'');
$searchResults = [];
if ($searchQ) {
    $sr = $pdo->prepare("SELECT u.id, sp.first_name, sp.last_name, sp.student_id_no, c.class_name, sec.section_name FROM users u JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.session_id=:sess AND se.status='active' LEFT JOIN classes c ON c.id=se.class_id LEFT JOIN sections sec ON sec.id=se.section_id WHERE (sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q) AND u.status='active' LIMIT 15");
    $sr->execute([':sess'=>(int)setting('current_session_id',0),':q'=>"%$searchQ%"]);
    $searchResults = $sr->fetchAll();
}

// Load student data for substitution
$studentData = null;
if ($student_id) {
    $sd = $pdo->prepare('SELECT sp.*, u.id as user_id FROM student_profiles sp JOIN users u ON u.id=sp.user_id WHERE u.id=?');
    $sd->execute([$student_id]);
    $studentData = $sd->fetch();
}

// Issue document
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tid    = int_param('template_id',0,$_POST);
    $sid    = int_param('student_id',0,$_POST)?:null;
    $notes  = trim($_POST['notes']??'');

    // Dues checking
    $certDuesAllow = (int)setting('certificate_dues_allow', '1');
    if ($certDuesAllow === 0 && $sid && student_has_dues((int)$sid)) {
        flash('error', 'Cannot issue document. Student has outstanding dues and Certificate Dues Block is active.');
        header("Location: issue.php?template_id=$tid&student_id=$sid");
        exit;
    }

    $count  = (int)$pdo->query('SELECT COUNT(*) FROM document_issues')->fetchColumn() + 1;
    $serial = 'DOC-'.date('Y').'-'.str_pad($count,5,'0',STR_PAD_LEFT);

    $pdo->prepare('INSERT INTO document_issues (template_id,student_id,issue_date,serial_number,issued_by,notes) VALUES (?,?,CURDATE(),?,?,?)')->execute([$tid,$sid,$serial,current_user_id(),$notes]);

    flash('success',"Document issued. Serial: $serial");
    header("Location: issue.php?template_id=$tid&student_id=".($sid??0)."&issued=$serial");
    exit;
}

// Generate preview
$preview = '';
$issuedSerial = $_GET['issued']??'';
if ($tpl && $studentData) {
    $school = setting('school_name','School');
    $subs   = [
        '[STUDENT_NAME]'    => $studentData['first_name'].' '.$studentData['last_name'],
        '[STUDENT_ID]'      => $studentData['student_id_no']??'',
        '[FATHER_NAME]'     => $studentData['father_name']??'',
        '[MOTHER_NAME]'     => $studentData['mother_name']??'',
        '[DOB]'             => fmt_date($studentData['dob']),
        '[ADMISSION_DATE]'  => fmt_date($studentData['admission_date']),
        '[ISSUE_DATE]'      => date('d M Y'),
        '[FILE_NUMBER]'     => $issuedSerial ?: 'PREVIEW',
        '[SCHOOL_NAME]'     => $school,
        '[PRINCIPAL_NAME]'  => 'Principal',
    ];
    $preview = str_replace(array_keys($subs), array_values($subs), $tpl['template_body']);
}

$templates = $pdo->query('SELECT id,template_name,template_type FROM document_templates WHERE status=1 ORDER BY template_name')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-file-earmark-check-fill me-2 text-primary"></i>Issue Document</h1>
  <?php if($preview): ?>
  <button onclick="document.getElementById('preview-frame').contentWindow.print()" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Print Document</button>
  <?php endif; ?>
</div>

<?php if($issuedSerial): ?><div class="alert alert-success d-flex gap-2"><i class="bi bi-check-circle-fill fs-5"></i><div>Document issued successfully. Serial: <strong><?= e($issuedSerial) ?></strong></div></div><?php endif; ?>

<div class="row g-3">
  <!-- Controls -->
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">1. Select Template</span></div>
      <div class="list-group list-group-flush">
        <?php foreach($templates as $t): ?>
        <a href="?template_id=<?= $t['id'] ?>&student_id=<?= $student_id ?>&q=<?= urlencode($searchQ) ?>"
           class="list-group-item list-group-item-action py-2 <?= $template_id==$t['id']?'active':'' ?>">
          <span class="fw-600"><?= e($t['template_name']) ?></span>
          <small class="d-block text-muted <?= $template_id==$t['id']?'text-white-50':'' ?>"><?= e(str_replace('_',' ',$t['template_type'])) ?></small>
        </a>
        <?php endforeach; ?>
        <?php if(empty($templates)): ?><div class="text-muted text-center small py-3">No templates. <a href="templates.php">Create one →</a></div><?php endif; ?>
      </div>
    </div>

    <?php if($template_id): ?>
    <div class="card mb-3">
      <div class="card-header py-3 px-4"><span class="card-title">2. Find Student</span></div>
      <div class="card-body">
        <form method="GET">
          <input type="hidden" name="template_id" value="<?= $template_id ?>">
          <div class="input-group mb-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Name or ID…" value="<?= e($searchQ) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
          </div>
        </form>
        <?php foreach($searchResults as $sr): ?>
        <a href="?template_id=<?= $template_id ?>&student_id=<?= $sr['id'] ?>" class="list-group-item list-group-item-action py-2 <?= $student_id==$sr['id']?'active':'' ?>">
          <div class="fw-600 small"><?= e($sr['first_name'].' '.$sr['last_name']) ?></div>
          <div class="text-muted small"><?= e($sr['student_id_no']??'') ?> · <?= e($sr['class_name']??'?') ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if($student_id && $studentData): 
      $hasDues = student_has_dues($student_id);
      $certDuesAllow = (int)setting('certificate_dues_allow', '1');
      $isBlocked = ($hasDues && $certDuesAllow === 0);
    ?>
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">3. Issue</span></div>
      <div class="card-body">
        <div class="fw-700 mb-1"><?= e($studentData['first_name'].' '.$studentData['last_name']) ?></div>
        <div class="text-muted small mb-3"><?= e($studentData['student_id_no']??'') ?></div>
        
        <?php if ($isBlocked): ?>
          <div class="alert alert-danger py-2 small mb-0">
            <i class="bi bi-lock-fill me-1"></i> Document locked due to outstanding tuition fees.
          </div>
        <?php else: ?>
          <?php if ($hasDues): ?>
            <div class="alert alert-warning py-1 small mb-2">
              <i class="bi bi-exclamation-triangle-fill me-1"></i> Student has outstanding dues.
            </div>
          <?php endif; ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="template_id" value="<?= $template_id ?>">
            <input type="hidden" name="student_id" value="<?= $student_id ?>">
            <div class="mb-3"><label class="form-label small">Notes (optional)</label><input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Requested by guardian"></div>
            <button type="submit" class="btn btn-success w-100" data-confirm="Issue this document?"><i class="bi bi-file-earmark-check me-1"></i>Issue Document</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Preview -->
  <div class="col-md-8">
    <?php if($preview): ?>
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Document Preview</span></div>
      <div class="card-body p-0">
        <iframe id="preview-frame" style="width:100%;min-height:600px;border:0;"
                srcdoc="<!DOCTYPE html><html><head><style>body{padding:20px;font-family:Arial,sans-serif;}</style></head><body><?= htmlspecialchars($preview, ENT_QUOTES) ?></body></html>">
        </iframe>
      </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-file-earmark-text"></i><p>Select a template and student to preview the document.</p></div></div></div>
    <?php endif; ?>
  </div>
</div>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
