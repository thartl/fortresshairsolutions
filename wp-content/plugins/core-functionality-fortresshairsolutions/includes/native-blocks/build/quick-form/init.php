<?php
declare( strict_types=1 );

namespace PWire\NativeBlocks\QuickForm;

use WP_Error;
use WP_REST_Request;

// Phase 0: load canonical field registry + merge helpers (side-effect-free).
require_once __DIR__ . '/fields-config.php';

const PWIRE_QUICK_FORM_DEFAULT_CONFIRMATION = 'Thank you! We’ll be in touch soon.';

add_action( 'init', __NAMESPACE__ . '\register_cpt' );
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );

// Expose registry to the block editor (no behavior change yet).
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\expose_registry_to_editor' );

add_action( 'admin_post_nopriv_pwire_quick_form_submit', __NAMESPACE__ . '\handle_admin_post' );
add_action( 'admin_post_pwire_quick_form_submit', __NAMESPACE__ . '\handle_admin_post' );

if ( is_admin() ) {
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\register_entry_metaboxes' );
	add_filter( 'manage_pwire_qform_entry_posts_columns', __NAMESPACE__ . '\add_entry_columns' );
	add_action( 'manage_pwire_qform_entry_posts_custom_column', __NAMESPACE__ . '\render_entry_column', 10, 2 );
	add_action( 'admin_head', __NAMESPACE__ . '\add_entry_admin_styles' );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\include_spam_in_admin_queries' );
	add_filter( 'views_edit-pwire_qform_entry', __NAMESPACE__ . '\adjust_entry_list_views' );
	add_filter( 'quick_edit_statuses', __NAMESPACE__ . '\filter_entry_quick_edit_statuses', 10, 4 );
	add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\normalize_entry_post_status', 10, 2 );
	add_action( 'admin_footer-post.php', __NAMESPACE__ . '\render_entry_status_ui_overrides' );
	add_action( 'admin_footer-post-new.php', __NAMESPACE__ . '\render_entry_status_ui_overrides' );
}

function expose_registry_to_editor(): void {
	$registry = get_quick_form_field_registry();

	$payload = [
		'registry' => $registry,
	];

	$handle = 'pwire-quick-form-editor-script';

	$js = 'window.PWIRE_QUICK_FORM = window.PWIRE_QUICK_FORM || {};';
	$js .= 'window.PWIRE_QUICK_FORM.data = window.PWIRE_QUICK_FORM.data || {};';
	$js .= 'window.PWIRE_QUICK_FORM.data = ' . wp_json_encode( $payload ) . ';';

	wp_add_inline_script( $handle, $js, 'before' );
}

function register_cpt(): void {

	register_post_type( 'pwire_qform_entry', [
		'labels'       => [
			'name'          => 'Form Entries',
			'singular_name' => 'Form Entry',
		],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-feedback',
		'supports'     => [ 'title' ],
	] );

	register_post_status( 'pwire_lead', [
		'label'                     => _x( 'Lead', 'post' ),
		'public'                    => false,
		'internal'                  => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Leads <span class="count">(%s)</span>', 'Leads <span class="count">(%s)</span>' ),
	] );

	register_post_status( 'spam', [
		'label'                     => _x( 'Spam', 'post' ),
		'public'                    => false,
		'internal'                  => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		'label_count'               => _n_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>' ),
	] );
}

function register_entry_metaboxes(): void {
	add_meta_box(
		'pwire_qform_entry_details',
		'Submission Details',
		__NAMESPACE__ . '\render_entry_metabox',
		'pwire_qform_entry',
		'normal',
		'high'
	);
}

