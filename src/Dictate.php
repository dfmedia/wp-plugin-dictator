<?php

namespace WPPluginDictator;


class Dictate {

	private static $active_plugins = [];

	private static $deactive_plugins = [];

	private static $errors = [];

	private static $raw_plugins = [];

	private static $dictated_plugins = [];

	private static $custom_path_plugins = [];

	public function run() {

		$this->get_general_configs();
		$this->get_plugin_configs();

		add_filter( 'option_active_plugins', [ $this, 'dictate_required_plugins' ] );
		add_action( 'muplugins_loaded', [ $this, 'load_custom_path_plugins' ] );
		add_action( 'plugins_loaded', [ $this, 'load_custom_path_plugins' ] );
		add_action( 'after_setup_theme', [ $this, 'load_custom_path_plugins' ] );

	}

	public static function get() {
		return apply_filters( 'wp_plugin_dictator_plugin_list', self::$plugin_list );
	}

	public static function get_raw_plugins() {
		return self::$raw_plugins;
	}

	private static function set( $data ) {
		self::$plugin_list = $data;
	}

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
		self::set( $config );
		return $config;

	}

	private function get_plugin_configs() {

		$general_config = self::get();

		if ( ! empty( $general_config ) && is_array( $general_config ) ) {
			foreach ( $general_config as $plugin => $settings ) {
				$config_path = apply_filters( 'wp_plugin_dictator_plugin_config_file_path', Utils::get_config_for_plugin( $plugin ), $plugin, $settings );
				if ( file_exists( $config_path ) && 0 === validate_file( $config_path ) ) {
					$data = file_get_contents( $config_path );
					if ( ! empty( $data = json_decode( $data, true ) ) ) {
						$general_config = array_merge_recursive( $general_config, $data );
					}
				}
			}

			self::set( $general_config );

		}
	}

	public function dictate_required_plugins( $active_plugins ) {

		if ( empty( self::$dictated_plugins ) ) {

			self::$raw_plugins = $active_plugins;
			self::$active_plugins = $active_plugins;
			$config = self::get();
			if ( ! empty( $config ) && is_array( $config ) ) {

				$activate_plugins = $config['activate'];
				$deactivate = $config['deactivate'];

				if ( ! empty( $activate_plugins ) && is_array( $activate_plugins ) ) {

					foreach ( $activate_plugins as $plugin_slug => $settings ) {

						if ( empty( $settings['force'] ) || false === $settings['force'] ) {
							continue;
						}

						/**
						 * Load in any required plugins for this plugin before we load the current one,
						 * so the dependencies are setup
						 */
						if ( ! empty( $settings['require'] ) && is_array( $settings['require'] ) ) {

							foreach ( $settings['require'] as $required_plugin_slug => $required_plugin_settings ) {

								if ( ! array_key_exists( $required_plugin_slug, $active_plugins ) ) {

									/**
									 * If we can't find the plugin that this plugin depends on, add an error and don't force activate either plugin
									 */
									if ( ! file_exists( trailingslashit( WP_PLUGIN_DIR ) . $required_plugin_slug ) ) {

										self::$errors[ $plugin_slug ] = sprintf( __( 'Could not find plugin file: %$1s to load as a dependency for: %$2s', 'wp-plugin-dictator' ), $plugin_slug, $required_plugin_slug );
										continue 2;

									}
									$active_plugins[] = $required_plugin_slug;
								}

							}
						}

						/**
						 * Add the required plugin to the array
						 */
						if ( ! array_key_exists( $plugin_slug, $activate_plugins ) ) {
							$active_plugins[] = $plugin_slug;
						}

					}
				}

				/**
				 * Deactivate plugins in the "deactivate" array
				 */
				if ( ! empty( $deactivate ) && is_array( $deactivate ) ) {
					foreach ( $deactivate as $plugin_slug => $settings ) {

						self::$deactive_plugins[ $plugin_slug ] = ( empty( $settings['force'] ) || false === $settings['force'] ) ? 'recommended' : 'required';

						if ( empty( $settings['force'] ) || false === $settings['force'] ) {
							continue;
						}

						if ( array_key_exists( $plugin_slug, $active_plugins ) ) {
							unset( $active_plugins[ $plugin_slug ] );
						}

					}
				}

			}

			self::$dictated_plugins = $active_plugins;

		} else {
			$active_plugins = self::$dictated_plugins;
		}

		return $active_plugins;

	}

	protected function register_plugin( $plugin_slug, $settings, $dependant = '' ) {

		self::$active_plugins[ $plugin_slug ] = ( empty( $settings['force'] ) || false === $settings['force'] ) ? 'recommended' : 'required';

		if ( ! empty( $settings['path'] ) ) {
			$this->add_custom_path_plugin( $plugin_slug, $settings );
			return;
		}

		if ( false !== ( $key = array_search( $plugin_slug, self::$active_plugins, true ) ) ) {
			return;
		}

		if ( ! empty( $dependant ) ) {
			$dependant_key = array_search( $plugin_slug, self::$active_plugins, true );
			if ( false !== $dependant_key ) {
				array_splice( self::$active_plugins, $dependant_key, 0, $plugin_slug );
			}
		}

	}

	protected function deregister_plugin( $plugin_slug, $settings, $dependant = '' ) {

		self::$deactive_plugins[ $plugin_slug ] = ( empty( $settings['force'] ) || false === $settings['force'] ) ? 'recommended' : 'required';

	}

	protected function add_custom_path_plugin( $plugin_slug, $data, $dependant = '' ) {

		if ( ! empty( $data['priority'] ) && ! empty( $data['path'] ) ) {

			if ( '/' === substr( $data['path'], 0, 1 ) ) {
				$root = untrailingslashit( ABSPATH );
			} else {
				$root = ABSPATH;
			}

			self::$custom_path_plugins[ absint( $data['priority'] ) ][] = $root . trailingslashit( $data ) . $plugin_slug;

		}

	}

}
