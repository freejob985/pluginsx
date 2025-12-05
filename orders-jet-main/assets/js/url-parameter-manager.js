/**
 * URL Parameter Manager
 * Centralized URL parameter handling for Orders Master
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * URL Parameter Manager Class
     */
    class OJURLParameterManager {
        constructor() {
            // Get current page from URL or default to orders-master-v2
            const urlParams = new URLSearchParams(window.location.search);
            this.basePage = urlParams.get('page') || 'orders-master-v2';
        }

        /**
         * Get current URL parameters as object
         */
        getCurrentParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const params = {};
            
            for (const [key, value] of urlParams.entries()) {
                if (value && key !== 'page') {
                    params[key] = value;
                }
            }
            
            return params;
        }

        /**
         * Update URL parameter and return new URL
         */
        updateParam(paramName, paramValue) {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            // Update the specific parameter
            if (paramValue && paramValue !== '') {
                params.set(paramName, paramValue);
            } else {
                params.delete(paramName);
            }
            
            params.set('page', this.basePage);
            params.delete('paged'); // Reset pagination
            
            return currentUrl.pathname + '?' + params.toString();
        }

        /**
         * Update multiple parameters and return new URL
         */
        updateMultipleParams(paramsObject) {
            const currentUrl = new URL(window.location.href);
            const params = new URLSearchParams(currentUrl.search);
            
            // Update all provided parameters
            Object.keys(paramsObject).forEach(key => {
                const value = paramsObject[key];
                if (value && value !== '') {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });
            
            params.set('page', this.basePage);
            params.delete('paged'); // Reset pagination
            
            return currentUrl.pathname + '?' + params.toString();
        }

        /**
         * Build URL from filter parameters object
         */
        buildFilterURL(filterParams) {
            const baseUrl = window.location.href.split('?')[0];
            const urlParams = new URLSearchParams();
            
            // Always include the page parameter
            urlParams.set('page', this.basePage);
            
            // Add all filter parameters
            Object.keys(filterParams).forEach(key => {
                const value = filterParams[key];
                if (value && value !== '') {
                    urlParams.set(key, value);
                }
            });
            
            // Preserve debug parameter if it exists
            if (window.location.search.includes('debug=1')) {
                urlParams.set('debug', '1');
            }
            
            return baseUrl + '?' + urlParams.toString();
        }

        /**
         * Update browser URL without reload
         */
        updateBrowserURL(newUrl) {
            history.pushState(null, '', newUrl);
        }

        /**
         * Convert URL parameters to filter parameters object
         */
        urlToFilterParams(url) {
            const params = new URLSearchParams(url.split('?')[1]);
            const filterParams = {};
            
            for (const [key, value] of params.entries()) {
                if (value && key !== 'page') {
                    filterParams[key] = value;
                }
            }
            
            return filterParams;
        }
    }

    // Make available globally
    window.OJURLParameterManager = OJURLParameterManager;

})(jQuery);
