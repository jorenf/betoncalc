<?php
/**
 * Admin class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Admin;

use Bossier\Calculator\Plugin;
use Bossier\Calculator\Calculator;
use Bossier\Calculator\Field_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class - Handles calculator admin interface.
 */
class Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . Plugin::POST_TYPE, array( $this, 'save_calculator' ), 10, 2 );
        add_filter( 'manage_' . Plugin::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
        add_action( 'manage_' . Plugin::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'add_row_actions' ), 10, 2 );
        add_action( 'admin_action_bossier_duplicate_calculator', array( $this, 'handle_duplicate' ) );
        add_action( 'admin_action_bossier_export_calculator', array( $this, 'handle_export_single' ) );
        add_action( 'admin_action_bossier_export_all_calculators', array( $this, 'handle_export_all' ) );
        add_action( 'admin_action_bossier_import_calculators', array( $this, 'handle_import' ) );
        add_action( 'wp_ajax_bossier_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wp_ajax_nopriv_bossier_calculate_price', array( $this, 'ajax_calculate_price' ) );

        // Add import/export page
        add_action( 'admin_menu', array( $this, 'add_import_export_page' ) );

        // Hide WordPress admin footer on this plugin's pages
        add_filter( 'admin_footer_text', array( $this, 'hide_admin_footer_text' ) );
        add_filter( 'update_footer', array( $this, 'hide_admin_footer_version' ), 11 );
    }

    /**
     * Hide admin footer text on plugin pages.
     *
     * @param string $text Footer text.
     * @return string
     */
    public function hide_admin_footer_text( $text ) {
        if ( $this->is_plugin_admin_page() ) {
            return '';
        }
        return $text;
    }

    /**
     * Hide admin footer version on plugin pages.
     *
     * @param string $text Version text.
     * @return string
     */
    public function hide_admin_footer_version( $text ) {
        if ( $this->is_plugin_admin_page() ) {
            return '';
        }
        return $text;
    }

    /**
     * Check if current page is a plugin admin page.
     *
     * @return bool
     */
    private function is_plugin_admin_page() {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }
        return Plugin::POST_TYPE === $screen->post_type;
    }

    /**
     * Add meta boxes to calculator edit screen.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'bossier_calculator_fields',
            __( 'Calculator Velden', 'bossier-calculator' ),
            array( $this, 'render_fields_meta_box' ),
            Plugin::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'bossier_calculator_settings',
            __( 'Calculator Instellingen', 'bossier-calculator' ),
            array( $this, 'render_settings_meta_box' ),
            Plugin::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'bossier_calculator_preview',
            __( 'Voorvertoning', 'bossier-calculator' ),
            array( $this, 'render_preview_meta_box' ),
            Plugin::POST_TYPE,
            'side',
            'low'
        );
    }

    /**
     * Render fields meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_fields_meta_box( $post ) {
        $calculator  = new Calculator( $post->ID );
        $fields      = $calculator->get_fields();
        $field_types = Field_Types::get_types();

        wp_nonce_field( 'bossier_calculator_save', 'bossier_calculator_nonce' );

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/calculator-fields.php';
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_settings_meta_box( $post ) {
        $calculator = new Calculator( $post->ID );
        $settings   = $calculator->get_settings();

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/calculator-settings.php';
    }

    /**
     * Render preview meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_preview_meta_box( $post ) {
        ?>
        <p class="description">
            <?php esc_html_e( 'Sla de calculator op en bezoek een gekoppeld product om de voorvertoning te zien.', 'bossier-calculator' ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Gekoppelde Producten:', 'bossier-calculator' ); ?></strong>
        </p>
        <?php
        $linked_products = $this->get_linked_products( $post->ID );

        if ( empty( $linked_products ) ) {
            echo '<p><em>' . esc_html__( 'Geen producten gekoppeld aan deze calculator.', 'bossier-calculator' ) . '</em></p>';
        } else {
            echo '<ul>';
            foreach ( $linked_products as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    printf(
                        '<li><a href="%s" target="_blank">%s</a></li>',
                        esc_url( get_permalink( $product_id ) ),
                        esc_html( $product->get_name() )
                    );
                }
            }
            echo '</ul>';
        }
    }

    /**
     * Get products linked to a calculator.
     *
     * @param int $calculator_id Calculator ID.
     * @return array Product IDs.
     */
    private function get_linked_products( $calculator_id ) {
        global $wpdb;

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_bossier_calculator_id'
                AND meta_value = %s",
                $calculator_id
            )
        );
    }

    /**
     * Save calculator data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function save_calculator( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['bossier_calculator_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bossier_calculator_nonce'] ) ), 'bossier_calculator_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Get and sanitize fields
        $fields = array();
        if ( isset( $_POST['bossier_fields'] ) && is_array( $_POST['bossier_fields'] ) ) {
            $fields = $this->sanitize_posted_fields( wp_unslash( $_POST['bossier_fields'] ) ); // phpcs:ignore
        }

        // Get and sanitize settings
        $settings = array();
        if ( isset( $_POST['bossier_settings'] ) && is_array( $_POST['bossier_settings'] ) ) {
            $settings = $this->sanitize_posted_settings( wp_unslash( $_POST['bossier_settings'] ) ); // phpcs:ignore
        }

        // Save via Calculator model
        $calculator = new Calculator( $post_id );
        $calculator->save( $fields, $settings );
    }

    /**
     * Sanitize posted fields.
     *
     * @param array $posted_fields Raw posted fields.
     * @return array Sanitized fields.
     */
    private function sanitize_posted_fields( $posted_fields ) {
        // Basic sanitization - the Calculator class does full sanitization
        $fields = array();

        foreach ( $posted_fields as $field_id => $field_data ) {
            $field_id = sanitize_key( $field_id );
            $fields[ $field_id ] = $field_data;
        }

        return $fields;
    }

    /**
     * Sanitize posted settings.
     *
     * @param array $posted_settings Raw posted settings.
     * @return array Sanitized settings.
     */
    private function sanitize_posted_settings( $posted_settings ) {
        // Basic sanitization - the Calculator class does full sanitization
        return $posted_settings;
    }

    /**
     * Add custom columns to calculator list.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_columns( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            if ( 'title' === $key ) {
                $new_columns['fields_count']   = __( 'Velden', 'bossier-calculator' );
                $new_columns['products_count'] = __( 'Gekoppelde Producten', 'bossier-calculator' );
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_column( $column, $post_id ) {
        $calculator = new Calculator( $post_id );

        switch ( $column ) {
            case 'fields_count':
                $fields = $calculator->get_enabled_fields();
                echo count( $fields );
                break;

            case 'products_count':
                $products = $this->get_linked_products( $post_id );
                echo count( $products );
                break;
        }
    }

    /**
     * Add row actions to calculator list.
     *
     * @param array    $actions Existing actions.
     * @param \WP_Post $post    Current post.
     * @return array Modified actions.
     */
    public function add_row_actions( $actions, $post ) {
        if ( Plugin::POST_TYPE !== $post->post_type ) {
            return $actions;
        }

        $duplicate_url = wp_nonce_url(
            admin_url( 'admin.php?action=bossier_duplicate_calculator&post=' . $post->ID ),
            'bossier_duplicate_' . $post->ID
        );

        $actions['duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $duplicate_url ),
            esc_html__( 'Dupliceren', 'bossier-calculator' )
        );

        $export_url = wp_nonce_url(
            admin_url( 'admin.php?action=bossier_export_calculator&post=' . $post->ID ),
            'bossier_export_' . $post->ID
        );

        $actions['export'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $export_url ),
            esc_html__( 'Exporteer', 'bossier-calculator' )
        );

        return $actions;
    }

    /**
     * Handle calculator duplication.
     */
    public function handle_duplicate() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        if ( ! $post_id ) {
            wp_die( esc_html__( 'Ongeldige calculator ID.', 'bossier-calculator' ) );
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bossier_duplicate_' . $post_id ) ) {
            wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'U heeft geen toestemming om deze calculator te dupliceren.', 'bossier-calculator' ) );
        }

        $calculator  = new Calculator( $post_id );
        $new_post_id = $calculator->duplicate();

        if ( $new_post_id ) {
            wp_safe_redirect(
                admin_url( 'post.php?action=edit&post=' . $new_post_id )
            );
            exit;
        } else {
            wp_die( esc_html__( 'Calculator dupliceren mislukt.', 'bossier-calculator' ) );
        }
    }

    /**
     * Add import/export submenu page.
     */
    public function add_import_export_page() {
        add_submenu_page(
            'edit.php?post_type=' . Plugin::POST_TYPE,
            __( 'Import / Export', 'bossier-calculator' ),
            __( 'Import / Export', 'bossier-calculator' ),
            'manage_options',
            'bossier-import-export',
            array( $this, 'render_import_export_page' )
        );
    }

    /**
     * Render import/export page.
     */
    public function render_import_export_page() {
        $export_all_url = wp_nonce_url(
            admin_url( 'admin.php?action=bossier_export_all_calculators' ),
            'bossier_export_all'
        );

        // Get all calculators for individual export
        $calculators = get_posts( array(
            'post_type'      => Plugin::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        // Check for import results
        $imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
        $updated  = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
        $errors   = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Calculator Import / Export', 'bossier-calculator' ); ?></h1>

            <?php if ( $imported > 0 || $updated > 0 ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        $messages = array();
                        if ( $imported > 0 ) {
                            $messages[] = sprintf(
                                /* translators: %d: number of calculators */
                                _n( '%d calculator geïmporteerd', '%d calculators geïmporteerd', $imported, 'bossier-calculator' ),
                                $imported
                            );
                        }
                        if ( $updated > 0 ) {
                            $messages[] = sprintf(
                                /* translators: %d: number of calculators */
                                _n( '%d calculator bijgewerkt', '%d calculators bijgewerkt', $updated, 'bossier-calculator' ),
                                $updated
                            );
                        }
                        echo esc_html( implode( ', ', $messages ) ) . '.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ( $errors > 0 ) : ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php
                        printf(
                            /* translators: %d: number of errors */
                            esc_html( _n( '%d calculator kon niet worden geïmporteerd.', '%d calculators konden niet worden geïmporteerd.', $errors, 'bossier-calculator' ) ),
                            $errors
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php
            // Display validation warnings from transient
            $validation_warnings = get_transient( 'bossier_import_warnings' );
            if ( $validation_warnings && is_array( $validation_warnings ) ) :
                delete_transient( 'bossier_import_warnings' );
            ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong><?php esc_html_e( 'Validatie waarschuwingen:', 'bossier-calculator' ); ?></strong></p>
                    <ul style="margin-left: 20px; list-style: disc;">
                        <?php foreach ( $validation_warnings as $warning ) : ?>
                            <li><?php echo esc_html( $warning ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><em><?php esc_html_e( 'De import is wel uitgevoerd, maar controleer de bovenstaande velden.', 'bossier-calculator' ); ?></em></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 30px; margin-top: 20px;">
                <!-- Export Section -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e( 'Exporteren', 'bossier-calculator' ); ?></h2>

                    <p><?php esc_html_e( 'Exporteer calculators naar een JSON-bestand dat je kunt importeren op een andere website.', 'bossier-calculator' ); ?></p>

                    <h3><?php esc_html_e( 'Alle Calculators Exporteren', 'bossier-calculator' ); ?></h3>
                    <p>
                        <a href="<?php echo esc_url( $export_all_url ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Exporteer Alle Calculators', 'bossier-calculator' ); ?>
                        </a>
                    </p>

                    <?php if ( ! empty( $calculators ) ) : ?>
                        <h3><?php esc_html_e( 'Individuele Calculator Exporteren', 'bossier-calculator' ); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Calculator', 'bossier-calculator' ); ?></th>
                                    <th style="width: 120px;"><?php esc_html_e( 'Actie', 'bossier-calculator' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $calculators as $calc ) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $calc->post_title ); ?></strong>
                                            <span style="color: #666;"> (ID: <?php echo esc_html( $calc->ID ); ?>)</span>
                                        </td>
                                        <td>
                                            <?php
                                            $export_url = wp_nonce_url(
                                                admin_url( 'admin.php?action=bossier_export_calculator&post=' . $calc->ID ),
                                                'bossier_export_' . $calc->ID
                                            );
                                            ?>
                                            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-small">
                                                <?php esc_html_e( 'Exporteer', 'bossier-calculator' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><em><?php esc_html_e( 'Geen calculators gevonden.', 'bossier-calculator' ); ?></em></p>
                    <?php endif; ?>
                </div>

                <!-- Import Section -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e( 'Importeren', 'bossier-calculator' ); ?></h2>

                    <p><?php esc_html_e( 'Importeer calculators vanuit een JSON-bestand dat je hebt geëxporteerd.', 'bossier-calculator' ); ?></p>

                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?action=bossier_import_calculators' ) ); ?>">
                        <?php wp_nonce_field( 'bossier_import_calculators', 'bossier_import_nonce' ); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php esc_html_e( 'JSON Bestand', 'bossier-calculator' ); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="import_file" id="import_file" accept=".json" required>
                                    <p class="description"><?php esc_html_e( 'Selecteer een .json bestand om te importeren.', 'bossier-calculator' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="import_mode"><?php esc_html_e( 'Import Modus', 'bossier-calculator' ); ?></label>
                                </th>
                                <td>
                                    <select name="import_mode" id="import_mode">
                                        <option value="new"><?php esc_html_e( 'Importeer als nieuwe calculators', 'bossier-calculator' ); ?></option>
                                        <option value="replace"><?php esc_html_e( 'Vervang bestaande (op naam)', 'bossier-calculator' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( '"Vervang bestaande" overschrijft calculators met dezelfde naam.', 'bossier-calculator' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Importeren', 'bossier-calculator' ); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <?php
        // Pass existing calculator names to JavaScript
        $existing_names = array();
        foreach ( $calculators as $calc ) {
            $existing_names[] = $calc->post_title;
        }
        ?>
        <script>
        (function() {
            var existingNames = <?php echo wp_json_encode( $existing_names ); ?>;
            var form = document.querySelector('form[action*="bossier_import_calculators"]');
            var fileInput = document.getElementById('import_file');
            var modeSelect = document.getElementById('import_mode');

            if (!form || !fileInput || !modeSelect) return;

            form.addEventListener('submit', function(e) {
                var file = fileInput.files[0];
                var mode = modeSelect.value;

                if (!file) return;

                // Only check for duplicates in "new" mode
                if (mode !== 'new') return;

                e.preventDefault();

                var reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        var data = JSON.parse(event.target.result);
                        if (!data.calculators || !Array.isArray(data.calculators)) {
                            form.submit();
                            return;
                        }

                        // Find duplicates
                        var duplicates = [];
                        data.calculators.forEach(function(calc) {
                            if (calc.title && existingNames.indexOf(calc.title) !== -1) {
                                duplicates.push(calc.title);
                            }
                        });

                        if (duplicates.length > 0) {
                            var message = '<?php echo esc_js( __( 'Let op! De volgende calculators bestaan al:', 'bossier-calculator' ) ); ?>\n\n';
                            message += duplicates.join('\n');
                            message += '\n\n<?php echo esc_js( __( 'Dit zal duplicaten aanmaken. Wil je doorgaan?', 'bossier-calculator' ) ); ?>\n';
                            message += '<?php echo esc_js( __( '(Tip: Gebruik "Vervang bestaande" om te overschrijven)', 'bossier-calculator' ) ); ?>';

                            if (confirm(message)) {
                                form.submit();
                            }
                        } else {
                            form.submit();
                        }
                    } catch (err) {
                        // Invalid JSON, let server handle error
                        form.submit();
                    }
                };
                reader.readAsText(file);
            });
        })();
        </script>
        <?php
    }

    /**
     * Handle single calculator export.
     */
    public function handle_export_single() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        if ( ! $post_id ) {
            wp_die( esc_html__( 'Ongeldige calculator ID.', 'bossier-calculator' ) );
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bossier_export_' . $post_id ) ) {
            wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'U heeft geen toestemming voor deze actie.', 'bossier-calculator' ) );
        }

        $export_data = $this->get_export_data( array( $post_id ) );
        $this->send_json_download( $export_data, 'calculator-export' );
    }

    /**
     * Handle export all calculators.
     */
    public function handle_export_all() {
        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bossier_export_all' ) ) {
            wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'U heeft geen toestemming voor deze actie.', 'bossier-calculator' ) );
        }

        // Get all calculator IDs
        $calculator_ids = get_posts( array(
            'post_type'      => Plugin::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ) );

        if ( empty( $calculator_ids ) ) {
            wp_die( esc_html__( 'Geen calculators gevonden om te exporteren.', 'bossier-calculator' ) );
        }

        $export_data = $this->get_export_data( $calculator_ids );
        $this->send_json_download( $export_data, 'all-calculators-export' );
    }

    /**
     * Get export data for given calculator IDs.
     *
     * @param array $calculator_ids Array of calculator post IDs.
     * @return array Export data.
     */
    private function get_export_data( $calculator_ids ) {
        $export = array(
            'plugin_version' => BOSSIER_CALC_VERSION,
            'export_date'    => current_time( 'mysql' ),
            'site_url'       => get_site_url(),
            'calculators'    => array(),
        );

        foreach ( $calculator_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || Plugin::POST_TYPE !== $post->post_type ) {
                continue;
            }

            $calculator = new Calculator( $post_id );

            $export['calculators'][] = array(
                'title'    => $post->post_title,
                'status'   => $post->post_status,
                'fields'   => $calculator->get_fields(),
                'settings' => $calculator->get_settings(),
            );
        }

        return $export;
    }

    /**
     * Send JSON data as file download.
     *
     * @param array  $data     Data to export.
     * @param string $filename Filename prefix.
     */
    private function send_json_download( $data, $filename ) {
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

        $filename = sanitize_file_name( $filename . '-' . gmdate( 'Y-m-d-His' ) . '.json' );

        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json ) );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Validate imported fields structure.
     *
     * @param array  $fields        Fields to validate.
     * @param string $calc_title    Calculator title for error messages.
     * @return array Array of warning messages.
     */
    private function validate_import_fields( $fields, $calc_title ) {
        $warnings = array();

        if ( ! is_array( $fields ) ) {
            $warnings[] = sprintf(
                /* translators: %s: calculator title */
                __( '%s: Velden zijn geen geldige array.', 'bossier-calculator' ),
                $calc_title
            );
            return $warnings;
        }

        foreach ( $fields as $field_id => $field ) {
            if ( ! is_array( $field ) ) {
                $warnings[] = sprintf(
                    /* translators: 1: calculator title, 2: field ID */
                    __( '%1$s: Veld "%2$s" is geen geldige array.', 'bossier-calculator' ),
                    $calc_title,
                    $field_id
                );
                continue;
            }

            $type  = isset( $field['type'] ) ? $field['type'] : '';
            $label = isset( $field['label'] ) ? $field['label'] : $field_id;

            if ( empty( $type ) ) {
                $warnings[] = sprintf(
                    /* translators: 1: calculator title, 2: field label */
                    __( '%1$s: Veld "%2$s" heeft geen type.', 'bossier-calculator' ),
                    $calc_title,
                    $label
                );
                continue;
            }

            // Validate specific field types
            switch ( $type ) {
                case 'mitre_angle':
                    // Must have either mitre_groups or angles
                    $has_groups = isset( $field['mitre_groups'] ) && is_array( $field['mitre_groups'] ) && ! empty( $field['mitre_groups'] );
                    $has_angles = isset( $field['angles'] ) && is_array( $field['angles'] ) && ! empty( $field['angles'] );

                    if ( ! $has_groups && ! $has_angles ) {
                        $warnings[] = sprintf(
                            /* translators: 1: calculator title, 2: field label */
                            __( '%1$s: Verstekhoek veld "%2$s" heeft geen hoeken geconfigureerd.', 'bossier-calculator' ),
                            $calc_title,
                            $label
                        );
                    } elseif ( $has_groups ) {
                        // Validate each group has angles
                        foreach ( $field['mitre_groups'] as $group_idx => $group ) {
                            if ( ! isset( $group['angles'] ) || ! is_array( $group['angles'] ) || empty( $group['angles'] ) ) {
                                $group_label = isset( $group['label'] ) ? $group['label'] : ( $group_idx + 1 );
                                $warnings[] = sprintf(
                                    /* translators: 1: calculator title, 2: field label, 3: group label */
                                    __( '%1$s: Verstekhoek "%2$s" groep "%3$s" heeft geen hoeken.', 'bossier-calculator' ),
                                    $calc_title,
                                    $label,
                                    $group_label
                                );
                            }
                        }
                    }
                    break;

                case 'length':
                    // Validate min/max
                    $min = isset( $field['min'] ) ? floatval( $field['min'] ) : 0;
                    $max = isset( $field['max'] ) ? floatval( $field['max'] ) : 0;
                    if ( $max > 0 && $min > $max ) {
                        $warnings[] = sprintf(
                            /* translators: 1: calculator title, 2: field label */
                            __( '%1$s: Lengte veld "%2$s" heeft minimum groter dan maximum.', 'bossier-calculator' ),
                            $calc_title,
                            $label
                        );
                    }
                    break;

                case 'color':
                    if ( ! isset( $field['colors'] ) || ! is_array( $field['colors'] ) || empty( $field['colors'] ) ) {
                        $warnings[] = sprintf(
                            /* translators: 1: calculator title, 2: field label */
                            __( '%1$s: Kleur veld "%2$s" heeft geen kleuren geconfigureerd.', 'bossier-calculator' ),
                            $calc_title,
                            $label
                        );
                    }
                    break;

                case 'custom':
                    if ( ! isset( $field['custom_options'] ) || ! is_array( $field['custom_options'] ) || empty( $field['custom_options'] ) ) {
                        $warnings[] = sprintf(
                            /* translators: 1: calculator title, 2: field label */
                            __( '%1$s: Extra veld "%2$s" heeft geen opties geconfigureerd.', 'bossier-calculator' ),
                            $calc_title,
                            $label
                        );
                    }
                    break;
            }
        }

        return $warnings;
    }

    /**
     * Handle calculator import.
     */
    public function handle_import() {
        // Verify nonce
        if ( ! isset( $_POST['bossier_import_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bossier_import_nonce'] ) ), 'bossier_import_calculators' ) ) {
            wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'U heeft geen toestemming voor deze actie.', 'bossier-calculator' ) );
        }

        // Check file upload
        if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
            wp_die( esc_html__( 'Geen bestand geüpload.', 'bossier-calculator' ) );
        }

        $file = $_FILES['import_file'];

        // Validate file extension directly (wp_check_filetype doesn't recognize .json by default)
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( 'json' !== $file_ext ) {
            wp_die( esc_html__( 'Ongeldig bestandstype. Alleen JSON-bestanden zijn toegestaan.', 'bossier-calculator' ) );
        }

        // Read and parse JSON
        $json_content = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $import_data  = json_decode( $json_content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_die( esc_html__( 'Ongeldig JSON-bestand.', 'bossier-calculator' ) );
        }

        if ( ! isset( $import_data['calculators'] ) || ! is_array( $import_data['calculators'] ) ) {
            wp_die( esc_html__( 'Ongeldig exportbestand. Geen calculators gevonden.', 'bossier-calculator' ) );
        }

        $import_mode = isset( $_POST['import_mode'] ) ? sanitize_key( $_POST['import_mode'] ) : 'new';
        $imported    = 0;
        $updated     = 0;
        $errors      = 0;

        $validation_warnings = array();

        foreach ( $import_data['calculators'] as $calc_idx => $calc_data ) {
            $title    = isset( $calc_data['title'] ) ? sanitize_text_field( $calc_data['title'] ) : '';
            $status   = isset( $calc_data['status'] ) ? sanitize_key( $calc_data['status'] ) : 'publish';
            $fields   = isset( $calc_data['fields'] ) && is_array( $calc_data['fields'] ) ? $calc_data['fields'] : array();
            $settings = isset( $calc_data['settings'] ) && is_array( $calc_data['settings'] ) ? $calc_data['settings'] : array();

            if ( empty( $title ) ) {
                $errors++;
                $validation_warnings[] = sprintf(
                    /* translators: %d: calculator index */
                    __( 'Calculator #%d: Titel ontbreekt.', 'bossier-calculator' ),
                    $calc_idx + 1
                );
                continue;
            }

            // Validate fields structure
            $field_warnings = $this->validate_import_fields( $fields, $title );
            if ( ! empty( $field_warnings ) ) {
                $validation_warnings = array_merge( $validation_warnings, $field_warnings );
            }

            $existing_id = null;

            // Check for existing calculator with same name
            if ( 'replace' === $import_mode ) {
                $existing = get_posts( array(
                    'post_type'      => Plugin::POST_TYPE,
                    'title'          => $title,
                    'posts_per_page' => 1,
                    'post_status'    => 'any',
                    'fields'         => 'ids',
                ) );

                if ( ! empty( $existing ) ) {
                    $existing_id = $existing[0];
                }
            }

            if ( $existing_id ) {
                // Update existing calculator
                $calculator = new Calculator( $existing_id );
                $calculator->save( $fields, $settings );
                $updated++;
            } else {
                // Create new calculator
                $post_id = wp_insert_post( array(
                    'post_type'   => Plugin::POST_TYPE,
                    'post_title'  => $title,
                    'post_status' => $status,
                ) );

                if ( is_wp_error( $post_id ) ) {
                    $errors++;
                    continue;
                }

                $calculator = new Calculator( $post_id );
                $calculator->save( $fields, $settings );
                $imported++;
            }
        }

        // Store warnings in transient if any
        if ( ! empty( $validation_warnings ) ) {
            set_transient( 'bossier_import_warnings', $validation_warnings, 60 );
        }

        // Redirect with success message
        $redirect_url = add_query_arg(
            array(
                'page'     => 'bossier-import-export',
                'imported' => $imported,
                'updated'  => $updated,
                'errors'   => $errors,
                'warnings' => count( $validation_warnings ),
            ),
            admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * AJAX handler for price calculation.
     */
    public function ajax_calculate_price() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bossier_calculator_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) ) );
        }

        $calculator_id = isset( $_POST['calculator_id'] ) ? absint( $_POST['calculator_id'] ) : 0;
        $selections    = isset( $_POST['selections'] ) ? $this->sanitize_ajax_selections( wp_unslash( $_POST['selections'] ) ) : array(); // phpcs:ignore

        if ( ! $calculator_id ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldige calculator.', 'bossier-calculator' ) ) );
        }

        $result = \Bossier\Calculator\Price_Calculator::calculate_from_request( $calculator_id, $selections );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Berekening mislukt.', 'bossier-calculator' ) ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Sanitize AJAX selections.
     *
     * @param array $selections Raw selections.
     * @return array Sanitized selections.
     */
    private function sanitize_ajax_selections( $selections ) {
        if ( ! is_array( $selections ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $selections as $key => $value ) {
            $key = sanitize_key( $key );

            if ( is_array( $value ) ) {
                $sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
            } else {
                $sanitized[ $key ] = sanitize_text_field( $value );
            }
        }

        return $sanitized;
    }
}
