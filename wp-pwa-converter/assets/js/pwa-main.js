/**
 * WP PWA Converter - Frontend JavaScript
 */

(function() {
    'use strict';
    
    // متغيرات عامة
    let deferredPrompt;
    let isOnline = navigator.onLine;
    
    // تسجيل Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register(wpPwaData.serviceWorkerUrl)
                .then(function(registration) {
                    console.log('Service Worker registered successfully:', registration.scope);
                    
                    // التحقق من التحديثات
                    checkForUpdates(registration);
                })
                .catch(function(error) {
                    console.error('Service Worker registration failed:', error);
                });
        });
    }
    
    // التحقق من التحديثات
    function checkForUpdates(registration) {
        registration.addEventListener('updatefound', function() {
            const newWorker = registration.installing;
            
            newWorker.addEventListener('statechange', function() {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    // يوجد تحديث جديد
                    showUpdatePrompt();
                }
            });
        });
    }
    
    // عرض رسالة التحديث
    function showUpdatePrompt() {
        const promptDiv = document.createElement('div');
        promptDiv.className = 'wp-pwa-update-prompt show';
        promptDiv.innerHTML = `
            <span>يوجد تحديث جديد للتطبيق!</span>
            <button id="wp-pwa-reload-btn">تحديث الآن</button>
        `;
        document.body.appendChild(promptDiv);
        
        document.getElementById('wp-pwa-reload-btn').addEventListener('click', function() {
            window.location.reload();
        });
    }
    
    // التعامل مع حدث التثبيت
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        
        // عرض رسالة التثبيت المخصصة
        showInstallPrompt();
    });
    
    // عرض رسالة التثبيت
    function showInstallPrompt() {
        // التحقق من أن المستخدم لم يرفض التثبيت سابقاً
        if (localStorage.getItem('wp_pwa_install_dismissed')) {
            return;
        }
        
        const promptDiv = document.createElement('div');
        promptDiv.className = 'wp-pwa-install-prompt show';
        promptDiv.innerHTML = `
            <h3>تثبيت التطبيق</h3>
            <p>ثبّت التطبيق على جهازك للوصول السريع والعمل دون اتصال</p>
            <div class="buttons">
                <button class="install-btn" id="wp-pwa-install-btn">تثبيت</button>
                <button class="dismiss-btn" id="wp-pwa-dismiss-btn">لاحقاً</button>
            </div>
        `;
        document.body.appendChild(promptDiv);
        
        // زر التثبيت
        document.getElementById('wp-pwa-install-btn').addEventListener('click', function() {
            promptDiv.remove();
            
            if (deferredPrompt) {
                deferredPrompt.prompt();
                
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                });
            }
        });
        
        // زر الرفض
        document.getElementById('wp-pwa-dismiss-btn').addEventListener('click', function() {
            promptDiv.remove();
            localStorage.setItem('wp_pwa_install_dismissed', 'true');
        });
    }
    
    // مراقبة حالة الاتصال
    window.addEventListener('online', function() {
        if (!isOnline) {
            isOnline = true;
            showOnlineIndicator();
        }
    });
    
    window.addEventListener('offline', function() {
        if (isOnline) {
            isOnline = false;
            showOfflineIndicator();
        }
    });
    
    // عرض مؤشر Offline
    function showOfflineIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'wp-pwa-offline-indicator show';
        indicator.innerHTML = '⚠️ أنت غير متصل بالإنترنت - يتم عرض المحتوى المخزن';
        document.body.insertBefore(indicator, document.body.firstChild);
    }
    
    // عرض مؤشر Online
    function showOnlineIndicator() {
        const offlineIndicator = document.querySelector('.wp-pwa-offline-indicator');
        if (offlineIndicator) {
            offlineIndicator.remove();
        }
        
        const indicator = document.createElement('div');
        indicator.className = 'wp-pwa-online-indicator show';
        indicator.innerHTML = '✓ تم استعادة الاتصال بالإنترنت';
        document.body.insertBefore(indicator, document.body.firstChild);
        
        // إخفاء المؤشر بعد 3 ثواني
        setTimeout(function() {
            indicator.classList.remove('show');
            setTimeout(function() {
                indicator.remove();
            }, 300);
        }, 3000);
    }
    
    // التعامل مع حدث التثبيت الناجح
    window.addEventListener('appinstalled', function() {
        console.log('PWA was installed successfully');
        
        // يمكنك إضافة تتبع أو إشعار هنا
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install', {
                'event_category': 'PWA',
                'event_label': 'App Installed'
            });
        }
    });
    
    // طلب الإذن بالإشعارات (إذا كانت مفعلة)
    function requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    console.log('Notification permission granted');
                }
            });
        }
    }
    
    // استدعاء طلب الإذن بعد تفاعل المستخدم
    document.addEventListener('click', function() {
        requestNotificationPermission();
    }, { once: true });
    
})();
