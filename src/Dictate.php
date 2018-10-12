<?php

namespace WPPluginDictator;

/**
 * Class Dictate
 *
 * @package WPPluginDictator
 */
class Dictate {

	/**
	 * Contains the default priority
	 *
	 * @var int $default_priority
	 * @access private
	 */
	private $default_priority = 1;

	/**
	 * Contains the built configs combined from all the plugins.json files
	 *
	 * @var array $configs
	 * @access private
	 */
	private static $configs = [];

	/**
	 * Contains any errors that may happen while this setup runs
	 *
	 * @var array $errors
	 * @access private
	 */
	private static $errors = [];

	/**
	 * Contains all of the plugins that need to be loaded from outside of the plugins directory
	 *
	 * @var array $custom_plugin_paths
	 * @access private
	 */
	private static $custom_plugin_paths = [];

	/**
	 * Contains all of the plugins that should be forced on
	 *
	 * @var array $required_plugins
	 * @access private
	 */
	private static $required_plugins = [];

	/**
	 * Contains all of the plugins that are recommended for the current environment
	 *
	 * @var array $recommended_plugins
	 * @access private
	 */
	private static $recommended_plugins = [];

	/**
	 * The array of plugins that have been registered as deactivated
	 *
	 * @var array $deactivated_plugins
	 * @access private
	 */
	private static $deactivated_plugins = [];

	/**
	 * The final result of what should come back from get_option( 'active_plugins' );
	 *
	 * @var array $dictated_plugins
	 * @access private
	 */
	private static $dictated_plugins = [];

	/**
	 * Builds the configs, and sets up callbacks for hooks & filters
	 * ᕕ( ՞ ᗜ ՞ )ᕗ
	 *
	 * @return void
	 * @access public
	 */
	public function run() {

		/**
		 * Sets the default priority of custom path plugins
		 * @return int
		 */
		$this->default_priority = apply_filters( 'wp_plugin_dictator_default_custom_path_priority', 1 );

		$this->get_general_configs();
		$this->get_plugin_configs();
		$this->dictate_plugin_loading();

		/**
		 * Fires after the initial plugin configs have been consumed and built. At this point you
		 * can add your own plugins to require or recommend for the environment.
		 */
		do_action( 'wp_plugin_dictator_after_default_configs_built' );

		/**
		 * Where the magic happens
		 */
		add_filter( 'option_active_plugins', [ $this, 'dictate_required_plugins' ] );

		/**
		 * Hack for loading plugins early on WordPress VIP.
		 */
		if ( did_action( 'muplugins_loaded' ) && ! did_action( 'plugins_loaded' ) ) {
			$this->load_custom_path_plugins( true, 0 );
		}

		add_action( 'muplugins_loaded', [ $this, 'load_custom_path_plugins' ] );
		add_action( 'plugins_loaded', [ $this, 'load_custom_path_plugins' ] );
		add_action( 'after_setup_theme', [ $this, 'load_custom_path_plugins' ] );

	}

	/**
	 * Returns the array of merged & built configs
	 *
	 * @return array
	 * @access public
	 */
	public static function get_configs() {
		return self::$configs;
	}

	/**
	 * Sets the config data to the static config variable
	 *
	 * @param array $data The data to set to the configs variable
	 * @access public
	 */
	public static function set_configs( $data ) {
		self::$configs = $data;
	}

	/**
	 * Retrieves any errors that happen when building configs / setting plugins for display
	 *
	 * @return array
	 * @access public
	 */
	public static function get_all_errors() {
		return self::$errors;
	}

	/**
	 * Gets the list of plugins that are recommended for the environment
	 *
	 * @return array
	 * @access public
	 */
	public static function get_recommended_plugins() {
		return self::$recommended_plugins;
	}

	/**
	 * Gets the list of plugins that are required for the environment
	 *
	 * @return array
	 * @access public
	 */
	public static function get_required_plugins() {
		return self::$required_plugins;
	}

