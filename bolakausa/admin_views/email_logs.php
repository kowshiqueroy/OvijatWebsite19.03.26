<?php
/**
 * Outgoing Email Logs & Invoice Dispatch Tracker (Administrators Only)
 */
restrict_to(['admin']);

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'resend') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch();
        
        if ($log) {
            $to = $log['to_email'];
            $subject = $log['subject'];
            $html_message = $log['body'];
            
            $host = get_setting($pdo, 'smtp_host');
            $port = get_setting($pdo, 'smtp_port', '587');
            $user = get_setting($pdo, 'smtp_user');
            $pass = get_setting($pdo, 'smtp_pass');
            $from = get_setting($pdo, 'smtp_from', 'noreply@bolakausa.com');
            $comp_name = get_setting($pdo, 'company_name', 'Bolakausa Wholesale');
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $comp_name . ' <' . $from . '>',
                'Reply-To: ' . $from,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $status = 'not_sent_no_smtp';
            $error_message = 'SMTP credentials are not configured in system settings.';
            $sent = false;
            
            if ($host && $user && $pass) {
                log_action($pdo, $_SESSION['user_id'], "Email Resend Attempt", "To: $to | Subject: $subject");
                $sent = @mail($to, $subject, $html_message, implode("\r\n", $headers));
                if ($sent) {
                    $status = 'successful';
                    $error_message = null;
                } else {
                    $status = 'failed';
                    $error_message = 'PHP mail() function returned false.';
                }
            } else {
                log_action($pdo, $_SESSION['user_id'], "Email Resend Logged (SMTP Not Configured)", "To: $to | Subject: $subject");
                $status = 'not_sent_no_smtp';
                $error_message = 'SMTP credentials are not configured in system settings.';
                $sent = true;
            }
            
            // Insert a new log entry for the resend event
            $ins = $pdo->prepare("INSERT INTO email_logs (to_email, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$to, "[RESENT] " . $subject, $html_message, $status, $error_message]);
            
            if ($sent && $status === 'successful') {
                $success_msg = "Email successfully resent to $to.";
            } elseif ($status === 'not_sent_no_smtp') {
                $success_msg = "Resend logged successfully (SMTP is not configured, email simulate-sent).";
            } else {
                $error_msg = "Failed to resend email: " . $error_message;
            }
        } else {
            $error_msg = "Log entry not found.";
        }
    } elseif ($action === 'compose_manual') {
        $to = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        
        if (empty($to) || empty($subject) || empty($body)) {
            $error_msg = "All fields (To Email, Subject, and Body) are required.";
        } else {
            require_once __DIR__ . '/../includes/mailer.php';
            send_system_email($pdo, $to, $subject, $body);
            $success_msg = "Manual email successfully sent/logged to $to.";
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$query = "SELECT * FROM email_logs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (to_email LIKE ? OR subject LIKE ? OR body LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY CURRENT_TIMESTAMP() DESC"; // Using Order By DESC
$query = "SELECT * FROM email_logs WHERE 1=1"; // Redefine search query safely
if (!empty($search)) {
    $query .= " AND (to_email LIKE ? OR subject LIKE ? OR body LIKE ?)";
}
if (!empty($status)) {
    $query .= " AND status = ?";
}
$query .= " ORDER BY created_at DESC LIMIT 150";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<style>
.logs-filter-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
}

.badge-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.725rem;
    font-weight: 800;
    text-transform: uppercase;
    display: inline-block;
    border: 1px solid transparent;
}

.badge-status.successful {
    background: rgba(16, 185, 129, 0.08);
    color: #166534;
    border-color: rgba(16, 185, 129, 0.15);
}

.badge-status.failed {
    background: rgba(244, 63, 94, 0.08);
    color: var(--rose);
    border-color: rgba(244, 63, 94, 0.15);
}

.badge-status.not_sent_no_smtp {
    background: rgba(245, 158, 11, 0.08);
    color: #d97706;
    border-color: rgba(245, 158, 11, 0.15);
}

