<?php

namespace WpImageOptimizer\Core;

class Settings {
	private const OPTION_KEY = 'wp_image_optimizer_settings';

	private array $defaults = array(
		// General settings
		'auto_convert'      => true,
		'enable_webp'       => true,
		'enable_avif'       => true,

		// WebP settings
		'webp_quality'      => 80,
		'webp_lossless'     => false,

		// AVIF settings
		'avif_quality'      => 65,
		'avif_speed'        => 6,
		'avif_lossless'     => false,

		// Performance settings
		'bulk_batch_size'   => 10,
		'processing_delay'  => 250, // milliseconds

		// Advanced settings
		'conversion_method' => 'auto', // auto, gd, imagick, or exec
	);

	private ?array $settings = null;

	/**
	 * Get a setting value
	 */
	public function get( string $key, $default = null ) {
		$this->loadIfNeeded();
		return $this->settings[ $key ] ?? ( $default ?? $this->defaults[ $key ] ?? null );
	}

	/**
	 * Set a setting value
	 */
	public function set( string $key, $value ): void {
		$this->loadIfNeeded();
		$this->settings[ $key ] = $value;
		$this->save();
	}

	/**
	 * Get all settings
	 */
	public function getAll(): array {
		$this->loadIfNeeded();
		return $this->settings;
	}

	/**
	 * Initialize default settings
	 */
	public function initDefaults(): void {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( empty( $saved ) ) {
			update_option( self::OPTION_KEY, $this->defaults );
		}
	}

	/**
	 * Load settings from database if not already loaded
	 */
	private function loadIfNeeded(): void {
		if ( $this->settings === null ) {
			$saved          = get_option( self::OPTION_KEY, array() );
			$this->settings = array_merge( $this->defaults, $saved );
		}
	}

	/**
	 * Save settings to database
	 */
	private function save(): void {
		update_option( self::OPTION_KEY, $this->settings );
	}
}