	/**
	 * Gets the list of plugins that have been marked as "deactivate" for the environment
	 *
	 * @param bool|string $slice Which deactivated array you want to retrieve, recommended or required
	 *
	 * @return array
	 * @access public
	 */
	public static function get_deactivated_plugins( $slice = false ) {
		if ( ! empty( $slice ) ) {
			return ( ! empty( self::$deactivated_plugins[ $slice ] ) ) ? self::$deactivated_plugins[ $slice ] : [];
		} else {
			return self::$deactivated_plugins;
		}
	}

	/**
	 * Return the array of dictated plugins
	 *
	 * @return array
	 * @access public
	 */
	public static function get_dictated_plugins() {
		return self::$dictated_plugins;
	}

	/**
	 * Returns an array of the custom path plugins
	 *
	 * @param bool $flat     Return as a single flat array or as a multidimensional array
	 * @param int  $priority The slice of the array to return by registered priority
	 *
	 * @return array|mixed
	 */
	public static function get_custom_path_plugins( $flat = false, $priority = 0 ) {

		if ( false === $flat ) {
			return ( ! empty( self::$custom_plugin_paths[ $priority ] ) ) ? self::$custom_plugin_paths[ $priority ] : [];
		} else {
			$clean_plugins = [];
			if ( ! empty( self::$custom_plugin_paths ) && is_array( self::$custom_plugin_paths ) ) {
				foreach ( self::$custom_plugin_paths as $priority => $plugins ) {
					if ( ! empty( $plugins ) && is_array( $plugins ) ) {
						foreach ( $plugins as $plugin_slug => $path ) {
							$clean_plugins[ $plugin_slug ] = [
								'priority' => $priority,
								'path' => $path,
							];
						}
					}
				}
			}
			return $clean_plugins;
		}
	}

	/**
	 * Build a config based off of the plugins.json files in general places like the wp-content folder
	 *
	 * @return void
	 * @access private
	 */
	private function get_general_configs() {

		$config = [];
		$paths = Utils::get_config_paths();

		if ( is_array( $paths ) && ! empty( $paths ) ) {
			foreach ( $paths as $path ) {
				if ( file_exists( $path ) && 0 === validate_file( $path ) ) {
					$data = file_get_contents( $path );
					if ( ! empty( $data = json_decode( $data, true ) ) ) {
						$config = array_merge_recursive( $config, $data );
					}
				}
			}
		}

		$config = apply_filters( 'wp_plugin_dictator_get_general_config', $config );
		self::set_configs( $config );

	}

	/**
	 * Get configs from each of the individual plugins already registered from the general configs
	 *
	 * @return void
	 * @access private
	 */
	private function get_plugin_configs() {

		$general_config = self::get_configs();

		if ( ! empty( $general_config['activate'] ) && is_array( $general_config['activate'] ) ) {
			foreach ( $general_config['activate'] as $plugin_slug => $settings ) {
				$path = ( ! empty( $settings['path'] ) ) ? $settings['path'] : '';
				$config_path = apply_filters( 'wp_plugin_dictator_plugin_config_file_path', Utils::get_config_for_plugin( $plugin_slug, $path ) );
				if ( file_exists( $config_path ) && 0 === validate_file( $config_path ) ) {
					$data = file_get_contents( $config_path );
					if ( ! empty( $data = json_decode( $data, true ) ) ) {
						$general_config = array_merge_recursive( $general_config, $data );
					}
				}
			}

			self::set_configs( $general_config );

		}

	}

