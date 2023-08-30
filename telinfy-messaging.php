<?php
/*
Plugin Name: Telinfy Messaging
Description: Sends out messages to customers for orders, order processing, and abandoned carts using SMS, RCS, and WhatsApp
Version: 1.0.0
Author: GreenAds Global
Author URI: https://www.greenadsglobal.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.0.0
Tested up to: 6.2
WC tested up to: 7.8
Requires PHP: 7.0
*/


// Forbid accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 401 Unauthorized' );
	exit;
}

// Load the main controller

if ( ! defined( 'WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH' ) ) {
	define('WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH', plugin_dir_path( __FILE__ ));
}

if( ! defined( 'TM_CURRENT_TIME' )){
	define( 'TM_CURRENT_TIME', current_time( 'U' ) );
}

define('TM_ABANDON_VER','1.0.0');

// Files.

register_activation_hook( __FILE__,  'plugin_activation_callback');

function plugin_activation_callback(){
	require_once WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . "includes/plugin.php";
	$telinfy_plugin = \TelinfyMessaging\Includes\Plugin::get_instance();
	$telinfy_plugin->activate();
}
require_once 'includes/telinfyLoader.php';













