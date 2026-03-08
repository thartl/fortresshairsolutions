<?php
$post_id = 0;

if ( isset( $block ) && $block instanceof WP_Block && isset( $block->context['postId'] ) ) {
	$post_id = (int) $block->context['postId'];
}

if ( ! $post_id && function_exists( 'get_queried_object_id' ) ) {
	$post_id = (int) get_queried_object_id();
}

$current_url = '';
$current_title = '';
$site_title = get_bloginfo( 'name' );
$site_title = is_string( $site_title ) ? wp_strip_all_tags( $site_title ) : '';
$charset = get_bloginfo( 'charset' );
$charset = is_string( $charset ) && $charset !== '' ? $charset : 'UTF-8';

if ( $post_id ) {
	$current_url = get_permalink( $post_id ) ?: '';
	$current_title = get_the_title( $post_id ) ?: '';
}

if ( ! $current_url ) {
	$scheme = is_ssl() ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? '';
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	$current_url = $host ? ( $scheme . '://' . $host . $uri ) : home_url( '/' );
}

if ( ! $current_title ) {
	$current_title = $site_title;
}

$current_title = wp_strip_all_tags( $current_title );
$site_title = html_entity_decode( $site_title, ENT_QUOTES | ENT_HTML5, $charset );
$current_title = html_entity_decode( $current_title, ENT_QUOTES | ENT_HTML5, $charset );
$mail_context_title = trim( $site_title . ' - ' . $current_title, " \t\n\r\0\x0B-" );
$mail_subject = $mail_context_title !== '' ? $mail_context_title : $current_title;
$mail_body = sprintf(
	'I wanted to share this page with you. %1$s: %2$s',
	$mail_context_title,
	$current_url
);

$share_urls = [
	'mail' => 'mailto:?subject=' . rawurlencode( $mail_subject ) . '&body=' . rawurlencode( $mail_body ),
	'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $current_url ),
	'x' => 'https://twitter.com/intent/tweet?url=' . rawurlencode( $current_url ) . '&text=' . rawurlencode( $current_title ),
	'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $current_url ),
	'whatsapp' => 'https://wa.me/?text=' . rawurlencode( $current_title . ' ' . $current_url ),
];

