<?php
include 'header.php';
?>
<?php
//if serial_print_id is set, update printed_at to current timestamp
if (isset($_GET['serial_print_id']) && !empty($_GET['serial_print_id']) && is_numeric($_GET['serial_print_id']) && $_GET['serial_print_id'] > 0 ) {

    //if user is not admin, redirect to $_SESSION['role'] == 1
    if ($_SESSION['role'] != 1) {
        echo "<script>alert('You are not authorized to Update.'); window.location.href='serials.php';</script>";
        exit();
    }

    $serial_print_id = $_GET['serial_print_id'];
    $update_query = "UPDATE serials SET printed_at=NOW() WHERE id='$serial_print_id'";
    mysqli_query($conn, $update_query);
    //make all the order ids in orders table as approved (order_status=1)
    $select_query = "SELECT order_ids FROM serials WHERE id='$serial_print_id'";
    $result = mysqli_query($conn, $select_query);
    $row = mysqli_fetch_assoc($result);
    $order_ids = $row['order_ids'];
    $order_ids_array = explode(',', $order_ids);
    foreach ($order_ids_array as $order_id) {

        $update_query = "UPDATE orders SET order_status='1', approved_at=NOW(),approved_by='" . $_SESSION['user_id'] . "' WHERE id='$order_id'";
        mysqli_query($conn, $update_query);
    }
    echo "<script>alert('Serial marked as printed'); window.location.href='serials.php';</script>";
    exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   

    if (isset($_POST['add_serial'])) {
        $serial_order_ids = $_POST['serial_order_ids'];
        $status = $_POST['status'];
        $user_id = $_POST['user_id'];
        $company_id = $_SESSION['company_id'];
        $check_query = "SELECT id FROM serials WHERE order_ids='$serial_order_ids' AND status='1' ";
        $check_result = mysqli_query($conn, $check_query);
        if (mysqli_num_rows($check_result) > 0) {
            echo "<script>alert('Serial Order IDs already exists'); window.location.href='serials.php';</script>";
            exit();
        }

        //check any id in serial_order_ids doesnot exists in orders table
        $order_ids_array = explode(',', $serial_order_ids);
        foreach ($order_ids_array as $order_id) {
            $check_query = "SELECT id, created_by FROM orders WHERE id='$order_id' AND company_id='$company_id'";
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) == 0 || $_SESSION['user_id'] != mysqli_fetch_assoc($check_result)['created_by']) {
                echo "<script>alert('Order ID $order_id does not exist in orders table'); window.location.href='serials.php?serial_order_id=$serial_order_ids';</script>";
                exit();
            }
        }
        //check if any of the order ids in serial_order_ids already exists in serials table
        $order_ids_array = explode(',', $serial_order_ids);
        foreach ($order_ids_array as $order_id) {
            $check_query = "SELECT id FROM serials WHERE status='1' AND (order_ids LIKE '%,$order_id,%' OR order_ids LIKE '$order_id,%' OR order_ids LIKE '%,$order_id' OR order_ids LIKE '$order_id')";
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) > 0) {
                $count=mysqli_num_rows($check_result);
                echo "<script>alert(' $count Serial Order IDs already exists'); window.location.href='serials.php?serial_order_id=$serial_order_ids';</script>";
                exit();
            }
        }

        $query = "INSERT INTO serials (order_ids, status, user_id, company_id, created_at,created_by) VALUES ('$serial_order_ids', '$status', '$user_id', '$company_id', NOW(), " . $_SESSION['user_id'] . ")";
        mysqli_query($conn, $query);
        header("Location: serials.php");
        exit();
    }
    if (isset($_POST['update_serial'])) {
        $serial_id = $_GET['serial_edit_id'];
        $serial_order_ids = $_POST['serial_order_ids'];
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];

     //check if any of the order ids in serial_order_ids already exists in serials table
        $order_ids_array = explode(',', $serial_order_ids);
        foreach ($order_ids_array as $order_id) {
            $check_query = "SELECT id FROM serials WHERE status='1' AND id!='$serial_id' AND (order_ids LIKE '%,$order_id,%' OR order_ids LIKE '$order_id,%' OR order_ids LIKE '%,$order_id' OR order_ids LIKE '$order_id')";
            $check_result = mysqli_query($conn, $check_query);
            if (mysqli_num_rows($check_result) > 0) {
                $count=mysqli_num_rows($check_result);
                echo "<script>alert(' $count Serial Order IDs already exists'); window.location.href='serials.php?serial_order_id=$serial_order_ids';</script>";
                exit();
            }
        }
        //if pribnted_at is not null, do not allow update
        $check_query = "SELECT printed_at FROM serials WHERE id='$serial_id'";
        $check_result = mysqli_query($conn, $check_query);
        $check_row = mysqli_fetch_assoc($check_result);
        if ($check_row['printed_at'] != NULL) {
            echo "<script>alert('This serial is already printed'); window.location.href='serials.php';</script>";
            exit();
        }
        $update_fields = "order_ids='$serial_order_ids', status='$status', user_id='$user_id'";
        $query = "UPDATE serials SET $update_fields WHERE id='$serial_id'";
        mysqli_query($conn, $query);
        //update orders table to set order_status=1 for all order ids in serial_order_ids
        $order_ids_array = explode(',', $serial_order_ids);
        foreach ($order_ids_array as $order_id) {
            $update_query = "UPDATE orders SET order_status=1 WHERE id='$order_id'";
            mysqli_query($conn, $update_query);
        }
        header("Location: serials.php");
        exit();
    }
    
}
      

