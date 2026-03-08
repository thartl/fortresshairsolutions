<?php
/**
 * Requires init.php, which is loaded in this plugin's bootstrap.
 */

declare( strict_types=1 );

use function PWire\NativeBlocks\QuickForm\get_effective_quick_form_fields_config;
use function PWire\NativeBlocks\QuickForm\get_quick_form_checkbox_label_allowed_html;
use function PWire\NativeBlocks\QuickForm\sanitize_notification_emails;
use function PWire\NativeBlocks\QuickForm\seal_notification_context_blob;


$attributes = is_array( $attributes ) ? $attributes : [];

$uid = isset( $attributes['uid'] ) && is_string( $attributes['uid'] )
	? trim( $attributes['uid'] )
	: '';

$post_id = 0;
if ( isset( $block ) && $block instanceof \WP_Block ) {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
}
if ( ! $post_id ) {
	$post_id = (int) get_the_ID();
}
if ( ! $post_id ) {
	$post_id = (int) get_queried_object_id();
}

$fields = isset( $attributes['fields'] ) && is_array( $attributes['fields'] )
	? $attributes['fields']
	: [];

$submit_label = isset( $attributes['submitLabel'] ) && is_string( $attributes['submitLabel'] )
	? $attributes['submitLabel']
	: 'Send';

$confirmation_message = isset( $attributes['confirmationMessage'] ) && is_string( $attributes['confirmationMessage'] )
	? $attributes['confirmationMessage']
	: 'Thank you! We’ll be in touch soon.';

$submit_text_color = isset( $attributes['submitTextColor'] ) && is_string( $attributes['submitTextColor'] )
	? $attributes['submitTextColor']
	: '';

$submit_bg_color = isset( $attributes['submitBgColor'] ) && is_string( $attributes['submitBgColor'] )
	? $attributes['submitBgColor']
	: '';

$submit_bg_hover_color = isset( $attributes['submitBgHoverColor'] ) && is_string( $attributes['submitBgHoverColor'] )
	? $attributes['submitBgHoverColor']
	: '';

$field_gap = isset( $attributes['fieldGap'] ) && is_numeric( $attributes['fieldGap'] )
	? $attributes['fieldGap']
	: '';

$label_color = isset( $attributes['labelColor'] ) && is_string( $attributes['labelColor'] )
	? $attributes['labelColor']
	: '';

$field_bg_color = isset( $attributes['fieldBgColor'] ) && is_string( $attributes['fieldBgColor'] )
	? $attributes['fieldBgColor']
	: '';

$field_border_color = isset( $attributes['fieldBorderColor'] ) && is_string( $attributes['fieldBorderColor'] )
	? $attributes['fieldBorderColor']
	: '';

$field_border_width = isset( $attributes['fieldBorderWidth'] ) && is_numeric( $attributes['fieldBorderWidth'] )
	? (float) $attributes['fieldBorderWidth']
	: null;

$field_border_radius = isset( $attributes['fieldBorderRadius'] ) && is_numeric( $attributes['fieldBorderRadius'] )
	? (float) $attributes['fieldBorderRadius']
	: null;

$label_font_size = isset( $attributes['labelFontSize'] ) && is_numeric( $attributes['labelFontSize'] )
	? (float) $attributes['labelFontSize']
	: '';

$notification_emails = isset( $attributes['notificationEmails'] ) && is_string( $attributes['notificationEmails'] )
	? $attributes['notificationEmails']
	: '';

$notification_blob = seal_notification_context_blob(
	sanitize_notification_emails( $notification_emails ),
	$uid,
	$post_id
);

$success_arg = isset( $attributes['successQueryArg'] ) && is_string( $attributes['successQueryArg'] ) && $attributes['successQueryArg'] !== ''
	? $attributes['successQueryArg']
	: 'pwire_form';

$success_value = isset( $attributes['successQueryValue'] ) && is_string( $attributes['successQueryValue'] ) && $attributes['successQueryValue'] !== ''
	? $attributes['successQueryValue']
	: 'success';

$error_value = 'error';

$form_id = function_exists( 'wp_unique_id' )
	? wp_unique_id( 'pwire-quick-form-' )
	: ( 'pwire-quick-form-' . (string) wp_rand( 10000, 99999 ) );

$hp_name = 'pwire_hp';

