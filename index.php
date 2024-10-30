<?php
/*
Plugin Name: Hide(disable) any Widget temporarily
Description: <i>(Note: This is a <a href="http://codesphpjs.blogspot.com/2014/10/nsp-non-slowing-plugins-for-wordpress.html" target="_blank">Non-Slowing</a> Plugin )</i> After activation, you will have an additional checkbox, so you can temporarily hide(disable) any widget from visitors.   (P.S.  OTHER MUST-HAVE PLUGINS FOR EVERYONE: http://bitly.com/MWPLUGINS  )
Author: TazoTodua
Author URI: http://www.protectpages.com/profile
Plugin URI: http://www.protectpages.com/
Donate link: http://paypal.me/tazotodua
License: free
Version: 1.1
*/
if ( ! defined( 'ABSPATH' ) ) exit; //Exit if accessed directly


//add "DISABLE THIS WIDGET" checkbox, to temporarily hide it.
if (!class_exists('EnableDisableWidget__TT')){
	class EnableDisableWidget__TT {
		public function __construct() {	add_filter( 'widget_form_callback', array( $this, 'myForm' ), 6, 2 );	add_filter('widget_update_callback', array ($this,'myUpdate'), 9, 2); 	add_filter( 'dynamic_sidebar_params', array( $this, 'myApply' ), 9 );   add_filter( 'dynamic_sidebar_params', array( $this, 'myApply2' ), 9 ); }
		//add form into ADMIN SIDEBARS
		public function myform( $instance, $widget ) {  if( !isset($instance['wONOFF__TT']) ) { $instance['wONOFF__TT'] = null; }	?>	
			<div style="font-size:0.8em;font-style:italic;background-color:#e7e7e7;margin:10px 0 0;padding:0 2px;text-align: right;">Hide this widget temporarily: <input type="checkbox" value="offf" name="widget-<?php echo $widget->id_base; ?>[<?php echo $widget->number; ?>][wONOFF__TT]" <?php if ('offf'==$instance['wONOFF__TT']){echo 'checked="checked"';}?>/> </div>	<?php return $instance;
		}
		public function myUpdate($instance,$new_instance) {$instance['wONOFF__TT']=wp_strip_all_tags($new_instance['wONOFF__TT']);return $instance;}
		// change front-end output 
		public function myApply( $params ) {	global $wp_registered_widgets;	$widget_id = $params[0][ 'widget_id' ];	$widget = $wp_registered_widgets[ $widget_id ];
				// because the Widget Logic plugin changes this structure - how selfish of it!
				if ( !( $default_name_fixed = $widget['callback'][0]->option_name ) ) { $default_name_fixed = $widget['callback_wl_redirect'][0]->option_name; }
			$option_name = get_site_option( $default_name_fixed ); $number = $widget['params'][0]['number'];
			if( isset( $option_name[$number][ 'wONOFF__TT' ] ) && !empty( $option_name[$number][ 'wONOFF__TT' ] ) ) {
				// find the end of the class= part and replace with new 
				$params[0]['before_widget'] = preg_replace('/"\>/', ' wONOFF__TT_'.$option_name[$number]['wONOFF__TT'].'">', $params[0]['before_widget'], 1);
			} 
			return $params;
		}
		//BASED ON FOUND CLASSNAME,  SHOW or HIDE
		public function myApply2( $params ) {	global $wp_registered_widgets; 
			$incl_nm= $params[0]['before_widget']; if (stripos($incl_nm,'wONOFF__TT_offf') !== false && !is_admin())  { $params=array(); $params['blabla']=''; }  return $params;
		}	
	} $enabledisablewidget__TT = new EnableDisableWidget__TT();
}



								//===========  links in Plugins list ==========//
								add_filter( "plugin_action_links_".plugin_basename( __FILE__ ), function ( $links ) {  $links[] = '<a href="http://paypal.me/tazotodua">Donate</a>';  return $links; } );