# Orders Reports Feature - Complete Implementation

## 📊 Overview

The Orders Reports page provides comprehensive business intelligence and analytics for WooCommerce orders. It's accessible at `/wp-admin/admin.php?page=orders-reports`.

Reference page: `/wp-admin/admin.php?page=business-intelligence`

## ✨ Features Implemented

### 1. Filter Bar (Top Section)
The filter bar allows users to slice data by multiple dimensions:

- **Date Range**: 
  - Preset options: Today, Yesterday, This Week, Week to Date, Month to Date, Last Week, Last Month, All Time
  - Custom range with date pickers (from/to)
  
- **Product Type**: Food / Beverages / All

- **Order Source**: 
  - Storefront (Dine-in orders)
  - Phone (Takeaway orders)  
  - Other (Delivery orders)
  - All

- **Group By**: Day / Week / Month

- **Action Buttons**: Apply Filters, Reset

### 2. Dynamic KPI Cards
Six key performance indicators that update based on selected filters:

1. **Total Revenue** 💰 - Sum of all orders in the selected period
2. **Total Orders** 📦 - Count of all orders
3. **Average Order Value** 📊 - Total revenue ÷ number of completed orders
4. **Completed Orders** ✅ - Count of completed orders
5. **Cancelled Orders** ❌ - Count of cancelled orders
6. **Refunded Orders** ↩️ - Count of refunded orders

### 3. Payment & Status Breakdown
Two visual breakdowns showing:

- **Payment Methods**: Cash vs Online payments with percentages and revenue
- **Order Status**: Distribution of orders by status (Completed, Pending, Cancelled, Refunded)

### 4. Report Tables

#### A) Summary Report (Monthly/Daily)
Shows aggregated data by period with columns:
- Period (Date/Week/Month label)
- Total Orders
- Completed Orders (green badge)
- Cancelled Orders (red badge)
- Total Revenue
- Actions (View Details button)

**Export Options**: Excel 📥, CSV 📄, PDF 📑

#### B) Category Report
Shows performance by product category:
- Category Name
- Orders Count (number of orders containing products from this category)
- Total Revenue (sum of line items from this category)

**Export Options**: Excel 📥, CSV 📄, PDF 📑

### 5. Drill-Down Functionality
Click "View Details" on any row in the Summary Report to:
- Load detailed orders for that specific period
- Show updated KPIs for just that period
- Display a table with individual orders including:
  - Order Number (clickable link to order edit page)
  - Customer Name
  - Status
  - Total
  - Payment Method
  - Date/Time
  - View button

### 6. Export Functionality
Each report table has three export buttons:
- **Excel** (XLSX format using PhpSpreadsheet if available, CSV fallback)
- **CSV** (native PHP implementation, always available)
- **PDF** (using TCPDF if available, HTML fallback)

All exports:
- Respect current filters and grouping
- Include proper headers
- Use formatted values
- Download directly to user's browser

## 🏗️ Architecture

### Files Structure

```
orders-jet-main/
├── includes/
│   ├── class-orders-jet-admin-dashboard.php (AJAX handlers, page registration)
│   └── classes/
│       ├── class-orders-reports-query-builder.php (Data queries & filtering)
│       ├── class-orders-reports-data.php (KPI calculations & data formatting)
│       └── class-orders-reports-export.php (Export handlers)
├── templates/
│   └── admin/
│       ├── orders-reports.php (Main template - updated)
│       └── orders-reports-new.php (Alternative clean template)
└── assets/
    ├── js/
    │   └── orders-reports.js (AJAX interactions)
    └── css/
        └── orders-reports.css (Styling)
```

### AJAX Endpoints

#### 1. Get Reports Data
```javascript
action: 'oj_reports_get_data'
nonce: ojReportsData.nonce
// + current filter parameters
```

Returns:
```json
{
  "success": true,
  "kpis": {...},
  "summary_table": [...],
  "category_table": [...],
  "payment_breakdown": {...},
  "status_breakdown": {...}
}
```

#### 2. Drill-Down
```javascript
action: 'oj_reports_drill_down'
nonce: ojReportsData.nonce
date: '2024-12-25'
// + current filter parameters
```

Returns:
```json
{
  "success": true,
  "date": "2024-12-25",
  "kpis": {...},
  "orders": [...]
}
```

#### 3. Export
```javascript
action: 'oj_reports_export'
nonce: ojReportsData.nonce
export_type: 'csv' | 'excel' | 'pdf'
report_type: 'summary' | 'category'
// + current filter parameters
```

Returns:
```json
{
  "success": true,
  "filename": "orders-report_summary_day_2024-12-03.csv",
  "url": "https://site.com/wp-content/uploads/orders-jet-exports/...",
  "message": "Export completed successfully"
}
```

## 🔧 Usage

### Basic Usage

1. Navigate to **Orders → 📊 Orders Reports** in WordPress admin
2. Select desired filters (date range, product type, order source)
3. Choose grouping level (day/week/month)
4. Click **Apply Filters**
5. View updated KPIs and report tables

### Drill-Down Analysis

