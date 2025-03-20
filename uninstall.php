<?php
/**
 * Uninstall script for WebP & AVIF Image Optimizer
 *
 * @package WpImageOptimizer
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'wp_image_optimizer_settings' );
delete_option( 'wp_image_optimizer_logs' );

// Delete transients
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_wp_image_optimizer_%'" );

// Check if user wants to delete converted images
$delete_images = get_option( 'wp_image_optimizer_delete_on_uninstall', false );

if ( $delete_images ) {
	// Get all attachments with WebP/AVIF versions
	$attachments = $wpdb->get_results(
		"SELECT post_id, meta_value FROM $wpdb->postmeta 
        WHERE meta_key = '_wp_attachment_metadata' 
        AND (meta_value LIKE '%webp_path%' OR meta_value LIKE '%avif_path%')"
	);

	foreach ( $attachments as $attachment ) {
		// Get metadata
		$meta = maybe_unserialize( $attachment->meta_value );

		// Delete WebP file if exists
		if ( ! empty( $meta['webp_path'] ) && file_exists( $meta['webp_path'] ) ) {
			@unlink( $meta['webp_path'] );
		}

		// Delete AVIF file if exists
		if ( ! empty( $meta['avif_path'] ) && file_exists( $meta['avif_path'] ) ) {
			@unlink( $meta['avif_path'] );
		}

		// Remove WebP/AVIF info from metadata
		unset( $meta['webp_path'] );
		unset( $meta['webp_url'] );
		unset( $meta['avif_path'] );
		unset( $meta['avif_url'] );

		// Update metadata
		update_post_meta( $attachment->post_id, '_wp_attachment_metadata', $meta );
	}
}

// Remove capabilities
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'manage_webp_avif_optimizer' );
}
