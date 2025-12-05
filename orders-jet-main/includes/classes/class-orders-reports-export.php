<?php
/**
 * Orders Reports Export Handler
 * 
 * Handles exporting reports data to Excel, CSV, and PDF formats.
 * Uses PhpSpreadsheet for Excel/CSV and TCPDF for PDF exports.
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Orders_Reports_Export
 * 
 * Handles all export functionality for reports.
 */
class Orders_Reports_Export {
    
    /**
     * @var Orders_Reports_Data Data layer instance
     */
    private $reports_data;
    
    /**
     * @var Orders_Reports_Query_Builder Query builder instance
     */
    private $query_builder;
    
    /**
     * Constructor
     * 
     * @param Orders_Reports_Data $reports_data Data layer instance
     * @param Orders_Reports_Query_Builder $query_builder Query builder instance
     */
    public function __construct($reports_data, $query_builder) {
        $this->reports_data = $reports_data;
        $this->query_builder = $query_builder;
    }
    
    /**
     * Export data to specified format
     * 
     * @param string $type Export type (excel, csv, pdf)
     * @param string $report_type Report type (summary, category)
     * @return array Result with success status and file info
     */
    public function export($type, $report_type) {
        switch ($type) {
            case 'excel':
                return $this->export_to_excel($report_type);
            case 'csv':
                return $this->export_to_csv($report_type);
            case 'pdf':
                return $this->export_to_pdf($report_type);
            default:
                return array(
                    'success' => false,
                    'message' => __('Invalid export type', 'orders-jet'),
                );
        }
    }
    
