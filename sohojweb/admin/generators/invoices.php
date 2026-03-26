<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$canEdit   = hasPermission('sales');
$canDelete = hasPermission('super_admin');

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function generateInvoiceNumber($prefix = 'INV') {
    $row = db()->selectOne("SELECT invoice_number FROM invoices WHERE invoice_prefix = ? ORDER BY id DESC LIMIT 1", [$prefix]);
    if ($row && preg_match('/(\d+)$/', $row['invoice_number'], $matches)) {
        $nextNum = (int)$matches[1] + 1;
    } else {
        $nextNum = 1;
    }
    return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

if ($action === 'getNextNumber') {
    header('Content-Type: application/json');
    $prefix = getSetting('invoice_prefix', 'INV');
    $nextNumber = generateInvoiceNumber($prefix);
    jsonResponse(['prefix' => $prefix, 'nextNumber' => $nextNumber]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'create')) {
    header('Content-Type: application/json');
    if (!hasPermission('sales')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $data = [
            'invoice_prefix' => sanitize($_POST['invoice_prefix'] ?? 'INV'),
            'invoice_number' => sanitize($_POST['invoice_number'] ?? generateInvoiceNumber($_POST['invoice_prefix'] ?? 'INV')),
            'invoice_date' => sanitize($_POST['invoice_date']),
            'due_date' => sanitize($_POST['due_date']) ?: null,
            'client_name' => sanitize($_POST['client_name']),
            'client_email' => sanitize($_POST['client_email']) ?: null,
            'client_address' => sanitize($_POST['client_address']) ?: null,
            'client_phone' => sanitize($_POST['client_phone']) ?: null,
            'client_company' => sanitize($_POST['client_company']) ?: null,
            'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
            'discount_type' => sanitize($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => (float)($_POST['discount_value'] ?? 0),
            'notes' => sanitize($_POST['notes']) ?: null,
            'terms' => sanitize($_POST['terms']) ?: getSetting('invoice_terms', 'Payment due within 30 days.'),
            'status' => sanitize($_POST['status'] ?? 'draft'),
            'created_by' => $_SESSION['user_id'] ?? null,
            'share_token' => generateToken(32)
        ];
        
        $items = json_decode($_POST['items'] ?? '[]', true) ?: [];
        
        $subtotal = 0;
        foreach ($items as &$item) {
            $item['total_price'] = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            $subtotal += $item['total_price'];
        }
        
        $discountAmount = $data['discount_type'] === 'percentage' ? ($subtotal * $data['discount_value'] / 100) : $data['discount_value'];
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = $afterDiscount * $data['tax_rate'] / 100;
        
        $data['subtotal'] = $subtotal;
        $data['discount_amount'] = $discountAmount;
        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = $afterDiscount + $taxAmount;
        
        if ($id) {
            db()->update('invoices', $data, 'id = :id', ['id' => $id]);
            db()->delete('invoice_items', 'invoice_id = :id', ['id' => $id]);
            $invoiceId = $id;
        } else {
            $invoiceId = db()->insert('invoices', $data);
        }
        
        foreach ($items as $idx => $item) {
            db()->insert('invoice_items', [
                'invoice_id' => $invoiceId,
                'item_name' => sanitize($item['item_name'] ?? 'Item'),
                'item_description' => sanitize($item['item_description'] ?? null),
                'quantity' => (float)($item['quantity'] ?? 1),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'total_price' => (float)(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0)),
                'sort_order' => $idx
            ]);
        }
        
        logAudit($id ? 'invoice_updated' : 'invoice_created', 'invoice', $invoiceId, null, ['invoice_number' => $data['invoice_number'], 'client' => $data['client_name'], 'total' => $data['total_amount']]);
        jsonResponse(['success' => true, 'message' => 'Invoice saved', 'id' => $invoiceId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'duplicate') {
    ob_clean();
    header('Content-Type: application/json');
    if (!hasPermission('sales')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $sourceId = (int)$_POST['id'];
        $source = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$sourceId]);
        if (!$source) throw new Exception('Source invoice not found');
        
        $items = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ?", [$sourceId]);
        
        $newData = $source;
        unset($newData['id'], $newData['created_at'], $newData['updated_at']);
        $newData['invoice_number'] = generateInvoiceNumber($source['invoice_prefix']);
        $newData['invoice_date'] = date('Y-m-d');
        $newData['status'] = 'draft';
        $newData['share_token'] = generateToken(32);
        $newData['viewed_at'] = null;
        
        $newId = db()->insert('invoices', $newData);
        
        foreach ($items as $item) {
            unset($item['id'], $item['invoice_id']);
            $item['invoice_id'] = $newId;
            db()->insert('invoice_items', $item);
        }
        
        jsonResponse(['success' => true, 'message' => 'Invoice duplicated', 'id' => $newId]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    header('Content-Type: application/json');
    if (!hasPermission('super_admin')) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    try {
        $delId = (int)$_POST['id'];
        $inv = db()->selectOne("SELECT status FROM invoices WHERE id = ?", [$delId]);
        if ($inv && $inv['status'] === 'paid') {
            jsonResponse(['success' => false, 'message' => 'Paid invoices cannot be deleted.'], 403);
        }
        db()->delete('invoice_items', 'invoice_id = :id', ['id' => $delId]);
        db()->delete('invoices', 'id = :id', ['id' => $delId]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($action === 'view' && $id) {
    $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$id]);
    $items = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$id]);
}

if ($action === 'edit' && $id) {
    $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$id]);
    $items = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$id]);
}

