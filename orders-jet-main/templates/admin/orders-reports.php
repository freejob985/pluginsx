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
 * Reference page: /wp-admin/admin.php?page=business-intelligence
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies for reports
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-data.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-master-helpers.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/class-orders-jet-business-insights.php';

// Enqueue existing CSS (reuse Orders Express design system)
wp_enqueue_style(
    'oj-manager-orders-cards',
    ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
    array(),
    ORDERS_JET_VERSION
);

wp_enqueue_style(
    'oj-dashboard-express',
    ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
    array('oj-manager-orders-cards'),
    ORDERS_JET_VERSION
);

// Add custom CSS for reports table dropdown
// Enqueue dedicated CSS file for insights cards
wp_enqueue_style(
    'oj-reports-insights',
    ORDERS_JET_PLUGIN_URL . 'assets/css/reports-insights.css',
    array(),
    ORDERS_JET_VERSION . '-' . time(), // Cache busting
    'all'
);

// Enqueue customer overlay CSS
wp_enqueue_style(
    'oj-customer-overlay',
    ORDERS_JET_PLUGIN_URL . 'assets/css/customer-overlay.css',
    array('oj-manager-orders-cards'),
    ORDERS_JET_VERSION . '-' . time(), // Cache busting
    'all'
);

// Add minimal inline CSS for panel adjustments
wp_add_inline_style('oj-dashboard-express', '
    .oj-table-select {
        width: 180px !important;
        max-width: 180px !important;
        min-width: 160px !important;
    }
    
    .oj-search-group {
        flex: 0 0 auto !important;
        width: auto !important;
    }
    
    .oj-panel-table-select {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    @media (max-width: 768px) {
        .oj-table-select {
            width: 140px !important;
            min-width: 120px !important;
        }
    }
');

// Enqueue Orders Master main JavaScript (AJAX filtering, pagination, grid refresh)
// Note: We reuse the same JS but will modify behavior for reports
wp_enqueue_script(
    'oj-orders-master',
    ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master.js',
    array('jquery'),
    ORDERS_JET_VERSION,
    true
);

// Localize Orders Master script
wp_localize_script(
    'oj-orders-master',
    'OrdersJetMaster',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oj_dashboard_nonce')
    )
);

// Note: We don't enqueue bulk actions or order editor JS for reports
// Reports are read-only, no action buttons needed

// Initialize services for data display (same as Orders Master)
$kitchen_service = new Orders_Jet_Kitchen_Service();
$order_method_service = new Orders_Jet_Order_Method_Service();

// ============================================
// STEP 1: Initialize Reports Query Builder
// ============================================

// Get current filter parameters from URL
$reports_params = array(
    'date_preset' => isset($_GET['date_preset']) ? sanitize_text_field($_GET['date_preset']) : 'month_to_date',
    'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
    'group_by' => isset($_GET['group_by']) ? sanitize_text_field($_GET['group_by']) : 'day',
    'product_type' => isset($_GET['product_type']) ? sanitize_text_field($_GET['product_type']) : '',
    'order_source' => isset($_GET['order_source']) ? sanitize_text_field($_GET['order_source']) : '',
    'filter' => isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all',
);

// Map to query builder params
$reports_params['kitchen_type'] = $reports_params['product_type'];
$reports_params['order_type'] = $reports_params['order_source'];

// Initialize Reports query builder
$reports_query_builder = new Orders_Reports_Query_Builder($reports_params);
$reports_data_handler = new Orders_Reports_Data($reports_query_builder);

// Get report-specific data
$reports_kpis = $reports_data_handler->get_kpis();
$formatted_reports_kpis = $reports_data_handler->format_kpis($reports_kpis);
$summary_table_data = $reports_data_handler->get_summary_table();
$category_table_data = $reports_data_handler->get_category_table();
$payment_breakdown_data = $reports_data_handler->get_payment_breakdown();
$status_breakdown_data = $reports_data_handler->get_status_breakdown();

// Also initialize standard query builder for orders list (for backward compatibility)
$query_builder = new Orders_Master_Query_Builder($_GET);
$orders = $query_builder->get_orders();
$filter_counts = $query_builder->get_filter_counts();
$pagination = $query_builder->get_pagination_data();

// Extract parameters for template use
$current_filter = $query_builder->get_filter();
$current_page = $query_builder->get_current_page();
$search = $query_builder->get_search(); // Will be removed in Phase 2
$orderby = $query_builder->get_orderby();
$order = $query_builder->get_order();
$order_direction = $order; // Preserve for display
$date_preset = $query_builder->get_date_preset();
$date_from = $query_builder->get_date_from();
$date_to = $query_builder->get_date_to();
$date_range_label = $query_builder->get_date_range_label();

// Advanced filter parameters
$order_type = $query_builder->get_order_type();
$kitchen_type = $query_builder->get_kitchen_type();
$kitchen_status = $query_builder->get_kitchen_status();
$assigned_waiter = $query_builder->get_assigned_waiter();
$unassigned_only = $query_builder->get_unassigned_only();
$payment_method = $query_builder->get_payment_method();
$customer_type = $query_builder->get_customer_type(); // MISSING PARAMETER ADDED!

// Amount filter variables
$amount_type = $query_builder->get_amount_type();
$amount_value = $query_builder->get_amount_value();
$amount_min = $query_builder->get_amount_min();
$amount_max = $query_builder->get_amount_max();

$per_page = $query_builder->get_per_page();
$total_orders = $pagination['total_orders'];
$total_pages = $pagination['total_pages'];
$orders_count = count($orders);

// Calculate total amount of filtered orders
$filtered_total = $query_builder->get_filtered_orders_total();

// Current params for URL building
$current_params = $query_builder->get_current_params();

// Check if we have active filters for reset button
$has_filters = Orders_Jet_Filter_URL_Builder::has_active_filters($current_params);

// ============================================
// REPORTS-SPECIFIC: Calculate Enhanced Business Insights
// ============================================

// Initialize business insights calculator
$insights_calculator = new Orders_Jet_Business_Insights();

// Calculate enhanced insights with current filter parameters
$enhanced_insights = $insights_calculator->calculate_enhanced_insights($current_params);

// Keep basic sales summary for backward compatibility
$sales_summary = array(
    'total_sales' => $filtered_total,
    'total_orders' => $total_orders,
    'avg_order_value' => $total_orders > 0 ? $filtered_total / $total_orders : 0,
    'currency_symbol' => get_woocommerce_currency_symbol()
);

?>

<div class="wrap oj-dashboard">
    <div class="oj-dashboard-header">
        <h1><?php _e('📊 Orders Reports', 'orders-jet'); ?></h1>
        <p class="description">
            <?php _e('Business intelligence and analytics for your orders data', 'orders-jet'); ?>
        </p>
    </div>
    
    <!-- Reports Filter Bar -->
    <div class="oj-reports-filters" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" action="" class="oj-filter-form">
            <input type="hidden" name="page" value="orders-reports">
            
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <!-- Date Range Selector -->
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-size: 13px; font-weight: 600;"><?php _e('Date Range', 'orders-jet'); ?></label>
                    <select name="date_preset" id="date-preset" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 180px;" onchange="if(this.value==='custom'){document.querySelector('.oj-custom-dates').style.display='flex';}else{document.querySelector('.oj-custom-dates').style.display='none';}">
                        <option value="" <?php selected($reports_params['date_preset'], ''); ?>><?php _e('All Time', 'orders-jet'); ?></option>
                        <option value="today" <?php selected($reports_params['date_preset'], 'today'); ?>><?php _e('Today', 'orders-jet'); ?></option>
                        <option value="yesterday" <?php selected($reports_params['date_preset'], 'yesterday'); ?>><?php _e('Yesterday', 'orders-jet'); ?></option>
                        <option value="this_week" <?php selected($reports_params['date_preset'], 'this_week'); ?>><?php _e('This Week', 'orders-jet'); ?></option>
                        <option value="week_to_date" <?php selected($reports_params['date_preset'], 'week_to_date'); ?>><?php _e('Week to Date', 'orders-jet'); ?></option>
                        <option value="month_to_date" <?php selected($reports_params['date_preset'], 'month_to_date'); ?>><?php _e('Month to Date', 'orders-jet'); ?></option>
                        <option value="last_week" <?php selected($reports_params['date_preset'], 'last_week'); ?>><?php _e('Last Week', 'orders-jet'); ?></option>
                        <option value="last_month" <?php selected($reports_params['date_preset'], 'last_month'); ?>><?php _e('Last Month', 'orders-jet'); ?></option>
                        <option value="custom" <?php selected($reports_params['date_preset'], 'custom'); ?>><?php _e('Custom Range', 'orders-jet'); ?></option>
                    </select>
                </div>

                <!-- Custom Date Range - تحسين التنسيق -->
                <div class="oj-custom-dates" style="<?php echo $reports_params['date_preset'] === 'custom' ? 'display: flex;' : 'display: none;'; ?> gap: 8px; align-items: flex-end; flex-wrap: nowrap;">
                    <div style="display: flex; flex-direction: column; gap: 5px; min-width: 0;">
                        <label style="font-size: 13px; font-weight: 600; white-space: nowrap;"><?php _e('من', 'orders-jet'); ?></label>
                        <input type="date" name="date_from" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; width: 150px;" value="<?php echo esc_attr($reports_params['date_from']); ?>">
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 5px; min-width: 0;">
                        <label style="font-size: 13px; font-weight: 600; white-space: nowrap;"><?php _e('إلى', 'orders-jet'); ?></label>
                        <input type="date" name="date_to" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; width: 150px;" value="<?php echo esc_attr($reports_params['date_to']); ?>">
                    </div>
                </div>

                <!-- Product Type Filter -->
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-size: 13px; font-weight: 600;"><?php _e('Product Type', 'orders-jet'); ?></label>
                    <select name="product_type" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
                        <option value=""><?php _e('All', 'orders-jet'); ?></option>
                        <option value="food" <?php selected($reports_params['product_type'], 'food'); ?>><?php _e('Food', 'orders-jet'); ?></option>
                        <option value="beverages" <?php selected($reports_params['product_type'], 'beverages'); ?>><?php _e('Beverages', 'orders-jet'); ?></option>
                    </select>
                </div>

                <!-- Order Source Filter -->
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-size: 13px; font-weight: 600;"><?php _e('Order Source', 'orders-jet'); ?></label>
                    <select name="order_source" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
                        <option value=""><?php _e('All', 'orders-jet'); ?></option>
                        <option value="dinein" <?php selected($reports_params['order_source'], 'dinein'); ?>><?php _e('Storefront', 'orders-jet'); ?></option>
                        <option value="takeaway" <?php selected($reports_params['order_source'], 'takeaway'); ?>><?php _e('Phone', 'orders-jet'); ?></option>
                        <option value="delivery" <?php selected($reports_params['order_source'], 'delivery'); ?>><?php _e('Other', 'orders-jet'); ?></option>
                    </select>
                </div>

                <!-- Group By Selector -->
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <label style="font-size: 13px; font-weight: 600;"><?php _e('Group By', 'orders-jet'); ?></label>
                    <select name="group_by" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 120px;">
                        <option value="day" <?php selected($reports_params['group_by'], 'day'); ?>><?php _e('Day', 'orders-jet'); ?></option>
                        <option value="week" <?php selected($reports_params['group_by'], 'week'); ?>><?php _e('Week', 'orders-jet'); ?></option>
                        <option value="month" <?php selected($reports_params['group_by'], 'month'); ?>><?php _e('Month', 'orders-jet'); ?></option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div style="margin-top: auto;">
                    <button type="submit" class="button button-primary" style="padding: 8px 20px;">
                        <?php _e('Apply Filters', 'orders-jet'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=orders-reports'); ?>" class="button" style="padding: 8px 20px; margin-left: 8px;">
                        <?php _e('Reset', 'orders-jet'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- KPI Cards Section - 4 cards per row -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <?php foreach ($formatted_reports_kpis as $kpi_key => $kpi): ?>
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid <?php echo esc_attr($kpi['color']); ?>;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 32px;"><?php echo $kpi['icon']; ?></div>
                <div style="flex: 1;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 5px;"><?php echo esc_html($kpi['label']); ?></div>
                    <div style="font-size: 24px; font-weight: 600; color: #333;"><?php echo $kpi['value']; ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <style>
    /* Responsive: 2 cards on tablets, 1 on mobile */
    @media (max-width: 1200px) {
        .wrap.oj-dashboard > div[style*="grid-template-columns: repeat(4, 1fr)"] {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    @media (max-width: 768px) {
        .wrap.oj-dashboard > div[style*="grid-template-columns: repeat(4, 1fr)"],
        .wrap.oj-dashboard > div[style*="grid-template-columns: repeat(2, 1fr)"] {
            grid-template-columns: 1fr !important;
        }
    }
    </style>

    <!-- Payment & Status Breakdown -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- Payment Methods Breakdown -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600;"><?php _e('Payment Methods', 'orders-jet'); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($payment_breakdown_data as $key => $data): ?>
                <div>
                    <div style="font-size: 14px; font-weight: 500; margin-bottom: 5px;"><?php echo esc_html($data['label']); ?></div>
                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 5px;">
                        <div style="height: 100%; background: #2271b1; width: <?php echo esc_attr($data['percentage']); ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #666;">
                        <span><?php echo number_format($data['orders']); ?> orders</span>
                        <span><?php echo wc_price($data['revenue']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Status Breakdown -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600;"><?php _e('Order Status', 'orders-jet'); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($status_breakdown_data as $key => $data): ?>
                <div>
                    <div style="font-size: 14px; font-weight: 500; margin-bottom: 5px;"><?php echo esc_html($data['label']); ?></div>
                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 5px;">
                        <div style="height: 100%; background: <?php echo esc_attr($data['color']); ?>; width: <?php echo esc_attr($data['percentage']); ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #666;">
                        <span><?php echo number_format($data['count']); ?> orders</span>
                        <span><?php echo number_format($data['percentage'], 1); ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Report Tables -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <!-- Tab Navigation -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd;">
            <button class="oj-reports-tab-btn active" data-tab="summary" type="button" style="background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 500; color: #666; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;">
                <?php _e('📅 Summary Report', 'orders-jet'); ?>
            </button>
            <button class="oj-reports-tab-btn" data-tab="category" type="button" style="background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 500; color: #666; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;">
                <?php _e('📊 Category Report', 'orders-jet'); ?>
            </button>
        </div>

        <!-- Summary Table Tab -->
        <div id="oj-reports-tab-summary" class="oj-reports-tab-content" style="display: block;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;"><?php _e('Monthly / Daily Summary', 'orders-jet'); ?></h3>
                <div style="display: flex; gap: 8px;">
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

            <table class="widefat" style="border: none; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: left;"><?php _e('Period', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Total Orders', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Completed', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Cancelled', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Revenue', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: center;"><?php _e('Actions', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($summary_table_data)): ?>
                        <?php foreach ($summary_table_data as $row): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                            <td style="padding: 12px 15px;">
                                <strong><?php echo esc_html($row['period_label']); ?></strong>
                                <div style="font-size: 11px; color: #999; margin-top: 2px;">
                                    <?php echo esc_html($row['period']); ?>
                                </div>
                            </td>
                            <td style="padding: 12px 15px; text-align: right;"><?php echo number_format_i18n($row['total_orders']); ?></td>
                            <td style="padding: 12px 15px; text-align: right;">
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #d1fae5; color: #065f46;">
                                    <?php echo number_format_i18n($row['completed_orders']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 15px; text-align: right;">
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #fee2e2; color: #991b1b;">
                                    <?php echo number_format_i18n($row['cancelled_orders']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 15px; text-align: right;"><strong><?php echo $row['revenue_formatted']; ?></strong></td>
                            <td style="padding: 12px 15px; text-align: center;">
                                <button class="button button-small oj-drill-down-btn" 
                                        data-date="<?php echo esc_attr($row['period']); ?>"
                                        data-label="<?php echo esc_attr($row['period_label']); ?>"
                                        data-total-orders="<?php echo esc_attr($row['total_orders']); ?>"
                                        onclick="console.log('Summary row clicked: <?php echo esc_js($row['period']); ?>, Total orders: <?php echo esc_js($row['total_orders']); ?>')">
                                    <?php _e('View Details', 'orders-jet'); ?> →
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 30px; text-align: center; color: #666;">
                                <?php _e('No data available for the selected filters.', 'orders-jet'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Category Table Tab -->
        <div id="oj-reports-tab-category" class="oj-reports-tab-content" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;"><?php _e('Orders by Category', 'orders-jet'); ?></h3>
                <div style="display: flex; gap: 8px;">
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

            <table class="widefat" style="border: none; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: left;"><?php _e('Category', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Orders Count', 'orders-jet'); ?></th>
                        <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Total Revenue', 'orders-jet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($category_table_data)): ?>
                        <?php foreach ($category_table_data as $row): ?>
                        <tr style="border-bottom: 1px solid #f0f0f0;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                            <td style="padding: 12px 15px;"><strong><?php echo esc_html($row['category_name']); ?></strong></td>
                            <td style="padding: 12px 15px; text-align: right;"><?php echo number_format_i18n($row['order_count']); ?></td>
                            <td style="padding: 12px 15px; text-align: right;"><strong><?php echo $row['revenue_formatted']; ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding: 30px; text-align: center; color: #666;">
                                <?php _e('No category data available.', 'orders-jet'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Drill-Down Section (Initially Hidden) -->
    <div id="oj-drill-down-section" style="display: none; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #ddd;">
            <h3 id="oj-drill-down-title" style="margin: 0; font-size: 20px; font-weight: 600;"><?php _e('Detailed Orders', 'orders-jet'); ?></h3>
            <button id="oj-close-drill-down" class="button">
                <?php _e('✕ Close', 'orders-jet'); ?>
            </button>
        </div>

        <!-- Drill-Down KPIs -->
        <div id="oj-drill-down-kpis" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
            <!-- Will be populated via AJAX -->
        </div>

        <!-- Drill-Down Orders Table -->
        <table class="widefat" style="border: none; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd;"><?php _e('Order #', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd;"><?php _e('Customer', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd;"><?php _e('Status', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: right;"><?php _e('Total', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd;"><?php _e('Payment', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd;"><?php _e('Date/Time', 'orders-jet'); ?></th>
                    <th style="padding: 15px; font-weight: 600; border-bottom: 2px solid #ddd; text-align: center;"><?php _e('Actions', 'orders-jet'); ?></th>
                </tr>
            </thead>
            <tbody id="oj-drill-down-orders">
                <!-- Will be populated via AJAX -->
            </tbody>
            <tfoot id="oj-drill-down-footer" style="display: none;">
                <tr>
                    <td colspan="7" style="padding: 0; border-top: 2px solid #ddd;">
                        <div style="padding: 20px; background: #f9fafb;">
                            <h4 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #374151;">📊 Order Status Summary</h4>
                            <div id="oj-drill-down-status-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                                <!-- Status cards will be populated via JavaScript -->
                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Helper function to get status badge styling - دالة لتنسيق شارات الحالة بألوان مختلفة
        function getStatusBadge(status) {
            // Remove 'wc-' prefix if exists
            var cleanStatus = status.replace('wc-', '');
            
            var statusMap = {
                'completed': { bg: '#d1fae5', color: '#065f46', icon: '✅', label: 'مكتمل' },
                'processing': { bg: '#fef3c7', color: '#92400e', icon: '👨‍🍳', label: 'قيد التحضير' },
                'pending': { bg: '#dbeafe', color: '#1e40af', icon: '⏳', label: 'قيد الانتظار' },
                'pending-payment': { bg: '#bfdbfe', color: '#1e3a8a', icon: '💳', label: 'انتظار الدفع' },
                'on-hold': { bg: '#fce7f3', color: '#831843', icon: '⏸️', label: 'معلق' },
                'cancelled': { bg: '#fee2e2', color: '#991b1b', icon: '❌', label: 'ملغي' },
                'refunded': { bg: '#e0e7ff', color: '#3730a3', icon: '💰', label: 'مسترد' },
                'failed': { bg: '#fecaca', color: '#7f1d1d', icon: '⚠️', label: 'فشل' },
                'checkout-draft': { bg: '#f3f4f6', color: '#4b5563', icon: '📝', label: 'مسودة' }
            };
            
            return statusMap[cleanStatus] || { bg: '#e5e7eb', color: '#374151', icon: '📋', label: cleanStatus };
        }
        
        // Tab switching for reports
        $('.oj-reports-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            
            $('.oj-reports-tab-btn').removeClass('active').css({'color': '#666', 'border-bottom-color': 'transparent'});
            $(this).addClass('active').css({'color': '#2271b1', 'border-bottom-color': '#2271b1'});
            
            $('.oj-reports-tab-content').hide();
            $('#oj-reports-tab-' + tab).show();
        });

        // Drill-down button handler
        $('.oj-drill-down-btn').on('click', function() {
            var date = $(this).data('date');
            var label = $(this).data('label');
            var expectedTotal = $(this).data('total-orders');
            
            console.log('=== DRILL-DOWN CLICKED ===');
            console.log('Period:', date);
            console.log('Label:', label);
            console.log('Expected total orders from summary:', expectedTotal);
            
            // Fix undefined issue - ensure label has a value
            var displayLabel = label && label !== 'undefined' && label.trim() !== '' ? label : (date || '<?php _e('Unknown Period', 'orders-jet'); ?>');
            $('#oj-drill-down-title').html('<?php _e('Details for', 'orders-jet'); ?> <strong>' + displayLabel + '</strong>...');
            $('#oj-drill-down-section').slideDown();
            $('#oj-drill-down-kpis').html('<p><?php _e('Loading...', 'orders-jet'); ?></p>');
            $('#oj-drill-down-orders').html('<tr><td colspan="7" style="padding: 30px; text-align: center;"><?php _e('Loading...', 'orders-jet'); ?></td></tr>');
            
            $('html, body').animate({
                scrollTop: $('#oj-drill-down-section').offset().top - 32
            }, 500);

            var filters = {
                action: 'oj_reports_drill_down',
                nonce: ojReportsData.nonce,
                date: date,
                date_preset: '<?php echo esc_js($reports_params['date_preset']); ?>',
                date_from: '<?php echo esc_js($reports_params['date_from']); ?>',
                date_to: '<?php echo esc_js($reports_params['date_to']); ?>',
                product_type: '<?php echo esc_js($reports_params['product_type']); ?>',
                order_source: '<?php echo esc_js($reports_params['order_source']); ?>',
                group_by: '<?php echo esc_js($reports_params['group_by']); ?>',
                kitchen_type: '<?php echo esc_js($reports_params['product_type']); ?>',
                order_type: '<?php echo esc_js($reports_params['order_source']); ?>'
            };

            console.log('=== DRILL-DOWN REQUEST ===');
            console.log('Date:', date);
            console.log('Filters:', filters);

            $.ajax({
                url: ojReportsData.ajaxUrl,
                type: 'POST',
                data: filters,
                success: function(response) {
                    console.log('=== DRILL-DOWN RESPONSE ===');
                    console.log('Full response:', response);
                    
                    if (response.data && response.data.orders) {
                        console.log('Number of orders in response:', response.data.orders.length);
                    }
                    
                    if (response.success) {
                        var actualTotal = response.data.orders ? response.data.orders.length : 0;
                        console.log('Actual orders returned:', actualTotal);
                        console.log('Expected orders from summary:', expectedTotal);
                        
                        if (actualTotal != expectedTotal) {
                            console.warn('⚠️ MISMATCH! Expected ' + expectedTotal + ' but got ' + actualTotal + ' orders');
                        } else {
                            console.log('✅ Match! Both show ' + actualTotal + ' orders');
                        }
                        
                        // Update title with proper label and accurate count
                        var finalLabel = label && label !== 'undefined' && label.trim() !== '' ? label : (date || '<?php _e('Period', 'orders-jet'); ?>');
                        $('#oj-drill-down-title').html('<?php _e('Details for', 'orders-jet'); ?> <strong>' + finalLabel + '</strong> <span style="color: #666; font-weight: normal; font-size: 14px;">(' + actualTotal + ' <?php _e('orders', 'orders-jet'); ?>)</span>');
                        
                        // Build KPIs HTML - only show first 4 KPIs for drill-down
                        var kpisHtml = '';
                        var kpiCount = 0;
                        $.each(response.data.kpis, function(key, kpi) {
                            if (kpiCount < 4) {
                                kpisHtml += '<div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid ' + kpi.color + ';">';
                                kpisHtml += '<div style="display: flex; align-items: center; gap: 10px;">';
                                kpisHtml += '<div style="font-size: 28px;">' + kpi.icon + '</div>';
                                kpisHtml += '<div style="flex: 1;"><div style="font-size: 12px; color: #666; margin-bottom: 3px;">' + kpi.label + '</div>';
                                kpisHtml += '<div style="font-size: 20px; font-weight: 600; color: #333;">' + kpi.value + '</div></div>';
                                kpisHtml += '</div></div>';
                                kpiCount++;
                            }
                        });
                        $('#oj-drill-down-kpis').html(kpisHtml);
                        
                        // Build orders table HTML with colored status badges
                        var ordersHtml = '';
                        var statusCounts = {};
                        
                        if (response.data.orders && response.data.orders.length > 0) {
                            $.each(response.data.orders, function(i, order) {
                                var statusBadge = getStatusBadge(order.status);
                                
                                // Count statuses for footer cards
                                if (!statusCounts[order.status]) {
                                    statusCounts[order.status] = {
                                        count: 0,
                                        badge: statusBadge
                                    };
                                }
                                statusCounts[order.status].count++;
                                
                                ordersHtml += '<tr style="border-bottom: 1px solid #f0f0f0;" onmouseover="this.style.background=\'#f8f9fa\'" onmouseout="this.style.background=\'white\'">';
                                ordersHtml += '<td style="padding: 12px 15px;"><a href="' + order.order_url + '" target="_blank"><strong>#' + order.order_number + '</strong></a></td>';
                                ordersHtml += '<td style="padding: 12px 15px;">' + order.customer_name + '</td>';
                                ordersHtml += '<td style="padding: 12px 15px;"><span class="oj-status-badge" style="display: inline-block; padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: ' + statusBadge.bg + '; color: ' + statusBadge.color + ';">' + statusBadge.icon + ' ' + statusBadge.label + '</span></td>';
                                ordersHtml += '<td style="padding: 12px 15px; text-align: right;"><strong>' + order.total_formatted + '</strong></td>';
                                ordersHtml += '<td style="padding: 12px 15px;">' + order.payment_method + '</td>';
                                ordersHtml += '<td style="padding: 12px 15px;">' + order.date_created + '</td>';
                                ordersHtml += '<td style="padding: 12px 15px; text-align: center;"><a href="' + order.order_url + '" class="button button-small" target="_blank"><?php _e('View', 'orders-jet'); ?></a></td>';
                                ordersHtml += '</tr>';
                            });
                            
                            // Build status summary cards for footer - بناء كروت الإحصائيات بدقة عالية
                            var statusCardsHtml = '';
                            var totalOrdersCount = response.data.orders.length;
                            
                            // Define all possible statuses with their details
                            var allStatuses = {
                                'completed': { bg: '#d1fae5', color: '#065f46', icon: '✅', label: 'مكتمل', count: 0 },
                                'processing': { bg: '#fef3c7', color: '#92400e', icon: '👨‍🍳', label: 'قيد التحضير', count: 0 },
                                'pending': { bg: '#dbeafe', color: '#1e40af', icon: '⏳', label: 'قيد الانتظار', count: 0 },
                                'pending-payment': { bg: '#bfdbfe', color: '#1e3a8a', icon: '💳', label: 'انتظار الدفع', count: 0 },
                                'on-hold': { bg: '#fce7f3', color: '#831843', icon: '⏸️', label: 'معلق', count: 0 },
                                'cancelled': { bg: '#fee2e2', color: '#991b1b', icon: '❌', label: 'ملغي', count: 0 },
                                'refunded': { bg: '#e0e7ff', color: '#3730a3', icon: '💰', label: 'مسترد', count: 0 },
                                'failed': { bg: '#fecaca', color: '#7f1d1d', icon: '⚠️', label: 'فشل', count: 0 }
                            };
                            
                            // Count actual orders by status
                            $.each(response.data.orders, function(i, order) {
                                var cleanStatus = order.status.replace('wc-', '');
                                if (allStatuses[cleanStatus]) {
                                    allStatuses[cleanStatus].count++;
                                } else {
                                    // Handle unknown statuses
                                    if (!allStatuses[cleanStatus]) {
                                        allStatuses[cleanStatus] = {
                                            bg: '#e5e7eb',
                                            color: '#374151',
                                            icon: '📋',
                                            label: cleanStatus,
                                            count: 1
                                        };
                                    } else {
                                        allStatuses[cleanStatus].count++;
                                    }
                                }
                            });
                            
                            // Sort statuses by count (descending) and only show statuses with orders
                            var statusArray = [];
                            $.each(allStatuses, function(status, data) {
                                if (data.count > 0) {
                                    statusArray.push({status: status, data: data});
                                }
                            });
                            statusArray.sort(function(a, b) { return b.data.count - a.data.count; });
                            
                            // Build cards HTML
                            $.each(statusArray, function(index, item) {
                                var data = item.data;
                                var percentage = totalOrdersCount > 0 ? ((data.count / totalOrdersCount) * 100).toFixed(1) : 0;
                                
                                statusCardsHtml += '<div style="background: linear-gradient(135deg, white 0%, ' + data.bg + ' 100%); padding: 15px; border-radius: 10px; border: 2px solid ' + data.bg + '; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s;" onmouseover="this.style.transform=\'scale(1.03)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                                statusCardsHtml += '<div style="display: flex; align-items: center; gap: 12px;">';
                                statusCardsHtml += '<div style="font-size: 32px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">' + data.icon + '</div>';
                                statusCardsHtml += '<div style="flex: 1;">';
                                statusCardsHtml += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">' + data.label + '</div>';
                                statusCardsHtml += '<div style="display: flex; align-items: baseline; gap: 8px;">';
                                statusCardsHtml += '<div style="font-size: 24px; font-weight: 800; color: ' + data.color + ';">' + data.count + '</div>';
                                statusCardsHtml += '<div style="font-size: 13px; color: #9ca3af; font-weight: 500;">(' + percentage + '%)</div>';
                                statusCardsHtml += '</div></div></div></div>';
                            });
                            
                            if (statusCardsHtml) {
                                $('#oj-drill-down-status-cards').html(statusCardsHtml);
                                $('#oj-drill-down-footer').show();
                            } else {
                                $('#oj-drill-down-footer').hide();
                            }
                        } else {
                            ordersHtml = '<tr><td colspan="7" style="padding: 30px; text-align: center; color: #666;"><?php _e('No orders found for this period.', 'orders-jet'); ?></td></tr>';
                            $('#oj-drill-down-footer').hide();
                        }
                        $('#oj-drill-down-orders').html(ordersHtml);
                    } else {
                        console.error('Drill-down error:', response);
                        var errorMsg = response.data && response.data.message ? response.data.message : '<?php _e('Unknown error', 'orders-jet'); ?>';
                        alert('<?php _e('Error loading drill-down data', 'orders-jet'); ?>: ' + errorMsg);
                        $('#oj-drill-down-kpis').html('<p style="color: #ef4444;">Error: ' + errorMsg + '</p>');
                        $('#oj-drill-down-orders').html('<tr><td colspan="7" style="padding: 30px; text-align: center; color: #ef4444;">Error loading data</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    alert('<?php _e('Failed to load drill-down data. Please try again.', 'orders-jet'); ?>\n\nError: ' + error);
                    $('#oj-drill-down-kpis').html('<p style="color: #ef4444;">AJAX Error: ' + error + '</p>');
                    $('#oj-drill-down-orders').html('<tr><td colspan="7" style="padding: 30px; text-align: center; color: #ef4444;">Connection error</td></tr>');
                }
            });
        });

        $('#oj-close-drill-down').on('click', function() {
            $('#oj-drill-down-section').slideUp();
        });

        // Export button handler
        $('.oj-export-btn').on('click', function() {
            var $btn = $(this);
            var type = $btn.data('type');
            var report = $btn.data('report');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('<?php _e('Exporting...', 'orders-jet'); ?>');
            
            var filters = {
                action: 'oj_reports_export',
                nonce: ojReportsData.nonce,
                export_type: type,
                report_type: report,
                date_preset: '<?php echo esc_js($reports_params['date_preset']); ?>',
                date_from: '<?php echo esc_js($reports_params['date_from']); ?>',
                date_to: '<?php echo esc_js($reports_params['date_to']); ?>',
                product_type: '<?php echo esc_js($reports_params['product_type']); ?>',
                order_source: '<?php echo esc_js($reports_params['order_source']); ?>',
                group_by: '<?php echo esc_js($reports_params['group_by']); ?>'
            };

            $.ajax({
                url: ojReportsData.ajaxUrl,
                type: 'POST',
                data: filters,
                success: function(response) {
                    if (response.success) {
                        window.open(response.data.url, '_blank');
                        alert('<?php _e('Export completed successfully!', 'orders-jet'); ?>');
                    } else {
                        alert('<?php _e('Export failed', 'orders-jet'); ?>: ' + (response.data.message || '<?php _e('Unknown error', 'orders-jet'); ?>'));
                    }
                },
                error: function() {
                    alert('<?php _e('Export failed. Please try again.', 'orders-jet'); ?>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
    });
    </script>
    
    <!-- Note: Debug panel removed for reports (cleaner interface) -->
    
    <!-- Reports-specific Toolbar -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/reports/reports-toolbar.php'; ?>
    
    <!-- Reports-specific Content Area -->
    <div id="oj-dynamic-content">
        <?php 
        // Prepare variables for the content area partial
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        
        // Include the reports-specific content area partial
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/reports/reports-content-area.php'; 
        ?>
    </div>
</div>

<!-- Reports-specific Filters Slide Panel -->
<?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/reports/reports-filters-panel.php'; ?>
