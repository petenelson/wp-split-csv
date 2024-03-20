<?php
/**
 * Plugin Name: WP Split CSV
 * Description: Splits an uploaded comma-delimited file into multiple files.
 * Version: 0.1.0
 * Text Domain: wp-split-csv
 * Requires at least: 6.3
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WPSplitCSV
 */

namespace WPSplitCSV;

defined( 'ABSPATH' ) || exit;

/**
 * Quickly provide a namespaced way to get functions.
 *
 * @param string $function Name of function in namespace.
 * @return string
 */
function n( $function ) {
	return __NAMESPACE__ . "\\$function";
}

/**
 * Setup hooks and filters.
 *
 * @return void
 */
function setup() {
	add_action( 'add_attachment', n( 'split_added_csv' ) );
}

/**
 * Splits a CSV into multiple files.
 *
 * @param string $file The full path and name to the uploaded file.
 * @return array|WP_Error List of new files, or any error that is encountered.
 */
function split_csv( $file, $args = [] ) {
	$results = [];

	$args = wp_parse_args(
		$args,
		[
			'delete_source_after_split' => false,
			'lines_per_file'            => 5, // TODO
			'pad_filename_with'         => '0',
		]
	);

	$args = apply_filters( 'wp_split_csv_args', $args, $file );

	if ( ! file_exists( $file ) ) {
		return new \WP_Error( 'file-not-exists', sprintf( __( 'File %s does not exist', 'wp-split-csv' ), $file ) );
	}

	$fp_source  = fopen( $file, 'r' );

	if ( ! is_resource( $fp_source ) ) {
		return new \WP_Error( 'file-write-error', sprintf( __( 'Unable to open/write to file %s', 'wp-split-csv' ), $file ) );
	}

	$fp_target  = false;
	$header     = false;
	$filenumber = 0;
	$lines      = 0;
	$basename   = pathinfo( $file, PATHINFO_FILENAME );
	$ext        = pathinfo( $file, PATHINFO_EXTENSION );

	while ( ! feof( $fp_source )  ) {

		$line = fgets( $fp_source );

		if ( empty( $line ) ) {
			break;
		}

		// Get the header.
		if ( empty( $header ) ) {
			$header = $line;
			continue;
		}

		// Create a new file and add the header.
		if ( false === $fp_target ) {
			$target_file = $basename . '_' . str_pad( strval( $filenumber ), 6, $args['pad_filename_with'], STR_PAD_LEFT ) . '.' . $ext;
			$target_file = wp_tempnam( $target_file );

			try {
				$fp_target = fopen( $target_file, 'w' );
			} catch ( \Exception $e ) {
				if ( ! is_resource( $fp_target ) ) {
					return new \WP_Error( 'file-write-error', sprintf( __( 'Unable to open/write to file %s, %s', 'wp-split-csv' ), $target_file, $e->getMessage() ) );
				}
			}

			// Put the header in the file.
			fputs( $fp_target, $header );

			$results[] = $target_file;
		}

		// Put the line in the file.
		fputs( $fp_target, $line );
		$lines++;

		if ( $lines >= $args['lines_per_file'] ) {
			@fclose( $fp_target ); // phpcs:ignore
			$filenumber++;
			$fp_target   = false;
			$lines       = 0;
		}
	}

	if ( is_resource( $fp_source ) ) {
		@fclose( $fp_source ); // phpcs:ignore
	}

	if ( is_resource( $fp_target ) ) {
		@fclose( $fp_target ); // phpcs:ignore
	}

	return $results;
}

/**
 * Hook to splits a newly uploaded CSV into multiple files.
 *
 * @param  int $attachment_id The attachment ID.
 * @return void
 */
function split_added_csv( $attachment_id ) {
	if ( 'text/csv' === get_post_mime_type( $attachment_id ) ) {
		$results = split_csv( get_attached_file( $attachment_id ) );

		var_dump( $results ); die();
	}
}


// Fire up the plugin.
\WPSplitCSV\setup();
