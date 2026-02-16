<?php
/**
 * Main Plugin class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class - Main entry point for the plugin.
 */
class Plugin {

    /**
     * Single instance of the class.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Calculator post type name.
     *
     * @var string
     */
    const POST_TYPE = 'bossier_calculator';

    /**
     * Calculator category taxonomy name.
     *
     * @var string
     */
    const TAXONOMY = 'calculator_category';

    /**
     * Get single instance of the class.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_classes();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Initialize plugin classes.
     */
    private function init_classes() {
        // Admin classes
        if ( is_admin() ) {
            new Admin\Admin();
            new Admin\Product_Meta_Box();
        }

        // Frontend classes
        new Frontend\Display();
        new Frontend\Cart();
        new Frontend\Order();
    }

    /**
     * Register calculator custom post type.
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Calculators', 'Post type general name', 'bossier-calculator' ),
            'singular_name'         => _x( 'Calculator', 'Post type singular name', 'bossier-calculator' ),
            'menu_name'             => _x( 'Boost Calculators', 'Admin Menu text', 'bossier-calculator' ),
            'add_new'               => __( 'Add New', 'bossier-calculator' ),
            'add_new_item'          => __( 'Add New Calculator', 'bossier-calculator' ),
            'edit_item'             => __( 'Edit Calculator', 'bossier-calculator' ),
            'new_item'              => __( 'New Calculator', 'bossier-calculator' ),
            'view_item'             => __( 'View Calculator', 'bossier-calculator' ),
            'search_items'          => __( 'Search Calculators', 'bossier-calculator' ),
            'not_found'             => __( 'No calculators found', 'bossier-calculator' ),
            'not_found_in_trash'    => __( 'No calculators found in Trash', 'bossier-calculator' ),
            'all_items'             => __( 'All Calculators', 'bossier-calculator' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 56,
            'menu_icon'           => 'dashicons-calculator',
            'supports'            => array( 'title' ),
            'show_in_rest'        => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Register calculator category taxonomy.
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x( 'Categorieën', 'taxonomy general name', 'bossier-calculator' ),
            'singular_name'     => _x( 'Categorie', 'taxonomy singular name', 'bossier-calculator' ),
            'search_items'      => __( 'Zoek categorieën', 'bossier-calculator' ),
            'all_items'         => __( 'Alle categorieën', 'bossier-calculator' ),
            'parent_item'       => __( 'Bovenliggende categorie', 'bossier-calculator' ),
            'parent_item_colon' => __( 'Bovenliggende categorie:', 'bossier-calculator' ),
            'edit_item'         => __( 'Categorie bewerken', 'bossier-calculator' ),
            'update_item'       => __( 'Categorie bijwerken', 'bossier-calculator' ),
            'add_new_item'      => __( 'Nieuwe categorie toevoegen', 'bossier-calculator' ),
            'new_item_name'     => __( 'Nieuwe categorienaam', 'bossier-calculator' ),
            'menu_name'         => __( 'Categorieën', 'bossier-calculator' ),
            'not_found'         => __( 'Geen categorieën gevonden.', 'bossier-calculator' ),
        );

        register_taxonomy( self::TAXONOMY, self::POST_TYPE, array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => false,
            'rewrite'           => false,
        ) );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets() {
        if ( ! is_product() ) {
            return;
        }

        global $post;
        $calculator_id = get_post_meta( $post->ID, '_bossier_calculator_id', true );

        if ( empty( $calculator_id ) ) {
            return;
        }

        wp_enqueue_style(
            'bs-calc-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'bossier-calculator-frontend',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/frontend.css',
            array( 'bs-calc-fonts' ),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'bossier-calculator-frontend',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/calculator.js',
            array( 'jquery' ),
            BOSSIER_CALC_VERSION,
            true
        );

        $calculator = new Calculator( $calculator_id );
        $product    = wc_get_product( $post->ID );

        // Get the product base price and weight
        $product_price  = 0;
        $product_weight = 0;
        if ( $product ) {
            $product_price  = (float) $product->get_price();
            $product_weight = (float) $product->get_weight();
        }

        // Get the config and add product price/weight
        $config                  = $calculator->get_config();
        $config['productPrice']  = $product_price;
        $config['productWeight'] = $product_weight;

        wp_localize_script(
            'bossier-calculator-frontend',
            'bossierCalculator',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'bossier_calculator_nonce' ),
                'calculatorId' => $calculator_id,
                'productId'    => $post->ID,
                'productPrice' => $product_price,
                'config'       => $config,
                'i18n'         => array(
                    'price'         => __( 'Price', 'bossier-calculator' ),
                    'weight'        => __( 'Weight', 'bossier-calculator' ),
                    'currency'      => get_woocommerce_currency_symbol(),
                    'weightUnit'    => get_option( 'woocommerce_weight_unit', 'kg' ),
                    'calculating'   => __( 'Calculating...', 'bossier-calculator' ),
                    'selectOption'  => __( 'Select an option', 'bossier-calculator' ),
                    'required'      => __( 'This field is required', 'bossier-calculator' ),
                ),
            )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        // Determine page context
        $is_calculator_page = ( self::POST_TYPE === $post_type );
        $is_product_page    = ( 'product' === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) );
        $is_taxonomy_page   = ( 'edit-tags.php' === $hook || 'term.php' === $hook )
                              // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection, not form submission.
                              && isset( $_GET['taxonomy'] ) && self::TAXONOMY === sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) );
        $is_submenu_page    = ( false !== strpos( $hook, self::POST_TYPE . '_page_' ) );

        // Load admin CSS on all plugin pages (list, edit, taxonomy, product, submenus)
        if ( $is_calculator_page || $is_product_page || $is_taxonomy_page || $is_submenu_page ) {
            wp_enqueue_style(
                'bossier-calculator-admin',
                BOSSIER_CALC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BOSSIER_CALC_VERSION
            );
        }

        // Only load heavy assets (color picker, media, admin JS) on edit pages
        if ( ! $is_calculator_page && ! $is_product_page ) {
            return;
        }

        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && ! $is_product_page ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_media();

        wp_enqueue_script(
            'bossier-calculator-admin',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
            BOSSIER_CALC_VERSION,
            true
        );

        wp_localize_script(
            'bossier-calculator-admin',
            'bossierCalculatorAdmin',
            array(
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'bossier_calculator_admin_nonce' ),
                'currencySymbol' => get_woocommerce_currency_symbol(),
                'weightUnit'     => get_option( 'woocommerce_weight_unit', 'kg' ),
                'i18n'           => array(
                    'confirmDelete'     => __( 'Are you sure you want to delete this field?', 'bossier-calculator' ),
                    'confirmDuplicate'  => __( 'Are you sure you want to duplicate this calculator?', 'bossier-calculator' ),
                    'selectImage'       => __( 'Select Image', 'bossier-calculator' ),
                    'useImage'          => __( 'Use this image', 'bossier-calculator' ),
                    'addOption'         => __( 'Add Option', 'bossier-calculator' ),
                    'removeOption'      => __( 'Remove', 'bossier-calculator' ),
                    'saveWarning'       => __( 'Let op: er zijn ongeldige velden.', 'bossier-calculator' ),
                    'saveConfirm'       => __( 'Controleer deze of druk nogmaals op Opslaan om toch door te gaan.', 'bossier-calculator' ),
                ),
            )
        );
    }

    /**
     * Get all calculators for dropdown.
     *
     * @return array Array of calculator ID => title pairs.
     */
    public static function get_calculators_for_dropdown() {
        $calculators = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $options = array(
            '' => __( '— Select Calculator —', 'bossier-calculator' ),
        );

        // Group calculators by category
        $categorized   = array();
        $uncategorized = array();

        foreach ( $calculators as $calculator ) {
            $terms = get_the_terms( $calculator->ID, self::TAXONOMY );

            if ( $terms && ! is_wp_error( $terms ) ) {
                $term = $terms[0]; // Use first category
                if ( ! isset( $categorized[ $term->term_id ] ) ) {
                    $categorized[ $term->term_id ] = array(
                        'label' => $term->name,
                        'items' => array(),
                    );
                }
                $categorized[ $term->term_id ]['items'][ $calculator->ID ] = $calculator->post_title;
            } else {
                $uncategorized[ $calculator->ID ] = $calculator->post_title;
            }
        }

        // If no categories exist, return flat list
        if ( empty( $categorized ) ) {
            foreach ( $calculators as $calculator ) {
                $options[ $calculator->ID ] = $calculator->post_title;
            }
            return $options;
        }

        // Build grouped options: category name as prefix for optgroup simulation
        // WooCommerce woocommerce_wp_select doesn't support optgroups,
        // so we prefix calculator names with category
        foreach ( $categorized as $cat ) {
            foreach ( $cat['items'] as $id => $title ) {
                $options[ $id ] = $cat['label'] . ' — ' . $title;
            }
        }

        // Uncategorized at the end
        foreach ( $uncategorized as $id => $title ) {
            $options[ $id ] = $title;
        }

        return $options;
    }
}
