<?php
declare(strict_types=1);

namespace CocoSEO\Core;

/**
 * Error handling class
 */
class Error {
    /**
     * Error log file
     */
    private const LOG_FILE = 'coco-seo-error.log';
    
    /**
     * Register error handling functions
     */
    public function register(): void {
        // Set error handler for specific plugin operations
        add_action('coco_seo_before_operation', [$this, 'setErrorHandler']);
        add_action('coco_seo_after_operation', [$this, 'restoreErrorHandler']);
        
        // Clean log periodically
        add_action('coco_seo_clean_logs', [$this, 'cleanLogs']);
        
        // Schedule log cleaning if not already scheduled
        if (!wp_next_scheduled('coco_seo_clean_logs')) {
            wp_schedule_event(time(), 'weekly', 'coco_seo_clean_logs');
        }
    }
    
    /**
     * Set custom error handler for sensitive operations
     *
     * @return void
     */
    public function setErrorHandler(): void {
        set_error_handler([$this, 'handleError']);
    }
    
    /**
     * Restore default error handler
     *
     * @return void
     */
    public function restoreErrorHandler(): void {
        restore_error_handler();
    }
    
    /**
     * Custom error handler
     *
     * @param int $errno Error level
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number where error occurred
     * @return bool Whether the error has been handled
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Only handle our plugin's errors
        if (!str_contains($errfile, 'coco-seo')) {
            return false;
        }

        $error_message = sprintf(
            "[%s] %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errstr,
            $errfile,
            $errline
        );
        
        $this->logError($error_message);
        
        // Let WordPress handle fatal errors
        return $errno !== E_ERROR;
    }
    
    /**
     * Log error to file
     *
     * @param string $message Error message
     * @return void
     */
    private function logError(string $message): void {
        $upload_dir = wp_upload_dir();
        $log_file = trailingslashit($upload_dir['basedir']) . self::LOG_FILE;
        
        error_log($message . PHP_EOL, 3, $log_file);
    }
    
    /**
     * Clean old log entries (keep only last 100 lines)
     *
     * @return void
     */
    public function cleanLogs(): void {
        $upload_dir = wp_upload_dir();
        $log_file = trailingslashit($upload_dir['basedir']) . self::LOG_FILE;
        
        if (!file_exists($log_file)) {
            return;
        }
        
        $lines = file($log_file);
        if (count($lines) <= 100) {
            return;
        }
        
        // Keep only the last 100 lines
        $lines = array_slice($lines, -100);
        file_put_contents($log_file, implode('', $lines));
    }
    
    /**
     * Log an exception
     *
     * @param \Throwable $exception The exception to log
     * @return void
     */
    public static function logException(\Throwable $exception): void {
        $instance = new self();
        $message = sprintf(
            "[%s] Exception: %s in %s on line %d\nStack trace: %s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        $instance->logError($message);
    }
}