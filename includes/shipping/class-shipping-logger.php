<?php
/**
 * Shipping Logger.
 *
 * Provides structured, admin-only logging for Boost Shipping cost calculations.
 * Log files are written to /wp-content/uploads/boost-shipping-logs/ and are
 * protected from public HTTP access via .htaccess and a silent index.php.
 *
 * Usage:
 *   Shipping_Logger::log( 'event_name', array( 'key' => 'value' ) );
 *
 * Logging only runs when 'shipping_debug_logging' is enabled in plugin settings.
 * All read/clear operations require the manage_options capability.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping_Logger class.
 */
class Shipping_Logger {

    /**
     * Log directory name inside wp-content/uploads/.
     */
    const LOG_DIR_NAME = 'boost-shipping-logs';

    /**
     * Maximum single log file size (bytes) before rotation.
     */
    const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5 MB

    /**
     * Maximum number of rotated log files to keep (per base name).
     */
    const MAX_LOG_FILES = 5;

    /**
     * Cached enabled state for the current request.
     *
     * @var bool|null
     */
    private static $enabled = null;

    /**
     * Current-request session identifier (8-char hex).
     *
     * @var string|null
     */
    private static $session_id = null;

    /**
     * In-memory buffer of log entries gathered during this request.
     * Entries are written to disk at PHP shutdown via flush().
     *
     * @var array
     */
    private static $buffer = array();

    /**
     * Whether the log directory has already been verified / created.
     *
     * @var bool
     */
    private static $dir_ready = false;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether debug logging is currently enabled.
     *
     * Result is cached after the first call so repeated checks are cheap.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        if ( null === self::$enabled ) {
            $settings      = Modules_Settings::get_settings();
            self::$enabled = ! empty( $settings['shipping_debug_logging'] );
        }