function render_entry_metabox( \WP_Post $post ): void {

	$fields = [];

	$stored_labels = get_post_meta( $post->ID, 'pwire_quick_form_field_labels', true );
	$stored_labels = is_string( $stored_labels ) ? json_decode( $stored_labels, true ) : $stored_labels;
	$stored_labels = is_array( $stored_labels ) ? $stored_labels : [];

	if ( $stored_labels ) {
		$fields = $stored_labels;
	}

	$client_meta = [
		'spam_status' => 'Spam Status',
		'reason'      => 'Spam Reason',
		'hp_value'    => 'Honeypot value',
		'delta_ms'    => 'Load to submit delta (seconds)',
		'ip_cf'       => 'CF-Connecting-IP',
		'ip_remote'   => 'REMOTE_ADDR',
		'referer'     => 'Referer',
		'origin'      => 'Origin',
		'user_agent'  => 'User Agent',
	];

	$spam_reason_labels = [
		'honeypot'      => 'Honeypot field filled',
		'no_delta'      => 'Missing timing delta',
		'invalid_delta' => 'Invalid timing delta',
		'timing_fast'   => 'Submitted too quickly',
		'timing_slow'   => 'Submitted after timing window',
		'throttle'      => 'Rate limited (throttle)',
	];

	$timing_state_labels = [
		'missing'  => 'No delta recorded',
		'invalid'  => 'Invalid delta',
		'too-fast' => 'Delta below minimum threshold',
		'too-slow' => 'Delta above maximum threshold',
		'ok'       => 'Valid timing delta',
	];

	echo '<table class="widefat striped" style="margin-block:1rem;">';
	echo '<tbody>';

	foreach ( $fields as $key => $label ) {
		if ( ! metadata_exists( 'post', $post->ID, 'pwire_' . $key ) ) {
			// Not stored => field was inactive when submitted.
			continue;
		}
		$value          = get_post_meta( $post->ID, 'pwire_' . $key, true );
		$value = is_string( $value ) ? $value : '';

		echo '<tr>';
		echo '<th scope="row" style="width:180px;">' . esc_html( $label ) . '</th>';
		echo '<td>' . wpautop( esc_html( $value ) ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	$source_post_id = get_post_meta( $post->ID, 'pwire_source_post', true );
	$source_post_id = is_numeric( $source_post_id ) ? (int) $source_post_id : 0;

	echo '<h3 style="margin-block: 2rem 0.75rem;">' . esc_html__( 'Sent from', 'pwire' ) . '</h3>';

	if ( $source_post_id > 0 ) {
		$front_url = get_permalink( $source_post_id );
		$front_url = is_string( $front_url ) ? $front_url : '';
		$title     = get_the_title( $source_post_id );
		$title     = is_string( $title ) && $title !== '' ? $title : ( 'Post #' . $source_post_id );

		if ( $front_url ) {
			echo '<p><a href="' . esc_url( $front_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></p>';
		} else {
			echo '<p>' . esc_html( $title ) . '</p>';
		}
	} else {
		echo '<p>—</p>';
	}

	$sent_to = get_post_meta( $post->ID, 'pwire_notification_sent_to', true );
	$sent_to = is_string( $sent_to ) ? $sent_to : '';
	$sent_to = preg_replace( '/\s*,\s*/', ', ', trim( $sent_to ) );

	echo '<h3 style="margin-block: 2rem 0.75rem;">' . esc_html__( 'Sent to', 'pwire' ) . '</h3>';
	echo '<p>' . esc_html( $sent_to !== '' ? $sent_to : '—' ) . '</p>';

	echo '<hr style="margin-block:4rem 1rem;" />';

	echo '<h3>' . esc_html__( 'Client Metadata', 'pwire' ) . '</h3>';
	echo '<table class="widefat striped">';
	echo '<tbody>';

	foreach ( $client_meta as $key => $label ) {

		$value = get_post_meta( $post->ID, 'pwire_' . $key, true );
		$value = is_string( $value ) ? $value : '';

		if ( $key === 'hp_value' && $value === '' ) {
			continue;
		}

		if ( $key === 'reason' ) {
			if ( $value === '' ) {
				continue;
			}

			$reason_key = strtolower( trim( $value ) );
			$value      = $spam_reason_labels[ $reason_key ] ?? $value;
		}

		if ( $key === 'delta_ms' ) {
			if ( $value === '' ) {
				$value = 'No delta recorded';
			} elseif ( ctype_digit( $value ) ) {
				$value = (float) $value / 1000;
			} else {
				$delta_key = strtolower( trim( $value ) );
				$value     = $timing_state_labels[ $delta_key ] ?? 'Invalid delta recorded';
			}
		}

		echo '<tr>';
		echo '<th scope="row" style="width:180px;">' . esc_html( $label ) . '</th>';
		echo '<td>' . esc_html( $value ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	echo '<h3>Form Block Metadata</h3>';
	echo '<table class="widefat striped">';
	echo '<tbody>';

	$source_block_uid = get_post_meta( $post->ID, 'pwire_source_block', true );
	$source_block_uid = is_string( $source_block_uid ) ? trim( $source_block_uid ) : '';

	if ( $source_block_uid !== '' ) {
		echo '<tr>';
		echo '<th scope="row" style="width:180px;">Block UID:</th>';
		echo '<td>' . esc_html( $source_block_uid ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
}

function add_entry_columns( array $columns ): array {
	$new = [];

	$admin_fields  = [];
	$show_spam_col = true;

	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		$show_spam_col = isset( $reg['adminSpamColumn'] ) ? (bool) $reg['adminSpamColumn'] : true;

		foreach ( $order as $field_key ) {
			if ( ! is_string( $field_key ) || $field_key === '' ) {
				continue;
			}

			$def = isset( $defs[ $field_key ] ) && is_array( $defs[ $field_key ] ) ? $defs[ $field_key ] : [];

			$show = isset( $def['adminColumn'] ) ? (bool) $def['adminColumn'] : false;
			if ( ! $show ) {
				continue;
			}

			$label = isset( $def['label'] ) && is_string( $def['label'] ) && $def['label'] !== ''
				? $def['label']
				: $field_key;
			$label = trim( wp_strip_all_tags( $label ) );
			$label = $label !== '' ? $label : $field_key;

			$admin_fields[ $field_key ] = $label;
		}
	}

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( $key === 'title' ) {
			foreach ( $admin_fields as $field_key => $field_label ) {
				$new[ 'pwire_' . $field_key ] = __( (string) $field_label, 'pwire' );
			}

			if ( $show_spam_col ) {
				$new['pwire_spam_status'] = __( 'Spam Status', 'pwire' );
			}
		}
	}

	return $new;
}

function render_entry_column( string $column, int $post_id ): void {

	// Registry-driven rendering for dynamic pwire_{field} columns.
	if ( strpos( $column, 'pwire_' ) === 0 && $column !== 'pwire_spam_status' ) {
		$field_key = substr( $column, 6 ); // remove "pwire_"
		$field_key = is_string( $field_key ) ? $field_key : '';

		$allowed = false;

		if ( $field_key !== '' && function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
			$reg  = get_quick_form_field_registry();
			$defs = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

			$def     = isset( $defs[ $field_key ] ) && is_array( $defs[ $field_key ] ) ? $defs[ $field_key ] : [];
			$allowed = isset( $def['adminColumn'] ) ? (bool) $def['adminColumn'] : false;
		} else {
			// Hard fallback allowlist to preserve current behavior if registry is unavailable.
			$allowed = in_array( $field_key, [ 'email', 'city', 'phone', 'message' ], true );
		}

		if ( ! $allowed ) {
			return;
		}

		$value = get_post_meta( $post_id, $column, true );
		$value = is_string( $value ) ? $value : '';

		if ( $value === '' ) {
			return;
		}

		if ( $field_key === 'message' ) {
			echo esc_html( wp_trim_words( $value, 15 ) );

			return;
		}

		echo esc_html( $value );

		return;
	}

	switch ( $column ) {
		case 'pwire_spam_status':
			$show_spam_col = true;

			if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
				$reg           = get_quick_form_field_registry();
				$show_spam_col = isset( $reg['adminSpamColumn'] ) ? (bool) $reg['adminSpamColumn'] : true;
			}

			if ( ! $show_spam_col ) {
				return;
			}

			$status = get_post_meta( $post_id, 'pwire_spam_status', true );
			if ( $status ) {
				echo esc_html( (string) $status );
			} else {
				echo 'OK';
			}
			break;
	}
}

function add_entry_admin_styles(): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'pwire_qform_entry' ) {
		return;
	}

	$css = "
	table.wp-list-table .status-spam .row-title {
		color: #c05a00;
	}
	";

	printf( '<style>%s</style>', $css );
}

function include_spam_in_admin_queries( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'pwire_qform_entry' ) {
		return;
	}

	// Entries are system records, not author-owned content.
	$query->set( 'author', '' );
	if ( isset( $query->query_vars['author'] ) ) {
		unset( $query->query_vars['author'] );
	}
	unset( $_GET['author'], $_REQUEST['author'] );

	$status = $query->get( 'post_status' );
	$all_posts = isset( $_REQUEST['all_posts'] ) && (string) $_REQUEST['all_posts'] !== '';

	if ( $all_posts || $status === 'any' ) {
		$statuses = array_values( get_post_stati( [ 'show_in_admin_all_list' => true ], 'names' ) );
		if ( ! in_array( 'spam', $statuses, true ) ) {
			$statuses[] = 'spam';
		}
		if ( ! in_array( 'pwire_lead', $statuses, true ) ) {
			$statuses[] = 'pwire_lead';
		}
		$query->set( 'post_status', $statuses );
		return;
	}

	if ( ! $status ) {
		$query->set( 'post_status', 'pwire_lead' );
		$_GET['post_status']     = 'pwire_lead';
		$_REQUEST['post_status'] = 'pwire_lead';
		return;
	}
}

function adjust_entry_list_views( array $views ): array {
	unset( $views['mine'] );

	if ( ! isset( $views['all'] ) || ! is_string( $views['all'] ) || $views['all'] === '' ) {
		return $views;
	}

	$views['all'] = preg_replace_callback(
		'/href=(["\'])([^"\']+)\1/',
		static function ( array $m ): string {
			$url = add_query_arg( 'all_posts', '1', html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
			return 'href="' . esc_url( $url ) . '"';
		},
		$views['all'],
		1
	);

	return $views;
}

function filter_entry_quick_edit_statuses( array $statuses, string $post_type, bool $bulk, bool $can_publish ): array {
	unset( $can_publish );

	if ( $post_type !== 'pwire_qform_entry' ) {
		return $statuses;
	}

	$filtered = [];
	if ( $bulk ) {
		$filtered['-1'] = __( '&mdash; No Change &mdash;' );
	}

	$filtered['pwire_lead'] = __( 'Lead', 'pwire' );
	$filtered['spam']       = __( 'Spam', 'pwire' );

	return $filtered;
}

function normalize_entry_post_status( array $data, array $postarr ): array {
	unset( $postarr );

	$post_type = isset( $data['post_type'] ) && is_string( $data['post_type'] ) ? $data['post_type'] : '';
	if ( $post_type !== 'pwire_qform_entry' ) {
		return $data;
	}

	$status = isset( $data['post_status'] ) && is_string( $data['post_status'] ) ? $data['post_status'] : '';
	if ( $status === '' ) {
		return $data;
	}

	if ( in_array( $status, [ 'publish', 'future', 'private', 'pending', 'draft' ], true ) ) {
		$data['post_status'] = 'pwire_lead';
	}

	return $data;
}

function render_entry_status_ui_overrides(): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'pwire_qform_entry' ) {
		return;
	}
	?>
	<script>
		(() => {
			const select = document.getElementById('post_status');
			const hidden = document.getElementById('hidden_post_status');
			const display = document.getElementById('post-status-display');

			if (!select || !hidden) {
				return;
			}

			const leadLabel = 'Lead';
			const spamLabel = 'Spam';

			const normalizeStatus = (value) => {
				const normalized = String(value || '').toLowerCase();
				return normalized === 'spam' ? 'spam' : 'pwire_lead';
			};

			const applyStatusUi = (status) => {
				const normalized = normalizeStatus(status);
				select.innerHTML = '';

				const leadOption = new Option(leadLabel, 'pwire_lead');
				const spamOption = new Option(spamLabel, 'spam');
				select.add(leadOption);
				select.add(spamOption);

				select.value = normalized;
				hidden.value = normalized;

				if (display) {
					display.textContent = normalized === 'spam' ? spamLabel : leadLabel;
				}
			};

			applyStatusUi(hidden.value || select.value);

			select.addEventListener('change', () => {
				const normalized = normalizeStatus(select.value);
				hidden.value = normalized;
				if (display) {
					display.textContent = normalized === 'spam' ? spamLabel : leadLabel;
				}
			});
		})();
	</script>
	<?php
}