1. In the Summary Report, click **View Details** on any row
2. A detailed section appears below showing:
   - KPIs for that specific period only
   - List of all orders in that period
3. Click **View** to open individual orders in WooCommerce
4. Click **✕ Close** to hide the drill-down section

### Exporting Reports

1. Choose the report tab (Summary or Category)
2. Click the desired export button (Excel/CSV/PDF)
3. File downloads automatically
4. Export respects all current filters

### Switching Report Types

Click the tabs at the top of the report tables:
- **📅 Summary Report**: Time-based aggregation
- **📊 Category Report**: Category-based aggregation

## 📝 Filter Parameters (URL)

The page uses standard URL parameters:

```
/wp-admin/admin.php?page=orders-reports
  &date_preset=month_to_date
  &product_type=food
  &order_source=dinein
  &group_by=day
```

Or custom date range:
```
&date_preset=custom
&date_from=2024-12-01
&date_to=2024-12-31
```

## 🎯 Performance Notes

- **Caching**: Filter counts are cached for 30 seconds using WordPress transients
- **Aggregation**: Data is aggregated in PHP after fetching orders (not using SQL GROUP BY)
- **Query Optimization**: Uses `wc_get_orders()` with proper status/date filters
- **Pagination**: Not implemented for reports (shows all data for selected period)

## 🔄 How Filters Update Statistics

When you change any filter and click **Apply Filters**:

1. Page reloads with new parameters in URL
2. `Orders_Reports_Query_Builder` is initialized with new parameters
3. It fetches orders matching the new filters
4. `Orders_Reports_Data` calculates KPIs from filtered orders
5. All sections update:
   - ✅ KPI Cards
   - ✅ Payment/Status Breakdowns
   - ✅ Summary Table
   - ✅ Category Table

**No AJAX required** - Full page reload ensures all data is consistent.

For drill-down, AJAX is used to avoid full page reload while showing detailed data.

## 🐛 Troubleshooting

### Exports Not Working

1. Check if export directory exists and is writable:
   ```
   /wp-content/uploads/orders-jet-exports/
   ```

2. For Excel exports, check if PhpSpreadsheet is available:
   ```php
   class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')
   ```
   If not, it falls back to CSV automatically.

3. For PDF exports, check if TCPDF is available:
   ```php
   class_exists('TCPDF')
   ```
   If not, it creates HTML file instead.

### No Data Showing

1. Check date range - make sure you have orders in that period
2. Check product/source filters - they might be too restrictive
3. Verify orders have proper meta data (_oj_kitchen_type, exwf_odmethod)

### Drill-Down Not Working

1. Check browser console for JavaScript errors
2. Verify nonce is being passed correctly (ojReportsData.nonce)
3. Check AJAX response in Network tab

## 📚 Code Examples

### Adding a New KPI

Edit `class-orders-reports-data.php`:

```php
public function get_kpis() {
    // ... existing code ...
    
    $kpis['new_metric'] = 0;
    
    foreach ($summary as $period_data) {
        $kpis['new_metric'] += $period_data['some_value'];
    }
    
    return $kpis;
}

public function format_kpis($kpis) {
    // ... existing KPIs ...
    
    $formatted['new_metric'] = array(
        'label' => __('New Metric', 'orders-jet'),
        'value' => number_format($kpis['new_metric']),
        'raw' => $kpis['new_metric'],
        'icon' => '🎯',
        'color' => '#f59e0b',
    );
    
    return $formatted;
}
```

### Adding a New Filter

1. Add to filter bar in template:
```php
<select name="new_filter">
    <option value="">All</option>
    <option value="option1">Option 1</option>
</select>
```

2. Add to query builder constructor:
```php
$this->new_filter = isset($params['new_filter']) 
    ? sanitize_text_field($params['new_filter']) 
    : '';
```

3. Add to query building logic:
```php
if (!empty($this->new_filter)) {
    $meta_query[] = array(
        'key' => '_meta_key',
        'value' => $this->new_filter,
        'compare' => '='
    );
}
```

## ✅ Completed Requirements

✔️ Admin page at `/wp-admin/admin.php?page=orders-reports`  
✔️ Filter bar with date range, product type, order source  
✔️ Grouping options (day/week/month)  
✔️ 6 dynamic KPI cards  
✔️ Payment & status breakdowns  
✔️ Summary report table  
✔️ Category report table  
✔️ Drill-down with AJAX  
✔️ Export to Excel/CSV/PDF  
✔️ Responsive design  
✔️ WordPress coding standards  
✔️ Proper code organization  
✔️ PHPDoc comments  

## 🚀 Future Enhancements

Possible improvements for future versions:

- [ ] Add charts/graphs using Chart.js
- [ ] Add comparison mode (compare two periods)
- [ ] Add email scheduled reports
- [ ] Add custom report builder
- [ ] Add more export formats (JSON, XML)
- [ ] Add report templates/saved views
- [ ] Add real-time updates via WebSocket/AJAX polling
- [ ] Add data visualization options (bar chart, pie chart, line chart)

---

**Last Updated**: December 3, 2024  
**Version**: 2.0.0  
**Author**: Orders Jet Development Team

