<?php
declare(strict_types=1);

namespace CocoSEO\Admin;

use CocoSEO\Core\Settings;

/**
 * SEO Status Table class
 */
class Table {
    /**
     * Table instance
     */
    private ?SEOStatusTable $table = null;
    
    /**
     * Register hooks
     */
    public function register(): void {
        add_action('admin_init', [$this, 'processActions']);
        add_action('wp_ajax_coco_seo_run_check', [$this, 'ajaxRunCheck']);
        add_action('wp_ajax_coco_seo_get_posts', [$this, 'ajaxGetPosts']);
    }
    
    /**
     * Process bulk actions
     */
    public function processActions(): void {
        if (!isset($_POST['coco_seo_action_nonce']) || 
            !wp_verify_nonce($_POST['coco_seo_action_nonce'], 'coco_seo_bulk_action')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $action = sanitize_text_field($_POST['action']);
            $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) 
                ? array_map('intval', $_POST['post_ids']) 
                : [];
            
            if (empty($post_ids)) {
                return;
            }
            
            switch ($action) {
                case 'check_index':
                    $this->checkPostsIndexStatus($post_ids);
                    break;
                    
                case 'set_index':
                    $this->setPostsIndexStatus($post_ids, 'index follow');
                    break;
                    
                case 'set_noindex':
                    $this->setPostsIndexStatus($post_ids, 'noindex nofollow');
                    break;
                    
                case 'reset_status':
                    $this->resetPostsIndexStatus($post_ids);
                    break;
            }
            
            // Redirect to avoid form resubmission
            wp_safe_redirect(add_query_arg(['page' => 'coco-seo-status'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Render the SEO status page
     */
    public function renderPage(): void {
        // Load WP_List_Table if not already loaded
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        
        // Initialize table
        $this->table = new SEOStatusTable();
        $this->table->prepare_items();
        
        ?>
        <div class="wrap coco-seo-status">
            <h1><?php echo esc_html__('SEO Status', 'coco-seo'); ?></h1>
            
            <div class="coco-seo-filters">
                <form method="get">
                    <input type="hidden" name="page" value="coco-seo-status">
                    
                    <?php
                        // Add post type filter
                        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'all';
                        $post_types = array_merge(
                            ['all' => __('All Post Types', 'coco-seo')],
                            wp_list_pluck(get_post_types(['public' => true], 'objects'), 'label', 'name')
                        );
                    ?>
                    <select name="post_type" id="filter-by-post-type">
                        <?php foreach ($post_types as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($post_type, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php
                        // Add index status filter
                        $index_status = isset($_GET['index_status']) ? sanitize_key($_GET['index_status']) : 'all';
                        $statuses = [
                            'all' => __('All Statuses', 'coco-seo'),
                            'index' => __('Indexed', 'coco-seo'),
                            'noindex' => __('Not Indexed', 'coco-seo'),
                            'not_checked' => __('Not Checked', 'coco-seo'),
                        ];
                    ?>
                    <select name="index_status" id="filter-by-index-status">
                        <?php foreach ($statuses as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($index_status, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php
                        // Add meta status filter
                        $meta_status = isset($_GET['meta_status']) ? sanitize_key($_GET['meta_status']) : 'all';
                        $meta_statuses = [
                            'all' => __('All Meta Statuses', 'coco-seo'),
                            'with_meta' => __('With Meta', 'coco-seo'),
                            'without_meta' => __('Without Meta', 'coco-seo'),
                        ];
                    ?>
                    <select name="meta_status" id="filter-by-meta-status">
                        <?php foreach ($meta_statuses as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($meta_status, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php submit_button(__('Filter', 'coco-seo'), 'secondary', 'filter_action', false); ?>
                    
                    <?php if (isset($_GET['s']) && !empty($_GET['s'])) : ?>
                        <span class="subtitle">
                            <?php printf(
                                /* translators: %s: search query */
                                esc_html__('Search results for: %s', 'coco-seo'),
                                '<strong>' . esc_html(wp_unslash($_GET['s'])) . '</strong>'
                            ); ?>
                        </span>
                    <?php endif; ?>
                </form>
            </div>
            
            <form id="coco-seo-status-form" method="post">
                <?php
                    $this->table->search_box(__('Search', 'coco-seo'), 'coco-seo-search');
                    wp_nonce_field('coco_seo_bulk_action', 'coco_seo_action_nonce');
                    $this->table->display();
                ?>
            </form>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Handle bulk action submission
                $('#doaction, #doaction2').click(function(e) {
                    e.preventDefault();
                    
                    var action = $(this).siblings('select').val();
                    if (action === '-1') {
                        alert('<?php echo esc_js(__('Please select an action', 'coco-seo')); ?>');
                        return;
                    }
                    
                    var selectedItems = $('input[name="post_ids[]"]:checked').length;
                    if (selectedItems === 0) {
                        alert('<?php echo esc_js(__('Please select at least one item', 'coco-seo')); ?>');
                        return;
                    }
                    
                    $('#coco-seo-status-form').submit();
                });
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for running indexing check
     */
    public function ajaxRunCheck(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coco_seo_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'coco-seo')]);
        }
        
        // Verify user can manage options
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'coco-seo')]);
        }
        
        // Get post ID
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'coco-seo')]);
        }
        
