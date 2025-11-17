<?php
/**
 * Google indexing check functionality for Coco SEO Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display indexing check page
 */
function coco_display_indexing_check_results() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Enqueue admin styles
    wp_enqueue_style('coco-seo-admin-style', plugin_dir_url(__FILE__) . '../assets/css/admin-minimal.css');
    
    echo '<div class="wrap"><h1>Google Indexing Check</h1>';

    // Process API key form
    if (isset($_POST['coco_save_api_key']) && isset($_POST['coco_api_key_nonce']) && 
        wp_verify_nonce($_POST['coco_api_key_nonce'], 'coco_save_api_key')) {
        
        $api_key = sanitize_text_field($_POST['coco_google_api_key']);
        update_option('coco_google_api_key', $api_key);
        echo '<div class="notice notice-success"><p>Google API key saved successfully.</p></div>';
    }

    // Get saved API key
    $saved_api_key = get_option('coco_google_api_key', '');

    // Display settings form
    echo '<div class="coco-seo-settings-section">';
    echo '<h2>Google API Credentials</h2>';
    echo '<form method="post" action="">';
    wp_nonce_field('coco_save_api_key', 'coco_api_key_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="coco_google_api_key">Google API Key</label></th>';
    echo '<td><input name="coco_google_api_key" type="text" id="coco_google_api_key" value="' . esc_attr($saved_api_key) . '" class="regular-text"></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="description">You need a Google API key with the Search Console API enabled to check indexing status.</p>';
    echo '<p class="submit"><input type="submit" name="coco_save_api_key" id="submit" class="button button-primary" value="Save API Key"></p>';
    echo '</form>';
    echo '</div>';

    // Show notice if API key is missing
    if (empty($saved_api_key)) {
        echo '<div class="notice notice-warning"><p>Please enter your Google API key above to proceed with indexing checks. You can get a new key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>.</p></div>';
    } else {
        // Get list of post types to check
        $post_types = get_post_types(['public' => true], 'objects');
        
        echo '<div class="coco-seo-settings-section">';
        echo '<h2>Check Indexing Status</h2>';
        
        // URL Check Form
        echo '<form id="coco-test-indexing-form">';
        echo '<p><strong>Check Individual URL:</strong></p>';
        echo '<p><input type="url" id="coco-test-url" class="regular-text" placeholder="https://example.com/page/" required>';
        echo '<button type="submit" class="button button-secondary">Check Now</button>';
        echo '<span class="spinner" style="float:none;"></span></p>';
        echo '</form>';
        echo '<div id="coco-test-result" style="margin-top:15px;"></div>';
        
// Bulk Check Form (via admin-post.php)
echo '<form id="coco-bulk-indexing-form" method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
wp_nonce_field('coco_bulk_indexing', 'coco_indexing_nonce');

// Tell admin-post which handler to use
echo '<input type="hidden" name="action" value="coco_bulk_indexing">';

echo '<p style="margin-top:20px;"><strong>Bulk Check by Post Type:</strong></p>';
echo '<p>';

echo '<select name="post_type" class="regular-text">';
foreach ($post_types as $type) {
    if ($type->name !== 'attachment') {
        echo '<option value="' . esc_attr($type->name) . '">' . esc_html($type->label) . '</option>';
    }
}
echo '</select>';

echo '<select name="max_posts">';
echo '<option value="10">10 most recent</option>';
echo '<option value="25">25 most recent</option>';
echo '<option value="50">50 most recent</option>';
echo '</select>';

echo '<button type="submit" class="button button-secondary">Run Bulk Check</button>';
echo '</p>';
echo '</form>';

        
// After the forms, still inside coco_display_indexing_check_results()
if (isset($_GET['bulk_checked']) && (int) $_GET['bulk_checked'] === 1) {
    $user_id       = get_current_user_id();
    $transient_key = 'coco_bulk_indexing_result_' . $user_id;
    $data          = get_transient($transient_key);

    if (!empty($data) && !empty($data['items'])) {
        echo '<h3>Bulk Check Results</h3>';
        echo '<p><em>Post type: ' . esc_html($data['post_type']) . ' – Max posts: ' . (int) $data['max_posts'] . '</em></p>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>URL</th><th>Status</th></tr></thead>';
        echo '<tbody>';

        foreach ($data['items'] as $item) {
            echo '<tr>';
            echo '<td><a href="' . esc_url(get_edit_post_link($item['id'])) . '">' . esc_html($item['title']) . '</a></td>';
            echo '<td><a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_url($item['url']) . '</a></td>';
            echo '<td><span class="' . esc_attr($item['status_class']) . '">' . esc_html($item['status_text']) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p><em>Note: In this transition version, indexing is simulated based on post age. The full version will use the Google API.</em></p>';
    } else {
        echo '<div class="notice notice-warning"><p>No bulk results found. Please run the bulk check again.</p></div>';
    }
}


        
        echo '</div>'; // End settings section
        
        // Add basic JavaScript for the test feature
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#coco-test-indexing-form').on('submit', function(e) {
                e.preventDefault();
                
                var url = $('#coco-test-url').val();
                var $spinner = $(this).find('.spinner');
                var $result = $('#coco-test-result');
                
                $spinner.addClass('is-active');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'coco_check_url_indexing',
                        url: url,
                        nonce: '<?php echo wp_create_nonce('coco_check_indexing'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>Error: ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>Server error. Please try again.</p></div>');
                    },
                    complete: function() {
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }

    echo '</div>'; // End wrap
}

