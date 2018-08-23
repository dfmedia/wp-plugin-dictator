<?php


class TestPluginsLoadedCest {

    public function _before(AcceptanceTester $I) {
    	//copy( WP_PLUGIN_DICTATOR_PLUGIN_DIR . '/tests/_data/configs/test_1_plugins.json', WP_CONTENT_DIR . '/plugins.json' );
    }

    public function _after(AcceptanceTester $I) {
		//unlink( WP_CONTENT_DIR. 'plugins.json' );
    }

    // tests
    public function tryToTest(AcceptanceTester $I) {
	    //copy( '../_data/configs/test_1_plugins.json', '/var/www/html/plugins.json' );
    	$I->loginAsAdmin();
    	$I->amOnPluginsPage();
    	$I->seePluginInstalled( 'test-plugin-1/test-plugin-1.php' );
    	//$I->seePluginActivated( 'test-plugin-1/test-plugin-1.php' );
	    //unlink( WP_CONTENT_DIR. 'plugins.json' );
    }
}
