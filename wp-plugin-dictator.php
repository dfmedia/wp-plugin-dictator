<?php
/**
 * Plugin Name:     WP Plugin Dictator
 * Plugin URI:      https://github.com/dfmedia/wp-plugin-dictator
 * Description:     Control which plugins should/should not be active in a given environment
 * Author:          Ryan Kanner, Digital First Media
 * Text Domain:     wp-plugin-dictator
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         WP_Plugin_Dictator
 */

// ensure the wp environment is loaded properly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPPluginDictator' ) ) {

	class WPPluginDictator {

		/**
		 * Stores the instance of the WPPluginDictator class
		 *
		 * @var Object $instance
		 * @access private
		 */
		private static $instance;

		/**
		 * Retrieves the instance of the WPPluginDictator class
		 *
		 * @access public
		 * @return Object|WPPluginDictator
		 * @throws exception
		 */
		public static function instance() {

			/**
			 * Make sure we are only instantiating the class once
			 */
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPPluginDictator ) ) {
				self::$instance = new WPPluginDictator();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->run();
			}

			/**
			 * Action that fires after we are done setting things up in the plugin. Extensions of
			 * this plugin should instantiate themselves on this hook to make sure the framework
			 * is available before they do anything.
			 *
			 * @param object $instance Instance of the current WPPluginDictator class
			 */
			do_action( 'wp_plugin_dictator_init', self::$instance );

			return self::$instance;

		}

		/**
		 * Sets up the constants for the plugin to use
		 *
		 * @access private
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WP_PLUGIN_DICTATOR_VERSION' ) ) {
				define( 'WP_PLUGIN_DICTATOR_VERSION', '1.0.0' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WP_PLUGIN_DICTATOR_PLUGIN_DIR' ) ) {
				define( 'WP_PLUGIN_DICTATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WP_PLUGIN_DICTATOR_PLUGIN_URL' ) ) {
				define( 'WP_PLUGIN_DICTATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WP_PLUGIN_DICTATOR_PLUGIN_FILE' ) ) {
				define( 'WP_PLUGIN_DICTATOR_PLUGIN_FILE', __FILE__ );
			}

		}

		/**
		 * Load the autoloaded files as well as the access functions
		 *
		 * @access private
		 * @return void
		 * @throws Exception
		 */
		private function includes() {

			if ( file_exists( WP_PLUGIN_DICTATOR_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
				require_once( WP_PLUGIN_DICTATOR_PLUGIN_DIR . 'vendor/autoload.php' );
			} else {
				throw new Exception( __( 'Could not find autoloader file to include all files', 'wp-plugin-dictator' ) );
			}

		}

		/**
		 * Instantiate the main classes we need for the plugin
		 *
		 * @access private
		 * @return void
		 */
		private function run() {

			/**
			 * Instantiate classes here
			 */
			$dictator = new \WPPluginDictator\Dictate();
			$dictator->run();

			add_action( 'init', function() {
				if ( is_admin() ) {
					$admin = new \WPPluginDictator\Admin();
					$admin->setup();
				}
			} );

			if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
				// Instantiate class for CLI commands here
				WP_CLI::add_command( 'plugin dictate', '\WPPluginDictator\CLI' );
			}

		}

	}

}

/**
 * Function to instantiate the WPPluginDictator class
 *
 * @return Object|WPPluginDictator Instance of the WPPluginDictator object
 * @access public
 * @throws Exception
 */
function wp_plugin_dictator_init() {

	if ( did_action( 'plugins_loaded' ) ) {
		throw new Exception( __( 'This plugin needs to be dropped in the wp-content/mu-plugins folder to work properly', 'wp-plugin-dictator' ) );
	}

	/**
	 * Returns an instance of the WPPluginDictator class
	 */
	return \WPPluginDictator::instance();

}

// Activate as early as possible, since this controls what plugins should/shouldn't be active, it needs to run before plugins are loaded.
if ( ! wp_installing() ) {
	wp_plugin_dictator_init();
}
