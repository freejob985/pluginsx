/**
 * Orders Master JavaScript
 * Task 1.2.3.4 - AJAX Filters Preparation
 * 
 * @package Orders_Jet
 * @version 1.2.3.4
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // ========================================================================
    // GLOBAL VARIABLES
    // ========================================================================
    
    let currentFilter = 'all';
    let currentPage = 1;
    let currentSearch = '';
    let isLoading = false;
    let searchTimeout = null;
    
    // ========================================================================
    // AJAX FILTERING - Task 1.2.3.4
    // ========================================================================
    
    /**
     * Handle filter button clicks
     */
    $(document).on('click', '.oj-filter-btn', function(e) {
        e.preventDefault();
        
        if (isLoading) return;
        
        const filter = $(this).data('filter');
        
        // Don't reload if same filter
        if (filter === currentFilter) return;
        
        currentFilter = filter;
        currentPage = 1; // Reset to first page when changing filter
        
        loadOrdersWithFilter(filter, currentPage, currentSearch);
    });
    
    /**
     * STEP 1: Handle grid refresh from bulk actions
     * Triggered after bulk operations (mark ready, complete, cancel, close table)
     */
    $(document).on('oj-refresh-grid', function(e) {
        console.log('🔄 STEP 1: Grid refresh event received');
        console.log('📊 Event object:', e);
        console.log('📊 Current state:', {
            filter: currentFilter, 
            page: currentPage, 
            search: currentSearch,
            isLoading: isLoading
        });
        
        // Reload with current state (preserves filters and search)
        loadOrdersWithFilter(currentFilter, currentPage, currentSearch);
        
        console.log('✅ STEP 1: Grid refresh triggered');
    });
    
    // Debug: Log that listener is registered
    console.log('✅ Grid refresh listener registered');
    
    // Debug: Create global test function
    window.testGridRefresh = function() {
        console.log('🧪 Test function called');
        console.log('🧪 Current state:', {filter: currentFilter, page: currentPage, search: currentSearch});
        console.log('🧪 Triggering event...');
        $(document).trigger('oj-refresh-grid');
        console.log('🧪 Event triggered');
    };
    
    /**
     * Load orders with specific filter and search via AJAX
     */
    function loadOrdersWithFilter(filter, page, search = '') {
        if (isLoading) return;
        
        isLoading = true;
        
        // Show loading state
        $('.oj-orders-grid').addClass('oj-loading');
        $('.oj-filter-btn').prop('disabled', true);
        
        // Get current URL parameters for waiter assignment filters
        const urlParams = new URLSearchParams(window.location.search);
        
        // Prepare AJAX data
        const ajaxData = {
            action: 'oj_get_orders_master_filtered',
            nonce: OrdersJetMaster.nonce,
            filter: filter,
            page: page,
            search: search || currentSearch,
            // Include waiter assignment parameters from URL
            assigned_waiter: urlParams.get('assigned_waiter') || '',
            unassigned_only: urlParams.get('unassigned_only') || '',
            assigned_only: urlParams.get('assigned_only') || ''
        };
        
        // Check if OrdersJetMaster exists
        if (typeof OrdersJetMaster === 'undefined') {
            console.error('OrdersJetMaster is undefined!');
            showNotification('Configuration error. Please refresh the page.', 'error');
            return;
        }
        
        // Make AJAX request
        $.ajax({
            url: OrdersJetMaster.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            timeout: 30000, // 30 second timeout for local testing
            success: function(response) {
                if (response.success && response.data) {
                    
                    // Update filter buttons
                    updateFilterButtons(filter);
                    
                    // Update filter counts
                    updateFilterCounts(response.data.counts);
                    
                    // Update orders grid
                    updateOrdersGrid(response.data.orders);
                    
                    // Update pagination
                    updatePagination(response.data.pagination, filter);
                    
                    // Update current state
                    currentFilter = filter;
                    currentPage = page;
                    currentSearch = search || currentSearch;
                    
                } else {
                    console.error('AJAX Error:', response);
                    showNotification('Failed to load orders. Please try again.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Request Failed:', status, error);
                console.error('XHR Status:', xhr.status);
                console.error('XHR Response:', xhr.responseText);
                console.error('AJAX URL:', OrdersJetMaster.ajaxUrl);
                console.error('AJAX Data:', ajaxData);
                
                let errorMessage = 'Connection error. ';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. The server is taking too long to respond. ';
                } else if (status === 'error') {
                    errorMessage = 'Server error. ';
                } else if (status === 'parsererror') {
                    errorMessage = 'Invalid server response. ';
                }
                
                showNotification(errorMessage + 'Please try again.', 'error');
            },
            complete: function() {
                // Hide loading state
                $('.oj-orders-grid').removeClass('oj-loading');
                $('.oj-filter-btn').prop('disabled', false);
                isLoading = false;
            }
        });
    }
    
    /**
     * Update filter button states
     */
    function updateFilterButtons(activeFilter) {
        $('.oj-filter-btn').removeClass('active');
        $(`.oj-filter-btn[data-filter="${activeFilter}"]`).addClass('active');
    }
    
    /**
     * Update filter counts in tabs
     */
    function updateFilterCounts(counts) {
        if (!counts) return;
        
        // Update each filter count
        Object.keys(counts).forEach(function(filter) {
            const $filterBtn = $(`.oj-filter-btn[data-filter="${filter}"]`);
            const $countSpan = $filterBtn.find('.oj-filter-count');
            
            if ($countSpan.length) {
                $countSpan.text(counts[filter] || 0);
            }
        });
    }
    
    /**
     * Update orders grid with new data
     */
    function updateOrdersGrid(orders) {
        const $grid = $('.oj-orders-grid');
        
        if (!orders || orders.length === 0) {
            // Show empty state
            $grid.html(`
                <div class="oj-empty-state">
                    <div class="oj-empty-icon">📋</div>
                    <h3 class="oj-empty-title">No Orders Found</h3>
                    <p class="oj-empty-message">
                        There are currently no orders matching the selected filter.
                    </p>
                </div>
            `);
            return;
        }
        
        // Build cards HTML
        let cardsHtml = '';
        orders.forEach(function(order) {
            cardsHtml += buildOrderCardHtml(order);
        });
        
        // Update grid with fade effect
        $grid.fadeOut(200, function() {
            $grid.html(cardsHtml).fadeIn(200, function() {
                // Trigger custom event for bulk actions to reinitialize
                $(document).trigger('oj-orders-grid-updated');
            });
        });
    }
    
    /**
     * Build HTML for a single order card
     */
    function buildOrderCardHtml(order) {
        // Icon mappings
        const typeIcons = {
            'dinein': '🍽️',
            'takeaway': '📦', 
            'delivery': '🚚'
        };
        
        const kitchenIcons = {
            'food': '🍕',
            'beverages': '🥤',
            'mixed': '🍽️'
        };
        
        const statusIcons = {
            'active': '👨‍🍳',
            'ready': '✅',
            'completed': '🎯'
        };
        
        // Build table reference
        const tableRef = order.table_number ? 
            `<span class="oj-table-ref">${escapeHtml(order.table_number)}</span>` : '';
        
        return `
            <div class="oj-order-card" 
                 data-order-id="${order.id}" 
                 data-status="${order.master_status}" 
                 data-method="${order.order_type}" 
                 data-kitchen-type="${order.kitchen_type}">
                 
                <!-- Row 1: Order number + Type badges -->
                <div class="oj-card-row-1">
                    <div class="oj-order-header">
                        <span class="oj-view-icon oj-view-order" data-order-id="${order.id}" title="View Order Details">👁️</span>
                        ${tableRef}
                        <span class="oj-order-number">#${escapeHtml(order.number)}</span>
                    </div>
                    <div class="oj-type-badges">
                        <span class="oj-type-badge ${order.order_type}">
                            ${typeIcons[order.order_type] || '📋'} 
                            ${escapeHtml(capitalizeFirst(order.order_type))}
                        </span>
                        <span class="oj-kitchen-badge ${order.kitchen_type}">
                            ${kitchenIcons[order.kitchen_type] || '🔥'} 
                            ${escapeHtml(capitalizeFirst(order.kitchen_type))}
                        </span>
                    </div>
                </div>

                <!-- Row 2: Time + Status -->
                <div class="oj-card-row-2">
                    <span class="oj-order-time">${escapeHtml(order.time_ago)} ago</span>
                    <span class="oj-status-badge ${order.master_status}">
                        ${statusIcons[order.master_status] || '📋'} 
                        ${escapeHtml(capitalizeFirst(order.master_status))}
                    </span>
                </div>

                <!-- Row 3: Customer + Price -->
                <div class="oj-card-row-3">
                    <span class="oj-customer-name">${escapeHtml(order.customer_name)}</span>
                    <span class="oj-order-total">${order.total_formatted}</span>
                </div>

                <!-- Row 4: Item count -->
                <div class="oj-card-row-4">
                    <span class="oj-item-count">
                        ${order.item_count} ${order.item_count === 1 ? 'item' : 'items'}
                    </span>
                </div>

                <!-- Row 5: Item details -->
                <div class="oj-card-row-5">
                    <div class="oj-items-list">${escapeHtml(order.items_display)}</div>
                </div>
            </div>
        `;
    }
    
    /**
     * Update pagination controls
     */
    function updatePagination(pagination, filter) {
        const $container = $('.oj-pagination-container');
        
        if (!pagination || pagination.total_pages <= 1) {
            $container.hide();
            return;
        }

        // Update pagination info
        const infoText = `Showing page ${pagination.current_page} of ${pagination.total_pages} (${pagination.total_orders} total orders)`;
        $('.oj-pagination-info').html(infoText);

        // Build pagination controls
        let controlsHtml = '';
        
        if (pagination.has_prev) {
            controlsHtml += `<a href="#" class="oj-pagination-btn" data-page="${pagination.current_page - 1}">← Previous</a>`;
        }
        
        controlsHtml += `<span class="oj-pagination-current">Page ${pagination.current_page} of ${pagination.total_pages}</span>`;
        
        if (pagination.has_next) {
            controlsHtml += `<a href="#" class="oj-pagination-btn" data-page="${pagination.current_page + 1}">Next →</a>`;
        }
        
        $('.oj-pagination-controls').html(controlsHtml);
        $container.show();
    }

    /**
     * Handle pagination clicks
     */
    $(document).on('click', '.oj-pagination-btn', function(e) {
        e.preventDefault();
        
        if (isLoading) return;
        
        const page = parseInt($(this).data('page'));
        if (isNaN(page) || page < 1) return;
        
        currentPage = page;
        loadOrdersWithFilter(currentFilter, currentPage, currentSearch);
    });

    /**
     * Show notification message
     */
    function showNotification(message, type = 'info') {
        const notificationClass = type === 'error' ? 'oj-success-notification error' : 'oj-success-notification';
        
        const $notification = $(`
            <div class="${notificationClass}">
                ${escapeHtml(message)}
                <button class="oj-notification-close">×</button>
            </div>
        `);
        
        $('body').append($notification);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual close
        $notification.on('click', '.oj-notification-close', function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // ========================================================================
    // UTILITY FUNCTIONS
    // ========================================================================
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Capitalize first letter
     */
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    
    // ========================================================================
    // SEARCH FUNCTIONALITY
    // ========================================================================
    
    /**
     * Handle search input with debouncing
     */
    $(document).on('input', '#oj-orders-search', function() {
        const searchValue = $(this).val().trim();
        
        // Show/hide clear button
        if (searchValue.length > 0) {
            $('.oj-search-clear').show();
        } else {
            $('.oj-search-clear').hide();
        }
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Set new timeout for debounced search (500ms delay)
        searchTimeout = setTimeout(function() {
            performSearch(searchValue);
        }, 500);
    });
    
    /**
     * Handle search clear button
     */
    $(document).on('click', '.oj-search-clear', function() {
        $('#oj-orders-search').val('').focus();
        $('.oj-search-clear').hide();
        performSearch('');
    });
    
    /**
     * Handle Enter key in search input
     */
    $(document).on('keypress', '#oj-orders-search', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            
            // Clear timeout and search immediately
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            const searchValue = $(this).val().trim();
            performSearch(searchValue);
        }
    });
    
    /**
     * Perform search with current filter and reset to page 1
     */
    function performSearch(searchValue) {
        currentSearch = searchValue;
        currentPage = 1; // Reset to first page when searching
        
        loadOrdersWithFilter(currentFilter, currentPage, searchValue);
    }
    
    // ========================================================================
    // INITIALIZATION
    // ========================================================================
    
    console.log('Orders Master JavaScript loaded - Search Implementation Complete');
    
    // Initialize state from URL (but don't auto-load since page is server-rendered)
    $(document).ready(function() {
        // Get initial filter from URL or default to 'all'
        const urlParams = new URLSearchParams(window.location.search);
        const initialFilter = urlParams.get('filter') || 'all';
        const initialPage = parseInt(urlParams.get('paged')) || 1;
        
        currentFilter = initialFilter;
        currentPage = initialPage;
        
        console.log('✅ Orders Master initialized (server-rendered mode)');
        console.log('📊 Initial state:', { filter: currentFilter, page: currentPage });
        
        // NOTE: We do NOT auto-load orders via AJAX because:
        // - Orders Master V2 is server-rendered (PHP generates initial HTML)
        // - AJAX is only used for: filter clicks, search, pagination, and bulk action refresh
        // - Auto-loading would cause duplicate content and performance issues
    });
    
});
