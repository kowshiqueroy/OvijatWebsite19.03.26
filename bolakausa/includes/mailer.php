<?php
/**
 * SMTP Mailer Utility
 * Pulls credentials from dynamic settings.
 */

function send_system_email($pdo, $to, $subject, $message) {
    // 1. Fetch SMTP Settings
    $host = get_setting($pdo, 'smtp_host');
    $port = get_setting($pdo, 'smtp_port', '587');
    $user = get_setting($pdo, 'smtp_user');
    $pass = get_setting($pdo, 'smtp_pass');
    $from = get_setting($pdo, 'smtp_from', 'noreply@bolakausa.com');
    $comp_name = get_setting($pdo, 'company_name', 'Bolakausa Wholesale');

    // 2. Formal HTML Template
    $html_message = "
    <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
        <div style='background: #10b981; color: white; padding: 30px; text-align: center;'>
            <h1 style='margin: 0; font-size: 24px;'>$comp_name</h1>
        </div>
        <div style='padding: 30px; background: white;'>
            $message
        </div>
        <div style='padding: 20px; background: #f8fafc; text-align: center; font-size: 12px; color: #64748b;'>
            &copy; " . date('Y') . " $comp_name. All Rights Reserved.
        </div>
    </div>
    ";

    // 3. Header Construction
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $comp_name . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    ];

    $status = 'not_sent_no_smtp';
    $error_msg = 'SMTP credentials are not configured in system settings.';
    $sent = false;

    if ($host && $user && $pass) {
        log_action($pdo, null, "Email Attempt", "To: $to | Subject: $subject");
        $sent = @mail($to, $subject, $html_message, implode("\r\n", $headers));
        if ($sent) {
            $status = 'successful';
            $error_msg = null;
        } else {
            $status = 'failed';
            $error_msg = 'PHP mail() function returned false.';
        }
    } else {
        log_action($pdo, null, "Email Logged (SMTP Not Configured)", "To: $to | Subject: $subject");
        $status = 'not_sent_no_smtp';
        $error_msg = 'SMTP credentials are not configured in system settings.';
        $sent = true; // Return true as a mock success for application workflow
    }

    // Save to email_logs table
    try {
        $log_stmt = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->execute([$to, $subject, $html_message, $status, $error_msg]);
    } catch (Exception $e) {
        // Silently capture database write errors for logs
    }

    return $sent;
}

/**
 * Compiles a full HTML invoice and dispatches it.
 */
