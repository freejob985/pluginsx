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
}
