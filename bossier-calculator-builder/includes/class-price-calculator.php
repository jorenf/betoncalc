<?php
/**
 * Price Calculator class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Price_Calculator class - Handles price and weight calculations.
 */
class Price_Calculator {

    /**
     * Calculator instance.
     *
     * @var Calculator
     */
    private $calculator;

    /**
     * Calculated price.
     *
     * @var float
     */
    private $price = 0;

    /**
     * Calculated weight.
     *
     * @var float
     */
    private $weight = 0;

    /**
     * Calculation breakdown.
     *
     * @var array
     */
    private $breakdown = array();

    /**
     * User selections.
     *
     * @var array
     */
    private $selections = array();

    /**
     * Product ID for getting base price/weight.
     *
     * @var int
     */
    private $product_id = 0;

    /**
     * Raw field values for external plugins.
     *
     * @var array
     */
    private $raw_values = array();

    /**
     * Constructor.
     *
     * @param Calculator $calculator Calculator instance.
     */
    public function __construct( Calculator $calculator ) {
        $this->calculator = $calculator;
    }

    /**
     * Calculate price and weight based on user selections.
     *
     * @param array $selections User field selections.
     * @param int   $product_id Optional product ID to include product base price/weight.
     * @return array Calculation results.
     */
    public function calculate( $selections, $product_id = 0 ) {
        $this->selections = $selections;
        $this->product_id = $product_id;
        $this->price      = 0;
        $this->weight     = 0;
        $this->breakdown  = array();
        $this->raw_values = array();

        $settings = $this->calculator->get_settings();
        $fields   = $this->calculator->get_enabled_fields();

        // Start with WooCommerce product base price and weight if product_id is provided
        $product_base_price  = 0;
        $product_base_weight = 0;

        if ( $product_id > 0 ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $product_base_price  = floatval( $product->get_price() );
                $product_base_weight = floatval( $product->get_weight() );

                if ( $product_base_price > 0 ) {
                    $this->price = $product_base_price;
                    $this->breakdown[] = array(
                        'label'  => __( 'Product base price', 'bossier-calculator' ),
                        'price'  => $product_base_price,
                        'weight' => 0,
                        'type'   => 'product_base',
                    );
                }

                if ( $product_base_weight > 0 ) {
                    $this->weight = $product_base_weight;
                    $this->breakdown[] = array(
                        'label'  => __( 'Product base weight', 'bossier-calculator' ),
                        'price'  => 0,
                        'weight' => $product_base_weight,
                        'type'   => 'product_base',
                    );
                }

                // Store raw values
                $this->raw_values['product_base_price']  = $product_base_price;
                $this->raw_values['product_base_weight'] = $product_base_weight;
            }
        }

        // Add calculator base price and weight
        $calc_base_price  = floatval( $settings['base_price'] );
        $calc_base_weight = floatval( $settings['base_weight'] );

        if ( $calc_base_price > 0 ) {
            $this->price += $calc_base_price;
            $this->breakdown[] = array(
                'label'  => __( 'Calculator base price', 'bossier-calculator' ),
                'price'  => $calc_base_price,
                'weight' => 0,
                'type'   => 'calculator_base',
            );
        }

        if ( $calc_base_weight > 0 ) {
            $this->weight += $calc_base_weight;
            $this->breakdown[] = array(
                'label'  => __( 'Calculator base weight', 'bossier-calculator' ),
                'price'  => 0,
                'weight' => $calc_base_weight,
                'type'   => 'calculator_base',
            );
        }

        // Store raw values
        $this->raw_values['calculator_base_price']  = $calc_base_price;
        $this->raw_values['calculator_base_weight'] = $calc_base_weight;

        // Process each field
        foreach ( $fields as $field_id => $field ) {
            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            $this->calculate_field( $field_id, $field, $selections[ $field_id ] );
        }

        // Apply rounding
        $price_decimals  = isset( $settings['price_decimals'] ) ? $settings['price_decimals'] : 2;
        $weight_decimals = isset( $settings['weight_decimals'] ) ? $settings['weight_decimals'] : 3;

        $this->price  = round( $this->price, $price_decimals );
        $this->weight = round( $this->weight, $weight_decimals );

