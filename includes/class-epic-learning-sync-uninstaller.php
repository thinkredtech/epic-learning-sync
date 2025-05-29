<?php
/**
 * Uninstaller class for Epic Learning Sync
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
 * Uninstaller class.
 *
 * Handles all uninstallation functionality.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync_Uninstaller
{

    /**
     * Uninstall the plugin.
     *
     * @since    1.0.0
     * @static
     */
    public static function uninstall()
    {
        // Delete options
        delete_option('epic_sync_api_credentials');
        delete_option('epic_sync_last_sync');

        // Clean up temporary files and directories
        self::cleanup_files_and_directories();

        // Clean up database tables if user opted to remove all data
        if (get_option('epic_sync_remove_all_data', false)) {
            self::cleanup_database();
        }
    }

    /**
     * Clean up files and directories.
     *
     * @since    1.0.0
     * @static
     * @access   private
     */
    private static function cleanup_files_and_directories()
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_temp';
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';
        $log_file = trailingslashit($upload_dir['basedir']) . 'epic_sync_debug.log';

        // Remove temporary directory
        if (file_exists($temp_dir)) {
            self::remove_directory($temp_dir);
        }

        // Remove backup directory
        if (file_exists($backup_dir)) {
            self::remove_directory($backup_dir);
        }

        // Remove log file
        if (file_exists($log_file)) {
            @unlink($log_file);
        }

        // Remove log rotation files
        for ($i = 1; $i <= 5; $i++) {
            $rotated_log = trailingslashit($upload_dir['basedir']) . "epic_sync_debug.{$i}.log";
            if (file_exists($rotated_log)) {
                @unlink($rotated_log);
            }
        }
    }

    /**
     * Remove a directory and all its contents.
     *
     * @since    1.0.0
     * @static
     * @access   private
     * @param    string    $dir    The directory to remove.
     */
    private static function remove_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = trailingslashit($dir) . $file;

            if (is_dir($path)) {
                self::remove_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Clean up database tables.
     *
     * @since    1.0.0
     * @static
     * @access   private
     */
    private static function cleanup_database()
    {
        global $wpdb;

        // Remove all course meta data
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'epic_course_%'");

        // Remove all courses if user opted to remove all data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_type = %s",
            'lp_course'
        ));

        // Clean orphaned postmeta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        // Clean orphaned term relationships
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        // Clean unused terms
        $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->term_taxonomy})");

        // Clean unused taxonomies
        $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE count = 0");
    }
}
