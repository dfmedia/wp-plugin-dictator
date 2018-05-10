<?php

namespace WPPluginDictator;


class Dictate {

	private static $plugin_list = [];

	public function run() {

	}

	public static function get() {
		return apply_filters( 'wp_plugin_dictator_plugin_list', self::$plugin_list );
	}

	private function get_general_configs() {

		$config = [];
		$paths = Utils::get_config_paths();

		if ( is_array( $paths ) && ! empty( $paths ) ) {
			foreach ( $paths as $path ) {
				if ( 0 === validate_file( $path ) ) {
					$data = file_get_contents( $path );
					if ( ! empty( $data = json_decode( $data, true ) ) ) {
						array_merge( $config, $data );
					}
				}
			}
		}

		return apply_filters( 'wp_plugin_dictator_get_general_config', $config );

	}
}
