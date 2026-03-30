<?php
include_once 'header.php';
?>
<?php
$msg="Manage Shops in the system.";
  
   if (isset($_POST['update_shop'])) {
        $old_name = $_POST['old_name'];
        $new_name = $_POST['new_name'];

        if (empty($old_name) || empty($new_name)) {
            $msg= "Error: Please fill in all fields.";
        } else {

            $stmt = $conn->prepare("UPDATE damage_details SET trader_name = ? WHERE trader_name = ?");
            $stmt->bind_param("ss", $new_name, $old_name);
            if ($stmt->execute()) {
                $msg= "Shop name updated successfully.";
                header("Location: shops.php");
                exit();
            } else {
                $msg= "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }


?>
<main class="printable">
    <h2>Shop Details</h2>
    <p><?php echo $msg; ?></p>
  <div class="form-row">
<?php
if (isset($_GET['tname'])) {
    $tname = $_GET['tname'];
    ?>

        <form class="card" method="POST">
                <h2>Update Shop</h2>
                <div class="form-group">
                    <label for="old_name">Old Name</label>
                    <input type="text" id="old_name" name="old_name" value="<?php echo $tname; ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="new_name">New Name</label>
                    <input type="text" id="new_name" name="new_name" value="<?php echo $tname; ?>" required>
                </div>
                <button type="submit" name="update_shop">Submit</button>
            </form>

<?php


    }
?>
  
    

<div class="table-container card">
            <h2>Shops</h2>
            <table>
                <thead>
                    <tr>
                 
                        <th>Name</th>
                       
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT DISTINCT trader_name FROM damage_details");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                  
                        echo "<td>" . htmlspecialchars($row['trader_name']) . "</td>";
                        echo "<td><a style= 'text-decoration: none' href='shops.php?tname=" . $row['trader_name'] . "' class='btn-sm'>" . "✏️" . "</a></td>"; 
                        echo "</tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>


        </div>


        




</main>

<?php
include_once 'footer.php';
?>