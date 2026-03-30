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
    $new_status = ($row['status'] == '0') ? '1' : '0';

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
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = 20;
                    $offset = ($page - 1) * $limit;
                    
                    $where = "";
                    $params = [];
                    $types = "";
                    
                    if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
                        $where = "WHERE id = ?";
                        $params[] = (int)$_GET['id'];
                        $types .= "i";
                    } else if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
                        $where = "WHERE inspection_date >= ? AND inspection_date <= ?";
                        $params[] = $_GET['date_from'];
                        $params[] = $_GET['date_to'];
                        $types .= "ss";
                    }
                    
                    $count_sql = "SELECT COUNT(*) as total FROM damage_details $where";
                    $stmt = $conn->prepare($count_sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $count_result = $stmt->get_result();
                    $total_row = $count_result->fetch_assoc();
                    $total_records = $total_row['total'];
                    $total_pages = ceil($total_records / $limit);
                    
                    $sql = "SELECT * FROM damage_details $where ORDER BY id DESC LIMIT $offset, $limit";
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
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
                        echo "<td>{$row['shop_type']} - " . htmlspecialchars($row['trader_name']) . "</td>";
                        echo "<td>{$row['shop_total_qty']} ={$row['shop_total_amount']}/-</td>";
                        echo "<td>{$row['received_total_qty']} ={$row['received_total_amount']}/-</td>";
                        echo "<td><a style='text-decoration: none' href='report.php?id={$row['id']}'>{$row['actual_total_qty']} ={$row['actual_total_amount']}/- </a></td>";

                        echo "<td>" . ($row['status'] == 0 ? "<a onclick=\"return confirm('Are you sure you want to delete this record?')\" href='damages.php?delid={$row['id']}' style='text-decoration:none'>🗑️</a>" : "") . "</td>";
                       
                       
                        $createdByQuery = $conn->prepare("SELECT username FROM users WHERE id = ?");
                        $createdByQuery->bind_param("i", $row['created_by']);
                        $createdByQuery->execute();
                        $createdByResult = $createdByQuery->get_result();
                        $createdByUsername = ($createdByResult->num_rows > 0) ? htmlspecialchars($createdByResult->fetch_assoc()['username']) : "-";
                        
                        $updatedByQuery = $conn->prepare("SELECT username FROM users WHERE id = ?");
                        $updatedByQuery->bind_param("i", $row['updated_by']);
                        $updatedByQuery->execute();
                        $updatedByResult = $updatedByQuery->get_result();
                        $updatedByUsername = ($updatedByResult->num_rows > 0) ? htmlspecialchars($updatedByResult->fetch_assoc()['username']) : "-";
                        
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

        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display: flex; justify-content: center; gap: 5px; margin-top: 20px; flex-wrap: wrap;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['date_from']) ? '&date_from='.$_GET['date_from'].'&date_to='.$_GET['date_to'] : '' ?>" class="btn btn-sm">Prev</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="btn btn-sm" style="background: #4a90e2; color: white;"><?= $i ?></span>
                <?php elseif ($i <= 3 || $i > $total_pages - 3 || abs($i - $page) <= 1): ?>
                    <a href="?page=<?= $i ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['date_from']) ? '&date_from='.$_GET['date_from'].'&date_to='.$_GET['date_to'] : '' ?>" class="btn btn-sm"><?= $i ?></a>
                <?php elseif ($i == 4 || $i == $total_pages - 3): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?><?= isset($_GET['id']) ? '&id='.$_GET['id'] : '' ?><?= isset($_GET['date_from']) ? '&date_from='.$_GET['date_from'].'&date_to='.$_GET['date_to'] : '' ?>" class="btn btn-sm">Next</a>
            <?php endif; ?>
            
            <span style="margin-left: 10px; font-size: 12px;">Total: <?= $total_records ?> records</span>
        </div>
        <?php endif; ?>








</main>

<?php
include_once 'footer.php';
?>