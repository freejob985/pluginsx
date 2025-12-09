<?php
declare(strict_types=1);
/**
 * Table Overview - Comprehensive Table Management Dashboard
 * 
 * Features:
 * - Summary cards (Total, Available, Occupied, Reserved)
 * - Table grid view with filters
 * - Real-time status updates
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get handler instance
$handler_factory = new Orders_Jet_Handler_Factory(
    new Orders_Jet_Tax_Service(),
    new Orders_Jet_Kitchen_Service(),
    new Orders_Jet_Notification_Service()
);
$assignment_handler = $handler_factory->get_table_assignment_handler();

// Get all tables
$tables = $assignment_handler->get_tables_with_assignments();

// Calculate summary statistics
$total_tables = count($tables);
$available_tables = 0;
$occupied_tables = 0;
$reserved_tables = 0;

foreach ($tables as $table) {
    $status = $table['status'] ?? 'available';
    switch ($status) {
        case 'available':
            $available_tables++;
            break;
        case 'occupied':
            $occupied_tables++;
            break;
        case 'reserved':
            $reserved_tables++;
            break;
    }
}

// Get current orders for tables to show session info
global $wpdb;
$today = date('Y-m-d');
$active_orders = $wpdb->get_results($wpdb->prepare("
    SELECT pm.meta_value as table_number, COUNT(*) as order_count, SUM(pm2.meta_value) as total_revenue
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-processing', 'wc-pending', 'wc-on-hold')
    AND DATE(p.post_date) = %s
    GROUP BY pm.meta_value
", WooJet_Meta_Keys::TABLE_NUMBER, $today), ARRAY_A);

$orders_by_table = array();
foreach ($active_orders as $order_data) {
    $orders_by_table[$order_data['table_number']] = array(
        'count' => intval($order_data['order_count']),
        'revenue' => floatval($order_data['total_revenue'])
    );
}

// Enqueue assets
wp_enqueue_style('oj-table-overview', ORDERS_JET_PLUGIN_URL . 'assets/css/table-overview.css', array(), ORDERS_JET_VERSION);
wp_enqueue_script('oj-table-overview', ORDERS_JET_PLUGIN_URL . 'assets/js/table-overview.js', array('jquery'), ORDERS_JET_VERSION, true);
wp_localize_script('oj-table-overview', 'ojTableOverview', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('oj_table_overview_nonce')
));

?>

<div class="wrap oj-table-overview">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-grid-view" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
            <?php _e('🍽️ Table Overview', 'orders-jet'); ?>
        </h1>
        <a href="<?php echo admin_url('edit.php?post_type=oj_table'); ?>" class="page-title-action">
            <?php _e('Manage Tables', 'orders-jet'); ?>
        </a>
        <hr class="wp-header-end">
        
        <p class="description">
            <?php _e('Monitor and manage all dining tables in real-time. View table status, guest information, and active sessions.', 'orders-jet'); ?>
        </p>
    </div>
    
    <!-- Summary Cards -->
    <div class="oj-summary-cards">
        <div class="oj-summary-card oj-card-primary">
            <div class="oj-card-icon">🍽️</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Total Tables', 'orders-jet'); ?></div>
                <div class="oj-card-value" id="oj-total-tables"><?php echo esc_html($total_tables); ?></div>
            </div>
        </div>
        
        <div class="oj-summary-card oj-card-success">
            <div class="oj-card-icon">✅</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Available', 'orders-jet'); ?></div>
                <div class="oj-card-value" id="oj-available-tables"><?php echo esc_html($available_tables); ?></div>
            </div>
        </div>
        
        <div class="oj-summary-card oj-card-warning">
            <div class="oj-card-icon">🟡</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Occupied', 'orders-jet'); ?></div>
                <div class="oj-card-value" id="oj-occupied-tables"><?php echo esc_html($occupied_tables); ?></div>
            </div>
        </div>
        
        <div class="oj-summary-card oj-card-danger">
            <div class="oj-card-icon">🔴</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Reserved', 'orders-jet'); ?></div>
                <div class="oj-card-value" id="oj-reserved-tables"><?php echo esc_html($reserved_tables); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="oj-filters-panel">
        <div class="oj-filters-row">
            <div class="oj-filter-group">
                <label><?php _e('Area:', 'orders-jet'); ?></label>
                <select id="oj-filter-area" class="oj-filter-select">
                    <option value=""><?php _e('All Areas', 'orders-jet'); ?></option>
                    <option value="indoor"><?php _e('Indoor', 'orders-jet'); ?></option>
                    <option value="outdoor"><?php _e('Outdoor', 'orders-jet'); ?></option>
                </select>
            </div>
            
            <div class="oj-filter-group">
                <label><?php _e('Capacity:', 'orders-jet'); ?></label>
                <select id="oj-filter-capacity" class="oj-filter-select">
                    <option value=""><?php _e('All Capacities', 'orders-jet'); ?></option>
                    <option value="1-2">1-2</option>
                    <option value="3-4">3-4</option>
                    <option value="5-6">5-6</option>
                    <option value="7+">7+</option>
                </select>
            </div>
            
            <div class="oj-filter-group">
                <label><?php _e('Status:', 'orders-jet'); ?></label>
                <select id="oj-filter-status" class="oj-filter-select">
                    <option value=""><?php _e('All Statuses', 'orders-jet'); ?></option>
                    <option value="available"><?php _e('Available', 'orders-jet'); ?></option>
                    <option value="occupied"><?php _e('Occupied', 'orders-jet'); ?></option>
                    <option value="reserved"><?php _e('Reserved', 'orders-jet'); ?></option>
                    <option value="maintenance"><?php _e('Maintenance', 'orders-jet'); ?></option>
                </select>
            </div>
            
            <div class="oj-filter-group">
                <input type="text" id="oj-search-tables" placeholder="<?php _e('Search tables...', 'orders-jet'); ?>" class="oj-search-input">
            </div>
            
            <div class="oj-filter-group">
                <button id="oj-reset-filters" class="button"><?php _e('Reset', 'orders-jet'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Tables Grid -->
    <div class="oj-tables-grid" id="oj-tables-grid">
        <?php if (empty($tables)): ?>
            <div class="oj-empty-state">
                <div class="oj-empty-icon">🍽️</div>
                <h3><?php _e('No Tables Found', 'orders-jet'); ?></h3>
                <p><?php _e('Create your first table to get started.', 'orders-jet'); ?></p>
                <a href="<?php echo admin_url('post-new.php?post_type=oj_table'); ?>" class="button button-primary">
                    <?php _e('Add New Table', 'orders-jet'); ?>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($tables as $table): 
                $table_number = $table['number'] ?? '';
                $table_id = $table['id'] ?? 0;
                $table_title = $table['title'] ?? '';
                $capacity = $table['capacity'] ?? 0;
                $status = $table['status'] ?? 'available';
                $location = $table['location'] ?? '';
                $assigned_waiter = $table['assigned_waiter'] ?? null;
                
                // Get active orders for this table
                $table_orders = $orders_by_table[$table_number] ?? array();
                $order_count = $table_orders['count'] ?? 0;
                $session_revenue = $table_orders['revenue'] ?? 0;
                
                // Determine area (indoor/outdoor) from location
                $area = 'indoor';
                if (stripos($location, 'outdoor') !== false || stripos($location, 'terrace') !== false || stripos($location, 'patio') !== false) {
                    $area = 'outdoor';
                }
                
                // Status colors
                $status_colors = array(
                    'available' => array('color' => '#4caf50', 'bg' => '#e8f5e9', 'icon' => '✅'),
                    'occupied' => array('color' => '#ff9800', 'bg' => '#fff3e0', 'icon' => '🟡'),
                    'reserved' => array('color' => '#f44336', 'bg' => '#ffebee', 'icon' => '🔴'),
                    'maintenance' => array('color' => '#9e9e9e', 'bg' => '#f5f5f5', 'icon' => '🔧')
                );
                $status_info = $status_colors[$status] ?? $status_colors['available'];
            ?>
                <div class="oj-table-card" 
                     data-table-id="<?php echo esc_attr($table_id); ?>"
                     data-table-number="<?php echo esc_attr($table_number); ?>"
                     data-status="<?php echo esc_attr($status); ?>"
                     data-area="<?php echo esc_attr($area); ?>"
                     data-capacity="<?php echo esc_attr($capacity); ?>">
                    
                    <div class="oj-table-card-header">
                        <div class="oj-table-number"><?php echo esc_html($table_number); ?></div>
                        <div class="oj-table-status-badge" style="background: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                            <?php echo $status_info['icon']; ?> <?php echo esc_html(ucfirst($status)); ?>
                        </div>
                    </div>
                    
                    <div class="oj-table-card-body">
                        <div class="oj-table-info-row">
                            <span class="oj-table-label"><?php _e('Capacity:', 'orders-jet'); ?></span>
                            <span class="oj-table-value"><?php echo esc_html($capacity); ?> <?php _e('guests', 'orders-jet'); ?></span>
                        </div>
                        
                        <?php if ($location): ?>
                        <div class="oj-table-info-row">
                            <span class="oj-table-label"><?php _e('Area:', 'orders-jet'); ?></span>
                            <span class="oj-table-value"><?php echo esc_html(ucfirst($area)); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order_count > 0): ?>
                        <div class="oj-table-session-info">
                            <div class="oj-session-orders">
                                <strong><?php echo esc_html($order_count); ?></strong> <?php _e('active order(s)', 'orders-jet'); ?>
                            </div>
                            <div class="oj-session-revenue">
                                <?php echo wc_price($session_revenue); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($assigned_waiter): ?>
                        <div class="oj-table-waiter">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php echo esc_html($assigned_waiter->display_name); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="oj-table-card-actions">
                        <a href="<?php echo admin_url('post.php?post=' . $table_id . '&action=edit'); ?>" class="button button-small">
                            <?php _e('Edit', 'orders-jet'); ?>
                        </a>
                        <?php if ($order_count > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=orders-express&table=' . urlencode($table_number)); ?>" class="button button-small button-primary">
                            <?php _e('View Orders', 'orders-jet'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

