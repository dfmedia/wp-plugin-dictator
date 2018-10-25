## WP Plugin Dictator
This WordPress plugin can be used to dictate which plugins should, and should not be active in a given environment. This is particularly useful for when you are using version control for your WordPress site, and have plugins that need to be active for things to function properly.

### Installation
To use this plugin simply copy all of the code from this repository into your `mu-plugins` directory **IMPORTANT** - This plugin MUST be used as an mu-plugin to function properly.

### plugins.json
The main feature of this plugin is consuming json configuration files, and using that data to determine which plugins should be active for the environment.

By default, the plugin looks for config files at the following paths:
- `/wp-content`
- `/wp-content/mu-plugins`
- `/wp-content/plugins`
- `/wp-content/themes/{active_parent_theme}`
- `/wp-content/themes/{active_child_theme}`

You can also add additional paths with the `wp_plugin_dictator_config_paths` filter.

Once the plugin has consumed all of these config files, it will also search for any config files within the plugins defined in these main configs, and merge them together. This allows individual plugins to define their own dependencies.

#### Basic Usage
Below is a basic example of what one of the plugins.json config files. You can use the `activate` array to note which plugins should be recommended to be active for the site, and the `deactivate` array to note which plugins should not be recommended for the environment. Any plugin in the deactivate array will override a plugin in the activate array, so if a plugin is in a deactivate array in any config file on your site, it will not be recommended for the site, even if it's in an activate array in another config file.
```json
{
  "activate" : {
    "wordpress-seo/wp-seo.php" : {}
  },
  "deactivate" : {
    "debug-bar/debug-bar.php" : {}
  }
}
```
#### Force activation/deactivation
Going beyond the basic example above, you can also force a plugin to be activated or deactivated for a site. This will remove any ability to turn the plugin on or off from the admin, or through any other means (CLI or REST).
```json
{
  "activate" : {
    "jetpack/jetpack.php" : {},
    "wordpress-seo/wp-seo.php" : {
      "force" : true
    }
  },
  "deactivate" : {
    "debug-bar/debug-bar.php" : {
      "force" : true
    }
  }
}
```

#### Custom path plugins
In addition to dictating the status of plugins within the plugin directory, you can also include plugins from a custom path. You can use the `path` key to set the parent directory of where the plugin is located. Then you can use the `priority` key for when the plugin should be loaded. `0` = mu_plugins_loaded, `1` = plugins_loaded, `2` = after_setup_theme
```json
{
  "activate" : {
    "wordpress-seo/wp-seo.php" : {
      "path" : "wp-content/custom-plugins",
      "priority" : 1
    }
  }
}
```

#### Dynamic plugin dictating
In addition to the config files you can also dictate plugins dynamically through code for when you need to activate/deactivate a plugin based on certain conditions. 
**NOTE** - These hooks and filters fire very early, so this needs to be done in a mu-plugin before the plugin dictator loads
```php
add_action( 'wp_plugin_dictator_after_default_configs_built', 'wppd_require_plugin_dynamically' );
function wppd_require_plugin_dynamically() {
	
	if ( ! defined( 'WPPD_ENVIRONMENT' ) ) {
		return;
	}
	
	$plugin_config = Dictate::get_configs();
	
	if ( 'staging' === WPPD_ENVIRONMENT ) {
		$plugin_config['activate']['my-debugger-plugin/my-debugger-plugin.php'] = [
			'path' => 'wp-content/custom-plugins',
			'priority' => 1,
		];
	} else {
		$plugin_config['deactivate']['my-debugger-plugin/my-debugger-plugin.php'] = [
			'path' => 'wp-content/custom-plugins',
			'force' => true,
		];
	}
	
	Dictate::set_configs( $plugin_config );
	
}
```

You can also load an entire config file dynamically if you have more than a handful of plugins that follow the same conditional.
```php
function wppd_include_environment_config( $configs ) {

	if ( ! defined( 'WPPD_ENVIRONMENT' ) {
		return $configs;
	}
	
	$environment = WPPD_ENVIRONMENT;
	$configs[ $environment ] = 'wp-content/configs/' . $environment . '.json';
	return $configs;

}
```
### WP-CLI Support
In order to enforce the dictated plugins list from the configs, you can run `wp plugin dictate reset`. Which should give you the following output:
```shell
Starting plugin reset...
Success: Plugins reset
```
You can also get the list of dictated plugins with `wp plugin dictate list`, which should give you an output that looks like:
```shell
+---------------------------------------------+----------+------------+-------+----------------------------------------------+------------+
| slug                                        | activate | deactivate | force | path                                         | status     |
+---------------------------------------------+----------+------------+-------+----------------------------------------------+------------+
| ad-layers/ad-layers.php                     | yes      |            | no    |                                              | active     |
| ai-logger/ai-logger.php                     | yes      |            | no    |                                              | not active |
| amp-wp/amp.php                              |          | yes        | no    |                                              | not active |
| Basic-Auth/basic-auth.php                   | yes      |            | yes   |                                              | active     |
| chartbeat/chartbeat.php                     |          | yes        | no    |                                              | not active |
| co-authors-plus/co-authors-plus.php         | yes      |            | no    | wp-content/custom-plugins                    | active     |
+---------------------------------------------+----------+------------+-------+----------------------------------------------+------------+
```
### Attribution
Props to the following projects and blog posts for giving me ideas / code.

- https://deliciousbrains.com/dependency-management-wordpress-proposal/
- https://journal.rmccue.io/322/plugin-dependencies/
- http://ottopress.com/2012/themeplugin-dependencies/
- http://tgmpluginactivation.com/
- https://gist.github.com/felixarntz/daff4006112b60dfea677ca08fc0b31c
