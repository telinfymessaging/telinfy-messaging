<?php
/**
 * Telinfy Plugin Loader.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

if ( ! class_exists( 'telinfyLoader' ) ) {

	/**
	 * Class telinfyLoader.
	 */
	final class telinfyLoader {


		/**
		 * Member Variable
		 *
		 * @var instance
		 */
		private static $instance = null;

		/**
		 *  Initiator
		 */
		public static function get_instance() {

			if ( is_null( self::$instance ) ) {

				self::$instance = new self();

				/**
				 * Telinfy loaded.
				 *
				 * Fires when Telinfy was fully loaded and instantiated.
				 *
				 */
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {

			if ( ! defined( 'WOOCOMMERCE_TELINFY_MESSAGING_INCLUDES_PATH' ) ) {
				define('WOOCOMMERCE_TELINFY_MESSAGING_INCLUDES_PATH', plugin_dir_path( __FILE__ ));
			}

			$this->telinfy_define_constants();

			add_action( 'plugins_loaded', array( $this, 'telinfy_load_plugin' ), 99 );

			// Remove the cron task when the plugin has been deactivated
			register_deactivation_hook(__FILE__, 'telinfy_messaging_deactivation');

		}

		/**
		 * Defines all constants
		 *
		 * @since 1.0.0
		 */
		public function telinfy_define_constants() {

			define( 'TM_CURRENT_PLUGIN_NAME', 'Telnify messaging' );

		}

		/**
		 * Loads plugin files.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function telinfy_load_plugin() {


			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'telinfy_missing_wc_notice' ) );
				return;
			}
			$this->telinfy_load_core_files();

			if(is_admin()) {
			    $adminController = \TelinfyMessaging\Controllers\adminController::get_instance();
			    $adminController->init();
			}

			$messageController = \TelinfyMessaging\Controllers\messageController::get_instance();
			$messageController->init();

			\TelinfyMessaging\Includes\abandonedController::get_instance();

			\TelinfyMessaging\Includes\Cron::get_instance();

			\TelinfyMessaging\Controllers\cronController::get_instance();

			\TelinfyMessaging\Api\smsConnector::get_instance();

			// add_action( 'admin_notices', array( $this, '<<function name>>' ) );

			// Hook to run when the cron schedule is triggered
			add_action('woocommerce_telinfy_messaging_cron', function() {
			    // Only proceed if WooCommerce is available
			    if(function_exists('wc')) {
			        $cronController = \TelinfyMessaging\Controllers\cronController::get_instance();
			        $cronController->cron();
			    }
			});
		}


		/**
		 * Load Core Components.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function telinfy_load_core_files() {

			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'controllers/adminController.php' );
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'controllers/cronController.php' );
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'controllers/messageController.php' );
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'controllers/abandonedController.php' );
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'includes/Cron.php' );
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'api/smsConnector.php');
			include_once( WOOCOMMERCE_TELINFY_MESSAGING_PLUGIN_PATH . 'api/telinfyConnector.php');
		}

		/**
		 * Deactivate plugin
		 */

		function telinfy_messaging_deactivation() {

		    wp_clear_scheduled_hook( 'woocommerce_telinfy_messaging_cron' );

		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return void
		 */
		public function telinfy_missing_wc_notice() {
			/* translators: %s WC download URL link. */
			echo '<div class="error"><p><strong>' . sprintf( esc_html__( '%1$s requires WooCommerce to be installed and active. You can download %2$s here.', 'telinfy-messaging' ), esc_html( TM_CURRENT_PLUGIN_NAME ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
		}
	}


	/**
	 *  Prepare if class 'telinfyLoader' exist.
	 *  Kicking this off by calling 'get_instance()' method
	 */
	telinfyLoader::get_instance();
}


if ( ! function_exists( 'telinfy_load' ) ) {
	/**
	 * Get global class.
	 *
	 * @return object
	 */
	function telinfy_load() {
		return telinfyLoader::get_instance();
	}
}