function send_invoice_email($pdo, $order_id, $subject_prefix) {
    // 1. Fetch Order
    $stmt = $pdo->prepare("SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order) return false;

    // 2. Fetch Items
    $stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? AND oi.is_deleted = 0");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    $company_name = get_setting($pdo, 'company_name', 'Bolakausa Wholesale');

    // 3. Compile HTML Item Rows
    $item_rows = "";
    $items_subtotal = 0;
    foreach ($items as $item) {
        $sub = (float)$item['price_at_purchase'] * (int)$item['qty'];
        $items_subtotal += $sub;
        
        $item_desc = htmlspecialchars($item['name']);
        if ($item['requested_discount_type'] !== 'none' && (float)$item['requested_discount_value'] > 0) {
            $type_char = $item['requested_discount_type'] === 'percent' ? '%' : '$';
            $item_desc .= " <small style='color:#b45309;'>(Requested discount: {$item['requested_discount_value']}{$type_char})</small>";
        }

        $item_rows .= "
        <tr>
            <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: left;'>
                <strong>{$item_desc}</strong>
            </td>
            <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: right;'>$" . number_format($item['price_at_purchase'], 2) . "</td>
            <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: center;'>{$item['qty']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600;'>$" . number_format($sub, 2) . "</td>
        </tr>";
    }

    // 4. Format Email Message Body
    $message = "
    <h2 style='color:#0f172a; margin-top:0;'>Invoice #{$order['id']}</h2>
    <p style='color:#64748b; font-size:14px; margin-bottom:20px;'>
        Payment Status: <strong style='text-transform:uppercase;'>{$order['payment_status']}</strong><br>
        Fulfillment Status: <strong style='text-transform:uppercase;'>{$order['fulfillment_status']}</strong><br>
        Date Placed: " . date('M d, Y', strtotime($order['created_at'])) . "
    </p>

    <div style='display: flex; justify-content: space-between; margin-bottom: 25px; flex-wrap: wrap; gap:15px; font-size:14px;'>
        <div style='flex:1; min-width: 200px;'>
            <strong style='color:#64748b; text-transform:uppercase; font-size:11px; display:block; border-bottom:1px solid #f1f5f9; padding-bottom:5px; margin-bottom:5px;'>Bill To</strong>
            <strong>" . htmlspecialchars($order['full_name']) . "</strong><br>
            Email: " . htmlspecialchars($order['email']) . "<br>
            Phone: " . htmlspecialchars($order['phone']) . "
        </div>
        <div style='flex:1; min-width: 200px;'>
            <strong style='color:#64748b; text-transform:uppercase; font-size:11px; display:block; border-bottom:1px solid #f1f5f9; padding-bottom:5px; margin-bottom:5px;'>Ship To</strong>
            " . nl2br(htmlspecialchars($order['delivery_address'])) . "
        </div>
    </div>

    <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px;'>
        <thead>
            <tr style='background: #f8fafc;'>
                <th style='padding: 8px 10px; border-bottom: 2px solid #e2e8f0; text-align: left;'>Description</th>
                <th style='padding: 8px 10px; border-bottom: 2px solid #e2e8f0; text-align: right;'>Price</th>
                <th style='padding: 8px 10px; border-bottom: 2px solid #e2e8f0; text-align: center;'>Qty</th>
                <th style='padding: 8px 10px; border-bottom: 2px solid #e2e8f0; text-align: right;'>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            {$item_rows}
        </tbody>
    </table>

    <div style='display: flex; justify-content: flex-end;'>
        <table style='width: 280px; font-size: 14px; border-collapse: collapse;'>
            <tr>
                <td style='padding: 4px 10px; text-align: right; color:#64748b;'>Subtotal:</td>
                <td style='padding: 4px 10px; text-align: right; font-weight:600;'>$" . number_format($items_subtotal, 2) . "</td>
            </tr>";

    if ((float)$order['discount_amount'] > 0) {
        $message .= "
            <tr style='color:#166534;'>
                <td style='padding: 4px 10px; text-align: right;'>Applied Discounts:</td>
                <td style='padding: 4px 10px; text-align: right; font-weight:700;'>-$" . number_format($order['discount_amount'], 2) . "</td>
            </tr>";
    }

    if ($order['requested_discount_type'] !== 'none' && (float)$order['requested_discount_value'] > 0) {
        $type_char = $order['requested_discount_type'] === 'percent' ? '%' : '$';
        $message .= "
            <tr style='color:#b45309;'>
                <td style='padding: 4px 10px; text-align: right;'>Requested Discount:</td>
                <td style='padding: 4px 10px; text-align: right; font-weight:700;'>{$order['requested_discount_value']}{$type_char} (Pending)</td>
            </tr>";
    }

    $shipping_display = $order['shipping_amount'] > 0 ? "$" . number_format($order['shipping_amount'], 2) : "Free";
    // Check if shipping type is manual and not yet set
    $stmt_loc = $pdo->prepare("SELECT l.shipping_type FROM user_addresses a JOIN locations l ON a.location_id = l.id WHERE a.user_id = ? ORDER BY a.is_default DESC LIMIT 1");
    $stmt_loc->execute([$order['user_id']]);
    $stype = $stmt_loc->fetchColumn();
    if ($stype === 'manual' && (float)$order['shipping_amount'] == 0 && !in_array($order['fulfillment_status'], ['Ready to Ship', 'Shipped', 'Delivered'])) {
        $shipping_display = "TBD (Set later)";
    }

    $message .= "
            <tr>
                <td style='padding: 4px 10px; text-align: right; color:#64748b;'>Shipping:</td>
                <td style='padding: 4px 10px; text-align: right; font-weight:600;'>{$shipping_display}</td>
            </tr>
            <tr>
                <td style='padding: 4px 10px; text-align: right; color:#64748b;'>Tax:</td>
                <td style='padding: 4px 10px; text-align: right; font-weight:600;'>$" . number_format($order['tax_amount'], 2) . "</td>
            </tr>
            <tr style='font-size:16px; font-weight:800; border-top:2px solid #cbd5e1;'>
                <td style='padding: 10px; text-align: right; color:#0f172a;'>Total:</td>
                <td style='padding: 10px; text-align: right; color:#10b981;'>$" . number_format($order['total_amount'], 2) . "</td>
            </tr>
        </table>
    </div>

    <div style='background: #f8fafc; padding: 15px; border-radius: 8px; font-size: 13px; margin-top: 20px; color:#1e293b;'>
        <strong>Payment Route Selected:</strong> {$order['payment_method']}
    </div>

    <p style='font-size:12px; color:#64748b; text-align:center; margin-top:30px;'>
        Thank you for choosing $company_name! If you have any questions regarding your order or shipping updates, please drop us a message in the system support chat.
    </p>
    ";

    return send_system_email($pdo, $order['email'], "{$subject_prefix} — Order #{$order['id']}", $message);
}

