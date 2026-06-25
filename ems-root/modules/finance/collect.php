<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Collect Fee';
$breadcrumbs = ['Finance' => null, 'Collect Fee' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['fees.collect']);

$pdo    = db();
$errors = [];
$receipt_no = null;

// ── Admin: Void a payment ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void_payment') {
    csrf_check();
    require_auth(['setup.edit']); // Only super admin / setup permission can void

    $pay_id  = int_param('payment_id', 0, $_POST);
    $reason  = trim($_POST['void_reason'] ?? '');
    $student_id_for_redirect = int_param('student_id', 0, $_POST);
    $session_redirect = int_param('session_id', (int)setting('current_session_id',0), $_POST);

    if (!$pay_id || !$reason) {
        flash('error', 'Payment ID and void reason are required.');
        header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect);
        exit;
    }

    $pmt = $pdo->prepare('SELECT fp.*, fl.amount_due, fl.amount_paid AS led_paid, fl.waiver_amount FROM fee_payments fp JOIN fee_ledgers fl ON fl.id=fp.ledger_id WHERE fp.id=? AND fp.approval_status != "void"');
    $pmt->execute([$pay_id]);
    $pmt = $pmt->fetch();

    if (!$pmt) {
        flash('error', 'Payment not found or already voided.');
        header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Mark payment as void
        $pdo->prepare('UPDATE fee_payments SET approval_status="void", void_reason=?, voided_by=?, voided_at=NOW() WHERE id=?')
            ->execute([$reason, current_user_id(), $pay_id]);

        if ($pmt['approval_status'] === 'approved') {
            // 2. Reverse ledger amount_paid
            $newPaid = max(0, round($pmt['led_paid'] - $pmt['amount'], 2));
            $newBalance = round($pmt['amount_due'] - $newPaid - $pmt['waiver_amount'], 2);
            $newStatus  = $newBalance <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            $pdo->prepare('UPDATE fee_ledgers SET amount_paid=?, status=? WHERE id=?')->execute([$newPaid, $newStatus, $pmt['ledger_id']]);

            // 3. Reverse account balance
            if ($pmt['account_id']) {
                $pdo->prepare('UPDATE accounts SET current_balance=current_balance-? WHERE id=?')->execute([$pmt['amount'], $pmt['account_id']]);
                $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,reference_table,reference_id,created_by) VALUES (?,?,"withdrawal",?,\'fee_payments\',?,?)')
                    ->execute([$pmt['account_id'], -$pmt['amount'], "VOID: Receipt {$pmt['receipt_number']} — $reason", $pay_id, current_user_id()]);
            }
        }

        // 4. Audit log
        $pdo->prepare('INSERT INTO payment_void_logs (entity_type,entity_id,action,old_status,new_status,amount_affected,performed_by,reason) VALUES ("payment",?,?,?,?,?,?,?)')
            ->execute([$pay_id, $pmt['approval_status'] === 'pending' ? 'void_pending' : 'void', $pmt['approval_status'], 'void', $pmt['amount'], current_user_id(), $reason]);

        $pdo->commit();
        log_activity('void_payment', 'finance', $pay_id, "RCP:{$pmt['receipt_number']}", "Reason:$reason");
        flash('success', "Payment {$pmt['receipt_number']} voided successfully. Ledger balance restored.");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Void failed: ' . $e->getMessage());
    }
    header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect);
    exit;
}

// ── Admin: Approve or reject a pending partial payment ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_partial') {
    csrf_check();
    require_auth(['setup.edit']);

    $pay_id  = int_param('payment_id', 0, $_POST);
    $verdict = $_POST['verdict'] ?? '';  // 'approve' | 'reject'
    $reason  = trim($_POST['review_note'] ?? '');
    $student_id_for_redirect = int_param('student_id', 0, $_POST);
    $session_redirect = int_param('session_id', (int)setting('current_session_id',0), $_POST);

    if (!$pay_id || !in_array($verdict, ['approve','reject'])) {
        flash('error', 'Invalid review action.');
        header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect);
        exit;
    }

    $pmt = $pdo->prepare('SELECT fp.*, fl.amount_due, fl.amount_paid AS led_paid, fl.waiver_amount FROM fee_payments fp JOIN fee_ledgers fl ON fl.id=fp.ledger_id WHERE fp.id=? AND fp.approval_status="pending"');
    $pmt->execute([$pay_id]);
    $pmt = $pmt->fetch();

    if (!$pmt) { flash('error', 'Pending payment not found.'); header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect); exit; }

    try {
        $pdo->beginTransaction();
        if ($verdict === 'approve') {
            $pdo->prepare('UPDATE fee_payments SET approval_status="approved" WHERE id=?')->execute([$pay_id]);
            // Apply amount to ledger
            $newPaid = round($pmt['led_paid'] + $pmt['amount'], 2);
            $newBalance = round($pmt['amount_due'] - $newPaid - $pmt['waiver_amount'], 2);
            $newStatus  = $newBalance <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            $pdo->prepare('UPDATE fee_ledgers SET amount_paid=?, status=? WHERE id=?')->execute([$newPaid, $newStatus, $pmt['ledger_id']]);
            if ($pmt['account_id']) {
                $pdo->prepare('UPDATE accounts SET current_balance=current_balance+? WHERE id=?')->execute([$pmt['amount'], $pmt['account_id']]);
                $pdo->prepare('INSERT INTO account_transactions (account_id,amount,transaction_type,description,created_by) VALUES (?,?,"deposit",?,?)')
                    ->execute([$pmt['account_id'], $pmt['amount'], "Approved partial payment {$pmt['receipt_number']}", current_user_id()]);
            }
            flash('success', "Partial payment {$pmt['receipt_number']} approved and applied to ledger.");
        } else {
            $pdo->prepare('UPDATE fee_payments SET approval_status="void",void_reason=?,voided_by=?,voided_at=NOW() WHERE id=?')->execute([$reason ?: 'Rejected by admin', current_user_id(), $pay_id]);
            flash('warning', "Partial payment {$pmt['receipt_number']} rejected. Ledger not affected.");
        }
        $pdo->prepare('INSERT INTO payment_void_logs (entity_type,entity_id,action,old_status,new_status,amount_affected,performed_by,reason) VALUES ("payment",?,?,?,?,?,?,?)')
            ->execute([$pay_id, $verdict==='approve'?'approve':'reject', 'pending', $verdict==='approve'?'approved':'void', $pmt['amount'], current_user_id(), $reason]);
        $pdo->commit();
        log_activity('review_partial_payment', 'finance', $pay_id, 'pending', $verdict);
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Review failed: ' . $e->getMessage());
    }
    header('Location: collect.php?student_id='.$student_id_for_redirect.'&session_id='.$session_redirect);
    exit;
}

