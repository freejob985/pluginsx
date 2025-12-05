<?php
/**
 * Orders BI Insights
 * 
 * Calculates business intelligence insights and metrics for the BI dashboard.
 * Works with Orders_BI_Query_Builder to provide actionable business insights.
 * 
 * @package Orders_Jet
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Orders_BI_Insights
 * 
 * Provides business intelligence insights including:
 * - Staff performance analysis
 * - Shift analysis and trends
 * - Discount intelligence and effectiveness
 * - Revenue trends and forecasting
 * - Table performance metrics
 * - Customer behavior insights
 */
class Orders_BI_Insights {
    
    /**
     * @var Orders_BI_Query_Builder Query builder instance
     */
    private $query_builder;
    
    /**
     * @var array Cached insights data
     */
    private $insights_cache;
    
    /**
     * Constructor
     * 
     * @param Orders_BI_Query_Builder $query_builder Query builder instance
     */
    public function __construct($query_builder) {
        $this->query_builder = $query_builder;
        $this->insights_cache = null;
    }
    
    /**
     * Calculate all BI insights - New 4-card approach with order status breakdown
     * 
     * @return array Array of 4 insight cards (Completed, Active, Cancelled, Refunded)
     */
    public function calculate_bi_insights() {
        // Use cache if available
        if ($this->insights_cache !== null) {
            return $this->insights_cache;
        }
        
        // Get order status breakdown
        $status_breakdown = $this->get_order_status_breakdown();
        
        // Calculate 4 universal cards with mini charts
        $insights = array(
            'completed' => $this->create_status_card(
                'completed', 
                '✅ Completed', 
                $status_breakdown,
                'success'
            ),
            'active' => $this->create_status_card(
                'active', 
                '🔄 Active', 
                $status_breakdown,
                'info'
            ),
            'cancelled' => $this->create_status_card(
                'cancelled', 
                '❌ Cancelled', 
                $status_breakdown,
                'warning'
            ),
            'refunded' => $this->create_status_card(
                'refunded', 
                '💸 Refunded', 
                $status_breakdown,
                'danger'
            )
        );
        
        // Cache the results
        $this->insights_cache = $insights;
        
        return $insights;
    }
    
    /**
     * Get order status breakdown for current filters/grouping
     * 
     * @return array Status breakdown with counts and revenue
     */
    private function get_order_status_breakdown() {
        // Get all orders matching current BI context (including BI filters)
        $all_orders = $this->get_all_orders_with_bi_context();
        
        $breakdown = array(
            'completed' => array('count' => 0, 'revenue' => 0),
            'active' => array('count' => 0, 'revenue' => 0),
            'cancelled' => array('count' => 0, 'revenue' => 0),
            'refunded' => array('count' => 0, 'revenue' => 0),
            'total' => array('count' => 0, 'revenue' => 0)
        );
        
        foreach ($all_orders as $order) {
            if (!($order instanceof WC_Order)) continue;
            
            $status = $order->get_status();
            $revenue = floatval($order->get_total());
            
            // Categorize by status
            $category = $this->categorize_order_status($status);
            
            $breakdown[$category]['count']++;
            $breakdown[$category]['revenue'] += $revenue;
            $breakdown['total']['count']++;
            $breakdown['total']['revenue'] += $revenue;
        }
        
        // Debug the final breakdown
        error_log("BI Status Breakdown Result - " . json_encode(array(
            'total_orders' => $breakdown['total']['count'],
            'completed' => $breakdown['completed']['count'],
            'active' => $breakdown['active']['count'],
            'cancelled' => $breakdown['cancelled']['count'],
            'refunded' => $breakdown['refunded']['count']
        )));
        
        return $breakdown;
    }
    
