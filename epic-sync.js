jQuery(document).ready(function ($) {
    let isSyncing = false;
    let isDeleting = false;

    // Sync Courses
    $('#start-sync').on('click', function () {
        if (isSyncing) return;
        isSyncing = true;

        $('#sync-status').text('Status: Fetching data...');
        $('#progress-bar-fill').css('width', '0%');

        // Start the sync process
        startSync();
    });

    function startSync() {
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_sync_courses',
                nonce: EpicSync.nonce,
            },
            success: function (response) {
                if (response.success) {
                    const { total } = response.data;
                    $('#sync-status').text('Status: Data fetched successfully.');
                    $('#sync-status').append(` (0/${total})`);

                    // Proceed to chunked processing
                    processChunks(1, total);
                } else {
                    $('#sync-status').text(`Error: ${response.data.message}`);
                    isSyncing = false;
                }
            },
            error: function () {
                $('#sync-status').text('Error: Unable to fetch data. Please try again.');
                isSyncing = false;
            },
        });
    }

    function processChunks(page, total, retries = 3) {
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_sync_courses_continue',
                nonce: EpicSync.nonce,
                page: page,
            },
            success: function (response) {
                if (response.success) {
                    const { progress, message, done, next_page } = response.data;
                    const completed = Math.round((progress / 100) * total);

                    $('#sync-status').text(`Status: ${message}`);
                    $('#progress-bar-fill').css('width', `${progress}%`);
                    $('#sync-status').append(` (${completed}/${total})`);

                    if (!done) {
                        processChunks(next_page, total);
                    } else {
                        $('#sync-status').text('Status: Sync complete!');
                        isSyncing = false;
                    }
                } else {
                    $('#sync-status').text(`Error: ${response.data.message}`);
                    isSyncing = false;
                }
            },
            error: function () {
                if (retries > 0) {
                    console.warn(`Retrying chunk... (${3 - retries + 1})`);
                    processChunks(page, total, retries - 1);
                } else {
                    $('#sync-status').text('Error: Unable to process data. Please try again.');
                    isSyncing = false;
                }
            },
        });
    }
    
    // Delete All Courses
    $('#start-delete').on('click', function () {
        if (isDeleting) return;
        isDeleting = true;

        $('#delete-status').text('Status: Deleting...');
        deleteCourses(1);
    });

    function deleteCourses(page) {
        $.ajax({
            url: EpicSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'epic_delete_courses',
                nonce: EpicSync.nonce,
                page: page,
            },
            success: function (response) {
                if (response.success) {
                    const { progress, message, done, next_page } = response.data;
                    $('#delete-status').text(`Status: ${message}`);
                    $('#delete-progress-bar-fill').css('width', `${progress}%`);
                    if (!done) {
                        deleteCourses(next_page);
                    } else {
                        $('#delete-status').text('Status: Deletion complete!');
                        isDeleting = false;
                    }
                } else {
                    $('#delete-status').text('Status: Error during deletion.');
                    isDeleting = false;
                }
            },
            error: function () {
                $('#delete-status').text('Status: AJAX error.');
                isDeleting = false;
            },
        });
    }
});
