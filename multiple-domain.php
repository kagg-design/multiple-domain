<?php
/**
 * Plugin Multiple Domain
 *
 * @package     multiple-domain
 * @author      goinput, kaggdesign
 * @license     GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name: Multiple Domain
 * Plugin URI:  https://github.com/straube/multiple-domain
 * Description: This plugin allows you to have multiple domains in a single WordPress installation and enables custom redirects for each domain.
 * Version:     2.0.0
 * Author:      goINPUT IT Solutions
 * Author URI:  http://goinput.de
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multiple-domain
 * Domain Path: /languages/
 */

use MultipleDomain\Main;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Plugin version.
 *
 * @since 2.0.0
 */
const MULTIPLE_DOMAIN_VERSION = '2.0.0';

/**
 * Path to the plugin dir.
 *
 * @since 2.0.0
 */
const MULTIPLE_DOMAIN_PATH = __DIR__;

/**
 * Plugin dir url.
 *
 * @since 2.0.0
 */
define( 'MULTIPLE_DOMAIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 *
 * @since 2.0.0
 */
const MULTIPLE_DOMAIN_FILE = __FILE__;

if ( ! defined( 'MULTIPLE_DOMAIN_LOW_MEMORY' ) ) {
	/**
	 * The low memory option.
	 *
	 * This option may be used where the site is throwing "allowed memory
	 * exhausted" errors. It will reduce the memory usage in domain replacements
	 * with the downside of a higher execution time.
	 *
	 * @since 1.0.2
	 */
	define( 'MULTIPLE_DOMAIN_LOW_MEMORY', false );
}

require_once MULTIPLE_DOMAIN_PATH . '/Main.php';
require_once MULTIPLE_DOMAIN_PATH . '/Settings.php';

/*
 * Register the activation method.
 */
register_activation_hook( MULTIPLE_DOMAIN_FILE, [ Main::class, 'activate' ] );

/**
 * Get Multiple Domain Main class instance.
 *
 * @since 2.0.0
 *
 * @return Main
 */
function multiple_domain(): Main {
	return Main::instance();
}

multiple_domain()->init();
