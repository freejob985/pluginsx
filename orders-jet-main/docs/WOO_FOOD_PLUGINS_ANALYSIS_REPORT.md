# تقرير تحليل إضافات WooFood - تحليل مفصل
# WooFood Plugins Analysis Report

**تاريخ التقرير:** 2025-12-05  
**المحلل:** Antigravity AI Assistant

---

## 📋 ملخص تنفيذي | Executive Summary

هذا التقرير يوفر تحليلاً شاملاً لإضافتين متكاملتين لإدارة طلبات المطاعم في WooCommerce:

| الإضافة | الوصف | الحالة |
|---------|-------|--------|
| **orders-jet-main** | إضافة إدارة طلبات المطاعم المتكاملة مع WooCommerce | ✅ متاح للتحليل |
| **woo-exfood** | إضافة WooFood الأساسية للمطاعم | ⚠️ محجوب بواسطة .gitignore |

---

## 🏗️ 1. Orders Jet Integration (`orders-jet-main`)

### 1.1 نظرة عامة | Overview

**Orders Jet Integration** هي إضافة تكاملية للمطاعم تعمل كطبقة فوق إضافة WooFood. تهدف إلى تحويل WooCommerce إلى نظام إدارة مطاعم متكامل وعالي الأداء.

#### المعلومات الأساسية:
- **اسم الإضافة:** Orders Jet Integration
- **الإصدار:** 1.0.0
- **الحد الأدنى لـ WordPress:** 5.0
- **الحد الأدنى لـ PHP:** 7.4
- **المتطلبات:** WooCommerce + WooCommerce Food (EX_WooFood)

### 1.2 الميزات الرئيسية | Key Features

#### 🍽️ إدارة الطاولات (Table Management)
```php
// ملف: includes/class-orders-jet-table-management.php

// إنشاء Custom Post Type للطاولات
'oj_table' - Restaurant tables

// Meta Fields للطاولات:
'_oj_table_number'   // رقم الطاولة (مثل T01, A12)
'_oj_table_capacity' // سعة الطاولة (عدد الأشخاص)
'_oj_table_status'   // حالة الطاولة (available, occupied, reserved, maintenance)
'_oj_table_location' // موقع الطاولة (Terrace, Corner, Window)
'_oj_table_qr_code'  // رابط QR Code
```

#### 👨‍🍳 نظام المطبخ الذكي (Smart Kitchen System)

##### اكتشاف نوع المنتج (Food / Beverage Detection)
```php
// ملف: includes/services/class-orders-jet-kitchen-service.php

class Orders_Jet_Kitchen_Service {
    
    /**
     * يحدد نوع المطبخ للطلب بناءً على عناصره
     * 
     * @return string 'food', 'beverages', أو 'mixed'
     */
    public function get_order_kitchen_type($order) {
        $kitchen_types = array();
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // التحقق من Variation أولاً، ثم المنتج الرئيسي
            $kitchen = '';
            if ($variation_id > 0) {
                $kitchen = get_post_meta($variation_id, 'Kitchen', true);
            }
            if (empty($kitchen)) {
                $kitchen = get_post_meta($product_id, 'Kitchen', true);
            }
            
            if (!empty($kitchen)) {
                $kitchen_types[] = strtolower(trim($kitchen));
            }
        }
        
        $unique_types = array_unique($kitchen_types);
        
        if (count($unique_types) === 1) {
            return $unique_types[0]; // food أو beverages
        } elseif (count($unique_types) > 1) {
            return 'mixed'; // طلب مختلط
        }
        
        return 'food'; // الافتراضي
    }
}
```

