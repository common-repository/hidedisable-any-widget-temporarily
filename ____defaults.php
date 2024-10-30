<?php 
	// ====================================================================================================================== //
	// =====================================   My default library for all of my plugins  ==================================== //
	// =========================== ( Current plugin might be using several methods from here )  ============================= //
	// ====================================================================================================================== //

if(trait_exists('default_methods__ProtectPages_com')) return;

trait default_methods__ProtectPages_com{
	
	public function __construct($arg1=false){
		// #### dont use __FILE__ & __DIR__ here, because trait is being included in other classes. Better is reflection ####
		$reflection = (new \ReflectionClass(__CLASS__));
		// set plugin's main file path
		$this->plugin_FILE		= $reflection->getFileName();
		// set plugin's dir path
		$this->plugin_DIR		= dirname($this->plugin_FILE);
		// set plugin's dir URL
		$this->plugin_DIR_URL	= plugin_dir_url($this->plugin_FILE);
		$this->homeUrl			= home_url('/');
		$this->domainCurrent	= $_SERVER['HTTP_HOST']; 
		$this->domainReal		= $this->getDomain($this->homeUrl); 
		$this->httpsCurrent		= (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || $_SERVER['SERVER_PORT']==443) ? 'https://' : 'http://' ); 
		$this->httpsReal		= preg_replace('/(http(s|):\/\/)(.*)/i', '$1', $this->homeUrl);
		$this->homeUrlStripped	= $this->stripUrlPrefixes( $this->homeUrl);
		$this->homePath			= home_url('/', 'relative');
		$this->urlAfterHome		= str_ireplace($this->homePath, '',  $_SERVER["REQUEST_URI"]);
		$this->pathAfterHome	= parse_url($this->urlAfterHome, PHP_URL_PATH);
		$this->is_localhost 	=  (stripos($this->homeUrl,'://127.0.0.1')!==false || stripos($this->homeUrl,'://localhost')!==false );
		$this->is_settings_page = false; 
		// initial variables
		$this->my_plugin_vars(1);
		$this->initial_static_settings = array('show_opts'=>false, 'required_role'=>'manage_options', 'managed_from'=>'multisite', 'allowed_on'=>'both'); 
		$this->initial_user_options= array();
		if(method_exists($this, 'declare_static_settings')) {  $this->declare_static_settings();  } 
		$this->static_settings = $this->initial_static_settings + $this->static_settings;
		$this->my_plugin_vars(2);																		//setup 2nd initial variables
		$this->opts= $this->refresh_options();															//setup final variables
		$this->check_if_pro_plugin();
		$this->__construct_my();																		//all other custom construction hooks
		$this->plugin_page_url= ( $this->opts['managed_from_primary_site'] ? network_admin_url( 'settings.php') : admin_url( 'options-general.php') ). '?page='.$this->plugin_slug; 
		$this->plugin_page_url1=( $this->opts['managed_from_primary_site'] ? network_admin_url( 'admin.php') : admin_url( 'admin.php') ). '?page='.$this->plugin_slug;  
		
		//==== my other default hooks ===//
		// If plugin has options
		if($this->opts['show_opts']) { 
			//add admin menu
			add_action( ( $this->opts['managed_from_primary_site'] ? 'network_' : ''). 'admin_menu', function(){
				$menu_button_name = (array_key_exists('menu_button_name', $this->opts) ? $this->opts['menu_button_name'] : $this->opts['Name'] );
				if(array_key_exists('menu_button_level', $this->opts)){
					$menu_level=$this->opts['menu_button_level'];
				}
				if(!isset($menu_level) || $menu_level=="submenu")
					add_submenu_page($this->opts['menu_pagePHP'], $menu_button_name, $menu_button_name, $this->opts['required_role'] , $this->plugin_slug,  array($this, 'opts_page_output') );
				else 
					add_menu_page($menu_button_name, $menu_button_name, $this->opts['required_role'] , $this->plugin_slug,  array($this, 'opts_page_output'), $this->opts['menu_icon'] );
				// if target is custom link (not options page)
				if(array_key_exists('menu_button_link', $this->opts)){
					add_action( 'admin_footer', function (){
							?>
							<script type="text/javascript">
								jQuery('a.toplevel_page_<?php echo $this->plugin_slug;?>').attr('href','<?php echo $this->opts['menu_button_link'];?>').attr('target','_blank');  
							</script>
							<?php
						}
					);
				}
			} ); 
			//redirect to settings page after activation (if not bulk activation)
			add_action('activated_plugin', function($plugin) { if ( $plugin == plugin_basename( $this->plugin_FILE ) && !((new WP_Plugins_List_Table())->current_action()=='activate-selected')) { exit( wp_redirect($this->plugin_page_url.'&isactivation') ); } } ); 
		}
		// add Settings & Donate buttons in plugins list
		add_filter( (is_network_admin() ? 'network_admin_' : ''). 'plugin_action_links_'.plugin_basename($this->plugin_FILE),  function($links){
			if(!$this->has_pro_version)	{ $links[] = '<a href="'.$this->opts['donate_url'].'">'.$this->opts['menu_text']['donate'].'</a>'; }
			if($this->opts['show_opts']){ $links[] = '<a href="'.$this->plugin_page_url.'">'.$this->opts['menu_text']['settings'].'</a>';  }
			return $links; 
		});
		//translation hook
		add_action('plugins_loaded', array($this, 'load_textdomain') );
		//activation & deactivation (empty hooks by default. all important things migrated into `refresh_options`)
		register_activation_hook( $this->plugin_FILE, array($this, 'activate')   );
		register_deactivation_hook( $this->plugin_FILE, array($this, 'deactivate'));
		if(is_admin()) add_action( 'shutdown', array($this, 'my_shutdown_for_versioning'));
		
