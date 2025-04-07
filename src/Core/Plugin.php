<?php

namespace WpImageOptimizer\Core;

class Plugin
{
  public function __construct(
    private Container $container
  ) {}

  /**
   * Bootstrap the plugin
   */
  public function boot(): void
  {
    register_activation_hook(WP_IMAGE_OPTIMIZER_FILE, array($this, 'activate'));
    register_deactivation_hook(WP_IMAGE_OPTIMIZER_FILE, array($this, 'deactivate'));

    add_action('plugins_loaded', array($this, 'init'));
  }

  /**
   * Plugin activation hook
   */
  public function activate(): void
  {
    // Check for minimum PHP version
    if (version_compare(PHP_VERSION, '8.1', '<')) {
      deactivate_plugins(plugin_basename(WP_IMAGE_OPTIMIZER_FILE));
      wp_die('WebP & AVIF Optimizer requires PHP 8.1 or higher.');
    }

    // Initialize settings with defaults
    $settings = $this->container->get('settings');
    $settings->initDefaults();

    // Add capabilities
    $this->addCapabilities();

    // Log activation
    $logger = $this->container->get('logger');
    $logger->info('Plugin activated', array('version' => WP_IMAGE_OPTIMIZER_VERSION));
  }

  /**
   * Plugin deactivation hook
   */
  public function deactivate(): void
  {
    // Clean up temporary data
    $this->cleanupTemporaryData();

    // Log deactivation
    $logger = $this->container->get('logger');
    $logger->info('Plugin deactivated');
  }

  /**
   * Initialize the plugin after WordPress is loaded
   */
  public function init(): void
  {
    // Load text domain for translations
    load_plugin_textdomain('wp-image-optimizer', false, dirname(plugin_basename(WP_IMAGE_OPTIMIZER_FILE)) . '/languages');

    // Register hooks for all services
    $this->container->get('admin_page')->registerHooks();
    $this->container->get('media_library_integration')->registerHooks();
    $this->container->get('dashboard_widget')->registerHooks();

    // Hook into WordPress upload process
    add_filter('wp_handle_upload', array($this->container->get('media_processor'), 'processUploadedMedia'));
  }

  /**
   * Add plugin capabilities to roles
   */
  private function addCapabilities(): void
  {
    $admin = get_role('administrator');
    if ($admin) {
      $admin->add_cap('manage_webp_avif_optimizer');
    }
  }

  /**
   * Clean up temporary data on deactivation
   */
  private function cleanupTemporaryData(): void
  {
    global $wpdb;

    // Delete all transients
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_wp_image_optimizer_%'");
  }
}
