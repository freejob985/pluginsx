<?php
/**
 * BI Grouped Data Display
 * 
 * Displays grouped business intelligence data with drill-down capabilities
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get grouped data from the BI query builder
$grouped_data = $display_data;
$current_group_by = $group_by;

// Group by labels for display
$group_labels = array(
    'day' => __('Daily Performance', 'orders-jet'),
    'waiter' => __('Staff Performance', 'orders-jet'),
    'shift' => __('Shift Analysis', 'orders-jet'),
    'table' => __('Table Performance', 'orders-jet'),
    'discount_status' => __('Discount Analysis', 'orders-jet')
);

$current_label = isset($group_labels[$current_group_by]) ? $group_labels[$current_group_by] : ucfirst(str_replace('_', ' ', $current_group_by));
?>

<div class="oj-bi-grouped-data">
    <div class="oj-grouped-header">
        <h3 class="oj-grouped-title">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php echo esc_html($current_label); ?>
        </h3>
        <div class="oj-grouped-summary">
            <?php if (!empty($grouped_data)): ?>
                <span class="oj-summary-item">
                    <strong><?php echo count($grouped_data); ?></strong> 
                    <?php echo strtolower($current_label); ?> groups
                </span>
                <span class="oj-summary-item">
                    <strong><?php echo array_sum(array_column($grouped_data, 'count')); ?></strong> 
                    total orders
                </span>
                <span class="oj-summary-item">
                    <strong><?php echo wc_price(array_sum(array_column($grouped_data, 'total_revenue'))); ?></strong> 
                    total revenue
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($grouped_data)): ?>
    <div class="oj-grouped-cards">
        <?php foreach ($grouped_data as $group): ?>
        <div class="oj-group-card" data-group-key="<?php echo esc_attr($group['group_key']); ?>">
            <div class="oj-group-header">
                <h4 class="oj-group-title"><?php echo esc_html($group['group_label']); ?></h4>
                <div class="oj-group-actions">
                    <button class="oj-drill-down-btn" data-group="<?php echo esc_attr($group['group_key']); ?>" title="<?php _e('View detailed orders', 'orders-jet'); ?>">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>
            </div>
            
            <div class="oj-group-metrics">
                <div class="oj-metric">
                    <span class="oj-metric-label"><?php _e('Orders', 'orders-jet'); ?></span>
                    <span class="oj-metric-value"><?php echo number_format($group['count']); ?></span>
                </div>
                
                <div class="oj-metric">
                    <span class="oj-metric-label"><?php _e('Revenue', 'orders-jet'); ?></span>
                    <span class="oj-metric-value"><?php echo wp_kses_post(wc_price($group['total_revenue'])); ?></span>
                </div>
                
                <div class="oj-metric">
                    <span class="oj-metric-label"><?php _e('Avg Order', 'orders-jet'); ?></span>
                    <span class="oj-metric-value"><?php echo wp_kses_post(wc_price($group['avg_order_value'])); ?></span>
                </div>
                
                <?php if ($group['discount_count'] > 0): ?>
                <div class="oj-metric oj-metric-discount">
                    <span class="oj-metric-label"><?php _e('Discounts', 'orders-jet'); ?></span>
                    <span class="oj-metric-value">
                        <?php echo number_format($group['discount_count']); ?> 
                        <small>(<?php echo wp_kses_post(wc_price($group['discount_amount'])); ?>)</small>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="oj-metric">
                    <span class="oj-metric-label"><?php _e('Completion', 'orders-jet'); ?></span>
                    <span class="oj-metric-value"><?php echo number_format($group['completion_rate'], 1); ?>%</span>
                </div>
            </div>
            
            <!-- Group-specific insights -->
            <?php if (!empty($group['metadata'])): ?>
            <div class="oj-group-insights">
                <?php 
                // Display group-specific metadata
                switch ($current_group_by) {
                    case 'waiter':
                        if (isset($group['metadata']['waiter_name'])) {
                            echo '<div class="oj-insight-item">';
                            echo '<span class="dashicons dashicons-admin-users"></span>';
                            echo '<span>' . esc_html($group['metadata']['waiter_name']) . '</span>';
                            echo '</div>';
                        }
                        break;
                        
                    case 'shift':
                        if (isset($group['metadata']['time_range'])) {
                            echo '<div class="oj-insight-item">';
                            echo '<span class="dashicons dashicons-clock"></span>';
                            echo '<span>' . esc_html($group['metadata']['time_range']) . '</span>';
                            echo '</div>';
                        }
                        break;
                        
                    case 'table':
                        if (isset($group['metadata']['table_info'])) {
                            echo '<div class="oj-insight-item">';
                            echo '<span class="dashicons dashicons-location"></span>';
                            echo '<span>' . esc_html($group['metadata']['table_info']) . '</span>';
                            echo '</div>';
                        }
                        break;
                        
                    case 'day':
                        if (isset($group['metadata']['day_of_week'])) {
                            echo '<div class="oj-insight-item">';
                            echo '<span class="dashicons dashicons-calendar-alt"></span>';
                            echo '<span>' . esc_html($group['metadata']['day_of_week']) . '</span>';
                            echo '</div>';
                        }
                        break;
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
    <div class="oj-no-data">
        <div class="oj-no-data-icon">📊</div>
        <h3><?php _e('No Data Available', 'orders-jet'); ?></h3>
        <p><?php _e('No orders found for the selected criteria and grouping.', 'orders-jet'); ?></p>
        <p><?php _e('Try adjusting your filters or date range.', 'orders-jet'); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Drill-down JavaScript -->
<script>
jQuery(document).ready(function($) {
    // Drill-down functionality with proper parameter handling
    $('.oj-drill-down-btn').on('click', function(e) {
        e.preventDefault();
        
        const groupKey = $(this).data('group');
        const currentUrl = new URL(window.location.href);
        
        // Switch to individual mode with drill-down
        currentUrl.searchParams.set('bi_mode', 'individual');
        currentUrl.searchParams.set('drill_down_group', groupKey);
        currentUrl.searchParams.set('group_by', '<?php echo esc_js($current_group_by); ?>');
        
        // CRITICAL: Reset pagination for drill-down
        currentUrl.searchParams.delete('paged');
        
        // Log drill-down action for debugging
        console.log('BI Drill-Down: Group Key =', groupKey, 'Group By =', '<?php echo esc_js($current_group_by); ?>');
        
        // Navigate to filtered individual view
        window.location.href = currentUrl.toString();
    });
    
    // Card hover effects
    $('.oj-group-card').hover(
        function() {
            $(this).addClass('oj-card-hover');
        },
        function() {
            $(this).removeClass('oj-card-hover');
        }
    );
    
    console.log('BI Grouped Data loaded with <?php echo count($grouped_data); ?> groups');
});
</script>