?>
    <div class="print-header">
           <h1><?php echo APP_NAME; ?> Report</h1>
        <p>Generated by: <?php echo $_SESSION['user_id'] . "@".$_SESSION['username']. " | Company: " . $_SESSION['company_id']; ?> | Date: <?php echo date("Y-m-d"); ?></p>

    </div>

    <div class="container">

        <div class="text-center" style="text-align: center; margin: 30px 0;">
            <h2 style="font-weight: 300; font-size: 2rem;">Serial List</h2>
            <p style="color: #666;">Create and manage serials.</p>
        </div>

        
        
        


        

        <div class="glass-panel form-section">
            <span class="section-title">New Serial Add</span>
            <form method="POST">


                <?php if (isset($_GET['serial_edit_id'])) {
                    $serial_edit_id = $_GET['serial_edit_id'];
                    $query = "SELECT * FROM serials WHERE id='$serial_edit_id'";
                    $result = mysqli_query($conn, $query);
                    if (mysqli_num_rows($result) > 0) {
                        $serial_data = mysqli_fetch_assoc($result);
                    }
                }
                ?>
                <div>
                    <input type="hidden" name="serial_edit_id" value="<?php echo isset($serial_edit_id) ? $serial_edit_id : ''; ?>">
                    <label>Serial Order IDs <span id="num_order_ids"></span><br><span id="duplicates_ids"></span></label>
                    <input type="text" placeholder="Serial Order IDs" name="serial_order_ids" id="serial_order_ids"
                    pattern="^([0-9]+,?)*[0-9]+$" 
                    title="Please enter only numbers and commas."
                    value="<?php
                    if (isset($_GET['serial_order_id'])) {
                        echo htmlspecialchars($_GET['serial_order_id']);
                    } else
                    echo htmlspecialchars(isset($serial_data['order_ids']) ? $serial_data['order_ids'] : ''); 
                    
                    
                    ?>" required>
                </div>
                <div class="desktop-span-2">
                   <div class="grid-layout desktop-4" style="grid-template-columns: 1fr 1fr;">
                  
                    
                    <div><label>User</label>

                    <select name="user_id" id= "user_id" required>
                        <option value="">Select User</option>
                        <?php
                        //if session role is admin, show all users of the company
                        if ($_SESSION['role'] == 1) {
                            $query = "SELECT id, username FROM users WHERE company_id = " . $_SESSION['company_id'];
                            $result = mysqli_query($conn, $query);
                            while ($user = mysqli_fetch_assoc($result)) {
                                echo "<option value='$user[id]' " . (isset($serial_data['user_id']) && $serial_data['user_id'] == $user['id'] ? 'selected' : '') . ">$user[username]</option>";
                            }
                        }
                        //if session role is not admin, show only the userself 
                        else {
                           echo "<option value='" . $_SESSION['user_id'] . "' selected>" . $_SESSION['username'] . "</option>";
                        }
                        ?>
                    </select>
          </select>
                          <script>
//on change id="serial_order_ids" , remove all spaces
    document.getElementById('serial_order_ids').addEventListener('input', function() {
        this.value = this.value.replace(/\s+/g, '');
        //remove anythingelse other than numbers and commas
        this.value = this.value.replace(/[^0-9,]/g, '');

        //calculate the number of order ids
        var orderIds = this.value.split(',');
        var numOrderIds = orderIds.length;
        document.getElementById('num_order_ids').textContent = numOrderIds;

        //show duplicates ids text in red
        var duplicates = orderIds.filter((item, index) => orderIds.indexOf(item) !== index);
        if (duplicates.length > 0) {
            document.getElementById('duplicates_ids').textContent = ' (Duplicates: ' + duplicates.join(', ') + ')';
            document.getElementById('duplicates_ids').style.color = 'red';
            //hide add/update button
            document.querySelector('button[type="submit"]').style.display = 'none';
         
            

        } else {
            document.getElementById('duplicates_ids').textContent = '';
            //show add/update button
            document.querySelector('button[type="submit"]').style.display = 'inline-block';
        }




    }     );

    $(document).ready(function() {
        // Initialize Select2 on the Add/Edit form elements
        $('#user_id').select2({
            width: '100%',
            placeholder: "Select User"
        });
     
      
    });
