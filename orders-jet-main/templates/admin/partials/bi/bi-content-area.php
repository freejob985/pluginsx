<?php
/**
 * BI Content Area (Grouped/Individual Display)
 */
if (!defined('ABSPATH')) exit;
?>

<div class="oj-bi-content-section">
    <div class="oj-bi-content" id="oj-bi-content">
        <?php if ($bi_mode === 'grouped'): ?>
        <!-- Grouped Business Intelligence Data -->
        <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-grouped-data.php'; ?>
        <?php else: ?>
        <!-- Individual Orders Analysis -->
        <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-individual-data.php'; ?>
        <?php endif; ?>
    </div>
</div>
