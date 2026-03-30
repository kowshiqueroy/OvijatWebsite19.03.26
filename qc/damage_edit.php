<?php
include_once 'header.php';
?>
<?php
$user_id = $_SESSION['user_id'];

if (isset($_GET['id']) && isset($_GET['damage_details_id']) && is_numeric($_GET['id']) && is_numeric($_GET['damage_details_id'])) {
    $id = (int)$_GET['id'];
    $damage_details_id = (int)$_GET['damage_details_id'];
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM damage_items WHERE damage_details_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $damage_details_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo "<script>alert('Item already added.'); window.location.href = 'damage_edit.php?id=$id';</script>";
            exit();
        }

        $shop_qty = (int)$_GET['shop_qty'];
        $shop_amount = (float)$_GET['shop_amount'];
        $received_qty = (int)$_GET['received_qty'];
        $received_amount = (float)$_GET['received_amount'];
        $actual_qty = (int)$_GET['actual_qty'];
        $actual_amount = (float)$_GET['actual_amount'];
        $insect = (int)$_GET['insect'];
        $label = (int)$_GET['label'];
        $sealing = (int)$_GET['sealing'];
        $expired = (int)$_GET['expired'];
        $date_problem = (int)$_GET['date_problem'];
        $broken = (int)$_GET['broken'];
        $VHsealing = (int)$_GET['VHsealing'];
        $good = (int)$_GET['good'];
        $intentional = (int)$_GET['intentional'];
        $soft = (int)$_GET['soft'];
        $bodyleakage = (int)$_GET['bodyleakage'];
        $others = (int)$_GET['others'];
        $total_negative_qty = (int)$_GET['total_negative_qty'];
        $total_negative_amount = (float)$_GET['total_negative_amount'];
        $remarks = isset($_GET['remarks']) ? $_GET['remarks'] : '';

        $stmt = $conn->prepare("INSERT INTO damage_items (damage_details_id, product_id, shop_qty, shop_amount, received_qty, received_amount, actual_qty, actual_amount, insect, label, sealing, expired, date_problem, broken, VHsealing, good, intentional, soft, bodyleakage, others, total_negative_qty, total_negative_amount, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidddiiiiiiiiiiiiiiiidds", $damage_details_id, $product_id, $shop_qty, $shop_amount, $received_qty, $received_amount, $actual_qty, $actual_amount, $insect, $label, $sealing, $expired, $date_problem, $broken, $VHsealing, $good, $intentional, $soft, $bodyleakage, $others, $total_negative_qty, $total_negative_amount, $remarks);
        
        if ($stmt->execute()) {
            $stmt = $conn->prepare("UPDATE damage_details SET updated_by = ?, shop_total_qty = shop_total_qty + ?, shop_total_amount = shop_total_amount + ?, received_total_qty = received_total_qty + ?, received_total_amount = received_total_amount + ?, actual_total_qty = actual_total_qty + ?, actual_total_amount = actual_total_amount + ? WHERE id = ?");
            $stmt->bind_param("iiiiiddi", $user_id, $shop_qty, $shop_amount, $received_qty, $received_amount, $actual_qty, $actual_amount, $id);
            if ($stmt->execute()) {
                header("Location: damage_edit.php?id=$id");
                exit();
            } else {
                $msg = "Error updating damage report: " . $conn->error;
            }
        } else {
            $msg = "Error creating damage report item: " . $conn->error;
        }
    }
}


if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM damage_items WHERE damage_details_id = ? AND id = ?");
    $stmt->bind_param("ii", $id, $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $shop_qty = (int)$row['shop_qty'];
        $shop_amount = (float)$row['shop_amount'];
        $received_qty = (int)$row['received_qty'];
        $received_amount = (float)$row['received_amount'];
        $actual_qty = (int)$row['actual_qty'];
        $actual_amount = (float)$row['actual_amount'];

        $stmt = $conn->prepare("UPDATE damage_details SET updated_by = ?, shop_total_qty = shop_total_qty - ?, shop_total_amount = shop_total_amount - ?, received_total_qty = received_total_qty - ?, received_total_amount = received_total_amount - ?, actual_total_qty = actual_total_qty - ?, actual_total_amount = actual_total_amount - ? WHERE id = ?");
        $stmt->bind_param("iiiiiddi", $user_id, $shop_qty, $shop_amount, $received_qty, $received_amount, $actual_qty, $actual_amount, $id);
        if ($conn->query($sql) === TRUE) {
            $stmt = $conn->prepare("DELETE FROM damage_items WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                header("Location: damage_edit.php?id=$id");
                exit();
            } else {
                $msg = "Error deleting damage item: " . $conn->error;
            }
        } else {
            $msg = "Error updating damage report totals: " . $conn->error;
        }
    } else {
        $msg = "Damage item not found.";
    }


}

