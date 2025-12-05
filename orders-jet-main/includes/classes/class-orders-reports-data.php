<?php
/**
 * Orders Reports Data Layer
 * 
 * Handles KPI calculations and report data generation.
 * Works with Orders_Reports_Query_Builder to provide analytics.
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Orders_Reports_Data
 * 
 * Calculates KPIs, generates reports, and provides analytics data.
 */
class Orders_Reports_Data {
    
    /**
     * @var Orders_Reports_Query_Builder Query builder instance
     */
    private $query_builder;
    
    /**
     * @var array Cached KPI data
     */
    private $kpi_cache;
    
    /**
     * Constructor
     * 
     * @param Orders_Reports_Query_Builder $query_builder Query builder instance
     */
    public function __construct($query_builder) {
        $this->query_builder = $query_builder;
        $this->kpi_cache = null;
    }
    
    /**
     * Calculate all KPIs
     * 
     * @return array KPI data
     */
    public function get_kpis() {
        if ($this->kpi_cache !== null) {
            return $this->kpi_cache;
        }
        
        // Get summary data
        $summary = $this->query_builder->get_summary_data();
        
        // Initialize KPIs
        $kpis = array(
            'total_revenue' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0,
            'refunded_orders' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'on_hold_orders' => 0,
            'average_order_value' => 0,
            'cash_orders' => 0,
            'online_orders' => 0,
            'cash_revenue' => 0,
            'online_revenue' => 0,
            'completed_revenue' => 0,
        );
        
        // Aggregate across all periods
        foreach ($summary as $period_data) {
            $kpis['total_revenue'] += $period_data['total_revenue'];
            $kpis['total_orders'] += $period_data['total_orders'];
            $kpis['completed_orders'] += $period_data['completed_orders'];
            $kpis['cancelled_orders'] += $period_data['cancelled_orders'];
            $kpis['refunded_orders'] += $period_data['refunded_orders'];
            $kpis['pending_orders'] += $period_data['pending_orders'];
            $kpis['processing_orders'] += isset($period_data['processing_orders']) ? $period_data['processing_orders'] : 0;
            $kpis['on_hold_orders'] += isset($period_data['on_hold_orders']) ? $period_data['on_hold_orders'] : 0;
            $kpis['cash_orders'] += $period_data['cash_orders'];
            $kpis['online_orders'] += $period_data['online_orders'];
            $kpis['cash_revenue'] += $period_data['cash_revenue'];
            $kpis['online_revenue'] += $period_data['online_revenue'];
            $kpis['completed_revenue'] += $period_data['completed_revenue'];
        }
        
        // Calculate average order value
        if ($kpis['completed_orders'] > 0) {
            $kpis['average_order_value'] = $kpis['completed_revenue'] / $kpis['completed_orders'];
        }
        
        $this->kpi_cache = $kpis;
        return $kpis;
    }
    
    /**
     * Get KPI for drill-down date
     * 
     * @param string $date Date in Y-m-d format
     * @return array KPI data for specific date
     */
    public function get_drill_down_kpis($date) {
        // Get current query builder parameters
        $current_params = $this->query_builder->get_current_params();
        
        // Override with drill-down date
        $params = $current_params;
        $params['drill_down_date'] = $date;
        $params['group_by'] = 'day';
        
        // Ensure kitchen_type and order_type are set from product_type and order_source
        if (isset($params['product_type']) && !empty($params['product_type'])) {
            $params['kitchen_type'] = $params['product_type'];
        }
        if (isset($params['order_source']) && !empty($params['order_source'])) {
            $params['order_type'] = $params['order_source'];
        }
        
        $drill_query = new Orders_Reports_Query_Builder($params);
        
        // CRITICAL FIX: Get actual orders to calculate accurate KPIs
        $orders_data = $drill_query->get_drill_down_orders();
        
        // Initialize counters
        $kpis = array(
            'total_revenue' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'completed_revenue' => 0,
            'cancelled_orders' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'refunded_orders' => 0,
            'failed_orders' => 0,
            'on_hold_orders' => 0,
            'average_order_value' => 0,
        );
        
        if (empty($orders_data)) {
            return $kpis;
        }
        
        // Calculate KPIs from actual orders
        $kpis['total_orders'] = count($orders_data);
        
        foreach ($orders_data as $order_data) {
            $status = $order_data['status_raw'];
            $total = $order_data['total'];
            
            // Add to total revenue
            $kpis['total_revenue'] += $total;
            
            // Count by status
            if ($status === 'completed') {
                $kpis['completed_orders']++;
                $kpis['completed_revenue'] += $total;
            } elseif ($status === 'cancelled') {
                $kpis['cancelled_orders']++;
            } elseif ($status === 'pending') {
                $kpis['pending_orders']++;
            } elseif ($status === 'processing') {
                $kpis['processing_orders']++;
            } elseif ($status === 'refunded') {
                $kpis['refunded_orders']++;
            } elseif ($status === 'failed') {
                $kpis['failed_orders']++;
            } elseif ($status === 'on-hold') {
                $kpis['on_hold_orders']++;
            }
        }
        
        // Calculate average order value from completed orders
        if ($kpis['completed_orders'] > 0) {
            $kpis['average_order_value'] = $kpis['completed_revenue'] / $kpis['completed_orders'];
        } else {
            // Fallback to all orders if no completed orders
            $kpis['average_order_value'] = $kpis['total_orders'] > 0 ? $kpis['total_revenue'] / $kpis['total_orders'] : 0;
        }
        
        return $kpis;
    }
    
