<?php
//get last 10 orders from orders table
include '../config.php';
$company_id = $_SESSION['company_id'];
$shop_id = $_GET['shop_id'];
$order_id = $_GET['order_id'];

$query = "SELECT * FROM orders WHERE company_id='$company_id' AND shop_id='$shop_id' AND status='1' AND order_status='1' AND id!=$order_id ORDER BY id DESC LIMIT 10";

$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<p>Order #" . $row['id'] . " - " . $row['order_date']  . "</p>";
        //show order items
        $order_items_query = "SELECT oi.*, i.item_name FROM order_items oi
        JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id='" . $row['id'] . "'";
        $order_items_result = mysqli_query($conn, $order_items_query);
        if (mysqli_num_rows($order_items_result) > 0) {
            $grand_total = 0.00;
            while ($order_item_row = mysqli_fetch_assoc($order_items_result)) {
                $total= $order_item_row['price'] * $order_item_row['quantity'];
                $grand_total += $total;
                echo "<p>" . $order_item_row['item_name'] . " - " . $order_item_row['quantity'] . " X " . number_format($order_item_row['price'], 2) . " = " . number_format($total, 2) . "</p>";
            }
            echo "<p><strong>Grand Total: " . number_format($grand_total, 2) . "</strong></p>";
        }
    }
} else {
    echo "<p>No orders found</p>";
}
?>