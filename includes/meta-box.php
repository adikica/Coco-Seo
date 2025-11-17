<?php
/**
 * Meta box functionality for Coco SEO Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register meta boxes for posts, pages, and custom post types
 */
function coco_add_meta_box()
{
    // Get all public post types
    $post_types = get_post_types(['public' => true]);
    
    // Remove attachment post type
    if (isset($post_types['attachment'])) {
        unset($post_types['attachment']);
    }

    foreach ($post_types as $post_type) {
        add_meta_box(
            'coco_meta_box',
            __('SEO Meta Title & Description', 'coco-seo'),
            'coco_meta_box_callback',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'coco_add_meta_box');

/**
 * Meta box callback
 *
 * @param WP_Post $post The post object
 */
function coco_meta_box_callback($post)
{
    // Get saved values
    $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_coco_meta_description', true);
    $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);

    // Add nonce
    wp_nonce_field('coco_save_meta_box_data', 'coco_meta_box_nonce');
    ?>

    <p>
        <label for="coco_meta_title"><?php echo esc_html__('Meta Title (45-60 characters)', 'coco-seo'); ?></label><br />
        <input type="text" id="coco_meta_title" name="coco_meta_title" value="<?php echo esc_attr($meta_title); ?>" size="30" style="width:100%;" maxlength="60" />
        <br /><small><?php echo esc_html__('Characters left: ', 'coco-seo'); ?><span id="title-count"><?php echo esc_html(60 - strlen($meta_title)); ?></span></small>
    </p>

    <p>
        <label for="coco_meta_description" style="display:block; margin-top:10px;"><?php echo esc_html__('Meta Description (130-160 characters)', 'coco-seo'); ?></label>
        <textarea id="coco_meta_description" name="coco_meta_description" rows="4" style="width:100%;" maxlength="160"><?php echo esc_textarea($meta_description); ?></textarea>
        <br /><small><?php echo esc_html__('Characters left: ', 'coco-seo'); ?><span id="description-count"><?php echo esc_html(160 - strlen($meta_description)); ?></span></small>
    </p>

    <p>
        <label for="coco_meta_index_follow" style="display:block; margin-top:10px;"><?php echo esc_html__('Indexing Options', 'coco-seo'); ?></label>
        <select id="coco_meta_index_follow" name="coco_meta_index_follow">
            <option value="index follow" <?php selected($meta_index_follow, 'index follow', false); ?>>
                <?php echo esc_html__('Index and Follow', 'coco-seo'); ?>
            </option>
            <option value="noindex nofollow" <?php selected($meta_index_follow, 'noindex nofollow', false); ?>>
                <?php echo esc_html__('Noindex and Nofollow', 'coco-seo'); ?>
            </option>
        </select>
    </p>

    <!-- Character counter script -->
    <script>
        (function(){
            function updateCount(inputId, countId, maxLength) {
                var input = document.getElementById(inputId);
                var countDisplay = document.getElementById(countId);
                if (input && countDisplay) {
                    countDisplay.innerText = maxLength - input.value.length;
                    input.addEventListener('input', function(){
                        countDisplay.innerText = maxLength - input.value.length;
                    });
                }
            }
            updateCount('coco_meta_title', 'title-count', 60);
            updateCount('coco_meta_description', 'description-count', 160);
        })();
    </script>
    <?php
}

/**
 * Save meta box data
 *
 * @param int $post_id The post ID
 */
function coco_save_meta_box_data($post_id)
{
    // Verify nonce
    if (!isset($_POST['coco_meta_box_nonce']) || !wp_verify_nonce($_POST['coco_meta_box_nonce'], 'coco_save_meta_box_data')) {
        return;
    }

    // Skip autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save meta title
    if (isset($_POST['coco_meta_title'])) {
        update_post_meta($post_id, '_coco_meta_title', sanitize_text_field($_POST['coco_meta_title']));
    }

    // Save meta description
    if (isset($_POST['coco_meta_description'])) {
        update_post_meta($post_id, '_coco_meta_description', sanitize_textarea_field($_POST['coco_meta_description']));
    }

    // Save index follow
    if (isset($_POST['coco_meta_index_follow'])) {
        $allowed_values = array('index follow', 'noindex nofollow');
        $value = sanitize_text_field($_POST['coco_meta_index_follow']);
        
        if (in_array($value, $allowed_values, true)) {
            update_post_meta($post_id, '_coco_meta_index_follow', $value);
        }
    }
}
add_action('save_post', 'coco_save_meta_box_data');

/**
 * Add meta tags to head
 */
function coco_add_all_meta_tags_in_head()
{
    // Only for singular
    if (!is_singular()) {
        return;
    }

    global $post;
    if (!$post) {
        return;
    }

    // Get meta values
    $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_coco_meta_description', true);
    $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);

    // Use post title if meta title is empty
    if (empty($meta_title)) {
        $meta_title = get_the_title($post->ID);
    }

    // Get fallback description if needed
    if (empty($meta_description) && function_exists('coco_get_fallback_description')) {
        $meta_description = coco_get_fallback_description($post);
    }

    // Get featured image
    $featured_image = '';
    if (function_exists('coco_get_featured_image_url')) {
        $featured_image = coco_get_featured_image_url($post);
    }

    // Use default index follow if empty
    if (empty($meta_index_follow)) {
        $meta_index_follow = 'index follow';
    }

    // Output meta tags
    echo '<meta name="robots" content="' . esc_attr($meta_index_follow) . ', max-image-preview:large">' . "\n";
    
    if (!current_theme_supports('title-tag')) {
        echo '<title>' . esc_html($meta_title) . '</title>' . "\n";
    }
    
    echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
    
    // Open Graph tags
    echo '<meta property="og:title" content="' . esc_attr($meta_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($meta_description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '">' . "\n";
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    
    if (!empty($featured_image)) {
        echo '<meta property="og:image" content="' . esc_url($featured_image) . '">' . "\n";
    }
    
    // Twitter Card tags
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($meta_description) . '">' . "\n";
    
    if (!empty($featured_image)) {
        echo '<meta name="twitter:image" content="' . esc_url($featured_image) . '">' . "\n";
    }
}
add_action('wp_head', 'coco_add_all_meta_tags_in_head');