    /**
     * Export to CSV format (WordPress native - no external libraries needed)
     * 
     * @param string $report_type Report type
     * @return array Result
     */
    private function export_to_csv($report_type) {
        // Get data based on report type
        $data = $this->get_export_data($report_type);
        
        if (empty($data)) {
            return array(
                'success' => false,
                'message' => __('No data to export', 'orders-jet'),
            );
        }
        
        // Generate filename
        $filename = $this->get_filename($report_type, 'csv');
        $filepath = $this->get_temp_filepath($filename);
        
        // Open file for writing
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Could not create CSV file', 'orders-jet'),
            );
        }
        
        // Write headers
        fputcsv($handle, $data['headers']);
        
        // Write data rows
        foreach ($data['rows'] as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        // Return download URL
        return array(
            'success' => true,
            'filename' => $filename,
            'url' => $this->get_download_url($filename),
            'message' => __('CSV export completed successfully', 'orders-jet'),
        );
    }
    
    /**
     * Export to Excel format (using PhpSpreadsheet if available, otherwise CSV)
     * 
     * @param string $report_type Report type
     * @return array Result
     */
    private function export_to_excel($report_type) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            // Fallback to CSV if PhpSpreadsheet is not available
            return $this->export_to_csv($report_type);
        }
        
        // Get data
        $data = $this->get_export_data($report_type);
        
        if (empty($data)) {
            return array(
                'success' => false,
                'message' => __('No data to export', 'orders-jet'),
            );
        }
        
        try {
            // Create new spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set title
            $sheet->setTitle($data['title']);
            
            // Write headers
            $col = 'A';
            foreach ($data['headers'] as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }
            
            // Write data rows
            $row = 2;
            foreach ($data['rows'] as $data_row) {
                $col = 'A';
                foreach ($data_row as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', $col) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Generate filename and save
            $filename = $this->get_filename($report_type, 'xlsx');
            $filepath = $this->get_temp_filepath($filename);
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);
            
            return array(
                'success' => true,
                'filename' => $filename,
                'url' => $this->get_download_url($filename),
                'message' => __('Excel export completed successfully', 'orders-jet'),
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('Excel export failed: %s', 'orders-jet'), $e->getMessage()),
            );
        }
    }
    
    /**
     * Export to PDF format (using TCPDF if available, otherwise HTML)
     * 
     * @param string $report_type Report type
     * @return array Result
     */
    private function export_to_pdf($report_type) {
        // Get data
        $data = $this->get_export_data($report_type);
        
        if (empty($data)) {
            return array(
                'success' => false,
                'message' => __('No data to export', 'orders-jet'),
            );
        }
        
        // Generate HTML content
        $html = $this->generate_pdf_html($data);
        
        // Check if TCPDF is available
        if (class_exists('TCPDF')) {
            return $this->export_to_tcpdf($report_type, $html, $data);
        }
        
        // Fallback: Generate HTML file
        return $this->export_to_html($report_type, $html);
    }
    
    /**
     * Export to PDF using TCPDF
     * 
     * @param string $report_type Report type
     * @param string $html HTML content
     * @param array $data Export data
     * @return array Result
     */
    private function export_to_tcpdf($report_type, $html, $data) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Orders Jet');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle($data['title']);
            
            // Remove header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Add page
            $pdf->AddPage();
            
            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Generate filename and save
            $filename = $this->get_filename($report_type, 'pdf');
            $filepath = $this->get_temp_filepath($filename);
            
            $pdf->Output($filepath, 'F');
            
            return array(
                'success' => true,
                'filename' => $filename,
                'url' => $this->get_download_url($filename),
                'message' => __('PDF export completed successfully', 'orders-jet'),
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('PDF export failed: %s', 'orders-jet'), $e->getMessage()),
            );
        }
    }
    
    /**
     * Export to HTML (fallback for PDF)
     * 
     * @param string $report_type Report type
     * @param string $html HTML content
     * @return array Result
     */
    private function export_to_html($report_type, $html) {
        $filename = $this->get_filename($report_type, 'html');
        $filepath = $this->get_temp_filepath($filename);
        
        file_put_contents($filepath, $html);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'url' => $this->get_download_url($filename),
            'message' => __('HTML export completed successfully (PDF library not available)', 'orders-jet'),
        );
    }
    
    /**
     * Get export data based on report type
     * 
     * @param string $report_type Report type
     * @return array Export data with headers and rows
     */
    private function get_export_data($report_type) {
        switch ($report_type) {
            case 'summary':
                return $this->get_summary_export_data();
            case 'category':
                return $this->get_category_export_data();
            case 'drill_down':
                return $this->get_drill_down_export_data();
            default:
                return array();
        }
    }
    
    /**
     * Get summary report export data
     * 
     * @return array Export data
     */
    private function get_summary_export_data() {
        $table = $this->reports_data->get_summary_table();
        
        $headers = array(
            __('Period', 'orders-jet'),
            __('Total Orders', 'orders-jet'),
            __('Completed', 'orders-jet'),
            __('Cancelled', 'orders-jet'),
            __('Revenue', 'orders-jet'),
        );
        
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                $row['period_label'],
                $row['total_orders'],
                $row['completed_orders'],
                $row['cancelled_orders'],
                html_entity_decode(strip_tags($row['revenue_formatted'])),
            );
        }
        
        return array(
            'title' => __('Orders Summary Report', 'orders-jet'),
            'headers' => $headers,
            'rows' => $rows,
        );
    }
    
    /**
     * Get category report export data
     * 
     * @return array Export data
     */
    private function get_category_export_data() {
        $table = $this->reports_data->get_category_table();
        
        $headers = array(
            __('Category', 'orders-jet'),
            __('Orders Count', 'orders-jet'),
            __('Revenue', 'orders-jet'),
        );
        
        $rows = array();
        foreach ($table as $row) {
            $rows[] = array(
                $row['category_name'],
                $row['order_count'],
                html_entity_decode(strip_tags($row['revenue_formatted'])),
            );
        }
        
        return array(
            'title' => __('Orders by Category Report', 'orders-jet'),
            'headers' => $headers,
            'rows' => $rows,
        );
    }
    
    /**
     * Get drill-down report export data (detailed orders for specific period)
     * 
     * @return array Export data
     */
    private function get_drill_down_export_data() {
        // Get drill-down date from POST
        $drill_down_date = isset($_POST['drill_down_date']) ? sanitize_text_field($_POST['drill_down_date']) : '';
        $drill_down_label = isset($_POST['drill_down_label']) ? sanitize_text_field($_POST['drill_down_label']) : '';
        
        if (empty($drill_down_date)) {
            return array(
                'title' => __('Detailed Orders Report', 'orders-jet'),
                'headers' => array(),
                'rows' => array(),
            );
        }
        
        // Get drill-down data
        $drill_data = $this->reports_data->get_drill_down_data($drill_down_date);
        $orders = $drill_data['orders'];
        
        $headers = array(
            __('Order #', 'orders-jet'),
            __('Customer', 'orders-jet'),
            __('Status', 'orders-jet'),
            __('Total', 'orders-jet'),
            __('Payment Method', 'orders-jet'),
            __('Date/Time', 'orders-jet'),
        );
        
        $rows = array();
        foreach ($orders as $order) {
            $rows[] = array(
                '#' . $order['order_number'],
                $order['customer_name'],
                $order['status'],
                html_entity_decode(strip_tags($order['total_formatted'])),
                $order['payment_method'],
                $order['date_created'],
            );
        }
        
        // Build descriptive title
        $title = sprintf(
            __('Detailed Orders Report - %s', 'orders-jet'),
            $drill_down_label ?: $drill_down_date
        );
        
        return array(
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
        );
    }
    
    /**
     * Generate HTML content for PDF export
     * 
     * @param array $data Export data
     * @return string HTML content
     */
    private function generate_pdf_html($data) {
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #2271b1; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #2271b1; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
        </head>
        <body>
            <h1>' . esc_html($data['title']) . '</h1>
            <table>
                <thead>
                    <tr>';
        
        foreach ($data['headers'] as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        foreach ($data['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        return $html;
    }
    
    /**
     * Get filename for export
     * 
     * @param string $report_type Report type
     * @param string $extension File extension
     * @return string Filename
     */
    private function get_filename($report_type, $extension) {
        $date = current_time('Y-m-d_H-i-s');
        $group_by = $this->query_builder->get_grouping();
        
        return sprintf(
            'orders-report_%s_%s_%s.%s',
            $report_type,
            $group_by,
            $date,
            $extension
        );
    }
    
    /**
     * Get temporary file path
     * 
     * @param string $filename Filename
     * @return string Full file path
     */
    private function get_temp_filepath($filename) {
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/orders-jet-exports';
        
        // Create directory if it doesn't exist
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        return $exports_dir . '/' . $filename;
    }
    
    /**
     * Get download URL for exported file
     * 
     * @param string $filename Filename
     * @return string Download URL
     */
    private function get_download_url($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/orders-jet-exports/' . $filename;
    }
    
    /**
     * Export single order as PDF invoice
     * 
     * @param WC_Order $order Order object
     * @return array Result with success status and file info
     */
    public static function export_single_order_pdf($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return array(
                'success' => false,
                'message' => __('Invalid order', 'orders-jet'),
            );
        }
        
        // Generate HTML content for invoice
        $html = self::generate_order_invoice_html($order);
        
        // Check if TCPDF is available
        if (class_exists('TCPDF')) {
            return self::export_order_to_tcpdf($order, $html);
        }
        
        // Fallback: Generate HTML file
        return self::export_order_to_html($order, $html);
    }
    
    /**
     * Generate HTML content for order invoice
     * 
     * @param WC_Order $order Order object
     * @return string HTML content
     */
    private static function generate_order_invoice_html($order) {
        $order_number = $order->get_order_number();
        $order_date = $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email() ?: __('Guest', 'orders-jet');
        }
        $customer_phone = $order->get_billing_phone();
        $customer_email = $order->get_billing_email();
        $billing_address = $order->get_formatted_billing_address();
        $shipping_address = $order->get_formatted_shipping_address();
        $payment_method = $order->get_payment_method_title();
        $order_status = wc_get_order_status_name($order->get_status());
        
        // Get order items
        $items_html = '';
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $item_name = $item->get_name();
            $quantity = $item->get_quantity();
            $line_total = $item->get_total();
            $line_subtotal = $item->get_subtotal();
            
            $items_html .= '<tr>';
            $items_html .= '<td style="padding: 10px; border-bottom: 1px solid #ddd;">' . esc_html($item_name) . '</td>';
            $items_html .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . $quantity . '</td>';
            $items_html .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">' . wc_price($line_subtotal / $quantity) . '</td>';
            $items_html .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right;">' . wc_price($line_total) . '</td>';
            $items_html .= '</tr>';
        }
        
        // Calculate totals
        $subtotal = $order->get_subtotal();
        $discount = $order->get_total_discount();
        $shipping = $order->get_shipping_total();
        $tax = $order->get_total_tax();
        $total = $order->get_total();
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . sprintf(__('Invoice #%s', 'orders-jet'), $order_number) . '</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    color: #333;
                }
                .invoice-header {
                    border-bottom: 3px solid #2271b1;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .invoice-title {
                    font-size: 28px;
                    font-weight: bold;
                    color: #2271b1;
                    margin-bottom: 10px;
                }
                .invoice-info {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 20px;
                }
                .invoice-info-left, .invoice-info-right {
                    width: 48%;
                }
                .invoice-section {
                    margin-bottom: 25px;
                }
                .invoice-section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2271b1;
                    margin-bottom: 10px;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #e5e7eb;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th {
                    background-color: #2271b1;
                    color: white;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                }
                th.text-right {
                    text-align: right;
                }
                th.text-center {
                    text-align: center;
                }
                td {
                    padding: 10px;
                }
                .totals-table {
                    margin-top: 20px;
                    width: 100%;
                }
                .totals-table td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .totals-table td:first-child {
                    text-align: right;
                    font-weight: 600;
                }
                .totals-table td:last-child {
                    text-align: right;
                    font-weight: 600;
                }
                .total-row {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2271b1;
                    border-top: 2px solid #2271b1;
                }
                .invoice-footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 2px solid #e5e7eb;
                    text-align: center;
                    color: #6b7280;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <div class="invoice-title">' . __('Invoice', 'orders-jet') . '</div>
                <div class="invoice-info">
                    <div class="invoice-info-left">
                        <div><strong>' . __('Order Number:', 'orders-jet') . '</strong> #' . esc_html($order_number) . '</div>
                        <div><strong>' . __('Order Date:', 'orders-jet') . '</strong> ' . esc_html($order_date) . '</div>
                        <div><strong>' . __('Status:', 'orders-jet') . '</strong> ' . esc_html($order_status) . '</div>
                    </div>
                    <div class="invoice-info-right">
                        <div><strong>' . __('Payment Method:', 'orders-jet') . '</strong> ' . esc_html($payment_method) . '</div>
                    </div>
                </div>
            </div>
            
            <div class="invoice-section">
                <div class="invoice-section-title">' . __('Customer Information', 'orders-jet') . '</div>
                <div><strong>' . __('Name:', 'orders-jet') . '</strong> ' . esc_html($customer_name) . '</div>';
        
        if ($customer_phone) {
            $html .= '<div><strong>' . __('Phone:', 'orders-jet') . '</strong> ' . esc_html($customer_phone) . '</div>';
        }
        if ($customer_email) {
            $html .= '<div><strong>' . __('Email:', 'orders-jet') . '</strong> ' . esc_html($customer_email) . '</div>';
        }
        if ($billing_address) {
            $html .= '<div style="margin-top: 10px;"><strong>' . __('Billing Address:', 'orders-jet') . '</strong><br>' . wp_kses_post($billing_address) . '</div>';
        }
        if ($shipping_address && $shipping_address !== $billing_address) {
            $html .= '<div style="margin-top: 10px;"><strong>' . __('Shipping Address:', 'orders-jet') . '</strong><br>' . wp_kses_post($shipping_address) . '</div>';
        }
        
        $html .= '
            </div>
            
            <div class="invoice-section">
                <div class="invoice-section-title">' . __('Order Items', 'orders-jet') . '</div>
                <table>
                    <thead>
                        <tr>
                            <th>' . __('Item', 'orders-jet') . '</th>
                            <th class="text-center">' . __('Quantity', 'orders-jet') . '</th>
                            <th class="text-right">' . __('Unit Price', 'orders-jet') . '</th>
                            <th class="text-right">' . __('Total', 'orders-jet') . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $items_html . '
                    </tbody>
                </table>
            </div>
            
            <div class="invoice-section">
                <table class="totals-table">
                    <tr>
                        <td>' . __('Subtotal:', 'orders-jet') . '</td>
                        <td>' . wc_price($subtotal) . '</td>
                    </tr>';
        
        if ($discount > 0) {
            $html .= '<tr>
                        <td>' . __('Discount:', 'orders-jet') . '</td>
                        <td>- ' . wc_price($discount) . '</td>
                    </tr>';
        }
        if ($shipping > 0) {
            $html .= '<tr>
                        <td>' . __('Shipping:', 'orders-jet') . '</td>
                        <td>' . wc_price($shipping) . '</td>
                    </tr>';
        }
        if ($tax > 0) {
            $html .= '<tr>
                        <td>' . __('Tax:', 'orders-jet') . '</td>
                        <td>' . wc_price($tax) . '</td>
                    </tr>';
        }
        
        $html .= '<tr class="total-row">
                        <td>' . __('Total:', 'orders-jet') . '</td>
                        <td>' . wc_price($total) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="invoice-footer">
                <div>' . sprintf(__('Generated on %s', 'orders-jet'), current_time(get_option('date_format') . ' ' . get_option('time_format'))) . '</div>
                <div>' . get_bloginfo('name') . '</div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Export order to PDF using TCPDF
     * 
     * @param WC_Order $order Order object
     * @param string $html HTML content
     * @return array Result
     */
    private static function export_order_to_tcpdf($order, $html) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Orders Jet');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle(sprintf(__('Invoice #%s', 'orders-jet'), $order->get_order_number()));
            
            // Remove header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Add page
            $pdf->AddPage();
            
            // Write HTML
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Generate filename and save
            $filename = sprintf('invoice-order-%s-%s.pdf', $order->get_order_number(), current_time('Y-m-d_H-i-s'));
            $filepath = self::get_temp_filepath_static($filename);
            
            $pdf->Output($filepath, 'F');
            
            return array(
                'success' => true,
                'filename' => $filename,
                'url' => self::get_download_url_static($filename),
                'message' => __('PDF invoice generated successfully', 'orders-jet'),
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => sprintf(__('PDF export failed: %s', 'orders-jet'), $e->getMessage()),
            );
        }
    }
    
    /**
     * Export order to HTML (fallback for PDF)
     * 
     * @param WC_Order $order Order object
     * @param string $html HTML content
     * @return array Result
     */
    private static function export_order_to_html($order, $html) {
        $filename = sprintf('invoice-order-%s-%s.html', $order->get_order_number(), current_time('Y-m-d_H-i-s'));
        $filepath = self::get_temp_filepath_static($filename);
        
        file_put_contents($filepath, $html);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'url' => self::get_download_url_static($filename),
            'message' => __('HTML invoice generated successfully (PDF library not available)', 'orders-jet'),
        );
    }
    
    /**
     * Get temporary file path (static version)
     * 
     * @param string $filename Filename
     * @return string Full file path
     */
    private static function get_temp_filepath_static($filename) {
        $upload_dir = wp_upload_dir();
        $exports_dir = $upload_dir['basedir'] . '/orders-jet-exports';
        
        // Create directory if it doesn't exist
        if (!file_exists($exports_dir)) {
            wp_mkdir_p($exports_dir);
        }
        
        return $exports_dir . '/' . $filename;
    }
    
    /**
     * Get download URL for exported file (static version)
     * 
     * @param string $filename Filename
     * @return string Download URL
     */
    private static function get_download_url_static($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/orders-jet-exports/' . $filename;
    }
}
