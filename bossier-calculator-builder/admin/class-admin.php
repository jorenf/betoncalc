<?php
/**
 * Admin class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Admin;

use Bossier\Calculator\Plugin;
use Bossier\Calculator\Calculator;
use Bossier\Calculator\Field_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class - Handles calculator admin interface.
 */
class Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . Plugin::POST_TYPE, array( $this, 'save_calculator' ), 10, 2 );
        add_filter( 'manage_' . Plugin::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
        add_action( 'manage_' . Plugin::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
        add_action( 'admin_action_bossier_duplicate_calculator', array( $this, 'handle_duplicate' ) );
        add_action( 'wp_ajax_bossier_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_bossier_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    /**
     * Add meta boxes to calculator edit screen.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'bossier_calculator_fields',
            __( 'Calculator Velden', 'bossier-calculator' ),
            array( $this, 'render_fields_meta_box' ),
            Plugin::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'bossier_calculator_settings',
            __( 'Calculator Instellingen', 'bossier-calculator' ),
            array( $this, 'render_settings_meta_box' ),
            Plugin::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'bossier_calculator_preview',
            __( 'Voorvertoning', 'bossier-calculator' ),
            array( $this, 'render_preview_meta_box' ),
            Plugin::POST_TYPE,
            'side',
            'low'
        );
    }

    /**
     * Render fields meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_fields_meta_box( $post ) {
        $calculator  = new Calculator( $post->ID );
        $fields      = $calculator->get_fields();
        $field_types = Field_Types::get_types();

        wp_nonce_field( 'bossier_calculator_save', 'bossier_calculator_nonce' );

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/calculator-fields.php';
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_settings_meta_box( $post ) {
        $calculator = new Calculator( $post->ID );
        $settings   = $calculator->get_settings();

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/calculator-settings.php';
    }

    /**
     * Render preview meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_preview_meta_box( $post ) {
        ?>
        <p class="description">
            <?php esc_html_e( 'Sla de calculator op en bezoek een gekoppeld product om de voorvertoning te zien.', 'bossier-calculator' ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Gekoppelde Producten:', 'bossier-calculator' ); ?></strong>
        </p>
        <?php
        $linked_products = $this->get_linked_products( $post->ID );

        if ( empty( $linked_products ) ) {
            echo '<p><em>' . esc_html__( 'Geen producten gekoppeld aan deze calculator.', 'bossier-calculator' ) . '</em></p>';
        } else {
            echo '<ul>';
            foreach ( $linked_products as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    printf(
                        '<li><a href="%s" target="_blank">%s</a></li>',
                        esc_url( get_permalink( $product_id ) ),
                        esc_html( $product->get_name() )
                    );
                }
            }
            echo '</ul>';
        }
    }

    /**
     * Get products linked to a calculator.
     *
     * @param int $calculator_id Calculator ID.
     * @return array Product IDs.
     */
    private function get_linked_products( $calculator_id ) {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_bossier_calculator_id'
                AND meta_value = %s",
                $calculator_id
            )
        );
    }

    /**
     * Save calculator data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function save_calculator( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['bossier_calculator_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bossier_calculator_nonce'] ) ), 'bossier_calculator_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Get and sanitize fields
        $fields = array();
        if ( isset( $_POST['bossier_fields'] ) && is_array( $_POST['bossier_fields'] ) ) {
            $fields = $this->sanitize_posted_fields( wp_unslash( $_POST['bossier_fields'] ) ); // phpcs:ignore
        }

        // Get and sanitize settings
        $settings = array();
        if ( isset( $_POST['bossier_settings'] ) && is_array( $_POST['bossier_settings'] ) ) {
            $settings = $this->sanitize_posted_settings( wp_unslash( $_POST['bossier_settings'] ) ); // phpcs:ignore
        }

        // Save via Calculator model
        $calculator = new Calculator( $post_id );
        $calculator->save( $fields, $settings );
    }

    /**
     * Sanitize posted fields.
     *
     * @param array $posted_fields Raw posted fields.
     * @return array Sanitized fields.
     */
    private function sanitize_posted_fields( $posted_fields ) {
        // Basic sanitization - the Calculator class does full sanitization
        $fields = array();

        foreach ( $posted_fields as $field_id => $field_data ) {
            $field_id = sanitize_key( $field_id );
            $fields[ $field_id ] = $field_data;
        }

        return $fields;
    }

    /**
     * Sanitize posted settings.
     *
     * @param array $posted_settings Raw posted settings.
     * @return array Sanitized settings.
     */
    private function sanitize_posted_settings( $posted_settings ) {
        // Basic sanitization - the Calculator class does full sanitization
        return $posted_settings;
    }

    /**
     * Add custom columns to calculator list.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_columns( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            if ( 'title' === $key ) {
                $new_columns['fields_count']   = __( 'Velden', 'bossier-calculator' );
                $new_columns['products_count'] = __( 'Gekoppelde Producten', 'bossier-calculator' );
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_column( $column, $post_id ) {
        $calculator = new Calculator( $post_id );

        switch ( $column ) {
            case 'fields_count':
                $fields = $calculator->get_enabled_fields();
                echo count( $fields );
                break;

            case 'products_count':
                $products = $this->get_linked_products( $post_id );
                echo count( $products );
                break;
        }
    }

    /**
     * Add row actions to calculator list.
     *
     * @param array    $actions Existing actions.
     * @param \WP_Post $post    Current post.
     * @return array Modified actions.
     */
    public function add_row_actions( $actions, $post ) {
        if ( Plugin::POST_TYPE !== $post->post_type ) {
            return $actions;
        }

        $duplicate_url = wp_nonce_url(
            admin_url( 'admin.php?action=bossier_duplicate_calculator&post=' . $post->ID ),
            'bossier_duplicate_' . $post->ID
        );

        $actions['duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $duplicate_url ),
            esc_html__( 'Dupliceren', 'bossier-calculator' )
        );

        return $actions;
    }

    /**
     * Handle calculator duplication.
     */
    public function handle_duplicate() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        if ( ! $post_id ) {
            wp_die( esc_html__( 'Ongeldige calculator ID.', 'bossier-calculator' ) );
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bossier_duplicate_' . $post_id ) ) {
            wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'U heeft geen toestemming om deze calculator te dupliceren.', 'bossier-calculator' ) );
        }

        $calculator  = new Calculator( $post_id );
        $new_post_id = $calculator->duplicate();

        if ( $new_post_id ) {
            wp_safe_redirect(
                admin_url( 'post.php?action=edit&post=' . $new_post_id )
            );
            exit;
        } else {
            wp_die( esc_html__( 'Calculator dupliceren mislukt.', 'bossier-calculator' ) );
        }
    }

    /**
     * AJAX handler for price calculation.
     */
    public function ajax_calculate_price() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bossier_calculator_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) ) );
        }

        $calculator_id = isset( $_POST['calculator_id'] ) ? absint( $_POST['calculator_id'] ) : 0;
        $selections    = isset( $_POST['selections'] ) ? $this->sanitize_ajax_selections( wp_unslash( $_POST['selections'] ) ) : array(); // phpcs:ignore

        if ( ! $calculator_id ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldige calculator.', 'bossier-calculator' ) ) );
        }

        $result = \Bossier\Calculator\Price_Calculator::calculate_from_request( $calculator_id, $selections );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Berekening mislukt.', 'bossier-calculator' ) ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Sanitize AJAX selections.
     *
     * @param array $selections Raw selections.
     * @return array Sanitized selections.
     */
    private function sanitize_ajax_selections( $selections ) {
        if ( ! is_array( $selections ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $selections as $key => $value ) {
            $key = sanitize_key( $key );

            if ( is_array( $value ) ) {
                $sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $sanitized[ $key ] = sanitize_text_field( $value );
            }
        }

        return $sanitized;
    }
}
