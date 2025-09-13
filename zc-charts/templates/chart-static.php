<?php
/**
 * ZC Charts Static Chart Template
 * Template for rendering static charts without interactive controls
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