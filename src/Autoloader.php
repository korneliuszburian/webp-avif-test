<?php

namespace WpImageOptimizer;

class Autoloader {
    /**
     * Register the autoloader
     */
    public static function register(): void {
        spl_autoload_register([self::class, 'autoload']);
    }
    
    /**
     * Autoload classes
     */
    public static function autoload(string $class): void {
        // Only handle our own namespace
        if (strpos($class, 'WpImageOptimizer\\') !== 0) {
            return;
        }
        
        // Convert namespace to file path
        $relative_class = substr($class, strlen('WpImageOptimizer\\'));
        $file = WP_IMAGE_OPTIMIZER_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';
        
        // Include the file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
