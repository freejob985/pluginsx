/**
 * Orders Master V2 - Management Dashboard JavaScript
 * 
 * Handles action buttons for Orders Master V2 page
 * Different behavior from Orders Express (operations view)
 * 
 * @package Orders_Jet
 * @version 2.0
 */

(function($) {
    'use strict';

    /**
     * Handle toolbar form submission via AJAX
     */
    $(document).on('submit', '.oj-toolbar-form', function(e) {
        e.preventDefault();
        
        
        // Get form data
        const formData = $(this).serialize();
        const params = new URLSearchParams(formData);
        const filterParams = {};
        
        // Convert form data to filter parameters object
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Build new URL
        const baseUrl = window.location.href.split('?')[0];
        const newUrl = baseUrl + '?' + formData;
        
        // Update browser URL
        history.pushState(null, '', newUrl);
        
        // Refresh content via AJAX using the filters panel method
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            // Fallback to page reload if filters panel not available
            window.location.href = newUrl;
        }
    });

    /**
     * Handle sort links via AJAX
     */
    $(document).on('click', '.oj-sort-link', function(e) {
        e.preventDefault();
        
        const sortUrl = $(this).attr('href');
        
        const params = new URLSearchParams(sortUrl.split('?')[1]);
        const filterParams = {};
        
        // Convert URL parameters to filter parameters object
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        // Update browser URL
        history.pushState(null, '', sortUrl);
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            // Fallback to page reload
            window.location.href = sortUrl;
        }
    });

    /**
     * Handle pagination links via AJAX
     */
    $(document).on('click', '.oj-pagination-controls a', function(e) {
        e.preventDefault();
        
        const pageUrl = $(this).attr('href');
        const params = new URLSearchParams(pageUrl.split('?')[1]);
        const filterParams = {};
        
        // Convert URL parameters to filter parameters object
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        // Update browser URL
        history.pushState(null, '', pageUrl);
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            // Fallback to page reload
            window.location.href = pageUrl;
        }
    });

    /**
     * Handle search input changes with debouncing
     */
    let searchTimeout;
    $(document).on('input', '.oj-search-input', function() {
        const searchValue = $(this).val();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Debounce search to avoid too many requests
        searchTimeout = setTimeout(() => {
            triggerSearchFilter(searchValue);
        }, 500); // 500ms delay
    });


    /**
     * Handle custom date input changes
     */
    $(document).on('change', '.oj-date-input', function() {
        const dateFromInput = $('input[name="date_from"]');
        const dateToInput = $('input[name="date_to"]');
        
        const dateFrom = dateFromInput.val();
        const dateTo = dateToInput.val();
        
        
        // Only trigger if both dates are set
        if (dateFrom && dateTo) {
            triggerDateRangeFilter(dateFrom, dateTo);
        }
    });

    /**
     * Trigger search filter via AJAX
     */
    function triggerSearchFilter(searchValue) {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Update search parameter
        if (searchValue && searchValue.trim() !== '') {
            params.set('search', searchValue.trim());
        } else {
            params.delete('search');
        }
        // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
        params.delete('paged'); // Reset pagination
        
        // Build new URL
        const newUrl = currentUrl.pathname + '?' + params.toString();
        
        // Update browser URL without reload
        history.pushState(null, '', newUrl);
        
        // Convert to filter parameters
        const filterParams = {};
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        }
    }

    /**
     * Trigger custom date range filter via AJAX
     */
    function triggerDateRangeFilter(dateFrom, dateTo) {
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Update date parameters
        params.set('date_preset', 'custom');
        params.set('date_from', dateFrom);
        params.set('date_to', dateTo);
        // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
        params.delete('paged'); // Reset pagination
        
        // Build new URL
        const newUrl = currentUrl.pathname + '?' + params.toString();
        
        // Update browser URL without reload
        history.pushState(null, '', newUrl);
        
        // Convert to filter parameters
        const filterParams = {};
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        }
    }

    // Print Invoice - Opens print dialog and changes button to "Paid?"
    $(document).on('click', '.oj-print-invoice', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        
        if (!orderId) {
            alert('Order ID not found');
            return;
        }
        
        // Generate invoice URL
        const invoiceUrl = ojExpressData.ajaxUrl + 
            '?action=oj_get_order_invoice' +
            '&order_id=' + orderId +
            '&print=1' +
            '&nonce=' + ojExpressData.nonces.invoice;
        
        // Create hidden iframe for printing in same page
        const $iframe = $('<iframe>', {
            src: invoiceUrl,
            style: 'position: absolute; left: -9999px; width: 1px; height: 1px;'
        });
        
        $('body').append($iframe);
        
        // Wait for iframe to load, then trigger print
        $iframe.on('load', function() {
            setTimeout(() => {
                try {
                    this.contentWindow.print();
                    
                    // Change button to "Paid?" after printing
                    $btn.removeClass('oj-print-invoice')
                        .addClass('oj-mark-paid-master')
                        .html('💰 Paid?')
                        .data('order-id', orderId);
                    
                    // Remove iframe after printing
                    setTimeout(() => $iframe.remove(), 1000);
                } catch (e) {
                    console.error('Print failed:', e);
                    alert('Failed to open print dialog: ' + e.message);
                    $iframe.remove();
                }
            }, 500);
        });
        
        $iframe.on('error', function() {
            alert('Failed to load invoice');
            $iframe.remove();
        });
    });

    // Mark as Paid - Marks order as paid and changes button back to "Print Invoice"
    // Card stays visible (different from Orders Express)
    $(document).on('click', '.oj-mark-paid-master', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const $btn = $(this);
        const $card = $btn.closest('.oj-order-card');
        const orderId = $btn.data('order-id');
        
        if (!orderId) {
            alert('Order ID not found');
            return;
        }
        
        // Confirm payment received
        if (!confirm('Confirm that payment has been received?')) {
            return;
        }
        
        // Disable button during AJAX
        $btn.prop('disabled', true).html('⏳ Processing...');
        
        // Mark order as paid via AJAX
        $.ajax({
            url: ojExpressData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oj_mark_order_paid',
                order_id: orderId,
                nonce: ojExpressData.nonces.dashboard
            },
            success: function(response) {
                if (response.success) {
                    // Change button back to "Print Invoice"
                    $btn.removeClass('oj-mark-paid-master')
                        .addClass('oj-print-invoice')
                        .html('🖨️ Print Invoice')
                        .prop('disabled', false)
                        .data('order-id', orderId);
                    
                    // Show success message
                    if (typeof showExpressNotification === 'function') {
                        showExpressNotification('✅ Payment confirmed! Order marked as paid.', 'success');
                    } else {
                        alert('✅ Payment confirmed!');
                    }
                    
                    // Card stays visible (no removal)
                } else {
                    $btn.prop('disabled', false).html('💰 Paid?');
                    alert('❌ Failed to mark as paid: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('💰 Paid?');
                alert('❌ Connection error. Please try again.');
            }
        });
    });

    // ========================================================================
    // TOOLBAR FILTER FUNCTIONS
    // ========================================================================
    
    /**
     * Update filter parameter while preserving all other URL parameters
     * Called when status filter dropdown changes - Updated for AJAX
     */
    window.updateFilterParam = function(selectElement) {
        const newFilterValue = selectElement.value;
        
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Update the filter parameter
        params.set('filter', newFilterValue);
        // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
        
        // Reset pagination to page 1 when changing filters
        params.delete('paged');
        
        // Build new URL with all preserved parameters
        const newUrl = currentUrl.pathname + '?' + params.toString();
        
        // Update browser URL without reload
        history.pushState(null, '', newUrl);
        
        // Convert URLSearchParams to filter parameters object
        const filterParams = {};
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            window.location.href = newUrl;
        }
    };
    
    /**
     * Update toolbar filter parameter and trigger AJAX
     * Called when toolbar dropdowns change (Order Type, Kitchen Type, etc.)
     */
    window.updateToolbarFilter = function(selectElement) {
        const paramName = selectElement.name;
        const newValue = selectElement.value;
        
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Special handling for combined orderby_order parameter
        if (paramName === 'orderby_order' && newValue) {
            const [orderby, order] = newValue.split('_');
            params.set('orderby', orderby);
            params.set('order', order);
        } else {
            // Update the specific parameter
            if (newValue && newValue !== '') {
                params.set(paramName, newValue);
            } else {
                params.delete(paramName);
            }
        }
        // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
        
        // Reset pagination to page 1 when changing filters
        params.delete('paged');
        
        // Build new URL with all preserved parameters
        const newUrl = currentUrl.pathname + '?' + params.toString();
        
        // Update browser URL without reload
        history.pushState(null, '', newUrl);
        
        // Convert URLSearchParams to filter parameters object
        const filterParams = {};
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            window.location.href = newUrl;
        }
    };
    
    /**
     * Update advanced filter parameter and trigger AJAX
     * Called when advanced panel dropdowns change (Order Type, Kitchen Type, etc.)
     */
    window.updateAdvancedFilter = function(selectElement) {
        const paramName = selectElement.getAttribute('data-param');
        const newValue = selectElement.value;
        
        const currentUrl = new URL(window.location.href);
        const params = new URLSearchParams(currentUrl.search);
        
        // Update the specific parameter
        if (newValue && newValue !== '') {
            params.set(paramName, newValue);
        } else {
            params.delete(paramName);
        }
        // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
        
        // Reset pagination to page 1 when changing filters
        params.delete('paged');
        
        // Build new URL with all preserved parameters
        const newUrl = currentUrl.pathname + '?' + params.toString();
        
        // Update browser URL without reload
        history.pushState(null, '', newUrl);
        
        // Convert URLSearchParams to filter parameters object
        const filterParams = {};
        for (const [key, value] of params.entries()) {
            if (value && key !== 'page') {
                filterParams[key] = value;
            }
        }
        
        
        // Refresh content via AJAX
        if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
            window.ojFiltersPanel.refreshOrdersContent(filterParams);
        } else {
            window.location.href = newUrl;
        }
    };
    
    /**
     * Handle custom date range toggle and AJAX filtering
     * Called when date preset dropdown changes
     */
    window.toggleCustomDates = function(selectElement) {
        const newDatePreset = selectElement.value;
        
        // Handle UI toggle for custom dates
        const customDatesDiv = selectElement.parentNode.querySelector('.oj-custom-dates');
        if (customDatesDiv) {
            if (newDatePreset === 'custom') {
                customDatesDiv.style.display = 'flex';
                // Don't trigger AJAX for custom - wait for user to set dates
                return;
            } else {
                customDatesDiv.style.display = 'none';
                // Clear custom date values when not using custom
                const dateInputs = customDatesDiv.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => input.value = '');
            }
        }
        
        // Trigger AJAX filtering for non-custom presets
        if (newDatePreset !== 'custom') {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            // Update date parameters - PRESERVE all existing parameters
            params.set('date_preset', newDatePreset);
            // Get current page from URL or default to orders-master-v2
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
        params.set('page', currentPage);
            
            // Only clear custom date parameters when using presets
            params.delete('date_from');
            params.delete('date_to');
            params.delete('paged'); // Reset pagination
            
            // Build new URL - this preserves all other parameters like 'filter'
            const newUrl = currentUrl.pathname + '?' + params.toString();
            
            // Update browser URL without reload
            history.pushState(null, '', newUrl);
            
            // Convert to filter parameters
            const filterParams = {};
            for (const [key, value] of params.entries()) {
                if (value && key !== 'page') {
                    filterParams[key] = value;
                }
            }
            
            // Refresh content via AJAX
            if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
                window.ojFiltersPanel.refreshOrdersContent(filterParams);
            } else {
                window.location.href = newUrl;
            }
        }
    };

    /**
     * Handle customer overlay "View all orders" button clicks
     * Prevent page redirect and use AJAX instead
     */
    $(document).on('click', '.oj-overlay-btn', function(e) {
        e.preventDefault();
        
        // Get the URL from the button
        const url = new URL(this.href);
        const searchParam = url.searchParams.get('search');
        
        if (searchParam) {
            // Build filter parameters from current URL
            const currentParams = new URLSearchParams(window.location.search);
            const filterParams = {};
            
            // Preserve all current filters
            for (const [key, value] of currentParams.entries()) {
                if (value && key !== 'paged') { // Reset pagination for new search
                    filterParams[key] = value;
                }
            }
            
            // Add the search parameter
            filterParams['search'] = searchParam;
            
            // Update the search input if it exists
            const searchInput = $('input[name="search"]');
            if (searchInput.length) {
                searchInput.val(searchParam);
            }
            
            // Build new URL
            const baseUrl = window.location.href.split('?')[0];
            const newUrlParams = new URLSearchParams(filterParams);
            const newUrl = baseUrl + '?' + newUrlParams.toString();
            
            // Update browser URL without reload
            window.history.pushState({}, '', newUrl);
            
            // Refresh the grid content via AJAX
            if (window.ojFiltersPanel && window.ojFiltersPanel.refreshOrdersContent) {
                window.ojFiltersPanel.refreshOrdersContent(filterParams);
            } else {
                // Fallback to page reload if AJAX not available
                window.location.href = newUrl;
            }
        }
    });

})(jQuery);

