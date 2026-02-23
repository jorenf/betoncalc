<?php
/**
 * Product Meta Box class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Admin;

use Bossier\Calculator\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Product_Meta_Box class - Adds calculator selection to WooCommerce products.
 */
class Product_Meta_Box {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_calculator_field' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_calculator_field' ) );
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_calculator_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_calculator_panel' ) );
    }

    /**
     * Add calculator info to product general options with link to Calculator tab.
     */
    public function add_calculator_field() {
        global $post;

        $calculator_id = get_post_meta( $post->ID, '_bossier_calculator_id', true );

        echo '<div class="options_group bossier-calculator-options">';
        echo '<p class="form-field">';
        echo '<label>' . esc_html__( 'Product Calculator', 'bossier-calculator' ) . '</label>';

        if ( $calculator_id ) {
            $calculator_post = get_post( $calculator_id );
            $title = $calculator_post ? $calculator_post->post_title : '#' . $calculator_id;
            printf(
                '<span><strong>%s</strong> &mdash; <a href="#" onclick="jQuery(\'.bossier_calculator_options a\').trigger(\'click\'); return false;">%s</a></span>',
                esc_html( $title ),
                esc_html__( 'Wijzig in Calculator tab', 'bossier-calculator' )
            );
        } else {
            printf(
                '<span><em>%s</em> &mdash; <a href="#" onclick="jQuery(\'.bossier_calculator_options a\').trigger(\'click\'); return false;">%s</a></span>',
                esc_html__( 'Geen calculator geselecteerd', 'bossier-calculator' ),
                esc_html__( 'Stel in via Calculator tab', 'bossier-calculator' )
            );
        }

        echo '</p>';
        echo '</div>';
    }

    /**
     * Save calculator field.
     *
     * @param int $post_id Product post ID.
     */
    public function save_calculator_field( $post_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification
        $calculator_id = isset( $_POST['_bossier_calculator_id'] ) ? absint( $_POST['_bossier_calculator_id'] ) : '';

        update_post_meta( $post_id, '_bossier_calculator_id', $calculator_id );
    }

    /**
     * Add calculator tab to product data tabs.
     *
     * @param array $tabs Existing tabs.
     * @return array Modified tabs.
     */
    public function add_calculator_tab( $tabs ) {
        $tabs['bossier_calculator'] = array(
            'label'    => __( 'Calculator', 'bossier-calculator' ),
            'target'   => 'bossier_calculator_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 21,
        );

        return $tabs;
    }

    /**
     * Add calculator panel to product data panels.
     */
    public function add_calculator_panel() {
        global $post;

        $calculator_id = get_post_meta( $post->ID, '_bossier_calculator_id', true );
        ?>
        <div id="bossier_calculator_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h4 style="padding-left: 12px;"><?php esc_html_e( 'Calculator Settings', 'bossier-calculator' ); ?></h4>

                <?php
                woocommerce_wp_select(
                    array(
                        'id'          => '_bossier_calculator_id',
                        'label'       => __( 'Select Calculator', 'bossier-calculator' ),
                        'desc_tip'    => true,
                        'description' => __( 'Select a calculator to enable custom pricing for this product.', 'bossier-calculator' ),
                        'options'     => Plugin::get_calculators_for_dropdown(),
                    )
                );
                ?>

                <p class="form-field">
                    <label>&nbsp;</label>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Plugin::POST_TYPE ) ); ?>"
                       target="_blank"
                       class="button">
                        <?php esc_html_e( 'Create New Calculator', 'bossier-calculator' ); ?>
                    </a>
                    <?php if ( $calculator_id ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $calculator_id ) ); ?>"
                           target="_blank"
                           class="button">
                            <?php esc_html_e( 'Edit Calculator', 'bossier-calculator' ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ( $calculator_id ) : ?>
                <div class="options_group">
                    <h4 style="padding-left: 12px;"><?php esc_html_e( 'Calculator Preview', 'bossier-calculator' ); ?></h4>
                    <p style="padding-left: 12px;">
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" class="button">
                            <?php esc_html_e( 'View Product with Calculator', 'bossier-calculator' ); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="options_group">
                <h4 style="padding-left: 12px;"><?php esc_html_e( 'How it works', 'bossier-calculator' ); ?></h4>
                <p style="padding-left: 12px; color: #666;">
                    <?php esc_html_e( '1. Create a calculator in Boost Calculators menu', 'bossier-calculator' ); ?><br>
                    <?php esc_html_e( '2. Select the calculator above for this product', 'bossier-calculator' ); ?><br>
                    <?php esc_html_e( '3. The calculator will appear on the product page', 'bossier-calculator' ); ?><br>
                    <?php esc_html_e( '4. Customer selections affect final price and weight', 'bossier-calculator' ); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
