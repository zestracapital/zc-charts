<?php
/**
 * ZC Charts Security Class
 * Handles API key validation and security checks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_Security {
    
    /**
     * Instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize the class
        $this->init();
    }
    
    /**
     * Initialize the class
     */
    public function init() {
        // Initialization logic if needed
    }
    
    /**
     * Validate API key with DMT plugin
     */
    public static function validate_api_key($api_key) {
        // Check if DMT plugin is active and has the required class
        if (!class_exists('ZC_DMT_Security')) {
            return false;
        }
        
        // Use DMT plugin's security class to validate the key
        $dmt_security = new ZC_DMT_Security();
        $validation_result = $dmt_security->validate_key($api_key);
        
        return $validation_result;
    }
    
    /**
     * Get API key from settings
     */
    public static function get_api_key() {
        return get_option('zc_charts_api_key', '');
    }
    
    /**
     * Check if user has permission to access charts
     */
    public static function check_permissions() {
        // For public charts, we just need to validate the API key
        $api_key = self::get_api_key();
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $validation_result = self::validate_api_key($api_key);
        
        if (!$validation_result) {
            return new WP_Error('invalid_api_key', __('Invalid API key.', 'zc-charts'));
        }
        
        return true;
    }
    
    /**
     * Sanitize API key
     */
    public static function sanitize_api_key($api_key) {
        // Remove any whitespace
        $api_key = trim($api_key);
        
        // Ensure it's a valid hash format (assuming SHA-256 hashes)
        if (strlen($api_key) !== 64 || !ctype_xdigit($api_key)) {
            return '';
        }
        
        return $api_key;
    }
    
    /**
     * Generate a unique identifier for tracking
     */
    public static function generate_tracking_id() {
        return uniqid('zc_charts_', true);
    }
    
    /**
     * Log security events
     */
    public static function log_security_event($event, $details = array()) {
        // Check if DMT plugin has error logging capability
        if (class_exists('ZC_DMT_Error_Logger')) {
            $logger = new ZC_DMT_Error_Logger();
            $logger->log('security', $event, $details);
        } else {
            // Fallback to WordPress debug log
            if (WP_DEBUG) {
                error_log(sprintf(
                    '[ZC Charts Security] %s: %s',
                    $event,
                    json_encode($details)
                ));
            }
        }
    }
    
    /**
     * Check for suspicious activity
     */
    public static function check_suspicious_activity($tracking_id, $request_count) {
        // Simple rate limiting - more sophisticated implementation would use transients
        if ($request_count > 100) { // Arbitrary high threshold
            self::log_security_event('suspicious_activity', array(
                'tracking_id' => $tracking_id,
                'request_count' => $request_count,
                'ip' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return sanitize_text_field($ip);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Verify nonce for admin actions
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Create nonce for admin actions
     */
    public static function create_nonce($action) {
        return wp_create_nonce($action);
    }
}