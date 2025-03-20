<?php

namespace WpImageOptimizer\Conversion;

use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Logger;

class WebpConverter implements ConverterInterface {
    public function __construct(
        private Settings $settings,
        private Logger $logger
    ) {}
    
    /**
     * {@inheritdoc}
     */
    public function convert(string $sourcePath, string $destinationPath, array $options = []): bool {
        // Merge with default settings
        $options = array_merge([
            'quality' => $this->settings->get('webp_quality'),
            'lossless' => $this->settings->get('webp_lossless'),
        ], $options);
        
        // Try conversion methods in order of preference
        foreach ($this->getEnabledMethods() as $method) {
            try {
                $success = match ($method) {
                    'gd' => $this->convertWithGd($sourcePath, $destinationPath, $options),
                    'imagick' => $this->convertWithImagick($sourcePath, $destinationPath, $options),
                    'cwebp' => $this->convertWithCwebp($sourcePath, $destinationPath, $options),
                    default => false,
                };
                
                if ($success) {
                    $this->logger->info("Successfully converted to WebP using $method", [
                        'source' => $sourcePath,
                        'destination' => $destinationPath,
                        'size_before' => filesize($sourcePath),
                        'size_after' => filesize($destinationPath),
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error("WebP conversion failed with $method: " . $e->getMessage(), [
                    'source' => $sourcePath,
                    'method' => $method,
                ]);
            }
        }
        
        $this->logger->error('All WebP conversion methods failed', [
            'source' => $sourcePath,
        ]);
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isSupported(): bool {
        return count($this->getAvailableMethods()) > 0;
    }
    
    /**
     * Get available conversion methods
     * 
     * @return array List of available methods
     */
    private function getAvailableMethods(): array {
        $methods = [];
        
        // Check GD
        if (function_exists('imagewebp')) {
            $methods[] = 'gd';
        }
        
        // Check ImageMagick
        if (class_exists('Imagick') && 
            method_exists('Imagick', 'setImageFormat') &&
            defined('Imagick::COMPRESSION_JPEG')) {
            $methods[] = 'imagick';
        }
        
        // Check cwebp command-line
        if ($this->isExecAvailable() && $this->isCwebpAvailable()) {
            $methods[] = 'cwebp';
        }
        
        return $methods;
    }
    
    /**
     * Get enabled conversion methods
     * 
     * @return array List of enabled methods
     */
    private function getEnabledMethods(): array {
        $preferred = $this->settings->get('conversion_method', 'auto');
        $available = $this->getAvailableMethods();
        
        // If specific method is selected and available, use only that
        if ($preferred !== 'auto' && in_array($preferred, $available)) {
            return [$preferred];
        }
        
        // Otherwise use all available methods in order of quality
        return $available;
    }
    
    /**
     * Convert using GD library
     */
    private function convertWithGd(string $sourcePath, string $destinationPath, array $options): bool {
        // Get image dimensions and type
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \RuntimeException("Failed to get image info for: $sourcePath");
        }
        
        // Create source image based on type
        $sourceImage = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            default => throw new \RuntimeException("Unsupported image type: {$imageInfo[2]}"),
        };
        
        if (!$sourceImage) {
            throw new \RuntimeException("Failed to create source image");
        }
        
        // Preserve transparency for PNG
        if ($imageInfo[2] === IMAGETYPE_PNG) {
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
        }
        
        // Convert to WebP
        $quality = $options['lossless'] ? 100 : $options['quality'];
        
        // Save as WebP
        $success = imagewebp($sourceImage, $destinationPath, $quality);
        
        // Free memory
        imagedestroy($sourceImage);
        
        return $success;
    }
    
    /**
     * Convert using ImageMagick
     */
    private function convertWithImagick(string $sourcePath, string $destinationPath, array $options): bool {
        $imagick = new \Imagick();
        $imagick->readImage($sourcePath);
        
        // Handle transparency for PNG
        if (pathinfo($sourcePath, PATHINFO_EXTENSION) === 'png') {
            $imagick->setImageFormat('png');
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
        }
        
        // Set WebP options
        $imagick->setImageFormat('webp');
        
        if ($options['lossless']) {
            $imagick->setImageCompressionQuality(100);
            $imagick->setOption('webp:lossless', 'true');
        } else {
            $imagick->setImageCompressionQuality($options['quality']);
        }
        
        // Write the image
        $success = $imagick->writeImage($destinationPath);
        $imagick->clear();
        
        return $success;
    }
    
    /**
     * Convert using cwebp command-line tool
     */
    private function convertWithCwebp(string $sourcePath, string $destinationPath, array $options): bool {
        $quality = $options['quality'];
        $lossless = $options['lossless'] ? '-lossless' : '';
        
        $command = "cwebp {$lossless} -q {$quality} \"{$sourcePath}\" -o \"{$destinationPath}\"";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \RuntimeException("cwebp failed with code $returnVar: " . implode("\n", $output));
        }
        
        return file_exists($destinationPath) && filesize($destinationPath) > 0;
    }
    
    /**
     * Check if exec() function is available
     */
    private function isExecAvailable(): bool {
        return function_exists('exec') && 
               !in_array('exec', explode(',', ini_get('disable_functions'))) && 
               strtolower(ini_get('safe_mode')) !== 'on';
    }
    
    /**
     * Check if cwebp command-line tool is available
     */
    private function isCwebpAvailable(): bool {
        if (!$this->isExecAvailable()) {
            return false;
        }
        
        $output = [];
        $returnVar = 0;
        
        exec('which cwebp', $output, $returnVar);
        
        return $returnVar === 0;
    }
}
