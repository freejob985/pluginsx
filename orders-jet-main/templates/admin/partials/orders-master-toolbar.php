<?php
/**
 * Orders Master V2 - Single Row Toolbar
 * 
 * Compact toolbar combining date range, search, status filters, and sort
 * Layout: [Date Range ▼] [🔍 Search...] [📊 Status ▼] [📈 Sort ▼] [Apply] [Reset]
 * 
 * @package Orders_Jet
 * @var string $current_filter Current filter
 * @var string $search Search term
 * @var string $orderby Sort field
 * @var string $order Sort direction
 * @var string $date_preset Date preset
 * @var string $date_from Date from
 * @var string $date_to Date to
 * @var string $date_range_label Human-readable date range label
 * @var array $filter_counts Filter counts array
 * @var array $current_params Current URL parameters
 * @var string $order_type Order type filter
 * @var string $kitchen_type Kitchen type filter
 * @var string $kitchen_status Kitchen status filter
 * @var int $assigned_waiter Assigned waiter filter
 * @var bool $unassigned_only Unassigned orders filter
 */

if (!defined('ABSPATH')) {
    exit;
}

// Build current date range display
$date_display = 'All time';
if (!empty($date_range_label)) {
    $date_display = $date_range_label;
} elseif (!empty($date_from) && !empty($date_to)) {
    $date_display = date('d/m/y', strtotime($date_from)) . ' - ' . date('d/m/y', strtotime($date_to));
} elseif (!empty($date_preset)) {
    $date_display = ucfirst(str_replace('_', ' ', $date_preset));
}

// $has_filters is now defined in main template

// Build reset URL (reset all filters to default)
$reset_params = array(
    'page' => 'orders-master-v2'
    // No other parameters = all filters reset to default
);
$reset_url = add_query_arg($reset_params, admin_url('admin.php'));
?>

