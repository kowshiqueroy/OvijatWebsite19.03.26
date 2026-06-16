    </main>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">
                        <?php 
                            $comp_name = get_setting($pdo, 'company_name', 'Bolakausa');
                            $name_parts = explode(' ', $comp_name, 2);
                            $first_part = $name_parts[0];
                            $second_part = $name_parts[1] ?? '';
                            echo $first_part; 
                            if($second_part) echo "<span>$second_part</span>";
                        ?>
                    </div>
                    <p style="color: #94a3b8; font-size: 0.875rem;">
                        <?php echo e(get_setting($pdo, 'company_address', 'The definitive choice for wholesale grocery and food supply chain management.')); ?>
                    </p>
                </div>
                <div>
                    <h4 style="margin-bottom: 1.5rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Platform</h4>
                    <ul class="footer-links">
                        <li><a href="/bolakausa/home">Product Search</a></li>
                        <li><a href="/bolakausa/login">Member Portal</a></li>
                        <li><a href="/bolakausa/register">Wholesale Partnership</a></li>
                    </ul>
                </div>
                <div>
                    <h4 style="margin-bottom: 1.5rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-phone" style="margin-right: 8px;"></i> <?php echo e(get_setting($pdo, 'company_phone', '+1 234 567 890')); ?></a></li>
                        <li><a href="#"><i class="fas fa-envelope" style="margin-right: 8px;"></i> <?php echo e(get_setting($pdo, 'company_email', 'help@bolakausa.com')); ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo date('Y'); ?> <?php echo e(get_setting($pdo, 'company_name', 'Bolakausa Enterprise')); ?>. All Rights Reserved.
            </div>
        </div>
    </footer>
    
    </div> <!-- Close main-content -->
</div> <!-- Close app-layout -->
</body>
</html>
