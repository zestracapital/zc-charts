<?php
/**
 * Plugin Name: Zestra Capital - Charts (Visualization)
 * Plugin URI: https://client.zestracapital.com
 * Description: Pure visualization system for economic data with secure API integration. Depends on the ZC DMT plugin.
 * Version: 2.0.0
 * Author: Zestra Capital
 * Text Domain: zc-charts
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ZC_Charts
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
 * ZC_Charts_Plugin class.
 * Main plugin class following the Singleton pattern.
 */
class ZC_Charts_Plugin {

    /**
     * The single instance of the class.
     *
     * @var ZC_Charts_Plugin
     */
    private static $instance = null;

    /**
     * Main ZC_Charts_Plugin Instance.
     *
     * Ensures only one instance of ZC_Charts_Plugin is loaded or can be loaded.
     *
     * @return ZC_Charts_Plugin - Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'zc-charts'), ZC_CHARTS_VERSION);
    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'zc-charts'), ZC_CHARTS_VERSION);
    }

    /**
     * ZC_Charts_Plugin Constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define ZC Charts Constants.
     */
    private function define_constants() {
        // Constants are already defined above
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    public function includes() {
        // Core classes
        require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-security.php';
        require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-fallback.php';
        require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-charts.php';
        require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-shortcodes.php';

        if (is_admin()) {
            $this->admin_includes();
        }
    }

    /**
     * Include required admin files.
     */
    public function admin_includes() {
        // Admin files
        require_once ZC_CHARTS_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'init'), 0);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        // Register shortcodes later in the init process
        add_action('init', array('ZC_Charts_Shortcodes', 'init'));
    }

    /**
     * Init ZC Charts when WordPress Initialises.
     */
    public function init() {
        // Load plugin text domain for translations
        load_plugin_textdomain('zc-charts', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        // Check for DMT dependency
        if (!$this->check_dmt_dependency()) {
            // The notice is added in the activate method or via admin_notices hook
            return;
        }

        // Initialize core classes if needed
        // Shortcodes are initialized via the action hook in init_hooks
    }

    /**
     * Check if the required ZC DMT plugin is active.
     *
     * @return bool True if dependency is met, false otherwise.
     */
    private function check_dmt_dependency() {
        // Check if ZC DMT plugin is active
        if (!is_plugin_active('zc-dmt/zc-dmt.php')) {
            return false;
        }
        return true;
    }

    /**
     * Display an admin notice if the DMT dependency is not met.
     */
    public function dependency_notice() {
        if (!$this->check_dmt_dependency()) {
            $class = 'notice notice-error';
            $message = sprintf(
                /* translators: 1: Link to DMT plugin */
                esc_html__('ZC Charts requires the %s to be installed and activated.', 'zc-charts'),
                '<a href="https://client.zestracapital.com" target="_blank">ZC DMT plugin</a>'
            );

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }
    }

    /**
     * Enqueue admin styles and scripts.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function admin_enqueue_scripts($hook_suffix) {
        // Only load on our settings page
        if ($hook_suffix !== 'settings_page_zc-charts-settings') {
            return;
        }
        wp_enqueue_style('zc-charts-admin', ZC_CHARTS_PLUGIN_URL . 'assets/css/admin.css', array(), ZC_CHARTS_VERSION);
        wp_enqueue_script('zc-charts-admin', ZC_CHARTS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ZC_CHARTS_VERSION, true);
    }

    /**
     * Enqueue frontend styles and scripts.
     */
    public function frontend_enqueue_scripts() {
        // Styles are loaded conditionally when a shortcode is rendered
        // Scripts are loaded conditionally by the chart loader JS
        wp_enqueue_style('zc-charts-public', ZC_CHARTS_PLUGIN_URL . 'assets/css/public.css', array(), ZC_CHARTS_VERSION);
    }

    /**
     * What to do on activation.
     */
    public function activate() {
        // Check dependency on activation
        if (!$this->check_dmt_dependency()) {
            // Deactivate this plugin
            deactivate_plugins(plugin_basename(__FILE__));
            // Add an admin notice
            add_action('admin_notices', array($this, 'dependency_notice'));
            // Prevent further execution
            wp_die(
                sprintf(
                    /* translators: 1: Link to DMT plugin */
                    esc_html__('ZC Charts requires the %s to be installed and activated. The plugin has been deactivated.', 'zc-charts'),
                    '<a href="https://client.zestracapital.com" target="_blank">ZC DMT plugin</a>'
                ),
                esc_html__('Plugin Activation Error', 'zc-charts'),
                array(
                    'response'  => 200,
                    'back_link' => true,
                )
            );
        }

        // Add an option to store the version for potential upgrades
        add_option('zc_charts_version', ZC_CHARTS_VERSION);
    }
}

/**
 * Main instance of ZC_Charts_Plugin.
 *
 * Returns the main instance of ZC_Charts to prevent the need to use globals.
 *
 * @return ZC_Charts_Plugin
 */
function ZC_Charts() {
    return ZC_Charts_Plugin::instance();
}

// Global for backwards compatibility.
$GLOBALS['zc_charts'] = ZC_Charts();

// Add admin notice hook
add_action('admin_notices', array('ZC_Charts_Plugin', 'dependency_notice'));
