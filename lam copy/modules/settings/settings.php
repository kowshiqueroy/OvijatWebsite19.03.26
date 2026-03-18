<?php
// ============================================================
// modules/settings/settings.php
// ============================================================
requireRole(ROLE_ADMIN);

$action = $_POST['action'] ?? '';
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = [
        'shop_name'=>trim($_POST['shop_name']??APP_NAME),
        'shop_address'=>trim($_POST['shop_address']??''),
        'shop_phone'=>trim($_POST['shop_phone']??''),
        'shop_email'=>trim($_POST['shop_email']??''),
        'shop_logo_url'=>trim($_POST['shop_logo_url']??''),
        'shop_tax_no'=>trim($_POST['shop_tax_no']??''),
        'invoice_footer'=>trim($_POST['invoice_footer']??''),
        'currency_symbol'=>trim($_POST['currency_symbol']??'$'),
        'discount_enabled'=>isset($_POST['discount_enabled'])?'1':'0',
        'discount_type'=>in_array($_POST['discount_type']??'',['percent','amount','both'])?$_POST['discount_type']:'both',
        'discount_max_percent'=>(float)($_POST['discount_max_percent']??100),
        'discount_max_amount'=>(float)($_POST['discount_max_amount']??0),
        'discount_default'=>(float)($_POST['discount_default']??0),
        'product_discount_enabled'=>isset($_POST['product_discount_enabled'])?'1':'0',
        'vat_enabled'=>isset($_POST['vat_enabled'])?'1':'0',
        'vat_default'=>(float)($_POST['vat_default']??0),
        'vat_inclusive'=>isset($_POST['vat_inclusive'])?'1':'0',
        'points_enabled'=>isset($_POST['points_enabled'])?'1':'0',
        'points_earn_rate'=>(float)($_POST['points_earn_rate']??1),
        'points_redeem_rate'=>(float)($_POST['points_redeem_rate']??0.01),
        'points_min_redeem'=>(int)($_POST['points_min_redeem']??0),
        'points_max_redeem_pct'=>(float)($_POST['points_max_redeem_pct']??100),
       
        'sms_enabled'=>isset($_POST['sms_enabled'])?'1':'0',
        'sms_balance'=>(int)(isset($_POST['sms_balance']) ? $_POST['sms_balance'] : getSetting('sms_balance', 0)),
    ];

      // Only update API key if a new value is provided (don't overwrite with empty)
    if (!empty($_POST['api_key_sms'])) {
        $fields['api_key_sms'] = trim($_POST['api_key_sms']);
    } else {
        // If empty, keep existing value (don't change)
        $fields['api_key_sms'] = getSetting('api_key_sms', '');
    }
    foreach ($fields as $key => $value) {
        $existing = dbFetch('SELECT id FROM settings WHERE `key` = ?', [$key]);
        if ($existing) { dbUpdate('settings', ['value'=>$value], '`key` = ?', [$key]); }
        else { dbInsert('settings', ['key'=>$key,'value'=>$value]); }
    }
    logAction('UPDATE','settings',null,'Updated system settings to: ' . json_encode($fields));
    flash('success','Settings saved.');
    redirect('settings');
}

$S = getAllSettings();
$pageTitle = 'Settings';
require_once BASE_PATH . '/includes/header.php';
?>
<h1 style="margin-bottom:16px">⚙️ System Settings</h1>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<input type="hidden" name="action" value="save_settings">

<div class="card mb-2">
  <div class="card-title">🏪 Shop / Invoice Information</div>

  <div class="form-row cols-2">
    <div class="form-group">
      <label class="form-label">Shop Name</label>
      <input type="text" name="shop_name" class="form-control" value="<?= e($S['shop_name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Address</label>
      <input type="text" name="shop_address" class="form-control" value="<?= e($S['shop_address']) ?>">
    </div>
 
  </div>

  <div class="form-row cols-3">
    <div class="form-group">
      <label class="form-label">Phone</label>
      <input type="text" name="shop_phone" class="form-control" value="<?= e($S['shop_phone']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" name="shop_email" class="form-control" value="<?= e($S['shop_email']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Logo URL <small class="text-muted">(https://)</small></label>
      <input type="url" name="shop_logo_url" class="form-control" value="<?= e($S['shop_logo_url']) ?>" placeholder="https://…/logo.png">
      <?php if ($S['shop_logo_url']): ?>
        <img src="<?= e($S['shop_logo_url']) ?>" style="max-height:30px; margin-top:6px; border-radius:4px; display:block;">
      <?php endif ?>
    </div>
  </div>

  <div class="form-row cols-3">
    <div class="form-group">
      <label class="form-label">Invoice Footer</label>
      <input type="text" name="invoice_footer" class="form-control" value="<?= e($S['invoice_footer']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Currency Symbol (৳)</label>
      <input type="text" name="currency_symbol" class="form-control"  value="<?= e($S['currency_symbol']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Tax/VAT No.</label>
      <input type="text" name="shop_tax_no" class="form-control" value="<?= e($S['shop_tax_no']) ?>">
    </div>
  </div>

</div>

