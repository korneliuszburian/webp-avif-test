<?php
/**
 * Plugin Name: WebP & AVIF Image Optimizer
 * Description: High-performance WebP and AVIF conversion for WordPress images
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-image-optimizer
 * Requires PHP: 8.1
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_IMAGE_OPTIMIZER_VERSION', '1.0.0');
define('WP_IMAGE_OPTIMIZER_FILE', __FILE__);
define('WP_IMAGE_OPTIMIZER_DIR', plugin_dir_path(__FILE__));
define('WP_IMAGE_OPTIMIZER_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader if available, or use manual autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/src/Autoloader.php';
    \WpImageOptimizer\Autoloader::register();
}

// Boot the plugin
(function() {
    // Create the service container
    $container = new \WpImageOptimizer\Core\Container();
    
    // Register services
    require_once __DIR__ . '/config/services.php';
    
    // Boot the plugin
    $plugin = $container->get('plugin');
    $plugin->boot();
})();
