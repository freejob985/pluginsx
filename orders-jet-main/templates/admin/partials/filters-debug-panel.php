<?php
/**
 * Filters Debug Panel - Development Testing Interface
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only show debug panel for administrators
if (!current_user_can('manage_options')) {
    return;
}

// Get current URL parameters
$current_params = $_GET;
$debug_enabled = isset($_GET['debug']) && $_GET['debug'] === '1';
?>

<!-- Filters Debug Panel -->
<div id="oj-debug-panel" class="oj-debug-panel" <?php echo $debug_enabled ? '' : 'style="display: none;"'; ?>>
    <div class="oj-debug-header">
        <h3>🐛 Filters Debug Panel</h3>
        <div class="oj-debug-controls">
            <?php if ($debug_enabled): ?>
                <a href="<?php echo esc_url(remove_query_arg('debug')); ?>" class="oj-debug-btn oj-debug-hide">Hide Debug</a>
            <?php else: ?>
                <a href="<?php echo esc_url(add_query_arg('debug', '1')); ?>" class="oj-debug-btn oj-debug-show">Show Debug</a>
            <?php endif; ?>
            <button type="button" id="oj-debug-refresh" class="oj-debug-btn oj-debug-refresh">Refresh</button>
        </div>
    </div>
    
    <div class="oj-debug-content">
        <div class="oj-debug-row">
            <!-- Current Parameters -->
            <div class="oj-debug-section">
                <h4>📋 Current URL Parameters</h4>
                <div class="oj-debug-params" id="oj-debug-params">
                    <?php if (!empty($current_params)): ?>
                        <table class="oj-debug-table">
                            <?php foreach ($current_params as $key => $value): ?>
                                <?php if ($key !== 'debug'): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($key); ?>:</strong></td>
                                        <td><code><?php echo esc_html($value); ?></code></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p><em>No parameters set</em></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel State -->
            <div class="oj-debug-section">
                <h4>🔧 Panel State</h4>
                <div class="oj-debug-state">
                    <table class="oj-debug-table">
                        <tr>
                            <td><strong>Panel Open:</strong></td>
                            <td><span id="debug-panel-state">closed</span></td>
                        </tr>
                        <tr>
                            <td><strong>Active Filters:</strong></td>
                            <td><span id="debug-active-filters">0</span></td>
                        </tr>
                        <tr>
                            <td><strong>Filter Count Badge:</strong></td>
                            <td><span id="debug-filter-badge">hidden</span></td>
                        </tr>
                        <tr>
                            <td><strong>Page Load Time:</strong></td>
                            <td><code><?php echo date('H:i:s'); ?></code></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="oj-debug-row">
            <!-- Query Information -->
            <div class="oj-debug-section">
                <h4>🔍 Query Information</h4>
                <div class="oj-debug-query">
                    <table class="oj-debug-table">
                        <tr>
                            <td><strong>Total Orders Found:</strong></td>
                            <td><code><?php echo isset($total_orders) ? $total_orders : 'N/A'; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Orders Displayed:</strong></td>
                            <td><code><?php echo isset($orders) ? count($orders) : 'N/A'; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Current Page:</strong></td>
                            <td><code><?php echo isset($_GET['paged']) ? intval($_GET['paged']) : 1; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Orders Per Page:</strong></td>
                            <td><code><?php echo isset($per_page) ? $per_page : 'N/A'; ?></code></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Filter Counts -->
            <div class="oj-debug-section">
                <h4>📊 Filter Counts</h4>
                <div class="oj-debug-counts">
                    <?php if (isset($filter_counts) && is_array($filter_counts)): ?>
                        <table class="oj-debug-table">
                            <?php foreach ($filter_counts as $filter => $count): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(ucfirst($filter)); ?>:</strong></td>
                                    <td><code><?php echo esc_html($count); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p><em>Filter counts not available</em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Test Buttons -->
        <div class="oj-debug-section oj-debug-full-width">
            <h4>⚡ Quick Test Actions</h4>
            <div class="oj-debug-actions">
                <button type="button" class="oj-debug-btn" onclick="ojDebugTestPanel()">Test Panel Open/Close</button>
                <button type="button" class="oj-debug-btn" onclick="ojDebugTestFilters()">Test Filter Count</button>
                <button type="button" class="oj-debug-btn" onclick="ojDebugLogState()">Log Current State</button>
                <button type="button" class="oj-debug-btn" onclick="ojDebugClearFilters()">Clear All Filters</button>
            </div>
        </div>
    </div>
</div>
