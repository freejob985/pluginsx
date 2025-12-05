# Notification System Architecture

## 🎯 Overview
Real-time notification system for Orders Jet with clean encapsulation and reusability.

---

## 📁 File Structure

```
orders-jet-main/
│
├── includes/
│   ├── services/
│   │   └── class-orders-jet-notification-service.php ✅ EXISTS (Backend Logic)
│   │
│   ├── handlers/
│   │   └── class-orders-jet-notification-handler.php 🆕 NEW (AJAX Handlers)
│   │
│   └── components/
│       └── notification-center.php 🆕 NEW (Reusable UI Component)
│
├── assets/
│   ├── js/
│   │   └── oj-notifications.js 🆕 NEW (Frontend Logic)
│   │
│   ├── css/
│   │   └── oj-notifications.css 🆕 NEW (Notification Styles)
│   │
│   └── sounds/ 🆕 NEW (Optional)
│       ├── new-order.mp3
│       ├── order-ready.mp3
│       └── invoice-request.mp3
│
└── templates/
    └── admin/
        └── partials/
            └── notification-item.php 🆕 NEW (Single Notification Template)
```

---

## 🔄 What We Already Have (Reusable)

### ✅ Backend Service
**File:** `includes/services/class-orders-jet-notification-service.php`

**Existing Methods:**
- `send_order_notification($order)` - Send new order notifications
- `send_ready_notifications($order, $table_number)` - Send order ready notifications
- `store_dashboard_notification($data)` - Store notifications in database
- `send_email_notification($data)` - Email notifications (optional)

**Storage:**
- Uses WordPress options table: `oj_dashboard_notifications`
- Stores last 50 notifications
- Structure:
```php
array(
    'id' => 'unique_id',
    'order_id' => 123,
    'order_number' => '456',
    'table_number' => '5',
    'type' => 'table_order|order_ready|invoice_request',
    'read' => false,
    'created_at' => '2025-11-05 10:30:00',
    'timestamp' => '2025-11-05 10:30:00'
)
```

### ✅ Frontend Notification UI (Toast)
**File:** `assets/js/dashboard-express.js`

**Existing Function:**
```javascript
showExpressNotification(message, type = 'success')
```

**What it does:**
- Shows temporary toast messages (5 seconds)
- Types: success, error, info
- Auto-dismiss with close button

**Reusable:** ✅ Keep for quick feedback, but add persistent notification center

---

## 🆕 What We Need to Build

### 1. **Notification Handler (AJAX Backend)**
**File:** `includes/handlers/class-orders-jet-notification-handler.php`

**Purpose:** Handle AJAX requests for notification center

**Methods:**
```php
class Orders_Jet_Notification_Handler {
    
    // Get unread notifications for current user
    public function ajax_get_notifications()
    
    // Mark notification as read
    public function ajax_mark_notification_read()
    
    // Mark all notifications as read
    public function ajax_mark_all_notifications_read()
    
    // Get notification count (for badge)
    public function ajax_get_notification_count()
    
    // Clear old notifications
    public function ajax_clear_notifications()
}
```

**AJAX Actions:**
- `wp_ajax_oj_get_notifications`
- `wp_ajax_oj_mark_notification_read`
- `wp_ajax_oj_mark_all_notifications_read`
- `wp_ajax_oj_get_notification_count`

---

### 2. **Notification Center Component (Reusable UI)**
**File:** `includes/components/notification-center.php`

**Purpose:** Reusable PHP component for notification bell + dropdown

**Usage:**
```php
// In any template:
<?php 
if (function_exists('oj_render_notification_center')) {
    oj_render_notification_center();
}
?>
```

**What it renders:**
- Bell icon with badge count
- Dropdown panel with notification list
- "Mark all read" button
- Empty state

**Enqueues:**
- `oj-notifications.css`
- `oj-notifications.js`

---

### 3. **Notification JavaScript (Frontend Logic)**
**File:** `assets/js/oj-notifications.js`

**Purpose:** Handle all notification center interactions

**Class Structure:**
```javascript
class OJ_NotificationCenter {
    constructor(options) {
        this.bellElement = options.bellElement;
        this.panelElement = options.panelElement;
        this.badgeElement = options.badgeElement;
        this.listElement = options.listElement;
        this.soundEnabled = options.soundEnabled || true;
        this.autoRefresh = options.autoRefresh || 30; // seconds
        
        this.init();
    }
    
    // Initialize notification center
    init()
    
    // Fetch notifications from server
    fetchNotifications()
    
    // Render notifications in dropdown
    renderNotifications(notifications)
    
    // Update badge count
    updateBadgeCount(count)
    
    // Mark notification as read
    markAsRead(notificationId)
    
    // Mark all as read
    markAllAsRead()
    
    // Toggle dropdown panel
    togglePanel()
    
    // Play notification sound
    playSound(type)
    
    // Start auto-refresh polling
    startAutoRefresh()
    
    // Stop auto-refresh
    stopAutoRefresh()
    
    // Handle new notification (from Heartbeat API)
    handleNewNotification(notification)
}

// Initialize on page load
jQuery(document).ready(function($) {
    if ($('#ojNotificationBell').length) {
        window.ojNotificationCenter = new OJ_NotificationCenter({
            bellElement: '#ojNotificationBell',
            panelElement: '#ojNotificationPanel',
            badgeElement: '#ojNotificationBadge',
            listElement: '#ojNotificationList',
            soundEnabled: true,
            autoRefresh: 30
        });
    }
});
```

