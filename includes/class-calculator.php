<?php
/**
 * Calculator model class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Calculator class - Represents a single calculator configuration.
 */
class Calculator {

    /**
     * Calculator ID.
     *
     * @var int
     */
    private $id;

    /**
     * Calculator post object.
     *
     * @var \WP_Post|null
     */
    private $post;

    /**
     * Calculator fields.
     *
     * @var array
     */
    private $fields = array();

    /**
     * Calculator settings.
     *
     * @var array
     */
    private $settings = array();

    /**
     * Default settings.
     *
     * @var array
     */
    private static $default_settings = array(
        'base_price'              => 0,
        'base_weight'             => 0,
        'decimal_places'          => 2,
        'price_decimals'          => 2,
        'weight_decimals'         => 3,
        'show_preview'            => true,
        'price_label'             => '',
        'weight_label'            => '',
        // Long length surcharge settings (applies to first dimension field of kind "length")
        'enable_long_surcharge'   => false,
        'long_surcharge_threshold'=> 1500,
        'long_surcharge_per_mm'   => 0,
    );

    /**
     * Constructor.
     *
     * @param int $calculator_id Calculator post ID.
     */
    public function __construct( $calculator_id ) {
        $this->id   = absint( $calculator_id );
        $this->post = get_post( $this->id );

        if ( $this->post && Plugin::POST_TYPE === $this->post->post_type ) {
            $this->load_data();
        }
    }

    /**
     * Load calculator data from database.
     */
    private function load_data() {
        // Load fields
        $fields = get_post_meta( $this->id, '_bossier_calculator_fields', true );
        $this->fields = is_array( $fields ) ? $fields : array();

        // Load settings
        $settings = get_post_meta( $this->id, '_bossier_calculator_settings', true );
        $settings = is_array( $settings ) ? $settings : array();

        $this->settings = wp_parse_args( $settings, self::$default_settings );

        // Migrate old sidebar length settings to a dimension field
        $this->maybe_migrate_length_to_dimension( $settings );
    }

    /**
     * Migrate old sidebar length settings to a dimension field.
     * Only runs if no dimension fields exist and old settings are present.
     *
     * @param array $raw_settings Raw settings from database (before defaults applied).
     */
    private function maybe_migrate_length_to_dimension( $raw_settings ) {
        // Check if there are already dimension fields
        foreach ( $this->fields as $field ) {
            if ( 'dimension' === ( $field['type'] ?? '' ) ) {
                return; // Already has dimension fields, no migration needed
            }
        }

        // Check for old length settings in sidebar
        if ( ! isset( $raw_settings['min_length_input'] ) && ! isset( $raw_settings['price_per_mm'] ) ) {
            return; // No old settings to migrate
        }

        // Create a dimension field from old sidebar settings
        $migrated_field = array(
            'type'           => 'dimension',
            'label'          => __( 'Lengte', 'bossier-calculator' ),
            'enabled'        => true,
            'required'       => true,
            'display_order'  => -1,
            'input_type'     => 'number',
            'help_text'      => '',
            'dimension_kind' => 'length',
            'min_value'      => isset( $raw_settings['min_length_input'] ) ? floatval( $raw_settings['min_length_input'] ) : 100,
            'max_value'      => isset( $raw_settings['max_length'] ) ? floatval( $raw_settings['max_length'] ) : 5000,
            'default_value'  => isset( $raw_settings['default_length'] ) && '' !== $raw_settings['default_length'] ? floatval( $raw_settings['default_length'] ) : '',
            'step_size'      => 1,
            'price_per_mm'   => isset( $raw_settings['price_per_mm'] ) ? floatval( $raw_settings['price_per_mm'] ) : 0,
            'threshold'      => isset( $raw_settings['min_length'] ) ? floatval( $raw_settings['min_length'] ) : 1000,
            'weight_per_mm'  => isset( $raw_settings['base_weight_per_mm'] ) ? floatval( $raw_settings['base_weight_per_mm'] ) : 0,
            'unit_type'      => 'mm',
        );

        // Prepend the migrated field (display_order -1 ensures it shows first)
        $this->fields = array_merge(
            array( '_migrated_length' => $migrated_field ),
            $this->fields
        );
    }

