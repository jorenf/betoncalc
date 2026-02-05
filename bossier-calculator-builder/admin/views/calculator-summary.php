<?php
/**
 * Calculator summary panel view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available:
 *
 * @var \Bossier\Calculator\Calculator $calculator  Calculator instance.
 * @var array                          $fields      Calculator fields.
 * @var array                          $settings    Calculator settings.
 */

$currency_symbol  = get_woocommerce_currency_symbol();
$weight_unit      = get_option( 'woocommerce_weight_unit', 'kg' );
$enabled_fields   = $calculator->get_enabled_fields();
$field_count      = count( $enabled_fields );

// Count field types
$length_fields    = 0;
$color_fields     = 0;
$angle_fields     = 0;
$custom_fields    = 0;

foreach ( $fields as $field ) {
    if ( empty( $field['enabled'] ) ) {
        continue;
    }
    switch ( $field['type'] ?? '' ) {
        case 'length':
            $length_fields++;
            break;
        case 'color':
            $color_fields++;
            break;
        case 'mitre_angle':
            $angle_fields++;
            break;
        case 'custom':
        case 'quantity':
            $custom_fields++;
            break;
    }
}

// Collect validation warnings
$warnings = array();

// Check if no fields
if ( 0 === $field_count ) {
    $warnings[] = array(
        'type'    => 'error',
        'message' => __( 'Geen velden geconfigureerd. Voeg minimaal één veld toe.', 'bossier-calculator' ),
    );
}

// Check for length field without price_per_mm
if ( $length_fields > 0 && empty( $settings['price_per_mm'] ) ) {
    $warnings[] = array(
        'type'    => 'warning',
        'message' => __( 'Prijs per mm (extra lengte) is niet ingesteld. Langere producten worden mogelijk niet correct berekend.', 'bossier-calculator' ),
    );
}

// Check for color field without colors
foreach ( $fields as $field ) {
    if ( 'color' === ( $field['type'] ?? '' ) && ! empty( $field['enabled'] ) ) {
        if ( empty( $field['colors'] ) ) {
            $warnings[] = array(
                'type'    => 'warning',
                'message' => sprintf(
                    __( 'Kleur veld "%s" heeft geen kleuren. Voeg minimaal één kleur toe.', 'bossier-calculator' ),
                    $field['label'] ?? 'Kleur'
                ),
            );
        }
    }
    if ( 'mitre_angle' === ( $field['type'] ?? '' ) && ! empty( $field['enabled'] ) ) {
        if ( empty( $field['angles'] ) ) {
            $warnings[] = array(
                'type'    => 'warning',
                'message' => sprintf(
                    __( 'Verstekhoek veld "%s" heeft geen opties. Voeg minimaal één hoek toe.', 'bossier-calculator' ),
                    $field['label'] ?? 'Verstekhoek'
                ),
            );
        }
    }
    if ( 'custom' === ( $field['type'] ?? '' ) && ! empty( $field['enabled'] ) ) {
        if ( empty( $field['custom_options'] ) ) {
            $warnings[] = array(
                'type'    => 'warning',
                'message' => sprintf(
                    __( 'Aangepast veld "%s" heeft geen opties. Voeg minimaal één optie toe.', 'bossier-calculator' ),
                    $field['label'] ?? 'Aangepast'
                ),
            );
        }
    }
}

// Check if long surcharge is enabled but threshold is 0
if ( ! empty( $settings['enable_long_surcharge'] ) && empty( $settings['long_surcharge_per_mm'] ) ) {
    $warnings[] = array(
        'type'    => 'warning',
        'message' => __( 'Lange lengte toeslag is ingeschakeld maar de toeslag per mm is 0.', 'bossier-calculator' ),
    );
}

$has_errors   = false;
$has_warnings = false;
foreach ( $warnings as $warning ) {
    if ( 'error' === $warning['type'] ) {
        $has_errors = true;
    } else {
        $has_warnings = true;
    }
}
?>

