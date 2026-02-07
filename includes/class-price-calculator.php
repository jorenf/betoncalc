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
 *
 * Pricing Logic:
 * 1. Product base price = price for minimum length (includes gray color)
 * 2. Length extra = (selected_length - min_length) * price_per_mm (if length > min_length)
 * 3. Gray price = product_base + length_extra (basis for color percentage)
 * 4. Long length surcharge = (length - threshold) * surcharge_per_mm (hidden from customer)
 * 5. Mitre surcharges (fixed amounts)
 * 6. Color surcharge (percentage of gray_price OR fixed amount)
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
     * Gray price (base for color percentage calculations).
     *
     * @var float
     */
    private $gray_price = 0;

    /**
     * Selected length in mm.
     *
     * @var float
     */
    private $length_mm = 0;

    /**
     * Long length surcharge amount (hidden from customer).
     *
     * @var float
     */
    private $long_length_surcharge = 0;

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
        $this->selections           = $selections;
        $this->product_id           = $product_id;
        $this->price                = 0;
        $this->weight               = 0;
        $this->breakdown            = array();
        $this->raw_values           = array();
        $this->gray_price           = 0;
        $this->length_mm            = 0;
        $this->long_length_surcharge = 0;

        $settings = $this->calculator->get_settings();
        $fields   = $this->calculator->get_enabled_fields();

        // Step 1: Get product base price (includes gray color for min_length)
        $product_base_price  = 0;
        $product_base_weight = 0;

        if ( $product_id > 0 ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $product_base_price  = floatval( $product->get_price() );
                $product_base_weight = floatval( $product->get_weight() );
            }
        }

        // Store raw values
        $this->raw_values['product_base_price']  = $product_base_price;
        $this->raw_values['product_base_weight'] = $product_base_weight;

        // Start with product base price
        $this->price = $product_base_price;
        $this->weight = $product_base_weight;

        $min_length = floatval( $settings['min_length'] ?? 1000 );

        if ( $product_base_price > 0 ) {
            $this->breakdown[] = array(
                'label'  => sprintf(
                    /* translators: %s: price threshold length */
                    __( 'Basisprijs (t/m %s mm)', 'bossier-calculator' ),
                    number_format_i18n( $min_length, 0 )
                ),
                'price'  => $product_base_price,
                'weight' => $product_base_weight,
                'type'   => 'product_base',
                'hidden' => false,
            );
        }

        // Step 2: Process length field first to determine length-based pricing
        $this->process_length_field( $fields, $selections, $settings );

        // Gray price = product base + length extra (for color percentage calculation)
        $this->gray_price = $this->price;
        $this->raw_values['gray_price'] = $this->gray_price;

        // Step 3: Add long length surcharge (hidden from customer)
        $this->calculate_long_length_surcharge( $settings );

        // Step 4: Process mitre angle field
        $this->process_mitre_field( $fields, $selections );

        // Step 5: Process color field (percentage based on gray_price)
        $this->process_color_field( $fields, $selections );

        // Step 6: Process any other custom fields
        $this->process_custom_fields( $fields, $selections );

        // Apply rounding
        $price_decimals  = isset( $settings['price_decimals'] ) ? intval( $settings['price_decimals'] ) : 2;
        $weight_decimals = isset( $settings['weight_decimals'] ) ? intval( $settings['weight_decimals'] ) : 3;

        $this->price  = round( $this->price, $price_decimals );
        $this->weight = round( $this->weight, $weight_decimals );

        // Store additional raw values
        $this->raw_values['final_price']           = $this->price;
        $this->raw_values['final_weight']          = $this->weight;
        $this->raw_values['long_length_surcharge'] = $this->long_length_surcharge;
        $this->raw_values['min_length']            = $min_length;

        return array(
            'price'                  => $this->price,
            'weight'                 => $this->weight,
            'breakdown'              => $this->breakdown,
            'raw_values'             => $this->raw_values,
            'gray_price'             => $this->gray_price,
            'long_length_surcharge'  => $this->long_length_surcharge,
            'formatted'              => array(
                'price'  => wc_price( $this->price ),
                'weight' => $this->format_weight( $this->weight ),
            ),
        );
    }

    /**
     * Process length field and calculate extra price above minimum length.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     * @param array $settings   Calculator settings.
     */
    private function process_length_field( $fields, $selections, $settings ) {
        $length_processed = false;

        // First, try to process length from configured fields
        foreach ( $fields as $field_id => $field ) {
            if ( 'length' !== ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            $selection = $selections[ $field_id ];
            $mode      = $field['length_mode'] ?? 'free';
            $unit_type = $field['unit_type'] ?? 'mm';

            $length_value  = 0;
            $display_value = '';

            if ( 'fixed' === $mode && ! empty( $field['fixed_options'] ) ) {
                // Fixed options mode
                $selection_index = intval( $selection );
                if ( isset( $field['fixed_options'][ $selection_index ] ) ) {
                    $option        = $field['fixed_options'][ $selection_index ];
                    $length_value  = floatval( $option['value'] );
                    $display_value = ! empty( $option['label'] ) ? $option['label'] : $length_value . ' ' . $unit_type;
                }
            } else {
                // Free input mode
                $length_value = floatval( $selection );

                // Clamp to min/max from field settings
                $field_min = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
                $field_max = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 10000;

                if ( $length_value < $field_min ) {
                    $length_value = $field_min;
                }
                if ( $length_value > $field_max ) {
                    $length_value = $field_max;
                }

                $display_value = $length_value . ' ' . $unit_type;
            }

            // Convert to mm for calculations
            $this->length_mm = $this->convert_to_mm( $length_value, $unit_type );

            // Get pricing settings
            $min_length   = floatval( $settings['min_length'] ?? 1000 );
            $price_per_mm = floatval( $settings['price_per_mm'] ?? 0 );
            $weight_per_mm = floatval( $settings['base_weight_per_mm'] ?? 0 );

            // Also check field-level price_per_unit for backwards compatibility
            if ( $price_per_mm <= 0 && isset( $field['price_per_unit'] ) ) {
                $price_per_mm = floatval( $field['price_per_unit'] );
            }
            if ( $weight_per_mm <= 0 && isset( $field['weight_per_unit'] ) ) {
                $weight_per_mm = floatval( $field['weight_per_unit'] );
            }

            $this->apply_length_pricing( $length_value, $min_length, $price_per_mm, $weight_per_mm, 'mm', $display_value );
            $length_processed = true;

            // Only process first length field
            break;
        }

        // Fallback: process core length field if no configured length field was found
        if ( ! $length_processed && isset( $selections['length'] ) ) {
            $length_value = floatval( $selections['length'] );

            // Clamp to min/max from settings
            $min_length_input = floatval( $settings['min_length_input'] ?? 100 );
            $max_length       = floatval( $settings['max_length'] ?? 5000 );

            if ( $length_value < $min_length_input ) {
                $length_value = $min_length_input;
            }
            if ( $length_value > $max_length ) {
                $length_value = $max_length;
            }

            // Core length is always in mm
            $min_length    = floatval( $settings['min_length'] ?? 1000 );
            $price_per_mm  = floatval( $settings['price_per_mm'] ?? 0 );
            $weight_per_mm = floatval( $settings['base_weight_per_mm'] ?? 0 );

            $display_value = $length_value . ' mm';

            $this->apply_length_pricing( $length_value, $min_length, $price_per_mm, $weight_per_mm, 'mm', $display_value );
        }
    }

    /**
     * Apply length-based pricing and weight calculations.
     *
     * @param float  $length_value  Length value.
     * @param float  $min_length    Minimum length (price threshold).
     * @param float  $price_per_mm  Price per mm above minimum.
     * @param float  $weight_per_mm Weight per mm.
     * @param string $unit_type     Unit type (mm, cm, m).
     * @param string $display_value Display value for breakdown.
     */
    private function apply_length_pricing( $length_value, $min_length, $price_per_mm, $weight_per_mm, $unit_type, $display_value ) {
        // Convert to mm for calculations
        $this->length_mm = $this->convert_to_mm( $length_value, $unit_type );

        // Calculate extra price for length above minimum
        $price_add  = 0;
        $weight_add = 0;

        if ( $this->length_mm > $min_length && $price_per_mm > 0 ) {
            $extra_length = $this->length_mm - $min_length;
            $price_add    = $extra_length * $price_per_mm;
        }

        // Weight is always calculated for full length
        if ( $weight_per_mm > 0 ) {
            $weight_add = $this->length_mm * $weight_per_mm;
        }

        $this->price  += $price_add;
        $this->weight += $weight_add;

        if ( $price_add > 0 ) {
            $this->breakdown[] = array(
                'label'        => sprintf(
                    /* translators: %s: extra length */
                    __( 'Extra length (%s mm above minimum)', 'bossier-calculator' ),
                    number_format_i18n( $this->length_mm - $min_length, 0 )
                ),
                'price'        => $price_add,
                'weight'       => $weight_add,
                'type'         => 'length_extra',
                'hidden'       => false,
            );
        }

        // Store raw length values
        $this->raw_values['length']             = $length_value;
        $this->raw_values['length_unit']        = $unit_type;
        $this->raw_values['length_mm']          = $this->length_mm;
        $this->raw_values['length_m']           = $this->length_mm / 1000;
        $this->raw_values['length_display']     = $display_value;
        $this->raw_values['length_extra_price'] = $price_add;
        $this->raw_values['length_weight']      = $weight_add;
    }

    /**
     * Calculate long length surcharge (hidden from customer).
     *
     * @param array $settings Calculator settings.
     */
    private function calculate_long_length_surcharge( $settings ) {
        if ( empty( $settings['enable_long_surcharge'] ) ) {
            return;
        }

        $threshold     = floatval( $settings['long_surcharge_threshold'] ?? 1500 );
        $surcharge_per_mm = floatval( $settings['long_surcharge_per_mm'] ?? 0 );

        if ( $this->length_mm <= $threshold || $surcharge_per_mm <= 0 ) {
            return;
        }

        $extra_length = $this->length_mm - $threshold;
        $surcharge    = $extra_length * $surcharge_per_mm;

        $this->long_length_surcharge = $surcharge;
        $this->price += $surcharge;

        // Add to breakdown but mark as hidden from customer
        $this->breakdown[] = array(
            'label'  => sprintf(
                /* translators: %s: threshold length */
                __( 'Long length surcharge (above %s mm)', 'bossier-calculator' ),
                number_format_i18n( $threshold, 0 )
            ),
            'price'  => $surcharge,
            'weight' => 0,
            'type'   => 'long_length_surcharge',
            'hidden' => true, // Hidden from customer, visible in admin
        );
    }

    /**
     * Process mitre angle field - supports multiple groups.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function process_mitre_field( $fields, $selections ) {
        foreach ( $fields as $field_id => $field ) {
            if ( 'mitre_angle' !== ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            $selection = $selections[ $field_id ];

            // Check for new groups structure
            if ( isset( $field['mitre_groups'] ) && is_array( $selection ) ) {
                // First pass: check if any selected angle has is_no_mitre set
                $no_mitre_label = '';
                foreach ( $field['mitre_groups'] as $group ) {
                    $group_id = isset( $group['id'] ) ? $group['id'] : '';
                    if ( ! isset( $selection[ $group_id ] ) ) {
                        continue;
                    }

                    $angle_idx    = $selection[ $group_id ];
                    $group_angles = isset( $group['angles'] ) ? $group['angles'] : array();

                    if ( isset( $group_angles[ $angle_idx ] ) ) {
                        $angle = $group_angles[ $angle_idx ];
                        if ( ! empty( $angle['is_no_mitre'] ) ) {
                            $no_mitre_label = $angle['label'] ?? '';
                            break;
                        }
                    }
                }

                // If geen hoek is selected, only show that label without angle breakdown
                if ( ! empty( $no_mitre_label ) ) {
                    $this->breakdown[] = array(
                        'label'       => $field['label'] ?? __( 'Verstekhoek', 'bossier-calculator' ),
                        'value'       => $no_mitre_label,
                        'price'       => 0,
                        'weight'      => 0,
                        'type'        => 'mitre_angle',
                        'hidden'      => false,
                        'is_no_mitre' => true,
                    );

                    $this->raw_values['mitre_labels']    = array( $no_mitre_label );
                    $this->raw_values['mitre_surcharge'] = 0;
                    $this->raw_values['is_no_mitre']     = true;
                } else {
                    // Normal processing - show all groups
                    $total_surcharge    = 0;
                    $total_extra_weight = 0;
                    $group_labels       = array();

                    foreach ( $field['mitre_groups'] as $group ) {
                        $group_id = isset( $group['id'] ) ? $group['id'] : '';

                        if ( ! isset( $selection[ $group_id ] ) ) {
                            continue;
                        }

                        $angle_idx    = $selection[ $group_id ];
                        $group_label  = isset( $group['label'] ) ? $group['label'] : '';
                        $group_angles = isset( $group['angles'] ) ? $group['angles'] : array();

                        if ( ! isset( $group_angles[ $angle_idx ] ) ) {
                            continue;
                        }

                        $angle        = $group_angles[ $angle_idx ];
                        $surcharge    = floatval( $angle['surcharge'] ?? 0 );
                        $extra_weight = floatval( $angle['extra_weight'] ?? 0 );
                        $angle_label  = $angle['label'] ?? '';

                        $total_surcharge    += $surcharge;
                        $total_extra_weight += $extra_weight;

                        // Build display label
                        if ( ! empty( $group_label ) ) {
                            $group_labels[] = $group_label . ': ' . $angle_label;
                        } else {
                            $group_labels[] = $angle_label;
                        }

                        // Add individual group to breakdown
                        $this->breakdown[] = array(
                            'label'    => ! empty( $group_label ) ? $group_label : __( 'Verstekhoek', 'bossier-calculator' ),
                            'value'    => $angle_label,
                            'price'    => $surcharge,
                            'weight'   => $extra_weight,
                            'type'     => 'mitre_angle',
                            'hidden'   => false,
                            'image'    => $angle['image'] ?? '',
                            'group_id' => $group_id,
                        );
                    }

                    $this->price  += $total_surcharge;
                    $this->weight += $total_extra_weight;

                    // Store combined raw values
                    $this->raw_values['mitre_labels']    = $group_labels;
                    $this->raw_values['mitre_surcharge'] = $total_surcharge;
                }

            } elseif ( ! empty( $field['angles'] ) ) {
                // Legacy single angles structure
                $selection_index = intval( $selection );

                if ( ! isset( $field['angles'][ $selection_index ] ) ) {
                    continue;
                }

                $angle        = $field['angles'][ $selection_index ];
                $surcharge    = floatval( $angle['surcharge'] ?? 0 );
                $extra_weight = floatval( $angle['extra_weight'] ?? 0 );
                $angle_label  = $angle['label'] ?? '';

                $label = $field['label'] ?? __( 'Mitre Angle', 'bossier-calculator' );

                // Check if this is a "geen hoek" option
                if ( ! empty( $angle['is_no_mitre'] ) ) {
                    $this->breakdown[] = array(
                        'label'       => $label,
                        'value'       => $angle_label,
                        'price'       => 0,
                        'weight'      => 0,
                        'type'        => 'mitre_angle',
                        'hidden'      => false,
                        'is_no_mitre' => true,
                    );

                    $this->raw_values['mitre_label']     = $angle_label;
                    $this->raw_values['mitre_surcharge'] = 0;
                    $this->raw_values['is_no_mitre']     = true;
                } else {
                    $this->price  += $surcharge;
                    $this->weight += $extra_weight;

                    $this->breakdown[] = array(
                        'label'  => $label,
                        'value'  => $angle_label,
                        'price'  => $surcharge,
                        'weight' => $extra_weight,
                        'type'   => 'mitre_angle',
                        'hidden' => false,
                        'image'  => $angle['image'] ?? '',
                    );

                    $this->raw_values['mitre_label']     = $angle_label;
                    $this->raw_values['mitre_surcharge'] = $surcharge;
                    $this->raw_values['mitre_image']     = $angle['image'] ?? '';
                }
            }
        }
    }

    /**
     * Process color field with percentage or fixed surcharge.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function process_color_field( $fields, $selections ) {
        foreach ( $fields as $field_id => $field ) {
            if ( 'color' !== ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            if ( empty( $field['colors'] ) ) {
                continue;
            }

            $selection_index = intval( $selections[ $field_id ] );

            if ( ! isset( $field['colors'][ $selection_index ] ) ) {
                continue;
            }

            $color      = $field['colors'][ $selection_index ];
            $color_name = $color['name'] ?? '';
            $price_type = $color['price_type'] ?? 'fixed';
            $surcharge_value = floatval( $color['surcharge'] ?? 0 );
            $is_default = ! empty( $color['is_default'] );

            // Calculate actual surcharge
            $surcharge = 0;

            if ( ! $is_default && $surcharge_value > 0 ) {
                if ( 'percentage' === $price_type ) {
                    // Percentage of gray price
                    $surcharge = ( $this->gray_price * $surcharge_value ) / 100;
                } else {
                    // Fixed amount
                    $surcharge = $surcharge_value;
                }
            }

            $this->price += $surcharge;

            $label = $field['label'] ?? __( 'Color', 'bossier-calculator' );

            // Format display value
            $display_surcharge = '';
            if ( $surcharge > 0 ) {
                if ( 'percentage' === $price_type ) {
                    $display_surcharge = sprintf( ' (+%s%%)', number_format_i18n( $surcharge_value, 0 ) );
                } else {
                    $display_surcharge = sprintf( ' (+%s)', wc_price( $surcharge_value ) );
                }
            }

            $this->breakdown[] = array(
                'label'      => $label,
                'value'      => $color_name . $display_surcharge,
                'price'      => $surcharge,
                'weight'     => 0,
                'type'       => 'color',
                'hidden'     => false,
                'hex'        => $color['hex'] ?? '',
                'is_default' => $is_default,
                'price_type' => $price_type,
            );

            $this->raw_values['color_name']      = $color_name;
            $this->raw_values['color_surcharge'] = $surcharge;
            $this->raw_values['color_hex']       = $color['hex'] ?? '';
            $this->raw_values['color_is_default'] = $is_default;
        }
    }

    /**
     * Process custom fields.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function process_custom_fields( $fields, $selections ) {
        foreach ( $fields as $field_id => $field ) {
            $field_type = $field['type'] ?? '';

            // Skip already processed field types
            if ( in_array( $field_type, array( 'length', 'color', 'mitre_angle', 'quantity' ), true ) ) {
                continue;
            }

            if ( 'custom' !== $field_type ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            if ( empty( $field['custom_options'] ) ) {
                continue;
            }

            $label = $field['label'] ?? __( 'Option', 'bossier-calculator' );
            $selection = $selections[ $field_id ];

            // Handle multiple selections (checkboxes)
            if ( is_array( $selection ) ) {
                foreach ( $selection as $option_index ) {
                    $this->apply_custom_option( $field, intval( $option_index ), $label );
                }
            } else {
                $this->apply_custom_option( $field, intval( $selection ), $label );
            }
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
        if ( ! isset( $field['custom_options'][ $option_index ] ) ) {
            return;
        }

        $option       = $field['custom_options'][ $option_index ];
        $surcharge    = floatval( $option['surcharge'] ?? 0 );
        $extra_weight = floatval( $option['extra_weight'] ?? 0 );
        $option_label = $option['label'] ?? '';

        $this->price  += $surcharge;
        $this->weight += $extra_weight;

        $this->breakdown[] = array(
            'label'  => $label,
            'value'  => $option_label,
            'price'  => $surcharge,
            'weight' => $extra_weight,
            'type'   => 'custom',
            'hidden' => false,
        );
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
     * Get raw values for external plugins.
     *
     * @return array
     */
    public function get_raw_values() {
        return $this->raw_values;
    }

    /**
     * Get gray price (basis for color percentage).
     *
     * @return float
     */
    public function get_gray_price() {
        return $this->gray_price;
    }

    /**
     * Get long length surcharge (hidden from customer).
     *
     * @return float
     */
    public function get_long_length_surcharge() {
        return $this->long_length_surcharge;
    }

    /**
     * Get breakdown filtered for customer display (hides hidden items).
     *
     * @return array
     */
    public function get_customer_breakdown() {
        return array_filter( $this->breakdown, function( $item ) {
            return empty( $item['hidden'] );
        });
    }

    /**
     * Static method to calculate from POST data (for AJAX).
     *
     * @param int   $calculator_id Calculator ID.
     * @param array $selections    User selections.
     * @param int   $product_id    Optional product ID.
     * @return array|false Calculation results or false on error.
     */
    public static function calculate_from_request( $calculator_id, $selections, $product_id = 0 ) {
        $calculator = new Calculator( $calculator_id );

        if ( ! $calculator->is_valid() ) {
            return false;
        }

        $price_calc = new self( $calculator );
        return $price_calc->calculate( $selections, $product_id );
    }
}
