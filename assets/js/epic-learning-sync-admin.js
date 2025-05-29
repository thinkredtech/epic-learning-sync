/**
 * Epic Learning Sync Admin JavaScript
 *
 * Handles all admin interactions for the Epic Learning Sync plugin.
 *
 * @package     Epic_Learning_Sync
 * @author      ThinkRED Technologies
 * @copyright   2025 ThinkRED Technologies
 * @license     GPL-3.0
 */

jQuery(document).ready(function ($) {
    // Variables to track sync and delete operations
    let isSyncing = false;
    let isDeleting = false;
    let syncCancelled = false;
    let deleteCancelled = false;

    /**
     * Course Sync Functionality
     */
    $('#start-sync').on('click', function () {
        if (isSyncing) return;
        
        // Confirm before starting sync
        if (!confirm(EpicSync.confirmSync)) {
            return;
        }
        
        isSyncing = true;
        syncCancelled = false;
        
        // Update UI
        $('#sync-status .epic-sync-status-value').text(EpicSync.fetchingData);
        $('#progress-bar-fill').css('width', '0%');
        $('#sync-progress-percent').text('0%');
        $('#start-sync').prop('disabled', true);
        $('#cancel-sync').show();
        
        // Start the sync process
        startSync();
    });
    
    // Cancel sync operation
    $('#cancel-sync').on('click', function() {
        if (confirm(EpicSync.confirmCancel)) {
            syncCancelled = true;
            $('#sync-status .epic-sync-status-value').text(EpicSync.cancelling);
        }
    });

    /**
     * Start the sync process by fetching data from API
     */
    function startSync() {
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_sync_courses',
                nonce: EpicSync.nonce,
            },
            success: function (response) {
                if (syncCancelled) {
                    resetSyncUI(EpicSync.cancelled);
                    return;
                }
                
                if (response.success) {
                    const { total } = response.data;
                    $('#sync-status .epic-sync-status-value').text(EpicSync.dataFetched);
                    $('#sync-status').append(` <span class="epic-sync-count">(0/${total})</span>`);

                    // Proceed to chunked processing
                    processChunks(1, total);
                } else {
                    resetSyncUI(response.data.message || EpicSync.errorFetching);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                resetSyncUI(EpicSync.errorFetching);
            },
        });
    }

    /**
     * Process courses in chunks
     * 
     * @param {number} page - Current page number
     * @param {number} total - Total number of courses
     * @param {number} retries - Number of retries left
     */
    function processChunks(page, total, retries = 3) {
        if (syncCancelled) {
            resetSyncUI(EpicSync.cancelled);
            return;
        }
        
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_sync_courses_continue',
                nonce: EpicSync.nonce,
                page: page,
            },
            success: function (response) {
                if (syncCancelled) {
                    resetSyncUI(EpicSync.cancelled);
                    return;
                }
                
                if (response.success) {
                    const { progress, message, done, next_page, processed } = response.data;
                    const completed = Math.round((progress / 100) * total);

                    // Update UI
                    $('#sync-status .epic-sync-status-value').text(message);
                    $('#progress-bar-fill').css('width', `${progress}%`);
                    $('#sync-progress-percent').text(`${progress}%`);
                    $('.epic-sync-count').text(`(${completed}/${total})`);

                    if (!done) {
                        // Continue processing next chunk
                        processChunks(next_page, total);
                    } else {
                        // Sync complete
                        resetSyncUI(EpicSync.syncComplete);
                        
                        // Refresh backup list if available
                        if (typeof refreshBackupList === 'function') {
                            refreshBackupList();
                        }
                    }
                } else {
                    resetSyncUI(response.data.message || EpicSync.errorProcessing);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                
                if (retries > 0) {
                    console.warn(`Retrying chunk... (${3 - retries + 1})`);
                    // Wait 2 seconds before retrying
                    setTimeout(function() {
                        processChunks(page, total, retries - 1);
                    }, 2000);
                } else {
                    resetSyncUI(EpicSync.errorProcessing);
                }
            },
        });
    }

    /**
     * Reset sync UI elements
     * 
     * @param {string} statusMessage - Status message to display
     */
    function resetSyncUI(statusMessage) {
        isSyncing = false;
        $('#sync-status .epic-sync-status-value').text(statusMessage);
        $('#start-sync').prop('disabled', false);
        $('#cancel-sync').hide();
        $('.epic-sync-count').remove();
    }

    /**
     * Course Deletion Functionality
     */
    $('#start-delete').on('click', function () {
        if (isDeleting) return;
        
        // Confirm before starting deletion
        if (!confirm(EpicSync.confirmDelete)) {
            return;
        }
        
        isDeleting = true;
        deleteCancelled = false;
        
        // Update UI
        $('#delete-status .epic-sync-status-value').text(EpicSync.deleting);
        $('#delete-progress-bar-fill').css('width', '0%');
        $('#delete-progress-percent').text('0%');
        $('#start-delete').prop('disabled', true);
        $('#cancel-delete').show();
        
        // Start the deletion process
        deleteCourses(1);
    });
    
    // Cancel delete operation
    $('#cancel-delete').on('click', function() {
        if (confirm(EpicSync.confirmCancel)) {
            deleteCancelled = true;
            $('#delete-status .epic-sync-status-value').text(EpicSync.cancelling);
        }
    });

    /**
     * Delete courses in batches
     * 
     * @param {number} page - Current page number
     */
    function deleteCourses(page) {
        if (deleteCancelled) {
            resetDeleteUI(EpicSync.cancelled);
            return;
        }
        
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_delete_courses',
                nonce: EpicSync.nonce,
                page: page,
            },
            success: function (response) {
                if (deleteCancelled) {
                    resetDeleteUI(EpicSync.cancelled);
                    return;
                }
                
                if (response.success) {
                    const { progress, message, done, next_page } = response.data;
                    
                    // Update UI
                    $('#delete-status .epic-sync-status-value').text(message);
                    $('#delete-progress-bar-fill').css('width', `${progress}%`);
                    $('#delete-progress-percent').text(`${progress}%`);
                    
                    if (!done) {
                        // Continue deleting next batch
                        deleteCourses(next_page);
                    } else {
                        // Deletion complete
                        resetDeleteUI(EpicSync.deleteComplete);
                        
                        // Refresh backup list if available
                        if (typeof refreshBackupList === 'function') {
                            refreshBackupList();
                        }
                    }
                } else {
                    resetDeleteUI(response.data.message || EpicSync.errorDeleting);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                resetDeleteUI(EpicSync.errorDeleting);
            },
        });
    }

    /**
     * Reset delete UI elements
     * 
     * @param {string} statusMessage - Status message to display
     */
    function resetDeleteUI(statusMessage) {
        isDeleting = false;
        $('#delete-status .epic-sync-status-value').text(statusMessage);
        $('#start-delete').prop('disabled', false);
        $('#cancel-delete').hide();
    }

    /**
     * Backup Management Functionality
     */
    // Initialize backup list on page load
    if ($('#backup-list-container').length) {
        refreshBackupList();
    }
    
    // Refresh backup list button
    $('#refresh-backups').on('click', function() {
        refreshBackupList();
    });
    
    /**
     * Refresh the list of available backups
     */
    function refreshBackupList() {
        $('#backup-list-container').html('<p class="epic-sync-loading">' + EpicSync.loadingBackups + '</p>');
        
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_get_backups',
                nonce: EpicSync.nonce,
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.backups.length === 0) {
                        $('#backup-list-container').html('<p>' + EpicSync.noBackups + '</p>');
                        return;
                    }
                    
                    let html = '<div class="epic-sync-backup-items">';
                    
                    response.data.backups.forEach(function(backup) {
                        const typeClass = backup.type === 'sync' ? 'epic-sync-backup-type-sync' : 'epic-sync-backup-type-delete';
                        const typeLabel = backup.type === 'sync' ? EpicSync.syncBackup : EpicSync.deleteBackup;
                        
                        html += `
                            <div class="epic-sync-backup-item">
                                <div class="epic-sync-backup-info">
                                    <span class="epic-sync-backup-type ${typeClass}">${typeLabel}</span>
                                    <span class="epic-sync-backup-date">${backup.date}</span>
                                </div>
                                <div class="epic-sync-backup-actions">
                                    <button class="button restore-backup" data-backup="${backup.file}">${EpicSync.restore}</button>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    $('#backup-list-container').html(html);
                    
                    // Attach event handlers to restore buttons
                    $('.restore-backup').on('click', function() {
                        const backupFile = $(this).data('backup');
                        restoreBackup(backupFile);
                    });
                } else {
                    $('#backup-list-container').html('<p class="epic-sync-error">' + (response.data.message || EpicSync.errorLoadingBackups) + '</p>');
                }
            },
            error: function() {
                $('#backup-list-container').html('<p class="epic-sync-error">' + EpicSync.errorLoadingBackups + '</p>');
            }
        });
    }
    
    /**
     * Restore from a backup file
     * 
     * @param {string} backupFile - Path to the backup file
     */
    function restoreBackup(backupFile) {
        if (!confirm(EpicSync.confirmRestore)) {
            return;
        }
        
        $('#backup-list-container').html('<p class="epic-sync-loading">' + EpicSync.restoring + '</p>');
        
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_restore_backup',
                nonce: EpicSync.nonce,
                backup_file: backupFile,
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    refreshBackupList();
                } else {
                    alert(response.data.message || EpicSync.errorRestoring);
                    refreshBackupList();
                }
            },
            error: function() {
                alert(EpicSync.errorRestoring);
                refreshBackupList();
            }
        });
    }
});
