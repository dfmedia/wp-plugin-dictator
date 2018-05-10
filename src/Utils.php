<?php

namespace WPPluginDictator;


class Utils {

	private static $paths = [];

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

		return apply_filters( 'wp_plugin_dictator_config_paths', self::$paths );

	}

	public static function get_config_file_name( $path ) {
		return apply_filters( 'wp_plugin_dictator_config_file_name', 'plugins', $path );
	}

	public static function get_config_for_plugin( $plugin_name ) {
		return apply_filters( 'wp_plugin_dictator_plugin_config', self::build_filepath( trailingslashit( WP_PLUGIN_DIR ) . $plugin_name ), $plugin_name );
	}

	private static function build_filepath( $path ) {
		return trailingslashit( $path ) . self::get_config_file_name( $path ) . '.json';
	}

}
