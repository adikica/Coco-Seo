<?php
declare(strict_types=1);

/**
 * Coco SEO Plugin
 *
 * @package     CocoSEO
 * @author      Adi Kica
 * @copyright   2025 dnovogroup.com
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Coco SEO Plugin
 * Plugin URI: https://dnovogroup.com/
 * Description: A modern SEO plugin for WordPress with advanced meta management, sitemaps, robots.txt, and Google indexing.
 * Version: 2.0.0
 * Author: Adi Kica
 * Author URI: https://dnovogroup.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coco-seo
 * Domain Path: /languages
 * Requires PHP: 8.3
 * Requires at least: 6.7
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Custom autoloader if Composer isn't available
    spl_autoload_register(function ($class) {
        // Project-specific namespace prefix
        $prefix = 'CocoSEO\\';
        
        // Base directory for the namespace prefix
        $base_dir = __DIR__ . '/src/';
        
        // Check if the class uses the namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separators with directory separators
        // and append .php
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Define plugin constants
final class CocoSEO {
    /**
     * Plugin version
     */
    public const VERSION = '2.0.0';
    
    /**
     * Plugin instance
     */
    private static ?self $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->defineConstants();
        $this->initHooks();
        
        // Initialize plugin components
        add_action('plugins_loaded', [$this, 'initPlugin']);
    }
    
    /**
     * Define plugin constants
     */
    private function defineConstants(): void {
        define('COCO_SEO_VERSION', self::VERSION);
        define('COCO_SEO_FILE', __FILE__);
        define('COCO_SEO_PATH', plugin_dir_path(__FILE__));
        define('COCO_SEO_URL', plugin_dir_url(__FILE__));
        define('COCO_SEO_BASENAME', plugin_basename(__FILE__));
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks(): void {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin components
     */
    public function initPlugin(): void {
        // Load text domain
        load_plugin_textdomain('coco-seo', false, dirname(COCO_SEO_BASENAME) . '/languages');
        
        // Simple initialization for first version
        $this->initSimplePlugin();
    }
    
    /**
     * Simple initialization for v1
     */
private function initSimplePlugin(): void {
    // Include core files
    require_once COCO_SEO_PATH . 'includes/meta-box.php';
    require_once COCO_SEO_PATH . 'includes/sitemap-generator.php';
    require_once COCO_SEO_PATH . 'includes/robots-txt.php';
    require_once COCO_SEO_PATH . 'includes/indexing-check.php';
    require_once COCO_SEO_PATH . 'includes/helpers.php';
    require_once COCO_SEO_PATH . 'includes/sitemap-submit.php';
    require_once COCO_SEO_PATH . 'admin/menu.php';

    // Bing Webmaster + IndexNow integration
    $bing_file = COCO_SEO_PATH . 'includes/bing-indexnow.php';
    if (file_exists($bing_file)) {
        require_once $bing_file;

        if (class_exists('\CocoSEO\Integrations\Bing_IndexNow')) {
            \CocoSEO\Integrations\Bing_IndexNow::register();
        }
    }
}
    
    /**
     * Plugin activation
     */
public function activate(): void {
    // Create necessary directory structure
    $this->createDirectoryStructure();

    // Set default options
    update_option('coco_seo_version', self::VERSION);
    update_option('coco_seo_flush_rewrite', true);

    // Bing / IndexNow: set up cron + rewrite
    $bing_file = COCO_SEO_PATH . 'includes/bing-indexnow.php';
    if (file_exists($bing_file)) {
        require_once $bing_file;

        if (class_exists('\CocoSEO\Integrations\Bing_IndexNow')) {
            \CocoSEO\Integrations\Bing_IndexNow::on_activate();
        }
    }

    // Flush rewrite rules (includes Bing key endpoint)
    flush_rewrite_rules();
}
    
    /**
     * Create necessary directory structure
     */
    private function createDirectoryStructure(): void {
        // Define required directories
        $directories = [
            // Original modern structure (for future use)
            COCO_SEO_PATH . 'src',
            COCO_SEO_PATH . 'src/Core',
            COCO_SEO_PATH . 'src/Admin',
            COCO_SEO_PATH . 'src/SEO',
            
            // Asset directories
            COCO_SEO_PATH . 'assets',
            COCO_SEO_PATH . 'assets/css',
            COCO_SEO_PATH . 'assets/js',
            
            // Legacy structure (for current use)
            COCO_SEO_PATH . 'includes',
            COCO_SEO_PATH . 'admin',
        ];
        
        // Create each directory if it doesn't exist
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                wp_mkdir_p($directory);
            }
        }
        
        // Create essential CSS file if it doesn't exist
        $css_file = COCO_SEO_PATH . 'assets/css/admin-minimal.css';
        if (!file_exists($css_file)) {
            $css_content = "/**\n * Minimal CSS for Coco SEO Plugin admin interface\n */\n\n";
            $css_content .= ".coco-seo-missing { color: #dc3545; }\n";
            $css_content .= ".coco-seo-enabled { background-color: #d1e7dd; color: #0f5132; padding: 2px 6px; border-radius: 3px; }\n";
            $css_content .= ".coco-seo-disabled { background-color: #f8d7da; color: #842029; padding: 2px 6px; border-radius: 3px; }\n";
            
            file_put_contents($css_file, $css_content);
        }
        
        // Copy original files to legacy structure if they don't exist
        $this->ensureLegacyFilesExist();
    }
    
    /**
     * Ensure legacy files exist
     */
    private function ensureLegacyFilesExist(): void {
        // Copy original files to the includes directory
        $original_files = [
            'meta-box.php' => 'includes/meta-box.php',
            'sitemap-generator.php' => 'includes/sitemap-generator.php',
            'robots-txt.php' => 'includes/robots-txt.php',
            'indexing-check.php' => 'includes/indexing-check.php',
            'helpers.php' => 'includes/helpers.php',
            'menu.php' => 'admin/menu.php',
        ];
        
        foreach ($original_files as $orig => $dest) {
            $dest_path = COCO_SEO_PATH . $dest;
            
            // Only create if file doesn't exist
            if (!file_exists($dest_path)) {
                // If plugin just copied from the original files
                $orig_file = dirname(__FILE__, 2) . '/' . $orig;
                
                if (file_exists($orig_file)) {
                    copy($orig_file, $dest_path);
                } else {
                    // Create empty file as placeholder
                    file_put_contents($dest_path, '<?php // File will be implemented in next version');
                }
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
public function deactivate(): void {
    // Clean up transients
    delete_transient('coco_sitemap_last_generated');

    // Bing / IndexNow: remove cron
    $bing_file = COCO_SEO_PATH . 'includes/bing-indexnow.php';
    if (file_exists($bing_file)) {
        require_once $bing_file;

        if (class_exists('\CocoSEO\Integrations\Bing_IndexNow')) {
            \CocoSEO\Integrations\Bing_IndexNow::on_deactivate();
        }
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
}

// Initialize the plugin
CocoSEO::getInstance();