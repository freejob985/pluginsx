# System Architecture.

**Complete system design, components, and reusable resources for Orders Jet / WooJet platform**

---

## 🏗️ **System Overview**

### **Platform Architecture**
Orders Jet is built as a comprehensive WooCommerce transformation platform with:
- **Smart Card Interface System** (replaces boring table rows)
- **High-Performance Data Layer** (80-90% faster than default WooCommerce)
- **Industry-Agnostic Workflow Engine** (customizable for any business type)
- **Role-Based Action System** (Manager/Waiter/Kitchen specific interfaces)

### **Technical Foundation**
- **WordPress/WooCommerce Integration** - Native WordPress plugin architecture
- **HPOS Compatibility** - Supports both legacy and High-Performance Order Storage
- **Performance-First Design** - Sub-1-second load times, bulk operations
- **Modular Component System** - Reusable across all modules

---

## 🔧 **Core Components**

### **1. Backend Management System**

#### **Table Management**
```php
// Custom Post Type: oj_table
Meta Fields:
- _oj_table_number        // Table Name/Number (T01, T25)
- _oj_table_capacity      // Capacity (number of people)
- _oj_table_status        // Status (Available, Occupied, Reserved)
- _oj_table_location      // Zone/Location
- _oj_table_qr_code       // QR Code (generated/regenerated)
- _oj_woofood_location_id // WooFood Location Integration
```

#### **Order Management**
```php
// WooCommerce Integration with HPOS Support
Order Status Flow:
Draft → Payment Processing → Processing → Ready → Completed

Order Types:
- Dine-in (table-based)
- Takeaway (pickup)
- Delivery (address-based)

Kitchen Management:
- Dual kitchen support (Food/Beverages)
- Kitchen readiness tracking
- Real-time status updates
```

#### **User Role System**
```php
// Role-Based Access Control
Manager:    Full system access, analytics, settings
Kitchen:    Order preparation, mark ready
Waiter:     Table service, order completion  
Delivery:   Delivery order management
```

### **2. Service Layer Architecture**

#### **Core Services**
```php
// Kitchen Management Service
Orders_Jet_Kitchen_Service {
    get_kitchen_readiness_status($order)  // Determines if order is ready
    get_kitchen_status_badge($order)      // Returns status badge HTML
    determine_kitchen_type($items)        // Food/Beverage classification
}

// Admin Dashboard Controller (BULK OPTIMIZED)
Orders_Jet_Admin_Dashboard {
    get_orders_master_data()              // Main data retrieval with caching
    prepare_orders_master_data_bulk()     // NO N+1 queries
    get_orders_meta_bulk($order_ids)      // Single query for all meta
    get_orders_items_bulk($order_ids)     // Single query for all items
    apply_search_filter()                 // HPOS-aware search
    get_optimized_filter_counts()         // Cached filter counts
}

// Performance Classes
Orders_Jet_Logger {
    debug($message, $context, $data)      // Controlled debug logging
    error($message, $context, $data)      // Production error logging
    performance($message, $time, $context) // Performance monitoring
}

Orders_Jet_Addon_Calculator {
    precalculate_addon_totals($order_ids) // NO nested loops
    get_order_addon_total($order_id)      // Pre-calculated data
    get_item_base_price($order_id, $item_id) // Pre-calculated pricing
}
```

#### **AJAX Handlers**
```php
Orders_Jet_Ajax_Handlers {
    ajax_get_orders_master_filtered()     // Advanced filtering with search
    submit_table_order()                  // Order submission
    get_table_status()                    // Table status queries
    close_table_group()                   // Table closure with invoice
    complete_individual_order()           // Individual order completion
    mark_order_ready()                    // Kitchen → Ready status
}
```

### **3. Advanced Search System**

#### **HPOS-Compatible Search**
```php
// Handles both WooCommerce storage methods
if (is_hpos_enabled()) {
    // Search wp_wc_orders + wp_wc_orders_meta tables
    $matching_ids = search_hpos_orders($search, $search_like);
} else {
    // Search wp_posts + wp_postmeta tables  
    $matching_ids = search_legacy_orders($search, $search_like);
}

// Two-Post-Type Search (Orders + Tables)
$table_linked_ids = search_table_linked_orders($search, $search_like);
$all_matching_ids = array_unique(array_merge($matching_ids, $table_linked_ids));

// Searches across:
- Order IDs, numbers, customer names
- Table numbers (T25, T04) via oj_table posts
- Order-table relationships via _oj_table_number meta
- Performance optimized with direct SQL queries
```

---

## 🎨 **Design System Components**

### **CSS Architecture (REUSE THESE)**

#### **Primary Stylesheets**
```css
📁 assets/css/
├── manager-orders-cards.css ✅ MASTER DESIGN SYSTEM
│   ├── Card layouts (.oj-order-card, .oj-card-header, .oj-card-body)
│   ├── Status badges (.oj-status-badge, .oj-badge-active, .oj-badge-ready)
│   ├── Action buttons (.oj-action-btn, .oj-btn-primary, .oj-btn-secondary)
│   ├── Typography and spacing
│   └── Color scheme and branding
│
└── dashboard-express.css ✅ LAYOUT SYSTEM
    ├── Grid system (.oj-orders-grid, .oj-grid-container)
    ├── Filter tabs (.oj-filter-tabs, .oj-filter-btn)
    ├── Pagination (.oj-pagination-wrapper, .oj-pagination-btn)
    ├── Search controls (.oj-search-wrapper, .oj-search-input)
    └── Responsive breakpoints
```