$icons = [
	'mail' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M19,5H5c-1.1,0-2,.9-2,2v10c0,1.1.9,2,2,2h14c1.1,0,2-.9,2-2V7c0-1.1-.9-2-2-2zm.5,12c0,.3-.2.5-.5.5H5c-.3,0-.5-.2-.5-.5V9.8l7.5,5.6,7.5-5.6V17zm0-9.1L12,13.6,4.5,7.9V7c0-.3.2-.5.5-.5h14c.3,0,.5.2.5.5v.9z"></path></svg>',
	'facebook' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path></svg>',
	'x' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M13.982 10.622 20.54 3h-1.554l-5.693 6.618L8.745 3H3.5l6.876 10.007L3.5 21h1.554l6.012-6.989L15.868 21h5.245l-7.131-10.378Zm-2.128 2.474-.697-.997-5.543-7.93H8l4.474 6.4.697.996 5.815 8.318h-2.387l-4.745-6.787Z"></path></svg>',
	'linkedin' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M19.7,3H4.3C3.582,3,3,3.582,3,4.3v15.4C3,20.418,3.582,21,4.3,21h15.4c0.718,0,1.3-0.582,1.3-1.3V4.3 C21,3.582,20.418,3,19.7,3z M8.339,18.338H5.667v-8.59h2.672V18.338z M7.004,8.574c-0.857,0-1.549-0.694-1.549-1.548 c0-0.855,0.691-1.548,1.549-1.548c0.854,0,1.547,0.694,1.547,1.548C8.551,7.881,7.858,8.574,7.004,8.574z M18.339,18.338h-2.669 v-4.177c0-0.996-0.017-2.278-1.387-2.278c-1.389,0-1.601,1.086-1.601,2.206v4.249h-2.667v-8.59h2.559v1.174h0.037 c0.356-0.675,1.227-1.387,2.526-1.387c2.703,0,3.203,1.779,3.203,4.092V18.338z"></path></svg>',
	'whatsapp' => '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M 12.011719 2 C 6.5057187 2 2.0234844 6.478375 2.0214844 11.984375 C 2.0204844 13.744375 2.4814687 15.462563 3.3554688 16.976562 L 2 22 L 7.2324219 20.763672 C 8.6914219 21.559672 10.333859 21.977516 12.005859 21.978516 L 12.009766 21.978516 C 17.514766 21.978516 21.995047 17.499141 21.998047 11.994141 C 22.000047 9.3251406 20.962172 6.8157344 19.076172 4.9277344 C 17.190172 3.0407344 14.683719 2.001 12.011719 2 z M 12.009766 4 C 14.145766 4.001 16.153109 4.8337969 17.662109 6.3417969 C 19.171109 7.8517969 20.000047 9.8581875 19.998047 11.992188 C 19.996047 16.396187 16.413812 19.978516 12.007812 19.978516 C 10.674812 19.977516 9.3544062 19.642812 8.1914062 19.007812 L 7.5175781 18.640625 L 6.7734375 18.816406 L 4.8046875 19.28125 L 5.2851562 17.496094 L 5.5019531 16.695312 L 5.0878906 15.976562 C 4.3898906 14.768562 4.0204844 13.387375 4.0214844 11.984375 C 4.0234844 7.582375 7.6067656 4 12.009766 4 z M 8.4765625 7.375 C 8.3095625 7.375 8.0395469 7.4375 7.8105469 7.6875 C 7.5815469 7.9365 6.9355469 8.5395781 6.9355469 9.7675781 C 6.9355469 10.995578 7.8300781 12.182609 7.9550781 12.349609 C 8.0790781 12.515609 9.68175 15.115234 12.21875 16.115234 C 14.32675 16.946234 14.754891 16.782234 15.212891 16.740234 C 15.670891 16.699234 16.690438 16.137687 16.898438 15.554688 C 17.106437 14.971687 17.106922 14.470187 17.044922 14.367188 C 16.982922 14.263188 16.816406 14.201172 16.566406 14.076172 C 16.317406 13.951172 15.090328 13.348625 14.861328 13.265625 C 14.632328 13.182625 14.464828 13.140625 14.298828 13.390625 C 14.132828 13.640625 13.655766 14.201187 13.509766 14.367188 C 13.363766 14.534188 13.21875 14.556641 12.96875 14.431641 C 12.71875 14.305641 11.914938 14.041406 10.960938 13.191406 C 10.218937 12.530406 9.7182656 11.714844 9.5722656 11.464844 C 9.4272656 11.215844 9.5585938 11.079078 9.6835938 10.955078 C 9.7955938 10.843078 9.9316406 10.663578 10.056641 10.517578 C 10.180641 10.371578 10.223641 10.267562 10.306641 10.101562 C 10.389641 9.9355625 10.347156 9.7890625 10.285156 9.6640625 C 10.223156 9.5390625 9.737625 8.3065 9.515625 7.8125 C 9.328625 7.3975 9.131125 7.3878594 8.953125 7.3808594 C 8.808125 7.3748594 8.6425625 7.375 8.4765625 7.375 z"></path></svg>',
];

