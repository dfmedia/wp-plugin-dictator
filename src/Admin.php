<?php

namespace WPPluginDictator;

/**
 * Class Admin
 *
 * @package WPPluginDictator
 */
class Admin {

	/**
	 * Stores the link actions to remove on the plugin list page if the plugin is force activated or deactivated
	 *
	 * @var array $actions_to_remove
	 * @access private
	 */
	private $actions_to_remove;

	/**
	 * Sets up all of the callbacks on filters and actions
	 *
	 * @return void
	 * @access public
	 */
	public function setup() {

		/**
		 * Filter to define which plugin action links to remove for required plugins
		 * @return array
		 */
		$this->actions_to_remove = apply_filters( 'wp_plugin_dictator_actions_to_remove', [ 'delete', 'deactivate', 'activate' ] );
		add_filter( 'plugin_action_links', [ $this, 'modify_plugin_links' ], 10, 2 );
		add_filter( 'map_meta_cap', [ $this, 'filter_plugin_meta_caps' ], 0, 4 );
		add_action( 'admin_notices', [ $this, 'plugin_mismatch_notice' ] );
		add_action( 'load-plugins.php', [ $this, 'dictate_recommended_plugins' ] );
		add_filter( 'views_plugins', [ $this, 'add_custom_path_tab' ] );
		add_filter( 'all_plugins', [ $this, 'filter_plugin_list' ] );

	}

	/**
	 * Modify's the action links for registered plugins
	 *
	 * @param array  $actions         The array of action links for the plugin
	 * @param string $plugin_basename The slug of the plugin
	 *
	 * @return array
	 * @access public
	 */
	public function modify_plugin_links( $actions, $plugin_basename ) {

		/**
		 * Remove actions for required plugins, and add a message about the plugin being enabled by code
		 */
		if ( in_array( $plugin_basename, Dictate::get_required_plugins(), true ) ) {

			if ( ! empty( $this->actions_to_remove ) && is_array( $this->actions_to_remove ) ) {
				foreach ( $this->actions_to_remove as $action_name ) {
					unset( $actions[ $action_name ] );
				}
			}

			$actions['required'] = '<span style="font-weight: bold;">' . __( 'Enabled by code', 'wp-plugin-dictator' )  . '</span>';

		} elseif ( in_array( $plugin_basename, Dictate::get_recommended_plugins() ) ) {

			/**
			 * Add text about being a recommended plugin
			 */
			$actions['recommended'] = __( 'Recommended Plugin', 'wp-plugin-dictator' );

		}

		if ( in_array( $plugin_basename, Dictate::get_deactivated_plugins( 'required' ) ) ) {

			/**
			 * Don't allow plugins to be activated if they are force deactivated
			 */
			unset( $actions['activate'] );
			unset( $actions['recommended'] );
			unset( $actions['required'] );
			$actions['deactivated'] = __( 'This plugin has been deactivated by code', 'wp-plugin-dictator' );

		}

		if ( ! empty( $_GET['plugin_status'] ) && 'custom-path' === sanitize_text_field( $_GET['plugin_status'] ) ) {
			$actions = [ 'required' => '<span style="font-weight: bold;">' . __( 'Enabled by code', 'wp-plugin-dictator' )  . '</span>' ];
		}

		return $actions;

	}

	/**
	 * Filters the capabilities for activating and deactivating plugins.
	 *
	 * This method prevents access to those capabilities for plugins that are deemed required
	 *
	 * @param array  $caps    List of primitive capabilities resolved to in `map_meta_cap()`.
	 * @param string $cap     Meta capability actually being checked.
	 * @param int    $user_id User ID for which the capability is being checked.
	 * @param array  $args    Additional arguments passed to the capability check.
	 *
	 * @return array Filtered value of $caps.
	 * @access public
	 */
	public function filter_plugin_meta_caps( array $caps, $cap, $user_id, array $args ) {
		switch ( $cap ) {
			case 'activate_plugin':
				if ( in_array( $args[0], Dictate::get_deactivated_plugins( 'required' ), true ) ) {
					$caps[] = 'do_not_allow';
				}
			case 'deactivate_plugin':
			case 'delete_plugin':
				if ( in_array( $args[0], Dictate::get_required_plugins(), true ) ) {
					$caps[] = 'do_not_allow';
				}
				break;
			/*
			 * Core does not actually have 'delete_plugin' yet, so this is a bad but
			 * necessary hack to prevent deleting one of these plugins loaded as MU.
			 */
			case 'delete_plugins':
				if ( isset( $_REQUEST['checked'] ) ) {
					$plugins = wp_unslash( $_REQUEST['checked'] );
					if ( array_intersect( $plugins, Dictate::get_required_plugins() ) ) {
						$caps[] = 'do_not_allow';
					}
				}
				break;
		}
		return $caps;

	}

