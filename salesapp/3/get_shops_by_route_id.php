<?php
include '../config.php';

if (isset($_GET['route_id'])) {
    $route_id = $_GET['route_id'];

    $query = "SELECT id, shop_name FROM shops WHERE route_id='$route_id' AND status=1 ORDER BY id DESC";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<option value="' . $row['id'] . '">' . $row['shop_name'] . '</option>';
        }
    } else {
        echo '<option value="">No shops found</option>';
    }
}
?>