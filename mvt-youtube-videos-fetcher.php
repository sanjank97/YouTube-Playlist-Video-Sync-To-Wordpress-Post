<?php
/*
Plugin Name: YouTube Sync Plugin
Description: Sync YouTube videos and thumbnails to WordPress database.
Version: 1.0
Author: Myvirtualteams
Author URI: https://www.myvirtualteams.com/
*/

// Hook to add admin menu
add_action('admin_menu', 'youtube_sync_plugin_menu');
add_action('wp_ajax_youtube_sync_plugin_fetch_videos', 'youtube_sync_plugin_fetch_videos');
add_action('wp_ajax_youtube_sync_plugin_import_videos', 'youtube_sync_plugin_import_videos');

function youtube_sync_plugin_menu() {
    // Add top-level menu page
    add_menu_page(
        'YouTube List', // Page title
        'MVT YouTube Sync', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-list', // Menu slug
        'youtube_sync_plugin_list_page', // Function to display the list page
        '', // Icon URL
        6 // Position
    );

    // Add first submenu item (YouTube List)
    add_submenu_page(
        'youtube-sync-plugin-list', // Parent slug
        'YouTube List', // Page title
        'YouTube List', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-list', // Menu slug
        'youtube_sync_plugin_list_page' // Function to display the list page
    );

    // Add second submenu item (Settings)
    add_submenu_page(
        'youtube-sync-plugin-list', // Parent slug
        'Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'youtube-sync-plugin-settings', // Menu slug
        'youtube_sync_plugin_settings_page' // Function to display the settings page
    );
}

function youtube_sync_plugin_list_page() {
    ?>
    <div class="wrap">
        <h1>YouTube Video List</h1>
        <form method="post" action="">
            <input type="hidden" name="youtube_sync_manual_sync" value="1" />
            <input type="submit" class="button button-primary" value="Sync Videos" />
        </form>
       
        <div id="video-list"></div>
    </div>
   
    <?php
}

function youtube_sync_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>YouTube Sync Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('youtube_sync_plugin_options');
            do_settings_sections('youtube-sync-plugin');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'youtube_sync_plugin_settings_init');

function youtube_sync_plugin_settings_init() {
    register_setting('youtube_sync_plugin_options', 'youtube_sync_plugin_api_key');
    register_setting('youtube_sync_plugin_options', 'youtube_sync_plugin_playlist_id'); // Register new setting

    add_settings_section(
        'youtube_sync_plugin_section',
        'YouTube API Settings',
        null,
        'youtube-sync-plugin'
    );

    add_settings_field(
        'youtube_sync_plugin_api_key',
        'YouTube API Key',
        'youtube_sync_plugin_api_key_callback',
        'youtube-sync-plugin',
        'youtube_sync_plugin_section'
    );

    add_settings_field(
        'youtube_sync_plugin_playlist_id',
        'YouTube Playlist ID',
        'youtube_sync_plugin_playlist_id_callback', // New callback function
        'youtube-sync-plugin',
        'youtube_sync_plugin_section'
    );
}

function youtube_sync_plugin_api_key_callback() {
    $api_key = get_option('youtube_sync_plugin_api_key');
    ?>
    <input type="text" name="youtube_sync_plugin_api_key" value="<?php echo esc_attr($api_key); ?>" />
    <?php
}

function youtube_sync_plugin_playlist_id_callback() {
    $playlist_id = get_option('youtube_sync_plugin_playlist_id');
    ?>
    <input type="text" name="youtube_sync_plugin_playlist_id" value="<?php echo esc_attr($playlist_id); ?>" />
    <?php
}

function youtube_sync_plugin_fetch_videos() {


    $api_key = get_option('youtube_sync_plugin_api_key');
    $playlist_id = get_option('youtube_sync_plugin_playlist_id');
    $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : '';

    if (!$api_key || !$playlist_id) {
        wp_send_json_error('API key or Playlist ID not set');
    }

    $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId={$playlist_id}&maxResults=50&pageToken={$page_token}&key={$api_key}";
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Failed to fetch data from YouTube API');
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (!isset($data->items)) {
        wp_send_json_error('No videos found');
    }
  
    $videos = array();
  
    foreach ($data->items as $item) {
        if (isset($item->snippet->resourceId->videoId)) {
            $video_id = $item->snippet->resourceId->videoId;
            $title = $item->snippet->title;
            $description = $item->snippet->description;
            $thumbnail = $item->snippet->thumbnails->maxres->url;
            $channel = $item->snippet->channelTitle;
            $playlistId = $item->snippet->playlistId;
            $publishedAt = $item->snippet->publishedAt;
     
            

            // Check if a post with the same title already exists
            $existing_post = get_page_by_title(sanitize_text_field($title), OBJECT, 'inkish-video');
          
            // $selected_category = get_post_categories_by_title($title);

            $videos[] = array(
                'video_id' => $video_id,
                'title' => $title,
                'thumbnail' => $thumbnail,
                'description' =>$description,
                'channel' => $channel,
                'playlistId' =>  $playlistId ,
                'publishedAt' => $publishedAt,
                // 'categories' => $category_list ,
                // 'selected_category' => $selected_category,
                'status' =>  $existing_post ? 'Imported' : 'Not Imported'
            );
        }
    }

    wp_send_json_success(array(
        'videos' => $videos,
        'nextPageToken' => isset($data->nextPageToken) ? $data->nextPageToken : null,
        'totalResults' => $data->pageInfo->totalResults,
        'resultsPerPage' => $data->pageInfo->resultsPerPage,

    ));
}

