/**
 * Table Overview JavaScript
 * Handles filtering, searching, and real-time updates
 */



(function($) {
    'use strict';
    
    const TableOverview = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.applyFilters();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Filter changes
            $('#oj-filter-area, #oj-filter-capacity, #oj-filter-status').on('change', () => {
                this.applyFilters();
            });
            
            // Search input
            $('#oj-search-tables').on('input', () => {
                this.applyFilters();
            });
            
            // Reset filters
            $('#oj-reset-filters').on('click', () => {
                this.resetFilters();
            });
        },
        
        /**
         * Apply filters to table cards
         */
        applyFilters: function() {
            const areaFilter = $('#oj-filter-area').val();
            const capacityFilter = $('#oj-filter-capacity').val();
            const statusFilter = $('#oj-filter-status').val();
            const searchTerm = $('#oj-search-tables').val().toLowerCase();
            
            let visibleCount = 0;
            
            $('.oj-table-card').each(function() {
                const $card = $(this);
                const cardArea = $card.data('area') || '';
                const cardCapacity = parseInt($card.data('capacity')) || 0;
                const cardStatus = $card.data('status') || '';
                const cardNumber = $card.data('table-number') || '';
                const cardTitle = $card.find('.oj-table-number').text().toLowerCase();
                
                // Area filter
                let areaMatch = true;
                if (areaFilter) {
                    areaMatch = cardArea.toLowerCase() === areaFilter.toLowerCase();
                }
                
                // Capacity filter
                let capacityMatch = true;
                if (capacityFilter) {
                    const [min, max] = capacityFilter.includes('+') 
                        ? [parseInt(capacityFilter.replace('+', '')), Infinity]
                        : capacityFilter.split('-').map(Number);
                    capacityMatch = cardCapacity >= min && cardCapacity <= max;
                }
                
                // Status filter
                let statusMatch = true;
                if (statusFilter) {
                    statusMatch = cardStatus === statusFilter;
                }
                
                // Search filter
                let searchMatch = true;
                if (searchTerm) {
                    searchMatch = cardNumber.toLowerCase().includes(searchTerm) || 
                                  cardTitle.includes(searchTerm);
                }
                
                // Show/hide card
                if (areaMatch && capacityMatch && statusMatch && searchMatch) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });
            
            // Update summary if needed
            this.updateVisibleCount(visibleCount);
        },
        
        /**
         * Reset all filters
         */
        resetFilters: function() {
            $('#oj-filter-area').val('');
            $('#oj-filter-capacity').val('');
            $('#oj-filter-status').val('');
            $('#oj-search-tables').val('');
            this.applyFilters();
        },
        
        /**
         * Update visible count
         */
        updateVisibleCount: function(count) {
            // Could update a counter if needed
            console.log('Visible tables:', count);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        TableOverview.init();
    });
    
})(jQuery);