function register_rest_routes(): void {

	register_rest_route( 'pwire/v1', '/quick-form/submit', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => __NAMESPACE__ . '\handle_rest_submit',
	] );
}

function handle_rest_submit( WP_REST_Request $request ) {

	$raw = $request->get_params();
	$raw = is_array( $raw ) ? $raw : [];

	$result = process_submission( $raw );

	$status = $result['success'] ? 200 : 400;

	$response = rest_ensure_response( [
		'success' => $result['success'],
		'message' => $result['message'],
		'errors'  => $result['errors'],
		'is_spam' => $result['is_spam'],
	] );

	$response->set_status( $status );

	return $response;
}

function handle_admin_post(): void {

	$raw = wp_unslash( $_POST );
	$raw = is_array( $raw ) ? $raw : [];

	$result = process_submission( $raw );

	$return_success = isset( $raw['pwire_return_success'] ) && is_string( $raw['pwire_return_success'] )
		? esc_url_raw( $raw['pwire_return_success'] )
		: '';

	$return_error = isset( $raw['pwire_return_error'] ) && is_string( $raw['pwire_return_error'] )
		? esc_url_raw( $raw['pwire_return_error'] )
		: '';

	$return_fallback = isset( $raw['pwire_return'] ) && is_string( $raw['pwire_return'] )
		? esc_url_raw( $raw['pwire_return'] )
		: '';

	if ( ! $return_fallback ) {
		$return_fallback = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	}

	if ( ! $return_fallback ) {
		$return_fallback = home_url( '/' );
	}

	if ( $result['success'] ) {
		$target = $return_success ?: $return_fallback;
		wp_safe_redirect( $target );
		exit;
	}

	$target = $return_error ?: $return_fallback;
	wp_safe_redirect( $target );
	exit;
}

