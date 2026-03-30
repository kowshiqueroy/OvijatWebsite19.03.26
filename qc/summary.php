<?php
include_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 1. SETUP & FETCH AUTO-COMPLETE DATA ---
$traders = [];
$tq = $conn->query("SELECT DISTINCT trader_name FROM damage_details ORDER BY trader_name ASC");
while($r = $tq->fetch_assoc()) $traders[] = $r['trader_name'];

$products = [];
$pq = $conn->query("SELECT DISTINCT name FROM products ORDER BY name ASC");
while($r = $pq->fetch_assoc()) $products[] = $r['name'];

// --- 2. DEFINE COLUMNS ---

// Parent (Invoice) Columns
$parent_cols = [
    'inspection_date' => ['label'=>'Insp. Date', 'def'=>true, 'type'=>'date'],
    'received_date'   => ['label'=>'Rec. Date', 'def'=>true, 'type'=>'date'],
    'trader_name'     => ['label'=>'Trader Name', 'def'=>true, 'type'=>'text'],
    'status'          => ['label'=>'Status', 'def'=>false, 'type'=>'status'],
    'shop_total_qty'       => ['label'=>'Inv. Shop Qty', 'def'=>true, 'type'=>'int'],
    'received_total_qty'   => ['label'=>'Inv. Rec Qty', 'def'=>true, 'type'=>'int'],
    'shop_total_amount'    => ['label'=>'Inv. Shop Amt', 'def'=>false, 'type'=>'money'],
    'received_total_amount'=> ['label'=>'Inv. Rec Amt', 'def'=>false, 'type'=>'money'],
    'actual_total_qty'     => ['label'=>'Inv. Act Qty', 'def'=>true, 'type'=>'int'],
    'actual_total_amount'  => ['label'=>'Inv. Act Amt', 'def'=>true, 'type'=>'money'],
   
   
    'created_at'            => ['label'=>'Created At', 'def'=>false, 'type'=>'date'],
    'created_by'            => ['label'=>'Created By', 'def'=>false, 'type'=>'text'],
    'updated_at'            => ['label'=>'Updated At', 'def'=>false, 'type'=>'date'],
    'updated_by'            => ['label'=>'Updated By', 'def'=>false, 'type'=>'text'],
];

// Child (Product) Columns
$child_cols = [
    'product_name'    => ['label'=>'Product Name', 'def'=>true, 'type'=>'text'],
    'shop_qty'        => ['label'=>'Shop Qty', 'def'=>true, 'type'=>'int'],
    'shop_amount'     => ['label'=>'Shop Amt', 'def'=>false, 'type'=>'money'],
    'received_qty'    => ['label'=>'Rec Qty', 'def'=>true, 'type'=>'int'],
    'received_amount' => ['label'=>'Rec Amt', 'def'=>false, 'type'=>'money'],
    'actual_qty'      => ['label'=>'Act Qty', 'def'=>true, 'type'=>'int'],
    'actual_amount'   => ['label'=>'Act Amt', 'def'=>true, 'type'=>'money'],
    // Breakdown
    'good'            => ['label'=>'Good', 'def'=>false, 'type'=>'int'],
    'label'           => ['label'=>'Label', 'def'=>false, 'type'=>'int'],
    'sealing'         => ['label'=>'Sealing', 'def'=>false, 'type'=>'int'],
    'expired'         => ['label'=>'Expired', 'def'=>true, 'type'=>'int'],
    'date_problem'    => ['label'=>'Date Prob', 'def'=>false, 'type'=>'int'],
    'broken'          => ['label'=>'Broken', 'def'=>false, 'type'=>'int'],
    'VHsealing'       => ['label'=>'VH Seal', 'def'=>true, 'type'=>'int'],
    'insect'          => ['label'=>'Insect', 'def'=>true, 'type'=>'int'],
    'intentional'     => ['label'=>'Intent.', 'def'=>true, 'type'=>'int'],
    'soft'            => ['label'=>'Soft', 'def'=>false, 'type'=>'int'],
    'bodyleakage'     => ['label'=>'Leakage', 'def'=>false, 'type'=>'int'],
    'others'          => ['label'=>'Others', 'def'=>false, 'type'=>'int'],
    // Final
    'total_negative_qty'    => ['label'=>'NCLU Qty', 'def'=>false, 'type'=>'int'],
    'total_negative_amount' => ['label'=>'NCLU Amt', 'def'=>false, 'type'=>'money'],
    'remarks'               => ['label'=>'Remarks', 'def'=>false, 'type'=>'text']
];