---

### 4. **Notification CSS (Styling)**
**File:** `assets/css/oj-notifications.css`

**Purpose:** All notification center styles

**Components:**
- `.oj-notification-center` - Container
- `.oj-notification-bell` - Bell button
- `.oj-notification-badge` - Unread count badge
- `.oj-notification-panel` - Dropdown panel
- `.oj-notification-item` - Single notification
- `.oj-notification-item.unread` - Unread notification
- `.oj-notification-empty` - Empty state

**Animations:**
- Badge pulse for new notifications
- Panel slide-in/out
- Notification fade-in

---

### 5. **Notification Item Template**
**File:** `templates/admin/partials/notification-item.php`

**Purpose:** Render single notification HTML

**Variables:**
```php
$notification = array(
    'id' => 'abc123',
    'type' => 'table_order',
    'order_id' => 123,
    'order_number' => '456',
    'table_number' => '5',
    'message' => 'New order from Table 5',
    'read' => false,
    'created_at' => '2025-11-05 10:30:00'
);
```

**Output:**
```html
<div class="oj-notification-item <?php echo $notification['read'] ? '' : 'unread'; ?>" 
     data-notification-id="<?php echo esc_attr($notification['id']); ?>">
    <div class="oj-notification-icon">
        <?php echo oj_get_notification_icon($notification['type']); ?>
    </div>
    <div class="oj-notification-content">
        <div class="oj-notification-message">
            <?php echo esc_html($notification['message']); ?>
        </div>
        <div class="oj-notification-meta">
            <span class="oj-notification-time">
                <?php echo oj_time_ago($notification['created_at']); ?>
            </span>
        </div>
    </div>
    <button class="oj-notification-mark-read" data-notification-id="<?php echo esc_attr($notification['id']); ?>">
        <span class="dashicons dashicons-yes"></span>
    </button>
</div>
```

---

## 🔗 Integration Points

### Where to Add Notification Center UI

1. **Orders Express** ✅ ALREADY ADDED
   - `templates/admin/dashboard-manager-orders-express.php`
   - Location: Header (next to title)

2. **Waiter Dashboard** 🔜 TO ADD
   - `templates/admin/dashboard-waiter.php`
   - Location: Header

3. **Manager Navigation** 🔜 TO ADD
   - `templates/admin/manager-navigation.php`
   - Location: Header right side (next to quick stats)

4. **Kitchen Dashboard** 🔜 TO ADD
   - `templates/admin/dashboard-kitchen.php`
   - Location: Header

### Where to Trigger Notifications

1. **New Order Placed**
   - File: `includes/handlers/class-orders-jet-order-submission-handler.php`
   - Method: After order creation
   - Notify: Kitchen + Manager

2. **Order Marked Ready**
   - File: `includes/class-orders-jet-ajax-handlers.php`
   - Method: `ajax_mark_order_ready()`
   - Notify: Waiter + Manager

3. **Guest Invoice Request**
   - File: `includes/handlers/class-orders-jet-invoice-request-handler.php`
   - Method: After invoice request
   - Notify: Waiter + Manager

4. **Kitchen Ready Status**
   - File: `includes/class-orders-jet-ajax-handlers.php`
   - Method: `finalize_kitchen_response()`
   - Notify: Waiter (if fully ready)

---

## 🔄 Real-Time Updates (Session 2)

### Option 1: WordPress Heartbeat API (Recommended)
**Pros:**
- Built into WordPress
- No additional server setup
- Works with all hosting
- 15-60 second intervals

**Implementation:**
```javascript
// In oj-notifications.js
jQuery(document).on('heartbeat-send', function(e, data) {
    data.oj_check_notifications = true;
    data.oj_last_notification_id = window.ojNotificationCenter.lastNotificationId;
});

jQuery(document).on('heartbeat-tick', function(e, data) {
    if (data.oj_new_notifications) {
        window.ojNotificationCenter.handleNewNotifications(data.oj_new_notifications);
    }
});
```

### Option 2: Server-Sent Events (SSE)
**Pros:**
- Real-time (instant)
- One-way communication (perfect for notifications)
- Lower overhead than WebSocket

**Cons:**
- Requires server configuration
- May not work on all hosting

