<?php
include 'header.php';
?>
<?php

$order_id = null;
    if (isset($_GET['order_id'])) {
        $order_id = $_GET['order_id'];
    } else {
        echo "<script>alert('Order ID not specified.'); window.location.href='orders.php';</script>";
        exit();
    }


    //if order_id and confirmation is set, update order_status to confirmed(1)
    if (isset($_GET['order_confirm']) && $_GET['order_confirm'] == 1) {
        $conn->begin_transaction();
        try {
            // 1. Check if already confirmed to prevent double-deduction
            $stmt = $conn->prepare("SELECT order_status FROM orders WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $current_status = $stmt->get_result()->fetch_assoc()['order_status'] ?? 0;
            $stmt->close();

            if ($current_status == 1) {
                $conn->rollback();
                echo "<script>alert('Order is already confirmed.'); window.location.href='orders.php?search_id=$order_id';</script>";
                exit();
            }

            // 2. Calculate total
            $stmt = $conn->prepare("SELECT SUM(price * quantity) as total FROM order_items WHERE order_id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt->close();

            // 3. Update order status
            $stmt = $conn->prepare("UPDATE orders SET order_status='1' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();

            // 4. Deduct from shop balance
            $stmt = $conn->prepare("UPDATE shops SET balance = balance - ? WHERE id = ?");
            $stmt->bind_param("di", $total, $shop_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo "<script>alert('Order confirmed and shop balance updated.'); window.location.href='orders.php?search_id=$order_id';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Confirmation failed.'); window.location.href='order_item.php?order_id=$order_id';</script>";
        }
        exit();
    }
    //get details from orders table
    $sql = "SELECT * FROM orders WHERE id = $order_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $order_date = $row['order_date'];
        $delivery_date = $row['delivery_date'];
        $route_id = $row['route_id'];
        $shop_id = $row['shop_id'];
        $status = $row['status'];
        $order_status = $row['order_status'];
        $approved_by = $row['approved_by'];
        $approved_date = $row['approved_at'];

        $remarks = $row['remarks'];
    } else {
        echo "<script>alert('Order not found.'); window.location.href='orders.php';</script>";
        exit();
    }
    $sql = "SELECT * FROM routes WHERE id = $route_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $route_name = $row['route_name'];
    } else {
        echo "<script>alert('Route not found.'); window.location.href='orders.php';</script>";
        exit();
    }
    $sql = "SELECT * FROM shops WHERE id = $shop_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $shop_name = $row['shop_name'];
        $shop_balance = $row['balance'];
    } else {
        echo "<script>alert('Shop not found.'); window.location.href='orders.php';</script>";
        exit();
    }
    $sql = "SELECT * FROM order_items WHERE order_id = $order_id";
    $result = $conn->query($sql);
    $order_items = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $order_items[] = $row;
        }
    }
?>

<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   

    if (isset($_POST['add_item'])) {
        $order_id = $_POST['order_id'];
        $item_id = $_POST['item_id'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        
        //status of orders table is confirmed(1), prevent deletion
        $order_query = "SELECT order_status FROM orders WHERE id='$order_id'";
        $order_result = mysqli_query($conn, $order_query);
        $order_row = mysqli_fetch_assoc($order_result);
        if ($order_row['order_status'] == 1) {
            echo "<script>alert('Cannot Add item to a confirmed order.'); window.location.href='order_item.php?order_id=$order_id';</script>";
            exit();
        }


        $check_query = "SELECT id FROM order_items WHERE item_id='$item_id' AND order_id='$order_id'";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            echo "<script>alert('item already exists'); window.location.href='order_item.php?order_id=$order_id';</script>";
            exit();
        }

        $query = "INSERT INTO order_items (order_id, item_id, price, quantity) VALUES ('$order_id', '$item_id', '$price', '$quantity')";
        mysqli_query($conn, $query);
        header("Location: order_item.php?order_id=$order_id");
        exit();
    }
  
    
}
 if (isset($_GET['item_delete_id']) && isset($_GET['order_id']) && $_GET['item_delete_id'] !== '' && $_GET['order_id'] !== '') {
        $item_id = $_GET['item_delete_id'];
        $order_id = $_GET['order_id'];

        //status of orders table is confirmed(1), prevent deletion
        $order_query = "SELECT order_status FROM orders WHERE id='$order_id'";
        $order_result = mysqli_query($conn, $order_query);
        $order_row = mysqli_fetch_assoc($order_result);
        if ($order_row['order_status'] == 1) {
            echo "<script>alert('Cannot delete item from a confirmed order.'); window.location.href='order_item.php?order_id=$order_id';</script>";
            exit();
        }

        $delete_query = "DELETE FROM order_items WHERE id='$item_id'";
        mysqli_query($conn, $delete_query);
        header("Location: order_item.php?order_id=$order_id");
        exit();
    }
      

