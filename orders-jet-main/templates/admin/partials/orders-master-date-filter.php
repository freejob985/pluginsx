<?php
/**
 * Orders Master V2 - Date Range Filter Partial
 * 
 * WooCommerce-style date range filter with presets and custom date picker
 * 
 * @package Orders_Jet
 * @var string $current_filter Current filter
 * @var string $search Search term
 * @var string $orderby Sort field
 * @var string $order Sort direction  
 * @var string $date_preset Date preset
 * @var string $date_from Date from (Y-m-d)
 * @var string $date_to Date to (Y-m-d)
 * @var string $date_range_label Human-readable date range label
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Date Range Filter (WooCommerce Style) -->
<div class="oj-date-range-filter" style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 4px;">
    <div style="margin-bottom: 15px;">
        <strong style="font-size: 14px;">📅 Date Range:</strong>
        <?php if (!empty($date_range_label)): ?>
            <span style="color: #2196f3; margin-left: 10px;"><?php echo esc_html($date_range_label); ?></span>
            <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
               style="margin-left: 10px; color: #d32f2f; text-decoration: none;">✕ Clear</a>
        <?php else: ?>
            <span style="color: #666; margin-left: 10px;">All time</span>
        <?php endif; ?>
    </div>
    
    <div class="oj-date-range-tabs" style="border-bottom: 2px solid #f0f0f0; margin-bottom: 20px;">
        <button type="button" class="oj-date-tab active" data-tab="presets" 
                style="padding: 10px 20px; background: none; border: none; border-bottom: 3px solid #2196f3; color: #2196f3; font-weight: 600; cursor: pointer;">
            Presets
        </button>
        <button type="button" class="oj-date-tab" data-tab="custom" 
                style="padding: 10px 20px; background: none; border: none; border-bottom: 3px solid transparent; color: #666; font-weight: 600; cursor: pointer;">
            Custom
        </button>
    </div>
    
    <!-- Presets Tab -->
    <div class="oj-date-tab-content" data-content="presets" style="display: block;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; max-width: 600px;">
            <div>
                <h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666; text-transform: uppercase;">Current Period</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=today<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'today' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'today' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'today' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Today
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=week_to_date<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'week_to_date' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'week_to_date' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'week_to_date' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Week to date
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=month_to_date<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'month_to_date' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'month_to_date' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'month_to_date' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Month to date
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=quarter_to_date<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'quarter_to_date' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'quarter_to_date' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'quarter_to_date' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Quarter to date
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=year_to_date<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'year_to_date' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'year_to_date' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'year_to_date' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Year to date
                    </a>
                </div>
            </div>
            
            <div>
                <h4 style="margin: 0 0 10px 0; font-size: 13px; color: #666; text-transform: uppercase;">Previous Period</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=yesterday<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'yesterday' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'yesterday' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'yesterday' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Yesterday
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=last_week<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'last_week' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'last_week' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'last_week' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Last week
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=last_month<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'last_month' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'last_month' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'last_month' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Last month
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=last_quarter<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'last_quarter' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'last_quarter' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'last_quarter' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Last quarter
                    </a>
                    <a href="?page=orders-master-v2&filter=<?php echo esc_attr($current_filter); ?>&date_preset=last_year<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?><?php echo $orderby !== 'date_created' ? '&orderby=' . esc_attr($orderby) : ''; ?><?php echo $order !== 'DESC' ? '&order=' . esc_attr($order) : ''; ?>" 
                       class="oj-date-preset-btn <?php echo $date_preset === 'last_year' ? 'active' : ''; ?>"
                       style="padding: 8px 15px; background: <?php echo $date_preset === 'last_year' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $date_preset === 'last_year' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; text-align: left; transition: all 0.2s;">
                        Last year
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Tab -->
    <div class="oj-date-tab-content" data-content="custom" style="display: none;">
        <form method="get" action="" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="orders-master-v2">
            <input type="hidden" name="filter" value="<?php echo esc_attr($current_filter); ?>">
            <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
            <?php endif; ?>
            <?php if ($orderby !== 'date_created'): ?>
                <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
            <?php endif; ?>
            <?php if ($order !== 'DESC'): ?>
                <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
            <?php endif; ?>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-weight: 600;">From:</label>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" 
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-weight: 600;">To:</label>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" 
                       style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <button type="submit" class="button button-primary">Apply Custom Range</button>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.oj-date-tab').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update tab buttons
        $('.oj-date-tab').removeClass('active').css({
            'border-bottom-color': 'transparent',
            'color': '#666'
        });
        $(this).addClass('active').css({
            'border-bottom-color': '#2196f3',
            'color': '#2196f3'
        });
        
        // Update tab content
        $('.oj-date-tab-content').hide();
        $('.oj-date-tab-content[data-content="' + tab + '"]').show();
    });
    
    // Hover effects for preset buttons
    $('.oj-date-preset-btn').hover(
        function() {
            if (!$(this).hasClass('active')) {
                $(this).css('background', '#e3f2fd');
            }
        },
        function() {
            if (!$(this).hasClass('active')) {
                $(this).css('background', '#f5f5f5');
            }
        }
    );
});
</script>

