<?php

namespace WpImageOptimizer\Admin;

use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Stats;
use WpImageOptimizer\Utility\Logger;
use WpImageOptimizer\Utility\ProgressManager;

class AdminPage {
	public function __construct(
		private Settings $settings,
		private Stats $stats,
		private Logger $logger,
		private ProgressManager $progressManager
	) {}

	/**
	 * Register hooks for admin page
	 */
	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'addMenuPages' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );

		// Ajax handlers
		add_action( 'wp_ajax_wp_image_optimizer_get_progress', array( $this, 'ajaxGetProgress' ) );
		add_action( 'wp_ajax_wp_image_optimizer_convert_single', array( $this, 'ajaxConvertSingle' ) );
		add_action( 'wp_ajax_wp_image_optimizer_bulk_convert', array( $this, 'ajaxBulkConvert' ) );
	}

	/**
	 * Add menu pages
	 */
	public function addMenuPages(): void {
		add_options_page(
			__( 'WebP & AVIF Optimizer', 'wp-image-optimizer' ),
			__( 'WebP & AVIF', 'wp-image-optimizer' ),
			'manage_options',
			'wp-image-optimizer',
			array( $this, 'renderSettingsPage' )
		);
	}

	/**
	 * Register settings
	 */
	public function registerSettings(): void {
		register_setting( 'wp_image_optimizer_settings', 'wp_image_optimizer_settings' );

		// General settings section
		add_settings_section(
			'wp_image_optimizer_general',
			__( 'General Settings', 'wp-image-optimizer' ),
			array( $this, 'renderGeneralSection' ),
			'wp_image_optimizer'
		);

		// Add settings fields
		add_settings_field(
			'wp_image_optimizer_auto_convert',
			__( 'Auto Convert on Upload', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_general',
			array(
				'id'          => 'auto_convert',
				'description' => __( 'Automatically convert images to WebP and AVIF when they are uploaded', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_enable_webp',
			__( 'Enable WebP Conversion', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_general',
			array(
				'id'          => 'enable_webp',
				'description' => __( 'Convert images to WebP format', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_enable_avif',
			__( 'Enable AVIF Conversion', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_general',
			array(
				'id'          => 'enable_avif',
				'description' => __( 'Convert images to AVIF format', 'wp-image-optimizer' ),
			)
		);

		// WebP settings section
		add_settings_section(
			'wp_image_optimizer_webp',
			__( 'WebP Settings', 'wp-image-optimizer' ),
			array( $this, 'renderWebpSection' ),
			'wp_image_optimizer'
		);

		add_settings_field(
			'wp_image_optimizer_webp_quality',
			__( 'WebP Quality', 'wp-image-optimizer' ),
			array( $this, 'renderRangeField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_webp',
			array(
				'id'          => 'webp_quality',
				'min'         => 1,
				'max'         => 100,
				'step'        => 1,
				'description' => __( 'Quality of WebP images (1-100). Higher values mean better quality but larger file size.', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_webp_lossless',
			__( 'WebP Lossless', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_webp',
			array(
				'id'          => 'webp_lossless',
				'description' => __( 'Use lossless compression for WebP images. Results in larger file sizes but no quality loss.', 'wp-image-optimizer' ),
			)
		);

		// AVIF settings section
		add_settings_section(
			'wp_image_optimizer_avif',
			__( 'AVIF Settings', 'wp-image-optimizer' ),
			array( $this, 'renderAvifSection' ),
			'wp_image_optimizer'
		);

		add_settings_field(
			'wp_image_optimizer_avif_quality',
			__( 'AVIF Quality', 'wp-image-optimizer' ),
			array( $this, 'renderRangeField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_avif',
			array(
				'id'          => 'avif_quality',
				'min'         => 1,
				'max'         => 100,
				'step'        => 1,
				'description' => __( 'Quality of AVIF images (1-100). Higher values mean better quality but larger file size.', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_avif_speed',
			__( 'AVIF Encoding Speed', 'wp-image-optimizer' ),
			array( $this, 'renderRangeField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_avif',
			array(
				'id'          => 'avif_speed',
				'min'         => 0,
				'max'         => 10,
				'step'        => 1,
				'description' => __( 'Speed of AVIF encoding (0-10). Lower values mean better quality but slower encoding.', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_avif_lossless',
			__( 'AVIF Lossless', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_avif',
			array(
				'id'          => 'avif_lossless',
				'description' => __( 'Use lossless compression for AVIF images. Results in larger file sizes but no quality loss.', 'wp-image-optimizer' ),
			)
		);

		// Performance settings section
		add_settings_section(
			'wp_image_optimizer_performance',
			__( 'Performance Settings', 'wp-image-optimizer' ),
			array( $this, 'renderPerformanceSection' ),
			'wp_image_optimizer'
		);

		add_settings_field(
			'wp_image_optimizer_bulk_batch_size',
			__( 'Bulk Conversion Batch Size', 'wp-image-optimizer' ),
			array( $this, 'renderNumberField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_performance',
			array(
				'id'          => 'bulk_batch_size',
				'min'         => 1,
				'max'         => 50,
				'description' => __( 'Number of images to process in each batch during bulk conversion. Lower values reduce server load.', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_processing_delay',
			__( 'Batch Processing Delay', 'wp-image-optimizer' ),
			array( $this, 'renderNumberField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_performance',
			array(
				'id'          => 'processing_delay',
				'min'         => 0,
				'max'         => 1000,
				'description' => __( 'Delay in milliseconds between batch processing. Higher values reduce server load.', 'wp-image-optimizer' ),
			)
		);

		// Advanced settings section
		add_settings_section(
			'wp_image_optimizer_advanced',
			__( 'Advanced Settings', 'wp-image-optimizer' ),
			array( $this, 'renderAdvancedSection' ),
			'wp_image_optimizer'
		);

		add_settings_field(
			'wp_image_optimizer_conversion_method',
			__( 'Conversion Method', 'wp-image-optimizer' ),
			array( $this, 'renderSelectField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_advanced',
			array(
				'id'          => 'conversion_method',
				'options'     => array(
					'auto'    => __( 'Auto (use best available)', 'wp-image-optimizer' ),
					'gd'      => __( 'GD Library', 'wp-image-optimizer' ),
					'imagick' => __( 'ImageMagick', 'wp-image-optimizer' ),
					'exec'    => __( 'Command Line Tools', 'wp-image-optimizer' ),
				),
				'description' => __( 'Method to use for image conversion. Auto will select the best available method.', 'wp-image-optimizer' ),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueueAssets( string $hook ): void {
		if ( $hook !== 'settings_page_wp-image-optimizer' ) {
			return;
		}

		wp_enqueue_style(
			'wp-image-optimizer-admin',
			WP_IMAGE_OPTIMIZER_URL . 'assets/css/admin.css',
			array(),
			WP_IMAGE_OPTIMIZER_VERSION
		);

		wp_enqueue_script(
			'wp-image-optimizer-admin',
			WP_IMAGE_OPTIMIZER_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_IMAGE_OPTIMIZER_VERSION,
			true
		);

		wp_localize_script(
			'wp-image-optimizer-admin',
			'wpImageOptimizer',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp-image-optimizer' ),
				'i18n'     => array(
					'converting' => __( 'Converting...', 'wp-image-optimizer' ),
					'converted'  => __( 'Converted', 'wp-image-optimizer' ),
					'error'      => __( 'Error', 'wp-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Render settings page
	 */
	public function renderSettingsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = $_GET['tab'] ?? 'settings';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=wp-image-optimizer&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Settings', 'wp-image-optimizer' ); ?>
				</a>
				<a href="?page=wp-image-optimizer&tab=bulk" class="nav-tab <?php echo $active_tab === 'bulk' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Bulk Convert', 'wp-image-optimizer' ); ?>
				</a>
				<a href="?page=wp-image-optimizer&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Statistics', 'wp-image-optimizer' ); ?>
				</a>
			</h2>
			
			<div class="tab-content">
				<?php
				if ( $active_tab === 'settings' ) {
					$this->renderSettingsTab();
				} elseif ( $active_tab === 'bulk' ) {
					$this->renderBulkTab();
				} elseif ( $active_tab === 'statistics' ) {
					$this->renderStatisticsTab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab
	 */
	private function renderSettingsTab(): void {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'wp_image_optimizer_settings' );
			do_settings_sections( 'wp_image_optimizer' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render bulk tab
	 */
	private function renderBulkTab(): void {
		$supportedCount = $this->stats->getSupportedFilesCount();
		?>
		<div class="wp-image-optimizer-bulk">
			<h2><?php _e( 'Bulk Convert Images', 'wp-image-optimizer' ); ?></h2>
			<p><?php printf( __( 'Found %d images that can be converted to WebP and AVIF formats.', 'wp-image-optimizer' ), $supportedCount ); ?></p>
			
			<div class="wp-image-optimizer-progress-wrapper" style="display:none;">
				<div class="wp-image-optimizer-progress">
					<div class="wp-image-optimizer-progress-bar"></div>
				</div>
				<div class="wp-image-optimizer-progress-text">
					<span class="wp-image-optimizer-progress-percentage">0%</span>
					<span class="wp-image-optimizer-progress-count">(0/<?php echo $supportedCount; ?>)</span>
				</div>
			</div>
			
			<p>
				<button type="button" class="button button-primary wp-image-optimizer-bulk-convert">
					<?php _e( 'Start Bulk Conversion', 'wp-image-optimizer' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render statistics tab
	 */
	private function renderStatisticsTab(): void {
		$stats      = $this->stats->countConvertedImages();
		$spaceSaved = $this->stats->calculateSpaceSaved();
		?>
		<div class="wp-image-optimizer-statistics">
			<h2><?php _e( 'Statistics', 'wp-image-optimizer' ); ?></h2>
			
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php _e( 'Metric', 'wp-image-optimizer' ); ?></th>
						<th><?php _e( 'Value', 'wp-image-optimizer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php _e( 'Total Images', 'wp-image-optimizer' ); ?></td>
						<td><?php echo $stats['total']; ?></td>
					</tr>
					<tr>
						<td><?php _e( 'Images with WebP Version', 'wp-image-optimizer' ); ?></td>
						<td><?php echo $stats['webp']; ?> (<?php echo $stats['total'] > 0 ? round( ( $stats['webp'] / $stats['total'] ) * 100, 2 ) : 0; ?>%)</td>
					</tr>
					<tr>
						<td><?php _e( 'Images with AVIF Version', 'wp-image-optimizer' ); ?></td>
						<td><?php echo $stats['avif']; ?> (<?php echo $stats['total'] > 0 ? round( ( $stats['avif'] / $stats['total'] ) * 100, 2 ) : 0; ?>%)</td>
					</tr>
					<tr>
						<td><?php _e( 'Images with Both Versions', 'wp-image-optimizer' ); ?></td>
						<td><?php echo $stats['both']; ?> (<?php echo $stats['total'] > 0 ? round( ( $stats['both'] / $stats['total'] ) * 100, 2 ) : 0; ?>%)</td>
					</tr>
					<tr>
						<td><?php _e( 'Original Size', 'wp-image-optimizer' ); ?></td>
						<td><?php echo size_format( $spaceSaved['original_size'] ); ?></td>
					</tr>
					<tr>
						<td><?php _e( 'WebP Size', 'wp-image-optimizer' ); ?></td>
						<td><?php echo size_format( $spaceSaved['webp_size'] ); ?></td>
					</tr>
					<tr>
						<td><?php _e( 'AVIF Size', 'wp-image-optimizer' ); ?></td>
						<td><?php echo size_format( $spaceSaved['avif_size'] ); ?></td>
					</tr>
					<tr>
						<td><?php _e( 'Total Space Saved', 'wp-image-optimizer' ); ?></td>
						<td><?php echo size_format( $spaceSaved['total_saved'] ); ?> (<?php echo $spaceSaved['percentage_saved']; ?>%)</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Section callbacks
	 */
	public function renderGeneralSection(): void {
		echo '<p>' . __( 'General settings for WebP & AVIF image optimization.', 'wp-image-optimizer' ) . '</p>';
	}

	public function renderWebpSection(): void {
		echo '<p>' . __( 'Settings for WebP image conversion.', 'wp-image-optimizer' ) . '</p>';
	}

	public function renderAvifSection(): void {
		echo '<p>' . __( 'Settings for AVIF image conversion.', 'wp-image-optimizer' ) . '</p>';
	}

	public function renderPerformanceSection(): void {
		echo '<p>' . __( 'Settings that affect conversion performance and server load.', 'wp-image-optimizer' ) . '</p>';
	}

	public function renderAdvancedSection(): void {
		echo '<p>' . __( 'Advanced settings for WebP & AVIF image optimization.', 'wp-image-optimizer' ) . '</p>';
	}

	/**
	 * Field renderers
	 */
	public function renderCheckboxField( array $args ): void {
		$id          = $args['id'];
		$description = $args['description'] ?? '';
		$value       = $this->settings->get( $id );
		?>
		<label>
			<input type="checkbox" name="wp_image_optimizer_settings[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $value, true ); ?>>
			<?php echo esc_html( $description ); ?>
		</label>
		<?php
	}

	public function renderRangeField( array $args ): void {
		$id          = $args['id'];
		$min         = $args['min'] ?? 0;
		$max         = $args['max'] ?? 100;
		$step        = $args['step'] ?? 1;
		$description = $args['description'] ?? '';
		$value       = $this->settings->get( $id );
		?>
		<div class="wp-image-optimizer-range-field">
			<input type="range" name="wp_image_optimizer_settings[<?php echo esc_attr( $id ); ?>]" id="<?php echo esc_attr( $id ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<span class="wp-image-optimizer-range-value"><?php echo esc_html( $value ); ?></span>
		</div>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<script>
			jQuery(document).ready(function($) {
				$('#<?php echo esc_attr( $id ); ?>').on('input', function() {
					$(this).next('.wp-image-optimizer-range-value').text($(this).val());
				});
			});
		</script>
		<?php
	}

	public function renderNumberField( array $args ): void {
		$id          = $args['id'];
		$min         = $args['min'] ?? 0;
		$max         = $args['max'] ?? 100;
		$description = $args['description'] ?? '';
		$value       = $this->settings->get( $id );
		?>
		<input type="number" name="wp_image_optimizer_settings[<?php echo esc_attr( $id ); ?>]" id="<?php echo esc_attr( $id ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	public function renderSelectField( array $args ): void {
		$id          = $args['id'];
		$options     = $args['options'] ?? array();
		$description = $args['description'] ?? '';
		$value       = $this->settings->get( $id );
		?>
		<select name="wp_image_optimizer_settings[<?php echo esc_attr( $id ); ?>]" id="<?php echo esc_attr( $id ); ?>">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	/**
	 * Ajax handlers
	 */
	public function ajaxGetProgress(): void {
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$process_id = $_POST['process_id'] ?? '';
		if ( ! $process_id ) {
			wp_send_json_error( array( 'message' => 'Missing process ID' ) );
		}

		$progress = $this->progressManager->getProgress( $process_id );
		if ( ! $progress ) {
			wp_send_json_error( array( 'message' => 'Process not found' ) );
		}

		wp_send_json_success( $progress );
	}

	public function ajaxConvertSingle(): void {
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => 'Missing attachment ID' ) );
		}

		$mediaProcessor = new \WpImageOptimizer\Media\MediaProcessor(
			$this->container->get( 'webp_converter' ),
			$this->container->get( 'avif_converter' ),
			$this->settings,
			$this->progressManager,
			$this->logger
		);

		$result = $mediaProcessor->convertSingleImage( $attachment_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message' => __( 'Image converted successfully', 'wp-image-optimizer' ),
					'webp'    => $result['webp'],
					'avif'    => $result['avif'],
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to convert image', 'wp-image-optimizer' ) ) );
		}
	}

	public function ajaxBulkConvert(): void {
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		global $wpdb;

		// Get all image attachments
		$attachments = $wpdb->get_results(
			"SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ('image/jpeg', 'image/png')"
		);

		$ids = array_map(
			function ( $attachment ) {
				return (int) $attachment->ID;
			},
			$attachments
		);

		// Generate a unique process ID
		$process_id = 'bulk_convert_' . uniqid();

		// Start a background process
		$mediaProcessor = new \WpImageOptimizer\Media\MediaProcessor(
			$this->container->get( 'webp_converter' ),
			$this->container->get( 'avif_converter' ),
			$this->settings,
			$this->progressManager,
			$this->logger
		);

		// Start the process
		$mediaProcessor->bulkConvertImages( $ids );

		wp_send_json_success(
			array(
				'message'    => __( 'Bulk conversion started', 'wp-image-optimizer' ),
				'process_id' => $process_id,
				'total'      => count( $ids ),
			)
		);
	}
}
