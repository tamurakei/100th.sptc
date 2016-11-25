<?php
/*
Plugin Name: CSV Post Manager
Plugin URI: http://www.cmswp.jp/plugins/csv_post_manager/
Description: This plugin adds the functionality to manage posts by using csv files.
Author: Hiroaki Miyashita
Version: 1.1.7
Author URI: http://www.cmswp.jp/
*/

/*  Copyright 2011 - 2012 Hiroaki Miyashita

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class csv_post_manager {

	function csv_post_manager() {
		add_action( 'init', array(&$this, 'csv_post_manager_init') );
		add_action( 'admin_init', array(&$this, 'csv_post_manager_admin_init') );
		add_action( 'admin_print_scripts', array(&$this, 'csv_post_manager_admin_scripts') );
		add_action( 'admin_menu', array(&$this, 'csv_post_manager_admin_menu') );
		add_filter( 'plugin_action_links', array(&$this, 'csv_post_manager_plugin_action_links'), 10, 2 );
	}
		
	function csv_post_manager_init() {
		if ( function_exists('load_plugin_textdomain') ) :
			if ( !defined('WP_PLUGIN_DIR') ) 
				load_plugin_textdomain('csv-post-manager', str_replace( ABSPATH, '', dirname(__FILE__) ) );
			else
				load_plugin_textdomain('csv-post-manager', false, dirname( plugin_basename(__FILE__) ) );
		endif;
				
		add_action( 'csv_post_manager_job', array(&$this, 'csv_post_manager_job') );
	}
	
	function csv_post_manager_admin_init() {
		if ( strstr($_SERVER['REQUEST_URI'], 'wp-admin/plugins.php') && ((isset($_GET['activate']) && $_GET['activate'] == 'true') || (isset($_GET['activate-multi']) && $_GET['activate-multi'] == 'true') ) ) :
			$options  = get_option('csv_post_manager_data');
			if( empty($options) ) $options = $this->csv_post_manager_install_data();
		endif;

		if( strstr($_SERVER['REQUEST_URI'], 'csv-post-manager') )
			$this->csv_post_manager_action();
	}
	
	function csv_post_manager_install_data() {
		delete_option('csv_post_manager_data');
		
		$options = array();
		$options['automatic_update']['file_path'] = ABSPATH;
	
		update_option('csv_post_manager_data', $options);
		
		return $options;
	}
	
	function csv_post_manager_admin_scripts() {
		if (strpos($_SERVER['REQUEST_URI'], 'csv-post-manager') !== false ) :
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-textarearesizer', '/'.PLUGINDIR.'/csv-post-manager/js/jquery.textarearesizer.js', array('jquery'));
		endif;
	}
	
	function csv_post_manager_admin_menu() {
		global $menu, $current_user;
		$options = get_option('csv_post_manager_data');

		add_options_page(__('CSV Post Manager', 'csv-post-manager'), __('CSV Post Manager', 'csv-post-manager'), 'manage_options', basename(__FILE__), array(&$this, 'csv_post_manager_admin'));
	}
	
	function csv_post_manager_plugin_action_links($links, $file){
		static $this_plugin;

		if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

		if( $file == $this_plugin ){
			$settings_link = '<a href="options-general.php?page=csv-post-manager.php">' . __('Settings', 'csv-post-manager') . '</a>';
			$links = array_merge( array($settings_link), $links);
		}
		return $links;
	}
	
	function csv_post_manager_action() {
		global $wp_version, $wpdb;
		$options = get_option('csv_post_manager_data');

		$_POST = stripslashes_deep($_POST);

		if ( !empty($_POST['csv_post_manager_post_importer_submit']) ) :
			if ( $_FILES['csvfile']['tmp_name'] ) :
				@set_time_limit(0);
				require_once( ABSPATH . WPINC . '/post.php');

				if ( is_numeric($_POST['setting']) ) :
					$setting = explode(',', $options['setting'][(int)$_POST['setting']]);
					$setting = array_map('trim', $setting);
				else :
					$_POST['skip_first_data'] = 1;
				endif;
				
				$row = 1;
				$handle = fopen($_FILES['csvfile']['tmp_name'], "r");
				if ( empty($setting) ) :
					$data = $this->fgetExcelCSV($handle, null, ',', '"');
					$setting = $data;
					fseek($handle, 0);
				endif;
				
				global $wp_taxonomies;
				if ( is_array($wp_taxonomies) && is_array($setting) ) :
					foreach ( $wp_taxonomies as $taxonomy => $values ) :
						if ( in_array($taxonomy, $setting) ) $taxonomies[] = $taxonomy;
					endforeach;
				endif;
				
				if ( !empty($options['global_settings']['hierarchical_delimiter']) ) :
					$delimiter = $options['global_settings']['hierarchical_delimiter'];
				else :
					$delimiter = "\\";
				endif;

				$_enc_to='UTF-8';
				mb_detect_order('UTF-8,SJIS-win,eucJP-win,SJIS,EUC-JP');
				$_enc_from=mb_detect_order();
				mb_regex_encoding("UTF-8");
				mb_convert_variables($_enc_to,$_enc_from,$setting);
				while (($data = $this->fgetExcelCSV($handle, null, ',', '"')) !== false) :
					$primary_key = '';
					$primary_val = '';
					if ( $row == 1 && !empty($_POST['skip_first_data']) ) :
						$row++;
						continue;
					endif;

					$post_media_thumbnail = '';
					unset($post, $post_meta, $post_media, $set_taxonomies);
					mb_convert_variables($_enc_to,$_enc_from,$data);
					if ( count($setting) != count($data) ) {
						$message = 'mismatch'; break;
					}

					for ($i=0; $i<count($setting); $i++) {
						$setting[$i] = trim($setting[$i]);
						if ( in_array($setting[$i], array('ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content',
														  'post_title', 'post_category', 'post_excerpt', 'post_status',
														  'comment_status', 'ping_status', 'post_password', 'post_name',
														  'to_ping', 'pinged', 'post_modified', 'post_modified_gmt',
														  'post_content_filtered', 'post_parent', 'guid', 'menu_order',
														  'post_type', 'comment_count', 'member_access_visibility')) ) :
							$post[$setting[$i]] = trim($data[$i]);
						elseif ( isset($taxonomies) && is_array($taxonomies) && in_array($setting[$i], $taxonomies) ) :
							$tax_array = explode( ',', trim($data[$i], " \n\t\r\0\x0B,") );
							if ( !empty($tax_array) && is_array($tax_array) ) :
								$new_tax_array = array();
								foreach ( $tax_array as $tax ) :
									$hierarchical_taxes = mb_split('['.preg_quote($delimiter).']', $tax);

									$j=0;
									$term_parent_id = 0;
									foreach ( $hierarchical_taxes as $hierarchical_tax ) :
										$j++;
										if ( empty($hierarchical_tax) ) continue;

										preg_match('/\[([^\]]+)\]/', trim($hierarchical_tax), $matches);
										if ( !empty($matches) ) :
											$hierarchical_tax = preg_replace('/\[[^\]]+\]/', '', trim($hierarchical_tax));
											$hierarchical_tax_slug = $matches[1];
										else :
											$hierarchical_tax_slug = '';
										endif;
										
										$term['term_id'] = $wpdb->get_var( $wpdb->prepare( "SELECT tt.term_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy as tt ON tt.term_id = t.term_id WHERE t.name = %s AND tt.taxonomy = %s AND tt.parent=%d", str_replace('&', '&amp;', $hierarchical_tax), $setting[$i], $term_parent_id ));

										if ( is_int($hierarchical_tax) ) :
											$new_tax_array[] = $hierarchical_tax;
										elseif ( !empty($term['term_id']) && ($j==count($hierarchical_taxes) || !empty($options['global_settings']['set_all_hierarchical_taxonomies'])) ) :
											if ( is_taxonomy_hierarchical($setting[$i]) ) :
												$new_tax_array[] = $term['term_id'];
											else :
												$new_tax_array[] = $hierarchical_tax;
											endif;
											$term_parent_id = $term['term_id'];
										else :
											if ( empty($term['term_id']) ) :
												$term = wp_insert_term($hierarchical_tax, $setting[$i], array('slug'=>$hierarchical_tax_slug, 'parent'=>$term_parent_id));
												if ( empty($hierarchical_tax_slug) && !empty($options['global_settings']['taxonomy_automatic_slug_id']) ) :
													wp_update_term($term['term_id'], $setting[$i], array('slug'=>$term['term_id']));
												endif;
											endif;

											if ( $j==count($hierarchical_taxes) || !empty($options['global_settings']['set_all_hierarchical_taxonomies']) ) :
												if ( is_taxonomy_hierarchical($setting[$i]) ) :
													$new_tax_array[] = $term['term_id'];
												else :
													$new_tax_array[] = $hierarchical_tax;
												endif;
											endif;
											$term_parent_id = $term['term_id'];
											delete_option($setting[$i]."_children");
										endif;
										unset($term);
									endforeach;
								endforeach;
								$data[$i] = implode(',', $new_tax_array);
							endif;
							$set_taxonomies[$setting[$i]] = trim($data[$i]);
						else :
							if ( preg_match('/%%$/', $setting[$i]) ) :
								$primary_key = preg_replace('/%%$/','',$setting[$i]);
								$primary_val = trim($data[$i]);
							endif;
							if ( preg_match('/post_media\#\#$/', $setting[$i]) ) :
								$post_media_thumbnail = trim($data[$i]);
								$post_media[str_replace('#','',$setting[$i])][] = trim($data[$i]);
							elseif ( preg_match('/post_media\#$/', $setting[$i]) ) :
								$post_media[str_replace('#','',$setting[$i])] = explode(',', trim($data[$i]));
							elseif ( preg_match('/\*$/', $setting[$i]) ) :
								$post_meta[str_replace('*','',$setting[$i])] = explode(',', trim($data[$i]));
							else :
								$post_meta[preg_replace('/%%$/','',$setting[$i])][0] = trim($data[$i]);
							endif;
						endif;
					}
					if ( !empty($primary_key) && !empty($primary_val) ) :
						$posts = get_posts(array('meta_key'=>$primary_key, 'meta_value'=>$primary_val,'numberposts'=>1));
						$post['ID'] = $posts[0]->ID;
					endif;
					
					if ( empty($post['ID']) ) :
						$post_id = wp_insert_post($post);
					else :
						if ( get_post($post['ID']) ) :
							if ( $post['post_status'] == 'delete' ) :
								$result = get_children(array('post_parent'=>$post['ID'],'post_type'=>'attachment','numberposts'=>-1));
								if ( is_array($result) ) :
									foreach( $result as $val ) :
										wp_delete_attachment($val->ID);
									endforeach;
								endif;
								wp_delete_post($post['ID'], true);
								$row++;
								continue;
							else :
								$post_id = wp_update_post($post);
							endif;
						else :
							$post_id=0;
						endif;
					endif;

					if ( $post_id ) :
						if ( is_array($set_taxonomies) ) :
							foreach ( $set_taxonomies as $set_taxonomy => $term_ids ) :
								wp_set_post_terms($post_id, $term_ids, $set_taxonomy);
							endforeach;
						endif;
						if ( !empty($post_meta) && is_array($post_meta) ) :
							foreach( $post_meta as $key => $vals ) :
								if ( empty($_POST['blank']) && empty($vals[0]) ) continue;
								foreach ( $vals as $i => $val ) :
									if ( count($vals) == 1 ) :
										if ( !add_post_meta( $post_id, $key, $val, true ) ) :
											if ( count(get_post_meta($post_id, $key, false))>1 ) :
												delete_post_meta($post_id, $key);
												add_post_meta( $post_id, $key, $val );
											else :
												update_post_meta( $post_id, $key, $val );
											endif;
										endif;
									elseif ( count($vals) > 1 ) :
										if ( $i==0 ) :
											delete_post_meta($post_id, $key);
										endif;
										add_post_meta( $post_id, $key, $val );
									endif;
								endforeach;
							endforeach;
						endif;
						if ( !empty($post_media) && is_array($post_media) ) :
							$uploads = wp_upload_dir();
							foreach( $post_media as $key => $vals ) :
								if ( empty($vals) ) continue;
								foreach ( $vals as $i => $val ) :
									$pathinfo = pathinfo($val);
									$object = array(
										'post_title' => $pathinfo['filename'],
										'post_parent' => $post_id
									);
									$media_id = $this->process_attachment($object, $val);
									if ( $post_media_thumbnail == $val )
										set_post_thumbnail($post_id, $media_id);
								endforeach;
							endforeach;
						endif;
						$row++;
					endif;
				endwhile;
				fclose($handle);
			else :
				$message = 'failed';
			endif;
			$row--;
			if ( !empty($_POST['skip_first_data']) ) $row--;
			if ( empty($message) ) $message = 'executed';
			if ( empty($_POST['csv_post_manager_job']) ) :
				wp_redirect(get_option('siteurl').'/wp-admin/options-general.php?page='.$_REQUEST['page'].'&message='.$message.'&row='.$row);
				exit();		
			endif;
		elseif ( !empty($_POST['csv_post_manager_post_exporter_submit']) ) :
			@set_time_limit(0);
			$filename = "cpm".date('Ymd').'.csv';
			header("Accept-Ranges: none");
			header("Content-Disposition: attachment; filename=$filename");
			header('Content-Type: application/octet-stream');
			
			if ( is_numeric($_POST['setting']) ) $setting = array_map('trim', explode(',', $options['setting'][(int)$_POST['setting']]));
			if ( empty($setting) ) exit();

			$tax_query = array();
			$cat = '';
			if ( !empty($_REQUEST['taxonomy']) && !empty($_REQUEST['terms']) && substr($wp_version, 0, 3) >= '3.1' ) :
				$tax_query = array(array('taxonomy' => $_REQUEST['taxonomy'], 'terms' => explode(',', $_REQUEST['terms'])));
			elseif ( trim($_REQUEST['taxonomy']) == 'category' && !empty($_REQUEST['terms']) ) :
				$cat = $_REQUEST['terms'];
			endif;
			$posts = query_posts(array('post_type' => $_REQUEST['post_type'], 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'post_status' => 'publish,pending,draft,future,private', 'tax_query' => $tax_query, 'cat' => $cat));

			global $wp_taxonomies;
			$taxonomies = $h_taxonomies = $n_taxonomies = array();
			if ( is_array($wp_taxonomies) && is_array($setting) ) :
				foreach ( $wp_taxonomies as $taxonomy => $values ) :
					if ( in_array($taxonomy, $setting) ) :
						$taxonomies[] = $taxonomy;
					endif;
				endforeach;
			endif;

			if ( !empty($_REQUEST['include_names']) ) :
				$names = '';
				for ($i=0; $i<count($setting); $i++) :
					switch( !empty($_REQUEST['encode']) ) :
						case 'SJIS': $item = mb_convert_encoding($setting[$i], 'SJIS-win', 'UTF-8'); break;
						case 'EUC-JP': $item = mb_convert_encoding($setting[$i], 'eucJP-win', 'UTF-8'); break;
					endswitch; 
					$names .= trim($item).",";
				endfor;
				echo trim($names, ',*')."\n";
			endif;
			
			if ( is_array($posts) ) :
				foreach($posts as $post) :
					$output = '';
					for ($i=0; $i<count($setting); $i++) :
						$setting[$i] = trim($setting[$i],'*');
						if ( isset($post->{$setting[$i]}) ) : $item = $post->{$setting[$i]};
						elseif ( in_array($setting[$i], $taxonomies) ) :
							$term_names = array();
							$terms = get_the_terms( $post->ID, $setting[$i] );
							if ( is_array($terms) ) :
								foreach($terms as $term) :
									if ( $term->name != rawurldecode($term->slug) ) $term_name = $term->name.'['.rawurldecode($term->slug).']';
									else $term_name = $term->name;
									$term_names[] = str_replace('&amp;','&',$this->csv_post_manager_return_terms($term, $term_name));
								endforeach;
							endif;
							$item = implode(',', $term_names);
						elseif ( $setting[$i]=='post_media#' ) :
							$result = get_children(array('post_parent'=>$post->ID,'post_type'=>'attachment','numberposts'=>-1));
							$media_ids = array();
							if ( is_array($result) ) :
								foreach( $result as $val ) :
									list($src, $width, $height) = wp_get_attachment_image_src($val->ID,'full');
									$media_ids[] = $src;
								endforeach;
							endif;
							$item = implode(',', $media_ids);
						else :
							$item = get_post_meta($post->ID, $setting[$i], false);
							if ( is_array($item) ) $item = implode(',', $item);
						endif;
						switch( !empty($_REQUEST['encode']) ) :
							case 'SJIS': $item = mb_convert_encoding($item, 'SJIS-win', 'UTF-8'); break;
							case 'EUC-JP': $item = mb_convert_encoding($item, 'eucJP-win', 'UTF-8'); break;
						endswitch; 
						$output .= '"'.str_replace('"','""',$item).'",';
					endfor;
					$output = trim($output, ',')."\n";
					echo $output;
					@ob_flush();
					flush();
				endforeach;
			endif;
			exit();		
		elseif ( !empty($_POST['csv_post_manager_setting_options_submit']) ) :
			unset($options['setting']);
			$j = 0;
			for($i=0;$i<count($_POST["setting"]);$i++) {
				if( !empty($_POST["setting"][$i]) ) {
					$options['setting'][$j] = $_POST["setting"][$i];
					$j++;
				}
			}			
			update_option('csv_post_manager_data', $options);
			$message = __('Options updated.', 'csv-post-manager');
			wp_redirect(get_option('siteurl').'/wp-admin/options-general.php?page='.$_REQUEST['page'].'&message=updated');
			exit();
		elseif( !empty($_POST["global_settings_submit"]) ) :
			unset($options['global_settings']);

			foreach($_POST as $key => $val) :
				if( $key != "global_settings_submit" ) $options['global_settings'][$key] = $val;
			endforeach;
			update_option('csv_post_manager_data', $options);
			wp_redirect(get_option('siteurl').'/wp-admin/admin.php?page='.$_REQUEST['page'].'&message=updated');
			exit();
		elseif( !empty($_POST["automatic_update_submit"]) ) :
			unset($options['automatic_update']);

			foreach($_POST as $key => $val) :
				if( $key != "automatic_update_submit" ) $options['automatic_update'][$key] = $val;
			endforeach;
			if ( !empty($options['automatic_update']['status']) ) :
				$recurrence = $options['automatic_update']['recurrence'];
				wp_clear_scheduled_hook('csv_post_manager_job');
				wp_schedule_event(time(), $recurrence, 'csv_post_manager_job');
			else :
				wp_clear_scheduled_hook('csv_post_manager_job');
			endif;
			update_option('csv_post_manager_data', $options);
			wp_redirect(get_option('siteurl').'/wp-admin/admin.php?page='.$_REQUEST['page'].'&message=updated');
			exit();
		elseif ( !empty($_POST['csv_post_manager_export_options_submit']) ) :
			$filename = "cpm".date('Ymd');
			header("Accept-Ranges: none");
			header("Content-Disposition: attachment; filename=$filename");
			header('Content-Type: application/octet-stream');
			echo maybe_serialize($options);
			exit();
		elseif ( !empty($_POST['csv_post_manager_import_options_submit']) ) :
			if ( is_uploaded_file($_FILES['cpmfile']['tmp_name']) ) :
				ob_start();
				readfile ($_FILES['cpmfile']['tmp_name']);
				$import = ob_get_contents();
				ob_end_clean();
				$import = maybe_unserialize($import);
				update_option('csv_post_manager_data', $import);
				wp_redirect(get_option('siteurl').'/wp-admin/options-general.php?page='.$_REQUEST['page'].'&message=imported');
				exit();
			endif;
		elseif ( !empty($_POST['reset_options_submit']) ) :
			$options = $this->csv_post_manager_install_data();
			wp_redirect(get_option('siteurl').'/wp-admin/admin.php?page='.$_REQUEST['page'].'&message=reset');
			exit();
		elseif ( !empty($_POST['csv_post_manager_delete_options_submit']) ) :
			delete_option('csv_post_manager_data');
			wp_redirect(get_option('siteurl').'/wp-admin/options-general.php?page='.$_REQUEST['page'].'&message=deleted');
			exit();
		endif;
	}
	
	function csv_post_manager_return_terms($term, $term_name='') {
		$options = get_option('csv_post_manager_data');
		
		if ( !empty($options['global_settings']['hierarchical_delimiter']) ) :
			$delimiter = preg_replace('/\|.*$/','',$options['global_settings']['hierarchical_delimiter']);
		else :
			$delimiter = "\\";
		endif;

		if ( $term->parent!=0 ) :
			$term_parent = get_term($term->parent, $term->taxonomy);
			if ( $term_parent->name != rawurldecode($term_parent->slug) ) $term_parent_name = $term_parent->name.'['.rawurldecode($term_parent->slug).']';
			else $term_parent_name = $term_parent->name;
			$term_name = $this->csv_post_manager_return_terms($term_parent, $term_parent_name.$delimiter.$term_name);
		endif;		

		return $term_name;
	}
	
	function csv_post_manager_admin() {
		global $current_user, $wp_version;

		if ( !defined('WP_PLUGIN_DIR') )
			$plugin_dir = str_replace( ABSPATH, '', dirname(__FILE__) );
		else
			$plugin_dir = dirname( plugin_basename(__FILE__) );

		$options = get_option('csv_post_manager_data');
		if(!$options) $options = array();
		
		if ( isset($_GET['message']) && $_GET['message'] == 'mismatch' )
			$message = __('Items do not match the csv.', 'csv-post-manager');
		if ( isset($_GET['message']) && $_GET['message'] == 'failed' )
			$message = __('Posts update failed.', 'csv-post-manager');
		if ( isset($_GET['message']) && $_GET['message'] == 'executed' )
			$message = sprintf(__('%d Posts updated.', 'csv-post-manager'), $_REQUEST['row']);
		if ( isset($_GET['message']) && $_GET['message'] == 'updated' )
			$message = __('Options updated.', 'csv-post-manager');
		if ( isset($_GET['message']) && $_GET['message'] == 'imported' )
			$message = __('Options imported.', 'csv-post-manager');
		if ( isset($_GET['message']) && $_GET['message'] == 'reset' )
			$message = __('Options reset.', 'csv-post-manager');
		if ( isset($_GET['message']) && $_GET['message'] == 'deleted' )
			$message = __('Options deleted.', 'csv-post-manager');
?>

<style type="text/css">
div.grippie {
background:#EEEEEE url(<?php echo '../' . PLUGINDIR . '/' . $plugin_dir . '/js/'; ?>grippie.png) no-repeat scroll center 2px;
border-color:#DDDDDD;
border-style:solid;
border-width:0pt 1px 1px;
cursor:s-resize;
height:9px;
overflow:hidden;
}
.resizable-textarea textarea {
display:block;
margin-bottom:0pt;
}
</style>
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('textarea:not(.processed)').TextAreaResizer();
	});
</script>

<?php if ( !empty($message) ) : ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php endif; ?>
<div class="wrap">
<div id="icon-plugins" class="icon32"><br/></div>
<h2><?php _e('Csv Post Manager', 'csv-post-manager'); ?></h2>

<br class="clear"/>

<div id="poststuff" style="margin-top:10px;">
<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('CSV Post Importer', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post" action="?options-general.php&page=csv-post-manager.php" enctype="multipart/form-data">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><?php _e('If you do not specify the following setting, the first line will be treated as the names.', 'csv-post-manager'); ?></p>
<p><select name="setting">
<option value=""><?php _e('None', 'csv-post-manager'); ?></option>
<?php
	for ( $i = 0; $i < count($options['setting']); $i++ ) {
?>
<option value="<?php echo $i; ?>"><?php echo sprintf(__('SETTING #%d', 'csv-post-manager'), $i); ?></option>
<?php
	}
?>
</select> <input type="file" name="csvfile" /></p>
<p><label><input type="checkbox" name="skip_first_data" value="1" /> <?php _e('Skip the first data', 'csv-post-manager'); ?></label></p>
<p><label><input type="checkbox" name="blank" value="1" /> <?php _e('Insert custom field data even if they are empty', 'csv-post-manager'); ?></label></p>
<p><input type="submit" name="csv_post_manager_post_importer_submit" value="<?php _e('Import Posts &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('CSV Post Exporter', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<dl>
<dt><label for="post_type"><?php _e('Post Type', 'csv-post-manager'); ?></label></dt>
<dd><input type="text" name="post_type" id="post_type" value="post" /></dd>
<dt><label for="taxonomy"><?php _e('Taxonomy', 'csv-post-manager'); ?></label></dt>
<dd><input type="text" name="taxonomy" id="taxonomy" value="category" /></dd>
<dt><label for="terms"><?php _e('Term ID (comma-separated)', 'csv-post-manager'); ?></label></dt>
<dd><input type="text" name="terms" id="terms" value="" /></dd>
<dt><label for="setting"><?php _e('Settings', 'csv-post-manager'); ?></label></dt>
<dd><select name="setting" id="setting">
<?php
	for ( $i = 0; $i < count($options['setting']); $i++ ) {
?>
<option value="<?php echo $i; ?>"><?php echo sprintf(__('SETTING #%d', 'csv-post-manager'), $i); ?></option>
<?php
	}
?>
</select></dd>
<dt><label for="encode"><?php _e('Encode', 'csv-post-manager'); ?></label></dt>
<dd><select name="encode" id="encode">
<option value="UTF-8">UTF-8</option>
<option value="SJIS">SJIS</option>
<option value="EUC-JP">EUC-JP</option>
</select></label></dd>
<dd><label><input type="checkbox" name="include_names" value="1" /> <?php _e('Include names as the first line.', 'csv-post-manager'); ?></label></dd>
</dl>
</td></tr>
<tr><td>
<p><input type="submit" name="csv_post_manager_post_exporter_submit" value="<?php _e('Export Posts &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('CSV Setting Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr>
<td><p><?php _e('Please input the comma-separated names of csv data.', 'csv-post-manager'); ?></p>
<p><?php _e('post_title must be included.', 'csv-post-manager'); ?></p>
<p><?php _e('If ID is included, the data will be overwritten.', 'csv-post-manager'); ?></p>
<p><?php _e('Following names are treated as post data: ', 'csv-post-manager'); ?><br />
ID, post_author, post_date, post_date_gmt, post_content, post_title, post_category, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, comment_count, member_access_visibility</p>
<p><?php _e('Others are treated as post meta data.', 'csv-post-manager'); ?></p></td>
</tr>
<?php
	for ( $i = 0; $i < count($options['setting'])+1; $i++ ) {
?>
<tr><td>
<p><strong><?php echo sprintf(__('SETTING #%d', 'csv-post-manager'), $i); ?></strong>
<textarea name="setting[<?php echo $i; ?>]" class="resizable large-text" id="setting_<?php echo $i; ?>" rows="5" cols="80"><?php if ( !empty($options['setting'][$i]) ) echo stripcslashes($options['setting'][$i]); ?></textarea></p>
</td></tr>
<?php
	}
?>
<tr><td>
<p><input type="submit" name="csv_post_manager_setting_options_submit" value="<?php _e('Update Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Global Settings', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr>
<th><label for="hierarchical_delimiter"><?php _e('Hierarchical Delimiter', 'csv-post-manager'); ?></label></th>
<td><input type="text" name="hierarchical_delimiter" id="hierarchical_delimiter" class="regular-text" value="<?php echo isset($options['global_settings']['hierarchical_delimiter']) ? esc_attr($options['global_settings']['hierarchical_delimiter']): "\\"; ?>" /></td>
</tr>
<tr>
<th><label for="taxonomy_automatic_slug_id"><?php _e('Taxonomy Automatic Slug ID', 'csv-post-manager'); ?></label></th>
<td><input type="checkbox" name="taxonomy_automatic_slug_id" id="taxonomy_automatic_slug_id" value="1" <?php checked($options['global_settings']['taxonomy_automatic_slug_id'],1); ?> /> <?php _e('Use', 'csv-post-manager'); ?></td>
</tr>
<tr>
<th><label for="set_all_hierarchical_taxonomies"><?php _e('Set all hierarchical taxonomies', 'csv-post-manager'); ?></label></th>
<td><input type="checkbox" name="set_all_hierarchical_taxonomies" id="set_all_hierarchical_taxonomies" value="1" <?php checked($options['global_settings']['set_all_hierarchical_taxonomies'],1); ?> /> <?php _e('Use', 'csv-post-manager'); ?></td>
</tr>
<tr>
<td colspan="2">
<p><input type="submit" name="global_settings_submit" value="<?php _e('Update Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Automatic Update Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr>
<th><label for="status"><?php _e('Status', 'csv-post-manager'); ?></label></th>
<td><input type="checkbox" name="status" id="status" value="1" <?php checked($options['automatic_update']['status'],1); ?> /> <?php _e('Run', 'csv-post-manager'); ?></td>
</tr>
<tr>
<th><label for="file_path"><?php _e('File Path', 'csv-post-manager'); ?></label></th>
<td><input type="text" name="file_path" id="file_path" class="large-text" value="<?php echo esc_attr($options['automatic_update']['file_path']); ?>" /></td>
</tr>
<tr>
<th><label for="recurrence"><?php _e('Recurrence', 'csv-post-manager'); ?></label></th>
<td>
<?php $schedules = wp_get_schedules(); ?>
<select name="recurrence">
<?php
	if ( is_array($schedules) ) :
		if ( empty($options['automatic_update']['recurrence']) ) $options['automatic_update']['recurrence'] = 'daily';
		foreach ( $schedules as $key => $val ) :
?>
<option value="<?php echo $key; ?>"<?php selected($key, $options['automatic_update']['recurrence']); ?>><?php echo $val['display']; ?></option>
<?php
		endforeach;
	endif;
?>
</select> 
</p></td>
</tr>
<tr>
<th><label for="delete_file"><?php _e('Delete', 'csv-post-manager'); ?></label></th>
<td><input type="checkbox" name="delete_file" id="delete_file" value="1" <?php checked($options['automatic_update']['delete_file'],1); ?> /> <?php _e('Delete the file after the update.', 'csv-post-manager'); ?></td>
</tr>
<tr>
<td colspan="2">
<p><input type="submit" name="automatic_update_submit" value="<?php _e('Update Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Export Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="csv_post_manager_export_options_submit" value="<?php _e('Export Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Import Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post" action="?page=csv-post-manager.php" enctype="multipart/form-data" onsubmit="return confirm('<?php _e('Are you sure to import options? Options you set will be overwritten.', 'csv-post-manager'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="file" name="cpmfile" /> <input type="submit" name="csv_post_manager_import_options_submit" value="<?php _e('Import Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Reset Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post" onsubmit="return confirm('<?php _e('Are you sure to reset options? Options you set will be reset to the default settings.', 'csv-post-manager'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="reset_options_submit" value="<?php _e('Reset Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

<div class="postbox closed">
<div class="handlediv" title="<?php _e('Click to toggle', 'csv-post-manager'); ?>"><br /></div>
<h3><?php _e('Delete Options', 'csv-post-manager'); ?></h3>
<div class="inside">
<form method="post" action="?page=csv-post-manager.php" onsubmit="return confirm('<?php _e('Are you sure to delete options? Options you set will be deleted.', 'csv-post-manager'); ?>');">
<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr><td>
<p><input type="submit" name="csv_post_manager_delete_options_submit" value="<?php _e('Delete Options &raquo;', 'csv-post-manager'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
</div>

</div>

<script type="text/javascript">
// <![CDATA[
<?php if ( version_compare( substr($wp_version, 0, 3), '2.7', '<' ) ) { ?>
jQuery('.postbox h3').prepend('<a class="togbox">+</a> ');
<?php } ?>
jQuery('.postbox div.handlediv').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); } );
jQuery('.postbox h3').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); } );
jQuery('.postbox.close-me').each(function(){
jQuery(this).addClass("closed");
});
//-->
</script>

</div>
<?php
	}

	function csv_post_manager_job() {
		$options  = get_option('csv_post_manager_data');
	
		$_POST['csv_post_manager_job'] = 1;
		$_POST['csv_post_manager_post_importer_submit'] = 1;
		$_FILES['csvfile']['tmp_name'] = $options['automatic_update']['file_path'];
		if ( file_get_contents($options['automatic_update']['file_path']) ) :
			$this->csv_post_manager_action();
			if ( !empty($options['automatic_update']['delete_file']) ) :
				unlink($options['automatic_update']['file_path']);
			endif;
		endif;
	}
	
	function fgetExcelCSV(&$fp , $length = null, $delimiter = ',' , $enclosure = '"') {
		$line = fgets($fp);
		if($line === false) {
			return false;
		}
		$bytes = preg_split('//' , trim($line));
		array_shift($bytes);array_pop($bytes);
		$cols = array();
		$col = '';
		$isInQuote = false;
		while($bytes) {
			$byte = array_shift($bytes);
			if($isInQuote) {
				if($byte == $enclosure) {
					if( isset($bytes[0]) && $bytes[0] == $enclosure) {
						$col .= $byte;
						array_shift($bytes);
					} else {
						$isInQuote = false;
					}
				} else {
					$col .= $byte;
				}
			} else {
				if($byte == $delimiter) {
					$cols[] = $col;
					$col = '';
				} elseif($byte == $enclosure && $col == '') {
					$isInQuote = true;
				} else {
					$col .= $byte;
				}
			}
			while(!$bytes && $isInQuote) {
				$col .= "\n";
				$line = fgets($fp);
				if($line === false) {
					$isInQuote = false;
				} else {
					$bytes = preg_split('//' , trim($line));
					array_shift($bytes);array_pop($bytes);
				}
			}
		}
		$cols[] = $col;
		return $cols;
	}
	
	function process_attachment( $post, $url ) {
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return $upload;

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', 'error' );

		$post['guid'] = $upload['url'];

		$post_id = wp_insert_attachment( $post, $upload['file'] );
		require_once(ABSPATH . '/wp-admin/includes/image.php');
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name = basename( $parts['basename'], ".{$parts['extension']}" );

			$parts_new = pathinfo( $upload['url'] );
			$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$this->url_remap[$parts['dirname'] . '/' . $name] = $parts_new['dirname'] . '/' . $name_new;
		}

		return $post_id;
	}

	function fetch_remote_file( $url, $post ) {
		$file_name = basename( $url );

		$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );
		if ( $upload['error'] )
			return new WP_Error( 'upload_dir_error', $upload['error'] );

		$headers = wp_get_http( $url, $upload['file'] );

		if ( ! $headers ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', 'error' );
		}

		if ( $headers['response'] != '200' ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', 'error' );
		}

		$filesize = filesize( $upload['file'] );

		if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', 'error' );
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', 'error' );
		}

		$this->url_remap[$url] = $upload['url'];
		$this->url_remap[$post['guid']] = $upload['url'];
		if ( isset($headers['x-final-location']) && $headers['x-final-location'] != $url )
			$this->url_remap[$headers['x-final-location']] = $upload['url'];

		return $upload;
	}
}
$csv_post_manager = new csv_post_manager();
?>