<?php
require_once __DIR__ . '/../../includes/config/database.php';

$pageTitle = 'Track Your Status | SohojWeb';
$favicon = getSetting('company_favicon', '');
$results = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = sanitize($_POST['reference_id'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($ref) || empty($email)) {
        $error = "Please provide both Reference ID and Email.";
    } else {
        // Try to find in Projects
        $project = db()->selectOne("SELECT * FROM projects WHERE project_code = ? AND client_email = ?", [$ref, $email]);
        
        // Try to find in Invoices
        $invoice = db()->selectOne("SELECT * FROM invoices WHERE invoice_number = ? AND client_email = ?", [$ref, $email]);
        
        // Try to find in Leads (using ID)
        $lead = null;
        if (is_numeric($ref)) {
            $lead = db()->selectOne("SELECT * FROM leads WHERE id = ? AND client_email = ?", [$ref, $email]);
        }
        
        if ($project) {
            $results = ['type' => 'Project', 'data' => $project];
        } elseif ($invoice) {
            $results = ['type' => 'Invoice', 'data' => $invoice];
        } elseif ($lead) {
            $results = ['type' => 'Lead', 'data' => $lead];
        } else {
            $error = "No records found matching these details. Please check and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: {'50': '#eff6ff', '500': '#3b82f6', '600': '#2563eb'} },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-text { background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="font-sans text-gray-800 bg-slate-50">
    <?php include __DIR__ . '/../../public/header.php'; ?>

<div class="pt-32 pb-16 min-h-screen">
    <div class="max-w-3xl mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Track Your <span class="gradient-text">Status</span></h1>
            <p class="text-lg text-slate-600">Enter your reference number and email address to view real-time updates.</p>
        </div>
        
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-8 mb-8" data-aos="fade-up" data-aos-delay="100">
            <form action="" method="POST" class="space-y-6">
                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-sm flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-lg"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Reference ID</label>
                        <input type="text" name="reference_id" placeholder="e.g. PRJ-001, INV-001" required value="<?= escape($_POST['reference_id'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <p class="text-[10px] text-slate-400 mt-2 uppercase tracking-widest font-bold">Project Code, Invoice #, or Lead ID</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" placeholder="your@email.com" required value="<?= escape($_POST['email'] ?? '') ?>" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                    </div>
                </div>
                
                <button type="submit" class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold rounded-2xl hover:shadow-lg hover:shadow-blue-500/30 transition-all transform hover:scale-[1.01] flex items-center justify-center gap-3">
                    <i class="fas fa-search"></i> Track Application
                </button>
            </form>
        </div>
        
        <?php if ($results): ?>
            <div class="bg-white rounded-3xl shadow-2xl border border-blue-50 overflow-hidden animate-fade-in-up" data-aos="zoom-in">
                <div class="bg-blue-600 px-8 py-5 flex justify-between items-center">
                    <h2 class="font-bold text-white flex items-center gap-3">
                        <i class="fas fa-file-alt text-xl"></i> Found <?= $results['type'] ?> Record
                    </h2>
                    <span class="bg-white/20 text-white px-4 py-1.5 rounded-full text-xs font-black tracking-widest uppercase backdrop-blur-md">
                        <?= $results['type'] === 'Project' ? $results['data']['project_code'] : ($results['type'] === 'Invoice' ? $results['data']['invoice_number'] : '#'.$results['data']['id']) ?>
                    </span>
                </div>
                
                <div class="p-8">
                    <?php if ($results['type'] === 'Project'): $p = $results['data']; ?>
                        <div class="space-y-8">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= escape($p['project_name']) ?></h3>
                                <p class="text-slate-500 leading-relaxed"><?= escape($p['description']) ?></p>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-2">Status</p>
                                    <p class="font-bold text-blue-600 capitalize"><?= str_replace('_', ' ', $p['status']) ?></p>
                                </div>
                                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-2">Start Date</p>
                                    <p class="font-bold text-gray-800"><?= $p['start_date'] ? date('M d, Y', strtotime($p['start_date'])) : 'TBD' ?></p>
                                </div>
                                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-2">Due Date</p>
                                    <p class="font-bold text-gray-800"><?= $p['due_date'] ? date('M d, Y', strtotime($p['due_date'])) : 'TBD' ?></p>
                                </div>
                                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-2">Completion</p>
                                    <?php 
                                        $progress = 0;
                                        if($p['status'] === 'completed') $progress = 100;
                                        elseif($p['status'] === 'testing') $progress = 80;
                                        elseif($p['status'] === 'in_progress') $progress = 50;
                                        elseif($p['status'] === 'planning') $progress = 20;
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-slate-200 rounded-full h-2 overflow-hidden">
                                            <div class="bg-blue-600 h-full transition-all duration-1000" style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <span class="font-bold text-blue-600 text-xs"><?= $progress ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($results['type'] === 'Invoice'): $inv = $results['data']; ?>
                        <div class="space-y-8">
                            <div class="flex justify-between items-start flex-wrap gap-4">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-1">Invoice #<?= escape($inv['invoice_number']) ?></h3>
                                    <p class="text-slate-500 font-medium">Billed to <?= escape($inv['client_name']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-black text-gray-900 mb-2"><?= getSetting('currency_symbol', '৳') . number_format($inv['total_amount'], 2) ?></p>
                                    <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest <?= $inv['status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                                        <?= $inv['status'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-6 border-t border-slate-100 pt-8">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-1">Invoice Date</p>
                                    <p class="font-bold text-gray-800"><?= date('M d, Y', strtotime($inv['invoice_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-1">Due Date</p>
                                    <p class="font-bold text-gray-800"><?= date('M d, Y', strtotime($inv['due_date'])) ?></p>
                                </div>
                                <div class="md:text-right">
                                    <a href="<?= BASE_URL ?>/print/?type=invoice&id=<?= $inv['id'] ?>" target="_blank" class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-50 text-blue-600 font-bold rounded-xl hover:bg-blue-600 hover:text-white transition-all">
                                        <i class="fas fa-file-pdf"></i> View Invoice
                                    </a>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($results['type'] === 'Lead'): $l = $results['data']; ?>
                        <div class="space-y-8">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-1"><?= escape($l['selected_module'] ?? 'Standard Development') ?></h3>
                                <p class="text-slate-500 font-medium">Submitted on <?= date('M d, Y', strtotime($l['created_at'])) ?></p>
                            </div>
                            
                            <div class="bg-slate-50 p-8 rounded-3xl border border-slate-100">
                                <div class="flex items-center justify-between mb-6">
                                    <span class="text-sm font-black text-slate-400 uppercase tracking-widest">Current Pipeline Stage</span>
                                    <span class="px-5 py-2 rounded-full text-sm font-black uppercase tracking-widest 
                                        <?= $l['status'] === 'new' ? 'bg-blue-100 text-blue-700' : 
                                           ($l['status'] === 'contacted' ? 'bg-yellow-100 text-yellow-700' : 
                                           ($l['status'] === 'won' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-700')) ?>">
                                        <?= str_replace('_', ' ', $l['status']) ?>
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden shadow-inner">
                                    <?php 
                                        $stages = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiating', 'won'];
                                        $currentStageIndex = array_search($l['status'], $stages);
                                        if ($currentStageIndex === false) $currentStageIndex = 0;
                                        $progress = (($currentStageIndex + 1) / count($stages)) * 100;
                                    ?>
                                    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 h-full transition-all duration-1000 shadow-lg" style="width: <?= $progress ?>%"></div>
                                </div>
                                <div class="flex justify-between mt-4">
                                    <p class="text-[10px] text-slate-400 font-black tracking-widest uppercase">Submission</p>
                                    <p class="text-[10px] text-slate-400 font-black tracking-widest uppercase">Project Launch</p>
                                </div>
                            </div>
                            
                            <?php if ($l['estimated_budget'] > 0): ?>
                            <div class="flex justify-between items-center bg-blue-50/50 p-6 rounded-2xl border border-blue-100">
                                <span class="text-sm font-bold text-blue-800">Initial Project Estimate</span>
                                <span class="text-xl font-black text-blue-900"><?= getSetting('currency_symbol', '৳') . number_format($l['estimated_budget']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../public/footer.php'; ?>
<script>AOS.init({ duration: 800, once: true });</script>
</body>
</html>
