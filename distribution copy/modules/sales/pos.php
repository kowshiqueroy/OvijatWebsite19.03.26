<?php
require_once '../../templates/header.php';
check_login();
// Customers, SRs, Managers, and Accountants can create drafts
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER]);

$customers = fetch_all("SELECT id, name, phone, type, balance FROM customers WHERE is_active = 1");
$products = fetch_all("SELECT id, name, tp_rate, dp_rate, retail_rate, stock_qty FROM products WHERE is_active = 1");
?>

<div class="row">
    <div class="col-12 mb-3">
        <h3>POS / New Sales Draft</h3>
    </div>
</div>

<form id="pos-form" action="save_draft.php" method="POST">
    <?php csrf_field(); ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Customer Lookup</label>
                    <?php if ($_SESSION['role'] == ROLE_CUSTOMER): ?>
                        <?php $my_cust = fetch_one("SELECT * FROM customers WHERE user_id = ?", [$_SESSION['user_id']]); ?>
                        <input type="text" class="form-control" value="<?php echo $my_cust['name']; ?>" readonly>
                        <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $my_cust['id']; ?>">
                        <input type="hidden" id="customer_type" value="<?php echo $my_cust['type']; ?>">
                    <?php else: ?>
                        <input type="text" id="customer_search" class="form-control" placeholder="Type Name or Phone..." list="customer-list" required autofocus>
                        <datalist id="customer-list">
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['name']; ?> (<?php echo $c['phone']; ?>)" data-id="<?php echo $c['id']; ?>" data-type="<?php echo $c['type']; ?>" data-balance="<?php echo $c['balance']; ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <input type="hidden" name="customer_id" id="customer_id">
                        <input type="hidden" id="customer_type">
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <input type="text" id="display_customer_type" class="form-control" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Balance</label>
                    <input type="text" id="display_balance" class="form-control" readonly>
                </div>
                <div class="col-md-3 text-end d-flex align-items-end justify-content-end">
                     <div class="text-uppercase small text-muted me-2">Draft Status</div>
                     <span class="badge bg-warning p-2">DRAFT</span>
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
                        <!-- Items will be added here -->
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
                     <label class="form-label">General Note / Shipping Instructions</label>
                     <textarea name="general_note" class="form-control" rows="4"></textarea>
                 </div>
             </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Sub Total</span>
                        <input type="number" step="0.01" name="sub_total" id="sub_total" class="form-control w-50 text-end" readonly value="0.00">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount</span>
                        <input type="number" step="0.01" name="discount" id="discount" class="form-control w-50 text-end" value="0.00">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>VAT (%)</span>
                        <input type="number" step="0.01" name="vat_percent" id="vat_percent" class="form-control w-50 text-end" value="0.00">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <h4 class="mb-0">Grand Total</h4>
                        <input type="number" step="0.01" name="grand_total" id="grand_total" class="form-control w-50 text-end fw-bold fs-4 text-primary" readonly value="0.00">
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-save me-2"></i> Save Sales Draft (Ctrl+S)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

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
                    
                    // Update all existing row rates based on new customer type
                    updateAllRates();
                    break;
                }
            }
        });
    } else {
        // For customer role
        const type = document.getElementById('customer_type').value;
        document.getElementById('display_customer_type').value = type;
        document.getElementById('display_balance').value = '<?php echo $my_cust['balance'] ?? 0; ?>';
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

    addRow(); // Initial row

    addRowBtn.addEventListener('click', addRow);

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
                    row.setAttribute('data-available-stock', options[i].getAttribute('data-stock'));
                    
                    const custType = document.getElementById('customer_type').value || 'Retail';
                    let rate = 0;
                    if (custType === 'TP') rate = options[i].getAttribute('data-tp');
                    else if (custType === 'DP') rate = options[i].getAttribute('data-dp');
                    else rate = options[i].getAttribute('data-retail');
                    
                    row.querySelector('.rate').value = rate;
                    checkStock(row);

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

        if (e.target.classList.contains('billed-qty') || e.target.classList.contains('free-qty') || e.target.classList.contains('rate')) {
            const rate = parseFloat(row.querySelector('.rate').value) || 0;
            const qty = parseInt(row.querySelector('.billed-qty').value) || 0;
            row.querySelector('.row-total').value = (rate * qty).toFixed(2);
            checkStock(row);
            calculateGrandTotal();
        }
    });

    function checkStock(row) {
        const stock = parseInt(row.getAttribute('data-available-stock')) || 0;
        const billed = parseInt(row.querySelector('.billed-qty').value) || 0;
        const free = parseInt(row.querySelector('.free-qty').value) || 0;
        const totalReq = billed + free;

        if (totalReq > stock) {
            row.style.backgroundColor = '#fff3f3';
            row.querySelector('.product-search').style.color = 'red';
            row.querySelector('.product-search').title = 'Insufficient Stock! Available: ' + stock;
        } else {
            row.style.backgroundColor = '';
            row.querySelector('.product-search').style.color = '';
            row.querySelector('.product-search').title = '';
        }
    }

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

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            addRow();
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('pos-form').submit();
        }
        if (e.key === 'Escape') {
            if(confirm('Discard this draft?')) window.location.href = 'index.php';
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
