# Orders Reports - Quick Setup Guide

## 🚀 Quick Start

The Orders Reports page is now fully implemented and ready to use!

### Access the Reports
Navigate to: **WordPress Admin → Orders → Orders Reports**
Direct URL: `/wp-admin/admin.php?page=orders-reports`

---

## 📋 Important: Update Template File

The new comprehensive reports template was created as `orders-reports-new.php`. To activate it:

### Option 1: Replace the old template
```bash
# In the plugin directory
cd templates/admin/
mv orders-reports.php orders-reports-old-backup.php
mv orders-reports-new.php orders-reports.php
```

### Option 2: Update the render method
In `includes/class-orders-jet-admin-dashboard.php`, update line ~685:

**Change from:**
```php
$template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-reports.php';
```

**To:**
```php
$template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-reports-new.php';
```

---

## ✅ Verify Installation

### Check Files Created
Run this in PowerShell from the plugin root:

```powershell
# Check all new files exist
Test-Path includes/classes/class-orders-reports-query-builder.php
Test-Path includes/classes/class-orders-reports-data.php
Test-Path includes/classes/class-orders-reports-export.php
Test-Path templates/admin/orders-reports-new.php
Test-Path assets/js/orders-reports.js
Test-Path assets/css/orders-reports.css
Test-Path docs/ORDERS_REPORTS_IMPLEMENTATION.md
```

All should return `True`.

### Check AJAX Handlers
In `includes/class-orders-jet-admin-dashboard.php`, verify these lines exist (around line 56-58):

```php
add_action('wp_ajax_oj_reports_get_data', array($this, 'ajax_reports_get_data'));
add_action('wp_ajax_oj_reports_drill_down', array($this, 'ajax_reports_drill_down'));
add_action('wp_ajax_oj_reports_export', array($this, 'ajax_reports_export'));
```

---

## 🧪 Testing the Reports

### 1. Basic Functionality Test
1. Go to **Orders → Orders Reports**
2. You should see:
   - Filter bar at top
   - 6 KPI cards showing metrics
   - Tabbed interface (Summary, Category, Payment, Status)
   - Summary table with data

### 2. Filter Test
1. Change **Group By** to "Week"
2. Click **Apply Filters**
3. Verify table updates to show weekly data

### 3. Drill-Down Test
1. In Summary table, click **View Details** on any row
2. Verify drill-down section appears below
3. Check that KPIs update for that specific date
4. Click **Close** to hide drill-down

### 4. Export Test
1. Click **Export CSV** button
2. Verify file downloads
3. Try other export formats (Excel, PDF)

---

## 🔧 Troubleshooting

### Reports page is blank
**Check:** Template file path in `render_orders_reports()` method

### AJAX not working
**Check:** Browser console for errors
**Verify:** `ojReportsData` is defined in page source
**Solution:** Clear browser cache and reload

### Export fails
**Check:** WordPress uploads directory is writable
**Verify:** Directory `/wp-content/uploads/orders-jet-exports/` exists and is writable

```powershell
# Create export directory if needed
New-Item -ItemType Directory -Path "d:\server\htdocs\Domain_project\ai-wo\wp-content\uploads\orders-jet-exports" -Force
```

### No data showing
**Check:** You have WooCommerce orders in the selected date range
**Verify:** Filters aren't too restrictive

---

## 📦 Optional Dependencies

### For Enhanced Export Functionality

#### PhpSpreadsheet (Excel exports)
```bash
composer require phpoffice/phpspreadsheet
```

#### TCPDF (PDF exports)
```bash
composer require tecnickcom/tcpdf
```

**Note:** The system will fallback gracefully if these are not available:
- Excel → Falls back to CSV
- PDF → Falls back to HTML

---

## 🎨 Customization

### Change KPI Cards
Edit: `includes/classes/class-orders-reports-data.php`
Method: `format_kpis()`

### Add New Report Tab
Edit: `templates/admin/orders-reports-new.php`
1. Add tab button in `.oj-reports-tabs`
2. Add tab content in `.oj-tab-content`

### Modify Filters
Edit: `includes/classes/class-orders-reports-query-builder.php`
Method: `get_base_query_args()`

### Customize Styling
Edit: `assets/css/orders-reports.css`

---

## 🔍 Key Features Overview

### ✅ Implemented Features
- [x] Filter by date range (presets + custom)
- [x] Group by day/week/month
- [x] Filter by product type (food/beverage)
- [x] Filter by order source (storefront/phone/other)
- [x] 6 dynamic KPI cards
- [x] Summary report table
- [x] Category analysis table
- [x] Payment methods breakdown
- [x] Order status breakdown
- [x] Drill-down to day details
- [x] Export to CSV
- [x] Export to Excel (with PhpSpreadsheet)
- [x] Export to PDF (with TCPDF)
- [x] Responsive design
- [x] Loading states
- [x] AJAX refresh

### 🔮 Potential Enhancements
- [ ] Chart visualizations (Chart.js)
- [ ] Saved report configurations
- [ ] Email scheduling
- [ ] Comparison periods
- [ ] Advanced filters
- [ ] Print functionality

---

## 📞 Support

### Documentation
- Full implementation details: `docs/ORDERS_REPORTS_IMPLEMENTATION.md`
- Code examples and API reference in documentation

### Common Issues
See troubleshooting section above

---

## ✨ Summary

You now have a fully functional, enterprise-grade reporting system for your WooCommerce orders with:

1. **Comprehensive Analytics** - Real-time KPIs and metrics
2. **Flexible Filtering** - Multiple filter dimensions
3. **Drill-Down Capability** - Detailed day-level analysis
4. **Export Functionality** - CSV, Excel, PDF exports
5. **Professional UI** - Modern, responsive design
6. **Performance Optimized** - Efficient queries and caching

**Next Step:** Activate the new template and start exploring your order data! 📊