function get_confirmation_message( array $raw ): string {

	$message = isset( $raw['pwire_confirmation_message'] ) && is_string( $raw['pwire_confirmation_message'] )
		? trim( $raw['pwire_confirmation_message'] )
		: '';

	if ( $message === '' ) {
		return PWIRE_QUICK_FORM_DEFAULT_CONFIRMATION;
	}

	return $message;
}

function sanitize_notification_emails( string $raw ): string {
	$emails = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	$valid  = [];

	foreach ( $emails as $email ) {
		$sanitized = sanitize_email( $email );
		if ( $sanitized && is_email( $sanitized ) ) {
			$valid[] = $sanitized;
		}
	}

	return implode( ',', $valid );
}

function base64url_encode( string $raw ): string {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
}

function base64url_decode( string $raw ) {
	$normalized = strtr( $raw, '-_', '+/' );
	$padding    = strlen( $normalized ) % 4;

	if ( $padding > 0 ) {
		$normalized .= str_repeat( '=', 4 - $padding );
	}

	return base64_decode( $normalized, true );
}

function get_notification_blob_key(): string {
	static $key = null;

	if ( is_string( $key ) && $key !== '' ) {
		return $key;
	}

	$material = (string) wp_salt( 'auth' ) . '|' . (string) wp_salt( 'secure_auth' ) . '|pwire-quick-form-notify-v1';
	$key      = hash( 'sha256', $material, true );

	return is_string( $key ) ? $key : '';
}

function seal_notification_context_blob( string $notify_to, string $uid, int $post_id ): string {
	$notify_to = sanitize_notification_emails( $notify_to );
	$uid       = trim( sanitize_text_field( $uid ) );

	if ( $notify_to === '' || $uid === '' ) {
		return '';
	}

	if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}

	$key = get_notification_blob_key();
	if ( $key === '' ) {
		return '';
	}

	try {
		$iv = random_bytes( 12 );
	} catch ( \Throwable $e ) {
		return '';
	}

	$payload = [
		'v'        => 1,
		'notifyTo' => $notify_to,
		'uid'      => $uid,
		'postId'   => $post_id,
	];

	$json = wp_json_encode( $payload );
	if ( ! is_string( $json ) || $json === '' ) {
		return '';
	}

	$tag        = '';
	$ciphertext = openssl_encrypt(
		$json,
		'aes-256-gcm',
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'pwire.quick-form.notify.v1',
		16
	);

	if ( ! is_string( $ciphertext ) || $ciphertext === '' || ! is_string( $tag ) || strlen( $tag ) !== 16 ) {
		return '';
	}

	return 'v1.' . base64url_encode( $iv ) . '.' . base64url_encode( $tag ) . '.' . base64url_encode( $ciphertext );
}

function unseal_notification_context_blob( string $blob, string $uid, int $post_id ): string {
	$blob = trim( $blob );
	$uid  = trim( sanitize_text_field( $uid ) );

	if ( $blob === '' || $uid === '' ) {
		return '';
	}

	if ( ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}

	$parts = explode( '.', $blob );
	if ( count( $parts ) !== 4 || $parts[0] !== 'v1' ) {
		return '';
	}

	$iv         = base64url_decode( (string) $parts[1] );
	$tag        = base64url_decode( (string) $parts[2] );
	$ciphertext = base64url_decode( (string) $parts[3] );

	if ( ! is_string( $iv ) || ! is_string( $tag ) || ! is_string( $ciphertext ) ) {
		return '';
	}

	if ( strlen( $iv ) !== 12 || strlen( $tag ) !== 16 || $ciphertext === '' ) {
		return '';
	}

	$key = get_notification_blob_key();
	if ( $key === '' ) {
		return '';
	}

	$json = openssl_decrypt(
		$ciphertext,
		'aes-256-gcm',
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'pwire.quick-form.notify.v1'
	);

	if ( ! is_string( $json ) || $json === '' ) {
		return '';
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) ) {
		return '';
	}

	$payload_uid = isset( $decoded['uid'] ) && is_string( $decoded['uid'] )
		? trim( sanitize_text_field( $decoded['uid'] ) )
		: '';

	$payload_post_id = isset( $decoded['postId'] ) ? absint( $decoded['postId'] ) : 0;

	if ( $payload_uid === '' || ! hash_equals( $payload_uid, $uid ) || $payload_post_id !== $post_id ) {
		return '';
	}

	$notify_to = isset( $decoded['notifyTo'] ) && is_string( $decoded['notifyTo'] )
		? $decoded['notifyTo']
		: '';

	return sanitize_notification_emails( $notify_to );
}

