<?php
declare(strict_types=1);

namespace CocoSEO\SEO;

use CocoSEO\Core\Settings;
use CocoSEO\Core\Error;

/**
 * Robots.txt handling class
 */
class Robots {
    /**
     * Register hooks
     */
    public function register(): void {
        // Hook into the robots.txt filter
        add_filter('robots_txt', [$this, 'customizeRobotsTxt'], 10, 2);
        
        // Update robots.txt when settings change
        add_action('update_option_coco_seo_settings', [$this, 'triggerRobotsTxtUpdate']);
        
        // Schedule clean up of private posts that shouldn't be indexed
        add_action('coco_seo_check_private_posts', [$this, 'checkPrivatePosts']);
        
        // Ensure the schedule is set
        if (!wp_next_scheduled('coco_seo_check_private_posts')) {
            wp_schedule_event(time(), 'daily', 'coco_seo_check_private_posts');
        }
    }
    
    /**
     * Customize robots.txt content
     * 
     * @param string $output Current robots.txt content
     * @param bool $public Whether the site is set to be publicly visible
     * @return string Modified robots.txt content
     */
    public function customizeRobotsTxt(string $output, bool $public): string {
        // If the site is not public, block everything
        if (!$public) {
            return "User-agent: *\nDisallow: /";
        }
        
        try {
            // Set error handler
            do_action('coco_seo_before_operation');
            
            // Start with basic rules
            $robots = "User-agent: *\n";
            
            // Add disallow rules for admin, login, etc.
            $robots .= "Disallow: /wp-admin/\n";
            $robots .= "Allow: /wp-admin/admin-ajax.php\n";
            
            // Add custom disallow paths from settings
            $disallow_paths = Settings::get('robots_disallow', []);
            if (!empty($disallow_paths) && is_array($disallow_paths)) {
                foreach ($disallow_paths as $path) {
                    if (!empty($path)) {
                        $robots .= "Disallow: " . esc_html($path) . "\n";
                    }
                }
            }
            
            // Add sitemap references
            $post_types = Settings::get('post_types', ['post', 'page']);
            $robots .= "\n# Sitemaps\n";
            $robots .= "Sitemap: " . esc_url(home_url('/sitemap.xml')) . "\n";
            
            foreach ($post_types as $post_type) {
                $count = wp_count_posts($post_type);
                if (isset($count->publish) && $count->publish > 0) {
                    $robots .= "Sitemap: " . esc_url(home_url("/sitemap-{$post_type}.xml")) . "\n";
                }
            }
            
            // Allow filtering the robots.txt content
            $robots = apply_filters('coco_seo_robots_txt', $robots);
            
            return $robots;
        } catch (\Exception $e) {
            Error::logException($e);
            return $output;
        } finally {
            // Restore error handler
            do_action('coco_seo_after_operation');
        }
    }
    
    /**
     * Trigger robots.txt update when settings change
     */
    public function triggerRobotsTxtUpdate(): void {
        if (function_exists('save_mod_rewrite_rules')) {
            save_mod_rewrite_rules();
        }
    }
    
    /**
     * Check and mark private posts as noindex
     */
    public function checkPrivatePosts(): void {
        try {
            // Set error handler
            do_action('coco_seo_before_operation');
            
            // Get all private posts
            $private_posts = get_posts([
                'post_status' => 'private',
                'post_type' => Settings::get('post_types', ['post', 'page']),
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            
            foreach ($private_posts as $post_id) {
                // Get current setting
                $meta_index_follow = get_post_meta($post_id, '_coco_meta_index_follow', true);
                
                // If not already set to noindex, update it
                if ($meta_index_follow !== 'noindex nofollow') {
                    update_post_meta($post_id, '_coco_meta_index_follow', 'noindex nofollow');
                }
            }
            
            // Get all draft posts
            $draft_posts = get_posts([
                'post_status' => 'draft',
                'post_type' => Settings::get('post_types', ['post', 'page']),
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            
            foreach ($draft_posts as $post_id) {
                // Get current setting
                $meta_index_follow = get_post_meta($post_id, '_coco_meta_index_follow', true);
                
                // If not already set to noindex, update it
                if ($meta_index_follow !== 'noindex nofollow') {
                    update_post_meta($post_id, '_coco_meta_index_follow', 'noindex nofollow');
                }
            }
        } catch (\Exception $e) {
            Error::logException($e);
        } finally {
            // Restore error handler
            do_action('coco_seo_after_operation');
        }
    }
    
    /**
     * Get physical robots.txt path
     * 
     * @return string Path to robots.txt
     */
    private function getRobotsFilePath(): string {
        return ABSPATH . 'robots.txt';
    }
    
    /**
     * Check if a physical robots.txt file exists
     * 
     * @return bool Whether file exists
     */
    public function physicalFileExists(): bool {
        return file_exists($this->getRobotsFilePath());
    }
    
    /**
     * Read physical robots.txt file content
     * 
     * @return string File content or empty string on failure
     */
    public function readPhysicalFile(): string {
        $file_path = $this->getRobotsFilePath();
        
        if (!$this->physicalFileExists()) {
            return '';
        }
        
        $content = file_get_contents($file_path);
        
        return $content !== false ? $content : '';
    }
    
    /**
     * Create/update physical robots.txt file
     * 
     * @param string $content File content
     * @return bool Whether operation was successful
     */
    public function writePhysicalFile(string $content): bool {
        $file_path = $this->getRobotsFilePath();
        
        // Make sure we have write permissions
        if (file_exists($file_path) && !is_writable($file_path)) {
            return false;
        }
        
        // Check if web server has write access to ABSPATH
        if (!is_writable(ABSPATH)) {
            return false;
        }
        
        // Try to write the file
        $result = file_put_contents($file_path, $content, LOCK_EX);
        
        return $result !== false;
    }
    
    /**
     * Delete physical robots.txt file
     * 
     * @return bool Whether operation was successful
     */
    public function deletePhysicalFile(): bool {
        $file_path = $this->getRobotsFilePath();
        
        if (!$this->physicalFileExists()) {
            return true;
        }
        
        // Make sure we have write permissions
        if (!is_writable($file_path)) {
            return false;
        }
        
        return unlink($file_path);
    }
}