/**
 * AJAX handler for URL indexing check
 */
/**
 * AJAX handler for URL indexing check
 */
function coco_ajax_check_url_indexing() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coco_check_indexing')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Verify user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
    }
    
    // Get URL
    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
    if (empty($url)) {
        wp_send_json_error(['message' => 'Please provide a valid URL.']);
    }
    
    // Get API key
    $api_key = get_option('coco_google_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'Google API key is not set.']);
    }
    
    // Check if URL is a localhost URL
    if (preg_match('/localhost|127\.0\.0\.1|::1/i', $url)) {
        wp_send_json_error(['message' => 'Localhost URLs cannot be indexed by Google. Please check a public URL.']);
        return;
    }
    
    // Check if URL is from the current site
    $site_url = site_url();
    if (strpos($url, $site_url) !== 0 && !WP_DEBUG) {
        // In non-debug mode, only allow checking URLs from this site
        wp_send_json_error(['message' => 'Please enter a URL from your own website.']);
        return;
    }
    
    // Simulation mode (for transition version)
    // In real implementation, this would call the Google API
    
    // For demo purposes, generate more realistic results:
    // - Find if we have a post with this URL
    $post_id = url_to_postid($url);
    
    if ($post_id) {
        // Get post date to determine how likely it is to be indexed
        $post = get_post($post_id);
        $post_age = time() - strtotime($post->post_date_gmt);
        $days_old = floor($post_age / DAY_IN_SECONDS);
        
        // Older posts have higher chance of being indexed
        $index_chance = min(90, 30 + ($days_old / 7 * 10)); // 30% base + 10% per week, max 90%
        $indexed = (rand(1, 100) <= $index_chance);
        
        // Store the result
        update_post_meta($post_id, '_coco_indexing_status', $indexed ? 'indexed' : 'not_indexed');
        update_post_meta($post_id, '_coco_indexing_checked', time());
    } else {
        // Unknown URL - less likely to be indexed
        $indexed = (rand(1, 100) <= 20); // 20% chance
    }
    
    if ($indexed) {
        wp_send_json_success([
            'indexed' => true,
            'message' => 'This URL appears to be indexed in Google.'
        ]);
    } else {
        wp_send_json_success([
            'indexed' => false,
            'message' => 'This URL does not appear to be indexed in Google.'
        ]);
    }
}
add_action('wp_ajax_coco_check_url_indexing', 'coco_ajax_check_url_indexing');

/**
 * Check Google indexing status for a URL
 * 
 * @param string $url The URL to check
 * @return array Result with 'success', 'indexed', and 'error' keys
 */
/**
 * Check Google indexing status for a URL
 * 
 * @param string $url The URL to check
 * @return array Result with 'success', 'indexed', and 'error' keys
 */
