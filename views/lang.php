<?php
/**
 * Lang view.
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
	<select name="multiple-domain-domains[<?php echo esc_attr( $data['count'] ); ?>][lang]">
		<option value="">
			<?php esc_html_e( 'None', 'multiple-domain' ); ?>
		</option>
		<option value="" disabled="disabled">--</option>
		<?php foreach ( $data['locales'] as $code => $name ) : ?>
			<option value="<?php echo esc_attr( $code ); ?>"
				<?php if ( $data['lang'] === $code ) : ?>
					selected
				<?php endif; ?>
			>
				<?php echo esc_attr( $name ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</label>