    /**
     * Check if calculator is valid.
     *
     * @return bool
     */
    public function is_valid() {
        return null !== $this->post && Plugin::POST_TYPE === $this->post->post_type;
    }

    /**
     * Get calculator ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get calculator title.
     *
     * @return string
     */
    public function get_title() {
        return $this->post ? $this->post->post_title : '';
    }

    /**
     * Get calculator fields.
     *
     * @return array
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Get enabled fields only, sorted by display order.
     *
     * @return array
     */
    public function get_enabled_fields() {
        $enabled = array_filter( $this->fields, function( $field ) {
            return ! empty( $field['enabled'] );
        });

        // Sort by display order
        uasort( $enabled, function( $a, $b ) {
            $order_a = isset( $a['display_order'] ) ? (int) $a['display_order'] : 0;
            $order_b = isset( $b['display_order'] ) ? (int) $b['display_order'] : 0;
            return $order_a - $order_b;
        });

        return $enabled;
    }

    /**
     * Get calculator settings.
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get a specific setting.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }
        if ( isset( self::$default_settings[ $key ] ) ) {
            return self::$default_settings[ $key ];
        }
        return $default;
    }

    /**
     * Get full configuration for JavaScript.
     *
     * @return array
     */
    public function get_config() {
        return array(
            'id'       => $this->id,
            'title'    => $this->get_title(),
            'fields'   => $this->get_enabled_fields(),
            'settings' => $this->settings,
        );
    }

    /**
     * Save calculator data.
     *
     * @param array $fields   Calculator fields.
     * @param array $settings Calculator settings.
     * @return bool
     */
    public function save( $fields, $settings ) {
        if ( ! $this->is_valid() ) {
            return false;
        }

        // Sanitize and save fields
        $sanitized_fields = $this->sanitize_fields( $fields );
        update_post_meta( $this->id, '_bossier_calculator_fields', $sanitized_fields );

        // Sanitize and save settings
        $sanitized_settings = $this->sanitize_settings( $settings );
        update_post_meta( $this->id, '_bossier_calculator_settings', $sanitized_settings );

        $this->fields   = $sanitized_fields;
        $this->settings = $sanitized_settings;

        return true;
    }

