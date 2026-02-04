<?php
/**
 * Field Types class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Field_Types class - Defines available field types for calculators.
 */
class Field_Types {

    /**
     * Get all available field types.
     *
     * @return array
     */
    public static function get_types() {
        return array(
            'length'      => array(
                'label'       => __( 'Length', 'bossier-calculator' ),
                'description' => __( 'Length input with price/weight per unit calculation', 'bossier-calculator' ),
                'icon'        => 'dashicons-editor-expand',
            ),
            'color'       => array(
                'label'       => __( 'Color', 'bossier-calculator' ),
                'description' => __( 'Color selection with optional surcharge', 'bossier-calculator' ),
                'icon'        => 'dashicons-art',
            ),
            'mitre_angle' => array(
                'label'       => __( 'Mitre Angle (Verstekhoek)', 'bossier-calculator' ),
                'description' => __( 'Angle cut selection with price/weight adjustments', 'bossier-calculator' ),
                'icon'        => 'dashicons-image-rotate',
            ),
            'quantity'    => array(
                'label'       => __( 'Quantity', 'bossier-calculator' ),
                'description' => __( 'Quantity selector with min/max limits', 'bossier-calculator' ),
                'icon'        => 'dashicons-forms',
            ),
            'custom'      => array(
                'label'       => __( 'Custom Field', 'bossier-calculator' ),
                'description' => __( 'Custom options with price/weight surcharges', 'bossier-calculator' ),
                'icon'        => 'dashicons-admin-generic',
            ),
        );
    }

    /**
     * Get input types for a field type.
     *
     * @param string $field_type Field type.
     * @return array
     */
    public static function get_input_types( $field_type ) {
        switch ( $field_type ) {
            case 'length':
                return array(
                    'number'   => __( 'Number Input', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Select', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Buttons', 'bossier-calculator' ),
                );

            case 'color':
                return array(
                    'swatch'   => __( 'Color Swatches', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Select', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Buttons', 'bossier-calculator' ),
                );

            case 'mitre_angle':
                return array(
                    'radio'    => __( 'Radio Buttons', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Select', 'bossier-calculator' ),
                );

            case 'quantity':
                return array(
                    'number'   => __( 'Number Input', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Select', 'bossier-calculator' ),
                );

            case 'custom':
                return array(
                    'dropdown' => __( 'Dropdown Select', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Buttons', 'bossier-calculator' ),
                    'checkbox' => __( 'Checkboxes', 'bossier-calculator' ),
                );

            default:
                return array(
                    'text' => __( 'Text Input', 'bossier-calculator' ),
                );
        }
    }

    /**
     * Get unit types for length field.
     *
     * @return array
     */
    public static function get_length_units() {
        return array(
            'mm' => array(
                'label'    => __( 'Millimeters (mm)', 'bossier-calculator' ),
                'symbol'   => 'mm',
                'to_meter' => 0.001,
            ),
            'cm' => array(
                'label'    => __( 'Centimeters (cm)', 'bossier-calculator' ),
                'symbol'   => 'cm',
                'to_meter' => 0.01,
            ),
            'm'  => array(
                'label'    => __( 'Meters (m)', 'bossier-calculator' ),
                'symbol'   => 'm',
                'to_meter' => 1,
            ),
        );
    }

    /**
     * Get price calculation modes.
     *
     * @return array
     */
    public static function get_price_modes() {
        return array(
            'per_mm'    => __( 'Price per mm', 'bossier-calculator' ),
            'per_cm'    => __( 'Price per cm', 'bossier-calculator' ),
            'per_m'     => __( 'Price per meter', 'bossier-calculator' ),
            'fixed'     => __( 'Fixed price per option', 'bossier-calculator' ),
        );
    }

    /**
     * Check if field type is valid.
     *
     * @param string $type Field type to check.
     * @return bool
     */
    public static function is_valid_type( $type ) {
        return array_key_exists( $type, self::get_types() );
    }
}
