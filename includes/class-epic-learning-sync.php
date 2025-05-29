<?php
/**
 * Main plugin class
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
 * Main plugin class.
 *
 * This class is responsible for initializing the plugin and setting up
 * all the necessary hooks and filters.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync
{

    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   private
     * @var      Epic_Learning_Sync    $instance    The single instance of the class.
     */
    private static $instance = null;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of the plugin.
     */
    private $version;

    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The name of the plugin.
     */
    private $plugin_name;

    /**
     * The plugin file.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_file    The main plugin file.
     */
    private $plugin_file;

    /**
     * The plugin directory.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_dir    The plugin directory.
     */
    private $plugin_dir;

    /**
     * The plugin URL.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_url    The plugin URL.
     */
    private $plugin_url;

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
     * Main Epic_Learning_Sync Instance.
     *
     * Ensures only one instance of Epic_Learning_Sync is loaded or can be loaded.
     *
     * @since    1.0.0
     * @static
     * @return   Epic_Learning_Sync    Main instance.
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Epic_Learning_Sync Constructor.
     */
    public function __construct()
    {
        $this->version = '2.1.0';
        $this->plugin_name = 'epic-learning-sync';
        $this->plugin_file = EPIC_LEARNING_SYNC_FILE;
        $this->plugin_dir = plugin_dir_path($this->plugin_file);
        $this->plugin_url = plugin_dir_url($this->plugin_file);
        $this->api_base_url = 'https://epiclearningnetwork.com/feeds/';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        // Include the logger class
        require_once $this->plugin_dir . 'includes/class-epic-learning-sync-logger.php';
        $this->logger = new Epic_Learning_Sync_Logger();

        // Include the admin class
        require_once $this->plugin_dir . 'includes/class-epic-learning-sync-admin.php';

        // Include the API class
        require_once $this->plugin_dir . 'includes/class-epic-learning-sync-api.php';

        // Include the course handler class
        require_once $this->plugin_dir . 'includes/class-epic-learning-sync-course-handler.php';
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $admin = new Epic_Learning_Sync_Admin($this->get_plugin_name(), $this->get_version(), $this->get_plugin_url());

        // Admin menu and settings
        add_action('admin_menu', array($admin, 'add_admin_menu'));
        add_action('admin_init', array($admin, 'register_settings'));

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
    }

    /**
     * Register all of the AJAX hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_ajax_hooks()
    {
        $api = new Epic_Learning_Sync_API($this->get_api_base_url(), $this->logger);
        $course_handler = new Epic_Learning_Sync_Course_Handler($this->logger);

        // AJAX hooks for course sync
        add_action('wp_ajax_epic_sync_courses', array($api, 'fetch_courses'));
        add_action('wp_ajax_epic_sync_courses_continue', array($course_handler, 'process_courses'));

        // AJAX hooks for course deletion
        add_action('wp_ajax_epic_delete_courses', array($course_handler, 'delete_courses'));
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'epic-learning-sync',
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages/'
        );
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Retrieve the plugin directory.
     *
     * @since     1.0.0
     * @return    string    The plugin directory.
     */
    public function get_plugin_dir()
    {
        return $this->plugin_dir;
    }

    /**
     * Retrieve the plugin URL.
     *
     * @since     1.0.0
     * @return    string    The plugin URL.
     */
    public function get_plugin_url()
    {
        return $this->plugin_url;
    }

    /**
     * Retrieve the API base URL.
     *
     * @since     1.0.0
     * @return    string    The API base URL.
     */
    public function get_api_base_url()
    {
        return $this->api_base_url;
    }

    /**
     * Retrieve the logger instance.
     *
     * @since     1.0.0
     * @return    Epic_Learning_Sync_Logger    The logger instance.
     */
    public function get_logger()
    {
        return $this->logger;
    }
}