// Include database connection
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

$itemid = isset($_GET['itemid']) ? $_GET['itemid'] : 0;
if ($itemid > 0) {
    $stmt = $conn->prepare("SELECT * FROM damage_items WHERE id = ?");
    $stmt->bind_param("i", $itemid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $product_id = $row['product_id'];
        $shop_qty = $row['shop_qty'];
        $shop_amount = $row['shop_amount'];
        $received_qty = $row['received_qty'];
        $received_amount = $row['received_amount'];
        $actual_qty = $row['actual_qty'];
        $actual_amount = $row['actual_amount'];
        $insect = $row['insect'];
        $label = $row['label'];
        $sealing = $row['sealing'];
        $expired = $row['expired'];
        $date_problem = $row['date_problem'];
        $broken = $row['broken'];
        $VHsealing = $row['VHsealing'];
        $good = $row['good'];
        $intentional = $row['intentional'];
        $soft = $row['soft'];
        $bodyleakage = $row['bodyleakage'];
        $others = $row['others'];
        $total_negative_qty = $row['total_negative_qty'];
        $total_negative_amount = $row['total_negative_amount'];
        $remarks = $row['remarks'];
    } else {
        // Handle case where no item is found

        $product_id = '';
        $shop_qty = 0;
        $shop_amount = 0.00;
        $received_qty = 0;
        $received_amount = 0.00;
        $actual_qty = 0;
        $actual_amount = 0.00;
        $insect = 0;
        $label = 0;
        $sealing = 0;
        $expired = 0;
        $date_problem = 0;
        $broken = 0;
        $VHsealing = 0;
        $good = 0;
        $intentional = 0;
        $soft = 0;
        $bodyleakage = 0;
        $others = 0;
        $total_negative_qty = 0;
        $total_negative_amount = 0.00;
        $remarks = '';
        echo "No damage item found.";
        exit();
    }
} else {
    // Default values for new item
    $product_id = '';
    $shop_qty = 0;
    $shop_amount = 0.00;
    $received_qty = 0;
    $received_amount = 0.00;
    $actual_qty = 0;
    $actual_amount = 0.00;
    $insect = 0;
    $label = 0;
    $sealing = 0;
    $expired = 0;
    $date_problem = 0;
    $broken = 0;
    $VHsealing = 0;
    $good = 0;
    $intentional = 0;
    $soft = 0;
    $bodyleakage = 0;
    $others = 0;
    $total_negative_qty = 0;
    $total_negative_amount = 0.00;
    $remarks = '';
}
if ($status == 1) {
    header("Location: report.php?id=$id");
    exit();
} 
?>
<main class="printable">
    <h2>Damage Report</h2>
    <p style="text-align: center;">
        <strong>ID:</strong> <?php echo $id; ?> |
        <strong>Type:</strong> <?php echo $shop_type; ?> |
        <strong>Received Date:</strong> <?php echo $received_date; ?> |
        <strong>Inspection Date:</strong> <?php echo $inspection_date; ?> |
        <strong>Trader:</strong> <?php echo $trader_name; ?>
    </p>
    <p style="text-align: center;">
        <strong>Send </strong> <?php echo $shop_total_qty." = ".$shop_total_amount; ?>/-
        <strong>Received </strong> <?php echo $received_total_qty." = ".$received_total_amount; ?>/-
        <strong>Actual </strong> <?php echo $actual_total_qty." = ".$actual_total_amount; ?>/-
        <strong></strong> <?php echo $status ? "" : "Draft"; ?>
    </p>

    <form action="damage_edit.php" method="get" style="display: flex; flex-wrap: wrap;" class="no-print">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="damage_details_id" value="<?= $id ?>">
        <div class="form-group" style="flex: 1 0 100%; margin: 0.5rem;">
            <label for="product_id">Product Details   <span type="hidden" id="rate"></span></label>
            <select name="product_id" id="product_id" class="form-control" required>
                <option value="">Select Product</option>
                <?php
                $stmt = $conn->prepare("SELECT id, name FROM products");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($product = $result->fetch_assoc()):
                ?>
                    <option value="<?= $product['id'] ?>" <?= isset($product_id) && $product_id == $product['id'] ? 'selected' : '' ?>><?= $product['name'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="shop_qty">Send Quantity</label>
            <input type="number" class="form-control" id="shop_qty" name="shop_qty" value="<?= $shop_qty ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="shop_amount">Send Amount</label>
            <input type="number" step="0.01" class="form-control" id="shop_amount" name="shop_amount" value="<?= $shop_amount ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="received_qty">Received Quantity</label>
            <input type="number" class="form-control" id="received_qty" name="received_qty" value="<?= $received_qty ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="received_amount">Received Amount</label>
            <input type="number" step="0.01" class="form-control" id="received_amount" name="received_amount" value="<?= $received_amount ?>" required>
        </div>
       
       
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="label">Label</label>
            <input type="number" class="form-control" id="label" name="label" value="<?= $label ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="sealing">Sealing</label>
            <input type="number" class="form-control" id="sealing" name="sealing" value="<?= $sealing ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="expired">Expired</label>
            <input type="number" class="form-control" id="expired" name="expired" value="<?= $expired ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="date_problem">DateP</label>
            <input type="number" class="form-control" id="date_problem" name="date_problem" value="<?= $date_problem ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="broken">Broken</label>
            <input type="number" class="form-control" id="broken" name="broken" value="<?= $broken ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="VHsealing">VHSeal</label>
            <input type="number" class="form-control" id="VHsealing" name="VHsealing" value="<?= $VHsealing ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="good">Good</label>
            <input type="number" class="form-control" id="good" name="good" value="<?= $good ?>" required>
        </div>
        
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="soft">Soft</label>
            <input type="number" class="form-control" id="soft" name="soft" value="<?= $soft ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="bodyleakage">BodyL.</label>
            <input type="number" class="form-control" id="bodyleakage" name="bodyleakage" value="<?= $bodyleakage ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="others">Others</label>
            <input type="number" class="form-control" id="others" name="others" value="<?= $others ?>" required>
        </div>
         <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="insect">Insect</label>
            <input type="number" class="form-control" id="insect" name="insect" value="<?= $insect ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="intentional">Intentional</label>
            <input type="number" class="form-control" id="intentional" name="intentional" value="<?= $intentional ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="total_negative_qty">T.NCLU Quantity</label>
            <input type="number" class="form-control" id="total_negative_qty" name="total_negative_qty" value="<?= $total_negative_qty ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="total_negative_amount">T.NCLU Amount</label>
            <input type="number" step="0.01" class="form-control" id="total_negative_amount" name="total_negative_amount" value="<?= $total_negative_amount ?>" required>
        </div>
         <div class="form-group" style="flex: 1 0 45%; margin: 0.5rem;">
            <label for="actual_qty">Actual Quantity</label>
            <input type="number" class="form-control" id="actual_qty" name="actual_qty" value="<?= $actual_qty ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 45%; margin: 0.5rem;">
            <label for="actual_amount">Actual Amount</label>
            <input type="number" step="0.01" class="form-control" id="actual_amount" name="actual_amount" value="<?= $actual_amount ?>" required>
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="remarks">Remarks</label>
            <input type="text" class="form-control" id="remarks" name="remarks" value="<?= $remarks ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="flex: 1 0 20%; margin: 0.5rem;">Add</button>
    </form>

  



<?php
// Fetch all rows from the damage_items table
$query = "SELECT * FROM damage_items WHERE damage_details_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0): ?>
     <div class="table-container">
    <table class="table table-striped table-bordered table-hover table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Damage Details ID</th>
                <th>Product ID</th>
                <th>Send Qty</th>
                <th>Send Amount</th>
                <th>Received Qty</th>
                <th>Received Amount</th>
                <th>Actual Qty</th>
                <th>Actual Amount</th>
            
                <th>Label</th>
                <th>Sealing</th>
                <th>Expired</th>
                <th>Date Problem</th>
                <th>Broken</th>
                <th>VHsealing</th>
                <th>good</th>
              
                <th>Soft</th>
                <th>Bodyleakage</th>
                <th>Others</th>
                <th>insect</th>
                <th>Intentional</th>
                <th>Total Negative Qty</th>
                <th>Total Negative Amount</th>
                <th>Remarks</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['damage_details_id']) ?></td>
                    <td><?= htmlspecialchars($row['product_id']) ?>

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
                    ?>
                
                
                
                </td>
                    <td><?= htmlspecialchars($row['shop_qty']) ?></td>
                    <td><?= htmlspecialchars($row['shop_amount']) ?></td>
                    <td><?= htmlspecialchars($row['received_qty']) ?></td>
                    <td><?= htmlspecialchars($row['received_amount']) ?></td>
                    <td><?= htmlspecialchars($row['actual_qty']) ?></td>
                    <td><?= htmlspecialchars($row['actual_amount']) ?></td>
                 
                    <td><?= htmlspecialchars($row['label']) ?></td>
                    <td><?= htmlspecialchars($row['sealing']) ?></td>
                    <td><?= htmlspecialchars($row['expired']) ?></td>
                    <td><?= htmlspecialchars($row['date_problem']) ?></td>
                    <td><?= htmlspecialchars($row['broken']) ?></td>
                    <td><?= htmlspecialchars($row['VHsealing']) ?></td>
                    <td><?= htmlspecialchars($row['good']) ?></td>
                
                    <td><?= htmlspecialchars($row['soft']) ?></td>
                    <td><?= htmlspecialchars($row['bodyleakage']) ?></td>
                    <td><?= htmlspecialchars($row['others']) ?></td>
                       <td><?= htmlspecialchars($row['insect']) ?></td>
                           <td><?= htmlspecialchars($row['intentional']) ?></td>
                    <td><?= htmlspecialchars($row['total_negative_qty']) ?></td>
                    <td><?= htmlspecialchars($row['total_negative_amount']) ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?></td>
                    <td>
                        <form method="POST" action="damage_edit.php?id=<?php echo $id; ?>&delete_id=<?= htmlspecialchars($row['id']) ?>" onsubmit="return confirm('Are you sure you want to delete this item?');">
                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
                            <button type="submit" class="btn btn-link">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
