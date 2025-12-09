<?php
declare(strict_types=1);
/**
 * Table Sessions & Reports - Session Management and Analytics
 * 
 * Features:
 * - Open/close table sessions
 * - Assign waiter to session
 * - Link orders to session
 * - Move table (change assignment)
 * - Merge tables
 * - Reports: Average session duration, Revenue per table, Most used tables, Utilization percentage
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
$staff = $assignment_handler->get_assignable_staff();

// Get session data and reports
global $wpdb;
$today = date('Y-m-d');
$last_30_days = date('Y-m-d', strtotime('-30 days'));

// Get active sessions
$active_sessions = $wpdb->get_results($wpdb->prepare("
    SELECT 
        pm.meta_value as table_number,
        COUNT(DISTINCT p.ID) as order_count,
        SUM(pm2.meta_value) as total_revenue,
        MIN(p.post_date) as session_start,
        MAX(p.post_date) as last_order_time
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-processing', 'wc-pending', 'wc-on-hold')
    AND DATE(p.post_date) = %s
    GROUP BY pm.meta_value
", WooJet_Meta_Keys::TABLE_NUMBER, $today), ARRAY_A);

// Get historical session data for reports
$session_reports = $wpdb->get_results($wpdb->prepare("
    SELECT 
        pm.meta_value as table_number,
        COUNT(DISTINCT DATE(p.post_date)) as session_days,
        COUNT(DISTINCT p.ID) as total_orders,
        SUM(pm2.meta_value) as total_revenue,
        AVG(TIMESTAMPDIFF(MINUTE, 
            (SELECT MIN(p2.post_date) FROM {$wpdb->posts} p2 
             INNER JOIN {$wpdb->postmeta} pm3 ON p2.ID = pm3.post_id 
             WHERE pm3.meta_key = %s AND pm3.meta_value = pm.meta_value 
             AND DATE(p2.post_date) = DATE(p.post_date)),
            (SELECT MAX(p3.post_date) FROM {$wpdb->posts} p3 
             INNER JOIN {$wpdb->postmeta} pm4 ON p3.ID = pm4.post_id 
             WHERE pm4.meta_key = %s AND pm4.meta_value = pm.meta_value 
             AND DATE(p3.post_date) = DATE(p.post_date))
        )) as avg_duration_minutes
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
    WHERE p.post_type = 'shop_order'
    AND p.post_status = 'wc-completed'
    AND DATE(p.post_date) >= %s
    GROUP BY pm.meta_value
    ORDER BY total_revenue DESC
", WooJet_Meta_Keys::TABLE_NUMBER, WooJet_Meta_Keys::TABLE_NUMBER, WooJet_Meta_Keys::TABLE_NUMBER, $last_30_days), ARRAY_A);

// Calculate overall statistics
$total_sessions_today = count($active_sessions);
$total_revenue_today = array_sum(array_column($active_sessions, 'total_revenue'));
$avg_duration_all = 0;
$total_duration_minutes = 0;
$session_count = 0;

foreach ($session_reports as $report) {
    if ($report['avg_duration_minutes'] > 0) {
        $total_duration_minutes += floatval($report['avg_duration_minutes']) * intval($report['session_days']);
        $session_count += intval($report['session_days']);
    }
}

if ($session_count > 0) {
    $avg_duration_all = round($total_duration_minutes / $session_count);
}

// Most used tables
$most_used_tables = array_slice($session_reports, 0, 5);

// Table utilization
$total_tables = count($tables);
$tables_with_sessions = count(array_filter($session_reports, function($r) { return $r['total_orders'] > 0; }));
$utilization_percentage = $total_tables > 0 ? round(($tables_with_sessions / $total_tables) * 100, 1) : 0;

// Enqueue assets
wp_enqueue_style('oj-table-sessions', ORDERS_JET_PLUGIN_URL . 'assets/css/table-sessions-reports.css', array(), ORDERS_JET_VERSION);
wp_enqueue_script('oj-table-sessions', ORDERS_JET_PLUGIN_URL . 'assets/js/table-sessions-reports.js', array('jquery'), ORDERS_JET_VERSION, true);
wp_localize_script('oj-table-sessions', 'ojTableSessions', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('oj_table_sessions_nonce'),
    'tables' => array_map(function($t) { return array('id' => $t['id'], 'number' => $t['number'], 'title' => $t['title']); }, $tables),
    'staff' => array_map(function($s) { return array('id' => $s->ID, 'name' => $s->display_name); }, $staff)
));

?>

<div class="wrap oj-table-sessions-reports">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-chart-line" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
            <?php _e('📊 Table Sessions & Reports', 'orders-jet'); ?>
        </h1>
        <hr class="wp-header-end">
        
        <p class="description">
            <?php _e('Manage table sessions and generate comprehensive reports for table utilization and performance.', 'orders-jet'); ?>
        </p>
    </div>
    
    <!-- Tabs -->
    <div class="oj-tabs">
        <button class="oj-tab-button active" data-tab="sessions">
            <?php _e('Session Management', 'orders-jet'); ?>
        </button>
        <button class="oj-tab-button" data-tab="reports">
            <?php _e('Reports & Analytics', 'orders-jet'); ?>
        </button>
    </div>
    
    <!-- Sessions Tab -->
    <div class="oj-tab-content active" id="oj-tab-sessions">
        <!-- Active Sessions -->
        <div class="oj-section">
            <h2><?php _e('Active Sessions', 'orders-jet'); ?></h2>
            
            <?php if (empty($active_sessions)): ?>
                <div class="oj-empty-state">
                    <p><?php _e('No active sessions at the moment.', 'orders-jet'); ?></p>
                </div>
            <?php else: ?>
                <div class="oj-sessions-grid">
                    <?php foreach ($active_sessions as $session): 
                        $table_number = $session['table_number'];
                        $table_info = null;
                        foreach ($tables as $t) {
                            if ($t['number'] == $table_number) {
                                $table_info = $t;
                                break;
                            }
                        }
                        
                        $session_start = strtotime($session['session_start']);
                        $session_duration = round((time() - $session_start) / 60); // minutes
                    ?>
                        <div class="oj-session-card" data-table-number="<?php echo esc_attr($table_number); ?>">
                            <div class="oj-session-header">
                                <h3><?php echo esc_html($table_number); ?></h3>
                                <span class="oj-session-duration"><?php echo esc_html($session_duration); ?> <?php _e('min', 'orders-jet'); ?></span>
                            </div>
                            
                            <div class="oj-session-body">
                                <div class="oj-session-info">
                                    <div class="oj-info-item">
                                        <span class="oj-label"><?php _e('Orders:', 'orders-jet'); ?></span>
                                        <span class="oj-value"><?php echo esc_html($session['order_count']); ?></span>
                                    </div>
                                    <div class="oj-info-item">
                                        <span class="oj-label"><?php _e('Revenue:', 'orders-jet'); ?></span>
                                        <span class="oj-value"><?php echo wc_price($session['total_revenue']); ?></span>
                                    </div>
                                    <div class="oj-info-item">
                                        <span class="oj-label"><?php _e('Started:', 'orders-jet'); ?></span>
                                        <span class="oj-value"><?php echo date('H:i', $session_start); ?></span>
                                    </div>
                                </div>
                                
                                <div class="oj-session-actions">
                                    <select class="oj-waiter-select" data-table-number="<?php echo esc_attr($table_number); ?>">
                                        <option value=""><?php _e('-- Assign Waiter --', 'orders-jet'); ?></option>
                                        <?php foreach ($staff as $staff_member): 
                                            $selected = ($table_info && $table_info['assigned_waiter'] && $table_info['assigned_waiter']->ID == $staff_member->ID) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo esc_attr($staff_member->ID); ?>" <?php echo $selected; ?>>
                                                <?php echo esc_html($staff_member->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button class="button button-small oj-link-orders-btn" data-table-number="<?php echo esc_attr($table_number); ?>">
                                        <?php _e('Link Orders', 'orders-jet'); ?>
                                    </button>
                                    
                                    <button class="button button-small oj-move-table-btn" data-table-number="<?php echo esc_attr($table_number); ?>">
                                        <?php _e('Move Table', 'orders-jet'); ?>
                                    </button>
                                    
                                    <button class="button button-small oj-merge-table-btn" data-table-number="<?php echo esc_attr($table_number); ?>">
                                        <?php _e('Merge', 'orders-jet'); ?>
                                    </button>
                                    
                                    <button class="button button-small button-primary oj-close-session-btn" data-table-number="<?php echo esc_attr($table_number); ?>">
                                        <?php _e('Close Session', 'orders-jet'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="oj-section">
            <h2><?php _e('Quick Actions', 'orders-jet'); ?></h2>
            <div class="oj-quick-actions">
                <button class="button button-primary oj-open-session-btn">
                    <?php _e('Open New Session', 'orders-jet'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Reports Tab -->
    <div class="oj-tab-content" id="oj-tab-reports">
        <!-- Report Cards -->
        <div class="oj-report-cards">
            <div class="oj-report-card">
                <div class="oj-report-icon">⏱️</div>
                <div class="oj-report-content">
                    <div class="oj-report-label"><?php _e('Average Session Duration', 'orders-jet'); ?></div>
                    <div class="oj-report-value"><?php echo esc_html($avg_duration_all); ?> <?php _e('minutes', 'orders-jet'); ?></div>
                </div>
            </div>
            
            <div class="oj-report-card">
                <div class="oj-report-icon">💰</div>
                <div class="oj-report-content">
                    <div class="oj-report-label"><?php _e('Total Revenue Today', 'orders-jet'); ?></div>
                    <div class="oj-report-value"><?php echo wc_price($total_revenue_today); ?></div>
                </div>
            </div>
            
            <div class="oj-report-card">
                <div class="oj-report-icon">📊</div>
                <div class="oj-report-content">
                    <div class="oj-report-label"><?php _e('Table Utilization', 'orders-jet'); ?></div>
                    <div class="oj-report-value"><?php echo esc_html($utilization_percentage); ?>%</div>
                    <div class="oj-report-subtitle"><?php echo esc_html($tables_with_sessions); ?>/<?php echo esc_html($total_tables); ?> <?php _e('tables active', 'orders-jet'); ?></div>
                </div>
            </div>
            
            <div class="oj-report-card">
                <div class="oj-report-icon">📈</div>
                <div class="oj-report-content">
                    <div class="oj-report-label"><?php _e('Active Sessions Today', 'orders-jet'); ?></div>
                    <div class="oj-report-value"><?php echo esc_html($total_sessions_today); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Per Table -->
        <div class="oj-section">
            <h2><?php _e('Revenue Per Table (Last 30 Days)', 'orders-jet'); ?></h2>
            <div class="oj-reports-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Table', 'orders-jet'); ?></th>
                            <th><?php _e('Sessions', 'orders-jet'); ?></th>
                            <th><?php _e('Total Orders', 'orders-jet'); ?></th>
                            <th><?php _e('Total Revenue', 'orders-jet'); ?></th>
                            <th><?php _e('Avg Duration', 'orders-jet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($session_reports)): ?>
                            <tr>
                                <td colspan="5" class="oj-empty-state"><?php _e('No session data available.', 'orders-jet'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($session_reports as $report): 
                                $avg_duration = $report['avg_duration_minutes'] > 0 ? round($report['avg_duration_minutes']) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($report['table_number']); ?></strong></td>
                                    <td><?php echo esc_html($report['session_days']); ?></td>
                                    <td><?php echo esc_html($report['total_orders']); ?></td>
                                    <td><?php echo wc_price($report['total_revenue']); ?></td>
                                    <td><?php echo esc_html($avg_duration); ?> <?php _e('min', 'orders-jet'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Most Used Tables -->
        <div class="oj-section">
            <h2><?php _e('Most Used Tables', 'orders-jet'); ?></h2>
            <div class="oj-most-used-tables">
                <?php if (empty($most_used_tables)): ?>
                    <p><?php _e('No data available.', 'orders-jet'); ?></p>
                <?php else: ?>
                    <?php foreach ($most_used_tables as $index => $table): ?>
                        <div class="oj-table-rank-card">
                            <div class="oj-rank-number"><?php echo $index + 1; ?></div>
                            <div class="oj-rank-content">
                                <div class="oj-rank-table"><?php echo esc_html($table['table_number']); ?></div>
                                <div class="oj-rank-stats">
                                    <span><?php echo esc_html($table['session_days']); ?> <?php _e('sessions', 'orders-jet'); ?></span>
                                    <span><?php echo wc_price($table['total_revenue']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="oj-open-session-modal" class="oj-modal" style="display: none;">
    <div class="oj-modal-content">
        <span class="oj-modal-close">&times;</span>
        <h2><?php _e('Open New Session', 'orders-jet'); ?></h2>
        <form id="oj-open-session-form">
            <div class="oj-form-group">
                <label><?php _e('Select Table:', 'orders-jet'); ?></label>
                <select name="table_number" required>
                    <option value=""><?php _e('-- Select Table --', 'orders-jet'); ?></option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?php echo esc_attr($table['number']); ?>">
                            <?php echo esc_html($table['number'] . ' - ' . $table['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="oj-form-group">
                <label><?php _e('Assign Waiter (Optional):', 'orders-jet'); ?></label>
                <select name="waiter_id">
                    <option value=""><?php _e('-- No Waiter --', 'orders-jet'); ?></option>
                    <?php foreach ($staff as $staff_member): ?>
                        <option value="<?php echo esc_attr($staff_member->ID); ?>">
                            <?php echo esc_html($staff_member->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="oj-form-actions">
                <button type="submit" class="button button-primary"><?php _e('Open Session', 'orders-jet'); ?></button>
                <button type="button" class="button oj-modal-cancel"><?php _e('Cancel', 'orders-jet'); ?></button>
            </div>
        </form>
    </div>
</div>

