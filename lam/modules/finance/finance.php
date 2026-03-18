<?php
// ============================================================
// modules/finance/finance.php — Full Accounts Management
// Accounts: shop_cash, shop_bank, owner_cash, owner_bank
// Entry types: income, expense, transfer
// Party: shop, owner
// ============================================================
requireLogin();

// ── Account/category definitions ─────────────────────────────
const ACCOUNTS = [
    'shop_cash'  => '🏪 Shop Cash',
    'shop_bank'  => '🏦 Shop Bank',
    'owner_cash' => '👤 Owner Cash',
    'owner_bank' => '👤 Owner Bank',
];
const ENTRY_TYPES = [
    'income'   => ['label'=>'Income',   'badge'=>'badge-success'],
    'expense'  => ['label'=>'Expense',  'badge'=>'badge-danger'],
    'transfer' => ['label'=>'Transfer', 'badge'=>'badge-info'],
    'opening'  => ['label'=>'Opening',  'badge'=>'badge-warn'],
];
const EXPENSE_CATS   = ['Rent','Salary','Utilities','Purchase','Transport','Maintenance','Marketing','Miscellaneous'];
const INCOME_CATS    = ['Sale','Loan Received','Capital Injection','Refund','Miscellaneous'];
const TRANSFER_CATS  = ['Shop Cash → Owner','Owner → Shop Cash','Shop Cash → Shop Bank','Shop Bank → Shop Cash','Shop → Owner Bank','Owner Cash → Shop'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$cur    = getAllSettings()['currency_symbol'] ?? '$';

// ── Save entry ─────────────────────────────────────────────────
if ($action === 'save_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['entry_id'] ?? 0);
    $type = in_array($_POST['type']??'', array_keys(ENTRY_TYPES)) ? $_POST['type'] : 'expense';

    // For transfers: amount debits from_account, credits to_account
    $fromAcc = in_array($_POST['from_account']??'', array_keys(ACCOUNTS)) ? $_POST['from_account'] : 'shop_cash';
    $toAcc   = in_array($_POST['to_account']??'',   array_keys(ACCOUNTS)) ? $_POST['to_account']   : 'owner_cash';

    $data = [
        'type'        => $type,
        'category'    => trim($_POST['category']    ?? ''),
        'account'     => $type === 'transfer' ? $fromAcc : ($type === 'income' ? ($fromAcc) : $fromAcc),
        'to_account'  => $type === 'transfer' ? $toAcc   : null,
        'party'       => in_array($_POST['party']??'', ['shop','owner','customer','supplier']) ? $_POST['party'] : 'shop',
        'amount'      => (float)($_POST['amount']   ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'entry_date'  => $_POST['entry_date'] ?? today(),
        'user_id'     => currentUser()['id'],
    ];
    if ($id) {
        dbUpdate('finance_entries', $data, 'id = ?', [$id]);
        logAction('UPDATE','finance',$id,'Updated finance entry: ' . json_encode($data));
        flash('success','Entry updated.');
    } else {
        $data['created_at'] = now();
        $newId = dbInsert('finance_entries', $data);
        logAction('CREATE','finance',$newId,'Added finance entry: ' . json_encode($data));
        flash('success','Entry added.');
    }
    redirect('finance');
}

// ── Set opening balance ────────────────────────────────────────
if ($action === 'save_opening' && isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys(ACCOUNTS) as $acc) {
        $bal = (float)($_POST['opening_'.$acc] ?? 0);
        if ($bal == 0) continue;
        // Remove old opening for this account on this date
        $date = $_POST['opening_date'] ?? today();
        dbQuery("DELETE FROM finance_entries WHERE type='opening' AND account=? AND entry_date=?", [$acc, $date]);
        dbInsert('finance_entries', [
            'type'        => 'opening',
            'category'    => 'Opening Balance',
            'account'     => $acc,
            'to_account'  => null,
            'party'       => 'shop',
            'amount'      => $bal,
            'description' => 'Opening balance',
            'entry_date'  => $date,
            'user_id'     => currentUser()['id'],
            'created_at'  => now(),
        ]);
    }
    logAction('CREATE','finance',null,'Set opening balances');
    flash('success','Opening balances set.');
    redirect('finance');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)$_GET['id'];
    $entry = dbFetch('SELECT * FROM finance_entries WHERE id=? AND ref_sale_id IS NULL', [$id]);
    if ($entry) { dbDelete('finance_entries','id=?',[$id]); logAction('DELETE','finance',$id,'Deleted finance entry'); flash('success','Deleted.'); }
    redirect('finance');
}

