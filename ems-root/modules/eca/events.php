<?php
define('EMS_ROOT', dirname(dirname(__DIR__)));
$page_title  = 'Events & Budgets';
$breadcrumbs = ['ECA & Events' => 'clubs.php', 'Events' => null];

require_once EMS_ROOT . '/core/auth.php';
require_once EMS_ROOT . '/core/functions.php';
require_once EMS_ROOT . '/core/rbac.php';
require_auth(['eca.view']);

$pdo        = db();
$session_id = int_param('session_id',(int)setting('current_session_id',0),$_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('eca.manage')) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_event') {
        $id    = int_param('id',0,$_POST);
        $sess  = int_param('session_id',$session_id,$_POST);
        $name  = trim($_POST['event_name']??'');
        $type  = $_POST['event_type']??'other';
        $club  = int_param('club_id',0,$_POST)?:null;
        $start = $_POST['start_date']??null;
        $end   = $_POST['end_date']??null;
        $budget= (float)($_POST['budget_allocated']??0);
        $desc  = trim($_POST['description']??'');
        $status= $_POST['status']??'planning';
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE events SET event_name=?,event_type=?,club_id=?,start_date=?,end_date=?,budget_allocated=?,description=?,status=? WHERE id=?')
                    ->execute([$name,$type,$club,$start,$end,$budget,$desc,$status,$id]);
                flash('success','Event updated.');
            } else {
                $pdo->prepare('INSERT INTO events (session_id,event_name,event_type,club_id,start_date,end_date,budget_allocated,description,status) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$sess,$name,$type,$club,$start,$end,$budget,$desc,$status]);
                flash('success',"Event '$name' created.");
            }
        }
    } elseif ($action === 'add_expense') {
        $event_id = int_param('event_id',0,$_POST);
        $desc     = trim($_POST['description']??'');
        $amount   = (float)($_POST['amount']??0);
        $date     = $_POST['expense_date']??date('Y-m-d');
        if ($event_id && $desc && $amount > 0) {
            $pdo->prepare('INSERT INTO event_expenses (event_id,description,amount,expense_date,approved_by) VALUES (?,?,?,?,?)')
                ->execute([$event_id,$desc,$amount,$date,current_user_id()]);
            flash('success','Expense added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM events WHERE id=?')->execute([int_param('id',0,$_POST)]);
        flash('success','Event deleted.');
    }
    header('Location: events.php?session_id='.$session_id.'&event='.($_POST['event_id']??''));
    exit;
}

$sessions = $pdo->query('SELECT id,session_name FROM academic_sessions ORDER BY id DESC')->fetchAll();
$events   = $pdo->prepare('SELECT e.*,c.club_name,COALESCE(SUM(ee.amount),0) as spent FROM events e LEFT JOIN clubs c ON c.id=e.club_id LEFT JOIN event_expenses ee ON ee.event_id=e.id WHERE e.session_id=:sess GROUP BY e.id ORDER BY e.start_date DESC, e.status');
$events->execute([':sess'=>$session_id]);
$events = $events->fetchAll();

$clubs = $pdo->query('SELECT id,club_name FROM clubs WHERE status=1 ORDER BY club_name')->fetchAll();

$activeEvent = int_param('event',0,$_GET);
$eventExpenses = [];
if ($activeEvent) {
    $eventExpenses = $pdo->query("SELECT ee.*,u.full_name as by_name FROM event_expenses ee LEFT JOIN users u ON u.id=ee.approved_by WHERE ee.event_id=$activeEvent ORDER BY ee.expense_date")->fetchAll();
}
$curEvent = null;
foreach($events as $ev) if($ev['id']==$activeEvent) { $curEvent=$ev; break; }

