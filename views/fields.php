<?php
/**
 * Fields view.
 *
 * @package multiple-domain
 */

/**
 * View data.
 *
 * @global array $data
 */

?>
<p class="multiple-domain-domain">
	<select
			name="multiple-domain-domains[<?php echo esc_attr( $data['count'] ); ?>][protocol]"
			title="<?php esc_html_e( 'Protocol', 'multiple-domain' ); ?>"
	>
		<option
				value="auto"
			<?php if ( empty( $data['protocol'] ) || 'auto' === $data['protocol'] ) : ?>
				selected
			<?php endif; ?>
		>
			Auto
		</option>
		<option
				value="http"
			<?php if ( 'http' === $data['protocol'] ) : ?>
				selected
			<?php endif; ?>
		>
			http://
		</option>
		<option
				value="https"
			<?php if ( 'https' === $data['protocol'] ) : ?>
				selected
			<?php endif; ?>
		>
			https://
		</option>
	</select>
	<input
			type="text"
			name="multiple-domain-domains[<?php echo esc_attr( $data['count'] ); ?>][host]"
			value="<?php echo esc_html( $data['host'] ?: '' ); ?>"
			class="regular-text code"
			placeholder="example.com"
			title="<?php esc_html_e( 'Domain', 'multiple-domain' ); ?>"
	>
	<input
			type="text"
			name="multiple-domain-domains[<?php echo esc_attr( $data['count'] ); ?>][base]"
			value="<?php echo esc_html( $data['base'] ?: '' ); ?>"
			class="regular-text code"
			placeholder="/base/path"
			title="<?php esc_html_e( 'Base path restriction', 'multiple-domain' ); ?>"
	>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $data['lang_field'];
	?>
	<button type="button" class="button multiple-domain-remove">
		<span class="required"><?php esc_html_e( 'Remove', 'multiple-domain' ); ?></span>
	</button>
</p>
