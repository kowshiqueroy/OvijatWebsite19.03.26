<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Rooms & Labs';
$breadcrumbs = ['Academic' => null, 'Rooms' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['academic.manage']);

$pdo = db();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = int_param('id', 0, $_POST);
        $name = trim($_POST['room_name'] ?? '');
        $num  = trim($_POST['room_number'] ?? '');
        $floor = int_param('floor', 1, $_POST);
        $type  = $_POST['room_type'] ?? 'classroom';

        // Layout: custom JSON or simple benches×bench_capacity
        $layout_raw = trim($_POST['layout_json'] ?? '');
        $is_custom  = ($layout_raw && $layout_raw !== 'null' && $layout_raw !== '[]');

        if ($is_custom) {
            $layout = json_decode($layout_raw, true);
            // Validate and calculate capacity from the JSON
            $cap = 0;
            $maxBenchesInCol = 0;
            $maxSeatsInBench = 0;
            if (isset($layout['cols']) && is_array($layout['cols'])) {
                foreach ($layout['cols'] as $col) {
                    $colCount = count($col);
                    $maxBenchesInCol = max($maxBenchesInCol, $colCount);
                    foreach ($col as $seats) {
                        $seats = max(1, (int)$seats);
                        $cap  += $seats;
                        $maxSeatsInBench = max($maxSeatsInBench, $seats);
                    }
                }
            }
            // Keep simple-mode fallbacks for backward compat
            $benches = $maxBenchesInCol ?: 10;
            $bench_c = $maxSeatsInBench ?: 4;
        } else {
            $benches = max(1, int_param('benches_count', 10, $_POST));
            $bench_c = max(1, int_param('bench_capacity', 2, $_POST));
            $cap     = $benches * $bench_c;
            $layout_raw = null;
        }

        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE rooms SET room_name=?,room_number=?,floor=?,capacity=?,benches_count=?,bench_capacity=?,layout_json=?,room_type=? WHERE id=?')
                    ->execute([$name,$num,$floor,$cap,$benches,$bench_c,$layout_raw,$type,$id]);
                flash('success', 'Room updated.');
            } else {
                $pdo->prepare('INSERT INTO rooms (room_name,room_number,floor,capacity,benches_count,bench_capacity,layout_json,room_type) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$name,$num,$floor,$cap,$benches,$bench_c,$layout_raw,$type]);
                flash('success', "Room '$name' added — $cap exam seats.");
            }
            log_activity('save_room', 'academic', $id ?: (int)$pdo->lastInsertId(), '', "$name cap=$cap");
        }
    } elseif ($action === 'delete') {
        $id = int_param('id', 0, $_POST);
        $pdo->prepare('UPDATE rooms SET status=0 WHERE id=?')->execute([$id]);
        flash('success', 'Room removed.');
    }
    header('Location: rooms.php');
    exit;
}

