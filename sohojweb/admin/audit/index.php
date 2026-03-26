<?php
require_once __DIR__ . '/../../includes/config/database.php';
requireLogin();

if (!hasPermission('super_admin')) {
    die('<div style="padding:2rem;text-align:center;color:red;font-family:sans-serif;">Access Denied: Super Admin only.</div>');
}

// Clear logs (super_admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'clear') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        db()->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        header('Location: index.php?cleared=1');
        exit;
    }
}

// Filters
$filterAction = sanitize($_GET['filter_action'] ?? '');
$filterUser   = sanitize($_GET['filter_user'] ?? '');
$filterDate   = sanitize($_GET['filter_date'] ?? '');

// Pagination
$perPage = 50;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($filterAction) {
    $where[]  = 'a.action LIKE ?';
    $params[] = '%' . $filterAction . '%';
}
if ($filterUser) {
    $where[]  = '(u.full_name LIKE ? OR u.username LIKE ?)';
    $params[] = '%' . $filterUser . '%';
    $params[] = '%' . $filterUser . '%';
}
if ($filterDate) {
    $where[]  = 'DATE(a.created_at) = ?';
    $params[] = $filterDate;
}

$whereStr = implode(' AND ', $where);
$total    = db()->selectOne("SELECT COUNT(*) as cnt FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $whereStr", $params)['cnt'] ?? 0;
$logs     = db()->select("SELECT a.*, u.full_name, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $whereStr ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$totalPages = ceil($total / $perPage);

// Action badge colors
$actionColors = [
    'invoice'  => 'bg-blue-100 text-blue-700',
    'project'  => 'bg-purple-100 text-purple-700',
    'lead'     => 'bg-green-100 text-green-700',
    'user'     => 'bg-orange-100 text-orange-700',
    'application' => 'bg-pink-100 text-pink-700',
];

function getActionColor($action) {
    global $actionColors;
    foreach ($actionColors as $key => $color) {
        if (str_contains($action, $key)) return $color;
    }
    return 'bg-slate-100 text-slate-600';
}

$pageTitle = 'Audit Log | SohojWeb Admin';
include __DIR__ . '/../header.php';
?>

<div class="mb-6 flex justify-between items-center flex-wrap gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Audit Log</h1>
        <p class="text-slate-500">Track all admin actions and system events</p>
    </div>
    <?php if (!empty($logs)): ?>
    <form method="POST" action="index.php?action=clear" onsubmit="return confirm('Delete all logs older than 30 days?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 rounded-xl text-sm font-semibold hover:bg-red-100 transition-colors">
            <i class="fas fa-trash mr-1"></i> Clear Old Logs (&gt;30 days)
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (isset($_GET['cleared'])): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">Logs older than 30 days have been removed.</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Action</label>
        <input type="text" name="filter_action" value="<?= escape($filterAction) ?>" placeholder="e.g. invoice, lead..." class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none w-44">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">User</label>
        <input type="text" name="filter_user" value="<?= escape($filterUser) ?>" placeholder="Name or username..." class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none w-44">
    </div>
    <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Date</label>
        <input type="date" name="filter_date" value="<?= escape($filterDate) ?>" class="px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none">
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors">
        <i class="fas fa-search mr-1"></i> Filter
    </button>
    <?php if ($filterAction || $filterUser || $filterDate): ?>
    <a href="index.php" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors">Clear</a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center text-sm text-slate-500">
        <span>Showing <?= number_format($total) ?> records <?= ($filterAction || $filterUser || $filterDate) ? '(filtered)' : '' ?></span>
        <span>Page <?= $page ?> of <?= $totalPages ?: 1 ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Action</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Entity</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">User</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Details</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">IP</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Date & Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="py-12 text-center text-slate-400">
                        <i class="fas fa-shield-alt text-4xl mb-3 block opacity-30"></i>
                        No audit logs found<?= ($filterAction || $filterUser || $filterDate) ? ' matching your filters' : '' ?>.
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="py-3 px-4">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= getActionColor($log['action']) ?>">
                            <?= escape(str_replace('_', ' ', $log['action'])) ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-sm text-slate-600">
                        <?php if ($log['entity_type']): ?>
                        <span class="font-medium"><?= escape(ucfirst($log['entity_type'])) ?></span>
                        <?php if ($log['entity_id']): ?>
                        <span class="text-slate-400"> #<?= $log['entity_id'] ?></span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4">
                        <?php if ($log['full_name']): ?>
                        <p class="text-sm font-medium text-slate-800"><?= escape($log['full_name']) ?></p>
                        <p class="text-xs text-slate-400">@<?= escape($log['username']) ?></p>
                        <?php else: ?>
                        <span class="text-slate-400 text-sm">System</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-xs text-slate-500 max-w-xs">
                        <?php
                        $newData = $log['new_data'] ? json_decode($log['new_data'], true) : null;
                        if ($newData):
                            $parts = [];
                            foreach (array_slice($newData, 0, 3) as $k => $v) {
                                $parts[] = '<span class="font-medium">' . escape($k) . '</span>: ' . escape(is_array($v) ? json_encode($v) : $v);
                            }
                            echo implode(' &middot; ', $parts);
                        else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-xs text-slate-500 font-mono"><?= escape($log['ip_address'] ?? '—') ?></td>
                    <td class="py-3 px-4 text-xs text-slate-500 whitespace-nowrap">
                        <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                        <span class="text-slate-400"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-200 flex justify-center gap-2 flex-wrap">
        <?php
        $baseUrl = 'index.php?' . http_build_query(array_filter(['filter_action' => $filterAction, 'filter_user' => $filterUser, 'filter_date' => $filterDate]));
        for ($i = 1; $i <= $totalPages; $i++):
        ?>
        <a href="<?= $baseUrl ?>&p=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
