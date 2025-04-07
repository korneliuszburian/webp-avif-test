<?php

namespace WpImageOptimizer\Conversion;

/**
 * WebP image converter
 */
class WebpConverter extends AbstractImageConverter {
    /**
     * {@inheritdoc}
     */
    protected function getFormatName(): string {
        return 'WebP';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getFormatPrefix(): string {
        return 'webp_';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions(): array {
        return [
            'quality'  => $this->settings->get('webp_quality', 80),
            'lossless' => $this->settings->get('webp_lossless', false),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function isGdSupported(): bool {
        return function_exists('imagewebp');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function isImagickSupported(): bool {
        return class_exists('Imagick') &&
            method_exists('Imagick', 'setImageFormat') &&
            defined('Imagick::COMPRESSION_JPEG');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function isCommandLineToolAvailable(): bool {
        if (!$this->isExecAvailable()) {
            return false;
        }

        $output    = [];
        $returnVar = 0;

        exec('which cwebp', $output, $returnVar);

        return $returnVar === 0;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function convertWithGd(string $sourcePath, string $destinationPath, array $options): bool {
        $sourceImage = $this->createSourceImage($sourcePath);
        
        if (!$sourceImage) {
            throw new \RuntimeException('Failed to create source image');
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
     * {@inheritdoc}
     */
    protected function convertWithImagick(string $sourcePath, string $destinationPath, array $options): bool {
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
     * {@inheritdoc}
     */
    protected function convertWithCommandLine(string $sourcePath, string $destinationPath, array $options): bool {
        $quality  = $options['quality'];
        $lossless = $options['lossless'] ? '-lossless' : '';

        $command   = "cwebp {$lossless} -q {$quality} \"{$sourcePath}\" -o \"{$destinationPath}\"";
        $output    = [];
        $returnVar = 0;

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \RuntimeException("cwebp failed with code $returnVar: " . implode("\n", $output));
        }

        return file_exists($destinationPath) && filesize($destinationPath) > 0;
    }
}
