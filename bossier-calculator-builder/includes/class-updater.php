<?php
/**
 * Plugin Updater class.
 *
 * Handles automatic updates from GitHub repository.
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
	 * GitHub branch to check for updates.
	 *
	 * @var string
	 */
	private $github_branch = 'main';

	/**
	 * Update checker instance.
	 *
	 * @var \YahnisElsts\PluginUpdateChecker\v5\PucFactory|null
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

		// Get GitHub access token from constant or option.
		$access_token = $this->get_access_token();

		// Build GitHub URL.
		$github_url = 'https://github.com/' . $this->github_repo;

		// Initialize update checker.
		$this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$github_url,
			BOSSIER_CALC_PLUGIN_FILE,
			'bossier-calculator-builder'
		);

		// Set branch.
		$this->update_checker->setBranch( $this->github_branch );

		// Set authentication for private repos.
		if ( ! empty( $access_token ) ) {
			$this->update_checker->setAuthentication( $access_token );
		}

		// Filter to add changelog to plugin info.
		add_filter( 'puc_request_info_result-bossier-calculator-builder', array( $this, 'add_changelog_to_info' ), 10, 2 );
	}

	/**
	 * Get GitHub access token.
	 *
	 * First checks for constant, then WordPress option.
	 *
	 * @return string|null
	 */
	private function get_access_token() {
		// Check for constant defined in wp-config.php.
		if ( defined( 'BOSSIER_GITHUB_TOKEN' ) && ! empty( BOSSIER_GITHUB_TOKEN ) ) {
			return BOSSIER_GITHUB_TOKEN;
		}

		// Check for option (can be set via admin page).
		$token = get_option( 'bossier_github_token', '' );
		if ( ! empty( $token ) ) {
			return $token;
		}

		return null;
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

		// Read changelog.txt.
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
	 * Handle admin actions (force update check, save token).
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

		// Save GitHub token.
		if ( isset( $_POST['bossier_save_token'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bossier_save_token' ) ) {
			$token = isset( $_POST['bossier_github_token'] ) ? sanitize_text_field( $_POST['bossier_github_token'] ) : '';
			update_option( 'bossier_github_token', $token );
			wp_redirect( admin_url( 'tools.php?page=bossier-updater&saved=1' ) );
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
		$has_token       = ! empty( $this->get_access_token() );
		$token_constant  = defined( 'BOSSIER_GITHUB_TOKEN' );

		// Check for notices.
		$checked = isset( $_GET['checked'] );
		$saved   = isset( $_GET['saved'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bossier Calculator Updater', 'bossier-calculator' ); ?></h1>

			<?php if ( $checked ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Update check voltooid.', 'bossier-calculator' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'GitHub token opgeslagen.', 'bossier-calculator' ); ?></p>
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
						<th><?php esc_html_e( 'Laatste versie (GitHub)', 'bossier-calculator' ); ?></th>
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
								<em><?php esc_html_e( 'Niet beschikbaar', 'bossier-calculator' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Laatste controle', 'bossier-calculator' ); ?></th>
						<td><?php echo esc_html( $last_check ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'GitHub authenticatie', 'bossier-calculator' ); ?></th>
						<td>
							<?php if ( $has_token ) : ?>
								<span style="color: #00a32a;">
									<?php esc_html_e( 'Geconfigureerd', 'bossier-calculator' ); ?>
									<?php if ( $token_constant ) : ?>
										(<?php esc_html_e( 'via wp-config.php', 'bossier-calculator' ); ?>)
									<?php endif; ?>
								</span>
							<?php else : ?>
								<span style="color: #dba617;">
									<?php esc_html_e( 'Niet geconfigureerd (vereist voor privé repo)', 'bossier-calculator' ); ?>
								</span>
							<?php endif; ?>
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

			<?php if ( ! $token_constant ) : ?>
				<div class="card" style="max-width: 600px; margin-top: 20px;">
					<h2><?php esc_html_e( 'GitHub Token (voor privé repository)', 'bossier-calculator' ); ?></h2>

					<p class="description">
						<?php esc_html_e( 'Voor een privé GitHub repository is een Personal Access Token vereist. Je kunt deze hier invoeren of definieren in wp-config.php:', 'bossier-calculator' ); ?>
					</p>
					<p><code>define( 'BOSSIER_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxx' );</code></p>

					<form method="post">
						<?php wp_nonce_field( 'bossier_save_token' ); ?>
						<table class="form-table">
							<tr>
								<th>
									<label for="bossier_github_token"><?php esc_html_e( 'GitHub Token', 'bossier-calculator' ); ?></label>
								</th>
								<td>
									<input type="password"
										   id="bossier_github_token"
										   name="bossier_github_token"
										   value="<?php echo esc_attr( get_option( 'bossier_github_token', '' ) ); ?>"
										   class="regular-text">
									<p class="description">
										<?php
										printf(
											/* translators: %s: GitHub settings URL */
											esc_html__( 'Maak een token aan op %s met "repo" scope.', 'bossier-calculator' ),
											'<a href="https://github.com/settings/tokens" target="_blank">GitHub Settings</a>'
										);
										?>
									</p>
								</td>
							</tr>
						</table>
						<p>
							<button type="submit" name="bossier_save_token" class="button">
								<?php esc_html_e( 'Token opslaan', 'bossier-calculator' ); ?>
							</button>
						</p>
					</form>
				</div>
			<?php endif; ?>

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