        return array(
            'price'      => $this->price,
            'weight'     => $this->weight,
            'breakdown'  => $this->breakdown,
            'raw_values' => $this->raw_values,
            'formatted'  => array(
                'price'  => wc_price( $this->price ),
                'weight' => $this->format_weight( $this->weight ),
            ),
        );
    }

    /**
     * Calculate price/weight contribution from a single field.
     *
     * @param string $field_id  Field identifier.
     * @param array  $field     Field configuration.
     * @param mixed  $selection User selection value.
     */
    private function calculate_field( $field_id, $field, $selection ) {
        $field_type = isset( $field['type'] ) ? $field['type'] : 'custom';

        switch ( $field_type ) {
            case 'length':
                $this->calculate_length_field( $field, $selection );
                break;

            case 'color':
                $this->calculate_color_field( $field, $selection );
                break;

            case 'mitre_angle':
                $this->calculate_angle_field( $field, $selection );
                break;

            case 'custom':
                $this->calculate_custom_field( $field, $selection );
                break;

            // Quantity is handled separately during cart total calculation
        }
    }

    /**
     * Calculate length field contribution.
     *
     * @param array $field     Field configuration.
     * @param mixed $selection User selection (length value or option index).
     */
    private function calculate_length_field( $field, $selection ) {
        $length_value  = 0;
        $price_add     = 0;
        $weight_add    = 0;
        $display_value = '';

        $mode = isset( $field['length_mode'] ) ? $field['length_mode'] : 'free';

        if ( 'fixed' === $mode && ! empty( $field['fixed_options'] ) ) {
            // Fixed options mode
            $selection_index = intval( $selection );
            if ( isset( $field['fixed_options'][ $selection_index ] ) ) {
                $option       = $field['fixed_options'][ $selection_index ];
                $length_value = floatval( $option['value'] );
                $price_add    = floatval( $option['price'] );
                $weight_add   = floatval( $option['weight'] );
                $display_value = ! empty( $option['label'] ) ? $option['label'] : $length_value . ' ' . $field['unit_type'];
            }
        } else {
            // Free input mode
            $length_value = floatval( $selection );

            // Clamp to min/max
            if ( isset( $field['min_value'] ) && $length_value < $field['min_value'] ) {
                $length_value = $field['min_value'];
            }
            if ( isset( $field['max_value'] ) && $length_value > $field['max_value'] ) {
                $length_value = $field['max_value'];
            }

            // Calculate price based on unit type
            $price_per_unit  = isset( $field['price_per_unit'] ) ? floatval( $field['price_per_unit'] ) : 0;
            $weight_per_unit = isset( $field['weight_per_unit'] ) ? floatval( $field['weight_per_unit'] ) : 0;
            $unit_type       = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';

            // Convert to base calculation units
            $multiplier = $this->get_unit_multiplier( $unit_type );

            $price_add   = $length_value * $price_per_unit * $multiplier;
            $weight_add  = $length_value * $weight_per_unit * $multiplier;
            $display_value = $length_value . ' ' . $unit_type;
        }

        $this->price  += $price_add;
        $this->weight += $weight_add;

        $label = isset( $field['label'] ) ? $field['label'] : __( 'Length', 'bossier-calculator' );

        $unit_type = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';

        $this->breakdown[] = array(
            'label'        => $label,
            'value'        => $display_value,
            'price'        => $price_add,
            'weight'       => $weight_add,
            'length_value' => $length_value,
            'unit_type'    => $unit_type,
            'type'         => 'length',
        );

        // Store raw length values for external plugins
        $this->raw_values['length']           = $length_value;
        $this->raw_values['length_unit']      = $unit_type;
        $this->raw_values['length_mm']        = $this->convert_to_mm( $length_value, $unit_type );
        $this->raw_values['length_m']         = $this->convert_to_meters( $length_value, $unit_type );
        $this->raw_values['length_price']     = $price_add;
        $this->raw_values['length_weight']    = $weight_add;
    }

    /**
     * Calculate color field contribution.
     *
     * @param array $field     Field configuration.
     * @param mixed $selection User selection (color index).
     */
    private function calculate_color_field( $field, $selection ) {
        if ( empty( $field['colors'] ) ) {
            return;
        }

        $selection_index = intval( $selection );

        if ( ! isset( $field['colors'][ $selection_index ] ) ) {
            return;
        }

        $color      = $field['colors'][ $selection_index ];
        $surcharge  = isset( $color['surcharge'] ) ? floatval( $color['surcharge'] ) : 0;
        $color_name = isset( $color['name'] ) ? $color['name'] : '';

        $this->price += $surcharge;

        $label = isset( $field['label'] ) ? $field['label'] : __( 'Color', 'bossier-calculator' );

        $this->breakdown[] = array(
            'label'  => $label,
            'value'  => $color_name,
            'price'  => $surcharge,
            'weight' => 0,
            'hex'    => isset( $color['hex'] ) ? $color['hex'] : '',
        );
    }

    /**
     * Calculate mitre angle field contribution.
     *
     * @param array $field     Field configuration.
     * @param mixed $selection User selection (angle index).
     */
    private function calculate_angle_field( $field, $selection ) {
        if ( empty( $field['angles'] ) ) {
            return;
        }

        $selection_index = intval( $selection );

        if ( ! isset( $field['angles'][ $selection_index ] ) ) {
            return;
        }

        $angle       = $field['angles'][ $selection_index ];
        $surcharge   = isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0;
        $extra_weight= isset( $angle['extra_weight'] ) ? floatval( $angle['extra_weight'] ) : 0;
        $angle_label = isset( $angle['label'] ) ? $angle['label'] : '';

        $this->price  += $surcharge;
        $this->weight += $extra_weight;

        $label = isset( $field['label'] ) ? $field['label'] : __( 'Mitre Angle', 'bossier-calculator' );

        $this->breakdown[] = array(
            'label'  => $label,
            'value'  => $angle_label,
            'price'  => $surcharge,
            'weight' => $extra_weight,
        );
    }

    /**
     * Calculate custom field contribution.
     *
     * @param array $field     Field configuration.
     * @param mixed $selection User selection (option index or array for checkboxes).
     */
    private function calculate_custom_field( $field, $selection ) {
        if ( empty( $field['custom_options'] ) ) {
            return;
        }

        $label = isset( $field['label'] ) ? $field['label'] : __( 'Option', 'bossier-calculator' );

        // Handle multiple selections (checkboxes)
        if ( is_array( $selection ) ) {
            foreach ( $selection as $option_index ) {
                $this->apply_custom_option( $field, $option_index, $label );
            }
        } else {
            $this->apply_custom_option( $field, $selection, $label );
        }
    }

    /**
     * Apply a single custom option.
     *
     * @param array  $field        Field configuration.
     * @param int    $option_index Option index.
     * @param string $label        Field label.
     */
    private function apply_custom_option( $field, $option_index, $label ) {
        $option_index = intval( $option_index );

        if ( ! isset( $field['custom_options'][ $option_index ] ) ) {
            return;
        }

        $option       = $field['custom_options'][ $option_index ];
        $surcharge    = isset( $option['surcharge'] ) ? floatval( $option['surcharge'] ) : 0;
        $extra_weight = isset( $option['extra_weight'] ) ? floatval( $option['extra_weight'] ) : 0;
        $option_label = isset( $option['label'] ) ? $option['label'] : '';

        $this->price  += $surcharge;
        $this->weight += $extra_weight;

        $this->breakdown[] = array(
            'label'  => $label,
            'value'  => $option_label,
            'price'  => $surcharge,
            'weight' => $extra_weight,
        );
    }

    /**
     * Get unit multiplier for price calculation.
     *
     * @param string $unit Unit type (mm, cm, m).
     * @return float Multiplier.
     */
    private function get_unit_multiplier( $unit ) {
        // The price_per_unit is already in the correct unit, so multiplier is 1
        // This method is here for future extensibility if conversion is needed
        return 1;
    }

    /**
     * Convert length to millimeters.
     *
     * @param float  $value Length value.
     * @param string $unit  Unit type (mm, cm, m).
     * @return float Length in mm.
     */
    private function convert_to_mm( $value, $unit ) {
        switch ( $unit ) {
            case 'm':
                return $value * 1000;
            case 'cm':
                return $value * 10;
            case 'mm':
            default:
                return $value;
        }
    }

    /**
     * Convert length to meters.
     *
     * @param float  $value Length value.
     * @param string $unit  Unit type (mm, cm, m).
     * @return float Length in meters.
     */
    private function convert_to_meters( $value, $unit ) {
        switch ( $unit ) {
            case 'm':
                return $value;
            case 'cm':
                return $value / 100;
            case 'mm':
            default:
                return $value / 1000;
        }
    }

    /**
     * Get raw values for external plugins.
     *
     * @return array
     */
    public function get_raw_values() {
        return $this->raw_values;
    }

    /**
     * Format weight with unit.
     *
     * @param float $weight Weight value.
     * @return string Formatted weight.
     */
    private function format_weight( $weight ) {
        $unit = get_option( 'woocommerce_weight_unit', 'kg' );
        return wc_format_localized_decimal( $weight ) . ' ' . $unit;
    }

    /**
     * Get calculated price.
     *
     * @return float
     */
    public function get_price() {
        return $this->price;
    }

    /**
     * Get calculated weight.
     *
     * @return float
     */
    public function get_weight() {
        return $this->weight;
    }

    /**
     * Get calculation breakdown.
     *
     * @return array
     */
    public function get_breakdown() {
        return $this->breakdown;
    }

    /**
     * Static method to calculate from POST data (for AJAX).
     *
     * @param int   $calculator_id Calculator ID.
     * @param array $selections    User selections.
     * @return array|false Calculation results or false on error.
     */
    public static function calculate_from_request( $calculator_id, $selections ) {
        $calculator = new Calculator( $calculator_id );

        if ( ! $calculator->is_valid() ) {
            return false;
        }

        $price_calc = new self( $calculator );
        return $price_calc->calculate( $selections );
    }
}
