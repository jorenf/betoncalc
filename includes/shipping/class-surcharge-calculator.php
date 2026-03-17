<?php
/**
 * Surcharge Calculator.
 *
 * Handles diesel toeslag, inpak toeslag, and BTW conversion for shipping prices
 * that are entered excl. BTW.
 *
 * Calculation formula:
 *   prijs_incl = max(0, basis_excl * (1 + totale_toeslag/100)) * (1 + BTW/100)
 *
 * Surcharge order (all applied on excl. BTW price first, BTW added last):
 *   1. Diesel toeslag
 *   2. Inpak toeslag
 *   3. BTW (21%)
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Surcharge_Calculator class.
 *
 * All methods are static and stateless — safe to call anywhere.
 */
class Surcharge_Calculator {

    /**
     * BTW percentage applied to excl. BTW prices.
     *
     * @var float
     */
    const BTW_PERCENTAGE = 21.0;

    /**
     * Diesel price threshold below which surcharge is 0%.
     *
     * @var float
     */
    const DIESEL_THRESHOLD = 1.03;

    /**
     * Price step (EUR/liter) that corresponds to 1% surcharge.
     *
     * @var float
     */
    const DIESEL_STEP = 0.04;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Calculate diesel surcharge percentage.
     *
     * Uses floor() so half-percentages are never charged.
     * Returns 0 when diesel_price is at or below DIESEL_THRESHOLD.
     * Returns a NEGATIVE integer when diesel_price is below the threshold
     * (falling prices reduce the surcharge below zero).
     * Diesel price is clamped to ≥ 0 to prevent nonsensical inputs.
     *
     * Examples:
     *   2.20 → floor((2.20 - 1.03) / 0.04) = floor(29.25) = 29
     *   1.03 → 0
     *   0.85 → floor((0.85 - 1.03) / 0.04) = floor(-4.5) = -5
     *
     * @param  float $diesel_price Current diesel price in EUR/liter.
     * @return int   Surcharge percentage (whole number, can be negative).
     */
    public static function bereken_diesel_toeslag( float $diesel_price ): int {
        // Guard: negative diesel prices are physically impossible.
        $diesel_price = max( 0.0, $diesel_price );

        $verschil = $diesel_price - self::DIESEL_THRESHOLD;

        // At threshold: exactly 0 — avoid floating-point edge cases.
        if ( abs( $verschil ) < 1e-9 ) {
            return 0;
        }

        return (int) floor( $verschil / self::DIESEL_STEP );
    }

    /**
     * Combine diesel and inpak surcharge into a single total percentage.
     *
     * @param  int|float $diesel_percentage Diesel surcharge % (may be negative).
     * @param  float     $inpak_percentage  Inpak surcharge %.
     * @return float     Combined surcharge percentage.
     */
    public static function bereken_totale_toeslag( $diesel_percentage, float $inpak_percentage ): float {
        return (float) $diesel_percentage + $inpak_percentage;
    }

    /**
     * Convert a base price (excl. BTW) to a final price (incl. BTW) after applying
     * the given surcharge percentage.
     *
     * Formula: round( max(0, basis_excl * (1 + toeslag/100)) * (1 + BTW/100), 2 )
     *
     * @param  float $basisprijs_excl    Base price excluding BTW. Clamped to ≥ 0.
     * @param  float $toeslag_percentage Combined surcharge % (can be negative).
     * @return float Final price including BTW, rounded to 2 decimals.
     */
    public static function bereken_prijs_incl_btw( float $basisprijs_excl, float $toeslag_percentage ): float {
        $basisprijs_excl      = max( 0.0, $basisprijs_excl );
        $prijs_na_toeslag     = $basisprijs_excl * ( 1.0 + $toeslag_percentage / 100.0 );
        // Prevent negative shipping price even with extreme negative surcharges.
        $prijs_na_toeslag     = max( 0.0, $prijs_na_toeslag );
        $prijs_incl           = $prijs_na_toeslag * ( 1.0 + self::BTW_PERCENTAGE / 100.0 );

        return round( $prijs_incl, 2 );
    }

    /**
     * Full surcharge breakdown: calculate diesel %, combined %, and final price.
     *
     * Optionally accepts a manual override percentage that replaces the auto-
     * calculated total surcharge (diesel + inpak). The override does NOT affect
     * BTW — BTW is always applied on top.
     *
     * @param  float      $basisprijs_excl  Base price excl. BTW. Must be ≥ 0.
     * @param  float      $diesel_price     Current diesel price EUR/liter.
     * @param  float      $inpak_percentage Inpak surcharge %.
     * @param  float|null $override_pct     Manual override for total surcharge %;
     *                                      null = use auto-calculated value.
     * @return array {
     *     @type int   $diesel_percentage         Diesel surcharge % (floor, may be negative).
     *     @type float $inpak_percentage           Inpak surcharge %.
     *     @type float $auto_totale_toeslag        Auto-calculated total (diesel + inpak).
     *     @type float $totale_toeslag             Effective total (override or auto).
     *     @type bool  $is_override                True when an override is active.
     *     @type float $prijs_excl_na_toeslag      Price excl. BTW after surcharges.
     *     @type float $prijs_incl_btw             Final price incl. BTW.
     * }
     */
    public static function bereken_alles(
        float $basisprijs_excl,
        float $diesel_price,
        float $inpak_percentage,
        ?float $override_pct = null
    ): array {
        $diesel_pct        = self::bereken_diesel_toeslag( $diesel_price );
        $auto_totaal       = self::bereken_totale_toeslag( $diesel_pct, $inpak_percentage );
        $is_override       = ( null !== $override_pct );
        $totale_toeslag    = $is_override ? $override_pct : $auto_totaal;

        $basisprijs_excl        = max( 0.0, $basisprijs_excl );
        $prijs_excl_na_toeslag  = max( 0.0, $basisprijs_excl * ( 1.0 + $totale_toeslag / 100.0 ) );
        $prijs_incl_btw         = round( $prijs_excl_na_toeslag * ( 1.0 + self::BTW_PERCENTAGE / 100.0 ), 2 );

        return array(
            'diesel_percentage'      => $diesel_pct,
            'inpak_percentage'       => $inpak_percentage,
            'auto_totale_toeslag'    => $auto_totaal,
            'totale_toeslag'         => $totale_toeslag,
            'is_override'            => $is_override,
            'prijs_excl_na_toeslag'  => round( $prijs_excl_na_toeslag, 4 ),
            'prijs_incl_btw'         => $prijs_incl_btw,
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers (used by Shipping_Calculator)
    // -------------------------------------------------------------------------

    /**
     * Resolve effective total surcharge percentage from plugin settings.
     *
     * If a manual override is stored in settings and is non-null, that value
     * is returned; otherwise diesel + inpak are summed automatically.
     *
     * @param  array $settings Plugin settings array from Modules_Settings::get_settings().
     * @return float Effective surcharge percentage.
     */
    public static function get_effective_surcharge( array $settings ): float {
        $override = $settings['shipping_surcharge_override'] ?? null;

        // Explicit numeric override (including 0) takes precedence.
        if ( null !== $override && '' !== (string) $override ) {
            return (float) $override;
        }

        $diesel_price = (float) ( $settings['shipping_diesel_price'] ?? self::DIESEL_THRESHOLD );
        $inpak_pct    = (float) ( $settings['shipping_inpak_percentage'] ?? 12.0 );
        $diesel_pct   = self::bereken_diesel_toeslag( $diesel_price );

        return self::bereken_totale_toeslag( $diesel_pct, $inpak_pct );
    }
}
