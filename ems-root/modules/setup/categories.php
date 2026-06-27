<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Dropdown Options';
$breadcrumbs = ['Setup' => 'index.php', 'Dropdown Options' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['setup.edit']);

$pdo = db();

// ── Table definitions for each managed category ──────────────────────────────
// [tab_id, label, table, cols_to_manage, extra_cols_schema]
// extra_cols_schema: array of [name, label, type, options/default]
$CATEGORIES = [
    'designation' => [
        'label'   => 'Designations',
        'icon'    => 'person-badge-fill',
        'table'   => 'designation_types',
        'cols'    => [
            ['name'=>'designation_name', 'label'=>'Designation Name', 'type'=>'text', 'required'=>true],
            ['name'=>'designation_role', 'label'=>'Category',         'type'=>'select',
             'options'=>['head'=>'Head / Principal','academic'=>'Academic / Teaching','admin'=>'Administration','support'=>'Support Staff','other'=>'Other']],
            ['name'=>'display_order',    'label'=>'Sort Order',       'type'=>'number','required'=>false],
            ['name'=>'status',           'label'=>'Active?',          'type'=>'bool'],
        ],
        'pk' => 'id',
    ],
    'groups_stream' => [
        'label'   => 'Groups / Streams',
        'icon'    => 'diagram-3-fill',
        'table'   => 'groups_stream',
        'cols'    => [
            ['name'=>'group_name',  'label'=>'Group Name', 'type'=>'text', 'required'=>true],
            ['name'=>'group_code',  'label'=>'Code',       'type'=>'text', 'required'=>true],
            ['name'=>'applicable_from_class_level', 'label'=>'Applicable From', 'type'=>'select',
             'options'=>['secondary'=>'Secondary (Class 9-10)','higher_secondary'=>'Higher Secondary (11-12)','both'=>'Both']],
            ['name'=>'status',      'label'=>'Active?',    'type'=>'bool'],
        ],
        'pk' => 'id',
    ],
    'fee_cat' => [
        'label'   => 'Fee Categories',
        'icon'    => 'cash-coin',
        'table'   => 'fee_categories',
        'cols'    => [
            ['name'=>'category_name',  'label'=>'Category Name', 'type'=>'text',   'required'=>true],
            ['name'=>'category_type',  'label'=>'Type',          'type'=>'select',
             'options'=>['tuition'=>'Tuition','admission'=>'Admission','session'=>'Session Charge',
                         'transport'=>'Transport','hostel'=>'Hostel','exam'=>'Exam Fee',
                         'library'=>'Library Fee','sport'=>'Sports Fee','fine'=>'Fine / Penalty',
                         'custom'=>'Custom']],
            ['name'=>'description',    'label'=>'Description',   'type'=>'text',   'required'=>false],
            ['name'=>'status',         'label'=>'Active?',       'type'=>'bool'],
        ],
        'pk' => 'id',
    ],
    'income_cat' => [
        'label'   => 'Income Categories',
        'icon'    => 'piggy-bank-fill',
        'table'   => 'income_categories',
        'cols'    => [
            ['name'=>'category_name',  'label'=>'Category Name', 'type'=>'text', 'required'=>true],
            ['name'=>'description',    'label'=>'Description',   'type'=>'text', 'required'=>false],
        ],
        'pk' => 'id',
    ],
    'expense_cat' => [
        'label'   => 'Expense Categories',
        'icon'    => 'receipt-cutoff',
        'table'   => 'expense_categories',
        'cols'    => [
            ['name'=>'category_name',  'label'=>'Category Name', 'type'=>'text', 'required'=>true],
            ['name'=>'description',    'label'=>'Description',   'type'=>'text', 'required'=>false],
        ],
        'pk' => 'id',
    ],
    'leave_type' => [
        'label'   => 'Leave Types',
        'icon'    => 'calendar-x-fill',
        'table'   => 'leave_types',
        'cols'    => [
            ['name'=>'leave_name',     'label'=>'Leave Type Name', 'type'=>'text',   'required'=>true],
            ['name'=>'annual_quota',   'label'=>'Annual Days',     'type'=>'number', 'required'=>true, 'min'=>0],
            ['name'=>'is_paid',        'label'=>'Paid Leave?',     'type'=>'bool'],
            ['name'=>'applicable_to',  'label'=>'Applies To',      'type'=>'select',
             'options'=>['all'=>'All Staff','teacher'=>'Teachers Only','staff'=>'Non-Teaching Staff']],
        ],
        'pk' => 'id',
    ],
    'asset_cat' => [
        'label'   => 'Asset Categories',
        'icon'    => 'pc-display',
        'table'   => 'asset_categories',
        'cols'    => [
            ['name'=>'category_name',  'label'=>'Category Name', 'type'=>'text', 'required'=>true],
            ['name'=>'description',    'label'=>'Description',   'type'=>'text', 'required'=>false],
        ],
        'pk' => 'id',
    ],
    'con_cat' => [
        'label'   => 'Consumable Categories',
        'icon'    => 'box-seam-fill',
        'table'   => 'consumable_categories',
        'cols'    => [
            ['name'=>'category_name',  'label'=>'Category Name', 'type'=>'text', 'required'=>true],
        ],
        'pk' => 'id',
    ],
    'sms_tpl' => [
        'label'   => 'SMS Templates',
        'icon'    => 'chat-left-dots-fill',
        'table'   => 'sms_templates',
        'cols'    => [
            ['name'=>'template_name',  'label'=>'Template Name', 'type'=>'text', 'required'=>true],
            ['name'=>'template_body',  'label'=>'Message Body',  'type'=>'textarea', 'required'=>true],
            ['name'=>'trigger_type',   'label'=>'Trigger',       'type'=>'select',
             'options'=>['custom'=>'Custom/Manual','attendance'=>'Attendance Alert','absent_student'=>'Student Absent',
                         'absent_staff'=>'Staff Absent','fee_due'=>'Fee Due Reminder',
                         'fee_collection'=>'Fee Collection Receipt','result'=>'Result Published',
                         'money_transaction'=>'Money Transaction','emergency'=>'Emergency']],
            ['name'=>'status',         'label'=>'Active?',       'type'=>'bool'],
        ],
        'pk' => 'id',
    ],
    'institute_level' => [
        'label'   => 'Institute Levels',
        'icon'    => 'layers-fill',
        'table'   => 'institute_levels',
        'cols'    => [
            ['name'=>'level_name',    'label'=>'Level Name',  'type'=>'text',   'required'=>true],
            ['name'=>'level_code',    'label'=>'Code',        'type'=>'text',   'required'=>true],
            ['name'=>'display_order', 'label'=>'Sort Order',  'type'=>'number', 'required'=>false],
            ['name'=>'status',        'label'=>'Active?',     'type'=>'bool'],
        ],
        'pk' => 'id',
    ],
];