    /**
     * Get all orders with BI context applied (includes BI filters)
     * 
     * @return array All orders matching current BI filters and context
     */
    private function get_all_orders_with_bi_context() {
        // The 4-card status breakdown should ALWAYS show ALL filtered orders
        // regardless of pagination - we want the complete picture
        
        // Use the new method that gets all filtered orders (no pagination)
        $all_orders = $this->query_builder->get_all_filtered_orders();
        
        error_log("BI Status Breakdown - Mode: " . $this->query_builder->get_bi_mode() . 
                  ", Group By: " . $this->query_builder->get_group_by() . 
                  ", Total Orders Found: " . count($all_orders));
        
        return $all_orders;
    }
    
    /**
     * Categorize WooCommerce order status into our 4 categories
     * 
     * @param string $status WooCommerce order status
     * @return string Category (completed, active, cancelled, refunded)
     */
    private function categorize_order_status($status) {
        switch ($status) {
            case 'completed':
                return 'completed';
            
            case 'processing':
            case 'pending':
            case 'on-hold':
            case 'pending-payment':
                return 'active';
            
            case 'cancelled':
            case 'failed':
                return 'cancelled';
            
            case 'refunded':
                return 'refunded';
            
            default:
                return 'active'; // Default to active for unknown statuses
        }
    }
    
    /**
     * Create a status card with counts, revenue, percentages, and chart data
     * 
     * @param string $status Status key
     * @param string $title Card title
     * @param array $breakdown Status breakdown data
     * @param string $type Card type for styling
     * @return array Card data
     */
    private function create_status_card($status, $title, $breakdown, $type) {
        $status_data = $breakdown[$status];
        $total_data = $breakdown['total'];
        
        // Calculate percentages
        $count_percentage = $total_data['count'] > 0 
            ? round(($status_data['count'] / $total_data['count']) * 100) 
            : 0;
        
        $revenue_percentage = $total_data['revenue'] > 0 
            ? round(($status_data['revenue'] / $total_data['revenue']) * 100) 
            : 0;
        
        // Use the count percentage for the chart (matches the main text)
        $chart_percentage = $count_percentage;
        
        // Format the main value (orders with percentage)
        $main_value = sprintf(
            __('%d of %d Orders (%d%%)', 'orders-jet'),
            $status_data['count'],
            $total_data['count'],
            $count_percentage
        );
        
        // Format the subtitle (revenue with percentage)
        $subtitle = sprintf(
            __('%s of %s (%d%%)', 'orders-jet'),
            wc_price($status_data['revenue']),
            wc_price($total_data['revenue']),
            $revenue_percentage
        );
        
        // Create insight text
        $insight = $this->create_status_insight($status, $status_data, $count_percentage, $revenue_percentage);
        
        return array(
            'title' => $title,
            'value' => $main_value,
            'subtitle' => $subtitle,
            'insight' => $insight,
            'type' => $type,
            'percentage' => $chart_percentage, // NEW: For chart display
            'chart_color' => $this->get_chart_color($type), // NEW: Chart color
            'drill_down' => array(
                'status_filter' => $status
            )
        );
    }
    
    /**
     * Create insight text for status card
     * 
     * @param string $status Status key
     * @param array $status_data Status data
     * @param int $count_percentage Count percentage
     * @param int $revenue_percentage Revenue percentage
     * @return string Insight text
     */
    private function create_status_insight($status, $status_data, $count_percentage, $revenue_percentage) {
        switch ($status) {
            case 'completed':
                if ($count_percentage >= 80) {
                    return __('Excellent completion rate - strong operational performance', 'orders-jet');
                } elseif ($count_percentage >= 60) {
                    return __('Good completion rate - room for minor improvements', 'orders-jet');
                } else {
                    return __('Low completion rate - review operational processes', 'orders-jet');
                }
            
            case 'active':
                if ($count_percentage >= 30) {
                    return __('High active order volume - ensure adequate staffing', 'orders-jet');
                } elseif ($count_percentage >= 15) {
                    return __('Moderate active orders - normal operational flow', 'orders-jet');
                } else {
                    return __('Low active orders - potential for increased capacity', 'orders-jet');
                }
            
            case 'cancelled':
                if ($count_percentage >= 15) {
                    return __('High cancellation rate - investigate common causes', 'orders-jet');
                } elseif ($count_percentage >= 5) {
                    return __('Moderate cancellations - monitor for patterns', 'orders-jet');
                } else {
                    return __('Low cancellation rate - excellent customer satisfaction', 'orders-jet');
                }
            
            case 'refunded':
                if ($count_percentage >= 10) {
                    return __('High refund rate - review quality control processes', 'orders-jet');
                } elseif ($count_percentage >= 3) {
                    return __('Some refunds - normal business operations', 'orders-jet');
                } else {
                    return __('Minimal refunds - excellent service quality', 'orders-jet');
                }
            
            default:
                return __('Status analysis available', 'orders-jet');
        }
    }
    
