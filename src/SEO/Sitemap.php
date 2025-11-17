<?php
declare(strict_types=1);

namespace CocoSEO\SEO;

use CocoSEO\Core\Settings;
use CocoSEO\Core\Error;

/**
 * XML Sitemap generation class
 */
class Sitemap {
    /**
     * Maximum URLs per sitemap
     */
    private const MAX_URLS_PER_SITEMAP = 2000;
    
    /**
     * Sitemap update throttle interval in seconds (1 hour)
     */
    private const UPDATE_THROTTLE = 3600;
    
    /**
     * Register hooks
     */
    public function register(): void {
        // Register sitemap rewrite rules
        add_action('init', [$this, 'registerRewriteRules']);
        
        // Handle sitemap requests
        add_action('template_redirect', [$this, 'handleSitemapRequests']);
        
        // Generate sitemap on save/update
        add_action('save_post', [$this, 'invalidateSitemapCache'], 10, 2);
        add_action('delete_post', [$this, 'invalidateSitemapCache']);
        add_action('switch_theme', [$this, 'invalidateSitemapCache']);
        
        // Schedule sitemap regeneration
        add_action('coco_seo_generate_sitemap', [$this, 'generateSitemaps']);
        
        // Ensure the schedule is set
        if (!wp_next_scheduled('coco_seo_generate_sitemap')) {
            wp_schedule_event(time(), 'daily', 'coco_seo_generate_sitemap');
        }
    }
    
    /**
     * Register rewrite rules for sitemaps
     */
    public function registerRewriteRules(): void {
        // Main sitemap
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?coco_sitemap=index',
            'top'
        );
        
