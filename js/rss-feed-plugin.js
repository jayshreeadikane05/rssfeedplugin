jQuery(document).ready(function($) {
    $('#fetch-recent-posts').on('click', function() {
        $('#fetch-status').text('Fetching recent posts...');

        $.ajax({
            url: rssFeedPlugin.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_recent_rss_posts'
            },
            success: function(response) {
                if (response.success) {
                    $('#fetch-status').text(rssFeedPlugin.fetch_success);
                    $('#recent-posts-list').empty();
                    response.data.forEach(function(post) {
                        $('#recent-posts-list').append(
                            `<div class="rss-post">
                                <h3><a href="${post.link}" target="_blank">${post.title}</a></h3>
                                <p>${post.description}</p>
                            </div>`
                        );
                    });
                } else {
                    $('#fetch-status').text(rssFeedPlugin.fetch_error);
                }
            },
            error: function() {
                $('#fetch-status').text(rssFeedPlugin.fetch_error);
            }
        });
    });
});
