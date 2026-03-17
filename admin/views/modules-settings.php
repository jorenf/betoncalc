<?php
/**
 * Modules settings page view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

// Ensure Surcharge_Calculator is available for server-side preview rendering,
// regardless of whether the shipping module has been bootstrapped yet.
if ( ! class_exists( 'Bossier\Calculator\Shipping\Surcharge_Calculator', false ) ) {
    require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-surcharge-calculator.php';
}

use Bossier\Calculator\Shipping\Surcharge_Calculator;

/**
 * Variables available:
 *
 * @var array  $settings    Current settings.
 * @var string $current_tab Current active tab.
 * @var array  $tabs        Available tabs.
 */

$base_url = admin_url( 'edit.php?post_type=bossier_calculator&page=boost-modules' );
?>

<div class="wrap boost-modules-settings">
    <h1><?php esc_html_e( 'Boost Modules Instellingen', 'bossier-calculator' ); ?></h1>

    <nav class="nav-tab-wrapper boost-nav-tabs">
        <?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, $base_url ) ); ?>"
               class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $tab_label ); ?>
                <?php if ( 'btw' === $tab_id && ! empty( $settings['btw_module_enabled'] ) ) : ?>
                    <span class="boost-module-badge boost-module-active"><?php esc_html_e( 'Actief', 'bossier-calculator' ); ?></span>
                <?php endif; ?>
                <?php if ( 'shipping' === $tab_id && ! empty( $settings['shipping_module_enabled'] ) ) : ?>
                    <span class="boost-module-badge boost-module-active"><?php esc_html_e( 'Actief', 'bossier-calculator' ); ?></span>
                <?php endif; ?>
                <?php if ( 'woopages' === $tab_id && ! empty( $settings['woopages_enabled'] ) ) : ?>
                    <span class="boost-module-badge boost-module-active"><?php esc_html_e( 'Actief', 'bossier-calculator' ); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php" class="boost-settings-form">
        <?php settings_fields( 'boost_modules_settings' ); ?>

        <?php if ( 'general' === $current_tab ) : ?>
            <!-- General Tab -->
            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Module Activatie', 'bossier-calculator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Activeer of deactiveer de modules die je wilt gebruiken.', 'bossier-calculator' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'BTW Verlegd Module', 'bossier-calculator' ); ?></th>
                        <td>
                            <label class="boost-toggle">
                                <input type="checkbox"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_module_enabled]"
                                       value="1"
                                       <?php checked( $settings['btw_module_enabled'] ); ?>>
                                <span class="boost-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Schakel BTW Verlegd (Reverse Charge VAT) functionaliteit in voor zakelijke klanten.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Verzending Module', 'bossier-calculator' ); ?></th>
                        <td>
                            <label class="boost-toggle">
                                <input type="checkbox"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_module_enabled]"
                                       value="1"
                                       <?php checked( $settings['shipping_module_enabled'] ); ?>>
                                <span class="boost-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Schakel aangepaste verzendberekening in met zones, pallets en levertijden.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Boost WooPages', 'bossier-calculator' ); ?></th>
                        <td>
                            <label class="boost-toggle">
                                <input type="checkbox"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[woopages_enabled]"
                                       value="1"
                                       <?php checked( $settings['woopages_enabled'] ); ?>>
                                <span class="boost-toggle-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Gebruik de door Boost Calculator ingebouwde winkelmand-, afreken- en bevestigingspagina\'s.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'WooCommerce Integratie', 'bossier-calculator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Optioneel: schakel WooCommerce standaard functies uit wanneer onze modules actief zijn.', 'bossier-calculator' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'WooCommerce Verzending uitschakelen', 'bossier-calculator' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_disable_wc_shipping]"
                                       value="1"
                                       <?php checked( $settings['shipping_disable_wc_shipping'] ); ?>
                                       <?php disabled( empty( $settings['shipping_module_enabled'] ) ); ?>>
                                <?php esc_html_e( 'Alleen Boost verzendmethoden gebruiken', 'bossier-calculator' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Schakel standaard WooCommerce verzendmethoden uit en gebruik alleen Boost verzending. Alleen beschikbaar als Verzending module actief is.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="boost-info-box" style="margin-top: 15px;">
                    <h4><?php esc_html_e( 'BTW Berekening', 'bossier-calculator' ); ?></h4>
                    <p><?php esc_html_e( 'De BTW Verlegd module gebruikt de WooCommerce belastinginstellingen. Zorg dat BTW is ingeschakeld in WooCommerce → Instellingen → Algemeen en dat er een 21% BTW-tarief is ingesteld.', 'bossier-calculator' ); ?></p>
                    <p><?php esc_html_e( 'Bij zakelijke klanten buiten Nederland met een geldig BTW-nummer wordt automatisch 0% BTW (reverse charge) toegepast.', 'bossier-calculator' ); ?></p>
                </div>
            </div>

        <?php elseif ( 'btw' === $current_tab ) : ?>
            <!-- BTW Verlegd Tab -->
            <?php if ( empty( $settings['btw_module_enabled'] ) ) : ?>
                <div class="boost-settings-section">
                    <div class="boost-module-inactive-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <h3><?php esc_html_e( 'BTW Verlegd Module is niet actief', 'bossier-calculator' ); ?></h3>
                        <p><?php esc_html_e( 'Activeer de BTW Verlegd module in het Algemeen tabblad om deze instellingen te configureren.', 'bossier-calculator' ); ?></p>
                        <a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $base_url ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Ga naar Algemeen', 'bossier-calculator' ); ?>
                        </a>
                    </div>
                </div>

                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Hoe BTW Verlegd werkt', 'bossier-calculator' ); ?></h2>
                    <div class="boost-info-box">
                        <h4><?php esc_html_e( 'Checkout Flow', 'bossier-calculator' ); ?></h4>
                        <ol>
                            <li><?php esc_html_e( 'Klant vinkt "Zakelijke bestelling" aan', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Bedrijfsnaam veld wordt verplicht', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'BTW-nummer veld verschijnt (optioneel)', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Bij geldig EU BTW-nummer + buitenlands adres: 0% BTW', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Zonder geldig nummer: normale BTW', 'bossier-calculator' ); ?></li>
                        </ol>
                        <h4><?php esc_html_e( 'VIES Validatie', 'bossier-calculator' ); ?></h4>
                        <p><?php esc_html_e( 'BTW-nummers worden real-time gevalideerd via de EU VIES API. Bij API-fouten kan de klant doorgaan met bestellen; het nummer wordt later handmatig gecontroleerd.', 'bossier-calculator' ); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <!-- BTW Module Active - Show Settings -->

                <!-- Checkout Velden -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Checkout Velden', 'bossier-calculator' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Pas de labels en teksten aan die klanten zien tijdens het afrekenen.', 'bossier-calculator' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Checkbox Label', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_checkbox_label]"
                                       value="<?php echo esc_attr( $settings['btw_checkbox_label'] ); ?>"
                                       class="large-text"
                                       placeholder="<?php esc_attr_e( 'Dit is een zakelijke bestelling', 'bossier-calculator' ); ?>">
                                <p class="description"><?php esc_html_e( 'Tekst naast de checkbox voor zakelijke bestellingen.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Bedrijfsnaam Label', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_company_label]"
                                       value="<?php echo esc_attr( $settings['btw_company_label'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'Bedrijfsnaam', 'bossier-calculator' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'BTW-nummer Label', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_vat_label]"
                                       value="<?php echo esc_attr( $settings['btw_vat_label'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'BTW-nummer (optioneel)', 'bossier-calculator' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'BTW-nummer Placeholder', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_vat_placeholder]"
                                       value="<?php echo esc_attr( $settings['btw_vat_placeholder'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'bijv. NL123456789B01', 'bossier-calculator' ); ?>">
                                <p class="description"><?php esc_html_e( 'Voorbeeld tekst in het BTW-nummer veld.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Validatie Berichten -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Validatie Berichten', 'bossier-calculator' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Berichten die getoond worden na BTW-nummer validatie.', 'bossier-calculator' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Geldig BTW-nummer', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_valid_message]"
                                       value="<?php echo esc_attr( $settings['btw_valid_message'] ); ?>"
                                       class="large-text"
                                       placeholder="<?php esc_attr_e( 'BTW-nummer gevalideerd', 'bossier-calculator' ); ?>">
                                <p class="description"><?php esc_html_e( 'Bericht bij succesvolle validatie.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Ongeldig BTW-nummer', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_invalid_message]"
                                       value="<?php echo esc_attr( $settings['btw_invalid_message'] ); ?>"
                                       class="large-text"
                                       placeholder="<?php esc_attr_e( 'BTW-nummer kon niet worden gevalideerd', 'bossier-calculator' ); ?>">
                                <p class="description"><?php esc_html_e( 'Bericht bij mislukte validatie.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Factuur Instellingen -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Factuur Instellingen', 'bossier-calculator' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Tekst die op facturen wordt getoond bij BTW verlegd orders.', 'bossier-calculator' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Standaard Factuurtekst', 'bossier-calculator' ); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_invoice_text]"
                                          rows="2"
                                          class="large-text"><?php echo esc_textarea( $settings['btw_invoice_text'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'EU standaard tekst voor BTW verlegd.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Aangepaste Factuurtekst', 'bossier-calculator' ); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_custom_invoice_text]"
                                          rows="2"
                                          class="large-text"
                                          placeholder="<?php esc_attr_e( 'Laat leeg om standaard tekst te gebruiken', 'bossier-calculator' ); ?>"><?php echo esc_textarea( $settings['btw_custom_invoice_text'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Optioneel: Eigen tekst in plaats van standaard.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Notificaties & Voorwaarden -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Notificaties & Voorwaarden', 'bossier-calculator' ); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Admin Email Notificatie', 'bossier-calculator' ); ?></th>
                            <td>
                                <label class="boost-toggle">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_admin_email]"
                                           value="1"
                                           <?php checked( $settings['btw_admin_email'] ); ?>>
                                    <span class="boost-toggle-slider"></span>
                                </label>
                                <span class="description"><?php esc_html_e( 'Stuur email naar admin bij BTW verlegd orders', 'bossier-calculator' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Admin Email Adres', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="email"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_admin_email_address]"
                                       value="<?php echo esc_attr( $settings['btw_admin_email_address'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                                <p class="description"><?php esc_html_e( 'Laat leeg voor standaard admin email.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Minimaal Bestelbedrag', 'bossier-calculator' ); ?></th>
                            <td>
                                <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[btw_minimum_amount]"
                                       value="<?php echo esc_attr( $settings['btw_minimum_amount'] ); ?>"
                                       class="small-text"
                                       min="0"
                                       step="0.01">
                                <p class="description"><?php esc_html_e( 'BTW verlegd alleen beschikbaar boven dit bedrag. 0 = geen minimum.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Info Box -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Hoe BTW Verlegd werkt', 'bossier-calculator' ); ?></h2>
                    <div class="boost-info-box">
                        <h4><?php esc_html_e( 'Checkout Flow', 'bossier-calculator' ); ?></h4>
                        <ol>
                            <li><?php esc_html_e( 'Klant vinkt "Zakelijke bestelling" aan', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Bedrijfsnaam veld wordt verplicht', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'BTW-nummer veld verschijnt (optioneel)', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Bij geldig EU BTW-nummer + buitenlands adres: 0% BTW', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Zonder geldig nummer: normale BTW', 'bossier-calculator' ); ?></li>
                        </ol>
                        <h4><?php esc_html_e( 'VIES Validatie', 'bossier-calculator' ); ?></h4>
                        <p><?php esc_html_e( 'BTW-nummers worden real-time gevalideerd via de EU VIES API. Bij API-fouten kan de klant doorgaan met bestellen; het nummer wordt later handmatig gecontroleerd.', 'bossier-calculator' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ( 'shipping' === $current_tab ) : ?>
            <!-- Shipping Tab -->
            <?php if ( empty( $settings['shipping_module_enabled'] ) ) : ?>
                <div class="boost-settings-section">
                    <div class="boost-module-inactive-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <h3><?php esc_html_e( 'Verzending Module is niet actief', 'bossier-calculator' ); ?></h3>
                        <p><?php esc_html_e( 'Activeer de Verzending module in het Algemeen tabblad om deze instellingen te configureren.', 'bossier-calculator' ); ?></p>
                        <a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $base_url ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Ga naar Algemeen', 'bossier-calculator' ); ?>
                        </a>
                    </div>
                </div>
            <?php else : ?>
            <!-- Shipping Module Active - Show Settings -->

            <!-- Section 1: Pickup -->
            <div class="boost-settings-section boost-collapsible-section">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-store"></span>
                    <?php esc_html_e( 'Afhalen', 'bossier-calculator' ); ?>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Afhalen inschakelen', 'bossier-calculator' ); ?></th>
                            <td>
                                <label class="boost-toggle">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pickup_enabled]"
                                           value="1"
                                           <?php checked( $settings['shipping_pickup_enabled'] ); ?>>
                                    <span class="boost-toggle-slider"></span>
                                </label>
                                <span class="description"><?php esc_html_e( 'Altijd gratis', 'bossier-calculator' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Afhaaladres', 'bossier-calculator' ); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pickup_address]"
                                          rows="3"
                                          class="large-text"
                                          placeholder="<?php esc_attr_e( 'Bedrijfsnaam\nStraat 123\n1234 AB Plaats', 'bossier-calculator' ); ?>"><?php echo esc_textarea( $settings['shipping_pickup_address'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Section 2: Shipping Methods -->
            <div class="boost-settings-section boost-collapsible-section open">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-car"></span>
                    <?php esc_html_e( 'Verzendmethoden', 'bossier-calculator' ); ?>
                    <?php
                    $shipping_methods = $settings['shipping_methods'] ?? array();
                    $active_count = count( array_filter( $shipping_methods, function( $m ) { return ! empty( $m['enabled'] ); } ) );
                    ?>
                    <span class="boost-section-badge"><?php echo esc_html( $active_count ); ?> <?php esc_html_e( 'actief', 'bossier-calculator' ); ?></span>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">
                    <p class="description"><?php esc_html_e( 'Het systeem selecteert automatisch de juiste methode op basis van het gewicht. Per product kunt u beperken welke methoden zijn toegestaan.', 'bossier-calculator' ); ?></p>

                    <?php $pallets = $settings['shipping_pallets']; ?>
                    <table class="widefat boost-shipping-methods-table" style="margin: 15px 0;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Naam', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'Pallet type', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'Max gewicht (kg)', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'Basisprijs', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'Actief', 'bossier-calculator' ); ?></th>
                                <th style="width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="boost-shipping-methods-body">
                            <?php
                            if ( ! empty( $shipping_methods ) ) :
                                foreach ( $shipping_methods as $m_index => $method ) :
                            ?>
                            <tr class="boost-shipping-method-row" data-index="<?php echo esc_attr( $m_index ); ?>">
                                <td>
                                    <input type="hidden"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][id]"
                                           value="<?php echo esc_attr( $method['id'] ?? '' ); ?>">
                                    <input type="text"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][name]"
                                           value="<?php echo esc_attr( $method['name'] ?? '' ); ?>"
                                           class="regular-text boost-method-name-input"
                                           placeholder="<?php esc_attr_e( 'bijv. Pallet', 'bossier-calculator' ); ?>">
                                </td>
                                <td>
                                    <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][pallet_type]">
                                        <option value=""><?php esc_html_e( '— Alle —', 'bossier-calculator' ); ?></option>
                                        <?php foreach ( $pallets as $pallet ) : ?>
                                            <option value="<?php echo esc_attr( $pallet['id'] ); ?>" <?php selected( $method['pallet_type'] ?? '', $pallet['id'] ); ?>>
                                                <?php echo esc_html( $pallet['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][max_weight]"
                                           value="<?php echo esc_attr( $method['max_weight'] ?? 800 ); ?>"
                                           class="small-text"
                                           min="1"
                                           step="1"> kg
                                </td>
                                <td>
                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][base_price]"
                                           value="<?php echo esc_attr( $method['base_price'] ?? 0 ); ?>"
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][<?php echo esc_attr( $m_index ); ?>][enabled]"
                                           value="1"
                                           <?php checked( ! empty( $method['enabled'] ) ); ?>>
                                </td>
                                <td>
                                    <button type="button" class="button boost-remove-shipping-method" title="<?php esc_attr_e( 'Verwijderen', 'bossier-calculator' ); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                    </table>

                    <button type="button" class="button button-primary" id="boost-add-shipping-method">
                        <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Verzendmethode toevoegen', 'bossier-calculator' ); ?>
                    </button>
                </div>
            </div>

            <!-- Section 3: Zones & Prices -->
            <div class="boost-settings-section boost-collapsible-section open">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-location-alt"></span>
                    <?php esc_html_e( 'Zones & Prijzen', 'bossier-calculator' ); ?>
                    <span class="boost-section-badge"><?php echo esc_html( count( $settings['shipping_zones'] ) ); ?> <?php esc_html_e( 'zones', 'bossier-calculator' ); ?></span>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">
                    <p class="description"><?php esc_html_e( 'Beheer zones met postcodes en stel per zone de verzendprijzen in.', 'bossier-calculator' ); ?></p>
                    <p class="description" style="color: #1e40af; font-weight: 500;"><span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; margin-right: 4px;"></span><?php esc_html_e( 'Alle prijzen zijn inclusief BTW.', 'bossier-calculator' ); ?></p>

                    <!-- Zone list with inline pricing -->
                    <div id="boost-shipping-zones" class="boost-repeater">
                        <?php
                        $zones = $settings['shipping_zones'];
                        if ( empty( $zones ) ) {
                            $zones = array( array( 'id' => 1, 'name' => '', 'countries' => array(), 'postcodes' => '', 'delivery_days' => '' ) );
                        }
                        foreach ( $zones as $index => $zone ) :
                        ?>
                        <div class="boost-repeater-item boost-zone-item" data-index="<?php echo esc_attr( $index ); ?>" data-zone-id="<?php echo esc_attr( $zone['id'] ); ?>">
                            <div class="boost-repeater-header">
                                <span class="boost-repeater-title"><?php echo esc_html( $zone['name'] ?: __( 'Nieuwe Zone', 'bossier-calculator' ) ); ?></span>
                                <span class="boost-repeater-subtitle"><?php echo esc_html( $zone['postcodes'] ?? '' ); ?></span>
                                <button type="button" class="boost-repeater-toggle dashicons dashicons-arrow-down-alt2"></button>
                                <button type="button" class="boost-repeater-remove dashicons dashicons-trash"></button>
                            </div>
                            <div class="boost-repeater-content">
                                <input type="hidden"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][id]"
                                       value="<?php echo esc_attr( $zone['id'] ?? $index + 1 ); ?>">

                                <div class="boost-zone-fields-row">
                                    <p>
                                        <label><?php esc_html_e( 'Zone Naam', 'bossier-calculator' ); ?></label>
                                        <input type="text"
                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][name]"
                                               value="<?php echo esc_attr( $zone['name'] ?? '' ); ?>"
                                               class="regular-text boost-zone-name-input">
                                    </p>
                                    <p>
                                        <label><?php esc_html_e( 'Landen', 'bossier-calculator' ); ?></label>
                                        <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][countries][]"
                                                multiple
                                                class="boost-country-select">
                                            <?php
                                            $countries = WC()->countries->get_countries();
                                            $selected  = $zone['countries'] ?? array();
                                            foreach ( $countries as $code => $name ) :
                                            ?>
                                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $selected, true ) ); ?>>
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label><?php esc_html_e( 'Postcodes', 'bossier-calculator' ); ?></label>
                                        <input type="text"
                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][postcodes]"
                                               value="<?php echo esc_attr( $zone['postcodes'] ?? '' ); ?>"
                                               class="regular-text"
                                               placeholder="1000-2999, 3500-3599">
                                    </p>
                                    <p>
                                        <label><?php esc_html_e( 'Levertijd', 'bossier-calculator' ); ?></label>
                                        <input type="text"
                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][delivery_days]"
                                               value="<?php echo esc_attr( $zone['delivery_days'] ?? '' ); ?>"
                                               class="small-text"
                                               placeholder="1-2">
                                        <span class="description"><?php esc_html_e( 'werkdagen', 'bossier-calculator' ); ?></span>
                                    </p>
                                </div>

                                <!-- Inline zone prices -->
                                <div class="boost-zone-prices-inline">
                                    <h4>
                                        <?php esc_html_e( 'Prijzen voor deze zone', 'bossier-calculator' ); ?>
                                        <?php if ( ! empty( $settings['shipping_prices_excl_btw'] ) ) : ?>
                                            <span style="font-weight:normal; font-size:0.85em; color:#666; margin-left:6px;">
                                                (<?php esc_html_e( 'excl. BTW — preview incl. BTW + toeslagen zichtbaar', 'bossier-calculator' ); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ( empty( $shipping_methods ) ) : ?>
                                        <p class="description" style="color: #d63638;"><?php esc_html_e( 'Voeg eerst verzendmethoden toe in de sectie hierboven.', 'bossier-calculator' ); ?></p>
                                    <?php else : ?>
                                    <table class="boost-zone-price-table widefat">
                                        <thead>
                                            <tr>
                                                <?php foreach ( $shipping_methods as $method ) :
                                                    $col_excl = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ][ $method['id'] ] );
                                                ?>
                                                    <th data-method-id="<?php echo esc_attr( $method['id'] ); ?>" class="<?php echo $col_excl ? 'boost-col-excluded' : ''; ?>">
                                                        <?php echo esc_html( $method['name'] ); ?>
                                                        <button type="button" class="boost-exclude-method" data-method-id="<?php echo esc_attr( $method['id'] ); ?>" title="<?php echo $col_excl ? esc_attr__( 'Herstel methode', 'bossier-calculator' ) : esc_attr__( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>"><?php echo $col_excl ? '+' : '×'; ?></button>
                                                    </th>
                                                <?php endforeach; ?>
                                                <?php $loose_excl    = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ]['loose'] ); ?>
                                                <?php $per_kg_excl_c = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ]['loose_per_kg'] ); ?>
                                                <th data-method-id="loose" class="<?php echo $loose_excl ? 'boost-col-excluded' : ''; ?>">
                                                    <?php esc_html_e( 'Los (vast)', 'bossier-calculator' ); ?>
                                                    <button type="button" class="boost-exclude-method" data-method-id="loose" title="<?php echo $loose_excl ? esc_attr__( 'Herstel methode', 'bossier-calculator' ) : esc_attr__( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>"><?php echo $loose_excl ? '+' : '×'; ?></button>
                                                </th>
                                                <th data-method-id="loose_per_kg" class="<?php echo $per_kg_excl_c ? 'boost-col-excluded' : ''; ?>">
                                                    <?php esc_html_e( 'Los (/kg)', 'bossier-calculator' ); ?>
                                                    <button type="button" class="boost-exclude-method" data-method-id="loose_per_kg" title="<?php echo $per_kg_excl_c ? esc_attr__( 'Herstel methode', 'bossier-calculator' ) : esc_attr__( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>"><?php echo $per_kg_excl_c ? '+' : '×'; ?></button>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Input row: enter prices (excl. BTW when mode is on) -->
                                            <tr>
                                                <?php foreach ( $shipping_methods as $method ) :
                                                    $td_excl = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ][ $method['id'] ] );
                                                ?>
                                                    <td data-method-id="<?php echo esc_attr( $method['id'] ); ?>" class="<?php echo $td_excl ? 'boost-col-excluded' : ''; ?>">
                                                        <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                                        <input type="number"
                                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][<?php echo esc_attr( $method['id'] ); ?>]"
                                                               value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ][ $method['id'] ] ?? '' ); ?>"
                                                               class="small-text boost-zone-price-input"
                                                               min="0"
                                                               step="0.01"
                                                               data-is-per-kg="0"
                                                               data-method-id="<?php echo esc_attr( $method['id'] ); ?>"
                                                               data-zone-id="<?php echo esc_attr( $zone['id'] ); ?>">
                                                        <input type="hidden"
                                                               class="boost-zone-excl-btw-flag"
                                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices_excl_btw_flags][<?php echo esc_attr( $zone['id'] ); ?>][<?php echo esc_attr( $method['id'] ); ?>]"
                                                               value="<?php echo esc_attr( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ][ $method['id'] ] ?? 0 ); ?>">
                                                        <input type="hidden"
                                                               class="boost-zone-excluded-flag"
                                                               data-method-id="<?php echo esc_attr( $method['id'] ); ?>"
                                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][<?php echo esc_attr( $zone['id'] ); ?>][<?php echo esc_attr( $method['id'] ); ?>]"
                                                               value="<?php echo $td_excl ? 1 : 0; ?>">
                                                    </td>
                                                <?php endforeach; ?>
                                                <?php
                                                $loose_td_excl    = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ]['loose'] );
                                                $per_kg_td_excl   = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ]['loose_per_kg'] );
                                                ?>
                                                <td data-method-id="loose" class="<?php echo $loose_td_excl ? 'boost-col-excluded' : ''; ?>">
                                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][loose]"
                                                           value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ]['loose'] ?? '' ); ?>"
                                                           class="small-text boost-zone-price-input"
                                                           min="0"
                                                           step="0.01"
                                                           data-is-per-kg="0"
                                                           data-method-id="loose"
                                                           data-zone-id="<?php echo esc_attr( $zone['id'] ); ?>">
                                                    <input type="hidden"
                                                           class="boost-zone-excl-btw-flag"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices_excl_btw_flags][<?php echo esc_attr( $zone['id'] ); ?>][loose]"
                                                           value="<?php echo esc_attr( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ]['loose'] ?? 0 ); ?>">
                                                    <input type="hidden"
                                                           class="boost-zone-excluded-flag"
                                                           data-method-id="loose"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][<?php echo esc_attr( $zone['id'] ); ?>][loose]"
                                                           value="<?php echo $loose_td_excl ? 1 : 0; ?>">
                                                </td>
                                                <td data-method-id="loose_per_kg" class="<?php echo $per_kg_td_excl ? 'boost-col-excluded' : ''; ?>">
                                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][loose_per_kg]"
                                                           value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ]['loose_per_kg'] ?? '' ); ?>"
                                                           class="small-text boost-zone-price-input"
                                                           min="0"
                                                           step="0.01"
                                                           data-is-per-kg="1"
                                                           data-method-id="loose_per_kg"
                                                           data-zone-id="<?php echo esc_attr( $zone['id'] ); ?>">
                                                    <input type="hidden"
                                                           class="boost-zone-excl-btw-flag"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices_excl_btw_flags][<?php echo esc_attr( $zone['id'] ); ?>][loose_per_kg]"
                                                           value="<?php echo esc_attr( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ]['loose_per_kg'] ?? 0 ); ?>">
                                                    <input type="hidden"
                                                           class="boost-zone-excluded-flag"
                                                           data-method-id="loose_per_kg"
                                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][<?php echo esc_attr( $zone['id'] ); ?>][loose_per_kg]"
                                                           value="<?php echo $per_kg_td_excl ? 1 : 0; ?>">
                                                </td>
                                            </tr>
                                            <!-- Preview row: excl. BTW mode only — shows calculated incl. price -->
                                            <tr class="boost-zone-preview-row" style="background:#f0f8f0; <?php echo empty( $settings['shipping_prices_excl_btw'] ) ? 'display:none;' : ''; ?>">
                                                <?php
                                                // Pre-calculate server-side for the initial page render.
                                                // JS will keep these in sync as the user edits values.
                                                $eff_toeslag = Surcharge_Calculator::get_effective_surcharge( $settings );
                                                $col_count   = count( $shipping_methods ) + 2; // methods + loose + loose_per_kg
                                                ?>
                                                <?php
                                                $col_index = 0;
                                                foreach ( $shipping_methods as $method ) :
                                                    $is_excl_flag = ! empty( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ][ $method['id'] ] );
                                                    $base_excl    = floatval( $settings['shipping_zone_prices'][ $zone['id'] ][ $method['id'] ] ?? 0 );
                                                    $incl         = ( $is_excl_flag && $base_excl > 0 ) ? Surcharge_Calculator::bereken_prijs_incl_btw( $base_excl, $eff_toeslag ) : null;
                                                    $col_index++;
                                                ?>
                                                    <?php $prev_excl = ! empty( $settings['shipping_zone_excluded_methods'][ $zone['id'] ][ $method['id'] ] ); ?>
                                                    <td data-method-id="<?php echo esc_attr( $method['id'] ); ?>" class="<?php echo $prev_excl ? 'boost-col-excluded' : ''; ?>">
                                                        <span class="boost-preview-incl" style="color:#2d6a2d; font-size:0.85em; white-space:nowrap;">
                                                            <?php if ( null !== $incl ) : ?>
                                                                &#8594; <?php echo esc_html( get_woocommerce_currency_symbol() . number_format( round( $incl ), 0, ',', '.' ) ); ?>
                                                            <?php else : ?>
                                                                —
                                                            <?php endif; ?>
                                                        </span>
                                                        <br><small style="color:#888;"><?php esc_html_e( 'incl. BTW', 'bossier-calculator' ); ?></small>
                                                    </td>
                                                <?php endforeach; ?>
                                                <?php
                                                $loose_is_excl  = ! empty( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ]['loose'] );
                                                $loose_excl_v   = floatval( $settings['shipping_zone_prices'][ $zone['id'] ]['loose'] ?? 0 );
                                                $loose_incl     = ( $loose_is_excl && $loose_excl_v > 0 ) ? Surcharge_Calculator::bereken_prijs_incl_btw( $loose_excl_v, $eff_toeslag ) : null;
                                                $per_kg_is_excl = ! empty( $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ]['loose_per_kg'] );
                                                $per_kg_excl_v  = floatval( $settings['shipping_zone_prices'][ $zone['id'] ]['loose_per_kg'] ?? 0 );
                                                $per_kg_incl    = ( $per_kg_is_excl && $per_kg_excl_v > 0 ) ? Surcharge_Calculator::bereken_prijs_incl_btw( $per_kg_excl_v, $eff_toeslag ) : null;
                                                ?>
                                                <td data-method-id="loose" class="<?php echo $loose_td_excl ? 'boost-col-excluded' : ''; ?>">
                                                    <span class="boost-preview-incl" style="color:#2d6a2d; font-size:0.85em; white-space:nowrap;">
                                                        <?php if ( null !== $loose_incl ) : ?>
                                                            &#8594; <?php echo esc_html( get_woocommerce_currency_symbol() . number_format( round( $loose_incl ), 0, ',', '.' ) ); ?>
                                                        <?php else : ?>
                                                            —
                                                        <?php endif; ?>
                                                    </span>
                                                    <br><small style="color:#888;"><?php esc_html_e( 'incl. BTW', 'bossier-calculator' ); ?></small>
                                                </td>
                                                <td data-method-id="loose_per_kg" class="<?php echo $per_kg_td_excl ? 'boost-col-excluded' : ''; ?>">
                                                    <span class="boost-preview-incl" style="color:#2d6a2d; font-size:0.85em; white-space:nowrap;">
                                                        <?php if ( null !== $per_kg_incl ) : ?>
                                                            &#8594; <?php echo esc_html( get_woocommerce_currency_symbol() . number_format( round( $per_kg_incl ), 0, ',', '.' ) ); ?>
                                                        <?php else : ?>
                                                            —
                                                        <?php endif; ?>
                                                    </span>
                                                    <br><small style="color:#888;"><?php esc_html_e( 'incl. BTW /kg', 'bossier-calculator' ); ?></small>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="boost-zone-buttons">
                        <button type="button" class="button boost-add-zone"><?php esc_html_e( 'Zone Toevoegen', 'bossier-calculator' ); ?></button>
                        <button type="button" class="button button-secondary boost-load-default-zones" id="boost-load-default-zones">
                            <?php esc_html_e( 'Standaard Zones Laden (NL/BE/DE)', 'bossier-calculator' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 4: Pallet Types -->
            <div class="boost-settings-section boost-collapsible-section">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-archive"></span>
                    <?php esc_html_e( 'Pallet Types', 'bossier-calculator' ); ?>
                    <span class="boost-section-badge"><?php echo esc_html( count( $settings['shipping_pallets'] ) ); ?></span>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">
                    <p class="description"><?php esc_html_e( 'Definieer de beschikbare pallet formaten.', 'bossier-calculator' ); ?></p>

                    <div id="boost-shipping-pallets" class="boost-repeater">
                        <?php
                        $pallets = $settings['shipping_pallets'];
                        if ( empty( $pallets ) ) {
                            $pallets = array( array( 'id' => 'euro', 'name' => 'Europallet', 'length' => 1200, 'width' => 800 ) );
                        }
                        foreach ( $pallets as $index => $pallet ) :
                        ?>
                        <div class="boost-repeater-item boost-pallet-item" data-index="<?php echo esc_attr( $index ); ?>">
                            <div class="boost-repeater-header">
                                <span class="boost-repeater-title"><?php echo esc_html( $pallet['name'] ); ?></span>
                                <span class="boost-repeater-subtitle"><?php echo esc_html( $pallet['length'] . 'x' . $pallet['width'] . 'mm' ); ?></span>
                                <button type="button" class="boost-repeater-toggle dashicons dashicons-arrow-down-alt2"></button>
                                <button type="button" class="boost-repeater-remove dashicons dashicons-trash"></button>
                            </div>
                            <div class="boost-repeater-content">
                                <input type="hidden"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][<?php echo esc_attr( $index ); ?>][id]"
                                       value="<?php echo esc_attr( $pallet['id'] ?? sanitize_key( $pallet['name'] ) ); ?>"
                                       class="boost-pallet-id-input">

                                <p>
                                    <label><?php esc_html_e( 'Naam', 'bossier-calculator' ); ?></label>
                                    <input type="text"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][<?php echo esc_attr( $index ); ?>][name]"
                                           value="<?php echo esc_attr( $pallet['name'] ); ?>"
                                           class="regular-text boost-pallet-name-input">
                                </p>
                                <p>
                                    <label><?php esc_html_e( 'Lengte (mm)', 'bossier-calculator' ); ?></label>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][<?php echo esc_attr( $index ); ?>][length]"
                                           value="<?php echo esc_attr( $pallet['length'] ); ?>"
                                           class="small-text boost-pallet-length-input"
                                           min="0"
                                           step="1">
                                </p>
                                <p>
                                    <label><?php esc_html_e( 'Breedte (mm)', 'bossier-calculator' ); ?></label>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][<?php echo esc_attr( $index ); ?>][width]"
                                           value="<?php echo esc_attr( $pallet['width'] ); ?>"
                                           class="small-text boost-pallet-width-input"
                                           min="0"
                                           step="1">
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button boost-add-pallet"><?php esc_html_e( 'Pallet Toevoegen', 'bossier-calculator' ); ?></button>

                    <hr style="margin: 16px 0;">
                    <p><strong><?php esc_html_e( 'Bulk: Compatibel pallet instellen', 'bossier-calculator' ); ?></strong></p>
                    <p class="description"><?php esc_html_e( 'Stel voor alle pallet-producten in dat ze ook op blokpallet gecombineerd kunnen worden. Werkt alleen als blokpallet actief is in de lijst hierboven.', 'bossier-calculator' ); ?></p>
                    <button type="button" class="button button-secondary" id="boost-bulk-compatible-blok">
                        <?php esc_html_e( 'Alle producten: voeg blokpallet toe als compatibel', 'bossier-calculator' ); ?>
                    </button>
                    <span id="boost-bulk-blok-result" style="margin-left:10px;display:inline-block;"></span>

                    <script>
                    jQuery(function($) {
                        $('#boost-bulk-compatible-blok').on('click', function() {
                            var $btn = $(this);
                            var $result = $('#boost-bulk-blok-result');
                            $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Bezig...', 'bossier-calculator' ) ); ?>');
                            $result.text('');
                            $.post(ajaxurl, {
                                action: 'boost_bulk_set_compatible_blok',
                                nonce: '<?php echo esc_js( wp_create_nonce( 'boost_bulk_compatible_blok' ) ); ?>'
                            }, function(response) {
                                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Alle producten: voeg blokpallet toe als compatibel', 'bossier-calculator' ) ); ?>');
                                if (response.success) {
                                    $result.css('color', 'green').text(response.data.message);
                                } else {
                                    $result.css('color', 'red').text(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Fout opgetreden.', 'bossier-calculator' ) ); ?>');
                                }
                            });
                        });
                    });
                    </script>
                </div>
            </div>

            <!-- Section 5: Tarieven & Toeslagen (Diesel, Inpak, BTW) -->
            <div class="boost-settings-section boost-collapsible-section">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e( 'Tarieven & Toeslagen', 'bossier-calculator' ); ?>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">

                    <!-- BTW toggle -->
                    <h3><?php esc_html_e( 'BTW instelling', 'bossier-calculator' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Prijzen excl. BTW', 'bossier-calculator' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_prices_excl_btw]"
                                           value="1"
                                           id="boost-prices-excl-btw"
                                           <?php checked( ! empty( $settings['shipping_prices_excl_btw'] ) ); ?>>
                                    <?php esc_html_e( 'Zoneprijzen zijn ingevoerd excl. BTW (21% wordt automatisch bovenop de toeslagen berekend)', 'bossier-calculator' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Laat dit uit als je al BTW-inclusieve prijzen invoert.', 'bossier-calculator' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- Surcharge fields — only relevant when excl. BTW is on -->
                    <div id="boost-surcharge-fields" style="<?php echo empty( $settings['shipping_prices_excl_btw'] ) ? 'display:none;' : ''; ?>">
                        <hr style="margin: 20px 0;">

                        <!-- Diesel toeslag -->
                        <h3><?php esc_html_e( 'Dieseltoeslag', 'bossier-calculator' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'Basis dieselprijs: €0.99 | Toeslag start bij: €1.03 | Elke €0.04 stijging = +1% (floor).', 'bossier-calculator' ); ?>
                        </p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Huidige dieselprijs', 'bossier-calculator' ); ?></th>
                                <td>
                                    <span class="boost-currency-prefix">€</span>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_diesel_price]"
                                           id="boost-diesel-price"
                                           value="<?php echo esc_attr( $settings['shipping_diesel_price'] ?? 1.03 ); ?>"
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                    <span class="description">/liter</span>
                                </td>
                            </tr>
                        </table>

                        <!-- Inpak toeslag + Tol -->
                        <h3><?php esc_html_e( 'Inpaktoeslag & Tol', 'bossier-calculator' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Inpaktoeslag', 'bossier-calculator' ); ?></th>
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_inpak_percentage]"
                                           id="boost-inpak-pct"
                                           value="<?php echo esc_attr( $settings['shipping_inpak_percentage'] ?? 12.0 ); ?>"
                                           class="small-text"
                                           step="0.1">
                                    <span class="description">%</span>
                                    <p class="description">
                                        <?php esc_html_e( 'Standaard 12%. Kan worden verhoogd of verlaagd.', 'bossier-calculator' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Toltoeslag', 'bossier-calculator' ); ?></th>
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_toll_percentage]"
                                           id="boost-toll-pct"
                                           value="<?php echo esc_attr( $settings['shipping_toll_percentage'] ?? 0 ); ?>"
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                    <span class="description">%</span>
                                    <p class="description">
                                        <?php esc_html_e( 'Standaard 0% (geen tol).', 'bossier-calculator' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- Live preview panel -->
                        <hr style="margin: 20px 0;">
                        <h3><?php esc_html_e( 'Berekend toeslagpercentage (preview)', 'bossier-calculator' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'De toeslag hieronder wordt automatisch berekend op basis van de huidige dieselprijs en inpaktoeslag. Je kunt het handmatig overschrijven als je dat wilt.', 'bossier-calculator' ); ?>
                        </p>
                        <table class="form-table" id="boost-surcharge-preview">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Dieseltoeslag', 'bossier-calculator' ); ?></th>
                                <td><strong id="boost-preview-diesel">—</strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Inpaktoeslag', 'bossier-calculator' ); ?></th>
                                <td><strong id="boost-preview-inpak">—</strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Toltoeslag', 'bossier-calculator' ); ?></th>
                                <td><strong id="boost-preview-toll">—</strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Totale toeslag (auto)', 'bossier-calculator' ); ?></th>
                                <td><strong id="boost-preview-totaal" style="font-size:1.1em;">—</strong></td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e( 'Voorbeeld', 'bossier-calculator' ); ?>
                                    <br><small style="font-weight:normal; color:#646970;"><?php esc_html_e( 'basis (excl. BTW):', 'bossier-calculator' ); ?></small>
                                    <br><input type="number" id="boost-preview-basis" value="57.70" class="small-text" min="0" step="0.01" style="margin-top:5px; width:75px;">
                                </th>
                                <td>
                                    <span id="boost-preview-voorbeeld">—</span>
                                    <p class="description" id="boost-preview-formule"></p>
                                </td>
                            </tr>
                        </table>

                        <!-- Manual override -->
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Handmatig overschrijven', 'bossier-calculator' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="boost-override-toggle"
                                               <?php checked( null !== ( $settings['shipping_surcharge_override'] ?? null ) && '' !== (string) ( $settings['shipping_surcharge_override'] ?? '' ) ); ?>>
                                        <?php esc_html_e( 'Overschrijf de berekende totale toeslag met een handmatig percentage', 'bossier-calculator' ); ?>
                                    </label>
                                    <div id="boost-override-input" style="margin-top:8px; <?php echo ( null === ( $settings['shipping_surcharge_override'] ?? null ) || '' === (string) ( $settings['shipping_surcharge_override'] ?? '' ) ) ? 'display:none;' : ''; ?>">
                                        <input type="number"
                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_surcharge_override]"
                                               id="boost-surcharge-override"
                                               value="<?php echo esc_attr( $settings['shipping_surcharge_override'] ?? '' ); ?>"
                                               class="small-text"
                                               step="0.1">
                                        <span class="description">%</span>
                                        <p class="description" style="color:#b32d2e;">
                                            <?php esc_html_e( 'Let op: dit vervangt de auto-berekening. Laat leeg om terug te gaan naar auto.', 'bossier-calculator' ); ?>
                                        </p>
                                    </div>
                                    <!-- Hidden empty field ensures override is cleared when checkbox is off -->
                                    <input type="hidden" id="boost-override-clear"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_surcharge_override]"
                                           value=""
                                           <?php echo ( null !== ( $settings['shipping_surcharge_override'] ?? null ) && '' !== (string) ( $settings['shipping_surcharge_override'] ?? '' ) ) ? 'disabled' : ''; ?>>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <!-- Toepassen op zones -->
                        <hr style="margin: 20px 0;">
                        <h3><?php esc_html_e( 'Toepassen op zones', 'bossier-calculator' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'Wijs de basisprijs hierboven (excl. BTW) toe aan een verzendmethode en selecteer de zones.', 'bossier-calculator' ); ?>
                        </p>
                        <div class="boost-assign-panel">
                            <div class="boost-assign-row">
                                <span class="boost-assign-label"><?php esc_html_e( 'Methode:', 'bossier-calculator' ); ?></span>
                                <select id="boost-assign-method" class="boost-assign-select">
                                    <?php foreach ( $shipping_methods as $method ) : ?>
                                    <option value="<?php echo esc_attr( $method['id'] ); ?>"><?php echo esc_html( $method['name'] ); ?></option>
                                    <?php endforeach; ?>
                                    <option value="loose"><?php esc_html_e( 'Los (vast)', 'bossier-calculator' ); ?></option>
                                    <option value="loose_per_kg"><?php esc_html_e( 'Los (/kg)', 'bossier-calculator' ); ?></option>
                                </select>
                            </div>
                            <div class="boost-assign-row">
                                <span class="boost-assign-label"><?php esc_html_e( 'Zones:', 'bossier-calculator' ); ?></span>
                                <div class="boost-assign-zones">
                                    <?php foreach ( $settings['shipping_zones'] as $az ) : ?>
                                    <label class="boost-assign-zone-label">
                                        <input type="checkbox" class="boost-assign-zone-check" value="<?php echo esc_attr( $az['id'] ); ?>">
                                        <?php echo esc_html( $az['name'] ?: __( 'Zone', 'bossier-calculator' ) . ' ' . $az['id'] ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                    <button type="button" class="boost-assign-all-btn button-link">
                                        <?php esc_html_e( 'Alle zones', 'bossier-calculator' ); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="boost-assign-actions">
                                <button type="button" class="button button-primary" id="boost-assign-apply">
                                    <?php esc_html_e( 'Toepassen op geselecteerde zones', 'bossier-calculator' ); ?>
                                </button>
                                <span class="boost-assign-result" id="boost-assign-result"></span>
                            </div>
                        </div>
                    </div><!-- /#boost-surcharge-fields -->

                    <style>
                    .boost-col-excluded { opacity: 0.35; }
                    .boost-col-excluded input { pointer-events: none; }
                    .boost-exclude-method {
                        background: none; border: none; cursor: pointer;
                        color: #d63638; font-size: 13px; font-weight: bold;
                        padding: 0 2px; margin-left: 4px; line-height: 1;
                        vertical-align: middle;
                    }
                    .boost-col-excluded .boost-exclude-method { color: #00a32a; }
                    </style>
                    <script>
                    jQuery(function($) {

                        // Toggle method exclusion per zone via × / + button in table header.
                        $(document).on('click', '.boost-exclude-method', function(e) {
                            e.preventDefault();
                            var methodId = $(this).data('method-id');
                            var $table   = $(this).closest('.boost-zone-price-table');
                            var $flag    = $table.find('.boost-zone-excluded-flag[data-method-id="' + methodId + '"]');
                            var nowExcl  = $flag.val() !== '1';
                            $flag.val( nowExcl ? '1' : '0' );
                            $table.find('[data-method-id="' + methodId + '"]').toggleClass('boost-col-excluded', nowExcl);
                            $(this).text( nowExcl ? '+' : '×' );
                            $(this).attr('title', nowExcl
                                ? '<?php echo esc_js( __( 'Herstel methode', 'bossier-calculator' ) ); ?>'
                                : '<?php echo esc_js( __( 'Verwijder methode uit zone', 'bossier-calculator' ) ); ?>'
                            );
                        });


                        // Show/hide surcharge fields based on excl. BTW checkbox.
                        $('#boost-prices-excl-btw').on('change', function() {
                            $('#boost-surcharge-fields').toggle( this.checked );
                        });

                        // Show/hide override input.
                        $('#boost-override-toggle').on('change', function() {
                            if ( this.checked ) {
                                $('#boost-override-input').show();
                                $('#boost-surcharge-override').prop('disabled', false);
                                $('#boost-override-clear').prop('disabled', true);
                            } else {
                                $('#boost-override-input').hide();
                                $('#boost-surcharge-override').prop('disabled', true);
                                $('#boost-override-clear').prop('disabled', false);
                            }
                            updatePreview();
                        });

                        // Diesel toeslag logic (matches PHP Surcharge_Calculator).
                        function calcDieselPct( dieselPrice ) {
                            var THRESHOLD = 1.03;
                            var STEP      = 0.04;
                            dieselPrice   = Math.max( 0, parseFloat( dieselPrice ) || 0 );
                            var verschil  = dieselPrice - THRESHOLD;
                            if ( Math.abs( verschil ) < 1e-9 ) return 0;
                            return Math.floor( verschil / STEP );
                        }

                        function updatePreview() {
                            var dieselPrice  = parseFloat( $('#boost-diesel-price').val() ) || 0;
                            var inpakPct     = parseFloat( $('#boost-inpak-pct').val() ) || 0;
                            var tollPct      = parseFloat( $('#boost-toll-pct').val() ) || 0;
                            var dieselPct    = calcDieselPct( dieselPrice );
                            var autoTotaal   = dieselPct + inpakPct + tollPct;
                            var isOverride   = $('#boost-override-toggle').is(':checked');
                            var effectief    = isOverride
                                ? ( parseFloat( $('#boost-surcharge-override').val() ) || 0 )
                                : autoTotaal;

                            var sign = function(n) { return n >= 0 ? '+' : ''; };

                            $('#boost-preview-diesel').text( sign(dieselPct) + dieselPct + '%' );
                            $('#boost-preview-inpak').text( sign(inpakPct) + inpakPct.toFixed(1) + '%' );
                            $('#boost-preview-toll').text( sign(tollPct) + tollPct.toFixed(2) + '%' );

                            var totaalLabel = sign(autoTotaal) + autoTotaal.toFixed(2) + '%';
                            if ( isOverride ) {
                                totaalLabel += ' (auto) → override: ' + sign(effectief) + effectief.toFixed(2) + '%';
                            }
                            $('#boost-preview-totaal').text( totaalLabel );

                            // Example: user-configurable basis price excl. BTW
                            var basis         = parseFloat( $('#boost-preview-basis').val() ) || 0;
                            var naToeslagExcl = basis > 0 ? Math.max( 0, basis * ( 1 + effectief / 100 ) ) : 0;
                            var inclBtw       = Math.round( naToeslagExcl * 1.21 );

                            if ( basis > 0 ) {
                                $('#boost-preview-voorbeeld').html(
                                    '€' + inclBtw + ' <?php echo esc_js( __( 'incl. BTW', 'bossier-calculator' ) ); ?>'
                                );
                                $('#boost-preview-formule').text(
                                    '€' + basis.toFixed(2) + ' × ' +
                                    ( 1 + effectief / 100 ).toFixed(4) + ' × 1.21 = €' + inclBtw
                                );
                            } else {
                                $('#boost-preview-voorbeeld').text('—');
                                $('#boost-preview-formule').text('');
                            }
                        }

                        // -------------------------------------------------------
                        // Zone price preview rows
                        // -------------------------------------------------------

                        // Called whenever the effective surcharge changes.
                        // Only shows/hides the preview rows — does NOT recalculate existing
                        // values, because those are already stored correctly incl. BTW.
                        function updateZonePreviews() {
                            var exclMode = $('#boost-prices-excl-btw').is(':checked');
                            $('.boost-zone-preview-row').toggle( exclMode );
                            $('.boost-zone-prices-inline h4 .boost-excl-hint').toggle( exclMode );
                        }

                        // Patch updatePreview to also call updateZonePreviews.
                        var _origUpdatePreview = updatePreview;
                        updatePreview = function() {
                            _origUpdatePreview();
                            updateZonePreviews();
                        };

                        // Flag setting: only on 'input' (real user typing), NOT on programmatic .trigger('change').
                        // This prevents the "Toepassen op zones" button from accidentally flagging
                        // other fields when it calls .trigger('change') for the preview update.
                        $(document).on('input', '.boost-zone-price-input', function() {
                            var exclMode = $('#boost-prices-excl-btw').is(':checked');
                            var $flag    = $(this).closest('td').find('.boost-zone-excl-btw-flag');
                            var val      = parseFloat( $(this).val() ) || 0;
                            if ( exclMode && val > 0 ) {
                                $flag.val('1');
                            } else if ( val <= 0 ) {
                                $flag.val('0');
                            }
                        });

                        // Preview update: on both 'input' (user typing) and 'change' (programmatic, e.g. Toepassen button).
                        $(document).on('input change', '.boost-zone-price-input', function() {
                            var baseExcl = parseFloat( $(this).val() ) || 0;
                            var $flag    = $(this).closest('td').find('.boost-zone-excl-btw-flag');

                            var currency    = '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>';
                            var dieselPrice = parseFloat( $('#boost-diesel-price').val() ) || 0;
                            var inpakPct    = parseFloat( $('#boost-inpak-pct').val() ) || 0;
                            var tollPct     = parseFloat( $('#boost-toll-pct').val() ) || 0;
                            var isOverride  = $('#boost-override-toggle').is(':checked');
                            var effectief   = isOverride
                                ? ( parseFloat( $('#boost-surcharge-override').val() ) || 0 )
                                : calcDieselPct( dieselPrice ) + inpakPct + tollPct;

                            var $table   = $(this).closest('.boost-zone-price-table');
                            var colIndex = $table.find('.boost-zone-price-input').index( this );
                            var $preview = $table.find('.boost-zone-preview-row .boost-preview-incl').eq( colIndex );

                            var isExclBtw = $flag.val() === '1';
                            if ( ! isExclBtw || baseExcl <= 0 ) {
                                $preview.text('—');
                            } else {
                                $preview.html('&#8594; ' + currency + Math.round( baseExcl * ( 1 + effectief / 100 ) * 1.21 ) );
                            }
                        });

                        // Bind to field changes.
                        $('#boost-diesel-price, #boost-inpak-pct, #boost-toll-pct, #boost-surcharge-override, #boost-preview-basis').on('input change', updatePreview);

                        // Run on page load.
                        updatePreview();
                    });
                    </script>

                </div>
            </div>

            <!-- Section 6: Advanced (Oversized + Fallback) -->
            <div class="boost-settings-section boost-collapsible-section">
                <h2 class="boost-section-toggle">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e( 'Geavanceerd', 'bossier-calculator' ); ?>
                    <span class="boost-section-arrow dashicons dashicons-arrow-down-alt2"></span>
                </h2>
                <div class="boost-section-body">
                    <h3><?php esc_html_e( 'Oversized Items Toeslag', 'bossier-calculator' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Drempel lengte', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_oversized_threshold]"
                                       value="<?php echo esc_attr( $settings['shipping_oversized_threshold'] ); ?>"
                                       class="small-text"
                                       min="0"
                                       step="1"> mm
                                <p class="description"><?php esc_html_e( 'Items langer dan dit krijgen een toeslag.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Toeslag type', 'bossier-calculator' ); ?></th>
                            <td>
                                <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_oversized_type]">
                                    <option value="fixed" <?php selected( $settings['shipping_oversized_type'], 'fixed' ); ?>><?php esc_html_e( 'Vast bedrag', 'bossier-calculator' ); ?></option>
                                    <option value="percentage" <?php selected( $settings['shipping_oversized_type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage van verzendkosten', 'bossier-calculator' ); ?></option>
                                    <option value="per_mm" <?php selected( $settings['shipping_oversized_type'], 'per_mm' ); ?>><?php esc_html_e( 'Per mm boven drempel', 'bossier-calculator' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Toeslag bedrag', 'bossier-calculator' ); ?></th>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_oversized_amount]"
                                       value="<?php echo esc_attr( $settings['shipping_oversized_amount'] ); ?>"
                                       class="small-text"
                                       min="0"
                                       step="0.01">
                                <span class="description boost-oversized-suffix">
                                    <?php
                                    switch ( $settings['shipping_oversized_type'] ) {
                                        case 'percentage':
                                            echo '%';
                                            break;
                                        case 'per_mm':
                                            echo esc_html( get_woocommerce_currency_symbol() ) . '/mm';
                                            break;
                                        default:
                                            echo esc_html( get_woocommerce_currency_symbol() );
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                    </table>

                    <hr style="margin: 25px 0;">

                    <h3><?php esc_html_e( 'Fallback / Onbekende Postcode', 'bossier-calculator' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Standaard Verzendkosten', 'bossier-calculator' ); ?></th>
                            <td>
                                <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_default_cost]"
                                       value="<?php echo esc_attr( $settings['shipping_default_cost'] ?? 0 ); ?>"
                                       class="small-text"
                                       min="0"
                                       step="0.01">
                                <p class="description"><?php esc_html_e( 'Fallback wanneer geen zone beschikbaar is. Zet op 0 om verzending te blokkeren.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Bericht', 'bossier-calculator' ); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_unknown_postcode_message]"
                                          rows="2"
                                          class="large-text"><?php echo esc_textarea( $settings['shipping_unknown_postcode_message'] ); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ( 'woopages' === $current_tab ) : ?>
            <!-- WooPages Tab -->
            <?php if ( empty( $settings['woopages_enabled'] ) ) : ?>
                <div class="boost-settings-section">
                    <div class="boost-module-inactive-notice">
                        <span class="dashicons dashicons-warning"></span>
                        <h3><?php esc_html_e( 'Boost WooPages is niet actief', 'bossier-calculator' ); ?></h3>
                        <p><?php esc_html_e( 'Activeer Boost WooPages in het Algemeen tabblad om deze functie te gebruiken.', 'bossier-calculator' ); ?></p>
                        <a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $base_url ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Ga naar Algemeen', 'bossier-calculator' ); ?>
                        </a>
                    </div>
                </div>

                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Wat doet Boost WooPages?', 'bossier-calculator' ); ?></h2>
                    <div class="boost-info-box">
                        <h4><?php esc_html_e( 'Template Override Systeem', 'bossier-calculator' ); ?></h4>
                        <p><?php esc_html_e( 'Boost WooPages vervangt de standaard WooCommerce pagina\'s met moderne, geoptimaliseerde templates die zijn ontworpen om naadloos samen te werken met Boost Calculator.', 'bossier-calculator' ); ?></p>

                        <h4><?php esc_html_e( 'Pagina\'s die worden aangepast:', 'bossier-calculator' ); ?></h4>
                        <ul>
                            <li><strong><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></strong> — <?php esc_html_e( 'Overzichtelijke weergave van producten met calculator specificaties', 'bossier-calculator' ); ?></li>
                            <li><strong><?php esc_html_e( 'Afrekenen', 'bossier-calculator' ); ?></strong> — <?php esc_html_e( 'Gestroomlijnde checkout met alle betaalmethodes', 'bossier-calculator' ); ?></li>
                            <li><strong><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></strong> — <?php esc_html_e( 'Professionele bedankpagina met orderdetails', 'bossier-calculator' ); ?></li>
                        </ul>

                        <h4><?php esc_html_e( 'Voordelen:', 'bossier-calculator' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'Betere weergave van calculator producten (kleur, afmetingen, gewicht)', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Moderne, snelle interface', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Volledige integratie met BTW Verlegd en Verzending modules', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Werkt met alle WooCommerce betaalmethodes', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Geen thema aanpassingen nodig', 'bossier-calculator' ); ?></li>
                        </ul>
                    </div>
                </div>
            <?php else : ?>
                <!-- WooPages Active - Show Info -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Boost WooPages Status', 'bossier-calculator' ); ?></h2>
                    <div class="boost-info-box" style="background: #ecfdf5; border-color: #a7f3d0;">
                        <p style="display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 600; color: #059669;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'Boost WooPages is actief', 'bossier-calculator' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Cart Icon Feature -->
                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Winkelwagen Icoon', 'bossier-calculator' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Voeg een winkelwagen icoon toe aan je navigatie of header.', 'bossier-calculator' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Winkelwagen Icoon inschakelen', 'bossier-calculator' ); ?></th>
                            <td>
                                <label class="boost-toggle">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[cart_icon_enabled]"
                                           value="1"
                                           <?php checked( $settings['cart_icon_enabled'] ); ?>>
                                    <span class="boost-toggle-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'Activeer het winkelwagen icoon voor gebruik in je thema.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <?php if ( ! empty( $settings['cart_icon_enabled'] ) ) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Toevoegen aan menu', 'bossier-calculator' ); ?></th>
                            <td>
                                <?php
                                $menu_locations = get_registered_nav_menus();
                                $nav_menus      = wp_get_nav_menus();
                                $current_location = isset( $settings['cart_icon_menu_location'] ) ? $settings['cart_icon_menu_location'] : 'none';
                                ?>
                                <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[cart_icon_menu_location]">
                                    <option value="none" <?php selected( $current_location, 'none' ); ?>><?php esc_html_e( '— Niet automatisch toevoegen —', 'bossier-calculator' ); ?></option>
                                    <?php if ( ! empty( $menu_locations ) ) : ?>
                                        <optgroup label="<?php esc_attr_e( 'Thema menu-locaties', 'bossier-calculator' ); ?>">
                                            <?php foreach ( $menu_locations as $location => $description ) : ?>
                                                <option value="<?php echo esc_attr( $location ); ?>" <?php selected( $current_location, $location ); ?>>
                                                    <?php echo esc_html( $description ); ?> (<?php echo esc_html( $location ); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $nav_menus ) ) : ?>
                                        <optgroup label="<?php esc_attr_e( 'WordPress menu\'s', 'bossier-calculator' ); ?>">
                                            <?php foreach ( $nav_menus as $menu ) : ?>
                                                <option value="menu_<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( $current_location, 'menu_' . $menu->term_id ); ?>>
                                                    <?php echo esc_html( $menu->name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Selecteer een menu om automatisch de winkelwagen icoon toe te voegen.', 'bossier-calculator' ); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php if ( ! empty( $settings['cart_icon_enabled'] ) ) : ?>
                    <div class="boost-info-box" style="margin-top: 15px;">
                        <h4><?php esc_html_e( 'Hoe te gebruiken', 'bossier-calculator' ); ?></h4>
                        <p><strong><?php esc_html_e( 'Shortcode:', 'bossier-calculator' ); ?></strong></p>
                        <code style="display: block; padding: 10px; background: #f1f5f9; border-radius: 4px; margin: 8px 0;">[boost_cart_icon]</code>
                        <p style="margin-top: 5px;"><small><?php esc_html_e( 'Met winkelwagen totaal:', 'bossier-calculator' ); ?> <code>[boost_cart_icon show_total="yes"]</code></small></p>

                        <p style="margin-top: 15px;"><strong><?php esc_html_e( 'PHP Functie (voor in thema bestanden):', 'bossier-calculator' ); ?></strong></p>
                        <code style="display: block; padding: 10px; background: #f1f5f9; border-radius: 4px; margin: 8px 0;">&lt;?php boost_cart_icon(); ?&gt;</code>
                        <p style="margin-top: 5px;"><small><?php esc_html_e( 'Met winkelwagen totaal:', 'bossier-calculator' ); ?> <code>&lt;?php boost_cart_icon( true ); ?&gt;</code></small></p>

                        <h4 style="margin-top: 20px;"><?php esc_html_e( 'Kenmerken', 'bossier-calculator' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'Toont aantal producten in winkelwagen', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Update automatisch via AJAX wanneer producten worden toegevoegd/verwijderd', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Optioneel: toon winkelwagen totaalbedrag', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Animatie bij toevoegen aan winkelwagen', 'bossier-calculator' ); ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Actieve Template Overrides', 'bossier-calculator' ); ?></h2>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Pagina', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'WooCommerce Pagina', 'bossier-calculator' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'bossier-calculator' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></strong></td>
                                <td>
                                    <?php
                                    $cart_page_id = wc_get_page_id( 'cart' );
                                    if ( $cart_page_id > 0 ) {
                                        echo '<a href="' . esc_url( get_permalink( $cart_page_id ) ) . '" target="_blank">' . esc_html( get_the_title( $cart_page_id ) ) . '</a>';
                                    } else {
                                        esc_html_e( 'Niet geconfigureerd', 'bossier-calculator' );
                                    }
                                    ?>
                                </td>
                                <td><span style="color: #059669;">✓ <?php esc_html_e( 'Override actief', 'bossier-calculator' ); ?></span></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Afrekenen', 'bossier-calculator' ); ?></strong></td>
                                <td>
                                    <?php
                                    $checkout_page_id = wc_get_page_id( 'checkout' );
                                    if ( $checkout_page_id > 0 ) {
                                        echo '<a href="' . esc_url( get_permalink( $checkout_page_id ) ) . '" target="_blank">' . esc_html( get_the_title( $checkout_page_id ) ) . '</a>';
                                    } else {
                                        esc_html_e( 'Niet geconfigureerd', 'bossier-calculator' );
                                    }
                                    ?>
                                </td>
                                <td><span style="color: #059669;">✓ <?php esc_html_e( 'Override actief', 'bossier-calculator' ); ?></span></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></strong></td>
                                <td><?php esc_html_e( 'Order Received (Bedankpagina)', 'bossier-calculator' ); ?></td>
                                <td><span style="color: #059669;">✓ <?php esc_html_e( 'Override actief', 'bossier-calculator' ); ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Compatibiliteit', 'bossier-calculator' ); ?></h2>
                    <div class="boost-info-box">
                        <h4><?php esc_html_e( 'Betaalmethodes', 'bossier-calculator' ); ?></h4>
                        <p><?php esc_html_e( 'Boost WooPages werkt met alle actieve betaalmethodes in WooCommerce. De templates gebruiken de standaard WooCommerce payment hooks.', 'bossier-calculator' ); ?></p>

                        <?php
                        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        if ( ! empty( $available_gateways ) ) :
                        ?>
                        <p><strong><?php esc_html_e( 'Actieve betaalmethodes:', 'bossier-calculator' ); ?></strong></p>
                        <ul>
                            <?php foreach ( $available_gateways as $gateway ) : ?>
                                <li><?php echo esc_html( $gateway->get_title() ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>

                        <h4><?php esc_html_e( 'Boost Calculator Integratie', 'bossier-calculator' ); ?></h4>
                        <p><?php esc_html_e( 'Alle calculator functionaliteit wordt volledig ondersteund:', 'bossier-calculator' ); ?></p>
                        <ul>
                            <li><?php esc_html_e( 'Product specificaties (kleur, lengte, afmetingen)', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Berekend gewicht', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Aangepaste prijzen', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'BTW Verlegd (indien actief)', 'bossier-calculator' ); ?></li>
                            <li><?php esc_html_e( 'Verzending zones (indien actief)', 'bossier-calculator' ); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="boost-settings-section">
                    <h2><?php esc_html_e( 'Uitschakelen', 'bossier-calculator' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Om Boost WooPages uit te schakelen, ga naar het Algemeen tabblad en schakel de "Boost WooPages" optie uit. WooCommerce zal dan de standaard thema templates gebruiken.', 'bossier-calculator' ); ?>
                    </p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php submit_button( __( 'Instellingen Opslaan', 'bossier-calculator' ) ); ?>
    </form>
</div>

<script type="text/html" id="tmpl-boost-zone-item">
    <div class="boost-repeater-item boost-zone-item" data-index="{{data.index}}">
        <div class="boost-repeater-header">
            <span class="boost-repeater-title"><?php esc_html_e( 'Nieuwe Zone', 'bossier-calculator' ); ?></span>
            <button type="button" class="boost-repeater-toggle dashicons dashicons-arrow-down-alt2"></button>
            <button type="button" class="boost-repeater-remove dashicons dashicons-trash"></button>
        </div>
        <div class="boost-repeater-content" style="display: block;">
            <input type="hidden"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][{{data.index}}][id]"
                   value="{{data.index}}">
            <p>
                <label><?php esc_html_e( 'Zone Naam', 'bossier-calculator' ); ?></label>
                <input type="text"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][{{data.index}}][name]"
                       value=""
                       class="regular-text boost-zone-name-input">
            </p>
            <p>
                <label><?php esc_html_e( 'Landen', 'bossier-calculator' ); ?></label>
                <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][{{data.index}}][countries][]"
                        multiple
                        class="boost-country-select">
                    <?php
                    $countries = WC()->countries->get_countries();
                    foreach ( $countries as $code => $name ) :
                    ?>
                        <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label><?php esc_html_e( 'Postcodes (reeks)', 'bossier-calculator' ); ?></label>
                <input type="text"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][{{data.index}}][postcodes]"
                       value=""
                       class="regular-text"
                       placeholder="1000-2999, 3500-3599">
            </p>
            <p>
                <label><?php esc_html_e( 'Levertijd (dagen)', 'bossier-calculator' ); ?></label>
                <input type="text"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][{{data.index}}][delivery_days]"
                       value=""
                       class="small-text"
                       placeholder="1-2">
            </p>

            <!-- Inline zone prices -->
            <div class="boost-zone-prices-inline">
                <h4><?php esc_html_e( 'Prijzen voor deze zone', 'bossier-calculator' ); ?></h4>
                <?php if ( ! empty( $shipping_methods ) ) : ?>
                <table class="boost-zone-price-table widefat">
                    <thead>
                        <tr>
                            <?php foreach ( $shipping_methods as $method ) : ?>
                                <th data-method-id="<?php echo esc_attr( $method['id'] ); ?>">
                                    <?php echo esc_html( $method['name'] ); ?>
                                    <button type="button" class="boost-exclude-method" data-method-id="<?php echo esc_attr( $method['id'] ); ?>" title="<?php esc_attr_e( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>">×</button>
                                </th>
                            <?php endforeach; ?>
                            <th data-method-id="loose">
                                <?php esc_html_e( 'Los (vast)', 'bossier-calculator' ); ?>
                                <button type="button" class="boost-exclude-method" data-method-id="loose" title="<?php esc_attr_e( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>">×</button>
                            </th>
                            <th data-method-id="loose_per_kg">
                                <?php esc_html_e( 'Los (/kg)', 'bossier-calculator' ); ?>
                                <button type="button" class="boost-exclude-method" data-method-id="loose_per_kg" title="<?php esc_attr_e( 'Verwijder methode uit zone', 'bossier-calculator' ); ?>">×</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ( $shipping_methods as $method ) : ?>
                                <td data-method-id="<?php echo esc_attr( $method['id'] ); ?>">
                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][{{data.index}}][<?php echo esc_attr( $method['id'] ); ?>]"
                                           value=""
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                    <input type="hidden"
                                           class="boost-zone-excluded-flag"
                                           data-method-id="<?php echo esc_attr( $method['id'] ); ?>"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][{{data.index}}][<?php echo esc_attr( $method['id'] ); ?>]"
                                           value="0">
                                </td>
                            <?php endforeach; ?>
                            <td data-method-id="loose">
                                <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][{{data.index}}][loose]"
                                       value=""
                                       class="small-text"
                                       min="0"
                                       step="0.01">
                                <input type="hidden"
                                       class="boost-zone-excluded-flag"
                                       data-method-id="loose"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][{{data.index}}][loose]"
                                       value="0">
                            </td>
                            <td data-method-id="loose_per_kg">
                                <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                <input type="number"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][{{data.index}}][loose_per_kg]"
                                       value=""
                                       class="small-text"
                                       min="0"
                                       step="0.01">
                                <input type="hidden"
                                       class="boost-zone-excluded-flag"
                                       data-method-id="loose_per_kg"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_excluded_methods][{{data.index}}][loose_per_kg]"
                                       value="0">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php else : ?>
                    <p class="description" style="color: #d63638;"><?php esc_html_e( 'Sla eerst verzendmethoden op voordat u zoneprijzen kunt instellen.', 'bossier-calculator' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</script>

<script type="text/html" id="tmpl-boost-pallet-item">
    <div class="boost-repeater-item boost-pallet-item" data-index="{{data.index}}">
        <div class="boost-repeater-header">
            <span class="boost-repeater-title"><?php esc_html_e( 'Nieuw Pallet', 'bossier-calculator' ); ?></span>
            <span class="boost-repeater-subtitle"></span>
            <button type="button" class="boost-repeater-toggle dashicons dashicons-arrow-down-alt2"></button>
            <button type="button" class="boost-repeater-remove dashicons dashicons-trash"></button>
        </div>
        <div class="boost-repeater-content" style="display: block;">
            <input type="hidden"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][{{data.index}}][id]"
                   value=""
                   class="boost-pallet-id-input">
            <p>
                <label><?php esc_html_e( 'Naam', 'bossier-calculator' ); ?></label>
                <input type="text"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][{{data.index}}][name]"
                       value=""
                       class="regular-text boost-pallet-name-input">
            </p>
            <p>
                <label><?php esc_html_e( 'Lengte (mm)', 'bossier-calculator' ); ?></label>
                <input type="number"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][{{data.index}}][length]"
                       value="1200"
                       class="small-text boost-pallet-length-input"
                       min="0"
                       step="1">
            </p>
            <p>
                <label><?php esc_html_e( 'Breedte (mm)', 'bossier-calculator' ); ?></label>
                <input type="number"
                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_pallets][{{data.index}}][width]"
                       value="800"
                       class="small-text boost-pallet-width-input"
                       min="0"
                       step="1">
            </p>
        </div>
    </div>
