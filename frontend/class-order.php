<?php
/**
 * Order integration class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Order class - Handles WooCommerce order integration.
 */
class Order {

    /**
     * Constructor.
     */
    public function __construct() {
        // Save calculator data to order item
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );

        // Display calculator data in order details (frontend)
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );

        // Display calculator data in admin order
        add_action( 'woocommerce_before_order_itemmeta', array( $this, 'display_admin_order_item_meta' ), 10, 3 );

        // Add calculator data to order emails
        add_filter( 'woocommerce_order_item_name', array( $this, 'add_calculator_info_to_emails' ), 10, 2 );

        // Display weight in order items
        add_action( 'woocommerce_order_item_meta_end', array( $this, 'display_order_item_weight' ), 10, 4 );

        // Add total weight to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_total_weight' ), 10, 2 );

        // Display total weight in order admin
        add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_admin_order_total_weight' ), 10 );

        // Display total weight on thank you page and order details
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_total_weight' ), 10 );

        // Add total weight to order emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_email_total_weight' ), 10, 4 );
    }

    /**
     * Save calculator data to order item meta.
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order object.
     */
    public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! isset( $values['bossier_calculator'] ) ) {
            return;
        }

        $calc_data = $values['bossier_calculator'];

        // Save calculator ID
        $item->add_meta_data( '_bossier_calculator_id', $calc_data['calculator_id'], true );

        // Save selections
        $item->add_meta_data( '_bossier_selections', $calc_data['selections'], true );

        // Save display data (for easy rendering)
        $item->add_meta_data( '_bossier_display_data', $calc_data['display_data'], true );

        // Save calculated price and weight
        $item->add_meta_data( '_bossier_calculated_price', $calc_data['calculated_price'], true );
        $item->add_meta_data( '_bossier_calculated_weight', $calc_data['calculated_weight'], true );

        // Save breakdown
        if ( ! empty( $calc_data['breakdown'] ) ) {
            $item->add_meta_data( '_bossier_breakdown', $calc_data['breakdown'], true );
        }

        // Save individual field values for display
        if ( ! empty( $calc_data['display_data'] ) ) {
            foreach ( $calc_data['display_data'] as $field_id => $data ) {
                if ( empty( $data['value'] ) ) {
                    continue;
                }
                // Prefix with underscore to hide from default display, we'll handle display ourselves
                $item->add_meta_data( '_bossier_field_' . $field_id, $data, true );

                // Also save visible meta for standard WC display
                $item->add_meta_data( $data['label'], $data['value'], true );
            }
        }

        // Save weight as visible meta (only if weight > 0)
        if ( ! empty( $calc_data['calculated_weight'] ) && floatval( $calc_data['calculated_weight'] ) > 0 ) {
            $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
            $item->add_meta_data(
                __( 'Weight', 'bossier-calculator' ),
                wc_format_localized_decimal( $calc_data['calculated_weight'] ) . ' ' . $weight_unit,
                true
            );
        }
    }

    /**
     * Format order item meta for display.
     *
     * @param array                  $formatted_meta Formatted meta data.
     * @param \WC_Order_Item_Product $item           Order item.
     * @return array Modified formatted meta.
     */
    public function format_order_item_meta( $formatted_meta, $item ) {
        // Hide internal meta keys from display
        $hidden_keys = array(
            '_bossier_calculator_id',
            '_bossier_selections',
            '_bossier_display_data',
            '_bossier_calculated_price',
            '_bossier_calculated_weight',
            '_bossier_breakdown',
        );

        foreach ( $formatted_meta as $meta_id => $meta ) {
            if ( in_array( $meta->key, $hidden_keys, true ) ) {
                unset( $formatted_meta[ $meta_id ] );
            }
            // Also hide field-specific internal keys
            if ( strpos( $meta->key, '_bossier_field_' ) === 0 ) {
                unset( $formatted_meta[ $meta_id ] );
            }
        }

        return $formatted_meta;
    }

    /**
     * Display calculator data in admin order item.
     *
     * @param int                    $item_id Order item ID.
     * @param \WC_Order_Item_Product $item    Order item.
     * @param \WC_Product|null       $product Product object.
     */
    public function display_admin_order_item_meta( $item_id, $item, $product ) {
        if ( ! $item instanceof \WC_Order_Item_Product ) {
            return;
        }

        $calc_id = $item->get_meta( '_bossier_calculator_id' );

        if ( empty( $calc_id ) ) {
            return;
        }

        $display_data = $item->get_meta( '_bossier_display_data' );
        $breakdown    = $item->get_meta( '_bossier_breakdown' );
        $weight       = $item->get_meta( '_bossier_calculated_weight' );
        $weight_unit  = get_option( 'woocommerce_weight_unit', 'kg' );

        if ( empty( $display_data ) && empty( $weight ) && empty( $breakdown ) ) {
            return;
        }

        echo '<div class="bossier-order-item-meta">';
        echo '<strong>' . esc_html__( 'Calculator Details:', 'bossier-calculator' ) . '</strong>';
        echo '<ul class="bossier-order-meta-list">';

        if ( is_array( $display_data ) ) {
            foreach ( $display_data as $data ) {
                if ( empty( $data['value'] ) ) {
                    continue;
                }
                echo '<li><strong>' . esc_html( $data['label'] ) . ':</strong> ' . esc_html( $data['value'] ) . '</li>';
            }
        }

        if ( $weight ) {
            echo '<li><strong>' . esc_html__( 'Weight', 'bossier-calculator' ) . ':</strong> ';
            echo esc_html( wc_format_localized_decimal( $weight ) . ' ' . $weight_unit );
            echo '</li>';
        }

        echo '</ul>';

        // Show price breakdown for admin (including hidden items like long length surcharge)
        if ( ! empty( $breakdown ) && is_array( $breakdown ) ) {
            echo '<div class="bossier-admin-breakdown" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc;">';
            echo '<strong>' . esc_html__( 'Price Breakdown:', 'bossier-calculator' ) . '</strong>';
            echo '<ul class="bossier-breakdown-list" style="margin: 5px 0 0 0;">';

            foreach ( $breakdown as $item_row ) {
                $label  = isset( $item_row['label'] ) ? $item_row['label'] : '';
                $amount = isset( $item_row['amount'] ) ? floatval( $item_row['amount'] ) : 0;
                $hidden = isset( $item_row['hidden'] ) && $item_row['hidden'];

                if ( empty( $label ) ) {
                    continue;
                }

                $style = '';
                $badge = '';
                if ( $hidden ) {
                    // Highlight hidden items (like long length surcharge) for admin
                    $style = 'color: #d63638; font-style: italic;';
                    $badge = ' <span style="background: #d63638; color: #fff; font-size: 10px; padding: 1px 5px; border-radius: 3px; margin-left: 5px;">' . esc_html__( 'Hidden from customer', 'bossier-calculator' ) . '</span>';
                }

                echo '<li style="' . esc_attr( $style ) . '">';
                echo esc_html( $label ) . ': ' . wp_kses_post( wc_price( $amount ) );
                echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Add calculator info to order emails.
     *
     * @param string                 $name Product name.
     * @param \WC_Order_Item_Product $item Order item.
     * @return string Modified name.
     */
    public function add_calculator_info_to_emails( $name, $item ) {
        if ( ! $item instanceof \WC_Order_Item_Product ) {
            return $name;
        }

        // Only modify in email context
        if ( ! did_action( 'woocommerce_email_order_details' ) ) {
            return $name;
        }

        $calc_id = $item->get_meta( '_bossier_calculator_id' );

        if ( empty( $calc_id ) ) {
            return $name;
        }

        $display_data = $item->get_meta( '_bossier_display_data' );

        if ( empty( $display_data ) || ! is_array( $display_data ) ) {
            return $name;
        }

        $info_parts = array();
        foreach ( $display_data as $data ) {
            if ( ! empty( $data['value'] ) ) {
                $info_parts[] = $data['label'] . ': ' . $data['value'];
            }
        }

        if ( ! empty( $info_parts ) ) {
            $name .= '<br><small style="color: #666;">' . esc_html( implode( ' | ', $info_parts ) ) . '</small>';
        }

        return $name;
    }

    /**
     * Display order item weight on frontend.
     *
     * @param int       $item_id    Order item ID.
     * @param array     $item       Order item data.
     * @param \WC_Order $order      Order object.
     * @param bool      $plain_text Is plain text email.
     */
    public function display_order_item_weight( $item_id, $item, $order, $plain_text = false ) {
        if ( ! $item instanceof \WC_Order_Item_Product ) {
            return;
        }

        $weight = $item->get_meta( '_bossier_calculated_weight' );

        if ( empty( $weight ) ) {
            return;
        }

        $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
        $quantity    = $item->get_quantity();
        $total_weight = $weight * $quantity;

        if ( $plain_text ) {
            echo "\n" . esc_html__( 'Weight', 'bossier-calculator' ) . ': ' . wc_format_localized_decimal( $total_weight ) . ' ' . $weight_unit;
        }
    }

    /**
     * Save total weight to order meta.
     *
     * @param \WC_Order $order Order object.
     * @param array     $data  Order data.
     */
    public function save_order_total_weight( $order, $data ) {
        $total_weight = 0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['bossier_calculator'] ) ) {
                $calc_data = $cart_item['bossier_calculator'];
                $item_weight = floatval( $calc_data['calculated_weight'] );
                $total_weight += $item_weight * $cart_item['quantity'];
            } else {
                $product = $cart_item['data'];
                if ( $product && $product->has_weight() ) {
                    $total_weight += floatval( $product->get_weight() ) * $cart_item['quantity'];
                }
            }
        }

        $order->update_meta_data( '_bossier_total_weight', $total_weight );
    }

    /**
     * Display total weight in admin order totals.
     *
     * @param int $order_id Order ID.
     */
    public function display_admin_order_total_weight( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $total_weight = $order->get_meta( '_bossier_total_weight' );

        if ( empty( $total_weight ) ) {
            // Calculate from items
            $total_weight = $this->calculate_order_weight( $order );
        }

        if ( empty( $total_weight ) ) {
            return;
        }

        $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
        ?>
        <tr>
            <td class="label"><?php esc_html_e( 'Total Weight', 'bossier-calculator' ); ?>:</td>
            <td width="1%"></td>
            <td class="total">
                <?php echo esc_html( wc_format_localized_decimal( $total_weight ) . ' ' . $weight_unit ); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Display total weight on order details page.
     *
     * @param \WC_Order $order Order object.
     */
    public function display_order_total_weight( $order ) {
        $total_weight = $order->get_meta( '_bossier_total_weight' );

        if ( empty( $total_weight ) ) {
            $total_weight = $this->calculate_order_weight( $order );
        }

        if ( empty( $total_weight ) ) {
            return;
        }

        $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
        ?>
        <p class="bossier-order-total-weight">
            <strong><?php esc_html_e( 'Total Weight', 'bossier-calculator' ); ?>:</strong>
            <?php echo esc_html( wc_format_localized_decimal( $total_weight ) . ' ' . $weight_unit ); ?>
        </p>
        <?php
    }

    /**
     * Display total weight in order emails.
     *
     * @param \WC_Order $order         Order object.
     * @param bool      $sent_to_admin Sent to admin.
     * @param bool      $plain_text    Is plain text.
     * @param object    $email         Email object.
     */
    public function display_email_total_weight( $order, $sent_to_admin, $plain_text, $email ) {
        $total_weight = $order->get_meta( '_bossier_total_weight' );

        if ( empty( $total_weight ) ) {
            $total_weight = $this->calculate_order_weight( $order );
        }

        if ( empty( $total_weight ) ) {
            return;
        }

        $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );

        if ( $plain_text ) {
            echo "\n" . esc_html__( 'Total Weight', 'bossier-calculator' ) . ': ' . wc_format_localized_decimal( $total_weight ) . ' ' . $weight_unit . "\n";
        } else {
            ?>
            <p style="margin-top: 10px;">
                <strong><?php esc_html_e( 'Total Weight', 'bossier-calculator' ); ?>:</strong>
                <?php echo esc_html( wc_format_localized_decimal( $total_weight ) . ' ' . $weight_unit ); ?>
            </p>
            <?php
        }
    }

    /**
     * Calculate total weight from order items.
     *
     * @param \WC_Order $order Order object.
     * @return float Total weight.
     */
    private function calculate_order_weight( $order ) {
        $total_weight = 0;

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof \WC_Order_Item_Product ) {
                continue;
            }

            $item_weight = $item->get_meta( '_bossier_calculated_weight' );

            if ( $item_weight ) {
                $total_weight += floatval( $item_weight ) * $item->get_quantity();
            } else {
                // Get weight from product
                $product = $item->get_product();
                if ( $product && $product->has_weight() ) {
                    $total_weight += floatval( $product->get_weight() ) * $item->get_quantity();
                }
            }
        }

        return $total_weight;
    }
}