// ── Filters ───────────────────────────────────────────────────
$from    = $_GET['from']    ?? date('Y-m-01');
$to      = $_GET['to']      ?? today();
$typeF   = $_GET['type_f']  ?? '';
$accF    = $_GET['acc_f']   ?? '';
$tab     = $_GET['tab']     ?? 'ledger';

$where  = 'fe.entry_date BETWEEN ? AND ?';
$params = [$from, $to];
if ($typeF) { $where .= ' AND fe.type=?';    $params[] = $typeF; }
if ($accF)  { $where .= ' AND (fe.account=? OR fe.to_account=?)'; $params[] = $accF; $params[] = $accF; }

$entries = dbFetchAll(
    "SELECT fe.*, u.full_name AS staff_name, s.invoice_no
     FROM finance_entries fe
     JOIN users u ON u.id = fe.user_id
     LEFT JOIN sales s ON s.id = fe.ref_sale_id
     WHERE $where
     ORDER BY fe.entry_date DESC, fe.id DESC",
    $params
);

// ── Account balances ──────────────────────────────────────────
function accountBalance(string $acc, string $upTo = ''): float {
    $cond   = $upTo ? "AND entry_date <= '$upTo'" : '';
    // Opening + income/transfer-in = credit; expense/transfer-out = debit
    $credit = (float)(dbFetch("SELECT COALESCE(SUM(amount),0) AS t FROM finance_entries
        WHERE (account=? AND type IN ('opening','income')) $cond", [$acc])['t'] ?? 0);
    $creditTransfer = (float)(dbFetch("SELECT COALESCE(SUM(amount),0) AS t FROM finance_entries
        WHERE to_account=? AND type='transfer' $cond", [$acc])['t'] ?? 0);
    $debit  = (float)(dbFetch("SELECT COALESCE(SUM(amount),0) AS t FROM finance_entries
        WHERE account=? AND type IN ('expense','transfer') $cond", [$acc])['t'] ?? 0);
    return $credit + $creditTransfer - $debit;
}
$balances = [];
foreach (array_keys(ACCOUNTS) as $acc) $balances[$acc] = accountBalance($acc, $to);

$totalShop  = ($balances['shop_cash']  ?? 0) + ($balances['shop_bank']  ?? 0);
$totalOwner = ($balances['owner_cash'] ?? 0) + ($balances['owner_bank'] ?? 0);
$totalAll   = $totalShop + $totalOwner;

// Period income/expense for summary
$periodStats = dbFetch(
    "SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense
     FROM finance_entries WHERE entry_date BETWEEN ? AND ?",
    [$from, $to]
);

$editing = !empty($_GET['edit']) ? dbFetch('SELECT * FROM finance_entries WHERE id=?',[(int)$_GET['edit']]) : null;

$pageTitle = 'Finance & Accounts';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>💰 Finance & Accounts</h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (isAdmin()): ?>
    <button class="btn btn-ghost btn-sm" onclick="openModal('openingModal')">📊 Opening Balance</button>
    <?php endif ?>
    <button class="btn btn-primary" onclick="openModal('entryModal')">+ Add Entry</button>
  </div>
</div>

<!-- Account Balance Cards -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px">
  <?php foreach (ACCOUNTS as $acc => $label): ?>
  <div class="stat-card <?= $balances[$acc] >= 0 ? 'accent' : 'danger' ?>">
    <div class="stat-label"><?= $label ?></div>
    <div class="stat-value" style="font-size:1.1rem"><?= $cur . number_format($balances[$acc],2) ?></div>
  </div>
  <?php endforeach ?>
</div>
<div class="stats-grid" style="margin-bottom:14px">
  <div class="stat-card success">
    <div class="stat-label">Total Shop</div>
    <div class="stat-value"><?= $cur . number_format($totalShop, 2) ?></div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">Total Owner</div>
    <div class="stat-value"><?= $cur . number_format($totalOwner, 2) ?></div>
  </div>
  <div class="stat-card <?= $totalAll>=0?'accent':'danger' ?>">
    <div class="stat-label">Net Total</div>
    <div class="stat-value"><?= $cur . number_format($totalAll, 2) ?></div>
  </div>
  <div class="stat-card success">
    <div class="stat-label">Period Income</div>
    <div class="stat-value"><?= $cur . number_format($periodStats['total_income']??0,2) ?></div>
  </div>
</div>

<!-- Filters -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
  <input type="hidden" name="page" value="finance">
  <input type="hidden" name="tab"  value="<?= e($tab) ?>">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:150px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:150px">
  <select name="type_f" class="form-control" style="max-width:130px">
    <option value="">All Types</option>
    <?php foreach (ENTRY_TYPES as $tv => $td): ?>
    <option value="<?= $tv ?>" <?= $typeF===$tv?'selected':'' ?>><?= $td['label'] ?></option>
    <?php endforeach ?>
  </select>
  <select name="acc_f" class="form-control" style="max-width:160px">
    <option value="">All Accounts</option>
    <?php foreach (ACCOUNTS as $av => $al): ?>
    <option value="<?= $av ?>" <?= $accF===$av?'selected':'' ?>><?= $al ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="btn btn-ghost">Filter</button>
  <a href="index.php?page=finance" class="btn btn-ghost">Reset</a>
</form>

<!-- Ledger Table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th><th>Type</th><th>Category</th><th>Account</th>
          <th>Description</th><th>Staff</th>
          <th class="text-right">Debit</th><th class="text-right">Credit</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
          <tr><td colspan="9" class="text-muted text-center" style="padding:24px">No entries for this period.</td></tr>
        <?php endif ?>
        <?php foreach ($entries as $en):
            $isCredit = in_array($en['type'], ['income','opening']);
            $isDebit  = $en['type'] === 'expense';
            $isXfer   = $en['type'] === 'transfer';
            $td = ENTRY_TYPES[$en['type']] ?? ['label'=>$en['type'],'badge'=>'badge-grey'];
        ?>
        <tr>
          <td style="white-space:nowrap"><?= fmtDate($en['entry_date']) ?></td>
          <td><span class="badge <?= $td['badge'] ?>"><?= $td['label'] ?></span></td>
          <td><?= e($en['category']) ?></td>
          <td style="font-size:.8rem">
            <?= ACCOUNTS[$en['account']] ?? e($en['account']) ?>
            <?php if ($en['to_account']): ?>
              <span style="color:var(--muted)">→</span>
              <?= ACCOUNTS[$en['to_account']] ?? e($en['to_account']) ?>
            <?php endif ?>
          </td>
          <td style="font-size:.83rem">
            <?= e($en['description']) ?>
            <?php if ($en['invoice_no']): ?>
              <br><a href="index.php?page=invoice&id=<?= $en['ref_sale_id'] ?>" style="font-size:.75rem"><?= e($en['invoice_no']) ?></a>
            <?php endif ?>
          </td>
          <td style="font-size:.8rem"><?= e($en['staff_name']) ?></td>
          <td class="text-right" style="color:var(--danger);font-weight:700">
            <?= $isDebit || $isXfer ? $cur . number_format($en['amount'],2) : '' ?>
          </td>
          <td class="text-right" style="color:var(--success);font-weight:700">
            <?= $isCredit ? $cur . number_format($en['amount'],2) : '' ?>
          </td>
          <td>
            <?php if (!$en['ref_sale_id']): ?>
            <a href="index.php?page=finance&edit=<?= $en['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
            <?php if (canDelete()): ?>
            <a href="index.php?page=finance&action=delete&id=<?= $en['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this entry?">🗑️</a>
            <?php endif ?>
            <?php else: ?>
            <span class="text-muted" style="font-size:.73rem">auto</span>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Add/Edit Entry Modal ───────────────────────────────── -->
<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="entryModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Entry' : 'New Finance Entry' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="entryForm">
        <input type="hidden" name="action"   value="save_entry">
        <input type="hidden" name="entry_id" value="<?= $editing['id'] ?? '' ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Entry Type *</label>
            <select name="type" class="form-control" id="entryType" onchange="updateEntryForm()" required>
              <?php foreach (ENTRY_TYPES as $tv => $td): ?>
              <option value="<?= $tv ?>" <?= ($editing['type']??'expense')===$tv?'selected':'' ?>><?= $td['label'] ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount *</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0" required value="<?= $editing['amount'] ?? '' ?>">
          </div>
        </div>

        <!-- Account row changes based on type -->
        <div class="form-row cols-2" id="accountRow">
          <div class="form-group" id="fromAccountGroup">
            <label class="form-label" id="fromAccountLabel">From Account</label>
            <select name="from_account" class="form-control">
              <?php foreach (ACCOUNTS as $av => $al): ?>
              <option value="<?= $av ?>" <?= ($editing['account']??'')===$av?'selected':'' ?>><?= $al ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group" id="toAccountGroup" style="display:none">
            <label class="form-label">To Account</label>
            <select name="to_account" class="form-control">
              <?php foreach (ACCOUNTS as $av => $al): ?>
              <option value="<?= $av ?>" <?= ($editing['to_account']??'')===$av?'selected':'' ?>><?= $al ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>

        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" id="categoryField"
                   value="<?= e($editing['category']??'') ?>" list="categoryList" placeholder="Type or pick…">
            <datalist id="categoryList">
              <?php foreach (EXPENSE_CATS as $c): ?><option value="<?= $c ?>"><?php endforeach ?>
            </datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Party / Source</label>
            <select name="party" class="form-control">
              <option value="shop"     <?= ($editing['party']??'shop')==='shop'    ?'selected':'' ?>>Shop</option>
              <option value="owner"    <?= ($editing['party']??'')==='owner'   ?'selected':'' ?>>Owner</option>
              <option value="customer" <?= ($editing['party']??'')==='customer'?'selected':'' ?>>Customer</option>
              <option value="supplier" <?= ($editing['party']??'')==='supplier'?'selected':'' ?>>Supplier</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="entry_date" class="form-control" required value="<?= $editing['entry_date'] ?? today() ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Description / Notes</label>
          <textarea name="description" class="form-control"><?= e($editing['description']??'') ?></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="entryForm" class="btn btn-primary">Save Entry</button>
    </div>
  </div>
</div>

<!-- ── Opening Balance Modal ─────────────────────────────── -->
<?php if (isAdmin()): ?>
<div class="modal-backdrop" id="openingModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <span class="modal-title">📊 Set Opening Balances</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="openingForm">
        <input type="hidden" name="action" value="save_opening">
        <div class="form-group">
          <label class="form-label">As of Date</label>
          <input type="date" name="opening_date" class="form-control" value="<?= today() ?>">
        </div>
        <?php foreach (ACCOUNTS as $acc => $label): ?>
        <div class="form-group">
          <label class="form-label"><?= $label ?></label>
          <input type="number" name="opening_<?= $acc ?>" class="form-control" step="0.01" value="0" min="0">
        </div>
        <?php endforeach ?>
        <p class="text-muted" style="font-size:.8rem">⚠️ This will overwrite any existing opening entry for the chosen date.</p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="openingForm" class="btn btn-primary">Set Balances</button>
    </div>
  </div>
</div>
<?php endif ?>

<script>
const EXPENSE_CATS  = <?= json_encode(EXPENSE_CATS) ?>;
const INCOME_CATS   = <?= json_encode(INCOME_CATS) ?>;
const TRANSFER_CATS = <?= json_encode(TRANSFER_CATS) ?>;

function updateEntryForm() {
  const type     = document.getElementById('entryType').value;
  const fromLbl  = document.getElementById('fromAccountLabel');
  const toGrp    = document.getElementById('toAccountGroup');
  const catList  = document.getElementById('categoryList');

  toGrp.style.display = type === 'transfer' ? '' : 'none';

  const cats = type === 'income' ? INCOME_CATS : type === 'transfer' ? TRANSFER_CATS : EXPENSE_CATS;
  catList.innerHTML = cats.map(c => `<option value="${c}">`).join('');

  if (type === 'transfer') {
    fromLbl.textContent = 'From Account';
  } else if (type === 'income' || type === 'opening') {
    fromLbl.textContent = 'Into Account';
  } else {
    fromLbl.textContent = 'From Account';
  }
}
updateEntryForm();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
