<?php
/**
 * API class for Epic Learning Sync
 *
 * @package     Epic_Learning_Sync
 * @author      ThinkRED Technologies
 * @copyright   2025 ThinkRED Technologies
 * @license     GPL-3.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API class.
 *
 * Handles all API-related functionality.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync_API
{

    /**
     * The API base URL.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_base_url    The API base URL.
     */
    private $api_base_url;

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Epic_Learning_Sync_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    string                     $api_base_url    The API base URL.
     * @param    Epic_Learning_Sync_Logger  $logger         The logger instance.
     */
    public function __construct($api_base_url, $logger)
    {
        $this->api_base_url = $api_base_url;
        $this->logger = $logger;
    }

    /**
     * Fetch courses from the API.
     *
     * @since    1.0.0
     */
    public function fetch_courses()
    {
        // Verify nonce
        if (!check_ajax_referer('epic_sync_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'epic-learning-sync')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->logger->error('Unauthorized access attempt to fetch_courses.');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'epic-learning-sync')));
        }

        // Define the path for the temporary file
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_temp';

        // Create temp directory if it doesn't exist
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);

            // Create .htaccess file to prevent direct access
            $htaccess_content = "# Prevent direct access to files\n";
            $htaccess_content .= "<IfModule mod_rewrite.c>\n";
            $htaccess_content .= "RewriteEngine On\n";
            $htaccess_content .= "RewriteRule .* - [F,L]\n";
            $htaccess_content .= "</IfModule>\n";

            file_put_contents(trailingslashit($temp_dir) . '.htaccess', $htaccess_content);
        }

        $temp_file = trailingslashit($temp_dir) . 'epic_courses_temp.json';

        // Validate upload directory
        if (!is_writable($temp_dir)) {
            $this->logger->error('Temp directory is not writable: ' . $temp_dir);
            wp_send_json_error(array('message' => __('Temporary directory is not writable.', 'epic-learning-sync')));
        }

        // Get API credentials
        $credentials = get_option('epic_sync_api_credentials', array('id' => '', 'key' => ''));

        if (empty($credentials['id']) || empty($credentials['key'])) {
            $this->logger->error('API credentials not configured.');
            wp_send_json_error(array('message' => __('API credentials not configured.', 'epic-learning-sync')));
        }

        // Fetch the API response with proper error handling
        $response = $this->make_api_request("courseexport.json", $credentials);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('Failed to fetch API data: ' . $error_message);
            wp_send_json_error(array('message' => __('Failed to fetch API data: ', 'epic-learning-sync') . $error_message));
        }

        // Process the response
        $data = $this->process_api_response($response, $temp_file);

        if (is_wp_error($data)) {
            $error_message = $data->get_error_message();
            $this->logger->error($error_message);
            wp_send_json_error(array('message' => $error_message));
        }

        // Log success
        $file_size = filesize($temp_file);
        $this->logger->info("Temporary file created successfully. Size: {$file_size} bytes.");

        wp_send_json_success(array(
            'message' => __('Data fetched successfully.', 'epic-learning-sync'),
            'progress' => 0,
            'done' => false,
            'next_page' => 1, // Start processing from page 1
            'total' => count($data), // Include total count
        ));
    }

    /**
     * Make an API request.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $endpoint      The API endpoint.
     * @param    array     $credentials   The API credentials.
     * @return   array|WP_Error          The API response or WP_Error on failure.
     */
    private function make_api_request($endpoint, $credentials)
    {
        $url = $this->api_base_url . $endpoint;

        $this->logger->info("Making API request to: {$url}");

        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($credentials['id'] . ':' . $credentials['key']),
                'Accept' => 'application/json',
            ),
            'timeout' => 60,
            'sslverify' => true,
        );

        return wp_remote_get($url, $args);
    }

    /**
     * Process the API response.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $response    The API response.
     * @param    string    $temp_file   The temporary file path.
     * @return   array|WP_Error        The processed data or WP_Error on failure.
     */
    private function process_api_response($response, $temp_file)
    {
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(
                    __('Unexpected API response status: %d', 'epic-learning-sync'),
                    $status_code
                )
            );
        }

        $response_body = wp_remote_retrieve_body($response);

        // Validate JSON
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_error',
                __('Invalid JSON received from API.', 'epic-learning-sync')
            );
        }

        // Validate data structure
        if (!is_array($data) || empty($data)) {
            return new WP_Error(
                'data_error',
                __('Invalid data structure received from API.', 'epic-learning-sync')
            );
        }

        // Write valid JSON to the temporary file
        $result = file_put_contents($temp_file, json_encode($data));

        if ($result === false) {
            return new WP_Error(
                'file_error',
                __('Failed to write data to the temporary file.', 'epic-learning-sync')
            );
        }

        return $data;
    }

    /**
     * Get the API base URL.
     *
     * @since    1.0.0
     * @return   string    The API base URL.
     */
    public function get_api_base_url()
    {
        return $this->api_base_url;
    }
}
