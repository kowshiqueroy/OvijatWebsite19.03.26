<?php
$pageTitle = 'Companies';
include 'header.php';

$success = $error = '';

/* ── POST handlers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_company'])) {
        $name    = trim($_POST['name']);
        $address = trim($_POST['address']);
        $phone   = trim($_POST['phone']);
        $email   = trim($_POST['email']);
        $website = trim($_POST['website']);
        $logo    = trim($_POST['logo']);

        if ($name === '') {
            $error = 'Company name is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO companies (name, address, phone, email, website, logo) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $name, $address, $phone, $email, $website, $logo);
            $stmt->execute();
            $stmt->close();
            header("Location: companies.php?msg=created"); exit;
        }
    }

    if (isset($_POST['update_company'])) {
        $cid     = (int)$_GET['edit'];
        $name    = trim($_POST['name']);
        $address = trim($_POST['address']);
        $phone   = trim($_POST['phone']);
        $email   = trim($_POST['email']);
        $website = trim($_POST['website']);
        $logo    = trim($_POST['logo']);

        if ($name === '') {
            $error = 'Company name is required.';
        } else {
            $stmt = $conn->prepare("UPDATE companies SET name=?,address=?,phone=?,email=?,website=?,logo=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $address, $phone, $email, $website, $logo, $cid);
            $stmt->execute();
            $stmt->close();
            header("Location: companies.php?msg=updated"); exit;
        }
    }
}

/* ── Load edit data ── */
$edit_data = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ── Fetch all companies ── */
$companies = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id) AS user_count FROM companies c ORDER BY c.id DESC");

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'created' ? 'Company created successfully.' : 'Company updated successfully.';
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Companies</div>
        <div class="page-subtitle">Manage all companies in the system</div>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Company Form -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $edit_data ? 'Edit Company' : 'Add Company' ?></span>
        <?php if ($edit_data): ?>
            <a href="companies.php" class="btn btn-ghost btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>
    <form method="POST" action="companies.php<?= $edit_data ? '?edit='.(int)$_GET['edit'] : '' ?>">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Company Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="name" placeholder="e.g. Ovijat Food Ltd." required
                       value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" placeholder="017XXXXXXXX"
                       value="<?= htmlspecialchars($edit_data['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="info@company.com"
                       value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="Full address"
                       value="<?= htmlspecialchars($edit_data['address'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Website</label>
                <input type="text" name="website" placeholder="https://company.com"
                       value="<?= htmlspecialchars($edit_data['website'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Logo URL</label>
                <input type="text" name="logo" placeholder="https://...logo.png"
                       value="<?= htmlspecialchars($edit_data['logo'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <?php if ($edit_data): ?>
                <button type="submit" name="update_company" class="btn btn-warning">
                    <i class="fa-solid fa-pen"></i> Update Company
                </button>
            <?php else: ?>
                <button type="submit" name="add_company" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Company
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Company List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Companies</span>
        <span class="badge badge-blue"><?= $companies ? $companies->num_rows : 0 ?> total</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Company Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($companies && $companies->num_rows > 0): ?>
                    <?php while ($row = $companies->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                            <?php if ($row['address']): ?>
                                <div class="text-muted text-xs mt-4"><?= htmlspecialchars($row['address']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td><span class="badge badge-blue"><?= $row['user_count'] ?></span></td>
                        <td>
                            <a href="companies.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No companies yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