        return self::$enabled;
    }

    /**
     * Add a log entry for a shipping event.
     *
     * Entries are buffered in memory and written to disk at PHP shutdown
     * to avoid repeated file I/O during a single request.
     *
     * @param string $event Short, snake_case identifier (e.g. 'zone_match').
     * @param array  $data  Structured key/value data for this event.
     * @param string $level Severity: 'info', 'warning', or 'error'.
     */
    public static function log( string $event, array $data = array(), string $level = 'info' ): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        // Sanitize data: remove any keys that could expose credentials.
        unset( $data['password'], $data['secret'], $data['token'] );

        self::$buffer[] = array(
            'timestamp'  => gmdate( 'c' ),
            'session_id' => self::get_session_id(),
            'event'      => sanitize_key( $event ),
            'level'      => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info',
            'data'       => $data,
        );

        // Register the disk-flush shutdown function on first buffer entry.
        if ( 1 === count( self::$buffer ) ) {
            register_shutdown_function( array( static::class, 'flush' ) );
        }
    }

    /**
     * Write all buffered log entries to disk.
     *
     * Called automatically at PHP shutdown. May also be called manually
     * (e.g. in tests).
     */
    public static function flush(): void {
        if ( empty( self::$buffer ) ) {
            return;
        }

        if ( ! self::ensure_log_dir() ) {
            return;
        }

        $log_file = self::get_log_file();

        // Rotate before writing if the active file is too large.
        if ( file_exists( $log_file ) && filesize( $log_file ) >= self::MAX_LOG_SIZE ) {
            self::rotate_logs( $log_file );
        }

        $lines = '';
        foreach ( self::$buffer as $entry ) {
            $lines .= wp_json_encode( $entry ) . "\n";
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $log_file, $lines, FILE_APPEND | LOCK_EX );

        self::$buffer = array();
    }

    /**
     * Retrieve log entries from the active log file, newest first.
     *
     * Only administrators (manage_options) may call this method; all others
     * receive an empty array.
     *
     * @param int    $limit   Maximum entries to return. 0 = unlimited.
     * @param string $session Filter by session_id; empty string = all sessions.
     * @return array Array of decoded log entry arrays.
     */
    public static function get_logs( int $limit = 200, string $session = '' ): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array();
        }

        $log_file = self::get_log_file();

        if ( ! file_exists( $log_file ) ) {
            return array();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $log_file );

        if ( false === $content || '' === $content ) {
            return array();
        }

        $lines   = array_filter( explode( "\n", trim( $content ) ) );
        $entries = array();

        foreach ( $lines as $line ) {
            $decoded = json_decode( $line, true );
            if ( is_array( $decoded ) ) {
                if ( '' !== $session && ( $decoded['session_id'] ?? '' ) !== $session ) {
                    continue;
                }
                $entries[] = $decoded;
            }
        }

        // Newest entries first.
        $entries = array_reverse( $entries );

        if ( $limit > 0 ) {
            $entries = array_slice( $entries, 0, $limit );
        }

        return $entries;
    }

    /**
     * Return the list of unique session IDs present in the active log file.
     *
     * Only administrators may call this method.
     *
     * @return string[] Session IDs, newest-first.
     */
    public static function get_sessions(): array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array();
        }

        $entries = self::get_logs( 0 );
        $seen    = array();
        $result  = array();

        foreach ( $entries as $entry ) {
            $sid = $entry['session_id'] ?? '';
            if ( '' !== $sid && ! isset( $seen[ $sid ] ) ) {
                $seen[ $sid ] = true;
                $result[]     = $sid;
            }
        }

        return $result;
    }

    /**
     * Delete all log files in the log directory.
     *
     * Only administrators may call this method.
     *
     * @return bool True on success.
     */
    public static function clear_logs(): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $log_dir = self::get_log_dir();

        if ( ! is_dir( $log_dir ) ) {
            return true;
        }

        $files   = glob( $log_dir . DIRECTORY_SEPARATOR . '*.log' );
        $success = true;

        if ( is_array( $files ) ) {
            foreach ( $files as $file ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                if ( ! unlink( $file ) ) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Absolute path to the log directory.
     *
     * @return string
     */
    public static function get_log_dir(): string {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . self::LOG_DIR_NAME;
    }

    /**
     * Absolute path to the active (today's) log file.
     *
     * @return string
     */
    public static function get_log_file(): string {
        return self::get_log_dir() . DIRECTORY_SEPARATOR . 'shipping-' . gmdate( 'Y-m-d' ) . '.log';
    }

    /**
     * Get or create the session identifier for this HTTP request.
     *
     * @return string 8-character hex string.
     */
    public static function get_session_id(): string {
        if ( null === self::$session_id ) {
            self::$session_id = substr( md5( uniqid( 'bsl', true ) ), 0, 8 );
        }

        return self::$session_id;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure the log directory exists and has protection files in place.
     *
     * @return bool True when the directory is ready to use.
     */
    private static function ensure_log_dir(): bool {
        if ( self::$dir_ready ) {
            return true;
        }

        $log_dir = self::get_log_dir();

        if ( ! is_dir( $log_dir ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
            if ( ! mkdir( $log_dir, 0755, true ) ) {
                return false;
            }
        }

        self::protect_log_dir( $log_dir );

        self::$dir_ready = true;

        return true;
    }

    /**
     * Write access-control files so the log directory cannot be browsed
     * or individual log files downloaded via HTTP.
     *
     * @param string $log_dir Absolute filesystem path to the log directory.
     */
    private static function protect_log_dir( string $log_dir ): void {
        // Apache: deny all direct HTTP access.
        $htaccess = $log_dir . DIRECTORY_SEPARATOR . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $content  = "# Boost Shipping Logs - block all direct HTTP access\n";
            $content .= "<IfModule mod_authz_core.c>\n";
            $content .= "    Require all denied\n";
            $content .= "</IfModule>\n";
            $content .= "<IfModule !mod_authz_core.c>\n";
            $content .= "    Order deny,allow\n";
            $content .= "    Deny from all\n";
            $content .= "</IfModule>\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $htaccess, $content );
        }

        // Nginx / direct PHP fallback: index.php that silently exits.
        $index = $log_dir . DIRECTORY_SEPARATOR . 'index.php';
        if ( ! file_exists( $index ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }
    }

    /**
     * Rotate log files to prevent any single file from growing too large.
     *
     * Shifts existing .N.log files: .4 → .5, .3 → .4 … .1 → .2,
     * then renames the current file to .1.log.
     * Files beyond MAX_LOG_FILES are deleted.
     *
     * @param string $log_file Active log file path.
     */
    private static function rotate_logs( string $log_file ): void {
        $log_dir = dirname( $log_file );
        $base    = basename( $log_file, '.log' );

        for ( $i = self::MAX_LOG_FILES - 1; $i >= 1; $i-- ) {
            $old = $log_dir . DIRECTORY_SEPARATOR . $base . '.' . $i . '.log';
            $new = $log_dir . DIRECTORY_SEPARATOR . $base . '.' . ( $i + 1 ) . '.log';

            if ( file_exists( $old ) ) {
                if ( $i + 1 > self::MAX_LOG_FILES ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                    unlink( $old );
                } else {
                    rename( $old, $new );
                }
            }
        }

        rename( $log_file, $log_dir . DIRECTORY_SEPARATOR . $base . '.1.log' );
    }
}