    /**
     * Format KPIs for display
     * 
     * @param array $kpis Raw KPI data
     * @return array Formatted KPI data
     */
    public function format_kpis($kpis) {
        return array(
            'total_revenue' => array(
                'label' => __('Total Revenue', 'orders-jet'),
                'value' => wc_price($kpis['total_revenue']),
                'raw' => $kpis['total_revenue'],
                'icon' => '💰',
                'color' => '#10b981',
            ),
            'total_orders' => array(
                'label' => __('Total Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['total_orders']),
                'raw' => $kpis['total_orders'],
                'icon' => '📦',
                'color' => '#3b82f6',
            ),
            'average_order_value' => array(
                'label' => __('Average Order Value', 'orders-jet'),
                'value' => wc_price($kpis['average_order_value']),
                'raw' => $kpis['average_order_value'],
                'icon' => '📊',
                'color' => '#8b5cf6',
            ),
            'completed_orders' => array(
                'label' => __('Completed Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['completed_orders']),
                'raw' => $kpis['completed_orders'],
                'icon' => '✅',
                'color' => '#10b981',
            ),
            'pending_orders' => array(
                'label' => __('Pending Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['pending_orders']),
                'raw' => $kpis['pending_orders'],
                'icon' => '⏳',
                'color' => '#f59e0b',
            ),
            'processing_orders' => array(
                'label' => __('Processing Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['processing_orders']),
                'raw' => $kpis['processing_orders'],
                'icon' => '👨‍🍳',
                'color' => '#0ea5e9',
            ),
            'cancelled_orders' => array(
                'label' => __('Cancelled Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['cancelled_orders']),
                'raw' => $kpis['cancelled_orders'],
                'icon' => '❌',
                'color' => '#ef4444',
            ),
            'refunded_orders' => array(
                'label' => __('Refunded Orders', 'orders-jet'),
                'value' => number_format_i18n($kpis['refunded_orders']),
                'raw' => $kpis['refunded_orders'],
                'icon' => '↩️',
                'color' => '#6b7280',
            ),
        );
    }
    
    /**
     * Get payment method breakdown
     * 
     * @return array Payment method data
     */
    public function get_payment_breakdown() {
        $kpis = $this->get_kpis();
        
        return array(
            'cash' => array(
                'label' => __('Cash Payments', 'orders-jet'),
                'orders' => $kpis['cash_orders'],
                'revenue' => $kpis['cash_revenue'],
                'percentage' => $kpis['total_orders'] > 0 ? ($kpis['cash_orders'] / $kpis['total_orders']) * 100 : 0,
            ),
            'online' => array(
                'label' => __('Online Payments', 'orders-jet'),
                'orders' => $kpis['online_orders'],
                'revenue' => $kpis['online_revenue'],
                'percentage' => $kpis['total_orders'] > 0 ? ($kpis['online_orders'] / $kpis['total_orders']) * 100 : 0,
            ),
        );
    }
    
    /**
     * Get orders by status breakdown
     * 
     * @return array Status breakdown data
     */
    public function get_status_breakdown() {
        $kpis = $this->get_kpis();
        
        $total = $kpis['total_orders'];
        
        return array(
            'completed' => array(
                'label' => __('Completed', 'orders-jet'),
                'count' => $kpis['completed_orders'],
                'percentage' => $total > 0 ? ($kpis['completed_orders'] / $total) * 100 : 0,
                'color' => '#10b981',
            ),
            'pending' => array(
                'label' => __('Pending', 'orders-jet'),
                'count' => $kpis['pending_orders'],
                'percentage' => $total > 0 ? ($kpis['pending_orders'] / $total) * 100 : 0,
                'color' => '#f59e0b',
            ),
            'cancelled' => array(
                'label' => __('Cancelled', 'orders-jet'),
                'count' => $kpis['cancelled_orders'],
                'percentage' => $total > 0 ? ($kpis['cancelled_orders'] / $total) * 100 : 0,
                'color' => '#ef4444',
            ),
            'refunded' => array(
                'label' => __('Refunded', 'orders-jet'),
                'count' => $kpis['refunded_orders'],
                'percentage' => $total > 0 ? ($kpis['refunded_orders'] / $total) * 100 : 0,
                'color' => '#6b7280',
            ),
        );
    }
    
    /**
     * Get summary table data
     * 
     * @return array Summary table rows
     */
    public function get_summary_table() {
        $summary = $this->query_builder->get_summary_data();
        $rows = array();
        
        foreach ($summary as $period => $data) {
            $rows[] = array(
                'period' => $period,
                'period_label' => $this->query_builder->get_period_label($period),
                'total_orders' => $data['total_orders'],
                'completed_orders' => $data['completed_orders'],
                'cancelled_orders' => $data['cancelled_orders'],
                'total_revenue' => $data['total_revenue'],
                'revenue_formatted' => wc_price($data['total_revenue']),
            );
        }
        
        return $rows;
    }
    
    /**
     * Get category table data
     * 
     * @return array Category table rows
     */
    public function get_category_table() {
        $category_data = $this->query_builder->get_category_data();
        $rows = array();
        
        foreach ($category_data as $key => $data) {
            $rows[] = array(
                'category_name' => $data['name'],
                'order_count' => $data['order_count'],
                'revenue' => $data['revenue'],
                'revenue_formatted' => wc_price($data['revenue']),
            );
        }
        
        return $rows;
    }
    
    /**
     * Get drill-down details
     * 
     * @param string $date Date in Y-m-d format
     * @return array Drill-down data
     */
    public function get_drill_down_data($date) {
        // Get current query builder parameters
        $current_params = $this->query_builder->get_current_params();
        
        // Override with drill-down date
        $params = $current_params;
        $params['drill_down_date'] = $date;
        $params['group_by'] = 'day';
        
        // Ensure kitchen_type and order_type are set from product_type and order_source
        if (isset($params['product_type']) && !empty($params['product_type'])) {
            $params['kitchen_type'] = $params['product_type'];
        }
        if (isset($params['order_source']) && !empty($params['order_source'])) {
            $params['order_type'] = $params['order_source'];
        }
        
        $drill_query = new Orders_Reports_Query_Builder($params);
        
        return array(
            'kpis' => $this->get_drill_down_kpis($date),
            'orders' => $drill_query->get_drill_down_orders(),
        );
    }
    
    /**
     * Get chart data for visualization
     * 
     * @return array Chart data (labels and datasets)
     */
    public function get_chart_data() {
        $summary = $this->query_builder->get_summary_data();
        
        $labels = array();
        $revenue_data = array();
        $orders_data = array();
        
        foreach ($summary as $period => $data) {
            $labels[] = $this->query_builder->get_period_label($period);
            $revenue_data[] = $data['total_revenue'];
            $orders_data[] = $data['total_orders'];
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                'revenue' => array(
                    'label' => __('Revenue', 'orders-jet'),
                    'data' => $revenue_data,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                ),
                'orders' => array(
                    'label' => __('Orders', 'orders-jet'),
                    'data' => $orders_data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                ),
            ),
        );
    }
}
