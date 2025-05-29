# Epic Learning Sync for LearnPress

![Epic Learning Sync Logo](/assets/images/epic-learning-sync-logo.png)

[![WordPress version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--3.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

## 🔍 Overview

Epic Learning Sync is a WordPress plugin that seamlessly synchronizes LearnPress courses with data from the Epic Learning Network API. It provides a robust, secure, and user-friendly way to manage course content while ensuring data integrity and performance.

## 🚀 Features

- **Automated Course Synchronization**: Import and update courses from Epic Learning Network API
- **Selective Sync**: Choose which courses to synchronize
- **Asynchronous Processing**: Perform sync operations without blocking the WordPress admin interface
- **Backup & Restore**: Create automatic backups before sync/delete operations with easy restore functionality
- **Secure API Integration**: Safely connect to Epic Learning Network with proper authentication
- **Detailed Logging**: Comprehensive error logging with rotation for troubleshooting
- **Clean Uninstallation**: Properly remove all plugin data when uninstalled
- **Developer-Friendly**: Extendable with hooks and filters

## 📦 Installation

### Manual Installation

1. Download the plugin zip file
2. Navigate to WordPress Admin Dashboard → Plugins → Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now"
5. After installation completes, click "Activate Plugin"

### Via WP-CLI

```bash
wp plugin install epic-learning-sync.zip --activate
```

## ⚙️ Configuration

1. Navigate to Settings → Epic Learning Sync in your WordPress admin dashboard
2. Enter your Epic Learning Network API credentials:
   - API Application ID
   - API Key
3. Save your settings
4. You're ready to start syncing courses!

## 🧩 Usage

### Synchronizing Courses

1. Go to Settings → Epic Learning Sync
2. Click the "Start Sync" button
3. The plugin will fetch course data from the Epic Learning Network API and create or update LearnPress courses accordingly
4. A progress bar will show the sync status
5. You can continue using WordPress while the sync runs in the background

### Deleting Courses

1. Go to Settings → Epic Learning Sync
2. Click the "Delete All Courses" button
3. Confirm the deletion when prompted
4. The plugin will remove all LearnPress courses and related data
5. A progress bar will show the deletion status

### Managing Backups

1. Go to Settings → Epic Learning Sync
2. Scroll down to the "Backup Management" section
3. View available backups with timestamps
4. Click "Restore" on any backup to revert to that state
5. Confirm the restoration when prompted

## 🪝 Hooks & Filters

Epic Learning Sync provides several hooks and filters for developers to extend its functionality:

### Actions

```php
/**
 * Fires before course sync begins
 */
do_action( 'epic_learning_sync_before_sync' );

/**
 * Fires after course sync completes
 * 
 * @param int $total_processed Number of courses processed
 */
do_action( 'epic_learning_sync_after_sync', $total_processed );

/**
 * Fires before course deletion begins
 */
do_action( 'epic_learning_sync_before_delete' );

/**
 * Fires after course deletion completes
 */
do_action( 'epic_learning_sync_after_delete' );

/**
 * Fires when storing additional course data
 * 
 * @param int   $post_id        The course post ID
 * @param array $additional_data Additional course data from API
 */
do_action( 'epic_learning_sync_store_course_data', $post_id, $additional_data );
```

### Filters

```php
/**
 * Filter the batch size for course processing
 * 
 * @param int $batch_size Default batch size (10)
 * @return int Modified batch size
 */
$batch_size = apply_filters( 'epic_learning_sync_batch_size', 10 );

/**
 * Filter the batch size for course deletion
 * 
 * @param int $batch_size Default batch size (50)
 * @return int Modified batch size
 */
$batch_size = apply_filters( 'epic_learning_sync_delete_batch_size', 50 );

/**
 * Filter the course data before processing
 * 
 * @param array $course Course data from API
 * @return array Modified course data
 */
$course = apply_filters( 'epic_learning_sync_course_data', $course );

/**
 * Filter the post data before creating/updating a course
 * 
 * @param array $post_data WordPress post data
 * @param array $course    Course data from API
 * @return array Modified post data
 */
$post_data = apply_filters( 'epic_learning_sync_post_data', $post_data, $course );
```

## 🧰 Development

### Requirements

- PHP 7.4 or higher
- WordPress 6.0 or higher
- LearnPress plugin installed and activated

### File Structure

```
epic-learning-sync/
├── admin/
│   └── partials/
│       └── epic-learning-sync-admin-display.php
├── assets/
│   ├── css/
│   │   └── epic-learning-sync-admin.css
│   ├── js/
│   │   └── epic-learning-sync-admin.js
│   └── images/
│       └── epic-learning-sync-logo.png
├── includes/
│   ├── class-epic-learning-sync.php
│   ├── class-epic-learning-sync-admin.php
│   ├── class-epic-learning-sync-api.php
│   ├── class-epic-learning-sync-course-handler.php
│   ├── class-epic-learning-sync-logger.php
│   └── class-epic-learning-sync-uninstaller.php
├── languages/
│   └── epic-learning-sync.pot
├── epic-learning-sync.php
├── index.php
├── LICENSE
├── README.md
└── uninstall.php
```

### Coding Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) for PHP, HTML, CSS, and JavaScript.

## 📃 License

Epic Learning Sync is licensed under the GPL v3.

## 🙋 Support

For support, please contact [ThinkRED Technologies](https://thinkred.tech).

## 📊 Changelog

### 2.1.0

- Major refactor for improved security, performance, and code structure
- Added backup and restore functionality
- Improved error handling and logging
- Enhanced UI with better progress indicators
- Added developer hooks and filters
- Added internationalization support

### 1.0.0

- Initial release

## ✍️ Credits

This plugin was developed by [ThinkRED Technologies](https://thinkred.tech).
