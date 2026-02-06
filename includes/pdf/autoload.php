<?php
/**
 * DOMPDF Autoloader.
 *
 * Loads DOMPDF and its dependencies for PDF generation.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

// Define DOMPDF paths.
define( 'BOSSIER_DOMPDF_DIR', dirname( __FILE__ ) . '/dompdf/' );
define( 'BOSSIER_FONTLIB_DIR', dirname( __FILE__ ) . '/php-font-lib/' );
define( 'BOSSIER_SVGLIB_DIR', dirname( __FILE__ ) . '/php-svg-lib/' );

// Register autoloader.
spl_autoload_register( function( $class ) {
	// DOMPDF classes.
	if ( strpos( $class, 'Dompdf\\' ) === 0 ) {
		$class_file = str_replace( 'Dompdf\\', '', $class );
		$class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
		$file_path  = BOSSIER_DOMPDF_DIR . 'src/' . $class_file . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}
	}

	// FontLib classes.
	if ( strpos( $class, 'FontLib\\' ) === 0 ) {
		$class_file = str_replace( 'FontLib\\', '', $class );
		$class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
		$file_path  = BOSSIER_FONTLIB_DIR . 'src/FontLib/' . $class_file . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}
	}

	// Svg classes.
	if ( strpos( $class, 'Svg\\' ) === 0 ) {
		$class_file = str_replace( 'Svg\\', '', $class );
		$class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
		$file_path  = BOSSIER_SVGLIB_DIR . 'src/Svg/' . $class_file . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}
	}

	// Sabberworm CSS Parser (required by php-svg-lib).
	if ( strpos( $class, 'Sabberworm\\CSS\\' ) === 0 ) {
		$class_file = str_replace( 'Sabberworm\\CSS\\', '', $class );
		$class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_file );
		$file_path  = BOSSIER_SVGLIB_DIR . 'src/Sabberworm/CSS/' . $class_file . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return true;
		}
	}

	return false;
});

// Include Cpdf.
if ( file_exists( BOSSIER_DOMPDF_DIR . 'lib/Cpdf.php' ) ) {
	require_once BOSSIER_DOMPDF_DIR . 'lib/Cpdf.php';
}
