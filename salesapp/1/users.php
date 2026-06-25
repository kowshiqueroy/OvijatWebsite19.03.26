<?php
$pageTitle = 'Users';
include 'header.php';
if (!$is_manager) { header("Location: index.php"); exit; }

$cid = (int)$_SESSION['company_id'];
$success = $error = '';
$role_labels = [1=>'Manager', 2=>'Sales Rep', 3=>'Sales Rep', 9=>'Viewer'];
$role_badge  = [1=>'badge-blue', 2=>'badge-green', 3=>'badge-green', 9=>'badge-purple'];

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role     = (int)$_POST['role'];
        $status   = (int)($_POST['status'] ?? 1);

        if ($username === '' || $password === '') { $error = 'Username and password are required.'; }
        elseif (!in_array($role, [1, 2, 3, 9])) { $error = 'Invalid role.'; }
        else {
            $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
            $chk->bind_param("s", $username); $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) { $error = "Username '$username' is already taken."; }
            else {
                $chk->close();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, status, company_id) VALUES (?,?,?,?,?)");
                $stmt->bind_param("ssiii", $username, $hash, $role, $status, $cid);
                $stmt->execute(); $stmt->close();
                header("Location: users.php?msg=created"); exit;
            }
            $chk->close();
        }
    }

    if (isset($_POST['update_user'])) {
        $uid_edit = (int)$_GET['edit'];
        $username = trim($_POST['username']);
        $role     = (int)$_POST['role'];
        $status   = (int)($_POST['status'] ?? 1);

        if ($username === '') { $error = 'Username is required.'; }
        else {
            $chk = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $chk->bind_param("si", $username, $uid_edit); $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) { $error = "Username '$username' is already taken."; }
            else {
                $chk->close();
                if (!empty($_POST['password'])) {
                    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("UPDATE users SET username=?,password=?,role=?,status=? WHERE id=? AND company_id=?");
                    $stmt->bind_param("ssiiii", $username, $hash, $role, $status, $uid_edit, $cid);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username=?,role=?,status=? WHERE id=? AND company_id=?");
                    $stmt->bind_param("siiii", $username, $role, $status, $uid_edit, $cid);
                }
                $stmt->execute(); $stmt->close();
                header("Location: users.php?msg=updated"); exit;
            }
            $chk->close();
        }
    }
}

/* ── Load edit ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $cid); $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

/* ── Pagination + filter ── */
$f_role   = $_GET['role'] ?? '';
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = ["u.company_id=$cid"];
$params = []; $types = '';
if ($f_role !== '') { $where[] = 'u.role=?'; $params[] = (int)$f_role; $types .= 'i'; }
$w = 'WHERE ' . implode(' AND ', $where);

$count_q = $conn->prepare("SELECT COUNT(*) AS c FROM users u $w");
if ($types) $count_q->bind_param($types, ...$params);
$count_q->execute(); $total = (int)$count_q->get_result()->fetch_assoc()['c']; $count_q->close();
$total_pages = max(1, (int)ceil($total / $per_page));

$list_q = $conn->prepare(
    "SELECT u.id, u.username, u.role, u.status, u.created_at, u.last_login,
     sg.name AS group_name, d.name AS division_name
     FROM users u
     LEFT JOIN user_group_assignments uga ON uga.user_id=u.id AND uga.is_active=1
     LEFT JOIN sales_groups sg ON sg.id=uga.group_id
     LEFT JOIN divisions d ON d.id=sg.division_id
     $w ORDER BY u.role, u.username LIMIT ? OFFSET ?"
);
$lp = array_merge($params, [$per_page, $offset]); $lt = $types . 'ii';
$list_q->bind_param($lt, ...$lp); $list_q->execute(); $rows = $list_q->get_result();

if (isset($_GET['msg'])) $success = $_GET['msg']==='created' ? 'User created successfully.' : 'User updated.';
?>

