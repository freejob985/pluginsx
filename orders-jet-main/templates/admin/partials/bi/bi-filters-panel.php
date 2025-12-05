<?php
/**
 * BI Filters Panel (Enhanced Filters)
 */
if (!defined('ABSPATH')) exit;
?>

<!-- BI Filters Overlay (Following Orders Master Pattern) -->
<div class="oj-bi-filters-overlay" id="oj-bi-filters-overlay">
    <div class="oj-bi-filters-panel" id="oj-bi-filters-panel">
    <div class="oj-filters-content">
        <div class="oj-filters-header">
            <h3><?php _e('🎯 Business Intelligence Filters', 'orders-jet'); ?></h3>
            <button class="oj-close-filters" id="oj-close-bi-filters">✕</button>
        </div>
        
        <div class="oj-filters-placeholder">
            <p><?php _e('BI filters ready for implementation:', 'orders-jet'); ?></p>
            <ul>
                <li>✅ <?php _e('Staff Assignment Filter (inherited from Orders Master)', 'orders-jet'); ?></li>
                <li><?php _e('🕐 Shift Analysis Filter', 'orders-jet'); ?></li>
                <li><?php _e('💰 Discount Intelligence Filter', 'orders-jet'); ?></li>
                <li><?php _e('📅 Advanced Date Ranges', 'orders-jet'); ?></li>
            </ul>
            <p><strong><?php _e('Note:', 'orders-jet'); ?></strong> <?php _e('The staff assignment filter (assigned_waiter, unassigned_only) is now available in both Orders Master and BI systems through inheritance.', 'orders-jet'); ?></p>
        </div>
        
        <div class="oj-filters-footer">
            <button class="button button-primary"><?php _e('Apply BI Filters', 'orders-jet'); ?></button>
            <button class="button button-secondary"><?php _e('Reset Filters', 'orders-jet'); ?></button>
        </div>
    </div>
    </div>
</div>