/**
 * Pull canonical field definitions from PHP registry and merge with block overrides.
 */
$fields_config = get_effective_quick_form_fields_config( $fields );

$field_order = isset( $fields_config['order'] ) && is_array( $fields_config['order'] )
	? $fields_config['order']
	: [];

$effective_fields = isset( $fields_config['fields'] ) && is_array( $fields_config['fields'] )
	? $fields_config['fields']
	: [];

$to_rem = static function ( $value ) use ( &$to_rem ): string {
	if ( is_numeric( $value ) ) {
		$rem        = (float) $value / 16;
		$rem_string = $rem === 0.0 ? '0' : rtrim( rtrim( sprintf( '%.6f', $rem ), '0' ), '.' );

		return $rem_string . 'rem';
	}

	if ( is_string( $value ) ) {
		$trimmed = trim( $value );

		if ( $trimmed === '' ) {
			return '';
		}

		if ( preg_match( '/rem$/i', $trimmed ) ) {
			return $trimmed;
		}

		if ( preg_match( '/^(-?\d+(?:\.\d+)?)px$/i', $trimmed, $matches ) ) {
			return $to_rem( (float) $matches[1] );
		}

		if ( is_numeric( $trimmed ) ) {
			return $to_rem( (float) $trimmed );
		}

		return $trimmed;
	}

	return '';
};

$is_success = isset( $_GET[ $success_arg ] ) && (string) $_GET[ $success_arg ] === (string) $success_value;
$is_error   = isset( $_GET[ $success_arg ] ) && (string) $_GET[ $success_arg ] === (string) $error_value;

$current_url = '';
if ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
	$scheme      = is_ssl() ? 'https' : 'http';
	$current_url = $scheme . '://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] );
	$current_url = esc_url_raw( $current_url );
}

$base_url = $current_url ? remove_query_arg( $success_arg, $current_url ) : '';

$success_url = $base_url ? add_query_arg( [ $success_arg => $success_value ], $base_url ) : '';
$error_url   = $base_url ? add_query_arg( [ $success_arg => $error_value ], $base_url ) : '';

$vars = [];

$field_gap_rem = $to_rem( $field_gap );
if ( $field_gap_rem !== '' ) {
	$vars[] = '--pwire-quick-form-field-gap:' . $field_gap_rem;
}
if ( $label_color !== '' ) {
	$vars[] = '--pwire-quick-form-label-color:' . $label_color;
}
if ( $field_bg_color !== '' ) {
	$vars[] = '--pwire-quick-form-field-bg:' . $field_bg_color;
}
if ( $field_border_color !== '' ) {
	$vars[] = '--pwire-quick-form-field-border-color:' . $field_border_color;
}
$field_border_width_rem = $to_rem( $field_border_width );
if ( $field_border_width_rem !== '' ) {
	$vars[] = '--pwire-quick-form-field-border-width:' . $field_border_width_rem;
}
$field_border_radius_rem = $to_rem( $field_border_radius );
if ( $field_border_radius_rem !== '' ) {
	$vars[] = '--pwire-quick-form-field-border-radius:' . $field_border_radius_rem;
}

$label_font_size_val = '';
if ( is_numeric( $label_font_size ) ) {
	$label_font_size_val = rtrim( rtrim( (string) $label_font_size, '0' ), '.' ) . 'em';
}
if ( $label_font_size_val !== '' ) {
	$vars[] = '--pwire-quick-form-label-font-size:' . $label_font_size_val;
}
if ( $submit_text_color !== '' ) {
	$vars[] = '--pwire-quick-form-submit-text-color:' . $submit_text_color;
}
if ( $submit_bg_color !== '' ) {
	$vars[] = '--pwire-quick-form-submit-bg-color:' . $submit_bg_color;
}
if ( $submit_bg_hover_color !== '' ) {
	$vars[] = '--pwire-quick-form-submit-bg-hover-color:' . $submit_bg_hover_color;
}

$inner_style = $vars ? implode( ';', $vars ) . ';' : '';

$inner_classes = [ 'pwire-quick-form' ];
if ( $submit_text_color !== '' ) {
	$inner_classes[] = 'has-submit-text-color';
}
if ( $submit_bg_color !== '' ) {
	$inner_classes[] = 'has-submit-bg-color';
}
if ( $submit_bg_hover_color !== '' ) {
	$inner_classes[] = 'has-submit-bg-hover-color';
}
$inner_class_attr = implode( ' ', $inner_classes );

