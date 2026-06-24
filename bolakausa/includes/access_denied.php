<?php
/**
 * Modern Access Denied / Error Page
 * Call render_access_denied($title, $message, $back_url) to show a styled 403 page.
 * Call render_account_pending() for pending-approval state.
 */

function render_access_denied($title = 'Access Denied', $message = 'You do not have permission to view this page.', $back_url = null) {
    http_response_code(403);
    if ($back_url === null) {
        $back_url = defined('BASE_URL') ? BASE_URL . 'home' : '/bolakausa/home';
    }
    ?>
    <style>
    .error-page-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 2rem 1rem;
        text-align: center;
    }
    .error-icon-ring {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(244,63,94,0.12) 0%, rgba(244,63,94,0.04) 100%);
        border: 2px solid rgba(244,63,94,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.75rem;
        box-shadow: 0 0 0 8px rgba(244,63,94,0.06);
    }
    .error-icon-ring i {
        font-size: 2.5rem;
        color: #f43f5e;
    }
    .error-code {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 5rem;
        font-weight: 800;
        color: #f43f5e;
        line-height: 1;
        letter-spacing: -2px;
        margin-bottom: 0.25rem;
        opacity: 0.15;
    }
    .error-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.65rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.75rem;
        letter-spacing: -0.5px;
    }
    .error-msg {
        color: #64748b;
        font-size: 0.975rem;
        max-width: 420px;
        line-height: 1.65;
        margin-bottom: 2rem;
    }
    .error-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .error-btn-primary {
        background: #10b981;
        color: white;
        padding: 0.7rem 1.6rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background 0.2s;
    }
    .error-btn-primary:hover { background: #059669; }
    .error-btn-outline {
        background: transparent;
        color: #64748b;
        padding: 0.7rem 1.6rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid rgba(15,23,42,0.1);
        transition: all 0.2s;
    }
    .error-btn-outline:hover { background: rgba(15,23,42,0.04); color: #0f172a; }
    </style>
    <div class="error-page-wrap">
        <div class="error-icon-ring">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="error-code">403</div>
        <h1 class="error-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="error-msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="error-actions">
            <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="error-btn-primary">
                <i class="fas fa-arrow-left"></i> Go to Dashboard
            </a>
            <a href="javascript:history.back()" class="error-btn-outline">
                <i class="fas fa-undo"></i> Go Back
            </a>
        </div>
    </div>
    <?php
    exit;
}

function render_account_pending() {
    $back_url = defined('BASE_URL') ? BASE_URL . 'home' : '/bolakausa/home';
    $logout_url = defined('BASE_URL') ? BASE_URL . 'logout' : '/bolakausa/logout';
    ?>
    <style>
    .pending-page-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: 2rem 1rem;
        text-align: center;
    }
    .pending-icon-ring {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(245,158,11,0.12) 0%, rgba(245,158,11,0.04) 100%);
        border: 2px solid rgba(245,158,11,0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.75rem;
        box-shadow: 0 0 0 8px rgba(245,158,11,0.06);
    }
    .pending-icon-ring i { font-size: 2.5rem; color: #f59e0b; }
    .pending-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(245,158,11,0.1);
        color: #d97706;
        border: 1px solid rgba(245,158,11,0.2);
        padding: 0.3rem 0.9rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1.25rem;
    }
    .pending-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.65rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.75rem;
        letter-spacing: -0.5px;
    }
    .pending-msg {
        color: #64748b;
        font-size: 0.975rem;
        max-width: 440px;
        line-height: 1.65;
        margin-bottom: 2rem;
    }
    .pending-steps {
        background: white;
        border: 1px solid rgba(15,23,42,0.07);
        border-radius: 14px;
        padding: 1.5rem 2rem;
        max-width: 400px;
        width: 100%;
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px -4px rgba(15,23,42,0.06);
        text-align: left;
    }
    .pending-steps h4 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }
    .pending-step {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 0.85rem;
        font-size: 0.875rem;
        color: #374151;
    }
    .pending-step:last-child { margin-bottom: 0; }
    .step-dot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(16,185,129,0.1);
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 800;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .pending-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    </style>
    <div class="pending-page-wrap">
        <div class="pending-icon-ring">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="pending-badge"><i class="fas fa-clock"></i> Pending Review</div>
        <h1 class="pending-title">Account Under Review</h1>
        <p class="pending-msg">Your wholesale account has been submitted and is currently being reviewed by our team. You'll receive an email notification once approved.</p>
        <div class="pending-steps">
            <h4>What happens next?</h4>
            <div class="pending-step">
                <div class="step-dot">1</div>
                <span>Our team reviews your business details (usually within 24 hours)</span>
            </div>
            <div class="pending-step">
                <div class="step-dot">2</div>
                <span>You'll receive an email confirmation when approved</span>
            </div>
            <div class="pending-step">
                <div class="step-dot">3</div>
                <span>Log back in to start placing wholesale orders</span>
            </div>
        </div>
        <div class="pending-actions">
            <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="error-btn-primary" style="background:#10b981;color:white;padding:0.7rem 1.6rem;border-radius:10px;font-weight:700;font-size:0.875rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <a href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8'); ?>" style="background:transparent;color:#64748b;padding:0.7rem 1.6rem;border-radius:10px;font-weight:700;font-size:0.875rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;border:1px solid rgba(15,23,42,0.1);">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </div>
    </div>
    <?php
    exit;
}
