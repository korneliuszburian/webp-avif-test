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

if ( ! defined( 'WP_IMAGE_OPTIMIZER_VERSION' ) ) {
	if ( is_dir( __DIR__ . '/.git' ) && function_exists( 'exec' ) ) {
		$git_tag = null;
		exec( 'git describe --tags --abbrev=0 2>&1', $output, $return_var );
		if ( $return_var === 0 && ! empty( $output[0] ) ) {
			$git_tag = trim( $output[0] );
			if ( substr( $git_tag, 0, 1 ) === 'v' ) {
				$git_tag = substr( $git_tag, 1 );
			}
		}

		define( 'WP_IMAGE_OPTIMIZER_VERSION', $git_tag ?: '1.0.0' );
	} else {
		define( 'WP_IMAGE_OPTIMIZER_VERSION', '1.0.0' );
	}
}

( function () {
	$container = new \WpImageOptimizer\Core\Container();

	require_once __DIR__ . '/config/services.php';

	$plugin = $container->get( 'plugin' );
	$plugin->boot();
} )();