<div class="card mb-2">
  <div class="card-title">🏷️ Discount Settings</div>
  <div class="form-row cols-3">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px">
      <input type="checkbox" name="discount_enabled" value="1" <?= $S['discount_enabled']=='1'?'checked':'' ?>>
      <span class="form-label" style="margin:0">Enable Discounts in POS</span></label>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px">
      <input type="checkbox" name="product_discount_enabled" value="1" <?= $S['product_discount_enabled']=='1'?'checked':'' ?>>
      <span class="form-label" style="margin:0">Allow Per-product Discount</span></label>

      <div class="form-group"><label class="form-label">Input Type</label>
      <select name="discount_type" class="form-control">
        <option value="percent" <?= $S['discount_type']==='percent'?'selected':'' ?>>Percent % only</option>
        <option value="amount"  <?= $S['discount_type']==='amount' ?'selected':'' ?>>Amount $ only</option>
        <option value="both"    <?= $S['discount_type']==='both'   ?'selected':'' ?>>Both (user picks)</option>
      </select></div>
  </div>
  <div class="form-row cols-3">
    
    <div class="form-group"><label class="form-label">Max Discount %</label>
      <input type="number" name="discount_max_percent" class="form-control" step="0.01" min="0" max="100" value="<?= e($S['discount_max_percent']) ?>"></div>
    <div class="form-group"><label class="form-label">Max Discount Amount (0=unlimited)</label>
      <input type="number" name="discount_max_amount" class="form-control" step="0.01" min="0" value="<?= e($S['discount_max_amount']) ?>"></div>
       <div class="form-group" style="max-width:200px"><label class="form-label">Default Discount</label>
    <input type="number" name="discount_default" class="form-control" step="0.01" min="0" value="<?= e($S['discount_default']) ?>"></div>
  </div>
 
</div>

<div class="card mb-2">
  <div class="card-title">🧾 VAT / Tax</div>
  <div class="form-row cols-3">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px">
      <input type="checkbox" name="vat_enabled" value="1" <?= $S['vat_enabled']=='1'?'checked':'' ?>>
      <span class="form-label" style="margin:0">Enable VAT</span></label>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px">
      <input type="checkbox" name="vat_inclusive" value="1" <?= $S['vat_inclusive']=='1'?'checked':'' ?>>
      <span class="form-label" style="margin:0">Prices Include VAT</span></label>
    <div class="form-group" style="margin-bottom:0"><label class="form-label">Default VAT %</label>
      <input type="number" name="vat_default" class="form-control" step="0.01" min="0" max="100" value="<?= e($S['vat_default']) ?>"></div>
  </div>
</div>

<div class="card mb-2">
  <div class="card-title">⭐ Loyalty Points</div>
  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;margin-bottom:8px">
    <input type="checkbox" name="points_enabled" value="1" <?= $S['points_enabled']=='1'?'checked':'' ?>>
    <span class="form-label" style="margin:0">Enable Loyalty Points Program</span></label>
  <div class="form-row cols-2">
    <div class="form-group"><label class="form-label">Points per <?= e($S['currency_symbol']) ?>1 Spent</label>
      <input type="number" name="points_earn_rate" class="form-control" step="0.01" min="0" value="<?= e($S['points_earn_rate']) ?>"></div>
    <div class="form-group"><label class="form-label"><?= e($S['currency_symbol']) ?> Value per Point</label>
      <input type="number" name="points_redeem_rate" class="form-control" step="0.001" min="0" value="<?= e($S['points_redeem_rate']) ?>"></div>
    <div class="form-group"><label class="form-label">Min Points to Redeem</label>
      <input type="number" name="points_min_redeem" class="form-control" min="0" value="<?= e($S['points_min_redeem']) ?>"></div>
    <div class="form-group"><label class="form-label">Max Redeem % of Order</label>
      <input type="number" name="points_max_redeem_pct" class="form-control" step="1" min="0" max="100" value="<?= e($S['points_max_redeem_pct']) ?>"></div>
  </div>
</div>

<div class="card mb-2">
  <div class="card-title">📱 SMS</div>
  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;margin-bottom:8px">
    <input type="checkbox" name="sms_enabled" value="1" <?= $S['sms_enabled']=='1'?'checked':'' ?>>
    <span class="form-label" style="margin:0">Enable SMS Notifications</span></label>
  <div class="form-row cols-2">
    <div class="form-group"><label class="form-label">SMS API Key</label>
      <input type="text" name="api_key_sms" class="form-control" placeholder="<?= e(isset($S['api_key_sms']) && $S['api_key_sms'] !== '' ? 'Leave empty to not change' : 'Not configured'); ?>">
  </div>
  <div class="form-group"><label class="form-label">SMS Balance</label>
<input type="number" 
       name="sms_balance" 
       class="form-control" 
       min="0" 
       value="<?php echo isset($_GET['sms_balance']) && intval($_GET['sms_balance']) > 0 
                    ? e($_GET['sms_balance'])+e($S['sms_balance']) 
                    : e($S['sms_balance']); ?>" 
       readonly></div>

<button type="submit" class="btn btn-primary">💾 Save Settings</button>
</form>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