    /**
     * Get chart colors for each status type
     * 
     * @param string $type Card type
     * @return string Hex color code
     */
    private function get_chart_color($type) {
        $colors = array(
            'success' => '#28a745',
            'info' => '#17a2b8', 
            'warning' => '#ffc107',
            'danger' => '#dc3545'
        );
        
        return $colors[$type] ?? '#6c757d';
    }
    
    /**
     * Calculate staff performance insight (Legacy - kept for compatibility)
     * 
     * @param array $summary Summary statistics
     * @return array Staff performance insight data
     */
    private function calculate_staff_performance_insight($summary) {
        // Get staff performance data when grouped by waiter
        if ($this->query_builder->get_group_by() === 'waiter') {
            $grouped_data = $this->query_builder->get_bi_data();
            
            if (!empty($grouped_data)) {
                // Find top performer
                $top_performer = $grouped_data[0]; // Already sorted by revenue
                $total_staff = count($grouped_data);
                
                return array(
                    'title' => __('👨‍💼 Staff Performance', 'orders-jet'),
                    'value' => sprintf(__('%d Active Staff', 'orders-jet'), $total_staff),
                    'subtitle' => sprintf(__('Top: %s', 'orders-jet'), $top_performer['group_label']),
                    'insight' => sprintf(
                        __('Best performer: %s with %s revenue', 'orders-jet'),
                        $top_performer['group_label'],
                        wc_price($top_performer['total_revenue'])
                    ),
                    'type' => 'staff',
                    'drill_down' => array(
                        'group_by' => 'waiter',
                        'filter' => 'staff_performance'
                    )
                );
            }
        }
        
        // Default/placeholder when not grouped by waiter
        return array(
            'title' => __('👨‍💼 Staff Performance', 'orders-jet'),
            'value' => __('Switch to Staff View', 'orders-jet'),
            'subtitle' => __('Group by Staff to see performance', 'orders-jet'),
            'insight' => __('Change grouping to "Staff Performance" to analyze waiter metrics', 'orders-jet'),
            'type' => 'staff',
            'drill_down' => array(
                'group_by' => 'waiter'
            )
        );
    }
    
    /**
     * Calculate shift analysis insight
     * 
     * @param array $summary Summary statistics
     * @return array Shift analysis insight data
     */
    private function calculate_shift_analysis_insight($summary) {
        // Get shift performance data when grouped by shift
        if ($this->query_builder->get_group_by() === 'shift') {
            $grouped_data = $this->query_builder->get_bi_data();
            
            if (!empty($grouped_data)) {
                // Find busiest shift
                $busiest_shift = $grouped_data[0]; // Already sorted by revenue
                
                return array(
                    'title' => __('🕐 Shift Analysis', 'orders-jet'),
                    'value' => sprintf(__('%d Shifts Active', 'orders-jet'), count($grouped_data)),
                    'subtitle' => sprintf(__('Busiest: %s', 'orders-jet'), $busiest_shift['group_label']),
                    'insight' => sprintf(
                        __('Peak shift: %s with %d orders (%s)', 'orders-jet'),
                        $busiest_shift['group_label'],
                        $busiest_shift['count'],
                        wc_price($busiest_shift['total_revenue'])
                    ),
                    'type' => 'shift',
                    'drill_down' => array(
                        'group_by' => 'shift',
                        'filter' => 'shift_analysis'
                    )
                );
            }
        }
        
        // Default analysis based on current data
        $avg_order_value = $summary['avg_order_value'] ?? 0;
        
        return array(
            'title' => __('🕐 Shift Analysis', 'orders-jet'),
            'value' => wc_price($avg_order_value),
            'subtitle' => __('Average Order Value', 'orders-jet'),
            'insight' => __('Switch to Shift Analysis to see time-based performance patterns', 'orders-jet'),
            'type' => 'shift',
            'drill_down' => array(
                'group_by' => 'shift'
            )
        );
    }
    
