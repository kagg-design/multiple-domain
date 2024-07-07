<?php
/**
 * Main class file.
 *
 * @package multiple-domain
 */

namespace MultipleDomain;

/**
 * Class Main.
 *
 * Contributors:
 *
 *  - Clay Allsopp <https://github.com/clayallsopp>
 *  - Alexander Nosov <https://github.com/cyberaleks>
 *  - João Faria <https://github.com/jffaria>
 *  - Raphael Stäbler <https://github.com/blazer82>
 *  - Tobias Keller <https://github.com/Tobias-Keller>
 *  - Maxime Granier <https://github.com/maxgranier>
 *
 * @author  Gustavo Straube <https://github.com/straube>
 */
class Main {
	/**
	 * Default HTTP port.
	 *
	 * @var integer
	 */
	private const PORT_HTTP = 80;

	/**
	 * Default HTTPS port.
	 *
	 * @var integer
	 */
	private const PORT_HTTPS = 443;

	/**
	 * The plugin instance.
	 *
	 * @since 0.8.4
	 *
	 * @var Main
	 */
	private static $instance;

	/**
	 * The current domain.
	 *
	 * This property's value also may include the host port
	 * when it's different from `80` (the default HTTP port) and `443` (the default HTTPS port).
	 *
	 * @since 0.2
	 *
	 * @var string
	 */
	private $domain;

	/**
	 * The original domain set in WordPress installation.
	 *
	 * This property's value also may include the host port
	 * when it's different from `80` (the default HTTP port) and `443` (the default HTTPS port).
	 *
	 * @since 0.3
	 *
	 * @var string
	 */
	private $original_domain;

	/**
	 * The list of available domains.
	 *
	 * This array holds all available domains as its keys.
	 * Each item in the array is also an array containing the following keys:
	 *
	 *  - `base`
	 *  - `lang`
	 *  - `protocol`
	 *
	 * @var string
	 */
	private $domains = [];

	/**
	 * Indicate whether the default ports should be ignored.
	 *
	 * This check is used when redirecting from a domain to another, for example.
	 *
	 * @since 0.11.0
	 *
	 * @var bool
	 */
	private $ignore_default_ports = false;

	/**
	 * Indicate whether a canonical link should be added to pages.
	 *
	 * @since 0.11.0
	 *
	 * @var bool
	 */
	private $add_canonical = false;

	/**
	 * Plugin activation tasks.
	 *
	 * The required plugin options are added to WordPress. We also make sure this plugin is the first loaded here.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	public static function activate(): void {
		add_option( 'multiple-domain-domains', [] );
		add_option( 'multiple-domain-ignore-default-ports', true );
		add_option( 'multiple-domain-add-canonical', false );

		self::load_first();
	}

	/**
	 * Update plugin loading order to load this plugin before any other plugin
	 * and make sure all plugins use the right domain replacements.
	 *
	 * @since 0.8.7
	 *
	 * @return void
	 */
	public static function load_first(): void {
		/*
		 * Relative path to this plugin. The array of active plugins has the plugin path as its keys.
		 * We'll use this path to move "Multiple Domain" to the first position in that array.
		 */
		$path    = str_replace( WP_PLUGIN_DIR . '/', '', MULTIPLE_DOMAIN_FILE );
		$plugins = get_option( 'active_plugins' );

		if ( empty( $plugins ) ) {
			return;
		}

		$key = array_search( $path, $plugins, true );

		if ( false !== $key ) {
			array_splice( $plugins, $key, 1 );
			array_unshift( $plugins, $path );
			update_option( 'active_plugins', $plugins );
		}
	}

