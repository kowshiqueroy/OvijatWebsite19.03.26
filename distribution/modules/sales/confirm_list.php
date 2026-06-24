<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$drafts = fetch_all("SELECT s.*, c.name as customer_name, c.type as customer_type, c.balance as customer_balance, c.credit_limit, u.username as creator_name 
                    FROM sales_drafts s 
                    JOIN customers c ON s.customer_id = c.id 
                    JOIN users u ON s.created_by = u.id 
                    WHERE s.status = 'Draft' AND s.isDelete = 0 AND c.isDelete = 0 AND u.isDelete = 0
                    ORDER BY s.created_at DESC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Pending Confirmations</h3>
        <p class="text-muted small mb-0"><?php echo count($drafts); ?> draft(s) awaiting confirmation.</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="copyDraftsWhatsApp(this)" class="btn btn-outline-success btn-sm"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</button>
        <button onclick="downloadTableCSV('pending_drafts_<?php echo date('Y-m-d'); ?>.csv','#drafts-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle export-table" id="drafts-table">
                <thead class="table-light">
                    <tr>
                        <th>Draft #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Cust. Type</th>
                        <th>Order Type</th>
                        <th>Current Balance</th>
                        <th>Invoice Amount</th>
                        <th data-no-export>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drafts)): ?>
                        <tr><td colspan="7" class="text-center py-4">No pending drafts found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($drafts as $d): ?>
                    <?php 
                        $new_potential_balance = $d['customer_balance'] - $d['grand_total'];
                        $is_capable = ($d['credit_limit'] == 0 || $new_potential_balance >= -$d['credit_limit']);
                        $bal_color = ($d['customer_balance'] >= 0) ? 'text-success' : 'text-danger';
                        $limit_text = ($d['credit_limit'] > 0) ? "Max Debt Allowed: " . format_currency($d['credit_limit']) : "Unlimited Credit";
                    ?>
                    <tr>
                        <td><strong>#<?php echo $d['id']; ?></strong></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($d['created_at'])); ?></td>
                        <td><?php echo $d['customer_name']; ?></td>
                        <td><span class="badge bg-outline-secondary border text-dark"><?php echo $d['customer_type']; ?></span></td>
                        <td>
                            <?php
                            $ot_colors = ['Local'=>'bg-primary','Export'=>'bg-success','Custom'=>'bg-warning text-dark','DMD'=>'bg-info'];
                            $ot = $d['order_type'] ?? 'Local';
                            echo '<span class="badge ' . ($ot_colors[$ot] ?? 'bg-secondary') . '">' . $ot . '</span>';
                            ?>
                        </td>
                        <td class="<?php echo $bal_color; ?> fw-bold" title="<?php echo $limit_text; ?>">
                            <?php echo format_currency($d['customer_balance']); ?>
                            <?php if (!$is_capable): ?>
                                <i class="fas fa-exclamation-triangle ms-1 text-danger" title="Credit Limit Exceeded!"></i>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo format_currency($d['grand_total']); ?></strong></td>
                        <td>
                            <a href="view.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-info" title="View Items"><i class="fas fa-eye"></i></a>
                            <a href="confirm.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Confirm this sale? Stock and balance will be updated.')">
                                <i class="fas fa-check me-1"></i> Confirm
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const DRAFTS_DATA = <?php echo json_encode(array_map(fn($d) => [
    'id'        => $d['id'],
    'customer'  => $d['customer_name'],
    'type'      => $d['customer_type'],
    'order_type'=> $d['order_type'] ?? 'Local',
    'amount'    => $d['grand_total'],
    'balance'   => $d['customer_balance'],
], $drafts)); ?>;

function copyDraftsWhatsApp(btn) {
    const date = new Date().toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
    let text = '*Pending Drafts — ' + date + '*\n' + '─'.repeat(30) + '\n';
    let total = 0;
    DRAFTS_DATA.forEach(d => {
        const amt = parseFloat(d.amount) || 0;
        const bal = parseFloat(d.balance) || 0;
        text += '#' + String(d.id).padStart(6,'0') + ' | ' + d.customer + ' (' + d.type + ')';
        text += ' | ৳' + amt.toLocaleString();
        if (bal < 0) text += ' ⚠bal:৳' + Math.abs(bal).toLocaleString();
        text += '\n';
        total += amt;
    });
    text += '─'.repeat(30) + '\n*Total: ৳' + total.toLocaleString() + ' (' + DRAFTS_DATA.length + ' drafts)*';
    copyText(text, btn);
}
</script>
<?php require_once '../../templates/footer.php'; ?>