<div class="bossier-summary-panel">
    <div class="bossier-summary-header">
        <h3><?php esc_html_e( 'Calculator Overzicht', 'bossier-calculator' ); ?></h3>
        <div class="bossier-summary-status">
            <?php if ( $has_errors ) : ?>
                <span class="bossier-status-badge bossier-status-error">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Fouten gevonden', 'bossier-calculator' ); ?>
                </span>
            <?php elseif ( $has_warnings ) : ?>
                <span class="bossier-status-badge bossier-status-warning">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e( 'Waarschuwingen', 'bossier-calculator' ); ?>
                </span>
            <?php else : ?>
                <span class="bossier-status-badge bossier-status-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Gereed', 'bossier-calculator' ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="bossier-summary-grid">
        <!-- Field counts -->
        <div class="bossier-summary-card">
            <div class="bossier-summary-card-icon">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="bossier-summary-card-content">
                <span class="bossier-summary-value"><?php echo esc_html( $field_count ); ?></span>
                <span class="bossier-summary-label"><?php esc_html_e( 'Actieve Velden', 'bossier-calculator' ); ?></span>
            </div>
        </div>

        <!-- Min Length -->
        <div class="bossier-summary-card">
            <div class="bossier-summary-card-icon">
                <span class="dashicons dashicons-editor-expand"></span>
            </div>
            <div class="bossier-summary-card-content">
                <span class="bossier-summary-value"><?php echo esc_html( number_format( $settings['min_length'], 0, ',', '.' ) ); ?> mm</span>
                <span class="bossier-summary-label"><?php esc_html_e( 'Minimum Lengte', 'bossier-calculator' ); ?></span>
            </div>
        </div>

        <!-- Price per mm -->
        <div class="bossier-summary-card">
            <div class="bossier-summary-card-icon">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="bossier-summary-card-content">
                <span class="bossier-summary-value"><?php echo esc_html( $currency_symbol . number_format( $settings['price_per_mm'], 4, ',', '.' ) ); ?></span>
                <span class="bossier-summary-label"><?php esc_html_e( 'Prijs per mm', 'bossier-calculator' ); ?></span>
            </div>
        </div>

        <!-- Long surcharge status -->
        <div class="bossier-summary-card <?php echo ! empty( $settings['enable_long_surcharge'] ) ? 'bossier-summary-card-active' : ''; ?>">
            <div class="bossier-summary-card-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="bossier-summary-card-content">
                <?php if ( ! empty( $settings['enable_long_surcharge'] ) ) : ?>
                    <span class="bossier-summary-value">&gt; <?php echo esc_html( number_format( $settings['long_surcharge_threshold'], 0, ',', '.' ) ); ?> mm</span>
                    <span class="bossier-summary-label"><?php esc_html_e( 'Lange Lengte Toeslag', 'bossier-calculator' ); ?></span>
                <?php else : ?>
                    <span class="bossier-summary-value"><?php esc_html_e( 'Uit', 'bossier-calculator' ); ?></span>
                    <span class="bossier-summary-label"><?php esc_html_e( 'Lange Lengte Toeslag', 'bossier-calculator' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Validation Warnings -->
    <?php if ( ! empty( $warnings ) ) : ?>
        <div class="bossier-validation-warnings">
            <h4><?php esc_html_e( 'Aandachtspunten', 'bossier-calculator' ); ?></h4>
            <ul class="bossier-warnings-list">
                <?php foreach ( $warnings as $warning ) : ?>
                    <li class="bossier-warning-item bossier-warning-<?php echo esc_attr( $warning['type'] ); ?>">
                        <span class="dashicons dashicons-<?php echo 'error' === $warning['type'] ? 'dismiss' : 'flag'; ?>"></span>
                        <?php echo esc_html( $warning['message'] ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Test Mode Button -->
    <div class="bossier-test-mode-section">
        <button type="button" class="button bossier-test-mode-btn" id="bossier-toggle-test-mode">
            <span class="dashicons dashicons-visibility"></span>
            <?php esc_html_e( 'Test Modus', 'bossier-calculator' ); ?>
        </button>
        <span class="bossier-test-mode-hint"><?php esc_html_e( 'Test de calculator met een voorbeeldproduct', 'bossier-calculator' ); ?></span>
    </div>
</div>

<!-- Test Mode Panel (hidden by default) -->
<div class="bossier-test-mode-panel" id="bossier-test-mode-panel" style="display: none;">
    <div class="bossier-test-mode-header">
        <h4><?php esc_html_e( 'Calculator Test Modus', 'bossier-calculator' ); ?></h4>
        <button type="button" class="button bossier-close-test-mode" id="bossier-close-test-mode">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="bossier-test-mode-body">
        <div class="bossier-test-inputs">
            <p>
                <label for="bossier_test_base_price">
                    <?php esc_html_e( 'Product Basisprijs', 'bossier-calculator' ); ?>
                    <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Simuleer de basisprijs van het product (zoals ingesteld in WooCommerce).', 'bossier-calculator' ); ?>">?</span>
                </label>
                <input type="number" id="bossier_test_base_price" value="50" step="0.01" min="0" class="widefat">
            </p>
            <p>
                <label for="bossier_test_length">
                    <?php esc_html_e( 'Test Lengte (mm)', 'bossier-calculator' ); ?>
                    <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Voer een lengte in om de berekening te testen.', 'bossier-calculator' ); ?>">?</span>
                </label>
                <input type="number" id="bossier_test_length" value="<?php echo esc_attr( $settings['min_length'] ); ?>" step="1" min="0" class="widefat">
            </p>
        </div>
        <div class="bossier-test-results">
            <h5><?php esc_html_e( 'Berekend Resultaat', 'bossier-calculator' ); ?></h5>
            <div class="bossier-test-result-item">
                <span class="bossier-test-result-label"><?php esc_html_e( 'Basisprijs (incl. min. lengte)', 'bossier-calculator' ); ?></span>
                <span class="bossier-test-result-value" id="bossier_test_result_base"><?php echo esc_html( $currency_symbol ); ?>50,00</span>
            </div>
            <div class="bossier-test-result-item">
                <span class="bossier-test-result-label"><?php esc_html_e( 'Extra lengte kosten', 'bossier-calculator' ); ?></span>
                <span class="bossier-test-result-value" id="bossier_test_result_extra"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
            </div>
            <div class="bossier-test-result-item bossier-test-result-surcharge" style="display: none;">
                <span class="bossier-test-result-label"><?php esc_html_e( 'Lange lengte toeslag (verborgen)', 'bossier-calculator' ); ?></span>
                <span class="bossier-test-result-value" id="bossier_test_result_surcharge"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
            </div>
            <div class="bossier-test-result-item bossier-test-result-total">
                <span class="bossier-test-result-label"><?php esc_html_e( 'Totaalprijs', 'bossier-calculator' ); ?></span>
                <span class="bossier-test-result-value" id="bossier_test_result_total"><?php echo esc_html( $currency_symbol ); ?>50,00</span>
            </div>
            <div class="bossier-test-result-item">
                <span class="bossier-test-result-label"><?php esc_html_e( 'Gewicht', 'bossier-calculator' ); ?></span>
                <span class="bossier-test-result-value" id="bossier_test_result_weight">0,000 <?php echo esc_html( $weight_unit ); ?></span>
            </div>
        </div>
        <button type="button" class="button button-primary" id="bossier-calculate-test">
            <?php esc_html_e( 'Bereken', 'bossier-calculator' ); ?>
        </button>
    </div>
</div>

<script>
jQuery(function($) {
    var settings = <?php echo wp_json_encode( $settings ); ?>;
    var currencySymbol = '<?php echo esc_js( $currency_symbol ); ?>';
    var weightUnit = '<?php echo esc_js( $weight_unit ); ?>';

    // Toggle test mode
    $('#bossier-toggle-test-mode').on('click', function() {
        $('#bossier-test-mode-panel').slideToggle(200);
    });

    $('#bossier-close-test-mode').on('click', function() {
        $('#bossier-test-mode-panel').slideUp(200);
    });

    // Calculate test
    function calculateTest() {
        var basePrice = parseFloat($('#bossier_test_base_price').val()) || 0;
        var length = parseFloat($('#bossier_test_length').val()) || 0;
        var minLength = parseFloat(settings.min_length) || 1000;
        var pricePerMm = parseFloat(settings.price_per_mm) || 0;
        var weightPerMm = parseFloat(settings.base_weight_per_mm) || 0;
        var enableLongSurcharge = settings.enable_long_surcharge;
        var longThreshold = parseFloat(settings.long_surcharge_threshold) || 1500;
        var longSurchargePerMm = parseFloat(settings.long_surcharge_per_mm) || 0;

        // Calculate extra length
        var extraLength = Math.max(0, length - minLength);
        var extraCost = extraLength * pricePerMm;

        // Calculate long surcharge
        var longSurcharge = 0;
        if (enableLongSurcharge && length > longThreshold) {
            var surchargeLength = length - longThreshold;
            longSurcharge = surchargeLength * longSurchargePerMm;
        }

        // Calculate weight
        var weight = length * weightPerMm;

        // Calculate totals
        var totalPrice = basePrice + extraCost + longSurcharge;

        // Update display
        $('#bossier_test_result_base').text(currencySymbol + formatNumber(basePrice));
        $('#bossier_test_result_extra').text(currencySymbol + formatNumber(extraCost));

        if (enableLongSurcharge) {
            $('.bossier-test-result-surcharge').show();
            $('#bossier_test_result_surcharge').text(currencySymbol + formatNumber(longSurcharge));
        } else {
            $('.bossier-test-result-surcharge').hide();
        }

        $('#bossier_test_result_total').text(currencySymbol + formatNumber(totalPrice));
        $('#bossier_test_result_weight').text(formatNumber(weight, 3) + ' ' + weightUnit);
    }

    function formatNumber(num, decimals) {
        decimals = decimals || 2;
        return num.toLocaleString('nl-NL', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    $('#bossier-calculate-test').on('click', calculateTest);
    $('#bossier_test_base_price, #bossier_test_length').on('input', calculateTest);

    // Initial calculation
    calculateTest();
});
</script>
