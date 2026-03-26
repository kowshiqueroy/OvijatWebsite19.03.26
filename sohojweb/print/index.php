<?php
require_once __DIR__ . '/../includes/config/database.php';

$companyName = getSetting('company_name', 'SohojWeb');
$companyEmail = getSetting('company_email', 'sohojweb.com@gmail.com');
$companyPhone = getSetting('company_phone', '01632950179');
$companyAddress = getSetting('company_address', '');
$companyLogo = getSetting('company_logo', '');
$companyLogoLarge = getSetting('company_logo_large', '');

$baseUrl = BASE_URL;
$companyLogo = str_replace('http://localhost/sohojweb', $baseUrl, $companyLogo);
$companyLogoLarge = str_replace('http://localhost/sohojweb', $baseUrl, $companyLogoLarge);

$type = $_GET['type'] ?? 'invoice';
$idParam = $_GET['id'] ?? '';

$docData = [];
$id = (int)$idParam;

if ($id > 0) {
    if ($type === 'invoice') {
        $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$id]);
        if ($invoice) {
            $items = db()->select("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$id]);
            $docData = [
                'type' => 'invoice',
                'doc_id' => $invoice['invoice_number'],
                'doc_date' => $invoice['invoice_date'],
                'due_date' => $invoice['due_date'],
                'client_name' => $invoice['client_name'],
                'client_email' => $invoice['client_email'],
                'client_phone' => $invoice['client_phone'],
                'client_company' => $invoice['client_company'] ?? '',
                'client_address' => $invoice['client_address'] ?? '',
                'items' => array_map(fn($i) => ['desc' => $i['item_name'], 'qty' => $i['quantity'], 'price' => $i['unit_price']], $items),
                'subtotal' => $invoice['subtotal'] ?? 0,
                'discount' => $invoice['discount_amount'] ?? 0,
                'total' => $invoice['total_amount'] ?? 0,
                'tax_amount' => $invoice['tax_amount'] ?? 0,
                'opts' => ['sign' => true, 'note' => true]
            ];
        }
    } elseif ($type === 'quotation') {
        $quote = db()->selectOne("SELECT * FROM quotations WHERE id = ?", [$id]);
        if ($quote) {
            $items = db()->select("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order", [$id]);
            $docData = [
                'type' => 'quotation',
                'doc_id' => $quote['quote_number'],
                'doc_date' => $quote['quote_date'],
                'due_date' => $quote['valid_until'],
                'client_name' => $quote['client_name'],
                'client_email' => $quote['client_email'],
                'client_phone' => $quote['client_phone'],
                'client_company' => $quote['client_company'] ?? '',
                'client_address' => $quote['client_address'] ?? '',
                'items' => array_map(fn($i) => ['desc' => $i['item_name'], 'qty' => $i['quantity'], 'price' => $i['unit_price']], $items),
                'subtotal' => $quote['subtotal'] ?? 0,
                'discount' => $quote['discount_amount'] ?? 0,
                'total' => $quote['total_amount'] ?? 0,
                'tax_amount' => $quote['tax_amount'] ?? 0,
                'opts' => ['sign' => true, 'note' => true]
            ];
        }
    } elseif ($type === 'job_offer') {
        $offer = db()->selectOne("SELECT * FROM job_offer_letters WHERE id = ?", [$id]);
        if ($offer) {
            $docData = [
                'type' => 'job_offer',
                'doc_id' => $offer['offer_number'] ?? 'OFFER-' . $id,
                'doc_date' => date('Y-m-d', strtotime($offer['created_at'])),
                'client_name' => $offer['candidate_name'],
                'client_email' => $offer['candidate_email'],
                'client_phone' => $offer['candidate_phone'],
                'client_company' => $offer['position'],
                'client_designation' => $offer['department'] ?? '',
                'subject' => 'Job Offer Letter - ' . $offer['position'],
                'body' => $offer['offer_content'] ?? "Dear " . $offer['candidate_name'] . ",\n\nWe are pleased to offer you the position of " . $offer['position'] . " at " . $companyName . ".\n\nPosition: " . $offer['position'] . "\nDepartment: " . ($offer['department'] ?? 'N/A') . "\nSalary: " . ($offer['salary'] ?? 'As discussed') . " BDT/month\nJoining Date: " . date('F d, Y', strtotime($offer['joining_date'])) . "\n\nPlease sign and return this letter to confirm your acceptance.",
                'opts' => ['sign' => true, 'note' => false]
            ];
        }
    } elseif ($type === 'application') {
        $app = db()->selectOne("SELECT * FROM application_forms WHERE id = ?", [$id]);
        if ($app) {
            $formData = json_decode($app['form_data'] ?? '{}', true);
            $docData = [
                'type' => 'application',
                'doc_id' => 'APP-' . str_pad($id, 4, '0', STR_PAD_LEFT),
                'doc_date' => date('Y-m-d', strtotime($app['created_at'])),
                'client_name' => $app['applicant_name'],
                'client_email' => $app['applicant_email'],
                'client_phone' => $app['applicant_phone'],
                'client_company' => $app['department'],
                'subject' => $app['form_title'],
                'body' => "Application Type: " . ucfirst($app['form_type']) . "\n\nName: " . $app['applicant_name'] . "\nEmail: " . $app['applicant_email'] . "\nPhone: " . $app['applicant_phone'] . "\nDepartment: " . $app['department'] . "\nStatus: " . ucfirst($app['status']),
                'opts' => ['sign' => false, 'note' => false]
            ];
        }
    } elseif ($type === 'custom') {
        $custom = db()->selectOne("SELECT * FROM custom_documents WHERE id = ? AND is_active = 1", [$id]);
        if ($custom) {
            $docData = [
                'type' => $custom['doc_type'] ?? 'Custom Document',
                'doc_id' => 'DOC-' . str_pad($id, 4, '0', STR_PAD_LEFT),
                'doc_date' => date('Y-m-d', strtotime($custom['created_at'])),
                'client_name' => $custom['recipient_name'],
                'client_email' => $custom['recipient_email'],
                'client_phone' => $custom['recipient_phone'],
                'client_company' => $custom['recipient_company'],
                'client_address' => $custom['recipient_address'],
                'subject' => $custom['doc_title'],
                'body' => $custom['content'],
                'opts' => ['sign' => true, 'note' => false]
            ];
        }
    } elseif ($type === 'letter') {
        $letter = db()->selectOne("SELECT * FROM application_forms WHERE id = ?", [$id]);
        if ($letter) {
            $formData = json_decode($letter['form_data'] ?? '{}', true);
            $letterTypeLabels = [
                'offer_letter'           => 'Offer Letter',
                'experience_certificate' => 'Experience Certificate',
                'recommendation'         => 'Recommendation Letter',
                'termination'            => 'Termination Letter',
                'promotion'              => 'Promotion Letter',
                'greeting'               => 'Greeting Letter',
                'formal_letter'          => 'Formal Letter',
                'notice'                 => 'Notice',
                'memo'                   => 'Memo',
                'other'                  => 'Letter',
                'experience'             => 'Experience Certificate',
                'leave'                  => 'Leave Letter',
                'general'                => 'Letter',
            ];
            $rawType = $formData['template_type'] ?? $letter['form_type'] ?? 'general';
            $letterTypeDisplay = $letterTypeLabels[$rawType] ?? ucwords(str_replace('_', ' ', $rawType));
            $docData = [
                'type' => $letterTypeDisplay,
                'doc_id' => 'LETTER-' . str_pad($id, 4, '0', STR_PAD_LEFT),
                'doc_date' => date('Y-m-d', strtotime($letter['created_at'])),
                'client_name' => $letter['applicant_name'],
                'client_email' => $letter['applicant_email'],
                'client_phone' => $letter['applicant_phone'],
                'client_company' => $formData['recipient_company'] ?? '',
                'client_designation' => $letter['department'] ?? '',
                'subject' => $letter['form_title'],
                'body' => $formData['content'] ?? '',
                'opts' => ['sign' => true, 'note' => false]
            ];
        }
    }
}