		// for backend ajax
		add_action( 'admin_enqueue_scripts',		array( $this, 'admin_scripts')	); 
		if($this->has_pro_version){
			add_action( 'wp_ajax_'.$this->plugin_slug_u,  		array( $this, 'backend_ajax_check_pro' )); 
		}
	}

	public function activate($network_wide){
		//if activation allowed from only on multisite or singlesite or Both?
		$die= $this->opts['allowed_on'] == 'both' ?  false :  (   ($this->opts['allowed_on'] =='multisite' && !$network_wide && is_multisite()) || ( $this->opts['allowed_on'] =='singlesite' && ($network_wide || is_network_admin()) )  ) ;
		if($die) {
			$text= '<h2>('.$this->opts['Name'].') '. $this->opts['menu_text']['activated_only_from']. ' <b style="color:red;">'.strtoupper($this->opts['allowed_on']).'</b> WordPress </h2>';
			die('<script>alert("'.strip_tags($text).'");</script>'.$text);
			return false;
		}
		if(method_exists($this, 'activation_funcs') ) {   $this->activation_funcs();  }
	}
	public function deactivate($network_wide){
		if(method_exists($this, 'deactivation_funcs') ) {   $this->deactivation_funcs();  }
	}

	public function my_plugin_vars($step){  
		//add my default values  
		if($step==1){
			$this->plugin_variables =  (include_once(ABSPATH . "wp-admin/includes/plugin.php")) ? get_plugin_data( $this->plugin_FILE) : array();
			$this->plugin_slug	=  sanitize_key($this->plugin_variables['TextDomain']);	
			$this->plugin_slug_u=  str_replace('-','_', $this->plugin_slug);					
			 
			$this->static_settings = $this->plugin_variables   +   array(
					'menu_text'			=> array(
						'donate'				=>__('Donate', 'default_methods__ProtectPages_com'),
						'settings'				=>__('Settings', 'default_methods__ProtectPages_com'),
						'open_settings'			=>__('You can access settings from dashboard of:', 'default_methods__ProtectPages_com'),
						'activated_only_from'	=>__('Plugin activated only from', 'default_methods__ProtectPages_com')
					),
					'lang'				=> $this->get_locale__SANITIZED(),
					'donate_url'		=> 'http://paypal.me/tazotodua',
					'mail_errors'		=> 'wp_plugin_errors@protectpages.com',
					'licenser_domain'	=> 'https://www.protectpages.com/',
					'musthave_plugins'	=> 'https://www.protectpages.com/blog/must-have-wordpress-plugins/',
			); 
			$this->static_settings = $this->static_settings   +		array(
				'purchase_url'			=> $this->static_settings['licenser_domain'].'?purchase_wp_plugin='.$this->plugin_slug,
				'purchase_check'		=> $this->static_settings['licenser_domain'].'?purchase_wp_check'
			);
		}
		elseif($step==2){
			$this->static_settings = $this->static_settings    +	array(
				'managed_from_primary_site'	=> $this->static_settings['managed_from']=='multisite' && is_multisite(),
				'menu_pagePHP'				=> $this->static_settings['managed_from']=='multisite' && is_multisite() ? 'settings.php' : 'options-general.php'
			);
		}
		
	}
			 
	
	//load translation
	public function load_textdomain(){
		load_plugin_textdomain( $this->plugin_slug, false, basename($this->plugin_DIR). '/lang/' );  		
	}
	
	//get latest options (in case there were updated,refresh them)
	public function refresh_options(){
		$this->opts	= $this->get_option_CHOSEN($this->plugin_slug, array()); 
		foreach($this->initial_user_options as $name=>$value){ if (!array_key_exists($name, $this->opts)) { $this->opts[$name]=$value;  $should_update=true; }  } 
		if(empty($this->opts['last_update_time'])) { $this->opts['last_update_time'] = time();   $should_update=true;  }
		if(isset($should_update)) {	$this->update_opts();	} 
		$this->opts = array_merge($this->opts, $this->static_settings);
		return $this->opts;
	}	
	
	// quick method to update this plugin's opts
	public function update_opts($opts=false){
		$this->update_option_CHOSEN($this->plugin_slug, ( $opts ? $opts : $this->opts) );
	}
	
	public function get_option_CHOSEN($optname,$default=false){
		return ( $this->static_settings['managed_from_primary_site'] ? get_site_option($optname,$default) :  get_option($optname,$default) );
	}
	public function update_option_CHOSEN($optname,$optvalue,$autoload=null){
		return ( $this->static_settings['managed_from_primary_site'] ? update_site_option($optname,$optvalue,$autoload) :  update_option($optname,$optvalue,$autoload) );
	}
	
	public function settings_page_part($type){ 
		$this->is_settings_page = true;
		
		if(!empty($_POST[$this->plugin_slug])) {
			$this->opts['last_update_time'] = time();
			$this->update_opts();
		}
		
		if($type=="start"){ ?>
			<div class="clear"></div>
			<div class="myplugin postbox wrap version_<?php echo (!$this->has_pro_version  ? "free" : ($this->is_pro ? "pro" : "not_pro") );?>">
				<h2 class="settingsTitle"><?php _e('Plugin Settings Page!', 'default_methods__ProtectPages_com');?></h2>
			<?php
		}
		elseif($type=="end"){ ?>
				<div class="newBlock additionals">
					<h4></h4> 
					<h3><?php _e('More Actions', 'default_methods__ProtectPages_com');?></h3>	
					<ul>
						<li>
						<p class="about-description"><?php printf(__('You can check other useful plugins at: <a href="%s">Must have free plugins for everyone</a>', 'default_methods__ProtectPages_com'),  $this->opts['musthave_plugins'] );  ?> </p>
						</li>
					</ul>
					<ul class="donations_block">
						<li><div class="welcome-icon welcome-widgets-menus"><?php printf(__('If you found this plugin useful, <a href="%s" target="_blank">donations</a> welcomed.', 'default_methods__ProtectPages_com'), $this->opts['donate_url']);?></div></li>
					</ul> 
				</div>
				<style>
				.myplugin * {position:relative;} 
				.myplugin { max-width:100%; display:flex; flex-wrap:rap; justify-content:center; flex-direction:column; padding: 20px; }
				.myplugin >h2 {text-align:center;}
				.myplugin h3 {text-align:center;} 
				.myplugin table tr { border-bottom: 1px solid #cacaca; }
				.myplugin table td {min-width:160px;}
				.myplugin p.submit {text-align: center;}
				zz.myplugin input[type="text"]{width:100%;}
				.additionals{ text-align:center; margin:5px;  padding: 5px; background: #efeab7;     padding: 5px 0 0 20px;}
				.additionals a{font-weight:bold;font-size:1.1em; color:blue;}  
				
				.myplugin.version_pro .donations_block, .myplugin.version_not_pro .donations_block { display:none; }
				</style>
				<div class="clear"></div>
				<?php if($this->is_pro == false) {  $this->purchase_pro_block(); } ?>
			</div>
		<?php
		}
	}
	
	public function admin_scripts(){ 
		if($this->is_this_settings_page()){
			// jquery ui
			$handle = 'jquery-effects-core';
			if ( !wp_script_is( $handle, "enqueued" ) ){
				wp_enqueue_script( $handle );
			}

			// jquery dialog
			$handle = 'jquery-ui-dialog';
			if ( !wp_script_is( $handle, "enqueued" ) ){
				wp_enqueue_script( $handle );
			}
			$handle = 'wp-jquery-ui-dialog';
			if ( !wp_style_is( $handle, "enqueued" ) ){
				wp_enqueue_style( $handle );
			}
			
			
			// spin.js
			$handle = 'spin.js';
			if ( !wp_script_is( $handle, "registered" ) ){
				wp_register_script( $handle, 'https://cdnjs.cloudflare.com/ajax/libs/spin.js/2.3.2/spin.min.js',  array('jquery'), '2.3.2', true );
			}
			if ( !wp_script_is( $handle, "enqueued" ) ){
				wp_enqueue_script( $handle );
			}
			
			/*
			// touch-punch.js
			$handle = 'touch-punch.js';
			if ( !wp_script_is( $handle, "registered" ) ){
				wp_register_script( $handle, 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js',  array('jquery'),  '0.2.3', true );
			}
			if ( !wp_script_is( $handle, "enqueued" ) ){
				wp_enqueue_script( $handle );
			}
			
			// my style
			$handle = $this->plugin_slug . '-admin-styles';
			if ( !wp_style_is( $handle, "registered" ) ){
				wp_register_style( $handle, $this->base_url .'/assets/css/admin-styles.css',  array('wp-jquery-ui-dialog'),  '1.0.0', "all" );
			}
			if ( !wp_style_is( $handle, "enqueued" ) ){
				wp_enqueue_style( $handle );
			}	
			*/
		}
	}
	
	
	// common funcs
	public function  str_replace_first($from, $to, $content, $type="plain"){
		if($type=="plain"){
			$pos = strpos($content, $needle);
			if ($pos !== false) {
				$content = substr_replace($content, $replace, $pos, strlen($needle));
			}
			return $content;
		}
		elseif($type=="regex"){
			$from = '/'.preg_quote($from, '/').'/';
			return preg_replace($from, $to, $content, 1);
		}
	}
	
	public function safemode_basedir_set(){
		return ( ini_get('open_basedir') || ini_get('safe_mode')) ; 
	}
	
	public function is_activation(){
		return (isset($_GET['isactivation']));
	}
	
	public function reload_without_query($params=array(), $js_redir=true){
		$url = remove_query_arg( array_merge($params,array('isactivation') ) );
		if ($js_redir=="js"){ $this->js_redirect($url); }
		else { $this->php_redirect($url); }
	}
	
	public function if_activation_reload_with_message($message){
		if($this->is_activation()){
			echo '<script>alert(\''.$message.'\');</script>';
			$this->reload_without_query();
		}
	}
	
	public function getDomain($url){
		return preg_replace('/http(s|):\/\/(www.|)(.*?)(\/.*|$)/i', '$3', $url);
	}
	
	public function adjustedUrlPrefixes($url){
		if(strpos($url, '://') !== false){
			return preg_replace('/^(http(s|)|):\/\/(www.|)/i', 'https://www.', $url);
		}
		else{
			return 'https://www.'.$url;
		}
	}
	
	public function stripUrlPrefixes($url){
		return preg_replace('/http(s|):\/\/(www.|)/i', '',  $url);
	}
	
	public function stripDomain($url){
		return str_replace( $this->adjustedUrlPrefixes($this->domainReal), '', $this->adjustedUrlPrefixes($url) );
	}
	
	public function try_increase_exec_time($seconds){
		if( ! $this-> safemode_basedir_set() ) {
			set_time_limit($seconds);
			ini_set('max_execution_time', $seconds);
			return true;
		}
		return false;
	}
	
	public function is_this_settings_page(){
		return (stripos(get_current_screen()->base, 'settings_page_'.$this->plugin_slug) !== false);
	}
	
	public function send_error_mail($error){
		wp_mail($this->opts['mail_errors'], 'wp plugin error at '. home_url(),  (is_array($error) ? print_r($error, true) : $error)  );
	}
	
	public function my_shutdown_for_versioning(){
		if(empty($this->opts['last_version']) || $this->opts['last_version'] != $this->opts['Version']){
			$this->opts['last_version'] = $this->opts['Version'];
			$this->update_opts();
		}
	}

	public function randomString($length = 11) {
		return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1, $length);
	}
	
	public function get_locale__SANITIZED(){
		return ( get_locale() ? "en" : preg_replace('/_(.*)/','',get_locale()) ); //i.e. 'en'
	}
	
	public function readUrl( $url){
		return  wp_remote_retrieve_body(  wp_remote_get( $url )  );
	}
				
	
	private function set_cookie($name, $val, $time_length = 86400, $httponly=true, $path=false){
		$site_urls = parse_url( (function_exists('home_url') ? home_url() : $_SERVER['SERVER_NAME']) );
		$real_domain = $site_urls["host"];
		$path = $path ?: ( (!empty($this) && property_exists($this,'homePath') ) ?  $this->homePath : '/');
		$domain = (substr($real_domain, 0, 4) == "www.") ? substr($real_domain, 4) : $real_domain;
		setcookie ( $name , $val , time()+$time_length, $path = $path, $domain = $domain ,  $only_on_https = FALSE,  $httponly  );
	}
	
	public function MessageAgainstMaliciousAttempt(){
		return 'Well... I know that these words wont change you, but I\'ll do it again: Developers try to create a balance & harmony in internet, and some people like you try to steal things from other people. Even if you can it, please dont repeat that';
	}
	
	public function FullIframeScript(){ ?>
		<script>
		function MakeIframeFullHeight_tt(iframeElement, cycling, overwrite_margin){
			cycling= cycling || false;
			overwrite_margin= overwrite_margin || false;
			iframeElement.style.width	= "100%";
			var ifrD = iframeElement.contentDocument || iframeElement.contentWindow.document;
			var mHeight = parseInt( window.getComputedStyle( ifrD.documentElement).height );  // Math.max( ifrD.body.scrollHeight, .. offsetHeight, ....clientHeight,
			var margins = ifrD.body.style.margin + ifrD.body.style.padding + ifrD.documentElement.style.margin + ifrD.documentElement.style.padding;
			if(margins=="") { margins=0; if(overwrite_margin) {  ifrD.body.style.margin="0px"; } }
			(function(){
			   var interval = setInterval(function(){
				if(ifrD.readyState  == 'complete' ){
					setTimeout( function(){ 
						if(!cycling) { setTimeout( function(){ clearInterval(interval);}, 500); }
						iframeElement.style.height	= (parseInt(window.getComputedStyle( ifrD.documentElement).height) + parseInt(margins)+1) +"px"; 
					}, 200 );
				} 
			   },200)
			})();
				//var funcname= arguments.callee.name;
				//window.setTimeout( function(){ console.log(funcname); console.log(cycling); window[funcname](iframeElement, cycling); }, 500 );	
		}
		</script> 
		<?php
	}
	
	
	
	
	
	public function cookieFuncs(){
	?>
	<script>
	// ================= create, read,delete cookies  =================
	function Is_Cookie_Set_tt(cookiename) { return document.cookie.indexOf('; '+cookiename+'=');}
		
	function createCookie_tt(name,value,days) {
		var expires = "";
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + (days*24*60*60*1000));
			expires = "; expires=" + date.toUTCString();
		}
		document.cookie = name + "=" + (value || "")  + expires + "; path=/";
	}
	function readCookie_tt(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	}
	function eraseCookie_tt(name) {   
		document.cookie = name+'=; Max-Age=-99999999;';  
	}
			function setCookie(name,value,days) { createCookie(name,value,days); }
			function getCookie(name) { return readCookie(name); }
			function setCookieOnce(name) { createCookie(name, "okk" , 1000); }
	// ===========================================================================================
	</script>
	<?php
	}
	
	
	public function startSessionIfNotStarted(){
		if(session_status() == PHP_SESSION_NONE)  { $this->session_being_opened = true; session_start();  } 
	}
	public function endSessionIfWasStarted( $method=2){
		if(session_status() != PHP_SESSION_NONE && property_exists($this,"session_being_opened") )  {  
			if($method==1) session_destroy(); 
			elseif($method==2) session_write_close();   
			elseif($method==3) session_abort();      
		}
	}
	
	public function rmdir_recursive($path){
		if(!empty($path) && is_dir($path) ){
			$dir  = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS); //upper dirs not included,otherwise DISASTER HAPPENS :)
			$files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $f) {if (is_file($f)) {unlink($f);} else {$empty_dirs[] = $f;} } if (!empty($empty_dirs)) {foreach ($empty_dirs as $eachDir) {rmdir($eachDir);}} rmdir($path);
		}
		//include_once(ABSPATH.'/wp-admin/includes/class-wp-filesystem-base.php');
		//\WP_Filesystem_Base::rmdir($fullPath, true);
	}


	public function ListAllInDir($path, $only_files = false) {
		$all_list = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
				( $only_files ? \RecursiveIteratorIterator::LEAVES_ONLY : \RecursiveIteratorIterator::SELF_FIRST )
		);
		$files = array(); 
		foreach ($all_list as $file)
			$files[] = $file->getPathname();

		return $files;
	}

	
	public function replace_occurences_in_dir($dir_base, $from, $to, $exts=array('php','shtml') ){
		$dirIterator = $this->ListAllInDir($dir_base, true);
		foreach($dirIterator as $idx => $value) {
			$filext = pathinfo($value, PATHINFO_EXTENSION);
			if( in_array($filext,  $exts ) ){
				$cont = file_get_contents($value);
				if(stripos($cont, $from) !== false){
					$new_cont = str_replace($from, $to, file_get_contents($value) );
					file_put_contents($value, $new_cont);
				}
			}
		}
	}

			
	public function startsWith($haystack, $needle) {   return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false; }
	public function endsWith($haystack, $needle) { $length = strlen($needle);  return $length === 0 ||  (substr($haystack, -$length) === $needle); }
	public function contains($content, $needle, $case_sens= true){ return ($case_sens ? strpos($content, $needle) : stripos($content, $needle)) !== false;  }

	// ================ flash rules ================= //
	// unique func to flush rewrite rules when needed
	public function flush_rules_if_needed($temp_key=false){
		$optname = 'last_flush_updates__'.$this->plugin_slug;
		$updates_opt = get_site_option($optname, array());
		
		// lets check if refresh needed
		$key="b".get_current_blog_id()."_". md5(    (empty($temp_key) ?  "sample" : ( stripos($temp_key, basename(__DIR__)) !== false ? md5(filemtime($temp_key)) : $temp_key ))    );
		if(empty($updates_opt['last_updates'][$key]) || $updates_opt['last_updates'][$key] < $this->opts['last_update_time']){
			$updates_opt['last_updates'][$key] = $this->opts['last_update_time'];
			$updates_opt['need_flush_reload'][$key] = true;
			$call_me = true;
		}
		elseif(!empty($updates_opt['need_flush_reload'][$key])){
			$updates_opt['need_flush_reload'][$key] = false;
			$call_me = true;
		}
		// final call
		if(isset($call_me)){
			update_site_option($optname, $updates_opt);
			$this->flush_rules(true);
		}
	}
	
	public function is_JSON_string($string){
	   return (is_string($string) && is_array(json_decode($string, true)));
	}

	public function flush_rules($redirect=false){
		flush_rewrite_rules();
		if($redirect) {
			if ($redirect=="js"){ $this->js_redirect(); }
			else { $this->php_redirect(); }
		}
	}
	public function js_redirect($url=false, $echo=true){
		$str = '<script>window.location = "'. ( $url ?: $_SERVER['REQUEST_URI'] ) .'";</script>';
		if($echo) { exit($str); }  else { return $str; }
	}
	public function php_redirect($url=false){
		header("location: ". ( $url ?: $_SERVER['REQUEST_URI'] ), true, 302); exit;  
	}
	
	public function js_redirect_message($message, $url=false){
		echo '<script>alert(\''.$message.'\');</script>';
		$this->js_redirect($url);
	}
	
	public function mkdir_recursive($dest, $permissions=0755, $create=true){
		if(!is_dir(dirname($dest))){ mkdir_recursive(dirname($dest), $permissions, $create); }  
		elseif(!is_dir($dest)){ mkdir($dest, $permissions, $create); }
		else{return true;}
	}
	// ================ flash rules ================= //


	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

	// ========= my functions for PRO plugins ========== //
	public function check_if_pro_plugin(){
		$this->is_pro = null;
		if( property_exists($this, "has_pro_version") ){
			//$this->has_pro_version = true;  // it is price of plugin
			$this->is_pro 	= false;
			if(file_exists(__DIR__.'/addon.php')){
				$ar= $this->get_license();
				if($ar['status']){
					$this->is_pro = true;
				}
			}
		}
		else{
			$this->has_pro_version = false;
		}
	}
	
	public function license_keyname(){
		return $this->plugin_slug_u ."_l_key";
	}
	
	public function get_license($key=false){
		$def_array = array(
			'status' => false,
			'key' => '',
		);
		$license_arr = get_site_option($this->license_keyname(), $def_array );
		return ($key ? $license_arr[$key] : $license_arr);
	}	
	
	public function update_license($val, $val1=false){
		if(is_array($val)){
			$array = $val;
		}
		else{
			$array= $this->get_license();
			$array[$val]=$val1;
		}
		update_site_option( $this->license_keyname(), $array );
	}
	

	
	public function get_check_answer($key){
		
		$this->info_arr		=  array('siteurl'=>home_url(), 'plugin_slug'=>$this->plugin_slug ) + $this->plugin_variables;
		
		$answer = 
			wp_remote_retrieve_body(
				wp_remote_post($this->opts['purchase_check'], 
					array(
						'method' => 'POST',
						'timeout' => 25,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(),
						'body' => array( 'key' => $key ) + $this->info_arr,
						'cookies' => array()
					)
				)
			);		
		return $answer;
	}
	
	public function backend_ajax_check_pro(){
		if(isset($_POST['check_key'])){
			$key = sanitize_text_field( $_POST['check_key'] );
			$answer = $this->get_check_answer($key);
			
			if(!$this->is_JSON_string($answer)){
				$result = array();
				$result['error'] = $answer;
			}
			else{
				$result = json_decode($answer, true);
			}
			//
			if(isset($result['valid'])){
				if($result['valid']){
					if(!empty($result['files'])){
						foreach($result['files'] as $name=>$val){
							if(stripos($name,'/')!== false || stripos($name,'..')!== false){
								$result['error'] = $answer;
								break;
							}
							else{
								$name = sanitize_file_name($name);
								file_put_contents(__DIR__.'/'.basename($name),  $val);
							}
						}
					}
					if(!isset($result['error'])){
						$this->update_license( 'status', true );
					}
				}
			}
			else{
				$result['error'] = $answer;
			}
			echo json_encode($result);
			
			if(isset($result['error'])) {
				$this->send_error_mail($result['error']);
			}
		}
		
		elseif(isset($_POST['save_results'])){
			
		}
		wp_die();
	}
	
	
	
	public function pro_field(){
		if($this->has_pro_version && !$this->is_pro){
			echo 'data-pro-overlay="pro_overlay"';
			//echo '<span class="pro_overlay overlay_lines"></span> ';
		}
	}
	
	public function purchase_pro_block(){ ?>
		<div class="pro_block">
			<style>
			.myplugin.version_free .get_pro_version, .myplugin.version_pro .get_pro_version{ display:none; }
			
			.myplugin .dialog_enter_key{ display:none; }
			
			.get_pro_version { line-height: 1.2; z-index: 1; background: #ff1818;  text-align: center; border-radius: 10px; display: inline-block;  position: fixed; bottom: 0px; right: 0; left: 0; padding: 10px 10px; max-width: 500px; margin: 0 auto; text-shadow: 0px 0px 6px white; }

			
			.get_pro_version .centered_div > span  { font-size: 1.5em; }
			.get_pro_version .centered_div > span  a { font-size: 1em; color: #7dff83;}
					
			.init_hidden{
				display:none;
			}
			
			
			#check_results{
				zzdisplay:inline;
				zzflex-direction:row;
				zzfont-style:italic;
			}

			#check_results .correct{
				background: #a8fba8;
			}

			#check_results .incorrect{
				background: pink;
			}

			#check_results span{
				padding:3px 5px;
			}
			
			.dialog_enter_key_content {
				display: flex; flex-direction: column; align-items: center;
			}
			.dialog_enter_key_content > *{
				margin: 10px ;
			}
			.or_enter_key_phrase{ font-style: italic; }
			
			[data-pro-overlay=pro_overlay]{
				pointer-events: none;
				cursor: default;
				position:relative;
				min-height: 2em;
				padding:5px;
			}
			[data-pro-overlay=pro_overlay]::before{ 
				content:"";
				width: 100%; height: 100%; position: absolute; background: black; opacity: 0.3; z-index: 1;  top: 0;   left: 0;
				background: url("https://ps.w.org/internal-functions-for-protectpages-com-users/trunk/assets/overlay-1.png"); 
			}
			[data-pro-overlay=pro_overlay]::after{ 
				content: "<?php _e('Only available in FULL VERSION', 'default_methods__ProtectPages_com');?>"; position: absolute; top: 0; left: 0; bottom: 0; right: 0; z-index: 3; overflow: hidden; font-size: 2em; color: red;
				text-shadow: 0px 0px 5px black; padding: 5px;
				opacity:1;   
				text-align: center; 
				animation-name: blinking;
				zzanimation-name: moving;
				animation-duration: 8s;
				animation-iteration-count: infinite;
				overflow:hidden;
				white-space: nowrap;
			}
			@keyframes blinking {
				0% {opacity: 0;}
				50% {opacity: 1;}
				100% {opacity: 0;}
			}
			@keyframes moving {
				0% {left: 30%;}
				40% {left: 100%;}
				100% {left: 0%;}
			}
			</style>
			<div class="get_pro_version">
				<span class="centered_div">
					<span class="purchase_phrase">
						<a id="purchase_key" href="<?php echo esc_url($this->opts['purchase_url']);?>" target="_blank"><?php _e('GET FULL VERSION', 'default_methods__ProtectPages_com');?></a> <span class="price_amnt">(<?php _e('only', 'default_methods__ProtectPages_com');?> <?php echo $this->has_pro_version;?>$)</span>
					</span>
					
					<span class="or_enter_key_phrase">
					<?php _e('or', 'default_methods__ProtectPages_com');?> <a id="enter_key"  href=""><?php _e('Enter License Key', 'default_methods__ProtectPages_com');?></a>
					</span>
				
				</span>
			</div>	
			
			<div class="dialog_enter_key">
				<div class="dialog_enter_key_content" title="Enter the purchased license key">
					<input id="key_this" class="regular-text" type="text" value="<?php echo $this->get_license('key');?>"  /> 
					<button id="check_key" ><?php _e( 'Check key', 'default_methods__ProtectPages_com' );?></button>
					<span id="check_results">
						<span class="correct init_hidden"><?php _e( 'correct', 'default_methods__ProtectPages_com' );?></span>
						<span class="incorrect init_hidden"><?php _e( 'incorrect', 'default_methods__ProtectPages_com' );?></span>
					</span>
				</div>
			</div>
		</div>
		<?php
		$this->plugin_scripts();
	}
	
	public function plugin_scripts(){
		?>
		<script> 
		function main_tt(){
			
			var this_action_name = '<?php echo $this->plugin_slug_u;?>';
			
			(function ( $ ) {
				$(function () {
					//$("#purchase").on("click", function(e){ this_name_tt.open_license_dialog(); } );
					$("#enter_key").on("click", function(e){ return this_name_tt.enter_key_popup(); } );
					$("#check_key").off().on("click", function(e){ return this_name_tt.check_key(); } );
				});
			})( jQuery );

			// Create our namespace
			this_name_tt = {
				
				spinner_initialized : false,
 
				InitializeSpinner: function() {
					
					// See all options definitions on the above source link
					var opts = {
						lines: 13, length: 38, width: 17, radius: 45, scale: 1, corners: 1, color: '#333333', fadeColor: '#e7e7e7', opacity: 0.25, rotate: 0, direction: 1, speed: 1, trail: 60, fps: 5, zIndex: 889999, className: 'spin-js-spinner', top: '50%', left: '50%', shadow: '0px 0px 5px black', position: 'fixed'
					};
					this.spinner_el = new Spinner(opts).spin();
					this.spinner_initialized = true;
					
				},
				
				reload_this_page : function(){
					window.location = window.location.href; 
				},
				
				/*
				*	Method to show/hide spinner
				*/
				spin: function(action) {
				
					// If it is the first call to spinner, then at first, it is initialized.
					if (!this.spinner_initialized) {
						this.InitializeSpinner();
					}
					
					// Check which action was called
					if (action == "show") {
						
						// We create a background and attach the spinner too, to the document.body
						var s = jQuery('<div class="spin-js">');
						s.append('<div id="blackground_spinner" style="top:0px; width:100%;height:100%;position:fixed;background:white; z-index:2; opacity:0.8;"></div>');
						s.append(this.spinner_el.el);
						jQuery("body").append(s);
						
					} else if (action == "hide") {
						
						// We remove the spinner & background
						jQuery("#blackground_spinner").remove();
						this.spinner_el.el.remove();
						
					}	
					
				},
				

				/*
				*	Method to check (using AJAX, which calls WP back-end) if inputed username is available
				*/
				enter_key_popup: function(e) {
					
					// Show jQuery dialog
					jQuery('.dialog_enter_key_content').dialog({
						modal: true,
						width: 500,
						close: function (event, ui) {
							//jQuery(this).remove();	// Remove it completely on close
						}
					});
					return false;
				},

				IsJsonString: function(str) {
					try {
						JSON.parse(str);
					} catch (e) {
						return false;
					}
					return true;
				},

				check_key : function(e) {
					 
					var this1 = this;
					
					var inp_value = jQuery("#key_this").val();
					
					if (inp_value == ""){  return;  }
 
					// Show spinner
					this.spin("show");

					jQuery.post(
						// Url to backend
						ajaxurl, 
						// Data to send
						{
							'action': this_action_name,
							'check_key': inp_value
						},
						// Function when request complete 
						function (answer) {
							// Hide spinner
							this1.spin("hide");
							
							if(typeof window.debug_this != "undefined"){  console.log(answer);  }
							  
							if(this1.IsJsonString(answer)){
								var new_res=  JSON.parse(answer);
								if(new_res.hasOwnProperty('valid')){
									if(new_res.valid){
										this1.show_green();
									}
									else{
										var reponse1 = JSON.parse(new_res.response);
										this1.show_red(reponse1.message);
									}
								}
								else {
									this1.show_red(new_res);
								}
							}
							else{
								this1.show_red(answer);
							}
						}
					);
					return false;
				},

				show_green : function(){
					jQuery("#check_results .correct").show();
					jQuery("#check_results .incorrect").hide();
					this.reload_this_page();
					//this.save_results();
				},
				
				show_red : function(e){
					jQuery("#check_results .correct").hide();
					jQuery("#check_results .incorrect").show();
					jQuery("#check_results .incorrect").html(e);
					
					/*
					var message = 'Your inputed username "' + tw_usr + '" is incorrect! \nPlease, change it.';
					// Show jQuery dialog
					jQuery('<div>' + message + '</div>').dialog({
						modal: true,
						width: 500,
						close: function (event, ui) {
							jQuery(this).remove();	// Remove it completely on close
						}
					});
					*/
				},
				

			};
		}
		main_tt();
		</script>
		
		<?php
	}
	
	// ================================================   ##end of default block##  ============================================= //
	// ========================================================================================================================== //
}
?>