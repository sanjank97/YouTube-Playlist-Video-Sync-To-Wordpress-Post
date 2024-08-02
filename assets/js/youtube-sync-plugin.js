jQuery(document).ready(function($) {


    var pageTokens = {1: ''}; // Initialize with the first page token being an empty string
    var currentPage = 1;

    function fetchVideos(pageToken = '', page = 1) {
        showLoader();
        $.post(youtubeSyncPlugin.ajax_url, {
            action: 'youtube_sync_plugin_fetch_videos',
            page_token: pageToken
        }, function(response) {
            if (response.success) {
                var videos = response.data.videos;
                var nextPageToken = response.data.nextPageToken;
                var totalResults = response.data.totalResults;
                var resultsPerPage = response.data.resultsPerPage;
                var totalPages = Math.ceil(totalResults / resultsPerPage);

                // Store the page tokens
                if (pageToken && !pageTokens[page]) {
                    pageTokens[page] = pageToken; // Store the current page token
                }
                if (nextPageToken) {
                    pageTokens[page + 1] = nextPageToken; // Store the next page token
                }

                var startRecord = (page - 1) * resultsPerPage + 1;
                var endRecord = Math.min(startRecord + resultsPerPage - 1, totalResults);

                var html = '<form method="post" action="">';
                html += '<input type="submit" id="import-selected-videos" class="button button-primary" value="Import Selected Videos" />';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th><input type="checkbox" id="select-all" /></th><th>Video ID</th><th>Title</th><th>Description</th><th>Thumbnails</th><th>Channel</th><th>Published At</th><th>Status</th></tr></thead><tbody>';

                for (var i = 0; i < videos.length; i++) {
                    var video = videos[i];
                    html += '<tr>';
                    html += '<td><input type="checkbox" class="video-checkbox" ' +
                        'data-video-id="' + video.video_id + '" ' +
                        'data-video-title="' + video.title + '" ' +
                        'data-video-thumbnail="' + video.thumbnail + '" ' +
                        'data-video-description="' + video.description + '" ' +
                        'data-video-playlistid="' + video.playlistId + '" ' +
                        'data-video-publishedat="' + video.publishedAt + '" /></td>';
                    html += '<td>' + video.video_id + '</td>';
                    html += '<td>' + video.title + '</td>';
                    html += '<td>' + truncateDescription(video.description, 100) + '</td>';
                    html += '<td><img src="' + video.thumbnail + '" width="100" /></td>';
                    html += '<td><span class="badge badge--info badge--smaller">' + video.channel + '</span></td>';
                    html += '<td>' + timeAgo(video.publishedAt) + '</td>';
                    html += '<td> <span class="badge ' + (video.status === 'Imported' ? 'badge--success' : 'badge--danger') + ' badge--smaller">' + video.status + '</span></td>';
                    html += '</tr>';
                }

                html += '</tbody></table></form>';
                html += '<div class="pagination-info">Showing ' + startRecord + ' to ' + endRecord + ' of ' + totalResults + ' (Page ' + page + ' of ' + totalPages + ')</div>';

                html += '<div class="pagination">';
                if (page > 1) {
                    html += '<a href="#" class="page-numbers" data-page-token="' + pageTokens[1] + '" data-page="1">First</a>';
                    html += '<a href="#" class="page-numbers" data-page-token="' + pageTokens[page - 1] + '" data-page="' + (page - 1) + '">Previous</a>';
                }

                var startPage = Math.max(1, page - 2);
                var endPage = Math.min(totalPages, page + 2);

                for (var p = startPage; p <= endPage; p++) {
                    var pageTokenAttr = pageTokens[p] ? pageTokens[p] : '';
                    html += '<a href="#" class="page-numbers ' + (page === p ? 'current' : '') + '" data-page-token="' + pageTokenAttr + '" data-page="' + p + '">' + p + '</a>';
                }

                if (page < totalPages) {
                    html += '<a href="#" class="page-numbers" data-page-token="' + nextPageToken + '" data-page="' + (page + 1) + '">Next</a>';
                }

                html += '</div>';

                $('#video-list').html(html);
                hideLoader();
            } else {
                $('#video-list').html('<div class="error"><p>' + response.data + '</p></div>');
                hideLoader();
            }
        });
    }

    fetchVideos();

    $(document).on('click', '.page-numbers', function(e) {
        e.preventDefault();
        var pageToken = $(this).data('page-token');
        var page = parseInt($(this).data('page'));

        currentPage = page;
        fetchVideos(pageToken, page);
    });

    $(document).on('click', '#select-all', function() {
        $('.video-checkbox').prop('checked', this.checked);
    });

    $(document).on('click', '#import-selected-videos', function(e) {
        e.preventDefault();

        var selectedVideos = {};
        $('.video-checkbox:checked').each(function() {
            var videoId = $(this).data('video-id');
            selectedVideos[videoId] = {
                video_id: videoId,
                title: $(this).data('video-title'),
                thumbnail: $(this).data('video-thumbnail'),
                description: $(this).data('video-description'),
                playlistId: $(this).data('video-playlistid'),
                publishedAt: $(this).data('video-publishedat')
            };
        });

        showLoader();

        $.post(youtubeSyncPlugin.ajax_url, {
            action: 'youtube_sync_plugin_import_videos',
            video_ids: Object.keys(selectedVideos),
            videos: selectedVideos
        }, function(response) {
            if (response.success) {

                fetchVideos();
                hideLoader();

                Swal.fire({
                    title: 'Import Successful',
                    text: 'The selected rows have been imported successfully.',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 2500,
                   
                });
                
            } else {

                Swal.fire({
                    title: 'No Rows Selected',
                    text: 'Please select at least one row to proceed.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'my-custom-confirm-button' // Apply custom class to confirm button
                    }
                });
                hideLoader();
            }
        });
    });

    function showLoader() {
      
        $('body').append('<div id="loader-overlay"><div class="loader"></div></div>');
    }

    function hideLoader() {
        $('#loader-overlay').remove();
    }

    // Function to truncate the description
    function truncateDescription(description, maxLength) {
        if (description.length > maxLength) {
            return description.substring(0, maxLength) + '...';
        }
        return description;
    }

    // Function to format time ago
    function timeAgo(dateString) {
        const now = new Date();
        const publishedDate = new Date(dateString);
        const seconds = Math.floor((now - publishedDate) / 1000);

        let interval = Math.floor(seconds / 31536000); // Years
        if (interval >= 1) {
            return interval + " year" + (interval > 1 ? "s" : "") + " ago";
        }
        interval = Math.floor(seconds / 2592000); // Months
        if (interval >= 1) {
            return interval + " month" + (interval > 1 ? "s" : "") + " ago";
        }
        interval = Math.floor(seconds / 86400); // Days
        if (interval >= 1) {
            return interval + " day" + (interval > 1 ? "s" : "") + " ago";
        }
        interval = Math.floor(seconds / 3600); // Hours
        if (interval >= 1) {
            return interval + " hour" + (interval > 1 ? "s" : "") + " ago";
        }
        interval = Math.floor(seconds / 60); // Minutes
        if (interval >= 1) {
            return interval + " minute" + (interval > 1 ? "s" : "") + " ago";
        }
        return Math.floor(seconds) + " second" + (seconds > 1 ? "s" : "") + " ago";
    }
});



 