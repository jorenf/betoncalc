<?php
/**
 * Shipping log viewer page.
 *
 * Only accessible to administrators (manage_options).
 * Shows entries from the active day's log file with session-level filtering.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'U heeft geen toegang tot deze pagina.', 'bossier-calculator' ) );
}

use Bossier\Calculator\Shipping\Shipping_Logger;

// Resolve filter params.
$filter_session = isset( $_GET['session'] ) ? sanitize_hex_color_no_hash( sanitize_text_field( wp_unslash( $_GET['session'] ) ) ) : '';
$entry_limit    = 300;

// Retrieve log data.
$entries  = Shipping_Logger::get_logs( $entry_limit, $filter_session );
$sessions = Shipping_Logger::get_sessions();

// Page URLs.
$settings_url  = admin_url( 'edit.php?post_type=bossier_calculator&page=boost-modules&tab=shipping' );
$base_page_url = admin_url( 'edit.php?post_type=bossier_calculator&page=boost-shipping-logs' );

// Friendly event labels.
$event_labels = array(
    'calculate_start'      => __( 'Berekening gestart', 'bossier-calculator' ),
    'zone_lookup_start'    => __( 'Zone zoeken gestart', 'bossier-calculator' ),
    'zone_lookup_result'   => __( 'Zone zoek resultaat', 'bossier-calculator' ),
    'zone_not_found'       => __( 'Zone niet gevonden', 'bossier-calculator' ),
    'zone_matched'         => __( 'Zone gevonden', 'bossier-calculator' ),
    'zone_prices'          => __( 'Zoneprijzen geladen', 'bossier-calculator' ),
    'cart_analysis'        => __( 'Winkelwagen analyse', 'bossier-calculator' ),
    'pallet_cost'          => __( 'Palletverzendkosten', 'bossier-calculator' ),
    'loose_cost'           => __( 'Losse verzendkosten', 'bossier-calculator' ),
    'oversized_surcharge'  => __( 'Toeslag lang product', 'bossier-calculator' ),
    'surcharge_calculation'=> __( 'Toeslag berekening', 'bossier-calculator' ),
    'cost_fallback'        => __( 'Fallback kosten', 'bossier-calculator' ),
    'no_rates_available'   => __( 'Geen tarieven beschikbaar', 'bossier-calculator' ),
    'calculate_result'     => __( 'Einresultaat', 'bossier-calculator' ),
);

// Level badge CSS.
$level_class = array(
    'info'    => 'boost-log-level-info',
    'warning' => 'boost-log-level-warning',
    'error'   => 'boost-log-level-error',
);

?>
<div class="wrap boost-shipping-logs-page">
    <h1>
        <span class="dashicons dashicons-list-view" style="font-size:28px;width:28px;height:28px;margin-right:8px;"></span>
        <?php esc_html_e( 'Boost Shipping — Debug Logs', 'bossier-calculator' ); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary">
            &larr; <?php esc_html_e( 'Terug naar instellingen', 'bossier-calculator' ); ?>
        </a>
    </p>

    <?php if ( ! Shipping_Logger::is_enabled() ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Debug logging is uitgeschakeld.', 'bossier-calculator' ); ?></strong>
                <?php esc_html_e( 'Schakel het in via de Verzending-instellingen om logs te genereren.', 'bossier-calculator' ); ?>
                <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Instellingen openen', 'bossier-calculator' ); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <!-- Toolbar: session filter + clear button -->
    <div class="boost-log-toolbar" style="display:flex;align-items:center;gap:12px;margin:15px 0;flex-wrap:wrap;">

        <!-- Session filter -->
        <form method="get" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="post_type" value="bossier_calculator">
            <input type="hidden" name="page" value="boost-shipping-logs">
            <label for="boost-log-session-filter" style="font-weight:600;">
                <?php esc_html_e( 'Sessie:', 'bossier-calculator' ); ?>
            </label>
            <select id="boost-log-session-filter" name="session" onchange="this.form.submit()">
                <option value=""><?php esc_html_e( '— Alle sessies —', 'bossier-calculator' ); ?></option>
                <?php foreach ( $sessions as $sid ) : ?>
                    <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $filter_session, $sid ); ?>>
                        <?php echo esc_html( $sid ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ( $filter_session ) : ?>
                <a href="<?php echo esc_url( $base_page_url ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Filter wissen', 'bossier-calculator' ); ?>
                </a>
            <?php endif; ?>
        </form>

        <div style="flex:1;"></div>

        <!-- Clear logs button -->
        <button type="button" id="boost-clear-logs-btn" class="button" style="color:#cc1818;border-color:#cc1818;">
            <span class="dashicons dashicons-trash" style="margin-top:3px;"></span>
            <?php esc_html_e( 'Alle logs wissen', 'bossier-calculator' ); ?>
        </button>
        <span id="boost-clear-logs-feedback" style="display:none;font-weight:600;"></span>
    </div>

    <!-- Log count summary -->
    <p style="color:#666;margin-bottom:10px;">
        <?php
        if ( $filter_session ) {
            printf(
                /* translators: 1: number of entries, 2: session id */
                esc_html__( '%1$d log-regels voor sessie %2$s', 'bossier-calculator' ),
                count( $entries ),
                '<code>' . esc_html( $filter_session ) . '</code>'
            );
        } else {
            printf(
                /* translators: %d: number of entries */
                esc_html__( '%d log-regels (nieuwste eerst)', 'bossier-calculator' ),
                count( $entries )
            );
        }
        ?>
    </p>

    <?php if ( empty( $entries ) ) : ?>
        <div class="boost-log-empty" style="padding:40px;text-align:center;background:#fff;border:1px solid #e0e0e0;border-radius:4px;">
            <span class="dashicons dashicons-info" style="font-size:48px;width:48px;height:48px;color:#ccc;display:block;margin:0 auto 15px;"></span>
            <h3 style="color:#999;margin:0 0 8px;"><?php esc_html_e( 'Geen logs gevonden', 'bossier-calculator' ); ?></h3>
            <p style="color:#bbb;margin:0;">
                <?php esc_html_e( 'Voer een verzendkostenberekening uit om logs te genereren (checkout of winkelwagen bezoeken).', 'bossier-calculator' ); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="boost-log-entries">
            <?php
            $current_session = null;
            foreach ( $entries as $entry ) :
                $sid       = $entry['session_id'] ?? '—';
                $event     = $entry['event'] ?? 'unknown';
                $level     = $entry['level'] ?? 'info';
                $timestamp = $entry['timestamp'] ?? '';
                $data      = $entry['data'] ?? array();
                $label     = $event_labels[ $event ] ?? esc_html( $event );

                // Print a session header when the session changes.
                if ( $sid !== $current_session ) :
                    $current_session = $sid;
                    ?>
                    <div class="boost-log-session-header" style="background:#2271b1;color:#fff;padding:8px 14px;border-radius:4px 4px 0 0;margin-top:20px;display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-randomize"></span>
                        <strong><?php esc_html_e( 'Sessie:', 'bossier-calculator' ); ?> <?php echo esc_html( $sid ); ?></strong>
                        <?php if ( ! $filter_session ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'session', $sid, $base_page_url ) ); ?>"
                               style="color:#9dd4ff;font-size:12px;margin-left:6px;">
                                <?php esc_html_e( 'filter op sessie', 'bossier-calculator' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <details class="boost-log-entry boost-log-entry--<?php echo esc_attr( $level ); ?>"
                         style="border:1px solid #e0e0e0;border-top:none;background:#fff;">
                    <summary style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;list-style:none;user-select:none;">
                        <span class="boost-log-level boost-log-level--<?php echo esc_attr( $level ); ?>"
                              style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:11px;font-weight:600;text-transform:uppercase;
                                     background:<?php echo 'warning' === $level ? '#ffe58f' : ( 'error' === $level ? '#ffd6d6' : '#e8f4fd' ); ?>;
                                     color:<?php echo 'warning' === $level ? '#7a5800' : ( 'error' === $level ? '#8b0000' : '#1a5276' ); ?>;">
                            <?php echo esc_html( $level ); ?>
                        </span>
                        <span class="boost-log-event" style="font-weight:600;flex:1;">
                            <?php echo esc_html( $label ); ?>
                            <span style="font-weight:400;color:#999;font-size:12px;margin-left:6px;">(<?php echo esc_html( $event ); ?>)</span>
                        </span>
                        <span class="boost-log-time" style="color:#888;font-size:12px;white-space:nowrap;">
                            <?php
                            if ( $timestamp ) {
                                $dt = new DateTime( $timestamp );
                                echo esc_html( $dt->format( 'H:i:s' ) );
                            }
                            ?>
                        </span>
                        <span style="color:#999;font-size:14px;">&#9660;</span>
                    </summary>
                    <div class="boost-log-data" style="padding:14px;border-top:1px solid #f0f0f0;background:#fafafa;">
                        <table class="widefat" style="margin:0;">
                            <thead>
                                <tr>
                                    <th style="width:220px;"><?php esc_html_e( 'Veld', 'bossier-calculator' ); ?></th>
                                    <th><?php esc_html_e( 'Waarde', 'bossier-calculator' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="color:#666;"><?php esc_html_e( 'Timestamp', 'bossier-calculator' ); ?></td>
                                    <td><code><?php echo esc_html( $timestamp ); ?></code></td>
                                </tr>
                                <tr>
                                    <td style="color:#666;"><?php esc_html_e( 'Sessie ID', 'bossier-calculator' ); ?></td>
                                    <td><code><?php echo esc_html( $sid ); ?></code></td>
                                </tr>
                                <?php foreach ( $data as $key => $value ) : ?>
                                <tr>
                                    <td style="color:#666;"><?php echo esc_html( $key ); ?></td>
                                    <td>
                                        <?php if ( is_array( $value ) || is_object( $value ) ) : ?>
                                            <pre style="margin:0;white-space:pre-wrap;font-size:12px;background:#f4f4f4;padding:6px;border-radius:3px;"><?php echo esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
                                        <?php elseif ( is_bool( $value ) ) : ?>
                                            <code><?php echo $value ? 'true' : 'false'; ?></code>
                                        <?php elseif ( is_null( $value ) ) : ?>
                                            <code style="color:#999;">null</code>
                                        <?php else : ?>
                                            <code><?php echo esc_html( (string) $value ); ?></code>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>

            <?php endforeach; ?>
        </div><!-- .boost-log-entries -->
    <?php endif; ?>
</div><!-- .wrap -->

<style>
.boost-shipping-logs-page .boost-log-entry summary::-webkit-details-marker { display:none; }
.boost-shipping-logs-page .boost-log-entry[open] summary span:last-child { transform:rotate(180deg); }
</style>

<script>
jQuery(function($) {
    $('#boost-clear-logs-btn').on('click', function() {
        if ( ! confirm('<?php echo esc_js( __( 'Weet je zeker dat je alle logbestanden wilt wissen? Dit kan niet ongedaan worden gemaakt.', 'bossier-calculator' ) ); ?>') ) {
            return;
        }

        var $btn      = $(this);
        var $feedback = $('#boost-clear-logs-feedback');

        $btn.prop('disabled', true);
        $feedback.show().css('color', '#666').text('<?php echo esc_js( __( 'Bezig...', 'bossier-calculator' ) ); ?>');

        $.post(
            '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
            {
                action : 'boost_clear_shipping_logs',
                nonce  : '<?php echo esc_js( wp_create_nonce( 'boost_modules_nonce' ) ); ?>'
            },
            function(response) {
                if ( response.success ) {
                    $feedback.css('color', 'green').text(response.data.message);
                    // Reload to show empty state.
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    $feedback.css('color', 'red').text(
                        response.data && response.data.message
                            ? response.data.message
                            : '<?php echo esc_js( __( 'Fout opgetreden.', 'bossier-calculator' ) ); ?>'
                    );
                    $btn.prop('disabled', false);
                }
            }
        );
    });
});
</script>
