<?php $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Kotha</title>
<link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
<link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
<meta name="theme-color" content="#00f2fe">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%}
body{
    font-family:'Outfit',sans-serif;
    min-height:100dvh;
    background:#070d14;
    color:#e9edef;
    -webkit-font-smoothing:antialiased;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:20px 16px;
    position:relative;overflow:hidden;
}
/* Animated gradient background */
body::before{
    content:'';position:fixed;inset:0;z-index:0;
    background:
        radial-gradient(ellipse 60% 50% at 25% 25%,rgba(0,242,254,.12) 0%,transparent 65%),
        radial-gradient(ellipse 50% 60% at 75% 75%,rgba(79,172,254,.1) 0%,transparent 65%),
        #070d14;
    background-size:200% 200%;
    animation:meshShift 18s ease infinite;
}
/* Subtle grid */
body::after{
    content:'';position:fixed;inset:0;z-index:0;
    background-image:
        linear-gradient(rgba(0,242,254,.025) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,242,254,.025) 1px,transparent 1px);
    background-size:52px 52px;
    mask-image:radial-gradient(ellipse 70% 70% at center,black,transparent);
}
@keyframes meshShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes cardIn{from{opacity:0;transform:translateY(28px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes spin{to{transform:rotate(360deg)}}

/* Back link */
.back-link{
    position:fixed;top:18px;left:18px;z-index:10;
    display:flex;align-items:center;gap:6px;
    font-size:.75rem;color:rgba(255,255,255,.35);font-weight:500;
    text-decoration:none;transition:color .2s;
    font-family:'Outfit',sans-serif;
}
.back-link:hover{color:rgba(0,242,254,.8)}
.back-link i{font-size:.7rem}

/* Card */
.auth-card{
    position:relative;z-index:1;
    width:100%;max-width:400px;
    background:rgba(11,20,30,.88);
    border:1px solid rgba(0,242,254,.12);
    border-radius:22px;
    padding:32px 28px 28px;
    backdrop-filter:blur(24px) saturate(160%);
    -webkit-backdrop-filter:blur(24px) saturate(160%);
    box-shadow:0 24px 60px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04),0 0 40px rgba(0,242,254,.04);
    animation:cardIn .5s cubic-bezier(.22,.6,.36,1) both;
}

/* Logo */
.logo-wrap{
    display:flex;align-items:center;justify-content:center;
    gap:10px;margin-bottom:28px;
}
.logo-wrap img{
    width:40px;height:40px;
    filter:drop-shadow(0 4px 14px rgba(0,242,254,.45));
}
.logo-text-wrap .logo-name{
    font-size:1.45rem;font-weight:800;letter-spacing:2.5px;line-height:1;
    background:linear-gradient(90deg,#00f2fe,#4facfe);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-size:200%;animation:shimmer 4s linear infinite;
}
.logo-text-wrap .logo-sub{
    font-size:.58rem;color:rgba(255,255,255,.3);letter-spacing:1.8px;
    text-transform:uppercase;margin-top:2px;
}

/* Heading */
.auth-head{text-align:center;margin-bottom:26px}
.auth-head h1{font-size:1.45rem;font-weight:700;color:#fff;margin-bottom:5px}
.auth-head p{font-size:.8rem;color:rgba(255,255,255,.38);font-weight:300;line-height:1.5}

/* Alert */
.alert{
    display:flex;align-items:flex-start;gap:10px;
    padding:12px 14px;border-radius:10px;
    font-size:.8rem;line-height:1.5;margin-bottom:20px;
}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.alert-error i{color:#ef4444;flex-shrink:0;margin-top:1px}
.alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#86efac}
.alert-success i{color:#22c55e;flex-shrink:0;margin-top:1px}

/* Form */
.form-group{margin-bottom:16px}
.form-group label{
    display:block;font-size:.72rem;font-weight:600;
    color:rgba(0,242,254,.7);letter-spacing:.6px;text-transform:uppercase;
    margin-bottom:7px;
}
.input-wrap{position:relative}
.input-wrap .input-icon{
    position:absolute;left:13px;top:50%;transform:translateY(-50%);
    color:rgba(255,255,255,.2);font-size:.85rem;pointer-events:none;
}
.form-group input{
    width:100%;padding:12px 14px 12px 40px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:10px;color:#e9edef;
    font-family:'Outfit',sans-serif;font-size:.88rem;
    outline:none;transition:border-color .25s,box-shadow .25s,background .25s;
    -webkit-appearance:none;
}
.form-group input:focus{
    border-color:rgba(0,242,254,.45);
    background:rgba(0,242,254,.04);
    box-shadow:0 0 0 3px rgba(0,242,254,.09);
}
.form-group input::placeholder{color:rgba(255,255,255,.18)}
.form-group input:-webkit-autofill{
    -webkit-box-shadow:0 0 0 100px rgba(11,20,30,.95) inset!important;
    -webkit-text-fill-color:#e9edef!important;
    caret-color:#e9edef;
}
/* Password toggle */
.toggle-pass{
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:rgba(255,255,255,.25);
    cursor:pointer;font-size:.85rem;padding:4px;
    transition:color .2s;
}
.toggle-pass:hover{color:rgba(0,242,254,.7)}

/* Submit button */
.btn-submit{
    width:100%;padding:13px;margin-top:6px;
    background:linear-gradient(135deg,#00f2fe,#4facfe);
    border:none;border-radius:12px;
    color:#040d14;font-family:'Outfit',sans-serif;
    font-size:.9rem;font-weight:700;cursor:pointer;
    letter-spacing:.3px;
    box-shadow:0 6px 24px rgba(0,242,254,.28);
    transition:box-shadow .25s,transform .2s,opacity .2s;
    display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit:hover{box-shadow:0 8px 32px rgba(0,242,254,.42);transform:translateY(-1px)}
.btn-submit:active{transform:translateY(0);opacity:.9}
.btn-submit .spinner{
    width:16px;height:16px;border:2px solid rgba(0,0,0,.3);
    border-top-color:#000;border-radius:50%;
    animation:spin .7s linear infinite;display:none;
}
.btn-submit.loading .btn-label{display:none}
.btn-submit.loading .spinner{display:block}

/* Divider */
.auth-divider{
    display:flex;align-items:center;gap:12px;margin:20px 0;
    font-size:.72rem;color:rgba(255,255,255,.2);
}
.auth-divider::before,.auth-divider::after{
    content:'';flex:1;height:1px;background:rgba(255,255,255,.07);
}

/* Footer links */
.auth-links{text-align:center;margin-top:22px}
.auth-links p{font-size:.78rem;color:rgba(255,255,255,.32);line-height:1.8}
.auth-links a{color:rgba(0,242,254,.8);font-weight:600;text-decoration:none;transition:color .2s}
.auth-links a:hover{color:#00f2fe}

/* Security note */
.security-note{
    display:flex;align-items:center;justify-content:center;gap:6px;
    margin-top:22px;
    font-size:.68rem;color:rgba(255,255,255,.2);
}
.security-note i{color:rgba(0,242,254,.4);font-size:.7rem}
</style>
</head>
<body>

<a href="<?= $baseUrl ?>/" class="back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to Home
</a>

<div class="auth-card">

    <!-- Logo -->
    <div class="logo-wrap">
        <img src="<?= $baseUrl ?>/public/img/icon.svg" alt="Kotha">
        <div class="logo-text-wrap">
            <div class="logo-name">KOTHA</div>
            <div class="logo-sub">Secure Messaging</div>
        </div>
    </div>

    <!-- Heading -->
    <div class="auth-head">
        <h1>Welcome back</h1>
        <p>Sign in to your secure corporate workspace</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form action="<?= $baseUrl ?>/login" method="POST" id="loginForm" novalidate>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Corporate Email</label>
            <div class="input-wrap">
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" id="email" name="email"
                       value="<?= e($email ?? '') ?>"
                       placeholder="name@company.com"
                       required autocomplete="email"
                       inputmode="email">
            </div>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       required autocomplete="current-password">
                <button type="button" class="toggle-pass" onclick="togglePass('password', this)" tabindex="-1" aria-label="Show password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="loginBtn">
            <span class="btn-label"><i class="fa-solid fa-right-to-bracket"></i> &nbsp;Sign In</span>
            <div class="spinner"></div>
        </button>

    </form>

    <div class="auth-links">
        <p>Don't have an account? <a href="<?= $baseUrl ?>/registration">Request Access</a></p>
    </div>

    <div class="security-note">
        <i class="fa-solid fa-shield-halved"></i>
        PIN-protected &nbsp;·&nbsp; Zero logs &nbsp;·&nbsp; End-to-end
    </div>

</div>

<script>
function togglePass(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>
</body>
</html>
