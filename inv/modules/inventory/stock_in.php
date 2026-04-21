<?php
/**
 * modules/inventory/stock_in.php
 */
include '../../includes/header.php';
requireRole(['Admin', 'Manager']);

$branch_id = $_SESSION['branch_id'];

// Fetch products for autocomplete
$stmt = $pdo->prepare("SELECT id, name, unit_name, conversion_ratio FROM products WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$products = $stmt->fetchAll();

// Fetch suppliers
$stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE branch_id = ? AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([$branch_id]);
$suppliers = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0 fw-bold">Stock IN (Purchase/Restock)</h5>
    </div>
    <div class="card-body">
        <form id="stockInForm">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reference/Note</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Invoice #123">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <h6 class="fw-bold mb-3 border-bottom pb-2">Products Selection</h6>
            <div class="table-responsive">
                <table class="table table-bordered" id="stockInTable">
                    <thead class="bg-light">
                        <tr>
                            <th>Product</th>
                            <th width="150">Pack Unit</th>
                            <th width="150">Quantity</th>
                            <th width="180">Purchase Price (Pack)</th>
                            <th width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="items[0][product_id]" class="form-select product-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $p): ?>
<option value="<?php echo $p['id']; ?>" data-unit="<?php echo $p['unit_name']; ?>" data-ratio="<?php echo $p['conversion_ratio']; ?>"><?php echo $p['name']; ?> (<?php echo $p['unit_name']; ?> X <?php echo $p['conversion_ratio']; ?> Pcs)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="items[0][unit_type]" class="form-select" required>
                                    <option value="pack">Pack (Box)</option>
                                    <option value="piece">Piece (Pcs)</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="items[0][quantity]" class="form-control" min="1" required>
                            </td>
                            <td>
                                <input type="number" name="items[0][purchase_price]" step="0.01" class="form-control" placeholder="Price per Pack" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm remove-row" disabled><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addRow">
                <i class="fas fa-plus me-1"></i> Add Another Product
            </button>

            <div class="text-end border-top pt-3">
                <button type="submit" class="btn btn-success px-5">Submit Stock IN</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    let rowCount = 1;

    // Add Row
    $('#addRow').on('click', function() {
        const newRow = `
            <tr>
                <td>
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-unit="<?php echo $p['unit_name']; ?>"><?php echo $p['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="items[${rowCount}][unit_type]" class="form-select" required>
                        <option value="pack">Pack</option>
                        <option value="piece">Piece</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${rowCount}][quantity]" class="form-control" min="1" required>
                </td>
                <td>
                    <input type="number" name="items[${rowCount}][purchase_price]" step="0.01" class="form-control" placeholder="Price per Pack" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#stockInTable tbody').append(newRow);
        
        // Re-initialize Select2 for the new row
        $(`#stockInTable tbody tr:last-child .product-select`).select2({
            theme: 'bootstrap-5',
            placeholder: 'Search Product...'
        });

        rowCount++;
        updateRemoveButtons();
    });

    // Remove Row
    $(document).on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        $('.remove-row').prop('disabled', $('#stockInTable tbody tr').length === 1);
    }

    // Submit Form
    $('#stockInForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../../actions/inventory.php?action=stock_in', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                alert(res.message);
                window.location.href = 'stock.php';
            } else {
                alert(res.message);
            }
        }, 'json');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
