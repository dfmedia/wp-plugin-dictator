<?php

namespace WPPluginDictator;


class CLI extends \WP_CLI_Command {

	/**
	 * Contains the supported properties for displaying
	 *
	 * @var array $supported_props
	 * @access private
	 */
	private $supported_props = [];

	/**
	 * Contains the current active plugins on the site
	 *
	 * @var array $active_plugins
	 * @access private
	 */
	private static $active_plugins = [];

	/**
	 * Resets the active plugin for the current environment
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *      $wp plugin dictate reset
	 *      Starting plugin reset...
	 *	    Success: Plugins reset
	 *
	 */
	public function reset() {
		\WP_CLI::log( 'Starting plugin reset...' );
		Dictate::reset_plugins();
		\WP_CLI::success( 'Plugins reset' );
	}

	/**
	 * List out the built configs for the current environment
	 *
	 * ## OPTIONS
	 * [<plugin_slugs>...]
	 * : Optionally pass the plugin slugs you want the info for
	 *
	 * [--fields]
	 * : Fields to display data for
	 * ---
	 * default: all
	 * options:
	 *   - slug
	 *   - activate
	 *   - deactivate
	 *   - force
	 *   - path
	 *   - status
	 * ---
	 *
	 * [--format]
	 * : Render the output in a particular format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--<field>=<value>]
	 * : One or more fields to filter the list with
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin dictate list
	 *     +-----------------------------------------------+----------+------------+-------+----------+------------+
	 *     | slug                                          | activate | deactivate | force | path     | status     |
	 *     +-----------------------------------------------+----------+------------+-------+----------+------------+
	 *     | My-Plugin/my-plugin.php                       | yes      |            | yes   |          | active     |
	 *     | co-authors-plus/co-authors-plus.php           | yes      |            | yes   |          | active     |
	 *     | OAuth1/oauth-server.php                       | yes      |            | no    |          | active     |
	 *     | post-meta-inspector/post-meta-inspector.php   | yes      |            | no    |          | active     |
	 *     +-----------------------------------------------+----------+------------+-------+----------+------------+
	 *
	 *     $ wp plugin dictate list my-plugin/my-plugin.php
	 *     +-------------------------+----------+------------+-------+------+----------+
	 *     | slug                    | activate | deactivate | force | path | status   |
	 *     +-------------------------+----------+------------+-------+------+----------+
	 *     | my-plugin/my-plugin.php |          | yes        | no    |      | active   |
	 *     +-------------------------+----------+------------+-------+------+----------+
     *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function list( $args, $assoc_args ) {

		$plugins = Dictate::get_configs();
		$this->supported_props = [ 'slug', 'activate', 'deactivate', 'force', 'path', 'status' ];
		if ( empty( $plugins ) || ! is_array( $plugins ) ) {
			WP_CLI::error( 'No plugins found to list' );
		}

		$clean_plugins = [];
		if ( ! empty( $plugins['activate'] ) && is_array( $plugins['activate'] ) ) {
			foreach ( $plugins['activate'] as $plugin_slug => $settings ) {
				$clean_plugins[ $plugin_slug ] = $this->build_plugin_atts( $plugin_slug, $settings, 'yes', '' );
			}
		}

		if ( ! empty( $plugins['deactivate'] && is_array( $plugins['deactivate'] ) ) ) {
			foreach ( $plugins['deactivate'] as $plugin_slug => $settings ) {
				$clean_plugins[ $plugin_slug ] = $this->build_plugin_atts( $plugin_slug, $settings, '', 'yes' );
			}
		}

		if ( ! empty( $args ) && ! empty( $clean_plugins ) ) {
			$clean_plugins = array_intersect_key( $clean_plugins, array_flip( $args ) );
		}

		if ( ! empty( $assoc_args ) ) {
			$filter_args = array_intersect_key( $assoc_args, array_flip( $this->supported_props ) );
			$clean_plugins = wp_list_filter( $clean_plugins, $filter_args );
		}

		$this->format_output( $clean_plugins, $assoc_args );

	}

	/**
	 * Handles the formatting of output
	 *
	 * @param array $plugins The data to display
	 * @param array $assoc_args Args so we know how to display it
	 *
	 * @return void
	 * @access private
	 */
	private function format_output( $plugins, $assoc_args ) {
		if ( ! empty( $assoc_args['fields'] ) ) {
			if ( is_string( $assoc_args['fields'] ) ) {
				$fields = explode( ',', $assoc_args['fields'] );
			} else {
				$fields = $assoc_args['fields'];
			}
			$fields = array_intersect( $fields, $this->supported_props );
		} else {
			$fields = $this->supported_props;
		}
		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );
		$formatter->display_items( $plugins );
	}

	/**
	 * Builds the plugin attributes
	 *
	 * @param string $plugin_slug The name of the slug
	 * @param array  $settings    Settings for the plugin
	 * @param string $activate    Value of the 'active' key in the array
	 * @param string $deactivate  Value of the 'deactive' key in the array
	 *
	 * @return array
	 * @access private
	 */
	private function build_plugin_atts( $plugin_slug, $settings, $activate, $deactivate ) {
		return [
			'slug' => $plugin_slug,
			'activate' => $activate,
			'deactivate' => $deactivate,
			'force' => ( isset( $settings['force'] ) ) ? 'yes' : 'no',
			'path' => ( ! empty( $settings['path'] ) ) ? $settings['path'] : '',
			'status' => $this->get_plugin_status( $plugin_slug, $settings )
		];
	}

	/**
	 * Get the status of the plugin, active vs not active
	 *
	 * @param string $plugin_slug The name of the plugin
	 * @param array  $settings    Settings for the registered plugin
	 *
	 * @return string
	 * @access private
	 */
	private function get_plugin_status( $plugin_slug, $settings ) {

		if ( ! empty( $settings['path'] ) ) {
			return 'active';
		}

		if ( false !== array_search( $plugin_slug, $this->get_active_plugins(), true ) ) {
			return 'active';
		} else {
			return 'not active';
		}

	}

	/**
	 * Retrieve the active plugins
	 *
	 * @return array
	 * @access private
	 */
	private function get_active_plugins() {

		if ( empty( self::$active_plugins ) ) {
			self::$active_plugins = get_option( 'active_plugins' );
		}

		return self::$active_plugins;

	}


}
