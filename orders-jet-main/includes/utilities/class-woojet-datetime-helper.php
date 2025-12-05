<?php
declare(strict_types=1);
/**
 * WooJet DateTime Helper
 * 
 * Centralized timezone-aware date and time utilities
 * 
 * PROBLEM: Date/time handling scattered across codebase causing timezone bugs
 * SOLUTION: ONE place for ALL date/time operations
 * 
 * USAGE EXAMPLES:
 * 
 * // Get current date (server timezone)
 * $today = WooJet_DateTime_Helper::get_current_date(); // '2024-01-15'
 * 
 * // Get for JavaScript (use in templates)
 * $js_date = WooJet_DateTime_Helper::format_for_js(); // '2024-01-15T10:30:00+00:00'
 * 
 * // Get for HTML date input
 * <input type="date" value="<?php echo WooJet_DateTime_Helper::get_current_date(); ?>">
 * 
 * // Compare dates (timezone-aware)
 * if (WooJet_DateTime_Helper::is_same_day($order_date, $today)) {
 *     echo "Order placed today";
 * }
 * 
 * @package WooJet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooJet_DateTime_Helper {
    
    /**
     * Get current date in WordPress timezone
     * 
     * Uses WordPress's current_time() which respects site timezone settings
     * 
     * @param string $format Date format (default: 'Y-m-d')
     * @return string Formatted date in WordPress timezone
     */
    public static function get_current_date($format = 'Y-m-d') {
        return current_time($format);
    }
    
    /**
     * Get current datetime in WordPress timezone
     * 
     * @param string $format DateTime format (default: 'Y-m-d H:i:s')
     * @return string Formatted datetime in WordPress timezone
     */
    public static function get_current_datetime($format = 'Y-m-d H:i:s') {
        return current_time($format);
    }
    
    /**
     * Get current timestamp in WordPress timezone
     * 
     * IMPORTANT: Returns timestamp adjusted to WP timezone
     * Use this instead of time() for consistency
     * 
     * @return int Unix timestamp in WordPress timezone
     */
    public static function get_current_timestamp() {
        return current_time('timestamp');
    }
    
    /**
     * Convert date string to timestamp (timezone-aware)
     * 
     * @param string $date_string Date string to convert
     * @return int Unix timestamp
     */
    public static function date_to_timestamp($date_string) {
        if (empty($date_string)) {
            return 0;
        }
        
        // If already a timestamp, return it
        if (is_numeric($date_string)) {
            return (int) $date_string;
        }
        
        return strtotime($date_string);
    }
    
    /**
     * Format date for JavaScript (ISO 8601)
     * 
     * Use this when passing dates from PHP to JavaScript
     * JavaScript Date object will parse correctly
     * 
     * @param string|int|null $datetime DateTime string, timestamp, or null for current
     * @return string ISO 8601 formatted datetime
     */
    public static function format_for_js($datetime = null) {
        if ($datetime === null) {
            $datetime = self::get_current_datetime();
        } elseif (is_numeric($datetime)) {
            $datetime = date('Y-m-d H:i:s', (int) $datetime);
        }
        
        // Convert to ISO 8601 format (c)
        $timestamp = strtotime($datetime);
        return date('c', $timestamp);
    }
    
    /**
     * Get date formatted for HTML date input
     * 
     * HTML5 date inputs require YYYY-MM-DD format
     * 
     * @param string|int|null $datetime DateTime string, timestamp, or null for current
     * @return string Date in YYYY-MM-DD format
     */
    public static function get_input_date($datetime = null) {
        if ($datetime === null) {
            return self::get_current_date('Y-m-d');
        } elseif (is_numeric($datetime)) {
            return date('Y-m-d', (int) $datetime);
        }
        
        $timestamp = strtotime($datetime);
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Get datetime formatted for HTML datetime-local input
     * 
     * HTML5 datetime-local inputs require YYYY-MM-DDTHH:MM format
     * 
     * @param string|int|null $datetime DateTime string, timestamp, or null for current
     * @return string DateTime in YYYY-MM-DDTHH:MM format
     */
    public static function get_input_datetime($datetime = null) {
        if ($datetime === null) {
            return self::get_current_datetime('Y-m-d\TH:i');
        } elseif (is_numeric($datetime)) {
            return date('Y-m-d\TH:i', (int) $datetime);
        }
        
        $timestamp = strtotime($datetime);
        return date('Y-m-d\TH:i', $timestamp);
    }
    
    /**
     * Compare if two dates are the same day (timezone-aware)
     * 
     * Ignores time, only compares dates
     * 
     * @param string|int $date1 First date (string or timestamp)
     * @param string|int $date2 Second date (string or timestamp)
     * @return bool True if same day
     */
    public static function is_same_day($date1, $date2) {
        $day1 = self::get_input_date($date1);
        $day2 = self::get_input_date($date2);
        
        return $day1 === $day2;
    }
    
    /**
     * Check if date is today
     * 
     * @param string|int $date Date to check (string or timestamp)
     * @return bool True if date is today
     */
    public static function is_today($date) {
        return self::is_same_day($date, self::get_current_date());
    }
    
    /**
     * Check if date is in the past
     * 
     * @param string|int $date Date to check (string or timestamp)
     * @return bool True if date is before today
     */
    public static function is_past($date) {
        $check_date = self::get_input_date($date);
        $today = self::get_current_date('Y-m-d');
        
        return $check_date < $today;
    }
    
    /**
     * Check if date is in the future
     * 
     * @param string|int $date Date to check (string or timestamp)
     * @return bool True if date is after today
     */
    public static function is_future($date) {
        $check_date = self::get_input_date($date);
        $today = self::get_current_date('Y-m-d');
        
        return $check_date > $today;
    }
    
    /**
     * Get start of day timestamp (00:00:00)
     * 
     * @param string|int|null $date Date (string, timestamp, or null for today)
     * @return int Timestamp for start of day
     */
    public static function get_start_of_day($date = null) {
        if ($date === null) {
            $date = self::get_current_date();
        } elseif (is_numeric($date)) {
            $date = date('Y-m-d', (int) $date);
        }
        
        return strtotime($date . ' 00:00:00');
    }
    
    /**
     * Get end of day timestamp (23:59:59)
     * 
     * @param string|int|null $date Date (string, timestamp, or null for today)
     * @return int Timestamp for end of day
     */
    public static function get_end_of_day($date = null) {
        if ($date === null) {
            $date = self::get_current_date();
        } elseif (is_numeric($date)) {
            $date = date('Y-m-d', (int) $date);
        }
        
        return strtotime($date . ' 23:59:59');
    }
    
    /**
     * Format date for display (human-readable)
     * 
     * Uses WordPress date format from settings
     * 
     * @param string|int $date Date to format
     * @return string Formatted date
     */
    public static function format_date($date) {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = self::date_to_timestamp($date);
        $wp_date_format = get_option('date_format', 'F j, Y');
        
        return date_i18n($wp_date_format, $timestamp);
    }
    
    /**
     * Format datetime for display (human-readable)
     * 
     * Uses WordPress date and time formats from settings
     * 
     * @param string|int $datetime DateTime to format
     * @return string Formatted datetime
     */
    public static function format_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = self::date_to_timestamp($datetime);
        $wp_date_format = get_option('date_format', 'F j, Y');
        $wp_time_format = get_option('time_format', 'g:i a');
        
        return date_i18n($wp_date_format . ' ' . $wp_time_format, $timestamp);
    }
    
    /**
     * Get WordPress timezone string
     * 
     * @return string Timezone string (e.g., 'America/New_York')
     */
    public static function get_timezone_string() {
        return wp_timezone_string();
    }
    
    /**
     * Get timezone offset in hours
     * 
     * @return float Offset in hours (e.g., -5.0 for EST)
     */
    public static function get_timezone_offset() {
        $timezone = new DateTimeZone(self::get_timezone_string());
        $datetime = new DateTime('now', $timezone);
        $offset = $timezone->getOffset($datetime);
        
        return $offset / 3600; // Convert seconds to hours
    }
    
    /**
     * Calculate difference between two dates
     * 
     * @param string|int $date1 First date
     * @param string|int $date2 Second date
     * @param string $unit Unit ('days', 'hours', 'minutes', 'seconds')
     * @return int Difference in specified unit
     */
    public static function get_difference($date1, $date2, $unit = 'days') {
        $timestamp1 = self::date_to_timestamp($date1);
        $timestamp2 = self::date_to_timestamp($date2);
        
        $diff = abs($timestamp1 - $timestamp2);
        
        switch ($unit) {
            case 'seconds':
                return $diff;
            case 'minutes':
                return floor($diff / 60);
            case 'hours':
                return floor($diff / 3600);
            case 'days':
            default:
                return floor($diff / 86400);
        }
    }
    
    /**
     * Add/subtract days from a date
     * 
     * @param string|int $date Base date
     * @param int $days Number of days to add (negative to subtract)
     * @param string $format Output format
     * @return string Modified date
     */
    public static function modify_date($date, $days, $format = 'Y-m-d') {
        $timestamp = self::date_to_timestamp($date);
        $new_timestamp = strtotime("+{$days} days", $timestamp);
        
        return date($format, $new_timestamp);
    }
}

