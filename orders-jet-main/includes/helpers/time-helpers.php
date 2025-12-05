<?php
declare(strict_types=1);
/**
 * Orders Jet - Centralized Time Helper Functions
 * ONE place for ALL time calculations to ensure consistency
 * 
 * NOW POWERED BY: WooJet_DateTime_Helper
 * These functions maintained for backward compatibility
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get human-readable time difference
 * CENTRALIZED function - use this EVERYWHERE for time calculations
 * 
 * NOW USES: WooJet_DateTime_Helper for timezone consistency
 * 
 * @param string|int $datetime MySQL datetime string OR Unix timestamp
 * @return string Human-readable time difference (e.g., "2 mins ago", "3 hours ago")
 */
function oj_get_time_ago($datetime) {
    // Convert to Unix timestamp
    $timestamp = WooJet_DateTime_Helper::date_to_timestamp($datetime);
    
    // Get current time in WordPress timezone
    $current_time = WooJet_DateTime_Helper::get_current_timestamp();
    
    // Calculate difference in seconds
    $diff = $current_time - $timestamp;
    
    // Handle negative differences (future times due to timezone issues)
    if ($diff < 0) {
        $diff = abs($diff);
    }
    
    // Format based on time difference
    if ($diff < 60) {
        // Less than 1 minute
        return $diff . ' secs ago';
    } elseif ($diff < 3600) {
        // Less than 1 hour
        $minutes = floor($diff / 60);
        return $minutes . ($minutes === 1 ? ' min ago' : ' mins ago');
    } elseif ($diff < 86400) {
        // Less than 1 day
        $hours = floor($diff / 3600);
        return $hours . ($hours === 1 ? ' hour ago' : ' hours ago');
    } else {
        // 1 day or more
        $days = floor($diff / 86400);
        return $days . ($days === 1 ? ' day ago' : ' days ago');
    }
}

/**
 * Get Unix timestamp for JavaScript
 * Returns timestamp that JavaScript can use reliably
 * 
 * NOW USES: WooJet_DateTime_Helper for consistency
 * 
 * @param string|int $datetime MySQL datetime string OR Unix timestamp
 * @return int Unix timestamp
 */
function oj_get_unix_timestamp($datetime) {
    return WooJet_DateTime_Helper::date_to_timestamp($datetime);
}

/**
 * Get current date (wrapper for WooJet_DateTime_Helper)
 * Convenience function for templates
 * 
 * @param string $format Date format (default: 'Y-m-d')
 * @return string Current date in WordPress timezone
 */
function oj_get_current_date($format = 'Y-m-d') {
    return WooJet_DateTime_Helper::get_current_date($format);
}

/**
 * Get current datetime (wrapper for WooJet_DateTime_Helper)
 * Convenience function for templates
 * 
 * @param string $format DateTime format (default: 'Y-m-d H:i:s')
 * @return string Current datetime in WordPress timezone
 */
function oj_get_current_datetime($format = 'Y-m-d H:i:s') {
    return WooJet_DateTime_Helper::get_current_datetime($format);
}

/**
 * Format date for display (wrapper for WooJet_DateTime_Helper)
 * Uses WordPress date format from settings
 * 
 * @param string|int $date Date to format
 * @return string Formatted date
 */
function oj_format_date($date) {
    return WooJet_DateTime_Helper::format_date($date);
}

/**
 * Format datetime for display (wrapper for WooJet_DateTime_Helper)
 * Uses WordPress date and time formats from settings
 * 
 * @param string|int $datetime DateTime to format
 * @return string Formatted datetime
 */
function oj_format_datetime($datetime) {
    return WooJet_DateTime_Helper::format_datetime($datetime);
}

