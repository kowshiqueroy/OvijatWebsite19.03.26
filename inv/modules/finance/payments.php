<?php
/**
 * modules/finance/payments.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Accountant']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT id, name, balance FROM customers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$customers = $stmt->fetchAll();
?>

<ul class="nav nav-tabs mb-4" id="financeTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment-panel" type="button">
            <i class="fas fa-money-bill me-1"></i> Receive Payment
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="adjust-tab" data-bs-toggle="tab" data-bs-target="#adjust-panel" type="button">
            <i class="fas fa-calculator me-1"></i> Adjust Balance
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Receive Payment Tab -->
    <div class="tab-pane fade show active" id="payment-panel">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0 fw-bold">Receive Payment from Customer</h5>
                    </div>
                    <div class="card-body">
                        <form id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="payment_customer_id" class="form-select" required>
                                    <option value="">-- Choose Customer --</option>
                                    <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-balance="<?php echo $c['balance']; ?>">
                                        <?php echo $c['name']; ?> (Balance: <?php echo formatCurrency($c['balance']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Payment Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Payment Method</label>
                                    <select name="method" class="form-select">
                                        <option value="Cash">Cash</option>
                                        <option value="Bank">Bank Transfer</option>
                                        <option value="Mobile">Mobile Payment (bKash/Rocket)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Note / Description</label>
                                <textarea name="description" class="form-control" rows="2" placeholder="e.g. Payment for Invoice #456"></textarea>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success px-5">Record Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Balance Tab -->
    <div class="tab-pane fade" id="adjust-panel">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0 fw-bold">Manual Debit/Credit Balance Adjustment</h5>
                    </div>
                    <div class="card-body">
                        <form id="adjustForm">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" id="adjust_customer_id" class="form-select" required>
                                    <option value="">-- Choose Customer --</option>
                                    <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" data-balance="<?php echo $c['balance']; ?>">
                                        <?php echo $c['name']; ?> (Balance: <?php echo formatCurrency($c['balance']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Transaction Type <span class="text-danger">*</span></label>
                                    <select name="type" class="form-select" required>
                                        <option value="credit">Credit (+)</option>
                                        <option value="debit">Debit (-)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="amount" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Reason <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Opening Balance, Discount, Bad Debt" required>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-warning px-5">Adjust Balance</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/finance.php?action=record_payment', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'ledger.php?customer_id=' + $('#payment_customer_id').val();
            } else {
                alert(res.message);
            }
        }, 'json');
    });

    $('#adjustForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/finance.php?action=adjust_balance', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>