/* Modal Overlay Styling */
.email-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.email-modal-content {
    background: white;
    width: 90%;
    max-width: 850px;
    height: 85%;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: modalSlideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalSlideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.email-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.email-modal-body {
    flex: 1;
    background: #f1f5f9;
    padding: 1rem;
}

.email-modal-body iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
}

.email-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-light);
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
}
</style>

<div class="section-title">
    <i class="fas fa-paper-plane" style="color: var(--primary);"></i> Corporate Email logs Hub
</div>

<?php if ($success_msg): ?>
    <div style="background: rgba(16, 185, 129, 0.08); color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.15);">
        <i class="fas fa-check-circle"></i> <?php echo e($success_msg); ?>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div style="background: rgba(244, 63, 94, 0.08); color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(244, 63, 94, 0.15);">
        <i class="fas fa-exclamation-triangle"></i> <?php echo e($error_msg); ?>
    </div>
<?php endif; ?>

<!-- Manual Composition Accordion -->
<div class="logs-filter-card" style="margin-bottom: 1.5rem;">
    <h3 style="font-family:'Plus Jakarta Sans', sans-serif; font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:0; cursor:pointer; display:flex; justify-content:space-between; align-items:center;" onclick="toggleComposePanel()">
        <span><i class="fas fa-edit" style="color: var(--primary); margin-right: 5px;"></i> Compose & Send Manual Email</span>
        <span style="font-size:0.75rem; color:var(--text-muted); font-weight: 500;">(Click to Expand/Collapse)</span>
    </h3>
    
    <div id="compose-panel" style="display: none; border-top: 1px solid var(--border-light); padding-top: 1.5rem; margin-top: 1rem;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="compose_manual">
            
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 700; color: var(--secondary); font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Recipient Address *</label>
                    <input type="email" name="to_email" required placeholder="customer@example.com" style="border-radius: 8px; padding: 0.60rem; border: 1px solid var(--border-dark); width: 100%; font-family: inherit;">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 700; color: var(--secondary); font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Email Subject *</label>
                    <input type="text" name="subject" required placeholder="e.g. Special Invoice Update" style="border-radius: 8px; padding: 0.60rem; border: 1px solid var(--border-dark); width: 100%; font-family: inherit;">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 700; color: var(--secondary); font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Email Body (HTML Allowed) *</label>
                <textarea name="body" required rows="6" placeholder="Type your email body details here..." style="width: 100%; border-radius: 8px; border: 1px solid var(--border-dark); padding: 0.75rem; font-family: inherit; font-size: 0.9rem; resize: vertical;"></textarea>
            </div>
            
            <button type="submit" class="btn btn-green" style="border-radius: 8px; border: none; padding: 0.6rem 1.5rem;"><i class="fas fa-paper-plane"></i> Send Custom Email</button>
        </form>
    </div>
</div>

