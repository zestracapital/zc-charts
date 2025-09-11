<?php
/**
 * Plugin Name: Zestra Capital - Charts (Visualization)
 * Plugin URI: https://client.zestracapital.com
 * Description: Pure visualization system for economic data with secure API integration
 * Version: 2.0.0
 * Author: Zestra Capital
 * Author URI: https://zestracapital.com
 * Text Domain: zc-charts
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZC_CHARTS_VERSION', '2.0.0');
define('ZC_CHARTS_PLUGIN_FILE', __FILE__);
define('ZC_CHARTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZC_CHARTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZC_CHARTS_TEXT_DOMAIN', 'zc-charts');

/**
 * Main Charts plugin class
 */
class ZC_Charts_Plugin {
    
    private static $instance = null;
    private $dmt_plugin_active = false;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->check_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Check for required dependencies
     */
    private function check_dependencies() {
        // Check if ZC DMT plugin is active
        if (is_plugin_active('zc-dmt/zc-dmt.php') || class_exists('ZC_Data_Management_Tool')) {
            $this->dmt_plugin_active = true;
        }
        
        if (!$this->dmt_plugin_active) {
            add_action('admin_notices', [$this, 'dependency_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Show dependency notice
     */
    public function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('ZC Charts Plugin Error:', ZC_CHARTS_TEXT_DOMAIN); ?></strong>
                <?php _e('This plugin requires the ZC DMT (Data Management Tool) plugin to be installed and activated.', ZC_CHARTS_TEXT_DOMAIN); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                    <?php _e('Go to Plugins', ZC_CHARTS_TEXT_DOMAIN); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if (!$this->dmt_plugin_active) {
            return;
        }
        
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
        
        // Shortcode registration
        add_action('init', [$this, 'register_shortcodes']);
        
        // AJAX handlers
        add_action('wp_ajax_zc_charts_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_zc_charts_test_connection', [$this, 'ajax_test_connection']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->load_includes();
        do_action('zc_charts_initialized');
    }
    
    /**
     * Load plugin includes
     */
    private function load_includes() {
        $includes = [
            'includes/class-security.php',
            'includes/class-api-client.php',
            'includes/class-charts.php',
            'includes/class-shortcodes.php',
            'includes/class-fallback.php'
        ];
        
        foreach ($includes as $file) {
            $file_path = ZC_CHARTS_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Load textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            ZC_CHARTS_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('ZC Charts Settings', ZC_CHARTS_TEXT_DOMAIN),
            __('ZC Charts', ZC_CHARTS_TEXT_DOMAIN),
            'manage_options',
            'zc-charts-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if ($hook !== 'settings_page_zc-charts-settings') {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'zc-charts-admin',
            ZC_CHARTS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ZC_CHARTS_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'zc-charts-admin',
            ZC_CHARTS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ZC_CHARTS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zc-charts-admin', 'zcCharts', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zc_charts_nonce'),
            'dmt_api_url' => rest_url('zc-dmt/v1'),
            'strings' => [
                'loading' => __('Loading...', ZC_CHARTS_TEXT_DOMAIN),
                'error' => __('Error occurred', ZC_CHARTS_TEXT_DOMAIN),
                'success' => __('Success!', ZC_CHARTS_TEXT_DOMAIN),
                'test_connection' => __('Testing connection...', ZC_CHARTS_TEXT_DOMAIN),
                'connection_success' => __('Connection successful!', ZC_CHARTS_TEXT_DOMAIN),
                'connection_failed' => __('Connection failed!', ZC_CHARTS_TEXT_DOMAIN),
            ]
        ]);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_scripts() {
        // Only enqueue if charts are present on the page
        if (!$this->page_has_charts()) {
            return;
        }
        
        // Chart CSS
        wp_enqueue_style(
            'zc-charts-public',
            ZC_CHARTS_PLUGIN_URL . 'assets/css/charts.css',
            [],
            ZC_CHARTS_VERSION
        );
        
        // Chart loader JS
        wp_enqueue_script(
            'zc-charts-loader',
            ZC_CHARTS_PLUGIN_URL . 'assets/js/chart-loader.js',
            ['jquery'],
            ZC_CHARTS_VERSION,
            true
        );
        
        // Get chart library preference
        $chart_library = get_option('zc_charts_default_library', 'chartjs');
        
        // Load chart libraries conditionally
        if ($chart_library === 'chartjs' || $this->page_needs_chartjs()) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                [],
                '3.9.1',
                true
            );
            
            wp_enqueue_script(
                'zc-chartjs-handler',
                ZC_CHARTS_PLUGIN_URL . 'assets/js/chartjs-handler.js',
                ['chartjs'],
                ZC_CHARTS_VERSION,
                true
            );
        }
        
        if ($chart_library === 'highcharts' || $this->page_needs_highcharts()) {
            wp_enqueue_script(
                'highcharts',
                'https://code.highcharts.com/highcharts.js',
                [],
                '10.3.0',
                true
            );
            
            wp_enqueue_script(
                'zc-highcharts-handler',
                ZC_CHARTS_PLUGIN_URL . 'assets/js/highcharts-handler.js',
                ['highcharts'],
                ZC_CHARTS_VERSION,
                true
            );
        }
        
        // Fallback handler
        wp_enqueue_script(
            'zc-fallback-handler',
            ZC_CHARTS_PLUGIN_URL . 'assets/js/fallback-handler.js',
            ['jquery'],
            ZC_CHARTS_VERSION,
            true
        );
        
        // Localize for frontend
        wp_localize_script('zc-charts-loader', 'zcChartsConfig', [
            'api_key' => ZC_Charts_Security::get_stored_api_key(),
            'dmt_api_url' => rest_url('zc-dmt/v1'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'default_library' => $chart_library,
            'fallback_enabled' => get_option('zc_charts_enable_fallback', 1),
            'strings' => [
                'loading_chart' => __('Loading chart...', ZC_CHARTS_TEXT_DOMAIN),
                'unauthorized' => __('Unauthorized access. Please check API key configuration.', ZC_CHARTS_TEXT_DOMAIN),
                'data_not_found' => __('Data not available for this indicator.', ZC_CHARTS_TEXT_DOMAIN),
                'network_error' => __('Unable to load data. Please check your connection.', ZC_CHARTS_TEXT_DOMAIN),
                'fallback_data' => __('Displaying cached data', ZC_CHARTS_TEXT_DOMAIN),
                'no_data' => __('No data available', ZC_CHARTS_TEXT_DOMAIN)
            ]
        ]);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        if (class_exists('ZC_Charts_Shortcodes')) {
            ZC_Charts_Shortcodes::init();
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('zc_charts_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $default_library = sanitize_text_field($_POST['default_library']);
        $enable_fallback = isset($_POST['enable_fallback']) ? 1 : 0;
        
        // Validate API key with DMT plugin
        if (!empty($api_key)) {
            $validation = ZC_Charts_API_Client::validate_api_key($api_key);
            if (!$validation) {
                wp_send_json_error('Invalid API key. Please check the key and try again.');
            }
        }
        
        // Save settings
        update_option('zc_charts_api_key', $api_key);
        update_option('zc_charts_default_library', $default_library);
        update_option('zc_charts_enable_fallback', $enable_fallback);
        
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('zc_charts_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
        // Test connection to DMT plugin
        $result = ZC_Charts_API_Client::test_connection($api_key);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Connection successful!',
                'indicators_count' => $result['indicators_count'] ?? 0,
                'key_name' => $result['key_name'] ?? 'Unknown'
            ]);
        } else {
            wp_send_json_error($result['message'] ?? 'Connection failed');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('ZC Charts requires ZC DMT plugin to be activated first.', ZC_CHARTS_TEXT_DOMAIN));
        }
        
        // Set default options
        add_option('zc_charts_version', ZC_CHARTS_VERSION);
        add_option('zc_charts_activated_time', current_time('timestamp'));
        add_option('zc_charts_default_library', 'chartjs');
        add_option('zc_charts_enable_fallback', 1);
        
        flush_rewrite_rules();
        do_action('zc_charts_activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        do_action('zc_charts_deactivated');
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->safe_include_admin_page('admin/settings.php');
    }
    
    /**
     * Safely include admin page
     */
    private function safe_include_admin_page($page) {
        $file_path = ZC_CHARTS_PLUGIN_DIR . $page;
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Page Not Found</h1><p>Admin template missing: ' . esc_html($page) . '</p></div>';
        }
    }
    
    /**
     * Check if current page has charts
     */
    private function page_has_charts() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for shortcodes in post content
        return has_shortcode($post->post_content, 'zc_chart_dynamic') || 
               has_shortcode($post->post_content, 'zc_chart_static');
    }
    
