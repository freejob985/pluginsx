<?php
declare(strict_types=1);
/**
 * Orders Jet - Business Insights Calculator
 * Calculates meaningful business intelligence metrics for reports dashboard
 * 
 * @package Orders_Jet
 * @version 1.0.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Business_Insights {
    
    /**
     * Calculate enhanced business insights for reports dashboard
     * 
     * @param array $filter_params Current filter parameters
     * @return array Enhanced insights data
     */
    public function calculate_enhanced_insights($filter_params = array()) {
        $insights = array();
        
        // Get today's data
        $today_data = $this->get_period_data('today', $filter_params);
        
        // Get yesterday's data for comparison
        $yesterday_data = $this->get_period_data('yesterday', $filter_params);
        
        // Calculate insights
        $insights['revenue_performance'] = $this->calculate_revenue_performance($today_data, $yesterday_data);
        $insights['order_velocity'] = $this->calculate_order_velocity($today_data, $yesterday_data);
        $insights['avg_order_value'] = $this->calculate_avg_order_value($today_data, $yesterday_data);
        $insights['service_efficiency'] = $this->calculate_service_efficiency($today_data, $yesterday_data);
        $insights['active_orders'] = $this->calculate_active_orders($today_data, $yesterday_data);
        $insights['expected_revenue'] = $this->calculate_expected_revenue($today_data, $yesterday_data);
        
        return $insights;
    }
    
    /**
     * Get order data for a specific period
     * 
     * @param string $period 'today' or 'yesterday'
     * @param array $filter_params Filter parameters
     * @return array Period data
     */
    private function get_period_data($period, $filter_params = array()) {
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        
        if ($period === 'today') {
            $start_date = $now->format('Y-m-d 00:00:00');
            $end_date = $now->format('Y-m-d 23:59:59');
        } else { // yesterday
            $yesterday = $now->modify('-1 day');
            $start_date = $yesterday->format('Y-m-d 00:00:00');
            $end_date = $yesterday->format('Y-m-d 23:59:59');
        }
        
        // Build query args
        $args = array(
            'status' => array('wc-processing', 'wc-pending-payment', 'wc-completed'),
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1, // Get all orders for accurate calculations
            'return' => 'objects'
        );
        
        // Apply additional filters if provided
        if (!empty($filter_params['order_type'])) {
            $args['meta_query'] = array(
                array(
                    'key' => 'exwf_odmethod',
                    'value' => $filter_params['order_type'],
                    'compare' => '='
                )
            );
        }
        
        $orders = wc_get_orders($args);
        
        return $this->process_orders_data($orders);
    }
    
    /**
     * Process orders data to extract metrics
     * 
     * @param array $orders WooCommerce order objects
     * @return array Processed data
     */
    private function process_orders_data($orders) {
        $data = array(
            'total_orders' => count($orders),
            'total_revenue' => 0,
            'completed_orders' => 0,
            'processing_orders' => 0,
            'pending_orders' => 0,
            'completion_times' => array(),
            'hourly_distribution' => array(),
            'delayed_orders' => 0
        );
        
        foreach ($orders as $order) {
            $order_total = $order->get_total();
            $data['total_revenue'] += $order_total;
            
            $status = $order->get_status();
            switch ($status) {
                case 'completed':
                    $data['completed_orders']++;
                    break;
                case 'processing':
                    $data['processing_orders']++;
                    break;
                case 'pending-payment':
                    $data['pending_orders']++;
                    break;
            }
            
            // Calculate completion time for completed orders
            if ($status === 'completed') {
                $completion_time = $this->calculate_order_completion_time($order);
                if ($completion_time > 0) {
                    $data['completion_times'][] = $completion_time;
                    
                    // Count delayed orders (over 30 minutes)
                    if ($completion_time > 30) {
                        $data['delayed_orders']++;
                    }
                }
            }
            
            // Track hourly distribution
            $hour = $order->get_date_created()->format('H');
            if (!isset($data['hourly_distribution'][$hour])) {
                $data['hourly_distribution'][$hour] = 0;
            }
            $data['hourly_distribution'][$hour]++;
        }
        
        // Calculate average order value
        $data['avg_order_value'] = $data['total_orders'] > 0 ? $data['total_revenue'] / $data['total_orders'] : 0;
        
        // Calculate average completion time
        $data['avg_completion_time'] = !empty($data['completion_times']) ? 
            array_sum($data['completion_times']) / count($data['completion_times']) : 0;
        
        // Find peak hour
        $data['peak_hour'] = $this->find_peak_hour($data['hourly_distribution']);
        
        return $data;
    }
    
    /**
     * Calculate order completion time in minutes
     * 
     * @param WC_Order $order Order object
     * @return int Completion time in minutes
     */
    private function calculate_order_completion_time($order) {
        $created_time = $order->get_date_created();
        $completed_time = $order->get_date_completed();
        
        if (!$created_time || !$completed_time) {
            return 0;
        }
        
        $diff = $completed_time->getTimestamp() - $created_time->getTimestamp();
        return round($diff / 60); // Convert to minutes
    }
    
    /**
     * Find peak hour from hourly distribution
     * 
     * @param array $hourly_distribution Hourly order counts
     * @return array Peak hour data
     */
    private function find_peak_hour($hourly_distribution) {
        if (empty($hourly_distribution)) {
            return array('hour' => null, 'orders' => 0, 'formatted' => 'N/A');
        }
        
        $peak_hour = array_keys($hourly_distribution, max($hourly_distribution))[0];
        $peak_orders = $hourly_distribution[$peak_hour];
        
        // Format hour for display
        $formatted_hour = sprintf('%02d:00', $peak_hour);
        
        return array(
            'hour' => $peak_hour,
            'orders' => $peak_orders,
            'formatted' => $formatted_hour
        );
    }
    
    /**
     * Calculate revenue performance insight
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array Revenue performance insight
     */
    private function calculate_revenue_performance($today_data, $yesterday_data) {
        $today_revenue = $today_data['total_revenue'];
        $yesterday_revenue = $yesterday_data['total_revenue'];
        
        $change_amount = $today_revenue - $yesterday_revenue;
        $change_percent = $yesterday_revenue > 0 ? ($change_amount / $yesterday_revenue) * 100 : 0;
        
        return array(
            'title' => '💰 Today\'s Revenue',
            'value' => wc_price($today_revenue),
            'comparison' => array(
                'change_amount' => $change_amount,
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount >= 0 ? 'up' : 'down',
                'trend_icon' => $change_amount >= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . wc_price($yesterday_revenue) . ')'
            )
        );
    }
    
    /**
     * Calculate order velocity insight
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array Order velocity insight
     */
    private function calculate_order_velocity($today_data, $yesterday_data) {
        $today_orders = $today_data['total_orders'];
        $yesterday_orders = $yesterday_data['total_orders'];
        
        $change_amount = $today_orders - $yesterday_orders;
        $change_percent = $yesterday_orders > 0 ? ($change_amount / $yesterday_orders) * 100 : 0;
        
        $peak_info = $today_data['peak_hour'];
        $peak_text = $peak_info['formatted'] !== 'N/A' ? 
            'Peak: ' . $peak_info['formatted'] . ' (' . $peak_info['orders'] . ' orders)' : 
            'No peak identified';
        
        return array(
            'title' => '🚀 Orders Today',
            'value' => $today_orders . ' orders',
            'comparison' => array(
                'change_amount' => $change_amount,
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount >= 0 ? 'up' : 'down',
                'trend_icon' => $change_amount >= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . $yesterday_orders . ' orders)'
            ),
            'additional_info' => $peak_text
        );
    }
    
    /**
     * Calculate average order value insight
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array AOV insight
     */
    private function calculate_avg_order_value($today_data, $yesterday_data) {
        $today_aov = $today_data['avg_order_value'];
        $yesterday_aov = $yesterday_data['avg_order_value'];
        
        $change_amount = $today_aov - $yesterday_aov;
        $change_percent = $yesterday_aov > 0 ? ($change_amount / $yesterday_aov) * 100 : 0;
        
        // Set target AOV (can be made configurable later)
        $target_aov = 30.00;
        
        return array(
            'title' => '📊 Avg Order Value',
            'value' => wc_price($today_aov),
            'comparison' => array(
                'change_amount' => $change_amount,
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount >= 0 ? 'up' : 'down',
                'trend_icon' => $change_amount >= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . wc_price($yesterday_aov) . ')'
            ),
            'additional_info' => 'Target: ' . wc_price($target_aov)
        );
    }
    
    /**
     * Calculate service efficiency insight
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array Service efficiency insight
     */
    private function calculate_service_efficiency($today_data, $yesterday_data) {
        $today_avg_time = $today_data['avg_completion_time'];
        $yesterday_avg_time = $yesterday_data['avg_completion_time'];
        
        $change_amount = $today_avg_time - $yesterday_avg_time;
        $change_percent = $yesterday_avg_time > 0 ? ($change_amount / $yesterday_avg_time) * 100 : 0;
        
        $delayed_orders = $today_data['delayed_orders'];
        $alert_text = $delayed_orders > 0 ? 
            $delayed_orders . ' orders > 30 min ⚠️' : 
            'All orders on time ✅';
        
        return array(
            'title' => '⚡ Avg Completion Time',
            'value' => round($today_avg_time) . ' min',
            'comparison' => array(
                'change_amount' => round($change_amount),
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount <= 0 ? 'up' : 'down', // Lower time is better
                'trend_icon' => $change_amount <= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . round($yesterday_avg_time) . ' min)'
            ),
            'additional_info' => $alert_text,
            'alert_level' => $delayed_orders > 0 ? 'warning' : 'success'
        );
    }
    
    /**
     * Calculate active orders insight
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array Active orders insight
     */
    private function calculate_active_orders($today_data, $yesterday_data) {
        $active_orders = $today_data['processing_orders'] + $today_data['pending_orders'];
        $yesterday_active = $yesterday_data['processing_orders'] + $yesterday_data['pending_orders'];
        
        $change_amount = $active_orders - $yesterday_active;
        $change_percent = $yesterday_active > 0 ? ($change_amount / $yesterday_active) * 100 : 0;
        
        // Determine alert level based on active orders count
        $alert_level = '';
        if ($active_orders > 20) {
            $alert_level = 'warning'; // High volume
        } elseif ($active_orders > 10) {
            $alert_level = ''; // Normal
        } else {
            $alert_level = 'success'; // Low/manageable
        }
        
        $status_breakdown = '';
        if ($today_data['processing_orders'] > 0 && $today_data['pending_orders'] > 0) {
            $status_breakdown = $today_data['processing_orders'] . ' processing, ' . $today_data['pending_orders'] . ' pending';
        } elseif ($today_data['processing_orders'] > 0) {
            $status_breakdown = $today_data['processing_orders'] . ' processing orders';
        } elseif ($today_data['pending_orders'] > 0) {
            $status_breakdown = $today_data['pending_orders'] . ' pending payment';
        } else {
            $status_breakdown = 'All orders completed ✅';
        }
        
        return array(
            'title' => '🔄 Active Orders',
            'value' => $active_orders . ' orders',
            'comparison' => array(
                'change_amount' => $change_amount,
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount <= 0 ? 'up' : 'down', // Fewer active orders is better
                'trend_icon' => $change_amount <= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . $yesterday_active . ' orders)'
            ),
            'additional_info' => $status_breakdown,
            'alert_level' => $alert_level
        );
    }
    
    /**
     * Calculate expected revenue insight (based on active orders)
     * 
     * @param array $today_data Today's data
     * @param array $yesterday_data Yesterday's data
     * @return array Expected revenue insight
     */
    private function calculate_expected_revenue($today_data, $yesterday_data) {
        // Get active orders data
        $args = array(
            'status' => array('wc-processing', 'wc-pending-payment'),
            'limit' => -1,
            'return' => 'objects'
        );
        
        $active_orders = wc_get_orders($args);
        $expected_revenue = 0;
        $processing_revenue = 0;
        $pending_revenue = 0;
        
        foreach ($active_orders as $order) {
            $order_total = $order->get_total();
            $expected_revenue += $order_total;
            
            if ($order->get_status() === 'processing') {
                $processing_revenue += $order_total;
            } else {
                $pending_revenue += $order_total;
            }
        }
        
        // Calculate yesterday's expected revenue for comparison
        $yesterday_args = array(
            'status' => array('wc-processing', 'wc-pending-payment'),
            'date_created' => date('Y-m-d 00:00:00', strtotime('-1 day')) . '...' . date('Y-m-d 23:59:59', strtotime('-1 day')),
            'limit' => -1,
            'return' => 'objects'
        );
        
        $yesterday_active_orders = wc_get_orders($yesterday_args);
        $yesterday_expected = 0;
        foreach ($yesterday_active_orders as $order) {
            $yesterday_expected += $order->get_total();
        }
        
        $change_amount = $expected_revenue - $yesterday_expected;
        $change_percent = $yesterday_expected > 0 ? ($change_amount / $yesterday_expected) * 100 : 0;
        
        // Create breakdown info
        $breakdown = '';
        if ($processing_revenue > 0 && $pending_revenue > 0) {
            $breakdown = wc_price($processing_revenue) . ' processing, ' . wc_price($pending_revenue) . ' pending';
        } elseif ($processing_revenue > 0) {
            $breakdown = wc_price($processing_revenue) . ' in processing';
        } elseif ($pending_revenue > 0) {
            $breakdown = wc_price($pending_revenue) . ' pending payment';
        } else {
            $breakdown = 'No pending revenue';
        }
        
        return array(
            'title' => '💳 Expected Revenue',
            'value' => wc_price($expected_revenue),
            'comparison' => array(
                'change_amount' => $change_amount,
                'change_percent' => round($change_percent, 1),
                'trend' => $change_amount >= 0 ? 'up' : 'down',
                'trend_icon' => $change_amount >= 0 ? '↗️' : '↘️',
                'comparison_text' => 'vs Yesterday (' . wc_price($yesterday_expected) . ')'
            ),
            'additional_info' => $breakdown,
            'alert_level' => $expected_revenue > 1000 ? 'success' : ($expected_revenue > 500 ? '' : 'warning')
        );
    }
}
