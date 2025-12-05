/**
 * Orders Jet - Notification Center JavaScript
 * Handles all notification center interactions
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Notification Center Class
     */
    class OJ_NotificationCenter {
        constructor(options) {
            this.bellElement = $(options.bellElement);
            this.panelElement = $(options.panelElement);
            this.badgeElement = $(options.badgeElement);
            this.listElement = $(options.listElement);
            this.soundEnabled = options.soundEnabled || false;
            this.autoRefresh = options.autoRefresh || 30; // seconds
            
            this.notifications = [];
            this.unreadCount = 0;
            this.refreshTimer = null;
            this.isPanelOpen = false;
            this.isInitialLoad = true; // Track if this is the first load
            
            // Pusher realtime support
            this.pusher = null;
            this.pusherChannels = [];
            this.realtimeEnabled = false;
            
            this.init();
        }
        
        /**
         * Initialize notification center
         */
        init() {
            // Bind events
            this.bindEvents();
            
            // Initialize Pusher if enabled
            if (this.initPusher()) {
                this.realtimeEnabled = true;
            }
            
            // Load initial notifications
            this.fetchNotifications();
            
            // Start auto-refresh (only if Pusher is disabled)
            if (!this.realtimeEnabled) {
                this.startAutoRefresh();
            }
        }
        
        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;
            
            // Toggle panel on bell click
            this.bellElement.on('click', function(e) {
                e.stopPropagation();
                self.togglePanel();
            });
            
            // Close panel when clicking outside
            $(document).on('click', function(e) {
                if (self.isPanelOpen && 
                    !self.panelElement.is(e.target) && 
                    self.panelElement.has(e.target).length === 0 &&
                    !self.bellElement.is(e.target) &&
                    self.bellElement.has(e.target).length === 0) {
                    self.closePanel();
                }
            });
            
            // Mark all as read button
            $('#ojMarkAllRead').on('click', function(e) {
                e.preventDefault();
                self.markAllAsRead();
            });
            
            // Delegate click events for notification items (dynamically loaded)
            this.listElement.on('click', '.oj-notification-item', function(e) {
                if (!$(e.target).closest('.oj-notification-mark-read').length) {
                    const notificationId = $(this).data('notification-id');
                    self.markAsRead(notificationId);
                }
            });
            
            // Delegate click events for mark as read buttons
            this.listElement.on('click', '.oj-notification-mark-read', function(e) {
                e.stopPropagation();
                const notificationId = $(this).data('notification-id');
                self.markAsRead(notificationId);
            });
            
            // Delegate click events for claim table buttons
            this.listElement.on('click', '.oj-notification-claim-btn', function(e) {
                e.stopPropagation();
                const tableNumber = $(this).data('table-number');
                self.claimTable(tableNumber, $(this));
            });
        }
        
        /**
         * Toggle notification panel
         */
        togglePanel() {
            if (this.isPanelOpen) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        }
        
        /**
         * Open notification panel
         */
        openPanel() {
            this.panelElement.fadeIn(200);
            this.isPanelOpen = true;
            this.bellElement.addClass('active');
            
            // Refresh notifications when opening
            this.fetchNotifications();
        }
        
        /**
         * Close notification panel
         */
        closePanel() {
            this.panelElement.fadeOut(200);
            this.isPanelOpen = false;
            this.bellElement.removeClass('active');
        }
        
        /**
         * Fetch notifications from server
         */
        fetchNotifications() {
            const self = this;
            
            // Store old notification IDs to detect new ones
            const oldNotificationIds = this.notifications.map(n => n.id);
            
            $.ajax({
                url: ojNotificationsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_get_notifications',
                    nonce: ojNotificationsData.nonce,
                    _timestamp: Date.now() // Cache busting
                },
                success: function(response) {
                    if (response.success) {
                        const newNotifications = response.data.notifications || [];
                        
                        // Detect truly new notifications (not in old list)
                        const brandNewNotifications = newNotifications.filter(function(notification) {
                            return !oldNotificationIds.includes(notification.id);
                        });
                        
                        // Update state
                        self.notifications = newNotifications;
                        self.unreadCount = response.data.unread_count || 0;
                        
                        self.renderNotifications();
                        self.updateBadgeCount(self.unreadCount);
                        
                        // Show toast for new notifications (but not on initial load)
                        if (brandNewNotifications.length > 0 && !self.isInitialLoad) {
                            self.showNewNotificationToast(brandNewNotifications[0]);
                            
                            // Play sound
                            if (self.soundEnabled) {
                                self.playSound(brandNewNotifications[0].type);
                            }
                            
                            // REFRESH: Simple refresh on relevant notification types
                            // Types that trigger REFRESH + SOUND
                            const refreshWithSound = ['new_order', 'table_order', 'pickup_order', 'order_ready', 'invoice_request'];
                            
                            // Types that trigger REFRESH ONLY (no additional sound)
                            const refreshOnly = ['kitchen_food_ready', 'kitchen_beverage_ready'];
                            
                            const notifType = brandNewNotifications[0].type;
                            
                            if (refreshWithSound.includes(notifType)) {
                                self.refreshOrdersGrid();
                            } else if (refreshOnly.includes(notifType)) {
                                self.refreshOrdersGrid();
                            }
                        }
                        
                        // Mark initial load as complete after processing
                        self.isInitialLoad = false;
                    } else {
                        console.error('❌ Failed to load notifications:', response.data.message);
                        self.showError(response.data.message || ojNotificationsData.i18n.loadingError);
                        
                        // Mark initial load as complete even on error
                        self.isInitialLoad = false;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Notification fetch error:', error);
                    self.showError(ojNotificationsData.i18n.connectionError);
                    
                    // Mark initial load as complete even on error
                    self.isInitialLoad = false;
                }
            });
        }
        
        /**
         * Render notifications in dropdown
         */
        renderNotifications() {
            const self = this;
            
            if (this.notifications.length === 0) {
                this.listElement.html(this.getEmptyStateHTML());
                return;
            }
            
            let html = '';
            this.notifications.forEach(function(notification) {
                html += self.getNotificationItemHTML(notification);
            });
            
            this.listElement.html(html);
        }
        
        /**
         * Get HTML for single notification item
         */
        getNotificationItemHTML(notification) {
            const isUnread = !this.isNotificationRead(notification);
            const unreadClass = isUnread ? 'unread' : '';
            const icon = this.getNotificationIcon(notification.type);
            const message = this.formatNotificationMessage(notification);
            
            // Use pre-calculated time from PHP (CENTRALIZED TIME CALCULATION)
            const timeAgo = notification.time_ago || this.getTimeAgo(notification.created_at, notification.timestamp_unix);
            
            return `
                <div class="oj-notification-item ${unreadClass}" data-notification-id="${notification.id}">
                    <div class="oj-notification-icon">${icon}</div>
                    <div class="oj-notification-content">
                        <div class="oj-notification-message">${message}</div>
                        <div class="oj-notification-meta">
                            <span class="oj-notification-time">
                                <span class="dashicons dashicons-clock"></span>
                                ${timeAgo}
                            </span>
                        </div>
                    </div>
                    <div class="oj-notification-actions">
                        ${this.getNotificationActionButtons(notification, isUnread)}
                    </div>
                </div>
            `;
        }
        
        /**
         * Get action buttons for notification
         */
        getNotificationActionButtons(notification, isUnread) {
            let buttons = '';
            
            // Add claim button for unassigned table notifications (only for unread notifications)
            if (isUnread && this.shouldShowClaimButton(notification)) {
                const buttonText = notification.type === 'waiter_call' ? 'Respond' : 'Claim';
                const buttonTitle = notification.type === 'waiter_call' ? 
                    `Respond to guest call at Table ${notification.table_number}` : 
                    `Claim Table ${notification.table_number}`;
                    
                buttons += `
                    <button class="oj-notification-claim-btn" 
                            data-table-number="${notification.table_number}" 
                            title="${buttonTitle}">
                        <span class="dashicons dashicons-plus-alt"></span>
                        ${buttonText}
                    </button>
                `;
            }
            
            // Add mark as read button for unread notifications
            if (isUnread) {
                buttons += `
                    <button class="oj-notification-mark-read" 
                            data-notification-id="${notification.id}" 
                            title="${ojNotificationsData.i18n.markAsRead}">
                        <span class="dashicons dashicons-yes"></span>
                    </button>
                `;
            }
            
            return buttons;
        }
        
        /**
         * Check if notification should show claim button
         */
        shouldShowClaimButton(notification) {
            // Show claim button for new order and waiter call notifications from unassigned tables
            // We can detect this by checking if it's a table_order/pickup_order/waiter_call type notification
            // and if the current user is a waiter
            return ((notification.type === 'table_order' || notification.type === 'pickup_order' || notification.type === 'waiter_call') && 
                   notification.table_number && 
                   ojNotificationsData.userFunction === 'waiter' &&
                   !notification.table_claimed); // Don't show if already claimed
        }
        
        /**
         * Get empty state HTML
         */
        getEmptyStateHTML() {
            return `
                <div class="oj-notification-empty">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p>${ojNotificationsData.i18n.noNotifications}</p>
                </div>
            `;
        }
        
        /**
         * Show error message
         */
        showError(message) {
            this.listElement.html(`
                <div class="oj-notification-empty">
                    <span class="dashicons dashicons-warning"></span>
                    <p>${message}</p>
                </div>
            `);
        }
        
        /**
         * Update badge count
         */
        updateBadgeCount(count) {
            this.unreadCount = count;
            
            if (count > 0) {
                this.badgeElement.text(count).fadeIn(200);
                this.bellElement.addClass('has-notifications');
            } else {
                this.badgeElement.fadeOut(200);
                this.bellElement.removeClass('has-notifications');
            }
        }
        
        /**
         * Mark notification as read
         */
        markAsRead(notificationId) {
            const self = this;
            
            $.ajax({
                url: ojNotificationsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_mark_notification_read',
                    nonce: ojNotificationsData.nonce,
                    notification_id: notificationId,
                    _timestamp: Date.now() // Cache busting
                },
                success: function(response) {
                    if (response.success) {
                        // Update local notification state
                        const notification = self.notifications.find(n => n.id === notificationId);
                        if (notification) {
                            if (!notification.read_by) {
                                notification.read_by = [];
                            }
                            const userId = self.getCurrentUserId();
                            if (userId && !notification.read_by.includes(userId)) {
                                notification.read_by.push(userId);
                            }
                        }
                        
                        // Re-render notifications
                        self.renderNotifications();
                        
                        // Update badge count
                        self.unreadCount = Math.max(0, self.unreadCount - 1);
                        self.updateBadgeCount(self.unreadCount);
                        
                    } else {
                        console.error('❌ Failed to mark as read:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Mark as read error:', error);
                }
            });
        }
        
        /**
         * Mark all notifications as read
         */
        markAllAsRead() {
            const self = this;
            
            $.ajax({
                url: ojNotificationsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_mark_all_notifications_read',
                    nonce: ojNotificationsData.nonce,
                    _timestamp: Date.now() // Cache busting
                },
                success: function(response) {
                    if (response.success) {
                        // Update all notifications to read
                        const userId = self.getCurrentUserId();
                        self.notifications.forEach(function(notification) {
                            if (!notification.read_by) {
                                notification.read_by = [];
                            }
                            if (userId && !notification.read_by.includes(userId)) {
                                notification.read_by.push(userId);
                            }
                        });
                        
                        // Re-render and update badge
                        self.renderNotifications();
                        self.updateBadgeCount(0);
                        
                        // Show success message (optional)
                        if (typeof showExpressNotification === 'function') {
                            showExpressNotification(ojNotificationsData.i18n.markedAllRead, 'success');
                        }
                    } else {
                        console.error('❌ Failed to mark all as read:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Mark all as read error:', error);
                }
            });
        }
        
        /**
         * Claim table from notification
         */
        claimTable(tableNumber, buttonElement) {
            const self = this;
            
            // Show loading state
            const originalText = buttonElement.html();
            buttonElement.prop('disabled', true).html(`
                <span class="dashicons dashicons-update spin"></span>
                Claiming...
            `);
            
            $.ajax({
                url: ojNotificationsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_claim_table',
                    table_number: tableNumber,
                    nonce: ojNotificationsData.dashboardNonce || ojNotificationsData.nonce
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    if (response.success) {
                        // Show success state
                        buttonElement.html(`
                            <span class="dashicons dashicons-yes"></span>
                            Claimed!
                        `).removeClass('oj-notification-claim-btn').addClass('oj-notification-claimed');
                        
                        // Show success message
                        if (typeof showExpressNotification === 'function') {
                            showExpressNotification(`Table ${tableNumber} claimed successfully!`, 'success');
                        }
                        
                        // Refresh notifications after a short delay
                        setTimeout(function() {
                            self.fetchNotifications();
                            
                            // Refresh waiter dashboard if function exists
                            if (typeof refreshWaiterDashboard === 'function') {
                                refreshWaiterDashboard();
                            } else if (typeof window.refreshWaiterDashboard === 'function') {
                                window.refreshWaiterDashboard();
                            }
                        }, 1500);
                        
                    } else {
                        // Show error and restore button
                        buttonElement.prop('disabled', false).html(originalText);
                        
                        if (typeof showExpressNotification === 'function') {
                            showExpressNotification(response.data.message || 'Failed to claim table', 'error');
                        }
                        
                        console.error('❌ Failed to claim table:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Restore button on error
                    buttonElement.prop('disabled', false).html(originalText);
                    
                    // Detailed error logging
                    console.error('❌ Claim table AJAX error details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('XHR Status:', xhr.status);
                    console.error('XHR Response:', xhr.responseText);
                    console.error('Ready State:', xhr.readyState);
                    
                    let errorMessage = 'Connection error. Please try again.';
                    
                    // Provide more specific error messages
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please check your connection.';
                    } else if (status === 'error' && xhr.status === 0) {
                        errorMessage = 'Network error. Please check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Server endpoint not found. Please contact support.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error. Please try again later.';
                    }
                    
                    if (typeof showExpressNotification === 'function') {
                        showExpressNotification(errorMessage, 'error');
                    }
                    
                    console.error('❌ Claim table error:', error);
                }
            });
        }
        
        /**
         * Initialize Pusher realtime connection
         */
        initPusher() {
            const realtimeConfig = ojNotificationsData.realtime || {};
            
            if (!realtimeConfig.enabled || typeof Pusher === 'undefined') {
                return false;
            }
            
            try {
                const pusherOptions = {
                    cluster: realtimeConfig.cluster,
                    forceTLS: realtimeConfig.options?.forceTLS !== false,
                    authEndpoint: realtimeConfig.authEndpoint,
                    auth: {
                        headers: {
                            'X-WP-Nonce': realtimeConfig.authNonce
                        }
                    }
                };
                
                this.pusher = new Pusher(realtimeConfig.key, pusherOptions);
                
                // Subscribe to all user channels
                const channels = realtimeConfig.channels || [];
                channels.forEach((channelName) => {
                    try {
                        const channel = this.pusher.subscribe(channelName);
                        this.pusherChannels.push(channel);
                        
                        // Listen for notification events
                        channel.bind('oj.notification', (data) => {
                            this.handlePusherNotification(data.notification);
                        });
                        
                        console.log('✅ Subscribed to Pusher channel:', channelName);
                    } catch (e) {
                        console.error('❌ Failed to subscribe to channel:', channelName, e);
                    }
                });
                
                // Handle connection events
                this.pusher.connection.bind('connected', () => {
                    console.log('✅ Pusher connected');
                });
                
                this.pusher.connection.bind('disconnected', () => {
                    console.warn('⚠️ Pusher disconnected - falling back to AJAX polling');
                    // Fallback to AJAX polling if Pusher disconnects
                    if (!this.refreshTimer) {
                        this.startAutoRefresh();
                    }
                });
                
                this.pusher.connection.bind('error', (err) => {
                    console.error('❌ Pusher connection error:', err);
                    // Fallback to AJAX polling on error
                    if (!this.refreshTimer) {
                        this.startAutoRefresh();
                    }
                });
                
                return true;
            } catch (e) {
                console.error('❌ Pusher initialization failed:', e);
                return false;
            }
        }
        
        /**
         * Handle notification from Pusher
         */
        handlePusherNotification(notification) {
            // Check if notification already exists
            const existingIndex = this.notifications.findIndex(n => n.id === notification.id);
            
            if (existingIndex >= 0) {
                // Update existing notification
                this.notifications[existingIndex] = notification;
            } else {
                // Add new notification at the beginning
                this.notifications.unshift(notification);
            }
            
            // Update unread count
            if (!this.isNotificationRead(notification)) {
                this.unreadCount++;
            }
            
            // Update UI
            this.renderNotifications();
            this.updateBadgeCount(this.unreadCount);
            
            // Play sound if enabled (only for new notifications)
            if (existingIndex < 0 && this.soundEnabled) {
                this.playSound(notification.type);
            }
            
            // Show toast notification (only for new notifications)
            if (existingIndex < 0) {
                this.showNewNotificationToast(notification);
                
                // Refresh orders grid if needed
                const refreshTypes = ['new_order', 'table_order', 'pickup_order', 'order_ready', 
                                     'invoice_request', 'kitchen_food_ready', 'kitchen_beverage_ready'];
                if (refreshTypes.includes(notification.type)) {
                    this.refreshOrdersGrid();
                }
            }
        }
        
        /**
         * Start auto-refresh timer (fallback when Pusher is disabled)
         */
        startAutoRefresh() {
            const self = this;
            
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
            }
            
            this.refreshTimer = setInterval(function() {
                if (!self.isPanelOpen && !self.realtimeEnabled) {
                    // Only auto-refresh when panel is closed and Pusher is disabled
                    self.fetchNotifications();
                }
            }, this.autoRefresh * 1000);
        }
        
        /**
         * Stop auto-refresh timer
         */
        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        }
        
        /**
         * Show toast notification for new notification
         */
        showNewNotificationToast(notification) {
            const message = this.formatNotificationMessage(notification);
            
            // Use showExpressNotification if available
            if (typeof showExpressNotification === 'function') {
                showExpressNotification(`🔔 ${message}`, 'info');
            }
        }
        
        /**
         * Handle new notification (legacy method - kept for compatibility)
         * Now primarily used by Pusher, but kept for backward compatibility
         */
        handleNewNotification(notification) {
            // Use Pusher handler if Pusher is enabled
            if (this.realtimeEnabled) {
                this.handlePusherNotification(notification);
                return;
            }
            
            // Fallback to original logic for non-Pusher scenarios
            // Add to notifications array
            this.notifications.unshift(notification);
            
            // Update unread count
            if (!this.isNotificationRead(notification)) {
                this.unreadCount++;
            }
            
            // Update UI
            this.renderNotifications();
            this.updateBadgeCount(this.unreadCount);
            
            // Play sound if enabled
            if (this.soundEnabled) {
                this.playSound(notification.type);
            }
            
            // Show toast notification
            this.showNewNotificationToast(notification);
        }
        
        /**
         * Cleanup Pusher connection
         */
        disconnectPusher() {
            if (this.pusher) {
                this.pusherChannels.forEach((channel) => {
                    this.pusher.unsubscribe(channel.name);
                });
                this.pusher.disconnect();
                this.pusher = null;
                this.pusherChannels = [];
            }
        }
        
        /**
         * Play notification sound
         */
        playSound(type) {
            if (!this.soundEnabled) return;
            
            try {
                const soundFile = this.getSoundFile(type);
                const audio = new Audio(ojNotificationsData.soundsUrl + soundFile);
                audio.volume = ojNotificationsData.soundVolume || 0.7;
                audio.play().catch(e => {});
            } catch (e) {
                console.error('Sound error:', e);
            }
        }
        
        /**
         * Get sound file for notification type
         */
        getSoundFile(type) {
            const soundMap = {
                'new_order': 'new-order.mp3',
                'table_order': 'new-order.mp3',
                'pickup_order': 'new-order.mp3',
                'order_ready': 'order-ready.mp3',
                'kitchen_food_ready': 'order-ready.mp3',
                'kitchen_beverage_ready': 'order-ready.mp3',
                'invoice_request': 'invoice-request.mp3',
                'waiter_call': 'call-waiter.mp3'
            };
            
            return soundMap[type] || 'new-order.mp3';
        }
        
        /**
         * Check if notification is read by current user
         */
        isNotificationRead(notification) {
            const userId = this.getCurrentUserId();
            const readBy = notification.read_by || [];
            return readBy.includes(userId);
        }
        
        /**
         * Get current user ID (from WordPress)
         */
        getCurrentUserId() {
            // Get from PHP localized data (most reliable)
            if (typeof ojNotificationsData !== 'undefined' && ojNotificationsData.userId) {
                return parseInt(ojNotificationsData.userId);
            }
            
            // Fallback: Try to get from WordPress global
            if (typeof window.wp !== 'undefined' && window.wp.data) {
                try {
                    const user = window.wp.data.select('core').getCurrentUser();
                    return user ? user.id : null;
                } catch (e) {
                    // Continue to next fallback
                }
            }
            
            // Fallback: parse from body class
            const bodyClass = $('body').attr('class');
            const match = bodyClass ? bodyClass.match(/user-(\d+)/) : null;
            return match ? parseInt(match[1]) : null;
        }
        
        /**
         * Get notification icon
         */
        getNotificationIcon(type) {
            const icons = {
                'new_order': '🆕',
                'table_order': '🍽️',
                'pickup_order': '📦',
                'order_ready': '✅',
                'kitchen_food_ready': '🍕',
                'kitchen_beverage_ready': '🥤',
                'invoice_request': '🔔',
                'order_completed': '💰',
                'order_cancelled': '❌'
            };
            
            return icons[type] || '📋';
        }
        
        /**
         * Format notification message
         */
        formatNotificationMessage(notification) {
            const type = notification.type || '';
            const orderNumber = notification.order_number || '';
            const tableNumber = notification.table_number || '';
            
            // Use pre-formatted message if available
            if (notification.message) {
                return notification.message;
            }
            
            // Format based on type
            switch (type) {
                case 'new_order':
                case 'table_order':
                    if (tableNumber) {
                        return `New order #${orderNumber} for Table ${tableNumber}`;
                    }
                    return `New order #${orderNumber}`;
                    
                case 'pickup_order':
                    return `New pickup order #${orderNumber}`;
                    
                case 'order_ready':
                    return `Order #${orderNumber} is ready`;
                    
                case 'kitchen_food_ready':
                    return `Food ready for order #${orderNumber}`;
                    
                case 'kitchen_beverage_ready':
                    return `Beverages ready for order #${orderNumber}`;
                    
                case 'invoice_request':
                    return `Invoice requested for Table ${tableNumber}`;
                    
                case 'order_completed':
                    return `Order #${orderNumber} completed`;
                    
                case 'order_cancelled':
                    return `Order #${orderNumber} cancelled`;
                    
                default:
                    return 'New notification';
            }
        }
        
        /**
         * Get time ago string (matching order cards format)
         */
        getTimeAgo(datetime, timestamp_unix) {
            let time;
            
            // Use Unix timestamp if available (more reliable)
            if (timestamp_unix) {
                time = parseInt(timestamp_unix) * 1000; // Convert to milliseconds
            } else {
                // Fallback: parse MySQL datetime string
                // Assume datetime is in server timezone, convert to local
                time = new Date(datetime.replace(/-/g, '/').replace(' ', 'T')).getTime();
            }
            
            const now = Date.now();
            const diff = Math.abs(Math.floor((now - time) / 1000)); // Use abs() to handle timezone issues
            
            if (diff < 60) {
                return diff + ' secs ago';
            } else if (diff < 3600) {
                const minutes = Math.floor(diff / 60);
                return minutes + (minutes === 1 ? ' min ago' : ' mins ago');
            } else if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return hours + (hours === 1 ? ' hour ago' : ' hours ago');
            } else {
                const days = Math.floor(diff / 86400);
                return days + (days === 1 ? ' day ago' : ' days ago');
            }
        }
        
        /**
         * Refresh orders grid - Simple approach
         * Just fetch current page and update grid content
         */
        refreshOrdersGrid() {
            const $grid = $('.oj-orders-grid');
            if ($grid.length === 0) {
                return; // Not on orders page
            }
            
            const $counts = $('.oj-filter-count');
            
            // Show loading state
            $grid.css('opacity', '0.5');
            
            // Fetch current page HTML
            $.ajax({
                url: window.location.href,
                type: 'GET',
                success: function(html) {
                    const $newPage = $(html);
                    
                    // Update grid content
                    const $newGrid = $newPage.find('.oj-orders-grid');
                    if ($newGrid.length) {
                        $grid.html($newGrid.html());
                    }
                    
                    // Update filter counts
                    const $newCounts = $newPage.find('.oj-filter-count');
                    $counts.each(function(index) {
                        if ($newCounts[index]) {
                            $(this).text($($newCounts[index]).text());
                        }
                    });
                    
                    // Update kitchen count if exists
                    const $newKitchenCount = $newPage.find('.oj-kitchen-count');
                    if ($newKitchenCount.length) {
                        $('.oj-kitchen-count').text($newKitchenCount.text());
                    }
                },
                error: function() {
                    console.error('Failed to refresh orders grid');
                },
                complete: function() {
                    $grid.css('opacity', '1');
                }
            });
        }
    }
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('#ojNotificationBell').length) {
            window.ojNotificationCenter = new OJ_NotificationCenter({
                bellElement: '#ojNotificationBell',
                panelElement: '#ojNotificationPanel',
                badgeElement: '#ojNotificationBadge',
                listElement: '#ojNotificationList',
                soundEnabled: ojNotificationsData.soundEnabled,
                autoRefresh: ojNotificationsData.autoRefresh
            });
            
        }
    });
    
})(jQuery);

