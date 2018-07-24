<?php
/*
Plugin Name: CachePurger for WordPress
Description: CachePurger is a plugin for Wordpress websites running CloudFlare. It purges the cache in CloudFlare website using their API when a post is saved.
Plugin URI: http://luizbossoi.com.br
Author: Luiz Bossoi
Version: 1.0
Author URI: http://luizbossoi.com.br
*/

function purgeCache($path, $zone_id) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/27321d1a882be12898fb6897224a6899/purge_cache");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = [
            'X-Auth-Email:' . get_option('cf_email_value'),
            'X-Auth-Key: ' . get_option('cf_key_value'),
            'Content-Type: application/json'
        ];

	$paths = array($path);

	// purge without www
	if(strpos($path,'www.')!==false) array_push($paths, array(str_replace('www.','',$path)) );

	// purge https
	if(strpos($path,'http:')!==false) array_push($paths, array(str_replace('http:','https:',$path)) );

	// purge http
	if(strpos($path,'https:')!==false) array_push($paths, array(str_replace('https:','http:',$path)) );

	// purge home
	array_push($paths, array(get_home_url()));
	array_push($paths, array(get_home_url() .'/'));

        $data = array("files" => $paths);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

	$arr_result = json_decode($result, true);
	if(isset($arr_result['success'])) return true; else return false;
}

function getZoneID($domain) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $headers = [
            'X-Auth-Email:' . get_option('cf_email_value'),
            'X-Auth-Key: ' . get_option('cf_key_value'),
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

        $arr_result = json_decode($result, true);
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


function clearCache() {
  	$plugin_path	= dirname ( __FILE__ );
	$post_url	= get_permalink($post_id);

	$cf_key_value	= get_option('cf_key_value');
	$cf_email_value	= get_option('cf_email_value');
	$arr_url	= parse_url($post_url);
	$domain		= str_replace('www.','',$arr_url['host']);

	if(strlen($cf_key_value)>0 && strlen($cf_email_value)>0) {
		file_put_contents($plugin_path . "/purge.log", date('Y-m-d H:i:s') .  "\tPurge Requested: " . $post_url . "\n", FILE_APPEND  );
		$zone_id = getZoneID($domain);
		if($zone_id==false) {
			file_put_contents($plugin_path . "/purge.log", date('Y-m-d H:i:s') .  "\tZone domain not found: $domain \n", FILE_APPEND  );
		} else {
			file_put_contents($plugin_path . "/purge.log", date('Y-m-d H:i:s') .  "\tZone ID $zone_id found, requesting purge... \n", FILE_APPEND  );

			if(purgeCache($post_url, $zone_id)) {
				file_put_contents($plugin_path . "/purge.log", date('Y-m-d H:i:s') .  "\tRequest to CloudFlare done. \n", FILE_APPEND  );
			}
		}
	}

	file_put_contents($plugin_path . "/purge.log", "---------------------------------------------------------------------\n", FILE_APPEND  );
}

function cachepurger_register_settings() {
   add_option( 'cf_key_value', addslashes($_POST['cf_key_value']));
   register_setting( 'cachepurger_options_group', 'cf_key_value', 'cachepurger_callback' );

   add_option( 'cf_email_value', addslashes($_POST['cf_email_value']));
   register_setting( 'cachepurger_options_group', 'cf_email_value', 'cachepurger_callback' );
}

function cachepurger_register_options_page() {
  add_options_page('CloudFlare CachePurger', 'CF CachePurger', 'manage_options', 'cachepurger', 'cachepurger_options_page');
}


add_action( 'admin_init', 'cachepurger_register_settings' );
add_action( 'admin_menu', 'cachepurger_register_options_page');
add_action( 'save_post', 'clearCache' );
add_action( 'admin_notices',  'admin_notices' );

function admin_notices() {
        $cf_key_value   = get_option('cf_key_value');
        $cf_email_value = get_option('cf_email_value');
	$screen 	= get_current_screen();
	if ( $screen->parent_base == 'edit' && (strlen($cf_key_value)<=0 || strlen($cf_email_value)<=0) ) {
   ?>
   <div class="error notice">
      <p>CachePurger for Wordpress is not configured properly, new posts or post updates will not be cleared from cache, please <a href="options-general.php?page=cachepurger">click here</a> to fix this issue.</p>
   </div>
   <?php
  }}

?><?php function cachepurger_options_page()
{
?>
  <div>
  <?php screen_icon(); ?>
  <h2>CachePurger for Wordpress</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'cachepurger_options_group' ); ?>
  <p>Inform your CloudFlare's API key and account holder's e-mail below to make cache purge runs automatically when a new post is saved (new post / post edit)</p>
  <table style="width:40%;max-width:650px;min-width:550px">
  <tr valign="top">
  <th scope="row"><label for="cf_email_name">E-mail Address:</label></th>
  <td><input type="text" id="cf_email_value" name="cf_email_value" value="<?php echo get_option('cf_email_value'); ?>" style="width:100%;"/></td>
  </tr>
  <tr valign="top">
  <th scope="row"><label for="cf_key_name">Global API Key:</label></th>
  <td><input type="text" id="cf_key_value" name="cf_key_value" value="<?php echo get_option('cf_key_value'); ?>" style="width:100%;"/></td>
  </tr>
  </table>
  <br>Purge Log:<br>

  <textarea style="width:80%;max-width:800px;height:120px;font-size:12px" readonly><?php
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

  <?php  submit_button(); ?>
  </form>

  <br>Developed by LuizBossoi - luizbossoi.com.br
  </div>
<?php
} ?>
