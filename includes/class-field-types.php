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
     * Note: 'length' is no longer a selectable field type.
     * Length is now handled automatically by the calculator core
     * and configured via sidebar settings.
     *
     * @return array
     */
    public static function get_types() {
        return array(
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
            'dimension'   => array(
                'label'       => __( 'Dimensie', 'bossier-calculator' ),
                'description' => __( 'Afmeting invoer (lengte, breedte, hoogte) met prijs/gewicht per mm', 'bossier-calculator' ),
                'icon'        => 'dashicons-editor-expand',
            ),
            'text'        => array(
                'label'       => __( 'Tekst', 'bossier-calculator' ),
                'description' => __( 'Vrije tekst invoer (informatief, geen prijsimpact)', 'bossier-calculator' ),
                'icon'        => 'dashicons-editor-textcolor',
            ),
            'brievenbus'  => array(
                'label'       => __( 'Brievenbus', 'bossier-calculator' ),
                'description' => __( 'Geneste ja/nee vragen met tekstvelden en toeslagen (huisnummer + toevoeging)', 'bossier-calculator' ),
                'icon'        => 'dashicons-email',
            ),
        );
    }

    /**
     * Get all field types including legacy types.
     *
     * This includes deprecated types like 'length' for backward compatibility.
     *
     * @return array
     */
    public static function get_all_types_including_legacy() {
        $types = self::get_types();

        // Add legacy length type for backward compatibility
        $types['length'] = array(
            'label'       => __( 'Lengte (verouderd)', 'bossier-calculator' ),
            'description' => __( 'Verouderd - lengte wordt nu automatisch afgehandeld', 'bossier-calculator' ),
            'icon'        => 'dashicons-editor-expand',
            'legacy'      => true,
        );

        return $types;
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

            case 'dimension':
                return array(
                    'number' => __( 'Nummer Invoer', 'bossier-calculator' ),
                );

            case 'text':
                return array(
                    'text' => __( 'Tekst Invoer', 'bossier-calculator' ),
                );

            case 'brievenbus':
                return array(
                    'toggle' => __( 'Ja/Nee Knoppen', 'bossier-calculator' ),
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
     * Check if field type is valid (including legacy types).
     *
     * @param string $type Field type to check.
     * @return bool
     */
    public static function is_valid_type( $type ) {
        return array_key_exists( $type, self::get_all_types_including_legacy() );
    }

    /**
     * Check if field type is a legacy/deprecated type.
     *
     * @param string $type Field type to check.
     * @return bool
     */
    public static function is_legacy_type( $type ) {
        $all_types = self::get_all_types_including_legacy();
        return isset( $all_types[ $type ]['legacy'] ) && $all_types[ $type ]['legacy'];
    }
}
