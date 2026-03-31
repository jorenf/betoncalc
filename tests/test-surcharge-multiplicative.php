<?php
/**
 * Standalone validation script for multiplicative surcharge calculations.
 *
 * Run: php tests/test-surcharge-multiplicative.php
 *
 * No framework required — uses PHP's built-in assert().
 */

// Minimal WordPress stub so the class file can be loaded standalone.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/shipping/class-surcharge-calculator.php';

use Bossier\Calculator\Shipping\Surcharge_Calculator;

$pass = 0;
$fail = 0;

function check( string $label, bool $condition, string $detail = '' ): void {
    global $pass, $fail;
    if ( $condition ) {
        echo "[PASS] $label\n";
        $pass++;
    } else {
        echo "[FAIL] $label" . ( $detail ? " — $detail" : '' ) . "\n";
        $fail++;
    }
}

// ---------------------------------------------------------------------------
// Test 1: Acceptance criterion — €100 with diesel=38%, inpak=12%, toll=7%
// Expected: 100 * 1.38 * 1.12 * 1.07 = 165.5112
// ---------------------------------------------------------------------------
$diesel_pct  = 38;
$inpak_pct   = 12.0;
$toll_pct    = 7.0;
$basis       = 100.0;

$combined_diesel_inpak = Surcharge_Calculator::bereken_totale_toeslag( $diesel_pct, $inpak_pct );
// Apply toll multiplicatively (matches class-shipping-calculator.php logic).
$effective = ( ( 1.0 + $combined_diesel_inpak / 100.0 ) * ( 1.0 + $toll_pct / 100.0 ) - 1.0 ) * 100.0;
$result    = $basis * ( 1.0 + $effective / 100.0 );

// 100 * 1.38 * 1.12 * 1.07 = 165.3792
check(
    'Acceptance: €100 × 1.38 × 1.12 × 1.07 ≈ €165.38',
    abs( $result - 165.3792 ) < 0.01,
    "got $result"
);

// Verify the old additive approach would have given €157 (confirming we changed behavior).
check(
    'Old additive approach would give €157 (not our result)',
    abs( $result - 157.0 ) > 0.5,
    "result=$result should differ from 157"
);

// ---------------------------------------------------------------------------
// Test 2: bereken_totale_toeslag — diesel + inpak multiplicative
// (1.38 * 1.12 - 1) * 100 = 54.56%
// ---------------------------------------------------------------------------
$combined = Surcharge_Calculator::bereken_totale_toeslag( 38, 12.0 );
check(
    'bereken_totale_toeslag(38, 12) = 54.56%',
    abs( $combined - 54.56 ) < 0.001,
    "got $combined"
);

// ---------------------------------------------------------------------------
// Test 3: Zero surcharges → 0% combined, base price unchanged
// ---------------------------------------------------------------------------
$zero = Surcharge_Calculator::bereken_totale_toeslag( 0, 0.0 );
check(
    'bereken_totale_toeslag(0, 0) = 0%',
    $zero === 0.0,
    "got $zero"
);

$prijs = Surcharge_Calculator::bereken_prijs_incl_btw( 100.0, 0.0 );
check(
    'bereken_prijs_incl_btw(100, 0%) = €121.00 (only BTW)',
    abs( $prijs - 121.0 ) < 0.01,
    "got $prijs"
);

// ---------------------------------------------------------------------------
// Test 4: Negative diesel surcharge — price-drop scenario
// diesel=-5%, inpak=12% → (0.95 * 1.12 - 1) * 100 = 6.4%
// ---------------------------------------------------------------------------
$neg = Surcharge_Calculator::bereken_totale_toeslag( -5, 12.0 );
$expected_neg = ( 0.95 * 1.12 - 1.0 ) * 100.0; // 6.4
check(
    'bereken_totale_toeslag(-5, 12) = 6.4% (negative diesel)',
    abs( $neg - $expected_neg ) < 0.001,
    "got $neg, expected $expected_neg"
);

// ---------------------------------------------------------------------------
// Test 5: bereken_prijs_incl_btw with known combined percentage
// basis=100, effective=54.56% → 100 * 1.5456 * 1.21 = 187.02
// ---------------------------------------------------------------------------
$prijs2 = Surcharge_Calculator::bereken_prijs_incl_btw( 100.0, 54.56 );
$expected_prijs2 = round( 100.0 * 1.5456 * 1.21, 2 );
check(
    "bereken_prijs_incl_btw(100, 54.56) = €$expected_prijs2",
    abs( $prijs2 - $expected_prijs2 ) < 0.01,
    "got $prijs2"
);

// ---------------------------------------------------------------------------
// Test 6: Single surcharge — one percentage alone must equal itself
// bereken_totale_toeslag(20, 0) = 20%  (identity for second factor)
// ---------------------------------------------------------------------------
$single = Surcharge_Calculator::bereken_totale_toeslag( 20, 0.0 );
check(
    'bereken_totale_toeslag(20, 0) = 20% (identity)',
    abs( $single - 20.0 ) < 0.001,
    "got $single"
);

// ---------------------------------------------------------------------------
// Test 7: bereken_alles — auto_totale_toeslag uses multiplicative formula.
// Use diesel price 2.56 → floor((2.56-1.03)/0.04) = floor(38.25) = 38 (no float edge case).
// ---------------------------------------------------------------------------
$alles = Surcharge_Calculator::bereken_alles( 100.0, 2.56, 12.0 );
check(
    'bereken_alles auto_totale_toeslag = 54.56% (diesel=38%, inpak=12%)',
    abs( $alles['auto_totale_toeslag'] - 54.56 ) < 0.01,
    "got {$alles['auto_totale_toeslag']}"
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n$pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