function find_quick_form_attrs_by_uid( array $blocks, string $uid ): ?array {
	foreach ( $blocks as $block ) {
		$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
		$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];

		if ( $block_name === 'pwire/quick-form' ) {
			$block_uid = isset( $attrs['uid'] ) && is_string( $attrs['uid'] ) ? trim( $attrs['uid'] ) : '';
			if ( $block_uid !== '' && $block_uid === $uid ) {
				return $attrs;
			}
		}

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
		if ( $inner ) {
			$found = find_quick_form_attrs_by_uid( $inner, $uid );
			if ( $found ) {
				return $found;
			}
		}
	}

	return null;
}

function get_notification_emails_from_block( int $post_id, string $uid ): string {
	if ( $post_id <= 0 || $uid === '' ) {
		return '';
	}

	$content = get_post_field( 'post_content', $post_id );
	$content = is_string( $content ) ? $content : '';
	if ( $content === '' ) {
		return '';
	}

	$blocks = parse_blocks( $content );
	$attrs  = find_quick_form_attrs_by_uid( is_array( $blocks ) ? $blocks : [], $uid );

	if ( ! $attrs ) {
		return '';
	}

	$raw = isset( $attrs['notificationEmails'] ) && is_string( $attrs['notificationEmails'] )
		? $attrs['notificationEmails']
		: '';

	return $raw;
}

function process_submission( array $raw ): array {

	$schema = parse_fields_schema( $raw );

	$client = get_client_meta();

	$confirmation_message = get_confirmation_message( $raw );
	$form_instance        = isset( $raw['pwire_form_instance'] ) && is_string( $raw['pwire_form_instance'] )
		? trim( $raw['pwire_form_instance'] )
		: '';

	$post_id = isset( $raw['pwire_post_id'] ) ? absint( $raw['pwire_post_id'] ) : 0;
	$uid     = isset( $raw['pwire_block_uid'] ) && is_string( $raw['pwire_block_uid'] )
		? trim( sanitize_text_field( $raw['pwire_block_uid'] ) )
		: '';

	$notification_to = '';
	if ( $post_id > 0 && $uid !== '' ) {
		$notification_to = sanitize_notification_emails( get_notification_emails_from_block( $post_id, $uid ) );
	}

	if ( $notification_to === '' && isset( $raw['pwire_notification_blob'] ) && is_string( $raw['pwire_notification_blob'] ) ) {
		$notification_to = unseal_notification_context_blob( $raw['pwire_notification_blob'], $uid, $post_id );
	}

	if ( $notification_to === '' && isset( $raw['pwire_notification_to'] ) && is_string( $raw['pwire_notification_to'] ) ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$notification_to = sanitize_notification_emails( $raw['pwire_notification_to'] );
		}
	}

	$clean = sanitize_fields( $raw, $schema );
	$field_labels = build_field_labels_for_submission( $schema );

	$loaded_at = isset( $raw['pwire_loaded_at'] ) && is_string( $raw['pwire_loaded_at'] )
		? trim( $raw['pwire_loaded_at'] )
		: '';

	$delta_ms = isset( $raw['pwire_delta_ms'] ) && is_string( $raw['pwire_delta_ms'] )
		? trim( $raw['pwire_delta_ms'] )
		: '';

	$hp = isset( $raw['pwire_hp'] ) && is_string( $raw['pwire_hp'] ) ? trim( $raw['pwire_hp'] ) : '';
	if ( $hp !== '' ) {
		maybe_store_entry( [
			'fields'      => $clean,
			'client_meta' => $client,
			'meta'        => [
				'pwire_is_spam'       => 1,
				'pwire_reason'        => 'honeypot',
				'pwire_spam_status'   => 'Honeypot',
				'pwire_hp_value'      => $hp,
				'pwire_delta_ms'      => $delta_ms,
				'pwire_notify_to'     => $notification_to,
				'pwire_source_post'   => $post_id,
				'pwire_source_block'  => $uid,
				'pwire_form_instance' => $form_instance,
				'pwire_quick_form_field_labels'  => $field_labels,
			],
			'post_status' => 'spam',
		] );

		return [
			'success' => true,
			'message' => $confirmation_message,
			'errors'  => [],
			'is_spam' => true,
		];
	}

	$timing_state = get_timing_state( $delta_ms );

	if ( $timing_state !== 'ok' ) {
		$timing_reason = '';
		$timing_status = '';

		switch ( $timing_state ) {
			case 'missing':
				$timing_reason = 'no_delta';
				$timing_status = 'No delta';
				break;

			case 'invalid':
				$timing_reason = 'invalid_delta';
				$timing_status = 'Invalid delta';
				break;

			case 'too-fast':
				$timing_reason = 'timing_fast';
				$timing_status = 'Fast submission';
				break;

			case 'too-slow':
				$timing_reason = 'timing_slow';
				$timing_status = 'Slow submission';
				break;

			default:
				$timing_reason = 'invalid_delta';
				$timing_status = 'Invalid delta';
				break;
		}

		maybe_store_entry( [
			'fields'      => $clean,
			'client_meta' => $client,
			'meta'        => [
				'pwire_is_spam'       => 1,
				'pwire_reason'        => $timing_reason,
				'pwire_spam_status'   => $timing_status,
				'pwire_delta_ms'      => $delta_ms,
				'pwire_loaded_at'     => $loaded_at,
				'pwire_notify_to'     => $notification_to,
				'pwire_source_post'   => $post_id,
				'pwire_source_block'  => $uid,
				'pwire_form_instance' => $form_instance,
				'pwire_quick_form_field_labels'  => $field_labels,
			],
			'post_status' => 'spam',
		] );

		return [
			'success' => true,
			'message' => $confirmation_message,
			'errors'  => [],
			'is_spam' => true,
		];
	}

	if ( throttle_key( $client ) ) {
		maybe_store_entry( [
			'fields'      => $clean,
			'client_meta' => $client,
			'meta'        => [
				'pwire_is_spam'       => 1,
				'pwire_reason'        => 'throttle',
				'pwire_spam_status'   => 'Throttle',
				'pwire_delta_ms'      => $delta_ms,
				'pwire_notify_to'     => $notification_to,
				'pwire_source_post'   => $post_id,
				'pwire_source_block'  => $uid,
				'pwire_form_instance' => $form_instance,
				'pwire_quick_form_field_labels'  => $field_labels,
			],
			'post_status' => 'spam',
		] );

		return [
			'success' => true,
			'message' => $confirmation_message,
			'errors'  => [],
			'is_spam' => true,
		];
	}

	$errors = validate_fields( $clean, $schema );

	if ( $errors ) {
		return [
			'success' => false,
			'message' => 'Please check the form and try again.',
			'errors'  => $errors,
			'is_spam' => false,
		];
	}

	$entry_id = maybe_store_entry( [
		'fields'      => $clean,
		'client_meta' => $client,
		'meta'        => [
			'pwire_is_spam'       => 0,
			'pwire_spam_status'   => 'OK',
				'pwire_delta_ms'      => $delta_ms,
				'pwire_notify_to'     => $notification_to,
				'pwire_source_post'   => $post_id,
				'pwire_source_block'  => $uid,
				'pwire_form_instance' => $form_instance,
				'pwire_quick_form_field_labels'  => $field_labels,
			],
			'post_status' => 'pwire_lead',
		] );

	$email_ok = send_notification_email( $clean, $client, $entry_id, $post_id );

	if ( ! $email_ok ) {
		return [
			'success' => false,
			'message' => 'Submission saved, but email could not be sent. Please try again later.',
			'errors'  => [ 'form' => 'Submission saved, but email could not be sent. Please try again later.' ],
			'is_spam' => false,
		];
	}

	return [
		'success' => true,
		'message' => $confirmation_message,
		'errors'  => [],
		'is_spam' => false,
	];
}

