<?php
require_once __DIR__ . '/../../includes/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();

// Single query for all counts
$counts = db()->selectOne("
    SELECT
        (SELECT COUNT(*) FROM invoices) AS invoices,
        (SELECT COUNT(*) FROM quotations) AS quotations,
        (SELECT COUNT(*) FROM leads) AS leads,
        (SELECT COUNT(*) FROM projects) AS projects,
        (SELECT COUNT(*) FROM tasks WHERE status != 'completed') AS tasks
");
$invoiceCount   = $counts['invoices']   ?? 0;
$quotationCount = $counts['quotations'] ?? 0;
$leadCount      = $counts['leads']      ?? 0;
$projectCount   = $counts['projects']   ?? 0;
$taskCount      = $counts['tasks']      ?? 0;

// Single query for revenue totals
$revenue = db()->selectOne("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status IN ('sent','viewed') THEN total_amount ELSE 0 END), 0) AS pending_revenue
    FROM invoices
");
$totalRevenue   = $revenue['total_revenue']   ?? 0;
$pendingRevenue = $revenue['pending_revenue'] ?? 0;

$recentInvoices  = db()->select("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 5");
$recentLeads     = db()->select("SELECT * FROM leads ORDER BY created_at DESC LIMIT 5");
$recentProjects  = db()->select("SELECT * FROM projects ORDER BY created_at DESC LIMIT 5");

$pageTitle = 'Dashboard | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Revenue -->
    <div class="card bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-wallet text-white text-xl"></i>
            </div>
            <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">+12%</span>
        </div>
        <p class="text-sm text-slate-500 mb-1">Total Revenue</p>
        <p class="text-2xl font-bold text-slate-800">৳ <?= number_format($totalRevenue) ?></p>
    </div>
    
    <!-- Active Projects -->
    <div class="card bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                <i class="fas fa-project-diagram text-white text-xl"></i>
            </div>
            <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded-full"><?= $projectCount ?> Projects</span>
        </div>
        <p class="text-sm text-slate-500 mb-1">Active Projects</p>
        <p class="text-2xl font-bold text-slate-800"><?= $projectCount ?></p>
    </div>
    
    <!-- Pending Tasks -->
    <div class="card bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/30">
                <i class="fas fa-tasks text-white text-xl"></i>
            </div>
            <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-1 rounded-full">Pending</span>
        </div>
        <p class="text-sm text-slate-500 mb-1">Pending Tasks</p>
        <p class="text-2xl font-bold text-slate-800"><?= $taskCount ?></p>
    </div>
    
    <!-- New Leads -->
    <div class="card bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                <i class="fas fa-user-plus text-white text-xl"></i>
            </div>
            <span class="text-xs font-medium text-purple-600 bg-purple-50 px-2 py-1 rounded-full"><?= $leadCount ?> Leads</span>
        </div>
        <p class="text-sm text-slate-500 mb-1">Total Leads</p>
        <p class="text-2xl font-bold text-slate-800"><?= $leadCount ?></p>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <a href="../generators/invoices.php?action=create" class="flex items-center gap-3 p-4 bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-blue-200 transition group">
        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center group-hover:bg-blue-500 transition">
            <i class="fas fa-plus-circle text-blue-600 text-lg group-hover:text-white"></i>
        </div>
        <span class="font-medium text-slate-700">New Invoice</span>
    </a>
    <a href="../generators/quotations.php?action=create" class="flex items-center gap-3 p-4 bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-green-200 transition group">
        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center group-hover:bg-green-500 transition">
            <i class="fas fa-file-invoice-dollar text-green-600 text-lg group-hover:text-white"></i>
        </div>
        <span class="font-medium text-slate-700">New Quote</span>
    </a>
    <a href="../generators/job-circular.php?action=create" class="flex items-center gap-3 p-4 bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-purple-200 transition group">
        <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center group-hover:bg-purple-500 transition">
            <i class="fas fa-bullhorn text-purple-600 text-lg group-hover:text-white"></i>
        </div>
        <span class="font-medium text-slate-700">Post Job</span>
    </a>
    <a href="../projects/index.php" class="flex items-center gap-3 p-4 bg-white rounded-xl border border-slate-100 shadow-sm hover:shadow-md hover:border-orange-200 transition group">
        <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center group-hover:bg-orange-500 transition">
            <i class="fas fa-folder-plus text-orange-600 text-lg group-hover:text-white"></i>
        </div>
        <span class="font-medium text-slate-700">New Project</span>
    </a>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Invoices -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Recent Invoices</h3>
            <a href="../generators/invoices.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left py-3 px-6 text-xs font-semibold text-slate-500 uppercase">Invoice</th>
                        <th class="text-left py-3 px-6 text-xs font-semibold text-slate-500 uppercase">Client</th>
                        <th class="text-left py-3 px-6 text-xs font-semibold text-slate-500 uppercase">Amount</th>
                        <th class="text-left py-3 px-6 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentInvoices as $invoice): $statusColors = ['paid' => 'bg-green-100 text-green-700', 'sent' => 'bg-blue-100 text-blue-700', 'viewed' => 'bg-purple-100 text-purple-700', 'draft' => 'bg-slate-100 text-slate-700', 'overdue' => 'bg-red-100 text-red-700']; ?>
                    <tr class="border-t border-slate-50 hover:bg-slate-50 transition">
                        <td class="py-3 px-6">
                            <a href="#" class="font-medium text-blue-600 hover:underline"><?= escape($invoice['invoice_number']) ?></a>
                        </td>
                        <td class="py-3 px-6 text-slate-600"><?= escape($invoice['client_name']) ?></td>
                        <td class="py-3 px-6 font-medium">৳ <?= number_format($invoice['total_amount']) ?></td>
                        <td class="py-3 px-6">
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $statusColors[$invoice['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                <?= ucfirst($invoice['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentInvoices)): ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-400">No invoices yet</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Leads -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Recent Leads</h3>
            <a href="../crm/leads.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($recentLeads as $lead): $leadColors = ['new' => 'bg-blue-100 text-blue-700', 'contacted' => 'bg-amber-100 text-amber-700', 'qualified' => 'bg-purple-100 text-purple-700', 'proposal_sent' => 'bg-cyan-100 text-cyan-700', 'won' => 'bg-green-100 text-green-700', 'lost' => 'bg-red-100 text-red-700']; ?>
            <div class="p-4 hover:bg-slate-50 transition">
                <div class="flex items-center justify-between mb-1">
                    <span class="font-medium text-slate-800"><?= escape($lead['client_name']) ?></span>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $leadColors[$lead['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                        <?= ucfirst($lead['status']) ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500"><?= escape($lead['company_name'] ?? $lead['selected_module'] ?? 'No details') ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= date('M j, Y', strtotime($lead['created_at'])) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLeads)): ?>
            <div class="p-8 text-center text-slate-400">No leads yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Projects Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <!-- Active Projects -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Recent Projects</h3>
            <a href="../projects/index.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        <div class="p-4 space-y-3">
            <?php foreach ($recentProjects as $project): $statusColors = ['planning' => 'bg-amber-100 text-amber-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'review' => 'bg-purple-100 text-purple-700', 'testing' => 'bg-cyan-100 text-cyan-700', 'completed' => 'bg-green-100 text-green-700', 'on_hold' => 'bg-slate-100 text-slate-700']; ?>
            <div class="flex items-center gap-4 p-3 bg-slate-50 rounded-xl">
                <div class="w-2 h-2 rounded-full <?= $project['status'] === 'completed' ? 'bg-green-500' : ($project['status'] === 'in_progress' ? 'bg-blue-500' : 'bg-amber-500') ?>"></div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-800 truncate"><?= escape($project['project_name']) ?></p>
                    <p class="text-sm text-slate-500"><?= escape($project['client_name'] ?: 'No client') ?></p>
                </div>
                <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $statusColors[$project['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                    <?= ucwords(str_replace('_', ' ', $project['status'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentProjects)): ?>
            <p class="text-center text-slate-400 py-8">No projects yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Quick Stats</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <span class="text-slate-600">Paid Invoices</span>
                </div>
                <span class="font-bold text-slate-800"><?= $invoiceCount ?></span>
            </div>
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt text-blue-600"></i>
                    </div>
                    <span class="text-slate-600">Quotations</span>
                </div>
                <span class="font-bold text-slate-800"><?= $quotationCount ?></span>
            </div>
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-amber-600"></i>
                    </div>
                    <span class="text-slate-600">Pending Revenue</span>
                </div>
                <span class="font-bold text-slate-800">৳ <?= number_format($pendingRevenue) ?></span>
            </div>
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-percentage text-purple-600"></i>
                    </div>
                    <span class="text-slate-600">Conversion Rate</span>
                </div>
                <span class="font-bold text-slate-800"><?= $leadCount > 0 ? round(($leadCount / ($leadCount + 10)) * 100) : 0 ?>%</span>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
