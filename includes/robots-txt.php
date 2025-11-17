<?php
/**
 * Robots.txt functionality for Coco SEO Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Update robots.txt content
 */
function coco_check_update_robots() {
    // Define the robots.txt path
    $robots_file = ABSPATH . 'robots.txt';

    // Get public post types
    $post_types = get_post_types(['public' => true], 'objects');
    $sitemaps = [];
    
    foreach ($post_types as $post_type => $details) {
        $sitemaps[] = 'Sitemap: ' . home_url('/sitemap-' . $post_type . '.xml');
    }
    
    $sitemap_content = implode(PHP_EOL, $sitemaps);
    $default_content = "User-agent: *" . PHP_EOL . "Disallow:" . PHP_EOL . $sitemap_content . PHP_EOL;

    // If robots.txt doesn't exist, create it
    if (!file_exists($robots_file)) {
        @file_put_contents($robots_file, $default_content, LOCK_EX);
        return;
    }

    // Update existing robots.txt
    $existing_content = @file_get_contents($robots_file);
    if ($existing_content === false) {
        return;
    }

    // Remove existing sitemap lines
    $pattern = '/^(Sitemap:\s*.*)$/im';
    $content_without_sitemaps = preg_replace($pattern, '', $existing_content);
    
    // Add new sitemap content
    $updated_content = trim($content_without_sitemaps) . PHP_EOL . $sitemap_content . PHP_EOL;

    // Only update if changed
    if (trim($existing_content) !== trim($updated_content)) {
        @file_put_contents($robots_file, $updated_content, LOCK_EX);
    }
}

// Register hook
add_action('init', 'coco_check_update_robots');