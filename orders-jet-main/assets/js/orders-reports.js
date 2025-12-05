/**
 * Orders Reports JavaScript
 * 
 * Handles AJAX interactions, filters, drill-down, and exports
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

(function($) {
    'use strict';

    const OrdersReports = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initializeDatePicker();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Filter controls
            $('#oj-date-preset').on('change', this.handleDatePresetChange.bind(this));
            $('#oj-apply-filters').on('click', this.applyFilters.bind(this));
            $('#oj-reset-filters').on('click', this.resetFilters.bind(this));

            // Tab switching
            $('.oj-tab-btn').on('click', this.switchTab.bind(this));

            // Drill-down
            $(document).on('click', '.oj-drill-down-btn', this.handleDrillDown.bind(this));
            $('#oj-close-drill-down').on('click', this.closeDrillDown.bind(this));

            // Export buttons
            $(document).on('click', '.oj-export-btn', this.handleExport.bind(this));
        },

        /**
         * Initialize date picker
         */
        initializeDatePicker: function() {
            const today = new Date().toISOString().split('T')[0];
            $('#oj-date-from, #oj-date-to').attr('max', today);
        },

        /**
         * Handle date preset change
         */
        handleDatePresetChange: function(e) {
            const preset = $(e.target).val();
            
            if (preset === 'custom') {
                $('.oj-custom-date').slideDown();
            } else {
                $('.oj-custom-date').slideUp();
            }
        },

        /**
         * Apply filters
         */
        applyFilters: function() {
            const filters = this.getFilterValues();
            
            // Show loading
            this.showLoading();

            // Make AJAX request
            $.ajax({
                url: ojReportsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_reports_get_data',
                    nonce: ojReportsData.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.updateReports(response);
                    } else {
                        this.showError(response.message || 'Failed to load reports');
                    }
                },
                error: () => {
                    this.showError('An error occurred while loading reports');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        },

        /**
         * Get current filter values
         */
        getFilterValues: function() {
            const preset = $('#oj-date-preset').val();
            
            const filters = {
                group_by: $('#oj-group-by').val(),
                product_type: $('#oj-product-type').val(),
                order_source: $('#oj-order-source').val(),
                date_preset: preset
            };

            if (preset === 'custom') {
                filters.date_from = $('#oj-date-from').val();
                filters.date_to = $('#oj-date-to').val();
            }

            return filters;
        },

        /**
         * Reset filters to default
         */
        resetFilters: function() {
            $('#oj-date-preset').val('last_7_days');
            $('#oj-group-by').val('day');
            $('#oj-product-type').val('all');
            $('#oj-order-source').val('all');
            $('.oj-custom-date').hide();
            $('#oj-date-from, #oj-date-to').val('');
            
            this.applyFilters();
        },

        /**
         * Update reports with new data
         */
        updateReports: function(response) {
            // Update KPIs
            this.updateKPIs(response.kpis);

            // Update summary table
            this.updateSummaryTable(response.summary_table);

            // Update category table
            this.updateCategoryTable(response.category_table);

            // Update payment breakdown
            this.updatePaymentBreakdown(response.payment_breakdown);

            // Update status breakdown
            this.updateStatusBreakdown(response.status_breakdown);
        },

        /**
         * Update KPI cards
         */
        updateKPIs: function(kpis) {
            $.each(kpis, function(key, kpi) {
                const $card = $('[data-kpi="' + key + '"]');
                if ($card.length) {
                    $card.find('.oj-kpi-value').html(kpi.value);
                }
            });
        },

        /**
         * Update summary table
         */
        updateSummaryTable: function(data) {
            const $tbody = $('#oj-summary-table tbody');
            $tbody.empty();

            if (!data || data.length === 0) {
                $tbody.append('<tr><td colspan="6" class="oj-no-data">No data available for selected filters</td></tr>');
                return;
            }

            $.each(data, function(i, row) {
                const $row = $('<tr>')
                    .addClass('oj-clickable-row')
                    .attr('data-period', row.period);

                $row.append('<td><strong>' + row.period_label + '</strong></td>');
                $row.append('<td>' + row.total_orders + '</td>');
                $row.append('<td class="oj-status-completed">' + row.completed_orders + '</td>');
                $row.append('<td class="oj-status-cancelled">' + row.cancelled_orders + '</td>');
                $row.append('<td><strong>' + row.revenue_formatted + '</strong></td>');
                $row.append('<td><button type="button" class="button button-small oj-drill-down-btn" data-date="' + row.period + '">View Details</button></td>');

                $tbody.append($row);
            });
        },

        /**
         * Update category table
         */
        updateCategoryTable: function(data) {
            const $tbody = $('#oj-category-table tbody');
            $tbody.empty();

            if (!data || data.length === 0) {
                $tbody.append('<tr><td colspan="3" class="oj-no-data">No data available for selected filters</td></tr>');
                return;
            }

            $.each(data, function(i, row) {
                const $row = $('<tr>');
                $row.append('<td><strong>' + row.category_name + '</strong></td>');
                $row.append('<td>' + row.order_count + '</td>');
                $row.append('<td><strong>' + row.revenue_formatted + '</strong></td>');
                $tbody.append($row);
            });
        },

        /**
         * Update payment breakdown
         */
        updatePaymentBreakdown: function(data) {
            // Implementation for payment breakdown update
            // This would update the payment cards dynamically
        },

        /**
         * Update status breakdown
         */
        updateStatusBreakdown: function(data) {
            // Implementation for status breakdown update
            // This would update the status cards dynamically
        },

        /**
         * Switch between tabs
         */
        switchTab: function(e) {
            const $btn = $(e.currentTarget);
            const tab = $btn.data('tab');

            // Update active states
            $('.oj-tab-btn').removeClass('active');
            $btn.addClass('active');

            $('.oj-tab-content').removeClass('active');
            $('[data-content="' + tab + '"]').addClass('active');
        },

        /**
         * Handle drill-down button click
         */
        handleDrillDown: function(e) {
            e.preventDefault();
            const date = $(e.currentTarget).data('date');
            
            this.showLoading();

            const filters = this.getFilterValues();

            $.ajax({
                url: ojReportsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_reports_drill_down',
                    nonce: ojReportsData.nonce,
                    date: date,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.showDrillDown(response);
                    } else {
                        this.showError(response.message || 'Failed to load drill-down data');
                    }
                },
                error: () => {
                    this.showError('An error occurred while loading drill-down data');
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        },

        /**
         * Show drill-down section
         */
        showDrillDown: function(data) {
            // Update title
            $('#oj-drill-down-title').text('Details for ' + data.date);

            // Update KPIs in drill-down
            let html = '<div class="oj-kpi-grid">';
            $.each(data.kpis, function(key, kpi) {
                html += '<div class="oj-kpi-card" style="border-left-color: ' + kpi.color + '">';
                html += '<div class="oj-kpi-icon">' + kpi.icon + '</div>';
                html += '<div class="oj-kpi-content">';
                html += '<div class="oj-kpi-label">' + kpi.label + '</div>';
                html += '<div class="oj-kpi-value">' + kpi.value + '</div>';
                html += '</div></div>';
            });
            html += '</div>';

            // Add orders table
            html += '<h3>Orders List</h3>';
            html += '<table class="oj-report-table"><thead><tr>';
            html += '<th>Order #</th><th>Customer</th><th>Status</th><th>Total</th><th>Payment</th><th>Date</th>';
            html += '</tr></thead><tbody>';

            if (data.orders && data.orders.length > 0) {
                $.each(data.orders, function(i, order) {
                    html += '<tr>';
                    html += '<td><a href="post.php?post=' + order.id + '&action=edit">#' + order.order_number + '</a></td>';
                    html += '<td>' + order.customer_name + '</td>';
                    html += '<td><span class="oj-status-badge oj-status-' + order.status + '">' + order.status + '</span></td>';
                    html += '<td><strong>' + order.total + '</strong></td>';
                    html += '<td>' + order.payment_method + '</td>';
                    html += '<td>' + order.date_created + '</td>';
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="6" class="oj-no-data">No orders found</td></tr>';
            }

            html += '</tbody></table>';

            $('#oj-drill-down-content').html(html);
            $('#oj-drill-down-section').slideDown();

            // Scroll to drill-down section
            $('html, body').animate({
                scrollTop: $('#oj-drill-down-section').offset().top - 100
            }, 500);
        },

        /**
         * Close drill-down section
         */
        closeDrillDown: function() {
            $('#oj-drill-down-section').slideUp();
        },

        /**
         * Handle export button click
         */
        handleExport: function(e) {
            const $btn = $(e.currentTarget);
            const exportType = $btn.data('type');
            const reportType = $btn.data('report');

            // Disable button
            $btn.prop('disabled', true).text('Exporting...');

            const filters = this.getFilterValues();

            $.ajax({
                url: ojReportsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_reports_export',
                    nonce: ojReportsData.nonce,
                    export_type: exportType,
                    report_type: reportType,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        // Trigger download
                        window.location.href = response.data.url;
                        this.showSuccess('Export completed! Download started.');
                    } else {
                        this.showError(response.data.message || 'Export failed');
                    }
                },
                error: () => {
                    this.showError('An error occurred during export');
                },
                complete: () => {
                    // Re-enable button
                    $btn.prop('disabled', false);
                    
                    // Restore button text
                    const icon = exportType === 'excel' ? '📊' : (exportType === 'csv' ? '📄' : '📕');
                    const text = exportType === 'excel' ? 'Export Excel' : (exportType === 'csv' ? 'Export CSV' : 'Export PDF');
                    $btn.text(icon + ' ' + text);
                }
            });
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#oj-loading-overlay').fadeIn();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#oj-loading-overlay').fadeOut();
        },

        /**
         * Get status badge styling based on order status
         */
        getStatusBadge: function(status) {
            const statusMap = {
                'completed': { bg: '#d1fae5', color: '#065f46', icon: '✅', label: 'Completed' },
                'processing': { bg: '#fef3c7', color: '#92400e', icon: '👨‍🍳', label: 'Processing' },
                'pending': { bg: '#dbeafe', color: '#1e40af', icon: '⏳', label: 'Pending' },
                'on-hold': { bg: '#fce7f3', color: '#831843', icon: '⏸️', label: 'On Hold' },
                'cancelled': { bg: '#fee2e2', color: '#991b1b', icon: '❌', label: 'Cancelled' },
                'refunded': { bg: '#e0e7ff', color: '#3730a3', icon: '💰', label: 'Refunded' },
                'failed': { bg: '#fecaca', color: '#7f1d1d', icon: '⚠️', label: 'Failed' }
            };
            
            return statusMap[status] || { bg: '#e5e7eb', color: '#374151', icon: '📋', label: status };
        },

        /**
         * Show error message
         */
        showError: function(message) {
            alert('Error: ' + message);
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            alert(message);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        OrdersReports.init();
    });

})(jQuery);