$invoice = $invoice ?? [];
$items = $items ?? [];

$invoices = db()->select("SELECT * FROM invoices ORDER BY created_at DESC");

$pageTitle = 'Invoices | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>
    <?php if ($action === 'view' && $id): ?>
    <div class="max-w-4xl mx-auto">
        <a href="invoices.php" class="inline-flex items-center text-gray-600 hover:text-gray-800 mb-4">
            <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
        </a>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="flex justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold">INVOICE</h1>
                    <p class="text-primary-600 text-xl"><?= escape($invoice['invoice_number'] ?? '') ?></p>
                    <span class="px-2 py-1 text-xs rounded-full <?= ($invoice['status'] ?? 'draft') === 'paid' ? 'bg-green-100 text-green-700' : (($invoice['status'] ?? 'draft') === 'sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                        <?= ucfirst($invoice['status'] ?? 'draft') ?>
                    </span>
                </div>
                <div class="text-right">
                    <h2 class="font-bold text-xl"><?= getSetting('company_name', 'SOHOJWEB') ?></h2>
                    <p class="text-gray-600"><?= getSetting('company_email') ?></p>
                    <p class="text-gray-600"><?= getSetting('company_phone') ?></p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="font-semibold text-gray-500 text-sm mb-2">BILL TO</h3>
                    <p class="font-bold"><?= escape($invoice['client_name'] ?? '') ?></p>
                    <p class="text-gray-600"><?= escape($invoice['client_company'] ?? '') ?></p>
                    <p class="text-gray-600"><?= escape($invoice['client_email'] ?? '') ?></p>
                    <p class="text-gray-600"><?= escape($invoice['client_phone'] ?? '') ?></p>
                </div>
                <div class="text-right">
                    <p class="mb-1"><span class="text-gray-500">Date:</span> <?= date('M d, Y', strtotime($invoice['invoice_date'] ?? date('Y-m-d'))) ?></p>
                    <?php if(!empty($invoice['due_date'])): ?>
                    <p><span class="text-gray-500">Due:</span> <?= date('M d, Y', strtotime($invoice['due_date'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <table class="w-full mb-8">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="text-left py-2">Item</th>
                        <th class="text-center py-2">Qty</th>
                        <th class="text-right py-2">Price</th>
                        <th class="text-right py-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= escape($item['item_name']) ?></td>
                        <td class="text-center py-2"><?= $item['quantity'] ?></td>
                        <td class="text-right py-2">৳ <?= number_format($item['unit_price']) ?></td>
                        <td class="text-right py-2">৳ <?= number_format($item['total_price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between py-1"><span class="text-gray-600">Subtotal</span><span>৳ <?= number_format($invoice['subtotal'] ?? 0) ?></span></div>
                    <?php if(($invoice['discount_amount'] ?? 0) > 0): ?>
                    <div class="flex justify-between py-1"><span class="text-gray-600">Discount</span><span class="text-green-600">-৳ <?= number_format($invoice['discount_amount'] ?? 0) ?></span></div>
                    <?php endif; ?>
                    <?php if(($invoice['tax_amount'] ?? 0) > 0): ?>
                    <div class="flex justify-between py-1"><span class="text-gray-600">Tax (<?= $invoice['tax_rate'] ?? 0 ?>%)</span><span>৳ <?= number_format($invoice['tax_amount'] ?? 0) ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between py-2 border-t font-bold text-xl"><span>Total</span><span class="text-primary-600">৳ <?= number_format($invoice['total_amount'] ?? 0) ?></span></div>
                </div>
            </div>
            
            <?php if(!empty($invoice['notes'])): ?>
            <div class="mt-8 pt-4 border-t">
                <h4 class="font-semibold text-gray-500 text-sm">Notes</h4>
                <p class="text-gray-600"><?= nl2br(escape($invoice['notes'] ?? '')) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 flex gap-2">
                <?php 
                $printData = [
                    'type' => 'invoice',
                    'doc_id' => $invoice['invoice_number'] ?? '',
                    'doc_date' => $invoice['invoice_date'] ?? date('Y-m-d'),
                    'client_name' => $invoice['client_name'] ?? '',
                    'client_designation' => '',
                    'client_phone' => $invoice['client_phone'] ?? '',
                    'client_address' => $invoice['client_address'] ?? '',
                    'items' => array_map(fn($i) => ['desc' => $i['item_name'], 'qty' => $i['quantity'], 'price' => $i['unit_price']], $items),
                    'subtotal' => $invoice['subtotal'] ?? 0,
                    'discount' => $invoice['discount_amount'] ?? 0,
                    'total' => $invoice['total_amount'] ?? 0,
                    'opts' => ['sign' => true, 'note' => true]
                ];
                ?>
                <a href="/sohojweb/print/?type=invoice&id=<?= $invoice['id'] ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-print mr-2"></i> Print
                </a>
                <?php if ($invoice['status'] !== 'paid' && $canEdit): ?>
                <a href="?action=edit&id=<?= $invoice['id'] ?>" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Invoices</h1>
        <?php if ($canEdit): ?>
        <button onclick="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
            <i class="fas fa-plus mr-2"></i> New Invoice
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Search -->
    <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search invoices..." class="w-full md:w-1/3 px-4 py-2 border border-gray-300 rounded-lg" onkeyup="searchInvoices()">
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full" id="invoicesTable">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Invoice</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Client</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Items</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Date</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Amount</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Status</th>
                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): 
                $invItems = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ?", [$inv['id']]);
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4"><a href="?action=view&id=<?= $inv['id'] ?>" class="font-medium text-primary-600 hover:underline"><?= escape($inv['invoice_number']) ?></a></td>
                    <td class="py-3 px-4 text-gray-600"><?= escape($inv['client_name']) ?></td>
                    <td class="py-3 px-4">
                        <?php if (!empty($invItems)): ?>
                        <div class="text-xs space-y-1">
                            <?php foreach (array_slice($invItems, 0, 3) as $item): ?>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-600 truncate max-w-[150px]"><?= escape($item['item_name']) ?></span>
                                <span class="text-gray-500">x<?= $item['quantity'] ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($invItems) > 3): ?>
                            <div class="text-xs text-gray-400">+<?= count($invItems) - 3 ?> more items</div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">No items</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></td>
                    <td class="py-3 px-4 font-medium">৳ <?= number_format($inv['total_amount']) ?></td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-1 text-xs rounded-full <?= $inv['status'] === 'paid' ? 'bg-green-100 text-green-700' : ($inv['status'] === 'sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                            <?= ucfirst($inv['status']) ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 flex gap-1">
                        <a href="/sohojweb/print/?type=invoice&id=<?= $inv['id'] ?>" target="_blank" class="p-2 text-green-600 hover:bg-green-50 rounded" title="Print"><i class="fas fa-print"></i></a>
                        <?php if ($inv['status'] !== 'paid' && $canEdit): ?>
                        <a href="?action=edit&id=<?= $inv['id'] ?>" class="p-2 text-orange-600 hover:bg-orange-50 rounded" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                        <button onclick="duplicateInvoice(<?= $inv['id'] ?>)" class="p-2 text-purple-600 hover:bg-purple-50 rounded" title="Duplicate"><i class="fas fa-copy"></i></button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <button onclick="deleteInvoice(<?= $inv['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </main>

    <!-- Modal Form -->
    <div id="invoiceModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800"><?= $id ? 'Edit Invoice' : 'Create Invoice' ?></h2>
                <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <form id="invoiceForm" class="p-6">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="items" id="itemsJson">
                
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?= getSetting('invoice_prefix', 'INV') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Invoice Number</label>
                        <input type="text" name="invoice_number" value="<?= !empty($id) ? escape($invoice['invoice_number'] ?? '') : generateInvoiceNumber() ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border rounded-lg">
                            <option value="draft" <?= ($invoice['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="sent" <?= ($invoice['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="paid" <?= ($invoice['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Invoice Date *</label>
                        <input type="date" name="invoice_date" value="<?= !empty($id) ? ($invoice['invoice_date'] ?? date('Y-m-d')) : date('Y-m-d') ?>" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Due Date</label>
                        <input type="date" name="due_date" value="<?= !empty($id) ? ($invoice['due_date'] ?? '') : '' ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <h3 class="text-lg font-semibold mb-4">Client Information</h3>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Client Name *</label>
                        <input type="text" name="client_name" value="<?= escape($invoice['client_name'] ?? '') ?>" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Company</label>
                        <input type="text" name="client_company" value="<?= escape($invoice['client_company'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input type="email" name="client_email" value="<?= escape($invoice['client_email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone</label>
                        <input type="text" name="client_phone" value="<?= escape($invoice['client_phone'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium mb-1">Address</label>
                        <textarea name="client_address" rows="2" class="w-full px-3 py-2 border rounded-lg"><?= escape($invoice['client_address'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <h3 class="text-lg font-semibold mb-4">Items</h3>
                <div id="itemsContainer" class="space-y-3 mb-4">
                    <?php if ($id && !empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <div class="item-row flex gap-2 p-3 bg-gray-50 rounded-lg">
                        <input type="text" name="item_name" value="<?= escape($item['item_name']) ?>" placeholder="Item name" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="w-20 px-3 py-2 border rounded-lg text-sm">
                        <input type="number" name="unit_price" value="<?= $item['unit_price'] ?>" min="0" class="w-32 px-3 py-2 border rounded-lg text-sm">
                        <button type="button" onclick="this.closest('.item-row').remove()" class="p-2 text-red-500"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="item-row flex gap-2 p-3 bg-gray-50 rounded-lg">
                        <input type="text" name="item_name" placeholder="Item name" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        <input type="number" name="quantity" value="1" min="1" class="w-20 px-3 py-2 border rounded-lg text-sm">
                        <input type="number" name="unit_price" value="0" min="0" class="w-32 px-3 py-2 border rounded-lg text-sm">
                        <button type="button" onclick="this.closest('.item-row').remove()" class="p-2 text-red-500"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addItem()" class="mb-6 px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-primary-500 hover:text-primary-600">
                    <i class="fas fa-plus mr-2"></i> Add Item
                </button>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" value="<?= $id ? $invoice['tax_rate'] : getSetting('tax_rate', 0) ?>" min="0" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Discount</label>
                        <div class="flex gap-2">
                            <select name="discount_type" class="px-3 py-2 border rounded-lg text-sm">
                                <option value="percentage" <?= ($invoice['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>%</option>
                                <option value="fixed" <?= ($invoice['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                            </select>
                            <input type="number" name="discount_value" value="<?= $invoice['discount_value'] ?? 0 ?>" min="0" class="flex-1 px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Notes</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= escape($invoice['notes'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Terms & Conditions</label>
                        <textarea name="terms" rows="3" class="w-full px-3 py-2 border rounded-lg"><?= escape($invoice['terms'] ?? getSetting('invoice_terms', 'Payment due within 30 days.')) ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-6 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Save Invoice</button>
                </div>
            </form>
        </div>
    </div>

<script>
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

async function openModal(id = null) {
    document.getElementById('invoiceModal').classList.remove('hidden');
    document.getElementById('invoiceModal').classList.add('flex');
    if (!id) {
        document.getElementById('invoiceForm').reset();
        const res = await fetch('invoices.php?action=getNextNumber');
        const data = await res.json();
        document.querySelector('input[name="invoice_number"]').value = data.nextNumber;
        document.querySelector('input[name="invoice_prefix"]').value = data.prefix;
    }
}
function closeModal() { document.getElementById('invoiceModal').classList.add('hidden'); document.getElementById('invoiceModal').classList.remove('flex'); }

function addItem() {
    const container = document.getElementById('itemsContainer');
    const html = `<div class="item-row flex gap-2 p-3 bg-gray-50 rounded-lg">
        <input type="text" name="item_name" placeholder="Item name" class="flex-1 px-3 py-2 border rounded-lg text-sm">
        <input type="number" name="quantity" value="1" min="1" class="w-20 px-3 py-2 border rounded-lg text-sm">
        <input type="number" name="unit_price" value="0" min="0" class="w-32 px-3 py-2 border rounded-lg text-sm">
        <button type="button" onclick="this.closest('.item-row').remove()" class="p-2 text-red-500"><i class="fas fa-trash"></i></button>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        items.push({
            item_name: row.querySelector('[name="item_name"]').value,
            quantity: row.querySelector('[name="quantity"]').value,
            unit_price: row.querySelector('[name="unit_price"]').value
        });
    });
    document.getElementById('itemsJson').value = JSON.stringify(items);
    fetch('invoices.php?action=save<?= $id ? '&id=' . $id : '' ?>', {
        method: 'POST',
        body: new FormData(this)
    }).then(res => res.json()).then(data => {
        if(data.success) {
            closeModal();
            location.reload();
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }).catch(err => {
        alert('An error occurred');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
});

function deleteInvoice(id) {
    if (!CAN_DELETE) { alert('Permission denied'); return; }
    if(confirm('Delete this invoice?')) {
        fetch('invoices.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id='+id
        }).then(() => location.reload());
    }
}

function duplicateInvoice(id) {
    if (!CAN_EDIT) { alert('Permission denied'); return; }
    if(confirm('Duplicate this invoice?')) {
        const formData = new URLSearchParams();
        formData.append('id', id);
        fetch('invoices.php?action=duplicate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else alert(data.message);
        });
    }
}

function searchInvoices() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#invoicesTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}

<?php if ($action === 'edit' && $id): ?>
openModal();
<?php endif; ?>
</script>
<?php include __DIR__ . '/../footer.php'; ?>