</script>

<script type="text/html" id="tmpl-boost-shipping-method-row">
    <tr class="boost-shipping-method-row" data-index="{{data.index}}">
        <td>
            <input type="hidden"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][id]"
                   value="">
            <input type="text"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][name]"
                   value=""
                   class="regular-text boost-method-name-input"
                   placeholder="<?php esc_attr_e( 'bijv. Pallet', 'bossier-calculator' ); ?>">
        </td>
        <td>
            <select name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][pallet_type]">
                <option value=""><?php esc_html_e( '— Alle —', 'bossier-calculator' ); ?></option>
                <?php foreach ( $settings['shipping_pallets'] as $pallet ) : ?>
                    <option value="<?php echo esc_attr( $pallet['id'] ); ?>"><?php echo esc_html( $pallet['name'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][max_weight]"
                   value="800"
                   class="small-text"
                   min="1"
                   step="1"> kg
        </td>
        <td>
            <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
            <input type="number"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][base_price]"
                   value="0"
                   class="small-text"
                   min="0"
                   step="0.01">
        </td>
        <td style="text-align: center;">
            <input type="checkbox"
                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_methods][{{data.index}}][enabled]"
                   value="1"
                   checked>
        </td>
        <td>
            <button type="button" class="button boost-remove-shipping-method" title="<?php esc_attr_e( 'Verwijderen', 'bossier-calculator' ); ?>">
                <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
            </button>
        </td>
    </tr>
</script>