// ── Handle multi-fee batch payment (with optional custom fees) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_batch') {
    csrf_check();

    $student_id     = int_param('student_id', 0, $_POST);
    $session_id_post= int_param('session_id', (int)setting('current_session_id',0), $_POST);
    $ledger_ids     = array_map('intval', array_filter((array)($_POST['ledger_ids'] ?? [])));
    $amounts        = $_POST['pay_amount']      ?? [];
    $method         = $_POST['payment_method']  ?? 'cash';
    $note           = trim($_POST['notes']       ?? '');
    $account_id     = int_param('account_id', 0, $_POST);

    // Custom ad-hoc fees
    $custom_cats    = $_POST['custom_cat']      ?? [];
    $custom_descs   = $_POST['custom_desc']     ?? [];
    $custom_amts    = $_POST['custom_amt']      ?? [];

    // Filter valid custom rows
    $custom_fees = [];
    foreach (array_keys($custom_descs) as $i) {
        $cat  = int_param($i, 0, $custom_cats);
        $desc = trim($custom_descs[$i] ?? '');
        $amt  = round((float)($custom_amts[$i] ?? 0), 2);
        if ($cat && $desc && $amt > 0) $custom_fees[] = compact('cat','desc','amt');
    }

    $hasRegular = !empty($ledger_ids);
    $hasCustom  = !empty($custom_fees);

    if (!$student_id && !$hasCustom) { $errors[] = 'Nothing to collect.'; }
    if ($student_id && !$hasRegular && !$hasCustom) { $errors[] = 'No fees selected or added.'; }
    if (!$account_id) { $errors[] = 'Please select a deposit account.'; }

    // Partial payment policy check
    $allowPartial   = setting('allow_partial_payment', 'no') === 'yes';
    $minPct         = (int)setting('partial_min_percent', '100');
    $needsApproval  = setting('partial_requires_approval', 'yes') === 'yes';

    if (empty($errors) && $hasRegular) {
        // Fetch balances for selected ledgers
        $inP = implode(',', array_fill(0, count($ledger_ids), '?'));
        $lRows = $pdo->prepare("SELECT id, amount_due, amount_paid, waiver_amount FROM fee_ledgers WHERE id IN ($inP)");
        $lRows->execute($ledger_ids);
        $lRowMap = array_column($lRows->fetchAll(), null, 'id');

        foreach ($ledger_ids as $lid) {
            $amt = (float)($amounts[$lid] ?? 0);
            if ($amt <= 0) { $errors[] = "Enter a valid amount for each selected fee."; break; }
            $lRow = $lRowMap[$lid] ?? null;
            if ($lRow) {
                $balance = round($lRow['amount_due'] - $lRow['amount_paid'] - $lRow['waiver_amount'], 2);
                $isPartial = $amt < $balance - 0.01;
                if ($isPartial && !$allowPartial) {
                    $errors[] = "Partial payment is disabled. You must pay the full balance for each fee.";
                    break;
                }
                if ($isPartial && $minPct < 100) {
                    $minAmt = round($balance * $minPct / 100, 2);
                    if ($amt < $minAmt - 0.01) {
                        $errors[] = "Minimum payment for one of the fees is " . money($minAmt) . " ({$minPct}% of balance).";
                        break;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $receipt_no = next_receipt_number();

            $insPayStmt = $pdo->prepare(
                'INSERT INTO fee_payments (ledger_id,student_id,amount,payment_date,payment_method,receipt_number,collected_by,notes,account_id,approval_status)
                 VALUES (?,?,?,CURDATE(),?,?,?,?,?,?)'
            );
            $updLedStmt = $pdo->prepare(
                'UPDATE fee_ledgers SET amount_paid=amount_paid+:a,
                 status=CASE WHEN (amount_paid+:b)>=(amount_due-waiver_amount) THEN "paid"
                             WHEN (amount_paid+:c)>0 THEN "partial" ELSE "unpaid" END
                 WHERE id=:lid'
            );

            $total = 0;
            $hasPending = false;

            // ── Regular ledger fees ─────────────────────────────
            foreach ($ledger_ids as $lid) {
                $amt = round((float)($amounts[$lid] ?? 0), 2);
                if ($amt <= 0) continue;
                // Check if this specific payment is partial
                $lRow = $lRowMap[$lid] ?? null;
                $isPartial = $lRow && $amt < round($lRow['amount_due'] - $lRow['amount_paid'] - $lRow['waiver_amount'], 2) - 0.01;
                $approvalStatus = ($isPartial && $needsApproval) ? 'pending' : 'approved';
                if ($approvalStatus === 'pending') $hasPending = true;

                $insPayStmt->execute([$lid, $student_id, $amt, $method, $receipt_no, current_user_id(), $note, $account_id, $approvalStatus]);
                if ($approvalStatus === 'approved') {
                    $updLedStmt->execute([':a'=>$amt,':b'=>$amt,':c'=>$amt,':lid'=>$lid]);
                    $total += $amt;
                }
            }

            // ── Custom / ad-hoc fees: create ledger entry on the fly ──
            $insLedStmt = $pdo->prepare(
                'INSERT INTO fee_ledgers (student_id,session_id,fee_category_id,amount_due,due_date,notes,status)
                 VALUES (?,?,?,?,CURDATE(),?,"paid")'
            );
            foreach ($custom_fees as $cf) {
                // Create instant ledger entry (already "paid")
                $insLedStmt->execute([$student_id, $session_id_post, $cf['cat'], $cf['amt'], $cf['desc']]);
                $newLid = (int)$pdo->lastInsertId();
                // Immediately pay it in full
                $insPayStmt->execute([$newLid, $student_id, $cf['amt'], $method, $receipt_no, current_user_id(), $cf['desc'], $account_id]);
                $pdo->prepare('UPDATE fee_ledgers SET amount_paid=? WHERE id=?')->execute([$cf['amt'], $newLid]);
                $total += $cf['amt'];
            }

            // Update Account Balance
            if ($account_id && $total > 0) {
                $pdo->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$total, $account_id]);
                
                // Log Account Ledger Transaction
                $tx = $pdo->prepare("
                    INSERT INTO account_transactions (account_id, amount, transaction_type, description, reference_table, reference_id, created_by) 
                    VALUES (?, ?, 'deposit', ?, 'fee_payments', ?, ?)
                ");
                // Reference ID is 0 or null as it's batch payment of multiple ledgers/receipts
                $tx->execute([$account_id, $total, "Student fee collection. Receipt: $receipt_no", null, current_user_id()]);
            }

            $pdo->commit();
            log_activity('fee_collected','finance',$student_id,'',money($total).' RCP:'.$receipt_no.($hasPending?' [PARTIAL-PENDING]':''));
            $pendingFlag = $hasPending ? '&pending=1' : '';
            header('Location: collect.php?student_id='.$student_id.'&session_id='.$session_id_post.'&paid='.urlencode($receipt_no).$pendingFlag);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
            $receipt_no = null;
        }
    }
}

// ── Handle misc / walk-in collection (no student required) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'misc_collect') {
    csrf_check();
    $misc_name   = trim($_POST['misc_name']   ?? '');
    $misc_desc   = trim($_POST['misc_desc']   ?? '');
    $misc_amt    = round((float)($_POST['misc_amt'] ?? 0), 2);
    $misc_cat    = int_param('misc_cat', 0, $_POST);
    $misc_method = $_POST['misc_method'] ?? 'cash';
    $sess_misc   = int_param('misc_session_id', (int)setting('current_session_id',0), $_POST);

    if (!$misc_desc || $misc_amt <= 0 || !$misc_cat) {
        $errors[] = 'Please fill in description, category, and amount.';
    }
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $receipt_no = next_receipt_number();
            // Record in incomes table as a general receipt
            $pdo->prepare(
                'INSERT INTO incomes (session_id,income_category_id,amount,income_date,description,received_by)
                 SELECT :sess,(SELECT id FROM income_categories LIMIT 1),:amt,CURDATE(),:desc,:uid
                 FROM DUAL'
            )->execute([':sess'=>$sess_misc,':amt'=>$misc_amt,':desc'=>"[$receipt_no] $misc_name — $misc_desc",':uid'=>current_user_id()]);

            // Also log as activity with receipt ref
            log_activity('misc_collection','finance',0,'',money($misc_amt).' RCP:'.$receipt_no);

            // Store in session for receipt display
            $_SESSION['misc_receipt'] = [
                'receipt_no' => $receipt_no,
                'name'       => $misc_name,
                'desc'       => $misc_desc,
                'amount'     => $misc_amt,
                'method'     => $misc_method,
                'date'       => date('Y-m-d'),
                'by'         => $_SESSION['full_name'] ?? 'Accounts',
                'school'     => setting('school_name','School'),
                'addr'       => setting('school_address',''),
                'tel'        => setting('school_phone',''),
                'cur'        => setting('currency_symbol','৳'),
            ];
            $pdo->commit();
            header('Location: collect.php?misc_rcp='.urlencode($receipt_no));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error: '.$e->getMessage();
        }
    }
}