    /**
     * Calculate discount intelligence insight
     * 
     * @param array $summary Summary statistics
     * @return array Discount intelligence insight data
     */
    private function calculate_discount_intelligence_insight($summary) {
        $discount_rate = $summary['discount_rate'] ?? 0;
        $total_discount_amount = $summary['total_discount_amount'] ?? 0;
        $orders_with_discount = $summary['orders_with_discount'] ?? 0;
        
        // Determine discount effectiveness
        $effectiveness = 'Low';
        $color_class = 'warning';
        
        if ($discount_rate > 30) {
            $effectiveness = 'High';
            $color_class = 'success';
        } elseif ($discount_rate > 15) {
            $effectiveness = 'Medium';
            $color_class = 'info';
        }
        
        return array(
            'title' => __('💰 Discount Intelligence', 'orders-jet'),
            'value' => sprintf(__('%.1f%% Usage', 'orders-jet'), $discount_rate),
            'subtitle' => sprintf(__('%d orders with discounts', 'orders-jet'), $orders_with_discount),
            'insight' => sprintf(
                __('%s discount usage - %s total savings offered', 'orders-jet'),
                $effectiveness,
                wc_price($total_discount_amount)
            ),
            'type' => 'discount ' . $color_class,
            'drill_down' => array(
                'group_by' => 'discount_status',
                'filter' => 'discount_intelligence'
            )
        );
    }
    
    /**
     * Calculate revenue trends insight
     * 
     * @param array $summary Summary statistics
     * @return array Revenue trends insight data
     */
    private function calculate_revenue_trends_insight($summary) {
        $total_revenue = $summary['total_revenue'] ?? 0;
        $total_orders = $summary['total_orders'] ?? 0;
        $avg_order_value = $summary['avg_order_value'] ?? 0;
        
        // Calculate trend (simplified - compare with previous period would be more accurate)
        $trend_direction = 'stable';
        $trend_icon = '📊';
        
        // Simple heuristic based on order volume vs average
        if ($total_orders > 50 && $avg_order_value > 25) {
            $trend_direction = 'up';
            $trend_icon = '📈';
        } elseif ($total_orders < 10 || $avg_order_value < 15) {
            $trend_direction = 'down';
            $trend_icon = '📉';
        }
        
        return array(
            'title' => __('📈 Revenue Trends', 'orders-jet'),
            'value' => wc_price($total_revenue),
            'subtitle' => sprintf(__('%d orders total', 'orders-jet'), $total_orders),
            'insight' => sprintf(
                __('%s Revenue trending %s - Avg: %s per order', 'orders-jet'),
                $trend_icon,
                $trend_direction,
                wc_price($avg_order_value)
            ),
            'type' => 'revenue ' . $trend_direction,
            'drill_down' => array(
                'group_by' => 'day',
                'filter' => 'revenue_trends'
            )
        );
    }
    
