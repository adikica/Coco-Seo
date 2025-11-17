<?php
declare(strict_types=1);

namespace CocoSEO\Core;

/**
 * Assets management class
 */
class Assets {
    /**
     * Register hooks
     */
    public function register(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_footer', [$this, 'injectAdminScripts']);
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAdminAssets(string $hook): void {
        // Only load on specific pages
        if (!$this->shouldLoadAssets($hook)) {
            return;
        }
        
        // Register and enqueue CSS
        wp_register_style(
            'coco-seo-admin',
            COCO_SEO_URL . 'assets/css/admin.css',
            [],
            COCO_SEO_VERSION
        );
        
        wp_enqueue_style('coco-seo-admin');
        
        // Register and enqueue JavaScript
        wp_register_script(
            'coco-seo-admin',
            COCO_SEO_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch', 'wp-element', 'wp-components'],
            COCO_SEO_VERSION,
            true
        );
        
        wp_enqueue_script('coco-seo-admin');
        
        // Localize script
        wp_localize_script('coco-seo-admin', 'cocoSEO', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('coco-seo/v1'),
            'nonce' => wp_create_nonce('coco_seo_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'settings' => Settings::getAll(),
            'translations' => [
                'checking' => __('Checking...', 'coco-seo'),
                'error' => __('Error', 'coco-seo'),
                'success' => __('Success', 'coco-seo'),
                'savingSettings' => __('Saving settings...', 'coco-seo'),
                'settingsSaved' => __('Settings saved successfully.', 'coco-seo'),
                'runningCheck' => __('Running check...', 'coco-seo'),
                'confirmReset' => __('Are you sure you want to reset all indexing status data? This cannot be undone.', 'coco-seo'),
            ]
        ]);
    }
    
    /**
     * Inject admin scripts for page-specific functionality
     */
    public function injectAdminScripts(): void {
        // Only inject on specific pages
        if (!$this->shouldLoadAssets(get_current_screen()->id ?? '')) {
            return;
        }
        
        if (isset($_GET['page']) && $_GET['page'] === 'coco-seo-status') {
            $this->injectSeoStatusScript();
        }
    }
    
    /**
     * Inject SEO status table enhancement script
     */
    private function injectSeoStatusScript(): void {
        ?>
        <script>
            (function($) {
                $(document).ready(function() {
                    // Handle indexing check buttons
                    $('.coco-seo-check').on('click', function(e) {
                        e.preventDefault();
                        
                        const button = $(this);
                        const postId = button.data('post-id');
                        const nonce = button.data('nonce');
                        const originalText = button.text();
                        
                        // Disable button and show loading
                        button.prop('disabled', true).text(cocoSEO.translations.checking);
                        
                        // Send AJAX request
                        $.ajax({
                            url: cocoSEO.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'coco_seo_run_check',
                                post_id: postId,
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Update status in the table
                                    const row = button.closest('tr');
                                    const statusCell = row.find('td.column-index_status');
                                    
                                    statusCell.html(
                                        '<span class="coco-seo-status coco-seo-' + 
                                        (response.data.status === 'indexed' ? 'indexed' : 'not-indexed') + 
                                        '">' + response.data.status_text + '</span>' +
                                        '<div class="coco-seo-checked">' + 
                                        cocoSEO.translations.checked + ': ' + 
                                        response.data.checked + '</div>'
                                    );
                                    
                                    // Show success message
                                    alert(response.data.message);
                                } else {
                                    // Show error message
                                    alert(cocoSEO.translations.error + ': ' + response.data.message);
                                }
                            },
                            error: function() {
                                alert(cocoSEO.translations.error);
                            },
                            complete: function() {
                                // Re-enable button and restore text
                                button.prop('disabled', false).text(originalText);
                            }
                        });
                    });
                    
                    // Handle bulk action confirmation
                    $('#doaction, #doaction2').on('click', function(e) {
                        const action = $(this).prev('select').val();
                        
                        if (action === 'reset_status') {
                            if (!confirm(cocoSEO.translations.confirmReset)) {
                                e.preventDefault();
                            }
                        }
                    });
                });
            })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Check if assets should be loaded on current page
     * 
     * @param string $hook Current admin page
     * @return bool Whether to load assets
     */
    private function shouldLoadAssets(string $hook): bool {
        // Load on plugin pages
        if (str_contains($hook, 'coco-seo')) {
            return true;
        }
        
        // Check if it's a post edit screen for supported post types
        $screen = get_current_screen();
        
        if ($screen && $screen->base === 'post' && $screen->post_type) {
            $post_types = Settings::get('post_types', ['post', 'page']);
            return in_array($screen->post_type, $post_types, true);
        }
        
        return false;
    }
}