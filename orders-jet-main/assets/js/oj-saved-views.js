/**
 * Orders Jet - Saved Views Class
 * Handles saved filter views functionality
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Saved Views Management Class
     */
    class OJSavedViews {
        constructor(filtersPanel) {
            this.filtersPanel = filtersPanel;
            this.selectedViewId = null;
            this.init();
        }

        init() {
            // Class is initialized when needed, no immediate setup required
        }

        /**
         * Load saved views for current user
         */
        loadSavedViews() {
            console.log('🔄 loadSavedViews() started');
            const container = $('#oj-saved-views-list');
            console.log('📦 Container found:', container.length);
            const loading = container.find('.oj-saved-views-loading');
            const empty = container.find('.oj-saved-views-empty');
            const items = container.find('.oj-saved-views-items');
            console.log('📦 Elements:', {loading: loading.length, empty: empty.length, items: items.length});
            
            // Show loading state
            loading.show();
            empty.hide();
            items.hide();
            console.log('✅ Loading state shown');
            
            // Make AJAX call to get saved views
            console.log('🌐 Making AJAX call to:', ordersJetAjax.ajaxurl);
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_get_user_saved_views',
                    nonce: ordersJetAjax.nonce
                },
                success: (response) => {
                    console.log('✅ AJAX response received:', response);
                    loading.hide();
                    
                    if (response.success && response.data.views && Object.keys(response.data.views).length > 0) {
                        console.log('📋 Views found:', Object.keys(response.data.views).length);
                        // Display saved views
                        this.displaySavedViews(response.data.views);
                        
                        // CRITICAL: Show items container and hide empty state
                        console.log('👁️ Before show - items visible?', items.is(':visible'));
                        console.log('👁️ Before hide - empty visible?', empty.is(':visible'));
                        items.show();
                        empty.hide();
                        console.log('👁️ After show - items visible?', items.is(':visible'));
                        console.log('👁️ After hide - empty visible?', empty.is(':visible'));
                        console.log('📐 Items display style:', items.css('display'));
                        console.log('📐 Items height:', items.height(), 'px');
                        console.log('📐 Items children count:', items.children().length);
                        console.log('📐 First child:', items.children().first());
                        console.log('📐 Items container:', items);
                        
                        // Check parent visibility
                        console.log('👁️ Tab content #oj-tab-saved-views visible?', $('#oj-tab-saved-views').is(':visible'));
                        console.log('👁️ Tab content has class active?', $('#oj-tab-saved-views').hasClass('active'));
                        console.log('👁️ Tab content display:', $('#oj-tab-saved-views').css('display'));
                    } else {
                        console.log('📭 No views found or response not successful');
                        // Show empty state
                        empty.show();
                        items.hide();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('❌ Load saved views error:', error, xhr, status);
                    loading.hide();
                    empty.show();
                    items.hide();
                    this.showNotification('Failed to load saved views', 'error');
                }
            });
        }

        /**
         * Display saved views in the UI
         */
        displaySavedViews(views) {
            console.log('🎨 displaySavedViews() called with:', views);
            const itemsContainer = $('.oj-saved-views-items');
            console.log('📦 Items container:', itemsContainer.length, itemsContainer);
            itemsContainer.empty();
            
            // Convert views object to array and sort by last_used
            const viewsArray = Object.values(views).sort((a, b) => {
                return new Date(b.last_used) - new Date(a.last_used);
            });
            console.log('📋 Views array:', viewsArray.length, viewsArray);
            
            viewsArray.forEach((view, index) => {
                console.log(`🔨 Creating view ${index + 1}:`, view.name);
                const viewHtml = this.createSavedViewHTML(view);
                console.log(`📄 HTML for view ${index + 1}:`, viewHtml.substring(0, 100) + '...');
                itemsContainer.append(viewHtml);
            });
            
            console.log('✅ All views appended, container HTML:', itemsContainer.html().substring(0, 200));
            
            // Bind click events for view items
            this.bindSavedViewEvents();
            console.log('✅ Events bound');
        }

        /**
         * Create HTML for a single saved view
         */
        createSavedViewHTML(view) {
            const filterTags = this.createFilterTags(view.filters);
            const lastUsed = this.formatRelativeTime(view.last_used);
            const useCount = view.use_count || 0;
            
            return `
                <div class="oj-saved-view-item" data-view-id="${view.id}">
                    <div class="oj-saved-view-header">
                        <div class="oj-saved-view-name">${view.name}</div>
                        <div class="oj-saved-view-actions">
                            <button class="oj-saved-view-action load" title="Load this view">📂</button>
                            <button class="oj-saved-view-action rename" title="Rename view">✏️</button>
                            <button class="oj-saved-view-action delete" title="Delete view">🗑️</button>
                        </div>
                    </div>
                    <div class="oj-saved-view-meta">
                        <span>Used ${useCount} times</span>
                        <span>Last used ${lastUsed}</span>
                    </div>
                    <div class="oj-saved-view-filters">
                        ${filterTags}
                    </div>
                </div>
            `;
        }

        /**
         * Create filter tags HTML
         */
        createFilterTags(filters) {
            const tags = [];
            
            // Map filter keys to readable names
            const filterLabels = {
                'filter': 'Status',
                'date_preset': 'Date',
                'search': 'Search',
                'order_type': 'Order Type',
                'kitchen_type': 'Kitchen',
                'payment_method': 'Payment',
                'amount_type': 'Amount',
                'orderby': 'Sort'
            };
            
            Object.keys(filters).forEach(key => {
                if (filterLabels[key] && filters[key]) {
                    let value = filters[key];
                    
                    // Format specific values
                    if (key === 'amount_type' && filters.amount_value) {
                        value = `${value} ${filters.amount_value}`;
                    } else if (key === 'amount_type' && filters.amount_min && filters.amount_max) {
                        value = `${filters.amount_min}-${filters.amount_max}`;
                    }
                    
                    tags.push(`<span class="oj-filter-tag">${filterLabels[key]}: ${value}</span>`);
                }
            });
            
            return tags.join(' ');
        }

        /**
         * Format relative time (e.g., "2 hours ago")
         */
        formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins} min ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString();
        }

        /**
         * Bind events for saved view items
         */
        bindSavedViewEvents() {
            // Load view
            $('.oj-saved-view-action.load').off('click').on('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const viewId = $(e.target).closest('.oj-saved-view-item').data('view-id');
                this.loadSavedView(viewId);
            });
            
            // Rename view
            $('.oj-saved-view-action.rename').off('click').on('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const viewId = $(e.target).closest('.oj-saved-view-item').data('view-id');
                const currentName = $(e.target).closest('.oj-saved-view-item').find('.oj-saved-view-name').text();
                this.renameSavedView(viewId, currentName);
            });
            
            // Delete view
            $('.oj-saved-view-action.delete').off('click').on('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const viewId = $(e.target).closest('.oj-saved-view-item').data('view-id');
                const viewName = $(e.target).closest('.oj-saved-view-item').find('.oj-saved-view-name').text();
                this.deleteSavedView(viewId, viewName);
            });
            
            // Click on view item to select it
            $('.oj-saved-view-item').off('click').on('click', (e) => {
                if (!$(e.target).hasClass('oj-saved-view-action')) {
                    // First select the view
                    this.selectSavedView($(e.currentTarget));
                }
            });
            
            // Double-click to load
            $('.oj-saved-view-item').off('dblclick').on('dblclick', (e) => {
                if (!$(e.target).hasClass('oj-saved-view-action')) {
                    const viewId = $(e.currentTarget).data('view-id');
                    this.loadSavedView(viewId);
                }
            });
        }

        /**
         * Select a saved view (visual feedback)
         */
        selectSavedView($viewItem) {
            // Remove selection from other views
            $('.oj-saved-view-item').removeClass('selected');
            
            // Add selection to clicked view
            $viewItem.addClass('selected');
            
            // Store selected view ID
            this.selectedViewId = $viewItem.data('view-id');
            
            // Update footer context for selected view
            this.updateFooterForSelectedView(this.selectedViewId);
        }

        /**
         * Clear view selection
         */
        clearSelection() {
            $('.oj-saved-view-item').removeClass('selected');
            this.selectedViewId = null;
            this.filtersPanel.updateFooterContext('saved-views'); // Reset to default context
        }

        /**
         * Update footer buttons based on selected view
         */
        updateFooterForSelectedView(viewId) {
            const saveBtn = $('#oj-save-view');
            const resetBtn = $('#oj-reset-filters');
            const applyBtn = $('#oj-apply-filters');
            
            if (viewId) {
                // View selected context
                applyBtn.text('Load Selected View').attr('title', 'Load the selected saved view');
                resetBtn.text('Clear Selection').attr('title', 'Clear view selection');
                saveBtn.text('Save Current View').attr('title', 'Save current filter settings as a new view');
                
                // Update apply button to load selected view
                applyBtn.off('click.selectedView').on('click.selectedView', () => {
                    this.loadSavedView(viewId);
                });
                
                // Update reset button to clear selection
                resetBtn.off('click.selectedView').on('click.selectedView', () => {
                    this.clearSelection();
                });
            } else {
                // No selection - reset to default
                this.filtersPanel.updateFooterContext('saved-views');
            }
        }

        /**
         * Load a saved view
         */
        loadSavedView(viewId) {
            // Get current page from URL
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'orders-master-v2';
            
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_load_filter_view',
                    nonce: ordersJetAjax.nonce,
                    view_id: viewId,
                    current_page: currentPage
                },
                success: (response) => {
                    if (response.success && response.data.redirect_url) {
                        // Extract filter parameters from redirect URL
                        const url = new URL(response.data.redirect_url);
                        const params = new URLSearchParams(url.search);
                        const filterParams = {};
                        
                        // Convert URL parameters to filter parameters object
                        for (const [key, value] of params.entries()) {
                            if (value && key !== 'page') {
                                filterParams[key] = value;
                            }
                        }
                        
                        // Update browser URL without reload
                        history.pushState(null, '', response.data.redirect_url);
                        
                        // Refresh content via AJAX instead of page reload
                        if (this.filtersPanel && this.filtersPanel.refreshOrdersContent) {
                            this.filtersPanel.refreshOrdersContent(filterParams);
                            this.showNotification(`View "${response.data.view_name || 'loaded'}" applied successfully`, 'success');
                            // Keep panel open so user can see the result
                        } else {
                            // Fallback to page reload if AJAX not available
                            window.location.href = response.data.redirect_url;
                        }
                    } else {
                        this.showNotification(response.data.message || 'Failed to load view', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('An error occurred while loading the view', 'error');
                }
            });
        }

        /**
         * Rename a saved view
         */
        renameSavedView(viewId, currentName) {
            const newName = prompt(`Rename view "${currentName}":`, currentName);
            if (!newName || newName.trim() === '' || newName.trim() === currentName) {
                return;
            }
            
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_rename_filter_view',
                    nonce: ordersJetAjax.nonce,
                    view_id: viewId,
                    new_name: newName.trim()
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message || 'View renamed successfully', 'success');
                        this.loadSavedViews(); // Refresh the list
                    } else {
                        this.showNotification(response.data.message || 'Failed to rename view', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Rename view error:', error);
                    this.showNotification('An error occurred while renaming the view', 'error');
                }
            });
        }

        /**
         * Delete a saved view
         */
        deleteSavedView(viewId, viewName) {
            if (!confirm(`Are you sure you want to delete the view "${viewName}"?`)) {
                return;
            }
            
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oj_delete_filter_view',
                    nonce: ordersJetAjax.nonce,
                    view_id: viewId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message || 'View deleted successfully', 'success');
                        this.loadSavedViews(); // Refresh the list
                        
                        // Clear selection if deleted view was selected
                        if (this.selectedViewId === viewId) {
                            this.clearSelection();
                        }
                    } else {
                        this.showNotification(response.data.message || 'Failed to delete view', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Delete view error:', error);
                    this.showNotification('An error occurred while deleting the view', 'error');
                }
            });
        }

        /**
         * Save current filter state as a named view
         */
        saveCurrentFilters() {
            // Get current filter parameters
            const currentParams = new URLSearchParams(window.location.search);
            const filterData = {};
            
            // Extract filter parameters
            const filterParams = [
                'filter', 'date_preset', 'date_from', 'date_to', 
                'search', 'order_type', 'kitchen_type', 'kitchen_status',
                'assigned_waiter', 'unassigned_only', 'orderby', 'order',
                'payment_method', 'amount_type', 'amount_value', 'amount_min', 'amount_max'
            ];
            
            filterParams.forEach(param => {
                const value = currentParams.get(param);
                if (value && value !== '') {
                    filterData[param] = value;
                }
            });
            
            // Check if there are any filters to save
            const hasFilters = Object.keys(filterData).length > 0;
            if (!hasFilters) {
                this.showNotification('No filters to save. Please set some filters first.', 'error');
                return;
            }
            
            // Show save view modal
            this.showSaveViewModal(filterData);
        }

        /**
         * Show save view modal for name input
         */
        showSaveViewModal(filterData) {
            // Create modal HTML
            const modalHtml = `
                <div class="oj-save-view-modal">
                    <div class="oj-save-view-modal-content">
                        <h3>💾 Save Filter View</h3>
                        <p>Enter a name for your current filter combination:</p>
                        <input type="text" id="oj-view-name-input" placeholder="e.g., High Value Orders, Today's Food Orders..." maxlength="100">
                        <div class="oj-save-view-modal-actions">
                            <button type="button" class="secondary" id="oj-cancel-save-view">Cancel</button>
                            <button type="button" class="primary" id="oj-confirm-save-view">Save View</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Focus input
            $('#oj-view-name-input').focus();
            
            // Bind events
            $('#oj-cancel-save-view').on('click', () => {
                this.closeSaveViewModal();
            });
            
            $('.oj-save-view-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeSaveViewModal();
                }
            });
            
            $('#oj-confirm-save-view').on('click', () => {
                const viewName = $('#oj-view-name-input').val().trim();
                if (viewName) {
                    this.performSaveView(viewName, filterData);
                } else {
                    $('#oj-view-name-input').focus();
                }
            });
            
            // Handle Enter key
            $('#oj-view-name-input').on('keypress', (e) => {
                if (e.which === 13) { // Enter key
                    $('#oj-confirm-save-view').click();
                }
            });
            
            // Handle Escape key
            $(document).on('keydown.saveViewModal', (e) => {
                if (e.key === 'Escape') {
                    this.closeSaveViewModal();
                }
            });
        }

        /**
         * Close save view modal
         */
        closeSaveViewModal() {
            $('.oj-save-view-modal').remove();
            $(document).off('keydown.saveViewModal');
        }

        /**
         * Perform the actual save view AJAX call
         */
        performSaveView(viewName, filterData) {
            // Show loading state
            $('#oj-confirm-save-view').text('Saving...').prop('disabled', true);
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'oj_save_filter_view',
                nonce: ordersJetAjax.nonce,
                view_name: viewName,
                filter_params: filterData
            };
            
            // Make AJAX call
            $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.closeSaveViewModal();
                        this.showNotification(`View "${viewName}" saved successfully!`, 'success');
                        
                        // Refresh saved views if we're on that tab
                        if ($('.oj-tab-btn.active').data('tab') === 'saved-views') {
                            this.loadSavedViews();
                        }
                    } else {
                        this.showNotification(response.data.message || 'Failed to save view', 'error');
                        $('#oj-confirm-save-view').text('Save View').prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save view error:', error);
                    this.showNotification('An error occurred while saving the view', 'error');
                    $('#oj-confirm-save-view').text('Save View').prop('disabled', false);
                }
            });
        }

        /**
         * Show notification message
         */
        showNotification(message, type = 'info') {
            // Create notification HTML
            const notificationHtml = `
                <div class="oj-notification oj-notification-${type}">
                    <span class="oj-notification-icon">
                        ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
                    </span>
                    <span class="oj-notification-message">${message}</span>
                    <button class="oj-notification-close">✕</button>
                </div>
            `;
            
            // Add to page
            if ($('.oj-notifications-container').length === 0) {
                $('body').append('<div class="oj-notifications-container"></div>');
            }
            
            const $notification = $(notificationHtml);
            $('.oj-notifications-container').append($notification);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 5000);
            
            // Manual close
            $notification.find('.oj-notification-close').on('click', () => {
                $notification.fadeOut(() => $notification.remove());
            });
        }

        /**
         * Get the currently selected view ID
         */
        getSelectedViewId() {
            return this.selectedViewId;
        }

        /**
         * Check if a view is currently selected
         */
        hasSelection() {
            return this.selectedViewId !== null;
        }
    }

    // Export class to global scope
    window.OJSavedViews = OJSavedViews;

})(jQuery);
