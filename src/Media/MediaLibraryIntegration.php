<?php

namespace WpImageOptimizer\Media;

use WpImageOptimizer\Core\Settings;

class MediaLibraryIntegration {
	public function __construct(
		private Settings $settings,
		private MediaProcessor $mediaProcessor
	) {}

	/**
	 * Register hooks for media library integration
	 */
	public function registerHooks(): void {
		// Add convert button to media library
		add_filter( 'attachment_fields_to_edit', array( $this, 'addConvertButtons' ), 10, 2 );

		// Add status column to media library
		add_filter( 'manage_media_columns', array( $this, 'addStatusColumn' ) );
		add_action( 'manage_media_custom_column', array( $this, 'renderStatusColumn' ), 10, 2 );

		// Filter media items by conversion status
		add_action( 'pre_get_posts', array( $this, 'filterMediaItems' ) );

		// Add bulk action
		add_filter( 'bulk_actions-upload', array( $this, 'addBulkActions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handleBulkActions' ), 10, 3 );

		// Add admin notices
		add_action( 'admin_notices', array( $this, 'showAdminNotices' ) );
	}

	/**
	 * Add convert buttons to media modal
	 */
	public function addConvertButtons( array $form_fields, object $post ): array {
		// Only show for supported image types
		if ( ! $this->isSupportedImageType( $post->post_mime_type ) ) {
			return $form_fields;
		}

		$meta    = wp_get_attachment_metadata( $post->ID );
		$hasWebp = ! empty( $meta['webp_path'] ) && file_exists( $meta['webp_path'] );
		$hasAvif = ! empty( $meta['avif_path'] ) && file_exists( $meta['avif_path'] );

		// Add convert button
		$form_fields['wp_image_optimizer'] = array(
			'label' => __( 'WebP & AVIF', 'wp-image-optimizer' ),
			'input' => 'html',
			'html'  => $this->getConversionButtonsHtml( $post->ID, $hasWebp, $hasAvif ),
		);

		return $form_fields;
	}

	/**
	 * Add status column to media library
	 */
	public function addStatusColumn( array $columns ): array {
		$columns['wp_image_optimizer'] = __( 'WebP & AVIF', 'wp-image-optimizer' );
		return $columns;
	}

	/**
	 * Render status column in media library
	 */
	public function renderStatusColumn( string $column_name, int $post_id ): void {
		if ( $column_name !== 'wp_image_optimizer' ) {
			return;
		}

		$post = get_post( $post_id );

		// Only show for supported image types
		if ( ! $this->isSupportedImageType( $post->post_mime_type ) ) {
			echo '<span class="dashicons dashicons-minus"></span>';
			return;
		}

		$meta    = wp_get_attachment_metadata( $post_id );
		$hasWebp = ! empty( $meta['webp_path'] ) && file_exists( $meta['webp_path'] );
		$hasAvif = ! empty( $meta['avif_path'] ) && file_exists( $meta['avif_path'] );

		if ( $hasWebp && $hasAvif ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color:green;" title="' . esc_attr__( 'Both WebP and AVIF versions available', 'wp-image-optimizer' ) . '"></span>';
		} elseif ( $hasWebp ) {
			echo '<span class="dashicons dashicons-yes" style="color:orange;" title="' . esc_attr__( 'WebP version available', 'wp-image-optimizer' ) . '"></span>';
		} elseif ( $hasAvif ) {
			echo '<span class="dashicons dashicons-yes" style="color:blue;" title="' . esc_attr__( 'AVIF version available', 'wp-image-optimizer' ) . '"></span>';
		} else {
			echo '<span class="dashicons dashicons-no" style="color:red;" title="' . esc_attr__( 'No optimized versions available', 'wp-image-optimizer' ) . '"></span>';
		}
	}

	/**
	 * Filter media items by conversion status
	 */
	public function filterMediaItems( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'upload' ) {
			return;
		}