<?php else: ?>
    <p>---------</p>
<?php endif; ?>
<div class="button-group">
    <button onclick="window.location.href='damages.php'" class="btn btn-outline-primary">
        <i class="bi bi-list"></i> Damage List
    </button>
    <button onclick="window.location.href='report.php?id=<?= htmlspecialchars($id) ?>'" class="btn btn-outline-info">
        <i class="bi bi-file-text"></i> View Report
    </button>
</div>

<style>
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
    <script>
        document.getElementById('product_id').addEventListener('change', function() {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var rate = JSON.parse(this.responseText);
                  
                    var amount;

                    if ("<?php echo $shop_type; ?>" == 'TP') {
                        console.log(rate);
                        amount = parseFloat(rate.tp_rate).toFixed(2);
                    } else {
                        console.log(rate);
                        amount = parseFloat(rate.dp_rate).toFixed(2);
                    }

                    document.getElementById('rate').innerHTML = "Rate: " + parseFloat(amount).toFixed(2);
                    document.getElementById('shop_amount').value = (parseFloat(amount) * parseFloat(document.getElementById('shop_qty').value)).toFixed(2);
                    document.getElementById('received_amount').value = (parseFloat(amount) * parseFloat(document.getElementById('received_qty').value)).toFixed(2);
                    document.getElementById('actual_amount').value = (parseFloat(amount) * parseFloat(document.getElementById('actual_qty').value)).toFixed(2);
                    document.getElementById('total_negative_amount').value = (parseFloat(amount) * parseFloat(document.getElementById('total_negative_qty').value)).toFixed(2);               
                }
            };
            xhttp.open("GET", "get_rate.php?id=" + this.value, true);
            xhttp.send();
        });


        document.getElementById('shop_qty').addEventListener('change', function() {
            document.getElementById('shop_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * this.value).toFixed(2);
        });
        document.getElementById('received_qty').addEventListener('change', function() {
            document.getElementById('received_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * this.value).toFixed(2);
        });
        document.getElementById('actual_qty').addEventListener('change', function() {
            document.getElementById('actual_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * this.value).toFixed(2);
        });
        document.getElementById('total_negative_qty').addEventListener('change', function() {
            document.getElementById('total_negative_amount').value = parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()).toFixed(2) * this.value;
        });

        document.getElementById('insect').addEventListener('change', function() {
            document.getElementById('total_negative_qty').value = parseInt(document.getElementById('insect').value) + parseInt(document.getElementById('intentional').value);
            document.getElementById('total_negative_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * document.getElementById('total_negative_qty').value).toFixed(2);
        });
        document.getElementById('intentional').addEventListener('change', function() {
            document.getElementById('total_negative_qty').value = parseInt(document.getElementById('insect').value) + parseInt(document.getElementById('intentional').value);
            document.getElementById('total_negative_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * document.getElementById('total_negative_qty').value).toFixed(2);
        });
        document.getElementById('label').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('sealing').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('expired').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('date_problem').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('broken').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('VHsealing').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('good').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('soft').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('bodyleakage').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });
        document.getElementById('others').addEventListener('change', function() {
            updateActualQtyAndAmount();
        });

        function updateActualQtyAndAmount() {
            document.getElementById('actual_qty').value =
                (parseInt(document.getElementById('label').value) + 
                parseInt(document.getElementById('sealing').value) + 
                parseInt(document.getElementById('expired').value) + 
                parseInt(document.getElementById('date_problem').value) + 
                parseInt(document.getElementById('broken').value) + 
                parseInt(document.getElementById('VHsealing').value) + 
                parseInt(document.getElementById('good').value) + 
                parseInt(document.getElementById('intentional').value) + 
                parseInt(document.getElementById('soft').value) + 
                parseInt(document.getElementById('bodyleakage').value) + 
                parseInt(document.getElementById('others').value));
            document.getElementById('actual_amount').value = (parseFloat(document.getElementById('rate').innerHTML.split(':')[1].trim()) * document.getElementById('actual_qty').value).toFixed(2);
        }
        
    </script>

</main>

<?php
include_once 'footer.php';
?>