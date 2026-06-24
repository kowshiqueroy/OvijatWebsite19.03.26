<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

// Handle supplier payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_supplier'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/accounts/payables.php', 'CSRF failed.', 'danger');

    $supplier_id = intval($_POST['supplier_id']);
    $amount      = floatval($_POST['amount']);
    $description = sanitize($_POST['description'] ?? '');
    $pay_from    = intval($_POST['pay_from'] ?? 1);

    if ($amount <= 0) redirect('modules/accounts/payables.php', 'Invalid amount.', 'danger');

    $conn = get_db_connection();
    $conn->begin_transaction();
    try {
        // Reduce supplier balance
        db_query("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$amount, $supplier_id]);
        db_query("INSERT INTO supplier_transactions (supplier_id,type,amount,description) VALUES (?,'Payment',?,?)",
            [$supplier_id, $amount, $description ?: "Payment"]);

        // Auto-post journal: DR AP / CR Cash/Bank
        $ap_account = get_supplier_ap_account($supplier_id);
        if ($ap_account && $pay_from) {
            post_journal(date('Y-m-d'), "Supplier payment — " . ($description ?: "Payment"), 'Payment', $supplier_id, [
                ['account_id' => $ap_account, 'dr' => $amount, 'cr' => 0,      'note' => 'AP settlement'],
                ['account_id' => $pay_from,   'dr' => 0,       'cr' => $amount, 'note' => 'Cash/Bank'],
            ]);
        }

        $conn->commit();
        log_activity($_SESSION['user_id'], "Paid supplier #$supplier_id ৳$amount");
        redirect('modules/accounts/payables.php', format_currency($amount) . " payment recorded for supplier.");
    } catch (Exception $e) {
        $conn->rollback();
        redirect('modules/accounts/payables.php', "Error: " . $e->getMessage(), 'danger');
    }
}

$suppliers = fetch_all("SELECT * FROM suppliers WHERE isDelete=0 AND balance != 0 ORDER BY balance DESC");
$all_suppliers = fetch_all("SELECT id, name FROM suppliers WHERE isDelete=0 AND is_active=1 ORDER BY name");
$pay_accounts  = fetch_all("SELECT a.id, a.name FROM accounts a JOIN account_groups ag ON a.group_id=ag.id WHERE ag.name='Cash & Bank' AND a.isDelete=0 ORDER BY a.name");
$total_payable = array_sum(array_column($suppliers, 'balance'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Supplier Payables</h3>
        <p class="text-muted small mb-0">Total Outstanding: <strong class="text-danger"><?php echo format_currency($total_payable); ?></strong></p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payModal">
        <i class="fa-solid fa-money-bill-wave me-2"></i>Record Payment
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Supplier</th><th>Phone</th><th class="text-end">Balance Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr class="<?php echo $s['balance'] > 50000 ? 'table-danger' : ($s['balance'] > 10000 ? 'table-warning' : ''); ?>">
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($s['phone']); ?></td>
                        <td class="text-end fw-bold text-danger"><?php echo format_currency($s['balance']); ?></td>
                        <td><span class="badge bg-<?php echo $s['balance'] > 0 ? 'danger' : 'success'; ?>"><?php echo $s['balance'] > 0 ? 'Overdue' : 'Clear'; ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary quick-pay-btn"
                                data-id="<?php echo $s['id']; ?>"
                                data-name="<?php echo htmlspecialchars($s['name']); ?>"
                                data-balance="<?php echo $s['balance']; ?>"
                                data-bs-toggle="modal" data-bs-target="#payModal">
                                <i class="fa-solid fa-circle-dollar-to-slot me-1"></i>Pay
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-success"><i class="fa-solid fa-circle-check me-2"></i>All suppliers fully paid.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($suppliers)): ?>
                <tfoot class="table-secondary fw-bold">
                    <tr><td colspan="2" class="text-end">Total Payable</td><td class="text-end text-danger"><?php echo format_currency($total_payable); ?></td><td colspan="2"></td></tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title">Record Supplier Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" id="pay_sup" class="form-select select2" required>
                        <option value="">— Select —</option>
                        <?php foreach ($all_suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (৳) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="amount" id="pay_amt" class="form-control" required>
                    <small class="text-muted" id="pay_bal_hint"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pay From</label>
                    <select name="pay_from" class="form-select">
                        <?php foreach ($pay_accounts as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option><?php endforeach; ?>
                        <option value="1">Cash in Hand</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Partial payment for Invoice INV-001">
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="pay_supplier" class="btn btn-primary">Record Payment</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.quick-pay-btn');
    if (!btn) return;
    document.getElementById('pay_sup').value = btn.dataset.id;
    document.getElementById('pay_amt').value = parseFloat(btn.dataset.balance).toFixed(2);
    document.getElementById('pay_bal_hint').textContent = 'Outstanding balance: ৳' + parseFloat(btn.dataset.balance).toLocaleString();
    if (typeof $ !== 'undefined' && $.fn.select2) $('#pay_sup').trigger('change');
});
</script>
<?php require_once '../../templates/footer.php'; ?>
