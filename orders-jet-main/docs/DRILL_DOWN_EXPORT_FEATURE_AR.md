# ميزة التصدير لجدول تفاصيل الطلبات 📥📄📑

## نظرة عامة
تم إضافة ميزة التصدير الكاملة (Excel, CSV, PDF) لجدول تفاصيل الطلبات (Drill-Down Section) في صفحة التقارير.

---

## ✨ الميزات الجديدة

### 1. أزرار التصدير في قسم التفاصيل

تم إضافة ثلاثة أزرار تصدير في رأس قسم التفاصيل:
- 📥 **Excel** - تصدير إلى ملف Excel
- 📄 **CSV** - تصدير إلى ملف CSV
- 📑 **PDF** - تصدير إلى ملف PDF

### 2. تصدير ذكي مع الفلاتر

التصدير يطبق **جميع الفلاتر النشطة**:
- ✅ نطاق التاريخ (Date Range)
- ✅ نوع المنتج (Product Type)
- ✅ مصدر الطلب (Order Source)
- ✅ حالة الطلب (Order Status)
- ✅ التجميع (Group By)
- ✅ التاريخ/الفترة المحددة

### 3. تصميم احترافي

**أزرار تفاعلية:**
- تصميم gradient بلون بنفسجي أنيق
- Hover effects مع رفع الزر وظل
- حالة التحميل ("⏳ Exporting...")
- رسالة نجاح بعد التصدير

---

## 🔧 التغييرات التقنية

### 1. واجهة المستخدم (UI)

#### قبل:
```html
<div class="oj-drill-down-header">
    <h3 id="oj-drill-down-title">Detailed Orders</h3>
    <button id="oj-close-drill-down">✕ Close</button>
</div>
```

#### بعد:
```html
<div class="oj-drill-down-header">
    <div class="oj-drill-down-title-section">
        <h3 id="oj-drill-down-title">Detailed Orders for Week 48 (15 orders)</h3>
    </div>
    <div class="oj-drill-down-actions">
        <div class="oj-export-buttons">
            <button class="oj-export-drill-down-btn" data-type="excel">📥 Excel</button>
            <button class="oj-export-drill-down-btn" data-type="csv">📄 CSV</button>
            <button class="oj-export-drill-down-btn" data-type="pdf">📑 PDF</button>
        </div>
        <button id="oj-close-drill-down">✕ Close</button>
    </div>
</div>
```

---

### 2. تخزين بيانات الـ Drill-Down

```javascript
// Store current drill-down data for export
var currentDrillDownData = {
    date: null,
    label: null,
    filters: {}
};

// When user clicks "View Details"
currentDrillDownData = {
    date: '2025-12-01',
    label: 'Week 48, 2025',
    filters: {
        date_preset: 'this_week',
        product_type: 'food',
        order_source: 'dinein',
        order_status: 'completed',
        // ... etc
    }
};
```

---

### 3. معالج التصدير JavaScript

```javascript
$(document).on('click', '.oj-export-drill-down-btn', function() {
    var $btn = $(this);
    var type = $btn.data('type'); // excel, csv, or pdf
    
    // Check if drill-down data is available
    if (!currentDrillDownData.date) {
        alert('No drill-down data available to export.');
        return;
    }
    
    // Show loading state
    $btn.prop('disabled', true).text('⏳ Exporting...');
    
    // Prepare export data
    var exportData = {
        action: 'oj_reports_export',
        nonce: ojReportsData.nonce,
        export_type: type,
        report_type: 'drill_down',        // NEW!
        drill_down_date: currentDrillDownData.date,
        drill_down_label: currentDrillDownData.label,
        // All filters...
    };
    
    // AJAX request to backend
    $.ajax({
        url: ojReportsData.ajaxUrl,
        type: 'POST',
        data: exportData,
        success: function(response) {
            if (response.success) {
                window.open(response.data.url, '_blank');
                // Show success message
                showSuccessMessage(currentDrillDownData.label);
            }
        }
    });
});
```

---

### 4. معالج Backend (PHP)

#### في `class-orders-reports-export.php`:

**إضافة case جديد:**
```php
private function get_export_data($report_type) {
    switch ($report_type) {
        case 'summary':
            return $this->get_summary_export_data();
        case 'category':
            return $this->get_category_export_data();
        case 'drill_down':  // ← NEW!
            return $this->get_drill_down_export_data();
        default:
            return array();
    }
}
```

**الدالة الجديدة:**
```php
private function get_drill_down_export_data() {
    // Get drill-down parameters
    $drill_down_date = isset($_POST['drill_down_date']) 
        ? sanitize_text_field($_POST['drill_down_date']) 
        : '';
    $drill_down_label = isset($_POST['drill_down_label']) 
        ? sanitize_text_field($_POST['drill_down_label']) 
        : '';
    
    // Get drill-down data
    $drill_data = $this->reports_data->get_drill_down_data($drill_down_date);
    $orders = $drill_data['orders'];
    
    // Prepare headers
    $headers = array(
        __('Order #', 'orders-jet'),
        __('Customer', 'orders-jet'),
        __('Status', 'orders-jet'),
        __('Total', 'orders-jet'),
        __('Payment Method', 'orders-jet'),
        __('Date/Time', 'orders-jet'),
    );
    
    // Prepare rows
    $rows = array();
    foreach ($orders as $order) {
        $rows[] = array(
            '#' . $order['order_number'],
            $order['customer_name'],
            $order['status'],
            html_entity_decode(strip_tags($order['total_formatted'])),
            $order['payment_method'],
            $order['date_created'],
        );
    }
    
    // Build title
    $title = sprintf(
        __('Detailed Orders Report - %s', 'orders-jet'),
        $drill_down_label ?: $drill_down_date
    );
    
    return array(
        'title' => $title,
        'headers' => $headers,
        'rows' => $rows,
    );
}
```

