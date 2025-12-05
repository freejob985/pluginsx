/**
 * Orders Jet - Overview Dashboard JavaScript
 * Handles auto-refresh, walkthrough, and interactive features
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Orders Overview Controller
     */
    const OrdersOverview = {
        
        /**
         * Configuration
         */
        config: {
            refreshInterval: 60000, // Refresh every 60 seconds
            animationDuration: 300,
            walkthroughSteps: 3,
            todoStorageKey: 'oj_overview_todos'
        },
        
        /**
         * State
         */
        state: {
            currentWalkthroughStep: 1,
            isRefreshing: false,
            refreshTimer: null
        },
        
        /**
         * Initialize
         */
        init: function() {
            console.log('[Orders Overview] Initializing...');
            
            // Check if we're on the overview page
            if (!$('.oj-overview').length) {
                return;
            }
            
            // Load saved todo state
            this.loadTodoState();
            
            // Bind events
            this.bindEvents();
            
            // Start auto-refresh
            this.startAutoRefresh();
            
            console.log('[Orders Overview] Initialized successfully');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Walkthrough events
            $('#oj-start-walkthrough').on('click', this.startWalkthrough.bind(this));
            $('#oj-walkthrough-next').on('click', this.nextWalkthroughStep.bind(this));
            $('#oj-walkthrough-prev').on('click', this.prevWalkthroughStep.bind(this));
            $('.oj-modal-close, .oj-modal-overlay').on('click', this.closeWalkthrough.bind(this));
            
            // Todo events
            $('.oj-todo-checkbox').on('change', this.handleTodoChange.bind(this));
            $('#oj-reset-todos').on('click', this.resetTodos.bind(this));
            
            // Card click events (optional - for accessibility)
            $('.oj-summary-card').on('click', this.handleCardClick.bind(this));
            
            // Prevent modal close when clicking inside modal content
            $('.oj-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },
        
        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            console.log('[Orders Overview] Starting auto-refresh...');
            
            // Initial refresh after 5 seconds
            setTimeout(this.refreshData.bind(this), 5000);
            
            // Set interval for periodic refresh
            this.state.refreshTimer = setInterval(
                this.refreshData.bind(this), 
                this.config.refreshInterval
            );
        },
        
        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
            }
        },
        
        /**
         * Refresh data via AJAX
         */
        refreshData: function() {
            // Prevent concurrent refreshes
            if (this.state.isRefreshing) {
                return;
            }
            
            console.log('[Orders Overview] Refreshing data...');
            this.state.isRefreshing = true;
            
            const self = this;
            
            $.ajax({
                url: ojOverviewData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_get_overview_data',
                    nonce: ojOverviewData.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updateUI(response.data);
                        console.log('[Orders Overview] Data refreshed successfully');
                    } else {
                        console.error('[Orders Overview] Refresh failed:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Orders Overview] AJAX error:', error);
                },
                complete: function() {
                    self.state.isRefreshing = false;
                }
            });
        },
        
        /**
         * Update UI with new data
         */
        updateUI: function(data) {
            // Update summary cards
            this.updateCard('today-count', data.today.count);
            this.updateCard('in-progress-count', data.in_progress.count);
            this.updateCard('completed-count', data.completed.count);
            this.updateCard('cancelled-count', data.cancelled.count);
            this.updateCard('unfulfilled-count', data.unfulfilled.count);
            this.updateCard('ready-count', data.ready.count);
            
            // Update requirements
            this.updateCard('refund-requests-count', data.refund_requests.count);
            this.updateCard('unfulfilled-requirements-count', data.unfulfilled.count);
            
            // Update quick stats with proper formatting
            if (data.quick_stats) {
                // Format avg order value as currency (will be displayed properly in template)
                const avgValue = parseFloat(data.quick_stats.avg_order_value);
                if (!isNaN(avgValue) && avgValue > 0) {
                    this.updateCard('avg-order-value', avgValue.toFixed(2));
                }
                
                this.updateCard('weekly-orders', data.quick_stats.weekly_orders);
                
                // Format completion rate with % symbol
                const compRate = parseFloat(data.quick_stats.completion_rate);
                if (!isNaN(compRate)) {
                    this.updateCard('completion-rate', compRate.toFixed(1));
                }
            }
            
            // Update timestamp
            if (data.timestamp) {
                $('#oj-last-update-time').text(data.timestamp);
            }
            
            // Update requirement cards styling based on counts
            this.updateRequirementCards(data);
        },
        
        /**
         * Update individual card value
         */
        updateCard: function(valueKey, newValue) {
            const $element = $('[data-value="' + valueKey + '"]');
            if ($element.length) {
                // Get current text without whitespace
                const currentText = $element.text().trim().replace(/[^0-9.]/g, '');
                const newText = String(newValue).replace(/[^0-9.]/g, '');
                
                // Only update if value changed
                if (currentText !== newText) {
                    const self = this;
                    // Animate the change
                    $element.fadeOut(this.config.animationDuration / 2, function() {
                        // For currency fields, format properly
                        if (valueKey === 'avg-order-value') {
                            const formatted = parseFloat(newValue).toFixed(2);
                            $element.text(formatted);
                        } else if (valueKey === 'completion-rate') {
                            $element.text(newValue);
                        } else {
                            $element.text(newValue);
                        }
                        $element.fadeIn(self.config.animationDuration / 2);
                    });
                }
            }
        },
        
        /**
         * Update requirement cards styling
         */
        updateRequirementCards: function(data) {
            // Refund requests card
            const $refundCard = $('.oj-requirement-card').first();
            if (data.refund_requests.count > 0) {
                $refundCard.removeClass('oj-no-items').addClass('oj-has-items');
            } else {
                $refundCard.removeClass('oj-has-items').addClass('oj-no-items');
            }
            
            // Unfulfilled orders card
            const $unfulfilledCard = $('.oj-requirement-card').last();
            if (data.unfulfilled.count > 0) {
                $unfulfilledCard.removeClass('oj-no-items').addClass('oj-has-items');
            } else {
                $unfulfilledCard.removeClass('oj-has-items').addClass('oj-no-items');
            }
        },
        
        /**
         * Format currency (basic formatting)
         */
        formatCurrency: function(amount) {
            // This is a simple implementation - WooCommerce handles actual currency display
            return parseFloat(amount).toFixed(2);
        },
        
        /**
         * Handle card click (navigate to link)
         */
        handleCardClick: function(e) {
            // Don't trigger if clicking directly on the link
            if ($(e.target).is('a') || $(e.target).closest('a').length) {
                return;
            }
            
            const $card = $(e.currentTarget);
            const $link = $card.find('.oj-card-link');
            
            if ($link.length) {
                window.location.href = $link.attr('href');
            }
        },
        
        /**
         * Start walkthrough
         */
        startWalkthrough: function(e) {
            e.preventDefault();
            console.log('[Orders Overview] Starting walkthrough...');
            
            this.state.currentWalkthroughStep = 1;
            $('#oj-walkthrough-modal').fadeIn(this.config.animationDuration);
            this.updateWalkthroughUI();
        },
        
        /**
         * Next walkthrough step
         */
        nextWalkthroughStep: function(e) {
            e.preventDefault();
            
            if (this.state.currentWalkthroughStep < this.config.walkthroughSteps) {
                this.state.currentWalkthroughStep++;
                this.updateWalkthroughUI();
            } else {
                this.closeWalkthrough();
            }
        },
        
        /**
         * Previous walkthrough step
         */
        prevWalkthroughStep: function(e) {
            e.preventDefault();
            
            if (this.state.currentWalkthroughStep > 1) {
                this.state.currentWalkthroughStep--;
                this.updateWalkthroughUI();
            }
        },
        
        /**
         * Close walkthrough
         */
        closeWalkthrough: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            $('#oj-walkthrough-modal').fadeOut(this.config.animationDuration);
            this.state.currentWalkthroughStep = 1;
        },
        
        /**
         * Update walkthrough UI
         */
        updateWalkthroughUI: function() {
            const currentStep = this.state.currentWalkthroughStep;
            
            // Hide all steps
            $('.oj-walkthrough-step').hide();
            
            // Show current step
            $('.oj-walkthrough-step[data-step="' + currentStep + '"]').fadeIn(this.config.animationDuration);
            
            // Update buttons
            if (currentStep === 1) {
                $('#oj-walkthrough-prev').hide();
            } else {
                $('#oj-walkthrough-prev').show();
            }
            
            if (currentStep === this.config.walkthroughSteps) {
                $('#oj-walkthrough-next').text(ojOverviewData.i18n.finish || 'Finish');
            } else {
                $('#oj-walkthrough-next').text(ojOverviewData.i18n.next || 'Next');
            }
        },
        
        /**
         * Handle todo checkbox change
         */
        handleTodoChange: function(e) {
            this.saveTodoState();
        },
        
        /**
         * Reset todos
         */
        resetTodos: function(e) {
            e.preventDefault();
            
            $('.oj-todo-checkbox').prop('checked', false);
            this.saveTodoState();
        },
        
        /**
         * Save todo state to localStorage
         */
        saveTodoState: function() {
            const todos = [];
            
            $('.oj-todo-checkbox').each(function() {
                todos.push({
                    id: $(this).attr('id'),
                    checked: $(this).is(':checked')
                });
            });
            
            try {
                localStorage.setItem(this.config.todoStorageKey, JSON.stringify(todos));
            } catch (e) {
                console.warn('[Orders Overview] Could not save todo state:', e);
            }
        },
        
        /**
         * Load todo state from localStorage
         */
        loadTodoState: function() {
            try {
                const savedState = localStorage.getItem(this.config.todoStorageKey);
                
                if (savedState) {
                    const todos = JSON.parse(savedState);
                    
                    todos.forEach(function(todo) {
                        $('#' + todo.id).prop('checked', todo.checked);
                    });
                }
            } catch (e) {
                console.warn('[Orders Overview] Could not load todo state:', e);
            }
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        OrdersOverview.init();
    });
    
    /**
     * Stop auto-refresh when leaving page
     */
    $(window).on('beforeunload', function() {
        OrdersOverview.stopAutoRefresh();
    });
    
    // Expose to global scope for debugging
    window.OrdersOverview = OrdersOverview;

})(jQuery);

