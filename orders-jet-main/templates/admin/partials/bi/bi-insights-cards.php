<?php
/**
 * BI Insights Cards - 4-Card Status Overview with Mini Charts
 */
if (!defined('ABSPATH')) exit;
?>

<div class="oj-bi-insights-section">
    <div class="oj-bi-insights-grid">
        <?php foreach ($insights_data as $insight_key => $insight): ?>
        <div class="oj-bi-insight-card <?php echo esc_attr($insight_key); ?> <?php echo esc_attr($insight['type']); ?>" 
             data-drill-down='<?php echo json_encode($insight['drill_down'] ?? array()); ?>'>
            
            <div class="oj-insight-header">
                <h3 class="oj-insight-title"><?php echo esc_html($insight['title']); ?></h3>
                
                <!-- NEW: Mini Chart -->
                <div class="oj-mini-chart">
                    <div class="oj-progress-bar">
                        <div class="oj-progress-fill" 
                             style="--target-width: <?php echo esc_attr($insight['percentage']); ?>%; width: 0%;"
                             data-percentage="<?php echo esc_attr($insight['percentage']); ?>"
                             data-color="<?php echo esc_attr($insight['chart_color']); ?>">
                        </div>
                    </div>
                    <span class="oj-chart-label"><?php echo esc_html($insight['percentage']); ?>%</span>
                </div>
            </div>
            
            <div class="oj-insight-value">
                <span class="oj-value-main"><?php echo wp_kses_post($insight['value']); ?></span>
                <?php if (isset($insight['subtitle'])): ?>
                <div class="oj-insight-subtitle"><?php echo wp_kses_post($insight['subtitle']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="oj-insight-footer">
                <span class="oj-insight-text"><?php echo wp_kses_post($insight['insight']); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
