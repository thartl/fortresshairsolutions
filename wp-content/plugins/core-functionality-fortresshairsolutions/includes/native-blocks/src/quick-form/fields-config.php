<?php

declare( strict_types=1 );

namespace PWire\NativeBlocks\QuickForm;

/**
 * Quick Form field registry + resilient merge helpers.
 *
 * Goal: store only overrides in block content, while PHP remains canonical.
 * This file is intentionally side-effect-free (no hooks). Consumers call it.
 */

/**
 * Bump when the registry meaningfully changes (field keys added/removed/renamed,
 * defaults changed, constraints changed). Used for editor acknowledgment gating.
 */
const PWIRE_QUICK_FORM_CONFIG_VERSION = '2026-02-18';

/**
 * Allowed inline markup for checkbox labels.
 *
 * @return array<string,array<string,bool>>
 */
function get_quick_form_checkbox_label_allowed_html(): array {
	return [
		'a'      => [
			'href'       => true,
			'title'      => true,
			'rel'        => true,
			'aria-label' => true,
		],
		'strong' => [],
		'em'     => [],
	];
}

/**
 * Sanitize a label override based on canonical field type.
 */
function sanitize_quick_form_label( string $type, string $raw ): string {
	$raw = trim( $raw );

	if ( $raw === '' ) {
		return '';
	}

	// Checkbox labels may contain limited inline markup.
	if ( $type === 'checkbox' ) {
		$sanitized = wp_kses( $raw, get_quick_form_checkbox_label_allowed_html() );
		$sanitized = preg_replace( '/\s+/', ' ', (string) $sanitized );
		$sanitized = trim( (string) $sanitized );

		return $sanitized;
	}

	return trim( wp_strip_all_tags( $raw ) );
}

/**
 * Returns the raw field registry (un-normalized).
 *
 * - "type" is canonical and NOT overridable by block attributes.
 * - "allowAutocompleteOverride" controls whether "autocomplete" overrides are accepted.
 * - Other overrides (label/enabled/required) are allowed for all fields.
 * - Editor messaging and save-locking are driven by PWIRE_QUICK_FORM_CONFIG_VERSION
 *   and the matching entry in "adminNotices":
 *     - If the version changes and there is NO notice for that version, the editor
 *       silently updates block ackVersion (no notices, no locking).
 *     - If a notice exists and requireAck is false, each Quick Form block shows
 *       the notice until acknowledged; no editor-level notice and saving stays
 *       unlocked.
 *     - If a notice exists and requireAck is true, blocks show the notice AND the
 *       editor displays a global notice while post saving is locked until every
 *       Quick Form block is acknowledged for that version.
 *
 * @return array{
 *   configVersion: string,
 *   order: string[],
 *   fields: array<string, array{
 *     type: 'text'|'email'|'tel'|'textarea'|'checkbox',
 *     label: string,
 *     enabled: bool,
 *     required: bool,
 *     autocomplete?: string,
 *     allowAutocompleteOverride?: bool
 *   }>,
 *   migrate: array<string,string>
 * }
 */
