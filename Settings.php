<?php
/**
 * Settings class file.
 *
 * @package multiple-domain
 */

namespace MultipleDomain;

/**
 * Multiple Domain settings.
 *
 * @author  Gustavo Straube <https://github.com/straube>
 * @since   0.11.0
 */
class Settings {

	/**
	 * The plugin core instance.
	 *
	 * @var Main
	 */
	private $core;

	/**
	 * Create a new instance.
	 *
	 * Adds actions and filters required by the plugin for the admin.
	 *
	 * @param Main $core The core plugin class instance.
	 */
	public function __construct( Main $core ) {
		$this->core = $core;

		$this->hook_actions();
		$this->hook_filters();
	}

	/**
	 * Hook plugin actions to WordPress.
	 *
	 * @return void
	 */
	private function hook_actions(): void {
		add_action( 'admin_init', [ $this, 'settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
	}

	/**
	 * Hook plugin filters to WordPress.
	 *
	 * @return void
	 */
	private function hook_filters(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( MULTIPLE_DOMAIN_FILE ), [ $this, 'action_links' ] );
	}

	/**
	 * Sets up the required settings to show in the admin.
	 *
	 * @return void
	 */
	public function settings(): void {
		add_settings_section(
			'multiple-domain',
			__( 'Multiple Domain', 'multiple-domain' ),
			[ $this, 'settings_heading' ],
			'general'
		);
		add_settings_field(
			'multiple-domain-domains',
			__( 'Domains', 'multiple-domain' ),
			[ $this, 'settings_fields_for_domains' ],
			'general',
			'multiple-domain'
		);
		add_settings_field(
			'multiple-domain-options',
			__( 'Options', 'multiple-domain' ),
			[ $this, 'settings_fields_for_options' ],
			'general',
			'multiple-domain'
		);

		register_setting(
			'general',
			'multiple-domain-domains',
			[ $this, 'sanitize_domains_settings' ]
		);
		register_setting(
			'general',
			'multiple-domain-ignore-default-ports',
			[ $this, 'cast_to_bool' ]
		);
		register_setting(
			'general',
			'multiple-domain-add-canonical',
			[ $this, 'cast_to_bool' ]
		);
	}

	/**
	 * Sanitizes the domain settings.
	 *
	 * It takes the value sent by the user in the settings form and parses it
	 * to store in the internal format used by the plugin.
	 *
	 * @param array|mixed $value The user defined option value.
	 *
	 * @return array The sanitized option value.
	 */
	public function sanitize_domains_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$domains = [];

		foreach ( $value as $row ) {
			if ( empty( $row['host'] ) ) {
				continue;
			}

			$host             = preg_replace( '/^https?:\/\//i', '', $row['host'] );
			$base             = ! empty( $row['base'] ) ? $row['base'] : null;
			$lang             = ! empty( $row['lang'] ) ? $row['lang'] : null;
			$proto            = ! empty( $row['protocol'] ) ? $row['protocol'] : 'auto';
			$domains[ $host ] = [
				'base'     => $base,
				'lang'     => $lang,
				'protocol' => $proto,
			];
		}

		return $domains;
	}