$networks = [
	[
		'slug' => 'linkedin',
		'label' => 'LinkedIn',
		'url' => $share_urls['linkedin'],
		'icon' => $icons['linkedin'],
		'open_in_new_tab' => true,
		'enabled' => isset( $attributes['showLinkedIn'] ) ? (bool) $attributes['showLinkedIn'] : true,
	],
	[
		'slug' => 'x',
		'label' => 'X',
		'url' => $share_urls['x'],
		'icon' => $icons['x'],
		'open_in_new_tab' => true,
		'enabled' => isset( $attributes['showX'] ) ? (bool) $attributes['showX'] : true,
	],
	[
		'slug' => 'facebook',
		'label' => 'Facebook',
		'url' => $share_urls['facebook'],
		'icon' => $icons['facebook'],
		'open_in_new_tab' => true,
		'enabled' => isset( $attributes['showFacebook'] ) ? (bool) $attributes['showFacebook'] : true,
	],
	[
		'slug' => 'whatsapp',
		'label' => 'WhatsApp',
		'url' => $share_urls['whatsapp'],
		'icon' => $icons['whatsapp'],
		'open_in_new_tab' => true,
		'enabled' => isset( $attributes['showWhatsApp'] ) ? (bool) $attributes['showWhatsApp'] : true,
	],
	[
		'slug' => 'email',
		'label' => 'Email',
		'url' => $share_urls['mail'],
		'icon' => $icons['mail'],
		'open_in_new_tab' => false,
		'enabled' => isset( $attributes['showEmail'] ) ? (bool) $attributes['showEmail'] : true,
	],
];

$enabled_networks = array_filter(
	$networks,
	static fn( $network ) => ! empty( $network['enabled'] )
);

if ( empty( $enabled_networks ) ) {
	if ( is_admin() ) {
		echo '<p class="pwire-social-share__empty">Select at least one network.</p>';
	}
	return;
}

$icon_color = '';
if ( ! empty( $attributes['iconColorValue'] ) && is_string( $attributes['iconColorValue'] ) ) {
	$icon_color = sanitize_hex_color( $attributes['iconColorValue'] ) ?: '';
}
if ( ! $icon_color && ! empty( $attributes['customIconColor'] ) && is_string( $attributes['customIconColor'] ) ) {
	$icon_color = sanitize_hex_color( $attributes['customIconColor'] ) ?: '';
}

$icon_background = '';
if ( ! empty( $attributes['iconBackgroundColorValue'] ) && is_string( $attributes['iconBackgroundColorValue'] ) ) {
	$icon_background = sanitize_hex_color( $attributes['iconBackgroundColorValue'] ) ?: '';
}
if ( ! $icon_background && ! empty( $attributes['customIconBackgroundColor'] ) && is_string( $attributes['customIconBackgroundColor'] ) ) {
	$icon_background = sanitize_hex_color( $attributes['customIconBackgroundColor'] ) ?: '';
}

$wrapper_classes = [
	'wp-block-social-links',
	'is-layout-flex',
	'wp-block-social-links-is-layout-flex',
];

$icon_size = isset( $attributes['size'] ) && is_string( $attributes['size'] ) ? $attributes['size'] : '';
if ( in_array( $icon_size, [ 'small', 'normal', 'large', 'huge' ], true ) ) {
	$wrapper_classes[] = 'has-' . $icon_size . '-icon-size';
}

if ( $icon_color ) {
	$wrapper_classes[] = 'has-icon-color';
}

if ( $icon_background ) {
	$wrapper_classes[] = 'has-icon-background-color';
}

$style_parts = [];
if ( $icon_color ) {
	$style_parts[] = '--pwire-social-share-icon-color:' . $icon_color;
}
if ( $icon_background ) {
	$style_parts[] = '--pwire-social-share-icon-background:' . $icon_background;
}

$wrapper_attributes = get_block_wrapper_attributes(
	[
		'class' => implode( ' ', $wrapper_classes ),
		'style' => implode( ';', $style_parts ),
	]
);

echo '<ul ' . $wrapper_attributes . '>';

foreach ( $enabled_networks as $network ) {
	$target = $network['open_in_new_tab'] ? ' target="_blank"' : '';
	$rel = $network['open_in_new_tab'] ? ' rel="noopener nofollow"' : '';

	printf(
		'<li class="wp-social-link wp-social-link-%1$s wp-block-social-link pwire-social-share__item"><a class="wp-block-social-link-anchor pwire-social-share__link" href="%2$s"%3$s%4$s>%5$s<span class="wp-block-social-link-label screen-reader-text">%6$s</span></a></li>',
		esc_attr( $network['slug'] ),
		esc_url( $network['url'] ),
		$target,
		$rel,
		$network['icon'],
		esc_html( $network['label'] )
	);
}

echo '</ul>';
