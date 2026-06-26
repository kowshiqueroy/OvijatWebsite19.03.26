<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Master Fee Collection';
$breadcrumbs = ['Finance' => 'ledger.php', 'Master Collection' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['fees.collect']);

$pdo = db();

// ── AJAX: Quick-pay a single ledger entry ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_pay') {
    ob_start();
    header('Content-Type: application/json');
    try {
        csrf_check();
        $ledger_id  = int_param('ledger_id',  0, $_POST);
        $amount     = round((float)($_POST['amount'] ?? 0), 2);
        $student_id = int_param('student_id', 0, $_POST);
        $method     = $_POST['method']     ?? 'cash';
        $acc_id     = int_param('account_id', 0, $_POST);

        if (!$ledger_id || $amount <= 0 || !$student_id || !$acc_id) {
            ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Invalid parameters.']); exit;
        }

        $allowPartial  = setting('allow_partial_payment','no') === 'yes';
        $minPct        = (int)setting('partial_min_percent','100');
        $needsApproval = setting('partial_requires_approval','yes') === 'yes';

        $led = $pdo->prepare('SELECT * FROM fee_ledgers WHERE id=?');
        $led->execute([$ledger_id]);
        $led = $led->fetch();
        if (!$led) { ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Ledger not found.']); exit; }

        $balance = round($led['amount_due'] - $led['amount_paid'] - $led['waiver_amount'], 2);
        if ($amount > $balance + 0.01) {
            ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Amount exceeds balance of '.money($balance)]); exit;
        }

        $isPartial = $amount < $balance - 0.01;
        if ($isPartial && !$allowPartial) {
            ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Partial payment is disabled. Full amount required: '.money($balance)]); exit;
        }
        if ($isPartial && $minPct < 100) {
            $minAmt = round($balance * $minPct / 100, 2);
            if ($amount < $minAmt - 0.01) {
                ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Minimum payment is '.money($minAmt)." ({$minPct}% of balance)."]); exit;
            }
        }

        $approvalStatus = ($isPartial && $needsApproval) ? 'pending' : 'approved';

        $pdo->beginTransaction();
        $rcpNo = next_receipt_number();

        $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,CURDATE(),?,?,?,?,?)')
            ->execute([$ledger_id,$student_id,$amount,$method,$rcpNo,current_user_id(),$acc_id,$approvalStatus]);

        if ($approvalStatus === 'approved') {
            $pdo->prepare('UPDATE fee_ledgers SET amount_paid=amount_paid+:a, status=CASE WHEN amount_paid+:b>=amount_due-waiver_amount THEN "paid" WHEN amount_paid+:c>0 THEN "partial" ELSE "unpaid" END WHERE id=:lid')
                ->execute([':a'=>$amount,':b'=>$amount,':c'=>$amount,':lid'=>$ledger_id]);
            $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?')->execute([$amount,$acc_id]);
            $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,reference_table,created_by) VALUES (?,?,"deposit",?,\'fee_payments\',?)')
                ->execute([$acc_id,$amount,"Quick-pay $rcpNo",current_user_id()]);
        }
        $pdo->commit();

        $newLed = $pdo->prepare('SELECT amount_paid,amount_due,waiver_amount,status FROM fee_ledgers WHERE id=?');
        $newLed->execute([$ledger_id]);
        $newLed = $newLed->fetch();
        $newBal = round($newLed['amount_due'] - $newLed['amount_paid'] - $newLed['waiver_amount'], 2);

        ob_clean();
        echo json_encode(['ok'=>true,'receipt'=>$rcpNo,'status'=>$newLed['status'],'balance'=>$newBal,'paid'=>$newLed['amount_paid'],'pending'=>$approvalStatus==='pending','receipt_url'=>'receipt.php?rcp='.urlencode($rcpNo)]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_clean();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Bulk pay all dues for a student ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_pay_student') {
    ob_start();
    header('Content-Type: application/json');
    try {
        csrf_check();
        $student_id = int_param('student_id', 0, $_POST);
        $sess       = int_param('session_id',  0, $_POST);
        $method     = $_POST['method']      ?? 'cash';
        $acc_id     = int_param('account_id', 0, $_POST);
        $cat_filter = int_param('fee_cat_id', 0, $_POST);

        if (!$student_id || !$acc_id || !$sess) {
            ob_clean(); echo json_encode(['ok'=>false,'msg'=>'Invalid parameters.']); exit;
        }

        $where  = 'fl.student_id=:sid AND fl.session_id=:sess AND fl.status!="paid" AND (fl.amount_due-fl.amount_paid-fl.waiver_amount)>0.01';
        $params = [':sid'=>$student_id,':sess'=>$sess];
        if ($cat_filter) { $where .= ' AND fl.fee_category_id=:cat'; $params[':cat']=$cat_filter; }

        $dues = $pdo->prepare("SELECT fl.id,(fl.amount_due-fl.amount_paid-fl.waiver_amount) AS balance FROM fee_ledgers fl WHERE $where");
        $dues->execute($params);
        $dues = $dues->fetchAll();

        if (empty($dues)) { ob_clean(); echo json_encode(['ok'=>false,'msg'=>'No outstanding dues.']); exit; }

        $pdo->beginTransaction();
        $rcpNo = next_receipt_number();
        $total = 0;

        foreach ($dues as $d) {
            $amt = round($d['balance'], 2);
            if ($amt <= 0) continue;
            $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,CURDATE(),?,?,?,?,"approved")')
                ->execute([$d['id'],$student_id,$amt,$method,$rcpNo,current_user_id(),$acc_id]);
            $pdo->prepare('UPDATE fee_ledgers SET amount_paid=amount_paid+?,status="paid" WHERE id=?')->execute([$amt,$d['id']]);
            $total += $amt;
        }

        if ($total > 0) {
            $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?')->execute([$total,$acc_id]);
            $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,created_by) VALUES (?,?,"deposit",?,?)')
                ->execute([$acc_id,$total,"Bulk pay $rcpNo",current_user_id()]);
        }

        $pdo->commit();
        ob_clean();
        echo json_encode(['ok'=>true,'receipt'=>$rcpNo,'total'=>$total,'receipt_url'=>'receipt.php?rcp='.urlencode($rcpNo)]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_clean();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Dropdowns ─────────────────────────────────────────────────────────────────
$session_id  = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$class_id    = int_param('class_id',   0, $_GET);
$section_id  = int_param('section_id', 0, $_GET);
$fee_cat_id  = int_param('fee_cat_id', 0, $_GET);

$sessions      = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes       = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();
$feeCategories = $pdo->query('SELECT id,category_name FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();
$accounts      = $pdo->query('SELECT id,account_name,current_balance FROM accounts ORDER BY account_name')->fetchAll();

$sections = [];
if ($class_id) {
    $s = $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=? AND status=1 ORDER BY section_name');
    $s->execute([$class_id]);
    $sections = $s->fetchAll();
}

// ── Build student+fee data ────────────────────────────────────────────────────
$students       = [];
$grandDue       = $grandPaid = $grandBal = 0;
$studentCount   = $studentsWithDue = 0;

if ($class_id && $session_id) {
    $sWhere  = 'se.session_id=:sess AND se.class_id=:cls AND se.status="active"';
    $sParams = [':sess'=>$session_id,':cls'=>$class_id];
    if ($section_id) { $sWhere .= ' AND se.section_id=:sec'; $sParams[':sec']=$section_id; }

    $stmtS = $pdo->prepare(
        "SELECT u.id, u.full_name, sp.student_id_no, se.roll_number, sec.section_name
         FROM student_enrollments se
         JOIN users u ON u.id=se.student_id
         LEFT JOIN student_profiles sp ON sp.user_id=u.id
         LEFT JOIN sections sec ON sec.id=se.section_id
         WHERE $sWhere ORDER BY sec.section_name, se.roll_number"
    );
    $stmtS->execute($sParams);
    $stuRows = $stmtS->fetchAll();

    if (!empty($stuRows)) {
        $sids    = array_column($stuRows, 'id');
        $inP     = implode(',', array_fill(0, count($sids), '?'));
        $lWhere  = "fl.session_id=? AND fl.student_id IN ($inP)";
        $lParams = [$session_id, ...$sids];
        if ($fee_cat_id) { $lWhere .= ' AND fl.fee_category_id=?'; $lParams[]=$fee_cat_id; }

        $stmtL = $pdo->prepare(
            "SELECT fl.*,fc.category_name,(fl.amount_due-fl.amount_paid-fl.waiver_amount) AS balance
             FROM fee_ledgers fl
             JOIN fee_categories fc ON fc.id=fl.fee_category_id
             WHERE $lWhere ORDER BY fl.student_id,fl.due_date,fc.category_name"
        );
        $stmtL->execute($lParams);
        $ledgersByStudent = [];
        foreach ($stmtL->fetchAll() as $l) {
            $ledgersByStudent[$l['student_id']][] = $l;
        }

        foreach ($stuRows as $stu) {
            $fees    = $ledgersByStudent[$stu['id']] ?? [];
            $stuDue  = array_sum(array_column($fees, 'amount_due'));
            $stuPaid = array_sum(array_column($fees, 'amount_paid'));
            $stuBal  = max(0, array_sum(array_map(fn($f)=>max(0,$f['balance']), $fees)));
            $grandDue  += $stuDue;
            $grandPaid += $stuPaid;
            $grandBal  += $stuBal;
            $studentCount++;
            if ($stuBal > 0.01) $studentsWithDue++;
            $students[] = $stu + ['fees'=>$fees,'total_due'=>$stuDue,'total_paid'=>$stuPaid,'outstanding'=>$stuBal];
        }
    }
}

// ── Recent receipts for this class ───────────────────────────────────────────
$recentReceipts = [];
if ($class_id && $session_id && !empty($students)) {
    $sids2 = array_column($students, 'id');
    $inP2  = implode(',', array_fill(0, count($sids2), '?'));
    $rr = $pdo->prepare(
        "SELECT fp.receipt_number, fp.payment_date, fp.payment_method, fp.amount, fp.approval_status,
                u.full_name AS student_name, sp.student_id_no,
                GROUP_CONCAT(fc.category_name SEPARATOR ', ') AS fee_cats
         FROM fee_payments fp
         JOIN users u ON u.id=fp.student_id
         LEFT JOIN student_profiles sp ON sp.user_id=fp.student_id
         JOIN fee_ledgers fl ON fl.id=fp.ledger_id
         JOIN fee_categories fc ON fc.id=fl.fee_category_id
         WHERE fp.student_id IN ($inP2) AND fp.approval_status != 'void'
         GROUP BY fp.receipt_number, fp.payment_date, fp.payment_method, fp.amount, fp.approval_status,
                  u.full_name, sp.student_id_no
         ORDER BY fp.id DESC LIMIT 15"
    );
    $rr->execute($sids2);
    $recentReceipts = $rr->fetchAll();
}

$collectRate = $grandDue > 0 ? round($grandPaid / $grandDue * 100) : 0;

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h1 class="page-title mb-0"><i class="bi bi-collection-fill me-2 text-primary"></i>Master Fee Collection</h1>
  <div class="d-flex gap-2 no-print">
    <?php if ($class_id && !empty($students)): ?>
      <a href="student_dues.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" class="btn btn-outline-warning btn-sm">
        <i class="bi bi-exclamation-triangle me-1"></i>Dues Report
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- ── Filter Bar ──────────────────────────────────────────────────────────── -->
<div class="card mb-3 border-0 shadow-sm no-print">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" data-no-protect id="filterForm">
      <div class="col-6 col-md-2">
        <label class="form-label small fw-600 mb-1">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-600 mb-1">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Class —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= e($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-600 mb-1">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Sections —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id']?'selected':'' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-600 mb-1">Fee Type</label>
        <select name="fee_cat_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— All Fees —</option>
          <?php foreach ($feeCategories as $fc): ?>
            <option value="<?= $fc['id'] ?>" <?= $fee_cat_id==$fc['id']?'selected':'' ?>><?= e($fc['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if (!$class_id): ?>
<div class="card"><div class="card-body"><div class="empty-state">
  <i class="bi bi-collection fs-1 text-muted"></i>
  <p>Select a class to start collecting fees.</p>
</div></div></div>
<?php elseif (empty($students)): ?>
<div class="card"><div class="card-body"><div class="empty-state">
  <i class="bi bi-person-x fs-1 text-muted"></i>
  <p>No students enrolled in this class for the selected session.</p>
</div></div></div>
<?php else: ?>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-value"><?= $studentCount ?></div><div class="stat-label">Students</div><i class="bi bi-people stat-icon"></i></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= $studentsWithDue ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">With Dues</div>
      <i class="bi bi-exclamation-circle stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= money($grandPaid) ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">Collected</div>
      <i class="bi bi-check-circle stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-value"><?= $collectRate ?>%</div>
      <div class="stat-label">Collection Rate</div>
      <i class="bi bi-bar-chart stat-icon"></i>
      <div class="progress mt-1" style="height:4px;"><div class="progress-bar bg-<?= $collectRate>=80?'success':($collectRate>=50?'warning':'danger') ?>" style="width:<?= $collectRate ?>%"></div></div>
    </div>
  </div>
</div>

<!-- ── Payment Controls (always visible) ─────────────────────────────────── -->
<div class="card mb-3 border-0 shadow-sm no-print" style="position:sticky;top:0;z-index:100;">
  <div class="card-body py-2">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <span class="text-muted small fw-600">Payment Settings:</span>
      <div class="d-flex align-items-center gap-1">
        <label class="form-label mb-0 small">Account</label>
        <select id="g-account" class="form-select form-select-sm" style="min-width:180px;">
          <?php foreach ($accounts as $acc): ?>
            <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (<?= money($acc['current_balance']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-flex align-items-center gap-1">
        <label class="form-label mb-0 small">Method</label>
        <select id="g-method" class="form-select form-select-sm" style="min-width:130px;">
          <option value="cash">Cash</option>
          <option value="bank">Bank Transfer</option>
          <option value="mobile_banking">bKash / Nagad</option>
          <option value="cheque">Cheque</option>
        </select>
      </div>
      <div class="d-flex align-items-center gap-2 ms-auto">
        <input type="text" id="g-search" class="form-control form-control-sm" placeholder="Search student…" style="max-width:160px;" oninput="filterStudents(this.value)">
        <div class="form-check form-switch mb-0">
          <input type="checkbox" class="form-check-input" id="g-show-paid" onchange="filterStudents()">
          <label class="form-check-label small" for="g-show-paid">Show cleared</label>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Tabs ───────────────────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-0 no-print" id="mcTabs">
  <li class="nav-item"><a class="nav-link active fw-600" data-bs-toggle="tab" href="#tab-students">
    <i class="bi bi-people me-1"></i>Students <span class="badge bg-primary ms-1"><?= $studentCount ?></span>
  </a></li>
  <li class="nav-item"><a class="nav-link fw-600" data-bs-toggle="tab" href="#tab-receipts">
    <i class="bi bi-receipt me-1"></i>Recent Receipts
    <?php if (!empty($recentReceipts)): ?><span class="badge bg-secondary ms-1"><?= count($recentReceipts) ?></span><?php endif; ?>
  </a></li>
</ul>

<div class="tab-content">

<!-- ─── Students Tab ──────────────────────────────────────────────────────── -->
<div class="tab-pane fade show active" id="tab-students">
  <div class="card border-0 shadow-sm" style="border-top-left-radius:0;">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="studentTable" style="font-size:.85rem;">
        <thead>
          <tr style="background:#1e293b;color:#e2e8f0;">
            <th style="background:#1e293b;color:#e2e8f0;width:50px;"></th>
            <th style="background:#1e293b;color:#e2e8f0;">Roll</th>
            <th style="background:#1e293b;color:#e2e8f0;">Student</th>
            <th style="background:#1e293b;color:#e2e8f0;" class="d-none d-md-table-cell">Section</th>
            <th style="background:#1e293b;color:#e2e8f0;text-align:right">Billed</th>
            <th style="background:#1e293b;color:#e2e8f0;text-align:right">Paid</th>
            <th style="background:#1e293b;color:#e2e8f0;text-align:right">Due</th>
            <th style="background:#1e293b;color:#e2e8f0;width:130px;"></th>
          </tr>
        </thead>
        <tbody id="studentTableBody">
          <?php foreach ($students as $idx => $stu):
            $hasDues = $stu['outstanding'] > 0.01;
            $allPaid = $stu['outstanding'] <= 0.01 && $stu['total_paid'] > 0;
          ?>
          <tr class="student-row <?= !$hasDues ? 'cleared-row' : '' ?>"
              data-name="<?= strtolower(e($stu['full_name'])) ?>"
              data-id="<?= $stu['id'] ?>" style="<?= !$hasDues ? 'opacity:.6' : '' ?>">
            <td class="text-center" style="padding:6px 4px;">
              <button class="btn btn-xs btn-outline-secondary" style="width:24px;height:24px;padding:0;line-height:1;"
                      onclick="toggleFees(<?= $stu['id'] ?>)" id="toggle-<?= $stu['id'] ?>" title="Show fees">
                <i class="bi bi-chevron-right" id="chevron-<?= $stu['id'] ?>"></i>
              </button>
            </td>
            <td class="fw-700 text-primary" style="width:45px;"><?= $stu['roll_number'] ?></td>
            <td>
              <div class="fw-600"><?= e($stu['full_name']) ?></div>
              <small class="text-muted"><?= e($stu['student_id_no'] ?? '') ?></small>
            </td>
            <td class="text-muted small d-none d-md-table-cell"><?= e($stu['section_name'] ?? '—') ?></td>
            <td class="text-end text-muted"><?= money($stu['total_due']) ?></td>
            <td class="text-end text-success fw-600"><?= money($stu['total_paid']) ?></td>
            <td class="text-end fw-700 <?= $hasDues ? 'text-danger' : 'text-success' ?>"><?= money($stu['outstanding']) ?></td>
            <td class="text-end" style="padding:6px 8px;">
              <?php if ($hasDues): ?>
                <button class="btn btn-sm btn-success" onclick="bulkPay(<?= $stu['id'] ?>,'<?= e(addslashes($stu['full_name'])) ?>',<?= $stu['outstanding'] ?>)">
                  <i class="bi bi-lightning-fill me-1"></i><?= money($stu['outstanding']) ?>
                </button>
              <?php else: ?>
                <span class="badge bg-success-subtle text-success border border-success">
                  <i class="bi bi-check-circle-fill me-1"></i>Cleared
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <!-- Expandable fee rows -->
          <tr id="fees-<?= $stu['id'] ?>" style="display:none;">
            <td colspan="8" style="padding:0 0 0 60px;background:#f8fafc;">
              <div class="p-2">
                <?php if (empty($stu['fees'])): ?>
                  <p class="text-muted small py-2 mb-0">No fee ledger entries. Generate ledgers from <a href="structures.php">Fee Structures</a>.</p>
                <?php else: ?>
                  <table class="table table-sm mb-0" style="font-size:.78rem;">
                    <thead><tr class="table-light">
                      <th>Fee Category</th><th>Period</th>
                      <th class="text-end">Due</th><th class="text-end">Paid</th>
                      <th class="text-end">Balance</th><th style="width:120px;"></th>
                    </tr></thead>
                    <tbody>
                      <?php foreach ($stu['fees'] as $fee):
                        $bal  = max(0, round($fee['balance'], 2));
                        $period = $fee['month'] ? date('M Y', mktime(0,0,0,$fee['month'],1,$fee['year'] ?? date('Y'))) : '';
                        $stClass = $fee['status']==='paid'?'success':($fee['status']==='partial'?'warning':'danger');
                      ?>
                      <tr>
                        <td class="fw-600"><?= e($fee['category_name']) ?></td>
                        <td class="text-muted"><?= $period ?: '—' ?></td>
                        <td class="text-end"><?= money($fee['amount_due']) ?></td>
                        <td class="text-end text-success"><?= money($fee['amount_paid']) ?></td>
                        <td class="text-end fw-700 text-<?= $stClass ?>"><?= money($bal) ?></td>
                        <td class="text-end">
                          <?php if ($bal > 0.01): ?>
                            <div class="d-flex gap-1 align-items-center justify-content-end">
                              <input type="number" id="amt-<?= $fee['id'] ?>"
                                     class="form-control form-control-sm text-end"
                                     style="width:70px;padding:2px 4px;"
                                     value="<?= number_format($bal,2,'.','') ?>"
                                     min="0.01" max="<?= $bal ?>" step="0.01">
                              <button class="btn btn-sm btn-primary" style="padding:2px 8px;"
                                      onclick="quickPay(<?= $fee['id'] ?>,<?= $stu['id'] ?>,'<?= e(addslashes($stu['full_name'])) ?>')">
                                Pay
                              </button>
                            </div>
                          <?php else: ?>
                            <span class="badge bg-<?= $stClass ?>-subtle text-<?= $stClass ?> border border-<?= $stClass ?> border-opacity-25" style="font-size:.7rem;">
                              <?= ucfirst($fee['status']) ?>
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <!-- Summary row -->
        <tfoot>
          <tr style="background:#0f172a;color:#e2e8f0;font-weight:700;">
            <td colspan="4" class="small">TOTALS — <?= $studentCount ?> students</td>
            <td class="text-end"><?= money($grandDue) ?></td>
            <td class="text-end text-success"><?= money($grandPaid) ?></td>
            <td class="text-end text-warning"><?= money($grandBal) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<!-- ─── Recent Receipts Tab ───────────────────────────────────────────────── -->
<div class="tab-pane fade" id="tab-receipts">
  <div class="card border-0 shadow-sm" style="border-top-left-radius:0;">
    <?php if (empty($recentReceipts)): ?>
      <div class="card-body"><div class="empty-state"><i class="bi bi-receipt"></i><p>No receipts yet for this class.</p></div></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
        <thead>
          <tr style="background:#1e293b;color:#e2e8f0;">
            <th style="background:#1e293b;color:#e2e8f0;">Receipt #</th>
            <th style="background:#1e293b;color:#e2e8f0;">Student</th>
            <th style="background:#1e293b;color:#e2e8f0;">Fees</th>
            <th style="background:#1e293b;color:#e2e8f0;text-align:right">Amount</th>
            <th style="background:#1e293b;color:#e2e8f0;">Date</th>
            <th style="background:#1e293b;color:#e2e8f0;">Method</th>
            <th style="background:#1e293b;color:#e2e8f0;text-align:center">Status</th>
            <th style="background:#1e293b;color:#e2e8f0;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentReceipts as $rr): ?>
          <tr>
            <td><code class="small"><?= e($rr['receipt_number']) ?></code></td>
            <td>
              <div class="fw-600"><?= e($rr['student_name']) ?></div>
              <small class="text-muted"><?= e($rr['student_id_no'] ?? '') ?></small>
            </td>
            <td class="text-muted small"><?= e(substr($rr['fee_cats'], 0, 60)) ?><?= strlen($rr['fee_cats'])>60?'…':'' ?></td>
            <td class="text-end fw-700 text-success"><?= money($rr['amount']) ?></td>
            <td class="text-muted small"><?= fmt_date($rr['payment_date']) ?></td>
            <td><span class="badge bg-light text-dark border text-capitalize"><?= e(str_replace('_',' ',$rr['payment_method'])) ?></span></td>
            <td class="text-center">
              <?php if ($rr['approval_status']==='pending'): ?>
                <span class="badge bg-warning text-dark">Pending</span>
              <?php else: ?>
                <span class="badge bg-success">Approved</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="receipt.php?rcp=<?= urlencode($rr['receipt_number']) ?>" target="_blank"
                 class="btn btn-xs btn-outline-primary" title="Print receipt">
                <i class="bi bi-printer"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

</div><!-- /tab-content -->

<?php endif; // end if students ?>

<!-- ─── Receipt Success Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title fw-700"><i class="bi bi-check-circle-fill me-2"></i>Payment Collected!</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="fs-5 fw-700 text-success mb-1" id="rct-amount"></div>
        <div class="text-muted small mb-3">Receipt: <strong id="rct-number" class="text-dark font-monospace"></strong></div>
        <div class="d-flex gap-2 justify-content-center">
          <a id="rct-print-btn" href="#" target="_blank" class="btn btn-success">
            <i class="bi bi-printer me-1"></i>Print Receipt
          </a>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Bulk Pay Confirm Modal ────────────────────────────────────────────── -->
<div class="modal fade" id="bulkModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title fw-700"><i class="bi bi-lightning-fill me-2"></i>Pay All Dues</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">
        <div class="text-muted small mb-1">Collecting all dues for</div>
        <div class="fw-700 fs-6 mb-2" id="bulk-name"></div>
        <div class="fs-4 fw-700 text-primary mb-1" id="bulk-amount"></div>
        <div class="text-muted small">
          Account: <span id="bulk-account-label"></span> &nbsp;·&nbsp; <span id="bulk-method-label"></span>
        </div>
        <div id="bulk-error" class="alert alert-danger py-2 small mt-2 d-none"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="bulk-confirm-btn" onclick="confirmBulkPay()">
          <i class="bi bi-check-lg me-1"></i>Confirm & Collect
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF         = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const SESSION_ID   = <?= (int)$session_id ?>;
const FEE_CAT_ID   = <?= (int)$fee_cat_id ?>;
let bulkStudentId  = 0;
let bulkDueAmount  = 0;

// ── Toggle fee rows ──────────────────────────────────────────────────────────
function toggleFees(sid) {
  const row = document.getElementById('fees-' + sid);
  const chevron = document.getElementById('chevron-' + sid);
  if (!row) return;
  const isOpen = row.style.display !== 'none';
  row.style.display = isOpen ? 'none' : '';
  chevron.className = isOpen ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
}

// ── Search / filter ──────────────────────────────────────────────────────────
function filterStudents(q) {
  q = (q ?? document.getElementById('g-search').value).toLowerCase();
  const showPaid = document.getElementById('g-show-paid').checked;
  document.querySelectorAll('.student-row').forEach(tr => {
    const name    = tr.dataset.name || '';
    const cleared = tr.classList.contains('cleared-row');
    const match   = !q || name.includes(q);
    const show    = match && (showPaid || !cleared);
    tr.style.display = show ? '' : 'none';
    // Hide expanded fees too if parent is hidden
    const feesRow = document.getElementById('fees-' + tr.dataset.id);
    if (feesRow && !show) feesRow.style.display = 'none';
  });
}

// ── Show receipt modal ────────────────────────────────────────────────────────
function showReceiptModal(receipt, amount, receiptUrl) {
  document.getElementById('rct-number').textContent  = receipt;
  document.getElementById('rct-amount').textContent  = '৳ ' + parseFloat(amount).toFixed(2);
  document.getElementById('rct-print-btn').href      = receiptUrl;
  new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

// ── Quick Pay (single fee) ───────────────────────────────────────────────────
async function quickPay(ledgerId, studentId, studentName) {
  const amtEl  = document.getElementById('amt-' + ledgerId);
  const amount = parseFloat(amtEl?.value || 0);
  const accId  = document.getElementById('g-account').value;
  const method = document.getElementById('g-method').value;
  if (!accId) { EMS.showError('Please select a deposit account.'); return; }
  if (!amount || amount <= 0) { EMS.showError('Enter a valid amount.'); return; }

  const btn = event?.target?.closest('button');
  if (btn) { btn._orig = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; btn.disabled = true; }

  try {
    const fd = new FormData();
    fd.append('action',     'quick_pay');
    fd.append('_csrf',      CSRF);
    fd.append('ledger_id',  ledgerId);
    fd.append('student_id', studentId);
    fd.append('amount',     amount);
    fd.append('method',     method);
    fd.append('account_id', accId);

    const res  = await fetch('master_collection.php', { method:'POST', body:fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) { EMS.showError('Server returned an unexpected response. Check PHP logs.', text.substring(0, 300)); return; }

    if (data.ok) {
      showReceiptModal(data.receipt, amount, data.receipt_url);
      // Refresh the page after closing the modal to show updated status
      document.getElementById('receiptModal').addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
    } else {
      EMS.showError(data.msg || 'Payment failed.', null, 5000);
    }
  } catch(e) {
    EMS.showError('Network error. Check your connection.', e.message);
  } finally {
    if (btn) { btn.innerHTML = btn._orig; btn.disabled = false; }
  }
}

// ── Bulk Pay (all dues for student) ─────────────────────────────────────────
function bulkPay(studentId, studentName, totalDue) {
  const accId  = document.getElementById('g-account').value;
  const method = document.getElementById('g-method').value;
  if (!accId) { EMS.showError('Select a deposit account first.'); return; }
  bulkStudentId = studentId;
  bulkDueAmount = totalDue;
  document.getElementById('bulk-name').textContent   = studentName;
  document.getElementById('bulk-amount').textContent = '৳ ' + totalDue.toFixed(2);
  document.getElementById('bulk-account-label').textContent = document.getElementById('g-account').options[document.getElementById('g-account').selectedIndex]?.text || '';
  document.getElementById('bulk-method-label').textContent  = document.getElementById('g-method').options[document.getElementById('g-method').selectedIndex]?.text || '';
  document.getElementById('bulk-error').classList.add('d-none');
  document.getElementById('bulk-confirm-btn').disabled = false;
  new bootstrap.Modal(document.getElementById('bulkModal')).show();
}

async function confirmBulkPay() {
  const btn    = document.getElementById('bulk-confirm-btn');
  const errEl  = document.getElementById('bulk-error');
  const accId  = document.getElementById('g-account').value;
  const method = document.getElementById('g-method').value;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
  errEl.classList.add('d-none');

  try {
    const fd = new FormData();
    fd.append('action',     'bulk_pay_student');
    fd.append('_csrf',      CSRF);
    fd.append('student_id', bulkStudentId);
    fd.append('session_id', SESSION_ID);
    fd.append('fee_cat_id', FEE_CAT_ID);
    fd.append('method',     method);
    fd.append('account_id', accId);

    const res  = await fetch('master_collection.php', { method:'POST', body:fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) { EMS.showError('Server returned an unexpected response.', text.substring(0, 300)); btn.disabled=false; btn.innerHTML='<i class="bi bi-check-lg me-1"></i>Confirm & Collect'; return; }

    if (data.ok) {
      bootstrap.Modal.getInstance(document.getElementById('bulkModal'))?.hide();
      showReceiptModal(data.receipt, data.total, data.receipt_url);
      document.getElementById('receiptModal').addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
    } else {
      errEl.textContent = data.msg || 'Payment failed.';
      errEl.classList.remove('d-none');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm & Collect';
    }
  } catch(e) {
    errEl.textContent = 'Network error: ' + e.message;
    errEl.classList.remove('d-none');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm & Collect';
  }
}

// Init: hide cleared rows by default
document.addEventListener('DOMContentLoaded', () => filterStudents(''));
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
