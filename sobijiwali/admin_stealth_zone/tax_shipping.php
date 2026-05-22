<?php
/**
 * Tax & Shipping Rate Manager
 */
$pageTitle = 'Tax & Shipping';
require_once 'layout_header.php';

$db = Database::getInstance();
$message = '';
$error = '';

// Handle Shipping Rate Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF Invalid.";
    } else {
        if ($_POST['action'] === 'save_shipping') {
            $min = (float)$_POST['min_weight'];
            $max = (float)$_POST['max_weight'];
            $rate = (float)$_POST['rate'];
            $id = $_POST['rate_id'] ? (int)$_POST['rate_id'] : null;

            if ($id) {
                $db->query("UPDATE shipping_rates SET min_weight = ?, max_weight = ?, rate = ? WHERE id = ?", [$min, $max, $rate, $id]);
                $message = "Shipping rate updated.";
            } else {
                $db->query("INSERT INTO shipping_rates (min_weight, max_weight, rate) VALUES (?, ?, ?)", [$min, $max, $rate]);
                $message = "Shipping rate added.";
            }
        } elseif ($_POST['action'] === 'delete_shipping') {
            $db->query("DELETE FROM shipping_rates WHERE id = ?", [(int)$_POST['rate_id']]);
            $message = "Shipping rate deleted.";
        } elseif ($_POST['action'] === 'save_tax') {
            $code = strtoupper(trim($_POST['state_code']));
            $name = trim($_POST['state_name']);
            $rate = (float)$_POST['tax_rate'] / 100; // Convert to decimal e.g. 5 -> 0.05
            $id = $_POST['tax_id'] ? (int)$_POST['tax_id'] : null;

            if ($id) {
                $db->query("UPDATE state_taxes SET state_code = ?, state_name = ?, tax_rate = ? WHERE id = ?", [$code, $name, $rate, $id]);
                $message = "Tax rule updated.";
            } else {
                $db->query("INSERT INTO state_taxes (state_code, state_name, tax_rate) VALUES (?, ?, ?)", [$code, $name, $rate]);
                $message = "Tax rule added.";
            }
        } elseif ($_POST['action'] === 'delete_tax') {
            $db->query("DELETE FROM state_taxes WHERE id = ?", [(int)$_POST['tax_id']]);
            $message = "Tax rule deleted.";
        }
    }
}

$shippingRates = $db->query("SELECT * FROM shipping_rates ORDER BY min_weight ASC")->fetchAll();
$taxRules = $db->query("SELECT * FROM state_taxes ORDER BY state_name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Logistics & Taxation</h1>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">
    
    <!-- Weight-Based Shipping -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3>Shipping Rates (By Weight)</h3>
            <button class="btn btn-primary" onclick="openShippingModal()">+ Add Rate</button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Weight Range (kg)</th>
                        <th>Rate ($)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shippingRates as $r): ?>
                    <tr>
                        <td><?php echo number_format($r['min_weight'], 2); ?> - <?php echo number_format($r['max_weight'], 2); ?></td>
                        <td><strong>$<?php echo number_format($r['rate'], 2); ?></strong></td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <button class="btn btn-outline" style="padding:4px 8px;" onclick='editShipping(<?php echo json_encode($r); ?>)'>Edit</button>
                                <form method="POST" onsubmit="return confirm('Delete this rate?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_shipping">
                                    <input type="hidden" name="rate_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 8px;">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- State-Based Tax -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3>State Tax Setup</h3>
            <button class="btn btn-primary" onclick="openTaxModal()">+ Add State</button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>State Name</th>
                        <th>Tax Rate (%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taxRules as $t): ?>
                    <tr>
                        <td><strong><?php echo $t['state_code']; ?></strong></td>
                        <td><?php echo htmlspecialchars($t['state_name']); ?></td>
                        <td><strong><?php echo number_format($t['tax_rate'] * 100, 2); ?>%</strong></td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <button class="btn btn-outline" style="padding:4px 8px;" onclick='editTax(<?php echo json_encode($t); ?>)'>Edit</button>
                                <form method="POST" onsubmit="return confirm('Delete this tax rule?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_tax">
                                    <input type="hidden" name="tax_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 8px;">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modals -->
<div id="shipModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:400px;">
        <h3 id="shipTitle">Add Shipping Rate</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="save_shipping">
            <input type="hidden" name="rate_id" id="rateId">
            <div class="form-group"><label>Min Weight (kg)</label><input type="number" step="0.01" name="min_weight" id="minWeight" required></div>
            <div class="form-group"><label>Max Weight (kg)</label><input type="number" step="0.01" name="max_weight" id="maxWeight" required></div>
            <div class="form-group"><label>Rate ($)</label><input type="number" step="0.01" name="rate" id="shipRate" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Rate</button>
            <button type="button" onclick="this.closest('#shipModal').style.display='none'" class="btn btn-outline" style="width:100%; margin-top:0.5rem;">Cancel</button>
        </form>
    </div>
</div>

<div id="taxModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width:400px;">
        <h3 id="taxTitle">Add Tax Rule</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="save_tax">
            <input type="hidden" name="tax_id" id="taxId">
            <div class="form-group"><label>State Code (e.g. NY)</label><input type="text" name="state_code" id="stateCode" maxlength="10" required></div>
            <div class="form-group"><label>State Name</label><input type="text" name="state_name" id="stateName" required></div>
            <div class="form-group"><label>Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" id="taxRate" required></div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Tax Rule</button>
            <button type="button" onclick="this.closest('#taxModal').style.display='none'" class="btn btn-outline" style="width:100%; margin-top:0.5rem;">Cancel</button>
        </form>
    </div>
</div>

<script>
    function openShippingModal() {
        document.getElementById('shipTitle').innerText = 'Add Shipping Rate';
        document.getElementById('rateId').value = '';
        document.getElementById('minWeight').value = '0.00';
        document.getElementById('maxWeight').value = '0.00';
        document.getElementById('shipRate').value = '0.00';
        document.getElementById('shipModal').style.display = 'flex';
    }
    function editShipping(data) {
        document.getElementById('shipTitle').innerText = 'Edit Shipping Rate';
        document.getElementById('rateId').value = data.id;
        document.getElementById('minWeight').value = data.min_weight;
        document.getElementById('maxWeight').value = data.max_weight;
        document.getElementById('shipRate').value = data.rate;
        document.getElementById('shipModal').style.display = 'flex';
    }
    function openTaxModal() {
        document.getElementById('taxTitle').innerText = 'Add Tax Rule';
        document.getElementById('taxId').value = '';
        document.getElementById('stateCode').value = '';
        document.getElementById('stateName').value = '';
        document.getElementById('taxRate').value = '0.00';
        document.getElementById('taxModal').style.display = 'flex';
    }
    function editTax(data) {
        document.getElementById('taxTitle').innerText = 'Edit Tax Rule';
        document.getElementById('taxId').value = data.id;
        document.getElementById('stateCode').value = data.state_code;
        document.getElementById('stateName').value = data.state_name;
        document.getElementById('taxRate').value = (data.tax_rate * 100).toFixed(2);
        document.getElementById('taxModal').style.display = 'flex';
    }
</script>

<?php require_once 'layout_footer.php'; ?>
