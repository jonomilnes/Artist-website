<?php

class PeThemeImage {

	protected $basedir;
	protected $baseurl;
	protected $blanks = array();
	protected $retina_enabled;
	protected $lazyload_enabled;
	protected $optkey;
	public $generated;
	protected $md5;
	protected $image_sizes;

	public function __construct() {
		$wp_upload = wp_upload_dir();
		$this->basedir = $wp_upload["basedir"];
		$this->baseurl = $wp_upload["baseurl"];
		$this->retina_enabled = peTheme()->options->get("retina") === "yes";
		$this->lazyload_enabled = peTheme()->options->get("lazyImages") === "yes";
		$this->optkey = "pe_theme_".PE_THEME_NAME."_thumbnails";
		//delete_option($this->optkey);
		$this->generated = get_option($this->optkey,array("index" => array()));
		$this->md5 = md5(serialize($this->generated));

		//peTheme()->thumbnail->clean();
	}

	public function &blank($w,$h) {
		$key = "{$w}x{$h}";
		if (isset($this->blanks[$key])) {
			return $this->blanks[$key];
		}

		$external = "/img/blank/$key.gif";

		if (file_exists(PE_THEME_PATH.$external)) {
			// a blank img exists with the requested size
			$this->blanks[$key] = PE_THEME_URL.$external;
		} else {
			// create blank img
			$image = imagecreate($w, $h);
			imagesavealpha($image, true);
			imagecolortransparent($image, imagecolorallocatealpha($image, 0, 0, 0, 0));
			ob_start();
			imagegif($image);
			$this->blanks[$key] = "data:image/gif;base64,".base64_encode(ob_get_clean());
			imagedestroy($image);
		}

		return $this->blanks[$key];
	}

	public function placeholder($w,$h) {
		return sprintf('<img src="%s" width="%s" height="%s" alt="" />',$this->blank($w,$h),$w,$h);
	}


	public function get_size($img) {
		return stripos($img,"http") === 0 ? false : getimagesize($img);
	}

	public function is_url($url) {
		return stripos($url,'http://') === 0 || strpos($url,'http://') === 0;
	}


	public function get_retina($url) {

		$ret = $this->retina_enabled;
		$lazy = $ret || $this->lazyload_enabled;
		$common = sprintf('src="%s" alt=""',$url);

		$img = str_replace(PE_THEME_URL,PE_THEME_PATH,$url);
		if ($this->is_url($img)) {
			$burl = $this->baseurl;
			$bdir = $this->basedir;
			$img = str_replace($burl,$bdir,$img);
			if ($this->is_url($img)) {
				// damn ... let's try adding/stripping www
				if (stripos("://www.",$url) > -1) {
					// www is there, try removing it
					$burl = str_replace("://www.","://",$burl);
				} else {
					// no www, try adding it
					$burl = str_replace("://","://www.",$burl);
				}
				$img = str_replace($burl,$bdir,$img);
			}
		}

		if ($ret || $lazy) {
			
			if ($ret) {
				$ret = preg_replace("/\.(\w+)$/",'@2x.$1',$img);
				$ret = is_readable($ret) ? preg_replace("/\.(\w+)$/",'@2x.$1',$url) : false;
			}

			if ($ret || $lazy) {
				// try to get image size
				$size = $this->get_size($img);
				if ($size) {
					list($w,$h) = $size;
					$common = sprintf('src="%s" width="%s" height="%s" alt=""',$this->blank($w,$h),$w,$h);
				} else {
					$common = sprintf('src="%s" alt=""',$this->blank(1,1));
				}

				$lazy = sprintf(' data-original="%s"',$url);
				$ret = $ret ? sprintf(' data-original-hires="%s"',$ret) : "";
			}
		} else {
			$size = $this->get_size($img);

			if ($size) {
				list($w,$h) = $size;
				$common = sprintf('src="%s" width="%s" height="%s" alt=""',$url,$w,$h);
			} 
		}

		return sprintf("<img %s%s%s>",$common,$lazy,$ret);
	}

	public function retina($img) {
		esc__pe($this->get_retina($img));
	}


