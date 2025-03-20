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

// Define version based on git tag if possible
if (!defined('WP_IMAGE_OPTIMIZER_VERSION')) {
    // Use git to determine version if this is a development setup
    if (is_dir(__DIR__ . '/.git') && function_exists('exec')) {
        $git_tag = null;
        // Try to get the latest tag
        exec('git describe --tags --abbrev=0 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output[0])) {
            $git_tag = trim($output[0]);
            // Remove 'v' prefix if present
            if (substr($git_tag, 0, 1) === 'v') {
                $git_tag = substr($git_tag, 1);
            }
        }
        
        // Use tag version, or default to 1.0.0
        define('WP_IMAGE_OPTIMIZER_VERSION', $git_tag ?: '1.0.0');
    } else {
        // Default version if not a git repository
        define('WP_IMAGE_OPTIMIZER_VERSION', '1.0.0');
    }
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
