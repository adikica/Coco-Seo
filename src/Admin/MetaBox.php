<?php
declare(strict_types=1);

namespace CocoSEO\Admin;

use CocoSEO\Core\Settings;

/**
 * SEO Meta Box class
 */
class MetaBox {
    /**
     * Register hooks
     */
    public function register(): void {
        // Block editor integration
        add_action('init', [$this, 'registerBlockEditorAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        
        // For non-block editor (classic editor compatibility)
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox']);
        
        // Register REST API endpoints for block editor
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }
    
    /**
     * Register block editor assets
     */
    public function registerBlockEditorAssets(): void {
        wp_register_script(
            'coco-seo-editor',
            COCO_SEO_URL . 'assets/js/editor.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'],
            COCO_SEO_VERSION,
            true
        );
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueueBlockEditorAssets(): void {
        $screen = get_current_screen();
        
        // Only load on post edit screens
        if (!$screen || !$screen->is_block_editor) {
            return;
        }
        
        // Get post types to check
        $post_types = Settings::get('post_types', ['post', 'page']);
        
        // Only load on enabled post types
        if (!in_array($screen->post_type, $post_types, true)) {
            return;
        }
        
        wp_enqueue_script('coco-seo-editor');
        
        // Pass data to script
        wp_localize_script('coco-seo-editor', 'cocoSEO', [
            'restUrl' => esc_url_raw(rest_url('coco-seo/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => get_the_ID(),
            'settings' => Settings::getAll(),
        ]);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void {
        register_rest_route('coco-seo/v1', '/meta/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMeta'],
            'permission_callback' => [$this, 'checkMetaPermission'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
        
        register_rest_route('coco-seo/v1', '/meta/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updateMeta'],
            'permission_callback' => [$this, 'checkMetaPermission'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }
    
    /**
     * Check permission for REST API
     * 
     * @param \WP_REST_Request $request The request object
     * @return bool Whether the user has permission
     */
    public function checkMetaPermission(\WP_REST_Request $request): bool {
        $post_id = (int) $request->get_param('id');
        
        return current_user_can('edit_post', $post_id);
    }
    
    /**
     * Get meta for REST API
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response The response object
     */
    public function getMeta(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = (int) $request->get_param('id');
        
        $meta_title = get_post_meta($post_id, '_coco_meta_title', true);
        $meta_description = get_post_meta($post_id, '_coco_meta_description', true);
        $meta_index_follow = get_post_meta($post_id, '_coco_meta_index_follow', true);
        $indexing_status = get_post_meta($post_id, '_coco_indexing_status', true);
        $indexing_checked = get_post_meta($post_id, '_coco_indexing_checked', true);
        
        // Set defaults if not set
        if (empty($meta_index_follow)) {
            $meta_index_follow = Settings::get('global_index', 'index') . ' ' . 
                                Settings::get('global_follow', 'follow');
        }
        
        $data = [
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
            'meta_index_follow' => $meta_index_follow,
            'indexing_status' => $indexing_status,
            'indexing_checked' => $indexing_checked ? date_i18n(get_option('date_format'), (int) $indexing_checked) : '',
        ];
        
        return new \WP_REST_Response($data, 200);
    }
    
    /**
     * Update meta for REST API
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response The response object
     */
    public function updateMeta(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = (int) $request->get_param('id');
        $params = $request->get_params();
        
        // Validate and sanitize
        $meta_title = isset($params['meta_title']) ? sanitize_text_field($params['meta_title']) : '';
        $meta_description = isset($params['meta_description']) ? sanitize_textarea_field($params['meta_description']) : '';
        $meta_index_follow = isset($params['meta_index_follow']) ? sanitize_text_field($params['meta_index_follow']) : '';
        
        // Validate index_follow value
        $allowed_values = ['index follow', 'index nofollow', 'noindex follow', 'noindex nofollow'];
        if (!in_array($meta_index_follow, $allowed_values, true)) {
            $meta_index_follow = 'index follow';
        }
        
        // Update post meta
        update_post_meta($post_id, '_coco_meta_title', $meta_title);
        update_post_meta($post_id, '_coco_meta_description', $meta_description);
        update_post_meta($post_id, '_coco_meta_index_follow', $meta_index_follow);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('SEO meta data updated successfully.', 'coco-seo'),
        ], 200);
    }
    
    /**
     * Add classic editor meta box
     */
    public function addMetaBox(): void {
        // Get enabled post types
        $post_types = Settings::get('post_types', ['post', 'page']);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'coco_seo_meta_box',
                __('SEO Settings', 'coco-seo'),
                [$this, 'renderMetaBox'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render classic editor meta box
     * 
     * @param \WP_Post $post The post object
     */
    public function renderMetaBox(\WP_Post $post): void {
        // Get meta values
        $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_coco_meta_description', true);
        $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);
        $indexing_status = get_post_meta($post->ID, '_coco_indexing_status', true);
        $indexing_checked = get_post_meta($post->ID, '_coco_indexing_checked', true);
        
        // Set defaults if not set
        if (empty($meta_index_follow)) {
            $meta_index_follow = Settings::get('global_index', 'index') . ' ' . 
                                Settings::get('global_follow', 'follow');
        }
        
        // Add nonce for security
        wp_nonce_field('coco_seo_meta_box', 'coco_seo_meta_box_nonce');
        
        ?>
        <div class="coco-seo-meta-box">
            <div class="coco-seo-meta-box-field">
                <label for="coco_meta_title"><?php esc_html_e('Meta Title (50-60 characters)', 'coco-seo'); ?></label>
                <input type="text" id="coco_meta_title" name="coco_meta_title" value="<?php echo esc_attr($meta_title); ?>" maxlength="60" style="width:100%;">
                <div class="coco-seo-meta-box-counter">
                    <span id="coco_meta_title_count"><?php echo esc_html(strlen($meta_title)); ?></span>/60
                </div>
            </div>
            
            <div class="coco-seo-meta-box-field">
                <label for="coco_meta_description"><?php esc_html_e('Meta Description (130-160 characters)', 'coco-seo'); ?></label>
                <textarea id="coco_meta_description" name="coco_meta_description" rows="4" maxlength="160" style="width:100%;"><?php echo esc_textarea($meta_description); ?></textarea>
                <div class="coco-seo-meta-box-counter">
                    <span id="coco_meta_description_count"><?php echo esc_html(strlen($meta_description)); ?></span>/160
                </div>
            </div>
            
            <div class="coco-seo-meta-box-field">
                <label for="coco_meta_index_follow"><?php esc_html_e('Search Engine Visibility', 'coco-seo'); ?></label>
                <select id="coco_meta_index_follow" name="coco_meta_index_follow" style="width:100%;">
                    <option value="index follow" <?php selected($meta_index_follow, 'index follow'); ?>>
                        <?php esc_html_e('Index this page & follow links (index, follow)', 'coco-seo'); ?>
                    </option>
                    <option value="index nofollow" <?php selected($meta_index_follow, 'index nofollow'); ?>>
                        <?php esc_html_e('Index this page & don\'t follow links (index, nofollow)', 'coco-seo'); ?>
                    </option>
                    <option value="noindex follow" <?php selected($meta_index_follow, 'noindex follow'); ?>>
                        <?php esc_html_e('Don\'t index this page & follow links (noindex, follow)', 'coco-seo'); ?>
                    </option>
                    <option value="noindex nofollow" <?php selected($meta_index_follow, 'noindex nofollow'); ?>>
                        <?php esc_html_e('Don\'t index this page & don\'t follow links (noindex, nofollow)', 'coco-seo'); ?>
                    </option>
                </select>
            </div>
            
            <?php if (!empty($indexing_status)): ?>
                <div class="coco-seo-meta-box-info">
                    <h4><?php esc_html_e('Indexing Status:', 'coco-seo'); ?></h4>
                    <p>
                        <strong><?php 
                            echo $indexing_status === 'indexed' 
                                ? esc_html__('Indexed', 'coco-seo') 
                                : esc_html__('Not Indexed', 'coco-seo'); 
                        ?></strong>
                        <?php if (!empty($indexing_checked)): ?>
                            <span class="coco-seo-checked">
                                (<?php esc_html_e('Checked:', 'coco-seo'); ?> 
                                <?php echo esc_html(date_i18n(get_option('date_format'), (int) $indexing_checked)); ?>)
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <script>
                (function() {
                    // Character counters
                    const titleInput = document.getElementById('coco_meta_title');
                    const titleCount = document.getElementById('coco_meta_title_count');
                    const descInput = document.getElementById('coco_meta_description');
                    const descCount = document.getElementById('coco_meta_description_count');
                    
                    if (titleInput && titleCount) {
                        titleInput.addEventListener('input', function() {
                            titleCount.textContent = this.value.length;
                        });
                    }
                    
                    if (descInput && descCount) {
                        descInput.addEventListener('input', function() {
                            descCount.textContent = this.value.length;
                        });
                    }
                })();
            </script>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     * 
     * @param int $post_id The post ID
     */
    public function saveMetaBox(int $post_id): void {
        // Check if nonce is set and valid
        if (!isset($_POST['coco_seo_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['coco_seo_meta_box_nonce'], 'coco_seo_meta_box')) {
            return;
        }
        
        // Check if autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save meta title
        if (isset($_POST['coco_meta_title'])) {
            $meta_title = sanitize_text_field($_POST['coco_meta_title']);
            update_post_meta($post_id, '_coco_meta_title', $meta_title);
        }
        
        // Save meta description
        if (isset($_POST['coco_meta_description'])) {
            $meta_description = sanitize_textarea_field($_POST['coco_meta_description']);
            update_post_meta($post_id, '_coco_meta_description', $meta_description);
        }
        
        // Save index follow
        if (isset($_POST['coco_meta_index_follow'])) {
            $meta_index_follow = sanitize_text_field($_POST['coco_meta_index_follow']);
            $allowed_values = ['index follow', 'index nofollow', 'noindex follow', 'noindex nofollow'];
            
            if (in_array($meta_index_follow, $allowed_values, true)) {
                update_post_meta($post_id, '_coco_meta_index_follow', $meta_index_follow);
            }
        }
    }
}