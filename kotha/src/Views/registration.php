<?php $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Kotha</title>
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
    align-items:center;justify-content:flex-start;
    padding:24px 16px 40px;
    position:relative;overflow-x:hidden;
}
body::before{
    content:'';position:fixed;inset:0;z-index:0;
    background:
        radial-gradient(ellipse 55% 45% at 20% 20%,rgba(0,242,254,.11) 0%,transparent 65%),
        radial-gradient(ellipse 45% 55% at 80% 80%,rgba(79,172,254,.09) 0%,transparent 65%),
        radial-gradient(ellipse 35% 40% at 60% 30%,rgba(124,58,237,.06) 0%,transparent 70%),
        #070d14;
    background-size:200% 200%;
    animation:meshShift 20s ease infinite;
}
body::after{
    content:'';position:fixed;inset:0;z-index:0;
    background-image:
        linear-gradient(rgba(0,242,254,.025) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,242,254,.025) 1px,transparent 1px);
    background-size:52px 52px;
    mask-image:radial-gradient(ellipse 80% 80% at center,black,transparent);
}
@keyframes meshShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes cardIn{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes checkPop{0%{transform:scale(0);opacity:0}60%{transform:scale(1.3)}100%{transform:scale(1);opacity:1}}

.back-link{
    position:fixed;top:18px;left:18px;z-index:10;
    display:flex;align-items:center;gap:6px;
    font-size:.75rem;color:rgba(255,255,255,.35);font-weight:500;
    text-decoration:none;transition:color .2s;font-family:'Outfit',sans-serif;
}
.back-link:hover{color:rgba(0,242,254,.8)}
.back-link i{font-size:.7rem}

/* Card */
.auth-card{
    position:relative;z-index:1;
    width:100%;max-width:480px;
    background:rgba(11,20,30,.88);
    border:1px solid rgba(0,242,254,.12);
    border-radius:22px;
    padding:28px 24px;
    backdrop-filter:blur(24px) saturate(160%);
    -webkit-backdrop-filter:blur(24px) saturate(160%);
    box-shadow:0 24px 60px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04),0 0 40px rgba(0,242,254,.04);
    animation:cardIn .5s cubic-bezier(.22,.6,.36,1) both;
    margin-top:14px;
}
@media(min-width:560px){.auth-card{padding:32px 32px 28px}}

