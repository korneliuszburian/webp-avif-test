<?php

/**
 * Service definitions for dependency injection container
 */

$container->set(
	'plugin',
	function ( $c ) {
		return new \WpImageOptimizer\Core\Plugin( $c );
	}
);

$container->set(
	'settings',
	function ( $c ) {
		return new \WpImageOptimizer\Core\Settings();
	}
);

$container->set(
	'logger',
	function ( $c ) {
		return new \WpImageOptimizer\Utility\Logger();
	}
);

$container->set(
	'progress_manager',
	function ( $c ) {
		return new \WpImageOptimizer\Utility\ProgressManager();
	}
);

$container->set(
	'stats',
	function ( $c ) {
		return new \WpImageOptimizer\Utility\Stats();
	}
);

$container->set(
	'webp_converter',
	function ( $c ) {
		return new \WpImageOptimizer\Conversion\WebpConverter(
			$c->get( 'settings' ),
			$c->get( 'logger' )
		);
	}
);

$container->set(
	'avif_converter',
	function ( $c ) {
		return new \WpImageOptimizer\Conversion\AvifConverter(
			$c->get( 'settings' ),
			$c->get( 'logger' )
		);
	}
);

// Media services
$container->set(
	'media_processor',
	function ( $c ) {
		return new \WpImageOptimizer\Media\MediaProcessor(
			$c->get( 'webp_converter' ),
			$c->get( 'avif_converter' ),
			$c->get( 'settings' ),
			$c->get( 'progress_manager' ),
			$c->get( 'logger' )
		);
	}
);

$container->set(
	'media_library_integration',
	function ( $c ) {
		return new \WpImageOptimizer\Media\MediaLibraryIntegration(
			$c->get( 'settings' ),
			$c->get( 'media_processor' ),
			$c->get( 'logger' )
		);
	}
);

$container->set(
    'admin_page',
    function ( $c ) {
        return new \WpImageOptimizer\Admin\AdminPage(
            $c, // pass the container here
            $c->get( 'settings' ),
            $c->get( 'stats' ),
            $c->get( 'logger' ),
            $c->get( 'progress_manager' )
        );
    }
);

$container->set(
	'dashboard_widget',
	function ( $c ) {
		return new \WpImageOptimizer\Admin\DashboardWidget(
			$c->get( 'stats' )
		);
	}
);

// Frontend services
$container->set(
	'image_delivery',
	function ( $c ) {
		return new \WpImageOptimizer\Frontend\ImageDelivery(
			$c->get( 'settings' )
		);
	}
);
