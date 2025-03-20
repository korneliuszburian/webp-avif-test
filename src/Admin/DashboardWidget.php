<?php

namespace WpImageOptimizer\Admin;

use WpImageOptimizer\Utility\Stats;

class DashboardWidget {
	public function __construct(
		private Stats $stats
	) {}

	/**
	 * Register hooks for dashboard widget
	 */
	public function registerHooks(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'addDashboardWidget' ) );
	}

	/**
	 * Add dashboard widget
	 */
	public function addDashboardWidget(): void {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget(
				'wp_image_optimizer_dashboard_widget',
				__( 'WebP & AVIF Optimizer', 'wp-image-optimizer' ),
				array( $this, 'renderDashboardWidget' )
			);
		}
	}

	/**
	 * Render dashboard widget
	 */
	public function renderDashboardWidget(): void {
		$stats      = $this->stats->countConvertedImages();
		$spaceSaved = $this->stats->calculateSpaceSaved();

		?>
		<div class="wp-image-optimizer-dashboard-widget">
			<div class="wp-image-optimizer-dashboard-stats">
				<div class="wp-image-optimizer-stat-box">
					<span class="wp-image-optimizer-stat-label"><?php _e( 'Total Images', 'wp-image-optimizer' ); ?></span>
					<span class="wp-image-optimizer-stat-value"><?php echo $stats['total']; ?></span>
				</div>
				
				<div class="wp-image-optimizer-stat-box">
					<span class="wp-image-optimizer-stat-label"><?php _e( 'Optimized Images', 'wp-image-optimizer' ); ?></span>
					<span class="wp-image-optimizer-stat-value">
						<?php echo $stats['webp'] + $stats['avif'] - $stats['both']; ?>
						(<?php echo $stats['total'] > 0 ? round( ( ( $stats['webp'] + $stats['avif'] - $stats['both'] ) / $stats['total'] ) * 100, 2 ) : 0; ?>%)
					</span>
				</div>
				
				<div class="wp-image-optimizer-stat-box">
					<span class="wp-image-optimizer-stat-label"><?php _e( 'Space Saved', 'wp-image-optimizer' ); ?></span>
					<span class="wp-image-optimizer-stat-value">
						<?php echo size_format( $spaceSaved['total_saved'] ); ?>
						(<?php echo $spaceSaved['percentage_saved']; ?>%)
					</span>
				</div>
				
				<div class="wp-image-optimizer-stat-box">
					<span class="wp-image-optimizer-stat-label"><?php _e( 'Remaining Images', 'wp-image-optimizer' ); ?></span>
					<span class="wp-image-optimizer-stat-value">
						<?php echo $stats['total'] - ( $stats['webp'] + $stats['avif'] - $stats['both'] ); ?>
					</span>
				</div>
			</div>
			
			<div class="wp-image-optimizer-dashboard-actions">
				<a href="<?php echo admin_url( 'options-general.php?page=wp-image-optimizer&tab=bulk' ); ?>" class="button button-primary">
					<?php _e( 'Bulk Optimize Now', 'wp-image-optimizer' ); ?>
				</a>
				
				<a href="<?php echo admin_url( 'options-general.php?page=wp-image-optimizer&tab=statistics' ); ?>" class="button">
					<?php _e( 'View Detailed Stats', 'wp-image-optimizer' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
