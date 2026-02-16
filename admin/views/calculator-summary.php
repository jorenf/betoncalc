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

// Derive legacy summary values from the first enabled dimension field.
// These were previously stored as top-level settings (min_length, price_per_mm,
// base_weight_per_mm) but now live on individual dimension fields.
$summary_min_length      = 0;
$summary_price_per_mm    = 0;
$summary_weight_per_mm   = 0;

foreach ( $fields as $field ) {
	if ( ! empty( $field['enabled'] ) && 'dimension' === ( $field['type'] ?? '' ) ) {
		$summary_min_length    = floatval( $field['threshold'] ?? 0 );
		$summary_price_per_mm  = floatval( $field['price_per_mm'] ?? 0 );
		$summary_weight_per_mm = floatval( $field['weight_per_mm'] ?? 0 );
		break;
	}
}

// Count field types (excluding deprecated length fields)
$color_fields     = 0;
$angle_fields     = 0;
$custom_fields    = 0;
$field_count      = 0;

foreach ( $fields as $field ) {
    if ( empty( $field['enabled'] ) ) {
        continue;
    }
    // Skip deprecated length fields in count
    if ( 'length' === ( $field['type'] ?? '' ) ) {
        continue;
    }
    $field_count++;
    switch ( $field['type'] ?? '' ) {
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

// Check for deprecated length fields
$has_deprecated_length = false;
foreach ( $fields as $field ) {
    if ( 'length' === ( $field['type'] ?? '' ) ) {
        $has_deprecated_length = true;
        break;
    }
}

if ( $has_deprecated_length ) {
    $warnings[] = array(
        'type'    => 'warning',
        'message' => __( 'Deze calculator bevat een oud lengteveld dat niet meer wordt gebruikt. Lengte wordt nu automatisch afgehandeld via de zijbalk instellingen. Het oude veld kan veilig worden verwijderd.', 'bossier-calculator' ),
    );
}

// Check if no fields (excluding deprecated length fields)
$active_non_length_fields = 0;
foreach ( $fields as $field ) {
    if ( ! empty( $field['enabled'] ) && 'length' !== ( $field['type'] ?? '' ) ) {
        $active_non_length_fields++;
    }
}

if ( 0 === $active_non_length_fields && 0 === $field_count ) {
    $warnings[] = array(
        'type'    => 'error',
        'message' => __( 'Geen velden geconfigureerd. Voeg minimaal één veld toe.', 'bossier-calculator' ),
    );
}

// Check if price_per_mm is set for length calculation
if ( empty( $summary_price_per_mm ) ) {
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
        // Check for either legacy angles or new mitre_groups structure
        $has_angles = ! empty( $field['angles'] );
        $has_groups = ! empty( $field['mitre_groups'] );
        if ( ! $has_angles && ! $has_groups ) {
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
                <span class="bossier-summary-value"><?php echo esc_html( number_format( $summary_min_length, 0, ',', '.' ) ); ?> mm</span>
                <span class="bossier-summary-label"><?php esc_html_e( 'Minimum Lengte', 'bossier-calculator' ); ?></span>
            </div>
        </div>

        <!-- Price per mm -->
        <div class="bossier-summary-card">
            <div class="bossier-summary-card-icon">
                <span class="dashicons dashicons-tag"></span>
            </div>
            <div class="bossier-summary-card-content">
                <span class="bossier-summary-value"><?php echo esc_html( $currency_symbol . number_format( $summary_price_per_mm, 4, ',', '.' ) ); ?></span>
                <span class="bossier-summary-label"><?php esc_html_e( 'Prijs per mm', 'bossier-calculator' ); ?></span>
            </div>
        </div>

        <!-- Long surcharge status -->
        <div class="<?php echo esc_attr( 'bossier-summary-card' . ( ! empty( $settings['enable_long_surcharge'] ) ? ' bossier-summary-card-active' : '' ) ); ?>">
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
<?php
// Collect color options for test mode
$test_colors = array();
$test_angles = array();
foreach ( $fields as $field ) {
    if ( empty( $field['enabled'] ) || 'length' === ( $field['type'] ?? '' ) ) {
        continue;
    }
    if ( 'color' === ( $field['type'] ?? '' ) && ! empty( $field['colors'] ) ) {
        foreach ( $field['colors'] as $color ) {
            $test_colors[] = array(
                'name'       => $color['name'] ?? '',
                'surcharge'  => floatval( $color['surcharge'] ?? 0 ),
                'price_type' => $color['price_type'] ?? 'fixed',
                'is_default' => ! empty( $color['is_default'] ),
            );
        }
    }
    if ( 'mitre_angle' === ( $field['type'] ?? '' ) && ! empty( $field['angles'] ) ) {
        foreach ( $field['angles'] as $angle ) {
            $test_angles[] = array(
                'label'     => $angle['label'] ?? '',
                'surcharge' => floatval( $angle['surcharge'] ?? 0 ),
            );
        }
    }
}
?>
<div class="bossier-test-mode-panel" id="bossier-test-mode-panel" style="display: none;">
    <div class="bossier-test-mode-header">
        <h4><?php esc_html_e( 'Calculator Test Modus', 'bossier-calculator' ); ?></h4>
        <button type="button" class="button bossier-close-test-mode" id="bossier-close-test-mode">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>

    <div class="bossier-test-mode-body">
        <div class="bossier-test-sections">
            <!-- SECTION 1: Test Inputs -->
            <div class="bossier-test-section bossier-test-section-inputs">
                <h5 class="bossier-test-section-title">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Test Invoer', 'bossier-calculator' ); ?>
                </h5>
                <div class="bossier-test-inputs-grid">
                    <div class="bossier-test-input-row">
                        <label for="bossier_test_base_price"><?php esc_html_e( 'Product Basisprijs', 'bossier-calculator' ); ?></label>
                        <div class="bossier-test-input-wrap">
                            <span class="bossier-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
                            <input type="number" id="bossier_test_base_price" value="50" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="bossier-test-input-row">
                        <label for="bossier_test_length"><?php esc_html_e( 'Lengte', 'bossier-calculator' ); ?></label>
                        <div class="bossier-test-input-wrap">
                            <input type="number" id="bossier_test_length" value="<?php echo esc_attr( $summary_min_length ); ?>" step="1" min="0">
                            <span class="bossier-input-suffix">mm</span>
                        </div>
                    </div>
                    <?php if ( ! empty( $test_colors ) ) : ?>
                    <div class="bossier-test-input-row">
                        <label for="bossier_test_color"><?php esc_html_e( 'Kleur', 'bossier-calculator' ); ?></label>
                        <select id="bossier_test_color">
                            <?php foreach ( $test_colors as $idx => $color ) : ?>
                            <option value="<?php echo esc_attr( $idx ); ?>"
                                    data-surcharge="<?php echo esc_attr( $color['surcharge'] ); ?>"
                                    data-price-type="<?php echo esc_attr( $color['price_type'] ); ?>"
                                    <?php selected( $color['is_default'] ); ?>>
                                <?php echo esc_html( $color['name'] ); ?>
                                <?php if ( ! $color['is_default'] && $color['surcharge'] > 0 ) : ?>
                                    (<?php echo 'percentage' === $color['price_type'] ? '+' . $color['surcharge'] . '%' : '+' . $currency_symbol . number_format( $color['surcharge'], 2, ',', '.' ); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $test_angles ) ) : ?>
                    <div class="bossier-test-input-row">
                        <label for="bossier_test_angle"><?php esc_html_e( 'Verstekhoek', 'bossier-calculator' ); ?></label>
                        <select id="bossier_test_angle">
                            <?php foreach ( $test_angles as $idx => $angle ) : ?>
                            <option value="<?php echo esc_attr( $idx ); ?>"
                                    data-surcharge="<?php echo esc_attr( $angle['surcharge'] ); ?>">
                                <?php echo esc_html( $angle['label'] ); ?>
                                <?php if ( $angle['surcharge'] > 0 ) : ?>
                                    (+<?php echo esc_html( $currency_symbol . number_format( $angle['surcharge'], 2, ',', '.' ) ); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="bossier-test-input-row">
                        <label for="bossier_test_quantity"><?php esc_html_e( 'Aantal', 'bossier-calculator' ); ?></label>
                        <div class="bossier-test-input-wrap">
                            <input type="number" id="bossier_test_quantity" value="1" step="1" min="1" max="100">
                            <span class="bossier-input-suffix">stuks</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Calculation Breakdown -->
            <div class="bossier-test-section bossier-test-section-breakdown">
                <h5 class="bossier-test-section-title">
                    <span class="dashicons dashicons-calculator"></span>
                    <?php esc_html_e( 'Prijsopbouw', 'bossier-calculator' ); ?>
                </h5>
                <div class="bossier-test-breakdown-list">
                    <div class="bossier-breakdown-row">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Basisprijs (incl. min. lengte)', 'bossier-calculator' ); ?></span>
                        <span class="bossier-breakdown-value" id="bossier_result_base"><?php echo esc_html( $currency_symbol ); ?>50,00</span>
                    </div>
                    <div class="bossier-breakdown-row" id="bossier_row_extra_length">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Extra lengte', 'bossier-calculator' ); ?> <small id="bossier_extra_length_info"></small></span>
                        <span class="bossier-breakdown-value" id="bossier_result_extra"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
                    </div>
                    <div class="bossier-breakdown-row bossier-breakdown-surcharge" id="bossier_row_long_surcharge" style="display: none;">
                        <span class="bossier-breakdown-label">
                            <?php esc_html_e( 'Lange lengte toeslag', 'bossier-calculator' ); ?>
                            <small class="bossier-hidden-label"><?php esc_html_e( '(verborgen voor klant)', 'bossier-calculator' ); ?></small>
                        </span>
                        <span class="bossier-breakdown-value" id="bossier_result_surcharge"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
                    </div>
                    <?php if ( ! empty( $test_colors ) ) : ?>
                    <div class="bossier-breakdown-row" id="bossier_row_color">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Kleur toeslag', 'bossier-calculator' ); ?></span>
                        <span class="bossier-breakdown-value" id="bossier_result_color"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $test_angles ) ) : ?>
                    <div class="bossier-breakdown-row" id="bossier_row_angle">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Verstekhoek toeslag', 'bossier-calculator' ); ?></span>
                        <span class="bossier-breakdown-value" id="bossier_result_angle"><?php echo esc_html( $currency_symbol ); ?>0,00</span>
                    </div>
                    <?php endif; ?>
                    <div class="bossier-breakdown-row bossier-breakdown-subtotal">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Subtotaal (per stuk)', 'bossier-calculator' ); ?></span>
                        <span class="bossier-breakdown-value" id="bossier_result_subtotal"><?php echo esc_html( $currency_symbol ); ?>50,00</span>
                    </div>
                    <div class="bossier-breakdown-row" id="bossier_row_quantity">
                        <span class="bossier-breakdown-label"><?php esc_html_e( 'Aantal', 'bossier-calculator' ); ?></span>
                        <span class="bossier-breakdown-value" id="bossier_result_quantity">× 1</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Final Result -->
            <div class="bossier-test-section bossier-test-section-result">
                <h5 class="bossier-test-section-title">
                    <span class="dashicons dashicons-awards"></span>
                    <?php esc_html_e( 'Eindresultaat', 'bossier-calculator' ); ?>
                </h5>
                <div class="bossier-test-final-results">
                    <div class="bossier-final-row bossier-final-price">
                        <span class="bossier-final-label"><?php esc_html_e( 'Totaalprijs', 'bossier-calculator' ); ?></span>
                        <span class="bossier-final-value" id="bossier_result_total"><?php echo esc_html( $currency_symbol ); ?>50,00</span>
                    </div>
                    <div class="bossier-final-row bossier-final-weight">
                        <span class="bossier-final-label"><?php esc_html_e( 'Totaal gewicht', 'bossier-calculator' ); ?></span>
                        <span class="bossier-final-value" id="bossier_result_weight">0,000 <?php echo esc_html( $weight_unit ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var settings = <?php echo wp_json_encode( $settings ); ?>;
    // Derived from first dimension field (legacy settings no longer exist in settings object)
    settings.min_length = <?php echo wp_json_encode( $summary_min_length ); ?>;
    settings.price_per_mm = <?php echo wp_json_encode( $summary_price_per_mm ); ?>;
    settings.base_weight_per_mm = <?php echo wp_json_encode( $summary_weight_per_mm ); ?>;
    var currencySymbol = <?php echo wp_json_encode( $currency_symbol ); ?>;
    var weightUnit = <?php echo wp_json_encode( $weight_unit ); ?>;

    // Toggle test mode
    $('#bossier-toggle-test-mode').on('click', function() {
        $('#bossier-test-mode-panel').slideToggle(200);
    });

    $('#bossier-close-test-mode').on('click', function() {
        $('#bossier-test-mode-panel').slideUp(200);
    });

    // Calculate test - live recalculation
    function calculateTest() {
        var basePrice = parseFloat($('#bossier_test_base_price').val()) || 0;
        var length = parseFloat($('#bossier_test_length').val()) || 0;
        var quantity = parseInt($('#bossier_test_quantity').val()) || 1;
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

        // Calculate color surcharge
        var colorSurcharge = 0;
        var $colorSelect = $('#bossier_test_color');
        if ($colorSelect.length) {
            var $selectedColor = $colorSelect.find(':selected');
            var colorSurchargeVal = parseFloat($selectedColor.data('surcharge')) || 0;
            var colorPriceType = $selectedColor.data('price-type') || 'fixed';

            if (colorPriceType === 'percentage') {
                // Percentage of gray price (base + extra length)
                var grayPrice = basePrice + extraCost;
                colorSurcharge = grayPrice * (colorSurchargeVal / 100);
            } else {
                colorSurcharge = colorSurchargeVal;
            }
        }

        // Calculate angle surcharge
        var angleSurcharge = 0;
        var $angleSelect = $('#bossier_test_angle');
        if ($angleSelect.length) {
            angleSurcharge = parseFloat($angleSelect.find(':selected').data('surcharge')) || 0;
        }

        // Calculate weight
        var weight = length * weightPerMm;

        // Calculate subtotal (per unit)
        var subtotal = basePrice + extraCost + longSurcharge + colorSurcharge + angleSurcharge;

        // Calculate totals
        var totalPrice = subtotal * quantity;
        var totalWeight = weight * quantity;

        // Update display - Base
        $('#bossier_result_base').text(currencySymbol + formatNumber(basePrice));

        // Update display - Extra length
        if (extraLength > 0) {
            $('#bossier_extra_length_info').text('(' + formatNumber(extraLength, 0) + ' mm × ' + currencySymbol + formatNumber(pricePerMm, 4) + ')');
        } else {
            $('#bossier_extra_length_info').text('');
        }
        $('#bossier_result_extra').text(currencySymbol + formatNumber(extraCost));

        // Update display - Long surcharge
        if (enableLongSurcharge) {
            $('#bossier_row_long_surcharge').show();
            $('#bossier_result_surcharge').text(currencySymbol + formatNumber(longSurcharge));
        } else {
            $('#bossier_row_long_surcharge').hide();
        }

        // Update display - Color surcharge
        if ($colorSelect.length) {
            $('#bossier_result_color').text(currencySymbol + formatNumber(colorSurcharge));
        }

        // Update display - Angle surcharge
        if ($angleSelect.length) {
            $('#bossier_result_angle').text(currencySymbol + formatNumber(angleSurcharge));
        }

        // Update display - Subtotal
        $('#bossier_result_subtotal').text(currencySymbol + formatNumber(subtotal));

        // Update display - Quantity
        $('#bossier_result_quantity').text('× ' + quantity);

        // Update display - Final results
        $('#bossier_result_total').text(currencySymbol + formatNumber(totalPrice));
        $('#bossier_result_weight').text(formatNumber(totalWeight, 3) + ' ' + weightUnit);
    }

    function formatNumber(num, decimals) {
        decimals = decimals !== undefined ? decimals : 2;
        return num.toLocaleString('nl-NL', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // Bind to all test inputs for live recalculation
    $('#bossier_test_base_price, #bossier_test_length, #bossier_test_quantity').on('input', calculateTest);
    $('#bossier_test_color, #bossier_test_angle').on('change', calculateTest);

    // Initial calculation
    calculateTest();
});
</script>
