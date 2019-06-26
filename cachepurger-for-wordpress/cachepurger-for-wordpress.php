<?php 
   /*
   Plugin Name: CF CachePurger for WordPress
   Description: CachePurger is a plugin for Wordpress websites running CloudFlare. It purges the cache in CloudFlare website using their API when a post is saved.
   Author: Luiz Bossoi
   Version: 1.0
   Author URI: http://luizbossoi.com.br
   */

function purgeCache($path, $zone_id) {
    $cf_check_allcache  = get_option('cf_check_allcache');
    if($cf_check_allcache=='true') {
        $data = array('purge_everything'=>true);
        addLog('Paths: - entire cache -');  
    } else {
        $data   = array("files" => $path);
        if(sizeof($path)<=0) { addLog('Nothing to clear'); return false; }
        addLog('Paths: ' . implode(', ',$path));  
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
    if(isset($arr_result['success'])) addLog('Cache purged'); else addLog('ERROR: ' . print_r($arr_result['errors'], true));
}

function getZoneID($domain) {
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

function human_filesize($bytes, $decimals = 2) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

function addLog($message) {
    $plugin_path	= dirname ( __FILE__ );
    if(file_exists($plugin_path . "/purge.log")==false) { fopen($plugin_path . "/purge.log", 'w') or die("Can't create logfile"); }
    file_put_contents($plugin_path . "/purge.log", date('Y-m-d H:i:s') .  "\t" . $message . "\n", FILE_APPEND  );
}

function clearCache($post_id) {
    $purge_paths    = array();
    $post_url	    = rtrim(get_permalink($post_id),'/');
    
    // cloudflare settings
    $cf_key_value       = get_option('cf_key_value');
    $cf_email_value     = get_option('cf_email_value');
    
    // cache settings
    $cf_check_homepage  = get_option('cf_check_homepage');
    $cf_check_postpage  = get_option('cf_check_postpage');
    $cf_check_httphttps = get_option('cf_check_httphttps');
    $cf_check_allcache  = get_option('cf_check_allcache');
    
    $arr_url	= parse_url($post_url);
    $domain		= str_replace('www.','', $arr_url['host']);
    $domain = 'traderlife.com.br';

    if($cf_check_homepage=='true' || $cf_check_allcache=='true') { array_push($purge_paths, get_site_url() . "/"); array_push($purge_paths, get_site_url()); }
    if($cf_check_postpage=='true' || $cf_check_allcache=='true') { array_push($purge_paths, $post_url); array_push($purge_paths, $post_url . "/"); }
    
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
        addLog("Post edited/added, need to purge cache...");
        $zone_id = getZoneID($domain);
        if($zone_id==false) {
            addLog("ERROR: Zone domain not found: $domain");
        } else {
            addLog("Zone ID $zone_id found, requesting purge... ");
            
            if(purgeCache($purge_paths, $zone_id)) {
              // 
            }
        }
    }
    
    addLog('--------------------------------------');
}

function cachepurger_register_settings() {
    // save cloudflare settings
    add_option( 'cf_key_value', sanitize_text_field($_POST['cf_key_value']));
    register_setting( 'cachepurger_options_group', 'cf_key_value' );
    
    add_option( 'cf_email_value', sanitize_text_field($_POST['cf_email_value']));
    register_setting( 'cachepurger_options_group', 'cf_email_value' );
    
    // save caching settings
    add_option( 'cf_check_homepage', sanitize_text_field($_POST['cf_check_homepage']));
    register_setting( 'cachepurger_options_group', 'cf_check_homepage' );

    add_option( 'cf_check_postpage', sanitize_text_field($_POST['cf_check_postpage']));
    register_setting( 'cachepurger_options_group', 'cf_check_postpage' );

    add_option( 'cf_check_httphttps', sanitize_text_field($_POST['cf_check_httphttps']));
    register_setting( 'cachepurger_options_group', 'cf_check_httphttps' );

    add_option( 'cf_check_allcache', sanitize_text_field($_POST['cf_check_allcache']));
    register_setting( 'cachepurger_options_group', 'cf_check_allcache' );
}

function cachepurger_register_options_page() {
    add_options_page('CloudFlare CachePurger', 'CF CachePurger', 'manage_options', 'cf-cachepurger', 'cachepurger_options_page');
}


// triggers
add_action( 'admin_init', 'cachepurger_register_settings' );
add_action( 'admin_menu', 'cachepurger_register_options_page' );
add_action( 'save_post', 'clearCache' );
add_action( 'admin_notices',  'admin_notices' );
add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'salcode_add_plugin_page_settings_link' );


function salcode_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' . admin_url( 'options-general.php?page=cf-cachepurger' ) . '">' . __('Settings') . '</a>';
    return $links;
}

function admin_notices() {
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

function cachepurger_options_page() { 
// check if user has permission

if (current_user_can('activate_plugins')==false && current_user_can('edit_theme_options')==false && current_user_can('manage_options')==false) { 
    print('<div class="alert notice"><p><span style="color:red">Access Denied:</span> Unfortunately, your user does not have access to this plugin page. Please use an administrator account to make this changes</p></div>');
} else {
?>
<div>
   <?php screen_icon(); ?>
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
            <td scope="row"><input type="checkbox" name="cf_check_allcache" id="cf_check_allcache" onclick="allcache(this)" value="true" <?php if(get_option('cf_check_allcache')) echo 'checked'; ?>></td>
            <td>All cache <span style="color:red;font-size:10px">(take care)</span></td>
         </tr>
      </table>
      <br>
      <div class="alert notice">
         <p><span style="color:red">Take care:</span> by clearing the full cache, your server may experience heavy processing consumption because all content in cache will be cleared and all new requests needs to be re-processed by your server.</p>
      </div>
      <br>Purge Log:<br>
      <textarea style="width:90%;max-width:1200px;height:120px;font-size:12px" readonly><?php
         if(file_exists(dirname ( __FILE__ ) . "/purge.log")) {
         $lines=array();
         $fp = fopen(dirname ( __FILE__ ) . "/purge.log", "r");
         while(!feof($fp))
         {
            $line = fgets($fp, 4096);
            array_push($lines, $line);
            if (count($lines)>16)
                array_shift($lines);
         }
         fclose($fp);
         $lines = array_reverse($lines);
         foreach($lines as $l) {
         	trim(print($l));
         }
         }
           ?></textarea>
      <?php if(file_exists(dirname ( __FILE__ ) . "/purge.log")) { ?>
      <br>
      <span style="color:#999999;font-size:10px">(Log size: <?php  echo human_filesize(filesize(dirname ( __FILE__ ) . "/purge.log")); ?> )</span>
      <?php } ?>
      <?php  submit_button(); ?>
   </form>
   <br>Developed by LuizBossoi - luizbossoi.com.br
</div>
<script>
function allcache(sender) {
   chk = sender.checked;
   document.getElementById("cf_check_homepage").checked = chk;
   document.getElementById("cf_check_postpage").checked = chk;
   document.getElementById("cf_check_httphttps").checked = chk;
}
</script>
<?php
   } }
?>