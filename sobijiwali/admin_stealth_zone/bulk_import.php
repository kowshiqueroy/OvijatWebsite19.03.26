<?php
/**
 * Bulk CSV Import Tool
 */
$pageTitle = 'Bulk Import';
require_once 'layout_header.php';

$pm = new ProductManager();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF invalid.");
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    $headers = fgetcsv($handle);
    $csvData = [];
    while (($row = fgetcsv($handle)) !== FALSE) { $csvData[] = array_combine($headers, $row); }
    fclose($handle);
    $results = $pm->bulkUpdateCSV($csvData);
}
?>

<h1>Bulk Inventory Import</h1>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; align-items: start;">
    <div class="card">
        <h3>Upload CSV File</h3>
        <p style="opacity: 0.6; font-size: 0.9rem; margin-bottom: 2rem;">Update prices and stock for thousands of items instantly.</p>
        
        <?php if ($results): ?>
            <div class="alert <?php echo empty($results['errors']) ? 'alert-success' : 'alert-error'; ?>">
                <strong>Import Complete!</strong><br>
                Updated: <?php echo $results['success']; ?> items.
                <?php if (!empty($results['errors'])): ?>
                    <ul style="margin: 0.5rem 0 0 1rem; font-size: 0.75rem;">
                        <?php foreach ($results['errors'] as $err) echo "<li>$err</li>"; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <div class="form-group">
                <label>Choose CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required style="padding: 1.5rem; border: 2px dashed var(--border); background: var(--bg); text-align: center;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">Start Bulk Update</button>
        </form>
    </div>

    <div class="card">
        <h3>Template Format</h3>
        <p style="opacity: 0.6; font-size: 0.85rem; margin-bottom: 1.5rem;">Your CSV must include these headers in the first row. Only `sku` is mandatory; others are updated only if provided:</p>
        <div style="background: #1a202c; color: #4db6ac; padding: 1.5rem; border-radius: 8px; font-family: monospace; font-size: 0.8rem; overflow-x: auto; line-height: 1.6;">
            sku,price,wholesale_price,retail_min,wholesale_min,box_qty,box_weight,stock_qty,cost_price
        </div>
        <div style="margin-top: 2rem;">
            <h4>Column Guide:</h4>
            <ul style="font-size: 0.8rem; opacity: 0.7; margin: 1rem 0 0 1.2rem; line-height: 1.6;">
                <li><strong>sku:</strong> Unique identifier (Required).</li>
                <li><strong>price:</strong> New retail selling price.</li>
                <li><strong>wholesale_price:</strong> New wholesale price.</li>
                <li><strong>retail_min / wholesale_min:</strong> Minimum order quantities.</li>
                <li><strong>box_qty / box_weight:</strong> Packaging specs for logistics.</li>
                <li><strong>stock_qty:</strong> Adds a new FIFO batch to existing stock.</li>
                <li><strong>cost_price:</strong> Required ONLY if `stock_qty` is provided.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
