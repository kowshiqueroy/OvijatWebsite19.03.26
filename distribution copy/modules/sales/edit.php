<?php
require_once '../../templates/header.php';
check_login();

$id = $_GET['id'] ?? 0;
$draft = fetch_one("SELECT * FROM sales_drafts WHERE id = ? AND status = 'Draft'", [$id]);

if (!$draft) {
    redirect('modules/sales/index.php', 'Draft not found or already confirmed.', 'danger');
}

// Permission Check
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($role == ROLE_SR || $role == ROLE_CUSTOMER) {
    if ($draft['created_by'] != $user_id) {
        redirect('modules/sales/index.php', 'You can only edit your own drafts.', 'danger');
    }
}

$items = fetch_all("SELECT i.*, p.name as product_name FROM sales_items i JOIN products p ON i.product_id = p.id WHERE i.draft_id = ? AND i.isDelete = 0 AND p.isDelete = 0", [$id]);
$customers = fetch_all("SELECT id, name, phone, type, balance FROM customers WHERE is_active = 1");
$products = fetch_all("SELECT id, name, tp_rate, dp_rate, retail_rate, stock_qty FROM products WHERE is_active = 1");
?>

<div class="row">
    <div class="col-12 mb-3">
        <h3>Edit Sales Draft #<?php echo $id; ?></h3>
    </div>
</div>

<form id="pos-form" action="update_draft.php" method="POST">
    <input type="hidden" name="draft_id" value="<?php echo $id; ?>">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Customer Lookup</label>
                    <?php 
                    $selected_cust = fetch_one("SELECT * FROM customers WHERE id = ?", [$draft['customer_id']]);
                    if ($role == ROLE_CUSTOMER): ?>
                        <input type="text" class="form-control" value="<?php echo $selected_cust['name']; ?>" readonly>
                        <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $selected_cust['id']; ?>">
                        <input type="hidden" id="customer_type" value="<?php echo $selected_cust['type']; ?>">
                    <?php else: ?>
                        <input type="text" id="customer_search" class="form-control" placeholder="Type Name or Phone..." list="customer-list" value="<?php echo $selected_cust['name']; ?> (<?php echo $selected_cust['phone']; ?>)" required>
                        <datalist id="customer-list">
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['name']; ?> (<?php echo $c['phone']; ?>)" data-id="<?php echo $c['id']; ?>" data-type="<?php echo $c['type']; ?>" data-balance="<?php echo $c['balance']; ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $selected_cust['id']; ?>">
                        <input type="hidden" id="customer_type" value="<?php echo $selected_cust['type']; ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <input type="text" id="display_customer_type" class="form-control" value="<?php echo $selected_cust['type']; ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Balance</label>
                    <input type="text" id="display_balance" class="form-control" value="<?php echo $selected_cust['balance']; ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 pos-grid-table" id="items-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 30%;">Product Name</th>
                            <th style="width: 15%;">Note</th>
                            <th style="width: 10%;">Rate</th>
                            <th style="width: 10%;">Billed Qty</th>
                            <th style="width: 10%;">Free Qty</th>
                            <th style="width: 15%;">Total</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="pos-items-body">
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td class="text-center align-middle row-index"><?php echo $idx + 1; ?></td>
                            <td>
                                <input type="text" class="product-search" placeholder="Search Product..." list="product-list" value="<?php echo $item['product_name']; ?>" required>
                                <input type="hidden" name="product_id[]" class="product-id" value="<?php echo $item['product_id']; ?>">
                            </td>
                            <td><input type="text" name="note[]" value="<?php echo $item['note']; ?>"></td>
                            <td><input type="number" step="0.01" name="rate[]" class="rate" value="<?php echo $item['rate']; ?>" required></td>
                            <td><input type="number" name="billed_qty[]" class="billed-qty" value="<?php echo $item['billed_qty']; ?>" required></td>
                            <td><input type="number" name="free_qty[]" class="free-qty" value="<?php echo $item['free_qty']; ?>"></td>
                            <td><input type="number" step="0.01" name="total[]" class="row-total" value="<?php echo $item['total']; ?>" readonly></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm text-danger remove-row"><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="add-row-btn">
                                    <i class="fas fa-plus me-1"></i> Add Item (F2)
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
             <div class="card shadow-sm h-100">
                 <div class="card-body">
                     <label class="form-label">General Note</label>
                     <textarea name="general_note" class="form-control" rows="4"></textarea>
                 </div>
             </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Sub Total</span>
                        <input type="number" step="0.01" name="sub_total" id="sub_total" class="form-control w-50 text-end" readonly value="<?php echo $draft['total_amount']; ?>">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount</span>
                        <input type="number" step="0.01" name="discount" id="discount" class="form-control w-50 text-end" value="<?php echo $draft['discount']; ?>">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>VAT (%)</span>
                        <input type="number" step="0.01" name="vat_percent" id="vat_percent" class="form-control w-50 text-end" value="<?php echo $draft['vat']; ?>">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <h4 class="mb-0">Grand Total</h4>
                        <input type="number" step="0.01" name="grand_total" id="grand_total" class="form-control w-50 text-end fw-bold fs-4 text-primary" readonly value="<?php echo $draft['grand_total']; ?>">
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <a href="index.php" class="btn btn-secondary btn-lg w-50">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg w-50">
                            <i class="fas fa-save me-2"></i> Update Draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Template and Scripts same as pos.php -->
