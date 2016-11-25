<?php

class KUSANAGI_Image_Optimizer {

	public  $settings;
	private $default;

	public function __construct() {
		$this->settings = get_option( 'kusanagi-image-optimizer-settings', array() );

		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING && ! is_array( $this->settings ) ) { return; }
		$this->default = array(
			'enable_image_optimize' => 0,
			'jpeg_quality'          => 82,
			'max_image_width'       => 1280,
			'error_mes'             => false,
		);

		$this->settings = array_merge( $this->default, $this->settings );
		add_action( 'admin_init'           , array( $this, 'add_tab' ) );
		add_filter( 'wp_handle_upload'     , array( $this, 'fullsize_image_limiter' ) );
		add_filter( 'jpeg_quality'         , array( $this, 'jpeg_quality' ) );
		if ( isset( $_GET['tab'] ) && 'image-optimizer' == $_GET['tab'] ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}
	}


	public function add_tab() {
		global $WP_KUSANAGI;
		$WP_KUSANAGI->add_tab( 'image-optimizer', __( 'Image Optimizer', 'wp-kusanagi' ) );
	}
	
	
	public function enqueue() {
		global $WP_KUSANAGI;
		wp_enqueue_script( 'image-optimizer', WP_CONTENT_URL . '/mu-plugins/kusanagi-core/js/image-optimizer.js', array( 'jquery-ui-slider' ), $WP_KUSANAGI->version );
		wp_enqueue_style( 'jquery-ui', WP_CONTENT_URL . '/mu-plugins/kusanagi-core/css/jquey-ui-slider.css', array(), $WP_KUSANAGI->version );
	}

	
	public function save_options() {
		global $WP_KUSANAGI;

		$post_data = wp_unslash( $_POST );
		$settings = array();
		foreach ( $this->default as $key => $def ) {
			switch ( $key ) {
			case 'enable_image_optimize' :
				if ( ! isset( $post_data[$key] ) || ! is_numeric( $post_data[$key] ) ) {
					$settings[$key] = $this->settings[$key];
				} else {
					$settings[$key] = (bool)$post_data[$key];
				}
				break;
			case 'jpeg_quality' :
				if ( ! isset( $post_data[$key] ) || ! is_numeric( $post_data[$key] ) || 0 > $post_data[$key] || 100 < $post_data[$key] ) {
					$settings[$key] = $this->settings[$key];
				} else {
					$settings[$key] = $post_data[$key];
				}
				break;
			case 'max_image_width' :
				if ( ! isset( $post_data[$key] ) || ! is_numeric( $post_data[$key] ) || 320 > $post_data[$key] ) {
					$settings[$key] = $this->settings[$key];
				} else {
					$settings[$key] = $post_data[$key];
				}
				break;
			default :
			}
		}
		$this->settings = $settings;

		$ret = update_option( 'kusanagi-image-optimizer-settings', $settings );

		if ( $ret ) {
			$WP_KUSANAGI->messages[] = __( 'Update settings successfully.', 'wp-kusanagi' );
		}
	}

	public function fullsize_image_limiter( $file_info ) {
		if ( 0 == $this->settings['enable_image_optimize'] ) {
			return $file_info;
		}
		
		if ( strpos( $file_info['type'], 'image/' ) === 0 ) {
			$size = getimagesize( $file_info['file'] );
			if ( $size ) {
				if ( $size[0] > $this->settings['max_image_width'] ) {

					$editor = wp_get_image_editor( $file_info['file'] );

					if ( ! is_wp_error( $editor ) && ! is_wp_error( $editor->resize( $this->settings['max_image_width'], 9999 ) ) ) {
						$image = wp_load_image( $file_info['file'] );
						$filename = basename( $file_info['file'] );
						$dest_path = dirname( $file_info['file'] );
						$tmp_name = $dest_path . '/temp-' . $filename;
						$resized_file = $editor->save( $tmp_name );

						if ( filesize( $tmp_name ) < filesize( $file_info['file'] ) ) {
							$stat = stat( dirname( $tmp_name ) );
							$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
							@ chmod( $tmp_name, $perms );
							@ rename( $tmp_name, $file_info['file'] );
						} else {
							@ unlink( $tmp_name );
						}
					}

				} elseif ( $file_info['type'] == 'image/jpeg' ) {
					list( $orig_w, $orig_h, $orig_type ) = $size;
					$image = wp_load_image( $file_info['file'] );
					$filename = basename( $file_info['file'] );
					$dest_path = dirname( $file_info['file'] );
					$max_w = min( $size[0], $this->settings['max_image_width'] );
	
					$dims = image_resize_dimensions( $orig_w, $orig_h, $max_w, 999999, false );
					if ( ! $dims ) {
						if ( $file_info['type'] != 'image/jpeg' ) {
							return $file_info;
						}
						$dims = array( 0, 0, 0, 0, $orig_w, $orig_h, $orig_w, $orig_h );
					}
	
					list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;
					$newimage = imagecreatetruecolor( $dst_w, $dst_h );

//					// preserve PNG transparency
//					if ( IMAGETYPE_PNG == $orig_type && function_exists( 'imagealphablending' ) && function_exists( 'imagesavealpha' ) ) {
//						imagealphablending( $newimage, false );
//						imagesavealpha( $newimage, true );
//					}

					imagecopyresampled( $newimage, $image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
	
					// we don't need the original in memory anymore
					imagedestroy( $image );
	
					// $suffix will be appended to the destination filename, just before the extension
					$suffix = 'tmp';
	
					$info = pathinfo( $file_info['file'] );
					$dir = $info['dirname'];
					$ext = $info['extension'];
					$name = basename( $file_info['file'], ".{$ext}" );
					$destfilename = "{$dir}/{$name}-{$suffix}.{$ext}";
	
//					if ( $orig_type == IMAGETYPE_GIF ) {
//						if ( ! imagegif( $newimage, $destfilename ) ) {
//							return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid', 'wp-kusanagi' ) );
//						}
//					} elseif ( $orig_type == IMAGETYPE_PNG ) {
//						if ( ! imagepng( $newimage, $destfilename ) ) {
//							return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid', 'wp-kusanagi' ) );
//						}
//					} else {
						// all other formats are converted to jpg
						$destfilename = "{$dir}/{$name}-{$suffix}.jpg";
						if ( ! imagejpeg( $newimage, $destfilename, apply_filters( 'jpeg_quality', $this->settings['jpeg_quality'] ) ) ) {
							return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid', 'wp-kusanagi' ) );
						}
//					}
	
					imagedestroy( $newimage );

					// Set correct file permissions
					$stat = stat( dirname( $destfilename ) );
					if ( filesize( $destfilename ) < filesize( $file_info['file'] ) ) {
						$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
						@ chmod( $destfilename, $perms );
						@ rename( $destfilename, $file_info['file'] );
					} else {
						@ unlink( $destfilename );
					}
				}
			}
		}
		return $file_info;
	}
	
	
	public function jpeg_quality( $jpeg_quality ) {
		if ( 1 == $this->settings['enable_image_optimize'] && isset( $this->settings['jpeg_quality'] ) ) {
			$jpeg_quality = $this->settings['jpeg_quality'];
		}
		return $jpeg_quality;
	}

} // class end

$this->modules['image-optimizer'] = new KUSANAGI_Image_Optimizer;