#### **CSS Class Reference**
```css
/* CARD SYSTEM - Use for all card-based layouts */
.oj-order-card              /* Main card container */
.oj-card-header             /* Card header section */
.oj-card-body               /* Main card content */
.oj-card-footer             /* Card footer with actions */
.oj-card-meta               /* Metadata display */

/* STATUS BADGES - Use for all status indicators */
.oj-status-badge            /* Base badge styling */
.oj-badge-active            /* Active/Processing status */
.oj-badge-ready             /* Ready status */
.oj-badge-completed         /* Completed status */
.oj-badge-cancelled         /* Cancelled status */

/* FILTER SYSTEM - Use for all filter interfaces */
.oj-filter-tabs             /* Filter tab container */
.oj-filter-btn              /* Individual filter button */
.oj-filter-btn.active       /* Active filter state */
.oj-filter-count            /* Count display in filters */

/* GRID LAYOUT - Use for all grid-based displays */
.oj-orders-grid             /* Main grid container */
.oj-grid-container          /* Grid wrapper */
.oj-grid-item               /* Individual grid items */

/* ACTION BUTTONS - Use for all interactive buttons */
.oj-action-btn              /* Base action button */
.oj-btn-primary             /* Primary action (blue) */
.oj-btn-secondary           /* Secondary action (gray) */
.oj-btn-success             /* Success action (green) */
.oj-btn-danger              /* Danger action (red) */
```

### **JavaScript Patterns (EXTEND THESE)**

#### **Core JavaScript Architecture**
```javascript
// From orders-master.js - AJAX Filtering Pattern
function loadOrdersWithFilter(filter, page, search) {
    // Debounced AJAX with proper error handling
    // Parameter preservation across requests
    // Loading states and user feedback
}

// Search Pattern with Debouncing
function performSearch() {
    // XSS protection with escapeHtml()
    // Real-time search with 300ms debounce
    // Maintains filter and pagination state
}

// UI Update Patterns
function updateOrdersGrid(orders) {
    // Dynamic grid updates with loading states
    // Smooth transitions and animations
    // Accessibility considerations
}

function updateFilterButtons(activeFilter) {
    // Real-time filter count updates
    // Visual state management
    // Parameter preservation in URLs
}

// Security & Utility Functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type) {
    // User feedback system
    // Auto-dismiss with timing
    // Multiple notification types
}
```

### **Template Structure (FOLLOW THIS PATTERN)**

#### **Page Template Architecture**
```php
// Based on orders-master.php - REUSE THIS STRUCTURE
<div class="wrap">
    <h1 class="wp-heading-inline">Page Title</h1>
    
    // Filter tabs with real-time counts
    <div class="oj-filter-tabs">
        <button class="oj-filter-btn active" data-filter="all">
            All Orders <span class="oj-filter-count"><?php echo $counts['all']; ?></span>
        </button>
        // ... more filter buttons
    </div>
    
    // Search and controls
    <div class="oj-controls-wrapper">
        <div class="oj-search-wrapper">
            <input type="text" class="oj-search-input" placeholder="Search orders...">
        </div>
        // Bulk actions, sort controls
    </div>
    
    // Main content grid
    <div class="oj-orders-grid" id="oj-orders-grid">
        // Dynamic content loaded via AJAX
        // Card-based layout using .oj-order-card
    </div>
    
    // Pagination with parameter preservation
    <div class="oj-pagination-wrapper">
        // Previous/Next buttons
        // Page number links
        // Records per page selector
    </div>
</div>
```

---

## 🚀 **Performance Architecture**

### **Database Optimization Patterns**

#### **Bulk Operations (REQUIRED)**
```php
// ✅ ALWAYS USE - Bulk Query Pattern
$order_ids = array_map(function($order) { return $order->get_id(); }, $orders);

// Single queries for all data
$meta_data = $this->get_orders_meta_bulk($order_ids);
$items_data = $this->get_orders_items_bulk($order_ids);
$users_data = $this->get_users_data_bulk($meta_data);

// Pre-calculate expensive operations
Orders_Jet_Addon_Calculator::precalculate_addon_totals($order_ids);

// Use pre-fetched data in loops (NO queries)
foreach ($orders as $order) {
    $meta = $meta_data[$order->get_id()] ?? array();
    $items = $items_data[$order->get_id()] ?? array();
    $addon_total = Orders_Jet_Addon_Calculator::get_order_addon_total($order->get_id());
}
```

#### **Caching Strategy**
```php
// Intelligent caching with appropriate durations
$cache_durations = [
    'dashboard_filters' => 60,    // 1 minute
    'table_orders' => 30,         // 30 seconds
    'menu_data' => 300,           // 5 minutes
    'location_data' => 300,       // 5 minutes
    'invoice_data' => 120         // 2 minutes
];

$cache_key = 'oj_data_' . md5(serialize($params));
$data = get_transient($cache_key);
if ($data === false) {
    $data = $expensive_operation();
    set_transient($cache_key, $data, $cache_durations['type']);
}
```

