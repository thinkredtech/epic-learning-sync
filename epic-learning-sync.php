<?php
/**
 * Plugin Name: Epic Learning Sync for LearnPress
 * Description: Synchronizes LearnPress courses with Epic Learning API data and provides tools to delete all courses.
 * Version: 2.1.0
 * Author: ThinkRED Technologies
 * Author URI: https://thinkred.tech
 * Text Domain: epic-learning-sync
 * Domain Path: /languages
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
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

// Define plugin constants
define('EPIC_LEARNING_SYNC_VERSION', '2.1.0');
define('EPIC_LEARNING_SYNC_FILE', __FILE__);
define('EPIC_LEARNING_SYNC_PATH', plugin_dir_path(__FILE__));
define('EPIC_LEARNING_SYNC_URL', plugin_dir_url(__FILE__));
define('EPIC_LEARNING_SYNC_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_epic_learning_sync()
{
    // Schedule cron job for automatic sync
    if (!wp_next_scheduled('epic_sync_cron_job')) {
        wp_schedule_event(time(), 'twicedaily', 'epic_sync_cron_job');
    }

    // Create default options
    add_option('epic_sync_api_credentials', array('id' => '', 'key' => ''));
    add_option('epic_sync_last_sync', 0);

    // Create necessary directories
    $upload_dir = wp_upload_dir();
    $temp_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_temp';
    $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';

    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }

    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }

    // Create .htaccess files to prevent direct access
    $htaccess_content = "# Prevent direct access to files\n";
    $htaccess_content .= "<IfModule mod_rewrite.c>\n";
    $htaccess_content .= "RewriteEngine On\n";
    $htaccess_content .= "RewriteRule .* - [F,L]\n";
    $htaccess_content .= "</IfModule>\n";

    file_put_contents(trailingslashit($temp_dir) . '.htaccess', $htaccess_content);
    file_put_contents(trailingslashit($backup_dir) . '.htaccess', $htaccess_content);

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_epic_learning_sync()
{
    // Clear scheduled cron job
    wp_clear_scheduled_hook('epic_sync_cron_job');

    // Flush rewrite rules
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'activate_epic_learning_sync');
register_deactivation_hook(__FILE__, 'deactivate_epic_learning_sync');

/**
 * The code that runs during plugin uninstallation.
 * This action is documented in includes/class-epic-learning-sync-uninstaller.php
 */
function uninstall_epic_learning_sync()
{
    // If uninstall.php is not called by WordPress, exit
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }

    // Include the uninstaller class
    require_once plugin_dir_path(__FILE__) . 'includes/class-epic-learning-sync-uninstaller.php';

    // Run the uninstaller
    Epic_Learning_Sync_Uninstaller::uninstall();
}

register_uninstall_hook(__FILE__, 'uninstall_epic_learning_sync');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_epic_learning_sync()
{
    // Include the main plugin class
    require_once plugin_dir_path(__FILE__) . 'includes/class-epic-learning-sync.php';

    // Initialize the plugin
    $plugin = Epic_Learning_Sync::instance();
}

// Run the plugin
run_epic_learning_sync();
