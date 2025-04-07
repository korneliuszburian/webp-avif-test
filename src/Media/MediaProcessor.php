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
		private Logger $logger,
		private FormatSelector $formatSelector
	) {}

	public function processUploadedMedia( array $upload ): array {
		if ( ! $this->settings->get( 'auto_convert', true ) ) {
			return $upload;
		}

		if ( ! $this->isSupportedImage( $upload['file'] ) ) {
			return $upload;
		}

        $this->logger->info("Processing uploaded media: " . $upload['file']);

		$attachmentId = $this->getAttachmentIdByUrl( $upload['url'] );
		if ( ! $attachmentId ) {
			$this->logger->error("Could not find attachment ID for URL: " . $upload['url']);
			return $upload;
		}

		if ( $this->settings->get( 'enable_webp', true ) && $this->webpConverter->isSupported() ) {
			$this->convertToFormat( $attachmentId, 'webp' );
		}

		if ( $this->settings->get( 'enable_avif', true ) && $this->avifConverter->isSupported() ) {
			$this->convertToFormat( $attachmentId, 'avif' );
		}

		return $upload;
	}

	/**
	 * Convert a single attachment image to WebP and AVIF formats
	 *
	 * @param int $attachmentId The attachment ID
	 * @return array Results of the conversion
	 */
	public function convertSingleImage(int $attachmentId): array {
		$result = [
			'success' => false,
			'webp' => false,
			'avif' => false,
		];

		$file = get_attached_file($attachmentId);
		if (!$file || !$this->isSupportedImage($file)) {
			$this->logger->error("Invalid file for attachment ID $attachmentId: " . ($file ?: 'null'));
			return $result;
		}

		$this->logger->info("Converting single image: $attachmentId - $file");

		// Convert main image
		if ($this->settings->get('enable_webp', true) && $this->webpConverter->isSupported()) {
			$result['webp'] = $this->convertToFormat($attachmentId, 'webp');
			$this->logger->info("WebP conversion result: " . ($result['webp'] ? 'success' : 'failed'));
		}

		if ($this->settings->get('enable_avif', true) && $this->avifConverter->isSupported()) {
			$result['avif'] = $this->convertToFormat($attachmentId, 'avif');
			$this->logger->info("AVIF conversion result: " . ($result['avif'] ? 'success' : 'failed'));
		}

		// Convert thumbnails if enabled
		if ($this->settings->get('convert_thumbnails')) {
			$thumbnailResults = $this->convertThumbnails($attachmentId);
			$result['thumbnails'] = $thumbnailResults;
			$result['success'] = $result['webp'] || $result['avif'] || $thumbnailResults['success'];
			
			// Log a summary of the thumbnail conversion
			$this->logger->info("Thumbnails converted: {$thumbnailResults['converted']}, " . 
							"failed: {$thumbnailResults['failed']}, " . 
							"skipped: {$thumbnailResults['skipped']}");
		} else {
			$result['success'] = $result['webp'] || $result['avif'];
		}

		return $result;
	}

	public function bulkConvertImages( array $ids, string $processId = '' ): void {
		$totalImages = count( $ids );
		if ($processId === '') {
		    $processId = 'bulk_convert_' . uniqid();
		}

		$this->progressManager->startProcess( $processId, $totalImages );
		$this->logger->info( "Starting bulk conversion of $totalImages images", array( 'process_id' => $processId ) );

		$batchSize = (int) $this->settings->get( 'bulk_batch_size', 10 );
		$batches   = array_chunk( $ids, $batchSize );

		foreach ( $batches as $index => $batch ) {
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

			if ( $index < count( $batches ) - 1 ) {
				$delay = (int) $this->settings->get( 'processing_delay', 250 );
				usleep( $delay * 1000 );
			}
		}

		$this->logger->info( "Completed bulk conversion of $totalImages images", array( 'process_id' => $processId ) );
	}

	/**
	 * Convert attachment file to a specific format and update metadata
	 *
	 * @param int $attachmentId The attachment ID
	 * @param string $format Format to convert to ('webp' or 'avif')
	 * @return bool Success or failure
	 */
	private function convertToFormat(int $attachmentId, string $format): bool {
		$file = get_attached_file($attachmentId);
		if (!$file) {
			$this->logger->error("Cannot get attached file for attachment ID: $attachmentId");
			return false;
		}

		// Convert the file
		$converter = $format === 'webp' ? $this->webpConverter : $this->avifConverter;
		$options = $format === 'webp' ? $this->getWebpSettings() : $this->getAvifSettings();
		
		$success = $this->convertFileToFormat($file, $format, $converter, $options);

		// Update metadata if conversion was successful
		if ($success) {
			$destPath = $this->getDestinationPath($file, $format);
			$meta = wp_get_attachment_metadata($attachmentId);
			if (!is_array($meta)) {
				$meta = array();
				$this->logger->warning("No metadata found for attachment ID: $attachmentId, creating new metadata");
			}
			
			$meta["{$format}_path"] = $destPath;
			$meta["{$format}_url"] = $this->getWebUrl($destPath);
			wp_update_attachment_metadata($attachmentId, $meta);
			
			$this->logger->info("Updated metadata for attachment ID: $attachmentId with $format path: $destPath");
		}

		return $success;
	}

	/**
	 * Get the destination path for a converted image, handling special file naming patterns
	 *
	 * @param string $sourcePath Original file path
	 * @param string $format Target format (webp or avif)
	 * @return string Path to save the converted image
	 */
	private function getDestinationPath(string $sourcePath, string $format): string {
		$pathInfo = pathinfo($sourcePath);
		$dirname = $pathInfo['dirname'];
		$filename = $pathInfo['filename'];
		$originalExt = strtolower($pathInfo['extension'] ?? '');
		
		// Check for common format indicators in filename (_jpg, _png, _webp, _avif)
		$formatPatterns = ['_jpg', '_jpeg', '_png', '_gif', '_webp', '_avif'];
		$hasFormatPattern = false;
		
		foreach ($formatPatterns as $pattern) {
			if (str_ends_with(strtolower($filename), $pattern)) {
				$hasFormatPattern = true;
				
				// If converting to the same format that's in the filename, keep original name
				// e.g., "image_avif.jpg" converting to avif should become "image_avif.avif"
				if (str_ends_with(strtolower($filename), "_{$format}")) {
					return "{$dirname}/{$filename}.{$format}";
				}
				
				// Replace the existing format indicator with the new one
				// e.g., "image_png.jpg" to "image_avif.avif"
				foreach ($formatPatterns as $oldPattern) {
					if (str_ends_with(strtolower($filename), $oldPattern)) {
						$baseFilename = substr($filename, 0, strlen($filename) - strlen($oldPattern));
						return "{$dirname}/{$baseFilename}_{$format}.{$format}";
					}
				}
			}
		}
		
		// Standard case - just append the new extension
		return "{$dirname}/{$filename}.{$format}";
	}

	private function getWebUrl( string $filePath ): string {
		$uploadsDir = wp_upload_dir();
		$baseDir    = $uploadsDir['basedir'];
		$baseUrl    = $uploadsDir['baseurl'];

		return str_replace( $baseDir, $baseUrl, $filePath );
	}

	private function isSupportedImage( string $file ): bool {
		$supportedTypes = array( 'jpg', 'jpeg', 'png' );
		$extension      = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		return in_array( $extension, $supportedTypes );
	}

	private function getAttachmentIdByUrl( string $url ): ?int {
		global $wpdb;

		$uploadDir = wp_upload_dir();
		$baseUrl   = $uploadDir['baseurl'];

		$relativePath = str_replace( $baseUrl, '', $url );

		$attachmentId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
				ltrim( $relativePath, '/' )
			)
		);

		return $attachmentId ? (int) $attachmentId : null;
	}

	/**
	 * Convert all thumbnails for an attachment and update metadata
	 *
	 * @param int $attachmentId The attachment ID
	 * @return array Results of the thumbnail conversions
	 */
	private function convertThumbnails(int $attachmentId): array {
		$this->logger->info("Converting thumbnails for attachment ID: $attachmentId");
		
		$metadata = wp_get_attachment_metadata($attachmentId);
		$results = [
			'success' => false,
			'converted' => 0,
			'failed' => 0,
			'skipped' => 0,
		];
		
		if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
			$this->logger->warning("No sizes found for attachment ID: $attachmentId");
			return $results;
		}
		
		$originalFilePath = get_attached_file($attachmentId);
		if (!$originalFilePath) {
			$this->logger->error("Original file not found for attachment ID: $attachmentId");
			return $results;
		}
		
		// Initialize thumbnail_conversions in metadata if it doesn't exist
		if (!isset($metadata['thumbnail_conversions']) || !is_array($metadata['thumbnail_conversions'])) {
			$metadata['thumbnail_conversions'] = [];
		}
		
		$baseDir = dirname($originalFilePath);
		
		foreach ($metadata['sizes'] as $size => $sizeData) {
			if (!isset($sizeData['file'])) {
				continue;
			}
			
			$thumbnailPath = $baseDir . '/' . $sizeData['file'];
			
			if (file_exists($thumbnailPath)) {
				$this->logger->info("Converting thumbnail: $thumbnailPath");
				
				$conversionResult = $this->convertImage($thumbnailPath);
				
				// Store the conversion results in metadata
				if ($conversionResult['success']) {
					$results['converted']++;
					$results['success'] = true;
					
					// Update thumbnail conversion metadata
					$metadata['thumbnail_conversions'][$size] = [
						'webp' => $conversionResult['webp'] ? $this->getDestinationPath($thumbnailPath, 'webp') : null,
						'avif' => $conversionResult['avif'] ? $this->getDestinationPath($thumbnailPath, 'avif') : null,
						'webp_url' => $conversionResult['webp'] ? $this->getWebUrl($this->getDestinationPath($thumbnailPath, 'webp')) : null,
						'avif_url' => $conversionResult['avif'] ? $this->getWebUrl($this->getDestinationPath($thumbnailPath, 'avif')) : null,
					];
				} else {
					$results['failed']++;
				}
			} else {
				$this->logger->warning("Thumbnail file not found: $thumbnailPath");
				$results['skipped']++;
			}
		}
		
		// Update the metadata with thumbnail conversion information
		if ($results['converted'] > 0) {
			wp_update_attachment_metadata($attachmentId, $metadata);
			$this->logger->info("Updated thumbnail conversion metadata for attachment ID: $attachmentId");
		}
		
		return $results;
	}

	/**
	 * Convert a single file to a specific format
	 *
	 * @param string $sourcePath Source file path
	 * @param string $format Format to convert to ('webp' or 'avif')
	 * @param ConverterInterface $converter The converter to use
	 * @param array $options Conversion options
	 * @return bool Success or failure
	 */
	private function convertFileToFormat(string $sourcePath, string $format, ConverterInterface $converter, array $options = []): bool {
		if (!file_exists($sourcePath)) {
			$this->logger->error("Source file not found: $sourcePath");
			return false;
		}

		$destPath = $this->getDestinationPath($sourcePath, $format);
		
		// Skip if the file already exists and skip_converted is enabled
		if ($this->settings->get('skip_converted', false) && file_exists($destPath)) {
			$this->logger->info("Skipping conversion for $sourcePath - $format version already exists");
			return true;
		}
		
		$this->logger->info("Converting $sourcePath to $format at $destPath");
		$success = $converter->convert($sourcePath, $destPath, $options);
		
		if (!$success) {
			$this->logger->error("Failed to convert $sourcePath to $format");
		}
		
		return $success;
	}

	/**
	 * Convert an image file to both WebP and AVIF formats if enabled
	 *
	 * @param string $filePath Path to the image file
	 * @return array Conversion results
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
		
		// Convert to WebP if enabled
		if ($this->settings->get('enable_webp')) {
			$result['webp'] = $this->convertFileToFormat(
				$filePath, 
				'webp', 
				$this->webpConverter, 
				$this->getWebpSettings()
			);
		}

		// Convert to AVIF if enabled
		if ($this->settings->get('enable_avif')) {
			$result['avif'] = $this->convertFileToFormat(
				$filePath, 
				'avif', 
				$this->avifConverter, 
				$this->getAvifSettings()
			);
		}

		$result['success'] = ($result['webp'] || $result['avif']);
		return $result;
	}
	
	/**
	 * Convert a file to its optimal format based on content
	 *
	 * @param string $sourcePath Path to the source image
	 * @return array Results with the selected formats and success status
	 */
	public function convertToOptimalFormat(string $sourcePath): array {
		$result = [
			'success' => false,
			'webp' => false,
			'avif' => false,
			'selected_format' => null,
		];

		if (!file_exists($sourcePath) || !$this->isSupportedImage($sourcePath)) {
			$this->logger->error("Invalid or unsupported file: $sourcePath");
			return $result;
		}

		// Determine available formats
		$availableFormats = [];
		if ($this->settings->get('enable_webp', true) && $this->webpConverter->isSupported()) {
			$availableFormats[] = 'webp';
		}
		if ($this->settings->get('enable_avif', true) && $this->avifConverter->isSupported()) {
			$availableFormats[] = 'avif';
		}

		// Skip if no formats are available
		if (empty($availableFormats)) {
			$this->logger->warning("No conversion formats available for: $sourcePath");
			return $result;
		}

		// Let the FormatSelector decide the optimal format
		$optimalFormat = $this->formatSelector->determineOptimalFormat($sourcePath, $availableFormats);
		
		if (!$optimalFormat) {
			$this->logger->warning("Could not determine optimal format for: $sourcePath");
			return $result;
		}

		$result['selected_format'] = $optimalFormat;
		$this->logger->info("Selected optimal format $optimalFormat for: $sourcePath");

		// Convert to the optimal format
		$converter = $optimalFormat === 'webp' ? $this->webpConverter : $this->avifConverter;
		$options = $optimalFormat === 'webp' ? $this->getWebpSettings() : $this->getAvifSettings();
		
		$success = $this->convertFileToFormat($sourcePath, $optimalFormat, $converter, $options);
		
		// Update result
		if ($optimalFormat === 'webp') {
			$result['webp'] = $success;
		} else if ($optimalFormat === 'avif') {
			$result['avif'] = $success;
		}
		
		$result['success'] = $success;
		
		return $result;
	}
	
	private function getWebpSettings(): array {
		return [
			'quality' => $this->settings->get('webp_quality', 80),
			'lossless' => $this->settings->get('webp_lossless', false),
		];
	}
	
	private function getAvifSettings(): array {
		return [
			'quality' => $this->settings->get('avif_quality', 80),
			'speed' => $this->settings->get('avif_speed', 6),
			'lossless' => $this->settings->get('avif_lossless', false),
		];
	}
}
