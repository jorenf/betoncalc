<?php
/**
 * Plugin Updater class.
 *
 * Handles automatic updates from private GitHub repository.
 * Token is embedded (obfuscated) - no configuration needed at customer sites.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Updater class - Manages plugin updates from GitHub.
 */
class Updater {

	/**
	 * GitHub repository owner/name.
	 *
	 * @var string
	 */
	private $github_repo = 'jorenf/betoncalc';

	/**
	 * GitHub branch for downloading releases.
	 * Note: We use GitHub Releases for version detection, not branch-based.
	 *
	 * @var string
	 */
	private $github_branch = 'claude/woocommerce-calculator-plugin-JnuB2';

	/**
	 * Update checker instance.
	 *
	 * @var object|null
	 */
	private $update_checker = null;

	/**
	 * Singleton instance.
	 *
	 * @var Updater|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Updater
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_update_checker();
		$this->register_admin_page();
	}

	/**
	 * Get the authentication credential.
	 * Obfuscated to prevent casual discovery.
	 *
	 * @return string
	 */
	private function get_auth_credential() {
		// Obfuscated credential - split and encoded.
		$parts = array(
			base64_decode( 'Z2hwXzZyMEY1' ),
			base64_decode( 'emtLMXA5eVlk' ),
			base64_decode( 'R3pjMGtYaGhk' ),
			base64_decode( 'TmlOd09ROTEz' ),
			base64_decode( 'MGNQcw==' ),
		);
		return implode( '', $parts );
	}

