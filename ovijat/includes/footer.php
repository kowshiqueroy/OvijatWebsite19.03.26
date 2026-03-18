<?php
// includes/footer.php
$pdo = getPDO();

// Ensure contact_info table exists (safe guard)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Settings
$stmtF = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN
  ('site_name','facebook_url','linkedin_url','youtube_url','about_short','site_logo','site_logo_2')");
$fs    = array_column($stmtF->fetchAll(), 'value', 'key');
$logo1 = $fs['site_logo']   ?? '';
$logo2 = $fs['site_logo_2'] ?? '';

// Multi-contact for footer
$footerContacts = $pdo->query(
    "SELECT * FROM contact_info WHERE is_active=1 AND show_footer=1 ORDER BY sort_order LIMIT 6"
)->fetchAll();

// Fallback to settings contact if table empty
if (!$footerContacts) {
    $fallback = $pdo->query(
        "SELECT `key`,`value` FROM settings WHERE `key` IN ('contact_email','contact_phone','contact_address')"
    )->fetchAll();
    $fallback = array_column($fallback, 'value', 'key');
    if ($fallback['contact_phone'] ?? $fallback['contact_email'] ?? '') {
        $footerContacts = [[
            'label'    => 'Head Office',
            'address'  => $fallback['contact_address'] ?? '',
            'phone'    => $fallback['contact_phone']   ?? '',
            'email'    => $fallback['contact_email']   ?? '',
            'whatsapp' => '',
        ]];
    }
}

// Categories
$footerCats = $pdo->query(
    "SELECT name,slug FROM categories WHERE is_active=1 ORDER BY sort_order LIMIT 6"
)->fetchAll();
?>

<footer id="main-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="footer-brand">
        <a href="<?= BASE_URL ?>/" style="display:inline-flex;align-items:center;gap:.65rem;text-decoration:none;margin-bottom:1.1rem;">
          <?php if ($logo1): ?>
            <img src="<?= BASE_URL ?>/uploads/logo/<?= e($logo1) ?>"
                 alt="Ovijat" style="height:46px;max-width:130px;object-fit:contain;">
          <?php else: ?>
            <div class="logo-mark" aria-hidden="true"><span>OFB</span></div>
          <?php endif; ?>
          <div class="logo-text-wrap">
            <span class="logo-brand-name" style="color:var(--clr-gold);">OVIJAT</span>
            <span style="font-family:var(--ff-ui);font-size:.55rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--clr-crimson);white-space:nowrap;">
              Food &amp; Bev. Industries Ltd.
            </span>
          </div>
        </a>
        <p style="font-size:.875rem;line-height:1.8;color:rgba(247,245,240,.65);max-width:280px;">
          <?= e($fs['about_short'] ?? 'Premium food & beverage products from Bangladesh to the world.') ?>
        </p>
        <div class="footer-social" style="margin-top:1rem;">
          <?php if (!empty($fs['facebook_url'])): ?>
            <a href="<?= e($fs['facebook_url']) ?>" target="_blank" rel="noopener" aria-label="Facebook">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
            </a>
          <?php endif; ?>
          <?php if (!empty($fs['linkedin_url'])): ?>
            <a href="<?= e($fs['linkedin_url']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7H10V9h4v2a6 6 0 0 1 6-3zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
            </a>
          <?php endif; ?>
          <?php if (!empty($fs['youtube_url'])): ?>
            <a href="<?= e($fs['youtube_url']) ?>" target="_blank" rel="noopener" aria-label="YouTube">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.96-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div>
        <p class="footer-heading">Quick Links</p>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/">Home</a></li>
          <li><a href="<?= BASE_URL ?>/about.php">About Us</a></li>
          <li><a href="<?= BASE_URL ?>/products.php">Products</a></li>
          <li><a href="<?= BASE_URL ?>/global.php">Global Presence</a></li>
          <li><a href="<?= BASE_URL ?>/contact.php">Contact</a></li>
          <li><a href="<?= BASE_URL ?>/freewifi/">Free WiFi</a></li>
        </ul>
      </div>

      <!-- Product Categories -->
      <div>
        <p class="footer-heading">Products</p>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/products.php">All Products</a></li>
          <?php foreach ($footerCats as $cat): ?>
            <li><a href="<?= BASE_URL ?>/products.php?cat=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Contact Info -->
      <div>
        <p class="footer-heading">Contact Us</p>
        <?php if ($footerContacts): ?>
          <?php foreach ($footerContacts as $idx => $ci): ?>
            <?php if ($idx > 0): ?>
              <hr style="border:none;border-top:1px solid rgba(201,168,76,.12);margin:.9rem 0;">
            <?php endif; ?>
            <?php if (count($footerContacts) > 1): ?>
              <p style="font-family:var(--ff-ui);font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(201,168,76,.55);margin-bottom:.5rem;"><?= e($ci['label']) ?></p>
            <?php endif; ?>
            <ul class="footer-contact" style="display:flex;flex-direction:column;gap:.55rem;">
              <?php if (!empty($ci['address'])): ?>
                <li style="display:flex;gap:.5rem;align-items:flex-start;font-size:.83rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span style="color:rgba(247,245,240,.65);"><?= e($ci['address']) ?></span>
                </li>
              <?php endif; ?>
              <?php if (!empty($ci['phone'])): ?>
                <li style="display:flex;gap:.5rem;align-items:center;font-size:.83rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-15.74-15.74A2 2 0 0 1 5.09 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                  <a href="tel:<?= e(preg_replace('/\s+/','',$ci['phone'])) ?>" style="color:rgba(247,245,240,.65);"><?= e($ci['phone']) ?></a>
                </li>
              <?php endif; ?>
              <?php if (!empty($ci['whatsapp'])): ?>
                <li style="display:flex;gap:.5rem;align-items:center;font-size:.83rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="var(--clr-gold)" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                  <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/','',$ci['whatsapp'])) ?>" target="_blank" rel="noopener" style="color:rgba(247,245,240,.65);"><?= e($ci['whatsapp']) ?></a>
                </li>
              <?php endif; ?>
              <?php if (!empty($ci['email'])): ?>
                <li style="display:flex;gap:.5rem;align-items:center;font-size:.83rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="var(--clr-gold)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
                  <a href="mailto:<?= e($ci['email']) ?>" style="color:rgba(247,245,240,.65);"><?= e($ci['email']) ?></a>
                </li>
              <?php endif; ?>
            </ul>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="font-size:.82rem;color:rgba(247,245,240,.35);">
            Add contact info via<br>Admin → Contact Info.
          </p>
        <?php endif; ?>
      </div>

    </div><!-- /.footer-grid -->

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> Ovijat Group. All rights reserved.</p>
      <p style="display:flex;align-items:center;gap:.3rem;">
        Designed &amp; Developed with
        <span style="color:var(--clr-crimson);font-size:1rem;">&hearts;</span>
        by
        <a href="<?= BASE_URL ?>/admin/login.php"
           class="footer-bottom-admin-link"
           style="color:var(--clr-gold);font-weight:700;">Ovijat IT</a>
      </p>
    </div>

  </div>
