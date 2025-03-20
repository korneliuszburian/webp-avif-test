<?php
/**
 * Plugin Name: WebP & AVIF Image Optimizer
 * Description: High-performance WebP and AVIF conversion for WordPress images
 * Version: 1.0.13
 * Author: WebP AVIF Team
 * License: GPL v2 or later
 * Text Domain: wp-image-optimizer
 * Requires PHP: 8.1
 * Requires at least: 5.8
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.13' );
define( 'WP_IMAGE_OPTIMIZER_FILE', __FILE__ );
define( 'WP_IMAGE_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader if available, or use manual autoloader
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Autoloader.php';
	\WpImageOptimizer\Autoloader::register();
}

// Setup the automatic update system
if ( class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    // Get GitHub repository name from constant or fallback to default
    $githubRepo = defined('WP_IMAGE_OPTIMIZER_GITHUB_REPO') 
        ? WP_IMAGE_OPTIMIZER_GITHUB_REPO 
        : 'korneliuszburian/webp-avif-test';
        
    // Create metadata URL from repository name
    $metadataUrl = "https://" . explode('/', $githubRepo)[0] . ".github.io/" . explode('/', $githubRepo)[1] . "/release-info.json";
    
    // Initialize update checker with the correct metadata handler
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $metadataUrl,
        __FILE__,
        'wp-image-optimizer'
    );
    
    // This is important - specify we're using a custom JSON format that's compatible with WordPress.org
    $updateChecker->addQueryArgFilter(function($queryArgs) {
        $queryArgs['stability'] = 'stable';
        return $queryArgs;
    });
}

// Initialize the plugin
( function () {
	// Create dependency injection container
	$container = new \WpImageOptimizer\Core\Container();

	// Register services
	require_once __DIR__ . '/config/services.php';

	// Boot the plugin
	$plugin = $container->get( 'plugin' );
	$plugin->boot();
} )();