// ── Misc receipt print page ──────────────────────────────────────────────────
if (!empty($_GET['print_misc'])) {
    $mr = $_SESSION['misc_receipt'] ?? null;
    if ($mr) {
        header('Content-Type: text/html; charset=utf-8');
        // render inline and exit — see bottom of file
    }
}

$student_id = int_param('student_id', 0, $_GET);
$session_id = int_param('session_id', (int)setting('current_session_id', 0), $_GET);
$receipt_no = $receipt_no ?? (trim($_GET['paid'] ?? '') ?: null);
$misc_rcp   = trim($_GET['misc_rcp'] ?? '');
$searchQ    = trim($_GET['student_search'] ?? '');

// Fee categories for custom fee dropdown
$feeCategories = $pdo->query('SELECT id,category_name,category_type FROM fee_categories WHERE status=1 ORDER BY category_name')->fetchAll();
$sessions      = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

$student = null;
$ledgers = [];

if ($student_id) {
    $s = $pdo->prepare(
        'SELECT u.id, u.full_name, sp.student_id_no, sp.guardian_phone,
                c.class_name, sec.section_name, se.roll_number
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.session_id=:sess AND se.status="active"
         LEFT JOIN classes c   ON c.id   = se.class_id
         LEFT JOIN sections sec ON sec.id = se.section_id
         WHERE u.id = :uid LIMIT 1'
    );
    $s->execute([':uid' => $student_id, ':sess' => $session_id]);
    $student = $s->fetch();

    if ($student) {
        $l = $pdo->prepare(
            'SELECT fl.*, fc.category_name, fc.category_type,
                    (fl.amount_due - fl.amount_paid - fl.waiver_amount) AS balance,
                    fl.month, fl.year
             FROM fee_ledgers fl
             JOIN fee_categories fc ON fc.id = fl.fee_category_id
             WHERE fl.student_id = :sid AND fl.session_id = :sess
             ORDER BY fl.due_date, fc.category_name'
        );
        $l->execute([':sid' => $student_id, ':sess' => $session_id]);
        $ledgers = $l->fetchAll();
    }
}

