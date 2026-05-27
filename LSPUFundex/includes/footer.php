<?php
// ============================================
// LSPUFundex - Reusable Footer
// File: includes/footer.php
// Location: C:\xampp\htdocs\LSPUFundex\includes\
// PURPOSE: Included at the BOTTOM of every page.
// Closes the layout divs opened in header.php
// and loads all JavaScript files.
// ============================================

if (!defined('LSPUFUNDEX')) {
    require_once __DIR__ . '/../config/app.php';
}
?>

        </div><!-- end .content-inner -->
    </main><!-- end .main-content -->

</div><!-- end .page-wrapper -->

<!-- ============ FOOTER BAR ============ -->
<footer class="lspu-footer">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-12 text-center">
                <span class="text-muted small">
                    &copy; <?php echo date('Y'); ?> <strong>Laguna State Polytechnic University</strong>

                </span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>

<!-- Inline Scripts (optional per page) -->
<?php if (isset($extraJS)) echo $extraJS; ?>

</body>
</html>