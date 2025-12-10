/**
 * Table Sessions & Reports JavaScript
 * Handles session management, modals, and interactions
 */

(function($) {
    'use strict';
    
    const TableSessions = {
        

        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },


        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab switching
            $('.oj-tab-button').on('click', (e) => {
                const tab = $(e.target).data('tab');
                this.switchTab(tab);
            });
            
            // Open session modal
            $('.oj-open-session-btn').on('click', () => {
                this.openModal('#oj-open-session-modal');
            });
            
            // Close modal
            $('.oj-modal-close, .oj-modal-cancel').on('click', () => {
                this.closeModal();
            });
            
            // Open session form
            $('#oj-open-session-form').on('submit', (e) => {
                e.preventDefault();
                this.handleOpenSession();
            });
            
            // Waiter assignment
            $('.oj-waiter-select').on('change', function() {
                const tableNumber = $(this).data('table-number');
                const waiterId = $(this).val();
                TableSessions.assignWaiter(tableNumber, waiterId);
            });
            
            // Link orders
            $('.oj-link-orders-btn').on('click', function() {
                const tableNumber = $(this).data('table-number');
                TableSessions.linkOrders(tableNumber);
            });
            
            // Move table
            $('.oj-move-table-btn').on('click', function() {
                const tableNumber = $(this).data('table-number');
                TableSessions.moveTable(tableNumber);
            });
            
            // Merge tables
            $('.oj-merge-table-btn').on('click', function() {
                const tableNumber = $(this).data('table-number');
                TableSessions.mergeTables(tableNumber);
            });
            
            // Close session
            $('.oj-close-session-btn').on('click', function() {
                const tableNumber = $(this).data('table-number');
                TableSessions.closeSession(tableNumber);
            });
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(tab) {
            $('.oj-tab-button').removeClass('active');
            $('.oj-tab-content').removeClass('active');
            
            $(`.oj-tab-button[data-tab="${tab}"]`).addClass('active');
            $(`#oj-tab-${tab}`).addClass('active');
        },
        
        /**
         * Open modal
         */
        openModal: function(modalId) {
            $(modalId).fadeIn(200);
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('.oj-modal').fadeOut(200);
        },
        
        /**
         * Handle open session form submission
         */
        handleOpenSession: function() {
            const formData = {
                action: 'oj_open_table_session',
                table_number: $('#oj-open-session-form [name="table_number"]').val(),
                waiter_id: $('#oj-open-session-form [name="waiter_id"]').val(),
                nonce: ojTableSessions.nonce
            };
            
            if (!formData.table_number) {
                alert('Please select a table.');
                return;
            }
            
            $.ajax({
                url: ojTableSessions.ajax_url,
                method: 'POST',
                data: formData,
                beforeSend: function() {
                    // Show loading
                },
                success: function(response) {
                    if (response.success) {
                        TableSessions.closeModal();
                        location.reload(); // Reload to show new session
                    } else {
                        alert(response.data.message || 'Error opening session.');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                }
            });
        },
        
        /**
         * Assign waiter to session
         */
        assignWaiter: function(tableNumber, waiterId) {
            $.ajax({
                url: ojTableSessions.ajax_url,
                method: 'POST',
                data: {
                    action: 'oj_assign_table',
                    table_number: tableNumber,
                    waiter_id: waiterId,
                    nonce: ojTableSessions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        console.log('Waiter assigned successfully');
                    } else {
                        alert(response.data.message || 'Error assigning waiter.');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                }
            });
        },
        
        /**
         * Link orders to session
         */
        linkOrders: function(tableNumber) {
            // Redirect to orders page filtered by table
            window.location.href = `admin.php?page=orders-express&table=${encodeURIComponent(tableNumber)}`;
        },
        
        /**
         * Move table (change assignment)
         */
        moveTable: function(tableNumber) {
            const newTable = prompt('Enter new table number to move to:');
            if (newTable) {
                $.ajax({
                    url: ojTableSessions.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'oj_move_table',
                        table_number: tableNumber,
                        new_table_number: newTable,
                        nonce: ojTableSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error moving table.');
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                    }
                });
            }
        },
        
        /**
         * Merge tables
         */
        mergeTables: function(tableNumber) {
            const targetTable = prompt('Enter table number to merge with:');
            if (targetTable) {
                $.ajax({
                    url: ojTableSessions.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'oj_merge_tables',
                        source_table: tableNumber,
                        target_table: targetTable,
                        nonce: ojTableSessions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error merging tables.');
                        }
                    },
                    error: function() {
                        alert('Connection error. Please try again.');
                    }
                });
            }
        },
        
        /**
         * Close session
         */
        closeSession: function(tableNumber) {
            if (!confirm('Are you sure you want to close this session?')) {
                return;
            }
            
            $.ajax({
                url: ojTableSessions.ajax_url,
                method: 'POST',
                data: {
                    action: 'oj_close_table_session',
                    table_number: tableNumber,
                    nonce: ojTableSessions.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error closing session.');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        TableSessions.init();
    });
    
})(jQuery);