function get_quick_form_field_registry_raw(): array {
	return [
		'configVersion'   => PWIRE_QUICK_FORM_CONFIG_VERSION,
		'adminSpamColumn' => true,
		'adminNotices'    => [
//			'2026-02-09' => [
//				'message'    => 'The "Town" field\'s default label was changed to "City".',
//				'requireAck' => true,
//			],
		],
		'order'           => [ 'name', 'company', 'email', 'phone', 'message', 'consent' ],
		'fields'          => [
			'name'    => [
				'type'                      => 'text',
				'label'                     => 'Name',
				'enabled'                   => true,
				'required'                  => true,
				'autocomplete'              => 'name',
				'allowAutocompleteOverride' => true,
				'adminColumn'               => false,
			],
			'company'    => [
				'type'                      => 'text',
				'label'                     => 'Company',
				'enabled'                   => true,
				'required'                  => false,
				'autocomplete'              => 'address-level2',
				'allowAutocompleteOverride' => true,
				'adminColumn'               => true,
			],
			'email'   => [
				'type'                      => 'email',
				'label'                     => 'Email',
				'enabled'                   => true,
				'required'                  => true,
				'autocomplete'              => 'email',
				'allowAutocompleteOverride' => true,
				'adminColumn'               => true,
			],
			'phone'   => [
				'type'                      => 'tel',
				'label'                     => 'Phone',
				'enabled'                   => true,
				'required'                  => false,
				'autocomplete'              => 'tel',
				'allowAutocompleteOverride' => true,
				'adminColumn'               => true,
			],
			'city'    => [
				'type'                      => 'text',
				'label'                     => 'City',
				'enabled'                   => true,
				'required'                  => false,
				'autocomplete'              => 'address-level2',
				'allowAutocompleteOverride' => true,
				'adminColumn'               => true,
			],
			'message' => [
				'type'                      => 'textarea',
				'label'                     => 'Message',
				'enabled'                   => true,
				'required'                  => true,
				'allowAutocompleteOverride' => true,
				'adminColumn'               => true,
			],
			'consent' => [
				'type'                      => 'checkbox',
				'label'                     => 'I agree with the privacy policy.',
				'enabled'                   => false,
				'required'                  => true,
				'allowAutocompleteOverride' => false,
				'adminColumn'               => false,
			],
		],

		/**
		 * Key migration map: oldKey => newKey
		 * Keep this empty for now; we’ll use it once you rename/remove fields later.
		 */
		'migrate'         => [],
	];
}

/**
 * Returns the normalized field registry, keeping only fields present in BOTH
 * "order" and "fields". This lets you leave a field defined but effectively
 * disable it by removing its key from "order".
 *
 * @return array{
 *   configVersion: string,
 *   order: string[],
 *   fields: array<string, array{
 *     type: 'text'|'email'|'tel'|'textarea'|'checkbox',
 *     label: string,
 *     enabled: bool,
 *     required: bool,
 *     autocomplete?: string,
 *     allowAutocompleteOverride?: bool
 *   }>,
 *   migrate: array<string,string>
 * }
 */
function get_quick_form_field_registry(): array {
	$raw    = get_quick_form_field_registry_raw();
	$fields = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : [];
	$order  = isset( $raw['order'] ) && is_array( $raw['order'] ) ? $raw['order'] : [];

	$filtered_order = [];
	foreach ( $order as $key ) {
		if ( ! is_string( $key ) || $key === '' ) {
			continue;
		}
		if ( array_key_exists( $key, $fields ) ) {
			$filtered_order[] = $key;
		}
	}

	$normalized_fields = array_intersect_key( $fields, array_flip( $filtered_order ) );

	return array_merge(
		$raw,
		[
			'order'  => $filtered_order,
			'fields' => $normalized_fields,
		]
	);
}

/**
 * Merge canonical defaults with untrusted overrides.
 *
 * @param array $raw_overrides Typically block attributes "fields".
 *
 * @return array{
 *   configVersion: string,
 *   order: string[],
 *   fields: array<string, array{
 *     type: string,
 *     label: string,
 *     enabled: bool,
 *     required: bool,
 *     autocomplete: string
 *   }>,
 *   report: array{
 *     migrated: array<int, array{from: string, to: string}>,
 *     droppedFields: string[],
 *     droppedProps: array<int, array{field: string, prop: string}>,
 *     normalized: array<int, array{field: string, prop: string}>
 *   }
 * }
 */
