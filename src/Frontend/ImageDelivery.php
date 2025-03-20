<?php

namespace WpImageOptimizer\Frontend;

use WpImageOptimizer\Core\Settings;

class ImageDelivery {
    public function __construct(
        private Settings $settings
    ) {}
    
    /**
     * Register hooks for frontend image delivery
     */
    public function registerHooks(): void {
        // Only apply filters if the feature is enabled
        if ($this->settings->get('enable_frontend_delivery', true)) {
            // Filter content to replace img tags
            add_filter('the_content', [$this, 'replaceImgTags']);
            
            // Filter post thumbnails
            add_filter('post_thumbnail_html', [$this, 'replaceImgTags']);
            
            // Add support for custom image HTML
            add_filter('wp_get_attachment_image', [$this, 'replaceImgTags']);
        }
    }
    
    /**
     * Replace img tags with picture tags for WebP/AVIF support
     */
    public function replaceImgTags(string $content): string {
        // Skip if the content doesn't contain img tags
        if (strpos($content, '<img') === false) {
            return $content;
        }
        
        // Regular expression to find img tags
        $pattern = '/<img([^>]+)>/i';
        
        // Replace img tags with picture tags
        $content = preg_replace_callback($pattern, [$this, 'replaceSingleImgTag'], $content);
        
        return $content;
    }
    
    /**
     * Replace a single img tag with a picture tag
     */
    private function replaceSingleImgTag(array $matches): string {
        // Extract attributes from the img tag
        $img_tag = $matches[0];
        $img_attrs = $matches[1];
        
        // Extract src attribute
        if (!preg_match('/src=["\'](https?:\/\/[^"\']+)["\']/', $img_attrs, $src_matches)) {
            return $img_tag; // No src attribute found, return original tag
        }
        
        $src = $src_matches[1];
        
        // Skip if not a local image or not from the uploads directory
        $uploads_url = wp_upload_dir()['baseurl'];
        if (strpos($src, $uploads_url) !== 0) {
            return $img_tag;
        }
        
        // Get attachment ID from URL
        $attachment_id = $this->getAttachmentIdFromUrl($src);
        if (!$attachment_id) {
            return $img_tag;
        }
        
        // Get WebP and AVIF versions
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$meta) {
            return $img_tag;
        }
        
        $webp_url = $meta['webp_url'] ?? null;
        $avif_url = $meta['avif_url'] ?? null;
        
        // Return original tag if no optimized versions found
        if (!$webp_url && !$avif_url) {
            return $img_tag;
        }
        
        // Build picture tag
        $picture = '<picture>';
        
        // Add AVIF source if available
        if ($avif_url) {
            $picture .= '<source srcset="' . esc_attr($avif_url) . '" type="image/avif">';
        }
        
        // Add WebP source if available
        if ($webp_url) {
            $picture .= '<source srcset="' . esc_attr($webp_url) . '" type="image/webp">';
        }
        
        // Add original img tag
        $picture .= $img_tag;
        $picture .= '</picture>';
        
        return $picture;
    }
    
    /**
     * Get attachment ID from URL
     */
    private function getAttachmentIdFromUrl(string $url): ?int {
        global $wpdb;
        
        $uploads_dir = wp_upload_dir();
        $base_url = $uploads_dir['baseurl'];
        
        // Remove the base URL to get the relative path
        $relative_path = str_replace($base_url, '', $url);
        
        // Find attachment by relative path
        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                ltrim($relative_path, '/')
            )
        );
        
        return $attachment_id ? (int) $attachment_id : null;
    }
}
