<?php

namespace WPPluginDictator;

/**
 * Class Utils
 *
 * @package WPPluginDictator
 */
class Utils {

	/**
	 * Stores the paths to check for configs
	 * @var array $paths
	 */
	private static $paths = [];

	/**
	 * Returns the paths to the plugins.json files to build the general config for the project
	 *
	 * @return array
	 * @access public
	 */
	public static function get_config_paths() {

		if ( empty( self::$paths ) ) {
			self::$paths = [
				'wp_content' => self::build_filepath( WP_CONTENT_DIR ),
				'mu_plugins' => self::build_filepath( WPMU_PLUGIN_DIR ),
				'plugins' => self::build_filepath( WP_PLUGIN_DIR ),
				'parent_theme' => self::build_filepath( get_template_directory() ),
				'child_theme' => self::build_filepath( get_stylesheet_directory() ),
			];
		}

		/**
		 * Filters the list of file paths to plugins.json files that should be checked to build the initial configuration for the project
		 *
		 * @param array $paths The paths that are checked by default
		 * @return array
		 */
		return apply_filters( 'wp_plugin_dictator_config_paths', self::$paths );

	}

	/**
	 * Gets the filename of the config pile given a path
	 *
	 * @param string $path path to the parent dir containing the config file
	 *
	 * @return string
	 * @access public
	 */
	public static function get_config_file_name( $path ) {

		/**
		 * Returns the name of the json file to consume
		 *
		 * @param string $path Filepath to the parent directory containing the config file
		 *
		 * @return string
		 */
		return apply_filters( 'wp_plugin_dictator_config_file_name', 'plugins', $path );

	}

	/**
	 * Retrieve the full filepath to a plugin's config file
	 *
	 * @param string $plugin_slug The slug of the plugin to get a config for
	 * @param string $path        Parent directory of the plugin
	 *
	 * @return string
	 * @access public
	 */
	public static function get_config_for_plugin( $plugin_slug, $path = '' ) {

		$plugin_slug_parts = explode( '/', $plugin_slug );

		if ( empty( $plugin_slug_parts[0] ) ) {
			return '';
		}

		if ( ! empty( $path ) ) {
			$plugin_path = trailingslashit( self::build_custom_plugin_base_path( $path ) ) . $plugin_slug_parts[0];
		} else {
			$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $plugin_slug_parts[0];
		}

		return apply_filters( 'wp_plugin_dictator_plugin_config', self::build_filepath( $plugin_path ), $plugin_slug );

	}

	/**
	 * Builds the full filepath to the json config file
	 *
	 * @param string $path Full path to the plugin
	 *
	 * @return string
	 * @access private
	 */
	private static function build_filepath( $path ) {
		return trailingslashit( $path ) . self::get_config_file_name( $path ) . '.json';
	}

	/**
	 * Builds the plugin base path for a custom path plugin
	 *
	 * @param string $path Parent directory of the custom path plugin
	 *
	 * @return string
	 */
	public static function build_custom_plugin_base_path( $path ) {

		$root = '';

		if ( false === strpos( $path, ABSPATH ) ) {
			if ( '/' === substr( $path, 0, 1 ) ) {
				$root = untrailingslashit( ABSPATH );
			} else {
				$root = ABSPATH;
			}
		}

		return $root . $path;

	}

}
