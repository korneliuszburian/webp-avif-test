<?php

namespace WpImageOptimizer\Conversion;

use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Logger;

/**
 * Abstract base class for image converters
 */
abstract class AbstractImageConverter implements ConverterInterface {
    /**
     * Constructor
     * 
     * @param Settings $settings Plugin settings
     * @param Logger $logger Logger instance
     */
    public function __construct(
        protected Settings $settings,
        protected Logger $logger
    ) {}

    /**
     * {@inheritdoc}
     */
    public function convert(string $sourcePath, string $destinationPath, array $options = array()): bool {
        // Merge with default settings
        $options = array_merge($this->getDefaultOptions(), $options);

        // Try conversion methods in order of preference
        foreach ($this->getEnabledMethods() as $method) {
            try {
                $success = $this->executeConversion($method, $sourcePath, $destinationPath, $options);

                if ($success) {
                    $this->logger->info(
                        "Successfully converted to " . $this->getFormatName() . " using $method",
                        array(
                            'source'      => $sourcePath,
                            'destination' => $destinationPath,
                            'size_before' => filesize($sourcePath),
                            'size_after'  => filesize($destinationPath),
                        )
                    );
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    $this->getFormatName() . " conversion failed with $method: " . $e->getMessage(),
                    array(
                        'source' => $sourcePath,
                        'method' => $method,
                    )
                );
            }
        }

        $this->logger->error(
            'All ' . $this->getFormatName() . ' conversion methods failed',
            array(
                'source' => $sourcePath,
            )
        );

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSupported(): bool {
        return count($this->getAvailableMethods()) > 0;
    }

    /**
     * Execute a specific conversion method
     *
     * @param string $method The conversion method to use
     * @param string $sourcePath Path to the source image
     * @param string $destinationPath Path to save the converted image
     * @param array $options Conversion options
     * @return bool Success or failure
     */
    protected function executeConversion(string $method, string $sourcePath, string $destinationPath, array $options): bool {
        return match ($method) {
            'gd' => $this->convertWithGd($sourcePath, $destinationPath, $options),
            'imagick' => $this->convertWithImagick($sourcePath, $destinationPath, $options),
            'exec' => $this->convertWithCommandLine($sourcePath, $destinationPath, $options),
            default => false,
        };
    }

    /**
     * Get the format name (e.g., 'WebP', 'AVIF')
     *
     * @return string Format name
     */
    abstract protected function getFormatName(): string;

    /**
     * Get default conversion options
     *
     * @return array Default options
     */
    abstract protected function getDefaultOptions(): array;

    /**
     * Get available conversion methods
     *
     * @return array List of available methods
     */
    protected function getAvailableMethods(): array {
        $methods = array();

        // Check GD
        if ($this->isGdSupported()) {
            $methods[] = 'gd';
        }

        // Check ImageMagick
        if ($this->isImagickSupported()) {
            $methods[] = 'imagick';
        }

        // Check command-line
        if ($this->isExecAvailable() && $this->isCommandLineToolAvailable()) {
            $methods[] = 'exec';
        }

        return $methods;
    }

    /**
     * Check if GD supports this format
     *
     * @return bool Whether GD supports this format
     */
    abstract protected function isGdSupported(): bool;

    /**
     * Check if ImageMagick supports this format
     *
     * @return bool Whether ImageMagick supports this format
     */
    abstract protected function isImagickSupported(): bool;

    /**
     * Check if command-line tool is available
     *
     * @return bool Whether command-line tool is available
     */
    abstract protected function isCommandLineToolAvailable(): bool;

    /**
     * Convert using GD library
     *
     * @param string $sourcePath Path to the source image
     * @param string $destinationPath Path to save the converted image
     * @param array $options Conversion options
     * @return bool Success or failure
     */
    abstract protected function convertWithGd(string $sourcePath, string $destinationPath, array $options): bool;

    /**
     * Convert using ImageMagick
     *
     * @param string $sourcePath Path to the source image
     * @param string $destinationPath Path to save the converted image
     * @param array $options Conversion options
     * @return bool Success or failure
     */
    abstract protected function convertWithImagick(string $sourcePath, string $destinationPath, array $options): bool;

    /**
     * Convert using command-line tool
     *
     * @param string $sourcePath Path to the source image
     * @param string $destinationPath Path to save the converted image
     * @param array $options Conversion options
     * @return bool Success or failure
     */
    abstract protected function convertWithCommandLine(string $sourcePath, string $destinationPath, array $options): bool;

    /**
     * Get enabled conversion methods
     *
     * @return array List of enabled methods
     */
    protected function getEnabledMethods(): array {
        $preferred = $this->settings->get($this->getFormatPrefix() . 'conversion_method', 'auto');
        $available = $this->getAvailableMethods();

        // If specific method is selected and available, use only that
        if ($preferred !== 'auto' && in_array($preferred, $available)) {
            return array($preferred);
        }

        // Otherwise use all available methods in order of quality
        return $available;
    }

    /**
     * Get the format-specific settings prefix
     *
     * @return string Settings prefix (e.g., 'webp_', 'avif_')
     */
    abstract protected function getFormatPrefix(): string;

    /**
     * Check if exec() function is available
     */
    protected function isExecAvailable(): bool {
        return function_exists('exec') &&
                !in_array('exec', explode(',', ini_get('disable_functions'))) &&
                strtolower(ini_get('safe_mode')) !== 'on';
    }

    /**
     * Create source image based on file type
     *
     * @param string $sourcePath Path to the source image
     * @return resource|object|false The image resource
     */
    protected function createSourceImage(string $sourcePath) {
        // Get image dimensions and type
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \RuntimeException("Failed to get image info for: $sourcePath");
        }

        // Create source image based on type
        return match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => $this->createSourceImageFromPng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => throw new \RuntimeException("Unsupported image type: {$imageInfo[2]}"),
        };
    }

    /**
     * Create source image from PNG with transparency handling
     *
     * @param string $sourcePath Path to the PNG image
     * @return resource|object|false The image resource
     */
    protected function createSourceImageFromPng(string $sourcePath) {
        $sourceImage = imagecreatefrompng($sourcePath);
        
        if ($sourceImage) {
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
        }
        
        return $sourceImage;
    }
}
