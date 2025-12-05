# Development Guide

**Essential standards and workflow for Orders Jet / WooJet development**

---

## 🚀 **Quick Start (5 min read)**

### **Critical Performance Rules (NEVER VIOLATE)**
1. **NO N+1 queries** → Use `get_orders_meta_bulk($order_ids)`
2. **NO direct `error_log()`** → Use `oj_debug_log($msg, $context)`
3. **NO nested loops with calculations** → Pre-calculate with `Orders_Jet_Addon_Calculator`

**Result:** 80-90% query reduction, <1s load times

### **Essential Workflow**
1. **Test locally first** → Never push untested code
2. **One task at a time** → Complete and test each step
3. **Clean debug code** → Remove before git push
4. **Reuse design system** → `manager-orders-cards.css` + `dashboard-express.css`

### **Must-Use Resources**
- **CSS:** Orders Express design system (cards, badges, buttons)
- **JS:** `orders-master.js` patterns (AJAX, search, pagination)  
- **PHP:** `Orders_Jet_Admin_Dashboard` (bulk operations)

---

## 📋 **Table of Contents**

1. [⚡ Performance Standards](#performance-standards) - Critical rules and patterns
2. [🔄 Development Workflow](#development-workflow) - Step-by-step process
3. [🔧 Reusable Components](#reusable-components) - CSS, JS, PHP patterns
4. [🔐 Security Requirements](#security-requirements) - Input/output handling
5. [🧪 Testing Checklist](#testing-checklist) - What to verify before push
6. [🚨 Troubleshooting](#troubleshooting) - Common issues and fixes
7. [📝 Code Standards](#code-standards) - WordPress/WooCommerce guidelines

---

## ⚡ **Performance Standards**

### **🚫 Forbidden Patterns**
```php
// ❌ N+1 Queries (causes 50-100 queries per page)
foreach ($orders as $order) {
    $meta = $order->get_meta('_oj_table_number'); // Query per order!
}

// ❌ Direct Logging (floods production logs)
error_log('Debug message'); // Creates log spam

// ❌ Expensive Loops (causes timeouts)
foreach ($orders as $order) {
    $addon_total = $this->calculate_addon_total($order); // Expensive!
}
```

### **✅ Required Patterns**
```php
// ✅ Bulk Operations (single queries)
$order_ids = array_column($orders, 'id');
$meta_data = $this->get_orders_meta_bulk($order_ids);
Orders_Jet_Addon_Calculator::precalculate_addon_totals($order_ids);

// ✅ Controlled Logging (rate-limited)
oj_debug_log('Message', 'CONTEXT', ['data' => $value]);

// ✅ Pre-calculated Data (no loops)
$addon_total = Orders_Jet_Addon_Calculator::get_order_addon_total($order_id);
```

### **Performance Targets**
- **Page Load:** <1 second
- **AJAX Requests:** <500ms  
- **Database Queries:** <10 per page
- **Memory Usage:** Optimized with bulk operations

---

## 🔄 **Development Workflow**

### **Task Execution Process**
```
1. 📋 Review task requirements
2. 🔍 Check reusable components (RESOURCE_INVENTORY.md)
3. 💻 Implement locally
4. 🧪 Test thoroughly (see checklist below)
5. 🧹 Clean debug code
6. 📝 Git commit with clear message
7. 🚀 Push to repository
8. ✅ Mark task complete
```

### **Git Workflow**
```bash
# Before starting
git pull origin main

# After completing task  
git add [files]
git commit -m "Task X.X.X - Clear description of changes"
git push origin main
```

### **Local Testing Requirements**
- [ ] Functionality works as expected
- [ ] No JavaScript console errors
- [ ] No PHP errors in logs
- [ ] Performance <1 second load time
- [ ] All debugging code removed

---

## 🔧 **Reusable Components**

### **CSS Design System (ALWAYS REUSE)**
```css
/* Primary Files */
manager-orders-cards.css    // Cards, badges, buttons
dashboard-express.css       // Grid, filters, pagination

/* Essential Classes */
.oj-order-card            // Card container
.oj-status-badge          // Status indicators
.oj-filter-tabs           // Filter interface  
.oj-action-btn            // Action buttons
.oj-btn-primary           // Primary button (blue)
.oj-btn-success           // Success button (green)
```

### **JavaScript Patterns (EXTEND THESE)**
```javascript
// From orders-master.js
loadOrdersWithFilter(filter, page, search)  // AJAX filtering
performSearch()                             // Debounced search  
updateOrdersGrid(orders)                    // Dynamic updates
showNotification(message, type)             // User feedback
escapeHtml(text)                           // XSS protection
```

### **PHP Services (LEVERAGE THESE)**
```php
// Core Classes
Orders_Jet_Admin_Dashboard     // Master controller with bulk ops
Orders_Jet_Kitchen_Service     // Kitchen operations
Orders_Jet_Ajax_Handlers       // AJAX handling with security
Orders_Jet_Logger             // Controlled logging
Orders_Jet_Addon_Calculator   // Bulk calculations

// Essential Methods
$this->get_orders_meta_bulk($order_ids)        // Single query for meta
$this->prepare_orders_master_data_bulk($orders) // NO N+1 queries
```

---

## 🔐 **Security Requirements**

### **Input Sanitization (ALWAYS)**
```php
// Sanitize all inputs
$search = sanitize_text_field($_POST['search'] ?? '');
$filter = sanitize_key($_POST['filter'] ?? 'all');
$page = absint($_POST['page'] ?? 1);

// Verify nonces
check_ajax_referer('oj_nonce', 'nonce');

// Check permissions
if (!current_user_can('access_oj_manager_dashboard')) {
    wp_die(__('Insufficient permissions', 'orders-jet'));
}
```

### **Output Escaping (ALWAYS)**
```php
// Escape output
echo esc_html($order_number);
echo esc_attr($css_class);
echo wp_kses_post($formatted_content);
```

### **JavaScript Security**
```javascript
// XSS Protection
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Use in templates
const html = `<div>${escapeHtml(userInput)}</div>`;
```

---

## 🧪 **Testing Checklist**

### **Before Each Commit**
- [ ] **Performance Check**
  - [ ] NO N+1 queries (verified bulk operations used)
  - [ ] NO `error_log()` calls (only `oj_debug_log()`)
  - [ ] NO nested loops with calculations
  - [ ] Page loads <1 second

- [ ] **Functionality Check**
  - [ ] Feature works as expected
  - [ ] No JavaScript errors in console
  - [ ] No PHP errors in logs
  - [ ] AJAX requests handle errors gracefully

- [ ] **Code Quality Check**
  - [ ] All debugging code removed
  - [ ] WordPress coding standards followed
  - [ ] Proper error handling implemented
  - [ ] Security measures in place

### **Integration Testing**
- [ ] New features work with existing filters
- [ ] Search functionality remains intact
- [ ] Pagination works correctly
- [ ] Role-based permissions enforced

---

## 🚨 **Troubleshooting**

### **JavaScript Not Loading**
**Symptoms:** AJAX broken, console errors, auto-refresh not working

**Quick Fix:**
```php
// Check includes/class-orders-jet-admin-dashboard.php
$manager_pages = array(
    'toplevel_page_manager-overview',
    'manager-overview_page_manager-orders-express', // Must be included!
    // ... other pages
);
```

### **Payment Modal Issues**
**Symptoms:** Modal appears but buttons unclickable

**Quick Fix:**
```javascript
// Verify CSS classes match event handlers
// Modal HTML: .oj-success-modal-overlay
// Event handlers: .oj-payment-btn
```

### **Performance Issues**
**Symptoms:** Slow page loads, high query counts

**Quick Fix:**
1. Check for N+1 queries in loops
2. Implement bulk operations
3. Add transient caching
4. Use `oj_debug_log()` instead of `error_log()`

### **Emergency Recovery**
```bash
# Revert to last working commit
git log --oneline
git reset --hard [commit-hash]
git push --force-with-lease origin main
```

---

## 📝 **Code Standards**

### **WordPress/WooCommerce Standards**
- Follow WordPress PHP Coding Standards (PSR-12 compatible)
- Use SOLID principles (Single responsibility, dependency injection)
- Implement proper error handling and validation
- Use WordPress hooks (actions/filters) for extensibility
- Document complex logic with PHPDoc comments

### **Commit Message Format**
```
Task X.X.X - Brief description

- Detailed change 1
- Detailed change 2
- Performance improvement
- Bug fix description

Fixes: #issue-number (if applicable)
```

### **File Organization**
```
includes/
├── class-orders-jet-*.php        // Main classes
├── services/                     // Business logic
├── handlers/                     // AJAX and form handlers
├── helpers/                      // Utility functions
└── classes/                      // Specialized classes

templates/admin/
├── page-name.php                 // Main page templates
└── partials/                     // Reusable UI components

assets/
├── css/                          // Stylesheets
└── js/                           // JavaScript files
```

---

## 🎯 **Success Metrics**

### **Performance Achievements**
- ✅ 80-90% database query reduction
- ✅ Sub-1-second load times  
- ✅ 60% memory usage reduction
- ✅ 90% log reduction

### **Code Quality Standards**
- ✅ WordPress coding standards compliance
- ✅ SOLID principles applied
- ✅ Zero N+1 query patterns
- ✅ Comprehensive error handling
- ✅ Security best practices

---

**Follow these standards to maintain high-performance, secure, maintainable code! 🚀**