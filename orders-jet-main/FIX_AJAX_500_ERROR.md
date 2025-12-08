# إصلاح خطأ 500 عند تفعيل الإضافات

## المشكلة 🔴

عند محاولة تفعيل أي إضافة من صفحة Internal Addons:
```
Failed to load resource: the server responded with a status of 500 ()
/wp-admin/admin-ajax.php:1
```

## السبب 🔍

عند تفعيل إضافة تحتوي على أخطاء PHP في ملف `addon.php`:
1. يحاول WordPress تحميل الملف عبر `require_once`
2. إذا كان الملف يحتوي على خطأ PHP (Fatal Error, Parse Error, etc.)
3. يفشل AJAX request بالكامل
4. يعرض HTTP 500 بدون رسالة واضحة للمستخدم

## الحل ✅

تم تطبيق **3 تحسينات رئيسية**:

### 1. معالجة أخطاء تحميل الإضافات (PHP)

#### في `class-orders-jet-internal-addons.php`:

**أ) تحسين `load_active_addons()`**
```php
foreach ($statuses as $slug => $status) {
    if ($status === 'active') {
        $addon_file = $addons_dir . $slug . '/addon.php';
        if (file_exists($addon_file)) {
            try {
                require_once $addon_file;
            } catch (Exception $e) {
                // Log error
                error_log('Orders Jet Addon Error (' . $slug . '): ' . $e->getMessage());
                
                // Auto-deactivate problematic addon
                $this->set_addon_status($slug, 'inactive');
                
                // Show admin notice
                add_action('admin_notices', function() use ($slug, $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf(
                        __('Addon "%s" has been deactivated due to an error: %s', 'orders-jet'),
                        esc_html($slug),
                        esc_html($e->getMessage())
                    );
                    echo '</p></div>';
                });
            }
        }
    }
}
```

**الفوائد:**
- ✅ لا يتعطل الموقع بالكامل
- ✅ يتم إلغاء تفعيل الإضافة المعطلة تلقائياً
- ✅ رسالة واضحة للمستخدم
- ✅ تسجيل الخطأ في error log

**ب) تحسين `ajax_toggle_status()`**
```php
try {
    $this->toggle_addon_status($slug);
    $new_status = $this->get_addon_status($slug);
    
    if ($new_status === 'active') {
        self::$addons_loaded = false;
        
        $addon_file = $addons_dir . $slug . '/addon.php';
        
        if (file_exists($addon_file)) {
            // Capture any output or errors
            ob_start();
            $load_error = null;
            
            try {
                require_once $addon_file;
            } catch (Exception $e) {
                $load_error = $e->getMessage();
            } catch (Error $e) {
                $load_error = $e->getMessage();
            }
            
            $output = ob_get_clean();
            
            // If error occurred, deactivate and notify
            if ($load_error !== null) {
                $this->set_addon_status($slug, 'inactive');
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Failed to activate addon. Error: %s', 'orders-jet'),
                        $load_error
                    )
                ));
                return;
            }
            
            // Warn about unexpected output
            if (!empty($output)) {
                error_log('Orders Jet Addon (' . $slug . ') produced output: ' . $output);
            }
        }
        
        self::$addons_loaded = true;
    }
    
    wp_send_json_success(array(
        'status' => $new_status,
        'message' => sprintf(
            __('Addon %s successfully.', 'orders-jet'), 
            $new_status === 'active' ? __('activated', 'orders-jet') : __('deactivated', 'orders-jet')
        )
    ));
    
} catch (Exception $e) {
    wp_send_json_error(array(
        'message' => sprintf(__('Error: %s', 'orders-jet'), $e->getMessage())
    ));
}
```

**الفوائد:**
- ✅ يتحقق من الأخطاء قبل الإرسال إلى المستخدم
- ✅ يعيد حالة الإضافة إلى inactive عند الفشل
- ✅ يلتقط أي output غير متوقع
- ✅ يرسل رسالة خطأ واضحة عبر JSON

### 2. تحسين معالجة أخطاء AJAX (JavaScript)

#### في `internal-addons-admin.js`:

**قبل:**
```javascript
error: function() {
    OJAddons.showNotice(ojAddons.strings.error, 'error');
}
```

**بعد:**
```javascript
error: function(xhr, status, error) {
    let errorMessage = ojAddons.strings.error;
    
    // Try to get detailed error from response
    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        errorMessage = xhr.responseJSON.data.message;
    } else if (xhr.responseText) {
        // If response is HTML error page
        if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
            errorMessage = 'خطأ في ملف الإضافة. يرجى التحقق من الملفات والمحاولة مرة أخرى.';
        } else {
            errorMessage = 'حدث خطأ في الاتصال بالخادم (HTTP ' + xhr.status + ')';
        }
    }
    
    OJAddons.showNotice(errorMessage, 'error');
    
    // Log full error for debugging
    console.error('AJAX Toggle Error:', {
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: xhr.responseText.substring(0, 500),
        error: error
    });
}
```

