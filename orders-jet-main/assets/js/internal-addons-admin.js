/**
 * Orders Jet - Internal Addons Admin JavaScript
 * Handles AJAX interactions for addons management
 */

(function($) {
    'use strict';
    
    const OJAddons = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Toggle addon status
            $(document).on('click', '.oj-toggle-btn', this.handleToggle);
            
            // Delete addon
            $(document).on('click', '.oj-delete-btn', this.handleDelete);
            
            // File input change
            $(document).on('change', '#addon_file', this.handleFileSelect);
        },
        
        /**
         * Handle toggle addon status
         */
        handleToggle: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $card = $button.closest('.oj-addon-card');
            const slug = $button.data('slug');
            
            if (!$button.length || !slug) {
                return;
            }
            
            // Add loading state
            $card.addClass('loading');
            $button.prop('disabled', true);
            
            $.ajax({
                url: ojAddons.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_addon_toggle_status',
                    nonce: ojAddons.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        const newStatus = response.data.status;
                        const $badge = $card.find('.oj-addon-status-badge');
                        
                        // Update badge
                        $badge.removeClass('active inactive')
                              .addClass(newStatus)
                              .text(newStatus === 'active' ? 'Active' : 'Inactive');
                        
                        // Update button
                        const isActive = newStatus === 'active';
                        $button.removeClass('button-primary button-secondary')
                               .addClass(isActive ? 'button-secondary' : 'button-primary');
                        
                        $button.find('.dashicons')
                               .removeClass('dashicons-dismiss dashicons-yes-alt')
                               .addClass(isActive ? 'dashicons-dismiss' : 'dashicons-yes-alt');
                        
                        $button.find('span:not(.dashicons)').text(
                            isActive ? 'Deactivate' : 'Activate'
                        );
                        
                        // Show success message
                        OJAddons.showNotice(response.data.message, 'success');
                        
                        // If activated, suggest page reload to ensure addon loads
                        if (newStatus === 'active') {
                            setTimeout(function() {
                                if (confirm('تم تفعيل الإضافة بنجاح! هل تريد إعادة تحميل الصفحة لضمان تحميل الإضافة بشكل صحيح؟')) {
                                    location.reload();
                                }
                            }, 1000);
                        }
                    } else {
                        OJAddons.showNotice(
                            response.data?.message || ojAddons.strings.error,
                            'error'
                        );
                    }
                },
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
                },
                complete: function() {
                    $card.removeClass('loading');
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Handle delete addon
         */
        handleDelete: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $card = $button.closest('.oj-addon-card');
            const slug = $button.data('slug');
            
            if (!$button.length || !slug) {
                return;
            }
            
            // Confirm deletion
            if (!confirm(ojAddons.strings.confirmDelete)) {
                return;
            }
            
            // Add loading state
            $card.addClass('loading');
            $button.prop('disabled', true);
            
            $.ajax({
                url: ojAddons.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oj_addon_delete',
                    nonce: ojAddons.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        // Remove card with animation
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Update count if exists
                            const $count = $('.oj-addons-count');
                            if ($count.length) {
                                const currentCount = parseInt($count.text().match(/\d+/)[0]) || 0;
                                const newCount = Math.max(0, currentCount - 1);
                                $count.text('(' + newCount + ')');
                            }
                            
                            // Show empty state if no addons left
                            if ($('.oj-addon-card').length === 0) {
                                location.reload();
                            }
                        });
                        
                        OJAddons.showNotice(response.data.message, 'success');
                    } else {
                        OJAddons.showNotice(
                            response.data?.message || ojAddons.strings.error,
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = ojAddons.strings.error;
                    
                    // Try to get detailed error from response
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        // If response is HTML error page
                        if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                            errorMessage = 'خطأ في حذف الإضافة. يرجى التحقق من صلاحيات الملفات.';
                        } else {
                            errorMessage = 'حدث خطأ في الاتصال بالخادم (HTTP ' + xhr.status + ')';
                        }
                    }
                    
                    OJAddons.showNotice(errorMessage, 'error');
                    
                    // Log full error for debugging
                    console.error('AJAX Delete Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText.substring(0, 500),
                        error: error
                    });
                },
                complete: function() {
                    $card.removeClass('loading');
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Handle file select
         */
        handleFileSelect: function(e) {
            const file = e.target.files[0];
            if (file) {
                const $label = $(this).siblings('.oj-upload-label');
                if ($label.length) {
                    $label.find('span:not(.dashicons)').text(file.name);
                }
            }
        },
        
        /**
         * Show notice message
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.oj-addons-notice').remove();
            
            // Create notice
            const $notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible oj-addons-notice')
                .html('<p>' + message + '</p>');
            
            // Insert at top
            $('.oj-addons-wrapper').prepend($notice);
            
            // Make dismissible
            $notice.append(
                $('<button>')
                    .addClass('notice-dismiss')
                    .html('<span class="screen-reader-text">Dismiss this notice.</span>')
                    .on('click', function() {
                        $(this).closest('.notice').fadeOut(300, function() {
                            $(this).remove();
                        });
                    })
            );
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        OJAddons.init();
    });
    
})(jQuery);
