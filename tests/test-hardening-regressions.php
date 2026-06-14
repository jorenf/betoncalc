<?php
/**
 * Standalone regression checks for security and calculator hardening.
 *
 * Run: php tests/test-hardening-regressions.php
 */

$pass = 0;
$fail = 0;

function check( string $label, bool $condition, string $detail = '' ): void {
    global $pass, $fail;
    if ( $condition ) {
        echo "[PASS] $label\n";
        $pass++;
    } else {
        echo "[FAIL] $label" . ( $detail ? " - $detail" : '' ) . "\n";
        $fail++;
    }
}

function source_file( string $relative_path ): string {
    $path = dirname( __DIR__ ) . '/' . $relative_path;
    return file_exists( $path ) ? file_get_contents( $path ) : '';
}

$cart_php      = source_file( 'frontend/class-cart.php' );
$admin_js      = source_file( 'assets/js/admin.js' );
$calculator_js = source_file( 'assets/js/calculator.js' );
$pdf_editor    = source_file( 'includes/pdf/class-pdf-template-editor.php' );
$pdf_generator = source_file( 'includes/pdf/class-pdf-generator.php' );
$woopages_php  = source_file( 'includes/woopages/class-woopages-loader.php' );
$btw_module    = source_file( 'includes/btw/class-btw-module.php' );
$vies_php      = source_file( 'includes/btw/class-vies-validator.php' );
$plugin_php    = source_file( 'bossier-calculator-builder.php' );

$plugin_header_version = '';
$asset_version         = '';
if ( preg_match( '/^\s*\*\s*Version:\s*([^\s]+)/m', $plugin_php, $matches ) ) {
    $plugin_header_version = $matches[1];
}
if ( preg_match( "/define\\(\\s*'BOSSIER_CALC_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $plugin_php, $matches ) ) {
    $asset_version = $matches[1];
}

check(
    'Plugin header version matches the frontend asset cache-buster version',
    '' !== $plugin_header_version
        && $plugin_header_version === $asset_version,
    'Header version: ' . $plugin_header_version . ', asset version: ' . $asset_version
);

check(
    'Cart total recalculation converts calculator price before setting WooCommerce price',
    false !== strpos( $cart_php, '$cart_item[\'data\']->set_price( $this->get_exclusive_price( $calculated_price ) );' )
        && false === strpos( $cart_php, '$cart_item[\'data\']->set_price($calculated_price);' )
        && false === strpos( $cart_php, '$cart_item[\'data\']->set_price($inclusive_price);' ),
    'Direct inclusive set_price() call still present.'
);

check(
    'Admin option counters use an undefined guard so zero is a valid counter value',
    false !== strpos( $admin_js, "typeof this.optionCounters[fieldId] === 'undefined'" ),
    'getNextOptionIndex should not use a falsy check.'
);

check(
    'Color option counter derives the next index from existing colors inputs',
    false !== strpos( $admin_js, "collectionName = 'colors'" )
        && false !== strpos( $admin_js, 'getNextOptionIndex(fieldId + \'_color\', $fieldItem)' ),
    'Color counters should continue after existing saved color indices.'
);

check(
    'Option counters reconcile with the current DOM before every new row is added',
    false !== strpos( $admin_js, 'nextExistingIndex' )
        && false !== strpos( $admin_js, 'this.optionCounters[fieldId] < nextExistingIndex' ),
    'A stale option counter can reuse an existing color index.'
);

check(
    'Option counters read indexes from the clicked field context instead of only a global data-field-id lookup',
    false !== strpos( $admin_js, 'getNextOptionIndex: function(fieldId, $fieldContext)' )
        && false !== strpos( $admin_js, '$fieldContext && $fieldContext.length' )
        && false !== strpos( $admin_js, 'getNextOptionIndex(fieldId + \'_color\', $fieldItem)' )
        && false !== strpos( $admin_js, 'getNextOptionIndex(fieldId + \'_length\', $fieldItem)' )
        && false !== strpos( $admin_js, 'getNextOptionIndex(fieldId + \'_custom\', $fieldItem)' ),
    'If fieldId is missing or stale, new rows can still reuse colors[0]/colors[1].'
);

check(
    'Color option add appends directly into the color table and keeps optional admin dependencies guarded',
    false !== strpos( $admin_js, ".closest('.bossier-options-list')" )
        && false !== strpos( $admin_js, ".find('.bossier-color-options-table tbody')" )
        && false !== strpos( $admin_js, 'window.bossierCalculatorAdmin || {}' )
        && false !== strpos( $admin_js, '$.fn.wpColorPicker' )
        && false === strpos( $admin_js, '$button.prev(\'table\').find(\'tbody\').append($row)' ),
    'The color add flow should not depend on a previous sibling table or unguarded globals.'
);

check(
    'Admin submit normalizes option indexes before validation so duplicate names cannot overwrite rows',
    false !== strpos( $admin_js, 'normalizeOptionIndexes: function()' )
        && false !== strpos( $admin_js, 'self.normalizeOptionIndexes();' )
        && false !== strpos( $admin_js, 'normalizeCollectionIndexes($fieldItem, \'colors\'' )
        && false !== strpos( $admin_js, 'normalizeCollectionIndexes($fieldItem, \'fixed_options\'' )
        && false !== strpos( $admin_js, 'normalizeCollectionIndexes($fieldItem, \'custom_options\'' )
        && false !== strpos( $admin_js, 'normalizeMitreGroupIndexes($fieldItem)' )
        && false !== strpos( $admin_js, 'replace(collectionPattern, \'[\' + collectionName + \'][\' + index + \']\')' ),
    'Duplicate option indexes in the submitted form can still collapse earlier rows.'
);

check(
    'Show-when admin dropdowns use real option row classes',
    false !== strpos( $admin_js, '.bossier-color-option-row' )
        && false !== strpos( $admin_js, '.bossier-angle-option-row' )
        && false === strpos( $admin_js, '.bossier-color-item' )
        && false === strpos( $admin_js, '.bossier-custom-option-item' )
        && false === strpos( $admin_js, '.bossier-mitre-angle-item, .bossier-angle-item' ),
    'Stale show_when selectors still present.'
);

check(
    'PDF template editor requires the high-trust template capability helper',
    false !== strpos( $pdf_editor, 'current_user_can_edit_templates' )
        && false === strpos( $pdf_editor, "current_user_can( 'manage_woocommerce' )" )
        && false !== strpos( $pdf_generator, "current_user_can( 'manage_options' )" ),
    'PDF template execution should not be available to manage_woocommerce-only users.'
);

check(
    'WooPages shipping selection validates posted method against available package rates',
    false !== strpos( $woopages_php, 'isset( $package[\'rates\'][ $method_id ] )' )
        && false !== strpos( $woopages_php, '$valid_method' ),
    'Shipping method is still accepted without rate validation.'
);

check(
    'VAT logging masks customer VAT numbers and avoids company names',
        false !== strpos( $btw_module, 'mask_vat_number_for_log' )
        && false === strpos( $btw_module, '\'VAT AJAX: \' . $vat_number' )
        && false === strpos( $btw_module, 'company: ' )
        && false !== strpos( $vies_php, 'mask_vat_number_for_log' )
        && false === strpos( $vies_php, 'VAT validation failed for "%s"' )
        && false === strpos( $vies_php, 'VIES validated %s%s' ),
    'VAT log output still exposes raw VAT or company details.'
);

check(
    'Frontend calculator debug logging is removed',
    false === strpos( $calculator_js, "console.log('Updating display with result:', result);" ),
    'Debug console.log still present.'
);

echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
