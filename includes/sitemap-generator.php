<?php
/**
 * Sitemap generator for Coco SEO Plugin
 * 
 * Generates XML sitemaps for all public post types and handles
 * automatic regeneration when content changes.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define throttle interval for sitemap regeneration
 */
define('COCO_SITEMAP_REGEN_INTERVAL', 600); // 10 minutes

/**
 * Generate the sitemap index
 */
function coco_generate_grouped_sitemap() {
    // Skip if recently generated (prevents multiple regenerations during bulk operations)
    if (get_transient('coco_sitemap_last_generated')) {
        return;
    }

    // Get all public post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    // Build sitemap index
    $sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    // Loop through post types and generate sitemaps
    foreach ($post_types as $post_type) {
        // Skip attachments
        if ($post_type->name === 'attachment') {
            continue;
        }

        // Get post count for this type
        $count = wp_count_posts($post_type->name);
        
        // Skip if no published posts
        if (!isset($count->publish) || $count->publish < 1) {
            continue;
        }

        // Generate individual sitemap
        if (coco_generate_individual_sitemap($post_type->name, $post_type->labels->name)) {
            $sitemap_index .= '  <sitemap>' . PHP_EOL;
            $sitemap_index .= '    <loc>' . esc_url(home_url('/sitemap-' . $post_type->name . '.xml')) . '</loc>' . PHP_EOL;
            $sitemap_index .= '    <lastmod>' . date('Y-m-d\TH:i:s\Z') . '</lastmod>' . PHP_EOL;
            $sitemap_index .= '  </sitemap>' . PHP_EOL;
        }
    }

    $sitemap_index .= '</sitemapindex>';

    // Save sitemap index
    $sitemap_index_path = ABSPATH . 'sitemap.xml';
    if (!coco_save_file($sitemap_index_path, $sitemap_index)) {
        error_log('Failed to write sitemap index to ' . $sitemap_index_path);
    }

    // Set transient to prevent frequent regeneration
    set_transient('coco_sitemap_last_generated', time(), COCO_SITEMAP_REGEN_INTERVAL);
}

/**
 * Generate individual sitemap for a post type
 *
 * @param string $post_type Post type slug
 * @param string $label Post type label
 * @return bool Success status
 */
function coco_generate_individual_sitemap($post_type, $label) {
    // Get published posts with a relatively high limit (could paginate for very large sites)
    $posts = get_posts([
        'numberposts' => 5000, // Reasonable limit for most sites
        'post_type' => $post_type,
        'post_status' => 'publish',
    ]);

    // Skip if no posts
    if (empty($posts)) {
        return false;
    }

    // Build sitemap
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' .
               ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
               ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' .
               ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
    $sitemap .= "<!-- Group: $label -->" . PHP_EOL;

    foreach ($posts as $post) {
        // Skip noindex posts
        $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);
        if ($meta_index_follow === 'noindex nofollow' || $meta_index_follow === 'noindex follow') {
            continue;
        }

        // Get permalink and last modified date
        $permalink = get_permalink($post->ID);
        
        // Skip if permalink is empty
        if (empty($permalink)) {
            continue;
        }
        
        // Get post modified time in correct format
        $last_mod = get_the_modified_time('Y-m-d\TH:i:s\Z', $post->ID);
        if (empty($last_mod)) {
            $last_mod = date('Y-m-d\TH:i:s\Z', strtotime($post->post_modified_gmt));
        }

        // Calculate priority based on post type and date
        $priority = 0.5; // Default priority
        
        // Higher priority for newer content
        $days_old = max(1, floor((time() - strtotime($post->post_date_gmt)) / DAY_IN_SECONDS));
        if ($days_old < 7) {
            $priority = 0.9; // Last week
        } elseif ($days_old < 30) {
            $priority = 0.8; // Last month
        } elseif ($days_old < 90) {
            $priority = 0.7; // Last quarter
        } elseif ($days_old < 365) {
            $priority = 0.6; // Last year
        }
        
        // Higher priority for important post types
        if ($post_type === 'page') {
            $priority += 0.1; // Pages are usually more important
        }
        
        // Cap priority at 1.0
        $priority = min(1.0, $priority);
        
        // Add URL to sitemap
        $sitemap .= '  <url>' . PHP_EOL;
        $sitemap .= '    <loc>' . esc_url($permalink) . '</loc>' . PHP_EOL;
        $sitemap .= '    <lastmod>' . esc_html($last_mod) . '</lastmod>' . PHP_EOL;
        $sitemap .= '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
        
        // Add change frequency based on post age
        if ($days_old < 7) {
            $sitemap .= '    <changefreq>daily</changefreq>' . PHP_EOL;
        } elseif ($days_old < 30) {
            $sitemap .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
        } elseif ($days_old < 90) {
            $sitemap .= '    <changefreq>monthly</changefreq>' . PHP_EOL;
        } else {
            $sitemap .= '    <changefreq>yearly</changefreq>' . PHP_EOL;
        }
        
        $sitemap .= '  </url>' . PHP_EOL;
    }

    $sitemap .= '</urlset>';

    // Save sitemap
    $file_path = ABSPATH . 'sitemap-' . $post_type . '.xml';
    if (!coco_save_file($file_path, $sitemap)) {
        error_log('Failed to write sitemap for ' . $post_type . ' to ' . $file_path);
        return false;
    }

    return true;
}