	public function iresize($file,$width,$height,$crop) { // compatibilty wrapper for 3.4, >= 3.5 use wp_get_image_editor
		if (function_exists("wp_get_image_editor")) {

			// check if custom cropping defined
			$size = "{$width}x{$height}";
			$thumb = str_replace($this->basedir,"",preg_replace("/(\.\w+)$/","-$size\\1",$file));
			if (!empty($this->generated["crop"][$thumb])) {
				$crop = $this->generated["crop"][$thumb];
				$url = str_replace($this->basedir,$this->baseurl,$file);
				$this->crop($url,$crop,$width,$height);
				return $this->basedir.$thumb;
			}

			// WP 3.5
			$editor = wp_get_image_editor($file);
			$editor->resize($width,$height,$crop);
			$dest_file = $editor->generate_filename();
			$editor->save($dest_file);
			return $dest_file;
		} else {
			$f = "image_resize"; // compatibilty with WP 3.4 and below
			return $f($file,$width,$height,$crop);
		}
	}


	public static function makeBWimage($file,$dest) {
		//$image = wp_load_image($file);
		$image = imagecreatefromstring( file_get_contents( $file ) );
		$res = imagefilter($image,IMG_FILTER_GRAYSCALE);
		$res = $res && imagejpeg($image,$dest);
		imagedestroy($image);
		return $res;
	}

	public function blackwhite_filter($meta) {
		if (!isset($meta) || !is_array($meta) || count($meta) == 0) {
			return $meta;
		}
		$base = dirname($this->basedir."/".$meta["file"]);
		$thumbs = array_keys(PeGlobal::$config["image-sizes"]);

		if (isset($meta["sizes"])) {
			$sizes =& $meta["sizes"];
		}

		foreach ($thumbs as $thumb) {
			if (substr($thumb,-3,3) == "-bw") {
				$normal = substr($thumb,0,strlen($thumb)-3);
				if (isset($sizes) && isset($sizes[$normal])) {
					$bwthumb = array_merge($sizes[$normal]);
				} else {
					$bwthumb = array("file" => wp_basename($meta["file"]), "width" => $meta["width"], "height" => $meta["height"]);
				}
				$file = "$base/{$bwthumb['file']}";
				$dest = preg_replace('/\.\w+$/', "-bw.jpg", $file);
				if (!isset($seen[$file])) {
					$seen[$file] = true;
					$this->makeBWimage($file,$dest);
				}
				$bwthumb["file"] = wp_basename($dest);
				$meta["sizes"][$thumb] = $bwthumb;
			}
		}

		return $meta;
	}

	/**
	 * Title		: Aqua Resizer
	 * Description	: Resizes WordPress images on the fly
	 * Version	: 1.1.3
	 * Author	: Syamil MJ
	 * Author URI	: http://aquagraphite.com
	 * License	: WTFPL - http://sam.zoy.org/wtfpl/
	 * Documentation	: https://github.com/sy4mil/Aqua-Resizer/
	 *
	 * @param string $url - (required) must be uploaded using wp media uploader
	 * @param int $width - (required)
	 * @param int $height - (optional)
	 * @param bool $crop - (optional) default to soft crop
	 * @param bool $single - (optional) returns an array if false
	 * @uses wp_upload_dir()
	 *
	 * @return str|array
	 */
	public function aq_resize( $url, $width, $height = null, $crop = null, $single = true ) {
		
		//validate inputs
		if(!$url OR !$width ) return false;
		
		//define upload path & dir
		$upload_dir = $this->basedir;
		$upload_url = $this->baseurl;
		
		//check if $img_url is local
		//if(strpos( $url, home_url() ) === false) return false;
		if(strpos( $url, $upload_url ) === false) return false;
		
		//define path of image
		$rel_path = str_replace( $upload_url, '', $url);
		$img_path = $upload_dir . $rel_path;
		
		//check if img path exists, and is an image indeed
		if( !file_exists($img_path) OR !getimagesize($img_path) ) return false;
		
		//get image info
		$info = pathinfo($img_path);
		$ext = $info['extension'];
		list($orig_w,$orig_h) = getimagesize($img_path);
		
		//get image size after cropping
		$dims = image_resize_dimensions($orig_w, $orig_h, $width, $height, $crop);
		$dst_w = $dims[4];
		$dst_h = $dims[5];
		
		//use this to check if cropped image already exists, so we can return that instead
		$suffix = "{$dst_w}x{$dst_h}";

		$dst_rel_path = str_replace( '.'.$ext, '', $rel_path);
		$destfilename = "{$upload_dir}{$dst_rel_path}-{$suffix}.{$ext}";
		
		//if orig size is smaller
		if($width >= $orig_w) {
			
			if(!$dst_h) :
				//can't resize, so return original url
				$img_url = $url;
			$dst_w = $orig_w;
			$dst_h = $orig_h;
			
			else :
				//else check if cache exists
				if(file_exists($destfilename) && getimagesize($destfilename)) {
					$img_url = "{$upload_url}{$dst_rel_path}-{$suffix}.{$ext}";
				} 
			//else resize and return the new resized image url
				else {
					$resized_img_path = $this->iresize( $img_path, $width, $height, $crop );
					$resized_rel_path = str_replace( $upload_dir, '', $resized_img_path);
					$img_url = $upload_url . $resized_rel_path;
					
				}
			
			endif;
			
		}
		//else check if cache exists
		elseif(file_exists($destfilename) && getimagesize($destfilename)) {
			$img_url = "{$upload_url}{$dst_rel_path}-{$suffix}.{$ext}";
		} 
		//else, we resize the image and return the new resized image url
		else {
			$resized_img_path = $this->iresize( $img_path, $width, $height, $crop );
			$resized_rel_path = str_replace( $upload_dir, '', $resized_img_path);
			$img_url = $upload_url . $resized_rel_path;
		}
		

		//return the output
		if($single) {
			//str return
			$image = $img_url;
		} else {
			//array return
			$image = array (
							0 => $img_url,
							1 => $dst_w,
							2 => $dst_h
							);
		}
		
		$this->bwMode = false;

		if ($url != $img_url && !is_admin()) {
			$size = "{$dst_w}x{$dst_h}";
			if (!isset($this->generated["index"][$rel_path][$size])) {
				$this->generated["index"][$rel_path][$size] = true;
			}
		}

		return $image;
	}

