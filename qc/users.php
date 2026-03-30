<?php
include_once 'header.php';
?>
<?php
$msg="Create and manage users in the system.";
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
?>
<main class="printable">
    <h2>Users</h2>
    <p><?php echo $msg; ?></p>


    <div class="form-row">
            <form class="card" method="POST" action="users.php">
                <h2>Update User</h2>
                <div class="form-group">
                    <label for="old_password">Old Password</label>
                    <input type="password" id="old_password" name="old_password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password">
                </div>
                <button type="submit" name="update_user">Submit</button>
            </form>



            <form class="card" method="POST" action="users.php">
                <h2>New User</h2>
               
                <div class="form-group">
                    <label for="username">User Name</label>
                    <input type="text" id="username" name="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password">
                </div>
                <button type="submit" name="add_user">Submit</button>
            </form>
        </div>


        <div class="table-container card">
            <h2>Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT id, username, status FROM users ORDER BY id DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
          
                        echo "<td>" . ($row['status'] == "active" ? " 🔵 Active" : " 🔴 Inactive") . "</td>";
                        echo "<td><a style= 'text-decoration: none' href='users.php?toggle=" . $row['id'] . "' class='btn-sm'>" . ($row['status'] == "active" ? "✖️  " : "✔️ ") . "</a></td>"; 
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