/**
 * WP PWA Converter - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // تهيئة Color Picker
    if ($.fn.wpColorPicker) {
        $('.wp-pwa-color-picker').wpColorPicker({
            change: function(event, ui) {
                updatePreview();
            }
        });
    }
    
    // رفع الأيقونات
    $('.wp-pwa-upload-icon').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var target = button.data('target');
        
        var mediaUploader = wp.media({
            title: 'اختر أيقونة',
            button: {
                text: 'استخدام هذه الأيقونة'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + target).val(attachment.url);
            $('#' + target + '_preview').html(
                '<img src="' + attachment.url + '" alt="Icon">' +
                '<button type="button" class="wp-pwa-remove-icon" data-target="' + target + '">×</button>'
            );
            updatePreview();
        });
        
        mediaUploader.open();
    });
    
    // حذف الأيقونات
    $(document).on('click', '.wp-pwa-remove-icon', function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $('#' + target).val('');
        $('#' + target + '_preview').html('');
        updatePreview();
    });
    
    // تحديث المعاينة
    function updatePreview() {
        var themeColor = $('#theme_color').val();
        var bgColor = $('#background_color').val();
        var icon192 = $('#icon_192').val();
        
        if (themeColor) {
            $('#preview-header').css('background-color', themeColor);
        }
        
        if (bgColor) {
            $('#preview-body').css('background-color', bgColor);
        }
        
        if (icon192) {
            $('#preview-icon').html('<img src="' + icon192 + '" alt="App Icon">');
        }
    }
    
    // اختبار PWA
    $('#test-pwa-btn').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultsDiv = $('#pwa-test-results');
        
        button.prop('disabled', true).text('جاري الاختبار...');
        resultsDiv.html('<div class="wp-pwa-loading"></div>');
        
        $.ajax({
            url: wpPwaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_pwa_test_pwa',
                nonce: wpPwaAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var html = '';
                    var tests = response.data;
                    
                    $.each(tests, function(key, test) {
                        html += '<div class="test-item ' + test.status + '">' + test.message + '</div>';
                    });
                    
                    resultsDiv.html(html);
                } else {
                    resultsDiv.html('<div class="wp-pwa-notice error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                resultsDiv.html('<div class="wp-pwa-notice error">حدث خطأ في الاتصال</div>');
            },
            complete: function() {
                button.prop('disabled', false).text('اختبار PWA');
            }
        });
    });
    
    // مسح الكاش
    $('#clear-cache-btn').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('هل أنت متأكد من مسح جميع الملفات المخزنة؟')) {
            return;
        }
        
        var button = $(this);
        var resultDiv = $('#clear-cache-result');
        
        button.prop('disabled', true).text('جاري المسح...');
        
        $.ajax({
            url: wpPwaAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_pwa_clear_cache',
                nonce: wpPwaAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="wp-pwa-notice success">' + response.data.message + '</div>');
                    // تحديث رقم الإصدار في الصفحة
                    var currentVersion = parseInt($('#cache_version').val());
                    $('#cache_version').val(currentVersion + 1);
                } else {
                    resultDiv.html('<div class="wp-pwa-notice error">' + response.data.message + '</div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="wp-pwa-notice error">حدث خطأ في الاتصال</div>');
            },
            complete: function() {
                button.prop('disabled', false).text('مسح جميع الملفات المخزنة');
            }
        });
    });
    
    // اختبار الإشعارات
    $('#test-notification-btn').on('click', function(e) {
        e.preventDefault();
        
        var resultDiv = $('#test-notification-result');
        
        // طلب الإذن
        if ('Notification' in window) {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    // إرسال إشعار تجريبي
                    new Notification('اختبار PWA', {
                        body: 'هذا إشعار تجريبي من تطبيق PWA',
                        icon: wpPwaAdmin.siteIcon || '',
                        badge: wpPwaAdmin.siteIcon || '',
                        vibrate: [200, 100, 200]
                    });
                    
                    resultDiv.html('<div class="wp-pwa-notice success">تم إرسال الإشعار بنجاح!</div>');
                } else {
                    resultDiv.html('<div class="wp-pwa-notice error">تم رفض الإذن بالإشعارات</div>');
                }
            });
        } else {
            resultDiv.html('<div class="wp-pwa-notice error">المتصفح لا يدعم الإشعارات</div>');
        }
    });
    
    // حفظ النموذج بـ AJAX (اختياري)
    $('.wp-pwa-settings form').on('submit', function(e) {
        // يمكنك إضافة حفظ بـ AJAX هنا إذا أردت
        // لكن الآن سنترك النموذج يُرسل بشكل عادي
    });
});
