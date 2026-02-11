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

        $fields = $calculator->get_enabled_fields();

        foreach ( $fields as $field_id => $field ) {
            // Skip deprecated length fields
            if ( 'length' === ( $field['type'] ?? '' ) ) {
                continue;
            }

            // Skip fields hidden by show_when condition
            if ( ! self::is_field_visible_in_post( $field, $fields ) ) {
                continue;
            }

            $field_key = 'bossier_calc_' . $field_id;

            // Validate required fields
            if ( ! empty( $field['required'] ) ) {
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
                    continue;
                }
            }

            // Validate dimension fields range
            if ( 'dimension' === ( $field['type'] ?? '' ) && isset( $_POST[ $field_key ] ) && '' !== $_POST[ $field_key ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $dim_value = floatval( $_POST[ $field_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $dim_min   = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
                $dim_max   = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 99999;
                $dim_label = isset( $field['label'] ) ? $field['label'] : $field_id;

                if ( $dim_value < $dim_min || $dim_value > $dim_max ) {
                    wc_add_notice(
                        sprintf(
                            /* translators: %1$s: Field label, %2$s: Min value, %3$s: Max value */
                            __( '%1$s moet tussen %2$s en %3$s mm zijn.', 'bossier-calculator' ),
                            $dim_label,
                            number_format( $dim_min, 0, ',', '.' ),
                            number_format( $dim_max, 0, ',', '.' )
                        ),
                        'error'
                    );
                    $passed = false;
                }
            }
        }

        return $passed;
    }

    /**
     * Check if a field is visible based on show_when condition against POST data.
     *
     * @param array $field      Field config.
     * @param array $all_fields All fields.
     * @return bool
     */
    public static function is_field_visible_in_post( $field, $all_fields ) {
        if ( empty( $field['show_when_field'] ) ) {
            return true;
        }

        $source_field_id = $field['show_when_field'];
        $expected_value  = isset( $field['show_when_value'] ) ? $field['show_when_value'] : '';
        $post_key        = 'bossier_calc_' . $source_field_id;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $actual_value = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';

        return (string) $actual_value === (string) $expected_value;
    }

    /**
     * Render a single field with new bs-calc BEM structure.
     *
     * @param string $field_id Field identifier.
     * @param array  $field    Field configuration.
     */
    public static function render_field( $field_id, $field ) {
        $field_type  = isset( $field['type'] ) ? $field['type'] : 'custom';
        $label       = isset( $field['label'] ) ? $field['label'] : '';
        $required    = ! empty( $field['required'] );
        $help_text   = isset( $field['help_text'] ) ? $field['help_text'] : '';
        $field_name  = 'bossier_calc_' . $field_id;

        // Build wrapper classes
        $wrapper_class = 'bs-calc__field';

        // Show_when: hidden by default if condition is set
        $show_attrs = '';
        if ( ! empty( $field['show_when_field'] ) ) {
            $wrapper_class .= ' bs-calc__field--hidden';
            $show_attrs = ' data-show-when-field="' . esc_attr( $field['show_when_field'] ) . '"'
                        . ' data-show-when-value="' . esc_attr( isset( $field['show_when_value'] ) ? $field['show_when_value'] : '' ) . '"';
        }

        echo '<div class="' . esc_attr( $wrapper_class ) . '" data-field-id="' . esc_attr( $field_id ) . '" data-field-type="' . esc_attr( $field_type ) . '"' . $show_attrs . '>';

        // Label
        echo '<label class="bs-calc__label">';
        echo esc_html( $label );
        if ( $required ) {
            echo ' <span class="bs-calc__label-req">*</span>';
        }
        if ( ! empty( $help_text ) ) {
            echo ' <span class="bs-calc__hint-icon" title="' . esc_attr( $help_text ) . '">?</span>';
        }
        echo '</label>';

        // Field content
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

            case 'dimension':
                self::render_dimension_field( $field_id, $field, $field_name );
                break;

            case 'text':
                self::render_text_field( $field_id, $field, $field_name );
                break;

            case 'brievenbus':
                self::render_brievenbus_field( $field_id, $field, $field_name );
                break;
        }

        echo '</div>'; // .bs-calc__field
    }

    /**
     * Render length field (legacy).
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
            $options = $field['fixed_options'];

            if ( 'dropdown' === $input_type ) {
                echo '<div class="bs-calc__select-wrap">';
                echo '<select name="' . esc_attr( $field_name ) . '" class="bs-calc__select" ' . ( $required ? 'required' : '' ) . '>';
                echo '<option value="">' . esc_html__( 'Selecteer...', 'bossier-calculator' ) . '</option>';
                foreach ( $options as $idx => $option ) {
                    $option_label = ! empty( $option['label'] ) ? $option['label'] : $option['value'] . ' ' . $unit;
                    echo '<option value="' . esc_attr( $idx ) . '">' . esc_html( $option_label ) . '</option>';
                }
                echo '</select>';
                echo '<span class="bs-calc__select-arrow">&#9660;</span>';
                echo '</div>';
            } else {
                echo '<div class="bs-calc__toggles">';
                foreach ( $options as $idx => $option ) {
                    $option_label = ! empty( $option['label'] ) ? $option['label'] : $option['value'] . ' ' . $unit;
                    echo '<button type="button" class="bs-calc__toggle" data-value="' . esc_attr( $idx ) . '">' . esc_html( $option_label ) . '</button>';
                }
                echo '</div>';
                echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="" class="bs-calc__toggle-value">';
            }
        } else {
            $min  = isset( $field['min_value'] ) ? $field['min_value'] : 0;
            $max  = isset( $field['max_value'] ) ? $field['max_value'] : 10000;
            $step = isset( $field['step_size'] ) ? $field['step_size'] : 1;

            echo '<div class="bs-calc__input-wrap">';
            echo '<input type="number" name="' . esc_attr( $field_name ) . '" ';
            echo 'min="' . esc_attr( $min ) . '" ';
            echo 'max="' . esc_attr( $max ) . '" ';
            echo 'step="' . esc_attr( $step ) . '" ';
            echo 'value="' . esc_attr( $min ) . '" ';
            echo 'class="bs-calc__input" ';
            echo ( $required ? 'required' : '' ) . '>';
            echo '<span class="bs-calc__unit">' . esc_html( $unit ) . '</span>';
            echo '</div>';
        }
    }

    /**
     * Render color field — toggle buttons (swatch), select dropdown, or radio toggles.
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
            echo '<div class="bs-calc__select-wrap">';
            echo '<select name="' . esc_attr( $field_name ) . '" class="bs-calc__select" ' . ( $required ? 'required' : '' ) . '>';
            echo '<option value="">' . esc_html__( 'Selecteer kleur...', 'bossier-calculator' ) . '</option>';
            foreach ( $colors as $idx => $color ) {
                $label      = $color['name'];
                $is_default = ! empty( $color['is_default'] );
                $surcharge  = isset( $color['surcharge'] ) ? floatval( $color['surcharge'] ) : 0;
                $price_type = isset( $color['price_type'] ) ? $color['price_type'] : 'fixed';

                if ( ! $is_default && $surcharge > 0 ) {
                    if ( 'percentage' === $price_type ) {
                        $label .= ' (+' . $surcharge . '%)';
                    } else {
                        $label .= ' (+' . wp_kses_post( wc_price( $surcharge ) ) . ')';
                    }
                }
                $selected = ( $default_idx === $idx ) ? ' selected' : '';
                echo '<option value="' . esc_attr( $idx ) . '"' . $selected . '>' . wp_kses_post( $label ) . '</option>';
            }
            echo '</select>';
            echo '<span class="bs-calc__select-arrow">&#9660;</span>';
            echo '</div>';
        } else {
            // Swatch and radio both render as toggle buttons
            echo '<div class="bs-calc__toggles">';
            foreach ( $colors as $idx => $color ) {
                $is_default = ! empty( $color['is_default'] );
                $surcharge  = isset( $color['surcharge'] ) ? floatval( $color['surcharge'] ) : 0;
                $price_type = isset( $color['price_type'] ) ? $color['price_type'] : 'fixed';
                $active     = ( $default_idx === $idx ) ? ' bs-calc__toggle--active' : '';

                $btn_label = esc_html( $color['name'] );
                if ( ! $is_default && $surcharge > 0 ) {
                    if ( 'percentage' === $price_type ) {
                        $btn_label .= ' <span class="bs-calc__surcharge">(+' . esc_html( $surcharge ) . '%)</span>';
                    } else {
                        $btn_label .= ' <span class="bs-calc__surcharge">(+' . wp_kses_post( wc_price( $surcharge ) ) . ')</span>';
                    }
                }

                echo '<button type="button" class="bs-calc__toggle' . esc_attr( $active ) . '" data-value="' . esc_attr( $idx ) . '">';
                echo $btn_label;
                echo '</button>';
            }
            echo '</div>';
            // Hidden input holds selected value
            $default_val = ( null !== $default_idx ) ? $default_idx : '';
            echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $default_val ) . '" class="bs-calc__toggle-value">';
        }
    }

    /**
     * Render mitre angle field — supports multiple groups with images.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_angle_field( $field_id, $field, $field_name ) {
        $input_type = isset( $field['input_type'] ) ? $field['input_type'] : 'dropdown';
        $required   = ! empty( $field['required'] );

        // Check for new groups structure
        $mitre_groups = array();
        if ( isset( $field['mitre_groups'] ) && ! empty( $field['mitre_groups'] ) ) {
            $mitre_groups = $field['mitre_groups'];
        } elseif ( isset( $field['angles'] ) && ! empty( $field['angles'] ) ) {
            // Backward compatibility: convert old structure to groups
            $mitre_groups = array(
                array(
                    'id'      => 'group_0',
                    'label'   => '',
                    'default' => isset( $field['default_angle'] ) ? (int) $field['default_angle'] : 0,
                    'angles'  => $field['angles'],
                ),
            );
        }

        if ( empty( $mitre_groups ) ) {
            return;
        }

        echo '<div class="bs-calc__mitre-groups">';

        foreach ( $mitre_groups as $group_idx => $group ) {
            $group_id      = isset( $group['id'] ) ? $group['id'] : 'group_' . $group_idx;
            $group_label   = isset( $group['label'] ) ? $group['label'] : '';
            $group_angles  = isset( $group['angles'] ) ? $group['angles'] : array();
            $group_default = isset( $group['default'] ) ? (int) $group['default'] : 0;

            if ( empty( $group_angles ) ) {
                continue;
            }

            $angle_keys  = array_keys( $group_angles );
            $default_key = isset( $angle_keys[ $group_default ] ) ? $angle_keys[ $group_default ] : reset( $angle_keys );

            $default_angle = isset( $group_angles[ $default_key ] ) ? $group_angles[ $default_key ] : array();
            $default_label = isset( $default_angle['label'] ) ? $default_angle['label'] : '';
            $default_image = isset( $default_angle['image'] ) ? $default_angle['image'] : '';

            $group_field_name = $field_name . '[' . $group_id . ']';

            // Check if any angle has an image
            $has_images = false;
            foreach ( $group_angles as $angle ) {
                if ( ! empty( $angle['image'] ) ) {
                    $has_images = true;
                    break;
                }
            }

            $group_classes = 'bs-calc__mitre-group';
            $group_attrs   = 'data-group-id="' . esc_attr( $group_id ) . '" data-group-index="' . esc_attr( $group_idx ) . '"';
            echo '<div class="' . esc_attr( $group_classes ) . '" ' . $group_attrs . '>';

            // Group label
            if ( ! empty( $group_label ) ) {
                echo '<label class="bs-calc__mitre-group-label">' . esc_html( $group_label ) . '</label>';
            }

            if ( $has_images ) {
                // Custom image dropdown
                echo '<div class="bs-calc__image-dropdown">';

                echo '<input type="hidden" name="' . esc_attr( $group_field_name ) . '" value="' . esc_attr( $default_key ) . '" class="bs-calc__image-dropdown-value">';

                echo '<div class="bs-calc__image-dropdown-selected">';
                echo '<span class="bs-calc__image-dropdown-text">';
                if ( ! empty( $default_image ) ) {
                    echo '<img src="' . esc_url( $default_image ) . '" alt="" class="bs-calc__mitre-thumb">';
                }
                echo esc_html( $default_label );
                echo '</span>';
                echo '<span class="bs-calc__image-dropdown-arrow">&#9660;</span>';
                echo '</div>';

                echo '<div class="bs-calc__image-dropdown-options">';
                foreach ( $group_angles as $idx => $angle ) {
                    $label       = isset( $angle['label'] ) ? $angle['label'] : '';
                    $image       = isset( $angle['image'] ) ? $angle['image'] : '';
                    $surcharge   = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
                    $is_no_mitre = ! empty( $angle['is_no_mitre'] );
                    $is_default  = ( $idx == $default_key );

                    $display_label = $label;
                    if ( $surcharge > 0 ) {
                        $display_label .= ' (+' . strip_tags( wc_price( $surcharge ) ) . ')';
                    }

                    $option_attrs = 'class="bs-calc__image-dropdown-option' . ( $is_default ? ' selected' : '' ) . '"';
                    $option_attrs .= ' data-value="' . esc_attr( $idx ) . '"';
                    $option_attrs .= ' data-surcharge="' . esc_attr( $surcharge ) . '"';
                    if ( $is_no_mitre ) {
                        $option_attrs .= ' data-is-no-mitre="1"';
                    }

                    echo '<div ' . $option_attrs . '>';
                    if ( ! empty( $image ) ) {
                        echo '<img src="' . esc_url( $image ) . '" alt="" class="bs-calc__dropdown-option-image bs-calc__mitre-thumb">';
                    }
                    echo '<span class="bs-calc__dropdown-option-label">' . esc_html( $display_label ) . '</span>';
                    echo '</div>';
                }
                echo '</div>'; // .bs-calc__image-dropdown-options
                echo '</div>'; // .bs-calc__image-dropdown
            } else {
                // Regular dropdown
                echo '<div class="bs-calc__select-wrap">';
                echo '<select name="' . esc_attr( $group_field_name ) . '" class="bs-calc__select bs-calc__mitre-select" data-group-id="' . esc_attr( $group_id ) . '" ' . ( $required ? 'required' : '' ) . '>';

                foreach ( $group_angles as $idx => $angle ) {
                    $label       = isset( $angle['label'] ) ? $angle['label'] : '';
                    $surcharge   = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
                    $is_no_mitre = ! empty( $angle['is_no_mitre'] );
                    $is_default  = ( $idx == $default_key );

                    $display_label = $label;
                    if ( $surcharge > 0 ) {
                        $display_label .= ' (+' . strip_tags( wc_price( $surcharge ) ) . ')';
                    }

                    $option_attrs = 'value="' . esc_attr( $idx ) . '"';
                    if ( $is_default ) {
                        $option_attrs .= ' selected';
                    }
                    $option_attrs .= ' data-surcharge="' . esc_attr( $surcharge ) . '"';
                    if ( $is_no_mitre ) {
                        $option_attrs .= ' data-is-no-mitre="1"';
                    }

                    echo '<option ' . $option_attrs . '>' . esc_html( $display_label ) . '</option>';
                }

                echo '</select>';
                echo '<span class="bs-calc__select-arrow">&#9660;</span>';
                echo '</div>';
            }

            // Hidden field to store group label for cart/order
            echo '<input type="hidden" name="' . esc_attr( $field_name ) . '_labels[' . esc_attr( $group_id ) . ']" value="' . esc_attr( $group_label ) . '">';

            echo '</div>'; // .bs-calc__mitre-group
        }

        echo '</div>'; // .bs-calc__mitre-groups
    }

    /**
     * Render quantity field — compact +/- buttons with number input.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_quantity_field( $field_id, $field, $field_name ) {
        $min      = isset( $field['min_qty'] ) ? $field['min_qty'] : 1;
        $max      = isset( $field['max_qty'] ) ? $field['max_qty'] : 100;
        $step     = isset( $field['step_qty'] ) ? $field['step_qty'] : 1;

        echo '<div class="bs-calc__qty">';
        echo '<button type="button" class="bs-calc__qty-btn" data-action="minus">&minus;</button>';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '" ';
        echo 'min="' . esc_attr( $min ) . '" ';
        echo 'max="' . esc_attr( $max ) . '" ';
        echo 'step="' . esc_attr( $step ) . '" ';
        echo 'value="' . esc_attr( $min ) . '" ';
        echo 'class="bs-calc__qty-val">';
        echo '<button type="button" class="bs-calc__qty-btn" data-action="plus">+</button>';
        echo '</div>';
    }

    /**
     * Render custom field — dropdown, toggle buttons, or checkboxes.
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
            echo '<div class="bs-calc__select-wrap">';
            echo '<select name="' . esc_attr( $field_name ) . '" class="bs-calc__select" ' . ( $required ? 'required' : '' ) . '>';
            echo '<option value="">' . esc_html__( 'Selecteer...', 'bossier-calculator' ) . '</option>';
            foreach ( $options as $idx => $option ) {
                $label = $option['label'];
                if ( $option['surcharge'] > 0 ) {
                    $label .= ' (+' . wc_price( $option['surcharge'] ) . ')';
                }
                echo '<option value="' . esc_attr( $idx ) . '">' . wp_kses_post( $label ) . '</option>';
            }
            echo '</select>';
            echo '<span class="bs-calc__select-arrow">&#9660;</span>';
            echo '</div>';
        } elseif ( 'checkbox' === $input_type ) {
            echo '<div class="bs-calc__checkbox-group">';
            foreach ( $options as $idx => $option ) {
                echo '<label class="bs-calc__checkbox-label">';
                echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '[]" value="' . esc_attr( $idx ) . '">';
                echo '<span>' . esc_html( $option['label'] ) . '</span>';
                if ( $option['surcharge'] > 0 ) {
                    echo '<span class="bs-calc__surcharge">(+' . wp_kses_post( wc_price( $option['surcharge'] ) ) . ')</span>';
                }
                // Inline text input for options that have has_text_input enabled
                if ( ! empty( $option['has_text_input'] ) ) {
                    $placeholder = ! empty( $option['text_placeholder'] ) ? $option['text_placeholder'] : '';
                    echo '<input type="text" name="' . esc_attr( $field_name ) . '_text_' . esc_attr( $idx ) . '" ';
                    echo 'class="bs-calc__input bs-calc__option-text" ';
                    echo 'placeholder="' . esc_attr( $placeholder ) . '" ';
                    echo 'style="display:none; margin-top:6px;" ';
                    echo 'disabled>';
                }
                echo '</label>';
            }
            echo '</div>';
        } else {
            // Radio as toggle buttons
            echo '<div class="bs-calc__toggles">';
            foreach ( $options as $idx => $option ) {
                $btn_label = esc_html( $option['label'] );
                if ( $option['surcharge'] > 0 ) {
                    $btn_label .= ' <span class="bs-calc__surcharge">(+' . wp_kses_post( wc_price( $option['surcharge'] ) ) . ')</span>';
                }
                echo '<button type="button" class="bs-calc__toggle" data-value="' . esc_attr( $idx ) . '">';
                echo $btn_label;
                echo '</button>';
            }
            echo '</div>';
            echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="" class="bs-calc__toggle-value">';
        }
    }

    /**
     * Render dimension field — number input with unit suffix and range bar.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_dimension_field( $field_id, $field, $field_name ) {
        $min_value     = isset( $field['min_value'] ) ? $field['min_value'] : 100;
        $max_value     = isset( $field['max_value'] ) ? $field['max_value'] : 5000;
        $default_value = isset( $field['default_value'] ) && '' !== $field['default_value'] ? $field['default_value'] : $min_value;
        $step_size     = isset( $field['step_size'] ) ? $field['step_size'] : 1;
        $unit_type     = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
        $required      = ! empty( $field['required'] );

        echo '<div class="bs-calc__input-wrap">';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '" ';
        echo 'min="' . esc_attr( $min_value ) . '" ';
        echo 'max="' . esc_attr( $max_value ) . '" ';
        echo 'step="' . esc_attr( $step_size ) . '" ';
        echo 'value="' . esc_attr( $default_value ) . '" ';
        echo 'class="bs-calc__input bs-calc__dimension" ';
        echo 'data-field-type="dimension" ';
        echo 'data-price-per-mm="' . esc_attr( isset( $field['price_per_mm'] ) ? $field['price_per_mm'] : 0 ) . '" ';
        echo 'data-threshold="' . esc_attr( isset( $field['threshold'] ) ? $field['threshold'] : 0 ) . '" ';
        echo 'data-weight-per-mm="' . esc_attr( isset( $field['weight_per_mm'] ) ? $field['weight_per_mm'] : 0 ) . '" ';
        echo ( $required ? 'required' : '' ) . '>';
        echo '<span class="bs-calc__unit">' . esc_html( $unit_type ) . '</span>';
        echo '</div>';
        echo '<div class="bs-calc__range">';
        echo '<span>Min: ' . esc_html( number_format( $min_value, 0, ',', '.' ) ) . '</span>';
        echo '<span>Max: ' . esc_html( number_format( $max_value, 0, ',', '.' ) ) . '</span>';
        echo '</div>';
        echo '<div class="bs-calc__dimension-error"></div>';
    }

    /**
     * Render text field — simple text input.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_text_field( $field_id, $field, $field_name ) {
        $placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $max_chars   = isset( $field['max_chars'] ) ? $field['max_chars'] : 50;
        $required    = ! empty( $field['required'] );

        echo '<input type="text" name="' . esc_attr( $field_name ) . '" ';
        echo 'class="bs-calc__input bs-calc__text" ';
        echo 'placeholder="' . esc_attr( $placeholder ) . '" ';
        echo 'maxlength="' . esc_attr( $max_chars ) . '" ';
        echo ( $required ? 'required' : '' ) . '>';
    }

    /**
     * Render brievenbus (mailbox) field — nested yes/no toggles with text inputs.
     *
     * @param string $field_id   Field identifier.
     * @param array  $field      Field configuration.
     * @param string $field_name Form field name.
     */
    private static function render_brievenbus_field( $field_id, $field, $field_name ) {
        $main_label       = isset( $field['main_label'] ) ? $field['main_label'] : __( 'Huisnummer', 'bossier-calculator' );
        $main_surcharge   = isset( $field['main_surcharge'] ) ? floatval( $field['main_surcharge'] ) : 0;
        $main_placeholder = isset( $field['main_placeholder'] ) ? $field['main_placeholder'] : __( 'Voer huisnummer in', 'bossier-calculator' );
        $sub_label        = isset( $field['sub_label'] ) ? $field['sub_label'] : __( 'Toevoeging', 'bossier-calculator' );
        $sub_surcharge    = isset( $field['sub_surcharge'] ) ? floatval( $field['sub_surcharge'] ) : 0;
        $sub_placeholder  = isset( $field['sub_placeholder'] ) ? $field['sub_placeholder'] : __( 'Voer toevoeging in', 'bossier-calculator' );

        $currency = get_woocommerce_currency_symbol();

        // Hidden field to store main yes/no value
        echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="nee" class="bs-calc__brievenbus-val">';

        // Main question: Huisnummer Ja/Nee
        echo '<div class="bs-calc__brievenbus">';

        echo '<div class="bs-calc__brievenbus-question" data-level="main">';
        echo '<span class="bs-calc__brievenbus-qlabel">' . esc_html( $main_label ) . '</span>';
        if ( $main_surcharge > 0 ) {
            echo ' <span class="bs-calc__brievenbus-cost">+' . esc_html( $currency ) . ' ' . esc_html( number_format( $main_surcharge, 2, ',', '.' ) ) . '</span>';
        }
        echo '<div class="bs-calc__toggles bs-calc__brievenbus-toggles" data-target="main">';
        echo '<button type="button" class="bs-calc__toggle bs-calc__brievenbus-btn" data-answer="ja">' . esc_html__( 'Ja', 'bossier-calculator' ) . '</button>';
        echo '<button type="button" class="bs-calc__toggle bs-calc__toggle--active bs-calc__brievenbus-btn" data-answer="nee">' . esc_html__( 'Nee', 'bossier-calculator' ) . '</button>';
        echo '</div>';
        echo '</div>';

        // Main text input (hidden by default)
        echo '<div class="bs-calc__brievenbus-detail bs-calc__brievenbus-detail--main" style="display:none;">';
        echo '<input type="text" name="' . esc_attr( $field_name ) . '_main_text" ';
        echo 'class="bs-calc__input bs-calc__brievenbus-text" ';
        echo 'placeholder="' . esc_attr( $main_placeholder ) . '">';

        // Sub question: Toevoeging Ja/Nee (inside main detail)
        echo '<div class="bs-calc__brievenbus-question" data-level="sub">';
        echo '<span class="bs-calc__brievenbus-qlabel">' . esc_html( $sub_label ) . '</span>';
        if ( $sub_surcharge > 0 ) {
            echo ' <span class="bs-calc__brievenbus-cost">+' . esc_html( $currency ) . ' ' . esc_html( number_format( $sub_surcharge, 2, ',', '.' ) ) . '</span>';
        }
        echo '<div class="bs-calc__toggles bs-calc__brievenbus-toggles" data-target="sub">';
        echo '<button type="button" class="bs-calc__toggle bs-calc__brievenbus-btn" data-answer="ja">' . esc_html__( 'Ja', 'bossier-calculator' ) . '</button>';
        echo '<button type="button" class="bs-calc__toggle bs-calc__toggle--active bs-calc__brievenbus-btn" data-answer="nee">' . esc_html__( 'Nee', 'bossier-calculator' ) . '</button>';
        echo '</div>';
        echo '</div>';

        // Sub text input (hidden by default)
        echo '<div class="bs-calc__brievenbus-detail bs-calc__brievenbus-detail--sub" style="display:none;">';
        echo '<input type="text" name="' . esc_attr( $field_name ) . '_sub_text" ';
        echo 'class="bs-calc__input bs-calc__brievenbus-text" ';
        echo 'placeholder="' . esc_attr( $sub_placeholder ) . '">';
        echo '</div>'; // .bs-calc__brievenbus-detail--sub

        echo '</div>'; // .bs-calc__brievenbus-detail--main

        echo '</div>'; // .bs-calc__brievenbus
    }
}