	/**
	 * Defines which plugins should/shouldn't be active for the environment
	 *
	 * @access private
	 * @return void
	 */
	private function dictate_plugin_loading() {

		$plugins = self::get_configs();

		/**
		 * Loop through the plugins in the activate array to figure out which ones should be force activated
		 */
		if ( ! empty( $plugins['activate'] ) && is_array( $plugins['activate'] ) ) {
			foreach ( $plugins['activate'] as $plugin_slug => $settings ) {

				if ( ! empty( $settings['path'] ) ) {
					$priority = ( ! empty( $settings['priority'] ) ) ? absint( $settings['priority'] ) : $this->default_priority;
					self::add_custom_path_plugin( $plugin_slug, $settings['path'], $priority );
				} else {
					self::register_plugin( $plugin_slug, $settings );
				}

			}

		}

		/**
		 * Loop through plugins in the deactivate array and remove any of the plugins that may be
		 * registered as active already. Also force deactivate them so they can't be activated
		 * through the admin or anything.
		 */
		if ( ! empty( $plugins['deactivate'] ) && is_array( $plugins['deactivate'] ) ) {
			foreach ( $plugins['deactivate'] as $plugin_slug => $settings ) {

				if ( ! empty( $settings['path'] ) ) {
					$priority = ( ! empty( $settings['priority'] ) ) ? absint( $settings['priority'] ) : $this->default_priority;
					self::remove_custom_path_plugin( $plugin_slug, $priority );
				} else {
					$force = ( isset( $settings['force'] ) ) ? (bool) $settings['force'] : false;
					self::deactivate_plugin( $plugin_slug, $force );
				}
			}
		}

	}

	/**
	 * Dictates which plugins should be force activated/deactivated
	 *
	 * @param array $plugins The plugins that are active in the database
	 *
	 * @return array
	 * @access public
	 */
	public function dictate_required_plugins( $plugins ) {

		if ( empty( self::$dictated_plugins ) ) {

			$dictated_plugins = array_unique( array_merge( $plugins, self::$required_plugins ) );

			if ( ! empty( self::$deactivated_plugins ) && is_array( self::$deactivated_plugins ) ) {
				$dictated_plugins = array_diff( $dictated_plugins, self::$deactivated_plugins );
			}

			self::$dictated_plugins = $dictated_plugins;

		}

		return self::$dictated_plugins;

	}

	/**
	 * Registers a plugin to be activated when we go to load plugins
	 *
	 * @param string $plugin_slug Slug of the plugin to register
	 * @param array  $settings    Plugin settings
	 *
	 * @access public
	 * @return void
	 */
	public static function register_plugin( $plugin_slug, $settings ) {

		if ( ! empty( $settings['force'] ) && true === $settings['force'] ) {
			if ( false === array_search( $plugin_slug, self::$required_plugins, true ) ) {
				self::$required_plugins[] = $plugin_slug;
			}
		} else {
			if ( false === array_search( $plugin_slug, self::$recommended_plugins, true ) ) {
				self::$recommended_plugins[] = $plugin_slug;
			}
		}
	}

	/**
	 * Dictate which plugins should be force deactivated
	 *
	 * @param string $plugin_slug Slug for the plugin to deactivate
	 * @param bool   $force       Whether or not the plugin should be force deactivated or not
	 *
	 * @return void
	 * @access public
	 */
	public static function deactivate_plugin( $plugin_slug, $force = false ) {

		if ( true === $force ) {
			if ( empty( self::$deactivated_plugins['required'] ) || false === array_search( $plugin_slug, self::$deactivated_plugins['required'], true ) ) {
				self::$deactivated_plugins['required'][] = $plugin_slug;
			}
		} else {
			if ( empty( self::$deactivated_plugins['recommended'] ) || false === array_search( $plugin_slug, self::$deactivated_plugins['recommended'], true ) ) {
				self::$deactivated_plugins['recommended'][] = $plugin_slug;
			}
		}

		if ( false !== ( $key = array_search( $plugin_slug, self::$required_plugins, true ) ) ) {
			unset( self::$required_plugins[ $key ] );
		} else if ( false !== ( $key = array_search( $plugin_slug, self::$recommended_plugins, true ) ) ) {
			unset( self::$recommended_plugins[ $key ] );
		}

	}

