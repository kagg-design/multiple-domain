<?php
/**
 * Domains view.
 *
 * @package multiple-domain
 */

/**
 * View data.
 *
 * @global array $data
 */

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $data['fields'];

?>
<p>
	<button type="button" class="button multiple-domain-add">
		<?php esc_html_e( 'Add domain', 'multiple-domain' ); ?>
	</button>
</p>
<p class="description">
	<?php
	echo wp_kses_post(
		__(
			'A domain may contain the port number. If a base URL restriction is set for a domain, all requests that don\'t start with the base URL will be redirected to the base URL. <b>Example</b>: the domain and base URL are <code>example.com</code> and <code>/base/path</code>, when requesting <code>example.com/other/path</code> it will be redirected to <code>example.com/base/path</code>. Additionally, it\'s possible to set a language for each domain, which will be used to add <code>&lt;link&gt;</code> tags with a <code>hreflang</code> attribute to the document head.',
			'multiple-domain'
		)
	);
	?>
</p>
<script type="text/javascript">
	const multipleDomainFields = <?php echo wp_json_encode( $data['fields_to_add'] ); ?>;
</script>