### Option 3: WebSocket
**Pros:**
- True real-time
- Two-way communication

**Cons:**
- Complex setup
- Requires Node.js server
- Overkill for notifications

**Decision:** Start with Heartbeat API, upgrade to SSE if needed

---

## 📊 Notification Types & Icons

| Type | Icon | Color | Notify |
|------|------|-------|--------|
| `new_order` | 🆕 | Blue | Kitchen, Manager |
| `order_ready` | ✅ | Green | Waiter, Manager |
| `invoice_request` | 🔔 | Orange | Waiter, Manager |
| `order_completed` | 💰 | Green | Manager |
| `kitchen_food_ready` | 🍕 | Green | Manager, Waiter |
| `kitchen_beverage_ready` | 🥤 | Green | Manager, Waiter |
| `order_cancelled` | ❌ | Red | All |

---

## 🎵 Sound Alerts (Session 2)

### Sound Files
- `new-order.mp3` - Kitchen receives new order
- `order-ready.mp3` - Waiter notified order is ready
- `invoice-request.mp3` - Guest requests invoice

### User Preferences
Store in user meta:
```php
update_user_meta($user_id, 'oj_notification_sounds', 'enabled');
update_user_meta($user_id, 'oj_notification_sound_volume', 0.7);
```

### JavaScript Implementation
```javascript
playSound(type) {
    if (!this.soundEnabled) return;
    
    const audio = new Audio(ojNotificationsData.soundsUrl + type + '.mp3');
    audio.volume = ojNotificationsData.soundVolume || 0.7;
    audio.play().catch(e => console.log('Sound play failed:', e));
}
```

---

## 🔐 Security

### Nonces
All AJAX requests require nonces:
```php
wp_create_nonce('oj_notifications_nonce')
```

### Permissions
Check user capabilities:
```php
if (!current_user_can('access_oj_manager_dashboard') && 
    !current_user_can('access_oj_kitchen_dashboard') &&
    !current_user_can('access_oj_waiter_dashboard')) {
    wp_send_json_error('Unauthorized');
}
```

### Data Sanitization
All output escaped:
```php
esc_html($notification['message'])
esc_attr($notification['id'])
```

---

## 📝 Implementation Plan

### Session 1: Visual Foundation (2-3 hours)
✅ Step 1: Add notification center HTML to Orders Express
🔄 Step 2: Create `oj-notifications.css` with all styles
📋 Step 3: Create basic `oj-notifications.js` (UI only, no AJAX yet)
📋 Step 4: Create notification item template
📋 Step 5: Test visual appearance

### Session 2: Real-Time Features (3-4 hours)
📋 Step 6: Create `class-orders-jet-notification-handler.php`
📋 Step 7: Add AJAX methods to `oj-notifications.js`
📋 Step 8: Integrate WordPress Heartbeat API
📋 Step 9: Add sound alerts
📋 Step 10: Add notification center to all dashboards
📋 Step 11: Trigger notifications from order actions
📋 Step 12: Final testing

---

## 🎯 Success Criteria

- ✅ Notification bell shows unread count
- ✅ Dropdown shows last 20 notifications
- ✅ Notifications update in real-time (30s max delay)
- ✅ Sound alerts play for new notifications
- ✅ Mark as read functionality works
- ✅ Works on all dashboards (Manager, Kitchen, Waiter)
- ✅ Mobile responsive
- ✅ No performance impact on page load
- ✅ Graceful degradation if JavaScript disabled

---

## 🔧 Maintenance

### Clear Old Notifications
Run daily via WP-Cron:
```php
// Delete notifications older than 7 days
add_action('oj_daily_cleanup', 'oj_cleanup_old_notifications');
```

### Performance Monitoring
- Monitor `oj_dashboard_notifications` option size
- Keep max 50 notifications per user
- Use transients for temporary data

---

## 📚 Code Standards

- ✅ Follow WordPress coding standards
- ✅ Use strict typing in PHP (`declare(strict_types=1);`)
- ✅ Use ES6 classes in JavaScript
- ✅ Document all functions with PHPDoc/JSDoc
- ✅ Prefix all functions/classes with `oj_` or `OJ_`
- ✅ Use nonces for all AJAX requests
- ✅ Escape all output
- ✅ Use translation functions (`__()`, `_e()`)

---

## 🎨 Design Principles

1. **Encapsulation** - Each component is self-contained
2. **Reusability** - Notification center can be added anywhere
3. **Performance** - Lazy load, cache, optimize queries
4. **Accessibility** - ARIA labels, keyboard navigation
5. **Progressive Enhancement** - Works without JS (basic functionality)
6. **Mobile First** - Responsive design from the start

---

**Last Updated:** 2025-11-05  
**Status:** Planning Complete ✅  
**Next Step:** Implement Session 1 (Visual Foundation)