	/**
	 * Initialize the update checker.
	 */
	private function init_update_checker() {
		// Load the Plugin Update Checker library.
		$puc_file = BOSSIER_CALC_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $puc_file ) ) {
			return;
		}

		require_once $puc_file;

		// Check if the factory class exists.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// Build GitHub URL.
		$github_url = 'https://github.com/' . $this->github_repo;

		// Initialize update checker.
		$this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$github_url,
			BOSSIER_CALC_PLUGIN_FILE,
			'bossier-calculator-builder'
		);

		// Set authentication with obfuscated credential.
		$this->update_checker->setAuthentication( $this->get_auth_credential() );

		// Use GitHub Releases for version detection (not branch-based).
		// This avoids the subdirectory issue since releases use tag names for versions.
		// The release should have a tag like "v1.0.2" and include the plugin ZIP as an asset,
		// or use the auto-generated source ZIP from GitHub.

		// Filter to add changelog to plugin info.
		add_filter( 'puc_request_info_result-bossier-calculator-builder', array( $this, 'add_changelog_to_info' ), 10, 2 );
	}

	/**
	 * Add changelog to plugin info popup.
	 *
	 * @param object $info   Plugin info object.
	 * @param object $result Request result.
	 * @return object Modified info object.
	 */
	public function add_changelog_to_info( $info, $result ) {
		if ( ! is_object( $info ) ) {
			return $info;
		}

		// Read changelog.txt from plugin.
		$changelog_file = BOSSIER_CALC_PLUGIN_DIR . 'changelog.txt';
		if ( file_exists( $changelog_file ) ) {
			$changelog = file_get_contents( $changelog_file );
			if ( ! empty( $changelog ) ) {
				$info->sections['changelog'] = $this->parse_changelog( $changelog );
			}
		}

		return $info;
	}

	/**
	 * Parse changelog.txt to HTML.
	 *
	 * @param string $changelog Raw changelog text.
	 * @return string HTML formatted changelog.
	 */
	private function parse_changelog( $changelog ) {
		$html  = '';
		$lines = explode( "\n", $changelog );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			// Version header: = 1.0.0 =
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $matches ) ) {
				if ( ! empty( $html ) ) {
					$html .= '</ul>';
				}
				$html .= '<h4>' . esc_html( $matches[1] ) . '</h4><ul>';
				continue;
			}

			// Bullet point: - Fixed something
			if ( preg_match( '/^-\s*(.+)$/', $line, $matches ) ) {
				$html .= '<li>' . esc_html( $matches[1] ) . '</li>';
				continue;
			}
		}

		if ( ! empty( $html ) ) {
			$html .= '</ul>';
		}

		return $html;
	}

	/**
	 * Register admin page.
	 */
	private function register_admin_page() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Bossier Updater', 'bossier-calculator' ),
			__( 'Bossier Updater', 'bossier-calculator' ),
			'manage_options',
			'bossier-updater',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Handle admin actions.
	 */
	public function handle_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Force update check.
		if ( isset( $_GET['bossier_force_check'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'bossier_force_check' ) ) {
			$this->force_update_check();
			wp_redirect( admin_url( 'tools.php?page=bossier-updater&checked=1' ) );
			exit;
		}
	}

	/**
	 * Force update check.
	 */
	public function force_update_check() {
		if ( $this->update_checker ) {
			$this->update_checker->checkForUpdates();
		}

		// Store last check time.
		update_option( 'bossier_last_update_check', current_time( 'mysql' ) );
	}

	/**
	 * Get latest version from GitHub.
	 *
	 * @return string|null
	 */
	public function get_latest_version() {
		if ( ! $this->update_checker ) {
			return null;
		}

		$update = $this->update_checker->getUpdate();
		return $update ? $update->version : null;
	}

	/**
	 * Check if update is available.
	 *
	 * @return bool
	 */
	public function has_update() {
		$latest = $this->get_latest_version();
		if ( ! $latest ) {
			return false;
		}
		return version_compare( $latest, BOSSIER_CALC_VERSION, '>' );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$current_version = BOSSIER_CALC_VERSION;
		$latest_version  = $this->get_latest_version();
		$last_check      = get_option( 'bossier_last_update_check', __( 'Nooit', 'bossier-calculator' ) );
		$has_update      = $this->has_update();

		// Check for notices.
		$checked = isset( $_GET['checked'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Boost Calculator Updater', 'bossier-calculator' ); ?></h1>

			<?php if ( $checked ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Update check voltooid.', 'bossier-calculator' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width: 600px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Versie Status', 'bossier-calculator' ); ?></h2>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Huidige versie', 'bossier-calculator' ); ?></th>
						<td><code><?php echo esc_html( $current_version ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Laatste versie', 'bossier-calculator' ); ?></th>
						<td>
							<?php if ( $latest_version ) : ?>
								<code><?php echo esc_html( $latest_version ); ?></code>
								<?php if ( $has_update ) : ?>
									<span style="color: #d63638; margin-left: 10px;">
										<?php esc_html_e( 'Update beschikbaar!', 'bossier-calculator' ); ?>
									</span>
								<?php else : ?>
									<span style="color: #00a32a; margin-left: 10px;">
										<?php esc_html_e( 'Up to date', 'bossier-calculator' ); ?>
									</span>
								<?php endif; ?>
							<?php else : ?>
								<em><?php esc_html_e( 'Controleer verbinding met update server', 'bossier-calculator' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Laatste controle', 'bossier-calculator' ); ?></th>
						<td><?php echo esc_html( $last_check ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Update bron', 'bossier-calculator' ); ?></th>
						<td>
							<span style="color: #00a32a;">
								<?php esc_html_e( 'GitHub (automatisch geauthenticeerd)', 'bossier-calculator' ); ?>
							</span>
						</td>
					</tr>
				</table>

				<p>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=bossier-updater&bossier_force_check=1' ), 'bossier_force_check' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Controleer op updates', 'bossier-calculator' ); ?>
					</a>

					<?php if ( $has_update ) : ?>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button" style="margin-left: 10px;">
							<?php esc_html_e( 'Ga naar plugins', 'bossier-calculator' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>

			<div class="card" style="max-width: 600px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Changelog', 'bossier-calculator' ); ?></h2>
				<?php
				$changelog_file = BOSSIER_CALC_PLUGIN_DIR . 'changelog.txt';
				if ( file_exists( $changelog_file ) ) {
					$changelog = file_get_contents( $changelog_file );
					echo wp_kses_post( $this->parse_changelog( $changelog ) );
				} else {
					echo '<p><em>' . esc_html__( 'Geen changelog beschikbaar.', 'bossier-calculator' ) . '</em></p>';
				}
				?>
			</div>
		</div>
		<?php
	}
}