/**
 * Build a map of active field slugs to labels for the current submission.
 *
 * @param array $schema Enabled/required schema decoded from the submitted form.
 *
 * @return array<string,string>
 */
function build_field_labels_for_submission( array $schema ): array {
	$labels = [];

	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		foreach ( $order as $key ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}

			$schema_enabled = ! isset( $schema[ $key ]['enabled'] ) || (bool) $schema[ $key ]['enabled'];
			if ( ! $schema_enabled ) {
				continue;
			}

			$def   = isset( $defs[ $key ] ) && is_array( $defs[ $key ] ) ? $defs[ $key ] : [];
			$label = isset( $def['label'] ) && is_string( $def['label'] ) && $def['label'] !== '' ? $def['label'] : $key;
			$label = trim( wp_strip_all_tags( $label ) );
			$label = $label !== '' ? $label : $key;

			$labels[ $key ] = $label;
		}
	}

	// Fallback: use schema keys if registry is unavailable.
	if ( ! $labels && $schema ) {
		foreach ( $schema as $key => $cfg ) {
			if ( isset( $cfg['enabled'] ) && ! $cfg['enabled'] ) {
				continue;
			}
			$labels[ $key ] = $key;
		}
	}

	return $labels;
}

function parse_fields_schema( array $raw ): array {

	// Registry-driven defaults (canonical allowlist of field keys).
	$defaults = [];

	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		foreach ( $order as $key ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}
			$def = isset( $defs[ $key ] ) && is_array( $defs[ $key ] ) ? $defs[ $key ] : [];

			$defaults[ $key ] = [
				'enabled'  => array_key_exists( 'enabled', $def ) ? (bool) $def['enabled'] : true,
				'required' => array_key_exists( 'required', $def ) ? (bool) $def['required'] : false,
			];
		}
	}

	$b64 = isset( $raw['pwire_fields_config'] ) && is_string( $raw['pwire_fields_config'] )
		? trim( $raw['pwire_fields_config'] )
		: '';

	if ( ! $b64 ) {
		return $defaults;
	}

	$json = base64_decode( $b64, true );
	if ( ! is_string( $json ) || $json === '' ) {
		return $defaults;
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) ) {
		return $defaults;
	}

	$schema = [];

	// Only accept allowlisted keys from defaults (drop unknowns).
	foreach ( $defaults as $key => $cfg ) {
		$in = isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ? $decoded[ $key ] : [];

		$schema[ $key ] = [
			'enabled'  => array_key_exists( 'enabled', $in ) ? (bool) $in['enabled'] : (bool) $cfg['enabled'],
			'required' => array_key_exists( 'required', $in ) ? (bool) $in['required'] : (bool) $cfg['required'],
		];
	}

	return $schema;
}

function is_checked_quick_form_checkbox_value( $value ): bool {
	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( is_numeric( $value ) ) {
		return (string) $value === '1';
	}

	if ( ! is_string( $value ) ) {
		return false;
	}

	$normalized = strtolower( trim( $value ) );

	return in_array( $normalized, [ '1', 'true', 'on', 'yes', 'checked' ], true );
}

