<?php

namespace WpImageOptimizer\Conversion;

interface ConverterInterface {
	/**
	 * Convert an image file to the target format
	 *
	 * @param string $sourcePath Path to the source image
	 * @param string $destinationPath Path to save the converted image
	 * @param array  $options Conversion options
	 * @return bool Success or failure
	 */
	public function convert( string $sourcePath, string $destinationPath, array $options = array() ): bool;

	/**
	 * Check if this converter is supported in the current environment
	 *
	 * @return bool Whether conversion is supported
	 */
	public function isSupported(): bool;
}
