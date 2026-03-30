<?php
include_once 'header.php';
?>
<?php
$msg="Create and manage products in the system.";
    if (isset($_POST['update_user'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];

        if (empty($old_password) || empty($new_password)) {
            $msg= "Error: Please fill in all fields.";
        } else {

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $user_id = $_SESSION['user_id'];
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if (password_verify($old_password, $row['password'])) {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->bind_param("si", $hashed_new_password, $user_id);
                if ($stmt->execute()) {
                    $msg= "Password updated successfully.";
                } else {
                    $msg= "Error: " . $conn->error;
                }
                $stmt->close();
            } else {
                $msg= "Current password is incorrect.";
            }
        }
    }

    if (isset($_POST['add_user'])) {
       $username = $_POST['username'];
    $password = $_POST['password'];

    if (strlen($username) < 3 || strlen($username) > 10) {
        $msg = "Username must be 3-10 characters.";
    } elseif (strlen($password) < 3) {
        $msg = "Password must be at least 3 characters.";
    } else {

     
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed_password);
        try {
            $stmt->execute();
            $msg = "User added successfully!";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $msg = "Duplicate entry for username: $username";
            } else {
                $msg = "Error adding user: " . $e->getMessage();
            }
        }
        $stmt->close();

    }
    
}


if (isset($_GET['toggle'])) {
    $user_id = $_GET['toggle'];

    // Fetch current status
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
   
    
    // Toggle status
    $new_status = ($row['status'] === 'active') ? 'inactive' : 'active';
  
    
    // Update status in the database
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    if ($stmt->execute()) {
        $msg = "User status changed to {$new_status}!";
        
    } else {
        $msg = "Error changing user status: " . $conn->error;
    }
    $stmt->close();
}

if (isset($_POST['add_product'])) {
    if (empty($_POST['name']) || empty($_POST['tp_rate']) || empty($_POST['dp_rate'])) {
        echo "<script>alert('All fields are required');</script>";
        exit;
    }
    if (strlen($_POST['name']) < 3 || strlen($_POST['name']) > 50) {
        echo "<script>alert('Product name must be between 3 and 50 characters');</script>";
        exit;
    }
    if (!is_numeric($_POST['tp_rate']) || !is_numeric($_POST['dp_rate'])) {
        echo "<script>alert('TP Rate and DP Rate must be numeric');</script>";
        exit;
    }
    if ($_POST['tp_rate'] < 0 || $_POST['dp_rate'] < 0) {
        echo "<script>alert('TP Rate and DP Rate must be non-negative');</script>";
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO products (name, tp_rate, dp_rate) VALUES (?, ?, ?)");
    $stmt->bind_param("sdd", $_POST['name'], $_POST['tp_rate'], $_POST['dp_rate']);
    if ($stmt->execute()) {
        $msg = "Product added successfully!";
    } else {
        $msg = "Error adding product.";
    }
    $stmt->close();
}

if (isset($_POST['update_product'])) {
    if (empty($_POST['name']) || empty($_POST['tp_rate']) || empty($_POST['dp_rate'])) {
        $msg = "All fields are required";
    } elseif (strlen($_POST['name']) < 3 || strlen($_POST['name']) > 50) {
        $msg = "Product name must be between 3 and 50 characters";
    } elseif (!is_numeric($_POST['tp_rate']) || !is_numeric($_POST['dp_rate'])) {
        $msg = "TP Rate and DP Rate must be numeric";
    } elseif ($_POST['tp_rate'] < 0 || $_POST['dp_rate'] < 0) {
        $msg = "TP Rate and DP Rate must be non-negative";
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, tp_rate = ?, dp_rate = ? WHERE id = ?");
        $stmt->bind_param("sddi", $_POST['name'], $_POST['tp_rate'], $_POST['dp_rate'], $_POST['id']);
        if ($stmt->execute()) {
            $msg = "Product updated successfully!";
        } else {
            $msg = "Error updating product.";
        }
        $stmt->close();
    }
}
?>
<main class="printable">
    <h2>Products</h2>
    <p><?php echo $msg; ?></p>


    <div class="form-row-center">
            <?php if (isset($_GET['edit'])): ?>
            <?php
            $stmt = $conn->prepare("SELECT name, tp_rate, dp_rate FROM products WHERE id = ?");
            $stmt->bind_param("i", $_GET['edit']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            ?>
            <form class="card" method="POST" action="products.php">
                <h2>Edit Product</h2>
                <input type="hidden" id="product_id" name="id" value="<?php echo $_GET['edit']; ?>">

                <div class="form-group">
                    <label for="name3">Name</label>
                    <input type="text" id="name3" name="name" value="<?php echo htmlspecialchars($row['name']); ?>">
                </div>
                <div class="form-group">
                    <label for="tp_rate">TP Rate</label>
                    <input type="number" step="0.01" id="tp_rate" name="tp_rate" value="<?php echo htmlspecialchars($row['tp_rate']); ?>">
                </div>
                <div class="form-group">
                    <label for="dp_rate">DP Rate</label>
                    <input type="number" step="0.01" id="dp_rate" name="dp_rate" value="<?php echo htmlspecialchars($row['dp_rate']); ?>">
                </div>
                <button type="submit" name="update_product">Update</button>
            </form>
            <?php else: ?>
            <form class="card" method="POST" action="products.php">
                <h2>Add Product</h2>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name">
                </div>
                <div class="form-group">
                    <label for="tp_rate">TP Rate</label>
                    <input type="number" step="0.01" id="tp_rate" name="tp_rate">
                </div>
                <div class="form-group">
                    <label for="dp_rate">DP Rate</label>
                    <input type="number" step="0.01" id="dp_rate" name="dp_rate">
                </div>
                <button type="submit" name="add_product">Add</button>
            </form>
            <?php endif; ?>
        </div>


        <div class="table-container card">
            <h2>Products</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>TP/DP Rates</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM products ORDER BY id DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['tp_rate']) . " / " . htmlspecialchars($row['dp_rate']) . "</td>";
                        echo "<td><a style= 'text-decoration: none' href='products.php?edit=" . $row['id'] . "' class='btn-sm'>✏️</a></td>";
                        echo "</tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>





</main>

<?php
include_once 'footer.php';
?>