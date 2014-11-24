<?php

if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	include( dirname( __FILE__ ) . '/lib/EDD_SL_Plugin_Updater.php' );
}

/**
 * EDD Software Licensing Magic
 *
 * A drop-in class that magically manages your EDD SL plugin licensing.
 *
 * @author Clifton H. Griffin II
 * @version 0.2.3
 * @copyright Clif Griffin Development, Inc. 2014
 * @license GNU GPL version 3 (or later) {@see license.txt}
 **/
class CGD_EDDSL_Magic {

	var $menu_slug; // parent menu slug to attach "License" submenu to
	var $prefix; // prefix for internal settings
	var $url; // plugin host site URL for EDD SL
	var $version; // plugin version for EDD SL
	var $name; // plugin name for EDD SL
	var $key_statuses; // store list of key statuses and messages
	var $activate_errors; // store list of activation errors and error messages
	var $last_activation_error; // because we can't pass variables directly to admin_notice
	var $plugin_file; // we need to pass this in so it maps to WP
	
	/**
	 * Constructor
	 * 
	 * @access public
	 * @param string $prefix A prefix that keeps your setting separate for this instance. Short and no spaces. (default: false)
	 * @param string $menu_slug The menu slug of the parent menu you want to attach the License submenu item to. Same syntax as add_submenu_page()  (default: false)
	 * @param string $url The URL of your host site (default: false)
	 * @param string $version The plugin version (default: false)
	 * @param string $name The plugin name (default: false)
	 * @param string $author The author of the plugin.
	 * @return void
	 */
	public function __construct( $prefix = false, $menu_slug = false, $url = false, $version = false, $name = false, $author, $plugin_file = false ) {
		if ( $prefix === false ) {
			error_log('CGD_EDDSL_Magic: No prefix specified. Aborting.');
			return;
		} 
		
		if ( $url === false || $version === false || $name == false ) {
			error_log('CGD_EDDSL_Magic: url, version, plugin file, or name parameter was false. Aborting.');
			return;
		} 
		
		// Try to figure out plugin file if not provided
		if ( $plugin_file === false ) {
			$bt = debug_backtrace();
			$plugin_file = $bt[0]['file'];
		}
				
		$this->url = $url;
		$this->version = $version;
		$this->name = $name;
		$this->author = $author;
		$this->menu_slug = $menu_slug;		
		$this->prefix = $prefix . "_";
		$this->plugin_file = $plugin_file;
		
		$this->key_statuses = array(
			'invalid' => 'The entered license key is not valid.',
			'expired' => 'Your key has expired and needs to be renewed.',
			'inactive' => 'Your license key is valid, but is not active.',
			'disabled' => 'Your license key is currently disabled. Please contact support.',
			'site_inactive' => 'Your license key is valid, but not active for this site.',
			'valid' => 'Your license key is valid and active for this site.'
		);
		
		$this->activate_errors = array(
			'missing' => 'The provided license key does not seem to exist.',
			'revoked' => 'The provided license key has been revoked. Please contact support.',
			'no_activations_left' => 'This license key has been activated the maximum number of times.',
			'expired' => 'This license key has expired.',
			'key_mismatch' => 'An unknown error has occurred: key_mismatch'
		);
		
		// Instantiate EDD_SL_Plugin_Updater
		add_action( 'admin_init', array($this, 'updater_init'), 0 ); // run first
		
		// Add License settings page to menu
		if ( $this->menu_slug !== false )
			add_action('admin_menu', array($this,'admin_menu'), 11);
		
		// Form Handler
		add_action('admin_init', array($this, 'save_settings') );
		
		// Cron action
		add_action($this->prefix . '_check_license', array($this, 'check_license') );
		
	}
	
	// Form Saving Stuff
	
	/**
	 * the_nonce 
	 * Creates a nonce for the license page form.
	 * 
	 * @access public
	 * @return void
	 */
	public function the_nonce() {
		wp_nonce_field( "save_{$this->prefix}_mb_settings", "{$this->prefix}_mb_save" );
	}
	
	/**
	 * get_field_name 
	 * Generates a field name from a setting value for the license page form. 
	 *
	 * @access public
	 * @param string $setting The key for the setting you're saving.
	 * @return void
	 */
	public function get_field_name ( $setting ) {
		return "{$this->prefix}_mb_setting[$setting]";
	}
	
