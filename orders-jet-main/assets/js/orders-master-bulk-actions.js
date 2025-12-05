/**
 * Orders Master - Bulk Actions JavaScript (Step 2)
 * 
 * DEBUGGING: Extensive console logging for testing
 * NO BACKEND: Just selection logic and visual feedback
 * 
 * @package Orders_Jet
 * @version 2.0
 */

jQuery(document).ready(function($) {
    'use strict';
    let selectedOrders = [];
    
    /**
     * Initialize/reinitialize bulk actions (called on page load and after AJAX updates)
     */
    function init() {
        // Enable all checkboxes (they're disabled in HTML)
        $('.oj-bulk-checkbox, .oj-order-checkbox').prop('disabled', false);
    }
    
    /**
     * Setup delegated event listeners (only called once on page load)
     */
    function setupEventListeners() {
        // Select All checkbox - Delegated event works after AJAX updates
        $(document).on('change', '#oj-select-all-orders', handleSelectAll);
        
        // Individual checkbox - Delegated event works after AJAX updates
        $(document).on('change', '.oj-order-checkbox', handleIndividualCheckbox);
    }
    
    /**
     * Handle Select All checkbox
     */
    function handleSelectAll() {
        const isChecked = $(this).is(':checked');
        $('.oj-order-checkbox').prop('checked', isChecked).trigger('change');
    }
    
    /**
     * Handle individual checkbox change
     */
    function handleIndividualCheckbox() {
        const $checkbox = $(this);
        const orderId = $checkbox.data('order-id');
        const $orderCard = $checkbox.closest('.oj-order-card');
        if ($checkbox.is(':checked')) {
            // Add to selection
            if (!selectedOrders.includes(orderId)) {
                selectedOrders.push(orderId);
            }
            $orderCard.addClass('oj-selected');
        } else {
            // Remove from selection
            selectedOrders = selectedOrders.filter(id => id !== orderId);
            $orderCard.removeClass('oj-selected');
            $('#oj-select-all-orders').prop('checked', false);
        }
        
        updateBulkActionsUI();
    }
    
    /**
     * Update bulk actions UI based on selection
     */
    function updateBulkActionsUI() {
        const count = selectedOrders.length;
        
        if (count > 0) {
            // Show bulk actions bar
            $('.oj-bulk-actions-bar').slideDown(200);
            // Update count
            $('.oj-selected-count').show();
            $('.oj-selected-number').text(count);
            // Enable buttons
            $('.oj-bulk-btn').prop('disabled', false);
            // ===================================================================
            // STEP 1: DETECT SINGLE ORDER SELECTION
            // ===================================================================
            if (count === 1) {
                handleSingleOrderSelection();
            } else {
                handleMultipleOrderSelection();
            }
            
        } else {
            // Hide bulk actions bar
            $('.oj-bulk-actions-bar').slideUp(200);
            // Hide count
            $('.oj-selected-count').hide();
            
            // Disable buttons
            $('.oj-bulk-btn').prop('disabled', true);
            // Hide single order actions
            $('.oj-single-order-actions').slideUp(200);
        }
        
        // Update Select All checkbox state
        const totalCheckboxes = $('.oj-order-checkbox').length;
        const checkedCheckboxes = $('.oj-order-checkbox:checked').length;
        const allChecked = totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes;
        $('#oj-select-all-orders').prop('checked', allChecked);
    }
    
    // ========================================================================
    // SINGLE ORDER SELECTION LOGIC - PHASE 1, STEP 1
    // ========================================================================
    
    /**
     * Handle single order selection (exactly 1 order selected)
     * This is where we'll show the gear button and order editor actions
     */
    function handleSingleOrderSelection() {
        const orderId = selectedOrders[0];
        const $orderCard = $(`.oj-order-checkbox[data-order-id="${orderId}"]`).closest('.oj-order-card');
        
        // Get order data from card attributes
        const orderData = {
            id: orderId,
            number: $orderCard.find('.oj-order-number').text(),
            status: $orderCard.data('status'),
            method: $orderCard.data('method'),
            parentId: $orderCard.data('parent-id') || 0,
            tableNumber: $orderCard.data('table-number') || '',
            total: $orderCard.find('.oj-order-total').text(),
            isTableChild: parseInt($orderCard.data('parent-id') || 0) > 0
        };
        
        // Trigger custom event with order data
        $(document).trigger('oj-single-order-selected', [orderData]);
    }
    
    /**
     * Handle multiple orders selection (2+ orders selected)
     * Hide single order actions, show only bulk actions
     */
    function handleMultipleOrderSelection() {
        // Hide single order actions if visible
        $('.oj-single-order-actions').slideUp(200);
        $('.oj-table-warning').slideUp(200);
        
        // Ensure bulk actions are visible (already handled above)
    }
    
    // ========================================================================
    // SINGLE ORDER ACTIONS UI - PHASE 1, STEP 2
    // ========================================================================
    
    /**
     * Listen for single order selection event and show actions bar
     */
    $(document).on('oj-single-order-selected', function(event, orderData) {
        // Populate order indicator
        $('.oj-order-num').text(orderData.number);
        $('.oj-order-status-text').text('Status: ' + capitalizeFirst(orderData.status));
        
        // Build badges
        let badgesHtml = '';
        
        // Add table child badge if applicable
        if (orderData.isTableChild) {
            badgesHtml += '<span class="oj-badge table-child">🍽️ Table Order</span>';
        }
        
        // Add method badge (dine-in, takeaway, delivery)
        const methodIcons = {
            'dinein': '🍽️',
            'takeaway': '📦',
            'delivery': '🚚'
        };
        const methodIcon = methodIcons[orderData.method] || '📋';
        badgesHtml += `<span class="oj-badge">${methodIcon} ${capitalizeFirst(orderData.method)}</span>`;
        
        $('.oj-order-badge-container').html(badgesHtml);
        
        // Show/hide table warning for table child orders
        if (orderData.isTableChild) {
            $('.oj-table-warning').slideDown(200);
            // Disable refund button for table child orders
            $('[data-action="refund"]').prop('disabled', true).css('opacity', '0.5').attr('title', 'Table orders cannot be refunded individually');
        } else {
            $('.oj-table-warning').slideUp(200);
            $('[data-action="refund"]').prop('disabled', false).css('opacity', '1').attr('title', '');
        }
        
        // Show single order actions bar
        $('.oj-single-order-actions').slideDown(200);
        // Store current order data for later use
        $('.oj-single-order-actions').data('current-order', orderData);
    });
    
    /**
     * Handle gear dropdown toggle
     */
    $(document).on('click', '.oj-gear-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $dropdown = $(this).closest('.oj-gear-dropdown');
        
        // Toggle dropdown
        $dropdown.toggleClass('open');
        
    });
    
    /**
     * Close dropdown when clicking outside
     */
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.oj-gear-dropdown').length) {
            $('.oj-gear-dropdown').removeClass('open');
        }
    });
    
    /**
     * NOTE: Action handlers have been moved to order-editor.js
     * This file only manages checkbox selection and bulk operations
     * The order-editor.js listens for specific .oj-single-action-btn clicks
     */
    
    /**
     * Capitalize first letter helper
     */
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    /**
     * STEP 2: Simple grid refresh function
     * Triggers the grid refresh event that orders-master.js listens for
     */
    function refreshOrdersGrid() {
        
        // Trigger the grid refresh event
        $(document).trigger('oj-refresh-grid');
    }
    
    // Debug: Create global test function for STEP 2
    window.testBulkRefresh = function() {
        refreshOrdersGrid();
    };
    
    /**
     * Button click handlers - Using delegated events to work after AJAX updates
     */
    $(document).on('click', '#oj-bulk-mark-ready', function(e) {
        e.preventDefault(); // Prevent any default behavior
        
        if (selectedOrders.length === 0) {
            alert('Please select at least one order');
            return;
        }
        
        // Confirm action
        const confirmMsg = 'Mark ' + selectedOrders.length + ' order(s) as ready?';
        if (!confirm(confirmMsg)) {
            return;
        }
        // Disable button and show loading
        const $btn = $(this);
        $btn.prop('disabled', true).text('Processing...');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_bulk_action',
                nonce: oj_ajax_data.nonce,
                bulk_action: 'mark_ready',
                order_ids: selectedOrders
            },
            success: function(response) {
                if (response.success) {
                    alert('Success!\n' + response.data.message);
                    
                    // Reload page to show updated orders (respects current filter in URL)
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text('✅ Mark Ready');
                }
            },
            error: function(xhr, status, error) {
                alert('Connection error. Please try again.');
                $btn.prop('disabled', false).text('✅ Mark Ready');
            }
        });
    });
    
    // Helper function for bulk actions (DRY principle)
    function performBulkAction(action, actionLabel) {
        
        if (selectedOrders.length === 0) {
            alert('Please select at least one order');
            return;
        }
        
        // Build confirmation message
        let confirmMsg = actionLabel + ' ' + selectedOrders.length + ' order(s)?';
        
        // For Close Table action, get the actual table number
        if (action === 'close_table') {
            const firstOrderCard = $('.oj-order-checkbox:checked').first().closest('.oj-order-card');
            const tableNumber = firstOrderCard.data('table-number');
            if (tableNumber) {
                confirmMsg = 'Close Table ' + tableNumber + ' (' + selectedOrders.length + ' order(s))?';
            }
        }
        
        if (!confirm(confirmMsg)) {
            return;
        }
        // Disable all buttons and show loading
        const $btn = $('#oj-bulk-' + action.replace('_', '-'));
        $('.oj-bulk-btn').prop('disabled', true);
        $btn.text('Processing...');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oj_bulk_action',
                nonce: oj_ajax_data.nonce,
                bulk_action: action,
                order_ids: selectedOrders
            },
            success: function(response) {
                if (response.success) {
                    alert('Success!\n' + response.data.message);
                    
                    // Reload page to show updated orders (respects current filter in URL)
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Error: ' + response.data.message);
                    $('.oj-bulk-btn').prop('disabled', false);
                    $btn.text(actionLabel);
                }
            },
            error: function(xhr, status, error) {
                alert('Connection error. Please try again.');
                $('.oj-bulk-btn').prop('disabled', false);
                $btn.text(actionLabel);
            }
        });
    }
    
    // Complete Orders button - Using delegated event
    $(document).on('click', '#oj-bulk-complete', function() {
        performBulkAction('complete', 'Complete');
    });
    
    // Cancel Orders button - Using delegated event
    $(document).on('click', '#oj-bulk-cancel', function() {
        performBulkAction('cancel', 'Cancel');
    });
    
    // Close Table button - Using delegated event
    $(document).on('click', '#oj-bulk-close-table', function() {
        performBulkAction('close_table', 'Close Table');
    });
    
    // Setup delegated event listeners (only once on page load)
    setupEventListeners();
    
    // Initialize on document ready
    init();
    
    // Reinitialize after AJAX filter updates (CRITICAL for filters to work!)
    $(document).on('oj-orders-grid-updated', function() {
        // Clear selected orders
        selectedOrders = [];
        
        // Re-enable checkboxes (they come disabled from backend)
        $('.oj-bulk-checkbox, .oj-order-checkbox').prop('disabled', false);
        
        // Hide bulk actions bar
        $('.oj-bulk-actions-bar').hide();
        
        // Reset select all checkbox
        $('#oj-select-all-orders').prop('checked', false);
        
        // Update UI
        updateBulkActionsUI();
    });
});

