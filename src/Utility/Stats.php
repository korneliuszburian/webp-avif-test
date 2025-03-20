<?php

namespace WpImageOptimizer\Utility;

class Stats {
	/**
	 * Count total number of images with WebP/AVIF versions
	 */
	public function countConvertedImages(): array {
		global $wpdb;

		$results = array(
			'total' => 0,
			'webp'  => 0,
			'avif'  => 0,
			'both'  => 0,
		);

		// Get all image attachments
		$imageAttachments = $wpdb->get_results(
			"SELECT ID, post_mime_type FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
            AND post_mime_type NOT LIKE 'image/webp'
            AND post_mime_type NOT LIKE 'image/avif'"
		);

		$results['total'] = count( $imageAttachments );

		// Count conversions
		foreach ( $imageAttachments as $attachment ) {
			$meta = wp_get_attachment_metadata( $attachment->ID );

			$hasWebp = ! empty( $meta['webp_path'] ) && file_exists( $meta['webp_path'] );
			$hasAvif = ! empty( $meta['avif_path'] ) && file_exists( $meta['avif_path'] );

			if ( $hasWebp ) {
				++$results['webp'];
			}

			if ( $hasAvif ) {
				++$results['avif'];
			}

			if ( $hasWebp && $hasAvif ) {
				++$results['both'];
			}
		}

		return $results;
	}

	/**
	 * Calculate space saved by WebP/AVIF conversions
	 */
	public function calculateSpaceSaved(): array {
		global $wpdb;

		$results = array(
			'original_size'    => 0,
			'webp_size'        => 0,
			'avif_size'        => 0,
			'total_saved'      => 0,
			'percentage_saved' => 0,
		);

		// Get all image attachments
		$imageAttachments = $wpdb->get_results(
			"SELECT ID, post_mime_type FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
            AND post_mime_type NOT LIKE 'image/webp'
            AND post_mime_type NOT LIKE 'image/avif'"
		);

		// Calculate sizes
		foreach ( $imageAttachments as $attachment ) {
			$meta         = wp_get_attachment_metadata( $attachment->ID );
			$originalFile = get_attached_file( $attachment->ID );

			if ( ! $originalFile || ! file_exists( $originalFile ) ) {
				continue;
			}

			$originalSize              = filesize( $originalFile );
			$results['original_size'] += $originalSize;

			// Check WebP
			if ( ! empty( $meta['webp_path'] ) && file_exists( $meta['webp_path'] ) ) {
				$webpSize              = filesize( $meta['webp_path'] );
				$results['webp_size'] += $webpSize;
			}

			// Check AVIF
			if ( ! empty( $meta['avif_path'] ) && file_exists( $meta['avif_path'] ) ) {
				$avifSize              = filesize( $meta['avif_path'] );
				$results['avif_size'] += $avifSize;
			}
		}

		// Calculate savings
		if ( $results['original_size'] > 0 ) {
			$smallestSize = min(
				$results['webp_size'] > 0 ? $results['webp_size'] : PHP_INT_MAX,
				$results['avif_size'] > 0 ? $results['avif_size'] : PHP_INT_MAX
			);

			if ( $smallestSize < PHP_INT_MAX ) {
				$results['total_saved']      = $results['original_size'] - $smallestSize;
				$results['percentage_saved'] = round( ( $results['total_saved'] / $results['original_size'] ) * 100, 2 );
			}
		}

		return $results;
	}

	/**
	 * Get count of supported image files in media library
	 */
	public function getSupportedFilesCount(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND (
                post_mime_type LIKE 'image/jpeg' OR
                post_mime_type LIKE 'image/jpg' OR
                post_mime_type LIKE 'image/png'
            )"
		);
	}
}
