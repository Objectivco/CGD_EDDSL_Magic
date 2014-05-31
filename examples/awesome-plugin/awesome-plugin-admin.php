<?php
global $AwesomePlugin; // we may need this below
?>
<div class="wrap">
    <h2>Awesome Plugin</h2>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
    	<p>In a real plugin, you'd have your main plugin settings page here.</p>
    	<p>To see how you can add your license settings to your main settings page instead of adding a separate License menu item, open up /plugins/awesome-plugin/awesome-plugin-admin.php and uncomment line 12 below.</p>
    </form>
    
    <?php //$AwesomePlugin->updater->admin_page(); ?>
</div>