function youtube_sync_plugin_import_videos() {

    $video_ids = isset($_POST['video_ids']) ? $_POST['video_ids'] : array();
    $videos = isset($_POST['videos']) ? $_POST['videos'] : array();

    if (empty($video_ids) || empty($videos)) {
        wp_send_json_error('No videos selected for import');
    }

    foreach ($video_ids as $video_id) {
        if (isset($videos[$video_id])) {
            $video = $videos[$video_id];

            $iso8601_date = $video['publishedAt']; // Assuming this is the ISO 8601 date
            $datetime = new DateTime($iso8601_date);
            $formatted_date = $datetime->format('Y-m-d H:i:s');

            // Check if a post with the same title already exists
            $existing_post = get_page_by_title(sanitize_text_field($video['title']), OBJECT, 'inkish-video');

            // Prepare the post data
            $post_data = array(
                'post_title'   => sanitize_text_field($video['title']),
                'post_content' => sanitize_text_field($video['description']),
                'post_status'  => 'publish',
                'post_type'    => 'inkish-video',
            );

            if ($existing_post) {
                // Update the existing post
                $post_data['ID'] = $existing_post->ID;
                $post_id = wp_update_post($post_data);

                // Update the custom fields
                update_post_meta($post_id, 'yvtwp_feed_key', sanitize_text_field($video['playlistId']));
                update_post_meta($post_id, 'yvtwp_video_key', sanitize_text_field($video['video_id']));
               
            } else {
                // Create a new post
                $post_id = wp_insert_post($post_data);

                // Add custom fields
                add_post_meta($post_id, 'yvtwp_feed_key', sanitize_text_field($video['playlistId']));
                add_post_meta($post_id, 'yvtwp_video_key', sanitize_text_field($video['video_id']));
            
            }


            // Handle the thumbnail
            $thumbnail_url = esc_url($video['thumbnail']);
            $thumbnail_id = media_sideload_image($thumbnail_url, $post_id, null, 'id');

            if (!is_wp_error($thumbnail_id)) {
                set_post_thumbnail($post_id, $thumbnail_id);
            }
            //Update video lang in the post
            set_the_video_lang_in_post($video['video_id'], $post_id);
        }
    }

    wp_send_json_success('Videos imported successfully');
}



// Enqueue scripts
add_action('admin_enqueue_scripts', 'youtube_sync_plugin_enqueue_scripts');

function youtube_sync_plugin_enqueue_scripts($hook) {
    if ('toplevel_page_youtube-sync-plugin-list' !== $hook) {
        return;
    }

    wp_enqueue_style( 'yt-style', plugin_dir_url(__FILE__)  . 'assets/css/style.css', array(), time(), 'all' );

    wp_enqueue_script('youtube-sync-plugin-script', plugin_dir_url(__FILE__) . 'assets/js/youtube-sync-plugin.js', array('jquery'), null, true);
    wp_enqueue_script('sweetalert2-script', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.12.3/dist/sweetalert2.all.min.js', array('jquery'), null, true);
    wp_enqueue_style('custom-sweetalert2-style', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.12.3/dist/sweetalert2.min.css');                

  

    wp_localize_script('youtube-sync-plugin-script', 'youtubeSyncPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));

   

}






function truncate_description($text, $chars = 100) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $truncated = substr($text, 0, $chars) . '...';
    return $truncated;
}


function set_the_video_lang_in_post($video_id, $post_id) {
    $api_key = get_option('youtube_sync_plugin_api_key');
    
    $post_data = get_post_meta($post_id, 'video_avail_lang', true);
    if (empty($post_data)) {
        $lang = '';
        $url_caption = "https://www.googleapis.com/youtube/v3/captions?videoId=" . $video_id . "&key=" . $api_key . "&part=snippet";
        $response = wp_remote_get($url_caption);
        
        if (is_wp_error($response)) {
            // Handle error
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        
        if (!empty($json['items'])) {
            foreach ($json['items'] as $value) {
                $lang .= "<span>" . esc_html(ucwords($value['snippet']['language'])) . "</span>  ";
            }
            $lang = rtrim($lang, "  "); // Trim the trailing spaces
            update_post_meta($post_id, 'video_avail_lang', $lang);
        }
    }
}