	public function file($url) {
		if(strpos($url,$this->baseurl) === false) return false;
		return str_replace($this->baseurl, $this->basedir, $url);
	}


	public function crop($url,$crop,$w,$h) {

		if (($file = $this->file($url)) === false) return false; 

		$file = str_replace($this->baseurl, $this->basedir, $url);
		$size = "{$w}x{$h}";
		$thumb = preg_replace("/(\.\w+)$/","-$size\\1",$file);

		list($x1,$y1,$x2,$y2) = explode(",",$crop);
		$cw = $x2-$x1;
		if ($cw<$w) {
			// fix x1
			$x1 -= min($x1,$w-$cw);
			$cw = $x2-$x1;
			if ($cw < $w) {
				// fix x2
				$x2 += $w-$cw;
			}
		}
		$y2 =  $y1 + ceil($h*($cw/$w));
		$ch = $y2-$y1;
		if ($ch<$h) {
			// fix y1
			$y1 -= min($y1,$h-$ch);
			$ch = $y2-$y1;
			if ($ch < $h) {
				// fix y2
				$y2 += $h-$ch;
			}
		}
		$cw = $x2-$x1;
		$ch = $y2-$y1;
		$editor = wp_get_image_editor($file);
		$editor->crop($x1,$y1,$cw,$ch,$w,$h);
		$ret = $editor->save($thumb);
		if (!empty($ret["path"])) {
			$ret["url"] = str_replace($this->basedir,$this->baseurl,$ret["path"]);
			$ret["cburl"] = $ret["url"]."?t=".filemtime($ret["path"]); 
		}
		$editor = null;
		return $ret;
	}


	public function resize($url,$w,$h = null,$crop = true) {
		return $this->aq_resize($url,$w,$h,$crop,false);
	}

	public function resizedImg($url,$w,$h = null,$crop = true) {
		if (!$url) return;
		$result = $this->resize($url,$w,$h,$crop);
		if (!$result) return;
		$unscaled = $url;
		list($url,$w,$h) = $result;

		return apply_filters("pe_theme_resized_img","<img alt=\"\" width=\"$w\" height=\"$h\" src=\"$url\"/>",$url,$w,$h,$unscaled);
	}

	public function resizedImgUrl($url,$w,$h = null,$crop = true) {
		return $this->aq_resize($url,$w,$h,$crop,true);
	}

	public function stats() {
		if ($this->md5 != md5(serialize($this->generated))) {
			update_option($this->optkey,$this->generated);
		}
	}