#### **Query Optimization**
```php
// Direct SQL for complex operations
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT p.ID 
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order'
    AND p.post_status IN ('wc-processing', 'wc-pending')
    AND pm.meta_key = '_oj_table_number'
    AND pm.meta_value LIKE %s
    LIMIT %d
", $search_like, $limit));

// Always use reasonable limits
$query_args['limit'] = min($requested_limit, 1000); // Prevent runaway queries
```

### **Performance Achievements**
- **Database Queries:** 5-8 per page load (80-90% reduction from 50-100)
- **Load Time:** <1 second (from 1.8-4.5 seconds)
- **Memory Usage:** 60% reduction through bulk processing
- **Log Volume:** 90% reduction with controlled logging

---

## 📊 **Database Schema**

### **Core Tables**

#### **WordPress/WooCommerce Tables (Extended)**
```sql
-- Orders (WooCommerce HPOS or legacy)
wp_wc_orders / wp_posts (post_type = 'shop_order')
wp_wc_orders_meta / wp_postmeta

-- Tables (Custom Post Type)
wp_posts (post_type = 'oj_table')
wp_postmeta (table configuration)

-- Users & Roles
wp_users
wp_usermeta
wp_user_roles (custom roles: oj_manager, oj_waiter, oj_kitchen)
```

#### **Key Meta Fields**
```sql
-- Order Meta
_oj_table_number          // Links order to table
_assigned_waiter          // Waiter assignment
_kitchen_status           // Kitchen preparation status
_order_method             // dinein/takeaway/delivery
_addon_data               // Product addons/customizations

-- Table Meta  
_oj_table_number          // Table identifier
_oj_table_capacity        // Seating capacity
_oj_table_status          // Current status
_oj_table_qr_code         // QR code for menu access
```

---

## 🔌 **Integration Points**

### **WooCommerce Integration**
- **Orders:** Native WC_Order objects with custom meta
- **Products:** Standard WooCommerce products with kitchen assignments
- **Payments:** WooCommerce payment gateways (Stripe, PayMob, etc.)
- **Customers:** WordPress users with enhanced profiles

### **Third-Party Integrations**
- **WooFood:** Location-based product availability
- **AWS SMS:** Mobile OTP authentication
- **Payment Gateways:** Stripe, PayMob, generic WooCommerce gateways
- **QR Code Generation:** Dynamic QR codes for table menus

### **WordPress Integration**
- **Custom Post Types:** Tables, potentially future entities
- **User Roles:** Custom roles with specific capabilities
- **Admin Menus:** Native WordPress admin integration
- **Hooks & Filters:** Extensible architecture for customization

---

## 🎯 **Reusable Resources**

### **Helper Functions**
```php
// Global logging helpers
oj_debug_log($message, $context, $data);    // Debug logging with rate limiting
oj_error_log($message, $context, $data);    // Production error logging
oj_perf_log($message, $time_ms, $context);  // Performance monitoring

// Query service helpers
oj_query_service()->get_table_orders($table_number, $options);
oj_query_service()->get_orders($args);
oj_query_service()->clear_cache($identifier);
```

### **Component Libraries**
```php
// UI Components (from orders-master-helpers.php)
oj_master_prepare_order_data($order, $services)     // Order data preparation
oj_express_get_optimized_badge_data($order)         // Status badge generation
oj_express_get_action_buttons($order, $user_role)   // Action buttons HTML
oj_build_filter_url($base_url, $params)             // URL building with parameters
oj_calculate_date_range($preset)                     // Date range calculations
oj_sort_order_ids($order_ids, $sort_by, $sort_order) // Order sorting
```

### **Query Builder Pattern**
```php
// From class-orders-master-query-builder.php
$query_builder = new Orders_Master_Query_Builder($_GET);
$orders = $query_builder->get_orders();              // Filtered/sorted/paginated orders
$filter_counts = $query_builder->get_filter_counts(); // Cached filter counts
$pagination = $query_builder->get_pagination_data(); // Complete pagination info

// Reusable for any order listing page
// HPOS-compatible, timezone-aware, cached
```

---

## 🔄 **Development Patterns**

### **Module Development Pattern**
1. **Create Query Builder** - Encapsulate all data logic
2. **Extract Helper Functions** - Reusable utilities
3. **Build UI Partials** - Modular template components
4. **Implement AJAX Handlers** - Real-time interactions
5. **Add Performance Optimizations** - Caching, bulk operations
6. **Document Components** - Update resource inventory

### **Code Quality Standards**
- **WordPress Coding Standards** - PSR-12 compatible
- **SOLID Principles** - Single responsibility, dependency injection
- **Performance First** - Sub-1-second load times required
- **Security** - Input sanitization, output escaping, nonce verification
- **Testing** - Unit testable components, integration testing

---

**This architecture provides a solid foundation for the complete WooJet platform transformation! 🚀**
