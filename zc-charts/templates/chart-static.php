<?php
/**
 * ZC Charts Static Chart Template
 *
 * This template is used for rendering static charts.
 * It is called by the ZC_Charts_Charts class when rendering a chart.
 *
 * @package ZC_Charts
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// This template expects the following variables to be defined:
// $unique_id (string) - Unique identifier for the chart container
// $config (array) - Chart configuration array
// $chart_data (array) - The data to be rendered in the chart
// $is_fallback (bool) - Whether this data is from the fallback system

// Extract configuration values
$slug = isset($config['slug']) ? sanitize_key($config['slug']) : '';
$height = isset($config['height']) ? sanitize_text_field($config['height']) : '300px';
$css_class = isset($config['css_class']) ? sanitize_html_class($config['css_class']) : '';

// Ensure $chart_data is an array
$chart_data = is_array($chart_data) ? $chart_data : array();

// Static charts do not have controls by definition
$config['controls'] = false;

?>
<div id="<?php echo esc_attr($unique_id); ?>-wrapper" class="zc-chart-wrapper <?php echo esc_attr($css_class); ?><?php echo $is_fallback ? ' zc-chart-fallback' : ''; ?>">
    <?php if ($is_fallback): ?>
        <div class="zc-chart-notice"><?php esc_html_e('Displaying cached data', 'zc-charts'); ?></div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($unique_id); ?>" class="zc-chart-container" style="height: <?php echo esc_attr($height); ?>; min-height: <?php echo esc_attr($height); ?>;">
        <?php
        // The actual chart will be rendered by JavaScript.
        // This container is where the charting library will attach its canvas or SVG.
        ?>
    </div>

    <?php if (!empty($config['controls'])): ?>
        <?php
        // This section should not appear for static charts, but is included for consistency
        // with the dynamic template in case of a configuration error.
        ?>
        <div class="zc-chart-controls" data-chart-id="<?php echo esc_attr($unique_id); ?>">
            <!-- Controls are not applicable for static charts -->
        </div>
    <?php endif; ?>

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof window.ZCChartLoader !== "undefined") {
                // Pass configuration to JavaScript
                var jsConfig = <?php echo wp_json_encode($config); ?>;
                jsConfig.data = <?php echo wp_json_encode($chart_data); ?>;
                jsConfig.isFallback = <?php echo $is_fallback ? 'true' : 'false'; ?>;
                // Static charts do not have controls
                jsConfig.controls = false;
                // Static charts show all data by default
                jsConfig.timeframe = 'all';

                new ZCChartLoader("<?php echo esc_js($unique_id); ?>", jsConfig).loadChart();
            } else {
                console.error("ZCChartLoader not found. Chart library may not be loaded correctly.");
            }
        });
    </script>
</div>
