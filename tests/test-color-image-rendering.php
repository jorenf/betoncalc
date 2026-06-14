<?php
/**
 * Standalone regression test for color option images on product pages.
 *
 * Run: php tests/test-color-image-rendering.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $value ) {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $value ) {
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $value ) {
        return filter_var( (string) $value, FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $value, $domain = null ) {
        return esc_html( $value );
    }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $value, $domain = null ) {
        echo esc_html( $value );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $value ) {
        return (string) $value;
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $value ) {
        return strip_tags( (string) $value );
    }
}

if ( ! function_exists( 'wc_price' ) ) {
    function wc_price( $value ) {
        return 'EUR ' . number_format( (float) $value, 2, '.', '' );
    }
}

require_once __DIR__ . '/../frontend/class-display.php';

use Bossier\Calculator\Frontend\Display;

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

function render_color_field_for_test( array $field ): string {
    $method = new ReflectionMethod( Display::class, 'render_color_field' );
    $method->setAccessible( true );

    ob_start();
    $method->invoke( null, 'kleur', $field, 'bossier_calc_kleur' );
    return ob_get_clean();
}

$with_images = render_color_field_for_test(
    array(
        'input_type' => 'swatch',
        'required'   => true,
        'colors'     => array(
            array(
                'name'       => 'Grijs',
                'hex'        => '#808080',
                'image'      => 'https://example.test/grijs.jpg',
                'surcharge'  => 0,
                'price_type' => 'fixed',
                'is_default' => true,
            ),
            array(
                'name'       => 'Antraciet',
                'hex'        => '#222222',
                'image'      => 'https://example.test/antraciet.jpg',
                'surcharge'  => 12.5,
                'price_type' => 'fixed',
                'is_default' => false,
            ),
        ),
    )
);

check(
    'Color field with configured images renders an image dropdown',
    false !== strpos( $with_images, 'bs-calc__image-dropdown' ),
    $with_images
);
check(
    'Color image dropdown includes the configured default image',
    false !== strpos( $with_images, 'https://example.test/grijs.jpg' ),
    $with_images
);
check(
    'Color image dropdown stores the selection in the existing hidden value class',
    false !== strpos( $with_images, 'bs-calc__image-dropdown-value' ),
    $with_images
);
check(
    'Color image dropdown exposes preview metadata and selected state for shoppers',
    false !== strpos( $with_images, 'bs-calc__preview-thumb' )
        && false !== strpos( $with_images, 'data-preview-label=' )
        && false !== strpos( $with_images, 'aria-selected="true"' )
        && false !== strpos( $with_images, 'aria-expanded="false"' ),
    $with_images
);

$without_default = render_color_field_for_test(
    array(
        'input_type' => 'swatch',
        'colors'     => array(
            array(
                'name'       => 'Grijs',
                'hex'        => '#808080',
                'image'      => 'https://example.test/grijs.jpg',
                'surcharge'  => 0,
                'price_type' => 'fixed',
            ),
            array(
                'name'       => 'Zwart',
                'hex'        => '#000000',
                'image'      => '',
                'surcharge'  => 5,
                'price_type' => 'fixed',
            ),
        ),
    )
);

check(
    'Color field without explicit default falls back to the first color on the frontend',
    false !== strpos( $without_default, 'value="0"' )
        && false !== strpos( $without_default, 'https://example.test/grijs.jpg' )
        && false !== strpos( $without_default, 'selected' ),
    $without_default
);

$without_images = render_color_field_for_test(
    array(
        'input_type' => 'dropdown',
        'colors'     => array(
            array(
                'name'       => 'Grijs',
                'hex'        => '#808080',
                'image'      => '',
                'surcharge'  => 0,
                'price_type' => 'fixed',
                'is_default' => true,
            ),
        ),
    )
);

check(
    'Color field without images keeps the regular dropdown fallback',
    false === strpos( $without_images, 'bs-calc__image-dropdown' ) && false !== strpos( $without_images, '<select' ),
    $without_images
);

$calculator_js = file_get_contents( __DIR__ . '/../assets/js/calculator.js' );
$color_case_pos = strpos( $calculator_js, "case 'color'" );
$color_case     = false === $color_case_pos ? '' : substr( $calculator_js, $color_case_pos, 500 );

check(
    'Frontend JS reads color image dropdown values',
    false !== strpos( $color_case, 'bs-calc__image-dropdown-value' ),
    $color_case
);

check(
    'Frontend JS uses the shared image preview for color thumbnails',
    false !== strpos( $calculator_js, 'bindImageHoverPreview' )
        && false !== strpos( $calculator_js, '.bs-calc__preview-thumb' )
        && false !== strpos( $calculator_js, 'data-preview-label' ),
    $calculator_js
);

check(
    'Frontend JS improves image dropdown keyboard and selected state handling',
    false !== strpos( $calculator_js, 'aria-expanded' )
        && false !== strpos( $calculator_js, 'aria-selected' )
        && false !== strpos( $calculator_js, '.bs-calc__image-dropdown-selected' )
        && false !== strpos( $calculator_js, 'keydown' ),
    $calculator_js
);

$frontend_css = file_get_contents( __DIR__ . '/../assets/css/frontend.css' );

check(
    'Frontend CSS styles the shared preview and color image dropdown clearly',
    false !== strpos( $frontend_css, '.bs-calc__image-preview' )
        && false !== strpos( $frontend_css, '.bs-calc__image-preview-caption' )
        && false !== strpos( $frontend_css, '.bs-calc__color-image-dropdown' ),
    $frontend_css
);

echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