		$status = $_GET['wp_image_optimizer_status'] ?? '';
		if ( ! $status ) {
			return;
		}

		// Add filter to the meta query
		$meta_query = $query->get( 'meta_query', array() );

		switch ( $status ) {
			case 'has_webp':
				$meta_query[] = array(
					'key'     => '_wp_attachment_metadata',
					'value'   => 'webp_path',
					'compare' => 'LIKE',
				);
				break;

			case 'has_avif':
				$meta_query[] = array(
					'key'     => '_wp_attachment_metadata',
					'value'   => 'avif_path',
					'compare' => 'LIKE',
				);
				break;

			case 'has_none':
				$meta_query[] = array(
					'relation' => 'AND',
					array(
						'key'     => '_wp_attachment_metadata',
						'value'   => 'webp_path',
						'compare' => 'NOT LIKE',
					),
					array(
						'key'     => '_wp_attachment_metadata',
						'value'   => 'avif_path',
						'compare' => 'NOT LIKE',
					),
				);
				break;
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Add bulk actions to media library
	 */
	public function addBulkActions( array $bulk_actions ): array {
		$bulk_actions['wp_image_optimizer_convert'] = __( 'Convert to WebP & AVIF', 'wp-image-optimizer' );
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions
	 */
	public function handleBulkActions( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( $doaction !== 'wp_image_optimizer_convert' ) {
			return $redirect_to;
		}

		// Filter to supported image types
		$supported_ids = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post && $this->isSupportedImageType( $post->post_mime_type ) ) {
				$supported_ids[] = $post_id;
			}
		}

		// Start bulk conversion in the background
		$this->mediaProcessor->bulkConvertImages( $supported_ids );

		// Add query args for admin notice
		$redirect_to = add_query_arg(
			'wp_image_optimizer_bulk_converted',
			count( $supported_ids ),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Show admin notices
	 */
	public function showAdminNotices(): void {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'upload' ) {
			return;
		}

		// Show bulk conversion notice
		$converted = $_GET['wp_image_optimizer_bulk_converted'] ?? 0;
		if ( $converted ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				__( 'Started conversion of %d images to WebP and AVIF formats. This process runs in the background.', 'wp-image-optimizer' ),
				$converted
			);
			echo '</p></div>';
		}
	}

	/**
	 * Get HTML for conversion buttons
	 */
	private function getConversionButtonsHtml( int $attachment_id, bool $has_webp, bool $has_avif ): string {
		$html = '<div class="wp-image-optimizer-buttons">';

		// WebP status
		$html .= '<div class="wp-image-optimizer-status">';
		$html .= '<span class="wp-image-optimizer-webp">';
		$html .= '<strong>WebP:</strong> ';
		if ( $has_webp ) {
			$html .= '<span class="dashicons dashicons-yes" style="color:green;"></span>';
		} else {
			$html .= '<span class="dashicons dashicons-no" style="color:red;"></span>';
		}
		$html .= '</span> ';

		// AVIF status
		$html .= '<span class="wp-image-optimizer-avif">';
		$html .= '<strong>AVIF:</strong> ';
		if ( $has_avif ) {
			$html .= '<span class="dashicons dashicons-yes" style="color:green;"></span>';
		} else {
			$html .= '<span class="dashicons dashicons-no" style="color:red;"></span>';
		}
		$html .= '</span>';
		$html .= '</div>';

		// Convert button
		$html .= '<button type="button" class="button wp-image-optimizer-convert" data-id="' . esc_attr( $attachment_id ) . '">';
		$html .= '<span class="spinner" style="float:none;margin-top:0;"></span> ';
		$html .= __( 'Convert Now', 'wp-image-optimizer' );
		$html .= '</button>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Check if a MIME type is a supported image type
	 */
	private function isSupportedImageType( string $mime_type ): bool {
		return in_array( $mime_type, array( 'image/jpeg', 'image/png' ) );
	}
}