// Payment history for selected student (all sessions, with void/pending details)
$paymentHistory = [];
if ($student_id) {
    $ph = $pdo->prepare(
        "SELECT fp.id AS payment_id, fp.receipt_number, fp.payment_date, fp.payment_method,
                fp.approval_status, fp.void_reason,
                GROUP_CONCAT(fc.category_name ORDER BY fc.category_name SEPARATOR ', ') AS fee_categories,
                SUM(fp.amount) AS total_paid,
                COUNT(fp.id) AS line_count
         FROM fee_payments fp
         JOIN fee_ledgers fl    ON fl.id  = fp.ledger_id
         JOIN fee_categories fc ON fc.id  = fl.fee_category_id
         WHERE fp.student_id = :sid
         GROUP BY fp.id, fp.receipt_number, fp.payment_date, fp.payment_method, fp.approval_status, fp.void_reason
         ORDER BY fp.payment_date DESC, fp.id DESC
         LIMIT 100"
    );
    $ph->execute([':sid' => $student_id]);
    $paymentHistory = $ph->fetchAll();
}
$pendingPartial = !empty($_GET['pending']);

// Student search results
$searchResults = [];
if ($searchQ) {
    $sr = $pdo->prepare(
        'SELECT u.id, u.full_name, sp.student_id_no, c.class_name, sec.section_name
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.session_id=:sess AND se.status="active"
         LEFT JOIN classes c   ON c.id   = se.class_id
         LEFT JOIN sections sec ON sec.id = se.section_id
         WHERE (sp.first_name LIKE :q OR sp.last_name LIKE :q OR sp.student_id_no LIKE :q OR sp.guardian_phone LIKE :q)
         LIMIT 20'
    );
    $sr->execute([':sess' => $session_id, ':q' => "%$searchQ%"]);
    $searchResults = $sr->fetchAll();
}

require_once EMS_ROOT . '/includes/header.php';
?>

<h1 class="page-title"><i class="bi bi-cash-coin me-2 text-primary"></i>Fee Collection</h1>

<?php if ($pendingPartial): ?>
<div class="alert alert-warning d-flex align-items-center gap-3">
  <i class="bi bi-clock-history fs-4"></i>
  <div>
    <strong>Partial Payment Pending Approval</strong> — Receipt <strong><?= e($receipt_no) ?></strong> recorded but requires admin approval before the ledger is updated.
    Notify admin to review at <a href="collect.php?student_id=<?= $student_id ?>&session_id=<?= $session_id ?>">payment history</a>.
  </div>
</div>
<?php endif; ?>