<script>
function toggleComposePanel() {
    const p = document.getElementById('compose-panel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
</script>

<!-- Search & Filtering Hub -->
<div class="logs-filter-card">
    <form method="GET" action="" style="margin: 0;">
        <input type="hidden" name="url" value="admin/emails">
        
        <div style="display: grid; grid-template-columns: 2fr 1.2fr 1fr; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 700; color: var(--secondary); font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Search Target/Subject/Content</label>
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="e.g. customer@example.com or invoice subject..." style="border-radius: 8px; padding: 0.6rem; border: 1px solid var(--border-dark);">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label style="font-weight: 700; color: var(--secondary); font-size: 0.8rem; margin-bottom: 0.4rem; display: block;">Status Filter</label>
                <select name="status" style="border-radius: 8px; padding: 0.6rem; border: 1px solid var(--border-dark); width: 100%; background: white;">
                    <option value="">All Statuses</option>
                    <option value="successful" <?php echo ($status === 'successful') ? 'selected' : ''; ?>>Successful (Delivered)</option>
                    <option value="failed" <?php echo ($status === 'failed') ? 'selected' : ''; ?>>Failed (SMTP/Internal Error)</option>
                    <option value="not_sent_no_smtp" <?php echo ($status === 'not_sent_no_smtp') ? 'selected' : ''; ?>>Not Sent (No SMTP Configuration)</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem; width: 100%;">
                <button type="submit" class="btn btn-blue" style="flex: 1; justify-content: center; height: 42px; border-radius: 8px; border: none;"><i class="fas fa-search"></i> Filter Logs</button>
                <?php if ($search || $status): ?>
                    <a href="?url=admin/emails" class="btn btn-outline" style="height: 42px; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-undo"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Logs Grid Table -->
<div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;"><i class="fas fa-list" style="color: var(--primary);"></i> Transpatched Invoice Emails (Total: <?php echo count($logs); ?>)</h3>
    
    <div class="table-wrap" style="margin: 0;">
        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">Recipient Address</th>
                    <th style="width: 35%;">Subject line</th>
                    <th style="width: 15%;">Dispatch Status</th>
                    <th style="width: 15%;">Timestamp</th>
                    <th style="text-align: right; width: 10%;">Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 4rem;">
                            <i class="fas fa-envelope-open" style="font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                            No dispatch log metrics recorded in database.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--secondary); font-size: 0.9rem;"><?php echo e($log['to_email']); ?></strong>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-main);"><?php echo e($log['subject']); ?></span>
                        </td>
                        <td>
                            <span class="badge-status <?php echo e($log['status']); ?>">
                                <?php 
                                if ($log['status'] === 'successful') echo 'Successful';
                                elseif ($log['status'] === 'failed') echo 'Failed';
                                else echo 'No SMTP Setup';
                                ?>
                            </span>
                            <?php if (!empty($log['error_message'])): ?>
                                <small style="display: block; color: var(--rose); font-size: 0.725rem; font-weight: 600; margin-top: 4px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($log['error_message']); ?>">
                                    <?php echo e($log['error_message']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small style="color: var(--text-muted); font-weight: 700;"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></small>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                <form method="POST" style="margin: 0; display: inline;">
                                    <input type="hidden" name="action" value="resend">
                                    <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                    <button type="submit" class="btn btn-green" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px; border: none; font-weight: 800;" onclick="return confirm('Are you sure you want to resend this email?');">
                                        <i class="fas fa-redo"></i> Resend
                                    </button>
                                </form>
                                <button class="btn btn-outline btn-preview-email" 
                                        data-subject="<?php echo e($log['subject']); ?>" 
                                        data-email="<?php echo e($log['to_email']); ?>" 
                                        data-body="<?php echo e($log['body']); ?>"
                                        style="padding: 0.35rem 0.75rem; font-size: 0.75rem; border-radius: 6px;">
                                    <i class="far fa-eye"></i> View HTML
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- interactive previewer modal -->
<div class="email-modal-overlay" id="email-modal-overlay" onclick="closeEmailModal()">
    <div class="email-modal-content" onclick="event.stopPropagation()">
        <div class="email-modal-header">
            <div>
                <h4 style="margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary);" id="modal-subject"></h4>
                <p style="margin: 3px 0 0 0; font-size: 0.8rem; color: var(--text-muted);" id="modal-recipient"></p>
            </div>
            <button onclick="closeEmailModal()" class="btn btn-outline" style="border: none; font-size: 1.25rem; padding: 0.5rem; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="fas fa-times"></i></button>
        </div>
        <div class="email-modal-body">
            <iframe id="email-iframe" src="about:blank"></iframe>
        </div>
        <div class="email-modal-footer">
            <button onclick="closeEmailModal()" class="btn btn-blue" style="border-radius: 8px; padding: 0.5rem 1.5rem;">Close Preview</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-preview-email');
    if (btn) {
        const subject = btn.getAttribute('data-subject');
        const email = btn.getAttribute('data-email');
        const body = btn.getAttribute('data-body');
        previewEmail(subject, email, body);
    }
});

function previewEmail(subject, recipient, bodyHtml) {
    document.getElementById('modal-subject').textContent = subject;
    document.getElementById('modal-recipient').textContent = "Sent to: " + recipient;
    
    const iframe = document.getElementById('email-iframe');
    iframe.srcdoc = bodyHtml;
    
    document.getElementById('email-modal-overlay').style.display = 'flex';
}

function closeEmailModal() {
    document.getElementById('email-modal-overlay').style.display = 'none';
    document.getElementById('email-iframe').srcdoc = '';
}
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
