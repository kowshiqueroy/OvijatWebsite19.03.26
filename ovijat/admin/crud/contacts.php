<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo    = getPDO();
$msg    = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Mark read
if ($action === 'read' && $id) {
    $pdo->prepare("UPDATE contact_submissions SET is_read=1 WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/contacts.php?action=view&id=' . $id); exit;
}
// Delete
if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare("DELETE FROM contact_submissions WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/contacts.php?msg=deleted'); exit;
}

$viewing = null;
if ($action === 'view' && $id) {
    $pdo->prepare("UPDATE contact_submissions SET is_read=1 WHERE id=?")->execute([$id]);
    $s = $pdo->prepare("SELECT * FROM contact_submissions WHERE id=?"); $s->execute([$id]);
    $viewing = $s->fetch();
}

if (isset($_GET['msg'])) $msg = 'Message deleted.';

adminOpen('Contact Messages', 'contacts');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

<?php if ($viewing): ?>
<!-- ===== VIEW MESSAGE ===== -->
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:700px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
    <h2 style="font-family:var(--ff-heading);font-size:1.4rem;">Message Details</h2>
    <a href="<?= BASE_URL ?>/admin/crud/contacts.php" class="btn btn-outline" style="padding:.4rem .9rem;font-size:.8rem;">← Back</a>
  </div>
  <dl style="display:grid;grid-template-columns:140px 1fr;gap:.75rem 1rem;font-family:var(--ff-body);font-size:.9rem;">
    <dt style="font-weight:700;color:var(--clr-dark);">Name</dt><dd><?= e($viewing['name']) ?></dd>
    <dt style="font-weight:700;color:var(--clr-dark);">Email</dt><dd><a href="mailto:<?= e($viewing['email']) ?>" style="color:var(--clr-gold);"><?= e($viewing['email']) ?></a></dd>
    <dt style="font-weight:700;color:var(--clr-dark);">Phone</dt><dd><?= e($viewing['phone'] ?: '—') ?></dd>
    <dt style="font-weight:700;color:var(--clr-dark);">Subject</dt><dd><?= e($viewing['subject'] ?: '—') ?></dd>
    <dt style="font-weight:700;color:var(--clr-dark);">Date</dt><dd><?= date('d M Y, g:i A', strtotime($viewing['created_at'])) ?></dd>
    <dt style="font-weight:700;color:var(--clr-dark);">IP</dt><dd style="color:var(--clr-muted);font-size:.8rem;"><?= e($viewing['ip_address']) ?></dd>
  </dl>
  <hr style="margin:1.5rem 0;border:none;border-top:1px solid rgba(0,0,0,.08);">
  <h3 style="font-family:var(--ff-ui);font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--clr-muted);margin-bottom:.75rem;">Message</h3>
  <div style="background:var(--clr-offwhite);border-radius:6px;padding:1.25rem;line-height:1.8;white-space:pre-wrap;">
    <?= e($viewing['message']) ?>
  </div>
  <div style="margin-top:1.5rem;display:flex;gap:1rem;">
    <a href="mailto:<?= e($viewing['email']) ?>?subject=Re: <?= e(rawurlencode($viewing['subject'] ?: 'Your enquiry')) ?>" class="btn btn-primary">Reply via Email</a>
    <form method="POST" action="?action=delete&id=<?= $viewing['id'] ?>" onsubmit="return confirm('Delete this message permanently?')">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-outline" style="border-color:var(--clr-crimson);color:var(--clr-crimson);">Delete</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ===== LIST ===== -->
<table class="admin-table">
  <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC")->fetchAll();
  foreach ($rows as $r):
  ?>
  <tr <?= !$r['is_read'] ? 'style="font-weight:600;"' : '' ?>>
    <td><?= e($r['name']) ?></td>
    <td><?= e($r['email']) ?></td>
    <td><?= e($r['subject'] ?: '—') ?></td>
    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
    <td><?= $r['is_read'] ? '<span class="badge badge-success">Read</span>' : '<span class="badge badge-gold">Unread</span>' ?></td>
    <td>
      <a href="?action=view&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">View</a>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--clr-muted);">No messages received yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
<?php endif; ?>
<?php adminClose(); ?>