/* Logo */
.logo-wrap{
    display:flex;align-items:center;justify-content:center;
    gap:10px;margin-bottom:22px;
}
.logo-wrap img{
    width:38px;height:38px;
    filter:drop-shadow(0 4px 14px rgba(0,242,254,.45));
}
.logo-text-wrap .logo-name{
    font-size:1.35rem;font-weight:800;letter-spacing:2.5px;line-height:1;
    background:linear-gradient(90deg,#00f2fe,#4facfe);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-size:200%;animation:shimmer 4s linear infinite;
}
.logo-text-wrap .logo-sub{
    font-size:.58rem;color:rgba(255,255,255,.3);letter-spacing:1.8px;
    text-transform:uppercase;margin-top:2px;
}

/* Heading */
.auth-head{text-align:center;margin-bottom:22px}
.auth-head h1{font-size:1.35rem;font-weight:700;color:#fff;margin-bottom:5px}
.auth-head p{font-size:.78rem;color:rgba(255,255,255,.38);font-weight:300;line-height:1.5}

/* Progress steps */
.reg-steps{
    display:flex;align-items:center;justify-content:center;
    gap:0;margin-bottom:26px;
}
.step-item{
    display:flex;flex-direction:column;align-items:center;gap:4px;
    flex:1;position:relative;
}
.step-item:not(:last-child)::after{
    content:'';position:absolute;top:14px;left:calc(50% + 14px);right:calc(-50% + 14px);
    height:1px;background:rgba(255,255,255,.08);
}
.step-item.done:not(:last-child)::after{background:rgba(0,242,254,.25)}
.step-dot{
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:.7rem;font-weight:700;
    border:1.5px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.04);color:rgba(255,255,255,.3);
    transition:all .3s;position:relative;z-index:1;
}
.step-item.active .step-dot{
    border-color:rgba(0,242,254,.5);background:rgba(0,242,254,.1);
    color:rgba(0,242,254,.9);
}
.step-item.done .step-dot{
    border-color:#22c55e;background:rgba(34,197,94,.15);color:#22c55e;
}
.step-label{font-size:.6rem;color:rgba(255,255,255,.25);text-align:center;white-space:nowrap;letter-spacing:.3px}
.step-item.active .step-label{color:rgba(0,242,254,.7)}
.step-item.done .step-label{color:rgba(34,197,94,.7)}

/* Alert */
.alert{
    display:flex;align-items:flex-start;gap:10px;
    padding:12px 14px;border-radius:10px;
    font-size:.8rem;line-height:1.55;margin-bottom:20px;
}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#fca5a5}
.alert-error i{color:#ef4444;flex-shrink:0;margin-top:2px}
.alert-success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#86efac}
.alert-success i{color:#22c55e;flex-shrink:0;margin-top:2px;animation:checkPop .4s ease}

/* Section headings inside form */
.form-section{
    display:flex;align-items:center;gap:10px;
    margin:22px 0 14px;font-size:.7rem;font-weight:700;
    color:rgba(0,242,254,.55);letter-spacing:1.2px;text-transform:uppercase;
}
.form-section::after{content:'';flex:1;height:1px;background:rgba(0,242,254,.08)}
.form-section i{font-size:.75rem}
.form-section:first-of-type{margin-top:0}

/* Form grid — 1 col mobile, 2 col ≥480px */
.form-row{display:grid;grid-template-columns:1fr;gap:0}
@media(min-width:480px){.form-row{grid-template-columns:1fr 1fr;gap:14px}}

/* Form groups */
.form-group{margin-bottom:14px}
.form-group label{
    display:block;font-size:.7rem;font-weight:600;
    color:rgba(0,242,254,.65);letter-spacing:.5px;text-transform:uppercase;
    margin-bottom:6px;
}
.input-wrap{position:relative}
.input-wrap .input-icon{
    position:absolute;left:13px;top:50%;transform:translateY(-50%);
    color:rgba(255,255,255,.18);font-size:.82rem;pointer-events:none;
    transition:color .25s;
}
.form-group input,.form-group select,.form-group textarea{
    width:100%;padding:11px 14px 11px 38px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:10px;color:#e9edef;
    font-family:'Outfit',sans-serif;font-size:.86rem;
    outline:none;transition:border-color .25s,box-shadow .25s,background .25s;
    -webkit-appearance:none;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
    border-color:rgba(0,242,254,.4);
    background:rgba(0,242,254,.03);
    box-shadow:0 0 0 3px rgba(0,242,254,.08);
}
.form-group input:focus + .input-icon,
.input-wrap:focus-within .input-icon{color:rgba(0,242,254,.5)}
.form-group input::placeholder{color:rgba(255,255,255,.16)}
.form-group input:-webkit-autofill{
    -webkit-box-shadow:0 0 0 100px rgba(11,20,30,.98) inset!important;
    -webkit-text-fill-color:#e9edef!important;caret-color:#e9edef;
}
.form-group select{
    padding-left:38px;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(255,255,255,.25)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;background-size:12px;
}
.form-group select option{background:#0d1822;color:#e9edef}
/* No left icon on inputs without icon wrapper */
.form-group input.no-icon,.form-group select.no-icon{padding-left:14px}

/* PIN input — special styling */
.pin-input{
    letter-spacing:8px!important;text-align:center!important;
    font-size:1.1rem!important;font-weight:700!important;
    padding-left:14px!important;
}

/* Password strength bar */
.pw-strength{height:3px;border-radius:2px;margin-top:6px;overflow:hidden;background:rgba(255,255,255,.06)}
.pw-strength-fill{height:100%;border-radius:2px;width:0%;transition:width .4s,background .4s}
.pw-hint{font-size:.65rem;color:rgba(255,255,255,.25);margin-top:4px}

/* Toggle pass */
.toggle-pass{
    position:absolute;right:11px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:rgba(255,255,255,.2);
    cursor:pointer;font-size:.82rem;padding:4px;
    transition:color .2s;line-height:1;
}
.toggle-pass:hover{color:rgba(0,242,254,.7)}

/* Submit */
.btn-submit{
    width:100%;padding:13px;margin-top:8px;
    background:linear-gradient(135deg,#00f2fe,#4facfe);
    border:none;border-radius:12px;
    color:#040d14;font-family:'Outfit',sans-serif;
    font-size:.9rem;font-weight:700;cursor:pointer;letter-spacing:.3px;
    box-shadow:0 6px 24px rgba(0,242,254,.28);
    transition:box-shadow .25s,transform .2s,opacity .2s;
    display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit:hover{box-shadow:0 8px 32px rgba(0,242,254,.42);transform:translateY(-1px)}
.btn-submit:active{transform:translateY(0);opacity:.9}
.spinner{width:16px;height:16px;border:2px solid rgba(0,0,0,.3);border-top-color:#000;border-radius:50%;animation:spin .7s linear infinite;display:none}
.btn-submit.loading .btn-label{display:none}
.btn-submit.loading .spinner{display:block}

/* Hint note */
.approval-note{
    display:flex;align-items:flex-start;gap:8px;
    margin-top:14px;padding:11px 13px;
    background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.15);
    border-radius:9px;font-size:.73rem;color:rgba(255,255,255,.4);line-height:1.55;
}
.approval-note i{color:#f59e0b;margin-top:1px;flex-shrink:0;font-size:.78rem}

/* Footer links */
.auth-links{text-align:center;margin-top:20px}
.auth-links p{font-size:.78rem;color:rgba(255,255,255,.3);line-height:1.8}
.auth-links a{color:rgba(0,242,254,.8);font-weight:600;text-decoration:none;transition:color .2s}
.auth-links a:hover{color:#00f2fe}
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
        <h1>Request Access</h1>
        <p>Complete your profile to join the secure network</p>
    </div>

    <!-- Progress indicator -->
    <div class="reg-steps" id="regSteps">
        <div class="step-item active" id="step1">
            <div class="step-dot"><i class="fa-solid fa-user" style="font-size:.6rem"></i></div>
            <div class="step-label">Identity</div>
        </div>
        <div class="step-item" id="step2">
            <div class="step-dot"><i class="fa-solid fa-id-card" style="font-size:.6rem"></i></div>
            <div class="step-label">Details</div>
        </div>
        <div class="step-item" id="step3">
            <div class="step-dot"><i class="fa-solid fa-lock" style="font-size:.6rem"></i></div>
            <div class="step-label">Security</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($error)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= e($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <?= e($success) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($success)): ?>
    <form action="<?= $baseUrl ?>/registration" method="POST" id="regForm" novalidate>

        <!-- ── Section 1: Identity ── -->
        <div class="form-section" data-step="1">
            <i class="fa-solid fa-user"></i> Identity
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e($fields['full_name'] ?? '') ?>"
                           placeholder="John Doe" required autocomplete="name">
                </div>
            </div>
            <div class="form-group">
                <label for="email">Corporate Email</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email"
                           value="<?= e($fields['email'] ?? '') ?>"
                           placeholder="john@company.com" required
                           autocomplete="email" inputmode="email">
                </div>
            </div>
        </div>

        <!-- ── Section 2: Details ── -->
        <div class="form-section" data-step="2">
            <i class="fa-solid fa-id-card"></i> Details
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-phone input-icon"></i>
                    <input type="tel" id="phone" name="phone"
                           value="<?= e($fields['phone'] ?? '') ?>"
                           placeholder="+8801700000000" required
                           autocomplete="tel" inputmode="tel">
                </div>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-calendar input-icon"></i>
                    <input type="date" id="dob" name="dob"
                           value="<?= e($fields['dob'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="institute">Institute / Department</label>
            <div class="input-wrap">
                <i class="fa-solid fa-building input-icon"></i>
                <input type="text" id="institute" name="institute"
                       value="<?= e($fields['institute'] ?? '') ?>"
                       placeholder="Information Security Dept" required>
            </div>
        </div>

        <div class="form-group">
            <label for="address">Office / Branch Address</label>
            <div class="input-wrap">
                <i class="fa-solid fa-location-dot input-icon"></i>
                <input type="text" id="address" name="address"
                       value="<?= e($fields['address'] ?? '') ?>"
                       placeholder="Level 5, Tower A, HQ Office" required>
            </div>
        </div>

        <!-- ── Section 3: Security ── -->
        <div class="form-section" data-step="3">
            <i class="fa-solid fa-shield-halved"></i> Security Credentials
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Account Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-key input-icon"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Min. 8 characters" required
                           autocomplete="new-password"
                           oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="toggle-pass"
                            onclick="togglePass('password', this)"
                            tabindex="-1" aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <div class="pw-strength"><div class="pw-strength-fill" id="pwFill"></div></div>
                <div class="pw-hint" id="pwHint">At least 8 characters</div>
            </div>
            <div class="form-group">
                <label for="pin">4-Digit Security PIN</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="pin" name="pin"
                           class="pin-input"
                           maxlength="4" placeholder="••••"
                           required pattern="\d{4}"
                           title="PIN must be exactly 4 digits"
                           inputmode="numeric" autocomplete="off">
                </div>
                <div class="pw-hint">Used to unlock camouflaged messages</div>
            </div>
        </div>

        <div class="approval-note">
            <i class="fa-solid fa-clock"></i>
            <span>Your request will be reviewed by an administrator before activation. You'll be able to sign in once approved.</span>
        </div>

        <button type="submit" class="btn-submit" id="regBtn" style="margin-top:18px;">
            <span class="btn-label"><i class="fa-solid fa-user-plus"></i> &nbsp;Submit Registration</span>
            <div class="spinner"></div>
        </button>

    </form>
    <?php endif; ?>

    <div class="auth-links">
        <p>Already have an account? <a href="<?= $baseUrl ?>/login">Sign In</a></p>
    </div>

</div>

<script>
/* Toggle password visibility */
function togglePass(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}

/* PIN: digits only */
document.getElementById('pin').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

/* Password strength */
function checkPasswordStrength(val) {
    const fill = document.getElementById('pwFill');
    const hint = document.getElementById('pwHint');
    let score = 0, label = '', color = '';

    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    if (val.length === 0) { fill.style.width='0%'; hint.textContent='At least 8 characters'; return; }

    const levels = [
        { pct:'20%', color:'#ef4444', label:'Too short' },
        { pct:'40%', color:'#f59e0b', label:'Weak' },
        { pct:'60%', color:'#f59e0b', label:'Fair' },
        { pct:'80%', color:'#22c55e', label:'Strong' },
        { pct:'100%',color:'#00f2fe', label:'Excellent' },
    ];
    const lvl = levels[Math.min(score, 4)];
    fill.style.width    = lvl.pct;
    fill.style.background = lvl.color;
    hint.textContent    = lvl.label;
    hint.style.color    = lvl.color;
}

/* Highlight active step based on focused section */
const sectionSteps = document.querySelectorAll('[data-step]');
const stepEls      = [
    document.getElementById('step1'),
    document.getElementById('step2'),
    document.getElementById('step3'),
];

function getVisibleStep() {
    let last = 0;
    sectionSteps.forEach(s => {
        const rect = s.getBoundingClientRect();
        if (rect.top < window.innerHeight * 0.6) last = parseInt(s.getAttribute('data-step')) - 1;
    });
    return last;
}

function updateSteps(activeIdx) {
    stepEls.forEach((el, i) => {
        el.classList.remove('active', 'done');
        if (i < activeIdx)  el.classList.add('done');
        if (i === activeIdx) el.classList.add('active');
    });
}

// Update on scroll + on input focus
window.addEventListener('scroll', () => updateSteps(getVisibleStep()), { passive: true });
document.querySelectorAll('#regForm input').forEach(inp => {
    inp.addEventListener('focus', () => {
        const section = inp.closest('[data-step]') || inp.closest('form')?.querySelector('[data-step]');
        // walk up to find nearest preceding section header
        let el = inp.parentElement;
        while (el) {
            const prev = el.previousElementSibling;
            if (prev && prev.hasAttribute('data-step')) {
                updateSteps(parseInt(prev.getAttribute('data-step')) - 1);
                break;
            }
            el = el.parentElement;
        }
    });
});
// Initial highlight
updateSteps(0);

/* Loading state on submit */
const form = document.getElementById('regForm');
if (form) {
    form.addEventListener('submit', function() {
        const btn = document.getElementById('regBtn');
        if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    });
}
</script>
</body>
</html>
