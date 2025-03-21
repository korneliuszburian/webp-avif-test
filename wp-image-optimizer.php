<?php
/**
 * Plugin Name: WebP & AVIF Image Optimizer
 * Description: High-performance WebP and AVIF conversion for WordPress images
 * Version: 1.0.20
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

define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.20' );
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
        
    // Use the raw GitHub URL for more reliable updates
    $metadataUrl = "https://raw.githubusercontent.com/{$githubRepo}/master/release-info.json";
    
    // Initialize update checker with the correct metadata handler
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $metadataUrl,
        __FILE__,
        'wp-image-optimizer'
    );
    
    // This is important - specify we're using a custom JSON format that's compatible with WordPress.org
    $updateChecker->addQueryArgFilter(function($queryArgs) {
        $queryArgs['stability'] = 'stable';
        // Add a timestamp to prevent caching
        $queryArgs['timestamp'] = time();
        return $queryArgs;
    });
    
    // Force more frequent update checks (in seconds)
    $updateChecker->setCheckPeriod(3600); // Check every hour instead of default 12 hours
    
    // Enable debug mode
    $updateChecker->debugMode = true;
    
    // Add filter to add a manual update check button that bypasses all caching
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) use ($updateChecker) {
        $settings_link = '<a href="#" onclick="jQuery.post(ajaxurl, {action: \'puc_check_update\', slug: \'wp-image-optimizer\'}, function(response) { alert(\'Update check performed. Refresh the page to see results.\'); }); return false;">Force Update Check</a>';
        array_unshift($links, $settings_link);
        return $links;
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