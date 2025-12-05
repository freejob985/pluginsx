<?php
declare(strict_types=1);
/**
 * Table Assignment Dashboard - Clean Implementation
 * Uses proper handler and meta keys constants
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// Enqueue existing styles - NO NEW STYLES
wp_enqueue_style('oj-manager-orders-cards', ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css', array(), ORDERS_JET_VERSION);
wp_enqueue_style('oj-dashboard-express', ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css', array('oj-manager-orders-cards'), ORDERS_JET_VERSION);

// Get handler instance (proper dependency injection)
$handler_factory = new Orders_Jet_Handler_Factory(
    new Orders_Jet_Tax_Service(),
    new Orders_Jet_Kitchen_Service(),
    new Orders_Jet_Notification_Service()
);
$assignment_handler = $handler_factory->get_table_assignment_handler();

// Get data using handler
$tables = $assignment_handler->get_tables_with_assignments();
$staff = $assignment_handler->get_assignable_staff();
$assignments = $assignment_handler->get_all_assignments();

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-grid-view" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
        <?php _e('🏷️ Assign Tables', 'orders-jet'); ?>
    </h1>
    <hr class="wp-header-end">
    
    <!-- Page Description -->
    <div class="oj-page-header">
        <p class="description">
            <?php _e('Assign tables to waiters for order management. Tables can be assigned statically or claimed dynamically by waiters.', 'orders-jet'); ?>
        </p>
    </div>
    
    <!-- Filter Tabs (reuse existing design) -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="all">
            <?php _e('All Tables', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo count($tables); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="assigned">
            <?php _e('Assigned', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo count($assignments); ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="available">
            <?php _e('Available', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo count($tables) - count($assignments); ?></span>
        </button>
    </div>

    <!-- Search Box -->
    <div style="margin-bottom: 20px;">
        <input type="text" id="oj-table-search" placeholder="<?php _e('Search tables...', 'orders-jet'); ?>" style="width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>

    <?php if (empty($tables)): ?>
        <!-- No Tables Found -->
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('No tables found!', 'orders-jet'); ?></strong>
                <?php _e('You need to create tables first before you can assign them.', 'orders-jet'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=oj_table'); ?>" class="button button-primary">
                    <?php _e('Manage Tables', 'orders-jet'); ?>
                </a>
            </p>
        </div>
    <?php else: ?>
        <!-- Tables Grid (reuse existing card layout) -->
        <div class="oj-orders-grid" id="oj-tables-grid">
            <?php foreach ($tables as $table): ?>
                <?php 
                $is_assigned = !empty($table['assigned_waiter']);
                
                // Guest Status Badge (Primary - Physical table state)
                $guest_status_colors = array(
                    'available' => array('color' => '#4caf50', 'bg' => '#e8f5e9', 'icon' => '⚪', 'label' => 'Available'),
                    'occupied' => array('color' => '#ff9800', 'bg' => '#fff3e0', 'icon' => '🟡', 'label' => 'Occupied'),
                    'reserved' => array('color' => '#f44336', 'bg' => '#ffebee', 'icon' => '🔴', 'label' => 'Reserved'),
                    'maintenance' => array('color' => '#9e9e9e', 'bg' => '#f5f5f5', 'icon' => '🔧', 'label' => 'Maintenance')
                );
                $guest_badge = $guest_status_colors[$table['status']] ?? $guest_status_colors['available'];
                
    // Assignment Status Badge (Secondary - Waiter assignment)
    if ($is_assigned) {
        $assignment_badge = array('color' => '#2196f3', 'bg' => '#e3f2fd', 'icon' => '👤', 'label' => 'Assigned');
        $display_status = 'assigned';
    } else {
        $assignment_badge = array('color' => '#ff5722', 'bg' => '#fbe9e7', 'icon' => '❗', 'label' => 'Unassigned');
        $display_status = 'unassigned';
    }
                ?>
                
                <div class="oj-order-card oj-table-card" 
                     data-table-number="<?php echo esc_attr($table['number']); ?>"
                     data-assigned="<?php echo $is_assigned ? 'yes' : 'no'; ?>"
                     data-status="<?php echo esc_attr($display_status); ?>">
                    
                    <!-- Table Header -->
                    <div class="oj-card-header">
                        <div class="oj-order-number">
                            🍽️ <?php echo esc_html($table['title']); ?>
                        </div>
                        <div class="oj-order-badges">
                            <!-- Guest Status Badge (Primary) -->
                            <span class="oj-status-badge" style="background: <?php echo $guest_badge['bg']; ?>; color: <?php echo $guest_badge['color']; ?>; margin-right: 5px;">
                                <?php echo $guest_badge['icon']; ?> <?php echo esc_html($guest_badge['label']); ?>
                            </span>
                            <!-- Assignment Status Badge (Secondary) -->
                            <span class="oj-status-badge" style="background: <?php echo $assignment_badge['bg']; ?>; color: <?php echo $assignment_badge['color']; ?>;">
                                <?php echo $assignment_badge['icon']; ?> <?php echo esc_html($assignment_badge['label']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Row 2: Table Info -->
                    <div class="oj-card-content">
                        <div class="oj-table-info-row">
                            <!-- Left side: Table number and seats -->
                            <div class="oj-table-left">
                                <span class="oj-table-number"><?php echo esc_html($table['number']); ?></span>
                                <?php if ($table['capacity']): ?>
                                    <span class="oj-table-capacity">| <?php echo esc_html($table['capacity']); ?> Seats</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Right side: Location and waiter -->
                            <div class="oj-table-right">
                                <?php if ($table['location']): ?>
                                    <span class="oj-table-location">📍 <?php echo esc_html($table['location']); ?></span>
                                <?php endif; ?>
                                <?php if ($is_assigned): ?>
                                    <span class="oj-assigned-waiter">👤 <?php echo esc_html($table['assigned_waiter']->display_name); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Row 3: Assignment Controls -->
                    <div class="oj-card-actions">
                        <?php if ($is_assigned): ?>
                            <!-- Assigned: Show waiter name and unassign button -->
                            <div class="oj-assignment-controls assigned">
                                <select class="oj-waiter-assignment" data-table-number="<?php echo esc_attr($table['number']); ?>">
                                    <option value=""><?php _e('-- Select Waiter --', 'orders-jet'); ?></option>
                                    <?php foreach ($staff as $staff_member): ?>
                                        <option value="<?php echo esc_attr($staff_member->ID); ?>" 
                                                <?php selected($table['assigned_waiter']->ID == $staff_member->ID); ?>>
                                            <?php echo esc_html($staff_member->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="oj-unassign-btn" data-table-number="<?php echo esc_attr($table['number']); ?>">
                                    <?php _e('Unassign', 'orders-jet'); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Unassigned: Show dropdown only -->
                            <div class="oj-assignment-controls unassigned">
                                <select class="oj-waiter-assignment" data-table-number="<?php echo esc_attr($table['number']); ?>">
                                    <option value=""><?php _e('-- Select Waiter --', 'orders-jet'); ?></option>
                                    <?php foreach ($staff as $staff_member): ?>
                                        <option value="<?php echo esc_attr($staff_member->ID); ?>">
                                            <?php echo esc_html($staff_member->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Bulk Actions -->
        <div style="margin-top: 30px; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 8px;">
            <h3><?php _e('Bulk Actions', 'orders-jet'); ?></h3>
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <label>
                    <input type="checkbox" id="oj-select-all-tables"> 
                    <?php _e('Select All Visible', 'orders-jet'); ?>
                </label>
                
                <select id="oj-bulk-waiter-select" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value=""><?php _e('-- Select Waiter --', 'orders-jet'); ?></option>
                    <?php foreach ($staff as $staff_member): ?>
                        <option value="<?php echo esc_attr($staff_member->ID); ?>">
                            <?php echo esc_html($staff_member->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button id="oj-bulk-assign-btn" class="button button-primary" disabled>
                    <?php _e('Assign Selected', 'orders-jet'); ?>
                </button>
                
                <button id="oj-bulk-unassign-btn" class="button" disabled>
                    <?php _e('Unassign Selected', 'orders-jet'); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 10px;">
                <?php _e('Select tables using checkboxes, then choose a waiter to assign them to, or unassign selected tables.', 'orders-jet'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Success/Error Messages -->
<div id="oj-assignment-messages"></div>

<style>
/* Add checkboxes to cards for bulk selection */
.oj-table-card {
    position: relative;
}

