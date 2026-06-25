<?php
/**
 * Result Card & Report Centre
 * Handles: Individual Card, Bulk Class Cards, Merit List, Transcript, Certificate
 */
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Result Cards & Reports';
$breadcrumbs = ['Reports' => 'index.php', 'Result Cards' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['reports.view']);

$pdo = db();

// ── Parameters ────────────────────────────────────────────────────────────────
$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$exam_id    = int_param('exam_id',    0, $_GET);
$class_id   = int_param('class_id',  0, $_GET);
$section_id = int_param('section_id',0, $_GET);
$student_id = int_param('student_id',0, $_GET);
$print_type = $_GET['print_type'] ?? '';   // card|bulk|merit|transcript|certificate
$sort_by    = $_GET['sort']       ?? 'merit'; // merit|roll

// ── Lookups ───────────────────────────────────────────────────────────────────
$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$exams    = $session_id
    ? $pdo->query("SELECT id,exam_name,exam_type FROM exams WHERE session_id=$session_id ORDER BY start_date,id")->fetchAll()
    : [];
$classes  = $exam_id
    ? $pdo->query("SELECT c.id,c.class_name FROM exam_class_map ecm JOIN classes c ON c.id=ecm.class_id WHERE ecm.exam_id=$exam_id ORDER BY c.display_order")->fetchAll()
    : [];
$sections = $class_id
    ? $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=:c AND status=1 ORDER BY section_name')
    : null;
if ($sections) { $sections->execute([':c'=>$class_id]); $sections=$sections->fetchAll(); } else $sections=[];

// ── School info ───────────────────────────────────────────────────────────────
$school  = setting('school_name','School');
$addr    = setting('school_address','');
$tel     = setting('school_phone','');
$cur     = setting('currency_symbol','৳');
$logo    = setting('school_logo','');
$hasLogo = $logo && file_exists(UPLOAD_LOGOS.$logo);
$logoSrc = $hasLogo ? '../../uploads/logos/'.htmlspecialchars($logo) : null;

// ── Core data loader ──────────────────────────────────────────────────────────
function load_results(PDO $pdo, int $exam_id, int $class_id, int $section_id, int $single_student=0): array {
    if (!$exam_id || !$class_id) return ['students'=>[],'subjects'=>[]];

    // Subjects
    $subStmt = $pdo->prepare(
        'SELECT esc.*,s.subject_name,s.has_mcq,s.has_practical
         FROM exam_subject_config esc JOIN subjects s ON s.id=esc.subject_id
         WHERE esc.exam_id=:e AND esc.class_id=:c ORDER BY s.subject_name'
    );
    $subStmt->execute([':e'=>$exam_id,':c'=>$class_id]);
    $subjects = $subStmt->fetchAll();

    // Students
    $where = 'se.class_id=:c AND se.status="active"';
    $params = [':e'=>$exam_id,':c'=>$class_id];
    if ($section_id) { $where.=' AND se.section_id=:sec'; $params[':sec']=$section_id; }
    if ($single_student) { $where.=' AND se.student_id=:sid'; $params[':sid']=$single_student; }

    $stuStmt = $pdo->prepare(
        "SELECT se.student_id,se.roll_number,sp.first_name,sp.last_name,sp.student_id_no,sp.photo,sp.dob,sp.gender,
                c.class_name,sec.section_name,gs.group_name
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         JOIN academic_sessions ass ON ass.id=se.session_id
         JOIN exams ex ON ex.session_id=ass.id AND ex.id=:e
         JOIN classes c ON c.id=se.class_id
         JOIN sections sec ON sec.id=se.section_id
         LEFT JOIN groups_stream gs ON gs.id=se.group_id
         WHERE $where ORDER BY se.roll_number"
    );
    $stuStmt->execute($params);
    $students = $stuStmt->fetchAll();

    // All marks
    $mkStmt = $pdo->prepare(
        'SELECT student_id,subject_id,marks_written,marks_mcq,marks_practical,is_absent
         FROM marks_entry WHERE exam_id=:e AND class_id=:c'
    );
    $mkStmt->execute([':e'=>$exam_id,':c'=>$class_id]);
    $marksMap = [];
    foreach ($mkStmt->fetchAll() as $m) $marksMap[$m['student_id']][$m['subject_id']] = $m;

    // Build result rows
    $results = [];
    foreach ($students as $stu) {
        $sid = $stu['student_id'];
        $row = ['stu'=>$stu,'subjects'=>[],'total'=>0,'full'=>0,'failed'=>0,'absent'=>0];
        foreach ($subjects as $sub) {
            $subId = $sub['subject_id'];
            $full  = $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'];
            $pass  = $sub['pass_marks_written']+$sub['pass_marks_mcq']+$sub['pass_marks_practical'];
            $m     = $marksMap[$sid][$subId] ?? null;
            $row['full'] += $full;
            if (!$m) {
                $row['subjects'][$subId] = ['w'=>null,'mcq'=>null,'prac'=>null,'total'=>null,'full'=>$full,'pass'=>$pass,'grade'=>null,'absent'=>false,'not_entered'=>true];
            } elseif ($m['is_absent']) {
                $row['subjects'][$subId] = ['w'=>null,'mcq'=>null,'prac'=>null,'total'=>0,'full'=>$full,'pass'=>$pass,'grade'=>['grade'=>'AB','gpa'=>0,'label'=>'Absent'],'absent'=>true,'not_entered'=>false];
                $row['absent']++; $row['failed']++;
            } else {
                $tot = ($m['marks_written']??0)+($m['marks_mcq']??0)+($m['marks_practical']??0);
                $pct = $full>0?($tot/$full)*100:0;
                $g   = calculate_grade($pct);
                $row['subjects'][$subId] = ['w'=>$m['marks_written'],'mcq'=>$m['marks_mcq'],'prac'=>$m['marks_practical'],'total'=>$tot,'full'=>$full,'pass'=>$pass,'grade'=>$g,'absent'=>false,'not_entered'=>false];
                $row['total'] += $tot;
                if ($tot < $pass) $row['failed']++;
            }
        }
        $pct          = $row['full']>0?round($row['total']/$row['full']*100,1):0;
        $row['pct']   = $pct;
        $row['grade'] = calculate_grade($pct);
        $row['pass']  = $row['failed']===0;
        $results[]    = $row;
    }

    // Merit sort + position
    usort($results, fn($a,$b) => $b['total']<=>$a['total']);
    $pos = 1;
    foreach ($results as &$r) { $r['position'] = $r['pass']?$pos++:'—'; }
    unset($r);

    return ['students'=>$results,'subjects'=>$subjects];
}