<?php if ($receipt_no): ?>
<div class="card mb-3 border-success" style="border-width:2px!important;">
  <div class="card-body py-3 d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-check-circle-fill text-success" style="font-size:2rem;"></i>
      <div>
        <div class="fw-700 text-success fs-5">Payment Collected Successfully</div>
        <div class="text-muted small">Receipt No: <strong class="text-dark"><?= e($receipt_no) ?></strong></div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
      <a href="receipt.php?rcp=<?= urlencode($receipt_no) ?>" target="_blank" class="btn btn-primary btn-lg">
        <i class="bi bi-printer me-2"></i>Print Receipt
      </a>
      <a href="collect.php?student_id=<?= $student_id ?>&session_id=<?= $session_id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-plus-lg me-1"></i>New Collection
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($misc_rcp && isset($_SESSION['misc_receipt']) && $_SESSION['misc_receipt']['receipt_no'] === $misc_rcp): ?>
<?php $mr = $_SESSION['misc_receipt']; ?>
<div class="card mb-3 border-info" style="border-width:2px!important;">
  <div class="card-body py-3 d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-check-circle-fill text-info" style="font-size:2rem;"></i>
      <div>
        <div class="fw-700 text-info fs-5">Misc Collection Recorded</div>
        <div class="text-muted small">
          <?= e($mr['name'] ? $mr['name'].' — ' : '') ?><?= e($mr['desc']) ?> &nbsp;|&nbsp;
          <strong><?= e($mr['cur'].' '.number_format($mr['amount'],2)) ?></strong> &nbsp;|&nbsp;
          Receipt: <strong class="text-dark"><?= e($misc_rcp) ?></strong>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
      <a href="misc_receipt.php?rcp=<?= urlencode($misc_rcp) ?>" target="_blank" class="btn btn-info text-white btn-lg">
        <i class="bi bi-printer me-2"></i>Print Receipt
      </a>
      <a href="collect.php" class="btn btn-outline-secondary">
        <i class="bi bi-plus-lg me-1"></i>New Collection
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-3">

  <!-- ── Student search panel ───────────────────── -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Find Student</span></div>
      <div class="card-body">
        <form method="GET" class="mb-3">
          <div class="mb-2">
            <label class="form-label small">Session</label>
            <select name="session_id" class="form-select form-select-sm">
              <?php foreach ($sessions as $sess): ?>
                <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="input-group">
            <input type="text" name="student_search" class="form-control" placeholder="Name, ID, phone…" value="<?= e($searchQ) ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
          </div>
        </form>

        <?php if (!empty($searchResults)): ?>
        <div class="list-group list-group-flush" style="max-height:350px;overflow-y:auto;">
          <?php foreach ($searchResults as $sr): ?>
          <a href="?student_id=<?= $sr['id'] ?>&session_id=<?= $session_id ?>"
             class="list-group-item list-group-item-action py-2 <?= $student_id == $sr['id'] ? 'active' : '' ?>">
            <div class="fw-600"><?= e($sr['full_name']) ?></div>
            <small><?= e($sr['student_id_no'] ?? 'No ID') ?> | <?= e($sr['class_name'] ?? '?') ?> - <?= e($sr['section_name'] ?? '?') ?></small>
          </a>
          <?php endforeach; ?>
        </div>
        <?php elseif ($searchQ): ?>
        <div class="text-muted small text-center py-2">No students found</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Payment History (collapsible, shown when student is selected) ── -->
    <?php if ($student_id && !empty($paymentHistory)): ?>
    <div class="card mt-3">
      <div class="card-header py-0 px-0" style="background:none;border:none;">
        <button class="btn w-100 text-start d-flex align-items-center justify-content-between px-4 py-3 fw-600"
                type="button" data-bs-toggle="collapse" data-bs-target="#payHistoryPanel"
                aria-expanded="<?= $receipt_no ? 'true' : 'true' ?>">
          <span>
            <i class="bi bi-clock-history me-2 text-primary"></i>
            Payment History
            <span class="badge bg-secondary ms-1"><?= count($paymentHistory) ?></span>
          </span>
          <i class="bi bi-chevron-down" id="hist-chevron"></i>
        </button>
      </div>
      <div class="collapse show" id="payHistoryPanel">
        <div class="list-group list-group-flush" style="max-height:380px;overflow-y:auto;">
          <?php foreach ($paymentHistory as $ph):
            $isLatest   = $receipt_no && $ph['receipt_number'] === $receipt_no;
            $isVoid     = $ph['approval_status'] === 'void';
            $isPending  = $ph['approval_status'] === 'pending';
            $isApproved = $ph['approval_status'] === 'approved';
            $methodLabel = ['cash'=>'Cash','bank'=>'Bank','mobile_banking'=>'Mobile Banking',
                            'cheque'=>'Cheque','online'=>'Online'][$ph['payment_method']] ?? ucfirst($ph['payment_method']);
            $rowCls = $isVoid ? 'list-group-item-secondary' : ($isPending ? 'list-group-item-warning' : ($isLatest ? 'list-group-item-success' : ''));
          ?>
          <div class="list-group-item py-2 px-3 <?= $rowCls ?>">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div style="min-width:0;flex:1;">
                <div class="fw-700 small d-flex align-items-center gap-1 flex-wrap">
                  <?php if ($isLatest && !$isPending && !$isVoid): ?>
                    <span class="badge bg-success" style="font-size:.6rem;">NEW</span>
                  <?php endif; ?>
                  <?php if ($isVoid): ?>
                    <span class="badge bg-secondary" style="font-size:.6rem;">VOID</span>
                  <?php elseif ($isPending): ?>
                    <span class="badge bg-warning text-dark" style="font-size:.6rem;">PENDING</span>
                  <?php endif; ?>
                  <span class="<?= $isVoid?'text-decoration-line-through text-muted':'' ?>">
                    <?= e(setting('currency_symbol','৳').' '.number_format($ph['total_paid'],2)) ?>
                  </span>
                  <span class="text-muted fw-400" style="font-size:.72rem;">· <?= e($methodLabel) ?></span>
                </div>
                <div class="text-muted" style="font-size:.7rem;margin-top:1px;">
                  <?= e(fmt_date($ph['payment_date'],'d M Y')) ?> · <?= $ph['line_count'] ?> fee<?= $ph['line_count']>1?'s':'' ?>
                </div>
                <div style="font-size:.68rem;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:165px;" title="<?= e($ph['fee_categories']) ?>">
                  <?= e($ph['fee_categories']) ?>
                </div>
                <div style="font-size:.65rem;font-family:'Courier New',monospace;color:#94a3b8;">
                  <?= e($ph['receipt_number']) ?>
                </div>
                <?php if ($isVoid && $ph['void_reason']): ?>
                  <div style="font-size:.65rem;color:#ef4444;margin-top:1px;">Void: <?= e($ph['void_reason']) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-column gap-1 flex-shrink-0">
                <?php if (!$isVoid): ?>
                  <a href="receipt.php?rcp=<?= urlencode($ph['receipt_number']) ?>" target="_blank"
                     class="btn btn-sm <?= $isLatest?'btn-success':'btn-outline-secondary' ?>"
                     style="font-size:.68rem;padding:.2rem .45rem;" title="Print Receipt">
                    <i class="bi bi-printer"></i>
                  </a>
                <?php endif; ?>
                <?php if ($isPending && has_permission('setup.edit')): ?>
                  <button class="btn btn-xs btn-success" style="font-size:.65rem;padding:.15rem .35rem;"
                          onclick="openApproveModal(<?= $ph['payment_id'] ?>, '<?= e(addslashes($ph['receipt_number'])) ?>', <?= $ph['total_paid'] ?>)"
                          title="Approve partial payment">✓</button>
                  <button class="btn btn-xs btn-danger" style="font-size:.65rem;padding:.15rem .35rem;"
                          onclick="openRejectModal(<?= $ph['payment_id'] ?>, '<?= e(addslashes($ph['receipt_number'])) ?>')"
                          title="Reject partial payment">✗</button>
                <?php endif; ?>
                <?php if ($isApproved && has_permission('setup.edit')): ?>
                  <button class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.15rem .35rem;"
                          onclick="openVoidModal(<?= $ph['payment_id'] ?>, '<?= e(addslashes($ph['receipt_number'])) ?>')"
                          title="Void this payment">
                    <i class="bi bi-x-circle"></i>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php elseif ($student_id): ?>
    <div class="card mt-3">
      <div class="card-body py-3 text-center text-muted small">
        <i class="bi bi-clock-history me-1"></i> No payment history yet
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Fee ledger + multi-select ─────────────── -->
  <div class="col-md-8">
    <?php if ($student): ?>

    <!-- Student header -->
    <div class="card mb-3">
      <div class="card-body py-3 d-flex align-items-center gap-3">
        <div class="topbar-avatar" style="width:44px;height:44px;font-size:1.1rem;">
          <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
        </div>
        <div>
          <div class="fw-700 fs-5"><?= e($student['full_name']) ?></div>
          <div class="text-muted small">
            <?= e($student['student_id_no'] ?? 'No ID') ?> &nbsp;|&nbsp;
            <?= e($student['class_name'] ?? '?') ?> – <?= e($student['section_name'] ?? '?') ?> &nbsp;|&nbsp;
            Roll: <strong><?= e($student['roll_number'] ?? '?') ?></strong>
            <?php if ($student['guardian_phone']): ?>&nbsp;|&nbsp; <?= e($student['guardian_phone']) ?><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($ledgers)): ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-cash-stack"></i>
        <p>No fee records for this session.</p>
        <a href="structures.php" class="btn btn-sm btn-primary mt-2">Set Up Fee Structure</a>
      </div>
    </div></div>

    <?php else: ?>

    <!-- Multi-select fee form -->
    <form method="POST" id="payForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="pay_batch">
      <input type="hidden" name="student_id" value="<?= $student_id ?>">

      <div class="card table-card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
          <span class="card-title">Fee Ledger</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(true)">Select All Due</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)">Clear</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th style="width:36px;"></th>
                <th>Category</th>
                <th>Period</th>
                <th>Due</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Pay Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ledgers as $led):
                $balance = max(0, $led['amount_due'] - $led['amount_paid'] - $led['waiver_amount']);
                $isDue   = $balance > 0;
                $period  = $led['month'] ? date('M Y', mktime(0,0,0,$led['month'],1,$led['year'])) : '';
              ?>
              <tr class="<?= !$isDue ? 'text-muted' : '' ?>">
                <td class="text-center">
                  <?php if ($isDue): ?>
                  <input type="checkbox" class="form-check-input fee-cb"
                         name="ledger_ids[]"
                         value="<?= $led['id'] ?>"
                         data-balance="<?= $balance ?>"
                         data-lid="<?= $led['id'] ?>"
                         onchange="onCheck(this)"
                         <?= $isDue ? 'checked' : '' ?>>
                  <?php else: ?>
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <?php endif; ?>
                </td>
                <td class="fw-600"><?= e($led['category_name']) ?></td>
                <td class="text-muted small"><?= $period ?: '—' ?></td>
                <td><?= money($led['amount_due']) ?></td>
                <td class="text-success"><?= money($led['amount_paid']) ?></td>
                <td class="<?= $isDue ? 'text-danger fw-600' : 'text-success' ?>">
                  <?= money($balance) ?>
                </td>
                <td style="min-width:110px;">
                  <?php if ($isDue): ?>
                  <input type="number"
                         name="pay_amount[<?= $led['id'] ?>]"
                         id="amt_<?= $led['id'] ?>"
                         class="form-control form-control-sm pay-input"
                         value="<?= number_format($balance, 2, '.', '') ?>"
                         min="0.01"
                         max="<?= $balance ?>"
                         step="0.01"
                         data-lid="<?= $led['id'] ?>"
                         onchange="recalcTotal()"
                         oninput="recalcTotal()">
                  <?php else: ?>
                  <span class="text-muted small">Paid</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-status badge-<?= $led['status'] === 'paid' ? 'paid' : ($led['status'] === 'partial' ? 'partial' : 'unpaid') ?>">
                    <?= ucfirst(e($led['status'])) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Custom / Ad-hoc Fees ──────────────────────── -->
      <div class="card mb-3">
        <div class="card-header py-0 px-0" style="background:none;border:none;">
          <button type="button"
                  class="btn w-100 text-start d-flex align-items-center justify-content-between px-4 py-3"
                  data-bs-toggle="collapse" data-bs-target="#customFeePanel">
            <span class="fw-600">
              <i class="bi bi-plus-circle-fill me-2 text-warning"></i>
              Add Custom / Ad-hoc Fee
              <small class="text-muted fw-400 ms-1">— fine, damage, misc, etc.</small>
            </span>
            <i class="bi bi-chevron-down"></i>
          </button>
        </div>
        <div class="collapse" id="customFeePanel">
          <div class="card-body pt-0">
            <div id="custom-fee-rows"></div>
            <button type="button" class="btn btn-sm btn-outline-warning mt-2" onclick="addCustomRow()">
              <i class="bi bi-plus-lg me-1"></i>Add Fee Row
            </button>
            <div class="text-muted small mt-2">
              <i class="bi bi-info-circle me-1"></i>
              Custom fees create an instant ledger entry for this student and are included in the same receipt.
            </div>
          </div>
        </div>
      </div>

      <!-- Payment summary bar -->
      <div class="card">
        <div class="card-body py-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-2">
              <label class="form-label small">Selected Fees</label>
              <div class="fw-700 fs-5" id="selected-count">0 items</div>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Total to Collect</label>
              <div class="fw-700 fs-4 text-success" id="total-display"><?= e(setting('currency_symbol','৳')) ?> 0.00</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select">
                <option value="cash">Cash</option>
                <option value="bank">Bank Transfer</option>
                <option value="mobile_banking">bKash / Nagad / Rocket</option>
                <option value="cheque">Cheque</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Deposit Account <span class="text-danger">*</span></label>
              <select name="account_id" class="form-select" required>
                <?php 
                $accList = db()->query("SELECT id, account_name, current_balance FROM accounts ORDER BY account_name")->fetchAll();
                foreach ($accList as $ac): 
                ?>
                  <option value="<?= $ac['id'] ?>"><?= e($ac['account_name']) ?> (৳<?= number_format($ac['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Note <small class="text-muted">(optional)</small></label>
              <input type="text" name="notes" class="form-control" placeholder="remarks">
            </div>
            <div class="col-12">
              <button type="submit" id="payBtn" class="btn btn-success btn-lg px-5" disabled
                      onclick="if(confirm('Confirm payment?')){this.disabled=true;this.form.submit();return true;}else{return false;}">
                <i class="bi bi-cash-coin me-2"></i>
                <span id="payBtnText">Collect Payment</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <?php else: ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i class="bi bi-search"></i>
        <p>Search for a student on the left to view and collect fees.</p>
      </div>
    </div></div>
    <?php endif; ?>
  </div><!-- /col-md-8 -->

</div><!-- /row -->

<!-- ── Misc / Walk-in Collection (no student required) ──────────────── -->
<div class="card mt-4" style="border:2px solid #e2e8f0;">
  <div class="card-header py-0 px-0" style="background:none;border:none;">
    <button type="button"
            class="btn w-100 text-start d-flex align-items-center justify-content-between px-4 py-3 fw-600"
            data-bs-toggle="collapse" data-bs-target="#miscCollectPanel">
      <span>
        <i class="bi bi-people-fill me-2 text-secondary"></i>
        Misc / Walk-in Collection
        <small class="text-muted fw-400 ms-1">— fees not tied to any student</small>
      </span>
      <i class="bi bi-chevron-down"></i>
    </button>
  </div>
  <div class="collapse" id="miscCollectPanel">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Use this for collecting fees from visitors, walk-in payments, donations, event tickets, or any
        collection that doesn't belong to a specific student account.
      </p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="misc_collect">
        <input type="hidden" name="misc_session_id" value="<?= $session_id ?>">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">From / Payer Name</label>
            <input type="text" name="misc_name" class="form-control" placeholder="e.g. Parent name, Visitor, Event">
          </div>
          <div class="col-md-4">
            <label class="form-label">Description <span class="text-danger">*</span></label>
            <input type="text" name="misc_desc" class="form-control" placeholder="e.g. Annual Sports Day ticket, Library fine" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select name="misc_cat" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($feeCategories as $fc): ?>
                <option value="<?= $fc['id'] ?>"><?= e($fc['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><?= e(setting('currency_symbol','৳')) ?></span>
              <input type="number" name="misc_amt" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Method</label>
            <select name="misc_method" class="form-select">
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
              <option value="mobile_banking">Mobile Banking</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-secondary">
              <i class="bi bi-cash me-1"></i>Record & Get Receipt
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CUR = '<?= e(setting('currency_symbol','৳')) ?>';

// Build the fee category options HTML once
const FEE_CAT_OPTIONS = `<option value="">— Category —</option><?php foreach($feeCategories as $fc): ?><option value="<?= $fc['id'] ?>"><?= e($fc['category_name']) ?></option><?php endforeach; ?>`;

let customRowIdx = 0;

function addCustomRow() {
  customRowIdx++;
  const idx = customRowIdx;
  const row = document.createElement('div');
  row.className = 'custom-row d-flex gap-2 align-items-center mb-2';
  row.dataset.idx = idx;
  row.innerHTML = `
    <select name="custom_cat[${idx}]" class="form-select form-select-sm" style="max-width:170px;" required>
      ${FEE_CAT_OPTIONS}
    </select>
    <input type="text" name="custom_desc[${idx}]" class="form-control form-control-sm"
           placeholder="Description (e.g. Library fine, Damage fee)" required>
    <div class="input-group input-group-sm" style="max-width:130px;">
      <span class="input-group-text">${CUR}</span>
      <input type="number" name="custom_amt[${idx}]" class="form-control custom-amt-input"
             placeholder="0.00" min="0.01" step="0.01" required oninput="recalcTotal()">
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0"
            onclick="removeCustomRow(this)">
      <i class="bi bi-x-lg"></i>
    </button>`;
  document.getElementById('custom-fee-rows').appendChild(row);
  // Open the collapse if not open
  const panel = document.getElementById('customFeePanel');
  if (panel && !panel.classList.contains('show')) {
    bootstrap.Collapse.getOrCreateInstance(panel).show();
  }
  recalcTotal();
}

function removeCustomRow(btn) {
  btn.closest('.custom-row').remove();
  recalcTotal();
}

function recalcTotal() {
  let total = 0, count = 0;

  // Regular ledger fees
  document.querySelectorAll('.fee-cb:checked').forEach(cb => {
    const inp = document.getElementById('amt_' + cb.dataset.lid);
    if (inp) { total += parseFloat(inp.value) || 0; count++; }
  });

  // Custom ad-hoc fees
  document.querySelectorAll('.custom-amt-input').forEach(inp => {
    const v = parseFloat(inp.value) || 0;
    if (v > 0) { total += v; count++; }
  });

  const totalEl = document.getElementById('total-display');
  const countEl = document.getElementById('selected-count');
  const btn     = document.getElementById('payBtn');
  const btnTxt  = document.getElementById('payBtnText');

  if (totalEl)  totalEl.textContent  = CUR + ' ' + total.toFixed(2);
  if (countEl)  countEl.textContent  = count + ' item' + (count !== 1 ? 's' : '');
  if (btn)      btn.disabled         = (count === 0 || total <= 0);
  if (btnTxt)   btnTxt.textContent   = count > 0 ? 'Collect ' + CUR + ' ' + total.toFixed(2) : 'Collect Payment';
}

function onCheck(cb) {
  const inp = document.getElementById('amt_' + cb.dataset.lid);
  if (inp) inp.disabled = !cb.checked;
  recalcTotal();
}

function selectAll(state) {
  document.querySelectorAll('.fee-cb').forEach(cb => {
    cb.checked = state;
    const inp = document.getElementById('amt_' + cb.dataset.lid);
    if (inp) inp.disabled = !state;
  });
  recalcTotal();
}

document.addEventListener('DOMContentLoaded', () => {
  // Disable unchecked fee inputs
  document.querySelectorAll('.fee-cb').forEach(cb => {
    if (!cb.checked) {
      const inp = document.getElementById('amt_' + cb.dataset.lid);
      if (inp) inp.disabled = true;
    }
  });
  recalcTotal();

  // Chevron on history collapse
  const histPanel = document.getElementById('payHistoryPanel');
  const histChev  = document.getElementById('hist-chevron');
  if (histPanel && histChev) {
    histPanel.addEventListener('show.bs.collapse', () => histChev.className = 'bi bi-chevron-up');
    histPanel.addEventListener('hide.bs.collapse', () => histChev.className = 'bi bi-chevron-down');
  }

  <?php if ($receipt_no): ?>
  // Scroll to NEW badge in history
  const newBadge = document.querySelector('.list-group-item-success');
  if (newBadge) newBadge.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  <?php endif; ?>
});
</script>

<?php if (has_permission('setup.edit')): ?>
<!-- Void Payment Modal -->
<div class="modal fade" id="voidModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="void_payment">
        <input type="hidden" name="payment_id" id="void-pay-id">
        <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header bg-danger text-white py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-x-circle me-2"></i>Void Payment</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>This will reverse the ledger balance and deduct from account. This action is permanent and logged.</div>
          <p class="text-muted small mb-3">Receipt: <strong id="void-receipt-no" class="text-dark"></strong></p>
          <div class="mb-0">
            <label class="form-label small fw-600">Reason for Void <span class="text-danger">*</span></label>
            <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="e.g. Double entry, Wrong student, Error" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirm void? This will reverse the ledger balance.')">Void Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Approve Partial Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review_partial">
        <input type="hidden" name="verdict" value="approve">
        <input type="hidden" name="payment_id" id="approve-pay-id">
        <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header bg-success text-white py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-check-circle me-2"></i>Approve Partial Payment</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2">Receipt: <strong id="approve-receipt-no" class="text-dark"></strong></p>
          <p class="small text-muted mb-3">Amount: <strong id="approve-amount" class="text-success"></strong></p>
          <p class="text-muted small mb-0">Approving will apply this payment to the fee ledger and credit the account balance.</p>
          <div class="mb-0 mt-3">
            <label class="form-label small fw-600">Approval Note <small class="text-muted">(optional)</small></label>
            <input type="text" name="review_note" class="form-control form-control-sm" placeholder="e.g. Approved by principal">
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Approve & Apply</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Partial Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review_partial">
        <input type="hidden" name="verdict" value="reject">
        <input type="hidden" name="payment_id" id="reject-pay-id">
        <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header bg-warning py-2">
          <h6 class="modal-title fw-600"><i class="bi bi-x-circle me-2"></i>Reject Partial Payment</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-3">Receipt: <strong id="reject-receipt-no" class="text-dark"></strong></p>
          <p class="small text-warning mb-3"><i class="bi bi-info-circle me-1"></i>The ledger will NOT be updated. The payment will be marked void.</p>
          <div class="mb-0">
            <label class="form-label small fw-600">Rejection Reason <span class="text-danger">*</span></label>
            <input type="text" name="review_note" class="form-control form-control-sm" placeholder="e.g. Insufficient amount, policy violation" required>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm">Reject Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openVoidModal(payId, receiptNo) {
  document.getElementById('void-pay-id').value = payId;
  document.getElementById('void-receipt-no').textContent = receiptNo;
  new bootstrap.Modal(document.getElementById('voidModal')).show();
}
function openApproveModal(payId, receiptNo, amount) {
  document.getElementById('approve-pay-id').value = payId;
  document.getElementById('approve-receipt-no').textContent = receiptNo;
  document.getElementById('approve-amount').textContent = '৳ ' + amount.toFixed(2);
  new bootstrap.Modal(document.getElementById('approveModal')).show();
}
function openRejectModal(payId, receiptNo) {
  document.getElementById('reject-pay-id').value = payId;
  document.getElementById('reject-receipt-no').textContent = receiptNo;
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>
<?php endif; ?>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
