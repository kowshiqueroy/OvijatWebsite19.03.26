<?php
include_once 'header.php';
?>
<?php


if (isset($_GET['delid'])) {
    $delid = $_GET['delid'];
    $stmt = $conn->prepare("DELETE FROM damage_details WHERE id = ?");
    $stmt->bind_param("i", $delid);
    if ($stmt->execute()) {
        $msg = "Damage report deleted successfully!";
    } else {
        $msg = "Error deleting damage report: " . $conn->error;
    }
    $stmt->close();
}





if (isset($_GET['toggle'])) {
    $user_id = $_GET['toggle'];

    // Fetch current status
    $stmt = $conn->prepare("SELECT status FROM damage_details WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc(); 

    // Toggle status
    $new_status = ($row['status'] == '0') ? '1' : '1';

    // Update status in the database
    $stmt = $conn->prepare("UPDATE damage_details SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    if ($stmt->execute()) {
        $msg = "User status changed to {$new_status}!";
    } else {
        $msg = "Error changing user status: " . $conn->error;
    }
    $stmt->close();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Process the parameter value
}
if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
    $date_from = $_GET['date_from'];
    $date_to = $_GET['date_to'];
} else {
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d');
}

?>
<main class="printable">
    <h2>Damages</h2>
    <p style="text-align: center;">
       <?php echo isset($msg) ? $msg : ''; ?>
    </p>
    <p><button class="edit-btn" onclick="window.location.href='damages_create.php'">Create New Damage Report</button></p>

    <form action="damages.php" method="get" style="display: flex; flex-wrap: wrap;" class="no-print">
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="id">ID</label>
            <input type="number" class="form-control" id="id" name="id" value="<?= isset($id) ? $id : '' ?>">
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="date_from">Inspection</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= isset($date_from) ? $date_from : date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <label for="date_to">To Date</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= isset($date_to) ? $date_to : date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="flex: 1 0 20%; margin: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="flex: 1 0 20%; margin: 0.5rem; display: flex; align-items: center; justify-content: center;">Search</button>
        </div>
    </form>


     <div class="table-container">
          
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        
                        <th>R. I. Date</th>
                        <th>Trader</th>
                        <th>Send</th>
                        <th>Receive</th>
                        <th>Actual</th>
                        <th></th>
                        <th></th>
                   
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM damage_details ORDER BY id DESC");
                    if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
                        $stmt->prepare("SELECT * FROM damage_details WHERE id = ?");
                        $stmt->bind_param("i", $_GET['id']);
                    } else if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
                        $stmt->prepare("SELECT * FROM damage_details WHERE inspection_date >= ? AND received_date <= ?");
                        $stmt->bind_param("ss", $_GET['date_from'], $_GET['date_to']);
                    } else {
                        $stmt->prepare("SELECT * FROM damage_details ORDER BY id DESC LIMIT 10");
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td><a style= 'text-decoration: none' href='damage_edit.php?id=" . $row['id'] .
                         "' class='btn-sm'>" . ($row['status']==1 ? "" : "✏️Edit") . "</a> {$row['id']}


                        " . ($row['status']==1 ? "<a style='text-decoration: none' href='report.php?id={$row['id']}&type=full'>🖨️Full</a> <a style='text-decoration: none' href='report_mini.php?id={$row['id']}&type=mini'><small>🖨️</small>Mini</a>" : "") . "
                         </td>";
                       
                        echo "<td>R: {$row['received_date']} I: {$row['inspection_date']} <a style= 'text-decoration: none' href='damages.php?toggle=" 
                         . $row['id'] . "' class='btn-sm'>" . ($row['status']==1 ? "" : "🔴Confirm") . "</a> </td>";
                        echo "<td>{$row['shop_type']} - {$row['trader_name']}</td>";
                        echo "<td>{$row['shop_total_qty']} ={$row['shop_total_amount']}/-</td>";
                        echo "<td>{$row['received_total_qty']} ={$row['received_total_amount']}/-</td>";
                        echo "<td><a style='text-decoration: none' href='report.php?id={$row['id']}'>{$row['actual_total_qty']} ={$row['actual_total_amount']}/- </a></td>";

                        echo "<td>" . ($row['status'] == 0 ? "<a onclick=\"return confirm('Are you sure you want to delete this record?')\" href='damages.php?delid={$row['id']}' style='text-decoration:none'>🗑️</a>" : "") . "</td>";
                       
                       
                        $createdByQuery = $conn->query("SELECT username FROM users WHERE id = '{$row['created_by']}'");
                        $createdByUsername = ($createdByQuery->num_rows > 0) ? $createdByQuery->fetch_assoc()['username'] : "-";
                        
                        $updatedByQuery = $conn->query("SELECT username FROM users WHERE id = '{$row['updated_by']}'");
                        $updatedByUsername = ($updatedByQuery->num_rows > 0) ? $updatedByQuery->fetch_assoc()['username'] : "-";
                        
                        echo "<td>by: {$createdByUsername} ";
                        echo "{$row['created_at']} ";
                        echo "Updated: {$updatedByUsername} ";
                        echo "{$row['updated_at']}</td>";


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