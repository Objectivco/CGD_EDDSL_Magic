<?php
/*
Plugin Name: Awesome Plugin
Description:  Demonstrates the amazing CGD_EDDSL_Magic drop-in updater for EDD SL.
Version: 0.1
Author: Clif Griffin Development Inc.
Author URI: http://cgd.io
*/

// Include the updater only if another plugin has not already done so
if( !class_exists( 'CGD_EDDSL_Magic' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/lib/CGD_EDDSL_Magic/CGD_EDDSL_Magic.php' );
}

class AwesomePlugin {
	var $updater; // this is not required, but can be helpful

	function __construct() {
		// Add an admin menu
		add_action('admin_menu', array($this, 'menu') );
		
		// Instantiate Updater
		$this->updater = new CGD_EDDSL_Magic("awesome", "awesome-plugin", 'http://cgd.io', '1.0.0', 'Awesome Plugin', 'CGD Inc.', __FILE__);
	}

	function menu () {	
		add_menu_page( "Awesome", "Awesome", 'manage_options', "awesome-plugin", array($this, 'admin_page') );
	}

	function admin_page () {
		include 'awesome-plugin-admin.php';
	}
}

$AwesomePlugin = new AwesomePlugin();