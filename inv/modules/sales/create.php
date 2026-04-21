<?php
/**
 * modules/sales/create.php - Sales with Working Search
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];
$stmt = $pdo->prepare("SELECT id, name, type, balance FROM customers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$customers = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name, unit_name, conversion_ratio FROM products WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$products = $stmt->fetchAll();

$stmt = $pdo->query("SELECT product_id, customer_type, pack_price, piece_price FROM product_prices");
$prices = [];
while ($row = $stmt->fetch()) {
    $prices[$row['product_id']][$row['customer_type']] = ['pack' => $row['pack_price'], 'piece' => $row['piece_price']];
}
?>

<style>
.search-box { position: relative; }
.search-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; z-index: 999; display: none; }
.search-list div { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; }
.search-list div:hover, .search-list div.active { background: #0d6efd; color: #fff; }
.search-list div small { opacity: 0.7; }
</style>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">New Sale / Invoice</h5>
    </div>
    <div class="card-body p-0">
        <!-- Search Row -->
        <div class="bg-light p-3 border-bottom">
            <div class="row">
                <div class="col-md-3 search-box">
                    <label class="form-label">Product (Alt+P)</label>
                    <input type="text" id="prodInput" class="form-control" placeholder="Type to search...">
                    <div id="prodList" class="search-list"></div>
                </div>
                <div class="col-md-3 search-box">
                    <label class="form-label">Customer (Alt+C)</label>
                    <input type="text" id="custInput" class="form-control" placeholder="Type to search...">
                    <div id="custList" class="search-list"></div>
                </div>
                <div class="col-md-4" id="custInfo">Select customer...</div>
                <div class="col-md-2 text-end">
                    <label class="form-label">Total</label>
                    <h4 class="text-success" id="totalDisplay">0.00</h4>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <form id="saleForm">
            <input type="hidden" name="customer_id" id="custId">
            <input type="hidden" name="total_amount" id="totalAmt">
            <input type="hidden" name="discount_amount" id="discAmt">
            <input type="hidden" name="sale_status" id="saleStatus">
            
            <table class="table table-bordered mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>Free</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>
        </form>

        <!-- Footer -->
        <div class="bg-light p-3 border-top">
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4">
                            <select id="discType" class="form-control form-control-sm">
                                <option value="amount">Amount</option>
                                <option value="percent">Percent %</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" id="discInput" class="form-control form-control-sm" value="0" placeholder="Discount">
                        </div>
                        <div class="col-md-4">
                            <input type="number" id="netInput" class="form-control form-control-sm fw-bold text-success" readonly placeholder="Net">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-secondary" onclick="saveDraft()">Draft</button>
                    <button type="button" class="btn btn-success" onclick="submitSale()">Submit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Data from PHP
var products = <?php echo json_encode($products); ?>;
var customers = <?php echo json_encode($customers); ?>;
var productPrices = <?php echo json_encode($prices); ?>;

var rows = [];
var custType = 'Retail';
var prodIndex = 0;
var custIndex = 0;

// Get price
function getPrice(pid, ut) {
    pid = String(pid);
    if (!productPrices[pid]) return 0;
    if (!productPrices[pid][custType]) return 0;
    return ut === 'piece' ? (productPrices[pid][custType].piece || 0) : (productPrices[pid][custType].pack || 0);
}

// Product search
document.getElementById('prodInput').addEventListener('input', function() {
    var term = this.value.toLowerCase();
    var list = document.getElementById('prodList');
    if (term.length < 1) { list.style.display = 'none'; return; }
    
    var matches = products.filter(function(p) { return p.name.toLowerCase().includes(term); }).slice(0, 8);
    if (matches.length === 0) { list.style.display = 'none'; return; }
    
    var html = '';
    matches.forEach(function(p, i) {
        var price = getPrice(p.id, 'pack') || 0;
        var ratio = p.conversion_ratio || 1;
        html += '<div onclick="addProduct(' + p.id + ')" class="' + (i === 0 ? 'active' : '') + '">' + p.name + '<br><small>' + p.unit_name + ' X ' + ratio + ' | ' + Number(price).toFixed(2) + '</small></div>';
    });
    list.innerHTML = html;
    list.style.display = 'block';
    prodIndex = 0;
});

document.getElementById('prodInput').addEventListener('keydown', function(e) {
    var list = document.getElementById('prodList');
    var items = list.querySelectorAll('div');
    if (list.style.display !== 'block' || items.length === 0) return;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        prodIndex = Math.min(prodIndex + 1, items.length - 1);
        items.forEach(function(item, i) { item.classList.toggle('active', i === prodIndex); });
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        prodIndex = Math.max(prodIndex - 1, 0);
        items.forEach(function(item, i) { item.classList.toggle('active', i === prodIndex); });
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        var id = parseInt(items[prodIndex].getAttribute('onclick').match(/\d+/)[0]);
        addProduct(id);
    }
});

// Customer search
document.getElementById('custInput').addEventListener('input', function() {
    var term = this.value.toLowerCase();
    var list = document.getElementById('custList');
    if (term.length < 1) { list.style.display = 'none'; return; }
    
    var matches = customers.filter(function(c) { return c.name.toLowerCase().includes(term); }).slice(0, 8);
    if (matches.length === 0) { list.style.display = 'none'; return; }
    
    var html = '';
    matches.forEach(function(c, i) {
        html += '<div onclick="selectCustomer(' + c.id + ',\'' + c.name + '\',\'' + c.type + '\',' + c.balance + ')" class="' + (i === 0 ? 'active' : '') + '">' + c.name + '<br><small>' + c.type + '</small></div>';
    });
    list.innerHTML = html;
    list.style.display = 'block';
    custIndex = 0;
});

document.getElementById('custInput').addEventListener('keydown', function(e) {
    var list = document.getElementById('custList');
    var items = list.querySelectorAll('div');
    if (list.style.display !== 'block' || items.length === 0) return;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        custIndex = Math.min(custIndex + 1, items.length - 1);
        items.forEach(function(item, i) { item.classList.toggle('active', i === custIndex); });
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        custIndex = Math.max(custIndex - 1, 0);
        items.forEach(function(item, i) { item.classList.toggle('active', i === custIndex); });
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        var match = items[custIndex].getAttribute('onclick').match(/(\d+),'([^']+)','([^']+)',(\d+)/);
        selectCustomer(parseInt(match[1]), match[2], match[3], parseInt(match[4]));
    }
});

// Hide dropdowns on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#prodInput')) document.getElementById('prodList').style.display = 'none';
    if (!e.target.closest('#custInput')) document.getElementById('custList').style.display = 'none';
});

// Add product
function addProduct(id, isFree) {
    isFree = isFree || false;
    var p = products.find(function(x) { return x.id == id; });
    if (!p) return;
    
    // Find existing with same id AND same free status
    var existing = rows.findIndex(function(r) { return r.id == id && r.free === isFree; });
    if (existing >= 0) {
        rows[existing].qty += 1;
    } else {
        var price = getPrice(p.id, 'pack') || 0;
        rows.push({ id: p.id, name: p.name, unit: p.unit_name, ratio: p.conversion_ratio || 1, qty: 1, price: price, free: isFree, unitType: 'pack' });
    }
    render();
    calcTotal();
    document.getElementById('prodInput').value = '';
    document.getElementById('prodList').style.display = 'none';
}

// Move free items to end
function sortRows() {
    rows.sort(function(a, b) { return a.free === b.free ? 0 : a.free ? 1 : -1; });
}

// Select customer
function selectCustomer(id, name, type, balance) {
    custType = type;
    document.getElementById('custId').value = id;
    document.getElementById('custInput').value = name;
    document.getElementById('custInfo').innerHTML = '<b>' + name + '</b> (' + type + ') | Balance: ' + balance;
    document.getElementById('custList').style.display = 'none';
    document.getElementById('prodInput').focus();
}

// Render table
function render() {
    // Sort: paid first, then free
    rows.sort(function(a, b) { return a.free === b.free ? 0 : a.free ? 1 : -1; });
    var html = '';
    rows.forEach(function(r, i) {
        var sub = r.free ? 0 : (r.qty * r.price);
        html += '<tr class="' + (r.free ? 'table-success text-muted' : '') + '">';
        html += '<td>' + (i + 1) + '</td>';
        html += '<td>' + r.name + ' <small class="text-muted">(' + r.unit + ' X ' + r.ratio + ')</small><input type="hidden" name="items[' + i + '][product_id]" value="' + r.id + '"></td>';
        html += '<td><select class="form-control form-control-sm" name="items[' + i + '][unit_type]" onchange="updateUnit(' + i + ',this.value)"><option value="pack" ' + (r.unitType === 'pack' ? 'selected' : '') + '>Pack</option><option value="piece" ' + (r.unitType === 'piece' ? 'selected' : '') + '>Piece</option></select></td>';
        html += '<td><input type="number" class="form-control" name="items[' + i + '][quantity]" value="' + r.qty + '" min="1" onchange="updateQty(' + i + ',this.value)"></td>';
        html += '<td><input type="number" class="form-control" name="items[' + i + '][price]" value="' + r.price + '" step="0.01" onchange="updatePrice(' + i + ',this.value)"></td>';
        html += '<td class="text-end fw-bold">' + (r.free ? 'FREE' : sub.toFixed(2)) + '</td>';
        html += '<td class="text-center"><input type="checkbox" name="items[' + i + '][is_free]" ' + (r.free ? 'checked' : '') + ' onchange="updateFree(' + i + ',this.checked)"></td>';
        html += '<td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger" onclick="deleteRow(' + i + ')">×</button></td>';
        html += '</tr>';
    });
    document.getElementById('itemsBody').innerHTML = html;
}

function updateQty(i, v) { rows[i].qty = parseInt(v) || 1; render(); calcTotal(); }
function updatePrice(i, v) { rows[i].price = parseFloat(v) || 0; render(); calcTotal(); }
function updateFree(i, v) { rows[i].free = v; render(); calcTotal(); }
function updateUnit(i, v) { 
    rows[i].unitType = v; 
    if (rows[i].id) rows[i].price = getPrice(rows[i].id, v) || 0;
    render(); 
    calcTotal(); 
}
function deleteRow(i) { rows.splice(i, 1); render(); calcTotal(); }

// Calculate total
function calcTotal() {
    var total = 0;
    var freeTotal = 0;
    rows.forEach(function(r) { 
        if (r.id) {
            if (r.free) freeTotal += r.qty * r.price;
            else total += r.qty * r.price;
        }
    });
    var disc = parseFloat(document.getElementById('discInput').value) || 0;
    if (document.getElementById('discType').value === 'percent') disc = total * disc / 100;
    var net = total - disc;
    
    var totalText = total.toFixed(2);
    if (freeTotal > 0) totalText += ' (+ FREE: ' + freeTotal.toFixed(2) + ')';
    document.getElementById('totalDisplay').innerText = totalText;
    document.getElementById('totalAmt').value = total.toFixed(2);
    document.getElementById('discAmt').value = disc.toFixed(2);
    document.getElementById('netInput').value = net.toFixed(2);
}

document.getElementById('discInput').addEventListener('input', calcTotal);
document.getElementById('discType').addEventListener('change', calcTotal);

// Submit
function saveDraft() {
    if (!document.getElementById('custId').value) return alert('Select customer');
    if (!rows.length || !rows[0].id) return alert('Add products');
    document.getElementById('saleStatus').value = 'draft';
    submitForm();
}

function submitSale() {
    if (!document.getElementById('custId').value) return alert('Select customer');
    if (!rows.length || !rows[0].id) return alert('Add products');
    document.getElementById('saleStatus').value = 'pending_approval';
    submitForm();
}

function submitForm() {
    var formData = new FormData(document.getElementById('saleForm'));
    var params = new URLSearchParams(formData).toString();
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../../actions/sales.php?action=create_sale', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.status === 'success') {
                window.location.href = 'list.php';
            } else {
                alert(res.message);
            }
        } catch(e) {
            alert('Error: ' + xhr.responseText);
        }
    };
    xhr.send(params);
}
</script>

<?php include '../../includes/footer.php'; ?>