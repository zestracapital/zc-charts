<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZC_Charts_API_Client class.
 * Handles communication with the ZC DMT plugin's REST API.
 */
class ZC_Charts_API_Client {

    /**
     * Get the base URL for the ZC DMT REST API.
     *
     * @return string|WP_Error The API base URL or WP_Error on failure.
     */
    public static function get_api_base_url() {
        return ZC_Charts_Security::get_dmt_api_base_url();
    }

    /**
     * Fetch live data for an indicator from the DMT plugin.
     *
     * @param string $indicator_slug The slug of the indicator.
     * @param string $api_key The API key for authentication.
     * @param array  $args Optional. Additional query arguments (e.g., start_date, end_date).
     * @return array|WP_Error Data array on success, WP_Error on failure.
     */
    public static function fetch_live_data($indicator_slug, $api_key, $args = array()) {
        $api_base = self::get_api_base_url();

        if (is_wp_error($api_base)) {
            return $api_base; // Return the WP_Error
        }

        $endpoint = 'data/' . sanitize_key($indicator_slug);

        // Add access key and any other args to the query
        $query_args = array_merge(
            array('access_key' => $api_key),
            $args
        );

        $url = add_query_arg($query_args, trailingslashit($api_base) . $endpoint);

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            error_log('ZC Charts: Live Data Fetch Failed (WP Error) - ' . $response->get_error_message() . ' - URL: ' . $url);
            return new WP_Error('api_fetch_error', sprintf(__('Failed to fetch live data: %s', 'zc-charts'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = sprintf(__('DMT API returned HTTP %d', 'zc-charts'), $response_code);
            // Try to get a more specific error message from the body
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
                $error_message = $data['message'];
            } elseif (!empty($body)) {
                // If it's not JSON, log the body for debugging
                error_log('ZC Charts: Live Data Fetch Failed (HTTP ' . $response_code . ') - Body: ' . $body);
            }
            return new WP_Error('api_http_error', $error_message, array('status' => $response_code));
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZC Charts: Live Data Fetch Failed (Invalid JSON Response) - Body: ' . $body);
            return new WP_Error('invalid_json', __('Invalid JSON response from DMT API.', 'zc-charts'));
        }

        return $data;
    }

    /**
     * Fetch backup data for an indicator from the DMT plugin.
     *
     * @param string $indicator_slug The slug of the indicator.
     * @param string $api_key The API key for authentication.
     * @return array|WP_Error Data array on success, WP_Error on failure.
     */
    public static function fetch_backup_data($indicator_slug, $api_key) {
        $api_base = self::get_api_base_url();

        if (is_wp_error($api_base)) {
            return $api_base; // Return the WP_Error
        }

        $endpoint = 'backup/' . sanitize_key($indicator_slug);
        $url = add_query_arg('access_key', $api_key, trailingslashit($api_base) . $endpoint);

        $response = wp_remote_get($url, array('timeout' => 20)); // Slightly longer timeout for backup

        if (is_wp_error($response)) {
            error_log('ZC Charts: Backup Data Fetch Failed (WP Error) - ' . $response->get_error_message() . ' - URL: ' . $url);
            return new WP_Error('backup_fetch_error', sprintf(__('Failed to fetch backup data: %s', 'zc-charts'), $response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_message = sprintf(__('DMT Backup API returned HTTP %d', 'zc-charts'), $response_code);
            // Try to get a more specific error message from the body
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
                $error_message = $data['message'];
            } elseif (!empty($body)) {
                error_log('ZC Charts: Backup Data Fetch Failed (HTTP ' . $response_code . ') - Body: ' . $body);
            }
            return new WP_Error('backup_http_error', $error_message, array('status' => $response_code));
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZC Charts: Backup Data Fetch Failed (Invalid JSON Response) - Body: ' . $body);
            return new WP_Error('invalid_json', __('Invalid JSON response from DMT Backup API.', 'zc-charts'));
        }

        return $data;
    }

    /**
     * Validate an API key with the DMT plugin.
     * This is a wrapper around ZC_Charts_Security::validate_api_key for consistency
     * and potential future enhancements (e.g., caching validation results).
     *
     * @param string $api_key The API key to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_api_key($api_key) {
        return ZC_Charts_Security::validate_api_key($api_key);
    }
}
