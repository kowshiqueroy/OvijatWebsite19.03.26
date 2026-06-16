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

    // NOTE: In a real production environment with valid SMTP details, 
    // you would use PHPMailer or a similar library. 
    // For this prototype, we use mail() which is often routed via 
    // local SMTP (like Sendmail/Postfix or XAMPP's fake sendmail).

    if ($host && $user && $pass) {
        // Log sending attempt
        log_action($pdo, 0, "Email Attempt", "To: $to | Subject: $subject");
        
        // Suppress errors to prevent breaking the UI flow
        return @mail($to, $subject, $html_message, implode("\r\n", $headers));
    } else {
        // Fallback: Just log it in system_logs for the admin to see if SMTP isn't set up yet
        log_action($pdo, 0, "Email Logged (SMTP Not Configured)", "To: $to | Subject: $subject");
        return true; 
    }
}