    /**
     * Sanitize calculator fields.
     *
     * @param array $fields Raw fields data.
     * @return array Sanitized fields.
     */
    private function sanitize_fields( $fields ) {
        if ( ! is_array( $fields ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $fields as $field_id => $field ) {
            $field_id = sanitize_key( $field_id );

            $sanitized_field = array(
                'id'            => $field_id,
                'type'          => isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text',
                'label'         => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '',
                'enabled'       => ! empty( $field['enabled'] ),
                'required'      => ! empty( $field['required'] ),
                'display_order' => isset( $field['display_order'] ) ? absint( $field['display_order'] ) : 0,
                'input_type'    => isset( $field['input_type'] ) ? sanitize_key( $field['input_type'] ) : 'text',
                'help_text'     => isset( $field['help_text'] ) ? sanitize_textarea_field( $field['help_text'] ) : '',
            );

            // Field-type specific settings
            switch ( $sanitized_field['type'] ) {
                case 'length':
                    $sanitized_field['length_mode']      = isset( $field['length_mode'] ) ? sanitize_key( $field['length_mode'] ) : 'free';
                    $sanitized_field['min_value']        = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 0;
                    $sanitized_field['max_value']        = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 10000;
                    $sanitized_field['default_value']    = isset( $field['default_value'] ) && '' !== $field['default_value'] ? floatval( $field['default_value'] ) : '';
                    $sanitized_field['step_size']        = isset( $field['step_size'] ) ? floatval( $field['step_size'] ) : 1;
                    $sanitized_field['price_per_unit']   = isset( $field['price_per_unit'] ) ? floatval( $field['price_per_unit'] ) : 0;
                    $sanitized_field['weight_per_unit']  = isset( $field['weight_per_unit'] ) ? floatval( $field['weight_per_unit'] ) : 0;
                    $sanitized_field['unit_type']        = isset( $field['unit_type'] ) ? sanitize_key( $field['unit_type'] ) : 'mm';
                    $sanitized_field['fixed_options']    = isset( $field['fixed_options'] ) ? $this->sanitize_length_options( $field['fixed_options'] ) : array();
                    break;

                case 'color':
                    $sanitized_field['colors'] = isset( $field['colors'] ) ? $this->sanitize_color_options( $field['colors'] ) : array();
                    break;

                case 'mitre_angle':
                    $sanitized_field['angles'] = isset( $field['angles'] ) ? $this->sanitize_angle_options( $field['angles'] ) : array();
                    $sanitized_field['mitre_groups'] = isset( $field['mitre_groups'] ) ? $this->sanitize_mitre_groups( $field['mitre_groups'] ) : array();
                    $sanitized_field['default_angle'] = isset( $field['default_angle'] ) ? absint( $field['default_angle'] ) : 0;
                    break;

                case 'quantity':
                    $sanitized_field['min_qty']  = isset( $field['min_qty'] ) ? absint( $field['min_qty'] ) : 1;
                    $sanitized_field['max_qty']  = isset( $field['max_qty'] ) ? absint( $field['max_qty'] ) : 100;
                    $sanitized_field['step_qty'] = isset( $field['step_qty'] ) ? absint( $field['step_qty'] ) : 1;
                    break;

                case 'custom':
                    $sanitized_field['custom_options'] = isset( $field['custom_options'] ) ? $this->sanitize_custom_options( $field['custom_options'] ) : array();
                    break;

                case 'text':
                    $sanitized_field['placeholder'] = isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '';
                    $sanitized_field['max_chars']   = isset( $field['max_chars'] ) ? absint( $field['max_chars'] ) : 50;
                    break;

                case 'dimension':
                    $sanitized_field['dimension_kind'] = isset( $field['dimension_kind'] ) && in_array( $field['dimension_kind'], array( 'length', 'width', 'height' ), true ) ? $field['dimension_kind'] : 'length';
                    $sanitized_field['min_value']      = isset( $field['min_value'] ) ? floatval( $field['min_value'] ) : 100;
                    $sanitized_field['max_value']      = isset( $field['max_value'] ) ? floatval( $field['max_value'] ) : 5000;
                    $sanitized_field['default_value']  = isset( $field['default_value'] ) && '' !== $field['default_value'] ? floatval( $field['default_value'] ) : '';
                    $sanitized_field['step_size']      = isset( $field['step_size'] ) ? floatval( $field['step_size'] ) : 1;
                    $sanitized_field['price_per_mm']   = isset( $field['price_per_mm'] ) ? floatval( $field['price_per_mm'] ) : 0;
                    $sanitized_field['threshold']      = isset( $field['threshold'] ) ? floatval( $field['threshold'] ) : 0;
                    $sanitized_field['weight_per_mm']  = isset( $field['weight_per_mm'] ) ? floatval( $field['weight_per_mm'] ) : 0;
                    $sanitized_field['unit_type']      = isset( $field['unit_type'] ) ? sanitize_key( $field['unit_type'] ) : 'mm';
                    break;
            }

            $sanitized[ $field_id ] = $sanitized_field;
        }

        return $sanitized;
    }

    /**
     * Sanitize length options.
     *
     * @param array $options Raw options.
     * @return array Sanitized options.
     */
    private function sanitize_length_options( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $options as $option ) {
            $sanitized[] = array(
                'value'     => isset( $option['value'] ) ? floatval( $option['value'] ) : 0,
                'label'     => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
                'price'     => isset( $option['price'] ) ? floatval( $option['price'] ) : 0,
                'weight'    => isset( $option['weight'] ) ? floatval( $option['weight'] ) : 0,
            );
        }
        return $sanitized;
    }