	/**
	 * Get the single plugin instance.
	 *
	 * @since 0.8.4
	 *
	 * @return Main The plugin instance.
	 */
	public static function instance(): Main {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Init main plugin class.
	 *
	 * @return void
	 */
	public function init(): void {
		/**
		 * The current domain.
		 *
		 * Since this value is checked against plugin settings,
		 * it may not reflect the actual domain in `HTTP_HOST` element from `$_SERVER`.
		 * It also may include the host port when it's different from 80 (default HTTP port) or 443 (default HTTPS port).
		 *
		 * @since 1.0.2
		 */
		define( 'MULTIPLE_DOMAIN_DOMAIN', $this->domain );

		/**
		 * The original domain set in WordPress installation.
		 *
		 * @since 1.0.2
		 */
		define( 'MULTIPLE_DOMAIN_ORIGINAL_DOMAIN', $this->original_domain );

		/**
		 * The current domain language.
		 *
		 * This value is the language associated with the current domain in the plugin settings.
		 * No check is made to verify if it reflects the actual user language or locale.
		 * Also, notice this constant may be `null` when no language is set in the plugin config.
		 *
		 * @since 1.0.2
		 */
		define( 'MULTIPLE_DOMAIN_DOMAIN_LANG', $this->get_domain_lang() );
	}

	/**
	 * Create a new instance.
	 *
	 * Adds actions and filters required by the plugin.
	 */
	private function __construct() {
		$this->init_attributes();
		$this->hook_actions();
		$this->hook_filters();
		$this->add_shortcodes();

		new Settings( $this );
	}

	/**
	 * Initialize the class attributes.
	 *
	 * @since 0.8
	 *
	 * @return void
	 */
	private function init_attributes(): void {
		$this->ignore_default_ports = (bool) get_option( 'multiple-domain-ignore-default-ports' );
		$this->original_domain      = $this->get_domain_from_url( get_option( 'home' ), $this->ignore_default_ports );
		$this->domain               = $this->get_domain_from_request();
		$domains                    = (array) get_option( 'multiple-domain-domains' );
		$defaults                   = [
			'base'     => null,
			'lang'     => null,
			'protocol' => null,
		];

		$this->reset_domains();

		foreach ( $domains as $domain => $options ) {
			$options = wp_parse_args( $options, $defaults );

			$this->add_domain( $domain, $options['base'], $options['lang'], $options['protocol'] );
		}

		if ( ! array_key_exists( $this->domain, $this->domains ) ) {
			$this->domain = $this->original_domain;
		}

		$this->add_canonical = (bool) get_option( 'multiple-domain-add-canonical' );
	}

	/**
	 * Hook actions.
	 *
	 * @since 0.8
	 *
	 * @return void
	 */
	private function hook_actions(): void {
		add_action( 'init', [ $this, 'redirect' ] );
		add_action( 'wp_head', [ $this, 'add_href_lang_tags' ] );
		add_action( 'wp_head', [ $this, 'add_canonical_tag' ] );
		add_action( 'plugins_loaded', [ $this, 'loaded' ] );
		add_action( 'activated_plugin', [ self::class, 'load_first' ] );
		add_action( 'wpseo_register_extra_replacements', [ $this, 'register_yoast_vars' ] );
	}

	/**
	 * Hook filters.
	 *
	 * @since 0.8
	 *
	 * @return void
	 */
	private function hook_filters(): void {
		// Generic domain replacement.
		add_filter( 'content_url', [ $this, 'fix_url' ] );
		add_filter( 'option_siteurl', [ $this, 'fix_url' ] );
		add_filter( 'option_home', [ $this, 'fix_url' ] );
		add_filter( 'plugins_url', [ $this, 'fix_url' ] );
		add_filter( 'wp_get_attachment_url', [ $this, 'fix_url' ] );
		add_filter( 'get_the_guid', [ $this, 'fix_url' ] );

		// Specific domain replacement filters.
		add_filter( 'upload_dir', [ $this, 'fix_upload_dir' ] );
		add_filter( 'the_content', [ $this, 'fix_content_urls' ], 20 );
		add_filter( 'allowed_http_origins', [ $this, 'add_allowed_origins' ] );

		// Add body class based on domain.
		add_filter( 'body_class', [ $this, 'add_domain_body_class' ] );

		// Stop WP built in Canonical URL if this plugin has 'Add canonical links' enabled.
		add_filter( 'get_canonical_url', [ $this, 'get_canonical_url' ] );
	}

	/**
	 * Add plugin shortcodes.
	 *
	 * @since 0.8.5
	 *
	 * @return void
	 */
	private function add_shortcodes(): void {
		add_shortcode( 'multiple_domain', [ $this, 'shortcode' ] );
	}

	/**
	 * Return the current domain.
	 *
	 * Since this value is checked against plugin settings,
	 * it may not reflect the actual current domain in `HTTP_HOST` key from global `$_SERVER` var.
	 *
	 * Depending on the plugin settings, the domain also may include the host port
	 * when it's different from `80` (the default HTTP port) and `443` (the default HTTPS port).
	 *
	 * @since 0.2
	 *
	 * @return string|null The domain.
	 */
	public function get_domain(): ?string {
		return $this->domain;
	}

	/**
	 * Return original domain set in WordPress installation.
	 *
	 * Notice this method may return an unexpected value when running the site using the `server` command from wp-cli.
	 * That's because wp-cli changes the value of `site_url` and `home_url` options through a filter.
	 * Unfortunately, it's not possible to change this behavior.
	 *
	 * The domain also may include the host port
	 * when it's different from `80` (the default HTTP port) and `443` (the default HTTPS port).
	 *
	 * @since 0.3
	 *
	 * @return string The domain.
	 * @noinspection PhpUnused
	 */
	public function get_original_domain(): string {
		return $this->original_domain;
	}

	/**
	 * Return all domains available.
	 *
	 * The keys in the returned array are the domain name.
	 * Each item in the array is also an array containing the following keys:
	 *
	 *  - `base`
	 *  - `lang`
	 *  - `protocol`
	 *
	 * @since 0.11.0
	 *
	 * @return array The list of domains.
	 */
	public function get_domains() {
		return $this->domains;
	}

	/**
	 * Indicate whether the default ports (`80` or `443`) should be ignored.
	 *
	 * This check is used when redirecting from a domain to another, for example.
	 *
	 * @since 0.8.2
	 *
	 * @return bool A boolean indicating if the default port should be ignored.
	 */
	public function should_ignore_default_ports(): bool {
		return $this->ignore_default_ports;
	}

	/**
	 * Indicate whether the canonical tags should be added to the page.
	 *
	 * @since 0.11.0
	 *
	 * @return bool A boolean indicating if the default port should be ignored.
	 */
	public function should_add_canonical(): bool {
		return $this->add_canonical;
	}

	/**
	 * Get the base path associated to the given domain.
	 *
	 * If no domain is passed to the function, it'll return the base path for the current domain.
	 *
	 * Notice this function may return `null` when no base path is set for a given domain in the plugin config.
	 *
	 * @since 0.10.3
	 *
	 * @param string|null $domain The domain.
	 *
	 * @return string|null The base path.
	 */
	public function get_domain_base( ?string $domain = null ): ?string {
		return $this->get_domain_attribute( 'base', $domain );
	}

	/**
	 * Get the language associated to the given domain.
	 *
	 * If no domain is passed to the function, it'll return the language for the current domain.
	 *
	 * Notice this function may return `null` when no language is set for a given domain in the plugin config.
	 *
	 * @since 0.8
	 *
	 * @param string|null $domain The domain.
	 *
	 * @return string|null The language code.
	 */
	public function get_domain_lang( ?string $domain = null ): ?string {
		return $this->get_domain_attribute( 'lang', $domain );
	}

	/**
	 * Get the protocol option for the given domain.
	 *
	 * If no domain is passed to the function, it'll return the option for the current domain.
	 *
	 * The possible returned values are `http`, `https`, or `auto` (default).
	 * If no protocol is defined for a given domain, the default value will be returned.
	 *
	 * @since 0.10.0
	 *
	 * @param string|null $domain The domain.
	 *
	 * @return string The protocol option.
	 */
	public function get_domain_protocol( ?string $domain = null ): ?string {
		$protocol = $this->get_domain_attribute( 'protocol', $domain );

		return in_array( (string) $protocol, [ 'http', 'https' ], true ) ? $protocol : 'auto';
	}

	/**
	 * Reset the list of domains.
	 *
	 * In case the `$keepOriginal` param is `true`, which is the default,
	 * the list of domains will have only the original domain where WordPress was installed.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $keep_original Indicates whether the original domain should be kept.
	 *
	 * @return void
	 */
	public function reset_domains( bool $keep_original = true ): void {
		if ( ! $keep_original || empty( $this->original_domain ) ) {
			$this->domains = [];

			return;
		}

		$this->domains = [
			$this->original_domain => [
				'base'     => null,
				'lang'     => null,
				'protocol' => 'auto',
			],
		];
	}

	/**
	 * Add a new domain to the list of domains.
	 *
	 * Besides the `$domain` param, all others are optional.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $domain   The domain.
	 * @param string|null $base     The base path.
	 * @param string|null $lang     The language.
	 * @param string|null $protocol The protocol option. It can be `http`, `https` or `auto`.
	 *
	 * @return void
	 */
	public function add_domain( string $domain, ?string $base = null, ?string $lang = null, ?string $protocol = 'auto' ): void {
		$this->domains[ $domain ] = [
			'base'     => $base,
			'lang'     => $lang,
			'protocol' => $protocol,
		];
	}

	/**
	 * Store the current list of domains in the WordPress options.
	 *
	 * This can be used to persist changes made to the list of domains with `resetDomains` and `addDomain` methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function store_domains(): void {
		update_option( 'multiple-domain-domains', $this->domains );
	}

	/**
	 * When the current domain has a base URL restriction and the current request URI doesn't match it, redirects the user.
	 *
	 * @return void
	 */
	public function redirect(): void {
		/*
		 * Allow developers to create their own logic for redirection.
		 */
		do_action( 'multiple_domain_redirect', $this->domain );

		$base = (string) $this->get_domain_base();
		$uri  = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$base = ltrim( $base, '/' );
		$uri  = ltrim( $uri, '/' );

		if ( empty( $base ) || preg_match( '/^wp-[a-z]+(\.php|\/|$)/i', $uri ) ) {
			return;
		}

		if ( strpos( $uri, $base ) !== 0 ) {
			wp_safe_redirect( home_url( '/' . $base ) );
			exit;
		}
	}

	/**
	 * Replaces the domain in the given URL.
	 *
	 * The domain in the given URL is replaced with the current domain.
	 * If the URL contains `/wp-admin/` it'll be ignored when replacing the domain and returned as is.
	 *
	 * @since 0.10.0
	 *
	 * @param string|mixed $url The URL to fix.
	 *
	 * @return string The domain replaced URL.
	 */
	public function fix_url( $url ): string {
		$url = (string) $url;

		if ( ! preg_match( '/\/wp-admin\/?/', $url ) ) {
			$domain = $this->get_domain_from_url( $url );
			$url    = $this->replace_domain( $domain, $url );
		}

		return $url;
	}

	/**
	 * Replaces the domain in `upload_dir` filter used by `wp_upload_dir()`.
	 *
	 * The domain in the given `url` and `baseurl` is replaced by the current domain.
	 *
	 * @since 0.4
	 *
	 * @param array|mixed $uploads The array of `url`, `baseurl` and other properties.
	 *
	 * @return array The domain-replaced values.
	 */
	public function fix_upload_dir( $uploads ): array {
		$uploads = (array) $uploads;

		$uploads['url']     = $this->fix_url( $uploads['url'] );
		$uploads['baseurl'] = $this->fix_url( $uploads['baseurl'] );

		return $uploads;
	}

	/**
	 * Replaces the domain in post content.
	 *
	 * All occurrences of any of the available domains (i.e., all domains set in the plugin config)
	 * will be replaced with the current domain.
	 *
	 * @since 0.8
	 *
	 * @param string|mixed $content The content to fix.
	 *
	 * @return string The domain replaced content.
	 */
	public function fix_content_urls( $content ): string {
		$content = (string) $content;

		foreach ( array_keys( $this->domains ) as $domain ) {
			$content = $this->replace_domain( $domain, $content );
		}

		return $content;
	}

	/**
	 * Add all available domains to allowed origins.
	 *
	 * This filter is used to prevent CORS issues.
	 *
	 * @since 0.8
	 *
	 * @param array|mixed $origins The default list of allowed origins.
	 *
	 * @return array The updated list of allowed origins.
	 * @noinspection HttpUrlsUsage
	 */
	public function add_allowed_origins( $origins ): array {
		$origins = (array) $origins;

		foreach ( array_keys( $this->domains ) as $domain ) {
			$origins[] = 'https://' . $domain;
			$origins[] = 'http://' . $domain;
		}

		return array_values( array_unique( $origins ) );
	}

	/**
	 * Add the current domain to the body class in a sanitized version.
	 *
	 * If the current domain is `example.com`, the class added to the page body will be `multiple-domain-example-com`.
	 * Notice this filter only has effect when the `body_class()` function is added to the page's `<body> tag`.
	 *
	 * @since 0.9.0
	 *
	 * @param array|mixed $classes The initial list of body class names.
	 *
	 * @return array Updated list of body class names.
	 */
	public function add_domain_body_class( $classes ): array {
		$classes = (array) $classes;

		$classes[] = 'multiple-domain-' . preg_replace( '/[^a-z0-9]+/i', '-', $this->domain );

		return $classes;
	}

	/**
	 * Add `hreflang` links to head for SEO purpose.
	 *
	 * @since  0.4
	 *
	 * @return void
	 * @author Alexander Nosov <https://github.com/cyberaleks>
	 */
	public function add_href_lang_tags(): void {
		/**
		 * The WP class instance.
		 */
		global $wp;

		$uri              = trailingslashit( '/' . ltrim( add_query_arg( [], $wp->request ), '/' ) );
		$current_protocol = $this->get_current_protocol();

		foreach ( array_keys( $this->domains ) as $domain ) {
			$protocol = $this->get_domain_protocol( $domain );

			if ( 'auto' === $protocol ) {
				$protocol = $current_protocol;
			}

			$protocol .= '://';

			$lang = $this->get_domain_lang( $domain );

			if ( ! empty( $lang ) ) {
				$this->output_href_lang_tag( $protocol . $domain . $uri, $lang );
			}

			if ( $domain === $this->original_domain ) {
				$this->output_href_lang_tag( $protocol . $domain . $uri );
			}
		}
	}

	/**
	 * Add `canonical` links to head for SEO purpose.
	 *
	 * @since 0.11.0
	 *
	 * @return void
	 */
	public function add_canonical_tag(): void {
		if ( ! $this->should_add_canonical() ) {
			return;
		}

		/**
		 * The WP class instance.
		 */
		global $wp;

		$uri              = home_url( add_query_arg( [], $wp->request ), 'relative' ) . '/';
		$current_protocol = $this->get_current_protocol();

		$protocol = $this->get_domain_protocol( $this->original_domain );

		if ( 'auto' === $protocol ) {
			$protocol = $current_protocol;
		}

		$protocol .= '://';

		$this->output_canonical_tag( $protocol . $this->original_domain . $uri );
	}

	/**
	 * This shortcode simply returns the current domain.
	 *
	 * @since 0.8.5
	 *
	 * @return string The current domain.
	 */
	public function shortcode(): string {
		return $this->domain;
	}

	/**
	 * Load text domain when the plugin is loaded.
	 *
	 * @since 0.8.6
	 *
	 * @return void
	 */
	public function loaded(): void {
		$path = dirname( plugin_basename( MULTIPLE_DOMAIN_FILE ) ) . '/languages/';

		load_plugin_textdomain( 'multiple-domain', false, $path );
	}

	/**
	 * Register vars to be used as text replacements in Yoast tags.
	 *
	 * @since 0.11.0
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function register_yoast_vars(): void {
		wpseo_register_var_replacement(
			'%%multiple_domain%%',
			[ $this, 'get_domain' ],
			'advanced',
			__( 'The current domain from Multiple Domain', 'multiple-domain' )
		);
	}

	/**
	 * Get the current domain via request headers parsing.
	 *
	 * @since 0.8.7
	 *
	 * @return string|null The current domain.
	 */
	private function get_domain_from_request(): ?string {
		$domain = $this->get_host_header();

		if ( empty( $domain ) ) {
			return null;
		}

		$matches = [];

		if ( preg_match( '/^(.*):(\d+)$/', $domain, $matches ) && $this->is_default_port( $matches[2] ) ) {
			$domain = $matches[1];
		}

		return $domain;
	}

	/**
	 * Get the `Host` HTTP header value.
	 *
	 * To make it compatible with proxies, this function first tries to get the value from `X-Host` header and, then,
	 * falls back to the regular `Host` header.
	 *
	 * It returns `null` in case both headers are empty.
	 *
	 * @since 0.8.7
	 *
	 * @return string|null The HTTP `Host` header value.
	 */
	private function get_host_header(): ?string {
		if ( ! empty( $_SERVER['HTTP_X_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HOST'] ) );
		}

		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}