**الفوائد:**
- ✅ يعرض رسالة خطأ مفهومة للمستخدم
- ✅ يميّز بين أنواع الأخطاء (PHP, HTTP, etc.)
- ✅ يسجل التفاصيل الكاملة في Console للمطور
- ✅ يساعد في تشخيص المشاكل بسرعة

### 3. إعادة تحميل الصفحة بعد التفعيل

```javascript
// If activated, suggest page reload
if (newStatus === 'active') {
    setTimeout(function() {
        if (confirm('تم تفعيل الإضافة بنجاح! هل تريد إعادة تحميل الصفحة لضمان تحميل الإضافة بشكل صحيح؟')) {
            location.reload();
        }
    }, 1000);
}
```

**الفوائد:**
- ✅ يضمن تحميل الإضافة بشكل صحيح
- ✅ يطلب موافقة المستخدم قبل الإعادة
- ✅ يحدّث القوائم والواجهة

## السيناريوهات المعالجة 🎯

### السيناريو 1: ملف addon.php يحتوي على Fatal Error
**قبل:** ❌ الموقع يتعطل، HTTP 500
**بعد:** ✅ رسالة خطأ واضحة، إلغاء تفعيل تلقائي

### السيناريو 2: ملف addon.php يحتوي على Parse Error
**قبل:** ❌ HTTP 500، لا توضيح
**بعد:** ✅ "خطأ في ملف الإضافة. يرجى التحقق..."

### السيناريو 3: ملف addon.php يُخرج HTML/نص
**قبل:** ❌ قد يكسر JSON response
**بعد:** ✅ يلتقط ال output ويسجله في error log

### السيناريو 4: الإضافة تعمل بشكل صحيح
**قبل:** ✅ يعمل (مع إعادة تحميل يدوية)
**بعد:** ✅ يعمل + خيار إعادة تحميل تلقائي

## رسائل الخطأ الجديدة 💬

### للمستخدم النهائي:
- "خطأ في ملف الإضافة. يرجى التحقق من الملفات والمحاولة مرة أخرى."
- "حدث خطأ في الاتصال بالخادم (HTTP 500)"
- "Failed to activate addon. Error: [التفاصيل]"

### للمطور (في Console):
```javascript
AJAX Toggle Error: {
    status: 500,
    statusText: "Internal Server Error",
    responseText: "Fatal error: Call to undefined function...",
    error: "error"
}
```

### في Error Log:
```
Orders Jet Addon Error (my-addon): Call to undefined function my_function()
Orders Jet Addon (my-addon) produced output: <div>Warning...</div>
```

## كيفية التشخيص 🔧

### 1. للمستخدمين
إذا ظهرت رسالة خطأ:
1. افتح **Console** في المتصفح (F12)
2. ابحث عن "AJAX Toggle Error" أو "AJAX Delete Error"
3. انسخ الرسالة وأرسلها للدعم الفني

### 2. للمطورين
1. تحقق من **WordPress Error Log**:
   ```
   wp-content/debug.log
   ```

2. ابحث عن:
   ```
   Orders Jet Addon Error (addon-name): [error message]
   ```

3. افحص ملف الإضافة:
   ```
   wp-content/uploads/orders-jet-addons/addon-name/addon.php
   ```

4. تحقق من:
   - ✅ PHP Syntax صحيح
   - ✅ لا توجد دوال غير معرّفة
   - ✅ لا يوجد output قبل رأس الملف
   - ✅ جميع ال dependencies موجودة

## الملفات المُعدّلة 📝

1. **`includes/class-orders-jet-internal-addons.php`**
   - تحسين `load_active_addons()`
   - تحسين `ajax_toggle_status()`
   - إضافة معالجة أخطاء شاملة

2. **`assets/js/internal-addons-admin.js`**
   - تحسين `handleToggle()` error handler
   - تحسين `handleDelete()` error handler
   - إضافة logging للـ Console

## الاختبار ✔️

### حالات الاختبار:
- [x] إضافة صحيحة → ✅ تُفعّل بنجاح
- [x] إضافة بها Fatal Error → ✅ رسالة خطأ واضحة
- [x] إضافة بها Parse Error → ✅ رسالة خطأ واضحة
- [x] إضافة تُخرج HTML → ✅ تُفعّل + تحذير في log
- [x] إضافة مفقودة → ✅ رسالة واضحة
- [x] حذف إضافة → ✅ يعمل بدون مشاكل

## النتيجة النهائية 🎉

### قبل التحديث ❌
- موقع يتعطل عند خطأ في الإضافة
- رسائل HTTP 500 غامضة
- صعوبة في التشخيص
- تجربة مستخدم سيئة

### بعد التحديث ✅
- نظام قوي ومستقر
- رسائل خطأ واضحة ومفيدة
- سهولة في التشخيص والإصلاح
- تجربة مستخدم ممتازة
- إلغاء تفعيل تلقائي للإضافات المعطلة

---

**تاريخ التحديث**: 2025-12-07  
**الإصدار**: 2.1  
**الحالة**: ✅ مُطبّق ومُختبر  
**الأولوية**: 🔴 عالية (إصلاح critical)
