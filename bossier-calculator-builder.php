<?php
/**
 * Plugin Name: Boost Calculator
 * Plugin URI: https://bossierbeton.nl
 * Description: Dynamic product calculator system for WooCommerce with admin builder and full cart/order integration.
 * Version: 2.1.6
 * Author: ByteQ
 * Author URI: https://byteq.nl
 * Text Domain: bossier-calculator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'BOSSIER_CALC_VERSION', '2.1.6' );
define( 'BOSSIER_CALC_PLUGIN_FILE', __FILE__ );
define( 'BOSSIER_CALC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOSSIER_CALC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOSSIER_CALC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
spl_autoload_register( function( $class_name ) {
    // Only autoload our namespace
    if ( strpos( $class_name, 'Bossier\\Calculator\\' ) !== 0 ) {
        return;
    }

    // Remove namespace prefix
    $class_name = str_replace( 'Bossier\\Calculator\\', '', $class_name );

    // Convert to file path
    $class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name );
    $class_file = str_replace( '_', '-', strtolower( $class_file ) );

    // Determine directory based on class name
    $directories = array(
        'Admin'    => 'admin',
        'Frontend' => 'frontend',
        'PDF'      => 'includes/pdf',
        'BTW'      => 'includes/btw',
        'Shipping' => 'includes/shipping',
    );

    $file_path = '';
    foreach ( $directories as $namespace => $dir ) {
        if ( strpos( $class_name, $namespace ) === 0 ) {
            $class_file = str_replace( strtolower( $namespace ) . DIRECTORY_SEPARATOR, '', $class_file );
            $file_path = BOSSIER_CALC_PLUGIN_DIR . $dir . '/class-' . $class_file . '.php';
            break;
        }
    }

    // Default to includes directory
    if ( empty( $file_path ) ) {
        $file_path = BOSSIER_CALC_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
    }

    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
});

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function is_woocommerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
        true
    );
}

/**
 * Initialize the plugin.
 */
function init_plugin() {
    // Check WooCommerce dependency
    if ( ! is_woocommerce_active() ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Boost Calculator requires WooCommerce to be installed and active.', 'bossier-calculator' );
            echo '</p></div>';
        });
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'bossier-calculator', false, dirname( BOSSIER_CALC_PLUGIN_BASENAME ) . '/languages' );

    // Initialize main plugin class
    Plugin::get_instance();

    // Initialize PDF system (invoices, packing slips, email attachments)
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-settings.php';
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-order-metabox.php';
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-email-attachment.php';
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-template-editor.php';
    PDF\PDF_Settings::get_instance();
    PDF\PDF_Order_Metabox::get_instance();
    PDF\PDF_Email_Attachment::get_instance();
    PDF\PDF_Template_Editor::get_instance();

    // Initialize updater (only in admin)
    if ( is_admin() ) {
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/class-updater.php';
        Updater::get_instance();
    }

    // Initialize Modules Settings (admin page with BTW & Shipping)
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/class-modules-settings.php';
    Modules_Settings::get_instance();

    // Initialize BTW Verlegd module if enabled
    if ( Modules_Settings::is_btw_enabled() ) {
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/btw/class-btw-module.php';
        BTW\BTW_Module::get_instance();
    }

    // Initialize Shipping module if enabled
    if ( Modules_Settings::is_shipping_enabled() ) {
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-shipping-module.php';
        Shipping\Shipping_Module::get_instance();
    }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_plugin' );

/**
 * Activation hook.
 */
function activate_plugin() {
    // Flush rewrite rules for custom post type
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );

/**
 * Deactivation hook.
 */
function deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_plugin' );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
