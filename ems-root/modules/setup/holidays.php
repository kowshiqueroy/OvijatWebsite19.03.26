<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Holiday Calendar';
$breadcrumbs = ['Setup' => 'index.php', 'Holiday Calendar' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['setup.edit']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $sess_id  = int_param('session_id', 0, $_POST) ?: null;
        $date     = $_POST['holiday_date'] ?? '';
        $name     = trim($_POST['holiday_name'] ?? '');
        $type     = $_POST['holiday_type'] ?? 'institutional';
        $id       = int_param('id', 0, $_POST);

        if ($date && $name) {
            if ($id) {
                $pdo->prepare('UPDATE holiday_calendar SET session_id=?,holiday_date=?,holiday_name=?,holiday_type=? WHERE id=?')
                    ->execute([$sess_id,$date,$name,$type,$id]);
                flash('success', 'Holiday updated.');
            } else {
                $pdo->prepare('INSERT INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)')
                    ->execute([$sess_id,$date,$name,$type]);
                flash('success', "'$name' added to calendar.");
            }
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('DELETE FROM holiday_calendar WHERE id=:id')->execute([':id' => $id]);
        flash('success', 'Holiday removed.');
    } elseif ($action === 'seed_bd_holidays') {
        $year    = (int)($_POST['seed_year'] ?? date('Y'));
        $sess_id = int_param('session_id', 0, $_POST) ?: null;

        // Delete existing govt holidays for this year first (idempotent re-seed)
        $delStmt = $sess_id
            ? $pdo->prepare("DELETE FROM holiday_calendar WHERE holiday_type='govt' AND YEAR(holiday_date)=? AND session_id=?")
            : $pdo->prepare("DELETE FROM holiday_calendar WHERE holiday_type='govt' AND YEAR(holiday_date)=? AND session_id IS NULL");
        $sess_id ? $delStmt->execute([$year, $sess_id]) : $delStmt->execute([$year]);

        $holidays = [
            ["$year-01-01", "New Year's Day",                          'govt'],
            ["$year-02-21", 'Language Martyrs Day (Shaheed Dibosh)',   'govt'],
            ["$year-03-17", 'Birthday of Father of Nation',            'govt'],
            ["$year-03-25", 'Genocide Remembrance Day',                'govt'],
            ["$year-03-26", 'Independence Day (Shadhinota Dibosh)',    'govt'],
            ["$year-04-14", 'Bengali New Year (Pohela Boishakh)',      'govt'],
            ["$year-04-17", 'Mujibnagar Day',                         'govt'],
            ["$year-05-01", 'International Workers Day (May Day)',     'govt'],
            ["$year-08-15", 'National Mourning Day',                   'govt'],
            ["$year-12-16", 'Victory Day (Bijoy Dibosh)',              'govt'],
        ];
        $ins = $pdo->prepare('INSERT INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
        foreach ($holidays as $h) $ins->execute([$sess_id, $h[0], $h[1], $h[2]]);
        flash('success', count($holidays) . ' BD national holidays set for ' . $year . '.');
        header('Location: holidays.php?session_id=' . (int)$_POST['session_id'] . '&year=' . $year);
        exit;

    } elseif ($action === 'seed_fridays') {
        $year    = (int)($_POST['seed_year'] ?? date('Y'));
        $sess_id = int_param('session_id', 0, $_POST) ?: null;

        // Delete existing weekend-type holidays for this year first (idempotent)
        $delStmt = $sess_id
            ? $pdo->prepare("DELETE FROM holiday_calendar WHERE holiday_type='weekend' AND YEAR(holiday_date)=? AND session_id=?")
            : $pdo->prepare("DELETE FROM holiday_calendar WHERE holiday_type='weekend' AND YEAR(holiday_date)=? AND session_id IS NULL");
        $sess_id ? $delStmt->execute([$year, $sess_id]) : $delStmt->execute([$year]);

        $ins   = $pdo->prepare('INSERT INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
        $count = 0;
        $date  = new DateTime("$year-01-01");
        $end   = new DateTime("$year-12-31");
        while ((int)$date->format('N') !== 5) $date->modify('+1 day'); // advance to first Friday
        while ($date <= $end) {
            $ins->execute([$sess_id, $date->format('Y-m-d'), 'Friday (Weekly Holiday)', 'weekend']);
            $date->modify('+7 days');
            $count++;
        }
        flash('success', "$count Fridays set as weekend holidays for $year.");
        header('Location: holidays.php?session_id=' . (int)$_POST['session_id'] . '&year=' . $year);
        exit;

    } elseif ($action === 'seed_saturdays') {
        $year    = (int)($_POST['seed_year'] ?? date('Y'));
        $sess_id = int_param('session_id', 0, $_POST) ?: null;
        $ins   = $pdo->prepare('INSERT INTO holiday_calendar (session_id,holiday_date,holiday_name,holiday_type) VALUES (?,?,?,?)');
        $count = 0;
        $date  = new DateTime("$year-01-01");
        $end   = new DateTime("$year-12-31");
        while ((int)$date->format('N') !== 6) $date->modify('+1 day');
        while ($date <= $end) {
            $ins->execute([$sess_id, $date->format('Y-m-d'), 'Saturday (Half-Day / Off)', 'weekend']);
            $date->modify('+7 days');
            $count++;
        }
        flash('success', "$count Saturdays added for $year.");
        header('Location: holidays.php?session_id=' . (int)$_POST['session_id'] . '&year=' . $year);
        exit;
    }
    header('Location: holidays.php');
    exit;
}

$session_id = int_param('session_id', (int)setting('current_session_id',0), $_GET);
$year_f     = int_param('year', (int)date('Y'), $_GET);

$holidays = $pdo->prepare(
    "SELECT * FROM holiday_calendar
     WHERE (session_id=:sess OR session_id IS NULL)
       AND YEAR(holiday_date)=:yr
     ORDER BY holiday_date"
);
$holidays->execute([':sess' => $session_id, ':yr' => $year_f]);
$holidays = $holidays->fetchAll();

$sessions = $pdo->query('SELECT id, session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-event-fill me-2 text-primary"></i>Holiday Calendar</h1>
  <div class="d-flex flex-wrap gap-2">

    <!-- Quick seed form -->
    <form method="POST" class="d-flex gap-2 align-items-center flex-wrap" id="seedForm">
      <?= csrf_field() ?>
      <input type="hidden" name="session_id" value="<?= $session_id ?>">
      <select name="seed_year" class="form-select form-select-sm" style="width:100px;">
        <?php for ($y = date('Y')+1; $y >= date('Y')-2; $y--): ?>
          <option value="<?= $y ?>" <?= $y == $year_f ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <button type="submit" name="action" value="seed_fridays" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-calendar3 me-1"></i>All Fridays
      </button>
      <button type="submit" name="action" value="seed_bd_holidays" class="btn btn-outline-success btn-sm">
        <i class="bi bi-flag me-1"></i>BD Holidays
      </button>
    </form>

    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#holidayModal" onclick="setHolidayForm(null)">
      <i class="bi bi-plus-lg me-1"></i>Add Holiday
    </button>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">Session</label>
        <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($sessions as $sess): ?>
            <option value="<?= $sess['id'] ?>" <?= $session_id == $sess['id'] ? 'selected' : '' ?>><?= e($sess['session_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small">Year</label>
        <input type="number" name="year" class="form-control form-control-sm" value="<?= $year_f ?>" min="2020" max="2035" onchange="this.form.submit()">
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <!-- Calendar month view -->
  <div class="col-md-7">
    <div class="card table-card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Holiday Name</th><th>Type</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($holidays)): ?>
              <tr><td colspan="4"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No holidays for <?= $year_f ?>. Add manually or seed BD national holidays.</p></div></td></tr>
            <?php else: foreach ($holidays as $h): ?>
            <tr>
              <td class="fw-700"><?= fmt_date($h['holiday_date'], 'd M Y (D)') ?></td>
              <td class="fw-600"><?= e($h['holiday_name']) ?></td>
              <td>
                <span class="badge bg-<?= $h['holiday_type'] === 'govt' ? 'danger' : ($h['holiday_type'] === 'institutional' ? 'primary' : 'secondary') ?>">
                  <?= ucfirst(e($h['holiday_type'])) ?>
                </span>
              </td>
              <td>
                <div class="table-actions">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#holidayModal"
                          onclick="setHolidayForm(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Remove this holiday?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Summary -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header py-3 px-4"><span class="card-title">Summary</span></div>
      <div class="card-body">
        <?php
        $byType = [];
        foreach ($holidays as $h) $byType[$h['holiday_type']][] = $h;
        $typeLabels = [
            'govt'        => ['Government / National', 'danger'],
            'weekend'     => ['Weekly Holidays (Fri)', 'secondary'],
            'institutional'=> ['Institutional',        'primary'],
            'custom'      => ['Custom / Other',        'info'],
        ];
        foreach ($typeLabels as $k => [$label, $color]):
            if (empty($byType[$k])) continue;
        ?>
        <div class="mb-3">
          <div class="fw-600 mb-1">
            <span class="badge bg-<?= $color ?> me-1"><?= count($byType[$k]) ?></span>
            <?= $label ?>
          </div>
          <?php if ($k !== 'weekend'): // Don't list all 52 Fridays individually ?>
            <?php foreach ($byType[$k] as $h): ?>
              <div class="small text-muted ms-2">• <?= fmt_date($h['holiday_date'], 'd M') ?> — <?= e($h['holiday_name']) ?></div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="small text-muted ms-2">Every Friday of <?= $year_f ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <hr>
        <div class="small fw-700">Total: <?= count($holidays) ?> entries in <?= $year_f ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Holiday Modal -->
<div class="modal fade" id="holidayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="h_id" value="0">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="holidayModalTitle">Add Holiday</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="holiday_date" id="h_date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
            <input type="text" name="holiday_name" id="h_name" class="form-control" required placeholder="e.g. Eid-ul-Fitr">
          </div>
          <div>
            <label class="form-label">Type</label>
            <select name="holiday_type" id="h_type" class="form-select">
              <option value="govt">Government / National</option>
              <option value="institutional">Institutional</option>
              <option value="custom">Custom</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setHolidayForm(h) {
  document.getElementById('holidayModalTitle').textContent = h ? 'Edit Holiday' : 'Add Holiday';
  document.getElementById('h_id').value   = h ? h.id : 0;
  document.getElementById('h_date').value = h ? h.holiday_date : '';
  document.getElementById('h_name').value = h ? h.holiday_name : '';
  document.getElementById('h_type').value = h ? h.holiday_type : 'institutional';
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