$wrapper_attrs = function_exists( 'get_block_wrapper_attributes' )
	? get_block_wrapper_attributes()
	: '';

$rest_url = function_exists( 'rest_url' )
	? rest_url( 'pwire/v1/quick-form/submit' )
	: '';

$schema = [];
foreach ( $field_order as $key ) {
	$cfg = isset( $effective_fields[ $key ] ) && is_array( $effective_fields[ $key ] )
		? $effective_fields[ $key ]
		: [];

	$schema[ $key ] = [
		'enabled'  => isset( $cfg['enabled'] ) ? (bool) $cfg['enabled'] : true,
		'required' => isset( $cfg['required'] ) ? (bool) $cfg['required'] : false,
	];
}

$schema_json = wp_json_encode( $schema );
$schema_b64  = $schema_json ? base64_encode( $schema_json ) : '';

?>
<div <?php echo $wrapper_attrs; ?>>
	<div
		class="<?php echo esc_attr( $inner_class_attr ); ?>"
		<?php if ( $inner_style ) : ?>
			style="<?php echo esc_attr( $inner_style ); ?>"
		<?php endif; ?>
	>
		<div
			id="<?php echo esc_attr( $form_id . '-status' ); ?>"
			class="pwire-quick-form__status"
			role="status"
			aria-live="polite"
			<?php if ( ! $is_success ) : ?>
				hidden
			<?php endif; ?>
		>
			<?php if ( $is_success ) : ?>
				<?php echo nl2br( esc_html( $confirmation_message ) ); ?>
			<?php endif; ?>
		</div>

		<div
			id="<?php echo esc_attr( $form_id . '-errors' ); ?>"
			class="pwire-quick-form__errors"
			role="alert"
			<?php if ( ! $is_error ) : ?>
				hidden
			<?php endif; ?>
		>
			<?php if ( $is_error ) : ?>
				<p class="pwire-quick-form__errors-message">
					<?php esc_html_e( 'There was a problem with your submission. Please review the fields below.', 'pwire' ); ?>
				</p>
				<ul class="pwire-quick-form__errors-list">
					<li><?php echo esc_html__( 'Please check the form and try again.', 'pwire' ); ?></li>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( ! $is_success ) : ?>
			<form
				class="pwire-quick-form__form"
				id="<?php echo esc_attr( $form_id ); ?>"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
				data-status-id="<?php echo esc_attr( $form_id . '-status' ); ?>"
				data-errors-id="<?php echo esc_attr( $form_id . '-errors' ); ?>"
				data-confirmation-message="<?php echo esc_attr( $confirmation_message ); ?>"
				novalidate
			>
				<input type="hidden" name="action" value="pwire_quick_form_submit"/>
				<input type="hidden" name="pwire_form_instance" value="<?php echo esc_attr( $form_id ); ?>"/>

				<input type="hidden" name="pwire_return" value="<?php echo esc_attr( $base_url ); ?>"/>
				<input type="hidden" name="pwire_return_success" value="<?php echo esc_attr( $success_url ); ?>"/>
				<input type="hidden" name="pwire_return_error" value="<?php echo esc_attr( $error_url ); ?>"/>
				<input type="hidden" name="pwire_confirmation_message"
					   value="<?php echo esc_attr( $confirmation_message ); ?>"/>

				<input type="hidden" name="pwire_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>"/>
				<input type="hidden" name="pwire_block_uid" value="<?php echo esc_attr( $uid ); ?>"/>
				<?php if ( $notification_blob !== '' ) : ?>
					<input type="hidden" name="pwire_notification_blob" value="<?php echo esc_attr( $notification_blob ); ?>"/>
				<?php endif; ?>

				<input type="hidden" name="pwire_fields_config"
					   value="<?php echo esc_attr( (string) $schema_b64 ); ?>"/>

				<input type="hidden" name="pwire_loaded_at" value=""/>
				<input type="hidden" name="pwire_delta_ms" value=""/>

				<div style="position:absolute;left:-624.9375rem;width:0.0625rem;height:0.0625rem;overflow:hidden;"
					 aria-hidden="true">
					<label for="<?php echo esc_attr( $form_id . '-hp' ); ?>">Website</label>
					<input
						type="text"
						id="<?php echo esc_attr( $form_id . '-hp' ); ?>"
						name="<?php echo esc_attr( $hp_name ); ?>"
						value=""
						tabindex="-1"
						autocomplete="off"
					/>
				</div>

				<?php foreach ( $field_order as $key ) : ?>
					<?php
					$cfg = isset( $effective_fields[ $key ] ) && is_array( $effective_fields[ $key ] )
						? $effective_fields[ $key ]
						: [];

					$enabled  = isset( $cfg['enabled'] ) ? (bool) $cfg['enabled'] : true;
					$required = isset( $cfg['required'] ) ? (bool) $cfg['required'] : false;
					$label    = isset( $cfg['label'] ) && is_string( $cfg['label'] ) ? $cfg['label'] : (string) $key;

					if ( ! $enabled ) {
						continue;
					}

					$id    = $form_id . '-' . $key;

					$type = isset( $cfg['type'] ) && is_string( $cfg['type'] ) ? $cfg['type'] : 'text';
					$is_checkbox = $type === 'checkbox';

					$autocomplete = isset( $cfg['autocomplete'] ) && is_string( $cfg['autocomplete'] ) ? $cfg['autocomplete'] : '';

					$checkbox_label_html = '';
					if ( $is_checkbox ) {
						$checkbox_label_html = wp_kses( $label, get_quick_form_checkbox_label_allowed_html() );
						$checkbox_label_text = trim( wp_strip_all_tags( $checkbox_label_html ) );

						if ( $checkbox_label_text === '' ) {
							$checkbox_label_text = (string) $key;
							$checkbox_label_html = esc_html( $checkbox_label_text );
						}
					} else {
						$label = wp_strip_all_tags( $label );
					}
					?>
					<div class="<?php echo esc_attr( 'pwire-quick-form__field' . ( $is_checkbox ? ' pwire-quick-form__field--checkbox' : '' ) ); ?>">
						<?php if ( $is_checkbox ) : ?>
							<label class="pwire-quick-form__checkbox-label" for="<?php echo esc_attr( $id ); ?>">
								<input
									class="pwire-quick-form__input pwire-quick-form__input--checkbox"
									id="<?php echo esc_attr( $id ); ?>"
									name="<?php echo esc_attr( $key ); ?>"
									type="checkbox"
									value="1"
									<?php if ( $required ) : ?>required<?php endif; ?>
								/>
								<span class="pwire-quick-form__checkbox-text">
									<?php echo $checkbox_label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php if ( $required ) : ?><span class="pwire-quick-form__required"> *</span><?php endif; ?>
								</span>
							</label>
						<?php elseif ( $type === 'textarea' ) : ?>
							<label class="pwire-quick-form__label" for="<?php echo esc_attr( $id ); ?>">
								<?php echo esc_html( $label ); ?><?php if ( $required ) : ?><span
									class="pwire-quick-form__required"> *</span><?php endif; ?>
							</label>
							<textarea
								class="pwire-quick-form__input"
								id="<?php echo esc_attr( $id ); ?>"
								name="<?php echo esc_attr( $key ); ?>"
								rows="5"
								<?php if ( $required ) : ?>required<?php endif; ?>
								<?php if ( $autocomplete ) : ?>autocomplete="<?php echo esc_attr( $autocomplete ); ?>"<?php endif; ?>
							></textarea>
						<?php else : ?>
							<label class="pwire-quick-form__label" for="<?php echo esc_attr( $id ); ?>">
								<?php echo esc_html( $label ); ?><?php if ( $required ) : ?><span
									class="pwire-quick-form__required"> *</span><?php endif; ?>
							</label>
							<input
								class="pwire-quick-form__input"
								id="<?php echo esc_attr( $id ); ?>"
								name="<?php echo esc_attr( $key ); ?>"
								type="<?php echo esc_attr( $type ); ?>"
								<?php if ( $required ) : ?>required<?php endif; ?>
								<?php if ( $autocomplete ) : ?>autocomplete="<?php echo esc_attr( $autocomplete ); ?>"<?php endif; ?>
							/>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="pwire-quick-form__actions">
					<button type="submit" class="pwire-quick-form__button wp-element-button">
						<?php echo esc_html( $submit_label ); ?>
					</button>
				</div>
			</form>
		<?php endif; ?>
	</div>
</div>