		return null;
	}

	/**
	 * Get the current URL protocol based on server settings.
	 *
	 * The possible returned values are `http` and `https`.
	 *
	 * @return string The protocol.
	 */
	private function get_current_protocol(): string {
		return empty( $_SERVER['HTTPS'] ) || 'off' === $_SERVER['HTTPS'] ? 'http' : 'https';
	}

	/**
	 * Get an attribute by its name for the given domain.
	 *
	 * If no domain is passed to the function, it'll return the attribute value
	 * for the current domain.
	 *
	 * Notice this function may return `null` when the attribute is not set in
	 * the plugin config or doesn't exist.
	 *
	 * @since 0.10.0
	 *
	 * @param string      $name   The attribute name.
	 * @param string|null $domain The domain.
	 *
	 * @return string|null The attribute value.
	 */
	private function get_domain_attribute( string $name, ?string $domain = null ): ?string {
		if ( empty( $domain ) ) {
			$domain = $this->domain;
		}

		$attribute = null;

		if ( ! empty( $this->domains[ $domain ][ $name ] ) ) {
			$attribute = $this->domains[ $domain ][ $name ];
		}

		return $attribute;
	}

	/**
	 * Replaces the domain.
	 *
	 * All occurrences of the given domain will be replaced with the current domain in the content.
	 *
	 * The protocol may also be replaced following the protocol settings defined in the plugin config for the current domain.
	 *
	 * @param string $domain  The domain to replace.
	 * @param string $content The content that will have the domain replaced.
	 *
	 * @return string The domain-replaced content.
	 */
	private function replace_domain( string $domain, string $content ): string {
		if ( MULTIPLE_DOMAIN_LOW_MEMORY ) {
			return $this->replace_domain_using_less_memory( $domain, $content );
		}

		if ( array_key_exists( $domain, $this->domains ) ) {
			$regex    = '/(https?):\/\/' . preg_quote( $domain, '/' ) . '(?![a-z0-9.\-:])/i';
			$protocol = $this->get_domain_protocol( $this->domain );
			$replace  = ( 'auto' === $protocol ? '${1}' : $protocol ) . '://' . $this->domain;
			$content  = (string) preg_replace( $regex, $replace, $content );
		}

		return $content;
	}

	/**
	 * Replaces the domain using less memory.
	 *
	 * This function does the same as `replace_domain`.
	 * However, it uses `mb_eregi_replace` instead of `preg_replace` for less memory consumption.
	 * On the other hand, it takes more time to execute.
	 *
	 * @since 1.0.2
	 *
	 * @param string $domain  The domain to replace.
	 * @param string $content The content that will have the domain replaced.
	 *
	 * @return string The domain-replaced content.
	 */
	private function replace_domain_using_less_memory( string $domain, string $content ): string {
		if ( array_key_exists( $domain, $this->domains ) ) {
			$regex    = '(https?):\/\/' . preg_quote( $domain, '/' ) . '(?![a-z0-9.\-:])';
			$protocol = $this->get_domain_protocol( $this->domain );
			$replace  = ( 'auto' === $protocol ? '\\1' : $protocol ) . '://' . $this->domain;
			$content  = (string) mb_eregi_replace( $regex, $replace, $content );
		}

		return $content;
	}

	/**
	 * Parses the given URL to return only its domain.
	 *
	 * The server port may be included in the returning value depending on its
	 * number and plugin settings.
	 *
	 * @since 0.2
	 *
	 * @param string $url                  The URL to parse.
	 * @param bool   $ignore_default_ports If `true` is passed to this value,
	 *                                   the default HTTP or HTTPS port will be ignored
	 *                                   even if it's present in the URL.
	 *
	 * @return string The domain.
	 */
	private function get_domain_from_url( string $url, bool $ignore_default_ports = false ): string {
		$parts  = wp_parse_url( $url );
		$domain = $parts['host'];

		if ( ! empty( $parts['port'] ) && ! ( $ignore_default_ports && $this->is_default_port( $parts['port'] ) ) ) {
			$domain .= ':' . $parts['port'];
		}

		return $domain;
	}

	/**
	 * Checks if the given port is a default HTTP (`80`) or HTTPS (`443`) port.
	 *
	 * @since 0.2
	 *
	 * @param string $port The port to check.
	 *
	 * @return bool Indicates if the port is a default one.
	 */
	private function is_default_port( string $port ): bool {
		$port_num = (int) $port;

		return self::PORT_HTTP === $port_num || self::PORT_HTTPS === $port_num;
	}

	/**
	 * Prints a `hreflang` link tag.
	 *
	 * @since 0.5
	 *
	 * @param string $url  The URL to be set into `href` attribute.
	 * @param string $lang The language code to be set into `hreflang` attribute. Defaults to `x-default`.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	private function output_href_lang_tag( string $url, string $lang = 'x-default' ): void {
		$url  = htmlentities( $url );
		$lang = str_replace( '_', '-', $lang );

		printf( '<link rel="alternate" href="%s" hreflang="%s" />', esc_url( $url ), esc_attr( $lang ) );
	}

	/**
	 * Prints a `canonical` link tag.
	 *
	 * @since 0.11.0
	 *
	 * @param string $url The canonical URL to be set into `href` attribute.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	private function output_canonical_tag( string $url ): void {
		$url = htmlentities( $url );

		printf( '<link rel="canonical" href="%s" />', esc_url( $url ) );
	}

	/**
	 * Filter override WordPress built-in canonical tag generation if using the plugin's canonical tag feature.
	 *
	 * @param string|mixed $url URL.
	 *
	 * @return string
	 */
	public function get_canonical_url( $url ): string {
		$url = (string) $url;

		// If *not* using the plugin's canonical tags, then return this URL. Otherwise, don't.
		if ( ! $this->should_add_canonical() ) {
			return $url;
		}

		return '';
	}
}