	/**
	 * Displays a notice about plugins that don't match up with the current environment configuration
	 *
	 * @return void
	 * @access public
	 */
	public function plugin_mismatch_notice() {

		$screen = get_current_screen();

		if ( 'plugins' === $screen->id ) {

			$plugins_active = ( ! empty( Dictate::get_dictated_plugins() ) ) ? Dictate::get_dictated_plugins() : [];
			$plugins_recommended = ( ! empty( Dictate::get_recommended_plugins() ) ) ? Dictate::get_recommended_plugins() : [];
			$plugins_required = ( ! empty( Dictate::get_required_plugins() ) ) ? Dictate::get_required_plugins() : [];

			$should_be_active = array_diff( $plugins_recommended, $plugins_active );
			$should_not_be_active = array_diff( $plugins_active, array_merge( $plugins_recommended, $plugins_required ) );

			$message = '';

			if ( ! empty( $should_be_active ) ) {
				$message .= '<br/><br/><strong>' . __( 'The following plugins are not active, but are recommended', 'wp-plugin-dictator' ) . ':</strong> ' . implode( $should_be_active, ', ' );
			}

			if ( ! empty( $should_not_be_active ) ) {
				$message .= '<br/><br/><strong>' . __( 'The following plugins are active but they are not recommended', 'wp-plugin-dictator' ) . ':</strong>' . implode( $should_not_be_active, ', ' );
			}

			if ( ! empty( $message ) ) {

				$url = add_query_arg(
					[
						'reset_plugins' => '',
						'_wpnonce' => wp_create_nonce( 'reset-plugins' ),
					],
					admin_url()
				);

				echo '<div id="plugin-diff-message" class="notice notice-warning"><p>' .
			     esc_html__( 'WARNING: The plugins active don\'t match what is recommended for this environment.', 'wp-plugin-dictator' ) .
			     wp_kses_post( $message ) .
			     '<br/><br/><a class="button" href="' . esc_url( $url ) . '">' . __( 'Reset Plugins', 'wp-plugin-dictator' ) . '</a>' .
				'</p></div>';

			}
		}

	}

	/**
	 * Reset the recommended plugins reset on page load
	 *
	 * @return void
	 * @access public
	 */
	public function dictate_recommended_plugins() {

		$needs_update = ( isset( $_GET['reset_plugins'] ) ) ? true : false;
		$nonce = ( ! empty( $_GET['_wpnonce'] ) ) ? $_GET['_wpnonce'] : '';

		if ( ! wp_verify_nonce( $nonce, 'reset-plugins' ) ) {
			return;
		}

		if ( true === $needs_update && current_user_can( 'activate_plugins' ) ) {

			Dictate::reset_plugins();

		}

	}

	/**
	 * Add a new tab for custom path plugins
	 *
	 * @param array $tabs The tabs for the page
	 *
	 * @return mixed
	 * @access public
	 */
	public function add_custom_path_tab( $tabs ) {

		$custom_path_plugins = Dictate::get_custom_path_plugins( true );

		if ( empty( $custom_path_plugins ) ) {
			return $tabs;
		}

		$custom_path_tab = sprintf(
			'<a class="%1$s" href="%2$s">%3$s</a><span class="count">(%4$d)</span>',
			( ! empty( $_GET['plugin_status'] ) && 'custom-path' === sanitize_text_field( $_GET['plugin_status'] ) ) ? 'current' : '',
			add_query_arg( 'plugin_status', 'custom-path', 'plugins.php' ),
			__( 'Custom Path', 'wp-plugin-dictator' ),
			count( $custom_path_plugins )
		);
		$tabs['custom-path'] = $custom_path_tab;
		return $tabs;

	}

	/**
	 * Filter out regular plugins for the custom path plugin list
	 *
	 * @param array $plugins The array of plugins to be filtered
	 *
	 * @return array
	 * @access public
	 */
	public function filter_plugin_list( $plugins ) {

		if ( empty( $_GET['plugin_status'] ) || 'custom-path' !== sanitize_text_field( $_GET['plugin_status'] ) ) {
			return $plugins;
		}

		$clean_plugins = [];
		$custom_path_plugins = Dictate::get_custom_path_plugins( true );
		if ( ! empty( $custom_path_plugins ) && is_array( $custom_path_plugins ) ) {
			foreach ( $custom_path_plugins as $plugin_slug => $data ) {
				$plugin_data = get_plugin_data( $data['path'] );
				if ( empty( $plugin_data['Name'] ) ) {
					$plugin_data['Name'] = $plugin_slug;
				}
				$clean_plugins[ $plugin_slug ] = $plugin_data;
			}
		}

		return $clean_plugins;

	}

}