<!-- Orders Master Toolbar -->
<div class="oj-master-toolbar">
    <form method="get" action="" class="oj-toolbar-form">
        <input type="hidden" name="page" value="orders-master-v2">
        
        <!-- Hidden inputs for advanced filter parameters -->
        <?php if (!empty($order_type)): ?>
            <input type="hidden" name="order_type" value="<?php echo esc_attr($order_type); ?>">
        <?php endif; ?>
        <?php if (!empty($kitchen_type)): ?>
            <input type="hidden" name="kitchen_type" value="<?php echo esc_attr($kitchen_type); ?>">
        <?php endif; ?>
        <?php if (!empty($kitchen_status)): ?>
            <input type="hidden" name="kitchen_status" value="<?php echo esc_attr($kitchen_status); ?>">
        <?php endif; ?>
        <?php if (!empty($assigned_waiter)): ?>
            <input type="hidden" name="assigned_waiter" value="<?php echo esc_attr($assigned_waiter); ?>">
        <?php endif; ?>
        <?php if ($unassigned_only): ?>
            <input type="hidden" name="unassigned_only" value="1">
        <?php endif; ?>
        <?php if (!empty($payment_method)): ?>
            <input type="hidden" name="payment_method" value="<?php echo esc_attr($payment_method); ?>">
        <?php endif; ?>
        <?php if (!empty($amount_type)): ?>
            <input type="hidden" name="amount_type" value="<?php echo esc_attr($amount_type); ?>">
        <?php endif; ?>
        <?php if (!empty($amount_value) && $amount_value > 0): ?>
            <input type="hidden" name="amount_value" value="<?php echo esc_attr($amount_value); ?>">
        <?php endif; ?>
        <?php if (!empty($amount_min) && $amount_min > 0): ?>
            <input type="hidden" name="amount_min" value="<?php echo esc_attr($amount_min); ?>">
        <?php endif; ?>
        <?php if (!empty($amount_max) && $amount_max > 0): ?>
            <input type="hidden" name="amount_max" value="<?php echo esc_attr($amount_max); ?>">
        <?php endif; ?>
        <?php if (!empty($search)): ?>
            <input type="hidden" name="search" value="<?php echo esc_attr($search); ?>">
        <?php endif; ?>
        <?php if (!empty($orderby) && $orderby !== 'date_created'): ?>
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
        <?php endif; ?>
        <?php if (!empty($order) && $order !== 'DESC'): ?>
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
        <?php endif; ?>
        
        <!-- Date Range Dropdown -->
        <div class="oj-toolbar-group oj-date-group">
            <select name="date_preset" class="oj-toolbar-select oj-date-select" onchange="toggleCustomDates(this)">
                <option value="" <?php selected(empty($date_preset), true); ?>>📅 All time</option>
                <option value="today" <?php selected($date_preset, 'today'); ?>>📅 Today</option>
                <option value="yesterday" <?php selected($date_preset, 'yesterday'); ?>>📅 Yesterday</option>
                <option value="week_to_date" <?php selected($date_preset, 'week_to_date'); ?>>📅 Week to date</option>
                <option value="month_to_date" <?php selected($date_preset, 'month_to_date'); ?>>📅 Month to date</option>
                <option value="last_week" <?php selected($date_preset, 'last_week'); ?>>📅 Last week</option>
                <option value="last_month" <?php selected($date_preset, 'last_month'); ?>>📅 Last month</option>
                <option value="custom" <?php selected($date_preset, 'custom'); ?>>📅 Custom range...</option>
            </select>
            
            <!-- Custom Date Inputs (Hidden by default) -->
            <div class="oj-custom-dates" style="display: <?php echo $date_preset === 'custom' ? 'flex' : 'none'; ?>;">
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" 
                       class="oj-toolbar-input oj-date-input" placeholder="From">
                <span class="oj-date-separator">to</span>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" 
                       class="oj-toolbar-input oj-date-input" placeholder="To">
            </div>
        </div>
        
        <!-- Search Input -->
        <div class="oj-toolbar-group oj-search-group">
            <input type="text" 
                   name="search" 
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="🔍 Search orders..."
                   class="oj-toolbar-input oj-search-input">
        </div>
        
        <!-- Status Filter Dropdown -->
        <div class="oj-toolbar-group oj-status-group">
            <select name="filter" class="oj-toolbar-select oj-status-select" onchange="updateFilterParam(this)">
                <option value="all" <?php selected(empty($current_filter) || $current_filter === 'all', true); ?>>
                    📊 All Orders (<?php echo isset($filter_counts['all']) ? $filter_counts['all'] : 0; ?>)
                </option>
                <option value="active" <?php selected($current_filter, 'active'); ?>>
                    🔥 Active (<?php echo isset($filter_counts['active']) ? $filter_counts['active'] : 0; ?>)
                </option>
                <option value="kitchen" <?php selected($current_filter, 'kitchen'); ?>>
                    👨‍🍳 Kitchen (<?php echo isset($filter_counts['kitchen']) ? $filter_counts['kitchen'] : 0; ?>)
                </option>
                <option value="ready" <?php selected($current_filter, 'ready'); ?>>
                    ✅ Ready (<?php echo isset($filter_counts['ready']) ? $filter_counts['ready'] : 0; ?>)
                </option>
                <option value="completed" <?php selected($current_filter, 'completed'); ?>>
                    ✔️ Completed (<?php echo isset($filter_counts['completed']) ? $filter_counts['completed'] : 0; ?>)
                </option>
            </select>
        </div>
        
        <!-- Order Type Filter -->
        <div class="oj-toolbar-group oj-order-type-group">
            <select name="order_type" class="oj-toolbar-select oj-order-type-select" onchange="updateToolbarFilter(this)">
                <option value="" <?php selected(empty($order_type), true); ?>>🍽️ All Types</option>
                <option value="dinein" <?php selected($order_type, 'dinein'); ?>>🍽️ Dine-in</option>
                <option value="takeaway" <?php selected($order_type, 'takeaway'); ?>>📦 Takeaway</option>
                <option value="delivery" <?php selected($order_type, 'delivery'); ?>>🚚 Delivery</option>
            </select>
        </div>
        
        <!-- Kitchen Type Filter -->
        <div class="oj-toolbar-group oj-kitchen-type-group">
            <select name="kitchen_type" class="oj-toolbar-select oj-kitchen-type-select" onchange="updateToolbarFilter(this)">
                <option value="" <?php selected(empty($kitchen_type), true); ?>>👨‍🍳 All Kitchen</option>
                <option value="food" <?php selected($kitchen_type, 'food'); ?>>🍕 Food</option>
                <option value="beverages" <?php selected($kitchen_type, 'beverages'); ?>>🥤 Beverages</option>
                <option value="mixed" <?php selected($kitchen_type, 'mixed'); ?>>🍽️ Mixed Only</option>
            </select>
        </div>
        
        <!-- Sort Dropdown - Temporarily Disabled -->
        <!-- TODO: Re-enable sorting after advanced filters are complete -->
        <!--
        <div class="oj-toolbar-group oj-sort-group">
            <select name="orderby_order" class="oj-toolbar-select oj-sort-select">
                <option value="date_created_DESC">📈 Newest First</option>
                <option value="date_created_ASC">📈 Oldest First</option>
            </select>
        </div>
        -->
        
        <!-- Action Buttons -->
        <div class="oj-toolbar-group oj-actions-group">
            <button type="submit" class="button button-primary oj-apply-btn">Apply</button>
            <a href="<?php echo admin_url('admin.php?page=orders-master-v2'); ?>" class="button oj-reset-btn">Reset</a>
            
            <!-- Advanced Filters Button -->
            <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/filters-floating-button.php'; ?>
        </div>
    </form>
</div>

<!-- JavaScript functions moved to orders-master-v2.js -->
