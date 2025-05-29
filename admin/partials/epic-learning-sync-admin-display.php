<?php
/**
 * Admin display template for Epic Learning Sync
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
?>

<div class="wrap epic-learning-sync-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('epic_sync_settings'); ?>

    <div class="epic-sync-admin-container">
        <!-- API Settings Section -->
        <div class="epic-sync-section epic-sync-settings-section">
            <h2><?php esc_html_e('API Settings', 'epic-learning-sync'); ?></h2>
            <p><?php esc_html_e('Configure your Epic Learning API credentials.', 'epic-learning-sync'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('epic_sync_settings_save', 'epic_sync_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label
                                for="epic_sync_id"><?php esc_html_e('API Application ID', 'epic-learning-sync'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="epic_sync_id" name="epic_sync_id"
                                value="<?php echo esc_attr($credentials['id']); ?>" class="regular-text" required />
                            <p class="description">
                                <?php esc_html_e('Enter your Epic Learning API Application ID.', 'epic-learning-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="epic_sync_key"><?php esc_html_e('API Key', 'epic-learning-sync'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="epic_sync_key" name="epic_sync_key"
                                value="<?php echo esc_attr($credentials['key']); ?>" class="regular-text" required />
                            <p class="description">
                                <?php esc_html_e('Enter your Epic Learning API Key.', 'epic-learning-sync'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="epic_sync_save" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'epic-learning-sync'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Course Sync Section -->
        <div class="epic-sync-section epic-sync-operation-section">
            <h2><?php esc_html_e('Course Sync', 'epic-learning-sync'); ?></h2>
            <p><?php esc_html_e('Synchronize LearnPress courses with Epic Learning Network.', 'epic-learning-sync'); ?>
            </p>

            <div id="sync-progress-section">
                <div class="epic-sync-status-container">
                    <p id="sync-status" class="epic-sync-status">
                        <?php esc_html_e('Status:', 'epic-learning-sync'); ?>
                        <span class="epic-sync-status-value"><?php esc_html_e('Idle', 'epic-learning-sync'); ?></span>
                    </p>
                    <div class="epic-sync-progress">
                        <div class="epic-sync-progress-bar">
                            <div id="progress-bar-fill" class="epic-sync-progress-bar-fill"></div>
                        </div>
                        <div class="epic-sync-progress-text">
                            <span id="sync-progress-percent">0%</span>
                        </div>
                    </div>
                </div>

                <div class="epic-sync-actions">
                    <button id="start-sync" class="button button-primary epic-sync-button">
                        <?php esc_html_e('Start Sync', 'epic-learning-sync'); ?>
                    </button>
                    <button id="cancel-sync" class="button epic-sync-button" style="display: none;">
                        <?php esc_html_e('Cancel', 'epic-learning-sync'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Course Deletion Section -->
        <div class="epic-sync-section epic-sync-operation-section">
            <h2><?php esc_html_e('Course Deletion', 'epic-learning-sync'); ?></h2>
            <p><?php esc_html_e('Delete all LearnPress courses.', 'epic-learning-sync'); ?></p>

            <div id="delete-progress-section">
                <div class="epic-sync-status-container">
                    <p id="delete-status" class="epic-sync-status">
                        <?php esc_html_e('Status:', 'epic-learning-sync'); ?>
                        <span class="epic-sync-status-value"><?php esc_html_e('Idle', 'epic-learning-sync'); ?></span>
                    </p>
                    <div class="epic-sync-progress">
                        <div class="epic-sync-progress-bar epic-sync-progress-bar-delete">
                            <div id="delete-progress-bar-fill"
                                class="epic-sync-progress-bar-fill epic-sync-progress-bar-fill-delete"></div>
                        </div>
                        <div class="epic-sync-progress-text">
                            <span id="delete-progress-percent">0%</span>
                        </div>
                    </div>
                </div>

                <div class="epic-sync-actions">
                    <button id="start-delete" class="button button-primary epic-sync-button epic-sync-button-delete">
                        <?php esc_html_e('Delete All Courses', 'epic-learning-sync'); ?>
                    </button>
                    <button id="cancel-delete" class="button epic-sync-button" style="display: none;">
                        <?php esc_html_e('Cancel', 'epic-learning-sync'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Backup Management Section -->
        <div class="epic-sync-section epic-sync-backup-section">
            <h2><?php esc_html_e('Backup Management', 'epic-learning-sync'); ?></h2>
            <p><?php esc_html_e('Restore courses from a previous backup.', 'epic-learning-sync'); ?></p>

            <div id="backup-management-section">
                <div class="epic-sync-backup-list">
                    <h3><?php esc_html_e('Available Backups', 'epic-learning-sync'); ?></h3>
                    <div id="backup-list-container">
                        <p class="epic-sync-loading"><?php esc_html_e('Loading backups...', 'epic-learning-sync'); ?>
                        </p>
                    </div>
                </div>

                <div class="epic-sync-backup-actions">
                    <button id="refresh-backups" class="button epic-sync-button">
                        <?php esc_html_e('Refresh Backups', 'epic-learning-sync'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>