/**
 * Save file using WordPress filesystem
 *
 * @param string $file_path File path
 * @param string $content File content
 * @return bool Success status
 */
function coco_save_file($file_path, $content) {
    // Try direct file writing first (faster)
    $result = @file_put_contents($file_path, $content);
    
    // If direct writing fails, try filesystem API
    if ($result === false) {
        // Include WordPress filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // Initialize filesystem
        if (WP_Filesystem()) {
            global $wp_filesystem;
            $result = $wp_filesystem->put_contents($file_path, $content, FS_CHMOD_FILE);
        }
    }
    
    return ($result !== false);
}

/**
 * Register rewrite rules for sitemaps
 */
function coco_register_sitemap_rewrites() {
    // Main sitemap index
    add_rewrite_rule(
        '^sitemap\.xml$',
        'index.php?coco_sitemap=index',
        'top'
    );
    
    // Individual post type sitemaps
    add_rewrite_rule(
        '^sitemap-([^/]+)\.xml$',
        'index.php?coco_sitemap=$matches[1]',
        'top'
    );
    
    // Add query var
    add_filter('query_vars', function($vars) {
        $vars[] = 'coco_sitemap';
        return $vars;
    });
}
add_action('init', 'coco_register_sitemap_rewrites');

/**
 * Handle sitemap requests
 */
function coco_handle_sitemap_requests() {
    global $wp_query;
    
    $sitemap = get_query_var('coco_sitemap');
    
    if (empty($sitemap)) {
        return;
    }
    
    // Send XML content type header
    header('Content-Type: application/xml; charset=UTF-8');
    
    // No caching for development, but consider enabling in production
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Check if we need to regenerate the sitemap
    $should_regenerate = false;
    
    // Always regenerate if requested explicitly or in debug mode
    if (isset($_GET['regenerate']) || WP_DEBUG) {
        $should_regenerate = true;
    }
    
    // Handle sitemap index or post type specific sitemap
    if ($sitemap === 'index') {
        $sitemap_path = ABSPATH . 'sitemap.xml';
        
        // Generate if file doesn't exist or regeneration requested
        if (!file_exists($sitemap_path) || $should_regenerate) {
            coco_generate_grouped_sitemap();
        }
        
        // Output the sitemap
        if (file_exists($sitemap_path)) {
            echo file_get_contents($sitemap_path);
        } else {
            echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
        }
    } else {
        // Get post type
        $post_type = sanitize_key($sitemap);
        
        // Verify post type exists
        if (!post_type_exists($post_type)) {
            status_header(404);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Invalid sitemap requested</error>';
            exit;
        }
        
        $sitemap_path = ABSPATH . 'sitemap-' . $post_type . '.xml';
        
        // Generate if file doesn't exist or regeneration requested
        if (!file_exists($sitemap_path) || $should_regenerate) {
            $post_type_obj = get_post_type_object($post_type);
            coco_generate_individual_sitemap($post_type, $post_type_obj->labels->name);
        }
        
        // Output the sitemap
        if (file_exists($sitemap_path)) {
            echo file_get_contents($sitemap_path);
        } else {
            // Output empty sitemap if generation failed
            echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        }
    }
    
    exit;
}
add_action('template_redirect', 'coco_handle_sitemap_requests');

/**
 * Invalidate sitemap cache when content changes
 * 
 * @param int $post_id Post ID
 * @param WP_Post|null $post Post object
 */
function coco_invalidate_sitemap_cache($post_id, $post = null) {
    // Skip auto-saves and revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    
    // Skip non-public post types
    $post_type = get_post_type($post_id);
    if ($post_type) {
        $post_type_obj = get_post_type_object($post_type);
        if (!$post_type_obj || !$post_type_obj->public) {
            return;
        }
    }
    
    // Delete the transient to allow regeneration
    delete_transient('coco_sitemap_last_generated');
    
    // Schedule regeneration
    wp_schedule_single_event(time() + 10, 'coco_regenerate_sitemaps');
}

/**
 * Register hooks to trigger sitemap regeneration
 */
function coco_register_sitemap_hooks() {
    // Post status changes
    add_action('save_post', 'coco_invalidate_sitemap_cache', 10, 2);
    add_action('delete_post', 'coco_invalidate_sitemap_cache');
    add_action('trash_post', 'coco_invalidate_sitemap_cache');
    add_action('publish_post', 'coco_invalidate_sitemap_cache');
    add_action('publish_page', 'coco_invalidate_sitemap_cache');
    
    // Custom post types
    $post_types = get_post_types(['public' => true]);
    foreach ($post_types as $post_type) {
        if ($post_type !== 'post' && $post_type !== 'page') {
            add_action("publish_{$post_type}", 'coco_invalidate_sitemap_cache');
        }
    }
    
    // Other events that should trigger regeneration
    add_action('edited_terms', 'coco_invalidate_sitemap_cache');
    add_action('switch_theme', 'coco_invalidate_sitemap_cache');
    
    // Register regeneration action
    add_action('coco_regenerate_sitemaps', 'coco_generate_grouped_sitemap');
}
add_action('init', 'coco_register_sitemap_hooks');