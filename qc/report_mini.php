<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    die('Invalid or missing ID parameter');
}

$id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch damage report details from the database
$stmt = $conn->prepare("SELECT * FROM damage_details WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $shop_type = $row['shop_type'];
    $received_date = $row['received_date'];
    $inspection_date = $row['inspection_date'];
    $trader_name = $row['trader_name'];
    $shop_total_qty = $row['shop_total_qty'];
    $received_total_qty = $row['received_total_qty'];
    $shop_total_amount = $row['shop_total_amount'];
    $received_total_amount = $row['received_total_amount'];
    $actual_total_qty = $row['actual_total_qty'];
    $actual_total_amount = $row['actual_total_amount'];
    $status = $row['status'];
} else {
    // Handle case where no report is found
    echo "No damage report found.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Damage Report <?php echo $id; ?> - <?php echo date('Y-m-d h:i:s A'); ?></title>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #printable, #printable * {
                visibility: visible;
            }
            #printable {
                position: absolute;
                left: 0;
                top: 0;
                width: 295mm;
                height: 200mm;
            }
        }
        #printable {
            width: 295mm;
            height: 200mm;
            margin: auto;
           
        }
        .button-group {
            text-align: center;
            margin-bottom: 20px;
        }
        button {
            padding: 10px 20px;
            margin-right: 10px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="button-group">
        <button onclick="window.history.back();">Go Back</button>
        <button onclick="window.print();">Print</button> <button onclick="window.location.href='report.php?id=<?php echo $id; ?>'">Print Full</button>
    </div>
    <div id="printable">
        <!-- Your report content goes here -->
        <h1 style="text-align: center; margin: 0; line-height: 1;">Ovijat Food & Beverages Industries Ltd.</h1>
        <p style="text-align: center; margin: 5px 0; line-height: 1;">QC Damage Report <?php echo date('Y-m-d h:i:s A'); ?></p>
    <div style="text-align: center; margin: 20px 0; line-height: 1;">
        <div style="margin-bottom: 5px;">
          
          <?php echo $shop_type; ?><?php echo $id; ?> </strong> <strong>Received:</strong> <?php echo $received_date; ?> 
           </strong> <strong>Inspection:</strong> <?php echo $inspection_date; ?> <strong>Trader:</strong> <?php echo $trader_name; ?>
        </div>
        <div style="font-size: 1.1em; padding: 5px; background-color: #f8f9fa; border-radius: 4px; display: inline-block;">
        <strong style="color: #2c3e50;">Send:</strong> <?php echo $shop_total_qty." = ".$shop_total_amount; ?>/-
        <strong style="color: #2c3e50; margin-left: 5px;">Received:</strong> <?php echo $received_total_qty." = ".$received_total_amount; ?>/-
        <strong style="color: #2c3e50; margin-left: 5px;">Actual:</strong> <?php echo $actual_total_qty." = ".$actual_total_amount; ?>/-
        <?php if (!$status): ?><span style="color: #e74c3c; margin-left: 5px;">Draft</span><?php endif; ?>
        </div>
    </div>
 

<?php
// Fetch all rows from the damage_items table
$query = "SELECT * FROM damage_items WHERE damage_details_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0): ?>
     <div class="table-container">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f2f2f2; text-align: center; font-size: 14px;">
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">SN</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Product</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Send Qty</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Send Amount</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Received Qty</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Received Amount</th>

                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Label</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Sealing</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Expired</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Date Problem</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Broken</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">V/H sealing</th>

                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Soft</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Body leakage</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Others</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Good</th>


                  <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Actual Qty</th>
                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Actual Amount</th>

                <th style="writing-mode: vertical-rl; transform: rotate(180deg); height: 80px; vertical-align: center; padding: 5px; border: 1px solid black;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 1; while ($row = $result->fetch_assoc()): ?>
                <tr style="text-align: center;"> <td style=" border: 1px solid black; padding: 5px;"><?= $sn++ ?></td>
                    <td style="border: 1px solid black; padding: 5px;">
                    <?php
                    $product_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                    $product_stmt->bind_param("i", $row['product_id']);
                    $product_stmt->execute();
                    $product_result = $product_stmt->get_result();
                    if ($product_row = $product_result->fetch_assoc()): ?>
                        <span class="badge badge-info"><?= htmlspecialchars($product_row['name']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning">Unknown Product</span>
                    <?php endif;
                    $product_stmt->close();
                    ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['shop_qty'] == 0 ? '' : htmlspecialchars($row['shop_qty'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['shop_amount'] == 0 ? '' : htmlspecialchars($row['shop_amount'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['received_qty'] == 0 ? '' : htmlspecialchars($row['received_qty'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['received_amount'] == 0 ? '' : htmlspecialchars($row['received_amount'])); ?></td>
                 
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['label'] == 0 ? '' : htmlspecialchars($row['label'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['sealing'] == 0 ? '' : htmlspecialchars($row['sealing'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['expired'] == 0 ? '' : htmlspecialchars($row['expired'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['date_problem'] == 0 ? '' : htmlspecialchars($row['date_problem'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['broken'] == 0 ? '' : htmlspecialchars($row['broken'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['VHsealing'] == 0 ? '' : htmlspecialchars($row['VHsealing'])); ?></td>
             
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['soft'] == 0 ? '' : htmlspecialchars($row['soft'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['bodyleakage'] == 0 ? '' : htmlspecialchars($row['bodyleakage'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['others'] == 0 ? '' : htmlspecialchars($row['others'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['good'] == 0 ? '' : htmlspecialchars($row['good'])); ?></td>

                       <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['actual_qty'] == 0 ? '' : htmlspecialchars($row['actual_qty'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['actual_amount'] == 0 ? '' : htmlspecialchars($row['actual_amount'])); ?></td>
                    <td style="border: 1px solid black; padding: 5px;"><?php echo htmlspecialchars($row['remarks'] == 0 ? '' : htmlspecialchars($row['remarks'])); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>No records found.</p>
<?php endif; ?>








<div style="display: flex; justify-content: space-around; position: fixed; bottom: 20px; width: 100%; padding: 20px;">
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
</div>

    </div>
</body>
</html>