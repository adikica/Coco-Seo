<?php
declare(strict_types=1);

namespace CocoSEO\SEO;

use CocoSEO\Core\Settings;

/**
 * Meta tags generation class
 */
class Meta {
    /**
     * Register hooks
     */
    public function register(): void {
        action('wp_head', [$this, 'outputMetaTags'], 5);
        add_filter('pre_get_document_title', [$this, 'customizeTitle'], 15);
        add_filter('wpseo_title', [$this, 'disableYoastTitle'], 100);
        add_filter('wpseo_metadesc', [$this, 'disableYoastDescription'], 100);
    }
    
    /**
     * Output meta tags in head
     */
    public function outputMetaTags(): void {
        // Only output on singular content
        if (!is_singular()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        // Get meta data
        $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_coco_meta_description', true);
        $meta_index_follow = get_post_meta($post->ID, '_coco_meta_index_follow', true);
        
        // Get fallbacks if needed
        if (empty($meta_title)) {
            $meta_title = $post->post_title;
        }
        
        if (empty($meta_description)) {
            $meta_description = $this->getFallbackDescription($post);
        }
        
        if (empty($meta_index_follow)) {
            // Use global settings as default
            $global_index = Settings::get('global_index', 'index');
            $global_follow = Settings::get('global_follow', 'follow');
            $meta_index_follow = "{$global_index} {$global_follow}";
        }
        
        // Get featured image
        $featured_image_url = $this->getFeaturedImageUrl($post);
        
        // Get site info
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $post_url = get_permalink($post->ID);
        
        // Robots meta tag
        echo "<meta name=\"robots\" content=\"{$meta_index_follow}, max-image-preview:large\">\n";
        
        // Description meta tag (only if not using Yoast SEO)
        if (!function_exists('wpseo_init')) {
            echo "<meta name=\"description\" content=\"" . esc_attr($meta_description) . "\">\n";
        }
        
        // Open Graph meta tags
        echo "<meta property=\"og:locale\" content=\"" . esc_attr(get_locale()) . "\">\n";
        echo "<meta property=\"og:type\" content=\"article\">\n";
        echo "<meta property=\"og:title\" content=\"" . esc_attr($meta_title) . "\">\n";
        echo "<meta property=\"og:description\" content=\"" . esc_attr($meta_description) . "\">\n";
        echo "<meta property=\"og:url\" content=\"" . esc_url($post_url) . "\">\n";
        echo "<meta property=\"og:site_name\" content=\"" . esc_attr($site_name) . "\">\n";
        
        // OG image
        if (!empty($featured_image_url)) {
            echo "<meta property=\"og:image\" content=\"" . esc_url($featured_image_url) . "\">\n";
            
            // Get image dimensions if possible
            $attachment_id = get_post_thumbnail_id($post->ID);
            if ($attachment_id) {
                $image_data = wp_get_attachment_image_src($attachment_id, 'full');
                if ($image_data) {
                    echo "<meta property=\"og:image:width\" content=\"" . esc_attr((string)$image_data[1]) . "\">\n";
                    echo "<meta property=\"og:image:height\" content=\"" . esc_attr((string)$image_data[2]) . "\">\n";
                }
            }
        }
        
        // Twitter Card meta tags
        echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "<meta name=\"twitter:title\" content=\"" . esc_attr($meta_title) . "\">\n";
        echo "<meta name=\"twitter:description\" content=\"" . esc_attr($meta_description) . "\">\n";
        
        // Twitter site
        $twitter_username = Settings::get('twitter_username', '');
        if (!empty($twitter_username)) {
            echo "<meta name=\"twitter:site\" content=\"@" . esc_attr($twitter_username) . "\">\n";
        }
        
        // Twitter image
        if (!empty($featured_image_url)) {
            echo "<meta name=\"twitter:image\" content=\"" . esc_url($featured_image_url) . "\">\n";
        }
        
        // Schema.org JSON-LD structured data
        $this->outputSchemaOrgData($post, $meta_title, $meta_description, $featured_image_url);
    }
    
    /**
     * Output Schema.org JSON-LD data
     *
     * @param \WP_Post $post The post object
     * @param string $title The meta title
     * @param string $description The meta description
     * @param string $image_url The featured image URL
     */
    private function outputSchemaOrgData(\WP_Post $post, string $title, string $description, string $image_url): void {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description,
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
        ];
        
        // Add author info if available
        $author = get_user_by('id', $post->post_author);
        if ($author) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $author->display_name,
                'url' => get_author_posts_url($author->ID),
            ];
        }
        
        // Add publisher info
        $schema['publisher'] = [
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => get_site_icon_url(144),
            ],
        ];
        
        // Add featured image
        if (!empty($image_url)) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $image_url,
            ];
            
            // Add image dimensions if possible
            $attachment_id = get_post_thumbnail_id($post->ID);
            if ($attachment_id) {
                $image_data = wp_get_attachment_image_src($attachment_id, 'full');
                if ($image_data) {
                    $schema['image']['width'] = $image_data[1];
                    $schema['image']['height'] = $image_data[2];
                }
            }
        }
        
        // Add categories as keywords
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $keywords = [];
            foreach ($categories as $category) {
                $keywords[] = $category->name;
            }
            $schema['keywords'] = implode(', ', $keywords);
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * Customize the document title
     *
     * @param string $title The current title
     * @return string The modified title
     */
    public function customizeTitle(string $title): string {
        // Only modify on singular content
        if (!is_singular()) {
            return $title;
        }
        
        global $post;
        if (!$post) {
            return $title;
        }
        
        // Check if Yoast SEO is active
        if (function_exists('wpseo_init')) {
            return $title;
        }
        
        // Get meta title
        $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
        
        if (!empty($meta_title)) {
            return $meta_title;
        }
        
        return $title;
    }
    
    /**
     * Disable Yoast SEO title if our title is set
     *
     * @param string $title The Yoast title
     * @return string The modified title
     */
    public function disableYoastTitle(string $title): string {
        if (!is_singular()) {
            return $title;
        }
        
        global $post;
        if (!$post) {
            return $title;
        }
        
        $meta_title = get_post_meta($post->ID, '_coco_meta_title', true);
        
        if (!empty($meta_title)) {
            return $meta_title;
        }
        
        return $title;
    }
    
    /**
     * Disable Yoast SEO description if our description is set
     *
     * @param string $description The Yoast description
     * @return string The modified description
     */
    public function disableYoastDescription(string $description): string {
        if (!is_singular()) {
            return $description;
        }
        
        global $post;
        if (!$post) {
            return $description;
        }
        
        $meta_description = get_post_meta($post->ID, '_coco_meta_description', true);
        
        if (!empty($meta_description)) {
            return $meta_description;
        }
        
        return $description;
    }
    
    /**
     * Get fallback meta description from post content
     *
     * @param \WP_Post $post The post object
     * @return string The fallback description
     */
    private function getFallbackDescription(\WP_Post $post): string {
        if (has_excerpt($post->ID)) {
            return wp_strip_all_tags(get_the_excerpt($post));
        }
        
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (mb_strlen($content) > 160) {
            $content = mb_substr($content, 0, 157) . '...';
        }
        
        return $content;
    }
    
    /**
     * Get featured image URL
     *
     * @param \WP_Post $post The post object
     * @return string The featured image URL
     */
    private function getFeaturedImageUrl(\WP_Post $post): string {
        if (has_post_thumbnail($post->ID)) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
            if ($image) {
                return $image[0];
            }
        }
        
        // Try to find the first image in the content
        if (preg_match('/<img.+?src="(.+?)"/', $post->post_content, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
}