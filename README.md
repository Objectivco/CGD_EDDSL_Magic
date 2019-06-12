CGD_EDDSL_Magic
===============

A drop-in class that magically manages your EDD SL plugin licensing.

#  What is magic and why do I need it?
EDD's brilliant Software Licensing add-on is awesome, but its implementation examples are thin.  Managing the various activation and licensing states takes a good amount of research and setup.

Once you have it setup, it can be a major pain in the tucus to manage across your various plugins.

For example, I have 8 plugins.  Everytime I find a bug in my licensing code, I have to update 8 plugins that have 8 slightly different implementations.  It's a major headache.

`CGD_EDDSL_Magic` fixes all of this.  With as little as a single line of code, you can add a fully functioning licensing settings page to your plugin.  

## Installing

The best way to install is with composer:
`composer require objectivco/cgd_eddsl_magic`

### Alternative method
1) Copy or clone CGD_EDDSL_Magic into your plugin project.  Put it in a lib or inc folder.

2)  At the top of your main plugin file, or wherever you do your includes, add some code like:

```php
if ( ! class_exists( 'CGD_EDDSL_Magic' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/lib/CGD_EDDSL_Magic/CGD_EDDSL_Magic.php' );
}
```

## Instantiating 
In your plugin constructor (or in the main plugin file if you're not using classes for some reason),  instantiate `CGD_EDDSL_Magic`.

```php
$updater = new CGD_EDDSL_Magic($prefix, $menu_slug,  $host_url, $plugin_version, $plugin_name, $plugin_author, $plugin_file, $theme = false, $beta = false, $home_url = false);
```

**Note: The last parameter, `$plugin_file` is technically optional, but it's better to pass it in. This should be the main file for your plugin, the one with the plugin header. If you're in the main plugin file, use `__FILE__`, otherwise, define it as a constant in your main plugin file and pass it in when you instantiate the class.**

## The parameters:
#### $prefix
This is a unique prefix for your instance.  It's used for saving settings and hooking up various behaviors.  Keep it short, and no spaces or weird symbols or other funny business.  Example: myplugin

#### $menu_slug
Assuming your plugin has a menu page, you would set the slug of that menu here so that `CGD_EDDSL_Magic` can add a submenu called "License" to this menu.  If you'd rather control this yourself, set to `false`.

#### $host_url
The URL of the site that hosts your plugins.

#### $plugin_version
The version of the plugin.

#### $plugin_name
The name of the plugin as setup in EDD.

#### $plugin_author
The author of the plugin.

#### $plugin_file
The main plugin file.

#### $theme
Set to true for theme updates.

#### $beta
Set to true to enable beta versions.

#### $home_url
Defaults to false. If false, it uses home_url() for activation checks. Otherwise, you can pass in a URL to check.

---

If you're using a class, it's probably a good idea to set a class variable called `updater` and then assign the new `CGD_EDDSL_Magic` instance to that. It will make it easier to access later.

**In a basic setup, you're done at this point.  Your plugin will now have a fully functioning license settings page, added to whatever your parent menu is.  For more advanced options, continue below.**

## What about updating themes?

If your project is a theme, you simply need to set the `$theme` parameter above to true. This makes the `$plugin_file` parameter uneccessary, so you can simply set this parameter to `false`. Either way, it will not be used.


# Advanced Implementation

## Controlling the licensing settings page
By default, a menu item called "License" is added to the parent menu of your choice. If you would like to have full control of where the license settings page is, that's actually really easy to do too.

Just set `$menu_slug` to false in your instantation, and then drop this line in your admin page, wherever you prefer:
``` php
$updater->admin_page();
```
Obviously, the exact syntax will vary depending on how you implement it.  This is one reason I find it easier to set the updater instance as a class variable.

**One important note: Do not place this line in another HTML form.  It will screw things up. Browsers hate nested forms. (and HTML standards do not permit them)**

## Cronning license checks
If you want it,  `CGD_EDDSL_Magic` includes a way to force regular license checks.  To do this, you'd add the following code to your activation or deactivation hooks:

### Activation hook
``` php
$this->updater->set_license_check_cron();
```

### Deactivation hook
``` php
$this->updater->unset_license_check_cron();
```

This will create daily checks that keep your key_status variable up-to-date.

# Really Advanced Implementation

If this does not satisfy you, and you want to add some type of nag to the plugin listing on the plugins page in WP admin, here's a quick example of how you might do that.  This is just a starting point, so you'll have to parse through it to figure out how it works.

```php
	add_action('admin_menu', 'add_key_nag', 11);
	function add_key_nag() {
		global $pagenow;

	    if( $pagenow == 'plugins.php' ) {
	        add_action( 'after_plugin_row_' . plugin_basename(__FILE__), 'after_plugin_row_message', 10, 2 );
	    }
	}

	function after_plugin_row_message() {
		$key_status = $this->updater->get_field_value('key_status');

		if ( empty($key_status) ) return;

		if ( $key_status != "valid" ) {
			$current = get_site_transient( 'update_plugins' );
			if ( isset( $current->response[ plugin_basename(__FILE__) ] ) ) return;

			if ( is_network_admin() || ! is_multisite() ) {
				$wp_list_table = _get_list_table('WP_Plugins_List_Table');
				echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
				echo keynag();
				echo '</div></td></tr>';
			}
		}
	}

	function keynag() {
		return "<span style='color:red'>You're missing out on important updates because your license key is missing, invalid, or expired.</span>";
	}
```

# Changelog
## Version 0.5.3
- Change license key field to a password field.

## Version 0.5.2
- Fixed bug with setting home url to passed in value.

## Version 0.5.1
- Ok, that deprecated option was a bad idea. I'm removing it and instead providing the ability to pass in the URL you want to use for activation with a default to `home_url()`

## Version 0.5.0
- Add deprecated_url option and default to true. This tells the updater to check `home_url()` for site activation actions instead of `get_site_url()`. New projects should use `get_site_url()`.
- WP Coding Standards fixes.

## Version 0.4.0
- Fix return types.
- Replace invalid reference to class EDD_SL_Theme_Updater with EDD_Theme_Updater.

## Version 0.3.2
- Composer package.
- Update EDD_SL_Plugin_Updater.
- Add some utility functions that we use internally that you may find useful. 

## Version 0.3.1
- Add trailing slash to remote API url. Fixes odd bug with wp_remote_get().

## Version 0.3
- Added theme update support!

## Version 0.2
- Added url parameter to API requests for more reliable handling.

## Version 0.1
- Initial release.