function get_effective_quick_form_fields_config( array $raw_overrides ): array {
	$registry = get_quick_form_field_registry();

	$order   = isset( $registry['order'] ) && is_array( $registry['order'] ) ? $registry['order'] : [];
	$defs    = isset( $registry['fields'] ) && is_array( $registry['fields'] ) ? $registry['fields'] : [];
	$migrate = isset( $registry['migrate'] ) && is_array( $registry['migrate'] ) ? $registry['migrate'] : [];

	$report = [
		'migrated'      => [],
		'droppedFields' => [],
		'droppedProps'  => [],
		'normalized'    => [],
	];

	// Normalize overrides container.
	$overrides = is_array( $raw_overrides ) ? $raw_overrides : [];

	// 1) Apply key migrations (silent, but reported).
	if ( $migrate ) {
		foreach ( $migrate as $from => $to ) {
			if ( ! is_string( $from ) || ! is_string( $to ) || $from === '' || $to === '' ) {
				continue;
			}
			if ( array_key_exists( $from, $overrides ) && ! array_key_exists( $to, $overrides ) ) {
				$overrides[ $to ] = $overrides[ $from ];
				unset( $overrides[ $from ] );
				$report['migrated'][] = [ 'from' => $from, 'to' => $to ];
			}
		}
	}

	// 2) Drop unknown field keys early (but report them).
	foreach ( array_keys( $overrides ) as $key ) {
		if ( ! is_string( $key ) || $key === '' ) {
			continue;
		}
		if ( ! array_key_exists( $key, $defs ) ) {
			unset( $overrides[ $key ] );
			$report['droppedFields'][] = $key;
		}
	}

	// 3) Build effective config in canonical order.
	$effective = [];

	foreach ( $order as $key ) {
		if ( ! is_string( $key ) || $key === '' ) {
			continue;
		}
		if ( ! isset( $defs[ $key ] ) || ! is_array( $defs[ $key ] ) ) {
			continue;
		}

		$def = $defs[ $key ];

		$type     = isset( $def['type'] ) && is_string( $def['type'] ) ? $def['type'] : 'text';
		$label    = isset( $def['label'] ) && is_string( $def['label'] ) ? $def['label'] : $key;
		$label    = sanitize_quick_form_label( $type, $label );
		$label    = $label !== '' ? $label : $key;
		$enabled  = array_key_exists( 'enabled', $def ) ? (bool) $def['enabled'] : true;
		$required = array_key_exists( 'required', $def ) ? (bool) $def['required'] : false;

		$autocomplete = '';
		if ( isset( $def['autocomplete'] ) && is_string( $def['autocomplete'] ) ) {
			$autocomplete = trim( $def['autocomplete'] );
		}

		$allow_ac_override = array_key_exists( 'allowAutocompleteOverride', $def ) ? (bool) $def['allowAutocompleteOverride'] : false;

		$ov = isset( $overrides[ $key ] ) && is_array( $overrides[ $key ] ) ? $overrides[ $key ] : [];

		// Allowlisted override props only.
		foreach ( array_keys( $ov ) as $prop ) {
			if ( ! is_string( $prop ) ) {
				continue;
			}
			if ( ! in_array( $prop, [ 'label', 'enabled', 'required', 'autocomplete' ], true ) ) {
				unset( $ov[ $prop ] );
				$report['droppedProps'][] = [ 'field' => $key, 'prop' => $prop ];
			}
		}

		// label override
		if ( array_key_exists( 'label', $ov ) ) {
			$raw  = is_string( $ov['label'] ) ? $ov['label'] : '';
			$next = sanitize_quick_form_label( $type, $raw );
			if ( $next !== $raw ) {
				$report['normalized'][] = [ 'field' => $key, 'prop' => 'label' ];
			}
			if ( $next !== '' ) {
				$label = $next;
			}
		}

		// enabled override
		if ( array_key_exists( 'enabled', $ov ) ) {
			$enabled = (bool) $ov['enabled'];
		}

		// required override
		if ( array_key_exists( 'required', $ov ) ) {
			$required = (bool) $ov['required'];
		}

		// autocomplete override (optional per-field)
		if ( $allow_ac_override && array_key_exists( 'autocomplete', $ov ) ) {
			$raw = is_string( $ov['autocomplete'] ) ? trim( $ov['autocomplete'] ) : '';
			if ( $raw !== '' ) {
				$autocomplete = $raw;
			} else {
				$autocomplete = '';
			}
		} elseif ( array_key_exists( 'autocomplete', $ov ) ) {
			// Override present but not allowed => drop + report.
			$report['droppedProps'][] = [ 'field' => $key, 'prop' => 'autocomplete' ];
		}

		$effective[ $key ] = [
			'type'         => $type,
			'label'        => $label,
			'enabled'      => $enabled,
			'required'     => $required,
			'autocomplete' => $autocomplete,
		];
	}

	return [
		'configVersion' => (string) ( $registry['configVersion'] ?? PWIRE_QUICK_FORM_CONFIG_VERSION ),
		'order'         => $order,
		'fields'        => $effective,
		'report'        => $report,
	];
}
