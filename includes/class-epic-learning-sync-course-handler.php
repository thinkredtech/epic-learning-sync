<?php
/**
 * Course Handler class for Epic Learning Sync
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
 * Course Handler class.
 *
 * Handles all course-related functionality.
 *
 * @since      1.0.0
 * @package    Epic_Learning_Sync
 * @author     ThinkRED Technologies
 */
class Epic_Learning_Sync_Course_Handler
{

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
     * @param    Epic_Learning_Sync_Logger  $logger    The logger instance.
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Process courses from temporary file.
     *
     * @since    1.0.0
     */
    public function process_courses()
    {
        global $wpdb;

        // Verify nonce
        if (!check_ajax_referer('epic_sync_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'epic-learning-sync')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->logger->error('Unauthorized access attempt to process_courses.');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'epic-learning-sync')));
        }

        // Get page number and batch size
        $batch_size = apply_filters('epic_learning_sync_batch_size', 10);
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        // Define the path for the temporary file
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_temp';
        $temp_file = trailingslashit($temp_dir) . 'epic_courses_temp.json';

        // Check if temporary file exists
        if (!file_exists($temp_file)) {
            $this->logger->error('Temporary file not found: ' . $temp_file);
            wp_send_json_error(array('message' => __('Temporary file not found.', 'epic-learning-sync')));
        }

        // Read and decode the temporary file
        $json_data = file_get_contents($temp_file);
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Malformed JSON in temporary file: ' . json_last_error_msg());
            @unlink($temp_file); // Cleanup
            wp_send_json_error(array('message' => __('Malformed JSON in temporary file.', 'epic-learning-sync')));
        }

        // Calculate total courses and current batch
        $total_courses = count($data);
        $start_index = ($page - 1) * $batch_size;
        $courses = array_slice($data, $start_index, $batch_size);

        // Create a backup of the current state before processing
        $this->create_sync_backup($page);

        // Process each course in the batch
        $processed_count = 0;
        foreach ($courses as $course) {
            // Validate course data
            if (!$this->validate_course_data($course)) {
                $this->logger->warning('Invalid course data, skipping course.');
                continue;
            }

            // Process the course
            $result = $this->process_single_course($course);

            if ($result) {
                $processed_count++;
            }
        }

        // Calculate progress
        $completed = $start_index + count($courses);
        $progress = min(100, round(($completed / $total_courses) * 100));
        $is_last_page = $completed >= $total_courses;

        // Clean up if this is the last batch
        if ($is_last_page) {
            @unlink($temp_file); // Remove temporary file

            // Keep only the last 5 backups
            $this->cleanup_old_backups(5);

            // Update last sync timestamp
            update_option('epic_sync_last_sync', time());
        }

        // Send response
        wp_send_json_success(array(
            'message' => $is_last_page ? __('Sync complete!', 'epic-learning-sync') : __('Syncing...', 'epic-learning-sync'),
            'progress' => $progress,
            'done' => $is_last_page,
            'next_page' => $is_last_page ? null : $page + 1,
            'total' => $total_courses,
            'processed' => $processed_count,
        ));
    }

    /**
     * Validate course data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $course    The course data.
     * @return   bool                True if valid, false otherwise.
     */
    private function validate_course_data($course)
    {
        // Check for required fields
        if (!isset($course['EpicCourseID']) || empty($course['EpicCourseID'])) {
            return false;
        }

        // Validate course ID format (assuming it should be numeric)
        if (!is_numeric($course['EpicCourseID'])) {
            return false;
        }

        return true;
    }

    /**
     * Process a single course.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $course    The course data.
     * @return   bool                True on success, false on failure.
     */
    private function process_single_course($course)
    {
        global $wpdb;

        // Extract course data with defaults
        $course_id = sanitize_text_field($course['EpicCourseID']);
        $course_title = isset($course['CourseTitle']) ? sanitize_text_field($course['CourseTitle']) : __('Untitled Course', 'epic-learning-sync');
        $course_description = isset($course['AboutCourse']) ? wp_kses_post(base64_decode($course['AboutCourse'])) : '';
        $last_update = isset($course['LastUpdateDate']) ? sanitize_text_field($course['LastUpdateDate']) : '1970-01-01';

        // Check if course already exists
        $existing_id = $this->get_existing_course_id($course_id);

        if ($existing_id) {
            // Update existing course
            return $this->update_course($existing_id, $course_title, $course_description, $last_update, $course);
        } else {
            // Create new course
            return $this->create_course($course_id, $course_title, $course_description, $last_update, $course);
        }
    }