// ── Load rooms ────────────────────────────────────────────────────────────────
$rooms = $pdo->query("
    SELECT r.*,
           COALESCE(r.benches_count, 10)  AS benches_count,
           COALESCE(r.bench_capacity, 2)   AS bench_capacity,
           (SELECT COUNT(*) FROM exam_seats es WHERE es.room_id=r.id) AS exam_seat_count
    FROM rooms r WHERE r.status=1 ORDER BY r.floor, r.room_name
")->fetchAll();

// Helper: compute display layout from a room row
function room_layout_summary(array $rm): array {
    if (!empty($rm['layout_json'])) {
        $j = json_decode($rm['layout_json'], true);
        if (isset($j['cols'])) {
            $numCols = count($j['cols']);
            $colSummaries = [];
            $total = 0;
            foreach ($j['cols'] as $colIdx => $benches) {
                $ct = array_sum($benches);
                $total += $ct;
                $colSummaries[] = ['benches' => count($benches), 'seats' => $ct, 'data' => $benches];
            }
            return ['type' => 'custom', 'cols' => $numCols, 'total' => $total, 'col_summaries' => $colSummaries];
        }
    }
    $b = (int)$rm['benches_count'];
    $c = (int)$rm['bench_capacity'];
    return ['type' => 'simple', 'cols' => 1, 'total' => $b * $c, 'benches' => $b, 'bench_cap' => $c];
}

require_once EMS_ROOT . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-door-open-fill me-2 text-primary"></i>Rooms & Labs</h1>
  <?php if (has_permission('academic.manage')): ?>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openRoomModal(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Room
  </button>
  <?php endif; ?>
</div>

<?php render_flash(); ?>

<?php if (empty($rooms)): ?>
  <div class="card"><div class="card-body"><div class="empty-state">
    <i class="bi bi-building"></i>
    <p>No rooms added yet. Add rooms to use in exam seat plans and timetables.</p>
  </div></div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($rooms as $rm):
    $layout = room_layout_summary($rm);
    $typeColor = match($rm['room_type']) {
        'exam_hall' => 'danger', 'lab' => 'warning', 'library' => 'info',
        'office' => 'secondary', default => 'primary',
    };
    $typeDark = in_array($rm['room_type'], ['lab']);
  ?>
  <div class="col-md-4 col-sm-6">
    <div class="card h-100 shadow-sm">
      <div class="card-body pb-2">
        <!-- Header -->
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div>
            <h6 class="fw-700 mb-0"><?= e($rm['room_name']) ?></h6>
            <div class="text-muted small">Room <?= e($rm['room_number'] ?: '—') ?> &nbsp;·&nbsp; Floor <?= $rm['floor'] ?></div>
          </div>
          <span class="badge bg-<?= $typeColor ?> <?= $typeDark ? 'text-dark' : '' ?>">
            <?= ucfirst(str_replace('_',' ',e($rm['room_type']))) ?>
          </span>
        </div>

        <!-- Layout info -->
        <div class="rounded p-2 mb-2" style="background:#f8fafc;border:1px solid #e2e8f0;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="small fw-700 text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.05em;">
              <?= $layout['type'] === 'custom' ? 'Custom Layout' : 'Simple Layout' ?>
            </span>
            <span class="badge bg-success fw-700"><?= $layout['total'] ?> seats</span>
          </div>

          <?php if ($layout['type'] === 'custom'): ?>
            <!-- Multi-column custom layout summary -->
            <div class="d-flex gap-2 align-items-start flex-wrap">
              <?php foreach ($layout['col_summaries'] as $ci => $col): ?>
              <div class="text-center" style="flex:1;min-width:40px;">
                <!-- Mini bench column preview -->
                <div class="d-flex flex-column align-items-center gap-1 mb-1">
                  <?php $previewBenches = array_slice($col['data'], 0, 5);
                  foreach ($previewBenches as $bSeats): ?>
                    <div style="display:flex;gap:1px;">
                      <?php for ($si=0;$si<$bSeats;$si++): ?>
                        <div style="width:8px;height:6px;background:#1a56db;border-radius:1px;opacity:.7;"></div>
                      <?php endfor; ?>
                    </div>
                  <?php endforeach; ?>
                  <?php if (count($col['data']) > 5): ?>
                    <div style="font-size:9px;color:#94a3b8;">+<?= count($col['data'])-5 ?></div>
                  <?php endif; ?>
                </div>
                <div style="font-size:.65rem;color:#64748b;">C<?= $ci+1 ?><br><?= $col['benches'] ?>B · <?= $col['seats'] ?>s</div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <!-- Simple layout -->
            <div class="d-flex align-items-center gap-2">
              <div style="display:grid;grid-template-columns:repeat(<?= $layout['bench_cap'] ?>,10px);gap:2px;flex-shrink:0;">
                <?php $pr = min($layout['benches'],5);
                for($r=0;$r<$pr;$r++) for($c=0;$c<$layout['bench_cap'];$c++): ?>
                  <div style="width:10px;height:8px;background:#1a56db;border-radius:1px;opacity:.7;"></div>
                <?php endfor; ?>
                <?php if ($layout['benches'] > 5): ?>
                  <div style="grid-column:1/-1;font-size:8px;color:#94a3b8;text-align:center;">+<?= $layout['benches']-5 ?></div>
                <?php endif; ?>
              </div>
              <div class="small text-muted">
                <strong><?= $layout['benches'] ?></strong> rows × <strong><?= $layout['bench_cap'] ?></strong> seats/row
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($rm['exam_seat_count'] > 0): ?>
          <div class="small text-muted"><i class="bi bi-clipboard2-check me-1 text-warning"></i>Used in <?= $rm['exam_seat_count'] ?> seat assignment(s)</div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex gap-2 py-2">
        <button class="btn btn-sm btn-outline-primary flex-fill"
                data-bs-toggle="modal" data-bs-target="#roomModal"
                onclick="openRoomModal(<?= htmlspecialchars(json_encode($rm), ENT_QUOTES) ?>)">
          <i class="bi bi-pencil me-1"></i>Edit Layout
        </button>
        <form method="POST" class="d-inline flex-fill" data-no-protect>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $rm['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                  onclick="return confirm('Remove \'<?= e(addslashes($rm['room_name'])) ?>\'?')">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     Room Add/Edit Modal with Custom Layout Editor
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="roomModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" id="roomForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="rm_id" value="0">
        <input type="hidden" name="layout_json" id="rm_layout_json_field" value="">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-600" id="roomModalTitle">
            <i class="bi bi-door-open me-2"></i>Room Layout Editor
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body" style="overflow-y:auto;max-height:calc(100vh - 200px);">

          <!-- ── Basic Info ──────────────────────────────────────────────── -->
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label small fw-600">Room Name <span class="text-danger">*</span></label>
              <input type="text" name="room_name" id="rm_name" class="form-control" required placeholder="e.g. Exam Hall A">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-600">Room No.</label>
              <input type="text" name="room_number" id="rm_num" class="form-control" placeholder="101">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-600">Floor</label>
              <input type="number" name="floor" id="rm_floor" class="form-control" value="1" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-600">Room Type</label>
              <select name="room_type" id="rm_type" class="form-select">
                <?php foreach(['classroom'=>'Classroom','lab'=>'Lab','office'=>'Office','exam_hall'=>'Exam Hall','library'=>'Library','other'=>'Other'] as $k=>$v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <hr class="my-3">

          <!-- ── Layout Mode Toggle ─────────────────────────────────────── -->
          <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <span class="fw-700 small text-uppercase text-muted" style="letter-spacing:.05em;">
              <i class="bi bi-grid-3x3-gap-fill me-1 text-primary"></i>Seat Layout
            </span>
            <div class="d-flex gap-2">
              <button type="button" id="btn_simple" class="btn btn-sm btn-primary" onclick="setMode('simple')">
                <i class="bi bi-grid me-1"></i>Simple (Uniform Grid)
              </button>
              <button type="button" id="btn_custom" class="btn btn-sm btn-outline-secondary" onclick="setMode('custom')">
                <i class="bi bi-layout-three-columns me-1"></i>Custom (Per-Bench Control)
              </button>
            </div>
            <div class="ms-auto">
              <span class="fw-700 text-success fs-5" id="rm_total_display">0</span>
              <span class="text-muted small"> total seats</span>
            </div>
          </div>

          <!-- ── SIMPLE MODE ────────────────────────────────────────────── -->
          <div id="simple_mode">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label small fw-600">Benches / Rows <small class="text-muted">(↕ depth)</small></label>
                <input type="number" name="benches_count" id="rm_benches" class="form-control" value="10" min="1" max="200" oninput="simpleChanged()">
                <div class="form-text">Rows of benches front-to-back</div>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-600">Seats per Bench <small class="text-muted">(↔ width)</small></label>
                <input type="number" name="bench_capacity" id="rm_bench_cap" class="form-control" value="2" min="1" max="20" oninput="simpleChanged()">
                <div class="form-text">Students side-by-side per bench</div>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-600">Capacity (auto)</label>
                <input type="text" id="rm_simple_cap" class="form-control fw-700 text-success" readonly>
              </div>
              <div class="col-md-3 d-flex align-items-center">
                <!-- Mini preview -->
                <div id="simple_mini_preview" style="display:inline-grid;gap:2px;"></div>
              </div>
            </div>
          </div>

          <!-- ── CUSTOM MODE ────────────────────────────────────────────── -->
          <div id="custom_mode" style="display:none;">
            <!-- Controls bar -->
            <div class="d-flex align-items-end gap-3 mb-3 flex-wrap p-3 rounded" style="background:#f1f5f9;">
              <div>
                <label class="form-label small fw-600 mb-1">No. of Bench Columns</label>
                <div class="d-flex align-items-center gap-1">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustCols(-1)">−</button>
                  <input type="number" id="ctrl_cols" class="form-control form-control-sm text-center" style="width:60px;" value="3" min="1" max="10" oninput="colCountChanged()">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustCols(1)">+</button>
                </div>
              </div>
              <div>
                <label class="form-label small fw-600 mb-1">Default Benches/Col</label>
                <input type="number" id="ctrl_def_benches" class="form-control form-control-sm text-center" style="width:70px;" value="10" min="1" onchange="applyDefaults('benches')">
              </div>
              <div>
                <label class="form-label small fw-600 mb-1">Default Seats/Bench</label>
                <input type="number" id="ctrl_def_seats" class="form-control form-control-sm text-center" style="width:70px;" value="4" min="1" onchange="applyDefaults('seats')">
              </div>
              <button type="button" class="btn btn-sm btn-warning" onclick="applyDefaults('both')">
                <i class="bi bi-arrow-repeat me-1"></i>Apply Defaults to All
              </button>
              <div class="ms-auto text-muted small text-end">
                Click any seat count to change it.<br>
                Use +/− to add or remove benches per column.
              </div>
            </div>

            <!-- Column editor -->
            <div id="col_editor" class="d-flex gap-3 pb-2" style="overflow-x:auto;min-height:200px;"></div>
          </div>

        </div><!-- /modal-body -->

        <div class="modal-footer py-2 d-flex align-items-center gap-2">
          <div class="text-muted small me-auto">
            <i class="bi bi-info-circle me-1 text-primary"></i>
            Seat layout is used for exam seat plan generation and room capacity calculations.
          </div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Room</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Column editor bench items */
.bench-item {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 3px;
}
.bench-num {
  font-size: .7rem;
  color: #94a3b8;
  width: 20px;
  text-align: right;
  flex-shrink: 0;
}
.bench-seat-input {
  width: 52px;
  text-align: center;
  font-size: .82rem;
  padding: 2px 4px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #fff;
}
.bench-seat-input:focus {
  outline: none;
  border-color: #1a56db;
  box-shadow: 0 0 0 2px rgba(26,86,219,.15);
}
.bench-remove-btn {
  background: none;
  border: none;
  color: #ef4444;
  cursor: pointer;
  font-size: .7rem;
  padding: 0 2px;
  opacity: .5;
}
.bench-remove-btn:hover { opacity: 1; }
.col-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 8px;
  min-width: 140px;
  flex-shrink: 0;
}
.col-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
  padding-bottom: 4px;
  border-bottom: 2px solid #1a56db;
}
.col-title { font-weight: 700; font-size: .8rem; color: #1a56db; }
.col-total { font-size: .7rem; color: #10b981; font-weight: 700; }
.col-bench-list { max-height: 280px; overflow-y: auto; }
.add-bench-btn {
  width: 100%;
  padding: 3px;
  font-size: .72rem;
  border: 1px dashed #94a3b8;
  background: none;
  border-radius: 4px;
  color: #64748b;
  cursor: pointer;
  margin-top: 4px;
}
.add-bench-btn:hover { border-color: #1a56db; color: #1a56db; background: #eff6ff; }
</style>

<script>
// ─── State ────────────────────────────────────────────────────────────────────
let currentMode = 'simple';
// layout = { cols: [[seats,seats,...], [seats,...], ...] }
let layout = { cols: [] };

// ─── Mode switch ──────────────────────────────────────────────────────────────
function setMode(mode) {
  currentMode = mode;
  document.getElementById('simple_mode').style.display = mode === 'simple' ? '' : 'none';
  document.getElementById('custom_mode').style.display = mode === 'custom' ? '' : 'none';
  document.getElementById('btn_simple').className = 'btn btn-sm ' + (mode === 'simple' ? 'btn-primary' : 'btn-outline-secondary');
  document.getElementById('btn_custom').className = 'btn btn-sm ' + (mode === 'custom' ? 'btn-primary' : 'btn-outline-secondary');
  if (mode === 'custom') {
    if (layout.cols.length === 0) initCustomFromSimple();
    renderColEditor();
  } else {
    document.getElementById('rm_layout_json_field').value = '';
    simpleChanged();
  }
}

// ─── Simple mode ──────────────────────────────────────────────────────────────
function simpleChanged() {
  const benches = parseInt(document.getElementById('rm_benches').value) || 10;
  const seats   = parseInt(document.getElementById('rm_bench_cap').value) || 2;
  const total   = benches * seats;
  document.getElementById('rm_simple_cap').value = total;
  document.getElementById('rm_total_display').textContent = total;
  document.getElementById('rm_layout_json_field').value = '';
  // Mini preview
  const grid = document.getElementById('simple_mini_preview');
  const PR = Math.min(benches, 6), PC = Math.min(seats, 8);
  grid.style.gridTemplateColumns = `repeat(${PC}, 10px)`;
  grid.innerHTML = '';
  for (let r = 0; r < PR; r++) {
    for (let c = 0; c < PC; c++) {
      const el = document.createElement('div');
      el.style.cssText = 'width:10px;height:8px;background:#1a56db;border-radius:1px;opacity:.7;';
      grid.appendChild(el);
    }
  }
  if (benches > 6 || seats > 8) {
    const note = document.createElement('div');
    note.style.cssText = 'grid-column:1/-1;font-size:8px;color:#94a3b8;text-align:center;';
    note.textContent = `${benches}×${seats}`;
    grid.appendChild(note);
  }
}

// ─── Custom mode — initialise from simple values when first switching ─────────
function initCustomFromSimple() {
  const defCols   = parseInt(document.getElementById('ctrl_cols').value) || 3;
  const defBenches= parseInt(document.getElementById('ctrl_def_benches').value) || 10;
  const defSeats  = parseInt(document.getElementById('ctrl_def_seats').value) || 4;
  layout.cols = [];
  for (let c = 0; c < defCols; c++) {
    layout.cols.push(Array(defBenches).fill(defSeats));
  }
}

// ─── Column count changed ────────────────────────────────────────────────────
function colCountChanged() {
  const n = Math.max(1, Math.min(10, parseInt(document.getElementById('ctrl_cols').value) || 1));
  const defBenches = parseInt(document.getElementById('ctrl_def_benches').value) || 10;
  const defSeats   = parseInt(document.getElementById('ctrl_def_seats').value) || 4;
  while (layout.cols.length < n) layout.cols.push(Array(defBenches).fill(defSeats));
  if (layout.cols.length > n) layout.cols = layout.cols.slice(0, n);
  renderColEditor();
}
function adjustCols(delta) {
  const inp = document.getElementById('ctrl_cols');
  inp.value = Math.max(1, Math.min(10, (parseInt(inp.value) || 1) + delta));
  colCountChanged();
}

// ─── Apply defaults ──────────────────────────────────────────────────────────
function applyDefaults(what) {
  const defBenches = parseInt(document.getElementById('ctrl_def_benches').value) || 10;
  const defSeats   = parseInt(document.getElementById('ctrl_def_seats').value) || 4;
  layout.cols = layout.cols.map(col => {
    if (what === 'benches' || what === 'both') {
      return Array(defBenches).fill(defSeats);
    } else if (what === 'seats') {
      return col.map(() => defSeats);
    }
    return col;
  });
  renderColEditor();
}

// ─── Add / remove bench ───────────────────────────────────────────────────────
function addBench(colIdx) {
  const defSeats = parseInt(document.getElementById('ctrl_def_seats').value) || 4;
  layout.cols[colIdx].push(defSeats);
  renderColEditor();
}
function removeBench(colIdx, benchIdx) {
  if (layout.cols[colIdx].length <= 1) return; // keep at least 1
  layout.cols[colIdx].splice(benchIdx, 1);
  renderColEditor();
}
function updateSeat(colIdx, benchIdx, val) {
  layout.cols[colIdx][benchIdx] = Math.max(1, parseInt(val) || 1);
  updateTotals();
}

// ─── Render column editor ────────────────────────────────────────────────────
function renderColEditor() {
  document.getElementById('ctrl_cols').value = layout.cols.length;
  const container = document.getElementById('col_editor');
  container.innerHTML = '';

  layout.cols.forEach((col, ci) => {
    const colTotal = col.reduce((a, b) => a + b, 0);
    const card = document.createElement('div');
    card.className = 'col-card';

    const header = document.createElement('div');
    header.className = 'col-card-header';
    header.innerHTML = `
      <span class="col-title">Column ${ci + 1}</span>
      <span class="col-total" id="col-total-${ci}">${colTotal} seats</span>`;
    card.appendChild(header);

    const list = document.createElement('div');
    list.className = 'col-bench-list';

    col.forEach((seats, bi) => {
      const row = document.createElement('div');
      row.className = 'bench-item';
      row.innerHTML = `
        <span class="bench-num">B${bi + 1}</span>
        <input type="number" class="bench-seat-input" value="${seats}" min="1" max="20"
               oninput="updateSeat(${ci}, ${bi}, this.value); rerenderColTotal(${ci});"
               title="Seats in Column ${ci+1} Bench ${bi+1}">
        <button type="button" class="bench-remove-btn" onclick="removeBench(${ci}, ${bi})" title="Remove this bench">✕</button>`;
      list.appendChild(row);
    });
    card.appendChild(list);

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'add-bench-btn';
    addBtn.innerHTML = '+ Add Bench';
    addBtn.onclick = () => addBench(ci);
    card.appendChild(addBtn);

    container.appendChild(card);
  });

  updateTotals();
}

// ─── Recalculate one column total without full rerender ──────────────────────
function rerenderColTotal(ci) {
  // Read current inputs for this column
  const inputs = document.querySelectorAll(`#col_editor .col-card:nth-child(${ci+1}) .bench-seat-input`);
  let colTotal = 0;
  inputs.forEach((inp, bi) => {
    const v = Math.max(1, parseInt(inp.value) || 1);
    layout.cols[ci][bi] = v;
    colTotal += v;
  });
  const el = document.getElementById(`col-total-${ci}`);
  if (el) el.textContent = colTotal + ' seats';
  updateTotals();
}

// ─── Sync totals & serialize JSON ────────────────────────────────────────────
function updateTotals() {
  let grand = 0;
  layout.cols.forEach(col => { grand += col.reduce((a, b) => a + b, 0); });
  document.getElementById('rm_total_display').textContent = grand;
  // Serialize to hidden field
  if (currentMode === 'custom') {
    document.getElementById('rm_layout_json_field').value = JSON.stringify({ cols: layout.cols });
  }
}

// ─── Open modal with existing room data ──────────────────────────────────────
function openRoomModal(r) {
  document.getElementById('roomModalTitle').innerHTML =
    '<i class="bi bi-door-open me-2"></i>' + (r ? 'Edit Room: ' + (r.room_name || '') : 'Add Room');
  document.getElementById('rm_id').value    = r ? r.id : 0;
  document.getElementById('rm_name').value  = r ? r.room_name : '';
  document.getElementById('rm_num').value   = r ? (r.room_number || '') : '';
  document.getElementById('rm_floor').value = r ? r.floor : 1;
  document.getElementById('rm_type').value  = r ? r.room_type : 'classroom';

  // Reset layout state
  layout = { cols: [] };

  if (r && r.layout_json) {
    // Restore custom layout
    try {
      const parsed = JSON.parse(r.layout_json);
      if (parsed.cols) {
        layout.cols = parsed.cols;
        document.getElementById('ctrl_cols').value = layout.cols.length;
        // Guess defaults from data
        const firstCol = layout.cols[0] || [];
        document.getElementById('ctrl_def_benches').value = firstCol.length || 10;
        document.getElementById('ctrl_def_seats').value = firstCol[0] || 4;
        setMode('custom');
        return;
      }
    } catch(e) {}
  }

  // Simple mode defaults
  document.getElementById('rm_benches').value  = r ? (r.benches_count || 10) : 10;
  document.getElementById('rm_bench_cap').value= r ? (r.bench_capacity || 2)  : 2;
  document.getElementById('ctrl_def_benches').value = r ? (r.benches_count || 10) : 10;
  document.getElementById('ctrl_def_seats').value   = r ? (r.bench_capacity || 4)  : 4;
  setMode('simple');
}

// Init on load
document.addEventListener('DOMContentLoaded', () => { simpleChanged(); });
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
