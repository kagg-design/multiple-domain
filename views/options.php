<?php
/**
 * Options view.
 *
 * @package multiple-domain
 */

/**
 * View data.
 *
 * @global array $data
 */

?>
<label>
	<input
			type="checkbox"
			name="multiple-domain-ignore-default-ports"
			value="1"
		<?php if ( $data['ignore_default_ports'] ) : ?>
			checked
		<?php endif; ?>
	>
	<?php esc_html_e( 'Ignore default ports', 'multiple-domain' ); ?>
</label>
<p class="description">
	<?php
	esc_html_e(
		'When enabled, removes the port from URL when redirecting and it\'s a default HTTP (<code>80</code>) or HTTPS (<code>443</code>) port.',
		'multiple-domain'
	);
	?>
</p>
<br/>
<label>
	<input
			type="checkbox"
			name="multiple-domain-add-canonical"
			value="1"
		<?php if ( $data['add_canonical'] ) : ?>
			checked
		<?php endif; ?>
	>
	<?php esc_html_e( 'Add canonical links', 'multiple-domain' ); ?>
</label>
<p class="description">
	<?php
	esc_html_e(
		'When enabled, adds canonical link tags to pages. The domain for canonical links will be the original domain where WordPress is installed. You may want to keep this option unchecked if you have a SEO plugin (e.g. Yoast) installed.',
		'multiple-domain'
	);
	?>
</p>
