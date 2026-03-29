<?php
/**
 * Footer Template
 * Core PHP Employee Management System
 */
?>

<?php if (defined('IS_ADMIN_PAGE') && IS_ADMIN_PAGE): ?>
        <footer class="text-center text-muted py-4 mt-4">
            <small>&copy; <?php echo date('Y'); ?> HR Management System. All rights reserved.</small>
        </footer>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(dropdown => {
            new bootstrap.Dropdown(dropdown);
        });
        
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
    
    function calculateAge() {
        const dobInput = document.getElementById('dob');
        const ageInput = document.getElementById('age');
        if (dobInput && ageInput && dobInput.value) {
            const dob = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            ageInput.value = age;
        }
    }
    
    if (document.getElementById('dob')) {
        document.getElementById('dob').addEventListener('change', calculateAge);
    }
    
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
</script>

</body>
</html>