	/**
	 * get_field_value
	 * Retrieves value from the database for specified setting.
	 * 
	 * @access public
	 * @param string $setting The setting key you're retrieving (default: false)
	 * @return void
	 */
	public function get_field_value ( $setting = false ) {
		if ( $setting === false ) return false;

		$value = get_option($this->prefix . "_" . $setting);

		return $value;
	}
	
	/**
	 * set_field_value 
	 * Set value for the specified setting.
	 *
	 * @access public
	 * @param string $setting (default: false)
	 * @param mixed $value
	 * @return void
	 */
	public function set_field_value ( $setting = false, $value ) { 
		if ( $setting === false ) return false;

		$value = update_option($this->prefix . "_" . $setting, $value); 
	}
	
	/**
	 * save_settings
	 * Save license settings.  Listens for settings form submit. Also handles activation / deactivation.
	 * 
	 * @access public
	 * @return void
	 */
	public function save_settings() {
		//print_r($_REQUEST); die;
		if( isset($_REQUEST["{$this->prefix}_mb_setting"]) && check_admin_referer("save_{$this->prefix}_mb_settings","{$this->prefix}_mb_save") ) {
			$settings = $_REQUEST["{$this->prefix}_mb_setting"];
			foreach($settings as $setting => $value) {
				$this->set_field_value($setting, $value);
			}
			
			// Handle activation if applicable
			if ( isset($_REQUEST['activate_key']) || isset($_REQUEST['deactivate_key']) ) {
				$this->manage_license_activation();
			} else {
				$this->check_license();
			}
			
			add_action( 'admin_notices', array($this, 'notice_settings_saved_success') );
		}
	}
	
	/**
	 * updater_init 
	 * Sets up the EDD_SL_Plugin_Updater object.
	 * 
	 * @access public
	 * @return void
	 */
	function updater_init() {

		// retrieve our license key from the DB
		$license_key = trim( $this->get_field_value('license_key') );

		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater( $this->url, $this->plugin_file, array(
				'version' 	=> $this->version, 				// current version number
				'license' 	=> $license_key, 		// license key (used get_option above to retrieve from DB)
				'item_name' => $this->name, 	// name of this plugin
				'author' 	=> $this->author  // author of this plugin
			)
		);
	}
	
	/**
	 * Adds "License" page to specified parent menu. Only attached if menu_slug is not false.
	 * 
	 * @access public
	 * @return void
	 */
	function admin_menu() {
		add_submenu_page( $this->menu_slug, "{$this->name} License Settings", "License", "manage_options", $this->prefix . "menu", array($this, "admin_page") );
	}
	
	/**
	 * admin_page 
	 * Generates license page form.
	 * 
	 * @access public
	 * @return void
	 */
	function admin_page() {
		$key_status = $this->get_field_value('key_status');
		?>
		<div class="wrap">
			<h2><?php echo $this->name; ?> License Settings</h2>
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
				<?php $this->the_nonce(); ?>
	
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row" valign="top">
								<label for="<?php echo $this->get_field_name('license_key'); ?>">License Key</label>
							</th>
							<td>
								<input type="text" class="regular-text" id="<?php echo $this->get_field_name('license_key'); ?>" name="<?php echo $this->get_field_name('license_key'); ?>" value="<?php echo $this->get_field_value('license_key'); ?>" /><br />
								<span>Your <?php echo $this->name; ?> license key.</span>
							</td>
						</tr>
	
						<?php if ( ! empty($key_status) ): ?>
						<tr>
							<th scope="row" valign="top">
								<label>Key Status</label>
							</th>
							<td>
								<?php if ( $key_status == "inactive" || $key_status == "site_inactive" ): ?>
									<input type="submit" name="activate_key" class="button-secondary" value="Activate Site" />
									<p style="color:red;"><?php echo $this->key_statuses[$key_status]; ?></p>
								<?php elseif ( $key_status == "valid" ): ?>
									<input type="submit" name="deactivate_key" class="button-secondary" value="Deactivate Site" />
									<p style="color:green;"><?php echo $this->key_statuses[$key_status]; ?></p>
								<?php else: ?>
									<p style="color:red;"><?php echo $this->key_statuses[$key_status]; ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
						
					</tbody>
				</table>
				
				<?php submit_button(); ?>
			</form>

		</div>								

		<?php
	}
	