// ── Handle POST (save / delete) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $tab_id  = $_POST['tab_id'] ?? '';
    $cfg     = $CATEGORIES[$tab_id] ?? null;

    if ($cfg) {
        $tbl = $cfg['table'];
        $pk  = $cfg['pk'];

        if ($action === 'save') {
            $id = int_param('id', 0, $_POST);
            // Build data from cols
            $data  = [];
            $names = [];
            $vals  = [];
            foreach ($cfg['cols'] as $col) {
                $names[] = $col['name'];
                if ($col['type'] === 'bool') {
                    $vals[] = isset($_POST[$col['name']]) ? 1 : 0;
                } elseif ($col['type'] === 'number') {
                    $vals[] = (int)($_POST[$col['name']] ?? 0);
                } else {
                    $vals[] = trim($_POST[$col['name']] ?? '');
                }
            }
            // Validate required
            $ok = true;
            foreach ($cfg['cols'] as $i => $col) {
                if (!empty($col['required']) && $vals[$i] === '') { $ok = false; break; }
            }
            if ($ok) {
                if ($id) {
                    $sets = implode(',', array_map(fn($n) => "$n=?", $names));
                    $pdo->prepare("UPDATE $tbl SET $sets WHERE $pk=?")->execute([...$vals, $id]);
                    flash('success', 'Updated successfully.');
                } else {
                    $cols_sql = implode(',', $names);
                    $phs      = implode(',', array_fill(0, count($names), '?'));
                    $pdo->prepare("INSERT INTO $tbl ($cols_sql) VALUES ($phs)")->execute($vals);
                    flash('success', 'Entry added.');
                }
            } else {
                flash('error', 'Please fill in all required fields.');
            }

        } elseif ($action === 'delete') {
            $id = int_param('id', 0, $_POST);
            if ($id) {
                try {
                    $pdo->prepare("DELETE FROM $tbl WHERE $pk=?")->execute([$id]);
                    flash('success', 'Entry deleted.');
                } catch (Exception $e) {
                    flash('error', 'Cannot delete — this entry is referenced by other records.');
                }
            }
        } elseif ($action === 'toggle') {
            // Toggle status/is_paid bool
            $id    = int_param('id', 0, $_POST);
            $field = preg_replace('/[^a-z_]/', '', $_POST['field'] ?? 'status');
            if ($id && $field) {
                $pdo->prepare("UPDATE $tbl SET $field = 1 - $field WHERE $pk=?")->execute([$id]);
            }
        }
    }

    $redirect_tab = $_POST['tab_id'] ?? 'fee_cat';
    header("Location: categories.php?tab=$redirect_tab");
    exit;
}

