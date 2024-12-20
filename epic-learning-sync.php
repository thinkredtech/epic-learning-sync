<?php
/**
 * Plugin Name: Epic Learning Sync for LearnPress
 * Description: Synchronizes LearnPress courses with Epic Learning API data and provides tools to delete all courses.
 * Version: 2.0.0
 * Author: ThinkRED Technologies (https://thinkred.tech)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constants
define( 'EPIC_API_BASE_URL', 'https://epiclearningnetwork.com/feeds/' );

// Activation/Deactivation
register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'epic_sync_cron_job' ) ) wp_schedule_event( time(), 'twicedaily', 'epic_sync_cron_job' );
    add_option( 'epic_sync_api_credentials', [ 'id' => '', 'key' => '' ] );
});
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'epic_sync_cron_job' );
    delete_option( 'epic_sync_api_credentials' );
});

// Scripts
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'settings_page_epic-sync-settings') return;
    wp_enqueue_script('epic-sync-script', plugin_dir_url(__FILE__) . 'epic-sync.js', ['jquery'], '1.0.0', true);
    wp_localize_script('epic-sync-script', 'EpicSync', ['ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('epic_sync_nonce')]);
});

// Admin Page
add_action( 'admin_menu', function() {
    add_options_page('Epic Learning Sync Settings', 'Epic Learning Sync', 'manage_options', 'epic-sync-settings', function() {
        if ( isset( $_POST['epic_sync_save'] ) ) {
            check_admin_referer( 'epic_sync_settings_save', 'epic_sync_nonce' );
            update_option( 'epic_sync_api_credentials', [
                'id' => sanitize_text_field( $_POST['epic_sync_id'] ),
                'key' => sanitize_text_field( $_POST['epic_sync_key'] )
            ]);
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        $credentials = get_option( 'epic_sync_api_credentials', [ 'id' => '', 'key' => '' ] );
        ?>
        <div class="wrap">
            <h1>Epic Learning Sync Settings</h1>
            <form method="post">
                <?php wp_nonce_field( 'epic_sync_settings_save', 'epic_sync_nonce' ); ?>
                <table class="form-table">
                    <tr><th>API Application ID</th><td><input type="text" name="epic_sync_id" value="<?php echo esc_attr( $credentials['id'] ); ?>" /></td></tr>
                    <tr><th>API Key</th><td><input type="text" name="epic_sync_key" value="<?php echo esc_attr( $credentials['key'] ); ?>" /></td></tr>
                </table>
                <button type="submit" name="epic_sync_save" class="button button-primary">Save Settings</button>
            </form>
            <div id="sync-progress-section"><h3>Course Sync Progress</h3>
                <p id="sync-status">Status: Idle</p>
                <div id="progress-bar" style="width:100%;background:#ccc;height:20px;">
                    <div id="progress-bar-fill" style="width:0;background:#4caf50;height:100%;"></div>
                </div><button id="start-sync" class="button">Start Sync</button>
            </div>
            <div id="delete-progress-section"><h3>Course Deletion Progress</h3>
                <p id="delete-status">Status: Idle</p>
                <div id="delete-progress-bar" style="width:100%;background:#ccc;height:20px;">
                    <div id="delete-progress-bar-fill" style="width:0;background:#f44336;height:100%;"></div>
                </div><button id="start-delete" class="button">Delete All Courses</button>
            </div>
        </div>
        <?php
    });
});

// Fetch API Data and Save to Temporary File
add_action('wp_ajax_epic_sync_courses', function() {
    check_ajax_referer('epic_sync_nonce', 'nonce');

    // Define the path for the temporary file
    $temp_file = WP_CONTENT_DIR . '/uploads/epic_courses_temp.json';

    // Validate upload directory
    if (!is_writable(WP_CONTENT_DIR . '/uploads')) {
        epic_log_error('Epic Sync Error: Uploads directory is not writable.');
        wp_send_json_error(['message' => 'Uploads directory is not writable.']);
    }

    // Fetch the API response
    $response = wp_remote_get(EPIC_API_BASE_URL . "courseexportv2.json", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(get_option('epic_sync_api_credentials')['id'] . ':' . get_option('epic_sync_api_credentials')['key']),
        ],
        'timeout' => 300,
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        epic_log_error("Epic Sync Error: Failed to fetch API data. Error: $error_message");
        wp_send_json_error(['message' => 'Failed to fetch API data.']);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        epic_log_error("Epic Sync Error: Unexpected status code $status_code.");
        wp_send_json_error(['message' => 'Unexpected API response status.']);
    }

    $response_body = wp_remote_retrieve_body($response);

    // Validate JSON
    $data = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        epic_log_error('Epic Sync Error: Invalid JSON received from API.');
        epic_log_error("Response body: $response_body");
        wp_send_json_error(['message' => 'Invalid JSON received from API.']);
    }

    // Write valid JSON to the temporary file
    $result = file_put_contents($temp_file, json_encode($data));
    if ($result === false) {
        epic_log_error('Epic Sync Error: Failed to write data to the temporary file.');
        wp_send_json_error(['message' => 'Failed to write data to the temporary file.']);
    }

    // Log success
    $file_size = filesize($temp_file);
    epic_log_error("Temporary file created successfully. Size: {$file_size} bytes.");

    $total_courses = count($data);

    wp_send_json_success([
        'message'   => 'Data fetched successfully.',
        'progress'  => 0,
        'done'      => false,
        'next_page' => 1, // Start processing from page 1
        'total'     => $total_courses, // Include total count
    ]);
});

// Continue Processing Data in Chunks
add_action('wp_ajax_epic_sync_courses_continue', function() {
    check_ajax_referer('epic_sync_nonce', 'nonce');
    global $wpdb;

    $batch_size = 10; // Process 10 courses per PHP iteration
    $page = $_POST['page'] ?? 1;

    $temp_file = WP_CONTENT_DIR . '/uploads/epic_courses_temp.json';
    if (!file_exists($temp_file)) {
        epic_log_error('Epic Sync Error: Temporary file not found.');
        wp_send_json_error(['message' => 'Temporary file not found.']);
    }

    $data = json_decode(file_get_contents($temp_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        epic_log_error('Epic Sync Error: Malformed JSON in temporary file.');
        unlink($temp_file); // Cleanup
        wp_send_json_error(['message' => 'Malformed JSON in temporary file.']);
    }

    $total_courses = count($data);
    $start_index = ($page - 1) * $batch_size;
    $courses = array_slice($data, $start_index, $batch_size);

    foreach ($courses as $course) {
        if (!isset($course['EpicCourseID'])) {
            epic_log_error('Epic Sync Warning: Missing EpicCourseID, skipping course.');
            continue;
        }
        epic_sync_process_course($course);
    }

    $completed = $start_index + count($courses);
    $progress = min(100, round(($completed / $total_courses) * 100));
    $is_last_page = $completed >= $total_courses;

    if ($is_last_page) {
        unlink($temp_file); // Cleanup after final batch
    }

    wp_send_json_success([
        'message'   => $is_last_page ? 'Sync complete!' : 'Syncing...',
        'progress'  => $progress,
        'done'      => $is_last_page,
        'next_page' => $is_last_page ? null : $page + 1,
        'total'     => $total_courses,
    ]);
});

// Process Individual Course. Ensure the process doesn't skip valid courses after deletion
function epic_sync_process_course($course) {
    global $wpdb;

    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        'epic_course_id', $course['EpicCourseID']
    ));

    $course_title = $course['CourseTitle'] ?? 'Untitled Course';
    $course_description = base64_decode($course['AboutCourse'] ?? '');
    $last_update = $course['LastUpdateDate'] ?? '1970-01-01';

    if ($existing) {
        $current_update = get_post_meta($existing, 'epic_last_update', true);
        if ($last_update > $current_update) {
            $result = wp_update_post([
                'ID' => $existing,
                'post_title' => $course_title,
                'post_content' => $course_description,
            ]);
            if ($result) {
                update_post_meta($existing, 'epic_last_update', $last_update);
                epic_log_error("Course updated: {$course['EpicCourseID']}.");
            } else {
                epic_log_error("Failed to update course: {$course['EpicCourseID']}.");
            }
        }
    } else {
        $post_id = wp_insert_post([
            'post_type' => 'lp_course',
            'post_status' => 'draft',
            'post_title' => $course_title,
            'post_content' => $course_description,
        ]);
        if ($post_id) {
            add_post_meta($post_id, 'epic_course_id', $course['EpicCourseID']);
            add_post_meta($post_id, 'epic_last_update', $last_update);
            epic_log_error("Course created: {$course['EpicCourseID']}.");
        } else {
            epic_log_error("Failed to create course: {$course['EpicCourseID']}.");
        }
    }
}

// Log Errors
function epic_log_error($message) {
    $log_file = WP_CONTENT_DIR . '/uploads/epic_sync_debug.log';
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $log_file);
}

// AJAX handler for deleting courses.
add_action('wp_ajax_epic_delete_courses', function() {
    check_ajax_referer('epic_sync_nonce', 'nonce');
    global $wpdb;

    $batch_size = 50;
    $page = $_POST['page'] ?? 1;

    // Fetch courses in batches
    $courses = get_posts([
        'post_type'   => 'lp_course',
        'fields'      => 'ids',
        'numberposts' => $batch_size,
        'offset'      => ($page - 1) * $batch_size,
    ]);

    if (empty($courses)) {
        // Clean up LearnPress-related tables
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_courses");
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_sections");
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_section_items");
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_user_items");
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_user_itemmeta");
        $wpdb->query("DELETE FROM {$wpdb->prefix}learnpress_user_item_results");

        // Clean orphaned data from WordPress core tables
        $wpdb->query("DELETE FROM {$wpdb->prefix}postmeta WHERE post_id NOT IN (SELECT ID FROM {$wpdb->prefix}posts)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id NOT IN (SELECT ID FROM {$wpdb->prefix}posts)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type = 'lp_course'");

        // Clean unused terms and taxonomies
        $wpdb->query("DELETE FROM {$wpdb->prefix}terms WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->prefix}term_taxonomy)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}term_taxonomy WHERE count = 0");

        // Remove residual meta data
        $wpdb->query("DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE 'epic_course_%'");

        wp_send_json_success(['message' => 'Deletion complete!', 'progress' => 100, 'done' => true]);
    }

    foreach ($courses as $course_id) {
        // Delete course and associated data
        wp_delete_post($course_id, true);
    }

    // Progress calculation
    $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'lp_course'");
    $total = $remaining + count($courses);
    $progress = min(100, round((($total - $remaining) / $total) * 100));

    wp_send_json_success([
        'message'   => $progress == 100 ? 'Deletion complete!' : 'Deleting...',
        'progress'  => $progress,
        'done'      => $progress == 100,
        'next_page' => $page + 1,
    ]);
});
