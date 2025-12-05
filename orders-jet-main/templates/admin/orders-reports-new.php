<?php
/**
 * Orders Reports - Complete Business Intelligence Dashboard
 * 
 * A comprehensive reporting page with:
 * - Dynamic KPI cards (Total Revenue, Orders, AOV, Status breakdown)
 * - Filter bar (Date range, Product type, Order source, Grouping)
 * - Monthly/Daily Summary table (clickable for drill-down)
 * - Orders by Category table
 * - Drill-down section showing detailed orders for a specific day
 * - Export functionality (Excel, CSV, PDF)
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-data.php';

// Get current filter parameters from URL
$current_params = array(
    'date_preset' => isset($_GET['date_preset']) ? sanitize_text_field($_GET['date_preset']) : 'month_to_date',
    'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
    'group_by' => isset($_GET['group_by']) ? sanitize_text_field($_GET['group_by']) : 'day',
    'product_type' => isset($_GET['product_type']) ? sanitize_text_field($_GET['product_type']) : '',
    'order_source' => isset($_GET['order_source']) ? sanitize_text_field($_GET['order_source']) : '',
    'order_status' => isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : '',
);

// Map product_type and order_source to query builder params
$query_params = $current_params;
$query_params['kitchen_type'] = $current_params['product_type'];
$query_params['order_type'] = $current_params['order_source'];

// Initialize query builder and data layer
$query_builder = new Orders_Reports_Query_Builder($query_params);
$reports_data = new Orders_Reports_Data($query_builder);

// Get report data
$kpis = $reports_data->get_kpis();
$formatted_kpis = $reports_data->format_kpis($kpis);
$summary_table = $reports_data->get_summary_table();
$category_table = $reports_data->get_category_table();
$payment_breakdown = $reports_data->get_payment_breakdown();
$status_breakdown = $reports_data->get_status_breakdown();
?>

<div class="wrap oj-reports-dashboard">
    <!-- Header -->
    <div class="oj-reports-header">
        <h1 class="oj-reports-title"><?php _e('📊 Orders Reports', 'orders-jet'); ?></h1>
        <p class="oj-reports-subtitle">
            <?php _e('Comprehensive business intelligence and analytics for your orders', 'orders-jet'); ?>
        </p>
    </div>

    <!-- Filter Bar -->
    <div class="oj-reports-filters">
        <form method="get" action="" class="oj-filter-form" id="oj-main-filter-form">
            <input type="hidden" name="page" value="orders-reports">
            
            <!-- Active Filters Indicator -->
            <?php 
            $active_filters = array();
            if ($current_params['date_preset'] && $current_params['date_preset'] !== 'month_to_date') {
                $active_filters[] = 'Date Range';
            }
            if ($current_params['product_type']) {
                $active_filters[] = 'Product Type';
            }
            if ($current_params['order_source']) {
                $active_filters[] = 'Order Source';
            }
            if ($current_params['order_status']) {
                $active_filters[] = 'Order Status';
            }
            if ($current_params['group_by'] && $current_params['group_by'] !== 'day') {
                $active_filters[] = 'Grouping';
            }
            ?>
            <?php if (!empty($active_filters)): ?>
            <div class="oj-active-filters-bar">
                <span class="oj-filter-badge">
                    <strong><?php echo count($active_filters); ?> Filter(s) Active:</strong>
                    <?php echo implode(', ', $active_filters); ?>
                </span>
                <span class="oj-filter-note">✓ Filters apply to all cards and tables</span>
            </div>
            <?php endif; ?>
            
        <div class="oj-filter-controls">
                <!-- Date Range Selector -->
            <div class="oj-filter-group">
                    <label for="date-preset"><?php _e('Date Range', 'orders-jet'); ?></label>
                    <select name="date_preset" id="date-preset" class="oj-filter-input">
                        <option value=""><?php _e('All Time', 'orders-jet'); ?></option>
                        <option value="today" <?php selected($current_params['date_preset'], 'today'); ?>><?php _e('Today', 'orders-jet'); ?></option>
                        <option value="yesterday" <?php selected($current_params['date_preset'], 'yesterday'); ?>><?php _e('Yesterday', 'orders-jet'); ?></option>
                        <option value="this_week" <?php selected($current_params['date_preset'], 'this_week'); ?>><?php _e('This Week', 'orders-jet'); ?></option>
                        <option value="week_to_date" <?php selected($current_params['date_preset'], 'week_to_date'); ?>><?php _e('Week to Date', 'orders-jet'); ?></option>
                        <option value="month_to_date" <?php selected($current_params['date_preset'], 'month_to_date'); ?>><?php _e('Month to Date', 'orders-jet'); ?></option>
                        <option value="last_week" <?php selected($current_params['date_preset'], 'last_week'); ?>><?php _e('Last Week', 'orders-jet'); ?></option>
                        <option value="last_month" <?php selected($current_params['date_preset'], 'last_month'); ?>><?php _e('Last Month', 'orders-jet'); ?></option>
                        <option value="custom" <?php selected($current_params['date_preset'], 'custom'); ?>><?php _e('Custom Range', 'orders-jet'); ?></option>
                </select>
            </div>

                <!-- Custom Date Range (shown when Custom is selected) -->
                <div class="oj-filter-group oj-custom-dates" style="<?php echo $current_params['date_preset'] === 'custom' ? '' : 'display: none;'; ?>">
                    <label><?php _e('From / To', 'orders-jet'); ?></label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="date" name="date_from" id="date-from" class="oj-filter-input" 
                               value="<?php echo esc_attr($current_params['date_from']); ?>">
                        <span>—</span>
                        <input type="date" name="date_to" id="date-to" class="oj-filter-input" 
                               value="<?php echo esc_attr($current_params['date_to']); ?>">
            </div>
            </div>

                <!-- Product Type Filter -->
            <div class="oj-filter-group">
                    <label for="product-type"><?php _e('Product Type', 'orders-jet'); ?></label>
                    <select name="product_type" id="product-type" class="oj-filter-input">
                        <option value=""><?php _e('All', 'orders-jet'); ?></option>
                        <option value="food" <?php selected($current_params['product_type'], 'food'); ?>><?php _e('Food', 'orders-jet'); ?></option>
                        <option value="beverages" <?php selected($current_params['product_type'], 'beverages'); ?>><?php _e('Beverages', 'orders-jet'); ?></option>
                </select>
            </div>

                <!-- Order Source Filter -->
            <div class="oj-filter-group">
                    <label for="order-source"><?php _e('Order Source', 'orders-jet'); ?></label>
                    <select name="order_source" id="order-source" class="oj-filter-input">
                        <option value=""><?php _e('All', 'orders-jet'); ?></option>
                        <option value="dinein" <?php selected($current_params['order_source'], 'dinein'); ?>><?php _e('Storefront / Dine-in', 'orders-jet'); ?></option>
                        <option value="takeaway" <?php selected($current_params['order_source'], 'takeaway'); ?>><?php _e('Phone / Takeaway', 'orders-jet'); ?></option>
                        <option value="delivery" <?php selected($current_params['order_source'], 'delivery'); ?>><?php _e('Other / Delivery', 'orders-jet'); ?></option>
                </select>
            </div>

                <!-- Order Status Filter -->
            <div class="oj-filter-group">
                    <label for="order-status"><?php _e('Order Status', 'orders-jet'); ?></label>
                    <select name="order_status" id="order-status" class="oj-filter-input">
                        <option value=""><?php _e('All', 'orders-jet'); ?></option>
                        <option value="pending" <?php selected($current_params['order_status'], 'pending'); ?>><?php _e('Pending', 'orders-jet'); ?></option>
                        <option value="processing" <?php selected($current_params['order_status'], 'processing'); ?>><?php _e('Processing', 'orders-jet'); ?></option>
                        <option value="completed" <?php selected($current_params['order_status'], 'completed'); ?>><?php _e('Completed', 'orders-jet'); ?></option>
                        <option value="cancelled" <?php selected($current_params['order_status'], 'cancelled'); ?>><?php _e('Cancelled', 'orders-jet'); ?></option>
                        <option value="refunded" <?php selected($current_params['order_status'], 'refunded'); ?>><?php _e('Refunded', 'orders-jet'); ?></option>
                        <option value="failed" <?php selected($current_params['order_status'], 'failed'); ?>><?php _e('Failed', 'orders-jet'); ?></option>
                </select>
            </div>

                <!-- Group By Selector -->
            <div class="oj-filter-group">
                    <label for="group-by"><?php _e('Group By', 'orders-jet'); ?></label>
                    <select name="group_by" id="group-by" class="oj-filter-input">
                        <option value="day" <?php selected($current_params['group_by'], 'day'); ?>><?php _e('Day', 'orders-jet'); ?></option>
                        <option value="week" <?php selected($current_params['group_by'], 'week'); ?>><?php _e('Week', 'orders-jet'); ?></option>
                        <option value="month" <?php selected($current_params['group_by'], 'month'); ?>><?php _e('Month', 'orders-jet'); ?></option>
                </select>
            </div>

                <!-- Action Buttons -->
                <div class="oj-filter-group" style="margin-top: auto;">
                    <button type="submit" class="button button-primary">
                    <?php _e('Apply Filters', 'orders-jet'); ?>
                </button>
                    <a href="<?php echo admin_url('admin.php?page=orders-reports'); ?>" class="button">
                    <?php _e('Reset', 'orders-jet'); ?>
                    </a>
            </div>
        </div>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="oj-kpi-section">
        <div class="oj-kpi-grid">
            <?php foreach ($formatted_kpis as $kpi_key => $kpi): ?>
            <div class="oj-kpi-card" style="border-left-color: <?php echo esc_attr($kpi['color']); ?>">
                <div class="oj-kpi-icon"><?php echo $kpi['icon']; ?></div>
                <div class="oj-kpi-content">
                    <div class="oj-kpi-label"><?php echo esc_html($kpi['label']); ?></div>
                    <div class="oj-kpi-value"><?php echo $kpi['value']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment & Status Breakdown -->
    <div class="oj-breakdown-section">
        <div class="oj-breakdown-grid">
            <!-- Payment Methods Breakdown -->
            <div class="oj-breakdown-card">
                <h3><?php _e('Payment Methods', 'orders-jet'); ?></h3>
                <div class="oj-breakdown-items">
                    <?php foreach ($payment_breakdown as $key => $data): ?>
                    <div class="oj-breakdown-item">
                        <div class="oj-breakdown-label"><?php echo esc_html($data['label']); ?></div>
                        <div class="oj-breakdown-bar">
                            <div class="oj-breakdown-fill" style="width: <?php echo esc_attr($data['percentage']); ?>%"></div>
                        </div>
                        <div class="oj-breakdown-stats">
                            <span><?php echo number_format($data['orders']); ?> orders</span>
                            <span><?php echo wc_price($data['revenue']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Status Breakdown -->
            <div class="oj-breakdown-card">
                <h3><?php _e('Order Status', 'orders-jet'); ?></h3>
                <div class="oj-breakdown-items">
                    <?php foreach ($status_breakdown as $key => $data): 
                        $badge_class = 'oj-badge-' . strtolower(str_replace(' ', '-', $data['label']));
                    ?>
                    <div class="oj-breakdown-item">
                        <div class="oj-breakdown-label">
                            <span class="oj-badge <?php echo esc_attr($badge_class); ?>" style="margin-right: 8px; padding: 3px 8px; font-size: 11px;">
                                <?php echo esc_html($data['label']); ?>
                            </span>
                        </div>
                        <div class="oj-breakdown-bar">
                            <div class="oj-breakdown-fill" style="width: <?php echo esc_attr($data['percentage']); ?>%; background-color: <?php echo esc_attr($data['color']); ?>"></div>
                        </div>
                        <div class="oj-breakdown-stats">
                            <span><strong><?php echo number_format($data['count']); ?></strong> orders</span>
                            <span><strong><?php echo number_format($data['percentage'], 1); ?>%</strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Tables -->
    <div class="oj-reports-tables">
        <!-- Tab Navigation -->
        <div class="oj-tab-navigation">
            <button class="oj-tab-btn active" data-tab="summary">
                <?php _e('📅 Summary Report', 'orders-jet'); ?>
            </button>
            <button class="oj-tab-btn" data-tab="category">
                <?php _e('📊 Category Report', 'orders-jet'); ?>
            </button>
    </div>

        <!-- Summary Table Tab -->
        <div id="tab-summary" class="oj-tab-content active">
        <div class="oj-table-header">
                <h3><?php _e('Monthly / Daily Summary', 'orders-jet'); ?></h3>
            <div class="oj-export-buttons">
                    <button class="button oj-export-btn" data-type="excel" data-report="summary">
                        <?php _e('📥 Excel', 'orders-jet'); ?>
                </button>
                    <button class="button oj-export-btn" data-type="csv" data-report="summary">
                        <?php _e('📄 CSV', 'orders-jet'); ?>
                </button>
                    <button class="button oj-export-btn" data-type="pdf" data-report="summary">
                        <?php _e('📑 PDF', 'orders-jet'); ?>
                </button>
            </div>
        </div>

            <div class="oj-table-wrapper">
                <table class="oj-reports-table widefat">
            <thead>
                <tr>
                    <th><?php _e('Period', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Total Orders', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Completed', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Cancelled', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Revenue', 'orders-jet'); ?></th>
                            <th class="oj-text-center"><?php _e('Actions', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody>
                        <?php if (!empty($summary_table)): ?>
                            <?php foreach ($summary_table as $row): ?>
                <tr>
                        <td><strong><?php echo esc_html($row['period_label']); ?></strong></td>
                                <td class="oj-text-right"><?php echo number_format_i18n($row['total_orders']); ?></td>
                                <td class="oj-text-right">
                                    <span class="oj-badge oj-badge-success"><?php echo number_format_i18n($row['completed_orders']); ?></span>
                                </td>
                                <td class="oj-text-right">
                                    <span class="oj-badge oj-badge-danger"><?php echo number_format_i18n($row['cancelled_orders']); ?></span>
                                </td>
                                <td class="oj-text-right"><strong><?php echo $row['revenue_formatted']; ?></strong></td>
                                <td class="oj-text-center">
                                    <button class="button button-small oj-drill-down-btn" 
                                            data-date="<?php echo esc_attr($row['period']); ?>"
                                            data-label="<?php echo esc_attr($row['period_label']); ?>">
                                        <?php _e('View Details', 'orders-jet'); ?> →
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="oj-text-center">
                                    <?php _e('No data available for the selected filters.', 'orders-jet'); ?>
                                </td>
                            </tr>
                <?php endif; ?>
            </tbody>
        </table>
            </div>
    </div>

        <!-- Category Table Tab -->
        <div id="tab-category" class="oj-tab-content">
        <div class="oj-table-header">
                <h3><?php _e('Orders by Category', 'orders-jet'); ?></h3>
            <div class="oj-export-buttons">
                    <button class="button oj-export-btn" data-type="excel" data-report="category">
                        <?php _e('📥 Excel', 'orders-jet'); ?>
                </button>
                    <button class="button oj-export-btn" data-type="csv" data-report="category">
                        <?php _e('📄 CSV', 'orders-jet'); ?>
                </button>
                    <button class="button oj-export-btn" data-type="pdf" data-report="category">
                        <?php _e('📑 PDF', 'orders-jet'); ?>
                </button>
            </div>
        </div>

            <div class="oj-table-wrapper">
                <table class="oj-reports-table widefat">
            <thead>
                <tr>
                    <th><?php _e('Category', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Orders Count', 'orders-jet'); ?></th>
                            <th class="oj-text-right"><?php _e('Total Revenue', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody>
                        <?php if (!empty($category_table)): ?>
                    <?php foreach ($category_table as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row['category_name']); ?></strong></td>
                                <td class="oj-text-right"><?php echo number_format_i18n($row['order_count']); ?></td>
                                <td class="oj-text-right"><strong><?php echo $row['revenue_formatted']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="oj-text-center">
                                    <?php _e('No category data available.', 'orders-jet'); ?>
                                </td>
                            </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
        </div>
    </div>

    <!-- Drill-Down Section (Initially Hidden) -->
    <div id="oj-drill-down-section" class="oj-drill-down-section" style="display: none;">
        <div class="oj-drill-down-header">
            <div class="oj-drill-down-title-section">
                <h3 id="oj-drill-down-title"><?php _e('Detailed Orders', 'orders-jet'); ?></h3>
            </div>
            <div class="oj-drill-down-actions" style="display: flex; align-items: center; gap: 15px;">
                <div class="oj-export-buttons" style="display: flex; gap: 8px;">
                    <button class="button oj-export-drill-down-btn" data-type="excel" style="background: white; border: 2px solid #667eea; color: #667eea; font-weight: 600; padding: 8px 16px; border-radius: 6px;">
                        📥 Excel
                    </button>
                    <button class="button oj-export-drill-down-btn" data-type="csv" style="background: white; border: 2px solid #667eea; color: #667eea; font-weight: 600; padding: 8px 16px; border-radius: 6px;">
                        📄 CSV
                    </button>
                    <button class="button oj-export-drill-down-btn" data-type="pdf" style="background: white; border: 2px solid #667eea; color: #667eea; font-weight: 600; padding: 8px 16px; border-radius: 6px;">
                        📑 PDF
                    </button>
                </div>
                <button id="oj-close-drill-down" class="button" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px;">
                    ✕ Close
                </button>
            </div>
        </div>

        <!-- Drill-Down KPIs -->
        <div id="oj-drill-down-kpis" class="oj-kpi-grid oj-kpi-grid-small">
            <!-- Will be populated via AJAX -->
                </div>

        <!-- Drill-Down Orders Table -->
        <div class="oj-table-wrapper">
            <table class="oj-reports-table widefat">
                <thead>
                    <tr>
                        <th><?php _e('Order #', 'orders-jet'); ?></th>
                        <th><?php _e('Customer', 'orders-jet'); ?></th>
                        <th><?php _e('Status', 'orders-jet'); ?></th>
                        <th class="oj-text-right"><?php _e('Total', 'orders-jet'); ?></th>
                        <th><?php _e('Payment', 'orders-jet'); ?></th>
                        <th><?php _e('Date/Time', 'orders-jet'); ?></th>
                        <th class="oj-text-center"><?php _e('Actions', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody id="oj-drill-down-orders">
                    <!-- Will be populated via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Additional inline styles for reports dashboard */

