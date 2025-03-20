<?php

namespace WpImageOptimizer\Media;

use WpImageOptimizer\Conversion\ConverterInterface;
use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\ProgressManager;
use WpImageOptimizer\Utility\Logger;

class MediaProcessor {
	public function __construct(
		private ConverterInterface $webpConverter,
		private ConverterInterface $avifConverter,
		private Settings $settings,
		private ProgressManager $progressManager,
		private Logger $logger
	) {}

	/**
	 * Process image on upload
	 */
	public function processUploadedMedia( array $upload ): array {
		// Skip processing if auto-convert is disabled
		if ( ! $this->settings->get( 'auto_convert', true ) ) {
			return $upload;
		}

		// Check if the file is a supported image type
		if ( ! $this->isSupportedImage( $upload['file'] ) ) {
			return $upload;
		}

		$attachmentId = $this->getAttachmentIdByUrl( $upload['url'] );
		if ( ! $attachmentId ) {
			return $upload;
		}

		// Convert to WebP if enabled
		if ( $this->settings->get( 'enable_webp', true ) && $this->webpConverter->isSupported() ) {
			$this->convertToFormat( $attachmentId, 'webp' );
		}

		// Convert to AVIF if enabled
		if ( $this->settings->get( 'enable_avif', true ) && $this->avifConverter->isSupported() ) {
			$this->convertToFormat( $attachmentId, 'avif' );
		}

		return $upload;
	}

	/**
	 * Convert a single image by attachment ID
	 */
	public function convertSingleImage( int $attachmentId ): array {
		$result = array(
			'success' => false,
			'webp'    => false,
			'avif'    => false,
		);

		$file = get_attached_file( $attachmentId );
		if ( ! $file || ! $this->isSupportedImage( $file ) ) {
			return $result;
		}

		if ( $this->settings->get( 'enable_webp', true ) && $this->webpConverter->isSupported() ) {
			$result['webp'] = $this->convertToFormat( $attachmentId, 'webp' );
		}

		if ( $this->settings->get( 'enable_avif', true ) && $this->avifConverter->isSupported() ) {
			$result['avif'] = $this->convertToFormat( $attachmentId, 'avif' );
		}

		$result['success'] = $result['webp'] || $result['avif'];

		return $result;
	}

	/**
	 * Bulk convert multiple images
	 */
	public function bulkConvertImages( array $ids ): void {
		$totalImages = count( $ids );
		$processId   = 'bulk_convert_' . uniqid();

		// Initialize progress tracking
		$this->progressManager->startProcess( $processId, $totalImages );
		$this->logger->info( "Starting bulk conversion of $totalImages images", array( 'process_id' => $processId ) );

		// Process in batches to prevent timeouts
		$batchSize = (int) $this->settings->get( 'bulk_batch_size', 10 );
		$batches   = array_chunk( $ids, $batchSize );

		foreach ( $batches as $index => $batch ) {
			// Process each image in the batch
			foreach ( $batch as $batchIndex => $attachmentId ) {
				$result    = $this->convertSingleImage( $attachmentId );
				$processed = ( $index * $batchSize ) + $batchIndex + 1;

				$this->progressManager->updateProgress( $processId, $processed );

				$this->logger->info(
					"Processed image $processed/$totalImages",
					array(
						'attachment_id' => $attachmentId,
						'success'       => $result['success'],
						'webp'          => $result['webp'],
						'avif'          => $result['avif'],
					)
				);
			}

			// Allow a pause between batches to prevent server overload
			if ( $index < count( $batches ) - 1 ) {
				$delay = (int) $this->settings->get( 'processing_delay', 250 );
				usleep( $delay * 1000 ); // Convert to microseconds
			}
		}

		$this->logger->info( "Completed bulk conversion of $totalImages images", array( 'process_id' => $processId ) );
	}

	/**
	 * Convert an attachment to a specific format
	 */
	private function convertToFormat( int $attachmentId, string $format ): bool {
		$file = get_attached_file( $attachmentId );
		if ( ! $file ) {
			return false;
		}

		$destPath  = $this->getDestinationPath( $file, $format );
		$converter = $format === 'webp' ? $this->webpConverter : $this->avifConverter;

		$success = $converter->convert( $file, $destPath, array() );

		if ( $success ) {
			// Update attachment metadata
			$meta                     = wp_get_attachment_metadata( $attachmentId );
			$meta[ "{$format}_path" ] = $destPath;
			$meta[ "{$format}_url" ]  = $this->getWebUrl( $destPath );
			wp_update_attachment_metadata( $attachmentId, $meta );
		}

		return $success;
	}

	/**
	 * Get the destination path for a converted image
	 */
	private function getDestinationPath( string $sourcePath, string $format ): string {
		$pathInfo = pathinfo( $sourcePath );
		return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;
	}

	/**
	 * Get web URL for a file path
	 */
	private function getWebUrl( string $filePath ): string {
		$uploadsDir = wp_upload_dir();
		$baseDir    = $uploadsDir['basedir'];
		$baseUrl    = $uploadsDir['baseurl'];

		return str_replace( $baseDir, $baseUrl, $filePath );
	}

	/**
	 * Check if a file is a supported image type
	 */
	private function isSupportedImage( string $file ): bool {
		$supportedTypes = array( 'jpg', 'jpeg', 'png' );
		$extension      = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		return in_array( $extension, $supportedTypes );
	}

	/**
	 * Get attachment ID by URL
	 */
	private function getAttachmentIdByUrl( string $url ): ?int {
		global $wpdb;

		$uploadDir = wp_upload_dir();
		$baseUrl   = $uploadDir['baseurl'];

		// Remove the base URL to get the relative path
		$relativePath = str_replace( $baseUrl, '', $url );

		// Find attachment by relative path
		$attachmentId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
				ltrim( $relativePath, '/' )
			)
		);

		return $attachmentId ? (int) $attachmentId : null;
	}
}
