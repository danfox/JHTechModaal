<?php
//* Start the engine
/*
Plugin Name: jhtechModaal
Plugin URI: http://jhtechservices.com
Description: Addes Modaaljs by Humaan to Wordpress
Author: Jerod Hammerstein
Version: 0.1
Author URI: http://jhtechservices.com
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

//TODO look at nested shortcodes to be able to add html/images as the link to the modal.
//TODO look at adding thumbnails of multiple images of image gallery.

class jhtechModaalPlugin {

	
	public function __construct() {
		
		// Set Plugin Path
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue'));

		add_shortcode( 'modaal', array( $this, 'shortcode'));
		add_shortcode( 'modaal_link', array( $this, 'shortcode_link') );
		
	}

	public function enqueue() {

		//https://mikejolley.com/2013/12/02/sensible-script-enqueuing-shortcodes/
		wp_register_script('modaal', plugins_url( '/js/dist/modaal.min.js', __FILE__), array('jquery'),'1.0.0',true);
		
		wp_enqueue_style('modaalcss', plugins_url( '/css/modaal.min.css', __FILE__));

	}
	
	public function shortcode($atts, $content="") {
		static $modalNum = 0;
		$output = '';

		wp_enqueue_script('modaal');
		
		extract(shortcode_atts(array(
			'type' 				=> 	'inline',  // inline, image, video, iframe. (ajax, confirm, instagram - not implemented yet)
			'button_class' 		=> 	'',
			'button_text'		=>  '',
			'button_image'		=>	'',
			'attribs'			=>  '',
			'inline_config'		=> 	true, // use -data-modaal-*.  If false, must call $().modaal manually with config, especially, if calling js functions for any modaal events
			'id'				=>  '',  // adds an id - could be used to select this modaal using external js if inline_config is false.
			'width'				=>  '400', // used if iframe
			'height'			=>  '300'   // used if iframe
		), $atts));

		if ($inline_config === 'false') {
			$inline_config = false;
		} else {
			$inline_config = true;
		}
		if($id !== '') {
			$id = sanitize_html_class( $id );
			$id = 'id="' . $id . '"';
		}

		$type = strtolower( $type );
		$options = '';  //will contain markup of data-modaal-* options
		$classes = ($inline_config? 'modaal ' : '') . $button_class;  //class modaal used by modaal.js to initialize modaal with inline configuration attributes

		/*
		 * clean wpautop mess
		 */
		//$output .= '<div>before str_replace</div><pre>' . print_r(esc_html($content),true) . '</pre>';
		$content = str_replace("<br>", "\n", $content);
		$content = str_replace("<br />", "\n", $content);
		$content = str_replace("<p></p>", "\n", $content);
		$content = preg_replace('/^<\/p>/', '', $content);
		$content = preg_replace('/<p>\[/', '[', $content);
		//$output .= '<div>after str_replace</div><pre>' . print_r(esc_html($content),true) . '</pre>';

				
		/*
		 * Check to see if there is an internal shortcode modaal_link containing the html that will be the inner content of the <a> tag
		 * If so, grab the shortcode, run it and put the results into $button_text.  Next remove the modaal_link shortcode from $content
		 */
		if ( has_shortcode( $content, 'modaal_link' ) ) {
			// from http://stackoverflow.com/questions/6290810/return-only-the-shortcode-from-post
			$pattern = get_shortcode_regex();
			preg_match('/'.$pattern.'/s', $content, $matches);
			if (is_array($matches) && $matches[2] == 'modaal_link') {
				$shortcode = $matches[0];
				$button_text = do_shortcode($shortcode);  //override any value in the attribute 
			}
			//now remove the shortcode from the $content
			$content = strip_shortcodes( $content );
		}
		
		
		/*
		 * If we are adding the inline configuration attributes (data-modaal-*), then parse the 'attribs' attribute, and 
		 * create an associative array called $dataAttribs.  Apply filters on the $dataAttribs so user can provide for default
		 * values or override the values in the shortcode attribs
		 */
		if( $inline_config ){
			$dataAttribs = array();  //assoc array of modaal options to be put as data-modaal-* attributes in the markup.

			/**
        	 * Filter to hook into to provide default shortcode attribs but would be overridden by shortcode attribs
        	 */
			$dataAttribs = apply_filters( 'jhtech_modaal_default_attribs', $dataAttribs, $id );
			
			if($attribs != '') {
				$partial = explode(',', $attribs);
				foreach ($partial as $pair) {
					$temp = explode(':', $pair);
					$dataAttribs[$temp[0]] = $temp[1];
				}
			}
			if( $type === 'images') $type = 'image';
			$dataAttribs['type'] = $type;

			if ($type === 'iframe') {
				$dataAttribs['width'] = $width;
				$dataAttribs['height'] = $height;
			}
			/**
			 * Filter to hook into to override shortcode attribs
			 */
			$dataAttribs = apply_filters( 'jhtech_modaal_override_attribs', $dataAttribs, $id );
			//Create the data-modaal-* options and escaping them

			foreach($dataAttribs as $key => $value) {
				$options .= 'data-modaal-' . esc_attr($key) . '="' .esc_attr($value) . '" ';
			}
		}

		//images #http://stackoverflow.com/questions/138313/how-to-extract-img-src-title-and-alt-from-html-using-php
		if($type === 'image') {
			$imgs = array();
			$doc = new DOMDocument();
			@$doc->loadHTML($content);
			$tags = $doc->getElementsByTagName('img');
			$gallery = esc_attr('gallery-' . $modalNum);
			$i=0;
			foreach ($tags as $tag) {
					$output .= '<a '. ($i == 0?$id : '') .' href="' . esc_url($tag->getAttribute('src')) . '" class="'. esc_attr($classes) .'" rel="' . esc_attr($gallery) . '" ' . $options . '>' . ($i==0? $button_text : ''). '</a>';
					$i++;
			}

		} else if($type === 'inline') {
			$contentId = 'inline-modaal-' . $modalNum;
			$output .= '<a '. $id  .' href="#' . $contentId . '" class="'. esc_attr($classes) .'" ' . $options . '>' . $button_text . '</a>';
			$output .= sprintf('<div id="%s" style="display:none;">%s</div>', $contentId, $content);
			
		} else if($type === 'video') {
			$doc = new DOMDocument();
			@$doc->loadHTML($content);
			$video = $doc->getElementsByTagName('iframe');
			$src = $video[0]->getAttribute('src');  //currently modaal doesn't support a gallery of videos.
			$output .= '<a '. $id .' href="' . $src . '" class="'. esc_attr($classes) .'" ' . $options . '>' . $button_text . '</a>';
			
		} else if($type === 'iframe') {
			$doc = new DOMDocument();
			@$doc->loadHTML($content);
			$iframe = $doc->getElementsByTagName('iframe');
			$src = $iframe[0]->getAttribute('src');  //currently modaal doesn't support a gallery of videos.
			$output .= '<a '. $id .' href="' . esc_url($src) . '" class="'. esc_attr($classes) .'" ' . $options . '>' . $button_text . '</a>';

		}

		$modalNum++;  // used if shortcode called again in same post or page.
		return $output;
	}
	
	public function shortcode_link ($atts, $content="") {
		return $content;
	}

	/**
	 * Activate Plugin
	 */
	public static function activate()
	{
		// Do nothing
	} // END public static function activate

	/**
	 * Deactivate the plugin
	 */
	public static function deactivate()
	{
		// Do nothing
	} // END public static function deactivate
}

if(class_exists('jhtechModaalPlugin')) {
	register_activation_hook(__FILE__, array( 'jhtechModaalPlugin', 'activate' ));
	register_deactivation_hook(__FILE__, array( 'jhtechModaalPlugin', 'deactivate'));
	
	$jhtechModaalPlugin = new jhtechModaalPlugin();
}
