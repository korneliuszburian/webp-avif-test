<?php
/**
 * Plugin Name: WebP & AVIF Image Optimizer
 * Description: High-performance WebP and AVIF conversion for WordPress images
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-image-optimizer
 * Requires PHP: 8.1
 * GitHub Plugin URI: your-username/webp-avif-test
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.0' );
define( 'WP_IMAGE_OPTIMIZER_FILE', __FILE__ );
define( 'WP_IMAGE_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Autoloader.php';
	\WpImageOptimizer\Autoloader::register();
}

// Setup the update checker
if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    // Option 1: Use GitHub directly (better for private repositories with token)
    if (defined('WP_IMAGE_OPTIMIZER_USE_GITHUB_UPDATES') && WP_IMAGE_OPTIMIZER_USE_GITHUB_UPDATES) {
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/your-username/webp-avif-test/', // Replace with your actual GitHub repository
            __FILE__,
            'wp-image-optimizer'
        );
        
        // Set the branch that contains the stable release
        $myUpdateChecker->setBranch('main');
        
        // For private repositories
        // $myUpdateChecker->setAuthentication('your-access-token');
        
        // Use release assets for updating
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();
    } 
    // Option 2: Use GitHub Pages for metadata (better for public repositories)
    else {
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://your-username.github.io/webp-avif-test/release-info.json', // Replace with your GitHub Pages URL
            __FILE__,
            'wp-image-optimizer'
        );
    }
}

// Get version from Git tag if available
if ( is_dir( __DIR__ . '/.git' ) && function_exists( 'exec' ) ) {
    $git_tag = null;
    exec( 'git describe --tags --abbrev=0 2>&1', $output, $return_var );
    if ( $return_var === 0 && ! empty( $output[0] ) ) {
        $git_tag = trim( $output[0] );
        if ( substr( $git_tag, 0, 1 ) === 'v' ) {
            $git_tag = substr( $git_tag, 1 );
        }
        
        // Only use git version for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            define( 'WP_IMAGE_OPTIMIZER_DEV_VERSION', $git_tag );
        }
    }
}

( function () {
	$container = new \WpImageOptimizer\Core\Container();

	require_once __DIR__ . '/config/services.php';

	$plugin = $container->get( 'plugin' );
	$plugin->boot();
} )();
