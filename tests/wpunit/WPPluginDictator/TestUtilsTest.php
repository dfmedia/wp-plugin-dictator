<?php
namespace WPPluginDictator;

use const Patchwork\CodeManipulation\Actions\RedefinitionOfNew\publicizeConstructors;

class TestUtilsTest extends \Codeception\TestCase\WPTestCase {

    public function setUp() {
        // before
        parent::setUp();

    }

    public function tearDown() {
        // then
        parent::tearDown();
    }

	/**
	 * Assert that the default filename is returned
	 */
    public function testGetConfigFileName() {
    	$this->assertEquals( 'plugins', Utils::get_config_file_name( '/my/plugin' ) );
    }

	/**
	 * Assert that the filter to return a custom filename works correctly
	 */
    public function testGetCustomConfigFileName() {

	    $test_slug = 'customTestSlug';
	    $test_name = 'custom-slug';
	    add_filter( 'wp_plugin_dictator_config_file_name', function( $name, $slug ) use ( $test_slug, $test_name ) {
		    if ( $test_slug === $slug ) {
			    $name = $test_name;
		    }
		    return $name;
	    }, 10, 2 );

	    $this->assertEquals( $test_name, Utils::get_config_file_name( $test_slug ) );

    }

	/**
	 * Assert that the proper base path is returned given a plugin path
	 *
	 * @dataProvider dataProviderBasePaths
	 * @param string $path Full path to the plugin including slug
	 */
    public function testBasePathBuilder( $path ) {
    	$actual = Utils::build_custom_plugin_base_path( $path );
    	$expected = trailingslashit( ABSPATH ) . 'my-plugin/my-plugin.php';
    	$this->assertEquals( $expected, $actual );
    }

	/**
	 * Provides different data sets for the testBasePathBuilder test to run through
	 * @return array
	 */
	public function dataProviderBasePaths() {

		return [
			[
				'path' => '/my-plugin/my-plugin.php',
			],
			[
				'path' => 'my-plugin/my-plugin.php',
			],
			[
				'path' => trailingslashit( ABSPATH ) . 'my-plugin/my-plugin.php'
			],
		];

	}

	/**
	 * Assert that the proper plugin config path is returned given the plugin slug and path
	 *
	 * @dataProvider dataProviderPluginConfigs
	 *
	 * @param string $slug     Plugin slug
	 * @param string $path     Path to the parent directory of the plugin if custom path plugin
	 * @param string $expected The expected outcome
	 */
    public function testGetPluginConfig( $slug, $path, $expected ) {
    	$actual = Utils::get_config_for_plugin( $slug, $path );
    	$this->assertEquals( $expected, $actual );
    }

	/**
	 * Provides data sets for the testGetPluginConfig test to go through
	 * @return array
	 */
    public function dataProviderPluginConfigs() {
    	$abspath = trailingslashit( ABSPATH );
    	return [
    		[
    			'slug' => 'my-plugin/my-plugin.php',
			    'path' => '',
			    'expected' => trailingslashit( WP_PLUGIN_DIR ) . 'my-plugin/plugins.json',
		    ],
		    [
		    	'slug' => 'my-plugin/my-plugin.php',
			    'path' => '/some-path/',
			    'expected' => $abspath . 'some-path/my-plugin/plugins.json',
		    ],
		    [
			    'slug' => 'my-plugin/my-plugin.php',
			    'path' => '/some-path',
			    'expected' => $abspath . 'some-path/my-plugin/plugins.json',
		    ],
		    [
			    'slug' => 'my-plugin/my-plugin.php',
			    'path' => 'some-path/',
			    'expected' => $abspath . 'some-path/my-plugin/plugins.json',
		    ],
		    [
		    	'slug' => 'my-plugin/my-plugin.php',
			    'path' => $abspath . 'some-path/',
			    'expected' => $abspath . 'some-path/my-plugin/plugins.json',
		    ],
		    [
		    	'slug' => 'my-plugin.php',
			    'path' => '',
			    'expected' => '',
		    ],
		    [
		    	'slug' => 'my-plugin.php',
			    'path' => '/some-path/',
			    'expected' => ''
		    ]
	    ];

    }

	/**
	 * Assert that the proper plugin config path is returned when we filter it for a specific plugin
	 */
    public function testGetFilteredPluginConfig() {

    	$slug_to_filter = 'my-filterable-config-slug/plugin.php';
    	$expected = trailingslashit( WP_PLUGIN_DIR ) . 'my-filterable-config-slug/data/plugins.json';
    	add_filter( 'wp_plugin_dictator_plugin_config', function( $file_path, $plugin_slug ) use ( $slug_to_filter, $expected ) {
    		if ( $slug_to_filter === $plugin_slug ) {
    			$file_path = $expected;
		    }
		    return $file_path;
	    }, 10, 2 );

    	$actual = Utils::get_config_for_plugin( $slug_to_filter );
    	$this->assertEquals( $expected, $actual );

    }

    public function testGetConfigPaths() {

    	$expected = [
    	    'wp_content' => trailingslashit( WP_CONTENT_DIR ) . 'plugins.json',
		    'mu_plugins' => trailingslashit( WPMU_PLUGIN_DIR ) . 'plugins.json',
		    'plugins' => trailingslashit( WP_PLUGIN_DIR ) . 'plugins.json',
		    'parent_theme' => trailingslashit( get_template_directory() ) . 'plugins.json',
		    'child_theme' => trailingslashit( get_stylesheet_directory() ) . 'plugins.json',
	    ];

    	$this->assertEquals( $expected, Utils::get_config_paths() );

    }

    public function testGetConfigPathsFiltered() {

	    $expected = [
		    'wp_content' => trailingslashit( WP_CONTENT_DIR ) . 'plugins.json',
		    'mu_plugins' => trailingslashit( WPMU_PLUGIN_DIR ) . 'plugins.json',
		    'plugins' => trailingslashit( WP_PLUGIN_DIR ) . 'plugins.json',
		    'parent_theme' => trailingslashit( get_template_directory() ) . 'plugins.json',
		    'child_theme' => trailingslashit( get_stylesheet_directory() ) . 'plugins.json',
		    'custom_path' => trailingslashit( ABSPATH ) . 'my-custom-path/plugins.json',
	    ];

	    add_filter( 'wp_plugin_dictator_config_paths', function( $paths ) {
	    	$paths['custom_path'] =  trailingslashit( ABSPATH ) . 'my-custom-path/plugins.json';
	    	return $paths;
	    } );

	    $actual = Utils::get_config_paths();
	    remove_all_filters( 'wp_plugin_dictator_config_paths' );

	    $this->assertEquals( $expected, $actual );
    }

}
