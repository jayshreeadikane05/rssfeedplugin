<?php
/**
 * Plugin Name: RSS Feed Widget Plugin
 * Description: A simple WordPress plugin that displays RSS feed content in a widget and creates WordPress posts from RSS feed items.
 * Version: 1.1
 * Author: Jayshree Adikane
 * Text Domain: rss-feed-plugin
 */

// Create settings menu in the dashboard
function rss_feed_plugin_menu() {
    add_menu_page(
        'RSS Feed Settings',   
        'RSS Feed Settings',   
        'manage_options',       
        'rss_feed_settings',   
        'rss_feed_settings_page',
        'dashicons-rss',       
        20                    
    );
}

add_action('admin_menu', 'rss_feed_plugin_menu');

// Display the Settings Page
function rss_feed_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('RSS Feed Settings', 'rss-feed-plugin'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('rss_feed_settings_group');
            do_settings_sections('rss_feed_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('RSS Feed URLs', 'rss-feed-plugin'); ?></th>
                    <td>
                        <textarea name="rss_feed_urls" rows="10" cols="50"><?php echo esc_textarea(get_option('rss_feed_urls')); ?></textarea>
                        <p class="description"><?php esc_html_e('Enter each RSS feed URL on a new line.', 'rss-feed-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <!-- Button to fetch recent posts -->
        <button id="fetch-recent-posts" class="button button-secondary"><?php esc_html_e('Fetch Recent Posts', 'rss-feed-plugin'); ?></button>
        <div id="fetch-status"></div>
        <div id="recent-posts-list"></div>
    </div>
    <?php
}

// Register the RSS Feed URL setting
function rss_feed_plugin_register_settings() {
    register_setting('rss_feed_settings_group', 'rss_feed_urls');
}

add_action('admin_init', 'rss_feed_plugin_register_settings');

function create_posts_from_rss_feed() {
    $rss_urls = get_option('rss_feed_urls');
    if ($rss_urls) {
        $rss_urls = explode("\n", $rss_urls); 
    } else {
        $rss_urls = []; // Default to an empty array if no URLs are saved
    }

    // Loop through each RSS feed URL and fetch items
    foreach ($rss_urls as $rss_url) {
        $rss_url = trim($rss_url); // Clean up any extra spaces around the URL
        $rss = fetch_feed($rss_url); // Fetch the RSS feed

        if (!is_wp_error($rss)) {
            $max_items = $rss->get_item_quantity(5); // Limit to 5 items
            $rss_items = $rss->get_items(0, $max_items);

            foreach ($rss_items as $item) {
                $existing_post = get_posts(array(
                    'meta_key'   => 'rss_feed_url',
                    'meta_value' => esc_url($item->get_permalink()), // Check for the feed URL
                    'posts_per_page' => 1, // Only need one post
                    'post_type'  => 'post',
                    'post_status'=> 'any', // Check for posts of any status
                ));

                // If the post exists, delete it
                if (!empty($existing_post)) {
                    wp_delete_post($existing_post[0]->ID, true); // True to permanently delete the post
                }

                // Prepare post data for a new post
                $post_content = $item->get_description();
                $post_content .= '<p>Source: <a href="' . esc_url($item->get_permalink()) . '" target="_blank">' . esc_html($item->get_permalink()) . '</a></p>';

                $post_data = array(
                    'post_title'   => wp_strip_all_tags($item->get_title()),  // Use the title of the RSS item
                    'post_content' => $post_content,  // Use the description of the RSS item, with source link added
                    'post_status'  => 'publish',  // Set post status to 'publish'
                    'post_author'  => 1,  // Set the author (ID 1 is the admin user)
                    'post_type'    => 'post',  // Set the post type as 'post'
                    'meta_input'   => array(
                        'rss_feed_url' => esc_url($item->get_permalink())  // Store the RSS feed URL as meta
                    ),
                    'tax_input'    => array(
                        'category' => array('news-and-community'),  // Default category
                        'post_tag' => array('rss-feed')        // Default tag
                    ),
                );

                // Insert the post into the database
                wp_insert_post($post_data);
                
            }
        }
    }
}
function fetch_recent_rss_posts_callback() {
    // Check permissions for security
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'rss-feed-plugin'));
        wp_die();
    }

    $rss_urls = get_option('rss_feed_urls');
    if ($rss_urls) {
        $rss_urls = explode("\n", $rss_urls);
    } else {
        $rss_urls = [];
    }

    $recent_posts = [];

    // Loop through each RSS feed URL
    foreach ($rss_urls as $rss_url) {
        $rss_url = trim($rss_url);
        $rss = fetch_feed($rss_url);

        if (!is_wp_error($rss)) {
            $max_items = $rss->get_item_quantity(5); // Fetch 5 recent items
            $rss_items = $rss->get_items(0, $max_items);

            foreach ($rss_items as $item) {
                $recent_posts[] = array(
                    'title' => esc_html($item->get_title()),
                    'link' => esc_url($item->get_permalink()),
                    'description' => $item->get_description()
                );
            }
        }
    }

    wp_send_json_success($recent_posts);
}

add_action('wp_ajax_fetch_recent_rss_posts', 'fetch_recent_rss_posts_callback');


function rss_feed_plugin_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_rss_feed_settings') {
        return;
    }

    wp_enqueue_script('rss-feed-plugin-script', plugin_dir_url(__FILE__) . 'js/rss-feed-plugin.js', array('jquery'), '1.0', true);

    wp_localize_script('rss-feed-plugin-script', 'rssFeedPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'fetch_success' => __('Recent posts fetched successfully.', 'rss-feed-plugin'),
        'fetch_error' => __('An error occurred while fetching posts.', 'rss-feed-plugin')
    ));
}
add_action('admin_enqueue_scripts', 'rss_feed_plugin_enqueue_scripts');

// Trigger post creation only when the settings are updated (avoid unnecessary triggers)
function rss_feed_plugin_on_settings_save($option) {
    // Ensure the post creation is triggered only when the 'rss_feed_urls' option is updated
    if ($option === 'rss_feed_urls') {
        create_posts_from_rss_feed();
    }
}

// Hook to run when the 'rss_feed_urls' option is updated
add_action('updated_option', 'rss_feed_plugin_on_settings_save', 10, 1);

// Ensure posts are created only when settings are saved, not on page reload
function trigger_post_creation_on_save() {
    // Check if the 'rss_feed_urls' option has been updated or saved for the first time
    if (isset($_POST['option_page']) && $_POST['option_page'] === 'rss_feed_settings_group') {
        $rss_urls = get_option('rss_feed_urls');
        if ($rss_urls) {
            create_posts_from_rss_feed();
        }
    }
}

// Trigger when settings are saved or updated in the admin area
add_action('admin_init', 'trigger_post_creation_on_save');