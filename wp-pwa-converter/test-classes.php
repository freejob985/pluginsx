<?php
/**
 * ملف اختبار لكلاسات PWA
 * استخدم هذا الملف للتحقق من تحميل الكلاسات بشكل صحيح
 */

// تحميل WordPress
require_once('../../../wp-load.php');

echo '<h1>اختبار كلاسات WP PWA Converter</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';

echo '<h2>1. فحص تحميل الكلاسات</h2>';

$classes = array(
    'WP_PWA_Settings' => 'كلاس الإعدادات',
    'WP_PWA_Manifest' => 'كلاس Manifest',
    'WP_PWA_Service_Worker' => 'كلاس Service Worker',
    'WP_PWA_Admin' => 'كلاس لوحة التحكم',
    'WP_PWA_Converter' => 'الكلاس الرئيسي'
);

foreach ($classes as $class_name => $description) {
    $exists = class_exists($class_name);
    $status = $exists ? '<span class="success">✓ محمّل</span>' : '<span class="error">✗ غير محمّل</span>';
    echo "<p><strong>{$description} ({$class_name}):</strong> {$status}</p>";
}

echo '<h2>2. فحص الإعدادات</h2>';

if (class_exists('WP_PWA_Settings')) {
    echo '<p class="success">✓ كلاس WP_PWA_Settings موجود</p>';
    
    try {
        $settings = WP_PWA_Settings::get_settings();
        echo '<p class="success">✓ تم استرجاع الإعدادات بنجاح</p>';
        echo '<p class="info">عدد الإعدادات: ' . count($settings) . '</p>';
        echo '<h3>الإعدادات الحالية:</h3>';
        echo '<pre>' . print_r($settings, true) . '</pre>';
    } catch (Exception $e) {
        echo '<p class="error">✗ خطأ في استرجاع الإعدادات: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">✗ كلاس WP_PWA_Settings غير موجود!</p>';
    echo '<p>يرجى التحقق من الملف: <code>includes/class-pwa-settings.php</code></p>';
}

echo '<h2>3. فحص Manifest</h2>';

if (class_exists('WP_PWA_Manifest')) {
    echo '<p class="success">✓ كلاس WP_PWA_Manifest موجود</p>';
    
    try {
        $manifest = WP_PWA_Manifest::generate_manifest();
        echo '<p class="success">✓ تم إنشاء Manifest بنجاح</p>';
        echo '<h3>محتوى Manifest:</h3>';
        echo '<pre>' . print_r($manifest, true) . '</pre>';
        echo '<p><a href="' . home_url('/manifest.json') . '" target="_blank">عرض Manifest</a></p>';
    } catch (Exception $e) {
        echo '<p class="error">✗ خطأ في إنشاء Manifest: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">✗ كلاس WP_PWA_Manifest غير موجود!</p>';
}

echo '<h2>4. فحص Service Worker</h2>';

if (class_exists('WP_PWA_Service_Worker')) {
    echo '<p class="success">✓ كلاس WP_PWA_Service_Worker موجود</p>';
    echo '<p><a href="' . home_url('/sw.js') . '" target="_blank">عرض Service Worker</a></p>';
} else {
    echo '<p class="error">✗ كلاس WP_PWA_Service_Worker غير موجود!</p>';
}

echo '<h2>5. فحص الثوابت</h2>';

$constants = array(
    'WP_PWA_VERSION' => 'إصدار البلاجن',
    'WP_PWA_PLUGIN_DIR' => 'مسار البلاجن',
    'WP_PWA_PLUGIN_URL' => 'رابط البلاجن'
);

foreach ($constants as $const_name => $description) {
    $defined = defined($const_name);
    $status = $defined ? '<span class="success">✓ معرّف</span>' : '<span class="error">✗ غير معرّف</span>';
    $value = $defined ? constant($const_name) : 'N/A';
    echo "<p><strong>{$description} ({$const_name}):</strong> {$status}</p>";
    if ($defined) {
        echo "<p class=\"info\">القيمة: <code>{$value}</code></p>";
    }
}

echo '<h2>6. ملخص الاختبار</h2>';

$all_classes_exist = true;
foreach ($classes as $class_name => $description) {
    if (!class_exists($class_name)) {
        $all_classes_exist = false;
        break;
    }
}

if ($all_classes_exist) {
    echo '<p class="success" style="font-size: 18px;">✓ جميع الكلاسات محمّلة بنجاح!</p>';
    echo '<p class="info">البلاجن يعمل بشكل صحيح ✓</p>';
} else {
    echo '<p class="error" style="font-size: 18px;">✗ بعض الكلاسات غير محمّلة!</p>';
    echo '<p>يرجى التحقق من ملفات البلاجن في المجلد: <code>wp-content/plugins/wp-pwa-converter/includes/</code></p>';
}

echo '<hr>';
echo '<p style="text-align: center; color: #666;">
    <small>WP PWA Converter - ملف الاختبار | ' . date('Y-m-d H:i:s') . '</small>
</p>';
