<?php
declare(strict_types=1);

namespace CocoSEO\Core;

/**
 * Settings management class
 */
class Settings {
    /**
     * Default options
     * 
     * @var array<string, mixed>
     */
    private array $defaults = [
        'global_index' => 'index',
        'global_follow' => 'follow',
        'twitter_username' => '',
        'google_api_key' => '',
        'post_types' => ['post', 'page'],
    ];
    
    /**
     * Settings option name
     */
    private const OPTION_NAME = 'coco_seo_settings';
    
    /**
     * Register hooks
     */
    public function register(): void {
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Register WordPress settings
     */
    public function registerSettings(): void {
        register_setting(
            'coco_seo_options',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->defaults,
            ]
        );
    }
    
    /**
     * Sanitize settings
     *
     * @param array<string, mixed> $input The input array
     * @return array<string, mixed> The sanitized array
     */
    public function sanitizeSettings(array $input): array {
        $sanitized = [];
        
        // Sanitize global index/follow
        $sanitized['global_index'] = isset($input['global_index']) && 
            in_array($input['global_index'], ['index', 'noindex'], true) ? 
            $input['global_index'] : $this->defaults['global_index'];
            
        $sanitized['global_follow'] = isset($input['global_follow']) && 
            in_array($input['global_follow'], ['follow', 'nofollow'], true) ? 
            $input['global_follow'] : $this->defaults['global_follow'];
        
        // Sanitize Twitter username
        $sanitized['twitter_username'] = isset($input['twitter_username']) ? 
            sanitize_text_field($input['twitter_username']) : '';
        
        // Sanitize Google API key
        $sanitized['google_api_key'] = isset($input['google_api_key']) ? 
            sanitize_text_field($input['google_api_key']) : '';
        
        // Sanitize post types
        $sanitized['post_types'] = isset($input['post_types']) && is_array($input['post_types']) ? 
            array_filter($input['post_types'], 'post_type_exists') : $this->defaults['post_types'];
        
        return $sanitized;
    }
    
    /**
     * Get all settings
     *
     * @return array<string, mixed> Settings array
     */
    public static function getAll(): array {
        $options = get_option(self::OPTION_NAME, []);
        $instance = new self();
        
        return wp_parse_args($options, $instance->defaults);
    }
    
    /**
     * Get a specific setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public static function get(string $key, mixed $default = null): mixed {
        $settings = self::getAll();
        
        return $settings[$key] ?? $default;
    }
    
    /**
     * Update a specific setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function update(string $key, mixed $value): bool {
        $settings = self::getAll();
        $settings[$key] = $value;
        
        return update_option(self::OPTION_NAME, $settings);
    }
}