$active_tab = $_GET['tab'] ?? 'fee_cat';
if (!isset($CATEGORIES[$active_tab])) $active_tab = 'fee_cat';

// ── Load rows for active tab ──────────────────────────────────────────────────
$cfg  = $CATEGORIES[$active_tab];
$rows = $pdo->query("SELECT * FROM {$cfg['table']} ORDER BY {$cfg['pk']} DESC")->fetchAll();

require_once EMS_ROOT . '/includes/header.php';

// ── Helper: render a value cell ───────────────────────────────────────────────
function render_val(array $col, mixed $val): string {
    if ($col['type'] === 'bool') {
        $on = (int)$val;
        return $on
            ? '<span class="badge bg-success">Yes</span>'
            : '<span class="badge bg-secondary">No</span>';
    }
    if ($col['type'] === 'select' && isset($col['options'][$val])) {
        return '<span class="badge bg-light text-dark border">'.htmlspecialchars($col['options'][$val]).'</span>';
    }
    if ($col['type'] === 'textarea') {
        return '<span class="text-muted small">'.htmlspecialchars(mb_strimwidth((string)$val,0,60,'…')).'</span>';
    }
    return htmlspecialchars((string)$val);
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0">
    <i class="bi bi-sliders me-2 text-primary"></i>Dropdown Options & Categories
  </h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal"
          onclick="setForm(null)">
    <i class="bi bi-plus-lg me-1"></i>Add Entry
  </button>
</div>

<p class="text-muted mb-3 small">
  <i class="bi bi-info-circle me-1"></i>
  Manage all dropdown lists used across the system — fee types, leave categories, SMS templates, and more.
  Changes here immediately affect all relevant forms.
</p>

<!-- Tab navigation -->
<ul class="nav nav-tabs mb-0" style="border-bottom:none;">
  <?php foreach ($CATEGORIES as $tid => $cat): ?>
  <li class="nav-item">
    <a class="nav-link d-flex align-items-center gap-1 py-2 px-3 <?= $active_tab===$tid?'active':'' ?>"
       href="?tab=<?= $tid ?>">
      <i class="bi bi-<?= e($cat['icon']) ?> small"></i>
      <span><?= e($cat['label']) ?></span>
      <?php
      $cnt = (int)$pdo->query("SELECT COUNT(*) FROM {$cat['table']}")->fetchColumn();
      echo '<span class="badge bg-secondary ms-1" style="font-size:.65rem;">'.$cnt.'</span>';
      ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- Table card -->
<div class="card table-card" style="border-top-left-radius:0;border-top:none;">
  <div class="card-header d-flex align-items-center justify-content-between py-3 px-4"
       style="background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
    <div>
      <i class="bi bi-<?= e($cfg['icon']) ?> me-2 text-primary"></i>
      <span class="card-title"><?= e($cfg['label']) ?></span>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#catModal"
            onclick="setForm(null)">
      <i class="bi bi-plus-lg me-1"></i>New
    </button>
  </div>

  <?php if (empty($rows)): ?>
  <div class="card-body">
    <div class="empty-state">
      <i class="bi bi-<?= e($cfg['icon']) ?>"></i>
      <p>No entries yet. Click <strong>New</strong> to add the first one.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="data-table">
      <thead>
        <tr>
          <th style="width:40px;">#</th>
          <?php foreach ($cfg['cols'] as $col): ?>
            <th><?= e($col['label']) ?></th>
          <?php endforeach; ?>
          <th style="width:90px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $row): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <?php foreach ($cfg['cols'] as $col): ?>
          <td><?= render_val($col, $row[$col['name']] ?? '') ?></td>
          <?php endforeach; ?>
          <td>
            <div class="table-actions">
              <button class="btn btn-sm btn-outline-primary"
                      data-bs-toggle="modal" data-bs-target="#catModal"
                      onclick="setForm(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="tab_id"  value="<?= e($active_tab) ?>">
                <input type="hidden" name="id"      value="<?= (int)$row[$cfg['pk']] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        data-confirm="Delete this entry? This cannot be undone if it is in use.">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Quick reference box -->
<div class="card mt-3">
  <div class="card-header py-3 px-4">
    <span class="card-title small"><i class="bi bi-question-circle me-2"></i>Where is this used?</span>
  </div>
  <div class="card-body py-3">
    <?php $usageMap = [
      'fee_cat'     => ['Fee Structures', 'Fee Collection', 'Fee Ledger', 'Receipt'],
      'income_cat'  => ['Non-Fee Income recording'],
      'expense_cat' => ['Expense Tracker', 'Event Budgets'],
      'leave_type'  => ['Leave Applications', 'HR module'],
      'asset_cat'   => ['Fixed Assets list'],
      'con_cat'     => ['Consumables & Stock'],
      'sms_tpl'     => ['Send SMS page (Template picker)'],
    ];
    $used = $usageMap[$active_tab] ?? []; ?>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($used as $u): ?>
      <span class="badge bg-light text-dark border"><i class="bi bi-link-45deg me-1"></i><?= e($u) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog <?= in_array($active_tab,['sms_tpl']) ? 'modal-lg' : '' ?>">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action"  value="save">
        <input type="hidden" name="tab_id"  value="<?= e($active_tab) ?>">
        <input type="hidden" name="id"      id="edit_id" value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="catModalTitle">Add <?= e($cfg['label']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <?php foreach ($cfg['cols'] as $col):
              $width = ($col['type'] === 'textarea' || $col['name'] === 'category_name' || $col['name'] === 'leave_name' || $col['name'] === 'template_name') ? '12' : '6';
              if ($col['type'] === 'bool') $width = '4';
              if ($col['type'] === 'textarea') $width = '12';
            ?>
            <div class="col-md-<?= $width ?>">
              <label class="form-label">
                <?= e($col['label']) ?>
                <?php if (!empty($col['required'])): ?><span class="text-danger">*</span><?php endif; ?>
              </label>

              <?php if ($col['type'] === 'text'): ?>
                <input type="text" name="<?= e($col['name']) ?>" id="f_<?= e($col['name']) ?>"
                       class="form-control" <?= !empty($col['required']) ? 'required' : '' ?>>

              <?php elseif ($col['type'] === 'number'): ?>
                <input type="number" name="<?= e($col['name']) ?>" id="f_<?= e($col['name']) ?>"
                       class="form-control" min="<?= $col['min'] ?? 0 ?>" value="0"
                       <?= !empty($col['required']) ? 'required' : '' ?>>

              <?php elseif ($col['type'] === 'textarea'): ?>
                <textarea name="<?= e($col['name']) ?>" id="f_<?= e($col['name']) ?>"
                          class="form-control" rows="3"
                          <?= !empty($col['required']) ? 'required' : '' ?>></textarea>
                <?php if ($col['name'] === 'template_body'): ?>
                <small class="text-muted">
                  Shortcodes: <code>[Student_Name]</code> <code>[Class]</code>
                  <code>[Amount]</code> <code>[Date]</code> <code>[School_Name]</code>
                </small>
                <?php endif; ?>

              <?php elseif ($col['type'] === 'select'): ?>
                <select name="<?= e($col['name']) ?>" id="f_<?= e($col['name']) ?>"
                        class="form-select">
                  <?php foreach ($col['options'] as $v => $lbl): ?>
                    <option value="<?= e($v) ?>"><?= e($lbl) ?></option>
                  <?php endforeach; ?>
                </select>

              <?php elseif ($col['type'] === 'bool'): ?>
                <div class="form-check form-switch mt-2">
                  <input type="checkbox" class="form-check-input" role="switch"
                         name="<?= e($col['name']) ?>" id="f_<?= e($col['name']) ?>" value="1">
                  <label class="form-check-label" for="f_<?= e($col['name']) ?>">Enabled</label>
                </div>

              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const COLS = <?= json_encode(array_map(fn($c) => ['name'=>$c['name'],'type'=>$c['type']], $cfg['cols'])) ?>;

function setForm(row) {
  const isEdit = row && row.id;
  document.getElementById('catModalTitle').textContent = isEdit
    ? 'Edit <?= addslashes($cfg['label']) ?>'
    : 'Add <?= addslashes($cfg['label']) ?>';
  document.getElementById('edit_id').value = row ? (row.id ?? row.<?= $cfg['pk'] ?> ?? 0) : 0;

  COLS.forEach(col => {
    const el = document.getElementById('f_' + col.name);
    if (!el) return;
    const val = row ? (row[col.name] ?? '') : '';
    if (col.type === 'bool') {
      el.checked = val == 1 || val === true;
    } else {
      el.value = val;
    }
  });
}
</script>

<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