    /**
     * Calculate table performance insight
     * 
     * @param array $summary Summary statistics
     * @return array Table performance insight data
     */
    private function calculate_table_performance_insight($summary) {
        // Get table performance data when grouped by table
        if ($this->query_builder->get_group_by() === 'table') {
            $grouped_data = $this->query_builder->get_bi_data();
            
            if (!empty($grouped_data)) {
                // Find top performing table
                $top_table = $grouped_data[0]; // Already sorted by revenue
                $total_tables = count($grouped_data);
                
                // Calculate table utilization
                $tables_with_orders = 0;
                foreach ($grouped_data as $table_data) {
                    if ($table_data['count'] > 0) {
                        $tables_with_orders++;
                    }
                }
                
                $utilization_rate = $total_tables > 0 ? ($tables_with_orders / $total_tables) * 100 : 0;
                
                return array(
                    'title' => __('🍽️ Table Performance', 'orders-jet'),
                    'value' => sprintf(__('%.1f%% Utilization', 'orders-jet'), $utilization_rate),
                    'subtitle' => sprintf(__('%d/%d tables active', 'orders-jet'), $tables_with_orders, $total_tables),
                    'insight' => sprintf(
                        __('Top table: %s with %s revenue', 'orders-jet'),
                        $top_table['group_label'],
                        wc_price($top_table['total_revenue'])
                    ),
                    'type' => 'table',
                    'drill_down' => array(
                        'group_by' => 'table',
                        'filter' => 'table_performance'
                    )
                );
            }
        }
        
        // Default table analysis
        $total_orders = $summary['total_orders'] ?? 0;
        
        return array(
            'title' => __('🍽️ Table Performance', 'orders-jet'),
            'value' => sprintf(__('%d Orders', 'orders-jet'), $total_orders),
            'subtitle' => __('All order types', 'orders-jet'),
            'insight' => __('Switch to Table Performance view to analyze table-specific metrics', 'orders-jet'),
            'type' => 'table',
            'drill_down' => array(
                'group_by' => 'table'
            )
        );
    }
    
    /**
     * Calculate customer insights
     * 
     * @param array $summary Summary statistics
     * @return array Customer insights data
     */
    private function calculate_customer_insights_insight($summary) {
        $total_orders = $summary['total_orders'] ?? 0;
        $avg_order_value = $summary['avg_order_value'] ?? 0;
        
        // Simple customer behavior analysis
        $customer_type = 'Mixed';
        $behavior_insight = 'Standard ordering patterns';
        
        if ($avg_order_value > 30) {
            $customer_type = 'Premium';
            $behavior_insight = 'High-value customers with larger orders';
        } elseif ($avg_order_value < 15) {
            $customer_type = 'Budget';
            $behavior_insight = 'Price-conscious customers with smaller orders';
        }
        
        // Estimate unique customers (simplified)
        $estimated_customers = max(1, intval($total_orders * 0.7)); // Rough estimate
        
        return array(
            'title' => __('👥 Customer Insights', 'orders-jet'),
            'value' => sprintf(__('~%d Customers', 'orders-jet'), $estimated_customers),
            'subtitle' => sprintf(__('%s segment', 'orders-jet'), $customer_type),
            'insight' => $behavior_insight . sprintf(__(' - Avg: %s per customer', 'orders-jet'), wc_price($avg_order_value)),
            'type' => 'customer',
            'drill_down' => array(
                'filter' => 'customer_insights'
            )
        );
    }
    
    /**
     * Get detailed staff performance data
     * 
     * @return array Detailed staff performance metrics
     */
    public function get_detailed_staff_performance() {
        // Temporarily change grouping to waiter if not already
        $original_group_by = $this->query_builder->get_group_by();
        
        if ($original_group_by !== 'waiter') {
            // Create new query builder instance with waiter grouping
            $params = array_merge($_GET, array('group_by' => 'waiter'));
            $waiter_query_builder = new Orders_BI_Query_Builder($params);
            $staff_data = $waiter_query_builder->get_bi_data();
        } else {
            $staff_data = $this->query_builder->get_bi_data();
        }
        
        // Add performance rankings and metrics
        foreach ($staff_data as &$staff_member) {
            $staff_member['performance_metrics'] = array(
                'orders_per_hour' => $this->calculate_orders_per_hour($staff_member),
                'table_coverage' => count($staff_member['metadata']['total_tables'] ?? array()),
                'efficiency_score' => $this->calculate_efficiency_score($staff_member)
            );
        }
        
        return $staff_data;
    }
    