	public function get_images_size() {
		// make thumbnails and other intermediate sizes
        global $_wp_additional_image_sizes;

        $this->image_sizes = array();
        foreach ( get_intermediate_image_sizes() as $s ) {
            $sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => false );
            if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
                $sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] ); // For theme-added sizes
            else
                $sizes[$s]['width'] = get_option( "{$s}_size_w" ); // For default sizes set in options
            if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
                $sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] ); // For theme-added sizes
            else
                $sizes[$s]['height'] = get_option( "{$s}_size_h" ); // For default sizes set in options
            if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
                $sizes[$s]['crop'] = intval( $_wp_additional_image_sizes[$s]['crop'] ); // For theme-added sizes
            else
                $sizes[$s]['crop'] = get_option( "{$s}_crop" ); // For default sizes set in options
        }

        $this->image_sizes = apply_filters( 'intermediate_image_sizes_advanced', $sizes );

	}


	public function post_thumbnail_html_filter($html,$post_id,$post_thumbnail_id,$size,$attr,$retina = false,$lazy = false) {
		//return $html;
		if (empty($post_thumbnail_id)) return $html;
		$url = wp_get_attachment_url($post_thumbnail_id);
		if (empty($url)) return $html;
		
		if (!isset($this->image_sizes[$size])) {
			$this->get_images_size();
			if (!isset($this->image_sizes[$size])) {
				return $html;
			}
		}

		$size = (object) $this->image_sizes[$size];

		if (!empty($size->width) && !empty($size->height)) {
			$resized = $this->resize($url,$size->width,$size->height,empty($size->crop) ? false : true);
			if (empty($resized)) {
				return $html;
			}
		}

		list($img,$w,$h) = $resized;

		if ($retina || $lazy) {
			$blank = $this->blank($w,$h);
			if ($retina) {
				$lazy = true;
				$retina = $this->resizedImgUrl($url,$w*2,$h*2);
				$newsrc = sprintf('src="%s" data-original="%s" data-original-hires="%s"',$blank,$img,$retina);
			} else {
				$newsrc = sprintf('src="%s" data-original="%s"',$blank,$img);
			}
		} else {
			$newsrc = sprintf('src="%s"',$img);
		}

		$newwidth = sprintf('width="%s"',$w);
		$newheight = sprintf('height="%s"',$h);

		$html = preg_replace("/src=\"[^\"]+\"/",$newsrc,$html);
		$html = preg_replace("/width=\"\d+\"/",$newwidth,$html);
		$html = preg_replace("/height=\"\d+\"/",$newheight,$html);

		if ($lazy) {
			if (strpos($html,'class=') > -1) {
				$html = preg_replace("/class=\"/",'class="peLazyLoading ',$html);
			} else {
				$html = preg_replace("/<img /",'<img class="peLazyLoading" ',$html);
			}
		}

		return $html;
	}

	public function bw($url) {
		if(!$url) return "";
		
		//define upload path & dir
		$upload_dir = $this->basedir;
		$upload_url = $this->baseurl;

		//check if $img_url is local
		if(strpos( $url, $upload_url ) === false) return false;
		
		//define path of image
		$rel_path = str_replace( $upload_url, '', $url);
		$img_path = $upload_dir . $rel_path;
		
		//check if img path exists, and is an image indeed
		if( !file_exists($img_path) OR !getimagesize($img_path) ) return "";
		
		//get image info
		$info = pathinfo($img_path);
		$ext = $info['extension'];

		$dst_rel_path = str_replace( '.'.$ext, '', $rel_path);
		$destfilename = "{$upload_dir}{$dst_rel_path}-bw.jpg";

		if (file_exists($destfilename) && getimagesize($destfilename)) {
			$url = "{$upload_url}{$dst_rel_path}-bw.jpg";
		} else {
			try {
				if (@$this->makeBWimage($img_path,$destfilename)) {
					$url = "{$upload_url}{$dst_rel_path}-bw.jpg";
				}
			} catch(Exception $e) {
			}
		}

		return $url;
	}

	public function get_attachment_id( $url ) {

		global $wpdb;

		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", esc_url( $url ) ) );

		if ( ! empty( $attachment ) && is_array( $attachment ) ) {

			return absint( $attachment[0] );

		} else {

			return 0;

		}

	}

}

?>