	/**
	 * Handles registering plugins that have a custom path
	 *
	 * @param string $plugin_slug Slug of the plugin to register
	 * @param string $path        Path of the parent directory (excluding plugin slug) where the
	 *                            plugin is located
	 * @param int    $priority    Priority number of when the plugin should be loaded. 1, 2, or 3.
	 *
	 * @return void
	 * @access public
	 */
	public static function add_custom_path_plugin( $plugin_slug, $path, $priority ) {

		$folder_path = Utils::build_custom_plugin_base_path( $path );
		self::$custom_plugin_paths[ $priority ][ $plugin_slug ] = trailingslashit( $folder_path ) . $plugin_slug;

	}

	/**
	 * Remove a custom path plugin from being loaded
	 *
	 * @param string $plugin_slug Slug of the plugin to remove
	 * @param int    $priority    Priority number of when the plugin should be loaded. 1, 2, or 3.
	 *
	 * @access public
	 * @return void
	 */
	public static function remove_custom_path_plugin( $plugin_slug, $priority ) {
		if ( array_key_exists( $plugin_slug, self::$custom_plugin_paths[ $priority ] ) ) {
			unset( self::$custom_plugin_paths[ $priority ][ $plugin_slug ] );
		}
	}

	/**
	 * @TODO: Sort out what to do with this...
	 *
	 * @param string $plugin_slug The slug of the plugin that had an issue
	 * @param string $message The error message
	 */
	public function add_error( $plugin_slug, $message ) {
		self::$errors[ $plugin_slug ] = $message;
	}

	/**
	 * Handles the loading of the custom path plugins.
	 *
	 * @param bool $now   Whether or not to run the loading right now
	 * @param int  $slice The priority of the plugins to load
	 *
	 * @return void
	 * @access public
	 */
	public function load_custom_path_plugins( $now = false, $slice = 0 ) {

		if ( false === $now ) {
			switch ( current_action() ) {
				case 'muplugins_loaded':
					$slice = 0;
					break;
				case 'plugins_loaded':
					$slice = 1;
					break;
				case 'after_setup_theme':
					$slice = 2;
					break;
				default:
					$slice = false;
					break;
			}
		}

		if ( false === $slice ) {
			return;
		}

		$plugins_to_load = ( ! empty( self::$custom_plugin_paths[ $slice ] ) ) ? self::$custom_plugin_paths[ $slice ] : [];

		if ( ! empty( $plugins_to_load ) && is_array( $plugins_to_load ) ) {
			foreach ( $plugins_to_load as $plugin_to_load ) {
				if ( file_exists( $plugin_to_load ) && 0 === validate_file( $plugin_to_load ) ) {
					require_once( $plugin_to_load );
				}
			}
		}

	}

	/**
	 * Method to reset the plugins to what is recommended for the environment
	 *
	 * @return void
	 * @access public
	 */
	public static function reset_plugins() {

		$recommended_plugins = ( ! empty( self::get_recommended_plugins() ) ) ? self::get_recommended_plugins() : [];
		$required_plugins = ( ! empty( self::get_required_plugins() ) ) ? self::get_required_plugins() : [];
		$active_plugins = get_option( 'active_plugins' );

		$missing_plugins = array_diff( $recommended_plugins, $active_plugins );
		$extra_plugins = array_diff( $active_plugins, array_merge( $recommended_plugins, $required_plugins ) );

		if ( ! empty( $missing_plugins ) ) {
			$active_plugins = array_merge( $active_plugins, $missing_plugins );
			foreach ( $missing_plugins as $missing_plugin ) {
				do_action( 'activate_' . $missing_plugin );
			}
		}

		if ( ! empty( $extra_plugins ) ) {
			$active_plugins = array_diff( $active_plugins, $extra_plugins );
			foreach ( $extra_plugins as $extra_plugin ) {
				do_action( 'deactivate_' . $extra_plugin );
			}
		}

		update_option( 'active_plugins', $active_plugins );

	}

}