        // Get post URL
        $url = get_permalink($post_id);
        if (!$url) {
            wp_send_json_error(['message' => __('Could not get post URL.', 'coco-seo')]);
        }
        
        // Run check
        try {
            $google = new \CocoSEO\SEO\Google();
            $result = $google->checkIndexStatus($url);
            
            // Update post meta
            update_post_meta(
                $post_id, 
                '_coco_indexing_status', 
                $result['indexed'] ? 'indexed' : 'not_indexed'
            );
            
            // Update last checked timestamp
            update_post_meta(
                $post_id,
                '_coco_indexing_checked',
                time()
            );
            
            wp_send_json_success([
                'message' => __('Indexing check completed.', 'coco-seo'),
                'status' => $result['indexed'] ? 'indexed' : 'not_indexed',
                'status_text' => $result['indexed'] 
                    ? __('Indexed', 'coco-seo') 
                    : __('Not Indexed', 'coco-seo'),
                'checked' => date_i18n(get_option('date_format'), time()),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * AJAX handler for getting posts with pagination
     */
    public function ajaxGetPosts(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coco_seo_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'coco-seo')]);
        }
        
        // Verify user can manage options
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'coco-seo')]);
        }
        
        // Get parameters
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'all';
        $index_status = isset($_POST['index_status']) ? sanitize_text_field($_POST['index_status']) : 'all';
        $meta_status = isset($_POST['meta_status']) ? sanitize_text_field($_POST['meta_status']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Initialize table
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        
        $this->table = new SEOStatusTable();
        $this->table->set_pagination($page, $per_page);
        $this->table->set_filters($post_type, $index_status, $meta_status, $search);
        $this->table->prepare_items();
        
        ob_start();
        $this->table->display();
        $table_html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $table_html,
            'total_items' => $this->table->get_total_items(),
            'total_pages' => $this->table->get_total_pages(),
        ]);
    }
    
    /**
     * Check indexing status for multiple posts
     * 
     * @param array<int> $post_ids Array of post IDs
     */
    private function checkPostsIndexStatus(array $post_ids): void {
        if (empty($post_ids)) {
            return;
        }
        
        $google = new \CocoSEO\SEO\Google();
        
        foreach ($post_ids as $post_id) {
            // Get post URL
            $url = get_permalink($post_id);
            if (!$url) {
                continue;
            }
            
            try {
                $result = $google->checkIndexStatus($url);
                
                // Update post meta
                update_post_meta(
                    $post_id, 
                    '_coco_indexing_status', 
                    $result['indexed'] ? 'indexed' : 'not_indexed'
                );
                
                // Update last checked timestamp
                update_post_meta(
                    $post_id,
                    '_coco_indexing_checked',
                    time()
                );
                
                // Avoid rate limiting
                if (count($post_ids) > 1) {
                    usleep(500000); // 0.5 seconds
                }
            } catch (\Exception $e) {
                // Log error and continue with next post
                error_log(sprintf(
                    'CocoSEO indexing check error for post %d: %s',
                    $post_id,
                    $e->getMessage()
                ));
            }
        }
    }
    
    /**
     * Set index/noindex status for multiple posts
     * 
     * @param array<int> $post_ids Array of post IDs
     * @param string $status Status to set ('index follow' or 'noindex nofollow')
     */
    private function setPostsIndexStatus(array $post_ids, string $status): void {
        if (empty($post_ids)) {
            return;
        }
        
        foreach ($post_ids as $post_id) {
            update_post_meta($post_id, '_coco_meta_index_follow', $status);
        }
    }
    
    /**
     * Reset indexing status for multiple posts
     * 
     * @param array<int> $post_ids Array of post IDs
     */
    private function resetPostsIndexStatus(array $post_ids): void {
        if (empty($post_ids)) {
            return;
        }
        
        foreach ($post_ids as $post_id) {
            delete_post_meta($post_id, '_coco_indexing_status');
            delete_post_meta($post_id, '_coco_indexing_checked');
        }
    }
}