    /**
     * Check if page needs Chart.js
     */
    private function page_needs_chartjs() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return strpos($post->post_content, 'library="chartjs"') !== false;
    }
    
    /**
     * Check if page needs Highcharts
     */
    private function page_needs_highcharts() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return strpos($post->post_content, 'library="highcharts"') !== false;
    }
    
    /**
     * Get DMT plugin status
     */
    public function get_dmt_status() {
        return [
            'active' => $this->dmt_plugin_active,
            'version' => defined('ZC_DMT_VERSION') ? ZC_DMT_VERSION : null,
            'api_available' => $this->dmt_plugin_active && function_exists('rest_url')
        ];
    }
}

// Initialize plugin
function zc_charts_init() {
    return ZC_Charts_Plugin::instance();
}
add_action('plugins_loaded', 'zc_charts_init', 15); // Load after DMT plugin

// Helper function
function zc_charts() {
    return ZC_Charts_Plugin::instance();
}

// Activation check function
function zc_charts_check_dmt_dependency() {
    if (!is_plugin_active('zc-dmt/zc-dmt.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        
        wp_die(
            '<h1>' . __('Plugin Activation Failed', ZC_CHARTS_TEXT_DOMAIN) . '</h1>' .
            '<p>' . __('ZC Charts plugin requires the ZC DMT (Data Management Tool) plugin to be installed and activated first.', ZC_CHARTS_TEXT_DOMAIN) . '</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to Plugins Page', ZC_CHARTS_TEXT_DOMAIN) . '</a></p>',
            __('Plugin Dependency Error', ZC_CHARTS_TEXT_DOMAIN),
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, 'zc_charts_check_dmt_dependency');
