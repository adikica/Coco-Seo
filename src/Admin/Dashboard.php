<?php
declare(strict_types=1);

namespace CocoSEO\Admin;

use CocoSEO\Core\Settings;

/**
 * Admin dashboard class
 */
class Dashboard {
    /**
     * Register hooks
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'registerMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_coco_seo_save_settings', [$this, 'ajaxSaveSettings']);
    }
    
    /**
     * Register admin menu page
     */
    public function registerMenuPage(): void {
        add_menu_page(
            __('Coco SEO', 'coco-seo'),
            __('Coco SEO', 'coco-seo'),
            'manage_options',
            'coco-seo',
            [$this, 'renderDashboard'],
            'dashicons-admin-site-alt3',
            25
        );
        
        add_submenu_page(
            'coco-seo',
            __('Dashboard', 'coco-seo'),
            __('Dashboard', 'coco-seo'),
            'manage_options',
            'coco-seo',
            [$this, 'renderDashboard']
        );
        
        add_submenu_page(
            'coco-seo',
            __('SEO Status', 'coco-seo'),
            __('SEO Status', 'coco-seo'),
            'manage_options',
            'coco-seo-status',
            [new Table(), 'renderPage']
        );
        
        add_submenu_page(
            'coco-seo',
            __('Settings', 'coco-seo'),
            __('Settings', 'coco-seo'),
            'manage_options',
            'coco-seo-settings',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets(string $hook): void {
        if (!str_contains($hook, 'coco-seo')) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'coco-seo-admin',
            COCO_SEO_URL . 'assets/css/admin.css',
            [],
            COCO_SEO_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'coco-seo-admin',
            COCO_SEO_URL . 'assets/js/admin.js',
            ['jquery'],
            COCO_SEO_VERSION,
            true
        );
        
        // Pass data to JS
        wp_localize_script('coco-seo-admin', 'cocoSEO', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('coco_seo_nonce'),
            'settings' => Settings::getAll(),
        ]);
    }
    
