<?php
/**
 * ZC Charts Dynamic Chart Template
 *
 * This template is used for rendering dynamic charts.
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
        <div class="zc-chart-controls" data-chart-id="<?php echo esc_attr($unique_id); ?>">
            <div class="timeframe-controls">
                <?php
                // Define available timeframes
                $timeframes = array(
                    '3m'  => __('3M', 'zc-charts'),
                    '6m'  => __('6M', 'zc-charts'),
                    '1y'  => __('1Y', 'zc-charts'),
                    '2y'  => __('2Y', 'zc-charts'),
                    '5y'  => __('5Y', 'zc-charts'),
                    'all' => __('All', 'zc-charts'),
                );

                foreach ($timeframes as $tf_value => $tf_label) :
                    $is_active = (!empty($config['timeframe']) && $config['timeframe'] === $tf_value) || (empty($config['timeframe']) && $tf_value === '1y');
                ?>
                    <button type="button" data-range="<?php echo esc_attr($tf_value); ?>" class="<?php echo $is_active ? 'active' : ''; ?>">
                        <?php echo esc_html($tf_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="chart-controls">
                <select class="chart-type-selector">
                    <option value="line"><?php esc_html_e('Line Chart', 'zc-charts'); ?></option>
                    <option value="bar"><?php esc_html_e('Bar Chart', 'zc-charts'); ?></option>
                    <option value="area"><?php esc_html_e('Area Chart', 'zc-charts'); ?></option>
                </select>
                <button type="button" class="export-btn"><?php esc_html_e('Export', 'zc-charts'); ?></button>
            </div>
        </div>
    <?php endif; ?>

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof window.ZCChartLoader !== "undefined") {
                // Pass configuration to JavaScript
                var jsConfig = <?php echo wp_json_encode($config); ?>;
                jsConfig.data = <?php echo wp_json_encode($chart_data); ?>;
                jsConfig.isFallback = <?php echo $is_fallback ? 'true' : 'false'; ?>;

                new ZCChartLoader("<?php echo esc_js($unique_id); ?>", jsConfig).loadChart();
            } else {
                console.error("ZCChartLoader not found. Chart library may not be loaded correctly.");
            }
        });
    </script>
</div>
