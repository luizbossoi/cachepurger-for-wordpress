<?php 
   /*
   Plugin Name: CloudFlare Cache Purger for WordPress
   Description: CachePurger is a plugin for Wordpress websites running CloudFlare. It purges the cache in CloudFlare website using their API when a post is saved.
   Author: Luiz Bossoi
   Version: 1.4
   Author URI: http://luizbossoi.com.br
   */

global $ccfw_db_version;
global $ccfw_tablename_log;
$ccfw_tablename_log = 'cf_cachepurger_log';
$ccfw_db_version    = '1.0';


function ccfw_install() {
	global $wpdb;
	global $ccfw_db_version;
    global $ccfw_tablename_log;

	$table_name = $wpdb->prefix . $ccfw_tablename_log;
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		      id mediumint(9) NOT NULL AUTO_INCREMENT,
		      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		      text text NOT NULL,
		      PRIMARY KEY  (id)
	       ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
    
    update_option( "ccfw_db_version", $ccfw_db_version );
}

function ccfw_uninstall() {
    global $wpdb;
	global $ccfw_db_version;
    global $ccfw_tablename_log;

	$table_name = $wpdb->prefix . $ccfw_tablename_log;
    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);
    delete_option("ccfw_db_version");
}

function ccfw_purgeCache($path, $zone_id) {
    $cf_check_allcache  = get_option('cf_check_allcache');
    if($cf_check_allcache=='true') {
        $data = array('purge_everything'=>true);
        ccfw_addLog('Paths: - entire cache -');  
    } else {
        $data   = array("files" => $path);
        if(sizeof($path)<=0) { ccfw_addLog('Nothing to clear'); return false; }
        ccfw_addLog('Paths: ' . implode(', ',$path));  
    } 

    $result = wp_remote_post("https://api.cloudflare.com/client/v4/zones/$zone_id/purge_cache", 
           array(
                   'body'    => json_encode($data),
                   'headers' => array(
                                   'X-Auth-Email'  => get_option('cf_email_value'),
                                   'X-Auth-Key'    => get_option('cf_key_value'),
                                   'Content-Type'  => 'application/json'
                               )
    ));

    $arr_result = json_decode($result['body'], true);
    if(isset($arr_result['success'])) ccfw_addLog('Cache purged'); else ccfw_addLog('ERROR: ' . print_r($arr_result['errors'], true));
}

function ccfw_getZoneID($domain) {
    $result = wp_remote_get("https://api.cloudflare.com/client/v4/zones", 
           array(
                   'headers' => array(
                                   'X-Auth-Email'  => get_option('cf_email_value'),
                                   'X-Auth-Key'    => get_option('cf_key_value'),
                                   'Content-Type'  => 'application/json'
                               )
   ));

    $arr_result = json_decode($result['body'], true);
    if(isset($arr_result['success'])) {
        foreach($arr_result['result'] as $r) {
            if($r['name']==$domain) {
                return $r['id'];
            }
        }
       return false;
    }

return false;
}

function ccfw_addLog($message) {
    global $wpdb;
    global $ccfw_tablename_log;
	$table_name = $wpdb->prefix . $ccfw_tablename_log;

	$wpdb->insert( 
		$table_name, 
		array( 
			'time' => current_time( 'mysql' ), 
			'text' => $message, 
		) 
	);
}

function ccfw_clearCache($post_id) {
    $purge_paths    = array();
    $post_url	    = rtrim(get_permalink($post_id),'/');
    
    // check post type to avoid calling twice
    if(  ( wp_is_post_revision( $post_id) || wp_is_post_autosave( $post_id ) ) ) {
       return false;
    }
    
    // cloudflare settings
    $cf_key_value       = get_option('cf_key_value');
    $cf_email_value     = get_option('cf_email_value');
    
    // cache settings
    $cf_check_homepage  = get_option('cf_check_homepage');
    $cf_check_postpage  = get_option('cf_check_postpage');
    $cf_check_httphttps = get_option('cf_check_httphttps');
    $cf_check_allcache  = get_option('cf_check_allcache');
    $cf_textarea_custom = get_option('cf_textarea_custompaths');
    
    $arr_url	= parse_url($post_url);
    $domain		= str_replace('www.','', $arr_url['host']);

    if($cf_check_homepage=='true' || $cf_check_allcache=='true') { array_push($purge_paths, get_site_url() . "/"); array_push($purge_paths, get_site_url()); }
    if($cf_check_postpage=='true' || $cf_check_allcache=='true') { array_push($purge_paths, $post_url); array_push($purge_paths, $post_url . "/"); }
    
    // custom purge paths
    if(strlen($cf_textarea_custom)>0) {
        $arr_paths = explode(PHP_EOL, $cf_textarea_custom);
        foreach($arr_paths as $cp) {
            if(substr($cp,0,1)!=="/") $cp = "/$cp";
            $cp = trim(preg_replace('/\s\s+/', ' ', $cp));
            array_push($purge_paths, get_site_url() . $cp); 
        }
    }

    // SSL purge
    if($cf_check_httphttps=='true' || $cf_check_allcache=='true') {
       foreach($purge_paths as $ppk=>$ppv) {
           if(substr($ppv, 0, 5)=='https') {
               array_push($purge_paths, str_replace('https:','http:',$ppv));
           } else {
               array_push($purge_paths, str_replace('http:','https:',$ppv));
           }
       }
    }

    if(strlen($cf_key_value)>0 && strlen($cf_email_value)>0) {
        ccfw_addLog("Post edited/added, need to purge cache...");
        $zone_id = ccfw_getZoneID($domain);
        if($zone_id==false) {
            ccfw_addLog("ERROR: Zone domain not found: $domain");
        } else {
            ccfw_addLog("Zone ID $zone_id found, requesting purge... ");
            
            if(ccfw_purgeCache($purge_paths, $zone_id)) {
              // 
            }
        }
    }
    
    ccfw_addLog('------------------ end --------------------');
}

