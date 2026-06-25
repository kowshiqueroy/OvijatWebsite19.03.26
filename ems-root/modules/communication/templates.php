<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Document Templates';
$breadcrumbs = ['Communication' => 'sms.php', 'Templates' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['documents.issue']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action']??'';
    if ($action === 'save') {
        $id    = int_param('id',0,$_POST);
        $name  = trim($_POST['template_name']??'');
        $type  = $_POST['template_type']??'custom';
        $body  = $_POST['template_body']??'';
        $paper = $_POST['paper_size']??'A4';
        if ($name && $body) {
            if ($id) {
                $pdo->prepare('UPDATE document_templates SET template_name=?,template_type=?,template_body=?,paper_size=? WHERE id=?')->execute([$name,$type,$body,$paper,$id]);
                flash('success','Template updated.');
            } else {
                $pdo->prepare('INSERT INTO document_templates (template_name,template_type,template_body,paper_size) VALUES (?,?,?,?)')->execute([$name,$type,$body,$paper]);
                flash('success',"Template '$name' created.");
            }
            header('Location: templates.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM document_templates WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Template deleted.');
        header('Location: templates.php');
        exit;
    }
}

$templates = $pdo->query('SELECT * FROM document_templates WHERE status=1 ORDER BY template_type, template_name')->fetchAll();
$editId    = int_param('edit',0,$_GET);
$editTpl   = null;
if ($editId) {
    $e = $pdo->prepare('SELECT * FROM document_templates WHERE id=?');
    $e->execute([$editId]);
    $editTpl = $e->fetch();
}

// Default TC template if none exist
$defaultTemplates = [
    ['tc','Transfer Certificate','<div style="text-align:center;border:2px solid #000;padding:20px;font-family:serif;">
<h2>[SCHOOL_NAME]</h2>
<h3>TRANSFER CERTIFICATE</h3>
<hr>
<table style="width:100%;margin-top:15px;">
<tr><td><b>File No:</b> [FILE_NUMBER]</td><td style="text-align:right;"><b>Date:</b> [ISSUE_DATE]</td></tr>
</table>
<br>
<table style="width:100%;border-collapse:collapse;">
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;width:40%;">Student Name</td><td style="border:1px solid #999;padding:6px;">[STUDENT_NAME]</td></tr>
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;">Father\'s Name</td><td style="border:1px solid #999;padding:6px;">[FATHER_NAME]</td></tr>
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;">Date of Birth</td><td style="border:1px solid #999;padding:6px;">[DOB]</td></tr>
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;">Student ID</td><td style="border:1px solid #999;padding:6px;">[STUDENT_ID]</td></tr>
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;">Admission Date</td><td style="border:1px solid #999;padding:6px;">[ADMISSION_DATE]</td></tr>
<tr><td style="border:1px solid #999;padding:6px;font-weight:bold;">Character</td><td style="border:1px solid #999;padding:6px;">Good</td></tr>
</table>
<div style="display:flex;justify-content:space-between;margin-top:40px;">
<div style="text-align:center;"><div style="border-top:1px solid #000;min-width:150px;padding-top:4px;">Class Teacher</div></div>
<div style="text-align:center;"><div style="border-top:1px solid #000;min-width:150px;padding-top:4px;">Principal</div></div>
</div>
</div>'],
    ['character_certificate','Character Certificate','<div style="text-align:center;border:2px solid #000;padding:20px;font-family:serif;">
<h2>[SCHOOL_NAME]</h2>
<h3>CHARACTER CERTIFICATE</h3>
<hr>
<p><b>Date:</b> [ISSUE_DATE] &nbsp;&nbsp; <b>Ref:</b> [FILE_NUMBER]</p>
<p style="text-align:justify;margin-top:20px;line-height:1.8;">
This is to certify that <b>[STUDENT_NAME]</b>, Son/Daughter of <b>[FATHER_NAME]</b>, bearing Student ID <b>[STUDENT_ID]</b>, was a student of this institution. During the period of study, [STUDENT_NAME] has maintained good character, conduct, and discipline. We wish him/her all success in future endeavours.
</p>
<div style="margin-top:50px;text-align:right;">
<div style="border-top:1px solid #000;display:inline-block;min-width:180px;padding-top:4px;">Principal / Headmaster<br><small>[SCHOOL_NAME]</small></div>
</div>
</div>'],
];

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-file-earmark-richtext-fill me-2 text-primary"></i>Document Templates</h1>
  <div class="d-flex gap-2">
    <?php if(empty($templates)): ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php foreach($defaultTemplates as $dt): ?>
      <input type="hidden" name="template_name[]" value="<?= e($dt[0]) ?>">
      <?php endforeach; ?>
    </form>
    <?php endif; ?>
    <a href="?edit=new" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Template</a>
  </div>
</div>

<?php if($editId || $editId === 'new'): ?>
<!-- Editor -->
<div class="card">
  <div class="card-header py-3 px-4"><span class="card-title"><?= $editTpl?'Edit Template':'New Template' ?></span></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editTpl?$editTpl['id']:0 ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label">Template Name *</label><input type="text" name="template_name" class="form-control" value="<?= e($editTpl['template_name']??'') ?>" required></div>
        <div class="col-md-3"><label class="form-label">Type</label><select name="template_type" class="form-select"><?php foreach(['tc'=>'Transfer Certificate','character_certificate'=>'Character Certificate','testimonial'=>'Testimonial','official_letter'=>'Official Letter','custom'=>'Custom'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($editTpl['template_type']??'custom')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Paper Size</label><select name="paper_size" class="form-select"><option value="A4">A4</option><option value="legal">Legal</option><option value="letter">Letter</option></select></div>
      </div>

      <div class="mb-2">
        <label class="form-label">Template Body (HTML with shortcodes)</label>
        <div class="mb-2 d-flex flex-wrap gap-1">
          <?php foreach(['[STUDENT_NAME]','[STUDENT_ID]','[FATHER_NAME]','[MOTHER_NAME]','[DOB]','[CLASS_NAME]','[ROLL_NUMBER]','[ADMISSION_DATE]','[ISSUE_DATE]','[FILE_NUMBER]','[SCHOOL_NAME]','[PRINCIPAL_NAME]'] as $sc): ?>
          <button type="button" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .45rem;" onclick="insertSC('<?= $sc ?>')"><?= $sc ?></button>
          <?php endforeach; ?>
        </div>
        <textarea name="template_body" id="tpl_body" class="form-control font-monospace" rows="20" required><?= e($editTpl['template_body']??($defaultTemplates[0][2]??'')) ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Template</button>
        <a href="templates.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="button" class="btn btn-outline-info" onclick="previewTemplate()"><i class="bi bi-eye me-1"></i>Preview</button>
      </div>
    </form>
  </div>
</div>

<script>
function insertSC(sc) {
  const ta = document.getElementById('tpl_body');
  const pos = ta.selectionStart;
  ta.value = ta.value.substring(0,pos) + sc + ta.value.substring(ta.selectionEnd);
  ta.selectionStart = ta.selectionEnd = pos + sc.length;
  ta.focus();
}
function previewTemplate() {
  const body = document.getElementById('tpl_body').value;
  const win  = window.open('','_blank');
  win.document.write('<!DOCTYPE html><html><head><style>body{padding:20px;}</style></head><body>' + body + '</body></html>');
  win.document.close();
}
</script>

<?php else: ?>
<!-- Template list -->
<div class="row g-3">
  <!-- Default templates hint -->
  <?php if(empty($templates)): ?>
  <div class="col-12"><div class="alert alert-info d-flex gap-2"><i class="bi bi-lightbulb-fill"></i><div>No templates yet. <strong>Quick start:</strong> Click "New Template" and use one of the shortcode buttons to build a Transfer Certificate or Character Certificate template.</div></div></div>
  <?php endif; ?>

  <?php foreach($templates as $tpl): ?>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <h6 class="fw-700 mb-0"><?= e($tpl['template_name']) ?></h6>
            <span class="badge bg-secondary small"><?= e(str_replace('_',' ',$tpl['template_type'])) ?></span>
          </div>
          <span class="text-muted small"><?= $tpl['paper_size'] ?></span>
        </div>
        <p class="text-muted small mb-3"><?= e(substr(strip_tags($tpl['template_body']),0,80)) ?>…</p>
        <div class="d-flex gap-2">
          <a href="?edit=<?= $tpl['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
          <a href="issue.php?template_id=<?= $tpl['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-check me-1"></i>Issue</a>
          <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $tpl['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if(empty($templates)): ?>
  <div class="col-12"><div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-file-earmark-richtext"></i><p>No document templates yet. Create one to start issuing certificates.</p></div></div></div></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
