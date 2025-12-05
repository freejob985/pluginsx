# Development Tools Guide

## 🛠️ Test Data Generator

### **Overview**
The Development Tools panel provides quick access to generate and clear test orders for development and testing purposes.

### **Location**
- Visible on: **Orders Master V2** page
- Access: Admin users only
- Requirement: `WP_DEBUG` must be `true`

---

## 🎯 **Features**

### **1. Generate Test Orders**
Creates 20 test orders with realistic data:

**Breakdown:**
- **5 Processing Orders** (Active/Kitchen)
  - Mixed table and takeaway orders
  - Random kitchen types (food_only, beverages_only, mixed)
  - Various table numbers (T01-T20)
  - Time spread: 1-8 hours ago

- **3 Pending Payment Orders** (Ready)
  - 70% table orders, 30% takeaway
  - Ready for completion
  - Time spread: 1-4 hours ago

- **10 Completed Orders**
  - 40% table orders, 60% takeaway
  - Various payment methods (cash, card, online)
  - Time spread: 4-24 hours ago

- **2 Mixed Kitchen Orders**
  - Food + Beverages
  - Partial ready states (one kitchen ready, one not)
  - Table orders only

**Sample Customers:**
- Ahmed Hassan
- Sara Ali
- Mohamed Ibrahim
- Fatma Mahmoud
- Khaled Omar

---

### **2. Delete All Orders**
Permanently deletes all WooCommerce orders from the database.

**Safety Features:**
- Double confirmation required
- Must type "DELETE" to confirm
- Only works in WP_DEBUG mode
- Admin access required
- Cleans up orphaned meta data

---

## 🔒 **Security**

### **Access Control**
```php
if (WP_DEBUG && current_user_can('manage_options'))
```

### **AJAX Security**
- Nonce verification (`oj_dev_tools`)
- Permission checks
- WP_DEBUG requirement

### **Rate Limiting**
- Safe query limits (1000 max for deletion)
- Prevents server overload

---

## 💻 **Usage**

### **Enable Development Tools**
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
```

### **Generate Test Orders**
1. Navigate to: **Orders → Orders Master V2**
2. Click: **"➕ Generate Test Orders (20 orders)"**
3. Confirm the action
4. Wait for success message
5. Page reloads automatically

### **Clear All Orders**
1. Navigate to: **Orders → Orders Master V2**
2. Click: **"🗑️ Delete All Orders"**
3. Confirm first warning
4. Type "DELETE" to confirm
5. Wait for success message
6. Page reloads automatically

---

## 🧪 **Testing Scenarios**

### **What You Can Test**

**Filters:**
- All Orders (20 total)
- Active/Kitchen (5-7 orders)
- Ready (3 orders)
- Completed (10 orders)

**Search:**
- By order number (#127, #128, etc.)
- By table (T01, T05, T12, etc.)
- By customer name (Ahmed, Sara, Mohamed)

**Sorting:**
- By date created (newest/oldest)
- By date modified

**Pagination:**
- Page 1: 20 orders
- Page 2+: Empty (only 20 generated)

**Order Cycle:**
- Mark processing → ready
- Complete ready → completed
- Mixed kitchen partial ready states

---

## 📊 **Generated Data Structure**

### **Order Meta**
```php
_oj_table_number       // "T01", "T05", etc. (50-70% of orders)
_oj_kitchen_type       // "food_only", "beverages_only", "mixed"
_oj_food_kitchen_ready // "yes" or "no" (for mixed orders)
_oj_beverage_kitchen_ready // "yes" or "no" (for mixed orders)
```

### **Order Properties**
```php
status:         processing, pending-payment, completed
billing_name:   "Ahmed Hassan", "Sara Ali", etc.
billing_phone:  "0100123456", etc.
items:          1-6 random products
quantities:     1-4 per item
payment_method: cash, card, bacs
date_created:   1-24 hours ago (varies by status)
```

---

## ⚠️ **Important Notes**

### **Requirements**
- ✅ WP_DEBUG must be enabled
- ✅ Admin user access required
- ✅ WooCommerce products must exist
- ✅ At least 10 products recommended

### **Limitations**
- Generates fixed 20 orders per click
- Delete limited to 1000 orders per action
- Requires page reload to see results

### **Best Practices**
- Clear orders before generating new test data
- Test one feature at a time
- Use realistic product data
- Don't use in production!

---

## 🚀 **Future Enhancements**

### **Planned Features**
- [ ] Custom order count (5, 10, 20, 50, 100)
- [ ] Specific scenario generation
  - Rush hour (10 active orders)
  - Ready to serve (5 ready orders)
  - High value orders (over 300 EGP)
  - Multiple tables scenario
- [ ] Export test data as JSON
- [ ] Import test data from JSON
- [ ] Schedule automatic cleanup

---

## 🐛 **Troubleshooting**

### **Panel Not Visible**
- Check: Is `WP_DEBUG` enabled?
- Check: Are you logged in as admin?
- Check: Is Orders Master V2 page loaded?

### **No Products Found Error**
- Create at least 1 product in WooCommerce
- Recommended: 10+ products for variety

### **AJAX Errors**
- Check browser console (F12)
- Verify nonce is being sent
- Check PHP error logs

### **Deletion Not Working**
- Verify confirmation text is exactly "DELETE"
- Check user permissions
- Check for locked orders (payment processing)

---

## 📝 **Code Reference**

### **Files**
- Template: `templates/admin/orders-master-v2.php` (lines 111-207)
- Handlers: `includes/class-orders-jet-admin-dashboard.php` (lines 2100-2310)

### **AJAX Actions**
- `oj_generate_test_orders` - Generate test data
- `oj_clear_all_orders` - Delete all orders

### **Hooks**
```php
add_action('wp_ajax_oj_generate_test_orders', array($this, 'ajax_generate_test_orders'));
add_action('wp_ajax_oj_clear_all_orders', array($this, 'ajax_clear_all_orders'));
```

---

**Status**: ✅ **ACTIVE AND READY**  
**Version**: 1.0  
**Last Updated**: October 31, 2025