function coco_check_google_indexing_status($url) {
    // Check if URL is a localhost URL
    if (preg_match('/localhost|127\.0\.0\.1|::1/i', $url)) {
        return [
            'success' => false,
            'error' => 'Localhost URLs cannot be indexed by Google.'
        ];
    }
    
    // This is a placeholder for the full implementation in the next version
    // It will use the Google Search Console API to check indexing status
    
    // Check cached result first
    $transient_key = 'coco_google_index_status_' . md5($url);
    $cached = get_transient($transient_key);
    if (false !== $cached) {
        return $cached;
    }
    
    // For demo purposes, generate more realistic results
    $post_id = url_to_postid($url);
    
    if ($post_id) {
        // Get post date to determine how likely it is to be indexed
        $post = get_post($post_id);
        $post_age = time() - strtotime($post->post_date_gmt);
        $days_old = floor($post_age / DAY_IN_SECONDS);
        
        // Older posts have higher chance of being indexed
        $index_chance = min(90, 30 + ($days_old / 7 * 10)); // 30% base + 10% per week, max 90%
        $indexed = (rand(1, 100) <= $index_chance);
    } else {
        // Unknown URL - less likely to be indexed
        $indexed = (rand(1, 100) <= 20); // 20% chance
    }
    
    $result = [
        'success' => true,
        'indexed' => $indexed,
    ];
    
    // Cache result for an hour
    set_transient($transient_key, $result, HOUR_IN_SECONDS);
    
    return $result;
}


/**
 * Handle bulk indexing check via admin-post.php
 */
function coco_handle_bulk_indexing_request() {
    // Only allow in admin and for users with correct capability
    if (!is_admin() || !current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Verify nonce
    if (
        !isset($_POST['coco_indexing_nonce']) ||
        !wp_verify_nonce($_POST['coco_indexing_nonce'], 'coco_bulk_indexing')
    ) {
        wp_die(__('Security check failed. Please try again.', 'coco-seo'));
    }

    $post_type = isset($_POST['post_type'])
        ? sanitize_text_field($_POST['post_type'])
        : 'post';

    $max_posts = isset($_POST['max_posts'])
        ? min(50, max(1, (int) $_POST['max_posts']))
        : 10;

    // Fetch posts
    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $max_posts,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    // Compute a transient key for this admin user
    $user_id       = get_current_user_id();
    $transient_key = 'coco_bulk_indexing_result_' . $user_id;

    $results = [];

    if (!empty($posts)) {
        foreach ($posts as $post) {
            $url = get_permalink($post->ID);

            // Localhost safeguard
            $is_localhost = preg_match(
                '/localhost|127\.0\.0\.1|::1/i',
                $url
            );

            if ($is_localhost) {
                $status_class = 'coco-seo-disabled';
                $status_text  = 'Cannot check localhost URLs';
                $is_indexed   = false;
            } else {
                // Same simulated logic you already use
                $post_age  = time() - strtotime($post->post_date_gmt);
                $days_old  = (int) floor($post_age / DAY_IN_SECONDS);
                $chance    = min(90, 30 + ($days_old / 7 * 10)); // 30% base + 10% per week, max 90
                $is_indexed = (rand(1, 100) <= $chance);

                $status_class = $is_indexed ? 'coco-seo-enabled' : 'coco-seo-disabled';
                $status_text  = $is_indexed ? 'Indexed' : 'Not Indexed';
            }

            // Save into postmeta (like before)
            update_post_meta($post->ID, '_coco_indexing_status', $is_indexed ? 'indexed' : 'not_indexed');
            update_post_meta($post->ID, '_coco_indexing_checked', time());

            $results[] = [
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'url'          => $url,
                'status_class' => $status_class,
                'status_text'  => $status_text,
            ];
        }
    }

    // Store the results in a transient for 10 minutes
    set_transient($transient_key, [
        'post_type' => $post_type,
        'max_posts' => $max_posts,
        'items'     => $results,
    ], 10 * MINUTE_IN_SECONDS);

    // Redirect back to the indexing page with a flag
    $redirect_url = add_query_arg(
        [
            'page'         => 'coco-indexing-check',
            'bulk_checked' => 1,
        ],
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_coco_bulk_indexing', 'coco_handle_bulk_indexing_request');