    /**
     * Get existing course ID by Epic course ID.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $epic_course_id    The Epic course ID.
     * @return   int|false                   The post ID if found, false otherwise.
     */
    private function get_existing_course_id($epic_course_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            'epic_course_id',
            $epic_course_id
        );

        return $wpdb->get_var($query);
    }

    /**
     * Update an existing course.
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $post_id             The post ID.
     * @param    string    $title               The course title.
     * @param    string    $description         The course description.
     * @param    string    $last_update         The last update date.
     * @param    array     $additional_data     Additional course data.
     * @return   bool                          True on success, false on failure.
     */
    private function update_course($post_id, $title, $description, $last_update, $additional_data)
    {
        // Get current last update date
        $current_update = get_post_meta($post_id, 'epic_last_update', true);

        // Only update if the course has been updated
        if ($last_update > $current_update) {
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $title,
                'post_content' => $description,
            );

            $result = wp_update_post($post_data);

            if ($result) {
                // Update last update date
                update_post_meta($post_id, 'epic_last_update', $last_update);

                // Store additional data as needed
                $this->store_additional_course_data($post_id, $additional_data);

                $this->logger->info("Course updated: {$additional_data['EpicCourseID']}.");
                return true;
            } else {
                $this->logger->error("Failed to update course: {$additional_data['EpicCourseID']}.");
                return false;
            }
        }

        // No update needed
        return true;
    }

    /**
     * Create a new course.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $epic_course_id      The Epic course ID.
     * @param    string    $title               The course title.
     * @param    string    $description         The course description.
     * @param    string    $last_update         The last update date.
     * @param    array     $additional_data     Additional course data.
     * @return   bool                          True on success, false on failure.
     */
    private function create_course($epic_course_id, $title, $description, $last_update, $additional_data)
    {
        $post_data = array(
            'post_type' => 'lp_course',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $description,
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Add course meta data
            add_post_meta($post_id, 'epic_course_id', $epic_course_id);
            add_post_meta($post_id, 'epic_last_update', $last_update);

            // Store additional data as needed
            $this->store_additional_course_data($post_id, $additional_data);

            $this->logger->info("Course created: {$epic_course_id}.");
            return true;
        } else {
            $this->logger->error("Failed to create course: {$epic_course_id}.");
            return false;
        }
    }

    /**
     * Store additional course data.
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $post_id             The post ID.
     * @param    array     $additional_data     Additional course data.
     */
    private function store_additional_course_data($post_id, $additional_data)
    {
        // Store any additional data from the API response
        // This can be extended based on the API response structure

        // Example: Store course duration
        if (isset($additional_data['Duration'])) {
            update_post_meta($post_id, 'epic_course_duration', sanitize_text_field($additional_data['Duration']));
        }

        // Example: Store course level
        if (isset($additional_data['Level'])) {
            update_post_meta($post_id, 'epic_course_level', sanitize_text_field($additional_data['Level']));
        }

        // Allow other plugins to store additional data
        do_action('epic_learning_sync_store_course_data', $post_id, $additional_data);
    }

    /**
     * Create a backup of the current sync state.
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $page    The current page number.
     */
    private function create_sync_backup($page)
    {
        // Only create backup at the beginning of sync
        if ($page !== 1) {
            return;
        }

        global $wpdb;

        // Create backup directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);

            // Create .htaccess file to prevent direct access
            $htaccess_content = "# Prevent direct access to files\n";
            $htaccess_content .= "<IfModule mod_rewrite.c>\n";
            $htaccess_content .= "RewriteEngine On\n";
            $htaccess_content .= "RewriteRule .* - [F,L]\n";
            $htaccess_content .= "</IfModule>\n";

            file_put_contents(trailingslashit($backup_dir) . '.htaccess', $htaccess_content);
        }

        // Create backup file with timestamp
        $timestamp = date('Y-m-d-H-i-s');
        $backup_file = trailingslashit($backup_dir) . "epic_sync_backup_{$timestamp}.json";

        // Get all courses
        $courses = get_posts(array(
            'post_type' => 'lp_course',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));

        $backup_data = array();

        foreach ($courses as $course) {
            $epic_course_id = get_post_meta($course->ID, 'epic_course_id', true);

            if (!empty($epic_course_id)) {
                $backup_data[] = array(
                    'post_id' => $course->ID,
                    'epic_course_id' => $epic_course_id,
                    'title' => $course->post_title,
                    'content' => $course->post_content,
                    'status' => $course->post_status,
                    'last_update' => get_post_meta($course->ID, 'epic_last_update', true),
                );
            }
        }

        // Save backup data
        file_put_contents($backup_file, json_encode($backup_data));

        $this->logger->info("Sync backup created: {$backup_file}");
    }

    /**
     * Clean up old backups.
     *
     * @since    1.0.0
     * @access   private
     * @param    int       $keep_count    Number of backups to keep.
     */
    private function cleanup_old_backups($keep_count)
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';

        if (!file_exists($backup_dir)) {
            return;
        }

        // Get all backup files
        $backup_files = glob(trailingslashit($backup_dir) . 'epic_sync_backup_*.json');

        // Sort by modification time (newest first)
        usort($backup_files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep only the specified number of backups
        if (count($backup_files) > $keep_count) {
            $files_to_delete = array_slice($backup_files, $keep_count);

            foreach ($files_to_delete as $file) {
                @unlink($file);
                $this->logger->info("Old backup deleted: {$file}");
            }
        }
    }

    /**
     * Delete all courses.
     *
     * @since    1.0.0
     */
    public function delete_courses()
    {
        global $wpdb;

        // Verify nonce
        if (!check_ajax_referer('epic_sync_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'epic-learning-sync')));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->logger->error('Unauthorized access attempt to delete_courses.');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'epic-learning-sync')));
        }

        // Create a backup before deletion
        $this->create_deletion_backup();

        // Get page number and batch size
        $batch_size = apply_filters('epic_learning_sync_delete_batch_size', 50);
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        // Fetch courses in batches
        $courses = get_posts(array(
            'post_type' => 'lp_course',
            'fields' => 'ids',
            'posts_per_page' => $batch_size,
            'offset' => ($page - 1) * $batch_size,
        ));

        if (empty($courses)) {
            // Clean up LearnPress-related tables using prepared statements
            $this->clean_learnpress_tables();

            // Clean orphaned data from WordPress core tables
            $this->clean_wordpress_tables();

            wp_send_json_success(array(
                'message' => __('Deletion complete!', 'epic-learning-sync'),
                'progress' => 100,
                'done' => true,
            ));
        }

        // Delete each course
        foreach ($courses as $course_id) {
            wp_delete_post($course_id, true);
        }

        // Calculate progress
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
            'lp_course'
        ));

        $total = $remaining + count($courses);
        $progress = min(100, round((($total - $remaining) / $total) * 100));

        wp_send_json_success(array(
            'message' => $progress == 100 ? __('Deletion complete!', 'epic-learning-sync') : __('Deleting...', 'epic-learning-sync'),
            'progress' => $progress,
            'done' => $progress == 100,
            'next_page' => $page + 1,
        ));
    }

    /**
     * Create a backup before deletion.
     *
     * @since    1.0.0
     * @access   private
     */
    private function create_deletion_backup()
    {
        global $wpdb;

        // Create backup directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);

            // Create .htaccess file to prevent direct access
            $htaccess_content = "# Prevent direct access to files\n";
            $htaccess_content .= "<IfModule mod_rewrite.c>\n";
            $htaccess_content .= "RewriteEngine On\n";
            $htaccess_content .= "RewriteRule .* - [F,L]\n";
            $htaccess_content .= "</IfModule>\n";

            file_put_contents(trailingslashit($backup_dir) . '.htaccess', $htaccess_content);
        }

        // Create backup file with timestamp
        $timestamp = date('Y-m-d-H-i-s');
        $backup_file = trailingslashit($backup_dir) . "epic_delete_backup_{$timestamp}.json";

        // Get all courses
        $courses = get_posts(array(
            'post_type' => 'lp_course',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));

        $backup_data = array();

        foreach ($courses as $course) {
            $epic_course_id = get_post_meta($course->ID, 'epic_course_id', true);

            $backup_data[] = array(
                'post_id' => $course->ID,
                'epic_course_id' => $epic_course_id,
                'title' => $course->post_title,
                'content' => $course->post_content,
                'status' => $course->post_status,
                'last_update' => get_post_meta($course->ID, 'epic_last_update', true),
            );
        }

        // Save backup data
        file_put_contents($backup_file, json_encode($backup_data));

        $this->logger->info("Deletion backup created: {$backup_file}");
    }

    /**
     * Clean LearnPress tables.
     *
     * @since    1.0.0
     * @access   private
     */
    private function clean_learnpress_tables()
    {
        global $wpdb;

        // List of LearnPress tables to clean
        $tables = array(
            'learnpress_courses',
            'learnpress_sections',
            'learnpress_section_items',
            'learnpress_user_items',
            'learnpress_user_itemmeta',
            'learnpress_user_item_results',
        );

        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }

        $this->logger->info('LearnPress tables cleaned.');
    }

    /**
     * Clean WordPress tables.
     *
     * @since    1.0.0
     * @access   private
     */
    private function clean_wordpress_tables()
    {
        global $wpdb;

        // Clean orphaned postmeta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        // Clean orphaned term relationships
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        // Delete all lp_course posts
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_type = %s",
            'lp_course'
        ));

        // Clean unused terms
        $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->term_taxonomy})");

        // Clean unused taxonomies
        $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE count = 0");

        // Remove residual meta data
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'epic_course_%'");

        $this->logger->info('WordPress tables cleaned.');
    }

    /**
     * Restore from backup.
     *
     * @since    1.0.0
     * @param    string    $backup_file    The backup file path.
     * @return   array                    Result array with status and message.
     */
    public function restore_from_backup($backup_file)
    {
        // Check if file exists
        if (!file_exists($backup_file)) {
            return array(
                'success' => false,
                'message' => __('Backup file not found.', 'epic-learning-sync'),
            );
        }

        // Read backup data
        $json_data = file_get_contents($backup_file);
        $backup_data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Invalid backup file format.', 'epic-learning-sync'),
            );
        }

        // Restore courses
        $restored = 0;
        $failed = 0;

        foreach ($backup_data as $course) {
            $post_data = array(
                'post_title' => $course['title'],
                'post_content' => $course['content'],
                'post_status' => $course['status'],
                'post_type' => 'lp_course',
            );

            $post_id = wp_insert_post($post_data);

            if ($post_id) {
                // Restore meta data
                update_post_meta($post_id, 'epic_course_id', $course['epic_course_id']);
                update_post_meta($post_id, 'epic_last_update', $course['last_update']);

                $restored++;
            } else {
                $failed++;
            }
        }

        $this->logger->info("Restore completed. Restored: {$restored}, Failed: {$failed}");

        return array(
            'success' => true,
            'message' => sprintf(
                __('Restore completed. Restored: %d, Failed: %d', 'epic-learning-sync'),
                $restored,
                $failed
            ),
        );
    }

    /**
     * Get available backups.
     *
     * @since    1.0.0
     * @return   array    List of available backups.
     */
    public function get_available_backups()
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'epic_sync_backups';

        if (!file_exists($backup_dir)) {
            return array();
        }

        // Get all backup files
        $sync_backups = glob(trailingslashit($backup_dir) . 'epic_sync_backup_*.json');
        $delete_backups = glob(trailingslashit($backup_dir) . 'epic_delete_backup_*.json');

        $backups = array_merge($sync_backups, $delete_backups);

        // Sort by modification time (newest first)
        usort($backups, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $result = array();

        foreach ($backups as $backup) {
            $filename = basename($backup);
            $type = strpos($filename, 'sync_backup') !== false ? 'sync' : 'delete';
            $date = filemtime($backup);

            $result[] = array(
                'file' => $backup,
                'name' => $filename,
                'type' => $type,
                'date' => date('Y-m-d H:i:s', $date),
            );
        }

        return $result;
    }
}
