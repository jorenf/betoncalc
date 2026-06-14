<?php
/**
 * Standalone regression test for popup settings and persistence assets.
 *
 * Run: php tests/test-popup-settings.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'BOSSIER_CALC_PLUGIN_DIR' ) ) {
    define( 'BOSSIER_CALC_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

$GLOBALS['boost_test_options'] = array();

function add_action() {}
function add_filter() {}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function get_option( $name, $default = false ) { return $GLOBALS['boost_test_options'][ $name ] ?? $default; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, is_array( $args ) ? $args : array() ); }
function __( $text, $domain = null ) { return $text; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_kses_post( $value ) {
    $value = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $value );
    return strip_tags( $value, '<strong><em><br><a><span>' );
}

require_once __DIR__ . '/../includes/class-modules-settings.php';

use Bossier\Calculator\Modules_Settings;

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

$defaults = Modules_Settings::get_settings();

check(
    'Popup is disabled by default',
    isset( $defaults['popup_enabled'] ) && false === $defaults['popup_enabled']
);
check(
    'Popup dismissal duration defaults to 30 days',
    isset( $defaults['popup_storage_days'] ) && 30 === (int) $defaults['popup_storage_days']
);
check(
    'Popup title has a usable default',
    ! empty( $defaults['popup_title'] )
);

$reflection = new ReflectionClass( Modules_Settings::class );
$settings   = $reflection->newInstanceWithoutConstructor();

$sanitized = $settings->sanitize_settings(
    array(
        'popup_enabled'          => '1',
        'popup_badge_label'      => '<b>Levertijd update</b>',
        'popup_title'            => "Momenteel erg druk\nlangere levertijden",
        'popup_info_text'        => 'Levertijd <strong>wijkt af</strong><script>alert(1)</script>',
        'popup_primary_url'      => 'https://example.test/contact?x=1',
        'popup_detail_1_icon'    => 'ti ti-truck-delivery',
        'popup_detail_1_image'   => 'https://example.test/truck.png',
        'popup_storage_days'     => '45',
    )
);

check(
    'Popup boolean setting is sanitized',
    ! empty( $sanitized['popup_enabled'] )
);
check(
    'Popup plain label strips HTML',
    isset( $sanitized['popup_badge_label'] ) && 'Levertijd update' === $sanitized['popup_badge_label'],
    $sanitized['popup_badge_label'] ?? ''
);
check(
    'Popup rich text allows safe emphasis but removes scripts',
    isset( $sanitized['popup_info_text'] )
        && false !== strpos( $sanitized['popup_info_text'], '<strong>wijkt af</strong>' )
        && false === strpos( $sanitized['popup_info_text'], '<script>' ),
    $sanitized['popup_info_text'] ?? ''
);
check(
    'Popup storage duration is configurable',
    isset( $sanitized['popup_storage_days'] ) && 45 === (int) $sanitized['popup_storage_days']
);

check(
    'Popup frontend script exists',
    file_exists( __DIR__ . '/../assets/js/popup.js' )
);

if ( file_exists( __DIR__ . '/../assets/js/popup.js' ) ) {
    $popup_js = file_get_contents( __DIR__ . '/../assets/js/popup.js' );
    check(
        'Popup frontend script persists dismissal choice in localStorage with expiry',
        false !== strpos( $popup_js, 'localStorage' ) && false !== strpos( $popup_js, 'expiresAt' ),
        $popup_js
    );
    check(
        'Popup secondary button always stores dismissal choice',
        false !== strpos( $popup_js, 'function rememberChoice()' )
            && false !== strpos( $popup_js, 'var secondaryButton = popup.querySelector' )
            && false !== strpos( $popup_js, 'closePopup(true)' )
            && false === strpos( $popup_js, '.boost-popup__no-show' )
            && false === strpos( $popup_js, '.checked' ),
        $popup_js
    );
    check(
        'Popup close controls are separate from persistent secondary dismissal',
        false === strpos( $popup_js, ".boost-popup__close, .boost-popup__button--secondary" ),
        $popup_js
    );
}

if ( file_exists( __DIR__ . '/../frontend/class-popup.php' ) ) {
    $popup_php = file_get_contents( __DIR__ . '/../frontend/class-popup.php' );
    check(
        'Popup no-show checkbox is not rendered',
        false === strpos( $popup_php, 'boost-popup__no-show' )
            && false === strpos( $popup_php, 'popup_no_show_label' ),
        $popup_php
    );
}

if ( file_exists( __DIR__ . '/../includes/class-modules-settings.php' ) ) {
    $settings_php = file_get_contents( __DIR__ . '/../includes/class-modules-settings.php' );
    check(
        'Popup no-show setting is no longer registered',
        false === strpos( $settings_php, 'popup_no_show_label' ),
        $settings_php
    );
}

if ( file_exists( __DIR__ . '/../admin/views/modules-settings.php' ) ) {
    $admin_php = file_get_contents( __DIR__ . '/../admin/views/modules-settings.php' );
    check(
        'Popup no-show setting field is not shown in admin',
        false === strpos( $admin_php, 'popup_no_show_label' )
            && false === strpos( $admin_php, 'Niet-meer-tonen label' ),
        $admin_php
    );
}

echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
