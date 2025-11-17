<?php
/**
 * Helper functions for Coco SEO Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a fallback meta description
 *
 * @param WP_Post $post The post object
 * @return string The fallback description
 */
function coco_get_fallback_description($post)
{
    if (has_excerpt($post->ID)) {
        return wp_trim_words(get_the_excerpt($post->ID), 30, '...');
    }
    return wp_trim_words(strip_tags($post->post_content), 30, '...');
}

/**
 * Get featured image URL
 *
 * @param WP_Post $post The post object
 * @return string The featured image URL or empty string if none
 */
function coco_get_featured_image_url($post)
{
    if (has_post_thumbnail($post->ID)) {
        return wp_get_attachment_url(get_post_thumbnail_id($post->ID));
    }

    $content = apply_filters('the_content', $post->post_content);
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
        return $matches[1];
    }

    return '';
}

/**
 * Check if we're in the transition period
 *
 * @return bool True if in transition
 */
function coco_is_in_transition()
{
    return true; // Always true for v1 migration release
}

/**
 * Log error to file
 *
 * @param string $message Error message
 */
function coco_log_error($message)
{
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit($upload_dir['basedir']) . 'coco-seo-error.log';
    
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $log_file);
}