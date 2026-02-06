<?php
/**
 * Frontend Display class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Frontend;

use Bossier\Calculator\Plugin;
use Bossier\Calculator\Calculator;
use Bossier\Calculator\Field_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Display class - Renders calculator on product pages.
 */
class Display {

    /**
     * Constructor.
     */
    public function __construct() {
        // Hook calculator display before add to cart button
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_calculator' ), 10 );

        // Modify add to cart behavior for calculator products
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_calculator_fields' ), 10, 3 );
    }

    /**
     * Render calculator on product page.
     */
    public function render_calculator() {
        global $product;

        if ( ! $product ) {
            return;
        }

        $calculator_id = get_post_meta( $product->get_id(), '_bossier_calculator_id', true );

        if ( empty( $calculator_id ) ) {
            return;
        }

        $calculator = new Calculator( $calculator_id );

        if ( ! $calculator->is_valid() ) {
            return;
        }

        $fields   = $calculator->get_enabled_fields();
        $settings = $calculator->get_settings();

        // Always render calculator - length field is always shown even without other fields
        include BOSSIER_CALC_PLUGIN_DIR . 'frontend/views/calculator-form.php';
    }

    /**
     * Validate calculator fields before adding to cart.
     *
     * @param bool $passed     Validation status.
     * @param int  $product_id Product ID.
     * @param int  $quantity   Quantity.
     * @return bool Modified validation status.
     */
    public function validate_calculator_fields( $passed, $product_id, $quantity ) {
        $calculator_id = get_post_meta( $product_id, '_bossier_calculator_id', true );

        if ( empty( $calculator_id ) ) {
            return $passed;
        }

        $calculator = new Calculator( $calculator_id );

        if ( ! $calculator->is_valid() ) {
            return $passed;
        }

        // Validate core length field (always required)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['bossier_calc_length'] ) || '' === $_POST['bossier_calc_length'] ) {
            wc_add_notice(
                __( 'Lengte is verplicht.', 'bossier-calculator' ),
                'error'
            );
            $passed = false;
        } else {
            // Validate length is within bounds
            $settings   = $calculator->get_settings();
            $min_length = isset( $settings['min_length'] ) ? floatval( $settings['min_length'] ) : 1000;
            $max_length = isset( $settings['max_length'] ) ? floatval( $settings['max_length'] ) : 5000;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $length = floatval( $_POST['bossier_calc_length'] );

            if ( $length < $min_length || $length > $max_length ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %1$s: Min length, %2$s: Max length */
                        __( 'Lengte moet tussen %1$s en %2$s mm zijn.', 'bossier-calculator' ),
                        number_format( $min_length, 0, ',', '.' ),
                        number_format( $max_length, 0, ',', '.' )
                    ),
                    'error'
                );
                $passed = false;
            }
        }

        $fields = $calculator->get_enabled_fields();

        foreach ( $fields as $field_id => $field ) {
            // Skip deprecated length fields
            if ( 'length' === ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( empty( $field['required'] ) ) {
                continue;
            }

            $field_key = 'bossier_calc_' . $field_id;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! isset( $_POST[ $field_key ] ) || '' === $_POST[ $field_key ] ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: Field label */
                        __( '%s is verplicht.', 'bossier-calculator' ),
                        isset( $field['label'] ) ? $field['label'] : $field_id
                    ),
                    'error'
                );
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Render a single field.
     *
     * @param string $field_id Field identifier.
     * @param array  $field    Field configuration.
     */
    public static function render_field( $field_id, $field ) {
        $field_type  = isset( $field['type'] ) ? $field['type'] : 'custom';
        $input_type  = isset( $field['input_type'] ) ? $field['input_type'] : 'text';
        $label       = isset( $field['label'] ) ? $field['label'] : '';
        $required    = ! empty( $field['required'] );
        $help_text   = isset( $field['help_text'] ) ? $field['help_text'] : '';
        $field_name  = 'bossier_calc_' . $field_id;

        $wrapper_class = 'bossier-calc-field';
        $wrapper_class .= ' bossier-calc-field-' . $field_type;
        $wrapper_class .= ' bossier-calc-input-' . $input_type;

        if ( $required ) {
            $wrapper_class .= ' bossier-calc-required';
        }

        echo '<div class="' . esc_attr( $wrapper_class ) . '" data-field-id="' . esc_attr( $field_id ) . '" data-field-type="' . esc_attr( $field_type ) . '">';

        echo '<label class="bossier-calc-label">';
        echo esc_html( $label );
        if ( $required ) {
            echo '<span class="required">*</span>';
        }
        if ( ! empty( $help_text ) ) {
            echo '<span class="bossier-calc-tooltip" title="' . esc_attr( $help_text ) . '"><span class="bossier-calc-tooltip-icon">?</span></span>';
        }
        echo '</label>';

        echo '<div class="bossier-calc-input-wrap">';

        switch ( $field_type ) {
            case 'length':
                self::render_length_field( $field_id, $field, $field_name );
                break;

            case 'color':
                self::render_color_field( $field_id, $field, $field_name );
                break;

            case 'mitre_angle':
                self::render_angle_field( $field_id, $field, $field_name );
                break;

            case 'quantity':
                self::render_quantity_field( $field_id, $field, $field_name );
                break;

            case 'custom':
                self::render_custom_field( $field_id, $field, $field_name );
                break;
        }

        echo '</div>'; // .bossier-calc-input-wrap
        echo '</div>'; // .bossier-calc-field
    }

    /**
     * Render length field.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_length_field( $field_id, $field, $field_name ) {
        $mode       = isset( $field['length_mode'] ) ? $field['length_mode'] : 'free';
        $input_type = isset( $field['input_type'] ) ? $field['input_type'] : 'number';
        $unit       = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
        $required   = ! empty( $field['required'] );

        if ( 'fixed' === $mode && ! empty( $field['fixed_options'] ) ) {
            // Fixed options mode
            $options = $field['fixed_options'];

            if ( 'dropdown' === $input_type ) {
                echo '<select name="' . esc_attr( $field_name ) . '" class="bossier-calc-select" ' . ( $required ? 'required' : '' ) . '>';
                echo '<option value="">' . esc_html__( 'Select...', 'bossier-calculator' ) . '</option>';
                foreach ( $options as $idx => $option ) {
                    $option_label = ! empty( $option['label'] ) ? $option['label'] : $option['value'] . ' ' . $unit;
                    echo '<option value="' . esc_attr( $idx ) . '">' . esc_html( $option_label ) . '</option>';
                }
                echo '</select>';
            } else {
                // Radio buttons
                echo '<div class="bossier-calc-radio-group">';
                foreach ( $options as $idx => $option ) {
                    $option_label = ! empty( $option['label'] ) ? $option['label'] : $option['value'] . ' ' . $unit;
                    echo '<label class="bossier-calc-radio-label">';
                    echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $idx ) . '" ' . ( $required ? 'required' : '' ) . '>';
                    echo '<span>' . esc_html( $option_label ) . '</span>';
                    echo '</label>';
                }
                echo '</div>';
            }
        } else {
            // Free input mode
            $min  = isset( $field['min_value'] ) ? $field['min_value'] : 0;
            $max  = isset( $field['max_value'] ) ? $field['max_value'] : 10000;
            $step = isset( $field['step_size'] ) ? $field['step_size'] : 1;

            echo '<div class="bossier-calc-number-input">';
            echo '<input type="number" name="' . esc_attr( $field_name ) . '" ';
            echo 'min="' . esc_attr( $min ) . '" ';
            echo 'max="' . esc_attr( $max ) . '" ';
            echo 'step="' . esc_attr( $step ) . '" ';
            echo 'value="' . esc_attr( $min ) . '" ';
            echo 'class="bossier-calc-input" ';
            echo ( $required ? 'required' : '' ) . '>';
            echo '<span class="bossier-calc-unit">' . esc_html( $unit ) . '</span>';
            echo '</div>';
        }
    }

    /**
     * Render color field.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_color_field( $field_id, $field, $field_name ) {
        $colors     = isset( $field['colors'] ) ? $field['colors'] : array();
        $input_type = isset( $field['input_type'] ) ? $field['input_type'] : 'swatch';
        $required   = ! empty( $field['required'] );

        if ( empty( $colors ) ) {
            return;
        }

        // Find default color index
        $default_idx = null;
        foreach ( $colors as $idx => $color ) {
            if ( ! empty( $color['is_default'] ) ) {
                $default_idx = $idx;
                break;
            }
        }

        if ( 'dropdown' === $input_type ) {
            echo '<select name="' . esc_attr( $field_name ) . '" class="bossier-calc-select" ' . ( $required ? 'required' : '' ) . '>';
            echo '<option value="">' . esc_html__( 'Select color...', 'bossier-calculator' ) . '</option>';
            foreach ( $colors as $idx => $color ) {
                $label       = $color['name'];
                $is_default  = ! empty( $color['is_default'] );
                $surcharge   = isset( $color['surcharge'] ) ? floatval( $color['surcharge'] ) : 0;
                $price_type  = isset( $color['price_type'] ) ? $color['price_type'] : 'fixed';

                // Show surcharge info (skip for default color)
                if ( ! $is_default && $surcharge > 0 ) {
                    if ( 'percentage' === $price_type ) {
                        $label .= ' (+' . $surcharge . '%)';
                    } else {
                        $label .= ' (+' . wp_kses_post( wc_price( $surcharge ) ) . ')';
                    }
                }
                echo '<option value="' . esc_attr( $idx ) . '">' . wp_kses_post( $label ) . '</option>';
            }
            echo '</select>';
        } elseif ( 'swatch' === $input_type ) {
            echo '<div class="bossier-calc-color-swatches">';
            foreach ( $colors as $idx => $color ) {
                $style = '';
                if ( ! empty( $color['image'] ) ) {
                    $style = 'background-image: url(' . esc_url( $color['image'] ) . ');';
                } elseif ( ! empty( $color['hex'] ) ) {
                    $style = 'background-color: ' . esc_attr( $color['hex'] ) . ';';
                }

                $is_default = ! empty( $color['is_default'] );
                $checked    = ( $default_idx === $idx ) ? 'checked' : '';

                echo '<label class="bossier-calc-swatch' . ( $is_default ? ' bossier-calc-swatch-default' : '' ) . '" title="' . esc_attr( $color['name'] ) . '">';
                echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $idx ) . '" ' . $checked . ' ' . ( $required ? 'required' : '' ) . '>';
                echo '<span class="bossier-calc-swatch-inner" style="' . esc_attr( $style ) . '"></span>';
                echo '<span class="bossier-calc-swatch-label">' . esc_html( $color['name'] ) . '</span>';
                echo '</label>';
            }
            echo '</div>';
        } else {
            // Radio buttons
            echo '<div class="bossier-calc-radio-group">';
            foreach ( $colors as $idx => $color ) {
                $is_default = ! empty( $color['is_default'] );
                $surcharge  = isset( $color['surcharge'] ) ? floatval( $color['surcharge'] ) : 0;
                $price_type = isset( $color['price_type'] ) ? $color['price_type'] : 'fixed';
                $checked    = ( $default_idx === $idx ) ? 'checked' : '';

                echo '<label class="bossier-calc-radio-label' . ( $is_default ? ' bossier-calc-radio-default' : '' ) . '">';
                echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $idx ) . '" ' . $checked . ' ' . ( $required ? 'required' : '' ) . '>';
                if ( ! empty( $color['hex'] ) ) {
                    echo '<span class="bossier-calc-color-dot" style="background-color: ' . esc_attr( $color['hex'] ) . ';"></span>';
                }
                echo '<span>' . esc_html( $color['name'] ) . '</span>';
                if ( ! $is_default && $surcharge > 0 ) {
                    if ( 'percentage' === $price_type ) {
                        echo '<span class="bossier-calc-surcharge">(+' . esc_html( $surcharge ) . '%)</span>';
                    } else {
                        echo '<span class="bossier-calc-surcharge">(+' . wp_kses_post( wc_price( $surcharge ) ) . ')</span>';
                    }
                }
                echo '</label>';
            }
            echo '</div>';
        }
    }

    /**
     * Render mitre angle field.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_angle_field( $field_id, $field, $field_name ) {
        $angles        = isset( $field['angles'] ) ? $field['angles'] : array();
        $input_type    = isset( $field['input_type'] ) ? $field['input_type'] : 'radio';
        $required      = ! empty( $field['required'] );
        $default_angle = isset( $field['default_angle'] ) ? (int) $field['default_angle'] : 0;

        if ( empty( $angles ) ) {
            return;
        }

        // Get angle keys to properly map default
        $angle_keys = array_keys( $angles );
        $default_key = isset( $angle_keys[ $default_angle ] ) ? $angle_keys[ $default_angle ] : reset( $angle_keys );

        if ( 'dropdown' === $input_type ) {
            // Check if any angles have images
            $has_images = false;
            foreach ( $angles as $angle ) {
                if ( ! empty( $angle['image'] ) ) {
                    $has_images = true;
                    break;
                }
            }

            if ( $has_images ) {
                // Custom image dropdown with default pre-selected
                $default_label = isset( $angles[ $default_key ]['label'] ) ? $angles[ $default_key ]['label'] : __( 'Selecteer...', 'bossier-calculator' );
                $default_image = isset( $angles[ $default_key ]['image'] ) ? $angles[ $default_key ]['image'] : '';

                echo '<div class="bossier-calc-image-dropdown" data-field-name="' . esc_attr( $field_name ) . '">';
                echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $default_key ) . '" class="bossier-calc-image-dropdown-value" ' . ( $required ? 'required' : '' ) . '>';
                echo '<div class="bossier-calc-image-dropdown-selected">';
                if ( ! empty( $default_image ) ) {
                    echo '<img src="' . esc_url( $default_image ) . '" alt="" class="bossier-calc-dropdown-selected-image" style="max-width: 30px; max-height: 30px; vertical-align: middle; margin-right: 8px;">';
                }
                echo '<span class="bossier-calc-image-dropdown-text">' . esc_html( $default_label ) . '</span>';
                echo '<span class="bossier-calc-image-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>';
                echo '</div>';
                echo '<div class="bossier-calc-image-dropdown-options">';
                foreach ( $angles as $idx => $angle ) {
                    $surcharge  = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
                    $image      = isset( $angle['image'] ) ? $angle['image'] : '';
                    $is_default = ( $idx == $default_key );

                    echo '<div class="bossier-calc-image-dropdown-option' . ( $is_default ? ' selected' : '' ) . '" data-value="' . esc_attr( $idx ) . '">';
                    if ( ! empty( $image ) ) {
                        echo '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $angle['label'] ) . '" class="bossier-calc-dropdown-option-image">';
                    }
                    echo '<span class="bossier-calc-dropdown-option-label">' . esc_html( $angle['label'] ) . '</span>';
                    if ( $surcharge > 0 ) {
                        echo '<span class="bossier-calc-surcharge">(+' . wp_kses_post( wc_price( $surcharge ) ) . ')</span>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            } else {
                // Standard dropdown without images
                echo '<select name="' . esc_attr( $field_name ) . '" class="bossier-calc-select" ' . ( $required ? 'required' : '' ) . '>';
                foreach ( $angles as $idx => $angle ) {
                    $label      = $angle['label'];
                    $surcharge  = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
                    $is_default = ( $idx == $default_key );

                    if ( $surcharge > 0 ) {
                        $label .= ' (+' . wp_kses_post( wc_price( $surcharge ) ) . ')';
                    }
                    echo '<option value="' . esc_attr( $idx ) . '"' . ( $is_default ? ' selected' : '' ) . '>' . wp_kses_post( $label ) . '</option>';
                }
                echo '</select>';
            }
        } else {
            // Radio buttons with optional images
            echo '<div class="bossier-calc-radio-group bossier-calc-angle-group">';
            foreach ( $angles as $idx => $angle ) {
                $surcharge  = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
                $image      = isset( $angle['image'] ) ? $angle['image'] : '';
                $is_default = ( $idx == $default_key );

                echo '<label class="bossier-calc-radio-label bossier-calc-angle-label">';
                echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $idx ) . '" ' . ( $is_default ? 'checked' : '' ) . ' ' . ( $required ? 'required' : '' ) . '>';

                // Show image if available
                if ( ! empty( $image ) ) {
                    echo '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $angle['label'] ) . '" class="bossier-calc-angle-image">';
                }

                echo '<span class="bossier-calc-angle-text">' . esc_html( $angle['label'] ) . '</span>';

                if ( $surcharge > 0 ) {
                    echo '<span class="bossier-calc-surcharge">(+' . wp_kses_post( wc_price( $surcharge ) ) . ')</span>';
                }
                echo '</label>';
            }
            echo '</div>';
        }
    }

    /**
     * Render quantity field.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_quantity_field( $field_id, $field, $field_name ) {
        $min      = isset( $field['min_qty'] ) ? $field['min_qty'] : 1;
        $max      = isset( $field['max_qty'] ) ? $field['max_qty'] : 100;
        $step     = isset( $field['step_qty'] ) ? $field['step_qty'] : 1;
        $required = ! empty( $field['required'] );

        echo '<div class="bossier-calc-quantity-input">';
        echo '<button type="button" class="bossier-calc-qty-minus">-</button>';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '" ';
        echo 'min="' . esc_attr( $min ) . '" ';
        echo 'max="' . esc_attr( $max ) . '" ';
        echo 'step="' . esc_attr( $step ) . '" ';
        echo 'value="' . esc_attr( $min ) . '" ';
        echo 'class="bossier-calc-input bossier-calc-qty" ';
        echo ( $required ? 'required' : '' ) . '>';
        echo '<button type="button" class="bossier-calc-qty-plus">+</button>';
        echo '</div>';
    }

    /**
     * Render custom field.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_custom_field( $field_id, $field, $field_name ) {
        $options    = isset( $field['custom_options'] ) ? $field['custom_options'] : array();
        $input_type = isset( $field['input_type'] ) ? $field['input_type'] : 'dropdown';
        $required   = ! empty( $field['required'] );

        if ( empty( $options ) ) {
            return;
        }

        if ( 'dropdown' === $input_type ) {
            echo '<select name="' . esc_attr( $field_name ) . '" class="bossier-calc-select" ' . ( $required ? 'required' : '' ) . '>';
            echo '<option value="">' . esc_html__( 'Select...', 'bossier-calculator' ) . '</option>';
            foreach ( $options as $idx => $option ) {
                $label = $option['label'];
                if ( $option['surcharge'] > 0 ) {
                    $label .= ' (+' . wc_price( $option['surcharge'] ) . ')';
                }
                echo '<option value="' . esc_attr( $idx ) . '">' . wp_kses_post( $label ) . '</option>';
            }
            echo '</select>';
        } elseif ( 'checkbox' === $input_type ) {
            echo '<div class="bossier-calc-checkbox-group">';
            foreach ( $options as $idx => $option ) {
                echo '<label class="bossier-calc-checkbox-label">';
                echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $idx ) . '">';
                echo '<span>' . esc_html( $option['label'] ) . '</span>';
                if ( $option['surcharge'] > 0 ) {
                    echo '<span class="bossier-calc-surcharge">(+' . wp_kses_post( wc_price( $option['surcharge'] ) ) . ')</span>';
                }
                echo '</label>';
            }
            echo '</div>';
        } else {
            // Radio buttons
            echo '<div class="bossier-calc-radio-group">';
            foreach ( $options as $idx => $option ) {
                echo '<label class="bossier-calc-radio-label">';
                echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $idx ) . '" ' . ( $required ? 'required' : '' ) . '>';
                echo '<span>' . esc_html( $option['label'] ) . '</span>';
                if ( $option['surcharge'] > 0 ) {
                    echo '<span class="bossier-calc-surcharge">(+' . wp_kses_post( wc_price( $option['surcharge'] ) ) . ')</span>';
                }
                echo '</label>';
            }
            echo '</div>';
        }
    }
}
