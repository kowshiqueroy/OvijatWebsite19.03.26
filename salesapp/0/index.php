<?php
$pageTitle = 'Dashboard';
include 'header.php';

/* ── KPI queries ── */
$kpi = [];

$r = $conn->query("SELECT COUNT(*) AS c FROM companies");
$kpi['companies'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM users");
$kpi['users'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 1");
$kpi['active_users'] = (int)$r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$kpi['logins_7d'] = (int)$r->fetch_assoc()['c'];

/* ── Recent logins ── */
$recent = $conn->query(
    "SELECT u.id, u.username, u.role, u.last_login, c.name AS company
     FROM users u
     LEFT JOIN companies c ON u.company_id = c.id
     WHERE u.last_login IS NOT NULL
     ORDER BY u.last_login DESC
     LIMIT 15"
);

$role_labels = [0=>'Super Admin', 1=>'Manager', 2=>'Sales Rep', 3=>'Sales Rep', 9=>'Viewer'];
?>

<div class="page-header">
    <div>
        <div class="page-title">System Dashboard</div>
        <div class="page-subtitle">Overview of all companies and users</div>
    </div>
    <a href="users.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New User</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Companies</div>
        <div class="kpi-value"><?= $kpi['companies'] ?></div>
        <div class="kpi-sub"><a href="companies.php" style="color:var(--primary)">Manage &rarr;</a></div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-label">Total Users</div>
        <div class="kpi-value"><?= $kpi['users'] ?></div>
        <div class="kpi-sub"><?= $kpi['active_users'] ?> active</div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Active Users</div>
        <div class="kpi-value"><?= $kpi['active_users'] ?></div>
        <div class="kpi-sub"><?= $kpi['users'] - $kpi['active_users'] ?> inactive</div>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-label">Logins (7 days)</div>
        <div class="kpi-value"><?= $kpi['logins_7d'] ?></div>
        <div class="kpi-sub">unique users</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-clock" style="color:var(--primary)"></i> Recent Logins</span>
        <a href="users.php" class="btn btn-ghost btn-sm">All Users</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Company</th>
                    <th>Role</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent && $recent->num_rows > 0): ?>
                    <?php while ($row = $recent->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted">#<?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td><?= htmlspecialchars($row['company'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $row['role'] == 0 ? 'badge-red' : ($row['role'] == 1 ? 'badge-blue' : ($row['role'] == 9 ? 'badge-purple' : 'badge-green')) ?>">
                                <?= htmlspecialchars($role_labels[$row['role']] ?? 'Role '.$row['role']) ?>
                            </span>
                        </td>
                        <td class="text-muted text-sm"><?= $row['last_login'] ? date('d M Y, h:i a', strtotime($row['last_login'])) : '—' ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No login activity yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