// --- 3. HANDLE SEARCH ---
$results = [];
$grand_totals = [];

$s_start  = $_POST['start_date'] ?? date('Y-m-01');
$s_end    = $_POST['end_date'] ?? date('Y-m-t');
$s_status = $_POST['status'] ?? '1';
$s_trader = $_POST['trader_name'] ?? '';
$s_product= $_POST['product_name'] ?? '';

$act_p_cols = [];
$act_c_cols = [];

if (isset($_POST['search'])) {
    foreach($parent_cols as $k=>$v) $act_p_cols[$k] = isset($_POST['p_cols'][$k]);
    foreach($child_cols as $k=>$v)  $act_c_cols[$k] = isset($_POST['c_cols'][$k]);
    
    $sql = "SELECT 
                dd.id as dd_id, dd.inspection_date, dd.received_date, dd.trader_name, dd.status,
                dd.shop_total_qty, dd.received_total_qty, dd.shop_total_amount, dd.received_total_amount,
                dd.actual_total_qty, dd.actual_total_amount, 
                dd.created_at, dd.created_by, dd.updated_at, dd.updated_by,
                p.name AS product_name,
                di.* FROM damage_items di
            JOIN damage_details dd ON di.damage_details_id = dd.id
            JOIN products p ON di.product_id = p.id
            WHERE dd.inspection_date BETWEEN ? AND ?";
    
    $types = "ss";
    $params = [$s_start, $s_end];

    if ($s_status !== 'all') { 
        $sql .= " AND dd.status = ?"; $types .= "i"; $params[] = $s_status; 
    }
    if (!empty($s_trader)) { 
        $sql .= " AND dd.trader_name LIKE ?"; $types .= "s"; $params[] = "%$s_trader%"; 
    }
    if (!empty($s_product)) { 
        $sql .= " AND p.name LIKE ?"; $types .= "s"; $params[] = "%$s_product%"; 
    }

    $sql .= " ORDER BY dd.inspection_date DESC, dd.id DESC, p.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while($row = $res->fetch_assoc()){
        $results[] = $row;
        foreach($act_c_cols as $k => $active) {
            if($active && ($child_cols[$k]['type']=='int' || $child_cols[$k]['type']=='money')) {
                if(!isset($grand_totals[$k])) $grand_totals[$k] = 0;
                $grand_totals[$k] += $row[$k];
            }
        }
    }
 
} else {
    // Defaults
    foreach($parent_cols as $k=>$v) $act_p_cols[$k] = $v['def'];
    foreach($child_cols as $k=>$v)  $act_c_cols[$k] = $v['def'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detailed Damage Report</title>
    <style>
        :root { --primary: #007bff; --border: #ced4da; --bg: #f4f6f9; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 20px; font-size: 12px; color: #333; }
        
        .report-header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #444; padding-bottom: 10px; }
        .report-header h1 { margin: 0; text-transform: uppercase; font-size: 22px; }

        /* SEARCH STYLES */
        .search-panel { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .search-group { border: 1px solid #e9ecef; padding: 10px; margin-bottom: 10px; border-radius: 4px; position: relative; }
        .search-group-title { position: absolute; top: -10px; left: 10px; background: #fff; padding: 0 5px; font-size: 11px; font-weight: bold; color: var(--primary); text-transform: uppercase; }
        .flex-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        label { font-weight: 600; font-size: 11px; margin-bottom: 4px; color: #555; }
        input[type="text"], input[type="date"], select { padding: 6px; border: 1px solid var(--border); border-radius: 3px; min-width: 160px; font-size: 12px; }
        .btn { padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; color: white; font-weight: bold; }
        .btn-gen { background: var(--primary); }
        .btn-print { background: #28a745; margin-left: auto; }

        /* COLUMN SELECTORS */
        .col-config-container { display: flex; gap: 20px; margin-top: 10px; }
        .col-box { flex: 1; border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 4px; }
        .col-box h4 { margin: 0 0 10px 0; font-size: 12px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 5px; }
        .check-item { display: flex; align-items: center; gap: 5px; font-size: 11px; cursor: pointer; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 15px; font-size: 11px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 5px; vertical-align: middle; }
        th { background: #f1f3f5; font-weight: 700; text-align: center; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        /* Merging Logic */
        .new-group { border-top: 2px solid #666 !important; } /* Standard thick grey border */
        tr:not(.new-group) .parent-cell { 
            color: transparent; 
            border-top-color: #fff; 
            border-bottom-color: #dee2e6;
        }
        tr.new-group .parent-cell { border-top: 2px solid #666; }
        
        /* Highlights */
        .bg-hl { background-color: #e8f4f8; font-weight: bold; }
        
        /* Clean Footer (No Black Bar) */
        .grand-total { background-color: #f8f9fa; color: #333; font-weight: 800; border-top: 2px solid #333; }

        @media print {
            @page { size: landscape; margin: 5mm; }
            body { background: #fff; padding: 0; }
            .search-panel { display: none; }
            table { border: 2px solid #000; font-size: 10px; }
            th, td { border: 1px solid #000; color: #000; }
            th { background-color: #ddd !important; -webkit-print-color-adjust: exact; }
            .grand-total { background-color: #eee !important; -webkit-print-color-adjust: exact; }
            .new-group { border-top: 2px solid #000 !important; }
            tr:not(.new-group) .parent-cell { border-top-color: #fff !important; }
        }
    </style>
</head>
<body>

<div class="report-header">
    <h1>Ovijat Damage Report</h1>
    <?php if(isset($_POST['search'])): ?>

        <?php if(isset($_POST['search']) && !empty($s_trader) && !empty($s_product)): ?>
        <p>Search Results for Trader: <?= $s_trader ?> and Product: <?= $s_product ?> from <?= date('d M Y', strtotime($s_start)) ?> to <?= date('d M Y', strtotime($s_end)) ?></p>
        <?php elseif (isset($_POST['search']) && !empty($s_trader)) : ?>
        <p>Search Results for Trader: <?= $s_trader ?> from <?= date('d M Y', strtotime($s_start)) ?> to <?= date('d M Y', strtotime($s_end)) ?></p>
        <?php elseif (isset($_POST['search']) && !empty($s_product)) : ?>
        <p>Search Results for Product: <?= $s_product ?> from <?= date('d M Y', strtotime($s_start)) ?> to <?= date('d M Y', strtotime($s_end)) ?></p>
        <?php else: ?>
        <p>Search Results from <?= date('d M Y', strtotime($s_start)) ?> to <?= date('d M Y', strtotime($s_end)) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<form method="POST" class="search-panel">

    <div class="search-group">
        <span class="search-group-title">Main Filters</span>
        <div class="flex-row">
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="start_date" value="<?= $s_start ?>" required>
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="end_date" value="<?= $s_end ?>" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                   
                    <option value="1" <?= $s_status=='1'?'selected':''  ?>>Confirmed</option>
                    <option value="0" <?= $s_status=='0'?'selected':'' ?>>Draft</option>
                     <option value="all" <?= $s_status=='all'?'selected':'' ?>>All Status</option>
                </select>
            </div>
            <div class="form-group">
                <button type="button" onclick="window.location.href='index.php'" class="btn btn-back" style="background-color: #4CAF50;">Back to Home</button>
            </div>
        </div>
    </div>

    <div class="search-group">
        <span class="search-group-title">Optional Search (Leave blank for all)</span>
        <div class="flex-row">
            <div class="form-group">
                <label>Trader Name</label>
                <input type="text" list="trader_list" name="trader_name" value="<?= htmlspecialchars($s_trader) ?>" placeholder="Search Trader...">
                <datalist id="trader_list"><?php foreach($traders as $t) echo "<option value='$t'>"; ?></datalist>
            </div>
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" list="product_list" name="product_name" value="<?= htmlspecialchars($s_product) ?>" placeholder="Search Product...">
                <datalist id="product_list"><?php foreach($products as $p) echo "<option value='$p'>"; ?></datalist>
            </div>
            <button type="submit" name="search" class="btn btn-gen">Generate Report</button>
            <?php if(!empty($results)): ?>
                <button type="button" onclick="window.print()" class="btn btn-print">Print Report</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-config-container">
        <div class="col-box">
            <h4>1. Invoice Details Columns</h4>
            <div class="check-grid">
                <?php foreach($parent_cols as $key => $conf): ?>
                    <label class="check-item">
                        <input type="checkbox" name="p_cols[<?=$key?>]" <?= $act_p_cols[$key]?'checked':'' ?>>
                        <?= $conf['label'] ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-box">
            <h4>2. Product Items Columns</h4>
            <div class="check-grid">
                <?php foreach($child_cols as $key => $conf): ?>
                    <label class="check-item">
                        <input type="checkbox" name="c_cols[<?=$key?>]" <?= $act_c_cols[$key]?'checked':'' ?>>
                        <?= $conf['label'] ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</form>

<?php if(isset($_POST['search'])): ?>
    <?php if(count($results) > 0): ?>
    <table>
        <thead>
            <tr>
                <?php foreach($parent_cols as $k => $c): if($act_p_cols[$k]): ?>
                    <th><?= $c['label'] ?></th>
                <?php endif; endforeach; ?>

                <?php foreach($child_cols as $k => $c): if($act_c_cols[$k]): ?>
                    <th><?= $c['label'] ?></th>
                <?php endif; endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $prev_dd_id = 0;
            foreach($results as $row): 
                $is_new = ($row['dd_id'] != $prev_dd_id);
                $row_class = $is_new ? 'new-group' : '';
            ?>
            <tr class="<?= $row_class ?>">
                <?php foreach($parent_cols as $k => $c): if($act_p_cols[$k]): ?>
                    <td class="parent-cell text-center">
                        <?php 
                        if ($c['type'] == 'date') echo date('d.m.y', strtotime($row[$k]));
                        elseif ($c['type'] == 'status') echo ($row[$k]==1?'✅':'📝');
                        elseif ($c['type'] == 'money') echo number_format($row[$k], 2);
                        else echo htmlspecialchars($row[$k]);
                        ?>
                    </td>
                <?php endif; endforeach; ?>

                <?php foreach($child_cols as $k => $c): if($act_c_cols[$k]): ?>
                    <?php 
                        $val = $row[$k];
                        $align = 'center';
                        $class = '';

                        if ($c['type'] == 'money') { $val = number_format($val, 2); $align = 'right'; }
                        elseif ($c['type'] == 'int') { $val = ($val==0) ? '-' : $val; } 
                        elseif ($c['type'] == 'text') { $align = 'left'; }

                        if ($k == 'actual_qty' || $k == 'actual_amount') $class = 'bg-hl';
                        if ($k == 'good' && $row[$k] > 0) $class .= ' style="color:green;font-weight:bold;"';
                    ?>
                    <td class="text-<?= $align ?> <?= $class ?>" <?= $class ?>>
                        <?= $val ?>
                    </td>
                <?php endif; endforeach; ?>
            </tr>
            <?php 
                $prev_dd_id = $row['dd_id'];
            endforeach; 
            ?>
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <?php foreach($parent_cols as $k => $c): if($act_p_cols[$k]): ?>
                      <td class="text-<?= ($c['type']=='money')?'right':'center' ?>">
                        <?php 
                        if(isset($grand_totals[$k])) {
                            echo  ($c['type']=='money') ? number_format($grand_totals[$k], 2) : $grand_totals[$k];
                        }
                        ?>.
                    </td>
                <?php endif; endforeach; ?>
                
                <?php foreach($child_cols as $k => $c): if($act_c_cols[$k]): ?>
                    <td class="text-<?= ($c['type']=='money')?'right':'center' ?>">
                        <?php 
                        if(isset($grand_totals[$k])) {
                            echo ($c['type']=='money') ? number_format($grand_totals[$k], 2) : $grand_totals[$k];
                        }
                        ?>
                    </td>
                <?php endif; endforeach; ?>

                
            </tr>
           
        </tfoot>
    </table>
    <div style="display: flex; justify-content: space-around; bottom: 20px;  width: 100%; padding: 20px;">
       
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            Checked by
        </div>
    </div>
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            Verified by EX-QC
        </div>
    </div>
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            AM Distribution
        </div>
    </div>
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            AM QC
        </div>
    </div>
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            AM Accounts
        </div>
    </div>
    <div style="text-align: center;">
        <div style="border-top: 1px solid black; width: 150px;">
            Approved by
        </div>
    </div>
</div>
    <?php else: ?>
        <p style="text-align:center; padding:40px; color: #777;">No records found matching your criteria.</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>