	/**
	 * Casts the given value to boolean.
	 *
	 * @param mixed $value The value to cast.
	 *
	 * @return bool A boolean representing the passed value.
	 */
	public function cast_to_bool( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Renders the settings heading.
	 *
	 * @return void
	 */
	public function settings_heading(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->load_view( 'heading' );
	}

	/**
	 * Renders the fields for setting domains.
	 *
	 * @return void
	 */
	public function settings_fields_for_domains(): void {
		$fields  = '';
		$counter = 0;

		foreach ( $this->core->get_domains() as $domain => $values ) {
			$base     = null;
			$lang     = null;
			$protocol = null;

			/*
			 * Backward compatibility with earlier versions.
			 */
			if ( is_string( $values ) ) {
				$base = $values;
			} else {
				$base     = ! empty( $values['base'] ) ? $values['base'] : null;
				$lang     = ! empty( $values['lang'] ) ? $values['lang'] : null;
				$protocol = ! empty( $values['protocol'] ) ? $values['protocol'] : null;
			}

			$fields .= $this->get_domain_fields( $counter++, $domain, $base, $lang, $protocol );
		}

		/*
		 * Adds a row of empty fields to the settings when no domain is set.
		 */
		if ( 0 === $counter ) {
			$fields = $this->get_domain_fields( $counter );
		}

		$fields_to_add = $this->get_domain_fields( 'COUNT' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->load_view( 'domains', compact( 'fields', 'fields_to_add' ) );
	}

	/**
	 * Renders the fields for plugin options.
	 *
	 * @return void
	 */
	public function settings_fields_for_options(): void {
		$ignore_default_ports = $this->core->should_ignore_default_ports();
		$add_canonical        = $this->core->should_add_canonical();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->load_view( 'options', compact( 'ignore_default_ports', 'add_canonical' ) );
	}

	/**
	 * Enqueues the required scripts.
	 *
	 * @param string|mixed $hook The current admin page.
	 *
	 * @return void
	 */
	public function scripts( $hook ): void {
		if ( 'options-general.php' !== (string) $hook ) {
			return;
		}

		$settings_path = plugins_url( 'settings.js', MULTIPLE_DOMAIN_FILE );

		wp_enqueue_script(
			'multiple-domain-settings',
			$settings_path,
			[ 'jquery' ],
			MULTIPLE_DOMAIN_VERSION,
			true
		);
	}

	/**
	 * Add the "Settings" link to the plugin row in the plugin's page.
	 *
	 * @param array|mixed $links The default list of links.
	 *
	 * @return array The updated list of links.
	 */
	public function action_links( $links ): array {
		$links = (array) $links;

		$url  = admin_url( 'options-general.php#multiple-domain' );
		$link = '<a href="' . $url . '">' . __( 'Settings', 'multiple-domain' ) . '</a>';

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Returns the fields for a domain setting.
	 *
	 * @param int|string  $count    The field count. It's used within the field name, since it's an array.
	 * @param string|null $host     The host field value.
	 * @param string|null $base     The base URL field value.
	 * @param string|null $lang     The language field value.
	 * @param string|null $protocol The protocol handling option.
	 *
	 * @return string The rendered group of fields.
	 */
	private function get_domain_fields( $count, ?string $host = null, ?string $base = null, ?string $lang = null, ?string $protocol = null ) {
		$lang_field = $this->get_lang_field( $count, $lang );

		return $this->load_view( 'fields', compact( 'count', 'host', 'base', 'protocol', 'lang_field' ) );
	}

	/**
	 * Gets the language field for domain settings.
	 *
	 * @param int|string  $count The field count. It's used within the field name, since it's an array.
	 * @param string|null $lang  The selected language.
	 *
	 * @return string The rendered field.
	 */
	private function get_lang_field( $count, ?string $lang = null ) {
		/*
		 * Backward compatibility with a locale defined in previous versions.
		 *
		 * The HTML `lang` attribute uses a dash (`en-US`) to separate language
		 * and region, but WP languages have an underscore (`en_US`).
		 */
		if ( ! empty( $lang ) ) {
			$lang = str_replace( '-', '_', $lang );
		}

		$locales = $this->get_locales();

		return $this->load_view( 'lang', compact( 'count', 'lang', 'locales' ) );
	}

	/**
	 * Get the list of locales.
	 *
	 * The keys of the returned array are locale codes and the values are their names.
	 *
	 * A cached version will be returned if available.
	 *
	 * @return array The locales list.
	 */
	private function get_locales(): array {
		$locales = wp_cache_get( 'locales', 'multiple-domain' );

		if ( empty( $locales ) ) {
			$locales = $this->get_locales_from_file();

			wp_cache_set( 'locales', $locales, 'multiple-domain' );
		}

		return $locales;
	}

	/**
	 * Get the list of locales from the source file.
	 *
	 * The keys of the returned array are locale codes and the values are their names.
	 *
	 * @return array The locales list.
	 */
	private function get_locales_from_file(): array {
		$locales = [];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( dirname( MULTIPLE_DOMAIN_FILE ) . '/locales.csv', 'rb' );

		$row = fgetcsv( $handle );

		while ( false !== $row ) {
			$locales[ $row[0] ] = $row[1];
			$row                = fgetcsv( $handle );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		asort( $locales );

		return $locales;
	}

	/**
	 * Load a view and return its contents.
	 *
	 * @param string     $name The view name.
	 * @param array|null $data The data to pass to the view.
	 *
	 * @return string The view contents.
	 */
	private function load_view( string $name, ?array $data = null ) {
		$path = sprintf( '%s/views/%s.php', dirname( MULTIPLE_DOMAIN_FILE ), $name );

		if ( ! is_file( $path ) ) {
			return false;
		}

		ob_start();

		// Data are sent to the $path file.
		$data = (array) $data;

		require $path;

		return ob_get_clean();
	}
}
