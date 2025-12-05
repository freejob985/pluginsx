<?php
/**
 * BI Mode Toggle Component - Simple 3-Row Design
 * 
 * Row 1: Analysis Mode
 * Row 2: Group Business Data By + BI Filter Bar  
 * Row 3: Business Data Analysis
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current mode and group by values
$current_bi_mode = isset($_GET['bi_mode']) ? sanitize_text_field($_GET['bi_mode']) : 'grouped';
$current_group_by = isset($_GET['group_by']) ? sanitize_text_field($_GET['group_by']) : 'day';

// Available grouping options (simplified)
$grouping_options = array(
    'day' => __('Daily Performance', 'orders-jet'),
    'waiter' => __('Staff Performance', 'orders-jet'),
    'shift' => __('Shift Analysis', 'orders-jet'),
    'table' => __('Table Performance', 'orders-jet'),
    'discount_status' => __('Discount Analysis', 'orders-jet')
);
?>

<!-- Simplified Tabs Bar -->
<div class="oj-bi-tabs-bar">
    <div class="oj-mode-toggle-simple">
        <button type="button" class="oj-mode-btn-simple <?php echo $current_bi_mode === 'grouped' ? 'active' : ''; ?>" 
                data-mode="grouped" id="oj-grouped-mode-btn">
            <?php _e('Grouped Reports', 'orders-jet'); ?>
        </button>
        
        <button type="button" class="oj-mode-btn-simple <?php echo $current_bi_mode === 'individual' ? 'active' : ''; ?>" 
                data-mode="individual" id="oj-individual-mode-btn">
            <?php _e('Individual Orders', 'orders-jet'); ?>
        </button>
    </div>
</div>

<!-- Controls Row -->
<div class="oj-bi-controls-row">
    <div class="oj-controls-left">
        <div class="oj-group-by-simple" id="oj-group-by-simple" <?php echo $current_bi_mode === 'individual' ? 'style="display: none;"' : ''; ?>>
            <label for="oj-group-by-select" class="oj-control-label"><?php _e('Group by:', 'orders-jet'); ?></label>
            <select id="oj-group-by-select" class="oj-select-simple">
                <?php foreach ($grouping_options as $option_key => $option_label): ?>
                <option value="<?php echo esc_attr($option_key); ?>" <?php selected($current_group_by, $option_key); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div class="oj-controls-right">
        <button type="button" class="oj-btn-simple oj-btn-filters" id="oj-open-bi-filters">
            <span class="dashicons dashicons-filter"></span>
            <?php _e('BI Filters', 'orders-jet'); ?>
        </button>
    </div>
</div>

<!-- Simple JavaScript for Mode Toggle -->
<script>
jQuery(document).ready(function($) {
    // Mode button toggle
    $('.oj-mode-btn-simple').on('click', function() {
        const mode = $(this).data('mode');
        
        // Update active state
        $('.oj-mode-btn-simple').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide group by section
        if (mode === 'grouped') {
            $('#oj-group-by-simple').slideDown(200);
        } else {
            $('#oj-group-by-simple').slideUp(200);
        }
        
        // Auto-apply changes
        applyChanges(mode);
    });
    
    // Group by dropdown change
    $('#oj-group-by-select').on('change', function() {
        const groupBy = $(this).val();
        applyChanges('grouped', groupBy);
    });
    
        function applyChanges(mode, groupBy) {
            const currentUrl = new URL(window.location.href);
            
            // Set mode
            if (mode) {
                currentUrl.searchParams.set('bi_mode', mode);
            }
            
            // Handle grouping based on mode
            if (mode === 'grouped' && groupBy) {
                currentUrl.searchParams.set('group_by', groupBy);
            } else if (mode === 'individual') {
                // Keep group_by for drill-down context, but remove if no drill-down
                if (!currentUrl.searchParams.has('drill_down_group')) {
                    currentUrl.searchParams.delete('group_by');
                }
            } else if (groupBy) {
                currentUrl.searchParams.set('group_by', groupBy);
            }
            
            // CRITICAL: Reset pagination when changing modes or grouping
            currentUrl.searchParams.delete('paged');
            
            // Clear conflicting filters when switching modes
            if (mode === 'grouped') {
                // Grouped mode: clear individual-specific parameters
                currentUrl.searchParams.delete('drill_down_group');
            }
            
            // Navigate to new URL
            window.location.href = currentUrl.toString();
        }
    
    console.log('Simple BI Mode Toggle loaded');
});
</script>
