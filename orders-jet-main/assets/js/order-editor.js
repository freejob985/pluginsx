/**
 * Order Editor Module
 * 
 * Handles all order editing operations:
 * - Add Notes
 * - Customer Info
 * - Refund
 * - Discount
 * - Add Items
 * - Order Actions
 * 
 * @package Orders_Jet
 * @version 2.0
 */

jQuery(document).ready(function($) {
    'use strict';
    // Store current order data
    let currentOrderData = null;
    
    // ========================================================================
    // LISTEN TO SINGLE ORDER SELECTION
    // ========================================================================
    
    /**
     * Listen for single order selection event from bulk actions module
     */
    $(document).on('oj-single-order-selected', function(event, orderData) {
        currentOrderData = orderData;
    });
    
    // ========================================================================
    // ACTION BUTTON CLICK HANDLERS
    // ========================================================================
    
    /**
     * Route action button clicks to appropriate handlers
     */
    $(document).on('click', '[data-action]', function(e) {
        e.preventDefault();
        
        const action = $(this).data('action');
        
        // Only handle order editor actions (not bulk actions)
        const editorActions = [
            'add_note',
            'add_items', 
            'add_discount',
            'refund',
            'mark_pending',
            'mark_on_hold',
            'mark_processing',
            'customer_info',
            'order_content',
            'order_actions',
            'coupons'
        ];
        
        if (!editorActions.includes(action)) {
            return; // Let bulk actions handle it
        }
        // Close dropdown if open
        $('.oj-gear-dropdown').removeClass('open');
        
        // Route to appropriate handler
        switch(action) {
            case 'add_note':
                openAddNoteModal();
                break;
            case 'customer_info':
                openCustomerInfoModal();
                break;
            case 'refund':
                openRefundModal();
                break;
            case 'add_discount':
                openDiscountModal();
                break;
            case 'coupons':
                openCouponModal();
                break;
            case 'add_items':
                openAddItemsModal();
                break;
            case 'mark_pending':
            case 'mark_on_hold':
            case 'mark_processing':
                handleStatusChange(action);
                break;
            case 'order_content':
                openOrderContentModal();
                break;
            case 'order_actions':
                openOrderActionsModal();
                break;
            default:
        }
    });
    
    // ========================================================================
    // MODAL SYSTEM - FOUNDATION
    // ========================================================================
    
    /**
     * Open modal with title, content, and buttons
     */
    function openModal(title, bodyContent, buttons) {
        // Set title
        $('.oj-modal-title').text(title);
        
        // Set body content
        $('.oj-modal-body').html(bodyContent);
        
        // Set footer buttons
        let buttonsHtml = '';
        buttons.forEach(function(btn) {
            let btnClass = 'oj-modal-btn oj-modal-btn-' + btn.style;
            // Add custom class if provided
            if (btn.class) {
                btnClass += ' ' + btn.class;
            }
            const disabled = btn.disabled ? 'disabled' : '';
            const btnId = btn.id ? `id="${btn.id}"` : '';
            buttonsHtml += `<button type="button" class="${btnClass}" ${btnId} data-modal-action="${btn.action}" ${disabled}>${btn.text}</button>`;
        });
        $('.oj-modal-footer').html(buttonsHtml);
        
        // Show modal with animation
        $('body').addClass('oj-modal-open');
        $('.oj-modal-overlay').show();
        
        // Trigger animation
        setTimeout(function() {
            $('.oj-modal-overlay').addClass('show');
        }, 10);
    }
    
    /**
     * Close modal with animation
     */
    function closeModal() {
        $('.oj-modal-overlay').removeClass('show');
        
        setTimeout(function() {
            $('.oj-modal-overlay').hide();
            $('body').removeClass('oj-modal-open');
            
            // Clear content
            $('.oj-modal-title').text('');
            $('.oj-modal-body').html('');
            $('.oj-modal-footer').html('');
        }, 300);
    }
    
    /**
     * Close modal when clicking X button
     */
    $(document).on('click', '.oj-modal-close', function() {
        closeModal();
    });
    
    /**
     * Close modal when clicking outside (on overlay)
     */
    $(document).on('click', '.oj-modal-overlay', function(e) {
        if ($(e.target).hasClass('oj-modal-overlay')) {
            closeModal();
        }
    });
    
    /**
     * Close modal on Escape key
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('.oj-modal-overlay').hasClass('show')) {
            closeModal();
        }
    });
    
    /**
     * Handle modal button clicks
     */
    $(document).on('click', '[data-modal-action]', function() {
        const action = $(this).data('modal-action');
        
        if (action === 'cancel') {
            closeModal();
        } else {
            // Route to specific action handler
            handleModalAction(action);
        }
    });
    
    // ========================================================================
    // PHASE 2: ADD NOTE MODAL
    // ========================================================================
    
    /**
     * Build Addresses Tab HTML
     * Phase 8 - Step 3
     */
    function buildAddressesTab(data) {
        const shipping = data.shipping || {};
        const billing = data.billing || {};
        const sameAsBilling = data.same_as_billing || false;
        
        return `
            <!-- Shipping Address (Default Open) -->
            <div class="oj-address-section">
                <button type="button" class="oj-address-header active" data-address="shipping">
                    <span class="oj-address-icon">🚚</span>
                    <span class="oj-address-title">Shipping Address</span>
                    <span class="oj-address-arrow">▼</span>
                </button>
                <div class="oj-address-content show" data-address-content="shipping">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="shipping-building">Building</label>
                            <input 
                                type="text" 
                                id="shipping-building" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.address_1 ? shipping.address_1.split(',')[0].replace('Building', '').trim() : '')}"
                                placeholder="Building 5"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="shipping-floor">Floor</label>
                            <input 
                                type="text" 
                                id="shipping-floor" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.address_1 && shipping.address_1.split(',')[1] ? shipping.address_1.split(',')[1].replace('Floor', '').trim() : '')}"
                                placeholder="3"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="shipping-apartment">Apartment</label>
                            <input 
                                type="text" 
                                id="shipping-apartment" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.address_1 && shipping.address_1.split(',')[2] ? shipping.address_1.split(',')[2].replace('Apt', '').trim() : '')}"
                                placeholder="12"
                            />
                        </div>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="shipping-address-2">Street Address</label>
                        <input 
                            type="text" 
                            id="shipping-address-2" 
                            class="oj-form-input" 
                            value="${escapeHtml(shipping.address_2 || '')}"
                            placeholder="123 Main Street, District Name"
                        />
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="shipping-city">City</label>
                            <input 
                                type="text" 
                                id="shipping-city" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.city || '')}"
                                placeholder="Cairo"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="shipping-state">State</label>
                            <input 
                                type="text" 
                                id="shipping-state" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.state || '')}"
                                placeholder="Cairo Governorate"
                            />
                        </div>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="shipping-country">Country</label>
                        <input 
                            type="text" 
                            id="shipping-country" 
                            class="oj-form-input" 
                            value="${escapeHtml(shipping.country || 'EG')}"
                            placeholder="EG"
                        />
                        <span class="oj-form-help">Country code (e.g., EG, SA, AE)</span>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="shipping-maps-link">📍 Delivery Location (Google Maps)</label>
                        <div style="display: flex; gap: 8px;">
                            <input 
                                type="url" 
                                id="shipping-maps-link" 
                                class="oj-form-input" 
                                value="${escapeHtml(shipping.google_maps_link || '')}"
                                placeholder="https://maps.app.goo.gl/xxxxx"
                                style="flex: 1;"
                            />
                            ${shipping.google_maps_link ? `<a href="${escapeHtml(shipping.google_maps_link)}" target="_blank" class="oj-map-btn" title="Open in Maps">📍</a>` : ''}
                        </div>
                        <span class="oj-form-help">Paste Google Maps share link for delivery</span>
                    </div>
                    
                    <div class="oj-form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="same-as-billing"
                                ${sameAsBilling ? 'checked' : ''}
                            />
                            <span>Same as billing address</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Billing Address (Default Closed) -->
            <div class="oj-address-section">
                <button type="button" class="oj-address-header" data-address="billing">
                    <span class="oj-address-icon">💳</span>
                    <span class="oj-address-title">Billing Address</span>
                    <span class="oj-address-arrow">▼</span>
                </button>
                <div class="oj-address-content" data-address-content="billing">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="billing-building">Building</label>
                            <input 
                                type="text" 
                                id="billing-building" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.address_1 ? billing.address_1.split(',')[0].replace('Building', '').trim() : '')}"
                                placeholder="Building 5"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="billing-floor">Floor</label>
                            <input 
                                type="text" 
                                id="billing-floor" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.address_1 && billing.address_1.split(',')[1] ? billing.address_1.split(',')[1].replace('Floor', '').trim() : '')}"
                                placeholder="3"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="billing-apartment">Apartment</label>
                            <input 
                                type="text" 
                                id="billing-apartment" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.address_1 && billing.address_1.split(',')[2] ? billing.address_1.split(',')[2].replace('Apt', '').trim() : '')}"
                                placeholder="12"
                            />
                        </div>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="billing-address-2">Street Address</label>
                        <input 
                            type="text" 
                            id="billing-address-2" 
                            class="oj-form-input" 
                            value="${escapeHtml(billing.address_2 || '')}"
                            placeholder="123 Main Street, District Name"
                        />
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="billing-city">City</label>
                            <input 
                                type="text" 
                                id="billing-city" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.city || '')}"
                                placeholder="Cairo"
                            />
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="billing-state">State</label>
                            <input 
                                type="text" 
                                id="billing-state" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.state || '')}"
                                placeholder="Cairo Governorate"
                            />
                        </div>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="billing-country">Country</label>
                        <input 
                            type="text" 
                            id="billing-country" 
                            class="oj-form-input" 
                            value="${escapeHtml(billing.country || 'EG')}"
                            placeholder="EG"
                        />
                        <span class="oj-form-help">Country code (e.g., EG, SA, AE)</span>
                    </div>
                    
                    <div class="oj-form-group">
                        <label class="oj-form-label" for="billing-maps-link">📍 Location (Google Maps)</label>
                        <div style="display: flex; gap: 8px;">
                            <input 
                                type="url" 
                                id="billing-maps-link" 
                                class="oj-form-input" 
                                value="${escapeHtml(billing.google_maps_link || '')}"
                                placeholder="https://maps.app.goo.gl/xxxxx"
                                style="flex: 1;"
                            />
                            ${billing.google_maps_link ? `<a href="${escapeHtml(billing.google_maps_link)}" target="_blank" class="oj-map-btn" title="Open in Maps">📍</a>` : ''}
                        </div>
                        <span class="oj-form-help">Paste Google Maps share link</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Build History tab content
     * Phase 8 - Step 4
     */
    function buildHistoryTab(data) {
        const history = data.history || {};
        const totalOrders = history.total_orders || 0;
        const totalRevenue = history.total_revenue || 0;
        const averageOrderValue = history.average_order_value || 0;
        const firstOrderDate = history.first_order_date || '';
        const lastOrderDate = history.last_order_date || '';
        
        // Format dates
        const firstOrderFormatted = firstOrderDate ? new Date(firstOrderDate).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        }) : 'N/A';
        
        const lastOrderFormatted = lastOrderDate ? new Date(lastOrderDate).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        }) : 'N/A';
        
        return `
            <!-- Customer History - Simple WooCommerce Style -->
            <div class="oj-history-simple">
                <h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #23282d;">Customer Statistics</h3>
                
                <table class="oj-stats-table">
                    <tbody>
                        <tr>
                            <td class="oj-stats-label">Total Orders:</td>
                            <td class="oj-stats-value">${totalOrders}</td>
                        </tr>
                        <tr>
                            <td class="oj-stats-label">Total Revenue:</td>
                            <td class="oj-stats-value">EGP ${totalRevenue.toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td class="oj-stats-label">Average Order Value:</td>
                            <td class="oj-stats-value">EGP ${averageOrderValue.toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td class="oj-stats-label">Customer Since:</td>
                            <td class="oj-stats-value">${firstOrderFormatted}</td>
                        </tr>
                        <tr>
                            <td class="oj-stats-label">Last Order Date:</td>
                            <td class="oj-stats-value">${lastOrderFormatted}</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- AI Feature (Disabled) -->
                <div class="oj-ai-disabled">
                    <span class="oj-ai-icon">🤖</span>
                    <span class="oj-ai-text">AI Customer Behavior Assistant</span>
                    <span class="oj-ai-badge">Coming in Stage 2</span>
                </div>
            </div>
        `;
    }
    
    /**
     * Setup tab switching for Customer Info modal
     * Phase 8 - Step 1 & 3
     */
    function setupCustomerInfoTabs() {
        // Tab button click handler
        $('.oj-tab-btn').off('click').on('click', function() {
            const tabName = $(this).data('tab');
            // Remove active class from all tabs and panes
            $('.oj-tab-btn').removeClass('active');
            $('.oj-tab-pane').removeClass('active');
            
            // Add active class to clicked tab and corresponding pane
            $(this).addClass('active');
            $(`[data-tab-content="${tabName}"]`).addClass('active');
        });
        
        // Collapsible address sections
        $('.oj-address-header').off('click').on('click', function() {
            const $header = $(this);
            const $content = $header.next('.oj-address-content');
            
            // Toggle this section
            $header.toggleClass('active');
            $content.toggleClass('show');
        });
    }
    
    /**
     * Open Add Note modal
     */
    function openAddNoteModal() {
        if (!currentOrderData) {
            return;
        }
        // Build modal content
        const bodyContent = `
            <div class="oj-form-group">
                <label class="oj-form-label">Note Type</label>
                <div class="oj-radio-group">
                    <div class="oj-radio-option">
                        <input type="radio" id="note-type-internal" name="note_type" value="internal" checked>
                        <label for="note-type-internal">
                            🔒 Internal Note (Staff Only)
                            <span class="oj-form-help">Only visible to staff members</span>
                        </label>
                    </div>
                    <div class="oj-radio-option">
                        <input type="radio" id="note-type-customer" name="note_type" value="customer">
                        <label for="note-type-customer">
                            👤 Customer Note
                            <span class="oj-form-help">Visible to customer and staff</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="order-note-text">Note Content</label>
                <textarea 
                    id="order-note-text" 
                    class="oj-form-textarea" 
                    placeholder="Enter your note here..."
                    rows="5"
                    required
                ></textarea>
                <span class="oj-form-help">Add any relevant information about this order</span>
            </div>
        `;
        
        // Define modal buttons
        const buttons = [
            {
                text: 'Cancel',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Add Note',
                action: 'save_note',
                style: 'primary',
                disabled: false
            }
        ];
        
        // Open modal
        openModal(
            '📝 Add Note to Order ' + currentOrderData.number,
            bodyContent,
            buttons
        );
        
        // Focus on textarea
        setTimeout(function() {
            $('#order-note-text').focus();
        }, 350);
    }
    
    /**
     * Handle save note action
     */
    function handleSaveNote() {
        // Get form values
        const noteType = $('input[name="note_type"]:checked').val();
        const noteText = $('#order-note-text').val().trim();
        
        // Validate
        if (!noteText) {
            alert('Please enter a note');
            $('#order-note-text').focus();
            return;
        }
        // Disable save button and show loading
        $('[data-modal-action="save_note"]')
            .prop('disabled', true)
            .text('Saving...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_add_order_note',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                note_type: noteType,
                note_text: noteText
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('✅ Note added successfully!\n\n' + stripHtml(response.data.message));
                    
                    // Close modal
                    closeModal();
                    
                    // TODO: Refresh order card to show note indicator
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="save_note"]')
                        .prop('disabled', false)
                        .text('Add Note');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="save_note"]')
                    .prop('disabled', false)
                    .text('Add Note');
            }
        });
    }
    
    // ========================================================================
    // PHASE 3: CUSTOMER INFO MODAL
    // ========================================================================
    
    /**
     * Open Customer Info modal
     */
    function openCustomerInfoModal() {
        if (!currentOrderData) {
            return;
        }
        // Show loading while fetching current customer data
        openModal(
            '👤 Edit Customer Info - Order ' + currentOrderData.number,
            '<div class="oj-modal-loading"><div class="spinner"></div><p>Loading customer data...</p></div>',
            []
        );
        
        // Fetch current customer data via AJAX
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_get_customer_info',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id
            },
            success: function(response) {
                if (response.success) {
                    // Build modal content with current data
                    const customer = response.data.customer;
                    
                    const bodyContent = `
                        <!-- Tab Navigation -->
                        <div class="oj-tabs-nav">
                            <button class="oj-tab-btn active" data-tab="user-info">
                                <span class="oj-tab-icon">👤</span>
                                <span class="oj-tab-label">Info</span>
                            </button>
                            <button class="oj-tab-btn" data-tab="addresses">
                                <span class="oj-tab-icon">📍</span>
                                <span class="oj-tab-label">Addresses</span>
                            </button>
                            <button class="oj-tab-btn" data-tab="history">
                                <span class="oj-tab-icon">📊</span>
                                <span class="oj-tab-label">History</span>
                            </button>
                        </div>
                        
                        <!-- Tab Content -->
                        <div class="oj-tabs-content">
                            <!-- Tab 1: User Information -->
                            <div class="oj-tab-pane active" data-tab-content="user-info">
                                <div class="oj-form-group">
                                    <label class="oj-form-label" for="customer-name">Customer Name</label>
                                    <input 
                                        type="text" 
                                        id="customer-name" 
                                        class="oj-form-input" 
                                        value="${escapeHtml(customer.name)}"
                                        placeholder="Enter customer name"
                                        required
                                    />
                                </div>
                                
                                <div class="oj-form-group">
                                    <label class="oj-form-label" for="customer-phone">Phone Number</label>
                                    <input 
                                        type="tel" 
                                        id="customer-phone" 
                                        class="oj-form-input" 
                                        value="${escapeHtml(customer.phone)}"
                                        placeholder="+20 123 456 7890"
                                    />
                                    <span class="oj-form-help">Include country code for international numbers</span>
                                </div>
                                
                                <div class="oj-form-group">
                                    <label class="oj-form-label" for="customer-email">Email Address</label>
                                    <input 
                                        type="email" 
                                        id="customer-email" 
                                        class="oj-form-input" 
                                        value="${escapeHtml(customer.email)}"
                                        placeholder="customer@example.com"
                                    />
                                    <span class="oj-form-help">Optional - for order notifications</span>
                                </div>
                                
                                <div class="oj-form-group">
                                    <label class="oj-form-label" for="customer-notes">Special Notes</label>
                                    <textarea 
                                        id="customer-notes" 
                                        class="oj-form-textarea" 
                                        placeholder="Any special requirements or notes..."
                                        rows="3"
                                    >${escapeHtml(customer.notes)}</textarea>
                                    <span class="oj-form-help">Dietary restrictions, preferences, etc.</span>
                                </div>
                            </div>
                            
                            <!-- Tab 2: Addresses -->
                            <div class="oj-tab-pane" data-tab-content="addresses">
                                ${buildAddressesTab(response.data)}
                            </div>
                            
                            <!-- Tab 3: History -->
                            <div class="oj-tab-pane" data-tab-content="history">
                                ${buildHistoryTab(response.data)}
                            </div>
                        </div>
                    `;
                    
                    // Define modal buttons
                    const buttons = [
                        {
                            text: 'Cancel',
                            action: 'cancel',
                            style: 'secondary',
                            disabled: false
                        },
                        {
                            text: 'Update Customer Info',
                            action: 'save_customer_info',
                            style: 'primary',
                            disabled: false
                        }
                    ];
                    
                    // Update modal with form
                    openModal(
                        '👤 Edit Customer Info - Order ' + currentOrderData.number,
                        bodyContent,
                        buttons
                    );
                    
                    // Setup tab switching
                    setupCustomerInfoTabs();
                    
                    // Focus on name field
                    setTimeout(function() {
                        $('#customer-name').focus();
                    }, 350);
                    
                } else {
                    alert('❌ Error loading customer data: ' + response.data.message);
                    closeModal();
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                closeModal();
            }
        });
    }
    
    /**
     * Escape HTML for safe display
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Strip HTML tags and decode HTML entities
     */
    function stripHtml(html) {
        if (!html) return '';
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }
    
    // ========================================================================
    // PLACEHOLDER HANDLERS (Other phases)
    // ========================================================================
    
    /**
     * Open Refund modal
     * CRITICAL: Must block table child orders!
     */
    function openRefundModal() {
        if (!currentOrderData) {
            return;
        }
        // CRITICAL: Block table child orders
        if (currentOrderData.isTableChild) {
            alert('❌ Table Orders Cannot Be Refunded Individually\n\nTable child orders must use the "Close Table" process to handle refunds properly.\n\nThis ensures invoice integrity and proper session management.');
            return;
        }
        
        // Check if order is paid (can only refund paid orders)
        if (currentOrderData.status !== 'completed' && currentOrderData.status !== 'pending-payment') {
            alert('❌ Cannot Refund Unpaid Order\n\nOnly completed or paid orders can be refunded.');
            return;
        }
        
        // Show loading while fetching order items
        openModal(
            '💰 Refund Order ' + currentOrderData.number,
            '<div class="oj-modal-loading"><div class="spinner"></div><p>Loading order details...</p></div>',
            []
        );
        
        // Fetch order items via AJAX
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_get_refund_data',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id
            },
            success: function(response) {
                if (response.success) {
                    showRefundForm(response.data);
                } else {
                    alert('❌ Error loading refund data: ' + response.data.message);
                    closeModal();
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                closeModal();
            }
        });
    }
    
    /**
     * Show refund form with order data
     */
    function showRefundForm(data) {
        const bodyContent = `
            <div class="oj-form-group">
                <label class="oj-form-label">Refund Type</label>
                <div class="oj-radio-group">
                    <div class="oj-radio-option">
                        <input type="radio" id="refund-full" name="refund_type" value="full" checked>
                        <label for="refund-full">
                            💰 Full Refund (${data.total_formatted})
                            <span class="oj-form-help">Refund entire order amount</span>
                        </label>
                    </div>
                    <div class="oj-radio-option" style="opacity: 0.5; pointer-events: none;">
                        <input type="radio" id="refund-partial" name="refund_type" value="partial" disabled>
                        <label for="refund-partial" style="color: #999;">
                            📦 Partial Refund <span style="background: #e0e0e0; color: #666; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Soon: Stage 2</span>
                            <span class="oj-form-help" style="color: #aaa;">Advanced feature coming soon</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="oj-form-group" id="partial-items" style="display:none;">
                <label class="oj-form-label">Select Items to Refund</label>
                <div class="oj-checkbox-group">
                    ${data.items.map(item => `
                        <div class="oj-checkbox-option">
                            <input type="checkbox" id="item-${item.id}" class="refund-item" value="${item.id}" data-amount="${item.total}">
                            <label for="item-${item.id}">
                                ${item.quantity}x ${escapeHtml(item.name)} - ${item.total_formatted}
                            </label>
                        </div>
                    `).join('')}
                </div>
                <p class="oj-form-help" style="margin-top: 10px;">
                    <strong>Selected Amount: <span id="refund-amount">0.00</span></strong>
                </p>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="refund-reason">Refund Reason</label>
                <textarea 
                    id="refund-reason" 
                    class="oj-form-textarea" 
                    placeholder="Explain why this refund is being processed..."
                    rows="3"
                    required
                ></textarea>
                <span class="oj-form-help">This will be added to order notes</span>
            </div>
        `;
        
        // Define modal buttons
        const buttons = [
            {
                text: 'Cancel',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Process Refund',
                action: 'process_refund',
                style: 'danger',
                disabled: false
            }
        ];
        
        // Update modal with form
        openModal(
            '💰 Refund Order ' + currentOrderData.number,
            bodyContent,
            buttons
        );
        
        // Setup event listeners
        setupRefundListeners(data);
    }
    
    /**
     * Setup refund form event listeners
     */
    function setupRefundListeners(data) {
        // Toggle partial items visibility
        $('input[name="refund_type"]').on('change', function() {
            if ($(this).val() === 'partial') {
                $('#partial-items').slideDown(200);
            } else {
                $('#partial-items').slideUp(200);
            }
        });
        
        // Calculate refund amount for partial
        $('.refund-item').on('change', function() {
            let total = 0;
            $('.refund-item:checked').each(function() {
                total += parseFloat($(this).data('amount'));
            });
            $('#refund-amount').text(total.toFixed(2));
        });
    }
    
    /**
     * Open Discount modal
     * Per-order discount (not per-item)
     */
    function openDiscountModal() {
        if (!currentOrderData) {
            return;
        }
        // Show loading while fetching order data
        openModal(
            '🏷️ Add Discount to Order ' + currentOrderData.number,
            '<div class="oj-modal-loading"><div class="spinner"></div><p>Loading order details...</p></div>',
            []
        );
        
        // Fetch order totals via AJAX
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_get_discount_data',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id
            },
            success: function(response) {
                if (response.success) {
                    showDiscountForm(response.data);
                } else {
                    alert('❌ Error loading discount data: ' + response.data.message);
                    closeModal();
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                closeModal();
            }
        });
    }
    
    /**
     * Show discount form with order data
     */
    function showDiscountForm(data) {
        const bodyContent = `
            <div class="oj-form-group">
                <label class="oj-form-label">Current Order Total</label>
                <div style="font-size: 18px; font-weight: 600; color: #2c3e50; padding: 10px 0;">
                    ${data.total_formatted}
                </div>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label">Discount Type</label>
                <div class="oj-radio-group">
                    <div class="oj-radio-option">
                        <input type="radio" id="discount-fixed" name="discount_type" value="fixed" checked>
                        <label for="discount-fixed">
                            💰 Fixed Amount
                            <span class="oj-form-help">Reduce by a specific amount (e.g., 10 EGP)</span>
                        </label>
                    </div>
                    <div class="oj-radio-option">
                        <input type="radio" id="discount-percent" name="discount_type" value="percentage">
                        <label for="discount-percent">
                            📊 Percentage
                            <span class="oj-form-help">Reduce by a percentage (e.g., 15%)</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="discount-value">Discount Value</label>
                <input 
                    type="number" 
                    id="discount-value" 
                    class="oj-form-input" 
                    placeholder="Enter amount or percentage"
                    min="0"
                    max="999999"
                    step="0.01"
                    required
                />
                <span class="oj-form-help">
                    <span id="discount-type-hint">Enter fixed amount (e.g., 10 for 10 EGP off)</span>
                </span>
            </div>
            
            <div class="oj-form-group" id="new-total-preview" style="display:none;">
                <label class="oj-form-label">New Order Total</label>
                <div style="font-size: 18px; font-weight: 600; color: #27ae60; padding: 10px 0;">
                    <span id="new-total-amount">--</span>
                    <span style="font-size: 14px; color: #6c757d; margin-left: 8px;">
                        (Save: <span id="discount-amount">--</span>)
                    </span>
                </div>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="discount-reason">Discount Reason</label>
                <textarea 
                    id="discount-reason" 
                    class="oj-form-textarea" 
                    placeholder="Explain why this discount is being applied..."
                    rows="3"
                    required
                ></textarea>
                <span class="oj-form-help">This will be added to order notes</span>
            </div>
        `;
        
        // Define modal buttons
        const buttons = [
            {
                text: 'Cancel',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Apply Discount',
                action: 'apply_discount',
                style: 'primary',
                disabled: false
            }
        ];
        
        // Update modal with form
        openModal(
            '🏷️ Add Discount to Order ' + currentOrderData.number,
            bodyContent,
            buttons
        );
        
        // Setup event listeners
        setupDiscountListeners(data);
    }
    
    /**
     * Setup discount form event listeners
     */
    function setupDiscountListeners(data) {
        const orderTotal = parseFloat(data.total);
        const orderSubtotal = parseFloat(data.subtotal);
        
        // Update hint text when discount type changes
        $('input[name="discount_type"]').on('change', function() {
            if ($(this).val() === 'fixed') {
                $('#discount-type-hint').text('Enter fixed amount (e.g., 10 for 10 EGP off)');
                $('#discount-value').attr('max', '999999');
            } else {
                $('#discount-type-hint').text('Enter percentage (e.g., 15 for 15% off)');
                $('#discount-value').attr('max', '100');
            }
            calculateNewTotal();
        });
        
        // Calculate new total when value changes
        $('#discount-value').on('input', function() {
            calculateNewTotal();
        });
        
        function calculateNewTotal() {
            const discountType = $('input[name="discount_type"]:checked').val();
            const discountValue = parseFloat($('#discount-value').val()) || 0;
            
            if (discountValue <= 0) {
                $('#new-total-preview').hide();
                return;
            }
            
            let discountAmount = 0;
            
            if (discountType === 'fixed') {
                discountAmount = discountValue;
            } else {
                // Percentage - CRITICAL: Calculate on SUBTOTAL not TOTAL to avoid tax miscalculation
                discountAmount = (orderSubtotal * discountValue) / 100;
            }
            
            // Ensure discount doesn't exceed subtotal
            if (discountAmount > orderSubtotal) {
                discountAmount = orderSubtotal;
            }
            
            // CRITICAL: Apply discount WITHOUT tax recalculation
            // Just subtract discount from subtotal, keep original tax unchanged
            const originalTaxAmount = orderTotal - orderSubtotal;
            const newSubtotal = Math.max(0, orderSubtotal - discountAmount);
            const newTotal = newSubtotal + originalTaxAmount;
            
            // Update display
            $('#discount-amount').text(discountAmount.toFixed(2) + ' EGP');
            $('#new-total-amount').text(newTotal.toFixed(2) + ' EGP');
            $('#new-total-preview').show();
        }
    }
    
    // ========================================================================
    // COUPON MODAL FUNCTIONS (New Feature)
    // ========================================================================
    
    /**
     * Open Coupon modal
     * Tabbed interface for applying existing coupons or creating new ones
     */
    function openCouponModal() {
        if (!currentOrderData) {
            return;
        }
        // Show loading while fetching coupon data
        openModal(
            '🎟️ Manage Coupons - Order ' + currentOrderData.number,
            '<div class="oj-modal-loading"><div class="spinner"></div><p>Loading coupon data...</p></div>',
            []
        );
        
        // Fetch both available coupons and order coupons in parallel
        Promise.all([
            fetchAvailableCoupons(),
            fetchOrderCoupons()
        ]).then(([availableCoupons, orderCoupons]) => {
            showCouponTabs(availableCoupons, orderCoupons);
        }).catch(error => {
            alert('❌ Error loading coupon data: ' + error);
            closeModal();
        });
    }
    
    /**
     * Fetch available coupons from backend
     */
    function fetchAvailableCoupons() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: oj_editor_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'oj_get_available_coupons',
                    nonce: oj_editor_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data.coupons);
                    } else {
                        reject(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    reject('Connection error: ' + error);
                }
            });
        });
    }
    
    /**
     * Fetch order's applied coupons from backend
     */
    function fetchOrderCoupons() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: oj_editor_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'oj_get_order_coupons',
                    nonce: oj_editor_data.nonce,
                    order_id: currentOrderData.id
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    reject('Connection error: ' + error);
                }
            });
        });
    }
    
    /**
     * Show coupon tabs with data
     */
    function showCouponTabs(availableCoupons, orderData) {
        const bodyContent = `
            <div class="oj-coupon-tabs">
                <!-- Tab Navigation -->
                <div class="oj-tab-nav">
                    <button class="oj-tab-btn active" data-tab="apply">Apply Coupon</button>
                    <button class="oj-tab-btn" data-tab="create">Create New</button>
                </div>
                
                <!-- Tab Content -->
                <div class="oj-tab-content">
                    <!-- Tab 1: Apply Coupon -->
                    <div class="oj-tab-panel active" id="tab-apply">
                        <div class="oj-form-group">
                            <label class="oj-form-label">Current Order Total</label>
                            <div style="font-size: 18px; font-weight: 600; color: #2c3e50; padding: 10px 0;">
                                ${orderData.order_total_formatted}
                            </div>
                        </div>
                        
                        <!-- Currently Applied Coupons -->
                        <div class="oj-form-group" id="applied-coupons-section">
                            <label class="oj-form-label">Currently Applied Coupons</label>
                            <div id="applied-coupons-list">
                                ${renderAppliedCoupons(orderData.applied_coupons)}
                            </div>
                        </div>
                        
                        <!-- Apply New Coupon -->
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="coupon-code-input">Apply Coupon Code</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input 
                                    type="text" 
                                    id="coupon-code-input" 
                                    class="oj-form-input" 
                                    placeholder="Enter coupon code..."
                                    style="flex: 1;"
                                />
                                <button class="button button-primary" onclick="applyCouponFromInput()">
                                    Apply
                                </button>
                            </div>
                        </div>
                        
                        <!-- Available Coupons -->
                        <div class="oj-form-group">
                            <label class="oj-form-label">Available Coupons (Click to Apply)</label>
                            <div class="oj-available-coupons">
                                ${renderAvailableCoupons(availableCoupons)}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 2: Create New Coupon -->
                    <div class="oj-tab-panel" id="tab-create">
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="new-coupon-code">Coupon Code</label>
                            <input 
                                type="text" 
                                id="new-coupon-code" 
                                class="oj-form-input" 
                                placeholder="Enter unique coupon code (e.g., SAVE20)"
                                style="text-transform: uppercase;"
                            />
                            <span class="oj-form-help">Code will be converted to uppercase</span>
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label">Discount Type</label>
                            <div class="oj-radio-group">
                                <div class="oj-radio-option">
                                    <input type="radio" id="new-discount-fixed" name="new_discount_type" value="fixed_cart" checked>
                                    <label for="new-discount-fixed">
                                        💰 Fixed Amount
                                        <span class="oj-form-help">Reduce by specific amount (e.g., 20 EGP)</span>
                                    </label>
                                </div>
                                <div class="oj-radio-option">
                                    <input type="radio" id="new-discount-percent" name="new_discount_type" value="percent">
                                    <label for="new-discount-percent">
                                        📊 Percentage
                                        <span class="oj-form-help">Reduce by percentage (e.g., 15%)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="new-coupon-amount">Discount Amount</label>
                            <input 
                                type="number" 
                                id="new-coupon-amount" 
                                class="oj-form-input" 
                                placeholder="Enter amount or percentage"
                                min="0"
                                step="0.01"
                                required
                            />
                            <span class="oj-form-help" id="new-discount-hint">Enter fixed amount (e.g., 20 for 20 EGP off)</span>
                        </div>
                        
                        <div class="oj-form-group">
                            <label class="oj-form-label" for="new-coupon-description">Description (Optional)</label>
                            <textarea 
                                id="new-coupon-description" 
                                class="oj-form-textarea" 
                                placeholder="Brief description of this coupon..."
                                rows="2"
                            ></textarea>
                            <span class="oj-form-help">This will help identify the coupon later</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Define modal buttons based on active tab
        const buttons = [
            {
                text: 'Close',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Create Coupon',
                action: 'create_coupon',
                style: 'primary',
                disabled: false,
                id: 'create-coupon-btn',
                class: 'tab-create-only'
            }
        ];
        
        // Update modal with tabbed content
        openModal(
            '🎟️ Manage Coupons - Order ' + currentOrderData.number,
            bodyContent,
            buttons
        );
        
        // Setup tab functionality and event listeners
        setupCouponTabListeners();
        updateTabButtons();
    }
    
    /**
     * Render applied coupons list
     */
    function renderAppliedCoupons(appliedCoupons) {
        if (!appliedCoupons || appliedCoupons.length === 0) {
            return '<div class="oj-no-coupons">No coupons applied to this order</div>';
        }
        
        return appliedCoupons.map(coupon => `
            <div class="oj-applied-coupon">
                <div class="oj-coupon-info">
                    <span class="oj-coupon-code">${coupon.code}</span>
                    <span class="oj-coupon-savings">-${coupon.discount_amount_formatted}</span>
                </div>
                <button class="button button-small button-link-delete" onclick="removeCouponFromOrder('${coupon.code}')">
                    Remove
                </button>
            </div>
        `).join('');
    }
    
    /**
     * Render available coupons list
     */
    function renderAvailableCoupons(availableCoupons) {
        if (!availableCoupons || availableCoupons.length === 0) {
            return '<div class="oj-no-coupons">No available coupons found</div>';
        }
        
        return availableCoupons.map(coupon => `
            <div class="oj-available-coupon" onclick="applyCouponToOrder('${coupon.code}')">
                <div class="oj-coupon-header">
                    <span class="oj-coupon-code">${coupon.code}</span>
                    <span class="oj-coupon-amount">${coupon.formatted_amount}</span>
                </div>
                <div class="oj-coupon-description">${coupon.description || 'No description'}</div>
                ${coupon.individual_use ? '<div class="oj-coupon-badge">Individual Use Only</div>' : ''}
            </div>
        `).join('');
    }
    
    /**
     * Setup coupon tab event listeners
     */
    function setupCouponTabListeners() {
        // Tab switching
        $('.oj-tab-btn').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Update tab buttons
            $('.oj-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Update tab panels
            $('.oj-tab-panel').removeClass('active');
            $('#tab-' + tabId).addClass('active');
            
            // Update modal buttons
            updateTabButtons();
        });
        
        // New coupon type change
        $('input[name="new_discount_type"]').on('change', function() {
            if ($(this).val() === 'fixed_cart') {
                $('#new-discount-hint').text('Enter fixed amount (e.g., 20 for 20 EGP off)');
            } else {
                $('#new-discount-hint').text('Enter percentage (e.g., 15 for 15% off)');
            }
        });
        
        // Auto-uppercase coupon code
        $('#new-coupon-code').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
    }
    
    /**
     * Update modal buttons based on active tab
     */
    function updateTabButtons() {
        const activeTab = $('.oj-tab-btn.active').data('tab');
        
        if (activeTab === 'create') {
            $('.tab-create-only').addClass('show');
        } else {
            $('.tab-create-only').removeClass('show');
        }
    }
    
    /**
     * Apply coupon from input field
     */
    window.applyCouponFromInput = function() {
        const couponCode = $('#coupon-code-input').val().trim();
        if (!couponCode) {
            alert('Please enter a coupon code');
            return;
        }
        applyCouponToOrder(couponCode);
    };
    
    /**
     * Apply coupon to order
     */
    window.applyCouponToOrder = function(couponCode) {
        // Show loading state
        const originalText = 'Applying coupon...';
        
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_apply_coupon_to_order',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                coupon_code: couponCode
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + stripHtml(response.data.message));
                    
                    // Refresh the coupon modal to show updated state
                    openCouponModal();
                    
                    // Refresh the order card
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
            }
        });
    };
    
    /**
     * Remove coupon from order
     */
    window.removeCouponFromOrder = function(couponCode) {
        if (!confirm('Remove coupon "' + couponCode + '" from this order?')) {
            return;
        }
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_remove_coupon_from_order',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                coupon_code: couponCode
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + stripHtml(response.data.message));
                    
                    // Refresh the coupon modal to show updated state
                    openCouponModal();
                    
                    // Refresh the order card
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
            }
        });
    };
    
    /**
     * Open Add Items modal
     * Shows "Soon Stage 2" message with table ordering option for table orders
     */
    function openAddItemsModal() {
        if (!currentOrderData) {
            return;
        }
        // Check if this is a table order
        const isTableOrder = currentOrderData.method === 'dinein';
        
        let bodyContent = '';
        let buttons = [];
        
        if (isTableOrder) {
            // Table order - show option to open table ordering
            const tableNumber = currentOrderData.tableNumber || 'Unknown';
            
            bodyContent = `
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🚀</div>
                    <h3 style="font-size: 20px; color: #2c3e50; margin-bottom: 16px;">Soon "Stage 2" | Table Ordering</h3>
                    <p style="color: #6c757d; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                        This feature will be available in Stage 2 of development.<br>
                        In the meantime, you can add items through the Table Ordering page.
                    </p>
                    <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; border-left: 4px solid #0073aa;">
                        <div style="font-size: 14px; color: #495057;">
                            <strong>Order:</strong> ${currentOrderData.number}<br>
                            <strong>Table:</strong> ${tableNumber}
                        </div>
                    </div>
                </div>
            `;
            
            buttons = [
                {
                    text: 'Close',
                    action: 'cancel',
                    style: 'secondary',
                    disabled: false
                },
                {
                    text: '🍽️ Open Table Ordering',
                    action: 'open_table_ordering',
                    style: 'primary',
                    disabled: false
                }
            ];
        } else {
            // Other order types - just show "Soon Stage 2"
            bodyContent = `
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 48px; margin-bottom: 20px;">🚀</div>
                    <h3 style="font-size: 20px; color: #2c3e50; margin-bottom: 16px;">Soon "Stage 2"</h3>
                    <p style="color: #6c757d; font-size: 15px; line-height: 1.6;">
                        This feature will be available in Stage 2 of development.
                    </p>
                </div>
            `;
            
            buttons = [
                {
                    text: 'Got it',
                    action: 'cancel',
                    style: 'primary',
                    disabled: false
                }
            ];
        }
        
        openModal(
            '➕ Add Items',
            bodyContent,
            buttons
        );
    }
    
    /**
     * Handle status change (mark_pending, mark_on_hold)
     */
    function handleStatusChange(action) {
        if (!currentOrderData) {
            return;
        }
        // Determine status details
        let statusLabel = '';
        let statusValue = '';
        let confirmMessage = '';
        
        switch(action) {
            case 'mark_pending':
                statusLabel = 'Pending Payment';
                statusValue = 'pending';
                confirmMessage = `Change order ${currentOrderData.number} status to "Pending Payment"?\n\nThis indicates the order is awaiting payment.`;
                break;
            case 'mark_on_hold':
                statusLabel = 'On Hold';
                statusValue = 'on-hold';
                confirmMessage = `Put order ${currentOrderData.number} "On Hold"?\n\nThis will pause order processing.`;
                break;
            case 'mark_processing':
                statusLabel = 'Processing';
                statusValue = 'processing';
                confirmMessage = `Change order ${currentOrderData.number} status to "Processing"?\n\nThis will move the order to the kitchen.`;
                break;
            default:
                return;
        }
        
        // Confirm action
        if (!confirm(confirmMessage)) {
            return;
        }
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_change_order_status',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                new_status: statusValue
            },
            success: function(response) {
                if (response.success) {
                    alert(`✅ Order status changed to "${statusLabel}"!\n\n` + stripHtml(response.data.message));
                    
                    // Refresh the order card (Phase 9)
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
            }
        });
    }
    
    /**
     * Open Order Content modal
     * Phase 7: View/Edit Order Type, Location, Order Date
     */
    function openOrderContentModal() {
        if (!currentOrderData) {
            return;
        }
        // Show loading modal
        openModal(
            '📋 Order Content',
            '<div class="oj-modal-loading"><div class="spinner"></div><p>Loading order details...</p></div>',
            []
        );
        
        // Fetch order content data via AJAX
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_get_order_content',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id
            },
            success: function(response) {
                if (response.success) {
                    showOrderContentForm(response.data);
                } else {
                    alert('❌ Error loading order content: ' + response.data.message);
                    closeModal();
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                closeModal();
            }
        });
    }
    
    /**
     * Show order content form
     */
    function showOrderContentForm(data) {
        const bodyContent = `
            <div class="oj-form-group">
                <label class="oj-form-label" for="order-type">Order Type</label>
                <select id="order-type" class="oj-form-select">
                    <option value="dinein" ${data.order_type === 'dinein' ? 'selected' : ''}>🍽️ Dine-in</option>
                    <option value="takeaway" ${data.order_type === 'takeaway' ? 'selected' : ''}>🥡 Takeaway</option>
                    <option value="delivery" ${data.order_type === 'delivery' ? 'selected' : ''}>🚚 Delivery</option>
                </select>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="order-location">Location / Table</label>
                <input 
                    type="text" 
                    id="order-location" 
                    class="oj-form-input" 
                    value="${escapeHtml(data.location || '')}"
                    placeholder="Table number, counter, etc."
                />
                <span class="oj-form-help">For dine-in: table number (e.g., T30)</span>
            </div>
            
            <div class="oj-form-group">
                <label class="oj-form-label" for="order-date">Order Date</label>
                <input 
                    type="datetime-local" 
                    id="order-date" 
                    class="oj-form-input" 
                    value="${data.order_date || ''}"
                />
                <span class="oj-form-help">When the order was placed</span>
            </div>
            
            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; border-left: 4px solid #0073aa; margin-top: 20px;">
                <div style="font-size: 14px; color: #495057;">
                    <strong>Order:</strong> ${currentOrderData.number}<br>
                    <strong>Current Status:</strong> ${currentOrderData.status}
                </div>
            </div>
        `;
        
        const buttons = [
            {
                text: 'Cancel',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Save Changes',
                action: 'save_order_content',
                style: 'primary',
                disabled: false
            }
        ];
        
        openModal(
            '📋 Order Content - ' + currentOrderData.number,
            bodyContent,
            buttons
        );
    }
    
    /**
     * Open Order Actions modal
     * WooCommerce order actions dropdown functionality
     */
    function openOrderActionsModal() {
        if (!currentOrderData) {
            return;
        }
        const bodyContent = `
            <div style="padding: 20px 0;">
                <div class="oj-form-group">
                    <label class="oj-form-label" for="order-action-select">Choose an action</label>
                    <select id="order-action-select" class="oj-form-select">
                        <option value="">Choose an action...</option>
                        <option value="send_order_details">📧 Send order details to customer</option>
                        <option value="send_order_details_admin">📧 Send order details to admin</option>
                        <option value="regenerate_download_permissions">🔑 Regenerate download permissions</option>
                        <option value="send_invoice">📄 Email invoice to customer</option>
                    </select>
                    <span class="oj-form-help">Select an action to perform on this order</span>
                </div>
                
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; border-left: 4px solid #0073aa; margin-top: 20px;">
                    <div style="font-size: 14px; color: #495057;">
                        <strong>Order:</strong> ${currentOrderData.number}<br>
                        <strong>Status:</strong> ${currentOrderData.status}
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                    <p style="font-size: 13px; color: #6c757d; margin-bottom: 8px;">
                        Need advanced features?
                    </p>
                    <button type="button" class="oj-woo-link-btn" onclick="openWooCommerceDirectly()">
                        🔧 Open in WooCommerce
                    </button>
                </div>
            </div>
        `;
        
        const buttons = [
            {
                text: 'Close',
                action: 'cancel',
                style: 'secondary',
                disabled: false
            },
            {
                text: 'Execute Action',
                action: 'execute_order_action',
                style: 'primary',
                disabled: false
            }
        ];
        
        openModal(
            '🔧 Order Actions',
            bodyContent,
            buttons
        );
    }
    
    /**
     * Global function to open WooCommerce directly
     */
    window.openWooCommerceDirectly = function() {
        if (!currentOrderData) {
            return;
        }
        
        const adminUrl = window.location.origin + '/wp-admin';
        const wooCommerceUrl = `${adminUrl}/post.php?post=${currentOrderData.id}&action=edit`;
        window.open(wooCommerceUrl, '_blank');
        closeModal();
    };
    
    /**
     * Route modal actions to appropriate handlers
     */
    function handleModalAction(action) {
        switch(action) {
            case 'save_note':
                handleSaveNote();
                break;
            case 'save_customer_info':
                handleSaveCustomerInfo();
                break;
            case 'process_refund':
                handleProcessRefund();
                break;
            case 'apply_discount':
                handleApplyDiscount();
                break;
            case 'open_table_ordering':
                handleOpenTableOrdering();
                break;
            case 'execute_order_action':
                handleExecuteOrderAction();
                break;
            case 'save_order_content':
                handleSaveOrderContent();
                break;
            case 'create_coupon':
                handleCreateCoupon();
                break;
            default:
        }
    }
    
    /**
     * Handle save order content
     * Phase 7 - Step 5
     */
    function handleSaveOrderContent() {
        // Get form values
        const orderType = $('#order-type').val();
        const location = $('#order-location').val().trim();
        const orderDate = $('#order-date').val();
        // Disable button and show loading
        $('[data-modal-action="save_order_content"]')
            .prop('disabled', true)
            .text('Saving...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_save_order_content',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                order_type: orderType,
                location: location,
                order_date: orderDate
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Order content updated successfully!\n\n' + stripHtml(response.data.message));
                    closeModal();
                    
                    // Refresh the order card (Phase 9)
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="save_order_content"]')
                        .prop('disabled', false)
                        .text('Save Changes');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="save_order_content"]')
                    .prop('disabled', false)
                    .text('Save Changes');
            }
        });
    }
    
    /**
     * Handle execute order action
     */
    function handleExecuteOrderAction() {
        if (!currentOrderData) {
            return;
        }
        
        // Get selected action
        const selectedAction = $('#order-action-select').val();
        
        if (!selectedAction) {
            alert('Please select an action to execute');
            $('#order-action-select').focus();
            return;
        }
        // Get action label for confirmation
        const actionLabel = $('#order-action-select option:selected').text();
        
        // Confirm action
        if (!confirm(`Execute action: ${actionLabel}?\n\nOrder: ${currentOrderData.number}`)) {
            return;
        }
        
        // Disable button and show loading
        $('[data-modal-action="execute_order_action"]')
            .prop('disabled', true)
            .text('Executing...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_execute_order_action',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                order_action: selectedAction
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Action executed successfully!\n\n' + stripHtml(response.data.message));
                    closeModal();
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="execute_order_action"]')
                        .prop('disabled', false)
                        .text('Execute Action');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="execute_order_action"]')
                    .prop('disabled', false)
                    .text('Execute Action');
            }
        });
    }
    
    /**
     * Handle open table ordering action
     * Opens table ordering page in iframe modal
     */
    function handleOpenTableOrdering() {
        if (!currentOrderData) {
            return;
        }
        // Get table number from order data
        const tableNumber = currentOrderData.tableNumber || '';
        
        if (!tableNumber) {
            alert('⚠️ Unable to determine table number for this order.');
            return;
        }
        
        // Construct table ordering URL
        const siteUrl = window.location.origin;
        const tableOrderingUrl = `${siteUrl}/table-menu/?table=${encodeURIComponent(tableNumber)}`;
        // Close the "Soon Stage 2" modal first
        closeModal();
        
        // Create iframe modal
        openTableOrderingIframe(tableOrderingUrl, tableNumber);
    }
    
    /**
     * Open table ordering page in full-screen iframe
     */
    function openTableOrderingIframe(url, tableNumber) {
        // Create iframe overlay HTML
        const iframeHtml = `
            <div class="oj-iframe-overlay" id="oj-table-ordering-iframe">
                <div class="oj-iframe-container">
                    <div class="oj-iframe-header">
                        <h3 class="oj-iframe-title">🍽️ Table Ordering - ${escapeHtml(tableNumber)}</h3>
                        <button type="button" class="oj-iframe-close" onclick="closeTableOrderingIframe()">&times;</button>
                    </div>
                    <div class="oj-iframe-body">
                        <iframe 
                            src="${escapeHtml(url)}" 
                            class="oj-table-ordering-iframe"
                            frameborder="0"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        `;
        
        // Append to body
        $('body').append(iframeHtml);
        
        // Add show class after a brief delay for animation
        setTimeout(function() {
            $('#oj-table-ordering-iframe').addClass('show');
        }, 10);
        
        // Prevent body scroll
        $('body').addClass('oj-iframe-open');
    }
    
    /**
     * Close table ordering iframe
     * Global function for onclick handler
     */
    window.closeTableOrderingIframe = function() {
        const $iframe = $('#oj-table-ordering-iframe');
        
        // Remove show class for animation
        $iframe.removeClass('show');
        
        // Remove from DOM after animation
        setTimeout(function() {
            $iframe.remove();
            $('body').removeClass('oj-iframe-open');
            
            // Reload page to reflect any new items added
            setTimeout(function() {
                location.reload();
            }, 500);
        }, 300);
    };
    
    // ========================================================================
    // PLACEHOLDER SAVE HANDLERS (Other phases)
    // ========================================================================
    
    /**
     * Handle save customer info action
     */
    function handleSaveCustomerInfo() {
        // Get customer info values
        const customerName = $('#customer-name').val().trim();
        const customerPhone = $('#customer-phone').val().trim();
        const customerEmail = $('#customer-email').val().trim();
        const customerNotes = $('#customer-notes').val().trim();
        
        // Get shipping address values - concatenate Building, Floor, Apartment
        const shippingBuilding = $('#shipping-building').val().trim();
        const shippingFloor = $('#shipping-floor').val().trim();
        const shippingApartment = $('#shipping-apartment').val().trim();
        
        // Build address_1 from separate fields
        let shippingAddress1 = '';
        if (shippingBuilding) shippingAddress1 += 'Building ' + shippingBuilding;
        if (shippingFloor) shippingAddress1 += (shippingAddress1 ? ', ' : '') + 'Floor ' + shippingFloor;
        if (shippingApartment) shippingAddress1 += (shippingAddress1 ? ', ' : '') + 'Apt ' + shippingApartment;
        
        const shippingAddress = {
            address_1: shippingAddress1,
            address_2: $('#shipping-address-2').val().trim(),
            city: $('#shipping-city').val().trim(),
            state: $('#shipping-state').val().trim(),
            postcode: '', // Not collected yet
            country: $('#shipping-country').val().trim(),
            google_maps_link: $('#shipping-maps-link').val().trim()
        };
        
        // Get billing address values - concatenate Building, Floor, Apartment
        const billingBuilding = $('#billing-building').val().trim();
        const billingFloor = $('#billing-floor').val().trim();
        const billingApartment = $('#billing-apartment').val().trim();
        
        // Build address_1 from separate fields
        let billingAddress1 = '';
        if (billingBuilding) billingAddress1 += 'Building ' + billingBuilding;
        if (billingFloor) billingAddress1 += (billingAddress1 ? ', ' : '') + 'Floor ' + billingFloor;
        if (billingApartment) billingAddress1 += (billingAddress1 ? ', ' : '') + 'Apt ' + billingApartment;
        
        const billingAddress = {
            address_1: billingAddress1,
            address_2: $('#billing-address-2').val().trim(),
            city: $('#billing-city').val().trim(),
            state: $('#billing-state').val().trim(),
            postcode: '', // Not collected yet
            country: $('#billing-country').val().trim(),
            google_maps_link: $('#billing-maps-link').val().trim()
        };
        
        const sameAsBilling = $('#same-as-billing').is(':checked');
        
        // Validate name (required)
        if (!customerName) {
            alert('Customer name is required');
            $('#customer-name').focus();
            return;
        }
        
        // Validate email format if provided
        if (customerEmail && !isValidEmail(customerEmail)) {
            alert('Please enter a valid email address');
            $('#customer-email').focus();
            return;
        }
        // Disable save button and show loading
        $('[data-modal-action="save_customer_info"]')
            .prop('disabled', true)
            .text('Saving...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_update_customer_info',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                customer_name: customerName,
                customer_phone: customerPhone,
                customer_email: customerEmail,
                customer_notes: customerNotes,
                shipping: shippingAddress,
                billing: billingAddress,
                same_as_billing: sameAsBilling
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('✅ Customer info updated successfully!\n\n' + stripHtml(response.data.message));
                    
                    // Close modal
                    closeModal();
                    
                    // Refresh the order card (Phase 9)
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="save_customer_info"]')
                        .prop('disabled', false)
                        .text('Update Customer Info');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="save_customer_info"]')
                    .prop('disabled', false)
                    .text('Update Customer Info');
            }
        });
    }
    
    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Handle process refund action
     */
    function handleProcessRefund() {
        // Get form values
        const refundType = $('input[name="refund_type"]:checked').val();
        const refundReason = $('#refund-reason').val().trim();
        
        // Validate reason
        if (!refundReason) {
            alert('Please provide a refund reason');
            $('#refund-reason').focus();
            return;
        }
        
        let refundAmount = 0;
        let refundItems = [];
        
        if (refundType === 'full') {
            refundAmount = 'full';
        } else {
            // Partial refund - get selected items
            $('.refund-item:checked').each(function() {
                refundItems.push($(this).val());
                refundAmount += parseFloat($(this).data('amount'));
            });
            
            if (refundItems.length === 0) {
                alert('Please select at least one item to refund');
                return;
            }
        }
        
        // Final confirmation
        const confirmMsg = refundType === 'full' 
            ? `Process FULL REFUND for order ${currentOrderData.number}?\n\nThis action cannot be undone.`
            : `Process PARTIAL REFUND of ${refundAmount.toFixed(2)} for order ${currentOrderData.number}?\n\nThis action cannot be undone.`;
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Disable button and show loading
        $('[data-modal-action="process_refund"]')
            .prop('disabled', true)
            .text('Processing...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_refund_order',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                refund_type: refundType,
                refund_items: refundItems,
                refund_reason: refundReason
            },
            success: function(response) {
                if (response.success) {
                    // Strip HTML from backend message for clean alert display
                    const cleanMessage = stripHtml(response.data.message);
                    alert('✅ Refund processed successfully!\n\n' + cleanMessage);
                    closeModal();
                    
                    // Refresh the order card (Phase 9)
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="process_refund"]')
                        .prop('disabled', false)
                        .text('Process Refund');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="process_refund"]')
                    .prop('disabled', false)
                    .text('Process Refund');
            }
        });
    }
    
    /**
     * Handle apply discount action
     */
    function handleApplyDiscount() {
        // Get form values
        const discountType = $('input[name="discount_type"]:checked').val();
        const discountValue = parseFloat($('#discount-value').val()) || 0;
        const discountReason = $('#discount-reason').val().trim();
        
        // Validate discount value
        if (discountValue <= 0) {
            alert('Please enter a valid discount value');
            $('#discount-value').focus();
            return;
        }
        
        // Validate percentage doesn't exceed 100%
        if (discountType === 'percentage' && discountValue > 100) {
            alert('❌ Discount percentage cannot exceed 100%');
            $('#discount-value').focus();
            return;
        }
        
        // Validate reason
        if (!discountReason) {
            alert('Please provide a discount reason');
            $('#discount-reason').focus();
            return;
        }
        // Final confirmation
        const confirmMsg = discountType === 'fixed' 
            ? `Apply ${discountValue} EGP discount to order ${currentOrderData.number}?`
            : `Apply ${discountValue}% discount to order ${currentOrderData.number}?`;
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Disable button and show loading
        $('[data-modal-action="apply_discount"]')
            .prop('disabled', true)
            .text('Applying...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_apply_discount',
                nonce: oj_editor_data.nonce,
                order_id: currentOrderData.id,
                discount_type: discountType,
                discount_value: discountValue,
                discount_reason: discountReason
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Discount applied successfully!\n\n' + stripHtml(response.data.message));
                    closeModal();
                    
                    // Refresh the order card (Phase 9)
                    refreshOrderCard(currentOrderData.id);
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="apply_discount"]')
                        .prop('disabled', false)
                        .text('Apply Discount');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="apply_discount"]')
                    .prop('disabled', false)
                    .text('Apply Discount');
            }
        });
    }
    
    /**
     * Handle create coupon
     * Creates new coupon via AJAX and switches to apply tab
     */
    function handleCreateCoupon() {
        // Get form values
        const couponCode = $('#new-coupon-code').val().trim().toUpperCase();
        const discountType = $('input[name="new_discount_type"]:checked').val();
        const discountAmount = parseFloat($('#new-coupon-amount').val()) || 0;
        const description = $('#new-coupon-description').val().trim();
        
        // Validate inputs
        if (!couponCode) {
            alert('❌ Please enter a coupon code');
            $('#new-coupon-code').focus();
            return;
        }
        
        if (discountAmount <= 0) {
            alert('❌ Please enter a valid discount amount');
            $('#new-coupon-amount').focus();
            return;
        }
        
        // Disable button and show loading
        $('[data-modal-action="create_coupon"]')
            .prop('disabled', true)
            .text('Creating...');
        
        // Send AJAX request
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_create_quick_coupon',
                nonce: oj_editor_data.nonce,
                coupon_code: couponCode,
                discount_type: discountType,
                discount_amount: discountAmount,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + stripHtml(response.data.message) + '\n\nSwitching to Apply tab...');
                    
                    // Refresh the coupon modal to show the new coupon in available list
                    openCouponModal();
                    
                    // Switch to apply tab after modal loads
                    setTimeout(() => {
                        $('.oj-tab-btn[data-tab="apply"]').click();
                        // Pre-fill the coupon code input
                        $('#coupon-code-input').val(couponCode);
                    }, 500);
                    
                } else {
                    alert('❌ Error: ' + response.data.message);
                    
                    // Re-enable button
                    $('[data-modal-action="create_coupon"]')
                        .prop('disabled', false)
                        .text('Create Coupon');
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Connection error. Please try again.');
                
                // Re-enable button
                $('[data-modal-action="create_coupon"]')
                    .prop('disabled', false)
                    .text('Create Coupon');
            }
        });
    }
    
    // ========================================================================
    // PHASE 9: CARD REFRESH AFTER EDIT
    // ========================================================================
    
    /**
     * Refresh single order card after edit
     * Fetches fresh HTML from backend and replaces card in DOM
     * 
     * @param {number} orderId - Order ID to refresh
     */
    window.refreshOrderCard = function(orderId) {
        $.ajax({
            url: oj_editor_data.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_refresh_order_card',
                nonce: oj_editor_data.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success && response.data.card_html) {
                    // Find the existing card in the DOM
                    const $existingCard = $(`.oj-order-card[data-order-id="${orderId}"]`);
                    
                    if ($existingCard.length > 0) {
                        // Parse new card HTML
                        const $newCard = $(response.data.card_html);
                        
                        // Get the checkbox from both cards
                        const $oldCheckbox = $existingCard.find('.oj-order-checkbox');
                        const $newCheckbox = $newCard.find('.oj-order-checkbox');
                        
                        // Preserve checkbox state
                        const wasChecked = $oldCheckbox.prop('checked');
                        
                        // Enable and set checkbox state
                        $newCheckbox.prop('disabled', false);
                        if (wasChecked) {
                            $newCheckbox.prop('checked', true);
                        }
                        
                        // Add fade animation
                        $existingCard.fadeOut(200, function() {
                            // Replace card
                            $(this).replaceWith($newCard);
                            
                            // Fade in new card
                            $newCard.hide().fadeIn(300);
                        });
                    } else {
                    }
                } else {
                }
            },
            error: function(xhr, status, error) {
                // Fail silently - don't interrupt user workflow
            }
        });
    };
});

