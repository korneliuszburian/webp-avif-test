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

        $this->logger->info("Processing uploaded media: " . $upload['file']);

		$attachmentId = $this->getAttachmentIdByUrl( $upload['url'] );
		if ( ! $attachmentId ) {
			$this->logger->error("Could not find attachment ID for URL: " . $upload['url']);
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
			$this->logger->error("Invalid file for attachment ID $attachmentId: " . ($file ?: 'null'));
			return $result;
		}

        $this->logger->info("Converting single image: $attachmentId - $file");

		if ( $this->settings->get( 'enable_webp', true ) && $this->webpConverter->isSupported() ) {
			$result['webp'] = $this->convertToFormat( $attachmentId, 'webp' );
			$this->logger->info("WebP conversion result: " . ($result['webp'] ? 'success' : 'failed'));
		}

		if ( $this->settings->get( 'enable_avif', true ) && $this->avifConverter->isSupported() ) {
			$result['avif'] = $this->convertToFormat( $attachmentId, 'avif' );
			$this->logger->info("AVIF conversion result: " . ($result['avif'] ? 'success' : 'failed'));
		}

		$result['success'] = $result['webp'] || $result['avif'];

		// Check if we should convert thumbnails
        if ($this->settings->get('convert_thumbnails')) {
            $this->convertThumbnails($attachmentId);
        }

		return $result;
	}

	/**
	 * Bulk convert multiple images
	 * 
	 * @param array $ids Array of attachment IDs to convert
	 * @param string $processId Optional process ID for tracking
	 */
	public function bulkConvertImages( array $ids, string $processId = '' ): void {
		$totalImages = count( $ids );
		if ($processId === '') {
		    $processId = 'bulk_convert_' . uniqid();
		}

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
						'process_id'    => $processId,
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
			$this->logger->error("Cannot get attached file for attachment ID: $attachmentId");
			return false;
		}

		$destPath  = $this->getDestinationPath( $file, $format );
		$converter = $format === 'webp' ? $this->webpConverter : $this->avifConverter;

		$this->logger->info("Converting $file to $format at $destPath");
		$success = $converter->convert( $file, $destPath, array() );

		if ( $success ) {
			// Update attachment metadata
			$meta = wp_get_attachment_metadata( $attachmentId );
			if (!is_array($meta)) {
			    $meta = array();
			    $this->logger->warning("No metadata found for attachment ID: $attachmentId, creating new metadata");
			}
			
			$meta[ "{$format}_path" ] = $destPath;
			$meta[ "{$format}_url" ]  = $this->getWebUrl( $destPath );
			wp_update_attachment_metadata( $attachmentId, $meta );
			
			$this->logger->info("Updated metadata for attachment ID: $attachmentId with $format path: $destPath");
		} else {
			$this->logger->error("Failed to convert $file to $format");
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

	/**
	 * Convert all thumbnails for an attachment
	 */
	private function convertThumbnails(int $attachmentId): void {
		$this->logger->info("Converting thumbnails for attachment ID: $attachmentId");
		
		// Get all the different sizes metadata for this attachment
		$metadata = wp_get_attachment_metadata($attachmentId);
		
		if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
			$this->logger->warning("No sizes found for attachment ID: $attachmentId");
			return;
		}
		
		// Get the original file path and info
		$originalFilePath = get_attached_file($attachmentId);
		if (!$originalFilePath) {
			$this->logger->error("Original file not found for attachment ID: $attachmentId");
			return;
		}
		
		$uploadDir = wp_upload_dir();
		$baseDir = dirname($originalFilePath);
		
		// Process each thumbnail
		foreach ($metadata['sizes'] as $size => $sizeData) {
			if (!isset($sizeData['file'])) {
				continue;
			}
			
			$thumbnailPath = $baseDir . '/' . $sizeData['file'];
			
			if (file_exists($thumbnailPath)) {
				$this->logger->info("Converting thumbnail: $thumbnailPath");
				$this->convertImage($thumbnailPath);
			} else {
				$this->logger->warning("Thumbnail file not found: $thumbnailPath");
			}
		}
	}

	/**
	 * Convert a single image file to WebP and AVIF
	 */
	private function convertImage(string $filePath): array {
		$result = [
			'success' => false,
			'webp' => false,
			'avif' => false,
		];

		if (!file_exists($filePath)) {
			$this->logger->error("File not found: $filePath");
			return $result;
		}

		// Check if we should skip already converted images
		$skipConverted = $this->settings->get('skip_converted');
		
		// Convert to WebP if enabled
		if ($this->settings->get('enable_webp')) {
			$webpPath = $filePath . '.webp';
			
			if (!$skipConverted || !file_exists($webpPath)) {
				$result['webp'] = $this->webpConverter->convert(
					$filePath, 
					$webpPath, 
					$this->getWebpSettings()
				);
				$this->logger->info("WebP conversion result for $filePath: " . ($result['webp'] ? 'success' : 'failed'));
			} else {
				$result['webp'] = true; // Already exists
				$this->logger->info("Skipping WebP conversion for $filePath (already exists)");
			}
		}

		// Convert to AVIF if enabled
		if ($this->settings->get('enable_avif')) {
			$avifPath = $filePath . '.avif';
			
			if (!$skipConverted || !file_exists($avifPath)) {
				$result['avif'] = $this->avifConverter->convert(
					$filePath, 
					$avifPath, 
					$this->getAvifSettings()
				);
				$this->logger->info("AVIF conversion result for $filePath: " . ($result['avif'] ? 'success' : 'failed'));
			} else {
				$result['avif'] = true; // Already exists
				$this->logger->info("Skipping AVIF conversion for $filePath (already exists)");
			}
		}

		$result['success'] = ($result['webp'] || $result['avif']);
		return $result;
	}
	
	/**
	 * Get WebP conversion settings from plugin settings
	 */
	private function getWebpSettings(): array {
		return [
			'quality' => $this->settings->get('webp_quality', 80),
			'lossless' => $this->settings->get('webp_lossless', false),
		];
	}
	
	/**
	 * Get AVIF conversion settings from plugin settings
	 */
	private function getAvifSettings(): array {
		return [
			'quality' => $this->settings->get('avif_quality', 80),
			'speed' => $this->settings->get('avif_speed', 6),
			'lossless' => $this->settings->get('avif_lossless', false),
		];
	}
}
