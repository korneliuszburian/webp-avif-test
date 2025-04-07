<?php

namespace WpImageOptimizer\Conversion;

/**
 * AVIF image converter
 */
class AvifConverter extends AbstractImageConverter {
    /**
     * {@inheritdoc}
     */
    protected function getFormatName(): string {
        return 'AVIF';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getFormatPrefix(): string {
        return 'avif_';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions(): array {
        return [
            'quality'  => $this->settings->get('avif_quality', 65),
            'speed'    => $this->settings->get('avif_speed', 6),
            'lossless' => $this->settings->get('avif_lossless', false),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function isGdSupported(): bool {
        return function_exists('imageavif');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function isImagickSupported(): bool {
        return class_exists('Imagick') &&
            defined('Imagick::COMPRESSION_JBIG2') && // Proxy check for AVIF support
            method_exists('Imagick', 'setCompressionQuality');
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

        exec('which avifenc', $output, $returnVar);

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
        
        // Convert to AVIF
        $quality = $options['lossless'] ? 100 : $options['quality'];
        $speed   = $options['speed'] ?? 6; // Lower is better quality but slower

        // Set AVIF encoding options
        imagepalettetotruecolor($sourceImage);

        // Save as AVIF
        $success = imageavif($sourceImage, $destinationPath, $quality, $speed);

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

        // Set AVIF options
        $imagick->setImageFormat('avif');

        if ($options['lossless']) {
            $imagick->setImageCompressionQuality(100);
            $imagick->setOption('avif:lossless', 'true');
        } else {
            $imagick->setImageCompressionQuality($options['quality']);
        }

        // Set speed
        $imagick->setOption('avif:speed', (string) $options['speed']);

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
        $speed    = $options['speed'];
        $lossless = $options['lossless'] ? '-l' : '';

        $command   = "avifenc {$lossless} -s {$speed} -q {$quality} \"{$sourcePath}\" \"{$destinationPath}\"";
        $output    = [];
        $returnVar = 0;

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \RuntimeException("avifenc failed with code $returnVar: " . implode("\n", $output));
        }

        return file_exists($destinationPath) && filesize($destinationPath) > 0;
    }
}
