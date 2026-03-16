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
 * 1. Product base price from WooCommerce
 * 2. For each dimension field: extra = max(0, value - threshold) * price_per_mm
 * 3. Gray price = product_base + SUM(dimension extras) (basis for color percentage)
 * 4. Long length surcharge = (length - threshold) * surcharge_per_mm (hidden from customer)
 * 5. Mitre surcharges (fixed amounts)
 * 6. Color surcharge (percentage of gray_price OR fixed amount)
 * 7. Custom field surcharges
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
     * Selected length in mm (first dimension with kind "length").
     *
     * @var float
     */
    private $length_mm = 0;

    /**
     * Maximum dimension value in mm across all dimension fields.
     * Used for long length surcharge calculation.
     *
     * @var float
     */
    private $max_dimension_mm = 0;

    /**
     * Long length surcharge amount (hidden from customer).
     *
     * @var float
     */
    private $long_length_surcharge = 0;

    /**
     * Non-standard length surcharge total across all dimension fields.
     *
     * @var float
     */
    private $nonstandard_surcharge = 0;

    /**
     * One-time product fee (charged once per product, not per quantity).
     *
     * @var float
     */
    private $product_fee = 0;

    /**
     * One-time product fee label.
     *
     * @var string
     */
    private $product_fee_label = '';

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
        $this->length_mm             = 0;
        $this->max_dimension_mm      = 0;
        $this->long_length_surcharge = 0;
        $this->nonstandard_surcharge = 0;
        $this->product_fee           = 0;
        $this->product_fee_label     = '';

        $settings     = $this->calculator->get_settings();
        $fields       = $this->calculator->get_enabled_fields();
        $pricing_mode = isset( $settings['pricing_mode'] ) ? $settings['pricing_mode'] : 'standard';

        $this->raw_values['pricing_mode'] = $pricing_mode;

        if ( 'dimensional' === $pricing_mode ) {
            // Dimensional pricing: price = product of all dimensions × unit price.
            // WooCommerce product base price is NOT used.
            $this->process_dimensional_pricing( $fields, $selections, $settings );
        } else {
            // Standard pricing: base price + dimension extras + surcharges.
            $this->process_standard_pricing( $fields, $selections, $settings );
        }

        // Common steps for all pricing modes:

        // Gray price = current price before surcharges (for color percentage calculation)
        $this->gray_price = $this->price;
        $this->raw_values['gray_price'] = $this->gray_price;

        // Long length surcharge only applies in standard mode
        if ( 'standard' === $pricing_mode ) {
            $this->calculate_long_length_surcharge( $settings );
        }

        // Non-standard surcharge only applies in standard mode
        if ( 'standard' === $pricing_mode ) {
            $this->calculate_nonstandard_surcharge( $settings, $fields, $selections );
        }

        // Process mitre angle field
        $this->process_mitre_field( $fields, $selections );

        // Process color field (percentage based on gray_price)
        $this->process_color_field( $fields, $selections );

        // Process any other custom fields
        $this->process_custom_fields( $fields, $selections );

        // Process brievenbus fields
        $this->process_brievenbus_fields( $fields, $selections );

        // Process one-time product fee (tracked separately, not added to item price)
        $this->process_product_fee( $settings );

        // If no weight was calculated from steps, fall back to WooCommerce product weight.
        if ( $this->weight <= 0 && $this->product_id > 0 ) {
            $product = wc_get_product( $this->product_id );
            if ( $product ) {
                $product_base_weight = floatval( $product->get_weight() );
                if ( $product_base_weight > 0 ) {
                    $this->weight = $product_base_weight;
                }
            }
        }

        // Apply rounding
        $price_decimals  = isset( $settings['price_decimals'] ) ? intval( $settings['price_decimals'] ) : 2;
        $weight_decimals = isset( $settings['weight_decimals'] ) ? intval( $settings['weight_decimals'] ) : 3;

        $this->price  = round( $this->price, $price_decimals );
        $this->weight = round( $this->weight, $weight_decimals );

        // Store additional raw values
        $this->raw_values['final_price']           = $this->price;
        $this->raw_values['final_weight']          = $this->weight;
        $this->raw_values['long_length_surcharge'] = $this->long_length_surcharge;
        $this->raw_values['nonstandard_surcharge'] = $this->nonstandard_surcharge;
        $this->raw_values['product_fee']           = $this->product_fee;
        $this->raw_values['product_fee_label']     = $this->product_fee_label;
        if ( 'standard' === $pricing_mode ) {
            $this->raw_values['min_length'] = floatval( $settings['min_length'] ?? 1000 );
        }

        return array(
            'price'                  => $this->price,
            'weight'                 => $this->weight,
            'breakdown'              => $this->breakdown,
            'raw_values'             => $this->raw_values,
            'gray_price'             => $this->gray_price,
            'long_length_surcharge'  => $this->long_length_surcharge,
            'nonstandard_surcharge'  => $this->nonstandard_surcharge,
            'product_fee'            => $this->product_fee,
            'product_fee_label'      => $this->product_fee_label,
            'formatted'              => array(
                'price'  => wc_price( $this->price ),
                'weight' => $this->format_weight( $this->weight ),
            ),
        );
    }

    /**
     * Process standard (length-based) pricing.
     * This is the original pricing logic, extracted to its own method.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     * @param array $settings   Calculator settings.
     */
    private function process_standard_pricing( $fields, $selections, $settings ) {
        // Get product base price (includes gray color for min_length)
        $product_base_price  = 0;
        $product_base_weight = 0;

        if ( $this->product_id > 0 ) {
            $product = wc_get_product( $this->product_id );
            if ( $product ) {
                // Always use the VAT-inclusive price as the base.
                // WooCommerce may store prices excluding VAT (woocommerce_prices_include_tax = 'no').
                // Using wc_get_price_including_tax() normalises both cases so that
                // calculated_price is always incl. BTW — matching the cart display and
                // the get_exclusive_price() conversion used in set_cart_item_price().
                $product_base_price  = floatval( wc_get_price_including_tax( $product ) );
                $product_base_weight = floatval( $product->get_weight() );
            }
        }

        // Store raw values
        $this->raw_values['product_base_price']  = $product_base_price;
        $this->raw_values['product_base_weight'] = $product_base_weight;

        // Extra base price from calculator settings (added on top of WooCommerce product price)
        $base_price = floatval( $settings['base_price'] ?? 0 );

        // Start with product base price + extra base price
        $this->price = $product_base_price + $base_price;

        // Weight starts at 0 — calculated from dimension fields
        $this->weight = 0;

        if ( $product_base_price > 0 ) {
            $this->breakdown[] = array(
                'label'  => __( 'Basisprijs', 'bossier-calculator' ),
                'price'  => $product_base_price,
                'weight' => 0,
                'type'   => 'product_base',
                'hidden' => false,
            );
        }

        if ( $base_price > 0 ) {
            $this->breakdown[] = array(
                'label'  => __( 'Extra Basisprijs', 'bossier-calculator' ),
                'price'  => $base_price,
                'weight' => 0,
                'type'   => 'base_price',
                'hidden' => false,
            );
        }

        // Process dimension fields (replaces old length field processing)
        $this->process_dimension_fields( $fields, $selections );
    }

    /**
     * Process dimensional pricing (Volume-based pricing).
     *
     * CALCULATION FLOW:
     * =================
     * Step 1: Collect ALL dimension fields in order
     *         - All dimension/length fields are collected
     *         - Order is preserved for consistent calculation
     *
     * Step 2: Calculate volume in mm³ using ONLY the first 3 dimensions
     *         - Volume = Dimension1 × Dimension2 × Dimension3 (all converted to mm)
     *         - Dimensions beyond the 3rd are NOT included in price calculation
     *         - Extra dimensions ARE stored for display in cart/invoice
     *
     * Step 3: Apply pricing factor (price per mm³)
     *         - Price = Volume × PricingFactor (dimensional_unit_price setting)
     *         - Weight = Volume × WeightFactor (dimensional_weight_per_unit setting)
     *
     * IMPORTANT:
     * - WooCommerce product base price is NOT used in dimensional mode
     * - The dimensional_unit_price is the cost per mm³ (pricing factor)
     * - Only the FIRST 3 dimensions contribute to volume/price calculation
     * - Extra dimensions (4th and beyond) are shown in cart/invoice but NOT in price
     *
     * EXAMPLE:
     * - Dimension A: 100 mm (used in calculation)
     * - Dimension B: 100 mm (used in calculation)
     * - Dimension C: 100 mm (used in calculation)
     * - Dimension D: 50 mm (NOT used in calculation, but shown in cart/invoice)
     * - PricingFactor = 1 (€ per mm³)
     * - Volume = 100 × 100 × 100 = 1,000,000 mm³
     * - Price = 1,000,000 × 1 = €1,000,000
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     * @param array $settings   Calculator settings.
     */
    private function process_dimensional_pricing( $fields, $selections, $settings ) {
        // -------------------------------------------------------------------------
        // STEP 1: Get pricing factor and settings (NOT hardcoded)
        // -------------------------------------------------------------------------
        $pricing_factor           = floatval( $settings['dimensional_unit_price'] ?? 0 );
        $weight_per_unit          = floatval( $settings['dimensional_weight_per_unit'] ?? 0 );
        $base_price               = floatval( $settings['base_price'] ?? 0 );
        $enable_weight_calculation = ! isset( $settings['enable_weight_calculation'] ) || ! empty( $settings['enable_weight_calculation'] );

        // -------------------------------------------------------------------------
        // STEP 2: Collect ALL dimension fields in order
        // We collect all dimensions but only use the first 3 for pricing
        // -------------------------------------------------------------------------
        $all_dimensions       = array();
        $dimension_labels     = array();

        foreach ( $fields as $field_id => $field ) {
            $field_type = $field['type'] ?? '';

            // Only process 'dimension' type fields for volume calculation
            // Legacy 'length' fields are also included
            if ( 'dimension' !== $field_type && 'length' !== $field_type ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            // Skip fields hidden by show_when condition
            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
                continue;
            }

            // Extract the dimension value
            $selection = $selections[ $field_id ];
            $mode      = $field['length_mode'] ?? 'free';
            $unit_type = $field['unit_type'] ?? 'mm';
            $label     = $field['label'] ?? $field_id;
            $dimension_kind = $field['dimension_kind'] ?? 'length';

            $dim_value     = 0;
            $display_value = '';

            if ( 'fixed' === $mode && ! empty( $field['fixed_options'] ) ) {
                $selection_index = intval( $selection );
                if ( isset( $field['fixed_options'][ $selection_index ] ) ) {
                    $option        = $field['fixed_options'][ $selection_index ];
                    $dim_value     = floatval( $option['value'] );
                    $display_value = ! empty( $option['label'] ) ? $option['label'] : $dim_value . ' ' . $unit_type;
                }
            } else {
                $dim_value = floatval( $selection );

                // Clamp to min/max from field settings
                $field_min = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
                $field_max = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 10000;

                if ( $dim_value < $field_min ) {
                    $dim_value = $field_min;
                }
                if ( $dim_value > $field_max ) {
                    $dim_value = $field_max;
                }

                $display_value = $dim_value . ' ' . $unit_type;
            }

            // Convert to mm for consistent volume calculation
            $dim_mm = $this->convert_to_mm( $dim_value, $unit_type );

            $all_dimensions[] = array(
                'field_id'       => $field_id,
                'dimension_kind' => $dimension_kind,
                'label'          => $label,
                'value'          => $dim_value,
                'value_mm'       => $dim_mm,
                'unit'           => $unit_type,
                'display'        => $display_value,
            );

            $dimension_labels[] = $label . ': ' . $display_value;
        }

        // -------------------------------------------------------------------------
        // STEP 3: Calculate volume in mm³ using ONLY the first 3 dimensions
        // -------------------------------------------------------------------------
        $volume_dimensions = array_slice( $all_dimensions, 0, 3 );
        $extra_dimensions  = array_slice( $all_dimensions, 3 );

        $volume_mm3 = 1;
        foreach ( $volume_dimensions as $dim ) {
            $volume_mm3 *= $dim['value_mm'];
        }

        // If no dimensions found, volume is 0
        if ( empty( $volume_dimensions ) ) {
            $volume_mm3 = 0;
        }

        // -------------------------------------------------------------------------
        // STEP 4: Apply pricing factor to calculate price
        // Price = Volume × PricingFactor
        // Weight = Volume × WeightFactor (optional, configurable)
        // IMPORTANT: OneTimeCost is NOT included in unit price anymore!
        // It will be added as a separate WooCommerce fee to prevent multiplication by quantity.
        // -------------------------------------------------------------------------
        $dimensional_price = $volume_mm3 * $pricing_factor;
        $calculated_price  = $dimensional_price + $base_price; // base_price added to unit price

        // Weight calculation is optional (configurable)
        $calculated_weight = 0;
        if ( $enable_weight_calculation ) {
            $calculated_weight = $volume_mm3 * $weight_per_unit;
        }

        $this->price  = $calculated_price;
        $this->weight = $calculated_weight;

        // -------------------------------------------------------------------------
        // STEP 5: Build breakdown for display
        // -------------------------------------------------------------------------
        // Add each dimension to breakdown (including those used in calculation)
        foreach ( $volume_dimensions as $index => $dim ) {
            $this->breakdown[] = array(
                'label'            => $dim['label'],
                'value'            => $dim['display'],
                'price'            => 0,
                'weight'           => 0,
                'type'             => 'dimension',
                'hidden'           => false,
                'used_in_pricing'  => true,
                'dimension_index'  => $index + 1,
            );
        }

        // Add extra dimensions (4th and beyond) - NOT used in pricing but shown in cart/invoice
        foreach ( $extra_dimensions as $index => $dim ) {
            $this->breakdown[] = array(
                'label'            => $dim['label'],
                'value'            => $dim['display'],
                'price'            => 0,
                'weight'           => 0,
                'type'             => 'dimension_extra',
                'hidden'           => false,
                'used_in_pricing'  => false,
                'dimension_index'  => count( $volume_dimensions ) + $index + 1,
            );
        }

        // Add volume and calculated price to breakdown
        if ( $dimensional_price > 0 || count( $volume_dimensions ) > 0 ) {
            $dim_display_parts = array();
            foreach ( $volume_dimensions as $dim ) {
                $dim_display_parts[] = number_format_i18n( $dim['value_mm'], 0 );
            }

            // Show volume calculation
            $this->breakdown[] = array(
                'label'  => __( 'Volume (mm³)', 'bossier-calculator' ),
                'value'  => implode( ' × ', $dim_display_parts ) . ' = ' . number_format_i18n( $volume_mm3, 0 ) . ' mm³',
                'price'  => 0,
                'weight' => 0,
                'type'   => 'volume_calculation',
                'hidden' => false,
            );

            // Show dimensional price (volume × price per mm³)
            $this->breakdown[] = array(
                'label'  => __( 'Prijs (volume × prijs per mm³)', 'bossier-calculator' ),
                'value'  => number_format_i18n( $volume_mm3, 0 ) . ' mm³ × ' . number_format_i18n( $pricing_factor, 6 ),
                'price'  => $dimensional_price,
                'weight' => $calculated_weight,
                'type'   => 'dimensional_price',
                'hidden' => false,
            );
        }

        // Add extra base price to breakdown if configured
        if ( $base_price > 0 ) {
            $this->breakdown[] = array(
                'label'  => __( 'Extra Basisprijs', 'bossier-calculator' ),
                'price'  => $base_price,
                'weight' => 0,
                'type'   => 'base_price',
                'hidden' => false,
            );
        }

        // -------------------------------------------------------------------------
        // STEP 6: Store raw values for debugging and external use
        // -------------------------------------------------------------------------
        $this->raw_values['pricing_mode']               = 'dimensional';
        $this->raw_values['all_dimensions']             = $all_dimensions;
        $this->raw_values['volume_dimensions']          = $volume_dimensions;
        $this->raw_values['extra_dimensions']           = $extra_dimensions;
        $this->raw_values['volume_mm3']                 = $volume_mm3;
        $this->raw_values['pricing_factor']             = $pricing_factor;
        $this->raw_values['weight_per_unit']            = $weight_per_unit;
        $this->raw_values['dimensional_price']          = $dimensional_price;
        $this->raw_values['calculated_price']           = $calculated_price;
        $this->raw_values['calculated_weight']          = $calculated_weight;
        $this->raw_values['enable_weight_calculation']  = $enable_weight_calculation;
        $this->raw_values['dimension_labels']           = $dimension_labels;
        $this->raw_values['dimensions_used_in_pricing'] = count( $volume_dimensions );
        $this->raw_values['dimensions_extra']           = count( $extra_dimensions );

        // Legacy compatibility - keep old keys for backwards compatibility
        $this->raw_values['dimensions']                = $all_dimensions;
        $this->raw_values['dimension_product']         = $volume_mm3;
        $this->raw_values['dimensional_unit_price']    = $pricing_factor;
    }

    /**
     * Check if a field is visible based on its show_when condition.
     *
     * @param array $field      The field to check.
     * @param array $all_fields All fields.
     * @param array $selections Posted selections.
     * @return bool True if visible.
     */
    private function is_field_visible( $field, $all_fields, $selections ) {
        if ( empty( $field['show_when_field'] ) ) {
            return true;
        }

        $source_field_id = $field['show_when_field'];
        $expected_value  = isset( $field['show_when_value'] ) ? $field['show_when_value'] : '';
        $actual_value    = isset( $selections[ $source_field_id ] ) ? $selections[ $source_field_id ] : '';

        // For array values (checkboxes), check if expected is in array
        if ( is_array( $actual_value ) ) {
            return in_array( (string) $expected_value, array_map( 'strval', $actual_value ), true );
        }

        return (string) $actual_value === (string) $expected_value;
    }

    /**
     * Process dimension and length fields and calculate price/weight contributions.
     *
     * Each dimension/length field can have its own price_per_mm, threshold, and weight_per_mm.
     * Values are ADDED (not multiplied) - each field contributes independently to the total.
     * This is the standard pricing mode calculation.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function process_dimension_fields( $fields, $selections ) {
        $dimension_index = 0;

        foreach ( $fields as $field_id => $field ) {
            $field_type = $field['type'] ?? '';

            // Process both 'dimension' and 'length' type fields in standard mode.
            // Each field contributes ADDITIVELY to the total price and weight.
            if ( 'dimension' !== $field_type && 'length' !== $field_type ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            // Skip fields hidden by show_when condition
            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
                continue;
            }

            $dim_label = isset( $field['label'] ) ? $field['label'] : __( 'Dimensie', 'bossier-calculator' );
            $unit_type = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
            $selection = $selections[ $field_id ];

            // Handle both 'length' and 'dimension' field types.
            // Length fields may have fixed_options mode where selection is an index.
            $mode      = $field['length_mode'] ?? 'free';
            $dim_value = 0;

            if ( 'fixed' === $mode && ! empty( $field['fixed_options'] ) ) {
                // Fixed options mode - selection is an index into the options array.
                $selection_index = intval( $selection );
                if ( isset( $field['fixed_options'][ $selection_index ] ) ) {
                    $option    = $field['fixed_options'][ $selection_index ];
                    $dim_value = floatval( $option['value'] );
                }
            } else {
                // Free input mode - selection is the direct value.
                $dim_value = floatval( $selection );

                // Clamp to min/max for free input.
                $dim_min = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
                $dim_max = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 99999;

                if ( $dim_value < $dim_min ) {
                    $dim_value = $dim_min;
                }
                if ( $dim_value > $dim_max ) {
                    $dim_value = $dim_max;
                }
            }

            // Convert to mm for internal calculations
            $value_mm = $this->convert_to_mm( $dim_value, $unit_type );

            // Track dimension kind and values for surcharge calculations.
            $dimension_kind = isset( $field['dimension_kind'] ) ? $field['dimension_kind'] : 'length';
            if ( 'length' === $dimension_kind && 0 === $this->length_mm ) {
                $this->length_mm = $value_mm;
            }

            // Track maximum dimension across all fields for long length surcharge.
            if ( $value_mm > $this->max_dimension_mm ) {
                $this->max_dimension_mm = $value_mm;
            }

            // Price extra: above threshold
            $price_per_mm = isset( $field['price_per_mm'] ) ? floatval( $field['price_per_mm'] ) : 0;
            $threshold    = isset( $field['threshold'] ) ? floatval( $field['threshold'] ) : 0;
            $price_add    = 0;

            if ( $price_per_mm > 0 ) {
                $extra_above_threshold = max( 0, $value_mm - $threshold );
                $price_add = $extra_above_threshold * $price_per_mm;
            }

            // Weight: always full value
            $weight_per_mm = isset( $field['weight_per_mm'] ) ? floatval( $field['weight_per_mm'] ) : 0;
            $weight_add    = 0;

            if ( $weight_per_mm > 0 ) {
                $weight_add = $value_mm * $weight_per_mm;
            }

            $this->price  += $price_add;
            $this->weight += $weight_add;

            // Breakdown entry
            if ( $price_add > 0 || $weight_add > 0 ) {
                $breakdown_label = $dim_label;
                if ( $price_add > 0 && $threshold > 0 ) {
                    $breakdown_label = sprintf(
                        /* translators: %1$s: dimension label, %2$s: extra mm above threshold */
                        __( '%1$s (+%2$s mm boven drempel)', 'bossier-calculator' ),
                        $dim_label,
                        number_format_i18n( $value_mm - $threshold, 0 )
                    );
                }

                $this->breakdown[] = array(
                    'label'    => $breakdown_label,
                    'value'    => $dim_value . ' ' . $unit_type,
                    'price'    => $price_add,
                    'weight'   => $weight_add,
                    'type'     => 'dimension',
                    'hidden'   => false,
                    'field_id' => $field_id,
                );
            }

            // Store raw dimension values
            $dim_key = 'dimension_' . $dimension_index;
            $this->raw_values[ $dim_key ] = array(
                'field_id'       => $field_id,
                'label'          => $dim_label,
                'dimension_kind' => $dimension_kind,
                'value'          => $dim_value,
                'value_mm'       => $value_mm,
                'unit'           => $unit_type,
                'price_extra'    => $price_add,
                'weight'         => $weight_add,
            );

            // Also store first length dimension in legacy keys for backward compat
            if ( 'length' === $dimension_kind && ! isset( $this->raw_values['length_mm'] ) ) {
                $this->raw_values['length']             = $dim_value;
                $this->raw_values['length_unit']        = $unit_type;
                $this->raw_values['length_mm']          = $value_mm;
                $this->raw_values['length_m']           = $value_mm / 1000;
                $this->raw_values['length_display']     = $dim_value . ' ' . $unit_type;
                $this->raw_values['length_extra_price'] = $price_add;
                $this->raw_values['length_weight']      = $weight_add;
            }

            $dimension_index++;
        }
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

        $threshold        = floatval( $settings['long_surcharge_threshold'] ?? 1500 );
        $surcharge_per_mm = floatval( $settings['long_surcharge_per_mm'] ?? 0 );

        // Use the maximum dimension value across all dimension fields,
        // so the surcharge works regardless of which dimension_kind is configured.
        $check_value = $this->max_dimension_mm;

        if ( $check_value <= $threshold || $surcharge_per_mm <= 0 ) {
            return;
        }

        $extra_length = $check_value - $threshold;
        $surcharge    = $extra_length * $surcharge_per_mm;

        $this->long_length_surcharge = $surcharge;
        $this->price += $surcharge;

        $this->breakdown[] = array(
            'label'  => sprintf(
                /* translators: %s: threshold length */
                __( 'Lange lengte toeslag (boven %s mm)', 'bossier-calculator' ),
                number_format_i18n( $threshold, 0 )
            ),
            'price'  => $surcharge,
            'weight' => 0,
            'type'   => 'long_length_surcharge',
            'hidden' => false,
        );
    }

    /**
     * Calculate non-standard surcharge — fixed amount when any dimension
     * differs from its Standard (mm) value.
     *
     * @param array $settings   Calculator settings.
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function calculate_nonstandard_surcharge( $settings, $fields, $selections ) {
        if ( empty( $settings['enable_nonstandard_surcharge'] ) ) {
            return;
        }

        $surcharge_amount = floatval( $settings['nonstandard_surcharge_amount'] ?? 0 );
        if ( $surcharge_amount <= 0 ) {
            return;
        }

        // Check if any dimension field's value differs from its standard (mm).
        foreach ( $fields as $field_id => $field ) {
            if ( 'dimension' !== ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
                continue;
            }

            $standard_mm = floatval( $field['default_value'] ?? 0 );
            if ( $standard_mm <= 0 ) {
                continue;
            }

            $dim_value = floatval( $selections[ $field_id ] );
            $unit_type = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
            $value_mm  = $this->convert_to_mm( $dim_value, $unit_type );

            // Clamp to min/max like process_dimension_fields does.
            $dim_min = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
            $dim_max = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 99999;
            if ( $value_mm < $dim_min ) {
                $value_mm = $dim_min;
            }
            if ( $value_mm > $dim_max ) {
                $value_mm = $dim_max;
            }

            if ( abs( $value_mm - $standard_mm ) > 0.001 ) {
                $this->nonstandard_surcharge = $surcharge_amount;
                $this->price += $surcharge_amount;

                $this->breakdown[] = array(
                    'label'  => __( 'Toeslag niet-standaard maat', 'bossier-calculator' ),
                    'price'  => $surcharge_amount,
                    'weight' => 0,
                    'type'   => 'nonstandard_surcharge',
                    'hidden' => false,
                );

                // Add only once, not per dimension field.
                return;
            }
        }
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

            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
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

            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
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
            if ( in_array( $field_type, array( 'length', 'color', 'mitre_angle', 'quantity', 'dimension', 'text' ), true ) ) {
                continue;
            }

            if ( 'custom' !== $field_type ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
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
     * Process brievenbus fields.
     *
     * @param array $fields     All fields.
     * @param array $selections User selections.
     */
    private function process_brievenbus_fields( $fields, $selections ) {
        foreach ( $fields as $field_id => $field ) {
            if ( 'brievenbus' !== ( $field['type'] ?? '' ) ) {
                continue;
            }

            if ( ! isset( $selections[ $field_id ] ) ) {
                continue;
            }

            if ( ! $this->is_field_visible( $field, $fields, $selections ) ) {
                continue;
            }

            $selection = $selections[ $field_id ];
            $label     = $field['label'] ?? __( 'Brievenbus', 'bossier-calculator' );

            // The selection can be either a string 'ja'/'nee' (from POST) or an array with sub-keys
            $main_answer = 'nee';
            if ( is_array( $selection ) ) {
                $main_answer = isset( $selection['main'] ) ? $selection['main'] : 'nee';
            } elseif ( is_string( $selection ) ) {
                $main_answer = $selection;
            }

            if ( 'ja' === $main_answer ) {
                $main_surcharge = floatval( $field['main_surcharge'] ?? 0 );
                $this->price += $main_surcharge;

                $main_label = $field['main_label'] ?? __( 'Huisnummer', 'bossier-calculator' );
                $this->breakdown[] = array(
                    'label'  => $main_label,
                    'value'  => __( 'Ja', 'bossier-calculator' ),
                    'price'  => $main_surcharge,
                    'weight' => 0,
                    'type'   => 'brievenbus',
                    'hidden' => false,
                );

                // Check sub answer — from array (JS) or from POST hidden field
                $sub_answer = 'nee';
                if ( is_array( $selection ) && isset( $selection['sub'] ) ) {
                    $sub_answer = $selection['sub'];
                } else {
                    // Read from POST: bossier_calc_[field_id]_sub
                    $sub_key = 'bossier_calc_' . $field_id . '_sub';
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    if ( isset( $_POST[ $sub_key ] ) ) {
                        $sub_answer = sanitize_text_field( wp_unslash( $_POST[ $sub_key ] ) );
                    }
                }

                if ( 'ja' === $sub_answer ) {
                    $sub_surcharge = floatval( $field['sub_surcharge'] ?? 0 );
                    $this->price += $sub_surcharge;

                    $sub_label = $field['sub_label'] ?? __( 'Toevoeging', 'bossier-calculator' );
                    $this->breakdown[] = array(
                        'label'  => $sub_label,
                        'value'  => __( 'Ja', 'bossier-calculator' ),
                        'price'  => $sub_surcharge,
                        'weight' => 0,
                        'type'   => 'brievenbus',
                        'hidden' => false,
                    );
                }
            }
        }
    }

    /**
     * Process one-time product fee.
     * This fee is charged once per product, regardless of quantity.
     * It is NOT added to the calculated price, but tracked separately
     * to be added as a WooCommerce fee in the cart.
     *
     * @param array $settings Calculator settings.
     */
    private function process_product_fee( $settings ) {
        $enable_product_fee = ! empty( $settings['enable_product_fee'] );
        $product_fee_amount = floatval( $settings['product_fee_amount'] ?? 0 );
        $product_fee_label  = ! empty( $settings['product_fee_label'] )
            ? $settings['product_fee_label']
            : __( 'Eenmalige productkosten', 'bossier-calculator' );

        if ( ! $enable_product_fee || $product_fee_amount <= 0 ) {
            return;
        }

        // Store the fee (NOT added to item price - will be added as WooCommerce fee)
        $this->product_fee       = $product_fee_amount;
        $this->product_fee_label = $product_fee_label;

        // Add to breakdown for display purposes
        $this->breakdown[] = array(
            'label'             => $product_fee_label,
            'price'             => $product_fee_amount,
            'weight'            => 0,
            'type'              => 'product_fee',
            'hidden'            => false,
            'is_one_time'       => true,
            'one_time_note'     => __( 'Eenmalig per product', 'bossier-calculator' ),
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
