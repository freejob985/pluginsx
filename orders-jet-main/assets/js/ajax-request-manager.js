/**
 * AJAX Request Manager
 * Centralized AJAX handling for Orders Master
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * AJAX Request Manager Class
     */
    class OJAjaxRequestManager {
        constructor() {
            this.isLoading = false;
            this.loadingCallback = null;
            this.successCallback = null;
            this.errorCallback = null;
        }

        /**
         * Set callback functions
         */
        setCallbacks(callbacks) {
            this.loadingCallback = callbacks.onLoading || null;
            this.successCallback = callbacks.onSuccess || null;
            this.errorCallback = callbacks.onError || null;
        }

        /**
         * Refresh orders content via AJAX
         */
        refreshOrdersContent(filterParams) {
            if (this.isLoading) {
                return; // Prevent multiple simultaneous requests
            }

            this.isLoading = true;
            
            // Show loading state
            if (this.loadingCallback) {
                this.loadingCallback(true);
            }
            
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
                        if (this.successCallback) {
                            this.successCallback(response.data);
                        }
                    } else {
                        if (this.errorCallback) {
                            this.errorCallback(response.data.message || 'Failed to refresh content');
                        }
                    }
                },
                error: (xhr, status, error) => {
                    if (this.errorCallback) {
                        this.errorCallback('An error occurred while refreshing the page. Please try again.');
                    }
                },
                complete: () => {
                    this.isLoading = false;
                    if (this.loadingCallback) {
                        this.loadingCallback(false);
                    }
                }
            });
        }

        /**
         * Generic AJAX request method
         */
        makeRequest(action, data, callbacks = {}) {
            const ajaxData = {
                action: action,
                nonce: ordersJetAjax.nonce,
                ...data
            };

            return $.ajax({
                url: ordersJetAjax.ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: callbacks.onSuccess || function() {},
                error: callbacks.onError || function() {},
                complete: callbacks.onComplete || function() {}
            });
        }

        /**
         * Check if currently loading
         */
        isCurrentlyLoading() {
            return this.isLoading;
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

    // Make available globally
    window.OJAjaxRequestManager = OJAjaxRequestManager;

})(jQuery);