    /**
     * Render dashboard page
     */
    public function renderDashboard(): void {
        // Get SEO stats
        $stats = $this->getSEOStats();
        
        // Output HTML
        ?>
        <div class="wrap coco-seo-dashboard">
            <h1><?php echo esc_html__('Coco SEO Dashboard', 'coco-seo'); ?></h1>
            
            <div class="coco-seo-cards">
                <div class="coco-seo-card">
                    <h2><?php echo esc_html__('Indexing Overview', 'coco-seo'); ?></h2>
                    <div class="coco-seo-card-content">
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo esc_html($stats['indexed']); ?></span>
                            <span class="coco-seo-stat-label"><?php echo esc_html__('Indexed', 'coco-seo'); ?></span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo esc_html($stats['not_indexed']); ?></span>
                            <span class="coco-seo-stat-label"><?php echo esc_html__('Not Indexed', 'coco-seo'); ?></span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo esc_html($stats['no_check']); ?></span>
                            <span class="coco-seo-stat-label"><?php echo esc_html__('Not Checked', 'coco-seo'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="coco-seo-card">
                    <h2><?php echo esc_html__('SEO Health', 'coco-seo'); ?></h2>
                    <div class="coco-seo-card-content">
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo esc_html($stats['with_meta']); ?></span>
                            <span class="coco-seo-stat-label"><?php echo esc_html__('With Meta Data', 'coco-seo'); ?></span>
                        </div>
                        <div class="coco-seo-stat">
                            <span class="coco-seo-stat-value"><?php echo esc_html($stats['without_meta']); ?></span>
                            <span class="coco-seo-stat-label"><?php echo esc_html__('Missing Meta', 'coco-seo'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="coco-seo-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=coco-seo-status')); ?>" class="button button-primary">
                    <?php echo esc_html__('View SEO Status', 'coco-seo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=coco-seo-settings')); ?>" class="button">
                    <?php echo esc_html__('Configure Settings', 'coco-seo'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function renderSettings(): void {
        $settings = Settings::getAll();
        $post_types = get_post_types(['public' => true], 'objects');
        
        ?>
        <div class="wrap coco-seo-settings">
            <h1><?php echo esc_html__('Coco SEO Settings', 'coco-seo'); ?></h1>
            
            <form id="coco-seo-settings-form" method="post">
                <?php wp_nonce_field('coco_seo_settings', 'coco_seo_settings_nonce'); ?>
                
                <div class="coco-seo-settings-section">
                    <h2><?php echo esc_html__('Global Settings', 'coco-seo'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Global Index', 'coco-seo'); ?></th>
                            <td>
                                <select name="coco_seo_settings[global_index]">
                                    <option value="index" <?php selected($settings['global_index'], 'index'); ?>>
                                        <?php echo esc_html__('Index', 'coco-seo'); ?>
                                    </option>
                                    <option value="noindex" <?php selected($settings['global_index'], 'noindex'); ?>>
                                        <?php echo esc_html__('Noindex', 'coco-seo'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('Default indexing behavior. Individual pages can override this.', 'coco-seo'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php echo esc_html__('Global Follow', 'coco-seo'); ?></th>
                            <td>
                                <select name="coco_seo_settings[global_follow]">
                                    <option value="follow" <?php selected($settings['global_follow'], 'follow'); ?>>
                                        <?php echo esc_html__('Follow', 'coco-seo'); ?>
                                    </option>
                                    <option value="nofollow" <?php selected($settings['global_follow'], 'nofollow'); ?>>
                                        <?php echo esc_html__('Nofollow', 'coco-seo'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('Default link following behavior. Individual pages can override this.', 'coco-seo'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php echo esc_html__('Twitter Username', 'coco-seo'); ?></th>
                            <td>
                                <input type="text" name="coco_seo_settings[twitter_username]" value="<?php echo esc_attr($settings['twitter_username']); ?>" class="regular-text">
                                <p class="description">
                                    <?php echo esc_html__('Your Twitter username (without @)', 'coco-seo'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php echo esc_html__('Google API Key', 'coco-seo'); ?></th>
                            <td>
                                <input type="text" name="coco_seo_settings[google_api_key]" value="<?php echo esc_attr($settings['google_api_key']); ?>" class="regular-text">
                                <p class="description">
                                    <?php echo esc_html__('Required for Google indexing check functionality', 'coco-seo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="coco-seo-settings-section">
                    <h2><?php echo esc_html__('Post Types', 'coco-seo'); ?></h2>
                    <p><?php echo esc_html__('Select post types to include in SEO management:', 'coco-seo'); ?></p>
                    
                    <table class="form-table">
                        <?php foreach ($post_types as $post_type): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($post_type->label); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="coco_seo_settings[post_types][]" 
                                               value="<?php echo esc_attr($post_type->name); ?>"
                                               <?php checked(in_array($post_type->name, $settings['post_types'], true)); ?>>
                                        <?php echo esc_html__('Enable', 'coco-seo'); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="coco-seo-save-settings">
                        <?php echo esc_html__('Save Settings', 'coco-seo'); ?>
                    </button>
                    <span class="spinner"></span>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajaxSaveSettings(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'coco_seo_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'coco-seo')]);
        }
        
        // Verify user can manage options
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'coco-seo')]);
        }
        
        // Get and sanitize settings
        $settings = [];
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            // Get settings and filter through our Settings class
            $input = wp_unslash($_POST['settings']);
            update_option('coco_seo_settings', $input);
            
            wp_send_json_success([
                'message' => __('Settings saved successfully.', 'coco-seo'),
                'settings' => Settings::getAll(),
            ]);
        }
        
        wp_send_json_error(['message' => __('No settings data received.', 'coco-seo')]);
    }
    
    /**
     * Get SEO statistics for the dashboard
     * 
     * @return array<string, int> SEO statistics
     */
    private function getSEOStats(): array {
        global $wpdb;
        
        // Get enabled post types
        $post_types = Settings::get('post_types', ['post', 'page']);
        $post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
        
        // Get counts of posts with/without meta
        $meta_query = $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN pm1.meta_value IS NOT NULL AND pm1.meta_value != '' THEN 1 ELSE 0 END) as with_meta,
                SUM(CASE WHEN pm1.meta_value IS NULL OR pm1.meta_value = '' THEN 1 ELSE 0 END) as without_meta,
                SUM(CASE WHEN pm2.meta_value = 'indexed' THEN 1 ELSE 0 END) as indexed,
                SUM(CASE WHEN pm2.meta_value = 'not_indexed' THEN 1 ELSE 0 END) as not_indexed,
                SUM(CASE WHEN pm2.meta_value IS NULL THEN 1 ELSE 0 END) as no_check
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
            WHERE p.post_status = 'publish'
            AND p.post_type IN ({$post_types_str})",
            '_coco_meta_title',
            '_coco_indexing_status'
        );
        
        $results = $wpdb->get_row($meta_query, ARRAY_A);
        
        return [
            'with_meta' => (int) ($results['with_meta'] ?? 0),
            'without_meta' => (int) ($results['without_meta'] ?? 0),
            'indexed' => (int) ($results['indexed'] ?? 0),
            'not_indexed' => (int) ($results['not_indexed'] ?? 0),
            'no_check' => (int) ($results['no_check'] ?? 0),
        ];
    }
}