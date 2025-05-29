<?php
/**
 * Logger class for Epic Learning Sync
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
 * Logger class.
 *
 * Handles logging for the plugin with rotation and size limits.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync_Logger
{

    /**
     * Log file path.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $log_file    The log file path.
     */
    private $log_file;

    /**
     * Maximum log file size in bytes.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_size    Maximum log file size in bytes (5MB).
     */
    private $max_size = 5242880; // 5MB

    /**
     * Maximum number of log files to keep.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_files    Maximum number of log files to keep.
     */
    private $max_files = 5;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit($upload_dir['basedir']) . 'epic_sync_debug.log';
    }

    /**
     * Log a message.
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     * @param    string    $level      The log level (info, warning, error).
     */
    public function log($message, $level = 'info')
    {
        // Check if log file exists and is too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_size) {
            $this->rotate_logs();
        }

        // Format the log message
        $formatted_message = sprintf(
            '[%s] [%s] %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            PHP_EOL
        );

        // Write to log file
        error_log($formatted_message, 3, $this->log_file);
    }

    /**
     * Log an info message.
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    public function info($message)
    {
        $this->log($message, 'info');
    }

    /**
     * Log a warning message.
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    public function warning($message)
    {
        $this->log($message, 'warning');
    }

    /**
     * Log an error message.
     *
     * @since    1.0.0
     * @param    string    $message    The message to log.
     */
    public function error($message)
    {
        $this->log($message, 'error');
    }

    /**
     * Rotate log files.
     *
     * @since    1.0.0
     * @access   private
     */
    private function rotate_logs()
    {
        // Remove oldest log file if max files reached
        $old_log = trailingslashit(dirname($this->log_file)) . 'epic_sync_debug.' . $this->max_files . '.log';
        if (file_exists($old_log)) {
            @unlink($old_log);
        }

        // Shift log files
        for ($i = $this->max_files - 1; $i >= 1; $i--) {
            $old_log = trailingslashit(dirname($this->log_file)) . 'epic_sync_debug.' . $i . '.log';
            $new_log = trailingslashit(dirname($this->log_file)) . 'epic_sync_debug.' . ($i + 1) . '.log';
            if (file_exists($old_log)) {
                @rename($old_log, $new_log);
            }
        }

        // Rename current log file
        @rename($this->log_file, trailingslashit(dirname($this->log_file)) . 'epic_sync_debug.1.log');
    }

    /**
     * Get the log file path.
     *
     * @since    1.0.0
     * @return   string    The log file path.
     */
    public function get_log_file()
    {
        return $this->log_file;
    }

    /**
     * Clear all log files.
     *
     * @since    1.0.0
     */
    public function clear_logs()
    {
        // Remove main log file
        if (file_exists($this->log_file)) {
            @unlink($this->log_file);
        }

        // Remove rotated log files
        for ($i = 1; $i <= $this->max_files; $i++) {
            $log_file = trailingslashit(dirname($this->log_file)) . 'epic_sync_debug.' . $i . '.log';
            if (file_exists($log_file)) {
                @unlink($log_file);
            }
        }
    }
}
