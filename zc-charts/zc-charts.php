<?php
/**
 * Plugin Name: Zestra Capital - Charts (Visualization)
 * Plugin URI: https://client.zestracapital.com
 * Description: Pure visualization system for economic data with secure API integration
 * Version: 2.0.0
 * Author: Zestra Capital
 * Text Domain: zc-charts
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * Depends: ZC DMT Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZC_CHARTS_VERSION', '2.0.0');
define('ZC_CHARTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZC_CHARTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main ZC Charts Plugin Class
 * This class handles the core functionality and initialization of the plugin.
 */
class ZC_Charts {

    /**
     * Instance of the class
     *
     * @var ZC_Charts|null
     */
    private static $instance = null;

    /**
     * Get instance of the class (Singleton pattern)
     *
     * @return ZC_Charts
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * Initializes the plugin by setting up hooks.
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     * Hooks into WordPress actions and filters.
     */
    public function init() {
        // Check plugin dependencies
        add_action('admin_init', array($this, 'check_dependencies'));

        // Load plugin text domain for translations
        add_action('init', array($this, 'load_textdomain'));

        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add admin menu items
        add_action('admin_menu', array($this, 'add_admin_menus'));

        // Initialize settings
        add_action('admin_init', array($this, 'init_settings'));

        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));
    }

    /**
     * Check plugin dependencies
     * Ensures the ZC DMT plugin is active.
     */
    public function check_dependencies() {
        if (!is_plugin_active('zc-dmt/zc-dmt.php')) {
            add_action('admin_notices', array($this, 'show_dependency_notice'));
            // Deactivate self to prevent errors
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    /**
     * Show dependency notice
     * Displays an admin notice if the DMT plugin is not active.
     */
    public function show_dependency_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('ZC Charts requires the ZC DMT plugin to be installed and activated.', 'zc-charts'); ?></p>
        </div>
        <?php
    }

    /**
     * Load plugin text domain
     * Enables translation support.
     */
    public function load_textdomain() {
        load_plugin_textdomain('zc-charts', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Plugin activation hook
     * Runs when the plugin is activated.
     */
    public function activate() {
        // No specific activation logic needed currently
    }

    /**
     * Plugin deactivation hook
     * Runs when the plugin is deactivated.
     */
    public function deactivate() {
        // No specific deactivation logic needed currently
    }

    /**
     * Enqueue frontend scripts and styles
     * Loads necessary assets for displaying charts on the frontend.
     */
    public function enqueue_frontend_scripts() {
        // Chart libraries and assets are enqueued conditionally within shortcodes
        // to avoid loading them on every page.
    }

    /**
     * Enqueue admin scripts and styles
     * Loads necessary assets for the admin interface.
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load our scripts on our plugin's settings page
        if ($hook_suffix === 'settings_page_zc-charts') {
            wp_enqueue_style('zc-charts-admin', ZC_CHARTS_PLUGIN_URL . 'assets/css/admin.css', array(), ZC_CHARTS_VERSION);
            wp_enqueue_script('zc-charts-admin', ZC_CHARTS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ZC_CHARTS_VERSION, true);
            
            // Localize script with necessary data for JS
            wp_localize_script('zc-charts-admin', 'zc_charts_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('zc_charts_admin_nonce')
            ));
        }
    }

    /**
     * Add admin menus
     * Adds the plugin's settings page to the WordPress admin menu.
     */
    public function add_admin_menus() {
        add_options_page(
            __('ZC Charts Settings', 'zc-charts'),
            __('ZC Charts', 'zc-charts'),
            'manage_options', // Required capability
            'zc-charts',      // Menu slug
            array($this, 'display_settings_page') // Function to display the page
        );
    }

    /**
     * Initialize settings
     * Registers plugin settings, sections, and fields.
     */
    public function init_settings() {
        // Register settings group
        register_setting(
            'zc_charts_settings_group',    // Settings group name
            'zc_charts_api_key',            // Option name for API key
            array('sanitize_callback' => 'sanitize_text_field') // Sanitization
        );
        register_setting(
            'zc_charts_settings_group',    // Settings group name
            'zc_charts_default_library',   // Option name for default library
            array('sanitize_callback' => 'sanitize_key') // Sanitization
        );

        // Add settings section
        add_settings_section(
            'zc_charts_main_settings',           // Section ID
            __('API Configuration', 'zc-charts'), // Section title
            array($this, 'main_settings_section_callback'), // Callback
            'zc-charts-settings'              // Page slug
        );

        // Add API Key field
        add_settings_field(
            'zc_charts_api_key_field',        // Field ID
            __('API Key', 'zc-charts'),       // Field title
            array($this, 'api_key_field_callback'), // Callback to render the field
            'zc-charts-settings',          // Page slug
            'zc_charts_main_settings'      // Section ID
        );

        // Add Default Library field
        add_settings_field(
            'zc_charts_default_library_field',        // Field ID
            __('Default Chart Library', 'zc-charts'), // Field title
            array($this, 'default_library_field_callback'), // Callback
            'zc-charts-settings',          // Page slug
            'zc_charts_main_settings'      // Section ID
        );
    }

    /**
     * Main settings section callback
     * Outputs content at the top of the settings section.
     */
    public function main_settings_section_callback() {
        echo '<p>' . esc_html__('Configure the API key and default chart library.', 'zc-charts') . '</p>';
    }

    /**
     * API Key field callback
     * Renders the API Key selection dropdown.
     */
    public function api_key_field_callback() {
        $current_key = get_option('zc_charts_api_key');
        ?>
        <select name="zc_charts_api_key" id="zc_charts_api_key">
            <option value=""><?php esc_html_e('Select API Key', 'zc-charts'); ?></option>
            <?php
            // Try to get API keys from the DMT plugin
            $dmt_api_keys = array();
            if (class_exists('ZC_DMT_Security')) {
                $dmt_security = new ZC_DMT_Security();
                $dmt_api_keys = $dmt_security->get_all_keys(); // Assuming this method exists
            }

            foreach ($dmt_api_keys as $key_data) {
                $key_hash = isset($key_data['key_hash']) ? $key_data['key_hash'] : '';
                $key_name = isset($key_data['key_name']) ? $key_data['key_name'] : __('Unnamed Key', 'zc-charts');
                $selected = selected($current_key, $key_hash, false);
                echo '<option value="' . esc_attr($key_hash) . '"' . $selected . '>' . esc_html($key_name) . '</option>';
            }
            ?>
        </select>
        <p class="description"><?php esc_html_e('Select the API key generated by the ZC DMT plugin.', 'zc-charts'); ?></p>
        <?php
    }

    /**
     * Default Library field callback
     * Renders the Default Chart Library selection.
     */
    public function default_library_field_callback() {
        $current_library = get_option('zc_charts_default_library', 'chartjs');
        ?>
        <select name="zc_charts_default_library" id="zc_charts_default_library">
            <option value="chartjs" <?php selected($current_library, 'chartjs'); ?>>Chart.js</option>
            <option value="highcharts" <?php selected($current_library, 'highcharts'); ?>>Highcharts</option>
        </select>
        <p class="description"><?php esc_html_e('Select the default chart library to use for rendering charts.', 'zc-charts'); ?></p>
        <?php
    }

    /**
     * Display settings page
     * Renders the HTML for the plugin's settings page.
     */
    public function display_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show any admin notices
        settings_errors('zc_charts_messages');
        ?>
        <div class="wrap zc-charts-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Simple Plugin Info Box -->
            <div class="zc-charts-info-box">
                <h2><?php esc_html_e('About ZC Charts', 'zc-charts'); ?></h2>
                <p><?php esc_html_e('This plugin visualizes economic indicators managed by the ZC DMT plugin. It requires a valid API key from DMT to function.', 'zc-charts'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php
                // Output security fields for the registered setting
                settings_fields('zc_charts_settings_group');
                
                // Output the settings section and fields
                do_settings_sections('zc-charts-settings');
                
                // Output the save settings button
                submit_button(__('Save Settings', 'zc-charts'));
                ?>
            </form>
            
            <!-- Help Section -->
            <div class="zc-charts-help-section">
                <h2><?php esc_html_e('How to Use', 'zc-charts'); ?></h2>
                <p><strong><?php esc_html_e('Shortcodes:', 'zc-charts'); ?></strong></p>
                <ul>
                    <li><code>[zc_chart_dynamic id="indicator-slug" library="chartjs" timeframe="1y" height="400px"]</code></li>
                    <li><code>[zc_chart_static id="indicator-slug" library="highcharts"]</code></li>
                </ul>
                <p><strong><?php esc_html_e('Steps:', 'zc-charts'); ?></strong></p>
                <ol>
                    <li><?php esc_html_e('Ensure the ZC DMT plugin is installed and activated.', 'zc-charts'); ?></li>
                    <li><?php esc_html_e('Go to ZC DMT > Settings and generate an API key.', 'zc-charts'); ?></li>
                    <li><?php esc_html_e('Select the API key above and save settings.', 'zc-charts'); ?></li>
                    <li><?php esc_html_e('Use the shortcodes in your posts/pages.', 'zc-charts'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Register shortcodes
     * Hooks the plugin's shortcodes into WordPress.
     */
    public function register_shortcodes() {
        add_shortcode('zc_chart_dynamic', array($this, 'shortcode_dynamic_chart'));
        add_shortcode('zc_chart_static', array($this, 'shortcode_static_chart'));
    }

    /**
     * Shortcode handler for [zc_chart_dynamic]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the chart.
     */
    public function shortcode_dynamic_chart($atts) {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(
            array(
                'id'        => '',
                'library'   => get_option('zc_charts_default_library', 'chartjs'),
                'timeframe' => '1y',
                'height'    => '400px',
            ),
            $atts,
            'zc_chart_dynamic'
        );

        // Basic validation
        if (empty($atts['id'])) {
            return '<div class="zc-chart-error">' . esc_html__('Indicator ID is required for the chart.', 'zc-charts') . '</div>';
        }

        // This is where you would integrate with DMT's API to fetch data
        // For now, we return a placeholder
        return $this->render_chart_placeholder($atts);
    }

    /**
     * Shortcode handler for [zc_chart_static]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the chart.
     */
    public function shortcode_static_chart($atts) {
        // Parse shortcode attributes with defaults
        $atts = shortcode_atts(
            array(
                'id'      => '',
                'library' => get_option('zc_charts_default_library', 'chartjs'),
                // Static charts typically don't have timeframe or height controls
            ),
            $atts,
            'zc_chart_static'
        );

        // Basic validation
        if (empty($atts['id'])) {
            return '<div class="zc-chart-error">' . esc_html__('Indicator ID is required for the chart.', 'zc-charts') . '</div>';
        }

        // This is where you would integrate with DMT's API to fetch data
        // For now, we return a placeholder
        return $this->render_chart_placeholder($atts);
    }

    /**
     * Render a placeholder chart
     * This is a temporary function until full DMT integration is done.
     *
     * @param array $atts Chart attributes.
     * @return string HTML for the placeholder.
     */
    private function render_chart_placeholder($atts) {
        $unique_id = 'zc-chart-placeholder-' . uniqid();
        $height_style = isset($atts['height']) ? 'style="height:' . esc_attr($atts['height']) . ';"' : '';
        
        ob_start();
        ?>
        <div class="zc-chart-placeholder" <?php echo $height_style; ?>>
            <div class="zc-chart-placeholder-content">
                <p><strong>ZC Charts Placeholder</strong></p>
                <p><?php esc_html_e('Chart would render here.', 'zc-charts'); ?></p>
                <p><small>ID: <?php echo esc_html($atts['id']); ?> | Lib: <?php echo esc_html($atts['library']); ?></small></p>
                <?php if (!empty($atts['timeframe'])): ?>
                <p><small>TF: <?php echo esc_html($atts['timeframe']); ?></small></p>
                <?php endif; ?>
            </div>
        </div>
        <style>
        .zc-chart-placeholder {
            border: 2px dashed #ccc;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #666;
            margin: 10px 0;
        }
        .zc-chart-placeholder-content p { margin: 5px 0; }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
ZC_Charts::get_instance();