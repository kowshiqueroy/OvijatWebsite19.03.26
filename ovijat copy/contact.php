<?php
require_once 'config.php';
$currentPage = 'contact';
$pageTitle   = 'Contact Us — Ovijat Food & Beverage Industries Ltd.';

$pdo = getPDO();
$msg = $err = '';

// Ensure contact_info table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS contact_info (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(100),
    email VARCHAR(150),
    whatsapp VARCHAR(50),
    show_header TINYINT(1) NOT NULL DEFAULT 1,
    show_footer TINYINT(1) NOT NULL DEFAULT 1,
    show_contact_page TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$contactInfos = $pdo->query(
    "SELECT * FROM contact_info WHERE is_active=1 AND show_contact_page=1 ORDER BY sort_order"
)->fetchAll();

// Fallback to settings if no contact_info entries
if (!$contactInfos) {
    $cs = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('contact_email','contact_phone','contact_address')")->fetchAll();
    $cs = array_column($cs, 'value', 'key');
    $contactInfos = [[
        'label'    => 'Head Office',
        'address'  => $cs['contact_address'] ?? '',
        'phone'    => $cs['contact_phone'] ?? '',
        'email'    => $cs['contact_email'] ?? '',
        'whatsapp' => '',
    ]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $err = 'Name, email, and message are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } elseif (!checkRateLimit($ip)) {
        $err = 'You have reached the daily submission limit. Please try again tomorrow.';
    } else {
        $pdo->prepare(
            "INSERT INTO contact_submissions (name,email,phone,subject,message,ip_address) VALUES (?,?,?,?,?,?)"
        )->execute([$name,$email,$phone,$subject,$message,$ip]);
        $msg = 'Thank you! Your message has been received. We will get back to you shortly.';
    }
}

require_once 'includes/header.php';
?>

<!-- Hero -->
<section style="background:var(--clr-dark);padding:4.5rem 0 3.5rem;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 70% 50%,rgba(201,168,76,.07) 0%,transparent 60%);pointer-events:none;"></div>
  <div class="container" style="position:relative;z-index:1;text-align:center;">
    <p style="font-family:var(--ff-ui);font-size:.75rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:.75rem;">Get In Touch</p>
    <h1 class="section-title light">Contact <em style="font-style:italic;color:var(--clr-gold);">Us</em></h1>
    <span class="title-rule" aria-hidden="true"></span>
    <p class="section-subtitle light">We'd love to hear from you — export inquiries, partnerships, or any question.</p>
  </div>
</section>

<!-- Contact Cards + Form -->
<section style="background:var(--clr-offwhite);padding:4rem 0 5rem;">
  <div class="container">

    <!-- Contact Info Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.25rem;margin-bottom:3rem;">
      <?php foreach ($contactInfos as $ci): ?>
      <div style="background:var(--clr-white);border-radius:8px;padding:1.5rem;box-shadow:var(--shadow-sm);border-top:3px solid var(--clr-gold);">
        <p style="font-family:var(--ff-ui);font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--clr-gold);margin-bottom:1rem;">
          <?= e($ci['label']) ?>
        </p>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.75rem;">
          <?php if ($ci['address']): ?>
          <li style="display:flex;gap:.6rem;align-items:flex-start;font-family:var(--ff-body);font-size:.875rem;color:var(--clr-text);">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px;" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?= e($ci['address']) ?></span>
          </li>
          <?php endif; ?>
          <?php if ($ci['phone']): ?>
          <li style="display:flex;gap:.6rem;align-items:center;font-size:.875rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-15.74-15.74A2 2 0 0 1 5.09 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <a href="tel:<?= e(preg_replace('/\s+/','',$ci['phone'])) ?>" style="color:var(--clr-text);"><?= e($ci['phone']) ?></a>
          </li>
          <?php endif; ?>
          <?php if ($ci['whatsapp']): ?>
          <li style="display:flex;gap:.6rem;align-items:center;font-size:.875rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="var(--clr-gold)" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/','',$ci['whatsapp'])) ?>" target="_blank" rel="noopener" style="color:var(--clr-text);"><?= e($ci['whatsapp']) ?></a>
          </li>
          <?php endif; ?>
          <?php if ($ci['email']): ?>
          <li style="display:flex;gap:.6rem;align-items:center;font-size:.875rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
            <a href="mailto:<?= e($ci['email']) ?>" style="color:var(--clr-text);"><?= e($ci['email']) ?></a>
          </li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Contact Form -->
    <div class="contact-form-wrap" style="background:var(--clr-white);border-radius:10px;box-shadow:var(--shadow-md);padding:2.5rem;max-width:760px;margin:0 auto;border-top:4px solid var(--clr-gold);">
      <h2 style="font-family:var(--ff-heading);font-size:1.6rem;color:var(--clr-dark);margin-bottom:.4rem;">Send a Message</h2>
      <p style="color:var(--clr-muted);font-size:.875rem;margin-bottom:2rem;">Fill in the form and we'll get back to you within 24 hours.</p>

      <?php if ($msg): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endif; ?>

      <?php if (!$msg): ?>
      <form method="POST" novalidate>
        <?= csrfField() ?>
        <div class="contact-form-inner" style="display:grid;grid-template-columns:1fr 1fr;gap:1.1rem;">
          <div class="form-group">
            <label class="form-label" for="cf-name">Full Name *</label>
            <input class="form-control" id="cf-name" name="name" required
                   value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name">
          </div>
          <div class="form-group">
            <label class="form-label" for="cf-email">Email Address *</label>
            <input class="form-control" id="cf-email" name="email" type="email" required
                   value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
          </div>
          <div class="form-group">
            <label class="form-label" for="cf-phone">Phone</label>
            <input class="form-control" id="cf-phone" name="phone" type="tel"
                   value="<?= e($_POST['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="cf-subject">Subject</label>
            <input class="form-control" id="cf-subject" name="subject"
                   value="<?= e($_POST['subject'] ?? '') ?>">
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label" for="cf-message">Your Message *</label>
            <textarea class="form-control" id="cf-message" name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <div style="grid-column:1/-1;">
            <button type="submit" class="btn btn-primary">
              Send Message
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
