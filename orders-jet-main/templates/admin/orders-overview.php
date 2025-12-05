<?php
declare(strict_types=1);
/**
 * Orders Overview Template - Dashboard Landing Page
 * Provides quick summary of all order activities with fast access to important actions
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check - verify user permissions
if (!current_user_can('read')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Get current user role and info
$user_role = oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : '');
$user_id = get_current_user_id();
$user_name = wp_get_current_user()->display_name;
$user_function = oj_get_user_function();

// Get dashboard instance for statistics
$dashboard = new Orders_Jet_Admin_Dashboard();

// Get overview statistics (lightweight query)
$stats = $dashboard->get_overview_statistics($user_role, $user_id);
?>

<div class="wrap oj-overview">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="oj-page-title">
            📊 <?php _e('Orders Overview', 'orders-jet'); ?>
        </h1>
        <p class="oj-subtitle">
            <?php 
            printf(
                __('Welcome back, %s! Here\'s what\'s happening with your orders.', 'orders-jet'),
                '<strong>' . esc_html($user_name) . '</strong>'
            ); 
            ?>
        </p>
    </div>

    <!-- Summary Cards Grid -->
    <div class="oj-summary-cards">
        <!-- Today's Orders Card -->
        <div class="oj-summary-card oj-card-primary" data-metric="today">
            <div class="oj-card-icon">📅</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Today\'s Orders', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="today-count"><?php echo esc_html($stats['today']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-revenue"><?php echo wc_price($stats['today']['revenue']); ?></span>
                    <?php if ($stats['today']['change'] !== null): ?>
                        <span class="oj-card-change <?php echo $stats['today']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['today']['change'] >= 0 ? '↑' : '↓'; ?> 
                            <?php echo abs($stats['today']['change']); ?>%
                        </span>
                    <?php elseif ($stats['today']['count'] > 0): ?>
                        <span class="oj-card-change positive">
                            → 100%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-express&filter=all'); ?>" class="oj-card-link">
                <?php _e('View All', 'orders-jet'); ?> →
            </a>
        </div>

        <!-- In Progress Card -->
        <div class="oj-summary-card oj-card-warning" data-metric="in-progress">
            <div class="oj-card-icon">⚡</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('In Progress', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="in-progress-count"><?php echo esc_html($stats['in_progress']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-subtitle"><?php _e('Active orders being prepared', 'orders-jet'); ?></span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-express&filter=active'); ?>" class="oj-card-link">
                <?php _e('View Active', 'orders-jet'); ?> →
            </a>
        </div>

        <!-- Completed Card -->
        <div class="oj-summary-card oj-card-success" data-metric="completed">
            <div class="oj-card-icon">✅</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Completed Today', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="completed-count"><?php echo esc_html($stats['completed']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-revenue"><?php echo wc_price($stats['completed']['revenue']); ?></span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-express&filter=completed'); ?>" class="oj-card-link">
                <?php _e('View Completed', 'orders-jet'); ?> →
            </a>
        </div>

        <!-- Cancelled/Refunded Card -->
        <div class="oj-summary-card oj-card-danger" data-metric="cancelled">
            <div class="oj-card-icon">❌</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Cancelled / Refunded', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="cancelled-count"><?php echo esc_html($stats['cancelled']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-subtitle">
                        <?php 
                        printf(
                            __('%d refunded', 'orders-jet'),
                            $stats['cancelled']['refunded']
                        ); 
                        ?>
                    </span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-master-v2&filter=cancelled'); ?>" class="oj-card-link">
                <?php _e('View Details', 'orders-jet'); ?> →
            </a>
        </div>

        <!-- Unfulfilled Orders Card -->
        <div class="oj-summary-card oj-card-info" data-metric="unfulfilled">
            <div class="oj-card-icon">📦</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Unfulfilled Orders', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="unfulfilled-count"><?php echo esc_html($stats['unfulfilled']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-subtitle"><?php _e('Pending fulfillment', 'orders-jet'); ?></span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-express&filter=active'); ?>" class="oj-card-link">
                <?php _e('Process Now', 'orders-jet'); ?> →
            </a>
        </div>

        <!-- Ready for Pickup/Delivery Card -->
        <div class="oj-summary-card oj-card-ready" data-metric="ready">
            <div class="oj-card-icon">🎯</div>
            <div class="oj-card-content">
                <div class="oj-card-label"><?php _e('Ready Orders', 'orders-jet'); ?></div>
                <div class="oj-card-value" data-value="ready-count"><?php echo esc_html($stats['ready']['count']); ?></div>
                <div class="oj-card-meta">
                    <span class="oj-card-subtitle"><?php _e('Ready for pickup/delivery', 'orders-jet'); ?></span>
                </div>
            </div>
            <a href="<?php echo admin_url('admin.php?page=orders-express&filter=ready'); ?>" class="oj-card-link">
                <?php _e('View Ready', 'orders-jet'); ?> →
            </a>
        </div>
    </div>

    <!-- Essential Requirements Section -->
    <div class="oj-requirements-section">
        <h2 class="oj-section-title"><?php _e('Essential Requirements', 'orders-jet'); ?></h2>
        
        <div class="oj-requirements-grid">
            <!-- Refund Requests -->
            <div class="oj-requirement-card <?php echo $stats['refund_requests']['count'] > 0 ? 'oj-has-items' : 'oj-no-items'; ?>">
                <div class="oj-requirement-header">
                    <span class="oj-requirement-icon">💰</span>
                    <h3 class="oj-requirement-title"><?php _e('Refund Requests', 'orders-jet'); ?></h3>
                </div>
                <div class="oj-requirement-content">
                    <div class="oj-requirement-count" data-value="refund-requests-count">
                        <?php echo esc_html($stats['refund_requests']['count']); ?>
                    </div>
                    <p class="oj-requirement-description">
                        <?php 
                        if ($stats['refund_requests']['count'] > 0) {
                            _e('Orders requiring refund review', 'orders-jet');
                        } else {
                            _e('No pending refund requests', 'orders-jet');
                        }
                        ?>
                    </p>
                </div>
                <?php if ($stats['refund_requests']['count'] > 0): ?>
                <div class="oj-requirement-actions">
                    <a href="<?php echo admin_url('admin.php?page=orders-master-v2&filter=refund'); ?>" class="oj-btn oj-btn-primary">
                        <?php _e('Review Requests', 'orders-jet'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Unfulfilled Orders -->
            <div class="oj-requirement-card <?php echo $stats['unfulfilled']['count'] > 0 ? 'oj-has-items' : 'oj-no-items'; ?>">
                <div class="oj-requirement-header">
                    <span class="oj-requirement-icon">📋</span>
                    <h3 class="oj-requirement-title"><?php _e('Unfulfilled Orders', 'orders-jet'); ?></h3>
                </div>
                <div class="oj-requirement-content">
                    <div class="oj-requirement-count" data-value="unfulfilled-requirements-count">
                        <?php echo esc_html($stats['unfulfilled']['count']); ?>
                    </div>
                    <p class="oj-requirement-description">
                        <?php 
                        if ($stats['unfulfilled']['count'] > 0) {
                            _e('Orders pending processing', 'orders-jet');
                        } else {
                            _e('All orders are fulfilled', 'orders-jet');
                        }
                        ?>
                    </p>
                </div>
                <?php if ($stats['unfulfilled']['count'] > 0): ?>
                <div class="oj-requirement-actions">
                    <a href="<?php echo admin_url('admin.php?page=orders-express&filter=active'); ?>" class="oj-btn oj-btn-primary">
                        <?php _e('Process Orders', 'orders-jet'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="oj-quick-actions-section">
        <h2 class="oj-section-title"><?php _e('Quick Actions', 'orders-jet'); ?></h2>
        
        <div class="oj-quick-actions-grid">
            <?php if (current_user_can('edit_shop_orders')): ?>
            <!-- Add Order -->
            <a href="<?php echo admin_url('post-new.php?post_type=shop_order'); ?>" class="oj-quick-action-btn oj-action-add">
                <span class="oj-action-icon">➕</span>
                <span class="oj-action-label"><?php _e('Add Order', 'orders-jet'); ?></span>
                <span class="oj-action-description"><?php _e('Create new order', 'orders-jet'); ?></span>
            </a>
            <?php endif; ?>

            <!-- View Express -->
            <a href="<?php echo admin_url('admin.php?page=orders-express'); ?>" class="oj-quick-action-btn oj-action-express">
                <span class="oj-action-icon">⚡</span>
                <span class="oj-action-label"><?php _e('View Express', 'orders-jet'); ?></span>
                <span class="oj-action-description"><?php _e('Fast active orders view', 'orders-jet'); ?></span>
            </a>

            <!-- View Master -->
            <?php if (current_user_can('access_oj_manager_dashboard') || current_user_can('manage_options')): ?>
            <a href="<?php echo admin_url('admin.php?page=orders-master-v2'); ?>" class="oj-quick-action-btn oj-action-master">
                <span class="oj-action-icon">📋</span>
                <span class="oj-action-label"><?php _e('View Master', 'orders-jet'); ?></span>
                <span class="oj-action-description"><?php _e('Comprehensive management', 'orders-jet'); ?></span>
            </a>
            <?php endif; ?>

            <!-- View Reports -->
            <a href="<?php echo admin_url('admin.php?page=orders-reports'); ?>" class="oj-quick-action-btn oj-action-reports">
                <span class="oj-action-icon">📊</span>
                <span class="oj-action-label"><?php _e('View Reports', 'orders-jet'); ?></span>
                <span class="oj-action-description"><?php _e('Analytics & insights', 'orders-jet'); ?></span>
            </a>
        </div>
    </div>

    <!-- Helper Utilities Section -->
    <div class="oj-helpers-section">
        <h2 class="oj-section-title"><?php _e('Helper Utilities', 'orders-jet'); ?></h2>
        
        <div class="oj-helpers-grid">
            <!-- Walkthrough -->
            <div class="oj-helper-card" id="oj-walkthrough-card">
                <div class="oj-helper-icon">🎓</div>
                <h3 class="oj-helper-title"><?php _e('Quick Walkthrough', 'orders-jet'); ?></h3>
                <p class="oj-helper-description">
                    <?php _e('Learn how to use Orders Jet effectively with a guided tour.', 'orders-jet'); ?>
                </p>
                <button type="button" class="oj-btn oj-btn-secondary" id="oj-start-walkthrough">
                    <?php _e('Start Tour', 'orders-jet'); ?>
                </button>
            </div>

            <!-- To-Do List -->
            <div class="oj-helper-card" id="oj-todo-card">
                <div class="oj-helper-icon">✓</div>
                <h3 class="oj-helper-title"><?php _e('Daily To-Do List', 'orders-jet'); ?></h3>
                <div class="oj-todo-list" id="oj-todo-list">
                    <div class="oj-todo-item">
                        <input type="checkbox" id="todo-check-pending" class="oj-todo-checkbox">
                        <label for="todo-check-pending"><?php _e('Review pending orders', 'orders-jet'); ?></label>
                    </div>
                    <div class="oj-todo-item">
                        <input type="checkbox" id="todo-check-ready" class="oj-todo-checkbox">
                        <label for="todo-check-ready"><?php _e('Process ready orders', 'orders-jet'); ?></label>
                    </div>
                    <div class="oj-todo-item">
                        <input type="checkbox" id="todo-check-refunds" class="oj-todo-checkbox">
                        <label for="todo-check-refunds"><?php _e('Handle refund requests', 'orders-jet'); ?></label>
                    </div>
                    <div class="oj-todo-item">
                        <input type="checkbox" id="todo-check-reports" class="oj-todo-checkbox">
                        <label for="todo-check-reports"><?php _e('Check daily reports', 'orders-jet'); ?></label>
                    </div>
                </div>
                <button type="button" class="oj-btn oj-btn-text" id="oj-reset-todos">
                    <?php _e('Reset List', 'orders-jet'); ?>
                </button>
            </div>

            <!-- Quick Stats -->
            <div class="oj-helper-card" id="oj-quick-stats-card">
                <div class="oj-helper-icon">📈</div>
                <h3 class="oj-helper-title"><?php _e('Quick Stats', 'orders-jet'); ?></h3>
                <div class="oj-quick-stats-list">
                    <div class="oj-stat-row">
                        <span class="oj-stat-label"><?php _e('Avg Order Value:', 'orders-jet'); ?></span>
                        <span class="oj-stat-value">
                            <?php 
                            $avg_value = $stats['quick_stats']['avg_order_value'];
                            if ($avg_value > 0) {
                                echo wc_price($avg_value);
                            } else {
                                echo wc_price(0);
                            }
                            ?>
                            <span data-value="avg-order-value" style="display:none;"><?php echo esc_html($avg_value); ?></span>
                        </span>
                    </div>
                    <div class="oj-stat-row">
                        <span class="oj-stat-label"><?php _e('Orders This Week:', 'orders-jet'); ?></span>
                        <span class="oj-stat-value" data-value="weekly-orders">
                            <?php echo esc_html($stats['quick_stats']['weekly_orders']); ?>
                        </span>
                    </div>
                    <div class="oj-stat-row">
                        <span class="oj-stat-label"><?php _e('Completion Rate:', 'orders-jet'); ?></span>
                        <span class="oj-stat-value">
                            <span data-value="completion-rate"><?php echo esc_html($stats['quick_stats']['completion_rate']); ?></span>%
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Last Updated Info -->
    <div class="oj-last-updated">
        <span class="oj-update-icon">🔄</span>
        <span class="oj-update-text">
            <?php _e('Last updated:', 'orders-jet'); ?> 
            <span id="oj-last-update-time"><?php echo current_time('H:i:s'); ?></span>
        </span>
        <span class="oj-auto-refresh-indicator">
            <?php _e('Auto-refresh: ON', 'orders-jet'); ?>
        </span>
    </div>
</div>

<!-- Walkthrough Modal (Hidden by default) -->
<div id="oj-walkthrough-modal" class="oj-modal" style="display: none;">
    <div class="oj-modal-overlay"></div>
    <div class="oj-modal-content">
        <div class="oj-modal-header">
            <h2><?php _e('Welcome to Orders Jet!', 'orders-jet'); ?></h2>
            <button type="button" class="oj-modal-close">&times;</button>
        </div>
        <div class="oj-modal-body">
            <div class="oj-walkthrough-step" data-step="1">
                <h3><?php _e('Overview Dashboard', 'orders-jet'); ?></h3>
                <p><?php _e('This is your central hub for managing orders. The summary cards show real-time order statistics.', 'orders-jet'); ?></p>
            </div>
            <div class="oj-walkthrough-step" data-step="2" style="display: none;">
                <h3><?php _e('Quick Actions', 'orders-jet'); ?></h3>
                <p><?php _e('Use quick action buttons to navigate to different order views or create new orders.', 'orders-jet'); ?></p>
            </div>
            <div class="oj-walkthrough-step" data-step="3" style="display: none;">
                <h3><?php _e('Essential Requirements', 'orders-jet'); ?></h3>
                <p><?php _e('Keep track of critical tasks like refund requests and unfulfilled orders.', 'orders-jet'); ?></p>
            </div>
        </div>
        <div class="oj-modal-footer">
            <button type="button" class="oj-btn oj-btn-secondary" id="oj-walkthrough-prev" style="display: none;">
                <?php _e('Previous', 'orders-jet'); ?>
            </button>
            <button type="button" class="oj-btn oj-btn-primary" id="oj-walkthrough-next">
                <?php _e('Next', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>

