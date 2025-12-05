<?php
/**
 * Orders Master V2 - Filter Tabs Partial
 * 
 * Display filter tabs with counts
 * 
 * @package Orders_Jet
 * @var string $current_filter Current active filter
 * @var array $filter_counts Filter counts array
 * @var array $current_params Current URL parameters
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Filter Tabs -->
<div class="oj-filter-tabs" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo esc_url(oj_build_filter_url('all', $current_params)); ?>" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: <?php echo $current_filter === 'all' ? '#2196f3' : '#f5f5f5'; ?>; color: <?php echo $current_filter === 'all' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: 600; transition: all 0.2s;">
            <span>📋 All Orders</span>
            <span style="background: <?php echo $current_filter === 'all' ? 'rgba(255,255,255,0.3)' : '#e0e0e0'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                <?php echo $filter_counts['all']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url(oj_build_filter_url('active', $current_params)); ?>" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: <?php echo $current_filter === 'active' ? '#ff9800' : '#f5f5f5'; ?>; color: <?php echo $current_filter === 'active' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: 600; transition: all 0.2s;">
            <span>🔥 Active</span>
            <span style="background: <?php echo $current_filter === 'active' ? 'rgba(255,255,255,0.3)' : '#e0e0e0'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                <?php echo $filter_counts['active']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url(oj_build_filter_url('kitchen', $current_params)); ?>" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: <?php echo $current_filter === 'kitchen' ? '#ff5722' : '#f5f5f5'; ?>; color: <?php echo $current_filter === 'kitchen' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: 600; transition: all 0.2s;">
            <span>👨‍🍳 Kitchen</span>
            <span style="background: <?php echo $current_filter === 'kitchen' ? 'rgba(255,255,255,0.3)' : '#e0e0e0'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                <?php echo $filter_counts['kitchen']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url(oj_build_filter_url('ready', $current_params)); ?>" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: <?php echo $current_filter === 'ready' ? '#4caf50' : '#f5f5f5'; ?>; color: <?php echo $current_filter === 'ready' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: 600; transition: all 0.2s;">
            <span>✅ Ready</span>
            <span style="background: <?php echo $current_filter === 'ready' ? 'rgba(255,255,255,0.3)' : '#e0e0e0'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                <?php echo $filter_counts['ready']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url(oj_build_filter_url('completed', $current_params)); ?>" 
           style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: <?php echo $current_filter === 'completed' ? '#607d8b' : '#f5f5f5'; ?>; color: <?php echo $current_filter === 'completed' ? '#fff' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: 600; transition: all 0.2s;">
            <span>✔️ Completed</span>
            <span style="background: <?php echo $current_filter === 'completed' ? 'rgba(255,255,255,0.3)' : '#e0e0e0'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                <?php echo $filter_counts['completed']; ?>
            </span>
        </a>
    </div>
</div>