/**
 * SEO Status Table class extending WP_List_Table
 */
class SEOStatusTable extends \WP_List_Table {
    /**
     * Current page
     */
    private int $current_page = 1;
    
    /**
     * Items per page
     */
    private int $per_page = 20;
    
    /**
     * Post type filter
     */
    private string $post_type = 'all';
    
    /**
     * Index status filter
     */
    private string $index_status = 'all';
    
    /**
     * Meta status filter
     */
    private string $meta_status = 'all';
    
    /**
     * Search query
     */
    private string $search = '';
    
    /**
     * Total items count
     */
    private int $total_items = 0;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'post',
            'plural'   => 'posts',
            'ajax'     => true,
        ]);
        
        // Set current page from URL or AJAX
        $this->current_page = $this->get_pagenum();
        
        // Set filters from URL or AJAX
        $this->post_type = isset($_REQUEST['post_type']) ? sanitize_key($_REQUEST['post_type']) : 'all';
        $this->index_status = isset($_REQUEST['index_status']) ? sanitize_key($_REQUEST['index_status']) : 'all';
        $this->meta_status = isset($_REQUEST['meta_status']) ? sanitize_key($_REQUEST['meta_status']) : 'all';
        $this->search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
    }
    
    /**
     * Set pagination parameters
     * 
     * @param int $page Current page
     * @param int $per_page Items per page
     */
    public function set_pagination(int $page, int $per_page): void {
        $this->current_page = max(1, $page);
        $this->per_page = max(10, $per_page);
    }
    
    /**
     * Set filter parameters
     * 
     * @param string $post_type Post type filter
     * @param string $index_status Index status filter
     * @param string $meta_status Meta status filter
     * @param string $search Search query
     */
    public function set_filters(string $post_type, string $index_status, string $meta_status, string $search): void {
        $this->post_type = $post_type;
        $this->index_status = $index_status;
        $this->meta_status = $meta_status;
        $this->search = $search;
    }
    
    /**
     * Get total items count
     */
    public function get_total_items(): int {
        return $this->total_items;
    }
    
    /**
     * Get total pages count
     */
    public function get_total_pages(): int {
        return ceil($this->total_items / $this->per_page);
    }
    
    /**
     * No items text
     */
    public function no_items(): void {
        esc_html_e('No posts found.', 'coco-seo');
    }
    
    /**
     * Get table columns
     * 
     * @return array<string, string> Columns
     */
    public function get_columns(): array {
        return [
            'cb'           => '<input type="checkbox" />',
            'title'        => __('Title', 'coco-seo'),
            'post_type'    => __('Type', 'coco-seo'),
            'meta_title'   => __('Meta Title', 'coco-seo'),
            'meta_desc'    => __('Meta Description', 'coco-seo'),
            'index_status' => __('Index Status', 'coco-seo'),
            'robots'       => __('Robots', 'coco-seo'),
            'actions'      => __('Actions', 'coco-seo'),
        ];
    }
    
    /**
     * Get sortable columns
     * 
     * @return array<string, array<int, bool|string>> Sortable columns
     */
    public function get_sortable_columns(): array {
        return [
            'title'      => ['title', false],
            'post_type'  => ['post_type', false],
        ];
    }
    
    /**
     * Get bulk actions
     * 
     * @return array<string, string> Bulk actions
     */
    public function get_bulk_actions(): array {
        return [
            'check_index'  => __('Check Indexing Status', 'coco-seo'),
            'set_index'    => __('Set Index/Follow', 'coco-seo'),
            'set_noindex'  => __('Set Noindex/Nofollow', 'coco-seo'),
            'reset_status' => __('Reset Indexing Status', 'coco-seo'),
        ];
    }
    
    /**
     * Prepare items for display
     */
    public function prepare_items(): void {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Get items from database
        $posts = $this->get_posts();
        $this->items = $posts['items'];
        $this->total_items = $posts['total'];
        
        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $this->total_items,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($this->total_items / $this->per_page),
        ]);
    }
    
    /**
     * Get posts from database
     * 
     * @return array{items: array<int, \WP_Post>, total: int} Posts and total count
     */
    private function get_posts(): array {
        global $wpdb;
        
        // Start building query
        $query = "
            SELECT SQL_CALC_FOUND_ROWS p.*, 
                   pm1.meta_value as meta_title,
                   pm2.meta_value as meta_description,
                   pm3.meta_value as index_follow,
                   pm4.meta_value as indexing_status,
                   pm5.meta_value as indexing_checked
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_coco_meta_title'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_coco_meta_description'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_coco_meta_index_follow'
            LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_coco_indexing_status'
            LEFT JOIN {$wpdb->postmeta} pm5 ON p.ID = pm5.post_id AND pm5.meta_key = '_coco_indexing_checked'
            WHERE p.post_status = 'publish'
        ";
        
        // Add post type filter
        if ($this->post_type !== 'all') {
            $query .= $wpdb->prepare(" AND p.post_type = %s", $this->post_type);
        } else {
            // Get enabled post types
            $post_types = Settings::get('post_types', ['post', 'page']);
            if (!empty($post_types)) {
                $post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
                $query .= " AND p.post_type IN ({$post_types_str})";
            }
        }
        
        // Add index status filter
        if ($this->index_status !== 'all') {
            switch ($this->index_status) {
                case 'index':
                    $query .= " AND pm4.meta_value = 'indexed'";
                    break;
                    
                case 'noindex':
                    $query .= " AND pm4.meta_value = 'not_indexed'";
                    break;
                    
                case 'not_checked':
                    $query .= " AND pm4.meta_value IS NULL";
                    break;
            }
        }
        
        // Add meta status filter
        if ($this->meta_status !== 'all') {
            switch ($this->meta_status) {
                case 'with_meta':
                    $query .= " AND pm1.meta_value IS NOT NULL AND pm1.meta_value != ''";
                    break;
                    
                case 'without_meta':
                    $query .= " AND (pm1.meta_value IS NULL OR pm1.meta_value = '')";
                    break;
            }
        }
        
        // Add search filter
        if (!empty($this->search)) {
            $query .= $wpdb->prepare(
                " AND (p.post_title LIKE %s OR p.post_content LIKE %s OR pm1.meta_value LIKE %s OR pm2.meta_value LIKE %s)",
                '%' . $wpdb->esc_like($this->search) . '%',
                '%' . $wpdb->esc_like($this->search) . '%',
                '%' . $wpdb->esc_like($this->search) . '%',
                '%' . $wpdb->esc_like($this->search) . '%'
            );
        }
        
        // Add sorting
        $orderby = 'p.post_title';
        $order = 'ASC';
        
        if (isset($_REQUEST['orderby'])) {
            switch ($_REQUEST['orderby']) {
                case 'title':
                    $orderby = 'p.post_title';
                    break;
                    
                case 'post_type':
                    $orderby = 'p.post_type';
                    break;
            }
        }
        
        if (isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC'])) {
            $order = strtoupper($_REQUEST['order']);
        }
        
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add pagination
        $offset = ($this->current_page - 1) * $this->per_page;
        $query .= $wpdb->prepare(" LIMIT %d, %d", $offset, $this->per_page);
        
        // Get results
        $results = $wpdb->get_results($query, ARRAY_A);
        $total = (int) $wpdb->get_var("SELECT FOUND_ROWS()");
        
        // Format results
        $items = [];
        foreach ($results as $result) {
            $post = new \WP_Post((object) $result);
            $post->meta_title = $result['meta_title'] ?? '';
            $post->meta_description = $result['meta_description'] ?? '';
            $post->index_follow = $result['index_follow'] ?? 'index follow';
            $post->indexing_status = $result['indexing_status'] ?? '';
            $post->indexing_checked = $result['indexing_checked'] ?? '';
            
            $items[] = $post;
        }
        
        return [
            'items' => $items,
            'total' => $total,
        ];
    }
    
    /**
     * Column default
     * 
     * @param \WP_Post $item Post object
     * @param string $column_name Column name
     * @return string Column content
     */
    public function column_default($item, $column_name) {
        return '';
    }
    
    /**
     * Checkbox column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="post_ids[]" value="%s" />',
            $item->ID
        );
    }
    
    /**
     * Title column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_title($item) {
        $edit_link = get_edit_post_link($item->ID);
        $view_link = get_permalink($item->ID);
        
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($edit_link),
                __('Edit', 'coco-seo')
            ),
            'view' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url($view_link),
                __('View', 'coco-seo')
            ),
        ];
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url($edit_link),
            esc_html($item->post_title),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Post type column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_post_type($item) {
        $post_type_obj = get_post_type_object($item->post_type);
        return $post_type_obj ? esc_html($post_type_obj->labels->singular_name) : esc_html($item->post_type);
    }
    
    /**
     * Meta title column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_meta_title($item) {
        if (!empty($item->meta_title)) {
            return sprintf(
                '<div class="coco-seo-meta-title">%s</div>',
                esc_html($item->meta_title)
            );
        }
        
        return sprintf(
            '<span class="coco-seo-missing">%s</span>',
            __('Not set', 'coco-seo')
        );
    }
    
    /**
     * Meta description column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_meta_desc($item) {
        if (!empty($item->meta_description)) {
            return sprintf(
                '<div class="coco-seo-meta-desc" title="%s">%s</div>',
                esc_attr($item->meta_description),
                esc_html(mb_substr($item->meta_description, 0, 50) . (mb_strlen($item->meta_description) > 50 ? '...' : ''))
            );
        }
        
        return sprintf(
            '<span class="coco-seo-missing">%s</span>',
            __('Not set', 'coco-seo')
        );
    }
    
    /**
     * Index status column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_index_status($item) {
        $status = '';
        $checked = '';
        
        if (!empty($item->indexing_status)) {
            if ($item->indexing_status === 'indexed') {
                $status = sprintf(
                    '<span class="coco-seo-status coco-seo-indexed">%s</span>',
                    __('Indexed', 'coco-seo')
                );
            } else {
                $status = sprintf(
                    '<span class="coco-seo-status coco-seo-not-indexed">%s</span>',
                    __('Not Indexed', 'coco-seo')
                );
            }
            
            if (!empty($item->indexing_checked)) {
                $checked = sprintf(
                    '<div class="coco-seo-checked">%s: %s</div>',
                    __('Checked', 'coco-seo'),
                    date_i18n(get_option('date_format'), (int) $item->indexing_checked)
                );
            }
        } else {
            $status = sprintf(
                '<span class="coco-seo-status coco-seo-not-checked">%s</span>',
                __('Not Checked', 'coco-seo')
            );
        }
        
        return $status . $checked;
    }
    
    /**
     * Robots column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_robots($item) {
        $index_follow = $item->index_follow ?? 'index follow';
        $parts = explode(' ', $index_follow);
        $index = $parts[0] ?? 'index';
        $follow = $parts[1] ?? 'follow';
        
        $index_class = $index === 'index' ? 'coco-seo-enabled' : 'coco-seo-disabled';
        $follow_class = $follow === 'follow' ? 'coco-seo-enabled' : 'coco-seo-disabled';
        
        $output = sprintf(
            '<div class="coco-seo-robots"><span class="%s">%s</span> / <span class="%s">%s</span></div>',
            esc_attr($index_class),
            esc_html($index),
            esc_attr($follow_class),
            esc_html($follow)
        );
        
        return $output;
    }
    
    /**
     * Actions column
     * 
     * @param \WP_Post $item Post object
     * @return string Column content
     */
    public function column_actions($item) {
        $actions = [];
        
        // Check indexing status
        $actions[] = sprintf(
            '<a href="#" class="button button-small coco-seo-check" data-post-id="%d" data-nonce="%s">%s</a>',
            $item->ID,
            wp_create_nonce('coco_seo_nonce'),
            __('Check Now', 'coco-seo')
        );
        
        // Edit meta data
        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url(get_edit_post_link($item->ID)),
            __('Edit Meta', 'coco-seo')
        );
        
        return implode(' ', $actions);
    }
}