        </div> <!-- end container-fluid -->
    </div> <!-- end content -->
</div> <!-- end wrapper -->

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>

<script>
    $(document).ready(function () {
        $('#sidebarCollapse').on('click', function () {
            $('#sidebar').toggleClass('active');
            $('#content').toggleClass('active');
        });

        // Initialize Select2 for all selects
        $('.form-select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search...',
            allowClear: true
        });
    });
</script>

</body>
</html>
