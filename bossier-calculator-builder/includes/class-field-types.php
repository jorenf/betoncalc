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
                'label'       => __( 'Lengte', 'bossier-calculator' ),
                'description' => __( 'Lengte invoer met prijs/gewicht per eenheid berekening', 'bossier-calculator' ),
                'icon'        => 'dashicons-editor-expand',
            ),
            'color'       => array(
                'label'       => __( 'Kleur', 'bossier-calculator' ),
                'description' => __( 'Kleur selectie met optionele toeslag', 'bossier-calculator' ),
                'icon'        => 'dashicons-art',
            ),
            'mitre_angle' => array(
                'label'       => __( 'Verstekhoek', 'bossier-calculator' ),
                'description' => __( 'Hoek snede selectie met prijs/gewicht aanpassingen', 'bossier-calculator' ),
                'icon'        => 'dashicons-image-rotate',
            ),
            'quantity'    => array(
                'label'       => __( 'Aantal', 'bossier-calculator' ),
                'description' => __( 'Aantal selector met min/max limieten', 'bossier-calculator' ),
                'icon'        => 'dashicons-forms',
            ),
            'custom'      => array(
                'label'       => __( 'Aangepast Veld', 'bossier-calculator' ),
                'description' => __( 'Aangepaste opties met prijs/gewicht toeslagen', 'bossier-calculator' ),
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
                    'number'   => __( 'Nummer Invoer', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Selectie', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Knoppen', 'bossier-calculator' ),
                );

            case 'color':
                return array(
                    'swatch'   => __( 'Kleur Swatches', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Selectie', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Knoppen', 'bossier-calculator' ),
                );

            case 'mitre_angle':
                return array(
                    'radio'    => __( 'Radio Knoppen', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Selectie', 'bossier-calculator' ),
                );

            case 'quantity':
                return array(
                    'number'   => __( 'Nummer Invoer', 'bossier-calculator' ),
                    'dropdown' => __( 'Dropdown Selectie', 'bossier-calculator' ),
                );

            case 'custom':
                return array(
                    'dropdown' => __( 'Dropdown Selectie', 'bossier-calculator' ),
                    'radio'    => __( 'Radio Knoppen', 'bossier-calculator' ),
                    'checkbox' => __( 'Checkboxen', 'bossier-calculator' ),
                );

            default:
                return array(
                    'text' => __( 'Tekst Invoer', 'bossier-calculator' ),
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
            'per_mm'    => __( 'Prijs per mm', 'bossier-calculator' ),
            'per_cm'    => __( 'Prijs per cm', 'bossier-calculator' ),
            'per_m'     => __( 'Prijs per meter', 'bossier-calculator' ),
            'fixed'     => __( 'Vaste prijs per optie', 'bossier-calculator' ),
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