.oj-table-card::before {
    content: '';
    position: absolute;
    top: -10px;
    left: 10px;
    width: 20px;
    height: 20px;
    background: white;
    border: 2px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    z-index: 10;
}

.oj-table-card.selected::before {
    background: #0073aa;
    border-color: #0073aa;
}

.oj-table-card.selected::after {
    content: '✓';
    position: absolute;
    top: -8px;
    left: 13px;
    color: white;
    font-size: 12px;
    font-weight: bold;
    z-index: 11;
}

/* Filter states */
.oj-table-card[data-filter-hidden="true"] {
    display: none;
}

/* Assignment status indicators */
.oj-table-card[data-assigned="yes"] {
    border-left: 4px solid #4caf50;
}

.oj-table-card[data-assigned="no"] {
    border-left: 4px solid #ddd;
}

/* Hover effects for assignment dropdowns */
.oj-waiter-assignment:hover {
    border-color: #0073aa;
}

.oj-unassign-btn:hover {
    background: #c82333 !important;
}

/* Dual badge layout */
.oj-order-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: center;
}

.oj-status-badge {
    font-size: 11px;
    white-space: nowrap;
}

/* Loading state */
.oj-table-card.oj-loading {
    opacity: 0.6;
    pointer-events: none;
}

.oj-table-card.oj-loading::after {
    content: '⏳';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    z-index: 100;
}