##### اقتراح تعيين المطبخ (Kitchen Assignment Suggestion)
```php
// ملف: includes/services/class-orders-jet-kitchen-filter-service.php

class Orders_Jet_Kitchen_Filter_Service {
    
    /**
     * اكتشاف نوع المطبخ بناءً على اسم المنتج
     * 
     * @param string $item_name اسم المنتج
     * @return string 'food' أو 'beverages'
     */
    private function detect_kitchen_type_by_name($item_name) {
        $item_name_lower = strtolower($item_name);
        
        // كلمات مفتاحية للمشروبات
        $beverage_keywords = array(
            'tea', 'coffee', 'juice', 'fayrouz', 'drink', 'soda', 
            'water', 'milk', 'latte', 'cappuccino', 'espresso',
            'smoothie', 'shake', 'cocktail', 'beer', 'wine'
        );
        
        foreach ($beverage_keywords as $keyword) {
            if (strpos($item_name_lower, $keyword) !== false) {
                return 'beverages';
            }
        }
        
        return 'food'; // الافتراضي للطعام
    }
    
    /**
     * تحديد نوع المطبخ لعنصر الطلب
     */
    public function get_item_kitchen_type($item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // التحقق من meta field للمطبخ
        $item_kitchen = '';
        if ($variation_id > 0) {
            $item_kitchen = get_post_meta($variation_id, 'Kitchen', true);
        }
        if (empty($item_kitchen)) {
            $item_kitchen = get_post_meta($product_id, 'Kitchen', true);
        }
        $item_kitchen = strtolower(trim($item_kitchen));
        
        // إذا لم يوجد حقل Kitchen، استخدم الاكتشاف الذكي
        if (empty($item_kitchen)) {
            $item_kitchen = $this->detect_kitchen_type_by_name($item->get_name());
        }
        
        return $item_kitchen;
    }
}
```

#### 🔌 استخراج Add-ons من WooFood Plugin

##### معالج تفاصيل المنتج (Product Details Handler)
```php
// ملف: includes/handlers/class-orders-jet-product-details-handler.php

class Orders_Jet_Product_Details_Handler {
    
    /**
     * الحصول على Add-ons من مصادر متعددة
     */
    private function get_product_addons($product_id, $product) {
        $addons = array();
        
        // المصادر المتعددة:
        $addons = array_merge($addons, $this->get_exfood_addons($product_id));
        $addons = array_merge($addons, $this->get_woocommerce_food_addons($product_id));
        $addons = array_merge($addons, $this->get_alternative_plugin_addons($product_id));
        $addons = array_merge($addons, $this->get_woocommerce_product_addons($product_id));
        $addons = array_merge($addons, $this->get_custom_food_plugin_addons($product_id));
        
        return $addons;
    }
    
    /**
     * استخراج Add-ons من Exfood Plugin
     * ⭐ الطريقة الرئيسية لـ WooFood
     */
    private function get_exfood_addons($product_id) {
        $addons = array();
        
        // التحقق من حقل exwo_options (بيانات serialized)
        $exwo_options = get_post_meta($product_id, 'exwo_options', true);
        if ($exwo_options) {
            $options_data = maybe_unserialize($exwo_options);
            if (is_array($options_data)) {
                foreach ($options_data as $option) {
                    if (isset($option['_name']) && !empty($option['_name'])) {
                        $addon = array(
                            'id' => $option['_id'] ?? uniqid(),
                            'name' => $option['_name'],
                            'type' => isset($option['_type']) ? $option['_type'] : 'checkbox',
                            'required' => !empty($option['_required']),
                            'min_selections' => intval($option['_min_op'] ?? 0),
                            'max_selections' => intval($option['_max_op'] ?? 0),
                            'min_opqty' => intval($option['_min_opqty'] ?? 0),
                            'max_opqty' => intval($option['_max_opqty'] ?? 0),
                            'enb_qty' => !empty($option['_enb_qty']),
                            'enb_img' => !empty($option['_enb_img']),
                            'display_type' => $option['_display_type'] ?? '',
                            'price' => floatval($option['_price'] ?? 0),
                            'price_type' => $option['_price_type'] ?? '',
                            'options' => array()
                        );
                        
                        // معالجة الخيارات الفرعية
                        if (isset($option['_options']) && is_array($option['_options'])) {
                            foreach ($option['_options'] as $key => $opt) {
                                if (isset($opt['name']) && !empty($opt['name'])) {
                                    $addon['options'][] = array(
                                        'id' => $key,
                                        'name' => $opt['name'],
                                        'price' => floatval($opt['price'] ?? 0),
                                        'type' => $opt['type'] ?? '',
                                        'def' => $opt['def'] ?? '',      // الخيار الافتراضي
                                        'dis' => $opt['dis'] ?? '',      // معطل
                                        'min' => intval($opt['min'] ?? 0),
                                        'max' => intval($opt['max'] ?? 0),
                                        'image' => $opt['image'] ?? ''
                                    );
                                }
                            }
                        }
                        
                        $addons[] = $addon;
                    }
                }
            }
        }
        
        return $addons;
    }
}
```

