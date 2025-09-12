<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZC_Charts_Shortcodes class.
 * Handles the registration and processing of chart shortcodes.
 */
class ZC_Charts_Shortcodes {

    /**
     * Initialize shortcodes.
     */
    public static function init() {
        add_shortcode('zc_chart_dynamic', array(__CLASS__, 'render_dynamic_chart'));
        add_shortcode('zc_chart_static', array(__CLASS__, 'render_static_chart'));
    }

    /**
     * Render a dynamic chart shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content (not used).
     * @param string $tag     The shortcode tag.
     * @return string The HTML output for the chart.
     */
    public static function render_dynamic_chart($atts, $content = '', $tag = 'zc_chart_dynamic') {
        // Normalize attribute keys to lowercase
        $atts = array_change_key_case((array) $atts, CASE_LOWER);

        // Merge user attributes with defaults
        $atts = shortcode_atts(
            array(
                'id'         => '', // Required
                'library'    => 'chartjs', // chartjs | highcharts
                'type'       => 'line', // line | bar | area
                'timeframe'  => '1y', // 3m, 6m, 1y, 2y, 3y, 5y, 10y, 15y, 20y, 25y, all
                'height'     => '300px',
                'controls'   => true, // Show interactive controls
                'css_class'  => '',
            ),
            $atts,
            $tag
        );

        // Sanitize attributes
        $config = array(
            'id'         => sanitize_key($atts['id']),
            'library'    => in_array($atts['library'], array('chartjs', 'highcharts')) ? $atts['library'] : 'chartjs',
            'type'       => sanitize_key($atts['type']),
            'timeframe'  => sanitize_text_field($atts['timeframe']),
            'height'     => sanitize_text_field($atts['height']),
            'controls'   => wp_validate_boolean($atts['controls']),
            'css_class'  => sanitize_html_class($atts['css_class']),
            'is_static'  => false, // Internal flag
            'unique_id'  => 'zc-dynamic-chart-' . sanitize_html_class($atts['id']) . '-' . uniqid(),
        );

        // Render the chart using the Charts class
        return ZC_Charts_Charts::render_chart($config);
    }

    /**
     * Render a static chart shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content (not used).
     * @param string $tag     The shortcode tag.
     * @return string The HTML output for the chart.
     */
    public static function render_static_chart($atts, $content = '', $tag = 'zc_chart_static') {
        // Normalize attribute keys to lowercase
        $atts = array_change_key_case((array) $atts, CASE_LOWER);

        // Merge user attributes with defaults
        $atts = shortcode_atts(
            array(
                'id'         => '', // Required
                'library'    => 'chartjs', // chartjs | highcharts
                'type'       => 'line', // line | bar | area
                'height'     => '300px',
                'css_class'  => '',
            ),
            $atts,
            $tag
        );

        // Sanitize attributes
        $config = array(
            'id'         => sanitize_key($atts['id']),
            'library'    => in_array($atts['library'], array('chartjs', 'highcharts')) ? $atts['library'] : 'chartjs',
            'type'       => sanitize_key($atts['type']),
            'height'     => sanitize_text_field($atts['height']),
            'controls'   => false, // Static charts do not have controls
            'timeframe'  => 'all', // Static charts show all data by default
            'css_class'  => sanitize_html_class($atts['css_class']),
            'is_static'  => true, // Internal flag
            'unique_id'  => 'zc-static-chart-' . sanitize_html_class($atts['id']) . '-' . uniqid(),
        );

        // Render the chart using the Charts class
        return ZC_Charts_Charts::render_chart($config);
    }
}