</footer>

<!-- ===================== SCRIPTS ===================== -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/countup.js@2.8.0/dist/countUp.umd.js" defer></script>
<script>
(function() {
  'use strict';

  // ── Loading screen: min 2s, wait for full page load ──
  var loader    = document.getElementById('loading-screen');
  var loadStart = Date.now();
  var MIN_MS    = 2000;

  function hideLoader() {
    var waited = Date.now() - loadStart;
    var delay  = Math.max(0, MIN_MS - waited);
    setTimeout(function() {
      if (loader) {
        loader.classList.add('hidden');
        setTimeout(function() { if (loader) loader.style.display = 'none'; }, 700);
      }
    }, delay);
  }
  if (document.readyState === 'complete') { hideLoader(); }
  else { window.addEventListener('load', hideLoader); }

  // ── Run everything after DOM ready ──
  document.addEventListener('DOMContentLoaded', function() {

    // Typed.js taglines on loading screen
    var taglines = <?php
      try {
        $stmtTags = getPDO()->query("SELECT tagline FROM taglines WHERE is_active=1 ORDER BY sort_order");
        echo json_encode(array_column($stmtTags->fetchAll(), 'tagline'));
      } catch (Exception $e) { echo '[]'; }
    ?>;
    if (loader && taglines.length && typeof Typed !== 'undefined') {
      new Typed('#typed-target', {
        strings: taglines, typeSpeed: 60, backSpeed: 30,
        backDelay: 1500, loop: true, showCursor: true, cursorChar: '|'
      });
    }

    // ── Sticky Nav ──
    var nav = document.getElementById('main-nav');
    window.addEventListener('scroll', function() {
      if (nav) nav.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });

    // ── Hamburger / Mobile Menu ──
    var btn  = document.getElementById('hamburger-btn');
    var menu = document.getElementById('mobile-menu');

    window.closeMobileMenu = function() {
      if (btn)  { btn.classList.remove('open'); btn.setAttribute('aria-expanded','false'); }
      if (menu) { menu.classList.remove('open'); menu.setAttribute('aria-hidden','true'); }
      document.body.style.overflow = '';
    };
    if (btn) {
      btn.addEventListener('click', function() {
        var isOpen = menu ? menu.classList.toggle('open') : false;
        btn.classList.toggle('open', isOpen);
        btn.setAttribute('aria-expanded', String(isOpen));
        if (menu) menu.setAttribute('aria-hidden', String(!isOpen));
        document.body.style.overflow = isOpen ? 'hidden' : '';
      });
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') window.closeMobileMenu();
    });

    // ── Touch-device dropdown ──
    document.querySelectorAll('.nav-item').forEach(function(item) {
      var link     = item.querySelector('a');
      var dropdown = item.querySelector('.nav-dropdown');
      if (!link || !dropdown) return;
      link.addEventListener('click', function(e) {
        if (window.matchMedia('(hover: none)').matches) {
          if (!item.classList.contains('touch-open')) {
            e.preventDefault();
            document.querySelectorAll('.nav-item').forEach(function(i) { i.classList.remove('touch-open'); });
            item.classList.add('touch-open');
          }
        }
      });
    });
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.nav-item')) {
        document.querySelectorAll('.nav-item').forEach(function(i) { i.classList.remove('touch-open'); });
      }
    });

    // ── Dual Logo Slider ──
    var lg1 = document.getElementById('logo-img-1');
    var lg2 = document.getElementById('logo-img-2');
    if (lg1 && lg2) {
      var cur = 1;
      setInterval(function() {
        if (cur === 1) {
          lg1.classList.remove('logo-active'); lg2.classList.add('logo-active'); cur = 2;
        } else {
          lg2.classList.remove('logo-active'); lg1.classList.add('logo-active'); cur = 1;
        }
      }, 4000);
    }

    // ── Logo Subtitle Animation (slide up) ──
    var subA = document.getElementById('sub-a');
    var subB = document.getElementById('sub-b');
    if (subA && subB) {
      var showingA = true;
      // Initial state
      subA.className = 'sub-active';
      subB.className = 'sub-enter';

      setInterval(function() {
        if (showingA) {
          // A exits up, B enters from below
          subA.className = 'sub-exit';
          subB.className = 'sub-active';
          showingA = false;
        } else {
          // B exits up, A enters from below
          subB.className = 'sub-exit';
          subA.className = 'sub-active';
          showingA = true;
        }
        // After transition completes, reset the exiting element to enter position
        setTimeout(function() {
          if (showingA) { subB.className = 'sub-enter'; }
          else          { subA.className = 'sub-enter'; }
        }, 700);
      }, 3500);
    }

    // ── Hero Swiper ──
    if (document.querySelector('.hero-swiper')) {
      new Swiper('.hero-swiper', {
        loop: true, speed: 900,
        autoplay: { delay: 5000, disableOnInteraction: false },
        effect: 'fade', fadeEffect: { crossFade: true },
        pagination: { el: '.hero-pagination', clickable: true }
      });
    }

    // ── CountUp Stats ──
    function initCountUp() {
      var CU = (typeof CountUp !== 'undefined') ? CountUp
             : (window.countUp && window.countUp.CountUp) ? window.countUp.CountUp
             : null;
      if (!CU) { setTimeout(initCountUp, 150); return; }

      var els = document.querySelectorAll('[data-countup]');
      if (!els.length) return;

      var io = new IntersectionObserver(function(entries) {
        entries.forEach(function(en) {
          if (!en.isIntersecting) return;
          var el = en.target;
          var end    = parseInt(el.dataset.countup, 10);
          var suffix = el.dataset.suffix || '';
          el.textContent = '0';
          try {
            var cu = new CU(el, end, { duration: 2.5, suffix: suffix, separator: ',', useEasing: true });
            if (!cu.error) cu.start();
            else el.textContent = end + suffix;
          } catch(err) { el.textContent = end + suffix; }
          io.unobserve(el);
        });
      }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

      els.forEach(function(el) { el.textContent = '0'; io.observe(el); });
    }
    initCountUp();

    // ── Map Region Filter ──
    document.querySelectorAll('.region-pill').forEach(function(pill) {
      pill.addEventListener('click', function() {
        document.querySelectorAll('.region-pill').forEach(function(p) { p.classList.remove('active'); });
        pill.classList.add('active');
        var region = pill.dataset.region;
        document.querySelectorAll('.map-dot').forEach(function(dot) {
          dot.classList.toggle('hidden', region !== 'all' && dot.dataset.region !== region);
        });
      });
    });

  }); // end DOMContentLoaded
})();
</script>
</body>
</html>
