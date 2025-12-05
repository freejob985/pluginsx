<?php
/**
 * BI Header with Mode Toggle
 */
if (!defined('ABSPATH')) exit;
?>

<div class="oj-bi-header">
    <div class="oj-bi-title-section">
        <h1 class="oj-bi-main-title">
            <span class="dashicons dashicons-chart-line" style="font-size: 32px; vertical-align: middle; margin-right: 10px; color: #2271b1;"></span>
            <?php _e('📈 Business Intelligence', 'orders-jet'); ?>
        </h1>
        <p class="oj-bi-subtitle">
            <?php _e('Transform your orders data into actionable business insights', 'orders-jet'); ?>
        </p>
    </div>
    
    <div class="oj-bi-actions">
        <button class="button button-primary" id="oj-export-bi-report">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export BI Report', 'orders-jet'); ?>
        </button>
        <button class="button button-secondary" id="oj-save-bi-view">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Save View', 'orders-jet'); ?>
        </button>
    </div>
</div>
