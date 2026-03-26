<?php
require_once __DIR__ . '/../../includes/config/database.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die('Invalid access');
}

$quote = db()->selectOne("SELECT * FROM quotations WHERE share_token = ?", [$token]);

if (!$quote) {
    die('Quotation not found');
}

if (!$quote['viewed_at']) {
    $updateData = ['viewed_at' => date('Y-m-d H:i:s')];
    if ($quote['status'] === 'sent') {
        $updateData['status'] = 'viewed';
    }
    db()->update('quotations', $updateData, 'id = :id', ['id' => $quote['id']]);
    $quote = array_merge($quote, $updateData);
}

$items = db()->select("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order", [$quote['id']]);

$statusColors = [
    'draft'    => 'bg-gray-100 text-gray-700',
    'sent'     => 'bg-blue-100 text-blue-700',
    'viewed'   => 'bg-purple-100 text-purple-700',
    'accepted' => 'bg-green-100 text-green-700',
    'rejected' => 'bg-red-100 text-red-700',
    'expired'  => 'bg-yellow-100 text-yellow-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?= escape($quote['quote_number']) ?> | SohojWeb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: { '100': '#dbeafe', '600': '#2563eb' } } } } }</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-blue-600 px-8 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-white">QUOTATION</h1>
                        <p class="text-blue-100 text-lg"><?= escape($quote['quote_number']) ?></p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-white font-bold text-xl"><?= getSetting('company_name', 'SOHOJWEB') ?></h2>
                        <p class="text-blue-100 text-sm"><?= getSetting('company_email') ?></p>
                        <p class="text-blue-100 text-sm"><?= getSetting('company_phone') ?></p>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Prepared For</h3>
                        <p class="font-semibold text-gray-800 text-lg"><?= escape($quote['client_name']) ?></p>
                        <?php if ($quote['client_company']): ?>
                        <p class="text-gray-600"><?= escape($quote['client_company']) ?></p>
                        <?php endif; ?>
                        <?php if ($quote['client_address']): ?>
                        <p class="text-gray-600 whitespace-pre-line"><?= escape($quote['client_address']) ?></p>
                        <?php endif; ?>
                        <?php if ($quote['client_email']): ?>
                        <p class="text-gray-600"><?= escape($quote['client_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="mb-3">
                            <span class="text-gray-500">Quote Date:</span>
                            <span class="font-medium text-gray-800 ml-2"><?= date('F d, Y', strtotime($quote['quote_date'])) ?></span>
                        </div>
                        <?php if ($quote['valid_until']): ?>
                        <div class="mb-3">
                            <span class="text-gray-500">Valid Until:</span>
                            <span class="font-medium ml-2 <?= strtotime($quote['valid_until']) < time() ? 'text-red-600' : 'text-gray-800' ?>"><?= date('F d, Y', strtotime($quote['valid_until'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="px-3 py-1 text-sm font-medium rounded-full ml-2 <?= $statusColors[$quote['status']] ?? 'bg-gray-100' ?>"><?= ucfirst($quote['status']) ?></span>
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
                            <span>৳ <?= number_format($quote['subtotal'], 2) ?></span>
                        </div>
                        <?php if ($quote['discount_amount'] > 0): ?>
                        <div class="flex justify-between py-2 text-gray-600">
                            <span>Discount</span>
                            <span class="text-green-600">-৳ <?= number_format($quote['discount_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($quote['tax_amount'] > 0): ?>
                        <div class="flex justify-between py-2 text-gray-600">
                            <span>Tax (<?= $quote['tax_rate'] ?>%)</span>
                            <span>৳ <?= number_format($quote['tax_amount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between py-4 border-t-2 border-gray-200">
                            <span class="text-xl font-bold text-gray-800">Total</span>
                            <span class="text-xl font-bold text-blue-600">৳ <?= number_format($quote['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($quote['notes']): ?>
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Notes</h3>
                    <p class="text-gray-600"><?= nl2br(escape($quote['notes'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($quote['terms']): ?>
                <div class="mt-4 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Terms & Conditions</h3>
                    <p class="text-gray-500 text-sm"><?= nl2br(escape($quote['terms'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-6">
            <p class="text-gray-500 text-sm">Powered by <a href="<?= BASE_URL ?>" class="text-blue-600 font-semibold hover:underline">SohojWeb</a></p>
        </div>
    </div>
</body>
</html>
