<?php
/**
 * ID Card Generator Selection Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'ID Cards';
$currentPage = 'id-cards';

$employees = getAllEmployees(['status' => 'Active']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>ID Card Generator</h4>
        <small class="text-muted">Select employees and generate printable ID cards</small>
    </div>
</div>

<form method="GET" action="id-cards-print.php" target="_blank" class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-check2-square me-2"></i>Select Employees</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <input type="text" id="empSearch" class="form-control mb-2" placeholder="Search employees...">
                <select name="id" id="empSelect" class="form-select" multiple size="10">
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['emp_name']); ?> - <?php echo htmlspecialchars($emp['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted d-block">Hold Ctrl/Cmd to select multiple</small>
                <div class="mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAll()">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAll()">Clear</button>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Options</label>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="includeQR" name="qr" value="1" checked>
                    <label class="form-check-label" for="includeQR">Include QR Code</label>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-success w-100" onclick="generateCards()">
                    <i class="bi bi-printer me-1"></i> Generate
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('empSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    Array.from(document.getElementById('empSelect').options).forEach(opt => {
        opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
    });
});

function selectAll() {
    Array.from(document.getElementById('empSelect').options).forEach(opt => {
        if (opt.style.display !== 'none') opt.selected = true;
    });
}

function clearAll() {
    Array.from(document.getElementById('empSelect').options).forEach(opt => opt.selected = false);
}

function generateCards() {
    const select = document.getElementById('empSelect');
    const selected = Array.from(select.selectedOptions).map(opt => opt.value);
    if (selected.length === 0) {
        alert('Please select at least one employee');
        return;
    }
    const qr = document.getElementById('includeQR').checked ? 1 : 0;
    const url = 'id-cards-print.php?id=' + selected.join(',') + '&qr=' + qr;
    window.open(url, '_blank');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>