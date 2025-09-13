<?php
/**
 * ZC Charts Dynamic Chart Template
 * Template for rendering dynamic charts with interactive controls
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Extract variables passed to the template
$chart_id = isset($chart_id) ? $chart_id : 'zc-chart-' . uniqid();
$config = isset($config) ? $config : array();
$data = isset($data) ? $data : array();
$fallback_notice = isset($fallback_notice) ? $fallback_notice : false;
?>

<div class="zc-chart-wrapper" id="<?php echo esc_attr($chart_id); ?>-wrapper">
    <?php if ($fallback_notice) : ?>
        <div class="zc-chart-notice">
            <?php echo esc_html__('Displaying cached data', 'zc-charts'); ?>
        </div>
    <?php endif; ?>
    
    <div class="zc-chart-controls">
        <div class="timeframe-controls">
            <button type="button" class="timeframe-btn active" data-timeframe="1y">
                <?php echo esc_html__('1Y', 'zc-charts'); ?>
            </button>
            <button type="button" class="timeframe-btn" data-timeframe="3y">
                <?php echo esc_html__('3Y', 'zc-charts'); ?>
            </button>
            <button type="button" class="timeframe-btn" data-timeframe="5y">
                <?php echo esc_html__('5Y', 'zc-charts'); ?>
            </button>
            <button type="button" class="timeframe-btn" data-timeframe="10y">
                <?php echo esc_html__('10Y', 'zc-charts'); ?>
            </button>
            <button type="button" class="timeframe-btn" data-timeframe="all">
                <?php echo esc_html__('All', 'zc-charts'); ?>
            </button>
        </div>
        
        <div class="chart-controls">
            <select class="chart-type-selector" aria-label="<?php echo esc_attr__('Chart Type', 'zc-charts'); ?>">
                <option value="line"><?php echo esc_html__('Line', 'zc-charts'); ?></option>
                <option value="bar"><?php echo esc_html__('Bar', 'zc-charts'); ?></option>
                <option value="area"><?php echo esc_html__('Area', 'zc-charts'); ?></option>
            </select>
            
            <button type="button" class="export-btn" aria-label="<?php echo esc_attr__('Export Chart', 'zc-charts'); ?>">
                <?php echo esc_html__('Export', 'zc-charts'); ?>
            </button>
        </div>
    </div>
    
    <div class="zc-chart-container" 
         id="<?php echo esc_attr($chart_id); ?>" 
         style="height: <?php echo isset($config['height']) ? esc_attr($config['height']) : '400px'; ?>;"
         data-config='<?php echo json_encode($config); ?>'>
    </div>
    
    <div class="zc-chart-loading" style="display: none;">
        <div class="zc-chart-loading-spinner"></div>
        <p><?php echo esc_html__('Loading chart data...', 'zc-charts'); ?></p>
    </div>
    
    <div class="zc-chart-error" style="display: none;">
        <div class="error-icon">⚠️</div>
        <div class="error-message"><?php echo esc_html__('Error loading chart', 'zc-charts'); ?></div>
        <div class="error-details"><?php echo esc_html__('Please try again later', 'zc-charts'); ?></div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Initialize chart when DOM is ready
    if (typeof window.zcChartLoader !== 'undefined') {
        // Chart will be initialized by the loader
    } else {
        // Fallback initialization
        const container = document.getElementById('<?php echo esc_js($chart_id); ?>');
        if (container) {
            container.innerHTML = '<div class="zc-chart-error"><div class="error-icon">⚠️</div><div class="error-message"><?php echo esc_js(__('Chart loader not found', 'zc-charts')); ?></div></div>';
        }
    }
});
</script>