$isTableFormat = in_array($docData['type'] ?? '', ['invoice', 'quotation', 'receipt', 'purchase_order']);
$currentUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($docData['doc_id'] ?? 'Document') ?> | <?= $companyName ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; }
        
        .doc-container {
            max-width: 794px;
            margin: 0 auto;
            background: white;
            position: relative;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(0deg);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .watermark-text {
            font-size: 200px;
            font-weight: bold;
            color: #000;
            opacity: 0.06;
        }
        
        @media print {
            body { background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .doc-container { 
                width: 100% !important;
                max-width: none !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
            .watermark { opacity: 0.1 !important; z-index: 0 !important; }
            @page { 
                size: A4; 
                margin: 0;
            }
        }
        
        @media screen and (max-width: 850px) {
            .doc-container {
                max-width: 100%;
                margin: 0 10px;
            }
            body { padding: 10px !important; }
        }
    </style>
</head>
<body class="py-4 sm:py-8">
<?php if (empty($docData)): ?>
    <div class="no-print flex items-center justify-center min-h-screen">
        <div class="text-center p-4">
            <h1 class="text-2xl font-bold mb-2" style="color: #000;">Document Not Found</h1>
            <p style="color: #333;">Invalid document URL or document does not exist.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Print Button -->
    <div class="no-print fixed top-0 left-0 right-0 flex justify-center gap-3 py-2 px-4 z-50" style="background: #333;">
        <button onclick="window.print()" class="px-4 py-1.5 rounded-lg font-semibold text-sm flex items-center" style="background: #fff; color: #000; border: 1px solid #000;">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <a href="javascript:history.back()" class="px-3 py-1.5 rounded-lg text-sm" style="background: #fff; color: #000; border: 1px solid #000;">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    <!-- Document -->
    <div class="doc-container" style="min-height: 100vh; display: flex; flex-direction: column; position: relative; border: 1px solid #ccc;">
        
        <!-- Watermark -->
        <?php $watermarkLogo = $companyLogo ?: $companyLogoLarge; ?>
        <?php if ($watermarkLogo): ?>
        <div class="watermark">
            <img src="<?= escape($watermarkLogo) ?>" class="w-96 h-96 object-contain">
        </div>
        <?php else: ?>
        <div class="watermark">
            <span class="watermark-text"><?= $companyName ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Header - 3 Columns -->
        <div class="p-4 sm:p-6 relative z-10" style="border-bottom: 2px solid #000; z-index: 10;">
            <div class="flex justify-between items-start gap-2 sm:gap-4">
                
                <!-- Column 1: Company Info -->
                <div class="flex-1">
                    <?php if ($companyLogoLarge): ?>
                        <img src="<?= escape($companyLogoLarge) ?>" class="h-10 sm:h-12 mb-2" alt="Logo">
                    <?php else: ?>
                        <h1 class="text-lg sm:text-xl font-bold font-['Rajdhani']" style="color: #000;"><?= $companyName ?></h1>
                    <?php endif; ?>
                    <div class="text-xs" style="color: #333; margin-top: 4px;">
                        <?php if ($companyEmail): ?>
                            <p class="truncate"><i class="fas fa-envelope w-3 inline"></i> <?= htmlspecialchars($companyEmail) ?></p>
                        <?php endif; ?>
                        <?php if ($companyPhone): ?>
                            <p><i class="fas fa-phone w-3 inline"></i> <?= htmlspecialchars($companyPhone) ?></p>
                        <?php endif; ?>
                        <?php if ($companyAddress): ?>
                            <p class="truncate"><i class="fas fa-map-marker-alt w-3 inline"></i> <?= htmlspecialchars($companyAddress) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Column 2: Document Type -->
                <div class="flex-1 text-center">
                    <div class="px-3 sm:px-5 py-1.5 rounded-lg inline-block" style="border: 2px solid #000; color: #000;">
                        <span class="text-base sm:text-lg font-bold font-['Rajdhani'] uppercase tracking-wider"><?= str_replace('_', ' ', $docData['type'] ?? 'Document') ?></span>
                    </div>
                    <div class="mt-2 text-xs" style="color: #333;">
                        <p><span class="font-semibold">Ref:</span> <?= htmlspecialchars($docData['doc_id'] ?? 'N/A') ?></p>
                        <p><span class="font-semibold">Date:</span> <?= htmlspecialchars($docData['doc_date'] ?? date('Y-m-d')) ?></p>
                        <?php if (!empty($docData['due_date'])): ?>
                            <p><span class="font-semibold"><?= $isTableFormat ? 'Due:' : 'Valid:' ?></span> <?= htmlspecialchars($docData['due_date']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Column 3: QR Code -->
                <div class="flex-1 flex flex-col items-end">
                    <div id="qrcode" class="p-1 rounded-lg" style="border: 1px solid #000;"></div>
                    <!-- <span class="text-[9px] mt-1" style="color: #666;">Scan to verify</span> -->
                </div>
            </div>
        </div>

        <!-- To Line - All on one line -->
        <div class="px-4 sm:px-6 py-3 relative z-10" style="border-bottom: 1px solid #ccc;">
            <div class="flex items-center text-sm">
                <span class="text-xs font-bold uppercase mr-2 whitespace-nowrap" style="color: #000;">For:</span>
                <span class="font-semibold" style="color: #000;"><?= htmlspecialchars($docData['client_name'] ?? 'N/A') ?></span>
                <?php if (!empty($docData['client_company'])): ?>
                    <span style="color: #333;"> . <?= htmlspecialchars($docData['client_company']) ?></span>
                <?php endif; ?>
                <?php if (!empty($docData['client_designation'])): ?>
                    <span style="color: #333;"> . <?= htmlspecialchars($docData['client_designation']) ?></span>
                <?php endif; ?>
                <?php if (!empty($docData['client_email'])): ?>
                    <span style="color: #333;"> . <?= htmlspecialchars($docData['client_email']) ?></span>
                <?php endif; ?>
                <?php if (!empty($docData['client_phone'])): ?>
                    <span style="color: #333;"> . <?= htmlspecialchars($docData['client_phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($docData['client_address'])): ?>
                    <span style="color: #333;"> . <?= htmlspecialchars($docData['client_address']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="p-4 sm:p-6 flex-1 relative z-10">
            <!-- Items Table -->
            <?php if ($isTableFormat && !empty($docData['items'])): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr style="border-bottom: 2px solid #000;">
                            <th class="text-left py-2 px-2 sm:px-3 text-xs font-semibold" style="color: #000;">Description</th>
                            <th class="text-center py-2 px-1 sm:px-2 text-xs font-semibold w-12 sm:w-14" style="color: #000;">Qty</th>
                            <th class="text-right py-2 px-1 sm:px-2 text-xs font-semibold w-16 sm:w-20" style="color: #000;">Rate</th>
                            <th class="text-right py-2 px-2 sm:px-3 text-xs font-semibold w-16 sm:w-20" style="color: #000;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docData['items'] as $item): ?>
                        <tr style="border-bottom: 1px solid #ccc;">
                            <td class="py-2 px-2 sm:px-3" style="color: #000;"><?= htmlspecialchars($item['desc'] ?? $item['description'] ?? 'Item') ?></td>
                            <td class="text-center py-2 px-1 sm:px-2" style="color: #333;"><?= $item['qty'] ?? 1 ?></td>
                            <td class="text-right py-2 px-1 sm:px-2" style="color: #333;"><?= number_format($item['price'] ?? 0) ?></td>
                            <td class="text-right py-2 px-2 sm:px-3 font-medium" style="color: #000;"><?= number_format(($item['qty'] ?? 1) * ($item['price'] ?? 0)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end">
                <div class="w-40 sm:w-56">
                    <div class="flex justify-between py-1 text-xs" style="color: #333;">
                        <span>Subtotal</span>
                        <span><?= number_format($docData['subtotal'] ?? 0) ?></span>
                    </div>
                    <?php if (($docData['discount'] ?? 0) > 0): ?>
                    <div class="flex justify-between py-1 text-xs" style="color: #333;">
                        <span>Discount</span>
                        <span>-<?= number_format($docData['discount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (($docData['tax_amount'] ?? 0) > 0): ?>
                    <div class="flex justify-between py-1 text-xs" style="color: #333;">
                        <span>Tax</span>
                        <span><?= number_format($docData['tax_amount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between py-2 text-sm font-bold" style="border-top: 2px solid #000; color: #000;">
                        <span>Total</span>
                        <span><?= number_format($docData['total'] ?? $docData['subtotal'] ?? 0) ?> BDT</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Letter Content -->
            <?php if (!empty($docData['subject']) || !empty($docData['body'])): ?>
            <div class="mb-4">
                <?php if (!empty($docData['subject'])): ?>
                <h3 class="text-base font-bold mb-2" style="color: #000;"><?= htmlspecialchars($docData['subject']) ?></h3>
                <?php endif; ?>
                <div class="whitespace-pre-wrap text-sm leading-relaxed" style="color: #333;"><?= htmlspecialchars($docData['body'] ?? '') ?></div>
            </div>
            <?php endif; ?>

            <!-- Thank You Note -->
            <?php if ($docData['opts']['note'] ?? false): ?>
            <div class="p-3 rounded text-center mb-4" style="border: 1px solid #ccc;">
                <p class="text-sm italic" style="color: #333;">Thank you for your business!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer - One line at bottom -->
        <div class="p-4 sm:p-6 mt-auto relative z-10" style="border-top: 1px solid #ccc;">
            <?php if ($docData['opts']['sign'] ?? true): ?>
            <div class="flex justify-between mb-4">
                <div class="w-2/5">
                    <div style="border-bottom: 1px solid #000; height: 32px;"></div>
                    <div class="text-[10px] uppercase mt-1" style="color: #333;">Prepared By</div>
                </div>
                <div class="w-2/5 text-right">
                    <div style="border-bottom: 1px solid #000; height: 32px;"></div>
                    <div class="text-[10px] uppercase mt-1" style="color: #333;">Received By</div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- One line footer -->
            <div class="flex justify-between items-center text-[10px] pt-2" style="border-top: 1px solid #eee; color: #666;">
                <span><?= $companyName ?> | <?= date('M j, Y g:i A') ?></span>
                <span><?= htmlspecialchars($currentUrl) ?></span>
            </div>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), { 
            text: "<?= addslashes($currentUrl) ?>", 
            width: 80, 
            height: 80, 
            colorDark : "#000000", 
            colorLight : "#ffffff", 
            correctLevel : QRCode.CorrectLevel.L
        });
    </script>
<?php endif; ?>
</body>
</html>
