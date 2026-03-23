<?php
// ============================================================
// modules/customers/customers.php
// ============================================================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['customer_id_db'] ?? 0);
    $data = [
        'name'  => trim($_POST['name']),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];
    if ($id) {
        dbUpdate('customers', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'customers', $id, 'Updated customer: ' . $data['name']. ' with data: ' . json_encode($data));
        flash('success', 'Customer updated.');
    } else {
        $data['created_at'] = now();
        $newId = dbInsert('customers', $data);
        logAction('CREATE', 'customers', $newId, 'Created customer: ' . $data['name']. ' with data: ' . json_encode($data));
        flash('success', 'Customer added.');
    }
    redirect('customers');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)$_GET['id'];
    dbDelete('customers', 'id = ?', [$id]);
    logAction('DELETE', 'customers', $id, 'Deleted customer: ' . $data['name']. ' with data: ' . json_encode($data));
    flash('success', 'Customer deleted.');
    redirect('customers');
}

$search    = trim($_GET['q'] ?? '');
$params    = [];
$where     = '1=1';
if ($search) { $where = 'name LIKE ? OR phone LIKE ?'; $params = ["%$search%", "%$search%"]; }

$customers = dbFetchAll("SELECT * FROM customers WHERE $where ORDER BY name", $params);
$editing   = !empty($_GET['edit']) ? dbFetch('SELECT * FROM customers WHERE id = ?', [(int)$_GET['edit']]) : null;

$pageTitle = 'Customers';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2">
  <h1>👤 Customers</h1>
  <button class="btn btn-primary" onclick="openModal('customerModal')">+ Add Customer</button>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:14px">
  <input type="hidden" name="page" value="customers">
  <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search by name or phone…" style="max-width:240px">
  <button type="submit" class="btn btn-ghost">Search</button>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th class="text-right">Points</th><th>Since</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td><?= e($c['phone']) ?></td>
          <td><?= e($c['email']) ?></td>
          <td class="text-right"><span class="badge badge-info"><?= number_format($c['points']) ?> pts</span></td>
          <td><?= fmtDate($c['created_at']) ?></td>
          <td>
            <a href="index.php?page=customers&edit=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
            <?php if (canDelete()): ?>
            <a href="index.php?page=customers&action=delete&id=<?= $c['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete customer?">🗑️</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (!$customers): ?><tr><td colspan="6" class="text-muted text-center">No customers found.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="customerModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Customer' : 'Add Customer' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="customerForm">
        <input type="hidden" name="action"          value="save_customer">
        <input type="hidden" name="customer_id_db"  value="<?= $editing['id'] ?? '' ?>">
        <div class="form-group"><label class="form-label">Name *</label>
          <input type="text" name="name" class="form-control" required value="<?= e($editing['name'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($editing['phone'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($editing['email'] ?? '') ?>"></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="customerForm" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