// ── Print mode — render standalone HTML ──────────────────────────────────────
if ($print_type && $exam_id && $class_id) {

    $data    = load_results($pdo,$exam_id,$class_id,$section_id,$student_id);
    $results = $data['students'];
    $subjects= $data['subjects'];

    if ($sort_by === 'roll') {
        usort($results, fn($a,$b) => $a['stu']['roll_number']<=>$b['stu']['roll_number']);
    }

    $examRow  = $pdo->prepare('SELECT exam_name,exam_type,start_date FROM exams WHERE id=?');
    $examRow->execute([$exam_id]);
    $examInfo = $examRow->fetch();
    $examName = $examInfo['exam_name'] ?? 'Exam';
    $examYear = $examInfo['start_date'] ? date('Y',strtotime($examInfo['start_date'])) : date('Y');

    $sessName = '';
    foreach($sessions as $s) if($s['id']==$session_id) { $sessName=$s['session_name']; break; }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars("$print_type — $examName") ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,'Helvetica Neue',sans-serif;font-size:9pt;color:#000;background:#fff;}
.no-print{padding:8px;background:#1a56db;color:#fff;display:flex;gap:8px;flex-wrap:wrap;}
.no-print button,.no-print a{padding:6px 16px;border:none;border-radius:5px;cursor:pointer;font-size:11px;font-weight:700;background:rgba(255,255,255,.2);color:#fff;text-decoration:none;}
.no-print button:hover,.no-print a:hover{background:rgba(255,255,255,.35);}

/* ── RESULT CARD ──────────────────────────────────── */
.rc-page{
  width:190mm;margin:0 auto;padding:6mm;
  page-break-after:always;
}
.rc-page:last-child{page-break-after:auto;}
.rc-head{
  display:flex;align-items:center;gap:4mm;
  border-bottom:2pt solid #000;padding-bottom:3mm;margin-bottom:3mm;
  text-align:center;
}
.rc-logo{width:18mm;height:18mm;flex-shrink:0;overflow:hidden;
  border:1.5pt solid #000;border-radius:2mm;
  display:flex;align-items:center;justify-content:center;}
.rc-logo img{width:100%;height:100%;object-fit:contain;}
.rc-logo .ini{font-size:9pt;font-weight:900;}
.rc-school{flex:1;}
.rc-school h1{font-size:13pt;font-weight:900;text-transform:uppercase;letter-spacing:.04em;}
.rc-school p{font-size:7.5pt;color:#444;margin-top:1px;}
.rc-badge{background:#1a56db;color:#fff;font-size:7pt;font-weight:900;
  text-transform:uppercase;letter-spacing:.1em;padding:1.5mm 4mm;
  border-radius:1mm;display:inline-block;margin-top:2mm;}
.rc-type-lbl{font-size:9pt;font-weight:900;text-transform:uppercase;letter-spacing:.06em;
  border-top:1pt solid #000;margin-top:2mm;padding-top:1.5mm;}

/* Student info */
.stu-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;
  border:1pt solid #000;margin-bottom:3mm;}
.stu-cell{padding:1.5mm 3mm;border-right:1pt solid #000;border-bottom:1pt solid #000;}
.stu-cell:last-child,.stu-cell:nth-child(3n){border-right:none;}
.stu-cell .k{font-size:6.5pt;color:#555;display:block;}
.stu-cell .v{font-size:8.5pt;font-weight:700;}
.stu-cell.wide{grid-column:span 2;}
.stu-cell.photo{grid-row:span 2;display:flex;align-items:center;justify-content:center;
  background:#f5f5f5;min-height:22mm;}
.stu-cell.photo img{width:18mm;height:22mm;object-fit:cover;}
.stu-cell.photo .ph-init{font-size:14pt;font-weight:900;color:#888;}

/* Marks table */
.mk-table{width:100%;border-collapse:collapse;margin-bottom:3mm;font-size:8pt;}
.mk-table th,.mk-table td{border:1pt solid #000;padding:1.5mm 2mm;text-align:center;}
.mk-table th{background:#f0f0f0;font-size:7pt;font-weight:900;text-transform:uppercase;}
.mk-table td.subname{text-align:left;font-weight:600;}
.mk-table .pass{background:#f0fff4;}
.mk-table .fail{background:#fff5f5;font-weight:700;color:#c00;}
.mk-table .ab{background:#fefce8;font-style:italic;}
.mk-table .total-row{background:#e8f0fe;font-weight:900;font-size:9pt;}

/* Summary bar */
.rc-summary{display:flex;gap:3mm;margin-bottom:4mm;}
.rc-sum-box{flex:1;border:1.5pt solid #000;border-radius:2mm;padding:2mm 3mm;text-align:center;}
.rc-sum-box .sv{font-size:13pt;font-weight:900;line-height:1;}
.rc-sum-box .sl{font-size:6.5pt;text-transform:uppercase;letter-spacing:.07em;color:#555;margin-top:1mm;}
.rc-sum-box.pass-box{border-color:#059669;background:#f0fff4;}
.rc-sum-box.pass-box .sv{color:#059669;}
.rc-sum-box.fail-box{border-color:#dc2626;background:#fff5f5;}
.rc-sum-box.fail-box .sv{color:#dc2626;}

/* Signature strip */
.sig-strip{display:flex;justify-content:space-between;gap:8mm;margin-top:4mm;
  border-top:1pt solid #000;padding-top:2.5mm;}
.sig-blk{flex:1;text-align:center;}
.sig-line{border-top:.75pt solid #000;margin-bottom:1mm;height:7mm;}
.sig-lbl{font-size:6.5pt;color:#444;}

/* ── MERIT LIST ──────────────────────────────────── */
.ml-page{padding:6mm 8mm;page-break-after:always;}
.ml-page:last-child{page-break-after:auto;}
.ml-head{text-align:center;border-bottom:1.5pt solid #000;padding-bottom:3mm;margin-bottom:3mm;}
.ml-head h1{font-size:12pt;font-weight:900;text-transform:uppercase;}
.ml-head h2{font-size:9pt;font-weight:400;margin-top:1mm;}
.ml-head h3{font-size:11pt;font-weight:700;margin-top:2mm;text-transform:uppercase;
  letter-spacing:.05em;border-top:1pt solid #000;padding-top:2mm;margin-top:2mm;}
.ml-table{width:100%;border-collapse:collapse;font-size:7.5pt;}
.ml-table th,.ml-table td{border:1pt solid #000;padding:1.5mm 2mm;}
.ml-table th{background:#1e293b;color:#fff;font-size:7pt;font-weight:900;text-transform:uppercase;text-align:center;}
.ml-table td{text-align:center;}
.ml-table td.name{text-align:left;font-weight:700;}
.ml-table .pass-row{background:#f0fff4;}
.ml-table .fail-row{background:#fff5f5;}
.ml-table .top3{background:#fffbeb;}
.ml-summary{display:flex;justify-content:space-between;font-size:7pt;
  margin-top:2mm;border-top:1pt solid #000;padding-top:1.5mm;}

/* ── TRANSCRIPT ──────────────────────────────────── */
.tr-page{width:190mm;margin:0 auto;padding:6mm;page-break-after:always;}
.tr-head{text-align:center;margin-bottom:4mm;border-bottom:2pt solid #000;padding-bottom:3mm;}
.tr-head h1{font-size:12pt;font-weight:900;text-transform:uppercase;}
.tr-head .tr-sub{font-size:8pt;color:#555;}
.tr-stu-banner{background:#1e293b;color:#fff;padding:2.5mm 4mm;margin-bottom:3mm;
  display:flex;justify-content:space-between;align-items:center;}
.tr-stu-banner .sn{font-size:11pt;font-weight:900;}
.tr-stu-banner .sm{font-size:7.5pt;opacity:.8;}
.tr-section{margin-bottom:4mm;}
.tr-exam-hdr{background:#f0f4ff;border:1pt solid #c7d2fe;border-radius:1.5mm;
  padding:1.5mm 3mm;font-size:8pt;font-weight:700;margin-bottom:1.5mm;
  display:flex;justify-content:space-between;}
.tr-mini-table{width:100%;border-collapse:collapse;font-size:7.5pt;}
.tr-mini-table th,.tr-mini-table td{border:.75pt solid #ccc;padding:1.2mm 2mm;}
.tr-mini-table th{background:#f8fafc;font-size:6.5pt;font-weight:900;text-transform:uppercase;text-align:center;}
.tr-mini-table td{text-align:center;}
.tr-mini-table td.sn{text-align:left;}
.tr-mini-table .gr-pass{color:#059669;font-weight:700;}
.tr-mini-table .gr-fail{color:#dc2626;font-weight:700;}
.tr-year-summary{background:#f8fafc;border:1.5pt solid #000;border-radius:2mm;
  padding:3mm 4mm;display:flex;gap:6mm;flex-wrap:wrap;margin-top:3mm;}
.tr-year-summary .ts{text-align:center;}
.tr-year-summary .tv{font-size:12pt;font-weight:900;}
.tr-year-summary .tl{font-size:6pt;text-transform:uppercase;color:#555;}

/* ── CERTIFICATE ─────────────────────────────────── */
.cert-page{width:240mm;min-height:170mm;margin:0 auto;padding:12mm;
  border:4pt double #000;position:relative;page-break-after:always;}
.cert-page:last-child{page-break-after:auto;}
.cert-head{text-align:center;margin-bottom:5mm;}
.cert-school{font-size:14pt;font-weight:900;text-transform:uppercase;letter-spacing:.05em;}
.cert-addr{font-size:8pt;color:#444;margin-top:1mm;}
.cert-title{font-size:11pt;font-weight:900;text-transform:uppercase;letter-spacing:.15em;
  border-top:1.5pt solid #000;border-bottom:1.5pt solid #000;
  padding:3mm 0;margin:4mm 0;text-align:center;}
.cert-body{font-size:9.5pt;line-height:1.9;text-align:justify;}
.cert-body .hl{font-weight:900;font-size:10pt;text-decoration:underline;}
.cert-marks-box{border:1.5pt solid #000;padding:3mm 5mm;margin:4mm 0;display:flex;
  justify-content:space-around;flex-wrap:wrap;gap:3mm;}
.cert-stat{text-align:center;}
.cert-stat .cv{font-size:14pt;font-weight:900;}
.cert-stat .cl{font-size:6.5pt;text-transform:uppercase;letter-spacing:.07em;color:#555;}
.cert-sigs{display:flex;justify-content:space-between;margin-top:10mm;}
.cert-sig{text-align:center;}
.cert-sig .csl{border-top:1pt solid #000;padding-top:1.5mm;min-width:45mm;font-size:7pt;}
.cert-stamp{width:22mm;height:22mm;border:1.5pt dashed #888;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:6pt;color:#888;text-align:center;
  position:absolute;bottom:15mm;right:15mm;}

@media print{
  .no-print{display:none!important;}
  @page{size:A4 portrait;margin:5mm;}
  body{background:#fff;}
}
</style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print">
  <button onclick="window.print()">🖨 Print All</button>
  <a href="resultcard.php?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>">← Back</a>
  <?php if ($print_type === 'bulk'): ?>
  <a href="?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print_type=merit&sort=merit">Merit List</a>
  <a href="?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print_type=bulk&sort=roll">Sort by Roll</a>
  <a href="?session_id=<?= $session_id ?>&exam_id=<?= $exam_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>&print_type=bulk&sort=merit">Sort by Merit</a>
  <?php endif; ?>
  <span style="margin-left:auto;opacity:.7;font-size:10px;"><?= htmlspecialchars($examName) ?> — <?= count($results) ?> students</span>
</div>

<?php
// ──────────────────────────────────────────────────────────────────────────────
// RENDER: Individual / Bulk Result Cards
// ──────────────────────────────────────────────────────────────────────────────
if (in_array($print_type, ['card','bulk'])):
    foreach ($results as $r):
        $stu = $r['stu'];
        $pass = $r['pass'];
        $photoPath = UPLOAD_PHOTOS . ($stu['photo'] ?? '');
        $hasPhoto  = !empty($stu['photo']) && file_exists($photoPath);
?>
<div class="rc-page">

  <!-- Header -->
  <div class="rc-head">
    <div class="rc-logo">
      <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>">
      <?php else: ?><div class="ini"><?= htmlspecialchars(substr($school,0,3)) ?></div><?php endif; ?>
    </div>
    <div class="rc-school">
      <h1><?= htmlspecialchars($school) ?></h1>
      <?php if ($addr): ?><p><?= htmlspecialchars($addr) ?><?= $tel?' | Tel: '.htmlspecialchars($tel):'' ?></p><?php endif; ?>
      <div class="rc-type-lbl">Student Report Card / Progress Report</div>
      <div class="rc-badge"><?= htmlspecialchars($examName) ?></div>
    </div>
  </div>

  <!-- Student details -->
  <div class="stu-grid">
    <div class="stu-cell photo">
      <?php if ($hasPhoto): ?>
        <img src="../../uploads/photos/<?= htmlspecialchars($stu['photo']) ?>">
      <?php else: ?>
        <div class="ph-init"><?= strtoupper(substr($stu['first_name'],0,1)) ?></div>
      <?php endif; ?>
    </div>
    <div class="stu-cell wide"><span class="k">Student Name</span><span class="v" style="font-size:10pt;"><?= htmlspecialchars($stu['first_name'].' '.$stu['last_name']) ?></span></div>
    <div class="stu-cell"><span class="k">Student ID</span><span class="v"><?= htmlspecialchars($stu['student_id_no']??'—') ?></span></div>
    <div class="stu-cell"><span class="k">Class</span><span class="v"><?= htmlspecialchars($stu['class_name']) ?></span></div>
    <div class="stu-cell"><span class="k">Section</span><span class="v"><?= htmlspecialchars($stu['section_name']) ?><?= $stu['group_name']?' / '.htmlspecialchars($stu['group_name']):'' ?></span></div>
    <div class="stu-cell"><span class="k">Roll No.</span><span class="v"><?= htmlspecialchars($stu['roll_number']) ?></span></div>
    <div class="stu-cell"><span class="k">Session</span><span class="v"><?= htmlspecialchars($sessName) ?></span></div>
    <div class="stu-cell"><span class="k">Exam</span><span class="v"><?= htmlspecialchars($examName) ?></span></div>
  </div>

  <!-- Marks table -->
  <table class="mk-table">
    <thead>
      <tr>
        <th style="width:6mm;">#</th>
        <th style="text-align:left;">Subject</th>
        <th>Full<br>Marks</th>
        <th>Pass<br>Marks</th>
        <th>Written</th>
        <th>MCQ</th>
        <th>Practical</th>
        <th>Total</th>
        <th>Grade</th>
        <th>GPA</th>
        <th>Result</th>
      </tr>
    </thead>
    <tbody>
      <?php $subjTotal=0; $subjFull=0; foreach ($subjects as $n => $sub):
        $sm    = $r['subjects'][$sub['subject_id']] ?? null;
        $grade = $sm['grade'] ?? null;
        $rowCls = !$sm||$sm['not_entered']?'':($sm['absent']?'ab':($sm['total']>=$sm['pass']?'pass':'fail'));
        $subjFull += $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'];
        if ($sm && !$sm['not_entered'] && !$sm['absent']) $subjTotal += $sm['total'];
      ?>
      <tr class="<?= $rowCls ?>">
        <td><?= $n+1 ?></td>
        <td class="subname"><?= htmlspecialchars($sub['subject_name']) ?></td>
        <td><?= $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'] ?></td>
        <td><?= $sub['pass_marks_written']+$sub['pass_marks_mcq']+$sub['pass_marks_practical'] ?></td>
        <td><?= $sm&&!$sm['not_entered']?($sm['absent']?'AB':($sm['w']??'—')):'—' ?></td>
        <td><?= $sub['full_marks_mcq']>0?($sm&&!$sm['absent']?($sm['mcq']??'—'):'—'):'—' ?></td>
        <td><?= $sub['full_marks_practical']>0?($sm&&!$sm['absent']?($sm['prac']??'—'):'—'):'—' ?></td>
        <td><strong><?= $sm&&!$sm['not_entered']?($sm['absent']?'AB':$sm['total']):'—' ?></strong></td>
        <td><?= $grade?htmlspecialchars($grade['grade']):'—' ?></td>
        <td><?= $grade?number_format($grade['gpa'],2):'—' ?></td>
        <td><strong><?= !$sm||$sm['not_entered']?'—':($sm['absent']?'Absent':($sm['total']>=$sm['pass']?'Pass':'Fail')) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="2" style="text-align:right;font-size:8pt;">TOTAL</td>
        <td><?= $subjFull ?></td>
        <td>—</td>
        <td colspan="3">—</td>
        <td><?= $r['total'] ?></td>
        <td><?= htmlspecialchars($r['grade']['grade']) ?></td>
        <td><?= number_format($r['grade']['gpa'],2) ?></td>
        <td><?= $pass?'<strong style="color:#059669">PASS</strong>':'<strong style="color:#dc2626">FAIL</strong>' ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- Summary boxes -->
  <div class="rc-summary">
    <div class="rc-sum-box">
      <div class="sv"><?= $r['total'] ?> / <?= $r['full'] ?></div>
      <div class="sl">Total Marks</div>
    </div>
    <div class="rc-sum-box">
      <div class="sv"><?= number_format($r['pct'],1) ?>%</div>
      <div class="sl">Percentage</div>
    </div>
    <div class="rc-sum-box">
      <div class="sv"><?= htmlspecialchars($r['grade']['grade']) ?></div>
      <div class="sl">Grade</div>
    </div>
    <div class="rc-sum-box">
      <div class="sv"><?= number_format($r['grade']['gpa'],2) ?></div>
      <div class="sl">GPA</div>
    </div>
    <div class="rc-sum-box">
      <div class="sv"><?= $r['position'] ?></div>
      <div class="sl">Merit Position</div>
    </div>
    <div class="rc-sum-box <?= $pass?'pass-box':'fail-box' ?>">
      <div class="sv"><?= $pass?'PASS':'FAIL' ?></div>
      <div class="sl">Result</div>
    </div>
  </div>

  <!-- Remarks/Grade scale -->
  <div style="font-size:7pt;color:#555;border:1pt solid #ddd;padding:2mm 3mm;margin-bottom:3mm;border-radius:1mm;">
    <strong>Grade Scale:</strong>&nbsp;
    A+(80-100, GPA 5.00) &nbsp; A(70-79, 4.00) &nbsp; A-(60-69, 3.50) &nbsp; B(50-59, 3.00) &nbsp; C(40-49, 2.00) &nbsp; D(33-39, 1.00) &nbsp; F(0-32, 0.00)
    &nbsp;&nbsp;&nbsp; <strong>Remarks:</strong> <?= htmlspecialchars($r['grade']['label'] ?? '') ?>
  </div>

  <!-- Signatures -->
  <div class="sig-strip">
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Class Teacher</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Head of Department</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Exam Controller</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Principal / Headmaster</div></div>
  </div>

</div><!-- /rc-page -->
<?php endforeach; ?>

<?php
// ──────────────────────────────────────────────────────────────────────────────
// RENDER: Merit List
// ──────────────────────────────────────────────────────────────────────────────
elseif ($print_type === 'merit'):
    $className   = $results[0]['stu']['class_name']  ?? '';
    $sectionName = $results[0]['stu']['section_name'] ?? '';
    $total    = count($results);
    $passed   = count(array_filter($results,fn($r)=>$r['pass']));
    $failed   = $total - $passed;
    $passRate = $total>0?round($passed/$total*100):0;
?>
<div class="ml-page">
  <div class="ml-head">
    <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" style="height:14mm;margin-bottom:2mm;"><br><?php endif; ?>
    <h1><?= htmlspecialchars($school) ?></h1>
    <?php if ($addr): ?><p style="font-size:7.5pt;color:#555;"><?= htmlspecialchars($addr) ?></p><?php endif; ?>
    <h3>Merit List — <?= htmlspecialchars($examName) ?> (<?= htmlspecialchars($sessName) ?>)</h3>
    <div style="font-size:8pt;margin-top:1mm;">
      Class: <strong><?= htmlspecialchars($className) ?></strong>
      <?php if ($sectionName): ?> &nbsp;|&nbsp; Section: <strong><?= htmlspecialchars($sectionName) ?></strong><?php endif; ?>
    </div>
  </div>

  <table class="ml-table">
    <thead>
      <tr>
        <th style="width:8mm;">Rank</th>
        <th style="width:10mm;">Roll</th>
        <th style="text-align:left;">Student Name</th>
        <?php foreach ($subjects as $sub): ?>
        <th style="max-width:30px;font-size:6pt;"><?= htmlspecialchars(mb_strimwidth($sub['subject_name'],0,12,'…')) ?></th>
        <?php endforeach; ?>
        <th>Total</th>
        <th>%</th>
        <th>Grade</th>
        <th>GPA</th>
        <th>Result</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $r):
      $cls = !$r['pass']?'fail-row':($r['position']<=3?'top3':'pass-row');
    ?>
    <tr class="<?= $cls ?>">
      <td><?= $r['position'] ?></td>
      <td><?= $r['stu']['roll_number'] ?></td>
      <td class="name"><?= htmlspecialchars($r['stu']['first_name'].' '.$r['stu']['last_name']) ?></td>
      <?php foreach ($subjects as $sub):
        $sm = $r['subjects'][$sub['subject_id']] ?? null;
        $v  = !$sm||$sm['not_entered']?'—':($sm['absent']?'AB':$sm['total']);
        $fc = $sm&&!$sm['not_entered']&&!$sm['absent']&&$sm['total']<$sm['pass']?'color:#c00;font-weight:700':'';
      ?>
      <td style="<?= $fc ?>"><?= $v ?></td>
      <?php endforeach; ?>
      <td><strong><?= $r['total'] ?></strong></td>
      <td><?= number_format($r['pct'],1) ?>%</td>
      <td><strong><?= htmlspecialchars($r['grade']['grade']) ?></strong></td>
      <td><?= number_format($r['grade']['gpa'],2) ?></td>
      <td><?= $r['pass']?'<strong style="color:#059669">Pass</strong>':'<strong style="color:#dc2626">Fail</strong>' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="ml-summary">
    <span>Total: <strong><?= $total ?></strong> &nbsp;|&nbsp; Passed: <strong style="color:#059669"><?= $passed ?></strong> &nbsp;|&nbsp; Failed: <strong style="color:#dc2626"><?= $failed ?></strong> &nbsp;|&nbsp; Pass Rate: <strong><?= $passRate ?>%</strong></span>
    <span style="font-style:italic;color:#777;">Published: <?= date('d M Y') ?> &nbsp;|&nbsp; <?= htmlspecialchars($school) ?></span>
  </div>

  <div class="sig-strip" style="margin-top:8mm;">
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Exam Controller</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Principal / Headmaster</div></div>
  </div>
</div>

<?php
// ──────────────────────────────────────────────────────────────────────────────
// RENDER: Transcript (per student — multi-exam if student_id given; else class)
// ──────────────────────────────────────────────────────────────────────────────
elseif ($print_type === 'transcript'):
    // Load all exams for session
    $allExams = $pdo->query("SELECT id,exam_name,exam_type,start_date FROM exams WHERE session_id=$session_id ORDER BY start_date,id")->fetchAll();

    foreach ($results as $r):
        $stu = $r['stu'];
?>
<div class="tr-page">
  <div class="tr-head">
    <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" style="height:14mm;margin-bottom:2mm;"><br><?php endif; ?>
    <h1><?= htmlspecialchars($school) ?></h1>
    <?php if ($addr): ?><div class="tr-sub"><?= htmlspecialchars($addr) ?></div><?php endif; ?>
    <div style="font-size:10pt;font-weight:900;text-transform:uppercase;letter-spacing:.07em;
         border-top:1.5pt solid #000;border-bottom:1.5pt solid #000;padding:2.5mm 0;margin-top:2.5mm;">
      Academic Transcript — Session <?= htmlspecialchars($sessName) ?>
    </div>
  </div>

  <div class="tr-stu-banner">
    <div>
      <div class="sn"><?= htmlspecialchars($stu['first_name'].' '.$stu['last_name']) ?></div>
      <div class="sm">ID: <?= htmlspecialchars($stu['student_id_no']??'—') ?> &nbsp;|&nbsp; Class: <?= htmlspecialchars($stu['class_name']) ?> — <?= htmlspecialchars($stu['section_name']) ?> &nbsp;|&nbsp; Roll: <?= htmlspecialchars($stu['roll_number']) ?></div>
    </div>
    <div style="text-align:right;">
      <div class="sm">Gender: <?= ucfirst($stu['gender']??'—') ?></div>
    </div>
  </div>

  <!-- Each exam's results -->
  <?php
  $yearTotal = 0; $yearFull = 0; $yearExamCount = 0;
  foreach ($allExams as $ex):
      $exData    = load_results($pdo, $ex['id'], $class_id, $section_id, $stu['student_id']);
      $exResults = $exData['students'];
      $exSubjs   = $exData['subjects'];
      if (empty($exResults)) continue;
      $exR = $exResults[0];
      $yearTotal += $exR['total']; $yearFull += $exR['full']; $yearExamCount++;
  ?>
  <div class="tr-section">
    <div class="tr-exam-hdr">
      <span><?= htmlspecialchars($ex['exam_name']) ?></span>
      <span style="font-size:7.5pt;font-weight:400;">
        <?= $ex['start_date']?fmt_date($ex['start_date'],'d M Y'):'' ?> &nbsp;|&nbsp;
        Total: <strong><?= $exR['total'] ?>/<?= $exR['full'] ?></strong> &nbsp;
        <?= $exR['pct'] ?>% &nbsp;
        Grade: <strong><?= htmlspecialchars($exR['grade']['grade']) ?></strong> &nbsp;
        <?= $exR['pass']?'<span style="color:#059669">PASS</span>':'<span style="color:#c00">FAIL</span>' ?>
      </span>
    </div>
    <table class="tr-mini-table">
      <thead>
        <tr>
          <th style="text-align:left;width:40%;">Subject</th>
          <th>Full</th><th>Pass</th><th>Total</th><th>Grade</th><th>GPA</th><th>Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($exSubjs as $sub):
          $sm = $exR['subjects'][$sub['subject_id']] ?? null;
          $full = $sub['full_marks_written']+$sub['full_marks_mcq']+$sub['full_marks_practical'];
          $pass_m = $sub['pass_marks_written']+$sub['pass_marks_mcq']+$sub['pass_marks_practical'];
          $grade = $sm['grade'] ?? null;
          $pCls  = $sm&&!$sm['not_entered']?($sm['absent']?'':'gr-'.($sm['total']>=$sm['pass']?'pass':'fail')):'';
        ?>
        <tr>
          <td class="sn"><?= htmlspecialchars($sub['subject_name']) ?></td>
          <td><?= $full ?></td><td><?= $pass_m ?></td>
          <td class="<?= $pCls ?>"><strong><?= $sm&&!$sm['not_entered']?($sm['absent']?'AB':$sm['total']):'—' ?></strong></td>
          <td><?= $grade?htmlspecialchars($grade['grade']):'—' ?></td>
          <td><?= $grade?number_format($grade['gpa'],2):'—' ?></td>
          <td class="<?= $pCls ?>"><?= !$sm||$sm['not_entered']?'—':($sm['absent']?'AB':($sm['total']>=$sm['pass']?'P':'F')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>

  <!-- Yearly summary -->
  <?php if ($yearExamCount > 0): ?>
  <div class="tr-year-summary">
    <div class="ts"><div class="tv"><?= $yearExamCount ?></div><div class="tl">Exams Taken</div></div>
    <div class="ts"><div class="tv"><?= $yearTotal ?> / <?= $yearFull ?></div><div class="tl">Total Score</div></div>
    <div class="ts"><div class="tv"><?= $yearFull>0?round($yearTotal/$yearFull*100,1):0 ?>%</div><div class="tl">Overall %</div></div>
    <div class="ts"><div class="tv"><?= htmlspecialchars(calculate_grade($yearFull>0?$yearTotal/$yearFull*100:0)['grade']) ?></div><div class="tl">Overall Grade</div></div>
    <div class="ts"><div class="tv"><?= number_format(calculate_grade($yearFull>0?$yearTotal/$yearFull*100:0)['gpa'],2) ?></div><div class="tl">Overall GPA</div></div>
  </div>
  <?php endif; ?>

  <div class="sig-strip" style="margin-top:6mm;">
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Class Teacher</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Exam Controller</div></div>
    <div class="sig-blk"><div class="sig-line"></div><div class="sig-lbl">Principal / Headmaster</div></div>
  </div>
  <div style="font-size:6pt;color:#777;text-align:center;margin-top:2mm;font-style:italic;">
    This is an official academic transcript issued by <?= htmlspecialchars($school) ?>. Issued: <?= date('d M Y') ?>.
  </div>
</div>
<?php endforeach; ?>

<?php
// ──────────────────────────────────────────────────────────────────────────────
// RENDER: Achievement Certificate
// ──────────────────────────────────────────────────────────────────────────────
elseif ($print_type === 'certificate'):
    foreach ($results as $r):
        $stu = $r['stu'];
        if (!$r['pass']) continue; // Only for passing students
?>
<div class="cert-page">
  <div class="cert-head">
    <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" style="height:20mm;margin-bottom:3mm;"><br><?php endif; ?>
    <div class="cert-school"><?= htmlspecialchars($school) ?></div>
    <?php if ($addr): ?><div class="cert-addr"><?= htmlspecialchars($addr) ?></div><?php endif; ?>
  </div>

  <div class="cert-title">Certificate of Achievement</div>

  <div class="cert-body">
    <p>This is to certify that</p>
    <br>
    <p style="text-align:center;font-size:14pt;margin:4mm 0;">
      <span class="hl"><?= htmlspecialchars($stu['first_name'].' '.$stu['last_name']) ?></span>
    </p>
    <p>
      Student ID: <strong><?= htmlspecialchars($stu['student_id_no']??'—') ?></strong>,
      Class: <strong><?= htmlspecialchars($stu['class_name']) ?></strong>,
      Section: <strong><?= htmlspecialchars($stu['section_name']) ?></strong>,
      Roll No: <strong><?= htmlspecialchars($stu['roll_number']) ?></strong>
    </p>
    <br>
    <p>
      has successfully completed the <strong><?= htmlspecialchars($examName) ?></strong>
      for the academic session <strong><?= htmlspecialchars($sessName) ?></strong>
      with the following results:
    </p>
  </div>

  <div class="cert-marks-box">
    <div class="cert-stat"><div class="cv"><?= $r['total'] ?> / <?= $r['full'] ?></div><div class="cl">Total Marks</div></div>
    <div class="cert-stat"><div class="cv"><?= number_format($r['pct'],1) ?>%</div><div class="cl">Percentage</div></div>
    <div class="cert-stat"><div class="cv"><?= htmlspecialchars($r['grade']['grade']) ?></div><div class="cl">Grade</div></div>
    <div class="cert-stat"><div class="cv"><?= number_format($r['grade']['gpa'],2) ?></div><div class="cl">GPA</div></div>
    <div class="cert-stat"><div class="cv"><?= $r['position'] ?></div><div class="cl">Merit Rank</div></div>
  </div>

  <div class="cert-body" style="margin-top:4mm;">
    <p>
      We congratulate <strong><?= htmlspecialchars($stu['first_name']) ?></strong> on this achievement
      and wish continued success in future academic endeavours.
    </p>
    <br>
    <p style="font-size:8pt;color:#555;">Issued on: <?= date('d F Y') ?></p>
  </div>

  <div class="cert-sigs">
    <div class="cert-sig"><div class="csl">Class Teacher</div></div>
    <div class="cert-sig"><div class="csl">Exam Controller</div></div>
    <div class="cert-sig"><div class="csl">Principal / Headmaster</div></div>
  </div>

  <div class="cert-stamp">SCHOOL<br>SEAL</div>
</div>
<?php endforeach; ?>
<?php endif; // end print_type switch ?>

</body></html>
<?php
    echo ob_get_clean();
    exit;
}

// ── Normal page (selector UI) ─────────────────────────────────────────────────
require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title">
  <i class="bi bi-journal-richtext me-2 text-primary"></i>Result Cards & Report Printing
</h1>

<!-- Filter card -->
<div class="card mb-4">
  <div class="card-header py-3 px-4"><span class="card-title">Select Exam & Class</span></div>
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Session</label>
        <select name="session_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($exams)): ?>
      <div class="col-md-3">
        <label class="form-label">Exam</label>
        <select name="exam_id" class="form-select" onchange="this.form.submit()">
          <option value="0">— Select Exam —</option>
          <?php foreach ($exams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $exam_id==$ex['id']?'selected':'' ?>><?= e($ex['exam_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($exam_id && !empty($classes)): ?>
      <div class="col-md-2">
        <label class="form-label">Class</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <option value="0">— Select —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($class_id && !empty($sections)): ?>
      <div class="col-md-2">
        <label class="form-label">Section</label>
        <select name="section_id" class="form-select" onchange="this.form.submit()">
          <option value="0">All Sections</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $section_id==$s['id']?'selected':'' ?>><?= e($s['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($exam_id && $class_id): ?>

<!-- Print options grid -->
<div class="row g-3 mb-4">

  <?php
  $baseUrl = "resultcard.php?session_id=$session_id&exam_id=$exam_id&class_id=$class_id&section_id=$section_id";

  $printOptions = [
    [
      'icon'  => 'person-vcard-fill',
      'color' => 'primary',
      'title' => 'All Student Cards',
      'desc'  => 'One result card per student — all students in this class/section. Merit order.',
      'url'   => "$baseUrl&print_type=bulk&sort=merit",
      'url2'  => ["Roll Order" => "$baseUrl&print_type=bulk&sort=roll"],
    ],
    [
      'icon'  => 'trophy-fill',
      'color' => 'success',
      'title' => 'Merit List',
      'desc'  => 'Tabular rank sheet with all subjects, sorted by total marks.',
      'url'   => "$baseUrl&print_type=merit&sort=merit",
      'url2'  => [],
    ],
    [
      'icon'  => 'file-earmark-text-fill',
      'color' => 'info',
      'title' => 'Class Transcript',
      'desc'  => 'Full academic transcript per student — all exams in the session in one document.',
      'url'   => "$baseUrl&print_type=transcript&sort=roll",
      'url2'  => [],
    ],
    [
      'icon'  => 'patch-check-fill',
      'color' => 'warning',
      'title' => 'Achievement Certificates',
      'desc'  => 'Formal certificate for passing students. One per page.',
      'url'   => "$baseUrl&print_type=certificate",
      'url2'  => [],
    ],
  ];
  ?>

  <?php foreach ($printOptions as $opt): ?>
  <div class="col-md-6">
    <div class="card h-100 border-<?= $opt['color'] ?>" style="border-width:2px!important;">
      <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;background:var(--bs-<?= $opt['color'] ?>);opacity:.15;"></div>
          <i class="bi bi-<?= $opt['icon'] ?> text-<?= $opt['color'] ?>" style="font-size:2rem;position:absolute;margin-left:7px;margin-top:7px;"></i>
          <div style="padding-left:55px;">
            <h5 class="fw-700 mb-1"><?= e($opt['title']) ?></h5>
            <p class="text-muted small mb-0"><?= e($opt['desc']) ?></p>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="<?= $opt['url'] ?>" target="_blank" class="btn btn-<?= $opt['color'] ?> btn-sm">
            <i class="bi bi-printer me-1"></i>Print
          </a>
          <?php foreach ($opt['url2'] as $label => $url): ?>
          <a href="<?= $url ?>" target="_blank" class="btn btn-outline-<?= $opt['color'] ?> btn-sm"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

</div>

<!-- Individual student card picker -->
<div class="card">
  <div class="card-header py-3 px-4">
    <span class="card-title"><i class="bi bi-person-fill me-2"></i>Individual Student Card</span>
  </div>
  <div class="card-body">
    <?php
    $stuList = $pdo->prepare(
        "SELECT se.student_id,se.roll_number,sp.first_name,sp.last_name,sp.student_id_no
         FROM student_enrollments se
         JOIN student_profiles sp ON sp.user_id=se.student_id
         JOIN academic_sessions ass ON ass.id=se.session_id
         JOIN exams ex ON ex.session_id=ass.id AND ex.id=:eid
         WHERE se.class_id=:cls".($section_id?" AND se.section_id=$section_id":'')." AND se.status='active'
         ORDER BY se.roll_number"
    );
    $stuList->execute([':eid'=>$exam_id,':cls'=>$class_id]);
    $stuList = $stuList->fetchAll();
    ?>
    <div class="row g-2">
      <?php foreach ($stuList as $stu): ?>
      <div class="col-md-3 col-sm-4 col-6">
        <a href="<?= $baseUrl ?>&student_id=<?= $stu['student_id'] ?>&print_type=card"
           target="_blank"
           class="btn btn-outline-secondary btn-sm w-100 text-start d-flex align-items-center gap-2 py-2">
          <span class="badge bg-secondary" style="min-width:28px;"><?= $stu['roll_number'] ?></span>
          <span class="text-truncate"><?= e($stu['first_name'].' '.$stu['last_name']) ?></span>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (empty($stuList)): ?>
      <div class="col-12 text-muted small">No students found for this class/section in the selected exam.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <i class="bi bi-journal-richtext"></i>
    <p>Select a <strong>session → exam → class</strong> above to access all print options.</p>
  </div>
</div></div>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