function ccfw_cachepurger_register_settings() {
    // save cloudflare settings
    if($_SERVER['REQUEST_METHOD']=='POST') {
        
        $cf_key_value       = isset($_POST['cf_key_value'])         ? sanitize_text_field($_POST['cf_key_value'])       : null;
        $cf_email_value     = isset($_POST['cf_email_value'])       ? sanitize_text_field($_POST['cf_email_value'])     : null;
        $cf_check_homepage  = isset($_POST['cf_check_homepage'])    ? sanitize_text_field($_POST['cf_check_homepage'])  : null;
        $cf_check_postpage  = isset($_POST['cf_check_postpage'])    ? sanitize_text_field($_POST['cf_check_postpage'])  : null;
        $cf_check_httphttps = isset($_POST['cf_check_httphttps'])   ? sanitize_text_field($_POST['cf_check_httphttps']) : null;
        $cf_check_allcache  = isset($_POST['cf_check_allcache'])    ? sanitize_text_field($_POST['cf_check_allcache'])  : null;
        $cf_textarea_custom = isset($_POST['cf_textarea_custom'])   ? sanitize_text_field($_POST['cf_textarea_custom'])  : null;

        
        add_option( 'cf_key_value', $cf_key_value);
        register_setting( 'cachepurger_options_group', 'cf_key_value' );

        add_option( 'cf_email_value', $cf_email_value);
        register_setting( 'cachepurger_options_group', 'cf_email_value' );

        // save caching settings
        add_option( 'cf_check_homepage', $cf_check_homepage);
        register_setting( 'cachepurger_options_group', 'cf_check_homepage' );

        add_option( 'cf_check_postpage', $cf_check_postpage);
        register_setting( 'cachepurger_options_group', 'cf_check_postpage' );

        add_option( 'cf_check_httphttps', $cf_check_httphttps);
        register_setting( 'cachepurger_options_group', 'cf_check_httphttps' );

        add_option( 'cf_check_allcache', $cf_check_allcache);
        register_setting( 'cachepurger_options_group', 'cf_check_allcache' );

        add_option( 'cf_textarea_custompaths', $cf_textarea_custom);
        register_setting( 'cachepurger_options_group', 'cf_textarea_custompaths' );
    }
}

function ccfw_cachepurger_register_options_page() {
    add_options_page('CloudFlare CachePurger', 'CF CachePurger', 'manage_options', 'cf-cachepurger', 'ccfw_cachepurger_options_page');
}


// triggers
add_action( 'admin_init', 'ccfw_cachepurger_register_settings' );
add_action( 'admin_menu', 'ccfw_cachepurger_register_options_page' );
add_action( 'save_post', 'ccfw_clearCache' );
add_action( 'admin_notices',  'ccfw_admin_notices' );
add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'ccfw_add_plugin_page_settings_link' );
register_activation_hook( __FILE__, 'ccfw_install' );
register_deactivation_hook( __FILE__, 'ccfw_uninstall' );

function ccfw_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' . admin_url( 'options-general.php?page=cf-cachepurger' ) . '">' . __('Settings') . '</a>';
    return $links;
}

function ccfw_admin_notices() {
    $cf_key_value   = get_option('cf_key_value');
    $cf_email_value = get_option('cf_email_value');
    $screen 	= get_current_screen();
    
    if ( ($screen->parent_base == 'edit' || $screen->parent_base == 'plugins' ) && (strlen($cf_key_value)<=0 || strlen($cf_email_value)<=0) ) {
?>
<div class="error notice">
   <p>CachePurger for Wordpress is not configured properly, new posts or post updates will not be cleared from cache, please <a href="options-general.php?page=cachepurger">click here</a> to fix this issue.</p>
</div>
<?php
   }} 

