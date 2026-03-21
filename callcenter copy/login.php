<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
$info  = '';

if ($_GET['msg'] ?? '' === 'logged_out') {
    $info = 'You have been logged out.';
}

// ── Handle login ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare(
            "SELECT id, username, password, full_name, department, status
             FROM agents WHERE username = ? AND id > 1 LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $agent = $stmt->get_result()->fetch_assoc();

        if ($agent && $agent['status'] === 'active' && password_verify($password, $agent['password'])) {
            $_SESSION['agent_id']   = $agent['id'];
            $_SESSION['username']   = $agent['username'];
            $_SESSION['full_name']  = $agent['full_name'];
            $_SESSION['department'] = $agent['department'];

            // Update last login
            $aid = $agent['id'];
            $conn->query("UPDATE agents SET last_login = NOW() WHERE id = $aid");
            logActivity('login', 'agents', $aid, 'Agent logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));

            $next = $_GET['next'] ?? (APP_URL . '/dashboard.php');
            header('Location: ' . $next);
            exit;
        } else {
            $error = 'Invalid username or password, or account inactive.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

// ── Handle first-run: create new agent (only if <2 agents exist) ──────────────
$agentCount = (int)$conn->query("SELECT COUNT(*) AS c FROM agents WHERE id > 1")->fetch_assoc()['c'];
$allowRegister = ($agentCount === 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register' && $allowRegister) {
    $un  = trim($_POST['reg_username'] ?? '');
    $fn  = trim($_POST['reg_fullname'] ?? '');
    $pw  = $_POST['reg_password'] ?? '';
    $pw2 = $_POST['reg_password2'] ?? '';

    if (!$un || !$fn || !$pw) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $un)) {
        $error = 'Username: 3-30 chars, letters/numbers/underscore only.';
    } elseif (strlen($pw) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO agents (username, password, full_name, department, status, created_by) VALUES (?, ?, ?, 'Management', 'active', 1)");
        $stmt->bind_param("sss", $un, $hash, $fn);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $conn->query("INSERT INTO agent_numbers (agent_id, number_type, number, label, is_primary, created_by)
                          VALUES ($newId, 'extension', '100', 'Main Ext', 1, $newId)");
            $info = 'Account created! You can now log in.';
            $allowRegister = false;
        } else {
            $error = 'Username already taken.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-wrap { width: 100%; max-width: 420px; padding: 1.5rem; }
        .login-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 2.2rem 2rem; }
        .brand      { text-align: center; margin-bottom: 2rem; }
        .brand-icon { width: 64px; height: 64px; background: linear-gradient(135deg,#4f46e5,#7c3aed); border-radius: 16px;
                      display: flex; align-items: center; justify-content: center; margin: 0 auto .8rem; }
        .brand-icon i { font-size: 1.8rem; color: #fff; }
        .brand-name { font-size: 1.3rem; font-weight: 700; color: var(--text); }
        .brand-sub  { font-size: .82rem; color: var(--muted); }
        .tab-pills  { display: flex; gap: .5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
        .tab-pill   { flex: 1; text-align: center; padding: .55rem; cursor: pointer; font-size: .9rem; font-weight: 500;
                      color: var(--muted); border-bottom: 2px solid transparent; transition: all .2s; }
        .tab-pill.active { color: var(--accent); border-bottom-color: var(--accent); }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-headset"></i></div>
            <div class="brand-name"><?= APP_NAME ?></div>
            <div class="brand-sub">Agent Portal</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3"><i class="fas fa-circle-exclamation me-2"></i><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-info py-2 mb-3"><i class="fas fa-circle-info me-2"></i><?= e($info) ?></div>
        <?php endif; ?>

        <?php if ($allowRegister): ?>
        <div class="tab-pills">
            <div class="tab-pill active" id="tab-login-btn" onclick="showTab('login')">Sign In</div>
            <div class="tab-pill" id="tab-reg-btn" onclick="showTab('reg')">First Setup</div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="tab-login">
            <form method="POST" autocomplete="on">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label class="form-label text-muted small">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" name="username" class="form-control border-start-0"
                               placeholder="your_username" autocomplete="username"
                               value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" name="password" id="pwField" class="form-control border-start-0 border-end-0"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <span class="input-group-text bg-transparent" style="cursor:pointer" onclick="togglePw()">
                            <i class="fas fa-eye text-muted" id="pwEye"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>

        <!-- First-run Register Form -->
        <?php if ($allowRegister): ?>
        <div id="tab-reg" style="display:none">
            <div class="alert alert-warning py-2 mb-3 small">
                <i class="fas fa-circle-info me-1"></i>
                No agents exist yet. Create the first account.
            </div>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="register">
                <div class="mb-3">
                    <label class="form-label text-muted small">Full Name</label>
                    <input type="text" name="reg_fullname" class="form-control" placeholder="Ahmed Ali" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Username</label>
                    <input type="text" name="reg_username" class="form-control" placeholder="ahmed_ali" required
                           pattern="[a-zA-Z0-9_]{3,30}">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Password</label>
                    <input type="password" name="reg_password" class="form-control" placeholder="Min 6 chars" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted small">Confirm Password</label>
                    <input type="password" name="reg_password2" class="form-control" placeholder="Repeat password" required>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-semibold py-2">
                    <i class="fas fa-user-plus me-2"></i>Create Account &amp; Login
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
    <div class="text-center mt-3" style="color:var(--muted);font-size:.75rem">
        <?= APP_NAME ?> v<?= APP_VERSION ?> &bull; Every action is logged with agent identity
    </div>
</div>

<script>
function showTab(t) {
    document.getElementById('tab-login').style.display = t === 'login' ? '' : 'none';
    const reg = document.getElementById('tab-reg');
    if (reg) reg.style.display = t === 'reg' ? '' : 'none';
    document.getElementById('tab-login-btn').classList.toggle('active', t === 'login');
    const regBtn = document.getElementById('tab-reg-btn');
    if (regBtn) regBtn.classList.toggle('active', t === 'reg');
}
function togglePw() {
    const f = document.getElementById('pwField');
    const e = document.getElementById('pwEye');
    if (f.type === 'password') { f.type = 'text'; e.classList.replace('fa-eye','fa-eye-slash'); }
    else { f.type = 'password'; e.classList.replace('fa-eye-slash','fa-eye'); }
}
<?php if ($allowRegister && $_POST['action'] ?? '' === 'register' && $error): ?>
showTab('reg');
<?php endif; ?>
</script>
</body>
</html>