?>
    <div class="print-header">
           <h1><?php echo APP_NAME; ?> Report</h1>
        <p>Generated by: <?php echo $_SESSION['user_id'] . "@".$_SESSION['username']. " | Company: " . $_SESSION['company_id']; ?> | Date: <?php echo date("Y-m-d"); ?></p>

    </div>

    <div class="container">

        <div class="text-center" style="text-align: center;">
          
            <p style="color: var(--primary);">
                <strong>Order ID:</strong> <?php echo $order_id; ?> </p>
            <p style="color: var(--primary);">
                <strong>Order Date:</strong> <?php echo $order_date; ?> |    
                <strong>Delivery Date:</strong> <?php echo $delivery_date; ?> </p>
                  <p style="color: var(--primary);">
                <strong>Route:</strong> <?php echo $route_name; ?>  </p>
            <p style="color: var(--primary);">
                <strong>Shop:</strong> <?php echo $shop_name; ?>     
                <strong>[</strong> <?php echo $shop_balance; ?>/= ]</p>
          
            <p style="color: var(--primary);">
                <strong>Status:</strong> <?php echo $status == '1' ? 'Active' : 'Inactive'; ?> |    
                <strong>Order Status:</strong> <?php echo $order_status == '1' ? 'Confirmed' : 'Draft'; ?>
                
            </p>
            <?php if (!empty($approved_by)) {   
                //get username from users table
                $user_query = "SELECT username FROM users WHERE id='$approved_by'";
                $user_result = mysqli_query($conn, $user_query);
                $user_row = mysqli_fetch_assoc($user_result);
                $approved_by_name = $user_row['username'];
                ?>
                <p style="color: var(--primary);">
                    <strong>Approved By:</strong> <?php echo $approved_by_name; ?>   
                    <strong>@</strong> <?php echo $approved_date; ?> </p>
                <?php }
                
                ?>
            <?php if (!empty($remarks)) { ?>
            <p style="color: var(--primary);">
                <strong>Remarks:</strong> <?php echo $remarks; ?>
            </p>
            <?php } ?>

            <button class="btn btn-primary" style="margin-top: 15px; margin-bottom: 15px; border-radius: 0; font-size: 0.8rem; color: #fff; background-color: var(--primary);" onclick="showLast10Orders()">Show Last 10 Orders</button>
            <div style=" margin-bottom: 15px;" id="last10Orders" onclick="showLast10Orders()"></div>

            <script>
                function showLast10Orders() {
                    //if already loaded, do not load again and hide
                    if (document.getElementById("last10Orders").innerHTML != "") {
                        document.getElementById("last10Orders").innerHTML = "";
                        return;
                    }
                    var xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("last10Orders").innerHTML = this.responseText;
                        }
                    };
                    xhttp.open("GET", "getLast10Orders.php?shop_id=<?php echo $shop_id; ?>&order_id=<?php echo $order_id; ?>", true);
                    xhttp.send();
                }
            </script>

           
        </div>

        
        
        


        

        <div class="glass-panel form-section">
            <span class="section-title">Add Item</span>
            <form method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">


                <div class="form-group">
                    <label for="item_name">Item Name</label>
                    <select id="item_name" name="item_id" class="form-control" required>
                        <option value="">Select an option</option>
                        <?php
                        $sql = "SELECT id, item_name FROM items WHERE company_id='{$_SESSION['company_id']}' AND status=1 ORDER BY item_name ASC";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . $row['id'] . '">' . $row['item_name'] . '</option>';
                            }
                        }
                        ?>
                    </select>
                          <script>
    $(document).ready(function() {
        // Initialize Select2 on the Add/Edit form elements
        $('#item_name').select2({
            width: '100%',
            placeholder: "Select Item"
        });
     
      
    });
</script>
                </div>
          
                <div class="grid-layout desktop-4" style="grid-template-columns: 1fr 1fr;">
                  
                    
                    <div><label>Quantity</label><input type="number" placeholder="Quantity" name="quantity" value="<?php echo htmlspecialchars(isset($item_data['quantity']) ? $item_data['quantity'] : ''); ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                    
                    <div><label>Price</label><input type="number" step="0.01" placeholder="Price" name="price" value="<?php echo htmlspecialchars(isset($item_data['price']) ? $item_data['price'] : ''); ?>" required></div>
                </div>
           
                  <div class="form-actions">
               
                 
                       <button type="submit" name="add_item" class="btn btn-yellow"><i class="fa-solid fa-plus"></i> Add Item</button>
                   
                </div>
             


            </form>
        </div>

        

        <div class="glass-panel printable">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <span class="section-title" style="margin:0;">All items</span>
                <button onclick="window.print()" class="btn btn-dark" style="padding: 5px 15px; font-size: 0.8rem;"><i class="fa-solid fa-print"></i></button>
            </div>
            
            <div class="table-responsive">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Details</th>
                            
                           
                      
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_amount = 0;
                        $query = "SELECT * FROM order_items WHERE order_id = $order_id ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);

                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $total_amount += $row['price'] * $row['quantity'];
                                //item name
                                $item_id = $row['item_id'];
                                $item_query = "SELECT item_name FROM items WHERE id = $item_id";
                                $item_result = mysqli_query($conn, $item_query);
                                $item_row = mysqli_fetch_assoc($item_result);
                                $item_name = $item_row['item_name'];
                              echo "
<tr>
    <td>{$row['id']}</td>
    <td>{$item_name} " . number_format($row['price'], 2) . " X " . number_format($row['quantity']) . " = " . number_format($row['price'] * $row['quantity'], 2) . "</td>
   
    <td style='text-align: right;'>
        <i class='fa-solid fa-trash' 
           style='color: var(--warning); margin-right: 10px; cursor: pointer;' 
           onclick=\"window.location.href='order_item.php?item_delete_id={$row['id']}&order_id={$order_id}'\">
        </i>
    </td>
</tr>";
                            }
                        }
                        echo "<tr><td colspan='3'><strong>Total Amount: </strong><strong>" . number_format($total_amount, 2) . "</strong></td></tr>";

                        ?>
                    </tbody>
                </table>
            </div>
        </div>


                            


        

        <div class="glass-panel printable" style="margin-top: 30px; text-align: center;">
            <button onClick="window.location.href='orders.php?search_id=<?php echo $order_id; ?>'"  class="btn btn-yellow"><i class="fa-solid fa-clipboard"></i> Draft</button>
<button onClick="window.location.href='order_item.php?order_id=<?php echo $order_id; ?>&order_confirm=1'" class="btn btn-green"><i class="fa-solid fa-check"></i> Confirm</button>
        </div>

    </div>



<?php
include 'footer.php';
?>