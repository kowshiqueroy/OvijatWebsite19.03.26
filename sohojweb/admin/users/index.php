<?php
require_once __DIR__ . '/../../includes/config/database.php';
requireLogin();

if (!hasPermission('super_admin')) {
    die('<div style="padding:2rem;text-align:center;color:red;font-family:sans-serif;">Access Denied: Super Admin only.</div>');
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Save (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    header('Content-Type: application/json');
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('Security token mismatch');

        $fullName = sanitize($_POST['full_name']);
        $username = sanitize($_POST['username']);
        $email    = sanitize($_POST['email']);
        $role     = sanitize($_POST['role']);
        $status   = sanitize($_POST['status']);

        if (empty($fullName) || empty($username) || empty($email)) {
            throw new Exception('Full name, username and email are required.');
        }

        $data = [
            'full_name' => $fullName,
            'username'  => $username,
            'email'     => $email,
            'role'      => $role,
            'status'    => $status,
        ];

        if ($id) {
            // Only update password if provided
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) throw new Exception('Password must be at least 6 characters.');
                $data['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            }
            db()->update('users', $data, 'id = :id', ['id' => $id]);
            logAudit('user_updated', 'user', $id, null, ['username' => $username, 'role' => $role]);
            jsonResponse(['success' => true, 'message' => 'User updated successfully']);
        } else {
            if (empty($_POST['password'])) throw new Exception('Password is required for new users.');
            if (strlen($_POST['password']) < 6) throw new Exception('Password must be at least 6 characters.');
            $data['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT);

            // Check duplicate
            $existing = db()->selectOne("SELECT id FROM users WHERE email = ? OR username = ?", [$email, $username]);
            if ($existing) throw new Exception('A user with this email or username already exists.');

            $newId = db()->insert('users', $data);
            logAudit('user_created', 'user', $newId, null, ['username' => $username, 'role' => $role]);
            jsonResponse(['success' => true, 'message' => 'User created successfully']);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
    header('Content-Type: application/json');
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('Security token mismatch');

        $uid = (int)$_POST['id'];
        if ($uid === (int)$_SESSION['user_id']) throw new Exception('Cannot deactivate your own account.');

        $user = db()->selectOne("SELECT status FROM users WHERE id = ?", [$uid]);
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        db()->update('users', ['status' => $newStatus], 'id = :id', ['id' => $uid]);
        logAudit('user_status_toggled', 'user', $uid, ['status' => $user['status']], ['status' => $newStatus]);
        jsonResponse(['success' => true, 'status' => $newStatus]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    header('Content-Type: application/json');
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) throw new Exception('Security token mismatch');

        $uid = (int)$_POST['id'];
        if ($uid === (int)$_SESSION['user_id']) throw new Exception('Cannot delete your own account.');

        logAudit('user_deleted', 'user', $uid);
        db()->delete('users', 'id = :id', ['id' => $uid]);
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

$users = db()->select("SELECT id, username, email, full_name, role, status, last_login, created_at FROM users ORDER BY created_at DESC");

$pageTitle = 'User Management | SohojWeb Admin';
include __DIR__ . '/../header.php';

$roleColors = [
    'super_admin' => 'bg-purple-100 text-purple-700',
    'editor'      => 'bg-blue-100 text-blue-700',
    'sales'       => 'bg-green-100 text-green-700',
    'hr'          => 'bg-orange-100 text-orange-700',
];
$roleLabels = ['super_admin' => 'Super Admin', 'editor' => 'Editor', 'sales' => 'Sales', 'hr' => 'HR'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
        <p class="text-slate-500">Create and manage admin user accounts</p>
    </div>
    <button onclick="openModal()" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-semibold transition-colors">
        <i class="fas fa-plus"></i> Add User
    </button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">User</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Role</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest">Last Login</th>
                    <th class="py-4 px-6 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="py-12 text-center text-slate-400">No users found.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-900"><?= escape($u['full_name']) ?></p>
                                <p class="text-xs text-slate-500"><?= escape($u['email']) ?> &middot; @<?= escape($u['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= $roleColors[$u['role']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= $roleLabels[$u['role']] ?? ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="py-4 px-6">
                        <button onclick="toggleStatus(<?= $u['id'] ?>, this)" data-status="<?= $u['status'] ?>"
                            class="px-2.5 py-1 text-xs font-bold rounded-full transition-colors
                            <?= $u['status'] === 'active' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200' ?>">
                            <?= ucfirst($u['status']) ?>
                        </button>
                    </td>
                    <td class="py-4 px-6 text-sm text-slate-500">
                        <?= $u['last_login'] ? date('M d, Y g:i A', strtotime($u['last_login'])) : 'Never' ?>
                    </td>
                    <td class="py-4 px-6 text-right">
                        <div class="flex justify-end gap-1">
                            <button onclick='editUser(<?= json_encode($u) ?>)' class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <button onclick="deleteUser(<?= $u['id'] ?>, '<?= escape($u['full_name']) ?>')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="userModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg">
        <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-900" id="modalTitle">Add User</h2>
            <button onclick="closeModal()" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400"><i class="fas fa-times"></i></button>
        </div>
        <form id="userForm" class="p-8 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" id="userId" value="">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" id="inp_full_name" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" id="inp_username" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="inp_email" required class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Role</label>
                    <select name="role" id="inp_role" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="editor">Editor</option>
                        <option value="sales">Sales</option>
                        <option value="hr">HR</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="inp_status" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Password <span id="pwdNote" class="text-slate-400 text-xs font-normal">(required)</span></label>
                <input type="password" name="password" id="inp_password" class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Min 6 characters">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-5 py-2 border border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Cancel</button>
                <button type="submit" id="submitBtn" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-colors">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function openModal(editing = false) {
    if (!editing) {
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('modalTitle').innerText = 'Add User';
        document.getElementById('pwdNote').innerText = '(required)';
        document.getElementById('inp_password').required = true;
    }
    const m = document.getElementById('userModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeModal() {
    const m = document.getElementById('userModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function editUser(u) {
    document.getElementById('userId').value = u.id;
    document.getElementById('inp_full_name').value = u.full_name;
    document.getElementById('inp_username').value = u.username;
    document.getElementById('inp_email').value = u.email;
    document.getElementById('inp_role').value = u.role;
    document.getElementById('inp_status').value = u.status;
    document.getElementById('inp_password').value = '';
    document.getElementById('inp_password').required = false;
    document.getElementById('pwdNote').innerText = '(leave blank to keep current)';
    document.getElementById('modalTitle').innerText = 'Edit User';
    openModal(true);
}

document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const data = new FormData(this);
    const uid = document.getElementById('userId').value;
    const url = uid ? `index.php?action=save&id=${uid}` : 'index.php?action=save';

    fetch(url, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeModal();
                location.reload();
            } else {
                alert(res.message || 'Error saving user');
                btn.disabled = false;
                btn.innerText = 'Save User';
            }
        })
        .catch(() => {
            alert('Network error');
            btn.disabled = false;
            btn.innerText = 'Save User';
        });
});

function toggleStatus(id, btn) {
    const current = btn.dataset.status;
    if (!confirm(`${current === 'active' ? 'Deactivate' : 'Activate'} this user?`)) return;

    const fd = new URLSearchParams({ csrf_token: CSRF_TOKEN, id });
    fetch('index.php?action=toggle_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message || 'Error');
        });
}

function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
    const fd = new URLSearchParams({ csrf_token: CSRF_TOKEN, id });
    fetch('index.php?action=delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message || 'Error');
        });
}
</script>

<?php include __DIR__ . '/../footer.php'; ?>