    /**
     * Calculate orders per hour for staff member
     * 
     * @param array $staff_data Staff member data
     * @return float Orders per hour
     */
    private function calculate_orders_per_hour($staff_data) {
        // Simplified calculation - would need actual shift hours for accuracy
        $orders_count = $staff_data['count'];
        $estimated_hours = 8; // Assume 8-hour shift
        
        return $orders_count / $estimated_hours;
    }
    
    /**
     * Calculate efficiency score for staff member
     * 
     * @param array $staff_data Staff member data
     * @return float Efficiency score (0-100)
     */
    private function calculate_efficiency_score($staff_data) {
        $completion_rate = $staff_data['completion_rate'];
        $avg_order_value = $staff_data['avg_order_value'];
        $table_coverage = count($staff_data['metadata']['total_tables'] ?? array());
        
        // Weighted efficiency score
        $score = ($completion_rate * 0.4) + 
                 (min(100, ($avg_order_value / 25) * 100) * 0.3) + 
                 (min(100, ($table_coverage / 5) * 100) * 0.3);
        
        return round($score, 1);
    }
    
    /**
     * Get shift comparison data
     * 
     * @return array Shift comparison metrics
     */
    public function get_shift_comparison() {
        // Create query builder for shift analysis
        $params = array_merge($_GET, array('group_by' => 'shift'));
        $shift_query_builder = new Orders_BI_Query_Builder($params);
        $shift_data = $shift_query_builder->get_bi_data();
        
        // Calculate shift comparisons
        $comparison = array(
            'shifts' => $shift_data,
            'peak_shift' => null,
            'revenue_distribution' => array(),
            'order_distribution' => array()
        );
        
        if (!empty($shift_data)) {
            $comparison['peak_shift'] = $shift_data[0]; // Highest revenue
            
            $total_revenue = array_sum(array_column($shift_data, 'total_revenue'));
            $total_orders = array_sum(array_column($shift_data, 'count'));
            
            foreach ($shift_data as $shift) {
                $comparison['revenue_distribution'][$shift['group_key']] = 
                    $total_revenue > 0 ? ($shift['total_revenue'] / $total_revenue) * 100 : 0;
                $comparison['order_distribution'][$shift['group_key']] = 
                    $total_orders > 0 ? ($shift['count'] / $total_orders) * 100 : 0;
            }
        }
        
        return $comparison;
    }
    
    /**
     * Get discount effectiveness analysis
     * 
     * @return array Discount effectiveness data
     */
    public function get_discount_effectiveness() {
        // Create query builder for discount analysis
        $params = array_merge($_GET, array('group_by' => 'discount_status'));
        $discount_query_builder = new Orders_BI_Query_Builder($params);
        $discount_data = $discount_query_builder->get_bi_data();
        
        $analysis = array(
            'discount_groups' => $discount_data,
            'effectiveness_score' => 0,
            'roi_analysis' => array(),
            'popular_coupons' => array()
        );
        
        if (!empty($discount_data)) {
            foreach ($discount_data as $group) {
                if ($group['group_key'] === 'with_discount') {
                    // Calculate ROI (simplified)
                    $discount_amount = $group['discount_amount'];
                    $revenue_generated = $group['total_revenue'];
                    
                    $analysis['roi_analysis'] = array(
                        'discount_given' => $discount_amount,
                        'revenue_generated' => $revenue_generated,
                        'roi_ratio' => $discount_amount > 0 ? $revenue_generated / $discount_amount : 0
                    );
                    
                    // Extract popular coupons
                    $analysis['popular_coupons'] = $group['metadata']['coupon_codes'] ?? array();
                    
                    // Calculate effectiveness score
                    $analysis['effectiveness_score'] = min(100, ($group['count'] / 10) * 100); // Simplified
                }
            }
        }
        
        return $analysis;
    }
}