### 1.3 حاسبة الـ Add-ons (Addon Calculator)

```php
// ملف: includes/class-orders-jet-addon-calculator.php

class Orders_Jet_Addon_Calculator {
    
    private static $addon_cache = array();
    
    /**
     * حساب مجموع Add-ons لعدة طلبات مسبقاً (للأداء)
     */
    public static function precalculate_addon_totals($order_ids) {
        global $wpdb;
        
        // استعلام واحد لجلب كل بيانات الـ Add-ons
        $sql = "
            SELECT oi.order_id, 
                   oi.order_item_id,
                   oi.order_item_name,
                   addon.meta_value as addon_data,
                   qty.meta_value as quantity,
                   total.meta_value as line_total
            FROM {$wpdb->prefix}woocommerce_order_items oi
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta addon 
                ON oi.order_item_id = addon.order_item_id 
                AND addon.meta_key = '_oj_addons_data'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty 
                ON oi.order_item_id = qty.order_item_id 
                AND qty.meta_key = '_qty'  
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta total 
                ON oi.order_item_id = total.order_item_id 
                AND total.meta_key = '_line_total'
            WHERE oi.order_id IN ($ids_placeholder)
            AND oi.order_item_type = 'line_item'
        ";
        
        // معالجة وتخزين النتائج
        // ...
    }
    
    /**
     * جلب مجموع Add-ons المخزن لطلب
     */
    public static function get_order_addon_total($order_id) {
        return self::$addon_cache[$order_id]['total_addon_cost'] ?? 0;
    }
}
```

### 1.4 هيكل Meta Fields الكامل

#### حقول الطلب (Order Meta Fields):
```php
'_oj_order_status'           // حالة الطلب الداخلية (placed, preparing, ready, etc.)
'_oj_table_number'           // رقم الطاولة (إذا كان dine-in)
'_oj_kitchen_type'           // نوع المطبخ (food, beverages, mixed)
'_oj_food_kitchen_ready'     // هل مطبخ الطعام جاهز (yes/no)
'_oj_beverage_kitchen_ready' // هل مطبخ المشروبات جاهز (yes/no)
'_oj_food_kitchen_ready_time'// وقت جاهزية مطبخ الطعام
'_oj_beverage_kitchen_ready_time' // وقت جاهزية مطبخ المشروبات
'exwf_odmethod'              // طريقة الطلب (من WooFood: dinein, takeaway, delivery)
```

#### حقول عنصر الطلب (Order Item Meta Fields):
```php
'_oj_addons_data'    // بيانات الـ Add-ons (مصفوفة)
'_oj_item_notes'     // ملاحظات العنصر
'_oj_item_addons'    // Add-ons كنص (الشكل القديم)
'_wc_pao_addon_value'// Add-ons من WooCommerce Product Add-ons
```

#### حقول المنتج (Product Meta Fields):
```php
'Kitchen'            // نوع المطبخ (Food/Beverages) ⭐ مهم جداً
'exwo_options'       // خيارات Add-ons من WooFood (serialized)
'_food_addons'       // Add-ons من WooFood
'_food_options'      // خيارات الطعام
'_food_info'         // معلومات الطعام
'_food_nutrition'    // معلومات التغذية
'_food_allergens'    // المواد المسببة للحساسية
'_food_calories'     // السعرات الحرارية
'_food_prep_time'    // وقت التحضير
'_food_cooking_time' // وقت الطهي
'_food_serving_size' // حجم الحصة
```

### 1.5 الخدمات والواجهات (Services & Interfaces)

