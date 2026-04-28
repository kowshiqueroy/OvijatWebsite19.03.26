            </div> <!-- container-fluid -->
        </div> <!-- page-content-wrapper -->
    </div> <!-- wrapper -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled");
        });

        // Keyboard navigation for forms
        $(document).on('keydown', 'input, select', function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                const inputs = $(this).closest('form').find(':input:visible');
                const next = inputs.eq(inputs.index(this) + 1);
                if (next.length) {
                    next.focus();
                } else {
                    $(this).closest('form').submit();
                }
            }
        });
    </script>
</body>
</html>