/* Row 2: Table Info Layout */
.oj-table-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
}

.oj-table-left {
    display: flex;
    align-items: center;
    gap: 6px;
}

.oj-table-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.oj-table-number {
    font-weight: 600;
    color: #333;
    font-size: 15px;
}

.oj-table-capacity {
    font-size: 13px;
    color: #666;
}

.oj-table-location,
.oj-assigned-waiter {
    font-size: 13px;
    color: #666;
}

.oj-assigned-waiter {
    color: #4caf50;
    font-weight: 500;
}

/* Row 3: Assignment Controls */
.oj-assignment-controls {
    display: flex;
    gap: 8px;
    align-items: stretch;
    width: 100%;
    margin: 0;
    padding: 0;
}

.oj-assignment-controls select {
    flex: 1;
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    min-height: 32px;
    box-sizing: border-box;
    width: 100%;
}

.oj-assignment-controls .oj-unassign-btn {
    padding: 6px 18px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    min-width: 85px;
    min-height: 32px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.oj-assignment-controls .oj-unassign-btn:hover {
    background: #c82333;
}

/* Full width for unassigned tables */
.oj-assignment-controls.unassigned select {
    width: 100%;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Filter functionality
    $('.oj-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        
        // Update active state
        $('.oj-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Filter cards
        $('.oj-table-card').each(function() {
            const $card = $(this);
            const isAssigned = $card.data('assigned') === 'yes';
            
            let show = false;
            if (filter === 'all') {
                show = true;
            } else if (filter === 'assigned' && isAssigned) {
                show = true;
            } else if (filter === 'available' && !isAssigned) {
                show = true;
            }
            
            $card.attr('data-filter-hidden', !show);
        });
    });
    
    // Search functionality
    $('#oj-table-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        $('.oj-table-card').each(function() {
            const $card = $(this);
            const tableNumber = $card.data('table-number').toString().toLowerCase();
            const tableTitle = $card.find('.oj-order-number').text().toLowerCase();
            
            const matches = tableNumber.includes(searchTerm) || tableTitle.includes(searchTerm);
            $card.toggle(matches);
        });
    });
    
    // Card selection for bulk actions
    $('.oj-table-card').on('click', function(e) {
        if (e.target.tagName === 'SELECT' || e.target.tagName === 'BUTTON') {
            return; // Don't select when clicking on controls
        }
        
        $(this).toggleClass('selected');
        updateBulkActionButtons();
    });
    
    // Select all functionality
    $('#oj-select-all-tables').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.oj-table-card:visible').toggleClass('selected', isChecked);
        updateBulkActionButtons();
    });
    
    // Update bulk action button states
    function updateBulkActionButtons() {
        const selectedCount = $('.oj-table-card.selected').length;
        const hasWaiterSelected = $('#oj-bulk-waiter-select').val() !== '';
        
        $('#oj-bulk-assign-btn').prop('disabled', selectedCount === 0 || !hasWaiterSelected);
        $('#oj-bulk-unassign-btn').prop('disabled', selectedCount === 0);
    }
    
    // Waiter selection change
    $('#oj-bulk-waiter-select').on('change', updateBulkActionButtons);
    
    // Individual assignment dropdown change
    $('.oj-waiter-assignment').on('change', function() {
        const $dropdown = $(this);
        const tableNumber = $dropdown.data('table-number');
        const waiterId = $dropdown.val();
        const $card = $dropdown.closest('.oj-table-card');
        
        if (waiterId === '') {
            // Unassign table
            unassignTable(tableNumber, $card);
        } else {
            // Assign table
            assignTable(tableNumber, waiterId, $card);
        }
    });
    
    // Individual unassign button click (using event delegation for dynamic buttons)
    $(document).on('click', '.oj-unassign-btn', function() {
        const $btn = $(this);
        const tableNumber = $btn.data('table-number');
        const $card = $btn.closest('.oj-table-card');
        
        unassignTable(tableNumber, $card);
    });
    
    // Bulk assign button click
    $('#oj-bulk-assign-btn').on('click', function() {
        const waiterId = $('#oj-bulk-waiter-select').val();
        const selectedTables = getSelectedTableNumbers();
        
        if (selectedTables.length > 0 && waiterId) {
            bulkAssignTables(selectedTables, waiterId);
        }
    });
    
    // Bulk unassign button click
    $('#oj-bulk-unassign-btn').on('click', function() {
        const selectedTables = getSelectedTableNumbers();
        
        if (selectedTables.length > 0) {
            bulkUnassignTables(selectedTables);
        }
    });
    
    // Helper function to get selected table numbers
    function getSelectedTableNumbers() {
        const tableNumbers = [];
        $('.oj-table-card.selected').each(function() {
            tableNumbers.push($(this).data('table-number'));
        });
        return tableNumbers;
    }
    
    // AJAX function: Assign table to waiter
    function assignTable(tableNumber, waiterId, $card) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'oj_assign_table',
                table_number: tableNumber,
                waiter_id: waiterId,
                nonce: '<?php echo wp_create_nonce('oj_table_assignment'); ?>'
            },
            beforeSend: function() {
                $card.addClass('oj-loading');
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    updateCardAssignment($card, waiterId);
                } else {
                    showMessage(response.data.message, 'error');
                    // Reset dropdown to previous value
                    $card.find('.oj-waiter-assignment').val('');
                }
            },
            error: function() {
                showMessage('Connection error. Please try again.', 'error');
                $card.find('.oj-waiter-assignment').val('');
            },
            complete: function() {
                $card.removeClass('oj-loading');
            }
        });
    }
    
    // AJAX function: Unassign table
    function unassignTable(tableNumber, $card) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'oj_unassign_table',
                table_number: tableNumber,
                nonce: '<?php echo wp_create_nonce('oj_table_assignment'); ?>'
            },
            beforeSend: function() {
                $card.addClass('oj-loading');
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    updateCardAssignment($card, '');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Connection error. Please try again.', 'error');
            },
            complete: function() {
                $card.removeClass('oj-loading');
            }
        });
    }
    
    // AJAX function: Bulk assign tables
    function bulkAssignTables(tableNumbers, waiterId) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'oj_bulk_assign_tables',
                table_numbers: tableNumbers,
                waiter_id: waiterId,
                nonce: '<?php echo wp_create_nonce('oj_table_assignment'); ?>'
            },
            beforeSend: function() {
                $('#oj-bulk-assign-btn').prop('disabled', true).text('Assigning...');
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Update all selected cards
                    $('.oj-table-card.selected').each(function() {
                        updateCardAssignment($(this), waiterId);
                        $(this).removeClass('selected');
                    });
                    // Reset bulk controls
                    $('#oj-select-all-tables').prop('checked', false);
                    $('#oj-bulk-waiter-select').val('');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Connection error. Please try again.', 'error');
            },
            complete: function() {
                $('#oj-bulk-assign-btn').prop('disabled', false).text('<?php _e('Assign Selected', 'orders-jet'); ?>');
                updateBulkActionButtons();
            }
        });
    }
    
    // AJAX function: Bulk unassign tables
    function bulkUnassignTables(tableNumbers) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'oj_bulk_unassign_tables',
                table_numbers: tableNumbers,
                nonce: '<?php echo wp_create_nonce('oj_table_assignment'); ?>'
            },
            beforeSend: function() {
                $('#oj-bulk-unassign-btn').prop('disabled', true).text('Unassigning...');
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Update all selected cards
                    $('.oj-table-card.selected').each(function() {
                        updateCardAssignment($(this), '');
                        $(this).removeClass('selected');
                    });
                    // Reset bulk controls
                    $('#oj-select-all-tables').prop('checked', false);
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Connection error. Please try again.', 'error');
            },
            complete: function() {
                $('#oj-bulk-unassign-btn').prop('disabled', false).text('<?php _e('Unassign Selected', 'orders-jet'); ?>');
                updateBulkActionButtons();
            }
        });
    }
    
    // Helper function: Update card assignment display
    function updateCardAssignment($card, waiterId) {
        const $dropdown = $card.find('.oj-waiter-assignment');
        const $assignmentBadge = $card.find('.oj-order-badges .oj-status-badge:last-child');
        const $tableRight = $card.find('.oj-table-right');
        const $assignmentControls = $card.find('.oj-assignment-controls');
        const $existingWaiter = $tableRight.find('.oj-assigned-waiter');
        
        if (waiterId && waiterId !== '') {
            // Table is now assigned
            $card.attr('data-assigned', 'yes');
            $card.attr('data-status', 'assigned');
            $dropdown.val(waiterId);
            
            // Update assignment badge
            $assignmentBadge.css({
                'background': '#e3f2fd',
                'color': '#2196f3'
            }).html('👤 Assigned');
            
            // Get waiter name from dropdown
            const waiterName = $dropdown.find('option:selected').text();
            
            // Update or add waiter info in Row 2 (right side)
            if ($existingWaiter.length > 0) {
                // Update existing waiter
                $existingWaiter.text('👤 ' + waiterName);
            } else {
                // Add new waiter info to the right side
                $tableRight.append('<span class="oj-assigned-waiter">👤 ' + waiterName + '</span>');
            }
            
            // Update Row 3 controls to "assigned" state
            $assignmentControls.removeClass('unassigned').addClass('assigned');
            
            // Add unassign button if it doesn't exist
            if ($assignmentControls.find('.oj-unassign-btn').length === 0) {
                $dropdown.after('<button class="oj-unassign-btn" data-table-number="' + $card.data('table-number') + '">Unassign</button>');
            }
            
        } else {
            // Table is now unassigned
            $card.attr('data-assigned', 'no');
            $card.attr('data-status', 'unassigned');
            $dropdown.val('');
            
            // Update assignment badge
            $assignmentBadge.css({
                'background': '#fbe9e7',
                'color': '#ff5722'
            }).html('❗ Unassigned');
            
            // Remove waiter info from Row 2
            $existingWaiter.remove();
            
            // Update Row 3 controls to "unassigned" state
            $assignmentControls.removeClass('assigned').addClass('unassigned');
            
            // Remove unassign button
            $assignmentControls.find('.oj-unassign-btn').remove();
        }
        
        // Update filter counts
        updateFilterCounts();
    }
    
    // Helper function: Update filter counts
    function updateFilterCounts() {
        const totalTables = $('.oj-table-card').length;
        const assignedTables = $('.oj-table-card[data-assigned="yes"]').length;
        const availableTables = totalTables - assignedTables;
        
        $('.oj-filter-btn[data-filter="all"] .oj-filter-count').text(totalTables);
        $('.oj-filter-btn[data-filter="assigned"] .oj-filter-count').text(assignedTables);
        $('.oj-filter-btn[data-filter="available"] .oj-filter-count').text(availableTables);
    }
    
    // Helper function: Show success/error messages
    function showMessage(message, type) {
        const $messagesContainer = $('#oj-assignment-messages');
        const alertClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        const $message = $('<div class="notice ' + alertClass + ' is-dismissible" style="margin: 15px 0;"><p>' + message + '</p></div>');
        
        $messagesContainer.html($message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut();
        }, 5000);
    }
});
</script>