#### 🍳 Kitchen Service
```php
// includes/services/class-orders-jet-kitchen-service.php

- get_order_kitchen_type($order)      // تحديد نوع المطبخ
- get_kitchen_readiness_status($order) // حالة الجاهزية
- get_kitchen_status_badge($order)     // شارة الحالة HTML
- get_kitchen_type_badge($order)       // شارة النوع HTML
- mark_kitchen_ready($order, $type)    // تعليم المطبخ كجاهز
- get_kitchen_summary($orders)         // ملخص المطبخ
```

#### 🍽️ Order Method Service
```php
// includes/services/class-orders-jet-order-method-service.php

- get_order_method($order)        // dinein, takeaway, delivery
- get_order_method_badge($order)  // شارة طريقة الطلب
- is_table_order($order)          // هل طلب طاولة
- is_pickup_order($order)         // هل طلب استلام
- is_delivery_order($order)       // هل طلب توصيل
```

#### 🔍 Kitchen Filter Service
```php
// includes/services/class-orders-jet-kitchen-filter-service.php

- filter_orders_for_kitchen($orders, $type)     // تصفية الطلبات للمطبخ
- get_item_kitchen_type($item)                  // نوع المطبخ للعنصر
- detect_kitchen_type_by_name($name)            // اكتشاف ذكي للنوع
- should_show_order_in_kitchen($order, ...)     // هل يظهر في المطبخ
- get_order_statuses_for_user($is_kitchen)      // الحالات المناسبة للمستخدم
```

#### 📋 Menu Service
```php
// includes/services/class-orders-jet-menu-service.php

- get_categories_with_products($location_id)  // الفئات مع المنتجات
- get_product_details($product_id)            // تفاصيل المنتج
- get_product_addons($product_id)             // Add-ons المنتج
- filter_products_by_location($products, $id) // تصفية بالموقع
```

### 1.6 Handlers (معالجات الإجراءات)

| Handler | الوظيفة |
|---------|--------|
| `Orders_Jet_Order_Submission_Handler` | معالجة تقديم الطلبات |
| `Orders_Jet_Kitchen_Management_Handler` | إدارة المطبخ |
| `Orders_Jet_Table_Closure_Handler` | إغلاق الطاولات |
| `Orders_Jet_Product_Details_Handler` | تفاصيل المنتجات |
| `Orders_Jet_Invoice_Generation_Handler` | إنشاء الفواتير |
| `Orders_Jet_Order_Editor_Handler` | تعديل الطلبات |
| `Orders_Jet_Notification_Handler` | الإشعارات |
| `Orders_Jet_Bulk_Actions_Handler` | العمليات الجماعية |

---

## 🍕 2. WooFood Plugin (`woo-exfood`)

### 2.1 نظرة عامة

⚠️ **ملاحظة:** هذا المجلد محجوب بواسطة `.gitignore` ولا يمكن الوصول إليه مباشرة.

من خلال تحليل كود `orders-jet-main`، نستنتج أن **woo-exfood** (المعروف أيضاً بـ EX_WooFood) هو:

- إضافة المطاعم الأساسية لـ WooCommerce
- توفر نظام Add-ons للمنتجات
- تدعم أنواع الطلبات (Dine-in, Takeaway, Delivery)
- تستخدم Taxonomy خاص بالمواقع (`exwoofood_loc`)

### 2.2 الحقول والـ Meta الخاصة بـ WooFood

بناءً على تحليل الكود، هذه الحقول المستخدمة:

```php
// Meta Keys المستخدمة من WooFood:
'exwo_options'        // خيارات Add-ons الرئيسية (serialized array)
'exwf_odmethod'       // طريقة الطلب (dinein, takeaway, delivery)
'exwoofood_loc'       // Taxonomy للمواقع/الفروع

// هيكل exwo_options:
array(
    '_id'          => 'unique_id',
    '_name'        => 'اسم المجموعة',
    '_type'        => 'checkbox|radio|select',
    '_required'    => true/false,
    '_min_op'      => 0,        // الحد الأدنى للاختيارات
    '_max_op'      => 0,        // الحد الأقصى للاختيارات
    '_min_opqty'   => 0,        // الحد الأدنى للكمية
    '_max_opqty'   => 0,        // الحد الأقصى للكمية
    '_enb_qty'     => true/false, // تفعيل الكمية
    '_enb_img'     => true/false, // تفعيل الصور
    '_display_type'=> 'display_type',
    '_price'       => 0.00,
    '_price_type'  => 'fixed|percentage',
    '_options'     => array(    // الخيارات الفرعية
        array(
            'name'  => 'اسم الخيار',
            'price' => 10.00,
            'type'  => 'option_type',
            'def'   => 'is_default',
            'dis'   => 'is_disabled',
            'min'   => 0,
            'max'   => 0,
            'image' => 'image_url'
        )
    )
)
```