	/**
	 * manage_license_activation
	 * Handles license activation and deactivation
	 * 
	 * @access public
	 * @return void
	 */
	function manage_license_activation() {
		
		$action = isset($_REQUEST['activate_key']) ? 'activate_license' : 'deactivate_license';
		
		// data to send in our API request
		$api_params = array(
			'edd_action'=> $action,
			'license' 	=> $this->get_field_value('license_key'),
			'item_name' => urlencode( $this->name ), // the name of our product in EDD
			'url'		=> home_url()
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, $this->url ), array( 'timeout' => 15, 'sslverify' => false ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( $action == "activate_license" ) {
			// Front end notice only
			// $license_data->license will be either "valid" or "invalid"
			
			if ( isset($license_data->error) ) {
				$this->last_activation_error = $license_data->error;
				add_action( 'admin_notices', array($this, 'notice_license_activate_error') );
			} else if ( $license_data->license == "invalid" ) {
				add_action( 'admin_notices', array($this, 'notice_license_invalid') );
			} else {
				add_action( 'admin_notices', array($this, 'notice_license_valid') );
			}
			
		} else {
			// $license_data->license will be either "deactivated" or "failed"
			if ( $license_data->license == "failed" ) {
				// warn user
				add_action( 'admin_notices', array($this, 'notice_license_deactivate_failed') );
			} else {
				add_action( 'admin_notices', array($this, 'notice_license_deactivate_success') );
			}
		}
		
		// Set detailed key_status
		$this->set_field_value('key_status', $this->get_license_status() );
	}
	
	/**
	 * get_license_status
	 * Retrieve status of license key for current site.
	 * 
	 * @access public
	 * @return void
	 */
	function get_license_status( ) {

		global $wp_version;

		$license = trim( $this->get_field_value('license_key') );

		if ( empty($license) ) return;

		$api_params = array(
			'edd_action'	=> 'check_license',
			'license' 		=> $license,
			'item_name' 	=> urlencode( $this->name ),
			'url'			=> home_url()
		);

		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, $this->url ), array( 'timeout' => 15, 'sslverify' => false ) );


		if ( is_wp_error( $response ) )
			return false;

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		return $license_data->license;
	}
	
	/**
	 * notice_license_invalid function.
	 * 
	 * @access public
	 * @return void
	 */
	function notice_license_invalid() {
		?>
		<div class="error">
			<p><?php echo $this->name; ?> license activation was not successful. Please check your key status below for more information.</p>
		</div>
		<?php
	}

	/**
	 * notice_license_valid function.
	 * 
	 * @access public
	 * @return void
	 */
	function notice_license_valid() {
		?>
		<div class="updated">
			<p><?php echo $this->name; ?> license successfully activated.</p>
		</div>
		<?php
	}
	
	/**
	 * notice_license_deactivate_failed function.
	 * 
	 * @access public
	 * @return void
	 */
	function notice_license_deactivate_failed() {
		?>
		<div class="error">
			<p><?php echo $this->name; ?> license deactivation failed. Please try again, or contact support.</p>
		</div>
		<?php
	}
	
	/**
	 * notice_license_deactivate_success function.
	 * 
	 * @access public
	 * @return void
	 */
	function notice_license_deactivate_success() {
		?>
		<div class="updated">
			<p><?php echo $this->name; ?> license deactivated successfully.</p>
		</div>
		<?php
	}
	
	function notice_settings_saved_success() {
		?>
		<div class="updated">
			<p><?php echo $this->name; ?> license settings saved successfully.</p>
		</div>
		<?php
	}
	
	/**
	 * notice_license_activate_error function.
	 * 
	 * @access public
	 * @param mixed $error
	 * @return void
	 */
	function notice_license_activate_error($error) {
		?>
		<div class="error">
			<p><?php echo $this->name; ?> license activation failed: <?php echo $this->activate_errors[$this->last_activation_error]; ?></p>
		</div>
		<?php	
	}
	
	/**
	 * set_license_check_cron 
	 * Create cron for license check
	 * 
	 * @access public
	 * @return void
	 */
	public function set_license_check_cron() {
		$this->unset_license_check_cron();
		wp_schedule_event(time(), 'daily', $this->prefix . '_check_license');
	}
	
	/**
	 * unset_license_check_cron 
	 * Clear cron for license check.
	 * 
	 * @access public
	 * @return void
	 */
	public function unset_license_check_cron() { 
		wp_clear_scheduled_hook( $this->prefix . '_check_license' );
	}
	
	/**
	 * check_license 
	 * Retrieve license status for current site and store in key_status setting.
	 * 
	 * @access public
	 * @return void
	 */
	function check_license() {
		$this->set_field_value('key_status', $this->get_license_status() );
	}
}