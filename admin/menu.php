<?php
/**
 * Register admin menu
 */
function coco_register_admin_menu()
{
    add_menu_page(
        'Coco SEO',
        'Coco SEO',
        'manage_options',
        'coco-seo',
        'coco_display_dashboard',
        'dashicons-admin-site-alt3',
        90
    );
    
    // Add submenu pages
    add_submenu_page(
        'coco-seo',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'coco-seo',
        'coco_display_dashboard'
    );
    
    add_submenu_page(
        'coco-seo',
        'SEO Status',
        'SEO Status',
        'manage_options',
        'coco-seo-status',
        'coco_display_seo_status'
    );
    
    add_submenu_page(
        'coco-seo',
        'Google Indexing',
        'Google Indexing',
        'manage_options',
        'coco-indexing-check',
        'coco_display_indexing_check_results'
    );
    
    add_submenu_page(
        'coco-seo',
        'Settings',
        'Settings',
        'manage_options',
        'coco-seo-settings',
        'coco_display_settings'
    );
}
add_action('admin_menu', 'coco_register_admin_menu');

/**
 * Display dashboard page
 */
function coco_display_dashboard()
{
    // Register admin styles
    wp_enqueue_style('coco-seo-admin-style', plugin_dir_url(__FILE__) . '../assets/css/admin-minimal.css');
    
    // Get SEO stats
    $stats = coco_get_seo_stats();
    
    ?>
    <div class="wrap">
        <h1>Coco SEO Dashboard</h1>
        
        <div class="coco-seo-dashboard">
            <div class="coco-seo-cards">
                <div class="coco-seo-card">
                    <h2>Indexing Overview</h2>
                    <div class="coco-seo-card-content">
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo intval($stats['indexed']); ?></span>
                            <span class="coco-seo-stat-label">Indexed</span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo intval($stats['not_indexed']); ?></span>
                            <span class="coco-seo-stat-label">Not Indexed</span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo intval($stats['no_check']); ?></span>
                            <span class="coco-seo-stat-label">Not Checked</span>
                        </div>
                    </div>
                </div>
                
                <div class="coco-seo-card">
                    <h2>SEO Health</h2>
                    <div class="coco-seo-card-content">
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo intval($stats['with_meta']); ?></span>
                            <span class="coco-seo-stat-label">With Meta Data</span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo intval($stats['without_meta']); ?></span>
                            <span class="coco-seo-stat-label">Missing Meta</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="coco-seo-actions">
                <a href="<?php echo admin_url('admin.php?page=coco-seo-status'); ?>" class="button button-primary">
                    View SEO Status
                </a>
                <a href="<?php echo admin_url('admin.php?page=coco-seo-settings'); ?>" class="button">
                    Configure Settings
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Display SEO status page
 */
function coco_display_seo_status()
{
    // Register admin styles
    wp_enqueue_style('coco-seo-admin-style', plugin_dir_url(__FILE__) . '../assets/css/admin-minimal.css');
    
    // Get all public post types
    $registered_post_types = get_post_types(['public' => true], 'objects');
    $post_types = [];
    
    foreach ($registered_post_types as $type) {
        // Skip attachments
        if ($type->name === 'attachment') {
            continue;
        }
        $post_types[$type->name] = $type->label;
    }
    
    // Get current filters - use 'coco_post_type' instead of 'post_type' to avoid conflicts
    $current_post_type = isset($_GET['coco_post_type']) ? sanitize_text_field($_GET['coco_post_type']) : 'all';
    $current_meta_status = isset($_GET['meta_status']) ? sanitize_text_field($_GET['meta_status']) : 'all';
    $current_index_status = isset($_GET['index_status']) ? sanitize_text_field($_GET['index_status']) : 'all';
    $current_paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $posts_per_page = 20;
    
    // Build query args
    $args = [
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged' => $current_paged,
    ];
    
    // Apply post type filter
    if ($current_post_type !== 'all') {
        $args['post_type'] = $current_post_type;
    } else {
        $args['post_type'] = array_keys($post_types);
    }
    
    // Add search parameter
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $args['s'] = sanitize_text_field($_GET['s']);
    }
    
    // Add meta query for meta status filtering
    if ($current_meta_status !== 'all') {
        $meta_query = [];
        
        if ($current_meta_status === 'with_meta') {
            $meta_query[] = [
                'key' => '_coco_meta_title',
                'compare' => '!=',
                'value' => '',
            ];
        } elseif ($current_meta_status === 'without_meta') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => '_coco_meta_title',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_coco_meta_title',
                    'compare' => '=',
                    'value' => '',
                ],
            ];
        }
        
        $args['meta_query'] = $meta_query;
    }
    
    // Add meta query for index status filtering
    if ($current_index_status !== 'all') {
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        
        if ($current_index_status === 'index') {
            $args['meta_query'][] = [
                'key' => '_coco_meta_index_follow',
                'compare' => 'LIKE',
                'value' => 'index',
            ];
        } elseif ($current_index_status === 'noindex') {
            $args['meta_query'][] = [
                'key' => '_coco_meta_index_follow',
                'compare' => 'LIKE',
                'value' => 'noindex',
            ];
        }
    }
    
    // Run query
    $query = new WP_Query($args);
    
    ?>
    <div class="wrap">
        <h1>SEO Status</h1>
        
        <div class="coco-seo-filters">
            <form method="get">
                <input type="hidden" name="page" value="coco-seo-status">
                
                <!-- Post type filter - RENAMED to coco_post_type -->
                <select name="coco_post_type" id="filter-by-post-type">
                    <option value="all" <?php selected($current_post_type, 'all'); ?>>All Post Types</option>
                    <?php foreach ($post_types as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($current_post_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Meta status filter -->
                <select name="meta_status" id="filter-by-meta-status">
                    <option value="all" <?php selected($current_meta_status, 'all'); ?>>All Meta Status</option>
                    <option value="with_meta" <?php selected($current_meta_status, 'with_meta'); ?>>With Meta</option>
                    <option value="without_meta" <?php selected($current_meta_status, 'without_meta'); ?>>Without Meta</option>
                </select>
                
                <!-- Index status filter -->
                <select name="index_status" id="filter-by-index-status">
                    <option value="all" <?php selected($current_index_status, 'all'); ?>>All Index Status</option>
                    <option value="index" <?php selected($current_index_status, 'index'); ?>>Indexed</option>
                    <option value="noindex" <?php selected($current_index_status, 'noindex'); ?>>Not Indexed</option>
                </select>
                
                <!-- Submit button - DON'T USE name="filter_action" -->
                <input type="submit" class="button button-secondary" value="Filter">
                
                <!-- Reset filters -->
                <a href="<?php echo admin_url('admin.php?page=coco-seo-status'); ?>" class="button">Reset Filters</a>
                
                <!-- Search box -->
                <p class="search-box">
                    <label class="screen-reader-text" for="post-search-input">Search Posts:</label>
                    <input type="search" id="post-search-input" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                    <input type="submit" id="search-submit" class="button" value="Search">
                </p>
            </form>
        </div>
        
        <?php if ($query->have_posts()) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Meta Title</th>
                        <th>Meta Description</th>
                        <th>Robots</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <?php 
                        $post_id = get_the_ID();
                        $meta_title = get_post_meta($post_id, '_coco_meta_title', true);
                        $meta_description = get_post_meta($post_id, '_coco_meta_description', true);
                        $meta_index_follow = get_post_meta($post_id, '_coco_meta_index_follow', true) ?: 'index follow';
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo get_edit_post_link(); ?>">Edit</a> | </span>
                                    <span class="view"><a href="<?php the_permalink(); ?>" target="_blank">View</a></span>
                                </div>
                            </td>
                            <td><?php echo get_post_type_object(get_post_type())->labels->singular_name; ?></td>
                            <td><?php echo !empty($meta_title) ? esc_html($meta_title) : '<span class="coco-seo-missing">Not set</span>'; ?></td>
                            <td>
                                <?php if (!empty($meta_description)) : ?>
                                    <?php echo esc_html(substr($meta_description, 0, 50) . (strlen($meta_description) > 50 ? '...' : '')); ?>
                                <?php else : ?>
                                    <span class="coco-seo-missing">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $parts = explode(' ', $meta_index_follow);
                                $index = $parts[0] ?? 'index';
                                $follow = $parts[1] ?? 'follow';
                                
                                $index_class = $index === 'index' ? 'coco-seo-enabled' : 'coco-seo-disabled';
                                $follow_class = $follow === 'follow' ? 'coco-seo-enabled' : 'coco-seo-disabled';
                                ?>
                                <span class="<?php echo $index_class; ?>"><?php echo $index; ?></span> / 
                                <span class="<?php echo $follow_class; ?>"><?php echo $follow; ?></span>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>" class="button button-small">Edit Meta</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $big = 999999999; // need an unlikely integer
                    $pagination_args = [
                        'base' => str_replace($big, '%#%', esc_url(add_query_arg('paged', $big))),
                        'format' => '&paged=%#%',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'current' => $current_paged,
                        'total' => $query->max_num_pages,
                    ];
                    
                    // Maintain current filters in pagination links
                    $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    $current_url = remove_query_arg('paged', $current_url);
                    $pagination_args['base'] = add_query_arg('paged', '%#%', $current_url);
                    
                    echo paginate_links($pagination_args);
                    ?>
                </div>
                <div class="alignleft actions">
                    <span class="displaying-num"><?php echo sprintf(_n('%s item', '%s items', $query->found_posts), number_format_i18n($query->found_posts)); ?></span>
                </div>
                <br class="clear">
            </div>
        <?php else : ?>
            <div class="notice notice-warning">
                <p>No content found matching your criteria. <a href="<?php echo admin_url('admin.php?page=coco-seo-status'); ?>">Reset filters</a></p>
            </div>
        <?php endif; ?>
        
        <?php wp_reset_postdata(); ?>
    </div>
    <?php
}

/**
 * Display settings page
 */
function coco_display_settings()
{
    // Register admin styles
    wp_enqueue_style('coco-seo-admin-style', plugin_dir_url(__FILE__) . '../assets/css/admin-minimal.css');
    
    // Process settings form
    if (isset($_POST['coco_seo_save_settings']) && check_admin_referer('coco_seo_settings_nonce')) {
        // Save global indexing settings
        $global_index = isset($_POST['global_index']) ? sanitize_text_field($_POST['global_index']) : 'index';
        $global_follow = isset($_POST['global_follow']) ? sanitize_text_field($_POST['global_follow']) : 'follow';
        $twitter_username = isset($_POST['twitter_username']) ? sanitize_text_field($_POST['twitter_username']) : '';
        
        update_option('coco_seo_global_index', $global_index);
        update_option('coco_seo_global_follow', $global_follow);
        update_option('coco_seo_twitter_username', $twitter_username);
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }
    
    // Get current settings
    $global_index = get_option('coco_seo_global_index', 'index');
    $global_follow = get_option('coco_seo_global_follow', 'follow');
    $twitter_username = get_option('coco_seo_twitter_username', '');
    
    ?>
    <div class="wrap">
        <h1>SEO Settings</h1>
        
        <form method="post">
            <?php wp_nonce_field('coco_seo_settings_nonce'); ?>
            
            <div class="coco-seo-settings-section">
                <h2>Global Settings</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Global Index</th>
                        <td>
                            <select name="global_index">
                                <option value="index" <?php selected($global_index, 'index'); ?>>Index</option>
                                <option value="noindex" <?php selected($global_index, 'noindex'); ?>>Noindex</option>
                            </select>
                            <p class="description">Default indexing behavior. Individual pages can override this.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Global Follow</th>
                        <td>
                            <select name="global_follow">
                                <option value="follow" <?php selected($global_follow, 'follow'); ?>>Follow</option>
                                <option value="nofollow" <?php selected($global_follow, 'nofollow'); ?>>Nofollow</option>
                            </select>
                            <p class="description">Default link following behavior. Individual pages can override this.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Twitter Username</th>
                        <td>
                            <input type="text" name="twitter_username" value="<?php echo esc_attr($twitter_username); ?>" class="regular-text">
                            <p class="description">Your Twitter username (without @)</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="coco_seo_save_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Get SEO statistics
 * 
 * @return array Statistics
 */
function coco_get_seo_stats()
{
    global $wpdb;
    
    // Get all public post types
    $registered_post_types = get_post_types(['public' => true]);
    
    // Remove attachment post type
    if (isset($registered_post_types['attachment'])) {
        unset($registered_post_types['attachment']);
    }
    
    $post_types_str = "'" . implode("','", array_map('esc_sql', $registered_post_types)) . "'";
    
    // Query for post statistics
    $stats_query = "
        SELECT 
            SUM(CASE WHEN pm1.meta_value IS NOT NULL AND pm1.meta_value != '' THEN 1 ELSE 0 END) as with_meta,
            SUM(CASE WHEN pm1.meta_value IS NULL OR pm1.meta_value = '' THEN 1 ELSE 0 END) as without_meta,
            SUM(CASE WHEN pm2.meta_value LIKE '%noindex%' THEN 1 ELSE 0 END) as not_indexed,
            SUM(CASE WHEN pm2.meta_value IS NULL OR pm2.meta_value NOT LIKE '%noindex%' THEN 1 ELSE 0 END) as indexed,
            COUNT(*) as total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_coco_meta_title'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_coco_meta_index_follow'
        WHERE p.post_status = 'publish'
        AND p.post_type IN ({$post_types_str})
    ";
    
    $results = $wpdb->get_row($stats_query, ARRAY_A);
    
    // Default values if query fails
    if (!$results) {
        return [
            'with_meta' => 0,
            'without_meta' => 0,
            'indexed' => 0,
            'not_indexed' => 0,
            'no_check' => 0,
        ];
    }
    
    return [
        'with_meta' => (int) $results['with_meta'],
        'without_meta' => (int) $results['without_meta'],
        'indexed' => (int) $results['indexed'],
        'not_indexed' => (int) $results['not_indexed'],
        'no_check' => 0, // Not used in this version
    ];
}