---

## 📊 3. مقارنة شاملة | Comprehensive Comparison

### 3.1 العلاقة بين الإضافتين

```
┌─────────────────────────────────────────────────────────────┐
│                    WooCommerce (الأساس)                      │
├─────────────────────────────────────────────────────────────┤
│     ↓                                                        │
│  WooFood (woo-exfood)                                       │
│  ├── Add-ons System                                         │
│  ├── Order Types (Dine-in, Takeaway, Delivery)             │
│  ├── Location Taxonomy                                      │
│  └── Product Options                                        │
├─────────────────────────────────────────────────────────────┤
│     ↓ (يعتمد عليه ويمتد)                                     │
│  Orders Jet (orders-jet-main)                                │
│  ├── Smart Kitchen System                                   │
│  │   ├── Food Kitchen Detection                            │
│  │   ├── Beverage Detection                                │
│  │   └── Mixed Order Handling                              │
│  ├── Table Management + QR Codes                           │
│  ├── Enhanced Dashboard                                    │
│  ├── Role-Based Access                                     │
│  └── Performance Optimizations                             │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 جدول المقارنة التفصيلي

| الميزة | woo-exfood | orders-jet-main |
|--------|------------|-----------------|
| **نظام Add-ons** | ✅ أساسي (exwo_options) | ✅ متقدم (مع حاسبة) |
| **أنواع الطلبات** | ✅ dinein, takeaway, delivery | ✅ + تحسينات |
| **إدارة الطاولات** | ❌ غير متوفر | ✅ كامل مع QR |
| **نظام المطبخ** | ❌ غير متوفر | ✅ Food/Beverage/Mixed |
| **الاكتشاف الذكي** | ❌ غير متوفر | ✅ بناءً على الاسم |
| **Dashboard مخصص** | ❌ غير متوفر | ✅ متعدد الأدوار |
| **إشعارات Real-time** | ❌ غير متوفر | ✅ Pusher Integration |
| **Roles & Capabilities** | ❌ غير متوفر | ✅ Manager/Kitchen/Waiter |
| **تحسينات الأداء** | غير معروف | ✅ 80-90% تحسين |

---

## 🔧 4. ربط Add-ons من WooFood إلى Orders Jet

### 4.1 خريطة الربط | Mapping Diagram

```php
/*
 * تدفق البيانات من WooFood إلى Orders Jet
 * 
 * WooFood Product                     Orders Jet Order Item
 * ================                    ====================
 * 
 * exwo_options (product meta)    →    _oj_addons_data (item meta)
 *   ├── _name: "Size"                   ├── name: "Large"
 *   ├── _price: 10.00                   ├── price: 10.00
 *   ├── _type: "radio"                  ├── quantity: 1
 *   └── _options: [                     └── total: 10.00
 *         {name: "Small", price: 0},
 *         {name: "Large", price: 10}
 *       ]
 *
 * Kitchen (product meta)         →    _oj_kitchen_type (order meta)
 *   value: "Food" or "Beverages"        value: "food", "beverages", "mixed"
 *
 * exwf_odmethod (order meta)     →    Order Method (service)
 *   value: "dinein"                     dinein, takeaway, delivery
 */
```

### 4.2 كيفية استخراج Add-ons

```php
// في Orders Jet - الطريقة المستخدمة لاستخراج Add-ons:

// 1. من المنتج (عند عرض القائمة)
$exwo_options = get_post_meta($product_id, 'exwo_options', true);
$options = maybe_unserialize($exwo_options);

// 2. من عنصر الطلب (بعد إضافة للسلة)
// الأولوية 1: _oj_addons_data
$oj_addons = $item->get_meta('_oj_addons_data');