/* Active Filters Indicator */
.oj-active-filters-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    animation: slideDown 0.3s ease-out;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.oj-filter-badge {
    font-size: 14px;
}
.oj-filter-badge strong {
    margin-right: 8px;
}
.oj-filter-note {
    font-size: 12px;
    opacity: 0.9;
    font-style: italic;
}

.oj-breakdown-section { margin-bottom: 30px; }
.oj-breakdown-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
.oj-breakdown-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.oj-breakdown-card h3 { margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333; }
.oj-breakdown-items { display: flex; flex-direction: column; gap: 15px; }
.oj-breakdown-item { }
.oj-breakdown-label { font-size: 14px; font-weight: 500; margin-bottom: 5px; color: #555; display: flex; align-items: center; }
.oj-breakdown-bar { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 5px; }
.oj-breakdown-fill { height: 100%; background: #2271b1; transition: width 0.3s ease; }
.oj-breakdown-stats { display: flex; justify-content: space-between; font-size: 13px; color: #666; }
.oj-tab-navigation { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
.oj-tab-btn { background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 500; color: #666; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.3s; }
.oj-tab-btn:hover { color: #2271b1; }
.oj-tab-btn.active { color: #2271b1; border-bottom-color: #2271b1; }
.oj-tab-content { display: none; }
.oj-tab-content.active { display: block; }
.oj-table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.oj-table-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
.oj-export-buttons { display: flex; gap: 8px; }
.oj-table-wrapper { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.oj-reports-table { margin: 0; border: none; }
.oj-reports-table th { background: #f8f9fa; font-weight: 600; padding: 15px; border-bottom: 2px solid #ddd; }
.oj-reports-table td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
.oj-reports-table tbody tr:hover { background: #f8f9fa; }
.oj-text-right { text-align: right; }
.oj-text-center { text-align: center; }
/* Status Badges - Enhanced Colors */
.oj-badge { 
    display: inline-block; 
    padding: 6px 12px; 
    border-radius: 16px; 
    font-size: 12px; 
    font-weight: 700; 
    border: 2px solid transparent;
    text-transform: capitalize;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
.oj-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.oj-badge-success { 
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
    color: #065f46; 
    border-color: #10b981; 
}
.oj-badge-danger { 
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
    color: #991b1b; 
    border-color: #ef4444; 
}
.oj-badge-pending { 
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
    color: #92400e; 
    border-color: #f59e0b; 
}
.oj-badge-processing { 
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); 
    color: #1e40af; 
    border-color: #3b82f6; 
}
.oj-badge-completed { 
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
    color: #065f46; 
    border-color: #10b981; 
}
.oj-badge-cancelled { 
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
    color: #991b1b; 
    border-color: #ef4444; 
}
.oj-badge-refunded { 
    background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); 
    color: #6b21a8; 
    border-color: #a855f7; 
}
.oj-badge-failed { 
    background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%); 
    color: #7f1d1d; 
    border-color: #dc2626; 
}
.oj-badge-on-hold {
    background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
    color: #7c2d12;
    border-color: #f97316;
}
.oj-drill-down-section { 
    background: white; 
    padding: 25px; 
    border-radius: 8px; 
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
    margin-top: 30px;
    border-left: 4px solid #667eea;
    animation: slideInUp 0.4s ease-out;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.oj-drill-down-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 20px; 
    padding-bottom: 15px; 
    border-bottom: 3px solid #667eea;
}
.oj-drill-down-title-section {
    flex: 1;
}
.oj-drill-down-header h3 { 
    margin: 0; 
    font-size: 22px; 
    font-weight: 700; 
    color: #333;
}
.oj-drill-down-actions {
    display: flex !important;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.oj-drill-down-actions .oj-export-buttons {
    display: flex !important;
    gap: 8px;
    flex-wrap: nowrap;
}
.oj-export-drill-down-btn {
    display: inline-block !important;
    background: white !important;
    border: 2px solid #667eea !important;
    color: #667eea !important;
    font-weight: 600 !important;
    padding: 8px 16px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
    text-decoration: none !important;
    box-shadow: none !important;
}
.oj-export-drill-down-btn:hover {
    background: #667eea !important;
    color: white !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3) !important;
    border-color: #667eea !important;
}
.oj-export-drill-down-btn:focus {
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2) !important;
}
.oj-kpi-grid-small .oj-kpi-card { padding: 15px; }
.oj-kpi-grid-small .oj-kpi-value { font-size: 20px; }
.oj-export-btn:hover { background: #2271b1; color: white; border-color: #2271b1; }
.oj-drill-down-section .oj-reports-table tbody tr:hover { 
    background: #f0f4ff; 
    transform: scale(1.01);
    transition: all 0.2s ease;
}
.oj-drill-down-section .oj-badge {
    font-size: 13px;
    padding: 7px 14px;
}
/* Export Success Message */
.oj-export-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    animation: slideInDown 0.3s ease-out;
}
@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Orders Reports: Script initialized with unified filter support');
    
    // Check if order status filter is active
    var orderStatus = $('#order-status').val();
    if (orderStatus) {
        console.log('Order status filter active:', orderStatus);
    }
    
    // Helper function to get status badge class with comprehensive status mapping
    function getStatusBadgeClass(status) {
        if (!status) return 'oj-badge-pending';
        
        // Normalize status: remove prefixes and convert to lowercase
        var statusLower = status.toLowerCase()
            .replace('wc-', '')
            .replace('wc_', '')
            .replace('order-', '')
            .trim();
        
        console.log('🎨 Status Badge Mapping:', {
            original: status,
            normalized: statusLower
        });
        
        var colorMap = {
            'pending': 'oj-badge-pending',
            'pending payment': 'oj-badge-pending',
            'processing': 'oj-badge-processing',
            'completed': 'oj-badge-completed',
            'complete': 'oj-badge-completed',
            'cancelled': 'oj-badge-cancelled',
            'canceled': 'oj-badge-cancelled',
            'refunded': 'oj-badge-refunded',
            'failed': 'oj-badge-failed',
            'on-hold': 'oj-badge-on-hold',
            'on hold': 'oj-badge-on-hold',
            'onhold': 'oj-badge-on-hold'
        };
        
        var badgeClass = colorMap[statusLower] || 'oj-badge-pending';
        console.log('✅ Badge class selected:', badgeClass);
        
        return badgeClass;
    }

    // Unified Filter Change Handler - Visual Feedback
    var filterTimeout;
    $('#oj-main-filter-form select, #oj-main-filter-form input').on('change', function() {
        // Clear previous timeout
        clearTimeout(filterTimeout);
        
        // Add visual feedback
        var $form = $('#oj-main-filter-form');
        $form.css('opacity', '0.7');
        
        // Show a temporary message
        if ($('.oj-filter-feedback').length === 0) {
            $form.prepend('<div class="oj-filter-feedback" style="background: #667eea; color: white; padding: 8px 15px; border-radius: 6px; margin-bottom: 10px; font-size: 13px; animation: fadeIn 0.3s;">⏳ Click "Apply Filters" to update all cards and tables</div>');
        }
        
        // Auto-hide message after 3 seconds
        filterTimeout = setTimeout(function() {
            $('.oj-filter-feedback').fadeOut(300, function() { $(this).remove(); });
            $form.css('opacity', '1');
        }, 3000);
    });

    // Date preset change handler
    $('#date-preset').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.oj-custom-dates').slideDown();
        } else {
            $('.oj-custom-dates').slideUp();
        }
    });
    
    // Form submission - show loading overlay
    $('#oj-main-filter-form').on('submit', function() {
        // Add loading overlay
        if ($('.oj-loading-overlay').length === 0) {
            $('body').append('<div class="oj-loading-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 30px 50px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);"><h2 style="margin: 0 0 10px 0; color: #333;">🔄 Updating Reports...</h2><p style="margin: 0; color: #666;">Applying filters to all cards and tables</p></div></div>');
        }
    });

    // Tab switching
    $('.oj-tab-btn').on('click', function() {
        var tab = $(this).data('tab');
        
        $('.oj-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.oj-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Drill-down button handler
    $('.oj-drill-down-btn').on('click', function() {
        var date = $(this).data('date');
        var label = $(this).data('label');
        
        console.log('🔍 Drill-down button clicked:', {
            date: date,
            label: label,
            hasDate: !!date,
            hasLabel: !!label
        });
        
        // Fallback for label
        var displayLabel = label || date || 'Selected Period';
        
        // Show loading state
        $('#oj-drill-down-title').html('Loading details for <strong>' + displayLabel + '</strong>...');
        $('#oj-drill-down-section').slideDown();
        $('#oj-drill-down-kpis').html('<p>Loading...</p>');
        $('#oj-drill-down-orders').html('<tr><td colspan="7" class="oj-text-center">Loading...</td></tr>');
        
        // Scroll to drill-down section
        $('html, body').animate({
            scrollTop: $('#oj-drill-down-section').offset().top - 32
        }, 500);

        // Get current filters
        var filters = {
            action: 'oj_reports_drill_down',
            nonce: ojReportsData.nonce,
            date: date,
            date_preset: $('#date-preset').val(),
            date_from: $('#date-from').val(),
            date_to: $('#date-to').val(),
            product_type: $('#product-type').val(),
            order_source: $('#order-source').val(),
            order_status: $('#order-status').val(),
            group_by: $('#group-by').val()
        };
        
        // Store drill-down data for export
        currentDrillDownData = {
            date: date,
            label: label,
            filters: filters
        };
        
        console.log('Drill-down filters:', filters);

        // Make AJAX request
        $.ajax({
            url: ojReportsData.ajaxUrl,
            type: 'POST',
            data: filters,
            success: function(response) {
                if (response.success) {
                    // Update title with accurate order count
                    var ordersCount = response.data.orders.length;
                    var displayLabel = label || date || 'Selected Period';
                    $('#oj-drill-down-title').html('Detailed Orders for <strong>' + displayLabel + '</strong> (' + ordersCount + ' orders)');
                    
                    // Update KPIs
                    var kpisHtml = '';
                    $.each(response.data.kpis, function(key, kpi) {
                        kpisHtml += '<div class="oj-kpi-card" style="border-left-color: ' + kpi.color + '">';
                        kpisHtml += '<div class="oj-kpi-icon">' + kpi.icon + '</div>';
                        kpisHtml += '<div class="oj-kpi-content">';
                        kpisHtml += '<div class="oj-kpi-label">' + kpi.label + '</div>';
                        kpisHtml += '<div class="oj-kpi-value">' + kpi.value + '</div>';
                        kpisHtml += '</div></div>';
                    });
                    $('#oj-drill-down-kpis').html(kpisHtml);
                    
                    // Update orders table with colored status badges
                    var ordersHtml = '';
                    console.log('Drill-down received orders:', response.data.orders);
                    console.log('Total orders count:', response.data.orders.length);
                    
                    if (response.data.orders.length > 0) {
                        $.each(response.data.orders, function(i, order) {
                            // Use status_raw for more accurate badge class determination
                            var statusRaw = order.status_raw || order.status;
                            var statusClass = getStatusBadgeClass(statusRaw);
                            
                            console.log('Order #' + order.order_number + ': status="' + order.status + '", status_raw="' + statusRaw + '", badge_class="' + statusClass + '"');
                            
                            ordersHtml += '<tr>';
                            ordersHtml += '<td><a href="' + order.order_url + '" target="_blank"><strong>#' + order.order_number + '</strong></a></td>';
                            ordersHtml += '<td>' + order.customer_name + '</td>';
                            ordersHtml += '<td><span class="oj-badge ' + statusClass + '">' + order.status + '</span></td>';
                            ordersHtml += '<td class="oj-text-right"><strong>' + order.total_formatted + '</strong></td>';
                            ordersHtml += '<td>' + order.payment_method + '</td>';
                            ordersHtml += '<td>' + order.date_created + '</td>';
                            ordersHtml += '<td class="oj-text-center"><a href="' + order.order_url + '" class="button button-small" target="_blank">View</a></td>';
                            ordersHtml += '</tr>';
                        });
                    } else {
                        ordersHtml = '<tr><td colspan="7" class="oj-text-center">No orders found for this period.</td></tr>';
                    }
                    $('#oj-drill-down-orders').html(ordersHtml);
                    
                    // Verify export buttons are visible
                    var exportBtns = $('.oj-export-drill-down-btn');
                    console.log('✅ Drill-down loaded. Export buttons found:', exportBtns.length);
                    if (exportBtns.length === 0) {
                        console.error('❌ Export buttons NOT found! Check HTML structure.');
                    }
                } else {
                    alert('Error loading drill-down data: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Drill-down AJAX error:', { xhr: xhr, status: status, error: error });
                var displayLabel = label || date || 'Selected Period';
                $('#oj-drill-down-title').html('Error loading details for <strong>' + displayLabel + '</strong>');
                alert('Failed to load drill-down data. Please try again.');
            }
        });
    });

    // Store current drill-down data for export
    var currentDrillDownData = {
        date: null,
        label: null,
        filters: {}
    };

    // Close drill-down section
    $('#oj-close-drill-down').on('click', function() {
        $('#oj-drill-down-section').slideUp();
        currentDrillDownData = { date: null, label: null, filters: {} };
    });

    // Export button handler
    $('.oj-export-btn').on('click', function() {
        var $btn = $(this);
        var type = $btn.data('type');
        var report = $btn.data('report');
        
        // Show loading state
        $btn.prop('disabled', true).text('Exporting...');
        
        // Get current filters
        var filters = {
            action: 'oj_reports_export',
            nonce: ojReportsData.nonce,
            export_type: type,
            report_type: report,
            date_preset: $('#date-preset').val(),
            date_from: $('#date-from').val(),
            date_to: $('#date-to').val(),
            product_type: $('#product-type').val(),
            order_source: $('#order-source').val(),
            order_status: $('#order-status').val(),
            group_by: $('#group-by').val()
        };
        
        console.log('Export filters:', filters);

        // Make AJAX request
        $.ajax({
            url: ojReportsData.ajaxUrl,
            type: 'POST',
            data: filters,
            success: function(response) {
                if (response.success) {
                    // Trigger download
                    window.open(response.data.url, '_blank');
                    alert('Export completed successfully!');
                } else {
                    alert('Export failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Export failed. Please try again.');
            },
            complete: function() {
                // Restore button state
                $btn.prop('disabled', false);
                var icon = type === 'excel' ? '📥' : (type === 'csv' ? '📄' : '📑');
                $btn.text(icon + ' ' + type.toUpperCase());
            }
        });
    });

    // Drill-Down Export button handler
    $(document).on('click', '.oj-export-drill-down-btn', function() {
        var $btn = $(this);
        var type = $btn.data('type');
        
        // Check if drill-down data is available
        if (!currentDrillDownData.date) {
            alert('No drill-down data available to export.');
            return;
        }
        
        // Show loading state
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('⏳ Exporting...');
        
        // Prepare export data with drill-down specific params
        var exportData = {
            action: 'oj_reports_export',
            nonce: ojReportsData.nonce,
            export_type: type,
            report_type: 'drill_down',
            drill_down_date: currentDrillDownData.date,
            drill_down_label: currentDrillDownData.label,
            date_preset: currentDrillDownData.filters.date_preset,
            date_from: currentDrillDownData.filters.date_from,
            date_to: currentDrillDownData.filters.date_to,
            product_type: currentDrillDownData.filters.product_type,
            order_source: currentDrillDownData.filters.order_source,
            order_status: currentDrillDownData.filters.order_status,
            group_by: currentDrillDownData.filters.group_by
        };
        
        console.log('📤 Drill-down export data:', exportData);

        // Make AJAX request
        $.ajax({
            url: ojReportsData.ajaxUrl,
            type: 'POST',
            data: exportData,
            success: function(response) {
                console.log('Export response:', response);
                if (response.success) {
                    // Trigger download
                    if (response.data.url) {
                        window.open(response.data.url, '_blank');
                        
                        // Show success message with animation
                        var successMsg = $('<div class="oj-export-success">✅ Export completed! Downloaded: ' + currentDrillDownData.label + '</div>');
                        $('.oj-drill-down-header').after(successMsg);
                        setTimeout(function() {
                            successMsg.fadeOut(300, function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        alert('Export completed but download URL not found.');
                    }
                } else {
                    alert('Export failed: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Export error:', error);
                alert('Export failed. Please try again. Error: ' + error);
            },
            complete: function() {
                // Restore button state
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
