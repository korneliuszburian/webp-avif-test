<?php
/**
 * Plugin Name: WebP & AVIF Image Optimizer
 * Description: High-performance WebP and AVIF conversion for WordPress images
 * Version: 1.0.28
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

define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.28' );
define( 'WP_IMAGE_OPTIMIZER_FILE', __FILE__ );
define( 'WP_IMAGE_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_IMAGE_OPTIMIZER_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Autoloader.php';
	\WpImageOptimizer\Autoloader::register();
}

if ( class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    $githubRepo = defined('WP_IMAGE_OPTIMIZER_GITHUB_REPO') 
        ? WP_IMAGE_OPTIMIZER_GITHUB_REPO 
        : 'korneliuszburian/webp-avif-test';
        
    $metadataUrl = "https://raw.githubusercontent.com/{$githubRepo}/master/release-info.json";
    
    $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $metadataUrl,
        __FILE__,
        'wp-image-optimizer'
    );
    
    $updateChecker->addQueryArgFilter(function($queryArgs) {
        $queryArgs['stability'] = 'stable';
        $queryArgs['t'] = time();
        return $queryArgs;
    });
    
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
        $check_link = '<a href="' . wp_nonce_url(admin_url('update-core.php?force-check=1'), 'upgrade-core') . '">Check for Updates</a>';
        array_unshift($links, $check_link);
        return $links;
    });
}

( function () {
	$container = new \WpImageOptimizer\Core\Container();

	require_once __DIR__ . '/config/services.php';

	$plugin = $container->get( 'plugin' );
	$plugin->boot();
} )();
