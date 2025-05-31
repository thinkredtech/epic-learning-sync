<?php
/**
 * Admin class for Epic Learning Sync
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
 * Admin class.
 *
 * Handles all admin-related functionality.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync_Admin
{

    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The name of the plugin.
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of the plugin.
     */
    private $version;

    /**
     * The plugin URL.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_url    The plugin URL.
     */
    private $plugin_url;

    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    The name of the plugin.
     * @param    string    $version        The version of the plugin.
     * @param    string    $plugin_url     The plugin URL.
     */
    public function __construct($plugin_name, $version, $plugin_url)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->plugin_url = $plugin_url;

        // Add settings link to plugin actions
        add_filter("plugin_action_links_" . EPIC_LEARNING_SYNC_BASENAME, array($this, "add_settings_link"));
    }

    /**
     * Add settings link to plugin actions.
     *
     * @since    2.1.0
     * @param    array    $links    The existing plugin action links.
     * @return   array    The modified plugin action links.
     */
    public function add_settings_link($links)
    {
        $settings_link = 
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url("options-general.php?page=epic-sync-settings")),
                esc_html__("Settings", "epic-learning-sync")
            );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add admin menu items.
     *
     * @since    1.0.0
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('Epic Learning Sync Settings', 'epic-learning-sync'),
            __('Epic Learning Sync', 'epic-learning-sync'),
            'manage_options',
            'epic-sync-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings()
    {
        register_setting(
            'epic_sync_settings',
            'epic_sync_api_credentials',
            array(
                'sanitize_callback' => array($this, 'sanitize_api_credentials'),
                'default' => array(
                    'id' => '',
                    'key' => '',
                ),
            )
        );
    }

    /**
     * Sanitize API credentials.
     *
     * @since    1.0.0
     * @param    array    $input    The input array.
     * @return   array    The sanitized input array.
     */
    public function sanitize_api_credentials($input)
    {
        $sanitized_input = array();

        if (isset($input['id'])) {
            $sanitized_input['id'] = sanitize_text_field($input['id']);
        }

        if (isset($input['key'])) {
            $sanitized_input['key'] = sanitize_text_field($input['key']);
        }

        return $sanitized_input;
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since    1.0.0
     * @param    string    $hook    The current admin page.
     */
    public function enqueue_scripts($hook)
    {
        if ('settings_page_epic-sync-settings' !== $hook) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            $this->plugin_name,
            $this->plugin_url . 'assets/css/epic-learning-sync-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Enqueue JS
        wp_enqueue_script(
            $this->plugin_name,
            $this->plugin_url . 'assets/js/epic-learning-sync-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script
        wp_localize_script(
            $this->plugin_name,
            'EpicSync',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('epic_sync_nonce'),
                'confirmDelete' => __('Are you sure you want to delete all courses? This action cannot be undone.', 'epic-learning-sync'),
                'confirmSync' => __('Are you sure you want to sync all courses? This may take a while.', 'epic-learning-sync'),
            )
        );
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form was submitted
        if (isset($_POST['epic_sync_save']) && check_admin_referer('epic_sync_settings_save', 'epic_sync_nonce')) {
            $credentials = array(
                'id' => isset($_POST['epic_sync_id']) ? sanitize_text_field(wp_unslash($_POST['epic_sync_id'])) : '',
                'key' => isset($_POST['epic_sync_key']) ? sanitize_text_field(wp_unslash($_POST['epic_sync_key'])) : '',
            );

            update_option('epic_sync_api_credentials', $credentials);

            add_settings_error(
                'epic_sync_settings',
                'epic_sync_settings_saved',
                __('Settings saved successfully.', 'epic-learning-sync'),
                'updated'
            );
        }

        // Get current settings
        $credentials = get_option('epic_sync_api_credentials', array('id' => '', 'key' => ''));

        // Include the settings page template
        include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/epic-learning-sync-admin-display.php';
    }
}
