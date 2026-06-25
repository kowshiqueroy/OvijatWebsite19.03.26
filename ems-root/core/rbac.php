<?php
// RBAC helpers — navigation + gating

// Build the sidebar menu array, filtered by session permissions
function get_nav_menu(): array {
    return [
        ['label' => 'Dashboard',     'icon' => 'speedometer2',     'url' => 'dashboard.php', 'perm' => null],
        ['label' => 'Setup',         'icon' => 'gear-fill',        'url' => null, 'perm' => 'setup.view', 'children' => [
            ['label' => 'System Settings',   'url' => 'modules/setup/index.php',       'perm' => 'setup.view'],
            ['label' => 'Dropdown Options',  'url' => 'modules/setup/categories.php',  'perm' => 'setup.edit'],
            ['label' => 'Academic Sessions', 'url' => 'modules/academic/sessions.php', 'perm' => 'academic.manage'],
            ['label' => 'Holiday Calendar',  'url' => 'modules/setup/holidays.php',    'perm' => 'setup.edit'],
            ['label' => 'Activity Log',      'url' => 'modules/setup/audit.php',       'perm' => 'setup.view'],
        ]],
        ['label' => 'Users & Roles', 'icon' => 'people-fill',      'url' => null, 'perm' => 'users.view', 'children' => [
            ['label' => 'All Users',    'url' => 'modules/users/index.php',       'perm' => 'users.view'],
            ['label' => 'Roles',        'url' => 'modules/users/roles.php',       'perm' => 'roles.manage'],
            ['label' => 'Permissions',  'url' => 'modules/users/permissions.php', 'perm' => 'roles.manage'],
        ]],
        ['label' => 'Academic',      'icon' => 'book-half',        'url' => null, 'perm' => 'academic.view', 'children' => [
            ['label' => 'Classes & Sections', 'url' => 'modules/academic/classes.php',   'perm' => 'academic.manage'],
            ['label' => 'Groups / Streams',   'url' => 'modules/academic/groups.php',    'perm' => 'academic.manage'],
            ['label' => 'Subjects',           'url' => 'modules/academic/subjects.php',  'perm' => 'academic.manage'],
            ['label' => 'Rooms',              'url' => 'modules/academic/rooms.php',     'perm' => 'academic.manage'],
            ['label' => 'Class Routine',      'url' => 'modules/academic/routine.php',   'perm' => 'routine.view'],
        ]],
        ['label' => 'Students',      'icon' => 'person-badge-fill','url' => null, 'perm' => 'students.view', 'children' => [
            ['label' => 'Student List',   'url' => 'modules/students/index.php',   'perm' => 'students.view'],
            ['label' => 'New Admission',  'url' => 'modules/students/create.php',  'perm' => 'students.create'],
            ['label' => 'Enrollments',    'url' => 'modules/students/enroll.php',  'perm' => 'students.create'],
            ['label' => 'Special Batches','url' => 'modules/students/batches.php', 'perm' => 'students.create'],
            ['label' => 'Promotions',     'url' => 'modules/students/promote.php', 'perm' => 'students.promote'],
            ['label' => 'Attendance',     'url' => 'modules/students/attendance.php','perm' => 'attendance.mark'],
            ['label' => 'Transfer (TC)',  'url' => 'modules/students/tc.php',      'perm' => 'students.tc'],
        ]],
        ['label' => 'Examinations',  'icon' => 'clipboard2-check-fill','url' => null, 'perm' => 'exams.view', 'children' => [
            ['label' => 'Exam List',     'url' => 'modules/exams/index.php',    'perm' => 'exams.view'],
            ['label' => 'Schedule',      'url' => 'modules/exams/schedule.php', 'perm' => 'exams.manage'],
            ['label' => 'Seat Plans',    'url' => 'modules/exams/seats.php',    'perm' => 'seats.manage'],
            ['label' => 'Admit Cards',   'url' => 'modules/exams/admits.php',   'perm' => 'exams.view'],
            ['label' => 'Mark Entry',    'url' => 'modules/exams/marks.php',    'perm' => 'marks.enter'],
            ['label' => 'Results',       'url' => 'modules/exams/results.php',  'perm' => 'marks.approve'],
            ['label' => 'Question Vault','url' => 'modules/exams/questions.php','perm' => 'exams.manage'],
        ]],
        ['label' => 'Finance',       'icon' => 'cash-coin',         'url' => null, 'perm' => 'finance.view', 'children' => [
            ['label' => 'Fee Structures', 'url' => 'modules/finance/structures.php','perm' => 'finance.view'],
            ['label' => 'Collect Fees',   'url' => 'modules/finance/collect.php',   'perm' => 'fees.collect'],
            ['label' => 'Fee Ledger',     'url' => 'modules/finance/ledger.php',    'perm' => 'finance.view'],
            ['label' => 'Waivers',        'url' => 'modules/finance/waivers.php',   'perm' => 'waivers.request'],
            ['label' => 'Expenses',       'url' => 'modules/finance/expenses.php',  'perm' => 'expenses.manage'],
            ['label' => 'Income',         'url' => 'modules/finance/income.php',    'perm' => 'expenses.manage'],
            ['label' => 'Reports',        'url' => 'modules/finance/reports.php',   'perm' => 'finance.view'],
        ]],
        ['label' => 'HR & Payroll',  'icon' => 'person-workspace',  'url' => null, 'perm' => 'hr.view', 'children' => [
            ['label' => 'Staff List',    'url' => 'modules/hr/staff.php',      'perm' => 'hr.view'],
            ['label' => 'Add Staff',     'url' => 'modules/hr/create.php',     'perm' => 'hr.manage'],
            ['label' => 'Attendance',    'url' => 'modules/hr/attendance.php', 'perm' => 'attendance.mark'],
            ['label' => 'Leave',         'url' => 'modules/hr/leave.php',      'perm' => 'leave.approve'],
            ['label' => 'Payroll',       'url' => 'modules/hr/payroll.php',    'perm' => 'payroll.view'],
            ['label' => 'Performance',   'url' => 'modules/hr/performance.php','perm' => 'hr.manage'],
        ]],
        ['label' => 'Inventory',     'icon' => 'boxes',             'url' => null, 'perm' => 'inventory.view', 'children' => [
            ['label' => 'Assets',          'url' => 'modules/inventory/assets.php',      'perm' => 'inventory.view'],
            ['label' => 'Asset Assignment','url' => 'modules/inventory/assignments.php', 'perm' => 'inventory.manage'],
            ['label' => 'Consumables',     'url' => 'modules/inventory/consumables.php', 'perm' => 'inventory.view'],
            ['label' => 'Stock Ledger',    'url' => 'modules/inventory/stock.php',       'perm' => 'inventory.view'],
        ]],
        ['label' => 'ECA & Events',  'icon' => 'trophy-fill',       'url' => null, 'perm' => 'eca.view', 'children' => [
            ['label' => 'Clubs',  'url' => 'modules/eca/clubs.php',  'perm' => 'eca.view'],
            ['label' => 'Events', 'url' => 'modules/eca/events.php', 'perm' => 'eca.view'],
        ]],
        ['label' => 'Communication', 'icon' => 'chat-dots-fill',    'url' => null, 'perm' => 'sms.send', 'children' => [
            ['label' => 'Send SMS',         'url' => 'modules/communication/sms.php',       'perm' => 'sms.send'],
            ['label' => 'SMS Logs',         'url' => 'modules/communication/sms_logs.php',  'perm' => 'sms.send'],
            ['label' => 'Document Templates','url' => 'modules/communication/templates.php','perm' => 'documents.issue'],
            ['label' => 'Issue Documents',  'url' => 'modules/communication/issue.php',     'perm' => 'documents.issue'],
            ['label' => 'Incidents',        'url' => 'modules/communication/incidents.php', 'perm' => 'incidents.manage'],
        ]],
        ['label' => 'Reports',       'icon' => 'bar-chart-fill', 'url' => null, 'perm' => 'reports.view', 'children' => [
            ['label' => 'Overview',          'url' => 'modules/reports/index.php',      'perm' => 'reports.view'],
            ['label' => 'Result Cards',      'url' => 'modules/reports/resultcard.php', 'perm' => 'reports.view'],
            ['label' => 'Student Register',  'url' => 'modules/reports/students.php',   'perm' => 'reports.view'],
            ['label' => 'Attendance Report', 'url' => 'modules/reports/attendance.php', 'perm' => 'reports.view'],
            ['label' => 'Finance Reports',   'url' => 'modules/finance/reports.php',    'perm' => 'finance.view'],
        ]],
        ['label' => 'Alumni',        'icon' => 'mortarboard-fill',  'url' => 'modules/alumni/index.php',  'perm' => 'students.view'],
    ];
}

// Filter menu to only items the current user can see
function filter_menu(array $menu): array {
    $result = [];
    foreach ($menu as $item) {
        $perm = $item['perm'] ?? null;
        if ($perm && !has_permission($perm)) continue;
        if (!empty($item['children'])) {
            $children = array_filter($item['children'], fn($c) => !$c['perm'] || has_permission($c['perm']));
            if (empty($children) && $item['url'] === null) continue;
            $item['children'] = array_values($children);
        }
        $result[] = $item;
    }
    return $result;
}

// Is the given URL currently active?
function is_active_url(string $url): bool {
    $current = ltrim($_SERVER['REQUEST_URI'] ?? '', '/');
    $base    = ltrim(defined('EMS_URL') ? parse_url(EMS_URL, PHP_URL_PATH) ?? '' : '', '/');
    $rel     = ltrim(str_replace($base, '', $current), '/');
    $rel     = strtok($rel, '?');
    return $url === $rel || str_starts_with($rel, dirname($url));
}