function ccfw_cachepurger_options_page() { 

    global $wpdb;
    global $ccfw_tablename_log;
    
    // get tablename
    $table_name = $wpdb->prefix . $ccfw_tablename_log;
    
    // check if user has permission    
    if (current_user_can('activate_plugins')==false && current_user_can('edit_theme_options')==false && current_user_can('manage_options')==false) { 
        print('<div class="alert notice"><p><span style="color:red">Access Denied:</span> Unfortunately, your user does not have access to this plugin page. Please use an administrator account to make this changes</p></div>');
    } else {
?>
<div>
   <h2>CachePurger for Wordpress</h2>
   <form method="post" action="options.php">
      <?php settings_fields( 'cachepurger_options_group' ); ?>
      <p>Inform your CloudFlare's API key and account holder's e-mail below to make cache purge runs automatically when a new post is saved (new post / post edit)</p>
      <table style="width:40%;max-width:650px;min-width:550px">
         <tr valign="top">
            <th scope="row">E-mail Address:</th>
            <td><input type="text" id="cf_email_value" name="cf_email_value" value="<?php echo get_option('cf_email_value'); ?>" style="width:100%;" required /></td>
         </tr>
         <tr valign="top">
            <th scope="row">Global API Key:</th>
            <td><input type="text" id="cf_key_value" name="cf_key_value" value="<?php echo get_option('cf_key_value'); ?>" style="width:100%;" required /></td>
         </tr>
      </table>
      <p>Content to be cleared:</p>
      <table style="width:40%;max-width:650px;min-width:550px;margin-left:30px;">
         <tr valign="top">
            <td scope="row" style="width:40px"><input type="checkbox" name="cf_check_homepage" id="cf_check_homepage" value="true" <?php if(get_option('cf_check_homepage')) echo 'checked'; ?>></td>
            <td style="width:100%">Homepage <span style="color:#999999;font-size:10px">(recommended)</span></td>
         </tr>
         <tr valign="top">
            <td scope="row" style="width:40px"><input type="checkbox" name="cf_check_postpage" id="cf_check_postpage" value="true" <?php if(get_option('cf_check_postpage')) echo 'checked'; ?>></td>
            <td style="width:100%">Post page <span style="color:#999999;font-size:10px">(recommended)</span></td>
         </tr>
         <tr valign="top">
            <td scope="row" style="width:40px"><input type="checkbox" name="cf_check_httphttps" id="cf_check_httphttps" value="true" <?php if(get_option('cf_check_httphttps')) echo 'checked'; ?>></td>
            <td style="width:100%">Clear both http / https</td>
         </tr>
         <tr valign="top">
            <td scope="row"><input type="checkbox" name="cf_check_allcache" id="cf_check_allcache" onclick="ccfw_allcache(this)" value="true" <?php if(get_option('cf_check_allcache')) echo 'checked'; ?>></td>
            <td>All cache <span style="color:red;font-size:10px">(take care)</span></td>
         </tr>
         <tr valign="top">
            <td colspan="2"><br>Custom paths:</td>
         </tr>
         <tr valign="top">
            <td scope="row"></td>
            <td>
                <textarea style="width:90%;max-width:1200px;height:120px;font-size:12px" placeholder="Example:&#10;/sitemap.xml&#10;/mydir/myfile.xml" name="cf_textarea_custompaths"><?php if(get_option('cf_textarea_custompaths')) echo get_option('cf_textarea_custompaths'); ?></textarea>
                <br>
                <span style="font-size:10px">One path by line, full path must be provided, without your website's domain (eg: /sitemap.xml OR /mydir/mypath). Wildcard not allowed</span>
            </td>
         </tr>         
      </table>
      <br>
      <div class="alert notice">
         <p><span style="color:red">Take care:</span> by clearing the full cache, your server may experience heavy processing consumption because all content in cache will be cleared and all new requests needs to be re-processed by your server.</p>
      </div>
      <br>Purge Log:<br>
      <textarea style="width:90%;max-width:1200px;height:120px;font-size:12px" readonly><?php
        
            $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 200");
            foreach($records as $r) {
               print($r->time . "\t" . $r->text . "\r\n");
            }
           ?></textarea>

      <?php  submit_button(); ?>
   </form>
   <br>Developed by LuizBossoi - luizbossoi.com.br
</div>
<script>
function ccfw_allcache(sender) {
   chk = sender.checked;
   document.getElementById("cf_check_homepage").checked = chk;
   document.getElementById("cf_check_postpage").checked = chk;
   document.getElementById("cf_check_httphttps").checked = chk;
}
</script>
<?php
   } }
?>