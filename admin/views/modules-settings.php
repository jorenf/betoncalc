<?php
/**
 * Modules settings page view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

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
            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Afhalen', 'bossier-calculator' ); ?></h2>

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

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Verzendingszones', 'bossier-calculator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Definieer zones met land en postcode reeksen. Formaat: NL:1000-2999 of BE:1000-1999', 'bossier-calculator' ); ?></p>

                <div id="boost-shipping-zones" class="boost-repeater">
                    <?php
                    $zones = $settings['shipping_zones'];
                    if ( empty( $zones ) ) {
                        $zones = array( array( 'id' => 1, 'name' => '', 'countries' => array(), 'postcodes' => '', 'delivery_days' => '' ) );
                    }
                    foreach ( $zones as $index => $zone ) :
                    ?>
                    <div class="boost-repeater-item boost-zone-item" data-index="<?php echo esc_attr( $index ); ?>">
                        <div class="boost-repeater-header">
                            <span class="boost-repeater-title"><?php echo esc_html( $zone['name'] ?: __( 'Nieuwe Zone', 'bossier-calculator' ) ); ?></span>
                            <button type="button" class="boost-repeater-toggle dashicons dashicons-arrow-down-alt2"></button>
                            <button type="button" class="boost-repeater-remove dashicons dashicons-trash"></button>
                        </div>
                        <div class="boost-repeater-content">
                            <input type="hidden"
                                   name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][id]"
                                   value="<?php echo esc_attr( $zone['id'] ?? $index + 1 ); ?>">

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
                                <label><?php esc_html_e( 'Postcodes (reeks)', 'bossier-calculator' ); ?></label>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][postcodes]"
                                       value="<?php echo esc_attr( $zone['postcodes'] ?? '' ); ?>"
                                       class="regular-text"
                                       placeholder="1000-2999, 3500-3599">
                                <span class="description"><?php esc_html_e( 'Bijv: 1000-2999, 3500-3599', 'bossier-calculator' ); ?></span>
                            </p>
                            <p>
                                <label><?php esc_html_e( 'Levertijd (dagen)', 'bossier-calculator' ); ?></label>
                                <input type="text"
                                       name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zones][<?php echo esc_attr( $index ); ?>][delivery_days]"
                                       value="<?php echo esc_attr( $zone['delivery_days'] ?? '' ); ?>"
                                       class="small-text"
                                       placeholder="1-2">
                                <span class="description"><?php esc_html_e( 'Bijv: 1-2 of 3-5', 'bossier-calculator' ); ?></span>
                            </p>
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

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Pallet Types', 'bossier-calculator' ); ?></h2>
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
            </div>

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Zone Prijzen', 'bossier-calculator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Stel prijzen in per zone en pallet type. Inclusief losse zending tarieven.', 'bossier-calculator' ); ?></p>

                <table class="boost-pricing-matrix widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Zone', 'bossier-calculator' ); ?></th>
                            <?php foreach ( $settings['shipping_pallets'] as $pallet ) : ?>
                                <th><?php echo esc_html( $pallet['name'] ); ?></th>
                            <?php endforeach; ?>
                            <th><?php esc_html_e( 'Los (vast)', 'bossier-calculator' ); ?></th>
                            <th><?php esc_html_e( 'Los (per kg)', 'bossier-calculator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $settings['shipping_zones'] as $zone ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $zone['name'] ); ?></strong></td>
                                <?php foreach ( $settings['shipping_pallets'] as $pallet ) : ?>
                                    <td>
                                        <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                        <input type="number"
                                               name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][<?php echo esc_attr( $pallet['id'] ); ?>]"
                                               value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ][ $pallet['id'] ] ?? '' ); ?>"
                                               class="small-text"
                                               min="0"
                                               step="0.01">
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][loose]"
                                           value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ]['loose'] ?? '' ); ?>"
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                </td>
                                <td>
                                    <span class="boost-currency-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                                    <input type="number"
                                           name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_zone_prices][<?php echo esc_attr( $zone['id'] ); ?>][loose_per_kg]"
                                           value="<?php echo esc_attr( $settings['shipping_zone_prices'][ $zone['id'] ]['loose_per_kg'] ?? '' ); ?>"
                                           class="small-text"
                                           min="0"
                                           step="0.01">
                                    <span class="description">/kg</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Oversized Items (> 1500mm)', 'bossier-calculator' ); ?></h2>

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
            </div>

            <div class="boost-settings-section">
                <h2><?php esc_html_e( 'Onbekende Postcode / Fallback', 'bossier-calculator' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Instellingen voor wanneer geen zone prijzen beschikbaar zijn.', 'bossier-calculator' ); ?></p>

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
                            <p class="description"><?php esc_html_e( 'Standaard verzendkosten wanneer geen zone prijzen beschikbaar zijn. Zet op 0 om verzending te blokkeren bij onbekende locaties.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Bericht Onbekende Locatie', 'bossier-calculator' ); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr( \Bossier\Calculator\Modules_Settings::OPTION_NAME ); ?>[shipping_unknown_postcode_message]"
                                      rows="2"
                                      class="large-text"><?php echo esc_textarea( $settings['shipping_unknown_postcode_message'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Dit bericht wordt getoond als de postcode niet in een zone valt en geen standaard verzendkosten zijn ingesteld.', 'bossier-calculator' ); ?></p>
                        </td>
                    </tr>
                </table>
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
