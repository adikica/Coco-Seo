<?php
declare(strict_types=1);

namespace CocoSEO\Core;

/**
 * Core initialization class
 */
class Init {
    /**
     * Store all service classes
     *
     * @var array<class-string>
     */
    private array $services = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->services = [
            // Core services
            Assets::class,
            Settings::class,
            Error::class,
            
            // Admin components
            \CocoSEO\Admin\Dashboard::class,
            \CocoSEO\Admin\MetaBox::class,
            \CocoSEO\Admin\Table::class,
            
            // SEO components
            \CocoSEO\SEO\Meta::class,
            \CocoSEO\SEO\Sitemap::class,
            \CocoSEO\SEO\Robots::class,
            \CocoSEO\SEO\Google::class,
        ];
    }
    
    /**
     * Register all plugin services
     */
    public function registerServices(): void {
        foreach ($this->services as $service) {
            if (class_exists($service)) {
                $instance = new $service();
                
                if (method_exists($instance, 'register')) {
                    $instance->register();
                }
            }
        }
    }
}