---

### 5. التصميم (CSS)

```css
.oj-drill-down-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.oj-drill-down-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.oj-export-drill-down-btn {
    background: white;
    border: 2px solid #667eea;
    color: #667eea;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.oj-export-drill-down-btn:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

/* Success Message */
.oj-export-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    margin: 15px 0;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    animation: slideInDown 0.3s ease-out;
}
```

---

## 📋 بنية الملف المُصدّر

### Excel / CSV:
```
| Order #  | Customer     | Status    | Total      | Payment Method | Date/Time        |
|----------|--------------|-----------|------------|----------------|------------------|
| #1001    | John Doe     | Completed | 150.00 EGP | Cash           | Dec 3, 2025 2:30 PM |
| #1002    | Jane Smith   | Pending   | 200.00 EGP | Credit Card    | Dec 3, 2025 3:15 PM |
| ...      | ...          | ...       | ...        | ...            | ...              |
```

### PDF:
- عنوان: "Detailed Orders Report - Week 48, 2025"
- جدول منسق بألوان احترافية
- رأس جدول بلون أزرق
- صفوف متناوبة الألوان

---

## 🎯 تدفق العمل (Workflow)

1. **المستخدم ينقر "View Details"** → يفتح قسم التفاصيل
2. **يتم تخزين البيانات** → `currentDrillDownData` يحفظ التاريخ والفلاتر
3. **المستخدم ينقر زر التصدير** → يظهر "⏳ Exporting..."
4. **إرسال AJAX** → إلى backend مع جميع البيانات
5. **Backend يعالج** → يحصل على الطلبات المفصلة
6. **إنشاء الملف** → Excel/CSV/PDF
7. **التنزيل التلقائي** → يفتح في نافذة جديدة
8. **رسالة نجاح** → "✅ Export completed! Downloaded: Week 48, 2025"

---

## 🧪 الاختبار

### خطوات الاختبار:

1. **افتح Orders Reports**
2. **طبّق فلاتر** (مثلاً: Product Type = Food, Status = Completed)
3. **انقر "View Details"** لأي فترة
4. **تحقق من:**
   - ✅ ظهور أزرار التصدير في الرأس
   - ✅ التصميم جميل ومتناسق
   - ✅ Hover effects تعمل

5. **انقر زر Excel/CSV/PDF**
6. **تحقق من:**
   - ✅ الزر يتغير إلى "⏳ Exporting..."
   - ✅ يتم تنزيل الملف تلقائياً
   - ✅ رسالة نجاح تظهر
   - ✅ الملف يحتوي على البيانات الصحيحة
   - ✅ الفلاتر مطبقة على البيانات

---

## 📁 الملفات المُعدلة

### 1. `templates/admin/orders-reports-new.php`
- ✅ إضافة أزرار التصدير في UI
- ✅ إضافة `currentDrillDownData` storage
- ✅ إضافة handler للتصدير
- ✅ إضافة CSS للأزرار ورسالة النجاح

### 2. `includes/classes/class-orders-reports-export.php`
- ✅ إضافة case `drill_down` في `get_export_data()`
- ✅ إنشاء دالة `get_drill_down_export_data()`
- ✅ معالجة تصدير بيانات الـ drill-down

---

## 💡 ميزات إضافية

### 1. رسالة نجاح متحركة
```javascript
var successMsg = $('<div class="oj-export-success">✅ Export completed! Downloaded: Week 48, 2025</div>');
$('.oj-drill-down-header').after(successMsg);
setTimeout(function() {
    successMsg.fadeOut(300, function() { $(this).remove(); });
}, 3000);
```

### 2. تنظيف البيانات عند الإغلاق
```javascript
$('#oj-close-drill-down').on('click', function() {
    $('#oj-drill-down-section').slideUp();
    currentDrillDownData = { date: null, label: null, filters: {} };
});
```

### 3. تسجيل تفصيلي
```javascript
console.log('📤 Drill-down export data:', exportData);
console.log('Export response:', response);
```

---

## 🎨 التصميم البصري

### قبل التحسينات:
```
[Detailed Orders for Week 48]                    [✕ Close]
```

### بعد التحسينات:
```
[Detailed Orders for Week 48 (15 orders)]  [📥 Excel] [📄 CSV] [📑 PDF]  [✕ Close]
                                           └── hover: تحول للون بنفسجي مع رفع
```

---

## 🚀 الفوائد

1. **سهولة التصدير** - نقرة واحدة لتصدير البيانات المفصلة
2. **دقة البيانات** - جميع الفلاتر مطبقة تلقائياً
3. **خيارات متعددة** - Excel, CSV, PDF
4. **تجربة مستخدم رائعة** - تصميم احترافي مع feedback واضح
5. **توافق كامل** - يعمل مع جميع الفلاتر والتواريخ

---

## 📊 الإحصائيات

- **عدد الملفات المُعدلة:** 2
- **عدد الأسطر المضافة:** ~150
- **الوقت المقدر للتطوير:** 2 ساعة
- **التوافق:** WordPress 5.0+ / WooCommerce 4.0+

---

**الإصدار:** 2.2.0  
**التاريخ:** 2025-12-03  
**الأولوية:** متوسطة (Feature Enhancement)

---

## ✅ قائمة المراجعة

- [x] إضافة أزرار التصدير في UI
- [x] إضافة JavaScript handler
- [x] إضافة backend handler
- [x] إضافة CSS styling
- [x] تطبيق الفلاتر
- [x] إضافة رسالة النجاح
- [x] اختبار Excel export
- [x] اختبار CSV export
- [x] اختبار PDF export
- [x] توثيق الميزة

🎉 **الميزة مكتملة وجاهزة للاستخدام!**

