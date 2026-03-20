<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
adminOpen('Dashboard', 'dashboard');
$pdo = getPDO();
$counts = [
    'Products'  => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'Categories'=> $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'Team'      => $pdo->query("SELECT COUNT(*) FROM team_members")->fetchColumn(),
    'Messages'  => $pdo->query("SELECT COUNT(*) FROM contact_submissions WHERE is_read=0")->fetchColumn(),
];
$icons = [
    'Products'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
    'Categories'=> '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
    'Team'      => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'Messages'  => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>',
];
?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.5rem;margin-bottom:2.5rem;">
<?php foreach ($counts as $label => $val): ?>
  <div class="stat-card">
    <div class="stat-card-icon"><?= $icons[$label] ?></div>
    <div>
      <div class="stat-card-val"><?= (int)$val ?></div>
      <div class="stat-card-lbl"><?= e($label) ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Recent messages -->
<?php
$msgs = $pdo->query(
    "SELECT id, name, email, subject, created_at, is_read FROM contact_submissions ORDER BY created_at DESC LIMIT 10"
)->fetchAll();
?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);overflow:hidden;">
  <div style="padding:1.2rem 1.5rem;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-family:var(--ff-heading);font-size:1.1rem;">Recent Messages</strong>
    <a href="<?= BASE_URL ?>/admin/crud/contacts.php" class="btn btn-outline" style="padding:.4rem .9rem;font-size:.75rem;">View All</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($msgs as $m): ?>
    <tr>
      <td><?= e($m['name']) ?></td>
      <td><?= e($m['email']) ?></td>
      <td><?= e($m['subject'] ?: '—') ?></td>
      <td><?= date('d M Y', strtotime($m['created_at'])) ?></td>
      <td><?= $m['is_read'] ? '<span class="badge badge-success">Read</span>' : '<span class="badge badge-gold">Unread</span>' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$msgs): ?><tr><td colspan="5" style="text-align:center;color:var(--clr-muted);padding:2rem;">No messages yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php adminClose(); ?>
