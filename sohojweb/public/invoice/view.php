<?php
require_once __DIR__ . '/../../includes/config/database.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die('Invalid access');
}

$invoice = db()->selectOne("SELECT * FROM invoices WHERE share_token = ?", [$token]);

if (!$invoice) {
    die('Invoice not found');
}

if (!$invoice['viewed_at']) {
    $updateData = ['viewed_at' => date('Y-m-d H:i:s')];
    if ($invoice['status'] === 'sent') {
        $updateData['status'] = 'viewed';
    }
    db()->update('invoices', $updateData, 'id = :id', ['id' => $invoice['id']]);
    $invoice = array_merge($invoice, $updateData);
}

$items = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$invoice['id']]);

$statusColors = [
    'draft' => 'bg-gray-100 text-gray-700',
    'sent' => 'bg-blue-100 text-blue-700',
    'viewed' => 'bg-purple-100 text-purple-700',
    'paid' => 'bg-green-100 text-green-700',
    'overdue' => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-gray-100 text-gray-500'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= escape($invoice['invoice_number']) ?> | SohojWeb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-primary-600 px-8 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-white">INVOICE</h1>
                        <p class="text-primary-100 text-lg"><?= escape($invoice['invoice_number']) ?></p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-white font-bold text-xl"><?= getSetting('company_name', 'SOHOJWEB') ?></h2>
                        <p class="text-primary-100 text-sm"><?= getSetting('company_email') ?></p>
                        <p class="text-primary-100 text-sm"><?= getSetting('company_phone') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="p-8">
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Bill To</h3>
                        <p class="font-semibold text-gray-800 text-lg"><?= escape($invoice['client_name']) ?></p>
                        <?php if ($invoice['client_company']): ?>
                        <p class="text-gray-600"><?= escape($invoice['client_company']) ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['client_address']): ?>
                        <p class="text-gray-600 whitespace-pre-line"><?= escape($invoice['client_address']) ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['client_email']): ?>
                        <p class="text-gray-600"><?= escape($invoice['client_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="mb-3">
                            <span class="text-gray-500">Invoice Date:</span>
                            <span class="font-medium text-gray-800 ml-2"><?= date('F d, Y', strtotime($invoice['invoice_date'])) ?></span>
                        </div>
                        <?php if ($invoice['due_date']): ?>
                        <div class="mb-3">
                            <span class="text-gray-500">Due Date:</span>
                            <span class="font-medium text-gray-800 ml-2"><?= date('F d, Y', strtotime($invoice['due_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="px-3 py-1 text-sm font-medium rounded-full ml-2 <?= $statusColors[$invoice['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($invoice['status']) ?></span>
                        </div>
                    </div>
                </div>
                
                <table class="w-full mb-8">
                    <thead class="bg-gray-50 border-b-2 border-gray-200">
                        <tr>
                            <th class="text-left py-3 px-4 text-xs font-bold text-gray-600 uppercase">Description</th>
                            <th class="text-right py-3 px-4 text-xs font-bold text-gray-600 uppercase">Qty</th>
                            <th class="text-right py-3 px-4 text-xs font-bold text-gray-600 uppercase">Rate</th>
                            <th class="text-right py-3 px-4 text-xs font-bold text-gray-600 uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr class="border-b border-gray-100">
                            <td class="py-4 px-4">
                                <p class="font-medium text-gray-800"><?= escape($item['item_name']) ?></p>
                                <?php if ($item['item_description']): ?>
                                <p class="text-sm text-gray-500"><?= escape($item['item_description']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-right text-gray-600"><?= $item['quantity'] ?></td>
                            <td class="py-4 px-4 text-right text-gray-600">৳ <?= number_format($item['unit_price'], 2) ?></td>
                            <td class="py-4 px-4 text-right font-semibold text-gray-800">৳ <?= number_format($item['total_price'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="flex justify-end">
                    <div class="w-72">
                        <div class="flex justify-between py-2 text-gray-600">
                            <span>Subtotal</span>
                            <span>৳ <?= number_format($invoice['subtotal'], 2) ?></span>
                        </div>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="flex justify-between py-2 text-gray-600">
                            <span>Discount</span>
                            <span class="text-green-600">-৳ <?= number_format($invoice['discount_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <div class="flex justify-between py-2 text-gray-600">
                            <span>Tax (<?= $invoice['tax_rate'] ?>%)</span>
                            <span>৳ <?= number_format($invoice['tax_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between py-4 border-t-2 border-gray-200">
                            <span class="text-xl font-bold text-gray-800">Total</span>
                            <span class="text-xl font-bold text-primary-600">৳ <?= number_format($invoice['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($invoice['notes']): ?>
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Notes</h3>
                    <p class="text-gray-600"><?= nl2br(escape($invoice['notes'])) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($invoice['terms']): ?>
                <div class="mt-4 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Terms & Conditions</h3>
                    <p class="text-gray-500 text-sm"><?= nl2br(escape($invoice['terms'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-6">
            <p class="text-gray-500 text-sm">Powered by <a href="<?= BASE_URL ?>" class="text-primary-600 font-semibold hover:underline">SohojWeb</a></p>
        </div>
    </div>
</body>
</html>