    /**
     * Sanitize color options.
     *
     * @param array $options Raw options.
     * @return array Sanitized options.
     */
    private function sanitize_color_options( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $options as $option ) {
            $sanitized[] = array(
                'name'        => isset( $option['name'] ) ? sanitize_text_field( $option['name'] ) : '',
                'hex'         => isset( $option['hex'] ) ? sanitize_hex_color( $option['hex'] ) : '#000000',
                'image'       => isset( $option['image'] ) ? esc_url_raw( $option['image'] ) : '',
                'surcharge'   => isset( $option['surcharge'] ) ? floatval( $option['surcharge'] ) : 0,
                'price_type'  => isset( $option['price_type'] ) && in_array( $option['price_type'], array( 'fixed', 'percentage' ), true ) ? $option['price_type'] : 'fixed',
                'is_default'  => ! empty( $option['is_default'] ),
            );
        }
        return $sanitized;
    }

    /**
     * Sanitize angle options.
     *
     * @param array $options Raw options.
     * @return array Sanitized options.
     */
    private function sanitize_angle_options( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $options as $option ) {
            $sanitized[] = array(
                'label'        => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
                'surcharge'    => isset( $option['surcharge'] ) ? floatval( $option['surcharge'] ) : 0,
                'extra_weight' => isset( $option['extra_weight'] ) ? floatval( $option['extra_weight'] ) : 0,
                'image'        => isset( $option['image'] ) ? esc_url_raw( $option['image'] ) : '',
                'is_no_mitre'  => ! empty( $option['is_no_mitre'] ) ? 1 : 0,
            );
        }
        return $sanitized;
    }

    /**
     * Sanitize mitre groups (multiple angle selectors).
     *
     * @param array $groups Raw groups data.
     * @return array Sanitized groups.
     */
    private function sanitize_mitre_groups( $groups ) {
        if ( ! is_array( $groups ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $groups as $group ) {
            $sanitized_group = array(
                'id'      => isset( $group['id'] ) ? sanitize_key( $group['id'] ) : 'group_' . count( $sanitized ),
                'label'   => isset( $group['label'] ) ? sanitize_text_field( $group['label'] ) : '',
                'default' => isset( $group['default'] ) ? absint( $group['default'] ) : 0,
                'angles'  => array(),
            );

            // Sanitize angles within the group
            if ( isset( $group['angles'] ) && is_array( $group['angles'] ) ) {
                $sanitized_group['angles'] = $this->sanitize_angle_options( $group['angles'] );
            }

            $sanitized[] = $sanitized_group;
        }
        return $sanitized;
    }

    /**
     * Sanitize custom field options.
     *
     * @param array $options Raw options.
     * @return array Sanitized options.
     */
    private function sanitize_custom_options( $options ) {
        if ( ! is_array( $options ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $options as $option ) {
            $sanitized[] = array(
                'label'       => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
                'value'       => isset( $option['value'] ) ? sanitize_text_field( $option['value'] ) : '',
                'surcharge'   => isset( $option['surcharge'] ) ? floatval( $option['surcharge'] ) : 0,
                'extra_weight'=> isset( $option['extra_weight'] ) ? floatval( $option['extra_weight'] ) : 0,
            );
        }
        return $sanitized;
    }

    /**
     * Sanitize calculator settings.
     *
     * @param array $settings Raw settings data.
     * @return array Sanitized settings.
     */
    private function sanitize_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return self::$default_settings;
        }

        return array(
            'base_price'               => isset( $settings['base_price'] ) ? floatval( $settings['base_price'] ) : 0,
            'base_weight'              => isset( $settings['base_weight'] ) ? floatval( $settings['base_weight'] ) : 0,
            'decimal_places'           => isset( $settings['decimal_places'] ) ? absint( $settings['decimal_places'] ) : 2,
            'price_decimals'           => isset( $settings['price_decimals'] ) ? absint( $settings['price_decimals'] ) : 2,
            'weight_decimals'          => isset( $settings['weight_decimals'] ) ? absint( $settings['weight_decimals'] ) : 3,
            'show_preview'             => ! empty( $settings['show_preview'] ),
            'price_label'              => isset( $settings['price_label'] ) ? sanitize_text_field( $settings['price_label'] ) : '',
            'weight_label'             => isset( $settings['weight_label'] ) ? sanitize_text_field( $settings['weight_label'] ) : '',
            // Long length surcharge settings (applies to first dimension field of kind "length")
            'enable_long_surcharge'    => ! empty( $settings['enable_long_surcharge'] ),
            'long_surcharge_threshold' => isset( $settings['long_surcharge_threshold'] ) ? floatval( $settings['long_surcharge_threshold'] ) : 1500,
            'long_surcharge_per_mm'    => isset( $settings['long_surcharge_per_mm'] ) ? floatval( $settings['long_surcharge_per_mm'] ) : 0,
        );
    }

    /**
     * Duplicate calculator.
     *
     * @return int|false New calculator ID or false on failure.
     */
    public function duplicate() {
        if ( ! $this->is_valid() ) {
            return false;
        }

        $new_post_id = wp_insert_post( array(
            'post_type'   => Plugin::POST_TYPE,
            'post_title'  => sprintf(
                /* translators: %s: Original calculator title */
                __( '%s (Kopie)', 'bossier-calculator' ),
                $this->get_title()
            ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
            return false;
        }

        // Copy meta data
        update_post_meta( $new_post_id, '_bossier_calculator_fields', $this->fields );
        update_post_meta( $new_post_id, '_bossier_calculator_settings', $this->settings );

        return $new_post_id;
    }

    /**
     * Get default field structure for a field type.
     *
     * @param string $type Field type.
     * @return array
     */
    public static function get_default_field( $type ) {
        $base = array(
            'id'            => '',
            'type'          => $type,
            'label'         => '',
            'enabled'       => true,
            'required'      => false,
            'display_order' => 0,
            'input_type'    => 'text',
            'help_text'     => '',
        );

        switch ( $type ) {
            case 'length':
                return array_merge( $base, array(
                    'label'           => __( 'Lengte', 'bossier-calculator' ),
                    'input_type'      => 'number',
                    'length_mode'     => 'free',
                    'min_value'       => 100,
                    'max_value'       => 5000,
                    'step_size'       => 1,
                    'price_per_unit'  => 0,
                    'weight_per_unit' => 0,
                    'unit_type'       => 'mm',
                    'fixed_options'   => array(),
                ) );

            case 'color':
                return array_merge( $base, array(
                    'label'      => __( 'Kleur', 'bossier-calculator' ),
                    'input_type' => 'swatch',
                    'colors'     => array(),
                ) );

            case 'mitre_angle':
                return array_merge( $base, array(
                    'label'      => __( 'Verstekhoek', 'bossier-calculator' ),
                    'input_type' => 'radio',
                    'angles'     => array(),
                ) );

            case 'quantity':
                return array_merge( $base, array(
                    'label'    => __( 'Aantal', 'bossier-calculator' ),
                    'input_type' => 'number',
                    'min_qty'  => 1,
                    'max_qty'  => 100,
                    'step_qty' => 1,
                ) );

            case 'custom':
                return array_merge( $base, array(
                    'label'          => __( 'Aangepast Veld', 'bossier-calculator' ),
                    'input_type'     => 'dropdown',
                    'custom_options' => array(),
                ) );

            case 'text':
                return array_merge( $base, array(
                    'label'       => __( 'Tekst', 'bossier-calculator' ),
                    'input_type'  => 'text',
                    'placeholder' => '',
                    'max_chars'   => 50,
                ) );

            case 'dimension':
                return array_merge( $base, array(
                    'label'          => __( 'Dimensie', 'bossier-calculator' ),
                    'input_type'     => 'number',
                    'required'       => true,
                    'dimension_kind' => 'length',
                    'min_value'      => 100,
                    'max_value'      => 5000,
                    'default_value'  => '',
                    'step_size'      => 1,
                    'price_per_mm'   => 0,
                    'threshold'      => 0,
                    'weight_per_mm'  => 0,
                    'unit_type'      => 'mm',
                ) );

            default:
                return $base;
        }
    }
}
