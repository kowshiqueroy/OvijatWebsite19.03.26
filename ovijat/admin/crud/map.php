<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/layout.php';

$pdo    = getPDO();
$msg = $err = '';
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    verifyCsrf();
    $pdo->prepare("DELETE FROM map_countries WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/crud/map.php?msg=deleted'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action,['add','edit'])) {
    verifyCsrf();
    $country  = trim($_POST['country'] ?? '');
    $region   = trim($_POST['region'] ?? '');
    $posX     = min(100, max(0, (float)($_POST['pos_x'] ?? 50)));
    $posY     = min(100, max(0, (float)($_POST['pos_y'] ?? 50)));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!$country || !$region) { $err = 'Country and Region are required.'; }
    else {
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO map_countries (country,region,pos_x,pos_y,is_active) VALUES (?,?,?,?,?)")
                ->execute([$country,$region,$posX,$posY,$isActive]);
        } else {
            $pdo->prepare("UPDATE map_countries SET country=?,region=?,pos_x=?,pos_y=?,is_active=? WHERE id=?")
                ->execute([$country,$region,$posX,$posY,$isActive,$id]);
        }
        header('Location: ' . BASE_URL . '/admin/crud/map.php?msg=saved'); exit;
    }
}

$editing = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM map_countries WHERE id=?"); $s->execute([$id]);
    $editing = $s->fetch();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'saved' ? 'Country saved.' : 'Country deleted.';

adminOpen('Map Countries', 'map');
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<?php if (in_array($action,['add','edit'])): ?>
<div style="background:var(--clr-white);border-radius:6px;box-shadow:var(--shadow-sm);padding:2rem;max-width:580px;">
  <h2 style="font-family:var(--ff-heading);font-size:1.4rem;margin-bottom:1.5rem;">
    <?= $action === 'add' ? 'Add Country' : 'Edit Country' ?>
  </h2>
  <p style="font-size:.82rem;color:var(--clr-muted);margin-bottom:1.5rem;">
    Position is a percentage (0–100) of the map image width/height.
    Measure the dot position on the world map image.
  </p>
  <form method="POST" novalidate>
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="form-group">
        <label class="form-label">Country Name *</label>
        <input class="form-control" name="country" required value="<?= e($editing['country'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Region *</label>
        <input class="form-control" name="region" required
               list="region-list"
               value="<?= e($editing['region'] ?? '') ?>">
        <datalist id="region-list">
          <option value="South Asia">
          <option value="Southeast Asia">
          <option value="East Asia">
          <option value="Middle East">
          <option value="Europe">
          <option value="Americas">
          <option value="Africa">
          <option value="Oceania">
        </datalist>
      </div>
      <div class="form-group">
        <label class="form-label">Position X % (left→right)</label>
        <input class="form-control" type="number" name="pos_x" step="0.1" min="0" max="100"
               value="<?= number_format((float)($editing['pos_x'] ?? 50), 1) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Position Y % (top→bottom)</label>
        <input class="form-control" type="number" name="pos_y" step="0.1" min="0" max="100"
               value="<?= number_format((float)($editing['pos_y'] ?? 50), 1) ?>">
      </div>
    </div>
    <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;cursor:pointer;">
      <input type="checkbox" name="is_active" value="1" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
      <span class="form-label" style="margin:0;">Active (show on map)</span>
    </label>
    <div style="display:flex;gap:1rem;">
      <button type="submit" class="btn btn-primary">Save Country</button>
      <a href="<?= BASE_URL ?>/admin/crud/map.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
  <a href="?action=add" class="btn btn-primary">+ Add Country</a>
</div>
<table class="admin-table">
  <thead><tr><th>Country</th><th>Region</th><th>X%</th><th>Y%</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php
  $rows = $pdo->query("SELECT * FROM map_countries ORDER BY region,country")->fetchAll();
  foreach ($rows as $r):
  ?>
  <tr>
    <td><strong><?= e($r['country']) ?></strong></td>
    <td><?= e($r['region']) ?></td>
    <td><?= number_format((float)$r['pos_x'],1) ?>%</td>
    <td><?= number_format((float)$r['pos_y'],1) ?>%</td>
    <td><?= $r['is_active'] ? '<span class="badge badge-success">Shown</span>' : '<span class="badge badge-danger">Hidden</span>' ?></td>
    <td>
      <a href="?action=edit&id=<?= $r['id'] ?>" style="color:var(--clr-dark);font-weight:600;font-size:.82rem;margin-right:.75rem;">Edit</a>
      <form method="POST" action="?action=delete&id=<?= $r['id'] ?>" style="display:inline;" onsubmit="return confirm('Delete?')">
        <?= csrfField() ?>
        <button type="submit" style="color:var(--clr-crimson);font-weight:600;font-size:.82rem;">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php adminClose(); ?>
