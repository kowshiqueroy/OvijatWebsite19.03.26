<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Master Fee Collection';
$breadcrumbs = ['Finance' => 'ledger.php', 'Master Collection' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['fees.collect']);

$pdo        = db();
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$class_id   = int_param('class_id',   0, $_GET);
$section_id = int_param('section_id', 0, $_GET);
$fee_cat_id = int_param('fee_cat_id', 0, $_GET);   // 0 = all categories
$view_month = int_param('month',      0, $_GET);    // 0 = all months
$view_year  = int_param('year',       (int)date('Y'), $_GET);

$sessions    = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$classes     = $pdo->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY display_order,class_name')->fetchAll();
$feeCategories = $pdo->query('SELECT id,category_name,category_type FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();
$accounts    = $pdo->query('SELECT id,account_name FROM accounts ORDER BY account_name')->fetchAll();

$sections = [];
if ($class_id) {
    $sections = $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=? AND status=1 ORDER BY section_name')->execute([$class_id]) ? [] : [];
    $s = $pdo->prepare('SELECT id,section_name FROM sections WHERE class_id=? AND status=1 ORDER BY section_name');
    $s->execute([$class_id]);
    $sections = $s->fetchAll();
}

// ── AJAX: Quick-pay a single ledger entry ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_pay') {
    header('Content-Type: application/json');
    csrf_check();

    $ledger_id  = int_param('ledger_id', 0, $_POST);
    $amount     = round((float)($_POST['amount'] ?? 0), 2);
    $student_id = int_param('student_id', 0, $_POST);
    $method     = $_POST['method'] ?? 'cash';
    $acc_id     = int_param('account_id', 0, $_POST);
    $allowPartial = setting('allow_partial_payment', 'no') === 'yes';
    $minPct       = (int)setting('partial_min_percent', '100');
    $needsApproval= setting('partial_requires_approval', 'yes') === 'yes';

    if (!$ledger_id || $amount <= 0 || !$student_id || !$acc_id) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid parameters.']);
        exit;
    }

    $led = $pdo->prepare('SELECT * FROM fee_ledgers WHERE id=?');
    $led->execute([$ledger_id]);
    $led = $led->fetch();
    if (!$led) { echo json_encode(['ok' => false, 'msg' => 'Ledger not found.']); exit; }

    $balance = round($led['amount_due'] - $led['amount_paid'] - $led['waiver_amount'], 2);
    if ($amount > $balance + 0.01) {
        echo json_encode(['ok' => false, 'msg' => 'Amount exceeds balance of '.money($balance)]);
        exit;
    }

    $isPartial = $amount < $balance - 0.01;

    if ($isPartial && !$allowPartial) {
        echo json_encode(['ok' => false, 'msg' => 'Partial payment is not allowed. Full amount required: '.money($balance)]);
        exit;
    }

    if ($isPartial && $minPct < 100) {
        $minAmt = round($balance * $minPct / 100, 2);
        if ($amount < $minAmt - 0.01) {
            echo json_encode(['ok' => false, 'msg' => 'Minimum payment is '.money($minAmt).' ('.$minPct.'% of balance).']);
            exit;
        }
    }

    $approvalStatus = ($isPartial && $needsApproval) ? 'pending' : 'approved';

    try {
        $pdo->beginTransaction();
        $rcpNo = next_receipt_number();

        $pdo->prepare(
            'INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status)
             VALUES (?,?,?,CURDATE(),?,?,?,?,?)'
        )->execute([$ledger_id, $student_id, $amount, $method, $rcpNo, current_user_id(), $acc_id, $approvalStatus]);

        if ($approvalStatus === 'approved') {
            // Only update ledger balance for approved payments
            $pdo->prepare(
                'UPDATE fee_ledgers SET amount_paid=amount_paid+:a,
                 status=CASE WHEN amount_paid+:b >= amount_due-waiver_amount THEN "paid"
                             WHEN amount_paid+:c > 0 THEN "partial" ELSE "unpaid" END
                 WHERE id=:lid'
            )->execute([':a'=>$amount,':b'=>$amount,':c'=>$amount,':lid'=>$ledger_id]);

            $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?')->execute([$amount, $acc_id]);
            $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,reference_table,created_by) VALUES (?,?,"deposit",?,\'fee_payments\',?)')
                ->execute([$acc_id, $amount, "Quick-pay receipt $rcpNo", current_user_id()]);
        }

        $pdo->commit();

        // Reload ledger
        $newLed = $pdo->prepare('SELECT amount_paid,amount_due,waiver_amount,status FROM fee_ledgers WHERE id=?');
        $newLed->execute([$ledger_id]);
        $newLed = $newLed->fetch();

        echo json_encode([
            'ok'      => true,
            'receipt' => $rcpNo,
            'status'  => $newLed['status'],
            'balance' => round($newLed['amount_due'] - $newLed['amount_paid'] - $newLed['waiver_amount'], 2),
            'paid'    => $newLed['amount_paid'],
            'pending' => $approvalStatus === 'pending',
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Bulk pay entire row (all dues for one student) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_pay_student') {
    header('Content-Type: application/json');
    csrf_check();

    $student_id  = int_param('student_id', 0, $_POST);
    $sess        = int_param('session_id', $session_id, $_POST);
    $method      = $_POST['method'] ?? 'cash';
    $acc_id      = int_param('account_id', 0, $_POST);
    $cat_filter  = int_param('fee_cat_id', 0, $_POST);

    if (!$student_id || !$acc_id) {
        echo json_encode(['ok'=>false,'msg'=>'Invalid parameters.']); exit;
    }

    $where = 'fl.student_id=:sid AND fl.session_id=:sess AND fl.status != "paid"
              AND (fl.amount_due - fl.amount_paid - fl.waiver_amount) > 0';
    $params = [':sid'=>$student_id, ':sess'=>$sess];
    if ($cat_filter) { $where .= ' AND fl.fee_category_id=:cat'; $params[':cat'] = $cat_filter; }

    $dues = $pdo->prepare("SELECT fl.id, fl.amount_due - fl.amount_paid - fl.waiver_amount AS balance FROM fee_ledgers fl WHERE $where");
    $dues->execute($params);
    $dues = $dues->fetchAll();

    if (empty($dues)) {
        echo json_encode(['ok'=>false,'msg'=>'No outstanding dues found for this student.']); exit;
    }

    try {
        $pdo->beginTransaction();
        $rcpNo = next_receipt_number();
        $total = 0;

        foreach ($dues as $d) {
            $amt = round($d['balance'], 2);
            if ($amt <= 0) continue;
            $pdo->prepare('INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,account_id,approval_status) VALUES (?,?,?,CURDATE(),?,?,?,?,"approved")')
                ->execute([$d['id'], $student_id, $amt, $method, $rcpNo, current_user_id(), $acc_id]);
            $pdo->prepare('UPDATE fee_ledgers SET amount_paid=amount_paid+?, status="paid" WHERE id=?')->execute([$amt, $d['id']]);
            $total += $amt;
        }

        if ($total > 0) {
            $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?')->execute([$total, $acc_id]);
            $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,created_by) VALUES (?,?,"deposit",?,?)')
                ->execute([$acc_id, $total, "Bulk pay receipt $rcpNo (student $student_id)", current_user_id()]);
        }

        $pdo->commit();
        log_activity('bulk_fee_collected', 'finance', $student_id, '', "Total:$total RCP:$rcpNo");
        echo json_encode(['ok'=>true,'receipt'=>$rcpNo,'total'=>$total]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Build the grid data ───────────────────────────────────────────────────────
$gridStudents = [];
$gridColumns  = [];  // [{key, label, cat_id, month, year}]
$gridData     = [];  // [student_id => [col_key => ledger_row]]
$summaryTotals= [];  // [col_key => {due, paid, balance}]

if ($class_id && $session_id) {
    // Students enrolled in this class/section for the session
    $sWhere = 'se.session_id=:sess AND se.class_id=:cls AND se.status="active"';
    $sParams = [':sess' => $session_id, ':cls' => $class_id];
    if ($section_id) { $sWhere .= ' AND se.section_id=:sec'; $sParams[':sec'] = $section_id; }

    $stmtS = $pdo->prepare(
        "SELECT u.id, u.full_name, sp.student_id_no, se.roll_number, sec.section_name
         FROM student_enrollments se
         JOIN users u ON u.id = se.student_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN sections sec ON sec.id = se.section_id
         WHERE $sWhere
         ORDER BY se.roll_number, u.full_name"
    );
    $stmtS->execute($sParams);
    $gridStudents = $stmtS->fetchAll();

    if (!empty($gridStudents)) {
        $studentIds = array_column($gridStudents, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));

        // Fetch all fee ledger entries for these students this session
        $lWhere = "fl.session_id=? AND fl.student_id IN ($inPlaceholders)";
        $lParams = [$session_id, ...$studentIds];
        if ($fee_cat_id) { $lWhere .= ' AND fl.fee_category_id=?'; $lParams[] = $fee_cat_id; }
        if ($view_month)  { $lWhere .= ' AND fl.month=?'; $lParams[] = $view_month; }
        if ($view_month && $view_year) { $lWhere .= ' AND fl.year=?'; $lParams[] = $view_year; }

        $stmtL = $pdo->prepare(
            "SELECT fl.*, fc.category_name, fc.category_type,
                    (fl.amount_due - fl.amount_paid - fl.waiver_amount) AS balance
             FROM fee_ledgers fl
             JOIN fee_categories fc ON fc.id = fl.fee_category_id
             WHERE $lWhere
             ORDER BY fc.category_name, fl.year, fl.month"
        );
        $stmtL->execute($lParams);
        $ledgers = $stmtL->fetchAll();

        // Build dynamic column list from the actual ledger data
        $colKeys = [];
        foreach ($ledgers as $l) {
            if ($l['month']) {
                $key = 'cat_' . $l['fee_category_id'] . '_' . $l['year'] . '_' . $l['month'];
                $label = $l['category_name'] . ' ' . date('M', mktime(0,0,0,$l['month'],1)) . ' ' . $l['year'];
            } else {
                $key = 'cat_' . $l['fee_category_id'];
                $label = $l['category_name'];
            }
            if (!isset($colKeys[$key])) {
                $colKeys[$key] = [
                    'key'    => $key,
                    'label'  => $label,
                    'cat_id' => $l['fee_category_id'],
                    'month'  => $l['month'],
                    'year'   => $l['year'],
                    'type'   => $l['category_type'],
                ];
                $summaryTotals[$key] = ['due'=>0,'paid'=>0,'balance'=>0,'count_due'=>0];
            }
            // Map ledger to student × column
            $gridData[$l['student_id']][$key] = $l;
            $summaryTotals[$key]['due']      += $l['amount_due'];
            $summaryTotals[$key]['paid']     += $l['amount_paid'];
            $summaryTotals[$key]['balance']  += max(0, $l['balance']);
            if ($l['balance'] > 0.01) $summaryTotals[$key]['count_due']++;
        }
        $gridColumns = array_values($colKeys);
    }
}

// Grand totals
$grandDue = $grandPaid = $grandBalance = 0;
foreach ($summaryTotals as $t) {
    $grandDue     += $t['due'];
    $grandPaid    += $t['paid'];
    $grandBalance += $t['balance'];
}

$allowPartial = setting('allow_partial_payment', 'no') === 'yes';
$minPct       = (int)setting('partial_min_percent', '100');

require_once EMS_ROOT . '/includes/header.php';
?>

<style>
.grid-table th, .grid-table td { white-space: nowrap; font-size: .8rem; }
.grid-table th { background: #1e293b; color: #e2e8f0; position: sticky; top: 0; z-index: 2; }
.grid-table th.col-student { position: sticky; left: 0; z-index: 3; background: #1e293b; min-width: 200px; }
.grid-table td.col-student { position: sticky; left: 0; z-index: 1; background: #fff; border-right: 2px solid #e2e8f0; min-width: 200px; }
.grid-table tr:hover td.col-student { background: #f8fafc; }
.fee-cell { text-align: center; cursor: pointer; min-width: 100px; padding: 4px 6px !important; }
.fee-cell.paid   { background: #dcfce7; color: #166534; }
.fee-cell.partial { background: #fef3c7; color: #92400e; }
.fee-cell.unpaid { background: #fee2e2; color: #991b1b; }
.fee-cell.void   { background: #f1f5f9; color: #94a3b8; text-decoration: line-through; }
.fee-cell.waived { background: #ede9fe; color: #5b21b6; }
.fee-cell.pending-approval { background: #fff7ed; color: #c2410c; border: 1px dashed #fb923c; }
.fee-cell:hover:not(.paid) { filter: brightness(0.93); }
.cell-amount { font-weight: 700; display: block; }
.cell-label  { font-size: .65rem; display: block; margin-top: 1px; }
.summary-row td { background: #0f172a !important; color: #e2e8f0; font-weight: 700; }
.bulk-pay-btn { cursor: pointer; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-table me-2 text-primary"></i>Master Fee Collection Grid</h1>
  <?php if ($class_id && !empty($gridStudents)): ?>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
      <a href="student_dues.php?session_id=<?= $session_id ?>&class_id=<?= $class_id ?>&section_id=<?= $section_id ?>" class="btn btn-outline-warning btn-sm">
        <i class="bi bi-exclamation-triangle me-1"></i>Dues Report
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- Filter bar -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end" id="gridFilterForm">
      <div class="col-md-2">
        <label class="form-label small fw-600">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $session_id==$s['id'] ? 'selected' : '' ?>><?= e($s['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Class</label>
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Classes —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id'] ? 'selected' : '' ?>><?= e($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Section</label>
        <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— All Sections —</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>" <?= $section_id==$sec['id'] ? 'selected' : '' ?>><?= e($sec['section_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-600">Fee Type</label>
        <select name="fee_cat_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">— All Fees —</option>
          <?php foreach ($feeCategories as $fc): ?>
            <option value="<?= $fc['id'] ?>" <?= $fee_cat_id==$fc['id'] ? 'selected' : '' ?>><?= e($fc['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-600">Month</label>
        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="0">All</option>
          <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $view_month==$m ? 'selected' : '' ?>><?= date('M', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-600">Year</label>
        <input type="number" name="year" class="form-control form-control-sm" value="<?= $view_year ?>" min="2020" max="2040" onchange="this.form.submit()">
      </div>
    </form>
  </div>
</div>

<?php if (!$class_id): ?>
<div class="card"><div class="card-body"><div class="empty-state">
  <i class="bi bi-table"></i><p>Select a class above to load the fee collection grid.</p>
</div></div></div>

<?php elseif (empty($gridStudents)): ?>
<div class="card"><div class="card-body"><div class="empty-state">
  <i class="bi bi-person-x"></i><p>No students enrolled in this class/section for the selected session.</p>
</div></div></div>

<?php elseif (empty($gridColumns)): ?>
<div class="card"><div class="card-body"><div class="empty-state">
  <i class="bi bi-cash-stack"></i><p>No fee ledger entries found. <a href="structures.php">Set up fee structures</a> and generate ledgers first.</p>
</div></div></div>

<?php else: ?>

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card"><div class="stat-value"><?= count($gridStudents) ?></div><div class="stat-label">Students</div><i class="bi bi-people stat-icon"></i></div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= money($grandPaid) ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">Total Collected</div>
      <i class="bi bi-check-circle stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;">
      <div class="stat-value" style="color:#fff"><?= money($grandBalance) ?></div>
      <div class="stat-label" style="color:rgba(255,255,255,.8)">Total Outstanding</div>
      <i class="bi bi-exclamation-circle stat-icon" style="color:rgba(255,255,255,.25)"></i>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <?php $collectRate = $grandDue > 0 ? round($grandPaid/$grandDue*100) : 0; ?>
      <div class="stat-value"><?= $collectRate ?>%</div>
      <div class="stat-label">Collection Rate</div>
      <i class="bi bi-bar-chart stat-icon"></i>
    </div>
  </div>
</div>

<!-- Payment controls -->
<div class="card mb-3 border-0 shadow-sm">
  <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
    <span class="text-muted small fw-600">Quick Pay Settings:</span>
    <select id="g-account" class="form-select form-select-sm" style="max-width:220px;">
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="g-method" class="form-select form-select-sm" style="max-width:180px;">
      <option value="cash">Cash</option>
      <option value="bank">Bank Transfer</option>
      <option value="mobile_banking">bKash/Nagad</option>
      <option value="cheque">Cheque</option>
    </select>
    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Click any <span class="text-danger fw-600">red/yellow</span> cell to collect inline. Click <i class="bi bi-check-all"></i> to pay all dues for a student.</small>
    <?php if ($allowPartial): ?>
      <span class="badge bg-warning text-dark">Partial payments ON (min <?= $minPct ?>%)</span>
    <?php endif; ?>
  </div>
</div>

<!-- The grid -->
<div class="card border-0 shadow-sm">
  <div style="overflow-x: auto; max-height: 70vh; overflow-y: auto;">
    <table class="table table-bordered mb-0 grid-table" id="feeGrid">
      <thead>
        <tr>
          <th class="col-student">
            <div class="d-flex align-items-center gap-1">
              <span>Student</span>
              <span class="badge bg-secondary ms-1"><?= count($gridStudents) ?></span>
            </div>
          </th>
          <?php foreach ($gridColumns as $col): ?>
          <th class="text-center" style="min-width:100px;">
            <div><?= e($col['label']) ?></div>
            <?php if (!empty($summaryTotals[$col['key']])): $t = $summaryTotals[$col['key']]; ?>
            <div style="font-size:.65rem;font-weight:400;margin-top:2px;" class="text-warning">
              Due: <?= count($gridStudents) - $t['count_due'] ?>/<?= count($gridStudents) ?> paid
            </div>
            <?php endif; ?>
          </th>
          <?php endforeach; ?>
          <th class="text-center" style="min-width:80px;">Total Due</th>
          <th class="text-center" style="min-width:80px;">Paid</th>
          <th class="text-center" style="min-width:80px;">Balance</th>
          <th class="text-center" style="min-width:60px;">Pay All</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gridStudents as $stu):
          $stuDue = $stuPaid = $stuBal = 0;
          foreach ($gridColumns as $col) {
            $l = $gridData[$stu['id']][$col['key']] ?? null;
            if ($l) { $stuDue += $l['amount_due']; $stuPaid += $l['amount_paid']; $stuBal += max(0, $l['balance']); }
          }
        ?>
        <tr id="row-<?= $stu['id'] ?>">
          <td class="col-student">
            <div class="fw-600" style="font-size:.82rem;"><?= e($stu['full_name']) ?></div>
            <div class="text-muted" style="font-size:.68rem;">
              Roll: <?= $stu['roll_number'] ?>
              <?= $stu['student_id_no'] ? ' · ' . e($stu['student_id_no']) : '' ?>
              <?= $stu['section_name'] ? ' · ' . e($stu['section_name']) : '' ?>
            </div>
          </td>

          <?php foreach ($gridColumns as $col):
            $l = $gridData[$stu['id']][$col['key']] ?? null;
            if (!$l): ?>
              <td class="fee-cell text-muted">—</td>
            <?php else:
              $bal = max(0, round($l['balance'], 2));
              $statusClass = $l['status'] === 'paid' ? 'paid' :
                            ($l['waiver_amount'] > 0 && $bal <= 0.01 ? 'waived' :
                            ($l['status'] === 'partial' ? 'partial' : 'unpaid'));
            ?>
              <td class="fee-cell <?= $statusClass ?>"
                  <?php if ($statusClass !== 'paid' && $statusClass !== 'waived'): ?>
                  onclick="openPayModal(<?= $l['id'] ?>, <?= $stu['id'] ?>, '<?= e(addslashes($stu['full_name'])) ?>', <?= $bal ?>, '<?= e(addslashes($l['category_name'])) ?>')"
                  title="Click to collect — Balance: <?= money($bal) ?>"
                  <?php endif; ?>>
                <span class="cell-amount">
                  <?php if ($statusClass === 'paid'): ?>
                    <i class="bi bi-check-circle-fill"></i>
                  <?php elseif ($statusClass === 'waived'): ?>
                    <i class="bi bi-gift"></i>
                  <?php else: ?>
                    <?= money($bal) ?>
                  <?php endif; ?>
                </span>
                <span class="cell-label">
                  <?php if ($statusClass === 'paid'): ?>
                    <?= money($l['amount_paid']) ?>
                  <?php elseif ($statusClass === 'partial'): ?>
                    Paid: <?= money($l['amount_paid']) ?>
                  <?php elseif ($statusClass === 'waived'): ?>
                    Waived
                  <?php else: ?>
                    <?= ucfirst($statusClass) ?>
                  <?php endif; ?>
                </span>
              </td>
            <?php endif; ?>
          <?php endforeach; ?>

          <td class="text-end fw-600" style="font-size:.8rem;"><?= money($stuDue) ?></td>
          <td class="text-end text-success fw-600" style="font-size:.8rem;"><?= money($stuPaid) ?></td>
          <td class="text-end <?= $stuBal>0.01?'text-danger fw-700':'text-success' ?>" id="bal-<?= $stu['id'] ?>" style="font-size:.8rem;"><?= money($stuBal) ?></td>
          <td class="text-center">
            <?php if ($stuBal > 0.01): ?>
              <button class="btn btn-xs btn-success bulk-pay-btn"
                      title="Pay all dues for <?= e($stu['full_name']) ?>"
                      onclick="bulkPayStudent(<?= $stu['id'] ?>, '<?= e(addslashes($stu['full_name'])) ?>', <?= $stuBal ?>)">
                <i class="bi bi-check-all"></i>
              </button>
            <?php else: ?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>

        <!-- Summary row -->
        <tr class="summary-row">
          <td class="col-student">COLUMN TOTALS</td>
          <?php foreach ($gridColumns as $col): $t = $summaryTotals[$col['key']] ?? []; ?>
          <td class="text-center">
            <span class="cell-amount text-warning"><?= money($t['balance'] ?? 0) ?></span>
            <span class="cell-label text-success"><?= money($t['paid'] ?? 0) ?> paid</span>
          </td>
          <?php endforeach; ?>
          <td class="text-end"><?= money($grandDue) ?></td>
          <td class="text-end text-success"><?= money($grandPaid) ?></td>
          <td class="text-end text-warning"><?= money($grandBalance) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Legend -->
<div class="d-flex gap-3 mt-3 flex-wrap small">
  <span><span class="badge" style="background:#dcfce7;color:#166534;">■</span> Paid</span>
  <span><span class="badge" style="background:#fef3c7;color:#92400e;">■</span> Partial</span>
  <span><span class="badge" style="background:#fee2e2;color:#991b1b;">■</span> Unpaid</span>
  <span><span class="badge" style="background:#ede9fe;color:#5b21b6;">■</span> Waived</span>
  <span class="text-muted">· Click any unpaid/partial cell to collect inline</span>
</div>

<!-- Quick Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title fw-600"><i class="bi bi-cash-coin me-2"></i>Quick Fee Collection</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3">
          <strong id="pm-student" class="text-dark"></strong> · <span id="pm-fee-name"></span>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label small fw-600">Amount to Collect (৳)</label>
            <input type="number" id="pm-amount" class="form-control form-control-sm text-end" step="0.01" min="0.01">
            <div class="text-muted" style="font-size:.7rem;margin-top:3px;">Balance: <strong id="pm-balance-label"></strong></div>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-600">Payment Method</label>
            <select id="pm-method" class="form-select form-select-sm">
              <option value="cash">Cash</option>
              <option value="bank">Bank Transfer</option>
              <option value="mobile_banking">bKash/Nagad</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
        </div>
        <?php if ($allowPartial): ?>
        <div class="alert alert-warning py-2 small mb-2">
          <i class="bi bi-info-circle me-1"></i>Partial payment allowed (min <?= $minPct ?>% of balance).
          <?php if (setting('partial_requires_approval','yes') === 'yes'): ?>
          Partial payments require admin approval before ledger is updated.
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div id="pm-error" class="alert alert-danger py-2 small d-none"></div>
        <div id="pm-success" class="alert alert-success py-2 small d-none"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success btn-sm" id="pm-submit" onclick="submitQuickPay()">
          <i class="bi bi-check-lg me-1"></i>Collect
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
let pmLedgerId = 0, pmStudentId = 0, pmBalance = 0;

function openPayModal(ledgerId, studentId, studentName, balance, feeName) {
  pmLedgerId  = ledgerId;
  pmStudentId = studentId;
  pmBalance   = balance;
  document.getElementById('pm-student').textContent      = studentName;
  document.getElementById('pm-fee-name').textContent     = feeName;
  document.getElementById('pm-balance-label').textContent = '৳ ' + balance.toFixed(2);
  document.getElementById('pm-amount').value  = balance.toFixed(2);
  document.getElementById('pm-amount').max    = balance;
  document.getElementById('pm-method').value  = document.getElementById('g-method').value;
  document.getElementById('pm-error').classList.add('d-none');
  document.getElementById('pm-success').classList.add('d-none');
  document.getElementById('pm-submit').disabled = false;
  new bootstrap.Modal(document.getElementById('payModal')).show();
}

async function submitQuickPay() {
  const amount  = parseFloat(document.getElementById('pm-amount').value);
  const method  = document.getElementById('pm-method').value;
  const accId   = document.getElementById('g-account').value;
  const errEl   = document.getElementById('pm-error');
  const sucEl   = document.getElementById('pm-success');
  const btn     = document.getElementById('pm-submit');

  if (!amount || amount <= 0) { errEl.textContent = 'Enter a valid amount.'; errEl.classList.remove('d-none'); return; }
  if (!accId) { errEl.textContent = 'Select a deposit account.'; errEl.classList.remove('d-none'); return; }

  btn.disabled = true;
  errEl.classList.add('d-none');
  sucEl.classList.add('d-none');

  const body = new FormData();
  body.append('action', 'quick_pay');
  body.append('_csrf', CSRF);
  body.append('ledger_id', pmLedgerId);
  body.append('student_id', pmStudentId);
  body.append('amount', amount);
  body.append('method', method);
  body.append('account_id', accId);

  try {
    const res = await fetch('master_collection.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      // Update cell in the grid
      updateCell(pmLedgerId, pmStudentId, data.status, data.balance, data.paid, data.pending);
      const rcp = data.pending
        ? '⚠️ Partial — Pending Admin Approval. Receipt: ' + data.receipt
        : '✅ Collected! Receipt: ' + data.receipt;
      sucEl.textContent = rcp;
      sucEl.classList.remove('d-none');
      btn.textContent = 'Done';
      setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('payModal'))?.hide(), 1800);
    } else {
      errEl.textContent = data.msg;
      errEl.classList.remove('d-none');
      btn.disabled = false;
    }
  } catch(e) {
    errEl.textContent = 'Network error. Please try again.';
    errEl.classList.remove('d-none');
    btn.disabled = false;
  }
}

function updateCell(ledgerId, studentId, status, balance, paid, isPending) {
  // Find the cell by scanning the row for the ledger (stored in onclick attr)
  const row = document.getElementById('row-' + studentId);
  if (!row) return;
  const cells = row.querySelectorAll('.fee-cell');
  cells.forEach(cell => {
    const onclick = cell.getAttribute('onclick') || '';
    if (onclick.includes('(' + ledgerId + ',')) {
      cell.className = 'fee-cell ' + (isPending ? 'pending-approval' : (status === 'paid' ? 'paid' : 'partial'));
      cell.innerHTML = isPending
        ? '<span class="cell-amount">⏳</span><span class="cell-label">Pending</span>'
        : (status === 'paid'
            ? '<span class="cell-amount"><i class="bi bi-check-circle-fill"></i></span><span class="cell-label">৳ ' + paid.toFixed(2) + '</span>'
            : '<span class="cell-amount">৳ ' + balance.toFixed(2) + '</span><span class="cell-label">Partial</span>');
      if (status === 'paid' || isPending) cell.removeAttribute('onclick');
    }
  });
  // Update balance cell
  const balCell = document.getElementById('bal-' + studentId);
  if (balCell && !isPending) {
    const newBal = Math.max(0, parseFloat(balCell.textContent.replace(/[^0-9.]/g,'')) - (pmBalance - balance));
    balCell.textContent = '৳ ' + newBal.toFixed(2);
  }
}

async function bulkPayStudent(studentId, studentName, totalDue) {
  if (!confirm(`Pay ALL outstanding dues (৳${totalDue.toFixed(2)}) for ${studentName}?\n\nAccount: ${document.getElementById('g-account').options[document.getElementById('g-account').selectedIndex]?.text}`)) return;

  const accId  = document.getElementById('g-account').value;
  const method = document.getElementById('g-method').value;
  if (!accId) { alert('Please select a deposit account first.'); return; }

  const body = new FormData();
  body.append('action', 'bulk_pay_student');
  body.append('_csrf', CSRF);
  body.append('student_id', studentId);
  body.append('session_id', <?= $session_id ?>);
  body.append('fee_cat_id', <?= $fee_cat_id ?>);
  body.append('method', method);
  body.append('account_id', accId);

  try {
    const res  = await fetch('master_collection.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      alert(`✅ All fees collected! Total: ৳${data.total.toFixed(2)}\nReceipt: ${data.receipt}`);
      location.reload();
    } else {
      alert('❌ ' + data.msg);
    }
  } catch(e) {
    alert('Network error. Please reload and try again.');
  }
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
