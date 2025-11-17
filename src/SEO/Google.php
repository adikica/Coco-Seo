<?php
declare(strict_types=1);

namespace CocoSEO\SEO;

use CocoSEO\Core\Settings;
use CocoSEO\Core\Error;

/**
 * Google integration class
 */
class Google {
    /**
     * API URL for URL inspection
     */
    private const API_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
    
    /**
     * Transient prefix
     */
    private const TRANSIENT_PREFIX = 'coco_seo_google_index_';
    
    /**
     * Transient expiration (1 hour)
     */
    private const TRANSIENT_EXPIRATION = 3600;
    
    /**
     * Check if a URL is indexed in Google
     * 
     * @param string $url The URL to check
     * @param bool $force Force check (ignore cache)
     * @return array{indexed: bool, message: string} Result array
     * @throws \Exception If API key is missing or API request fails
     */
    public function checkIndexStatus(string $url, bool $force = false): array {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(__('Invalid URL.', 'coco-seo'));
        }
        
        // Get API key
        $api_key = Settings::get('google_api_key', '');
        if (empty($api_key)) {
            throw new \Exception(__('Google API key is not set. Please set it in the settings.', 'coco-seo'));
        }
        
        // Check cache first if not forcing
        $transient_key = self::TRANSIENT_PREFIX . md5($url);
        if (!$force && ($cached = get_transient($transient_key))) {
            return $cached;
        }
        
        try {
            // Set error handler
            do_action('coco_seo_before_operation');
            
            // Prepare request body
            $body = [
                'inspectionUrl' => $url,
                'siteUrl' => get_site_url(),
            ];
            
            // Make API request
            $response = wp_remote_post(self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
                'timeout' => 30,
            ]);
            
            // Handle WP_Error
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            // Get response code and body
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            // Parse response
            $data = json_decode($response_body, true);
            
            // Check for errors in response
            if ($response_code !== 200) {
                $error_message = $data['error']['message'] ?? __('Unknown API error.', 'coco-seo');
                throw new \Exception($error_message);
            }
            
            // Check if URL is indexed
            $coverage_state = $data['inspectionResult']['indexStatusResult']['coverageState'] ?? null;
            $indexed = $coverage_state === 'INDEXED';
            
            // Prepare result
            $result = [
                'indexed' => $indexed,
                'message' => $indexed 
                    ? __('URL is indexed in Google.', 'coco-seo') 
                    : __('URL is not indexed in Google.', 'coco-seo'),
            ];
            
            // Cache result
            set_transient($transient_key, $result, self::TRANSIENT_EXPIRATION);
            
            return $result;
        } catch (\Exception $e) {
            // Log exception
            Error::logException($e);
            
            // Re-throw the exception
            throw $e;
        } finally {
            // Restore error handler
            do_action('coco_seo_after_operation');
        }
    }
    
    /**
     * Bulk check indexing status for multiple URLs
     * 
     * @param array<string> $urls Array of URLs
     * @param bool $force Force check (ignore cache)
     * @return array<string, array{indexed: bool, message: string, error?: string}> Results
     */
    public function bulkCheckIndexStatus(array $urls, bool $force = false): array {
        $results = [];
        
        foreach ($urls as $url) {
            try {
                $results[$url] = $this->checkIndexStatus($url, $force);
            } catch (\Exception $e) {
                $results[$url] = [
                    'indexed' => false,
                    'message' => __('Error checking indexing status.', 'coco-seo'),
                    'error' => $e->getMessage(),
                ];
            }
            
            // Avoid rate limiting
            if (count($urls) > 1) {
                usleep(1000000); // 1 second
            }
        }
        
        return $results;
    }
    
    /**
     * Flush all transients related to Google indexing
     * 
     * @return int Number of transients deleted
     */
    public function flushTransients(): int {
        global $wpdb;
        
        $count = 0;
        $transient_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_' . self::TRANSIENT_PREFIX . '%'
            )
        );
        
        foreach ($transient_keys as $key) {
            $key = str_replace('_transient_', '', $key);
            if (delete_transient($key)) {
                $count++;
            }
        }
        
        return $count;
    }
}