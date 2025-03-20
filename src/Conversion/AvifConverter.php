<?php

namespace WpImageOptimizer\Conversion;

use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Logger;

class AvifConverter implements ConverterInterface {
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
            'quality' => $this->settings->get('avif_quality', 65),
            'speed' => $this->settings->get('avif_speed', 6),
            'lossless' => $this->settings->get('avif_lossless', false),
        ], $options);
        
        // Try conversion methods in order of preference
        foreach ($this->getEnabledMethods() as $method) {
            try {
                $success = match ($method) {
                    'gd' => $this->convertWithGd($sourcePath, $destinationPath, $options),
                    'imagick' => $this->convertWithImagick($sourcePath, $destinationPath, $options),
                    'avifenc' => $this->convertWithAvifenc($sourcePath, $destinationPath, $options),
                    default => false,
                };
                
                if ($success) {
                    $this->logger->info("Successfully converted to AVIF using $method", [
                        'source' => $sourcePath,
                        'destination' => $destinationPath,
                        'size_before' => filesize($sourcePath),
                        'size_after' => filesize($destinationPath),
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error("AVIF conversion failed with $method: " . $e->getMessage(), [
                    'source' => $sourcePath,
                    'method' => $method,
                ]);
            }
        }
        
        $this->logger->error('All AVIF conversion methods failed', [
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
        if (function_exists('imageavif')) {
            $methods[] = 'gd';
        }
        
        // Check ImageMagick
        if (class_exists('Imagick') && 
            defined('Imagick::COMPRESSION_JBIG2') && // Proxy check for AVIF support
            method_exists('Imagick', 'setCompressionQuality')) {
            $methods[] = 'imagick';
        }
        
        // Check avifenc command-line
        if ($this->isExecAvailable() && $this->isAvifencAvailable()) {
            $methods[] = 'avifenc';
        }
        
        return $methods;
    }
    
    /**
     * Get enabled conversion methods
     * 
     * @return array List of enabled methods
     */
    private function getEnabledMethods(): array {
        $preferred = $this->settings->get('avif_conversion_method', 'auto');
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
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
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
        
        // Convert to AVIF
        $quality = $options['lossless'] ? 100 : $options['quality'];
        $speed = $options['speed'] ?? 6; // Lower is better quality but slower
        
        // Set AVIF encoding options
        imagepalettetotruecolor($sourceImage);
        
        // Save as AVIF
        $success = imageavif($sourceImage, $destinationPath, $quality, $speed);
        
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
        
        // Set AVIF options
        $imagick->setImageFormat('avif');
        
        if ($options['lossless']) {
            $imagick->setImageCompressionQuality(100);
            $imagick->setOption('avif:lossless', 'true');
        } else {
            $imagick->setImageCompressionQuality($options['quality']);
        }
        
        // Set speed
        $imagick->setOption('avif:speed', (string)$options['speed']);
        
        // Write the image
        $success = $imagick->writeImage($destinationPath);
        $imagick->clear();
        
        return $success;
    }
    
    /**
     * Convert using avifenc command-line tool
     */
    private function convertWithAvifenc(string $sourcePath, string $destinationPath, array $options): bool {
        $quality = $options['quality'];
        $speed = $options['speed'];
        $lossless = $options['lossless'] ? '-l' : '';
        
        $command = "avifenc {$lossless} -s {$speed} -q {$quality} \"{$sourcePath}\" \"{$destinationPath}\"";
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new \RuntimeException("avifenc failed with code $returnVar: " . implode("\n", $output));
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
     * Check if avifenc command-line tool is available
     */
    private function isAvifencAvailable(): bool {
        if (!$this->isExecAvailable()) {
            return false;
        }
        
        $output = [];
        $returnVar = 0;
        
        exec('which avifenc', $output, $returnVar);
        
        return $returnVar === 0;
    }
}