<template id="product-row-template">
    <tr>
        <td class="text-center align-middle row-index">1</td>
        <td>
            <input type="text" class="product-search" placeholder="Search Product..." list="product-list" required>
            <input type="hidden" name="product_id[]" class="product-id">
        </td>
        <td>
            <input type="text" name="note[]" class="product-note" list="note-list-dynamic">
            <datalist class="note-suggestions"></datalist>
        </td>
        <td><input type="number" step="0.01" name="rate[]" class="rate" required></td>
        <td><input type="number" name="billed_qty[]" class="billed-qty" required></td>
        <td><input type="number" name="free_qty[]" class="free-qty" value="0"></td>
        <td><input type="number" step="0.01" name="total[]" class="row-total" readonly value="0.00"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm text-danger remove-row"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<datalist id="product-list">
    <?php foreach ($products as $p): ?>
        <option value="<?php echo $p['name']; ?>" data-id="<?php echo $p['id']; ?>" data-tp="<?php echo $p['tp_rate']; ?>" data-dp="<?php echo $p['dp_rate']; ?>" data-retail="<?php echo $p['retail_rate']; ?>" data-stock="<?php echo $p['stock_qty']; ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
// (JS logic from pos.php should be duplicated or centralized)
// For now, I'll copy the core parts
document.addEventListener('DOMContentLoaded', function() {
    const itemsBody = document.getElementById('pos-items-body');
    const addRowBtn = document.getElementById('add-row-btn');
    const template = document.getElementById('product-row-template');
    
    // Customer Selection Logic
    const customerSearch = document.getElementById('customer_search');
    if (customerSearch) {
        customerSearch.addEventListener('input', function() {
            const val = this.value;
            const options = document.getElementById('customer-list').options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === val) {
                    document.getElementById('customer_id').value = options[i].getAttribute('data-id');
                    document.getElementById('customer_type').value = options[i].getAttribute('data-type');
                    document.getElementById('display_customer_type').value = options[i].getAttribute('data-type');
                    document.getElementById('display_balance').value = options[i].getAttribute('data-balance');
                    updateAllRates();
                    break;
                }
            }
        });
    }

    function addRow() {
        const clone = template.content.cloneNode(true);
        itemsBody.appendChild(clone);
        updateIndices();
        const newRow = itemsBody.lastElementChild;
        newRow.querySelector('.product-search').focus();
    }

    function updateIndices() {
        const rows = itemsBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelector('.row-index').textContent = index + 1;
        });
    }

    addRowBtn.addEventListener('click', addRow);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            addRow();
        }
    });

    itemsBody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            e.target.closest('tr').remove();
            updateIndices();
            calculateGrandTotal();
        }
    });

    itemsBody.addEventListener('input', function(e) {
        const row = e.target.closest('tr');
        
        if (e.target.classList.contains('product-search')) {
            const val = e.target.value;
            const options = document.getElementById('product-list').options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === val) {
                    const productId = options[i].getAttribute('data-id');
                    row.querySelector('.product-id').value = productId;
                    
                    const custType = document.getElementById('customer_type').value || 'Retail';
                    let rate = 0;
                    if (custType === 'TP') rate = options[i].getAttribute('data-tp');
                    else if (custType === 'DP') rate = options[i].getAttribute('data-dp');
                    else rate = options[i].getAttribute('data-retail');
                    
                    row.querySelector('.rate').value = rate;

                    // Fetch Product Notes (NEW)
                    const noteInput = row.querySelector('.product-note');
                    const noteDatalist = row.querySelector('.note-suggestions');
                    const uniqueId = 'notes-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
                    noteInput.setAttribute('list', uniqueId);
                    noteDatalist.id = uniqueId;

                    fetch('get_product_notes.php?product_id=' + productId)
                        .then(response => response.json())
                        .then(data => {
                            noteInput.value = data.latest; // Set default to last note
                            noteDatalist.innerHTML = ''; // Clear suggestions
                            data.history.forEach(note => {
                                let opt = document.createElement('option');
                                opt.value = note;
                                noteDatalist.appendChild(opt);
                            });
                        });
                    break;
                }
            }
        }

        if (e.target.classList.contains('billed-qty') || e.target.classList.contains('rate')) {
            const rate = parseFloat(row.querySelector('.rate').value) || 0;
            const qty = parseInt(row.querySelector('.billed-qty').value) || 0;
            row.querySelector('.row-total').value = (rate * qty).toFixed(2);
            calculateGrandTotal();
        }
    });

    function calculateGrandTotal() {
        let subtotal = 0;
        document.querySelectorAll('.row-total').forEach(input => {
            subtotal += parseFloat(input.value) || 0;
        });
        
        document.getElementById('sub_total').value = subtotal.toFixed(2);
        
        const discount = parseFloat(document.getElementById('discount').value) || 0;
        const vatPercent = parseFloat(document.getElementById('vat_percent').value) || 0;
        
        const afterDiscount = subtotal - discount;
        const vatAmount = (afterDiscount * vatPercent) / 100;
        
        document.getElementById('grand_total').value = (afterDiscount + vatAmount).toFixed(2);
    }

    document.getElementById('discount').addEventListener('input', calculateGrandTotal);
    document.getElementById('vat_percent').addEventListener('input', calculateGrandTotal);

    function updateAllRates() {
        const custType = document.getElementById('customer_type').value;
        const rows = itemsBody.querySelectorAll('tr');
        rows.forEach(row => {
            const productSearch = row.querySelector('.product-search').value;
            if (productSearch) {
                const options = document.getElementById('product-list').options;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === productSearch) {
                        let rate = 0;
                        if (custType === 'TP') rate = options[i].getAttribute('data-tp');
                        else if (custType === 'DP') rate = options[i].getAttribute('data-dp');
                        else rate = options[i].getAttribute('data-retail');
                        row.querySelector('.rate').value = rate;
                        
                        const qty = parseInt(row.querySelector('.billed-qty').value) || 0;
                        row.querySelector('.row-total').value = (rate * qty).toFixed(2);
                        break;
                    }
                }
            }
        });
        calculateGrandTotal();
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
