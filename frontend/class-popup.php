<?php
/**
 * Frontend popup renderer.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Frontend;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Popup class - renders the configurable frontend notice popup.
 */
class Popup {

    /**
     * localStorage key used for the popup dismissal preference.
     *
     * @var string
     */
    const STORAGE_KEY = 'boost_popup_dismissed';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_popup' ) );
    }

    /**
     * Check whether the popup should be available on the frontend.
     *
     * @return bool
     */
    private function should_render() {
        if ( is_admin() ) {
            return false;
        }

        $settings = Modules_Settings::get_settings();
        return ! empty( $settings['popup_enabled'] );
    }

    /**
     * Enqueue popup assets only when the popup is enabled.
     */
    public function enqueue_assets() {
        if ( ! $this->should_render() ) {
            return;
        }

        wp_enqueue_style( 'dashicons' );

        wp_enqueue_style(
            'boost-popup-fonts',
            'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'boost-popup',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/popup.css',
            array( 'dashicons', 'boost-popup-fonts' ),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'boost-popup',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/popup.js',
            array(),
            BOSSIER_CALC_VERSION,
            true
        );
    }

    /**
     * Render icon class or image URL.
     *
     * @param string $icon_class Icon font class.
     * @param string $image_url  Image URL.
     * @param string $class_name Extra CSS class.
     */
    private function render_icon( $icon_class, $image_url, $class_name = '' ) {
        if ( ! empty( $image_url ) ) {
            echo '<img src="' . esc_url( $image_url ) . '" alt="" class="boost-popup__icon-img ' . esc_attr( $class_name ) . '">';
            return;
        }

        if ( ! empty( $icon_class ) ) {
            echo '<i class="' . esc_attr( $icon_class . ' ' . $class_name ) . '" aria-hidden="true"></i>';
        }
    }

    /**
     * Render popup markup in the footer.
     */
    public function render_popup() {
        if ( ! $this->should_render() ) {
            return;
        }

        $settings     = Modules_Settings::get_settings();
        $storage_days = max( 1, min( 365, absint( $settings['popup_storage_days'] ?? 30 ) ) );
        $details      = array();

        for ( $idx = 1; $idx <= 3; $idx++ ) {
            $text = $settings[ 'popup_detail_' . $idx . '_text' ] ?? '';
            if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
                continue;
            }

            $details[] = array(
                'text'  => $text,
                'icon'  => $settings[ 'popup_detail_' . $idx . '_icon' ] ?? '',
                'image' => $settings[ 'popup_detail_' . $idx . '_image' ] ?? '',
            );
        }

        $primary_url = $settings['popup_primary_url'] ?? '';
        ?>
        <div class="boost-popup" data-boost-popup data-storage-key="<?php echo esc_attr( self::STORAGE_KEY ); ?>" data-storage-days="<?php echo esc_attr( $storage_days ); ?>" hidden aria-hidden="true">
            <div class="boost-popup__overlay" data-boost-popup-overlay>
                <section class="boost-popup__card" role="dialog" aria-modal="true" aria-labelledby="boost-popup-title" aria-describedby="boost-popup-description">
                    <h2 class="boost-popup__sr-only"><?php echo esc_html( $settings['popup_screen_reader_title'] ?? '' ); ?></h2>

                    <div class="boost-popup__header">
                        <button type="button" class="boost-popup__close" aria-label="<?php echo esc_attr( $settings['popup_close_label'] ?? __( 'Sluiten', 'bossier-calculator' ) ); ?>">
                            <span aria-hidden="true">&times;</span>
                        </button>

                        <?php if ( ! empty( $settings['popup_badge_label'] ) ) : ?>
                            <div class="boost-popup__badge">
                                <?php $this->render_icon( $settings['popup_badge_icon'] ?? '', $settings['popup_badge_image'] ?? '', 'boost-popup__badge-icon' ); ?>
                                <span><?php echo esc_html( $settings['popup_badge_label'] ); ?></span>
                            </div>
                        <?php endif; ?>

                        <h3 id="boost-popup-title" class="boost-popup__title"><?php echo nl2br( esc_html( $settings['popup_title'] ?? '' ) ); ?></h3>

                        <?php if ( ! empty( $settings['popup_subtitle'] ) ) : ?>
                            <p class="boost-popup__subtitle"><?php echo esc_html( $settings['popup_subtitle'] ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="boost-popup__body" id="boost-popup-description">
                        <?php if ( ! empty( $settings['popup_info_text'] ) ) : ?>
                            <div class="boost-popup__info">
                                <p><?php echo wp_kses_post( $settings['popup_info_text'] ); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $details ) ) : ?>
                            <div class="boost-popup__details">
                                <?php foreach ( $details as $detail ) : ?>
                                    <div class="boost-popup__detail-row">
                                        <?php $this->render_icon( $detail['icon'], $detail['image'], 'boost-popup__detail-icon' ); ?>
                                        <span><?php echo wp_kses_post( $detail['text'] ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="boost-popup__actions">
                            <?php if ( ! empty( $settings['popup_primary_label'] ) ) : ?>
                                <?php if ( ! empty( $primary_url ) ) : ?>
                                    <a class="boost-popup__button boost-popup__button--primary" href="<?php echo esc_url( $primary_url ); ?>">
                                        <?php $this->render_icon( $settings['popup_primary_icon'] ?? '', $settings['popup_primary_image'] ?? '', 'boost-popup__action-icon' ); ?>
                                        <span><?php echo esc_html( $settings['popup_primary_label'] ); ?></span>
                                    </a>
                                <?php else : ?>
                                    <button type="button" class="boost-popup__button boost-popup__button--primary">
                                        <?php $this->render_icon( $settings['popup_primary_icon'] ?? '', $settings['popup_primary_image'] ?? '', 'boost-popup__action-icon' ); ?>
                                        <span><?php echo esc_html( $settings['popup_primary_label'] ); ?></span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ( ! empty( $settings['popup_secondary_label'] ) ) : ?>
                                <button type="button" class="boost-popup__button boost-popup__button--secondary">
                                    <?php echo esc_html( $settings['popup_secondary_label'] ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                </section>
            </div>
        </div>
        <?php
    }
}
