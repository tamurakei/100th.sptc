<?php
/*
Plugin Name: Customise Plugin
Plugin URI: https://github.com/tamurakei/
Description: Small Customize
Author: tamurakei
Version: 1.0
Author URI: https://github.com/tamurakei/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html
  Copyright 2013-2015 wokamoto (email : tamura.kei@gmail.com)

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function add_custom_column( $defaults ) {
	$defaults['addcat'] = '提供者';
	return $defaults;
}
add_filter('manage_data_columns', 'add_custom_column');

function add_custom_column_id($column_name, $id) {
	if( $column_name == 'player' ) {
		$terms = $terms = get_the_terms( $id, 'player' );
		$cnt = 0;
		foreach($terms as $var) {
			echo $cnt != 0 ? ", " : "";
			echo "<a href=\"" . get_admin_url() . "edit.php?player=" . $var->slug . "&post_type=data" . "\">" . $var->name . "</a>";
		++$cnt;
		}
	}
}
add_action('manage_data_posts_custom_column', 'add_custom_column_id', 10, 2);
