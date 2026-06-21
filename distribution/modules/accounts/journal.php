<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// ── Verify Entry ────────────────────────────────────────────────────────────
if (isset($_POST['verify_entry'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        redirect('modules/accounts/journal.php', 'CSRF validation failed.', 'danger');
    }
    $eid = intval($_POST['entry_id']);
    db_query("UPDATE journal_entries SET is_verified=1, verified_by=?, verified_at=NOW() WHERE id=?",
        [$_SESSION['user_id'], $eid]);
    redirect('modules/accounts/journal.php', 'Journal entry verified.', 'success');
}

// ── Filters ─────────────────────────────────────────────────────────────────
$start_date  = $_GET['start_date']  ?? date('Y-m-01');
$end_date    = $_GET['end_date']    ?? date('Y-m-d');
$ref_type    = $_GET['ref_type']    ?? '';
$search      = $_GET['search']      ?? '';

$sql = "SELECT je.*,
               u.username AS created_by_name,
               (SELECT COALESCE(SUM(jl.dr_amount),0) FROM journal_lines jl WHERE jl.journal_id = je.id AND jl.isDelete=0) AS total_dr,
               (SELECT COALESCE(SUM(jl.cr_amount),0) FROM journal_lines jl WHERE jl.journal_id = je.id AND jl.isDelete=0) AS total_cr
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        WHERE je.isDelete = 0
          AND DATE(je.date) BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($ref_type !== '') {
    $sql .= " AND je.reference_type = ?";
    $params[] = $ref_type;
}
if ($search !== '') {
    $sql .= " AND (je.entry_no LIKE ? OR je.narration LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY je.date DESC, je.id DESC";

$entries = fetch_all($sql, $params);
$ref_types = fetch_all("SELECT DISTINCT reference_type FROM journal_entries WHERE isDelete=0 ORDER BY reference_type");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-book me-2 text-primary"></i>Journal Entries</h4>
    <a href="add_journal.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Journal Entry
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Reference Type</label>
                <select name="ref_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach ($ref_types as $rt): ?>
                    <option value="<?php echo htmlspecialchars($rt['reference_type']); ?>" <?php echo $ref_type == $rt['reference_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rt['reference_type']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Search Entry/Narration</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search); ?>" placeholder="JV-2025-0001 or narration...">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-1">
                <a href="journal.php" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Row -->
<?php
$grand_dr = array_sum(array_column($entries, 'total_dr'));
$grand_cr = array_sum(array_column($entries, 'total_cr'));
?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25 shadow-sm">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total Entries</div>
                <div class="fw-bold text-primary fs-5"><?php echo count($entries); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10 border-success border-opacity-25 shadow-sm">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total Debit</div>
                <div class="fw-bold text-success fs-6"><?php echo format_currency($grand_dr); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10 border-danger border-opacity-25 shadow-sm">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total Credit</div>
                <div class="fw-bold text-danger fs-6"><?php echo format_currency($grand_cr); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-<?php echo abs($grand_dr - $grand_cr) < 0.01 ? 'success' : 'warning'; ?> bg-opacity-10 shadow-sm">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Balance Check</div>
                <div class="fw-bold text-<?php echo abs($grand_dr - $grand_cr) < 0.01 ? 'success' : 'warning'; ?>">
                    <?php echo abs($grand_dr - $grand_cr) < 0.01 ? '✓ Balanced' : '⚠ Diff: ' . format_currency(abs($grand_dr - $grand_cr)); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Entry No.</th>
                        <th>Date</th>
                        <th>Narration</th>
                        <th>Ref. Type</th>
                        <th class="text-end">Total Dr</th>
                        <th class="text-end">Total Cr</th>
                        <th>Status</th>
                        <th>By</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">No journal entries found for the selected period.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $i => $je): ?>
                <tr>
                    <td class="text-muted small"><?php echo $i + 1; ?></td>
                    <td><a href="view_journal.php?id=<?php echo $je['id']; ?>" class="fw-semibold text-primary text-decoration-none"><?php echo htmlspecialchars($je['entry_no']); ?></a></td>
                    <td class="small"><?php echo date('d M Y', strtotime($je['date'])); ?></td>
                    <td class="small"><?php echo htmlspecialchars(mb_strimwidth($je['narration'], 0, 60, '...')); ?></td>
                    <td>
                        <?php if ($je['reference_type']): ?>
                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars($je['reference_type']); ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Manual</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-success fw-semibold"><?php echo format_currency($je['total_dr']); ?></td>
                    <td class="text-end text-danger fw-semibold"><?php echo format_currency($je['total_cr']); ?></td>
                    <td>
                        <?php if ($je['is_verified'] ?? false): ?>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($je['created_by_name'] ?? '—'); ?></td>
                    <td class="text-center">
                        <a href="view_journal.php?id=<?php echo $je['id']; ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (!($je['is_verified'] ?? false)): ?>
                        <form method="POST" class="d-inline">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="entry_id" value="<?php echo $je['id']; ?>">
                            <button type="submit" name="verify_entry" class="btn btn-xs btn-outline-success btn-sm py-0 px-2" title="Verify"
                                    onclick="return confirm('Mark this entry as verified?')">
                                <i class="fas fa-check-double"></i>
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
</div>

<?php require_once '../../templates/footer.php'; ?>
