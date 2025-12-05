/**
 * Filters Slide Panel JavaScript
 * Advanced Filters Interface for Orders Master
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Filters Panel Class
     */
    class OJFiltersPanel {
        constructor() {
            this.isOpen = false;
            this.currentFilters = {};
            this.savedViews = null; // Will be initialized when needed
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadCurrentFilters();
            this.updateFooterContext('filters'); // Set default context
        }

        bindEvents() {
            // Open panel
            $(document).on('click', '#oj-filters-trigger', (e) => {
                e.preventDefault();
                this.openPanel();
            });

            // Close panel - close button
            $(document).on('click', '#oj-filters-close', (e) => {
                e.preventDefault();
                this.closePanel();
            });

            // Close panel - overlay click
            $(document).on('click', '.oj-filters-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.closePanel();
                }
            });

            // Close panel - escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closePanel();
                }
            });

            // Tab switching
            $(document).on('click', '.oj-tab-btn', (e) => {
                e.preventDefault();
                const tabName = $(e.currentTarget).data('tab');
                this.switchTab(tabName);
            });

            // Panel filter changes
            $(document).on('change', '#panel-date-preset', (e) => {
                const value = $(e.target).val();
                if (value === 'custom') {
                    $('#panel-custom-dates').slideDown(200);
                } else {
                    $('#panel-custom-dates').slideUp(200);
                }
            });

            // Amount filter type change
            $(document).on('change', '#panel-amount-type', (e) => {
                this.toggleAmountInputs($(e.target).val());
            });

            // Footer buttons
            $(document).on('click', '#oj-apply-filters', (e) => {
                e.preventDefault();
                this.applyFilters();
            });

            // Reset Filters button - now handled by direct link
            // $(document).on('click', '#oj-reset-filters', (e) => {
            //     e.preventDefault();
            //     this.resetFilters();
            // });

            $(document).on('click', '#oj-save-view', (e) => {
                e.preventDefault();
                this.saveView();
            });
        }

        openPanel() {
            $('.oj-filters-overlay').addClass('active');
            $('body').addClass('oj-filters-open');
            this.isOpen = true;
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
            
        }

        closePanel() {
            $('.oj-filters-overlay').removeClass('active');
            $('body').removeClass('oj-filters-open');
            this.isOpen = false;
            
            // Restore body scroll
            $('body').css('overflow', '');
            
        }

        loadCurrentFilters() {
            // Extract current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            this.currentFilters = {};
            
            // Get all filter parameters
            const filterParams = [
                'filter', 'date_preset', 'date_from', 'date_to', 
                'search', 'order_type', 'kitchen_type', 'kitchen_status',
                'assigned_waiter', 'unassigned_only', 'assigned_only', 'orderby', 'order',
                'payment_method', 'amount_type', 'amount_value', 'amount_min', 'amount_max',
                'customer_type'
            ];

            filterParams.forEach(param => {
                const value = urlParams.get(param);
                if (value) {
                    this.currentFilters[param] = value;
                }
            });

            this.updateFilterCount();
            this.populatePanelFilters();
        }

        populatePanelFilters() {
            // Set defaults first
            const defaults = {
                'filter': 'all',
                'orderby': 'date_created',
                'order': 'DESC'
            };

            // Apply defaults to panel elements
            Object.keys(defaults).forEach(param => {
                const element = $(`[data-param="${param}"]`);
                if (element.length > 0 && element.is('select')) {
                    element.val(defaults[param]);
                }
            });

            // Then populate with current values (overriding defaults)
            Object.keys(this.currentFilters).forEach(param => {
                const value = this.currentFilters[param];
                const element = $(`[data-param="${param}"]`);
                
                if (element.length > 0) {
                    if (element.is('select')) {
                        element.val(value);
                    } else if (element.is('input')) {
                        element.val(value);
                    }
                }
            });

            // Handle custom date visibility
            const datePreset = this.currentFilters['date_preset'];
            if (datePreset === 'custom') {
                $('#panel-custom-dates').show();
            } else {
                $('#panel-custom-dates').hide();
            }

            // Handle amount filter inputs visibility
            const amountType = this.currentFilters['amount_type'];
            if (amountType) {
                this.toggleAmountInputs(amountType);
            }

            // Load assigned waiter filter
            const assignedWaiter = this.currentFilters['assigned_waiter'];
            const unassignedOnly = this.currentFilters['unassigned_only'];
            const assignedOnly = this.currentFilters['assigned_only'];

            if (unassignedOnly === '1') {
                $('#panel-assigned-waiter').val('unassigned');
            } else if (assignedOnly === '1') {
                $('#panel-assigned-waiter').val('assigned_only');
            } else if (assignedWaiter) {
                $('#panel-assigned-waiter').val(assignedWaiter);
            }

        }

        updateFilterCount() {
            // Count active filters (excluding default values)
            let activeCount = 0;
            const defaults = {
                'filter': 'all',
                'orderby': 'date_created',
                'order': 'DESC'
            };

            Object.keys(this.currentFilters).forEach(key => {
                const value = this.currentFilters[key];
                if (value && value !== '' && value !== defaults[key]) {
                    activeCount++;
                }
            });

            // Update count badge
            const $countBadge = $('#oj-filters-count');
            if (activeCount > 0) {
                $countBadge.text(activeCount).show();
            } else {
                $countBadge.hide();
            }

        }


        applyFilters() {
            
            // Collect all filter values from panel
            const newFilters = {};
            const defaults = {
                'filter': 'all',
                'orderby': 'date_created',
                'order': 'DESC'
            };
            
            // Get values from all panel filter elements (except assigned_waiter - handled specially)
            $('.oj-filters-body [data-param]').each(function() {
                const param = $(this).data('param');
                const value = $(this).val();
                
                // Skip assigned_waiter - we handle it specially below
                if (param === 'assigned_waiter') {
                    return;
                }
                
                if (value && value !== '') {
                    newFilters[param] = value;
                }
            });
            
            // Special handling for assigned_waiter filter
            const assignedWaiter = $('#panel-assigned-waiter').val();
            
            if (assignedWaiter && assignedWaiter !== '') {
                if (assignedWaiter === 'unassigned') {
                    // Convert "unassigned" to unassigned_only parameter
                    newFilters['unassigned_only'] = '1';
                    // Ensure other parameters are not set
                    delete newFilters['assigned_waiter'];
                    delete newFilters['assigned_only'];
                } else if (assignedWaiter === 'assigned_only') {
                    // Show only orders that have ANY waiter assigned
                    newFilters['assigned_only'] = '1';
                    // Ensure other parameters are not set
                    delete newFilters['assigned_waiter'];
                    delete newFilters['unassigned_only'];
                } else {
                    // Set assigned_waiter to the specific user ID
                    newFilters['assigned_waiter'] = assignedWaiter;
                    // Ensure other parameters are not set
                    delete newFilters['unassigned_only'];
                    delete newFilters['assigned_only'];
                }
            }
            
            // Ensure sort parameters are always included (with defaults if not set)
            if (!newFilters['orderby']) {
                newFilters['orderby'] = defaults['orderby'];
            }
            if (!newFilters['order']) {
                newFilters['order'] = defaults['order'];
            }
            
            // Build new URL with filters
            const baseUrl = window.location.href.split('?')[0];
            const urlParams = new URLSearchParams();
            
            // Always include the page parameter
            // Get current page from URL or default to orders-master-v2
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
            urlParams.set('page', currentPage);
            
            // Add all filter parameters
            Object.keys(newFilters).forEach(key => {
                urlParams.set(key, newFilters[key]);
            });
            
            // Preserve debug parameter if it exists
            if (window.location.search.includes('debug=1')) {
                urlParams.set('debug', '1');
            }
            
            const newUrl = baseUrl + '?' + urlParams.toString();
            
            // Update browser URL without reload
            history.pushState(null, '', newUrl);
            
            // Close panel on mobile after applying filters, keep open on desktop
            if (window.innerWidth <= 768) {
                this.closePanel(); // Close on mobile for better UX
            }
            // On desktop, keep panel open for easy filter adjustments
            
            // Refresh content via AJAX
            this.refreshOrdersContent(newFilters);
        }

        resetFilters() {
            
            const defaults = {
                'filter': 'all',
                'orderby': 'date_created',
                'order': 'DESC'
            };
            
            // Clear all panel filter values and set defaults
            $('.oj-filters-body [data-param]').each(function() {
                const param = $(this).data('param');
                
                if ($(this).is('select')) {
                    // Set to default if available, otherwise empty
                    $(this).val(defaults[param] || '');
                } else if ($(this).is('input')) {
                    $(this).val('');
                }
            });
            
            // Hide custom dates
            $('#panel-custom-dates').hide();
            
            // Build reset URL with defaults
            const baseUrl = window.location.href.split('?')[0];
            // Get current page for reset URL
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
            let resetUrl = baseUrl + '?page=' + currentPage;
            
            // Add default sort parameters to URL
            resetUrl += '&orderby=' + defaults['orderby'];
            resetUrl += '&order=' + defaults['order'];
            
            // Preserve debug parameter if it exists
            if (window.location.search.includes('debug=1')) {
                resetUrl += '&debug=1';
            }
            
            // Update browser URL without reload
            history.pushState(null, '', resetUrl);
            
            // Close panel on mobile after reset, keep open on desktop
            if (window.innerWidth <= 768) {
                this.closePanel(); // Close on mobile for better UX
            }
            // On desktop, keep panel open for easy filter adjustments
            
            // Refresh content via AJAX with defaults
            this.refreshOrdersContent(defaults);
        }

        toggleAmountInputs(amountType) {
            const singleInput = $('#amount-single-input');
            const rangeInputs = $('#amount-range-inputs');
            
            // Hide all inputs first
            singleInput.hide();
            rangeInputs.hide();
            
            // Show appropriate inputs based on selection
            if (amountType === 'between') {
                rangeInputs.show();
            } else if (amountType && amountType !== '') {
                singleInput.show();
            }
            
        }

        /**
         * Refresh orders content via AJAX
         */
        refreshOrdersContent(filterParams) {
            
            // Show loading state
            this.showLoadingState();
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'oj_refresh_orders_content',
                nonce: ordersJetAjax.nonce,
                step: 0, // Full implementation
                current_page: this.getCurrentPageType(), // Add current page context
                ...filterParams
            };
            
            
            // Make AJAX request
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        // Update the content area with new HTML
                        this.updateContentArea(response.data);
                        
                        // Update filter counts in the panel
                        this.updateFilterCounts(response.data.filter_counts);
                        
                        // Synchronize toolbar filters with current URL parameters
                        this.synchronizeToolbarFilters();
                        
                        // Update current filters for future operations
                        this.loadCurrentFilters();
                        
                        // Show success feedback
                        this.showSuccessFeedback(response.data);
                        
                    } else {
                        this.showErrorMessage(response.data.message || 'Failed to refresh content');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('An error occurred while refreshing the page. Please try again.');
                },
                complete: () => {
                    this.hideLoadingState();
                }
            });
        }

        /**
         * Show loading state during AJAX requests
         */
        showLoadingState() {
            // Add loading class to content areas (subtle dimming effect)
            $('.oj-orders-meta-row, .oj-orders-grid, .oj-pagination-container').addClass('oj-loading');
            
            // No loading overlay - just the subtle content dimming is enough
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            $('.oj-orders-meta-row, .oj-orders-grid, .oj-pagination-container').removeClass('oj-loading');
            // No overlay to remove - just remove the dimming effect
        }

        /**
         * Update content area with new HTML
         */
        updateContentArea(data) {
            // Target the specific dynamic content container
            const $dynamicContent = $('#oj-dynamic-content');
            
            if ($dynamicContent.length > 0 && data.content_html) {
                // Replace the entire dynamic content area
                $dynamicContent.html(data.content_html);
            } else {
                
                // Fallback to individual section updates
                if (data.orders_html) {
                    const $ordersGrid = $('.oj-orders-grid');
                    if ($ordersGrid.length > 0) {
                        $ordersGrid.replaceWith(data.orders_html);
                    }
                }
                
                if (data.count_html) {
                    const $ordersCount = $('.oj-orders-count');
                    if ($ordersCount.length > 0) {
                        $ordersCount.html(data.count_html);
                    }
                }
                
                if (data.pagination_html) {
                    const $pagination = $('.oj-pagination-container');
                    if ($pagination.length > 0) {
                        $pagination.replaceWith(data.pagination_html);
                    } else {
                        // If no pagination container exists, append after orders grid
                        $('.oj-orders-grid, .oj-empty-state').after(data.pagination_html);
                    }
                }
            }
            
            // CRITICAL: Trigger event for bulk actions to reinitialize after filter updates
            $(document).trigger('oj-orders-grid-updated');
        }

        /**
         * Synchronize toolbar filters with current URL parameters
         */
        synchronizeToolbarFilters() {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            // Define default values for each filter (matches PHP defaults)
            const filterDefaults = {
                'filter': 'all',           // Order Status defaults to "All Orders"
                'date_preset': '',         // Date defaults to empty (All time)
                'order_type': '',          // Order Type defaults to empty (All Types)
                'kitchen_type': ''         // Kitchen Type defaults to empty (All Kitchen)
            };
            
            // Synchronize each toolbar filter
            const toolbarFilters = {
                'filter': '.oj-status-select',
                'date_preset': '.oj-date-select',
                'order_type': '.oj-order-type-select',
                'kitchen_type': '.oj-kitchen-type-select'
            };
            
            Object.keys(toolbarFilters).forEach(paramName => {
                const selector = toolbarFilters[paramName];
                const $select = $(selector);
                
                if ($select.length > 0) {
                    // Get value from URL or use default
                    const currentValue = params.get(paramName) || filterDefaults[paramName];
                    if ($select.val() !== currentValue) {
                        $select.val(currentValue);
                    }
                }
            });
            
            // Handle custom dates display
            const datePreset = params.get('date_preset');
            const customDatesDiv = $('.oj-custom-dates');
            if (customDatesDiv.length > 0) {
                if (datePreset === 'custom') {
                    customDatesDiv.show();
                    $('input[name="date_from"]').val(params.get('date_from') || '');
                    $('input[name="date_to"]').val(params.get('date_to') || '');
                } else {
                    customDatesDiv.hide();
                }
            }
            
            // Synchronize search input
            const searchValue = params.get('search') || '';
            const $searchInput = $('.oj-search-input');
            if ($searchInput.length > 0 && $searchInput.val() !== searchValue) {
                $searchInput.val(searchValue);
            }
        }

        /**
         * Update filter counts in the panel
         */
        updateFilterCounts(filterCounts) {
            if (!filterCounts) return;
            
            // Update status filter counts
            if (filterCounts.status) {
                Object.keys(filterCounts.status).forEach(status => {
                    const count = filterCounts.status[status];
                    const $statusOption = $(`.oj-filters-body select[data-param="filter"] option[value="${status}"]`);
                    if ($statusOption.length > 0) {
                        const originalText = $statusOption.text().replace(/ \(\d+\)$/, '');
                        $statusOption.text(`${originalText} (${count})`);
                    }
                });
            }
            
            // Update kitchen type counts
            if (filterCounts.kitchen_type) {
                Object.keys(filterCounts.kitchen_type).forEach(kitchenType => {
                    const count = filterCounts.kitchen_type[kitchenType];
                    const $kitchenOption = $(`.oj-filters-body select[data-param="kitchen_type"] option[value="${kitchenType}"]`);
                    if ($kitchenOption.length > 0) {
                        const originalText = $kitchenOption.text().replace(/ \(\d+\)$/, '');
                        $kitchenOption.text(`${originalText} (${count})`);
                    }
                });
            }
            
            // Update order type counts
            if (filterCounts.order_type) {
                Object.keys(filterCounts.order_type).forEach(orderType => {
                    const count = filterCounts.order_type[orderType];
                    const $orderOption = $(`.oj-filters-body select[data-param="order_type"] option[value="${orderType}"]`);
                    if ($orderOption.length > 0) {
                        const originalText = $orderOption.text().replace(/ \(\d+\)$/, '');
                        $orderOption.text(`${originalText} (${count})`);
                    }
                });
            }
        }

        /**
         * Show success feedback
         */
        showSuccessFeedback(data) {
            // Optional: Show brief success message
            if (data.orders_count !== undefined) {
                const message = `Updated: ${data.orders_count} orders found`;
                this.showTemporaryMessage(message, 'success');
            }
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            this.showTemporaryMessage(message, 'error');
        }

        /**
         * Show temporary message
         */
        showTemporaryMessage(message, type = 'info') {
            const $message = $(`
                <div class="oj-temp-message oj-temp-message-${type}">
                    <span class="oj-message-icon">
                        ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
                    </span>
                    <span class="oj-message-text">${message}</span>
                </div>
            `);
            
            $('body').append($message);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 3000);
        }

        /**
         * Switch between tabs
         */
        switchTab(tabName) {
            console.log('🔄 Switching to tab:', tabName);
            
            // Update tab buttons
            $('.oj-tab-btn').removeClass('active');
            $(`.oj-tab-btn[data-tab="${tabName}"]`).addClass('active');
            
            // Update tab content - CRITICAL: Hide all tabs first
            console.log('👁️ Before switch - Filters active?', $('#oj-tab-filters').hasClass('active'));
            console.log('👁️ Before switch - Views active?', $('#oj-tab-saved-views').hasClass('active'));
            
            $('.oj-tab-content').removeClass('active').hide();
            $(`#oj-tab-${tabName}`).addClass('active').show();
            
            console.log('👁️ After switch - Filters active?', $('#oj-tab-filters').hasClass('active'));
            console.log('👁️ After switch - Views active?', $('#oj-tab-saved-views').hasClass('active'));
            console.log('👁️ After switch - Filters display?', $('#oj-tab-filters').css('display'));
            console.log('👁️ After switch - Views display?', $('#oj-tab-saved-views').css('display'));
            
            // Update footer button context
            this.updateFooterContext(tabName);
            
            // Load saved views if switching to saved views tab
            if (tabName === 'saved-views') {
                console.log('📂 Switching to saved views tab');
                this.initializeSavedViews();
                console.log('✅ OJSavedViews initialized:', this.savedViews);
                this.savedViews.loadSavedViews();
                console.log('✅ loadSavedViews() called');
            }
        }

        /**
         * Initialize saved views class when needed
         */
        initializeSavedViews() {
            if (!this.savedViews) {
                this.savedViews = new OJSavedViews(this);
            }
        }

        /**
         * Update footer button context based on active tab
         */
        updateFooterContext(tabName) {
            const saveBtn = $('#oj-save-view');
            const resetBtn = $('.oj-btn-secondary'); // Reset is now a link
            const applyBtn = $('#oj-apply-filters');
            
            if (tabName === 'filters') {
                // Filters tab context
                saveBtn.text('Save View').attr('title', 'Save current filter settings as a named view');
                resetBtn.text('Reset').attr('title', 'Reset all filters to defaults');
                applyBtn.text('Apply Filters').attr('title', 'Apply current filter settings');
                
                // Show/hide buttons based on context
                applyBtn.show();
                resetBtn.show();
                saveBtn.show();
                
            } else if (tabName === 'saved-views') {
                // Saved views tab context
                saveBtn.text('Save Current View').attr('title', 'Save current filter settings as a new view');
                resetBtn.text('Clear Selection').attr('title', 'Clear selected view');
                applyBtn.text('Load Selected').attr('title', 'Load the selected saved view');
                
                // Show/hide buttons based on context
                applyBtn.show();
                resetBtn.show(); 
                saveBtn.show();
            }
        }





        saveView() {
            this.initializeSavedViews();
            this.savedViews.saveCurrentFilters();
        }

        /**
         * Get current page type for AJAX context
         */
        getCurrentPageType() {
            // Check URL parameters to determine page type
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            
            if (page === 'orders-reports') {
                return 'orders-reports';
            } else if (page === 'orders-master' || page === 'orders-jet-master') {
                return 'orders-master';
            } else {
                // Default fallback
                return 'orders-master';
            }
        }

    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        // Only initialize on Orders Master V2 page
        if ($('#oj-filters-trigger').length > 0) {
            window.ojFiltersPanel = new OJFiltersPanel();
        }
        
    });


})(jQuery);
