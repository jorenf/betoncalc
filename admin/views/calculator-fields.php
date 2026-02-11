<?php
/**
 * Calculator fields meta box view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available in this template:
 *
 * @var \Bossier\Calculator\Calculator $calculator  Calculator instance.
 * @var array                          $fields      Calculator fields.
 * @var array                          $field_types Available field types.
 */

$settings = $calculator->get_settings();
include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/calculator-summary.php';
?>

<div class="bossier-calculator-fields-wrap">
    <div class="bossier-fields-header">
        <h3><?php esc_html_e( 'Configureer Calculator Velden', 'bossier-calculator' ); ?></h3>
        <div class="bossier-add-field-wrap">
            <select id="bossier-add-field-type">
                <option value=""><?php esc_html_e( '— Selecteer Veld Type —', 'bossier-calculator' ); ?></option>
                <?php foreach ( $field_types as $type => $type_info ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>">
                        <?php echo esc_html( $type_info['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button button-primary" id="bossier-add-field">
                <?php esc_html_e( 'Veld Toevoegen', 'bossier-calculator' ); ?>
            </button>
        </div>
    </div>

    <div id="bossier-fields-container" class="bossier-fields-container">
        <?php
        if ( ! empty( $fields ) ) :
            $order = 0;
            foreach ( $fields as $field_id => $field ) :
                $field['id']            = $field_id;
                $field['display_order'] = $order++;
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
            endforeach;
        else :
            ?>
            <div class="bossier-no-fields">
                <p><?php esc_html_e( 'Geen velden geconfigureerd. Voeg een veld toe met de dropdown hierboven.', 'bossier-calculator' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sentinel: if missing on save, POST was truncated by max_input_vars -->
    <input type="hidden" name="bossier_fields_sentinel" value="1">
</div>

<!-- Field Templates (hidden, used by JavaScript) -->
<script type="text/template" id="bossier-field-template-length">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'length' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-color">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'color' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-mitre_angle">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'mitre_angle' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-quantity">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'quantity' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-custom">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'custom' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-dimension">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'dimension' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>

<script type="text/template" id="bossier-field-template-text">
    <?php
    $field = \Bossier\Calculator\Calculator::get_default_field( 'text' );
    $field['id'] = '{{FIELD_ID}}';
    include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-item.php';
    ?>
</script>
