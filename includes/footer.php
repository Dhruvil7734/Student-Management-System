<?php
/**
 * Close the layout and load scripts.
 * Set $use_charts = true on pages that render Chart.js charts.
 */
$use_charts = $use_charts ?? false;
?>
        </main>

        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span>
            <span class="text-muted">Built with PHP, MySQL &amp; Bootstrap</span>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($use_charts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