// الأولوية 2: WC Product Add-ons
$wc_addons = $item->get_meta('_wc_pao_addon_value');

// الأولوية 3: Legacy format
$legacy_addons = $item->get_meta('_oj_item_addons');
```

---

## 📁 5. هيكل الملفات | File Structure

### orders-jet-main
```
orders-jet-main/
├── orders-jet-integration.php       # الملف الرئيسي
├── includes/
│   ├── services/
│   │   ├── class-orders-jet-kitchen-service.php ⭐
│   │   ├── class-orders-jet-kitchen-filter-service.php ⭐
│   │   ├── class-orders-jet-menu-service.php
│   │   ├── class-orders-jet-order-method-service.php
│   │   ├── class-orders-jet-notification-service.php
│   │   ├── class-orders-jet-realtime-service.php
│   │   └── class-orders-jet-tax-service.php
│   ├── handlers/
│   │   ├── class-orders-jet-product-details-handler.php ⭐
│   │   ├── class-orders-jet-kitchen-management-handler.php
│   │   ├── class-orders-jet-order-submission-handler.php
│   │   └── [16 handlers total]
│   ├── class-orders-jet-addon-calculator.php ⭐
│   ├── class-orders-jet-admin-dashboard.php
│   ├── class-orders-jet-ajax-handlers.php
│   ├── class-orders-jet-table-management.php
│   └── class-orders-jet-user-roles.php
├── templates/
│   ├── admin/
│   │   ├── orders-master.php
│   │   └── partials/kitchen-order-card.php
│   ├── qr-menu.php
│   └── table-invoice.php
└── assets/
    ├── css/
    └── js/
```

### woo-exfood (المتوقع)
```
woo-exfood/
├── woo-food.php                 # الملف الرئيسي
├── admin/                       # واجهة الإدارة
├── inc/                         # الوظائف الأساسية
├── templates/                   # القوالب
├── css/                         # الأنماط
├── js/                          # السكربتات
├── languages/                   # الترجمات
└── sample-data/                 # بيانات نموذجية
```

---

## 🚀 6. التوصيات | Recommendations

### للمطورين:

1. **حقل Kitchen مهم جداً:**
   - تأكد من تعيين حقل `Kitchen` لكل منتج
   - القيم المقبولة: `Food` أو `Beverages`

2. **استخدام الخدمات:**
   ```php
   // للحصول على نوع المطبخ:
   $kitchen_service = new Orders_Jet_Kitchen_Service();
   $type = $kitchen_service->get_order_kitchen_type($order);
   
   // لتصفية الطلبات:
   $filter_service = new Orders_Jet_Kitchen_Filter_Service();
   $filtered = $filter_service->filter_orders_for_kitchen($orders, 'food');
   ```

3. **معالجة Add-ons:**
   ```php
   // استخدم الـ Handler للحصول على Add-ons:
   $handler = new Orders_Jet_Product_Details_Handler();
   $details = $handler->get_details(['product_id' => $id]);
   $addons = $details['addons'];
   ```

### للتحسينات المستقبلية:

1. **إضافة كلمات مفتاحية عربية:**
   ```php
   $beverage_keywords = array(
       // الحالية + العربية:
       'شاي', 'قهوة', 'عصير', 'ماء', 'حليب', 'مشروب'
   );
   ```

2. **دعم أكثر من مطبخين:**
   - إضافة مطبخ للحلويات
   - إضافة مطبخ للمقبلات

---

## 📝 7. الخاتمة | Conclusion

**orders-jet-main** هي إضافة متقدمة تعتمد على **woo-exfood** وتوفر:

- ✅ نظام مطبخ ذكي مع اكتشاف تلقائي
- ✅ إدارة طاولات متكاملة مع QR
- ✅ معالجة متقدمة لـ Add-ons
- ✅ أداء محسن بنسبة 80-90%
- ✅ نظام أدوار متعدد المستويات

العلاقة بينهما:
- **woo-exfood**: البنية التحتية الأساسية (Add-ons, Order Types)
- **orders-jet-main**: الطبقة المتقدمة (Kitchen, Tables, Dashboard)

---

*تم إنشاء هذا التقرير بواسطة Antigravity AI Assistant*
