<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class KUSANAGI_Replace {
	static function replace( $content ) {
		$replaces = array(
### REPLACES ARRAY ###
		);

		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			foreach ( $replaces as $reg => $val ) {
				$reg = preg_quote( $reg, '#' );
				$content = preg_replace( "#$reg#", $val, $content );
			}
		}
		return $content;
	}
}