require_once EMS_ROOT . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="page-title mb-0"><i class="bi bi-calendar-event-fill me-2 text-primary"></i>Events & Budgets</h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" onchange="location='?session_id='+this.value" style="width:auto">
      <?php foreach($sessions as $s): ?><option value="<?= $s['id'] ?>" <?= $session_id==$s['id']?'selected':'' ?>><?= e($s['session_name']) ?></option><?php endforeach; ?>
    </select>
    <?php if(has_permission('eca.manage')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="setEventForm(null)"><i class="bi bi-plus-lg me-1"></i>New Event</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <!-- Events list -->
  <div class="col-md-5">
    <div class="row g-2">
      <?php if(empty($events)): ?>
        <div class="col-12"><div class="card"><div class="card-body text-center text-muted small py-3">No events this session</div></div></div>
      <?php else: foreach($events as $ev):
        $remaining = $ev['budget_allocated'] - $ev['spent'];
        $pct = $ev['budget_allocated'] > 0 ? min(100,round($ev['spent']/$ev['budget_allocated']*100)) : 0;
        $statusColor = ['planning'=>'draft','ongoing'=>'pending','completed'=>'active','cancelled'=>'rejected'][$ev['status']]??'draft';
      ?>
      <div class="col-12">
        <div class="card <?= $activeEvent==$ev['id']?'border-primary':'' ?> mb-0">
          <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <a href="?session_id=<?= $session_id ?>&event=<?= $ev['id'] ?>" class="fw-700 text-decoration-none"><?= e($ev['event_name']) ?></a>
              <span class="badge-status badge-<?= $statusColor ?> ms-1"><?= ucfirst(e($ev['status'])) ?></span>
            </div>
            <div class="small text-muted mb-1"><?= ucfirst(str_replace('_',' ',e($ev['event_type']))) ?><?= $ev['club_name']?' · '.e($ev['club_name']):'' ?></div>
            <?php if($ev['start_date']): ?><div class="small text-muted"><?= fmt_date($ev['start_date']) ?><?= $ev['end_date']?' – '.fmt_date($ev['end_date']):'' ?></div><?php endif; ?>
            <?php if($ev['budget_allocated']>0): ?>
            <div class="mt-2">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Spent: <?= money($ev['spent']) ?></span>
                <span class="<?= $remaining<0?'text-danger':'text-success' ?> fw-600">Left: <?= money($remaining) ?></span>
              </div>
              <div class="progress" style="height:4px"><div class="progress-bar bg-<?= $pct>90?'danger':($pct>70?'warning':'success') ?>" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endif; ?>
            <?php if(has_permission('eca.manage')): ?>
            <div class="mt-2 d-flex gap-1">
              <button class="btn btn-xs btn-outline-primary" style="font-size:.65rem;padding:.1rem .3rem;" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="setEventForm(<?= htmlspecialchars(json_encode($ev),ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ev['id'] ?>"><button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:.65rem;padding:.1rem .3rem;" data-confirm="Delete?"><i class="bi bi-trash"></i></button></form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Event expenses -->
  <div class="col-md-7">
    <?php if($curEvent): ?>
    <div class="card">
      <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
        <span class="card-title">Budget: <?= money($curEvent['budget_allocated']) ?> · Spent: <?= money($curEvent['spent']) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 small">
          <thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>By</th></tr></thead>
          <tbody>
            <?php if(empty($eventExpenses)): ?><tr><td colspan="4" class="text-center text-muted small py-2">No expenses logged yet</td></tr><?php endif; ?>
            <?php foreach($eventExpenses as $ee): ?>
            <tr>
              <td><?= fmt_date($ee['expense_date']) ?></td>
              <td class="fw-600"><?= e($ee['description']) ?></td>
              <td class="fw-700 text-danger"><?= money($ee['amount']) ?></td>
              <td><?= e($ee['by_name']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if(has_permission('eca.manage')): ?>
      <div class="card-footer p-3">
        <form method="POST" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_expense"><input type="hidden" name="event_id" value="<?= $activeEvent ?>">
          <div class="col-md-5"><input type="text" name="description" class="form-control form-control-sm" placeholder="Expense description" required></div>
          <div class="col-md-3"><input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" placeholder="Amount" required></div>
          <div class="col-md-3"><input type="date" name="expense_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-auto"><button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-plus-lg"></i></button></div>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-calendar-event"></i><p>Select an event to track its budget and expenses.</p></div></div></div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="eventModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="save_event"><input type="hidden" name="id" id="ev_id" value="0"><input type="hidden" name="session_id" value="<?= $session_id ?>">
        <div class="modal-header"><h5 class="modal-title" id="evModalTitle">New Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Event Name *</label><input type="text" name="event_name" id="ev_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Type</label><select name="event_type" id="ev_type" class="form-select"><?php foreach(['annual_sports'=>'Annual Sports','cultural'=>'Cultural Fest','study_tour'=>'Study Tour','debate'=>'Debate','seminar'=>'Seminar','other'=>'Other'] as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Organizing Club (optional)</label><select name="club_id" id="ev_club" class="form-select"><option value="">— None —</option><?php foreach($clubs as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['club_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Budget Allocated (৳)</label><input type="number" name="budget_allocated" id="ev_budget" class="form-control" step="0.01" value="0"></div>
            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" id="ev_start" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" id="ev_end" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="ev_status" class="form-select"><option value="planning">Planning</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="ev_desc" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Event</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function setEventForm(e) {
  document.getElementById('evModalTitle').textContent=e?'Edit Event':'New Event';
  document.getElementById('ev_id').value=e?e.id:0;
  document.getElementById('ev_name').value=e?e.event_name:'';
  document.getElementById('ev_type').value=e?e.event_type:'other';
  document.getElementById('ev_club').value=e?(e.club_id||''):'';
  document.getElementById('ev_budget').value=e?e.budget_allocated:0;
  document.getElementById('ev_start').value=e?(e.start_date||''):'';
  document.getElementById('ev_end').value=e?(e.end_date||''):'';
  document.getElementById('ev_status').value=e?e.status:'planning';
  document.getElementById('ev_desc').value=e?(e.description||''):'';
}
</script>
<?php require_once EMS_ROOT . '/includes/footer.php'; ?>