function sanitize_fields( array $raw, array $schema ): array {

	$clean = [];

	// Registry-driven sanitize (canonical allowlist + canonical types).
	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		foreach ( $order as $key ) {
			if ( ! is_string( $key ) || $key === '' ) {
				continue;
			}

			$cfg = isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ? $schema[ $key ] : [];
			if ( isset( $cfg['enabled'] ) && ! $cfg['enabled'] ) {
				continue;
			}

			$def  = isset( $defs[ $key ] ) && is_array( $defs[ $key ] ) ? $defs[ $key ] : [];
			$type = isset( $def['type'] ) && is_string( $def['type'] ) ? $def['type'] : 'text';

			$raw_value = $raw[ $key ] ?? '';
			$val       = is_scalar( $raw_value ) ? (string) $raw_value : '';

			switch ( $type ) {
				case 'checkbox':
					$clean[ $key ] = is_checked_quick_form_checkbox_value( $raw_value ) ? 'Checked' : 'Unchecked';
					break;

				case 'email':
					$clean[ $key ] = sanitize_email( $val );
					break;

				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $val );
					break;

				case 'tel':
				case 'text':
				default:
					$clean[ $key ] = sanitize_text_field( $val );
					break;
			}
		}
	}

	return $clean;
}

function validate_fields( array $clean, array $schema ): array {

	$errors = [];
	$field_types = [];

	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		foreach ( $order as $field_key ) {
			if ( ! is_string( $field_key ) || $field_key === '' ) {
				continue;
			}

			$def = isset( $defs[ $field_key ] ) && is_array( $defs[ $field_key ] ) ? $defs[ $field_key ] : [];

			$field_types[ $field_key ] = isset( $def['type'] ) && is_string( $def['type'] ) ? $def['type'] : 'text';
		}
	}

	foreach ( $schema as $key => $cfg ) {
		$enabled  = isset( $cfg['enabled'] ) ? (bool) $cfg['enabled'] : true;
		$required = isset( $cfg['required'] ) ? (bool) $cfg['required'] : false;
		$type     = isset( $field_types[ $key ] ) ? (string) $field_types[ $key ] : 'text';

		if ( ! $enabled ) {
			continue;
		}

		$value = isset( $clean[ $key ] ) ? trim( (string) $clean[ $key ] ) : '';

		if ( $type === 'checkbox' ) {
			$is_checked = $value === 'Checked';
			if ( $required && ! $is_checked ) {
				$errors[ $key ] = 'This field is required.';
			}
			continue;
		}

		if ( $required && $value === '' ) {
			$errors[ $key ] = 'This field is required.';
			continue;
		}

		if ( $type === 'email' && $value !== '' && ! is_email( $value ) ) {
			$errors[ $key ] = 'Please enter a valid email address.';
			continue;
		}

		if ( $type === 'tel' && $value !== '' ) {
			$digits = preg_replace( '/\D+/', '', $value );
			if ( is_string( $digits ) && $digits !== '' && strlen( $digits ) < 7 ) {
				$errors[ $key ] = 'Please enter a valid phone number.';
			}
		}
	}

	return $errors;
}

function get_timing_state( string $delta_ms ): string {

	if ( $delta_ms === '' ) {
		return 'missing';
	}

	if ( ! ctype_digit( $delta_ms ) ) {
		return 'invalid';
	}

	$delta = (int) $delta_ms;

	$min_ms = 2000;
	$max_ms = 30 * DAY_IN_SECONDS * 1000;

	if ( $delta < $min_ms ) {
		return 'too-fast';
	}

	if ( $delta > $max_ms ) {
		return 'too-slow';
	}

	return 'ok';
}

function throttle_key( array $client ): bool {

	$ip = $client['ip_cf'] ?: $client['ip_remote'];
	$ua = $client['ua'];

	if ( ! $ip ) {
		return false;
	}

	$key = 'pwire_sf_' . md5( $ip . '|' . $ua );

	$window = 20;
	$window = (int) apply_filters( 'pwire_quick_form_throttle_window', $window );

	if ( get_transient( $key ) ) {
		return true;
	}

	set_transient( $key, 1, $window );

	return false;
}

