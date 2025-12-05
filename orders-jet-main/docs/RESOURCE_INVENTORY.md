# Resource Inventory

**Quick reference guide to reusable components, patterns, and code examples**

---

## 🚀 **Quick Reference**

### **Most Used Resources**
- **CSS:** `manager-orders-cards.css` + `dashboard-express.css`
- **JavaScript:** `orders-master.js` (AJAX patterns)
- **PHP:** `Orders_Jet_Admin_Dashboard` (bulk operations)
- **Templates:** `orders-master.php` structure

### **Critical Performance Classes**
- `Orders_Jet_Logger` - Controlled logging
- `Orders_Jet_Addon_Calculator` - Bulk calculations
- `Orders_Master_Query_Builder` - HPOS-compatible queries
- `Orders_Jet_Amount_Filter_Service` - Amount filtering logic
- `Orders_Jet_Filter_URL_Builder` - URL parameter handling
- `Orders_Jet_Kitchen_Filter_Service` - Kitchen order filtering and item detection

### **Essential Helper Functions**
- `oj_debug_log()` - Debug logging
- `oj_master_prepare_order_data()` - Order data prep
- `oj_express_get_action_buttons()` - Action buttons

---

## 📋 **Table of Contents**

1. [🎨 CSS Design System](#css-design-system) - Cards, badges, buttons, layouts
2. [⚡ JavaScript Patterns](#javascript-patterns) - AJAX, search, UI updates
3. [🏗️ PHP Service Classes](#php-service-classes) - Core business logic
4. [📊 Performance Patterns](#performance-patterns) - Bulk operations, caching
5. [📝 Template Structures](#template-structures) - Page layouts, partials
6. [👨‍🍳 Kitchen Interface System](#kitchen-interface-system) - Kitchen-specific components
7. [🔧 Helper Functions](#helper-functions) - Utilities and shortcuts

---

## 🎨 **CSS Design System**

### **Primary Stylesheets**
```css
📁 assets/css/
├── manager-orders-cards.css    ✅ MASTER DESIGN SYSTEM
│   └── Cards, badges, buttons, typography, colors
├── dashboard-express.css       ✅ LAYOUT SYSTEM
│   └── Grid, filters, pagination, search, responsive
├── orders-master-toolbar.css   ✅ TOOLBAR SYSTEM
│   └── Single-row toolbar, mobile-first responsive
├── kitchen-express.css         ✅ KITCHEN INTERFACE SYSTEM
│   └── Invoice-style cards, monospace fonts, no prices, highlighting
└── filters-slide-panel.css     ✅ ADVANCED FILTERS SYSTEM
    └── Slide-out panel, filter builder, debug panel
```

### **Essential CSS Classes**
```css
/* CARDS - Use for all card layouts */
.oj-order-card              /* Main card container */
.oj-card-header             /* Card header section */
.oj-card-body               /* Main content area */
.oj-card-footer             /* Action buttons area */

/* BADGES - Use for all status indicators */
.oj-status-badge            /* Base badge styling */
.oj-badge-active            /* Processing/Active status (blue) */
.oj-badge-ready             /* Ready status (green) */
.oj-badge-completed         /* Completed status (gray) */
.oj-badge-pending           /* Pending status (orange) */

/* BUTTONS - Use for all actions */
.oj-action-btn              /* Base action button */
.oj-btn-primary             /* Primary action (blue) */
.oj-btn-success             /* Success action (green) */
.oj-btn-danger              /* Danger action (red) */

/* LAYOUT - Use for all admin pages */
.oj-orders-grid             /* Main grid container */
.oj-filter-tabs             /* Filter tab container */
.oj-search-wrapper          /* Search input wrapper */
.oj-pagination-wrapper      /* Pagination container */

/* TOOLBAR - Single-row responsive toolbar */
.oj-master-toolbar          /* Main toolbar container */
.oj-toolbar-form            /* Toolbar form wrapper */
.oj-toolbar-group           /* Individual control groups */
.oj-toolbar-select          /* Dropdown controls */
.oj-toolbar-input           /* Input controls */
.oj-custom-dates            /* Custom date range inputs */
.oj-order-type-group        /* Order type filter group */
.oj-kitchen-type-group      /* Kitchen type filter group */
```

---

## ⚡ **JavaScript Patterns**

### **Core File: `orders-master.js`**

#### **AJAX Pattern (Copy This)**
```javascript
function loadOrdersWithFilter(filter, page, search) {
    $('#oj-orders-grid').addClass('oj-loading');
    
    $.ajax({
        url: ojAjax.ajaxurl,
        type: 'POST',
        data: {
            action: 'oj_get_orders_master_filtered',
            nonce: ojAjax.nonce,
            filter: filter,
            page: page,
            search: search
        },
        success: function(response) {
            if (response.success) {
                updateOrdersGrid(response.data.orders);
                updatePagination(response.data.pagination, filter);
            }
        },
        complete: function() {
            $('#oj-orders-grid').removeClass('oj-loading');
        }
    });
}
```

#### **Search Pattern (Copy This)**
```javascript
let searchTimeout;
function performSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const searchTerm = escapeHtml($('#oj-search-input').val().trim());
        const currentFilter = $('.oj-filter-btn.active').data('filter');
        loadOrdersWithFilter(currentFilter, 1, searchTerm);
    }, 300);
}
```

#### **Essential Utilities**
```javascript
// XSS Protection
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// User Notifications
function showNotification(message, type) {
    const notification = $(`
        <div class="oj-notification oj-notification-${type}">
            ${escapeHtml(message)}
        </div>
    `);
    $('body').append(notification);
    setTimeout(() => notification.fadeOut(() => notification.remove()), 5000);
}
```

---

## 🏗️ **PHP Service Classes**

### **Core Controllers**

#### **Orders_Jet_Admin_Dashboard** (Master Controller)
```php
// Location: includes/class-orders-jet-admin-dashboard.php

// ✅ Main data method - Use this pattern
public function get_orders_master_data($user_role, $user_id, $filter, $page, $per_page, $search) {
    // Intelligent caching + HPOS compatibility + bulk operations
}

// ✅ Bulk operations - ALWAYS use these
public function get_orders_meta_bulk($order_ids) { /* Single query for all meta */ }
public function get_orders_items_bulk($order_ids) { /* Single query for all items */ }
public function prepare_orders_master_data_bulk($orders) { /* NO N+1 queries */ }
```

#### **Orders_Jet_Kitchen_Service** (Business Logic)
```php
// Location: includes/services/class-orders-jet-kitchen-service.php

public function get_kitchen_readiness_status($order) { /* Kitchen status logic */ }
public function get_kitchen_status_badge($order) { /* Badge HTML generation */ }
public function determine_kitchen_type($product_id) { /* Food vs Beverage */ }
```

#### **Orders_Jet_Kitchen_Filter_Service** (Kitchen Filtering)
```php
// Location: includes/services/class-orders-jet-kitchen-filter-service.php

public function get_order_statuses_for_user($is_kitchen_user) { /* Get appropriate statuses */ }
public function should_show_order_in_kitchen($order, $order_kitchen_type, $user_kitchen_type) { /* Complete filtering logic */ }
public function filter_orders_for_kitchen($orders, $user_kitchen_type) { /* Filter orders by kitchen type */ }
public function order_has_relevant_items($order, $user_kitchen_type) { /* Check if order has relevant items */ }
public function get_item_kitchen_type($item) { /* Detect item kitchen type */ }
```

#### **Orders_Jet_Amount_Filter_Service** (Amount Filtering)
```php
// Location: includes/services/class-orders-jet-amount-filter-service.php

// ✅ Main filtering method
public function order_matches_amount_filter($order, $filter_type, $filter_value, $filter_min, $filter_max) {
    // Handles: equals, less_than, greater_than, between
    // Uses WooCommerce native $order->get_total()
    // Float precision handling with 0.01 tolerance
}

// ✅ Parameter validation
public function validate_amount_filter_params($params) {
    // Sanitizes and validates all amount filter parameters
    // Handles range logic validation (min/max swap)
}

// ✅ Human-readable descriptions
public function get_amount_filter_description($filter_type, $value, $min, $max) {
    // Returns: "Amount between $25.00 and $100.00"
}
```

#### **Orders_Jet_Ajax_Handlers** (AJAX Framework)
```php
// Location: includes/class-orders-jet-ajax-handlers.php

// ✅ AJAX handler template - Copy this structure
public function ajax_get_orders_master_filtered() {
    try {
        check_ajax_referer('oj_nonce', 'nonce');
        
        $filter = sanitize_key($_POST['filter'] ?? 'all');
        $page = absint($_POST['page'] ?? 1);
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        // Get data using optimized methods
        $data = $dashboard->get_orders_master_data($user_role, $user_id, $filter, $page, 24, $search);
        
        wp_send_json_success($data);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'An error occurred']);
    }
}
```

### **Performance Classes (CRITICAL)**

#### **Orders_Jet_Logger** (Controlled Logging)
```php
// Global helpers - Use these everywhere
oj_debug_log($message, $context, $data);    // Debug only
oj_error_log($message, $context, $data);    // Production errors
oj_perf_log($message, $time_ms, $context);  // Performance tracking
```

#### **Orders_Jet_Addon_Calculator** (Bulk Calculations)
```php
// ✅ Pre-calculate before loops - REQUIRED pattern
Orders_Jet_Addon_Calculator::precalculate_addon_totals($order_ids);

// ✅ Use in loops - NO calculations
$addon_total = Orders_Jet_Addon_Calculator::get_order_addon_total($order_id);
```

---

## 📊 **Performance Patterns**

### **🚫 FORBIDDEN (Never Use)**
```php
// ❌ N+1 Queries
foreach ($orders as $order) {
    $meta = $order->get_meta('key'); // Query per order!
}

// ❌ Direct Logging
error_log('Debug message'); // Floods production logs

// ❌ Expensive Loops
foreach ($orders as $order) {
    $total = $this->calculate_total($order); // Expensive calculation
}
```

### **✅ REQUIRED (Always Use)**
```php
// ✅ Bulk Operations
$order_ids = array_column($orders, 'id');
$meta_data = $this->get_orders_meta_bulk($order_ids); // Single query
Orders_Jet_Addon_Calculator::precalculate_addon_totals($order_ids);

// ✅ Controlled Logging
oj_debug_log('Message', 'CONTEXT', ['data' => $value]);

// ✅ Caching
$cache_key = 'oj_data_' . md5($params);
$data = get_transient($cache_key) ?: $this->expensive_operation();
set_transient($cache_key, $data, 60);
```

### **Cache Duration Guidelines**
```php
$cache_durations = [
    'dashboard_filters' => 60,    // 1 minute (frequently changing)
    'table_orders' => 30,         // 30 seconds (real-time updates)
    'menu_data' => 300,           // 5 minutes (rarely changes)
    'invoice_data' => 120         // 2 minutes (moderate changes)
];
```

---

## 📝 **Template Structures**

### **Page Template Pattern**
```php
<?php
// ✅ Standard page structure - Copy this
// Location: templates/admin/orders-master.php

// 1. Load dependencies
require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-master-helpers.php';

// 2. Initialize services
$kitchen_service = new Orders_Jet_Kitchen_Service();

// 3. Get data
$query_builder = new Orders_Master_Query_Builder($_GET);
$orders = $query_builder->get_orders();
$pagination = $query_builder->get_pagination_data();
?>

<!-- 4. HTML Structure -->
<div class="wrap oj-dashboard">
    <h1><?php echo esc_html__('Page Title', 'orders-jet'); ?></h1>
    
    <!-- Search & Filters -->
    <?php include 'partials/search-form.php'; ?>
    <?php include 'partials/filter-tabs.php'; ?>
    
    <!-- Main Content -->
    <div class="oj-orders-grid" id="oj-orders-grid">
        <!-- Dynamic content via AJAX -->
    </div>
    
    <!-- Pagination -->
    <?php include 'partials/pagination.php'; ?>
</div>
```

### **Modular Partials**
```php
// ✅ Reusable UI components
templates/admin/partials/
├── orders-master-search-form.php      // Search input + sort controls
├── orders-master-filter-tabs.php      // Filter tabs with counts
├── orders-master-date-filter.php      // Date range picker
└── orders-master-pagination.php       // Pagination controls
```

---

## 👨‍🍳 **Kitchen Interface System**

### **Kitchen User Management**
```php
// Location: includes/class-orders-jet-capabilities.php
// WordPress-native role system with kitchen specialization

// ✅ Check user function
$user_function = oj_get_user_function(); // 'kitchen', 'manager', 'waiter'

// ✅ Get kitchen specialization
$kitchen_type = oj_get_kitchen_specialization(); // 'food', 'beverages', 'both'

// ✅ Role assignment (Editor + meta)
$user = new WP_User($user_id);
$user->set_role('editor');
update_user_meta($user_id, '_oj_function', 'kitchen');
update_user_meta($user_id, '_oj_kitchen_type', 'food');
```

### **Kitchen Order Filtering**
```php
// Location: includes/services/class-orders-jet-kitchen-filter-service.php
// Reusable kitchen filtering logic

$kitchen_filter_service = new Orders_Jet_Kitchen_Filter_Service();

// ✅ Filter orders for kitchen users
$filtered_orders = $kitchen_filter_service->filter_orders_for_kitchen($orders, $user_kitchen_type);

// ✅ Check if order has relevant items
$has_relevant_items = $kitchen_filter_service->order_has_relevant_items($order, 'food');

// ✅ Get appropriate order statuses
$statuses = $kitchen_filter_service->get_order_statuses_for_user($is_kitchen_user);
// Kitchen: ['wc-processing'] | Manager: ['wc-pending', 'wc-processing']

// ✅ Detect item kitchen type
$item_kitchen_type = $kitchen_filter_service->get_item_kitchen_type($item);
// Returns: 'food', 'beverages', or 'mixed'
```

### **Kitchen Card Template**
```php
// Location: templates/admin/partials/kitchen-order-card.php
// Invoice-style kitchen cards with item highlighting

// ✅ Automatic kitchen detection
$user_function = oj_get_user_function();
if ($user_function === 'kitchen') {
    include __DIR__ . '/kitchen-order-card.php';
    return;
}

// ✅ Features:
// - Invoice-style layout (monospace font)
// - Item highlighting (food highlighted, beverages dimmed)
// - No prices displayed anywhere
// - Clean add-ons display (no price information)
// - Smart variations (no redundant attributes)
// - Single action button per kitchen type
// - Product status filtering (no draft products)
```

### **Kitchen CSS System**
```css
/* Location: assets/css/kitchen-express.css */
/* Invoice-style kitchen interface */

.oj-kitchen-card {
    font-family: 'Courier New', monospace; /* Invoice style */
    border: 2px solid #333;
    background: white;
}

.oj-kitchen-item.highlighted {
    background: #fff3cd; /* Food items highlighted */
    border-left: 4px solid #ffc107;
}

.oj-kitchen-item.dimmed {
    opacity: 0.5; /* Beverage items dimmed in food kitchen */
    color: #6c757d;
}

.oj-kitchen-addons .oj-kitchen-addon {
    background: #e3f2fd;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    /* No prices - kitchen-appropriate */
}
```

### **Kitchen JavaScript Integration**
```javascript
// Location: assets/js/dashboard-express.js
// Kitchen card removal on action

// ✅ Kitchen detection
const isKitchenUser = $('body').hasClass('oj-kitchen-user') || $('.oj-kitchen-card').length > 0;

// ✅ Kitchen workflow
if (isKitchenUser) {
    // Remove card immediately after marking ready
    $card.fadeOut(300, function() {
        $(this).remove();
        // Update header count
        const $counter = $('.oj-count-text');
        const currentCount = parseInt($counter.text().match(/\d+/)[0]) - 1;
        $counter.text(currentCount + ' orders');
    });
    showExpressNotification(`✅ Order #${orderId} marked ready!`, 'success');
    return; // Exit early - no manager processing
}
```

### **Kitchen Interface Features**
- ✅ **WordPress-native roles** (Editor + meta instead of custom roles)
- ✅ **Kitchen specialization** (food, beverages, both)
- ✅ **Item-level filtering** (smart name detection + meta fields)
- ✅ **Invoice-style cards** (monospace, no prices, highlighting)
- ✅ **Immediate card removal** (smooth UX for kitchen staff)
- ✅ **Status-based queries** (kitchen sees only processing orders)
- ✅ **Complete add-on support** (3 formats: structured, WC, string)
- ✅ **Smart variation display** (no redundant attributes)
- ✅ **Production-ready** (clean, fast, reliable)

---

## 🔧 **Helper Functions**

### **Order Data Helpers**
```php
// Location: includes/helpers/orders-master-helpers.php

// ✅ Order preparation
oj_master_prepare_order_data($order, $services);

// ✅ Badge generation
oj_express_get_optimized_badge_data($order, $kitchen_service);

// ✅ Action buttons
oj_express_get_action_buttons($order, $user);

// ✅ URL building
oj_build_filter_url($base_url, $params);
```

### **Filter URL Builder Helper**
```php
// Location: includes/helpers/class-orders-jet-filter-url-builder.php

// ✅ Build filter URLs with parameter preservation
Orders_Jet_Filter_URL_Builder::build_filter_url($new_params, $preserve_params, $base_url);

// ✅ Build reset URL with defaults
Orders_Jet_Filter_URL_Builder::build_reset_url($keep_params);

// ✅ Get current filter parameters
Orders_Jet_Filter_URL_Builder::get_current_filter_params($filter_keys);

// ✅ Check if filters are active
Orders_Jet_Filter_URL_Builder::has_active_filters($current_params);

// ✅ Build sort URLs for clickable links
Orders_Jet_Filter_URL_Builder::build_sort_url($orderby, $current_orderby, $current_order);

// ✅ Get sort arrows and CSS classes
Orders_Jet_Filter_URL_Builder::get_sort_arrow($orderby, $current_orderby, $current_order);
Orders_Jet_Filter_URL_Builder::get_sort_class($orderby, $current_orderby);
```

### **Query Builder Pattern**
```php
// ✅ Reusable for any listing page
$query_builder = new Orders_Master_Query_Builder($_GET);

$orders = $query_builder->get_orders();              // Filtered/sorted/paginated
$filter_counts = $query_builder->get_filter_counts(); // Cached counts
$pagination = $query_builder->get_pagination_data(); // Complete pagination

// Features: HPOS-compatible, timezone-aware, cached, searchable
```

---

## 🎯 **Usage Examples**

### **Creating New Admin Page**
1. **Copy** `orders-master.php` structure
2. **Reuse** CSS classes from design system
3. **Extend** JavaScript patterns from `orders-master.js`
4. **Leverage** service classes for data operations
5. **Use** helper functions for common tasks

### **Performance Checklist**
- [ ] Use bulk query methods (no N+1 queries)
- [ ] Implement caching for expensive operations
- [ ] Pre-calculate data outside loops
- [ ] Use controlled logging functions (`oj_debug_log`)
- [ ] Limit query results (max 1000)

### **Security Checklist**
- [ ] Sanitize inputs (`sanitize_text_field`, `absint`)
- [ ] Escape outputs (`esc_html`, `esc_attr`)
- [ ] Verify nonces (`check_ajax_referer`)
- [ ] Check permissions (`current_user_can`)

---

## 📚 **File Locations Reference**

### **CSS Files**
- `assets/css/manager-orders-cards.css` - Master design system
- `assets/css/dashboard-express.css` - Layout system

### **JavaScript Files**
- `assets/js/orders-master.js` - Master interaction patterns
- `assets/js/admin.js` - Global admin functionality
- `assets/js/filters-slide-panel.js` - Advanced filters interface (main controller)
- `assets/js/oj-saved-views.js` - Saved views management (encapsulated class)

#### **JavaScript Architecture Pattern**
```javascript
// Main Controller Class (filters-slide-panel.js)
class OJFiltersPanel {
    constructor() {
        this.savedViews = null; // Lazy initialization
    }
    
    initializeSavedViews() {
        if (!this.savedViews) {
            this.savedViews = new OJSavedViews(this); // Dependency injection
        }
    }
    
    saveView() {
        this.initializeSavedViews();
        this.savedViews.saveCurrentFilters(); // Delegation
    }
}

// Specialized Class (oj-saved-views.js)
class OJSavedViews {
    constructor(filtersPanel) {
        this.filtersPanel = filtersPanel; // Reference to parent
        this.selectedViewId = null;
    }
    
    // Encapsulated functionality:
    // - loadSavedViews(), displaySavedViews(), createSavedViewHTML()
    // - selectSavedView(), loadSavedView(), renameSavedView(), deleteSavedView()
    // - saveCurrentFilters(), showSaveViewModal(), performSaveView()
    // - showNotification(), formatRelativeTime(), createFilterTags()
}
```

**Benefits:**
- ✅ **Separation of Concerns:** Filters UI vs Saved Views logic
- ✅ **Maintainability:** 400+ lines extracted from main class
- ✅ **Lazy Loading:** Saved views class only initialized when needed
- ✅ **Dependency Injection:** Clean communication between classes
- ✅ **Encapsulation:** All saved views functionality in one place

### **PHP Classes**
- `includes/class-orders-jet-admin-dashboard.php` - Master controller
- `includes/services/class-orders-jet-kitchen-service.php` - Kitchen logic
- `includes/services/class-orders-jet-amount-filter-service.php` - Amount filtering
- `includes/helpers/class-orders-jet-filter-url-builder.php` - URL parameter handling
- `includes/class-orders-jet-ajax-handlers.php` - AJAX framework
- `includes/helpers/orders-master-helpers.php` - Utility functions

### **Templates**
- `templates/admin/orders-master.php` - Master page template
- `templates/admin/partials/` - Reusable UI components

---

## **JavaScript Utility Classes (NEW)**

### **OJURLParameterManager**
```javascript
// Location: assets/js/url-parameter-manager.js
// Centralized URL parameter handling

const urlManager = new OJURLParameterManager();

// ✅ Get current parameters
const params = urlManager.getCurrentParams();

// ✅ Update single parameter
const newUrl = urlManager.updateParam('filter', 'active');

// ✅ Update multiple parameters
const newUrl = urlManager.updateMultipleParams({
    filter: 'active',
    search: 'table 5'
});

// ✅ Build URL from filter object
const url = urlManager.buildFilterURL(filterParams);

// ✅ Update browser URL without reload
urlManager.updateBrowserURL(newUrl);
```

### **OJAjaxRequestManager**
```javascript
// Location: assets/js/ajax-request-manager.js
// Centralized AJAX request management

const ajaxManager = new OJAjaxRequestManager();

// ✅ Set callbacks
ajaxManager.setCallbacks({
    onLoading: (isLoading) => showLoadingState(isLoading),
    onSuccess: (data) => updateContentArea(data),
    onError: (message) => showErrorMessage(message)
});

// ✅ Refresh orders content
ajaxManager.refreshOrdersContent(filterParams);

// ✅ Generic AJAX request
ajaxManager.makeRequest('oj_save_view', {
    view_name: 'My View',
    filters: filterParams
}, {
    onSuccess: (response) => console.log('Saved!'),
    onError: (error) => console.error('Failed!')
});
```

---

**This inventory ensures consistent, high-performance development across all modules! 🚀**