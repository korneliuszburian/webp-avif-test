<?php

namespace WpImageOptimizer\Media;

use WpImageOptimizer\Core\Settings;
use WpImageOptimizer\Utility\Logger;

/**
 * Determines the optimal image format based on image content and settings
 */
class FormatSelector {
    /**
     * Constructor
     * 
     * @param Settings $settings Plugin settings
     * @param Logger $logger Logger instance
     */
    public function __construct(
        private Settings $settings,
        private Logger $logger
    ) {}
    
    /**
     * Determine the optimal format for an image
     * 
     * @param string $sourcePath Image path
     * @param array $availableFormats Available formats
     * @return string|null The optimal format or null if none suitable
     */
    public function determineOptimalFormat(string $sourcePath, array $availableFormats = ['webp', 'avif']): ?string {
        // Skip if file doesn't exist
        if (!file_exists($sourcePath)) {
            $this->logger->error("Cannot determine optimal format for non-existent file: $sourcePath");
            return null;
        }
        
        // Filter available formats by settings
        $enabledFormats = [];
        if (in_array('webp', $availableFormats) && $this->settings->get('enable_webp', true)) {
            $enabledFormats[] = 'webp';
        }
        if (in_array('avif', $availableFormats) && $this->settings->get('enable_avif', true)) {
            $enabledFormats[] = 'avif';
        }
        
        if (empty($enabledFormats)) {
            return null;
        }
        
        // Get image info
        $imageInfo = $this->getImageInfo($sourcePath);
        if (!$imageInfo) {
            return $enabledFormats[0]; // Return first enabled format if we can't analyze image
        }
        
        // Use AVIF for high-res images (if available)
        if (in_array('avif', $enabledFormats) && $imageInfo['isHighResolution']) {
            $this->logger->info("Selected AVIF for high-resolution image: $sourcePath");
            return 'avif';
        }
        
        // Use WebP for images with transparency (if available)
        if (in_array('webp', $enabledFormats) && $imageInfo['hasTransparency']) {
            $this->logger->info("Selected WebP for transparent image: $sourcePath");
            return 'webp';
        }
        
        // Use WebP for animated images (if available)
        if (in_array('webp', $enabledFormats) && $imageInfo['isAnimated']) {
            $this->logger->info("Selected WebP for animated image: $sourcePath");
            return 'webp';
        }
        
        // For other cases, prefer AVIF if available (better compression)
        if (in_array('avif', $enabledFormats)) {
            return 'avif';
        }
        
        // Fall back to WebP
        if (in_array('webp', $enabledFormats)) {
            return 'webp';
        }
        
        // No suitable format found
        return null;
    }
    
    /**
     * Get image information for format optimization decisions
     * 
     * @param string $sourcePath Image path
     * @return array|null Image information or null if unavailable
     */
    private function getImageInfo(string $sourcePath): ?array {
        // Initialize result with defaults
        $result = [
            'width' => 0,
            'height' => 0,
            'hasTransparency' => false,
            'isAnimated' => false,
            'isHighResolution' => false,
            'fileSize' => 0,
        ];
        
        // Get basic image dimensions
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return null;
        }
        
        $result['width'] = $imageInfo[0];
        $result['height'] = $imageInfo[1];
        $result['fileSize'] = filesize($sourcePath);
        
        // Check if image is high resolution (larger than 1MP)
        $result['isHighResolution'] = ($result['width'] * $result['height'] > 1000000);
        
        // Check for transparency in PNG
        if ($imageInfo[2] === IMAGETYPE_PNG) {
            $result['hasTransparency'] = $this->pngHasTransparency($sourcePath);
        }
        
        // Check for animation in GIF
        if ($imageInfo[2] === IMAGETYPE_GIF) {
            $result['isAnimated'] = $this->gifIsAnimated($sourcePath);
        }
        
        return $result;
    }
    
    /**
     * Check if PNG image has transparency
     * 
     * @param string $sourcePath PNG image path
     * @return bool True if the image has transparency
     */
    private function pngHasTransparency(string $sourcePath): bool {
        // Quick check based on file header
        $content = file_get_contents($sourcePath, false, null, 8, 8);
        if ($content === "\x00\x00\x00\x04\x67\x41\x4D\x41") {
            // Likely has an alpha channel (quick estimate)
            return true;
        }
        
        // More thorough check using GD
        if (function_exists('imagecreatefrompng')) {
            $im = @imagecreatefrompng($sourcePath);
            if ($im) {
                imagepalettetotruecolor($im);
                // Check if alpha saving is needed, which indicates transparency
                $hasTransparency = imagesavealpha($im, true);
                imagedestroy($im);
                return $hasTransparency;
            }
        }
        
        // Couldn't determine, assume no transparency
        return false;
    }
    
    /**
     * Check if GIF image is animated
     * 
     * @param string $sourcePath GIF image path
     * @return bool True if the image is animated
     */
    private function gifIsAnimated(string $sourcePath): bool {
        if (!($fh = @fopen($sourcePath, 'rb'))) {
            return false;
        }
        
        $count = 0;
        
        // An animated gif contains multiple "frames", with each frame having a
        // header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21)
        
        // Read through the file til we reach the end or find at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // Read 100kb at a time
            
            if ($count === 0) {
                // Make sure it's a GIF file
                if (substr($chunk, 0, 3) !== 'GIF') {
                    fclose($fh);
                    return false;
                }
            }
            
            // Search for frame header
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk, $matches);
            
            if ($count >= 2) {
                fclose($fh);
                return true;
            }
        }
        
        fclose($fh);
        return false;
    }
}
