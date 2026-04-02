<?php
/**
 * Bonus Management Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Manage Bonuses';
$currentPage = 'bonuses';

$filter = [
    'office'     => $_GET['office']     ?? '',
    'department' => $_GET['department'] ?? '',
];

$selectedMonth = $_GET['month'] ?? '';

$offices     = getOfficeList();
$departments = getDepartmentList($filter['office']);

$message     = '';
$messageType = 'success';

// ── Setup check ──────────────────────────────────────────────────────────────
$bonusSheetsReady = tableExists('bonus_sheets');

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $conn    = getDBConnection();
    $month   = $_POST['month'] ?? '';
    $adminId = (int)$_SESSION['admin_id'];

    // ── save_bonuses ──────────────────────────────────────────────────────────
    if (isset($_POST['save_bonuses']) && $month) {
        $employees = getAllEmployees(array_merge($filter, ['status' => 'Active']));

        $newCount    = 0;
        $updateCount = 0;
        $skipCount   = 0;

        foreach ($employees as $emp) {
            $empId = (int)$emp['id'];

            $basicSalary = isset($_POST["basic_{$empId}"]) && $_POST["basic_{$empId}"] !== ''
                ? (float)$_POST["basic_{$empId}"]
                : (float)$emp['basic_salary'];

            $bonusPct = isset($_POST["pct_{$empId}"]) && $_POST["pct_{$empId}"] !== ''
                ? (float)$_POST["pct_{$empId}"]
                : 0.0;

            $bonusAmt = isset($_POST["amt_{$empId}"]) && $_POST["amt_{$empId}"] !== ''
                ? (float)$_POST["amt_{$empId}"]
                : 0.0;

            $bonusType   = sanitize($_POST["type_{$empId}"]   ?? 'Festival');
            $description = sanitize($_POST["desc_{$empId}"]   ?? '');

            // Check existing
            $chk = $conn->prepare("SELECT id, confirmed FROM bonus_sheets WHERE employee_id = ? AND month = ?");
            $chk->bind_param("is", $empId, $month);
            $chk->execute();
            $chkRes  = $chk->get_result();
            $exists  = $chkRes->num_rows > 0;
            $existing = $exists ? $chkRes->fetch_assoc() : null;
            $chk->close();

            // Skip confirmed rows
            if ($existing && $existing['confirmed'] == 1) {
                $skipCount++;
                continue;
            }

            // Skip zero-amount entries — don't create/update empty records
            if ($bonusAmt <= 0) {
                $skipCount++;
                continue;
            }

            if ($exists) {
                // UPDATE: "dddssis"
                $upd = $conn->prepare("UPDATE bonus_sheets SET
                    basic_salary = ?, bonus_pct = ?, bonus_amount = ?,
                    bonus_type = ?, description = ?
                    WHERE employee_id = ? AND month = ?");
                $upd->bind_param("dddssis",
                    $basicSalary, $bonusPct, $bonusAmt,
                    $bonusType, $description,
                    $empId, $month
                );
                if ($upd->execute()) {
                    $updateCount++;
                }
                $upd->close();
            } else {
                // INSERT: "isdddssi"
                $ins = $conn->prepare("INSERT INTO bonus_sheets
                    (employee_id, month, basic_salary, bonus_pct, bonus_amount, bonus_type, description, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("isdddssi",
                    $empId, $month,
                    $basicSalary, $bonusPct, $bonusAmt,
                    $bonusType, $description, $adminId
                );
                if ($ins->execute()) {
                    $newCount++;
                }
                $ins->close();
            }
        }

        $message = "{$newCount} new, {$updateCount} updated, {$skipCount} skipped (confirmed).";
        if ($newCount === 0 && $updateCount === 0 && $skipCount > 0) {
            $messageType = 'warning';
        }
    }

    // ── confirm_all ───────────────────────────────────────────────────────────
    if (isset($_POST['confirm_all']) && $month) {
        // confirm_all bind: "is" (admin_id INT, month VARCHAR)
        $stmt = $conn->prepare("UPDATE bonus_sheets SET confirmed = 1, confirmed_by = ?, confirmed_at = NOW() WHERE month = ? AND confirmed = 0");
        $stmt->bind_param("is", $adminId, $month);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $message = "{$affected} bonus record(s) confirmed successfully.";
    }

    // ── confirm_selected ─────────────────────────────────────────────────────
    if (isset($_POST['confirm_selected']) && $month && !empty($_POST['confirm_ids'])) {
        $ids = array_map('intval', (array)$_POST['confirm_ids']);
        $ids = array_filter($ids, fn($id) => $id > 0);
        $affected = 0;
        foreach ($ids as $cid) {
            $s = $conn->prepare("UPDATE bonus_sheets SET confirmed=1, confirmed_by=?, confirmed_at=NOW() WHERE id=? AND confirmed=0");
            $s->bind_param("ii", $adminId, $cid);
            $s->execute();
            $affected += $s->affected_rows;
            $s->close();
        }
        $message = "{$affected} bonus record(s) confirmed.";
    }

    // ── unconfirm_all ─────────────────────────────────────────────────────────
    if (isset($_POST['unconfirm_all']) && $month) {
        // unconfirm_all bind: "s" (month VARCHAR)
        $stmt = $conn->prepare("UPDATE bonus_sheets SET confirmed = 0, confirmed_by = NULL, confirmed_at = NULL WHERE month = ? AND confirmed = 1");
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $message     = "{$affected} bonus record(s) un-confirmed for correction.";
        $messageType = 'warning';
    }

    // Preserve GET params after POST redirect
    $qs = http_build_query([
        'month'      => $month,
        'office'     => $filter['office'],
        'department' => $filter['department'],
    ]);
    header("Location: bonuses.php?{$qs}&msg=" . urlencode($message) . "&mt=" . $messageType);
    exit;
}

// ── Restore flash message from redirect ───────────────────────────────────────
if (!empty($_GET['msg'])) {
    $message     = htmlspecialchars($_GET['msg']);
    $messageType = in_array($_GET['mt'] ?? '', ['success','danger','warning','info']) ? $_GET['mt'] : 'success';
}

// ── Load employees & bonus sheets for selected month ──────────────────────────
$employees         = (!empty($selectedMonth) && $bonusSheetsReady) ? getAllEmployees(array_merge($filter, ['status' => 'Active'])) : [];
$sheetsByEmployee  = [];

if (!empty($selectedMonth) && $bonusSheetsReady) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, employee_id, basic_salary, bonus_pct, bonus_amount, bonus_type, description, confirmed FROM bonus_sheets WHERE month = ?");
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sheetsByEmployee[$row['employee_id']] = $row;
    }
    $stmt->close();
}

// ── Confirmation status summary ───────────────────────────────────────────────
$allConfirmed  = false;
$anyConfirmed  = false;
$anyUnconfirmed = false;

if (!empty($employees) && !empty($sheetsByEmployee)) {
    $allConfirmed = true;
    foreach ($employees as $emp) {
        $sheet = $sheetsByEmployee[$emp['id']] ?? null;
        if ($sheet) {
            if ($sheet['confirmed'] == 1) {
                $anyConfirmed = true;
            } else {
                $allConfirmed  = false;
                $anyUnconfirmed = true;
            }
        } else {
            $allConfirmed  = false;
            $anyUnconfirmed = true;
        }
    }
    if (!$anyConfirmed) {
        $allConfirmed = false;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><i class="bi bi-gift me-2"></i>Bonus Management</h4>
        <small class="text-muted">Festival, performance, and special bonuses — per month</small>
    </div>
</div>

<?php if (!$bonusSheetsReady): ?>
    <div class="alert alert-warning d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong>Database setup required.</strong>
            The <code>bonus_sheets</code> table does not exist yet.
            Please <a href="../config/setup.php" class="alert-link">run setup</a> (with Reset enabled) to create it.
        </div>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<!-- Filter form (GET) -->
<form method="GET" action="" class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Employees</h5>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Month <span class="text-danger">*</span></label>
                <?php
                $bonusMonths    = getBonusMonths();
                $standardMonths = generateMonthOptions();
                $standardValues = array_column($standardMonths, 'value');
                $extraMonths    = array_filter($bonusMonths, fn($m) => !in_array($m, $standardValues));
                ?>
                <select name="month" class="form-select" required>
                    <option value="">-- Select Month --</option>
                    <?php foreach ($standardMonths as $opt): ?>
                        <?php if (in_array($opt['value'], $bonusMonths)): ?>
                            <option value="<?php echo $opt['value']; ?>" <?php echo $selectedMonth === $opt['value'] ? 'selected' : ''; ?>>
                                <?php echo $opt['label']; ?> (Existing)
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $opt['value']; ?>" <?php echo $selectedMonth === $opt['value'] ? 'selected' : ''; ?>>
                                <?php echo $opt['label']; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($extraMonths)): ?>
                        <optgroup label="── Older Entries ──">
                            <?php foreach ($extraMonths as $em): ?>
                                <option value="<?php echo $em; ?>" <?php echo $selectedMonth === $em ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($em . '-01')); ?> (Existing)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Office</label>
                <select name="office" class="form-select filter-select">
                    <option value="">All Offices</option>
                    <?php foreach ($offices as $off): ?>
                        <option value="<?php echo htmlspecialchars($off['office_name']); ?>"
                            <?php echo $filter['office'] === $off['office_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($off['office_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select name="department" class="form-select filter-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                            <?php echo $filter['department'] === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </div>
    </div>
</form>

<?php if (empty($selectedMonth)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-gift fs-1 d-block mb-3"></i>
            <h5>Select a Month</h5>
            <p>Please select a month above to view employees and manage their bonuses.</p>
        </div>
    </div>

<?php elseif (empty($employees)): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-people fs-1 d-block mb-2"></i>
            No active employees found with the selected filters.
        </div>
    </div>

<?php else: ?>

    <!-- Status banner -->
    <?php if ($anyConfirmed && $allConfirmed): ?>
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="bi bi-lock-fill me-2"></i>
            <strong>All Confirmed (locked).</strong>&nbsp; Every bonus entry for this month has been confirmed.
        </div>
    <?php elseif ($anyConfirmed && $anyUnconfirmed): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Partial confirmation.</strong>&nbsp; Some entries are confirmed, others are still pending.
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
        <?php if (!empty($filter['office'])): ?>
            <input type="hidden" name="office" value="<?php echo htmlspecialchars($filter['office']); ?>">
        <?php endif; ?>
        <?php if (!empty($filter['department'])): ?>
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($filter['department']); ?>">
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>
                    Employees
                    <span class="badge bg-secondary ms-1"><?php echo count($employees); ?></span>
                    &mdash; <span class="text-muted fw-normal fs-6"><?php echo date('F Y', strtotime($selectedMonth . '-01')); ?></span>
                </h5>
                <?php if (!$allConfirmed): ?>
                    <button type="submit" name="save_bonuses" class="btn btn-success">
                        <i class="bi bi-floppy me-1"></i> Save Bonuses
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px;" class="text-center">
                                <input type="checkbox" id="selectAll" title="Select all saved">
                            </th>
                            <th style="min-width:160px;">Employee</th>
                            <th style="width:110px;">Basic Salary</th>
                            <th style="width:80px;">Bonus %</th>
                            <th style="width:110px;">Bonus Amt</th>
                            <th style="width:140px;">Type</th>
                            <th>Description</th>
                            <th style="width:100px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                            $empId      = (int)$emp['id'];
                            $sheet      = $sheetsByEmployee[$empId] ?? null;
                            $isConfirmed = $sheet && $sheet['confirmed'] == 1;

                            $basic      = $sheet ? (float)$sheet['basic_salary']  : (float)$emp['basic_salary'];
                            $pct        = $sheet ? (float)$sheet['bonus_pct']     : 0.0;
                            $amt        = $sheet ? (float)$sheet['bonus_amount']  : 0.0;
                            $bType      = $sheet ? htmlspecialchars($sheet['bonus_type'])   : 'Festival';
                            $desc       = $sheet ? htmlspecialchars($sheet['description'])  : '';

                            $ro = $isConfirmed ? 'readonly' : '';
                            ?>
                            <tr class="<?php echo $isConfirmed ? 'table-success' : ''; ?>">
                                <td class="text-center align-middle">
                                    <?php if ($sheet && !$isConfirmed): ?>
                                        <input type="checkbox" class="row-check" name="confirm_ids[]"
                                               value="<?php echo (int)$sheet['id']; ?>">
                                    <?php endif; ?>
                                </td>
                                <!-- Employee cell (compact style matching salary-generate) -->
                                <td style="min-width:160px;">
                                    <span class="badge bg-dark d-inline-block mb-1" style="font-size:10px;">
                                        <?php echo htmlspecialchars(generateEmployeeID($emp['id'], $emp['office_code'], $emp['dept_code'])); ?>
                                    </span>
                                    <div style="font-size:12px;font-weight:600;line-height:1.3;">
                                        <?php echo htmlspecialchars($emp['emp_name']); ?>
                                    </div>
                                    <div class="text-muted" style="font-size:10px;line-height:1.3;">
                                        <?php echo htmlspecialchars($emp['department']); ?><br>
                                        <?php echo htmlspecialchars($emp['position']); ?> &middot; <?php echo htmlspecialchars($emp['office_name']); ?>
                                    </div>
                                </td>

                                <!-- Basic Salary -->
                                <td>
                                    <input type="number" name="basic_<?php echo $empId; ?>"
                                           class="form-control form-control-sm bonus-basic"
                                           value="<?php echo $basic; ?>" step="0.01" min="0"
                                           <?php echo $ro; ?>>
                                </td>

                                <!-- Bonus % -->
                                <td>
                                    <input type="number" name="pct_<?php echo $empId; ?>"
                                           class="form-control form-control-sm bonus-pct"
                                           value="<?php echo $pct; ?>" step="0.01" min="0" max="100"
                                           <?php echo $ro; ?>>
                                </td>

                                <!-- Bonus Amount -->
                                <td>
                                    <input type="number" name="amt_<?php echo $empId; ?>"
                                           class="form-control form-control-sm bonus-amt"
                                           value="<?php echo $amt; ?>" step="0.01" min="0"
                                           <?php echo $ro; ?>>
                                </td>

                                <!-- Type -->
                                <td>
                                    <?php if ($isConfirmed): ?>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $bType; ?>" readonly>
                                        <input type="hidden" name="type_<?php echo $empId; ?>" value="<?php echo $bType; ?>">
                                    <?php else: ?>
                                        <select name="type_<?php echo $empId; ?>" class="form-select form-select-sm">
                                            <?php foreach (['Festival','Performance','Special','Annual','Other'] as $t): ?>
                                                <option value="<?php echo $t; ?>" <?php echo $bType === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>

                                <!-- Description -->
                                <td>
                                    <input type="text" name="desc_<?php echo $empId; ?>"
                                           class="form-control form-control-sm"
                                           value="<?php echo $desc; ?>" maxlength="255"
                                           placeholder="Optional note"
                                           <?php echo $ro; ?>>
                                </td>

                                <!-- Status -->
                                <td class="text-center align-middle">
                                    <?php if ($isConfirmed): ?>
                                        <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>Confirmed</span>
                                    <?php elseif ($sheet): ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">New</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer actions -->
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                    Bonus Amt = Basic &times; Bonus% &divide; 100 &nbsp;|&nbsp; Changing either field recalculates the other.
                    Confirmed rows are locked.
                </small>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($anyUnconfirmed): ?>
                        <button type="submit" name="save_bonuses" class="btn btn-success">
                            <i class="bi bi-floppy me-1"></i> Save Bonuses
                        </button>
                        <button type="submit" name="confirm_selected" class="btn btn-outline-primary" id="confirmSelBtn" disabled
                                onclick="return confirm('Confirm selected bonus entries?');">
                            <i class="bi bi-check-square me-1"></i> Confirm Selected
                        </button>
                        <button type="submit" name="confirm_all" class="btn btn-primary"
                                onclick="return confirm('Confirm all pending bonus entries for this month?');">
                            <i class="bi bi-check-all me-1"></i> Confirm All
                        </button>
                    <?php endif; ?>
                    <?php if ($anyConfirmed): ?>
                        <button type="submit" name="unconfirm_all"
                                class="btn btn-outline-warning"
                                onclick="return confirm('Un-confirm all confirmed entries for this month to allow corrections?');">
                            <i class="bi bi-unlock me-1"></i> Unconfirm All
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>

<style>
.border-warning { border: 2px solid #ffc107 !important; }
</style>

<script>
// Select All checkbox
const selectAllChk = document.getElementById('selectAll');
const confirmSelBtn = document.getElementById('confirmSelBtn');

function updateConfirmBtn() {
    const any = document.querySelectorAll('.row-check:checked').length > 0;
    if (confirmSelBtn) confirmSelBtn.disabled = !any;
}

if (selectAllChk) {
    selectAllChk.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
        updateConfirmBtn();
    });
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('row-check')) {
        updateConfirmBtn();
        // Uncheck "select all" if any row is unchecked
        if (selectAllChk && !e.target.checked) selectAllChk.checked = false;
    }
});

// Bidirectional bonus % / amount
document.addEventListener('input', function (e) {
    const isPct = e.target.classList.contains('bonus-pct');
    const isAmt = e.target.classList.contains('bonus-amt');
    const isBasic = e.target.classList.contains('bonus-basic');

    if (!isPct && !isAmt && !isBasic) return;

    const row      = e.target.closest('tr');
    if (!row) return;

    const basicIn  = row.querySelector('.bonus-basic');
    const pctIn    = row.querySelector('.bonus-pct');
    const amtIn    = row.querySelector('.bonus-amt');

    if (!basicIn || !pctIn || !amtIn) return;

    const basic = parseFloat(basicIn.value) || 0;

    if (isPct) {
        // Pct changed → recalculate amount
        const pct = parseFloat(pctIn.value) || 0;
        amtIn.value = basic > 0 ? (basic * pct / 100).toFixed(2) : '0.00';
    } else if (isAmt) {
        // Amount changed → recalculate pct
        const amt = parseFloat(amtIn.value) || 0;
        pctIn.value = basic > 0 ? (amt / basic * 100).toFixed(2) : '0.00';
    } else if (isBasic) {
        // Basic changed → keep pct, recalculate amount
        const pct = parseFloat(pctIn.value) || 0;
        if (pct > 0) {
            amtIn.value = (basic * pct / 100).toFixed(2);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