function get_client_meta(): array {

	$ip_remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip_cf     = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';
	$ua        = isset( $_SERVER['HTTP_USER_AGENT'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$referer   = isset( $_SERVER['HTTP_REFERER'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	$origin    = isset( $_SERVER['HTTP_ORIGIN'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

	return [
		'ip_remote' => $ip_remote,
		'ip_cf'     => $ip_cf,
		'ua'        => $ua,
		'referer'   => $referer,
		'origin'    => $origin,
	];
}

function maybe_store_entry( array $payload ): int {

	if ( ! post_type_exists( 'pwire_qform_entry' ) ) {
		return 0;
	}

	$fields = isset( $payload['fields'] ) && is_array( $payload['fields'] ) ? $payload['fields'] : [];
	$client = isset( $payload['client_meta'] ) && is_array( $payload['client_meta'] ) ? $payload['client_meta'] : [];
	$meta   = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : [];
	$status = isset( $payload['post_status'] ) && is_string( $payload['post_status'] ) ? $payload['post_status'] : 'pwire_lead';
	$status = $status ?: 'publish';

	$name  = isset( $fields['name'] ) ? sanitize_text_field( (string) $fields['name'] ) : '';
	$title = $name !== '' ? $name : 'Quick Form Entry — ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';

	$post_id = wp_insert_post( [
		'post_type'   => 'pwire_qform_entry',
		'post_status' => $status,
		'post_author' => 0,
		'post_title'  => $title,
	], true );

	if ( $post_id instanceof WP_Error ) {
		return 0;
	}

	foreach ( $fields as $k => $v ) {
		update_post_meta( (int) $post_id, 'pwire_' . $k, (string) $v );
	}

	update_post_meta( (int) $post_id, 'pwire_ip_remote', (string) ( $client['ip_remote'] ?? '' ) );
	update_post_meta( (int) $post_id, 'pwire_ip_cf', (string) ( $client['ip_cf'] ?? '' ) );
	update_post_meta( (int) $post_id, 'pwire_user_agent', (string) ( $client['ua'] ?? '' ) );
	update_post_meta( (int) $post_id, 'pwire_referer', (string) ( $client['referer'] ?? '' ) );
	update_post_meta( (int) $post_id, 'pwire_origin', (string) ( $client['origin'] ?? '' ) );

	foreach ( $meta as $k => $v ) {
		update_post_meta( (int) $post_id, (string) $k, $v );
	}

	return (int) $post_id;
}

function send_notification_email( array $fields, array $client, int $entry_id, int $source_post_id = 0 ): bool {

	$notify_raw = get_post_meta( $entry_id, 'pwire_notify_to', true );
	$notify_raw = is_string( $notify_raw ) ? $notify_raw : '';
	$notify_raw = sanitize_notification_emails( $notify_raw );

	$to = $notify_raw;

	if ( $to === '' ) {
		$admin_email = (string) get_option( 'admin_email' );
		$admin_email = sanitize_email( $admin_email );
		$to          = ( $admin_email && is_email( $admin_email ) ) ? $admin_email : '';
	}

	if ( $to === '' ) {
		return false;
	}

	$to = (string) apply_filters( 'pwire_quick_form_notification_to', $to, $fields, $client, $entry_id );

	// Re-sanitize after filters in case something unsafe/invalid gets injected.
	$to = sanitize_notification_emails( (string) $to );

	if ( $to === '' ) {
		return false;
	}

	// Store who we intended to notify on the Entry post.
	update_post_meta( $entry_id, 'pwire_notification_sent_to', $to );

	$is_spam     = false;
	$spam_status = get_post_meta( $entry_id, 'pwire_spam_status', true );
	if ( $spam_status && $spam_status !== 'OK' ) {
		$is_spam = true;
	}

	$subject = 'New Quick Form submission';
	if ( $is_spam ) {
		$subject .= ' - likely spam';
	}
	$subject = (string) apply_filters( 'pwire_quick_form_notification_subject', $subject, $fields, $client, $entry_id );

	$reply_to = isset( $fields['email'] ) && is_email( (string) $fields['email'] ) ? (string) $fields['email'] : '';

	$headers = [];
	if ( $reply_to ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	$rows = [];

	if ( function_exists( __NAMESPACE__ . '\get_quick_form_field_registry' ) ) {
		$reg   = get_quick_form_field_registry();
		$order = isset( $reg['order'] ) && is_array( $reg['order'] ) ? $reg['order'] : [];
		$defs  = isset( $reg['fields'] ) && is_array( $reg['fields'] ) ? $reg['fields'] : [];

		foreach ( $order as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}
			$val   = (string) $fields[ $key ];
			$def   = isset( $defs[ $key ] ) && is_array( $defs[ $key ] ) ? $defs[ $key ] : [];
			$label = isset( $def['label'] ) && is_string( $def['label'] ) && $def['label'] !== '' ? $def['label'] : $key;
			$label = trim( wp_strip_all_tags( $label ) );
			$label = $label !== '' ? $label : $key;

			$rows[ $label ] = $val;
		}
	}

	// Fallback: include any remaining fields (even empty) not already added.
	if ( ! $rows ) {
		foreach ( $fields as $k => $v ) {
			$rows[ $k ] = (string) $v;
		}
	}

	$body = '<div>';
	$body .= '<h2>New submission</h2>';
	if ( $is_spam ) {
		$body .= '<h3 style="color:#c02b0a;margin:0 0 12px 0;">Likely spam</h3>';
	}
	$body .= '<table cellpadding="6" cellspacing="0" border="0">';

	foreach ( $rows as $label => $value ) {
		$body .= '<tr>';
		$body .= '<td style="vertical-align:top;"><strong>' . esc_html( $label ) . '</strong></td>';
		$body .= '<td style="vertical-align:top;">' . nl2br( esc_html( $value ) ) . '</td>';
		$body .= '</tr>';
	}

	$body .= '</table>';

	$body .= '<hr style="border:none;border-top:1px solid #c5c5c5;height:1px;margin:24px 0 0 0;" />';
	$body .= '<div style="height:42px;line-height:42px;font-size:42px;">&nbsp;</div>';

	$body .= '<h3 style="margin:0 0 8px 0;">Sent from</h3>';
	if ( $source_post_id > 0 ) {
		$front_url = get_permalink( $source_post_id );
		$front_url = is_string( $front_url ) ? $front_url : '';
		$title     = get_the_title( $source_post_id );
		$title     = is_string( $title ) && $title !== '' ? $title : ( 'Post #' . $source_post_id );

		if ( $front_url ) {
			$body .= '<p style="margin:0 0 16px 0;"><a href="' . esc_url( $front_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></p>';
		} else {
			$body .= '<p style="margin:0 0 16px 0;">' . esc_html( $title ) . '</p>';
		}
	} else {
		$body .= '<p>—</p>';
	}

//	$sent_to = preg_replace( '/\s*,\s*/', ', ', trim( $to ) );
//
//	$body .= '<h3 style="margin:32px 0 8px 0;">Sent to</h3>';
//	$body .= '<p style="margin:0 0 16px 0;">' . esc_html( $sent_to ) . '</p>';

	$body .= '</div>';

	return (bool) wp_mail( $to, $subject, $body, $headers );
}