        // Post type sitemaps
        add_rewrite_rule(
            '^sitemap-([^/]+)\.xml$',
            'index.php?coco_sitemap=$matches[1]',
            'top'
        );
        
        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'coco_sitemap';
            return $vars;
        });
        
        // Flush rewrite rules if needed (only in admin to avoid performance issues)
        if (is_admin()) {
            $flush_rewrite = get_option('coco_seo_flush_rewrite', false);
            
            if ($flush_rewrite) {
                flush_rewrite_rules();
                update_option('coco_seo_flush_rewrite', false);
            }
        }
    }
    
    /**
     * Handle sitemap requests
     */
    public function handleSitemapRequests(): void {
        $sitemap = get_query_var('coco_sitemap');
        
        if (empty($sitemap)) {
            return;
        }
        
        // Send XML content type header
        header('Content-Type: application/xml; charset=UTF-8');
        
        // No caching for sitemap
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        if ($sitemap === 'index') {
            $this->outputSitemapIndex();
        } else {
            $this->outputSitemap($sitemap);
        }
        
        exit;
    }
    
    /**
     * Output sitemap index
     */
    private function outputSitemapIndex(): void {
        // Check if cached sitemap index exists and is valid
        $cache_key = 'coco_sitemap_index';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            echo $cached;
            return;
        }
        
        // Start XML
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $output .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        // Get enabled post types
        $post_types = Settings::get('post_types', ['post', 'page']);
        
        foreach ($post_types as $post_type) {
            // Check if post type has any published posts
            $count = wp_count_posts($post_type);
            
            if (!isset($count->publish) || $count->publish < 1) {
                continue;
            }
            
            // Add sitemap
            $output .= '  <sitemap>' . PHP_EOL;
            $output .= '    <loc>' . esc_url(home_url("/sitemap-{$post_type}.xml")) . '</loc>' . PHP_EOL;
            $output .= '    <lastmod>' . date('c') . '</lastmod>' . PHP_EOL;
            $output .= '  </sitemap>' . PHP_EOL;
        }
        
        $output .= '</sitemapindex>';
        
        // Cache sitemap index
        set_transient($cache_key, $output, self::UPDATE_THROTTLE);
        
        echo $output;
    }
    
    /**
     * Output specific sitemap
     * 
     * @param string $post_type The post type
     */
    private function outputSitemap(string $post_type): void {
        // Validate post type
        if (!post_type_exists($post_type)) {
            status_header(404);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Invalid sitemap.</error>';
            return;
        }
        
        // Check if post type is enabled
        $enabled_post_types = Settings::get('post_types', ['post', 'page']);
        if (!in_array($post_type, $enabled_post_types, true)) {
            status_header(404);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Post type not enabled for SEO.</error>';
            return;
        }
        
        // Check if cached sitemap exists and is valid
        $cache_key = "coco_sitemap_{$post_type}";
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            echo $cached;
            return;
        }
        
        // Start XML
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' .
                  ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
                  ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' .
                  ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
        
        // Get posts
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);
        
        foreach ($posts as $post) {
            // Skip posts marked as noindex
            $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);
            if (str_contains($meta_index_follow, 'noindex')) {
                continue;
            }
            
            // Get permalink and last modified date
            $permalink = get_permalink($post->ID);
            $last_mod = get_post_modified_time('c', true, $post->ID);
            
            // Add URL to sitemap
            $output .= '  <url>' . PHP_EOL;
            $output .= '    <loc>' . esc_url($permalink) . '</loc>' . PHP_EOL;
            $output .= '    <lastmod>' . esc_html($last_mod) . '</lastmod>' . PHP_EOL;
            $output .= '  </url>' . PHP_EOL;
        }
        
        $output .= '</urlset>';
        
        // Cache sitemap
        set_transient($cache_key, $output, self::UPDATE_THROTTLE);
        
        echo $output;
    }
    
    /**
     * Generate all sitemaps
     */
    public function generateSitemaps(): void {
        try {
            // Set error handler
            do_action('coco_seo_before_operation');
            
            // Get enabled post types
            $post_types = Settings::get('post_types', ['post', 'page']);
            
            // Invalidate existing sitemap caches
            $this->invalidateSitemapCache();
            
            // Force generation of sitemap index
            $this->generateSitemapIndex();
            
            // Force generation of all post type sitemaps
            foreach ($post_types as $post_type) {
                $this->generatePostTypeSitemap($post_type);
            }
        } catch (\Exception $e) {
            Error::logException($e);
        } finally {
            // Restore error handler
            do_action('coco_seo_after_operation');
        }
    }
    
    /**
     * Generate sitemap index
     * 
     * @return string The sitemap index content
     */
    private function generateSitemapIndex(): string {
        // Start XML
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $output .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        // Get enabled post types
        $post_types = Settings::get('post_types', ['post', 'page']);
        
        foreach ($post_types as $post_type) {
            // Check if post type has any published posts
            $count = wp_count_posts($post_type);
            
            if (!isset($count->publish) || $count->publish < 1) {
                continue;
            }
            
            // Add sitemap
            $output .= '  <sitemap>' . PHP_EOL;
            $output .= '    <loc>' . esc_url(home_url("/sitemap-{$post_type}.xml")) . '</loc>' . PHP_EOL;
            $output .= '    <lastmod>' . date('c') . '</lastmod>' . PHP_EOL;
            $output .= '  </sitemap>' . PHP_EOL;
        }
        
        $output .= '</sitemapindex>';
        
        // Cache sitemap index
        set_transient('coco_sitemap_index', $output, self::UPDATE_THROTTLE);
        
        return $output;
    }
    
    /**
     * Generate sitemap for specific post type
     * 
     * @param string $post_type The post type
     * @return string The sitemap content
     */
    private function generatePostTypeSitemap(string $post_type): string {
        // Validate post type
        if (!post_type_exists($post_type)) {
            return '';
        }
        
        // Start XML
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' .
                  ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
                  ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' .
                  ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
        
        // Get posts
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
        ]);
        
        foreach ($posts as $post) {
            // Skip posts marked as noindex
            $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);
            if (str_contains($meta_index_follow, 'noindex')) {
                continue;
            }
            
            // Get permalink and last modified date
            $permalink = get_permalink($post->ID);
            $last_mod = get_post_modified_time('c', true, $post->ID);
            
            // Add URL to sitemap
            $output .= '  <url>' . PHP_EOL;
            $output .= '    <loc>' . esc_url($permalink) . '</loc>' . PHP_EOL;
            $output .= '    <lastmod>' . esc_html($last_mod) . '</lastmod>' . PHP_EOL;
            $output .= '  </url>' . PHP_EOL;
        }
        
        $output .= '</urlset>';
        
        // Cache sitemap
        set_transient("coco_sitemap_{$post_type}", $output, self::UPDATE_THROTTLE);
        
        return $output;
    }
    
    /**
     * Invalidate sitemap cache on content updates
     * 
     * @param int $post_id The post ID
     * @param \WP_Post|null $post The post object
     */
    public function invalidateSitemapCache(int $post_id = 0, ?\WP_Post $post = null): void {
        // Delete sitemap index cache
        delete_transient('coco_sitemap_index');
        
        // If we have a specific post, only invalidate that post type's sitemap
        if ($post && $post->post_status === 'publish') {
            delete_transient("coco_sitemap_{$post->post_type}");
            return;
        }
        
        // Otherwise, invalidate all sitemaps
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_coco_sitemap_%'");
    }
}