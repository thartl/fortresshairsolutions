<?php

declare( strict_types=1 );

namespace PWire\CoreFunctionality\WPCLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class Fluid_Command extends WP_CLI_Command {

	/**
	 * Generate a WordPress-core-style fluid clamp() expression (like the editor’s Font size presets UI).
	 *
	 * - Pass --size only to mirror: Fluid typography ON + Custom fluid values OFF (core computes min automatically).
	 * - Pass --min and --max to mirror: Custom fluid values ON (explicit min/max).
	 *
	 * ## OPTIONS
	 *
	 * [--size=<value>]
	 * : A single CSS size (e.g. 20px, 1.25rem). Core treats this as the maximum and computes the minimum
	 * automatically.
	 *
	 * [--min=<value>]
	 * : Minimum CSS size (e.g. 16px, 1rem). Requires --max (and cannot be used with --size).
	 *
	 * [--max=<value>]
	 * : Maximum CSS size (e.g. 22px, 1.375rem). Requires --min (and cannot be used with --size).
	 *
	 * [--min-vw=<value>]
	 * : Override the minimum viewport width used in the fluid calculation (defaults to site fluid setting or 320px).
	 *
	 * [--max-vw=<value>]
	 * : Override the maximum viewport width used in the fluid calculation (defaults to site fluid setting, or
	 * layout.wideSize, or 1600px).
	 *
	 * ## EXAMPLES
	 *
	 *     # Custom fluid values OFF (single size entered)
	 *     wp fluid clamp --size=2rem
	 *
	 *     # Custom fluid values ON (explicit min/max)
	 *     wp fluid clamp --min=1rem --max=2rem
	 *
	 *     # Override viewport range (single size)
	 *     wp fluid clamp --size=2rem --min-vw=360px --max-vw=1440px
	 *
	 *     # Override viewport range (explicit min/max)
	 *     wp fluid clamp --min=1rem --max=2rem --min-vw=360px --max-vw=1440px
	 *
	 * @when after_wp_load
	 */
	public function clamp( array $args, array $assoc_args ): void {
		$size = $assoc_args['size'] ?? null;
		$min  = $assoc_args['min'] ?? null;
		$max  = $assoc_args['max'] ?? null;

		$has_size = null !== $size && '' !== (string) $size;
		$has_min  = null !== $min && '' !== (string) $min;
		$has_max  = null !== $max && '' !== (string) $max;

		// Enforce either:
		// - --size only
		// - or both --min and --max
		if ( $has_size ) {
			if ( $has_min || $has_max ) {
				WP_CLI::error( 'Use either --size=<...> OR the pair --min=<...> --max=<...>, not both.' );
			}
		} else {
			if ( ! ( $has_min && $has_max ) ) {
				WP_CLI::error( 'Missing required args. Provide --size=<...> OR both --min=<...> and --max=<...>.' );
			}
		}

		if ( ! function_exists( 'wp_get_typography_font_size_value' ) ) {
			require_once ABSPATH . WPINC . '/block-supports/typography.php';
		}

		$settings = wp_get_global_settings();

		// Optional viewport overrides — apply for both modes.
		if ( isset( $assoc_args['min-vw'] ) || isset( $assoc_args['max-vw'] ) ) {
			if ( ! isset( $settings['typography'] ) || ! is_array( $settings['typography'] ) ) {
				$settings['typography'] = [];
			}

			if ( ! isset( $settings['typography']['fluid'] ) || true === $settings['typography']['fluid'] || false === $settings['typography']['fluid'] ) {
				$settings['typography']['fluid'] = [];
			}

			if ( isset( $assoc_args['min-vw'] ) ) {
				$settings['typography']['fluid']['minViewportWidth'] = (string) $assoc_args['min-vw'];
			}

			if ( isset( $assoc_args['max-vw'] ) ) {
				$settings['typography']['fluid']['maxViewportWidth'] = (string) $assoc_args['max-vw'];
			}
		}

		if ( $has_size ) {
			$preset = [
				'size'  => (string) $size,
				'fluid' => true,
			];

			$mode_label = 'Mode: single value (editor: Custom fluid values OFF)';
		} else {
			$preset = [
				'size'  => (string) $max,
				'fluid' => [
					'min' => (string) $min,
					'max' => (string) $max,
				],
			];

			$mode_label = 'Mode: explicit min/max (editor: Custom fluid values ON)';
		}

		$value = wp_get_typography_font_size_value( $preset, $settings );

		if ( empty( $value ) ) {
			WP_CLI::error( 'No clamp() could be generated for the given arguments.' );
		}

		WP_CLI::line( '' );
		WP_CLI::line( '# Computed CSS value (WordPress fluid typography). ' . $mode_label );
		WP_CLI::line( $value );
		WP_CLI::line( '' );
	}
}

WP_CLI::add_command(
	'fluid',
	__NAMESPACE__ . '\Fluid_Command',
	[
		'shortdesc' => 'Generate WordPress-core fluid clamp() expressions.',
	]
);
