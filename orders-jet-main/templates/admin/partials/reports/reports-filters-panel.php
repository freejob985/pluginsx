<?php
/**
 * Filters Slide Panel - Advanced Filters Interface
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Filters Slide-out Panel -->
<div id="oj-filters-overlay" class="oj-filters-overlay">
    <div class="oj-filters-panel">
        <!-- Panel Header with Tabs -->
        <div class="oj-filters-header">
            <div class="oj-filters-tabs">
                <button class="oj-tab-btn active" data-tab="filters" type="button">
                    <span class="oj-tab-icon">🔧</span>
                    <?php _e('Filters', 'orders-jet'); ?>
                </button>
                <button class="oj-tab-btn" data-tab="saved-views" type="button">
                    <span class="oj-tab-icon">💾</span>
                    <?php _e('Views', 'orders-jet'); ?>
                </button>
            </div>
            <button id="oj-filters-close" class="oj-filters-close" type="button" title="<?php _e('Close', 'orders-jet'); ?>">
                <span>✕</span>
            </button>
        </div>

        <!-- Panel Body -->
        <div class="oj-filters-body">
            <!-- Tab 1: Filters Content -->
            <div id="oj-tab-filters" class="oj-tab-content active">
                <!-- Filters Content -->
                <div class="oj-filters-content">
                
                <!-- Date Range Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('📅 Date Range:', 'orders-jet'); ?></label>
                    <select id="panel-date-preset" class="oj-filter-select" data-param="date_preset">
                        <option value=""><?php _e('All time', 'orders-jet'); ?></option>
                        <option value="last_2_hours"><?php _e('⏰ Last 2 hours', 'orders-jet'); ?></option>
                        <option value="last_4_hours"><?php _e('⏰ Last 4 hours', 'orders-jet'); ?></option>
                        <option value="today"><?php _e('Today', 'orders-jet'); ?></option>
                        <option value="yesterday"><?php _e('Yesterday', 'orders-jet'); ?></option>
                        <option value="this_week"><?php _e('This week', 'orders-jet'); ?></option>
                        <option value="week_to_date"><?php _e('Week to date', 'orders-jet'); ?></option>
                        <option value="month_to_date"><?php _e('Month to date', 'orders-jet'); ?></option>
                        <option value="last_week"><?php _e('Last week', 'orders-jet'); ?></option>
                        <option value="last_month"><?php _e('Last month', 'orders-jet'); ?></option>
                        <option value="custom"><?php _e('Custom range...', 'orders-jet'); ?></option>
                    </select>
                    
                    <!-- Custom Date Inputs -->
                    <div id="panel-custom-dates" class="oj-custom-dates-panel" style="display: none;">
                        <div class="oj-date-inputs">
                            <input type="date" id="panel-date-from" class="oj-filter-input" data-param="date_from" placeholder="<?php _e('From', 'orders-jet'); ?>">
                            <span class="oj-date-separator">to</span>
                            <input type="date" id="panel-date-to" class="oj-filter-input" data-param="date_to" placeholder="<?php _e('To', 'orders-jet'); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Table Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('🏠 Table Filter:', 'orders-jet'); ?></label>
                    <?php
                    // Check if the method exists and get available tables
                    if (class_exists('Orders_Master_Query_Builder') && method_exists('Orders_Master_Query_Builder', 'get_available_tables')) {
                        try {
                            $available_tables = Orders_Master_Query_Builder::get_available_tables();
                            ?>
                            <select id="panel-search" class="oj-filter-select oj-panel-table-select" data-param="search">
                                <option value=""><?php _e('🏠 All Tables', 'orders-jet'); ?></option>
                                <?php
                                foreach ($available_tables as $table) {
                                    echo '<option value="' . esc_attr($table['value']) . '">' . esc_html($table['label']) . '</option>';
                                }
                                ?>
                            </select>
                            <?php
                        } catch (Exception $e) {
                            // Fallback to search input if there's an error
                            ?>
                            <input type="text" id="panel-search" class="oj-filter-input" data-param="search" 
                                   placeholder="<?php _e('Search orders... (fallback)', 'orders-jet'); ?>">
                            <?php
                        }
                    } else {
                        // Fallback to search input if class not available
                        ?>
                        <input type="text" id="panel-search" class="oj-filter-input" data-param="search" 
                               placeholder="<?php _e('Search orders... (class not found)', 'orders-jet'); ?>">
                        <?php
                    }
                    ?>
                </div>
                
                <!-- Order Status Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('📊 Order Status:', 'orders-jet'); ?></label>
                    <select id="panel-filter" class="oj-filter-select" data-param="filter">
                        <option value="all"><?php _e('All Orders', 'orders-jet'); ?></option>
                        <option value="active"><?php _e('🔥 Active', 'orders-jet'); ?></option>
                        <option value="kitchen"><?php _e('👨‍🍳 Kitchen', 'orders-jet'); ?></option>
                        <option value="ready"><?php _e('✅ Ready', 'orders-jet'); ?></option>
                        <option value="completed"><?php _e('✔️ Completed', 'orders-jet'); ?></option>
                        <option value="on-hold"><?php _e('⏸️ On Hold', 'orders-jet'); ?></option>
                        <option value="cancelled"><?php _e('❌ Cancelled', 'orders-jet'); ?></option>
                        <option value="refunded"><?php _e('💰 Refunded', 'orders-jet'); ?></option>
                        <option value="failed"><?php _e('⚠️ Failed', 'orders-jet'); ?></option>
                        <option value="pending-payment"><?php _e('💳 Pending Payment', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Order Type Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('🍽️ Order Type:', 'orders-jet'); ?></label>
                    <select id="panel-order-type" class="oj-filter-select" data-param="order_type">
                        <option value=""><?php _e('All Types', 'orders-jet'); ?></option>
                        <option value="dinein"><?php _e('🍽️ Dine-in', 'orders-jet'); ?></option>
                        <option value="takeaway"><?php _e('📦 Takeaway', 'orders-jet'); ?></option>
                        <option value="delivery"><?php _e('🚚 Delivery', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Kitchen Type Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('👨‍🍳 Kitchen Type:', 'orders-jet'); ?></label>
                    <select id="panel-kitchen-type" class="oj-filter-select" data-param="kitchen_type">
                        <option value=""><?php _e('All Kitchen', 'orders-jet'); ?></option>
                        <option value="food"><?php _e('🍕 Food', 'orders-jet'); ?></option>
                        <option value="beverages"><?php _e('🥤 Beverages', 'orders-jet'); ?></option>
                        <option value="mixed"><?php _e('🍽️ Mixed Only', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Customer Type Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('👥 Customer Type:', 'orders-jet'); ?></label>
                    <select id="panel-customer-type" class="oj-filter-select" data-param="customer_type">
                        <option value=""><?php _e('All Customers', 'orders-jet'); ?></option>
                        <option value="table_guest"><?php _e('🍽️ Table Guests', 'orders-jet'); ?></option>
                        <option value="registered_customer"><?php _e('👤 Registered Customers', 'orders-jet'); ?></option>
                        <option value="repeat_visitor"><?php _e('🔄 Returning Customers', 'orders-jet'); ?></option>
                        <option value="new_session"><?php _e('🆕 New Table Session', 'orders-jet'); ?></option>
                        <option value="continuing_session"><?php _e('➕ Continuing Session', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Sort Options -->
                <div class="oj-filter-group">
                    <label><?php _e('📈 Sort By:', 'orders-jet'); ?></label>
                    <select id="panel-orderby" class="oj-filter-select" data-param="orderby">
                        <option value="date_created"><?php _e('📅 Date Created', 'orders-jet'); ?></option>
                        <option value="date_modified"><?php _e('🔄 Date Modified', 'orders-jet'); ?></option>
                        <option value="total"><?php _e('💰 Amount', 'orders-jet'); ?></option>
                        <option value="order_number"><?php _e('📋 Order Number', 'orders-jet'); ?></option>
                        <option value="customer_name"><?php _e('👤 Customer Name', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Sort Direction -->
                <div class="oj-filter-group">
                    <label><?php _e('📊 Sort Direction:', 'orders-jet'); ?></label>
                    <select id="panel-order" class="oj-filter-select" data-param="order">
                        <option value="DESC"><?php _e('↓ Newest/Highest First', 'orders-jet'); ?></option>
                        <option value="ASC"><?php _e('↑ Oldest/Lowest First', 'orders-jet'); ?></option>
                    </select>
                </div>
                
                <!-- Payment Method Filter -->
                <div class="oj-filter-group">
                    <label><?php _e('💳 Payment Method:', 'orders-jet'); ?></label>
                    <select id="panel-payment-method" class="oj-filter-select" data-param="payment_method">
                        <option value=""><?php _e('All Payment Methods', 'orders-jet'); ?></option>
                        <option value="cash"><?php _e('💵 Cash', 'orders-jet'); ?></option>
                        <option value="card"><?php _e('💳 Card', 'orders-jet'); ?></option>
                        <option value="other"><?php _e('🔧 Other', 'orders-jet'); ?></option>
                        <option value="online"><?php _e('📱 Online Payment', 'orders-jet'); ?></option>
                        <option value="bacs"><?php _e('🏦 Bank Transfer (BACS)', 'orders-jet'); ?></option>
                        <option value="cod"><?php _e('💰 Cash on Delivery', 'orders-jet'); ?></option>
                        <option value="stripe"><?php _e('💳 Stripe', 'orders-jet'); ?></option>
                        <option value="paypal"><?php _e('🅿️ PayPal', 'orders-jet'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Additional Filters Section -->
            <div class="oj-filters-section">
                <div class="oj-filter-group">
                    <label><?php _e('💰 Amount Filter:', 'orders-jet'); ?></label>
                    <div class="oj-amount-filter-container">
                        <select id="panel-amount-type" class="oj-filter-select oj-amount-type" data-param="amount_type">
                            <option value=""><?php _e('Any Amount', 'orders-jet'); ?></option>
                            <option value="equals"><?php _e('Equals', 'orders-jet'); ?></option>
                            <option value="less_than"><?php _e('Less than', 'orders-jet'); ?></option>
                            <option value="greater_than"><?php _e('Greater than', 'orders-jet'); ?></option>
                            <option value="between"><?php _e('Between', 'orders-jet'); ?></option>
                        </select>
                        
                        <!-- Single value input (for equals, less_than, greater_than) -->
                        <div id="amount-single-input" class="oj-amount-input-group" style="display: none;">
                            <input type="number" id="panel-amount-value" class="oj-filter-input oj-amount-input" 
                                   placeholder="0.00" step="0.01" min="0" data-param="amount_value">
                        </div>
                        
                        <!-- Range inputs (for between) -->
                        <div id="amount-range-inputs" class="oj-amount-input-group oj-range-inputs" style="display: none;">
                            <input type="number" id="panel-amount-min" class="oj-filter-input oj-range-input" 
                                   placeholder="<?php _e('Min', 'orders-jet'); ?>" step="0.01" min="0" data-param="amount_min">
                            <span class="oj-range-separator">to</span>
                            <input type="number" id="panel-amount-max" class="oj-filter-input oj-range-input" 
                                   placeholder="<?php _e('Max', 'orders-jet'); ?>" step="0.01" min="0" data-param="amount_max">
                        </div>
                    </div>
                </div>
            
            <!-- Assigned Waiter Filter -->
            <div class="oj-filter-group">
                <label><?php _e('👥 Assigned Waiter:', 'orders-jet'); ?></label>
                    <select class="oj-filter-select" disabled>
                        <option value=""><?php _e('All Waiters', 'orders-jet'); ?></option>
                        <option value="unassigned"><?php _e('Unassigned', 'orders-jet'); ?></option>
                    </select>
                    <small class="oj-filter-note"><?php _e('Coming in Phase 2', 'orders-jet'); ?></small>
                </div>
                </div>
            </div>
            
            <!-- Tab 2: Saved Views Content -->
            <div id="oj-tab-saved-views" class="oj-tab-content">
                <div class="oj-saved-views-container">
                    <!-- Views Header -->
                    <div class="oj-saved-views-header">
                        <h4><?php _e('💾 My Views', 'orders-jet'); ?></h4>
                        <p class="oj-section-description"><?php _e('Manage your saved filter combinations', 'orders-jet'); ?></p>
                    </div>
                    
                    <!-- Saved Views List -->
                    <div class="oj-saved-views-list-container">
                        <div id="oj-saved-views-list" class="oj-saved-views-list">
                            <!-- Loading State -->
                            <div class="oj-saved-views-loading" style="display: none;">
                                <div class="oj-saved-views-spinner">
                                    <div class="oj-spinner-dot"></div>
                                    <div class="oj-spinner-dot"></div>
                                    <div class="oj-spinner-dot"></div>
                                </div>
                                <span><?php _e('Loading views...', 'orders-jet'); ?></span>
                            </div>
                            
                            <!-- Empty State -->
                            <div class="oj-saved-views-empty">
                                <span class="oj-empty-icon">📝</span>
                                <p><?php _e('No saved views yet.', 'orders-jet'); ?></p>
                                <small><?php _e('Switch to the Filters tab, set up your desired filters, then click "Save View" below.', 'orders-jet'); ?></small>
                            </div>
                            
                            <!-- Populated State -->
                            <div class="oj-saved-views-items">
                                <!-- Saved views will be populated here via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- View Statistics (Optional) -->
                    <div class="oj-saved-views-stats" style="display: none;">
                        <div class="oj-stats-item">
                            <span class="oj-stats-label"><?php _e('Total Views:', 'orders-jet'); ?></span>
                            <span class="oj-stats-value" id="oj-stats-total">0</span>
                        </div>
                        <div class="oj-stats-item">
                            <span class="oj-stats-label"><?php _e('Most Used:', 'orders-jet'); ?></span>
                            <span class="oj-stats-value" id="oj-stats-most-used">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Footer -->
        <div class="oj-filters-footer">
            <button id="oj-apply-filters" class="oj-btn oj-btn-primary" type="button">
                <?php _e('Apply Filters', 'orders-jet'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=orders-reports'); ?>" class="oj-btn oj-btn-secondary">
                <?php _e('Reset', 'orders-jet'); ?>
            </a>
            <button id="oj-save-view" class="oj-btn oj-btn-outline" type="button">
                <?php _e('Save View', 'orders-jet'); ?>
            </button>
        </div>
    </div>
</div>
