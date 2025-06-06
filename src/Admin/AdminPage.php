<?php

namespace WpImageOptimizer\Admin;

use WpImageOptimizer\Core\Container;
use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Stats;
use WpImageOptimizer\Utility\Logger;
use WpImageOptimizer\Utility\ProgressManager;
use WpImageOptimizer\Media\MediaProcessor;

class AdminPage {
    private Container $container;
    private Settings $settings;
    private Stats $stats;
    private Logger $logger;
    private ProgressManager $progressManager;

    public function __construct(
        Container $container,
        Settings $settings,
        Stats $stats,
        Logger $logger,
        ProgressManager $progressManager
    ) {
        $this->container       = $container;
        $this->settings        = $settings;
        $this->stats           = $stats;
        $this->logger          = $logger;
        $this->progressManager = $progressManager;
    }
	
	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'addMenuPages' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );

		add_action( 'wp_ajax_wp_image_optimizer_process_batch', array( $this, 'ajaxProcessBatch' ) );
		add_action( 'wp_ajax_wp_image_optimizer_get_progress', array( $this, 'ajaxGetProgress' ) );
		add_action( 'wp_ajax_wp_image_optimizer_convert_single', array( $this, 'ajaxConvertSingle' ) );
		add_action( 'wp_ajax_wp_image_optimizer_bulk_convert', array( $this, 'ajaxBulkConvert' ) );
		add_action( 'wp_ajax_wp_image_optimizer_clear_debug_log', array( $this, 'ajaxClearDebugLog' ) );
		
		// Register background bulk conversion hooks
		add_action( 'wp_ajax_nopriv_wp_image_optimizer_background_process', array( $this, 'handleBackgroundProcess' ) );
		add_action( 'wp_ajax_wp_image_optimizer_background_process', array( $this, 'handleBackgroundProcess' ) );
	}

	public function addMenuPages(): void {
		add_options_page(
			__( 'WebP & AVIF Optimizer', 'wp-image-optimizer' ),
			__( 'WebP & AVIF', 'wp-image-optimizer' ),
			'manage_options',
			'wp-image-optimizer',
			array( $this, 'renderSettingsPage' )
		);
	}

	public function registerSettings(): void {
		register_setting( 'wp_image_optimizer_settings', 'wp_image_optimizer_settings' );

		add_settings_section(
			'wp_image_optimizer_general',
			__( 'General Settings', 'wp-image-optimizer' ),
			array( $this, 'renderGeneralSection' ),
			'wp_image_optimizer'
		);

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

		add_settings_field(
			'wp_image_optimizer_convert_thumbnails',
			__( 'Convert Thumbnails', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_general',
			array(
				'id'          => 'convert_thumbnails',
				'description' => __( 'Also convert thumbnail sizes to WebP and AVIF formats', 'wp-image-optimizer' ),
			)
		);

		add_settings_field(
			'wp_image_optimizer_skip_converted',
			__( 'Skip Already Converted', 'wp-image-optimizer' ),
			array( $this, 'renderCheckboxField' ),
			'wp_image_optimizer',
			'wp_image_optimizer_general',
			array(
				'id'          => 'skip_converted',
				'description' => __( "Don't convert already converted images in bulk", 'wp-image-optimizer' ),
			)
		);

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

	public function enqueueAssets( string $hook ): void {
	    $media_screens = array('upload.php', 'post.php', 'post-new.php', 'media-new.php');
	    $is_media_or_settings = in_array($hook, $media_screens) || $hook === 'settings_page_wp-image-optimizer';
		
		if ($is_media_or_settings) {
		    $this->logger->info("Enqueuing assets on screen: $hook");
		    
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
    			WP_IMAGE_OPTIMIZER_VERSION . '-' . time(), // Add timestamp to force cache refresh
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
                        'convert'    => __( 'Convert Now', 'wp-image-optimizer' ),
    				),
    			)
    		);
		}
	}

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
				<a href="?page=wp-image-optimizer&tab=debug" class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Debug Log', 'wp-image-optimizer' ); ?>
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
				} elseif ( $active_tab === 'debug' ) {
					$this->renderDebugTab();
				}
				?>
			</div>
		</div>
		<?php
	}

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

	private function renderBulkTab(): void {
		$supportedCount = $this->stats->getSupportedFilesCount();
		
		// If skip converted is enabled, show count of images that actually need conversion
		$displayCount = $supportedCount;
		$skipConverted = $this->settings->get('skip_converted', false);
		$convertThumbnails = $this->settings->get('convert_thumbnails', false);
		
		if ($skipConverted) {
			global $wpdb;
			$attachments = $wpdb->get_results(
				"SELECT ID FROM {$wpdb->posts} 
				WHERE post_type = 'attachment' 
				AND post_mime_type IN ('image/jpeg', 'image/png')"
			);
			$ids = array_map(function($attachment) { return (int) $attachment->ID; }, $attachments);
			$filteredIds = $this->filterUnconvertedImages($ids);
			
			// Calculate total files including thumbnails
			$fileData = $this->calculateTotalFiles($filteredIds);
			$displayCount = $convertThumbnails ? $fileData['total_files'] : count($filteredIds);
		} else {
			// For non-filtered view, we need to get all attachments and calculate
			global $wpdb;
			$attachments = $wpdb->get_results(
				"SELECT ID FROM {$wpdb->posts} 
				WHERE post_type = 'attachment' 
				AND post_mime_type IN ('image/jpeg', 'image/png')"
			);
			$ids = array_map(function($attachment) { return (int) $attachment->ID; }, $attachments);
			
			$fileData = $this->calculateTotalFiles($ids);
			$displayCount = $convertThumbnails ? $fileData['total_files'] : count($ids);
		}
		?>
		<div class="wp-image-optimizer-bulk">
			<h2><?php _e( 'Bulk Convert Images', 'wp-image-optimizer' ); ?></h2>
			<?php if ($skipConverted): ?>
				<p><?php printf( __( 'Found %d images that need conversion to WebP and AVIF formats (already converted images will be skipped).', 'wp-image-optimizer' ), $displayCount ); ?></p>
			<?php else: ?>
				<p><?php printf( __( 'Found %d images that can be converted to WebP and AVIF formats.', 'wp-image-optimizer' ), $displayCount ); ?></p>
			<?php endif; ?>
			
			<div class="wp-image-optimizer-progress-wrapper" style="display:none;">
				<div class="wp-image-optimizer-progress">
					<div class="wp-image-optimizer-progress-bar"></div>
				</div>
				<div class="wp-image-optimizer-progress-text">
					<span class="wp-image-optimizer-progress-percentage">0%</span>
					<span class="wp-image-optimizer-progress-count">(0/<?php echo $displayCount; ?>)</span>
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

	public function ajaxProcessBatch(): void {
	    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] AJAX process_batch called\n", FILE_APPEND);
	    $this->logger->info('AJAX process_batch called');
	    
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$process_id = $_POST['process_id'] ?? '';
		if ( ! $process_id ) {
			wp_send_json_error( array( 'message' => 'Missing process ID' ) );
		}

		$batch_data = get_transient("wp_image_optimizer_bulk_data_$process_id");
		if (!$batch_data) {
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] No batch data found for $process_id\n", FILE_APPEND);
			wp_send_json_error( array( 'message' => 'Process data not found' ) );
		}

		$batchSize = (int) $this->settings->get( 'bulk_batch_size', 5 ); // Smaller batch size for better responsiveness
		$processed = $batch_data['processed'];
		$totalAttachments = $batch_data['total'];
		$totalFiles = $batch_data['total_files'];
		$processedFiles = $batch_data['processed_files'];
		$ids = $batch_data['ids'];
		
		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Processing batch for $process_id - processed attachments: $processed/$totalAttachments, processed files: $processedFiles/$totalFiles, batch size: $batchSize\n", FILE_APPEND);

		$batchIds = array_slice($ids, $processed, $batchSize);
		$mediaProcessor = $this->container->get('media_processor');
		
		$batchResults = [];
		$successCount = 0;
		foreach ($batchIds as $attachmentId) {
		    // Count files for this attachment (main + thumbnails)
		    $attachmentFiles = 1; // Main image
		    if ($this->settings->get('convert_thumbnails', false)) {
		        $metadata = wp_get_attachment_metadata($attachmentId);
		        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
		            $attachmentFiles += count($metadata['sizes']);
		        }
		    }
		    
		    $result = $mediaProcessor->convertSingleImage($attachmentId);
		    $batchResults[] = [
		        'id' => $attachmentId,
		        'success' => $result['success'],
		        'webp' => $result['webp'],
		        'avif' => $result['avif'],
		        'files_processed' => $attachmentFiles
		    ];
		    if ($result['success']) {
		        $successCount++;
		    }
		    $processed++;
		    $processedFiles += $attachmentFiles;
		    $this->progressManager->updateProgress($process_id, $processedFiles);
		}
		
		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Batch completed: $successCount/$batchSize images converted successfully\n", FILE_APPEND);

		// Update batch data
		$batch_data['processed'] = $processed;
		$batch_data['processed_files'] = $processedFiles;
		set_transient("wp_image_optimizer_bulk_data_$process_id", $batch_data, 3600);
		
		$isComplete = $processed >= $totalAttachments;
		if ($isComplete) {
		    delete_transient("wp_image_optimizer_bulk_data_$process_id");
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] 🎉 BULK CONVERSION COMPLETE! Processed $processed/$totalAttachments images for $process_id\n", FILE_APPEND);
		}

		wp_send_json_success([
		    'processed' => $processedFiles,
		    'total' => $totalFiles,
		    'percentage' => round(($processedFiles / $totalFiles) * 100, 2),
		    'complete' => $isComplete,
		    'batch_results' => $batchResults
		]);
	}

	public function ajaxGetProgress(): void {
	    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] AJAX get_progress called\n", FILE_APPEND);
	    $this->logger->info('AJAX get_progress called');
	    
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Insufficient permissions for get_progress\n", FILE_APPEND);
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$process_id = $_POST['process_id'] ?? '';
		if ( ! $process_id ) {
		    $this->logger->error('Missing process ID in AJAX request');
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Missing process ID in get_progress\n", FILE_APPEND);
			wp_send_json_error( array( 'message' => 'Missing process ID' ) );
		}
		
		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Getting progress for process: $process_id\n", FILE_APPEND);

		$progress = $this->progressManager->getProgress( $process_id );
		if ( ! $progress ) {
		    $this->logger->error("Process not found: $process_id");
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Process not found: $process_id\n", FILE_APPEND);
			wp_send_json_error( array( 'message' => 'Process not found' ) );
		}
		
		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Returning progress: " . json_encode($progress) . "\n", FILE_APPEND);
		$this->logger->info("Returning progress for $process_id: " . print_r($progress, true));

		wp_send_json_success( $progress );
	}

	public function ajaxConvertSingle(): void {
	    $this->logger->info('AJAX convert_single called');
	    
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
		    $this->logger->error('Missing attachment ID in AJAX request');
			wp_send_json_error( array( 'message' => 'Missing attachment ID' ) );
		}

		$this->logger->info( "Single conversion requested for attachment ID: $attachment_id" );
		
		$mediaProcessor = $this->container->get('media_processor');
		
		$result = $mediaProcessor->convertSingleImage( $attachment_id );
		
        $this->logger->info( "Conversion result for attachment ID: $attachment_id", [
            'success' => $result['success'],
            'webp' => $result['webp'],
            'avif' => $result['avif']
        ]);

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
	    // Log to debug file
	    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] AJAX bulk_convert called\n", FILE_APPEND);
	    $this->logger->info('AJAX bulk_convert called');
	    
		check_ajax_referer( 'wp-image-optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
		    $this->logger->error('Insufficient permissions for bulk conversion');
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Insufficient permissions\n", FILE_APPEND);
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		global $wpdb;

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

		// Filter out already converted images if "skip converted" is enabled
		if ($this->settings->get('skip_converted', false)) {
		    $this->logger->info('Filtering out already converted images');
		    $ids = $this->filterUnconvertedImages($ids);
		    $this->logger->info('After filtering: ' . count($ids) . ' images remain');
		}

		$process_id = 'bulk_convert_' . uniqid();
		
		// Calculate total files including thumbnails
		$fileData = $this->calculateTotalFiles($ids);
		$totalFiles = $this->settings->get('convert_thumbnails', false) ? $fileData['total_files'] : count($ids);
		
		$this->logger->info( "Bulk conversion requested with process ID: $process_id", [
		    'total_attachments' => count($ids),
		    'total_files' => $totalFiles,
		    'convert_thumbnails' => $this->settings->get('convert_thumbnails', false)
		]);
		
		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Starting process $process_id with " . count($ids) . " attachments (" . $totalFiles . " total files)\n", FILE_APPEND);

        $this->progressManager->startProcess($process_id, $totalFiles);
        
		if (count($ids) > 0) {
    		// Store the conversion data for batch processing
    		set_transient("wp_image_optimizer_bulk_data_$process_id", [
    		    'ids' => $ids,
    		    'total' => count($ids),
    		    'total_files' => $totalFiles,
    		    'processed' => 0,
    		    'processed_files' => 0
    		], 3600);
    		
    		$this->logger->info("Bulk conversion started for process ID: $process_id");
    		file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Bulk conversion initialized for $process_id\n", FILE_APPEND);
    		
    		wp_send_json_success([
    			'message'    => __( 'Bulk conversion started', 'wp-image-optimizer' ),
    			'process_id' => $process_id,
    			'total'      => $totalFiles, // Send total files instead of total attachments
    		]);
		} else {
		    $this->logger->warning("No images found for bulk conversion");
		    file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] No images found for conversion\n", FILE_APPEND);
		    wp_send_json_error(['message' => __('No images found to convert', 'wp-image-optimizer')]);
		}
	}
	
	/**
	 * AJAX handler to clear debug log
	 */
	public function ajaxClearDebugLog(): void {
		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'], 'wp_image_optimizer_nonce')) {
			wp_send_json_error(['message' => __('Invalid nonce', 'wp-image-optimizer')]);
			return;
		}

		// Check user capabilities
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Insufficient permissions', 'wp-image-optimizer')]);
			return;
		}

		$debug_file = dirname(__DIR__, 2) . '/debug.log';
		
		if (file_exists($debug_file)) {
			if (unlink($debug_file)) {
				wp_send_json_success(['message' => __('Debug log cleared successfully', 'wp-image-optimizer')]);
			} else {
				wp_send_json_error(['message' => __('Failed to clear debug log', 'wp-image-optimizer')]);
			}
		} else {
			wp_send_json_success(['message' => __('Debug log already empty', 'wp-image-optimizer')]);
		}
	}
	
	/**
	 * Calculate total number of files that will be processed including thumbnails
	 *
	 * @param array $ids Array of attachment IDs
	 * @return array Returns ['total_files' => int, 'attachments' => int, 'thumbnails' => int]
	 */
	private function calculateTotalFiles(array $ids): array {
		$totalFiles = 0;
		$totalThumbnails = 0;
		$convertThumbnails = $this->settings->get('convert_thumbnails', false);
		
		foreach ($ids as $id) {
			// Count the main image
			$totalFiles++;
			
			// Count thumbnails if enabled
			if ($convertThumbnails) {
				$metadata = wp_get_attachment_metadata($id);
				if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
					$thumbnailCount = count($metadata['sizes']);
					$totalThumbnails += $thumbnailCount;
					$totalFiles += $thumbnailCount;
				}
			}
		}
		
		// Debug logging for first few images
		if (count($ids) > 0) {
			$sampleCount = min(3, count($ids));
			file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] Sample file counts (convert_thumbnails=" . ($convertThumbnails ? 'true' : 'false') . "):\n", FILE_APPEND);
			for ($i = 0; $i < $sampleCount; $i++) {
				$sampleId = $ids[$i];
				$sampleFileCount = 1;
				if ($convertThumbnails) {
					$metadata = wp_get_attachment_metadata($sampleId);
					if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
						$sampleFileCount += count($metadata['sizes']);
					}
				}
				file_put_contents(WP_IMAGE_OPTIMIZER_DIR . 'debug.log', "[" . date('Y-m-d H:i:s') . "] - Attachment $sampleId: $sampleFileCount files\n", FILE_APPEND);
			}
		}
		
		return [
			'total_files' => $totalFiles,
			'attachments' => count($ids),
			'thumbnails' => $totalThumbnails
		];
	}
	
	/**
	 * Filter out images that are already converted when skip_converted setting is enabled
	 *
	 * @param array $ids Array of attachment IDs to filter
	 * @return array Filtered array of attachment IDs
	 */
	private function filterUnconvertedImages(array $ids): array {
		$filtered = [];
		
		foreach ($ids as $id) {
			$meta = wp_get_attachment_metadata($id);
			
			// Check if image already has both WebP and AVIF versions
			$hasWebp = !empty($meta['webp_path']) && file_exists($meta['webp_path']);
			$hasAvif = !empty($meta['avif_path']) && file_exists($meta['avif_path']);
			
			// Determine what conversions we need based on settings
			$needsWebp = $this->settings->get('enable_webp', true) && !$hasWebp;
			$needsAvif = $this->settings->get('enable_avif', true) && !$hasAvif;
			
			// Only include if image needs conversion
			if ($needsWebp || $needsAvif) {
				$filtered[] = $id;
				$this->logger->info("Image $id needs conversion - WebP: " . ($needsWebp ? 'yes' : 'no') . ", AVIF: " . ($needsAvif ? 'yes' : 'no'));
			} else {
				$this->logger->info("Image $id already converted - WebP: " . ($hasWebp ? 'yes' : 'no') . ", AVIF: " . ($hasAvif ? 'yes' : 'no'));
			}
		}
		
		$this->logger->info("Filtered " . count($ids) . " images down to " . count($filtered) . " that need conversion");
		
		return $filtered;
	}

	/**
	 * Render the debug tab
	 */
	private function renderDebugTab(): void {
		$debug_file = dirname(__DIR__, 2) . '/debug.log';
		$debug_content = '';
		
		if (file_exists($debug_file)) {
			$debug_content = file_get_contents($debug_file);
			// Show only last 100 lines for performance
			$lines = explode("\n", $debug_content);
			if (count($lines) > 100) {
				$lines = array_slice($lines, -100);
				$debug_content = implode("\n", $lines);
			}
		}
		?>
		<div class="wp-image-optimizer-debug-tab">
			<h3><?php _e('Debug Information', 'wp-image-optimizer'); ?></h3>
			
			<div class="debug-actions">
				<button type="button" class="button" onclick="location.reload()">
					<?php _e('Refresh Log', 'wp-image-optimizer'); ?>
				</button>
				<button type="button" class="button" onclick="clearDebugLog()">
					<?php _e('Clear Log', 'wp-image-optimizer'); ?>
				</button>
			</div>
			
			<div class="debug-log-container">
				<h4><?php _e('Debug Log', 'wp-image-optimizer'); ?></h4>
				<textarea readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($debug_content); ?></textarea>
			</div>
			
			<div class="debug-info">
				<h4><?php _e('System Information', 'wp-image-optimizer'); ?></h4>
				<table class="widefat">
					<tr>
						<th><?php _e('WordPress Version', 'wp-image-optimizer'); ?></th>
						<td><?php echo get_bloginfo('version'); ?></td>
					</tr>
					<tr>
						<th><?php _e('PHP Version', 'wp-image-optimizer'); ?></th>
						<td><?php echo PHP_VERSION; ?></td>
					</tr>
					<tr>
						<th><?php _e('GD Extension', 'wp-image-optimizer'); ?></th>
						<td><?php echo extension_loaded('gd') ? __('Available', 'wp-image-optimizer') : __('Not Available', 'wp-image-optimizer'); ?></td>
					</tr>
					<tr>
						<th><?php _e('WebP Support', 'wp-image-optimizer'); ?></th>
						<td><?php echo (extension_loaded('gd') && function_exists('imagewebp')) ? __('Available', 'wp-image-optimizer') : __('Not Available', 'wp-image-optimizer'); ?></td>
					</tr>
					<tr>
						<th><?php _e('AVIF Support', 'wp-image-optimizer'); ?></th>
						<td><?php echo (extension_loaded('gd') && function_exists('imageavif')) ? __('Available', 'wp-image-optimizer') : __('Not Available', 'wp-image-optimizer'); ?></td>
					</tr>
					<tr>
						<th><?php _e('Max Execution Time', 'wp-image-optimizer'); ?></th>
						<td><?php echo ini_get('max_execution_time'); ?> <?php _e('seconds', 'wp-image-optimizer'); ?></td>
					</tr>
					<tr>
						<th><?php _e('Memory Limit', 'wp-image-optimizer'); ?></th>
						<td><?php echo ini_get('memory_limit'); ?></td>
					</tr>
					<tr>
						<th><?php _e('Upload Max Filesize', 'wp-image-optimizer'); ?></th>
						<td><?php echo ini_get('upload_max_filesize'); ?></td>
					</tr>
					<tr>
						<th><?php _e('Debug File Path', 'wp-image-optimizer'); ?></th>
						<td><?php echo esc_html($debug_file); ?></td>
					</tr>
					<tr>
						<th><?php _e('Debug File Exists', 'wp-image-optimizer'); ?></th>
						<td><?php echo file_exists($debug_file) ? __('Yes', 'wp-image-optimizer') : __('No', 'wp-image-optimizer'); ?></td>
					</tr>
				</table>
			</div>
		</div>
		
		<script>
		function clearDebugLog() {
			if (confirm('<?php echo esc_js(__('Are you sure you want to clear the debug log?', 'wp-image-optimizer')); ?>')) {
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wp_image_optimizer_clear_debug_log',
						nonce: '<?php echo wp_create_nonce('wp_image_optimizer_nonce'); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('<?php echo esc_js(__('Failed to clear debug log', 'wp-image-optimizer')); ?>');
						}
					}
				});
			}
		}
		</script>
		<?php
	}
}
