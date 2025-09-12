<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZC_Charts_Fallback class.
 * Handles retrieving backup data from the ZC DMT plugin.
 */
class ZC_Charts_Fallback {

    /**
     * Attempt to load backup data for an indicator when live data fails.
     *
     * @param string $indicator_slug The slug of the indicator.
     * @param string $api_key The API key for authentication.
     * @return array|false Data array on success, false on failure.
     */
    public static function get_backup_data($indicator_slug, $api_key) {
        // Basic validation
        if (empty($indicator_slug) || empty($api_key)) {
            return false;
        }

        // Get the DMT API base URL
        $api_base = ZC_Charts_API_Client::get_api_base_url();

        if (is_wp_error($api_base)) {
            error_log('ZC Charts Fallback: Failed to get DMT API base URL. Error: ' . $api_base->get_error_message());
            return false;
        }

        // Construct the backup endpoint URL
        $endpoint = 'backup/' . sanitize_key($indicator_slug);
        $url = add_query_arg('access_key', $api_key, trailingslashit($api_base) . $endpoint);

        // Set up the request arguments
        $args = array(
            'timeout' => 20, // Slightly longer timeout for backup data
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        // Make the API request to the DMT backup endpoint
        $response = wp_remote_get($url, $args);

        // Check for WP HTTP API errors
        if (is_wp_error($response)) {
            error_log('ZC Charts Fallback: Backup Data Fetch Failed (WP Error) - ' . $response->get_error_message() . ' - URL: ' . $url);
            return false;
        }

        // Get response details
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check for non-200 HTTP response codes
        if ($response_code !== 200) {
            $error_message = sprintf(__('DMT Backup API returned HTTP %d', 'zc-charts'), $response_code);
            // Try to get a more specific error message from the response body
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
                $error_message = $data['message'];
            } elseif (!empty($body)) {
                // Log the raw body for debugging if it's not valid JSON
                error_log('ZC Charts Fallback: Backup Data Fetch Failed (HTTP ' . $response_code . ') - Body: ' . $body);
            }
            error_log('ZC Charts Fallback: ' . $error_message . ' - URL: ' . $url);
            return false;
        }

        // Decode the JSON response
        $data = json_decode($body, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZC Charts Fallback: Backup Data Fetch Failed (Invalid JSON Response) - Body: ' . $body);
            return false;
        }

        // Check if the response contains the expected 'data' key
        if (!isset($data['data']) || !is_array($data['data'])) {
            error_log('ZC Charts Fallback: Backup Data Fetch Failed (Missing or invalid data in response) - Response: ' . print_r($data, true));
            return false;
        }

        // Return the data array (which should be the time series data points)
        return $data['data'];
    }
}
