<?php
/**
 * Orders Jet - Notification Item Template
 * Single notification display (for reference - actual rendering done in JS)
 * 
 * @package Orders_Jet
 * @version 1.0.0
 * 
 * Variables available:
 * @var array $notification Notification data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract notification data
$notification_id = $notification['id'] ?? '';
$type = $notification['type'] ?? '';
$order_number = $notification['order_number'] ?? '';
$table_number = $notification['table_number'] ?? '';
$created_at = $notification['created_at'] ?? '';
$read_by = $notification['read_by'] ?? array();

// Check if read by current user
$user_id = get_current_user_id();
$is_unread = !in_array($user_id, $read_by);
$unread_class = $is_unread ? 'unread' : '';

// Get icon and message
$icon = oj_get_notification_icon($type);
$message = oj_format_notification_message($notification);
$time_ago = oj_time_ago($created_at);
?>

<div class="oj-notification-item <?php echo esc_attr($unread_class); ?>" 
     data-notification-id="<?php echo esc_attr($notification_id); ?>">
    
    <div class="oj-notification-icon">
        <?php echo $icon; ?>
    </div>
    
    <div class="oj-notification-content">
        <div class="oj-notification-message">
            <?php echo esc_html($message); ?>
        </div>
        <div class="oj-notification-meta">
            <span class="oj-notification-time">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html($time_ago); ?>
            </span>
        </div>
    </div>
    
    <?php if ($is_unread): ?>
    <button class="oj-notification-mark-read" 
            data-notification-id="<?php echo esc_attr($notification_id); ?>"
            title="<?php _e('Mark as read', 'orders-jet'); ?>">
        <span class="dashicons dashicons-yes"></span>
    </button>
    <?php endif; ?>
    
</div>

