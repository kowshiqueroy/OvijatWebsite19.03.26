    </main>

    <footer style="background: white; border-top: 1px solid var(--border-light); padding: 4rem 2rem 2rem 2rem; margin-top: 5rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0; box-shadow: 0 -10px 40px -10px rgba(0,0,0,0.02); width: 100%;">
        <div class="container" style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 3rem; width: 100%; max-width: 1200px; margin: 0 auto; flex-wrap: wrap;">
            <!-- Column 1: Brand & Logo -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php $base_path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']); ?>
                <a href="/bolakausa/home" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    <img src="<?php echo $base_path; ?>public/images/logo/logoofbolakausa.png" alt="Bolakausa Logo" style="max-height: 38px; width: auto; object-fit: contain;">
                </a>
                <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; max-width: 280px; font-weight: 500;">
                    BolakaUSA.com is a premier B2B wholesale platform supplying high-quality food, fresh produce cases, and restaurant supplies directly to kitchens and grocery retailers.
                </p>
                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 1.5rem;">
                    &copy; <?php echo date('Y'); ?> <?php echo e(get_setting($pdo, 'company_name', 'Bolakausa Wholesale')); ?>. All Rights Reserved.
                </span>
            </div>
            <!-- Column 2: Contact Details -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--secondary); font-size: 0.95rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Contact Information</h4>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.825rem; color: var(--text-main); font-weight: 500;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fas fa-phone-alt" style="color: var(--primary); width: 16px;"></i>
                        <span>+1 (800) 555-FOOD</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fas fa-envelope" style="color: var(--primary); width: 16px;"></i>
                        <a href="mailto:support@bolakausa.com" style="color: inherit; text-decoration: none;">support@bolakausa.com</a>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 0.6rem; line-height: 1.45;">
                        <i class="fas fa-map-marker-alt" style="color: var(--primary); width: 16px; margin-top: 0.2rem;"></i>
                        <span>100 Logistics Parkway, Suite 500<br>New York, NY 10001</span>
                    </div>
                </div>
            </div>
            <!-- Column 3: Navigation Links -->
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--secondary); font-size: 0.95rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Wholesale Portal</h4>
                <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.825rem; font-weight: 600;" class="footer-links">
                    <a href="/bolakausa/home" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="fas fa-store" style="margin-right: 4px; font-size: 0.75rem;"></i> Wholesale Catalog</a>
                    <a href="/bolakausa/login" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="fas fa-sign-in-alt" style="margin-right: 4px; font-size: 0.75rem;"></i> Partner Login Portal</a>
                    <a href="/bolakausa/register" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="fas fa-user-plus" style="margin-right: 4px; font-size: 0.75rem;"></i> Apply for B2B Account</a>
                    <a href="/bolakausa/wallet" style="color: var(--text-muted); text-decoration: none; transition: color 0.2s;"><i class="fas fa-wallet" style="margin-right: 4px; font-size: 0.75rem;"></i> Digital Wallet Top-up</a>
                </div>
            </div>
        </div>
    </footer>
    
    </div> <!-- Close main-content -->
</div> <!-- Close app-layout -->
</body>
</html>
