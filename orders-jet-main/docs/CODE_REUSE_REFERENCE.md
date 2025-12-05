# Code Reuse Reference

**Quick reference to proven implementations within Orders Jet plugin**

---

## 🎯 **Purpose**

This document centralizes references to proven code implementations within the Orders Jet plugin that can be reused for similar functionality. When implementing new features, always check this reference first to avoid reinventing the wheel.

---

## 📋 **Proven Implementations**

### **Add-ons Processing (3 Formats)**
**Location:** `includes/class-orders-jet-ajax-handlers.php` → `process_item_addons_for_details()`
**Used in:** Kitchen order cards, order details, table invoices
**Formats handled:**
1. `_oj_addons_data` (structured array) - Priority format
2. `_wc_pao_addon_value` (WooCommerce Product Add-ons)
3. `_oj_item_addons` (string format) - Fallback

**Reuse pattern:**
```php
// Copy the complete logic from process_item_addons_for_details()
// Handles all 3 formats with proper fallback chain
// Includes price removal for kitchen displays
```

### **Variations Processing (Multiple Formats)**
**Location:** `includes/handlers/class-orders-jet-table-query-handler.php`
**Used in:** Kitchen cards, table invoices, order details
**Formats handled:**
1. WooCommerce native variations (product variations)
2. Custom variations from meta (`_oj_variations_data`)
3. Attribute meta (`pa_*`, `attribute_*`)

**Reuse pattern:**
```php
// Copy the complete variation processing logic
// Handles both native WC and custom variations
// Includes smart filtering for redundant attributes
```

### **Product Status Filtering**
**Location:** `includes/services/class-orders-jet-menu-service.php`
**Used in:** Kitchen cards, menu displays, order processing
**Pattern:**
```php
$product = $item->get_product();
if (!$product || $product->get_status() !== 'publish') {
    continue; // Skip draft/private products
}
```

### **Kitchen Type Detection**
**Location:** `includes/services/class-orders-jet-kitchen-service.php` → `get_order_kitchen_type()`
**Used in:** Kitchen filtering, order classification
**Logic:** Checks product meta `Kitchen` field, determines food/beverages/mixed

### **Bulk Database Operations**
**Location:** `includes/class-orders-jet-admin-dashboard.php`
**Pattern:** Always use bulk operations to avoid N+1 queries
```php
// ✅ Good: Bulk operation
$orders_meta = $this->get_orders_meta_bulk($order_ids);

// ❌ Bad: N+1 queries
foreach ($orders as $order) {
    $meta = $order->get_meta('key'); // Individual query per order
}
```

### **Kitchen User Detection**
**Location:** `includes/class-orders-jet-capabilities.php`
**Functions:** `oj_get_user_function()`, `oj_get_kitchen_specialization()`
**Pattern:** WordPress-native roles + user meta for specialization

### **Order Status Workflows**
**Established flow:** `processing` → `pending-payment` → `completed`
**Kitchen users:** Only see `processing` orders (need preparation)
**Manager users:** See both `processing` and `pending-payment` orders

---

## 🚫 **Anti-Patterns (Never Do)**

### **Performance Violations**
1. **N+1 Queries:** Never loop through orders calling individual meta queries
2. **Direct error_log():** Use `oj_debug_log()` or `oj_error_log()` instead
3. **Nested loops with calculations:** Pre-calculate outside loops

### **Code Duplication**
1. **Add-on parsing:** Always reuse `process_item_addons_for_details()` logic
2. **Variation handling:** Always reuse table-query-handler patterns
3. **Product filtering:** Always check product status before processing

### **Role Management**
1. **Custom roles:** Use WordPress native roles + user meta instead
2. **Hard-coded capabilities:** Use the capability system from `class-orders-jet-capabilities.php`

---

## 📚 **Implementation Examples**

### **Kitchen Card Implementation**
**File:** `templates/admin/partials/kitchen-order-card.php`
**Demonstrates:**
- ✅ Product status filtering (line 15-20)
- ✅ Complete add-ons processing (line 85-140)
- ✅ Complete variations processing (line 45-84)
- ✅ Price removal for kitchen display
- ✅ Smart attribute filtering (no redundant size attributes)

### **Kitchen Filtering Service**
**File:** `includes/services/class-orders-jet-kitchen-filter-service.php`
**Demonstrates:**
- ✅ Reusable kitchen filtering logic
- ✅ Item-level kitchen type detection
- ✅ Smart name-based detection with fallbacks
- ✅ Order status management for different user types

### **Express Dashboard Integration**
**File:** `templates/admin/dashboard-manager-orders-express.php`
**Demonstrates:**
- ✅ Service integration pattern (lines 55-57)
- ✅ Kitchen user detection and filtering (lines 30-35, 411-414)
- ✅ Conditional CSS/JS loading for kitchen users (lines 47-52)
- ✅ Clean separation of manager vs kitchen workflows

---

## 🔄 **Update Protocol**

When you implement new functionality that could be reused:

1. **Document the implementation** in this file
2. **Note the file location** and key functions/patterns
3. **Describe what it handles** and any special considerations
4. **Add usage examples** if the pattern is complex

When you need similar functionality:

1. **Check this document first** before writing new code
2. **Copy and adapt** proven implementations
3. **Test thoroughly** to ensure the copied logic works in your context
4. **Update this document** if you enhance or extend the pattern

---

**Last Updated:** November 2024  
**Status:** Production-ready implementations documented