</script>
                
                </div>
                    <div><label>Status</label>
                    <select name="status">
                        <option value="1" <?php echo isset($serial_data['status']) && $serial_data['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo isset($serial_data['status']) && $serial_data['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                    </div>
                </div>
                
                
                
                <div class="form-actions">
                    <?php if (isset($serial_edit_id)) {
                        echo '<button type="submit" name="update_serial" class="btn btn-yellow"><i class="fa-solid fa-edit"></i> Update Serial</button>';
                    } else {
                        echo '<button type="submit" name="add_serial" class="btn btn-yellow"><i class="fa-solid fa-plus"></i> Add Serial</button>';
                    }

                    ?>
                </div>
                 
             


            </form>
        </div>

        <div class=" form-section" style="margin-bottom: 20px;">
          
            <form method="GET">


                <?php if (isset($_GET['cash_edit_id'])) {
                    $cash_edit_id = $_GET['cash_edit_id'];
                    $query = "SELECT * FROM cash_collections WHERE id='$cash_edit_id'";
                    $result = mysqli_query($conn, $query);
                    if (mysqli_num_rows($result) > 0) {
                        $cash_data = mysqli_fetch_assoc($result);
                    }
                }
                ?>
                <div class="desktop-span-2">
                   


                   
                    
                <div class="grid-layout desktop-4" style="grid-template-columns: 1fr 1fr;">
                    <div><label>Date</label>
                    <input type="date" placeholder="Date" name="search_date" value="<?php echo isset($_GET['search_date']) ? $_GET['search_date'] : date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div><label>ID</label>
                    <input type="number"  placeholder="All" name="search_id" value="<?php echo htmlspecialchars(isset($_GET['search_id']) ? $_GET['search_id'] : ''); ?>">
                    </div>
                </div>
                
                
                
                <div class="form-actions">
                   <button type="submit" name="search_serial" class="btn btn-green"><i class="fa-solid fa-search"></i> Search</button>
                </div>
                 
             
 

            </form>
        </div>

        <div class="glass-panel printable" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <span class="section-title" style="margin:0;">All serials</span>
                <button onclick="window.print()" class="btn btn-dark" style="padding: 5px 15px; font-size: 0.8rem;"><i class="fa-solid fa-print"></i></button>
            </div>
            
            <div class="table-responsive">
                <table class="table-simple">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Order IDs</th>
                            <th>Status</th>
                           
                      
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM serials WHERE company_id='{$_SESSION['company_id']}' AND user_id='{$_SESSION['user_id']}' ORDER BY id DESC LIMIT 5";

                        //if search_date is set
                        if (isset($_GET['search_date']) && !empty($_GET['search_date'])) {
                            $search_date = $_GET['search_date'];
                            $query = "SELECT * FROM serials WHERE company_id='{$_SESSION['company_id']}' AND user_id='{$_SESSION['user_id']}' AND DATE(created_at)='$search_date' ORDER BY id DESC";
                        }
                        //if search_id is set
                        if (isset($_GET['search_id']) && !empty($_GET['search_id'])) {
                            $search_id = $_GET['search_id'];
                            $query = "SELECT * FROM serials WHERE company_id='{$_SESSION['company_id']}' AND id='$search_id' ORDER BY id DESC";
                        }
                        $result = mysqli_query($conn, $query);

                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                //username
                                $user_id = $row['user_id'];
                                $user_query = "SELECT username FROM users WHERE id = $user_id";
                                $user_result = mysqli_query($conn, $user_query);
                                $user_row = mysqli_fetch_assoc($user_result);
                                $user_username = $user_row['username'];
                              echo "
<tr>
    <td>{$row['id']} ";
    if ($row['printed_at'] != NULL) {
        echo "<i class='fa-solid fa-check' style='color: green; margin-right: 10px; cursor: pointer;'></i>";
    }
    else {
    echo "<i class='fa-solid fa-clock' style='color: red; margin-right: 10px; cursor: pointer;' onclick=\"window.location.href='serials.php?serial_print_id={$row['id']}'\"></i>
    ";
    }

    echo "
    </td>
    <td>{$user_username} ({$row['user_id']}) <i class='fa-solid fa-print' style='color: var(--warning); margin-right: 10px; cursor: pointer;' onclick=\"window.location.href='invoices.php?order_ids={$row['order_ids']}'\"></i> <br>{$row['created_at']}</td>
    <td>{$row['order_ids']}</td>
    <td>" . 
        ($row['status'] 
            ? "<span class='badge bg-green'>Active</span>" 
            : "<span class='badge bg-red'>Inactive</span>") . 
    "</td>
    <td style='text-align: right;'>
    "; if ($row['printed_at'] == NULL) {

       echo  "<i class='fa-solid fa-pen' 
           style='color: var(--warning); margin-right: 10px; cursor: pointer;' 
           onclick=\"window.location.href='serials.php?serial_edit_id={$row['id']}'\">
        </i>";
    } else {
        echo "<span class='badge bg-red'>Printed</span>";
    }
       echo "
    </td>
</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>


                            


        

        

    </div>



<?php
include 'footer.php';
?>