<div class="page-header">
    <div><div class="page-title">Users</div><div class="page-subtitle">Manage users for <?= htmlspecialchars($company_name) ?></div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit User' : 'Add User' ?></span>
        <?php if ($edit_data): ?><a href="users.php" class="btn btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <form method="POST" action="users.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Username <span style="color:var(--danger)">*</span></label>
                <input type="text" name="username" required placeholder="e.g. rahim_sr"
                       value="<?= htmlspecialchars($edit_data['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password <?= $edit_data ? '(leave blank to keep)' : '<span style="color:var(--danger)">*</span>' ?></label>
                <input type="password" name="password" <?= $edit_data ? '' : 'required' ?>>
            </div>
            <div class="form-group">
                <label>Role <span style="color:var(--danger)">*</span></label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="1" <?= ($edit_data && $edit_data['role']==1)?'selected':'' ?>>Manager</option>
                    <option value="3" <?= ($edit_data && $edit_data['role']==3)?'selected':'' ?>>Sales Rep (SR)</option>
                    <option value="9" <?= ($edit_data && $edit_data['role']==9)?'selected':'' ?>>Viewer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="1" <?= (!$edit_data || $edit_data['status']==1)?'selected':'' ?>>Active</option>
                    <option value="0" <?= ($edit_data && $edit_data['status']==0)?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="<?= $edit_data ? 'update_user' : 'add_user' ?>" class="btn btn-primary">
                <i class="fa-solid <?= $edit_data ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data ? 'Update User' : 'Create User' ?>
            </button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="min-width:140px">
            <label>Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="1" <?=$f_role==='1'?'selected':''?>>Manager</option>
                <option value="3" <?=$f_role==='3'?'selected':''?>>Sales Rep</option>
                <option value="9" <?=$f_role==='9'?'selected':''?>>Viewer</option>
            </select>
        </div>
        <div class="form-group" style="max-width:90px">
            <label>Per Page</label>
            <select id="perPageSelect" name="per_page">
                <?php foreach ([10,25,50] as $n): ?><option value="<?=$n?>" <?=$per_page==$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="users.php" class="btn btn-ghost btn-sm" style="align-self:flex-end">Reset</a>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <span class="card-title">Users in <?= htmlspecialchars($company_name) ?></span>
        <span class="badge badge-blue"><?= $total ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Username</th><th>Role</th><th>Group / Division</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($rows->num_rows > 0): ?>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td><span class="badge <?= $role_badge[$row['role']] ?? 'badge-gray' ?>"><?= $role_labels[$row['role']] ?? 'Role '.$row['role'] ?></span></td>
                        <td class="text-sm">
                            <?php if ($row['group_name']): ?>
                                <span class="text-muted"><?= htmlspecialchars($row['division_name'] ?? '') ?></span> &rsaquo;
                                <span class="badge badge-blue"><?= htmlspecialchars($row['group_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $row['status'] ? 'badge-green' : 'badge-red' ?>"><?= $row['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-muted text-sm"><?= $row['last_login'] ? date('d M Y, h:i a', strtotime($row['last_login'])) : 'Never' ?></td>
                        <td>
                            <a href="users.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
                            <?php if (in_array($row['role'], [2, 3])): ?>
                            <a href="sr_assignment.php" class="btn btn-info btn-sm btn-icon" title="Assign to Group"><i class="fa-solid fa-people-group"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="padding:16px">
        <?php $base = "users.php?role=$f_role&per_page=$per_page&page="; ?>
        <a href="<?=$base?>1"                   class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angles-left"></i></a>
        <a href="<?=$base.max(1,$page-1)?>"     class="page-btn <?=$page==1?'disabled':''?>"><i class="fa-solid fa-angle-left"></i></a>
        <?php for ($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
            <a href="<?=$base.$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
        <a href="<?=$base.min($total_pages,$page+1)?>" class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angle-right"></i></a>
        <a href="<?=$base.$total_pages?>"              class="page-btn <?=$page==$total_pages?'disabled':''?>"><i class="fa-solid fa-angles-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<?php $list_q->close(); include 'footer.php'; ?>
