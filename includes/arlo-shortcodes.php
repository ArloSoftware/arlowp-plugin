<?php
namespace Arlo;

// load main Transport class for extending
require_once 'arlo-singleton.php';

use Arlo\Singleton;

class Shortcodes extends Singleton {
	private $shortcodes = array();
	
    public function __construct() {

    }
    
        
    public function add() {
	    $args = func_get_args();
	    
	    if(is_array($args[0])) {
		    $options = $args[0];
		    
		    $name = $options['name'];
		    $function = $options['function'];
	    } else {
		    $name = $args[0];
		    $function = $args[1];
	    }
	    
	    $shortcode_name = 'arlo_' . $name;
	
	    // add the shortcode
	    add_shortcode($shortcode_name, array($this, 'the_shortcode'));
	    
	    // 
	    $closure = new \ReflectionFunction($function);
	    
	    // assign the passed function to a filter
	    // all shortcodes are run through filters to allow external manipulation if required, however we also need a means of running the passed function
	    //add_filter('arlo_shortcode_content_' . $shortcode_name, $function, 10, 3);
	    add_filter('arlo_shortcode_content_' . $shortcode_name, function($content='', $atts, $shortcode_name) use($closure) {
		    return $closure->invokeArgs(array($content, $atts, $shortcode_name));
	    }, 10, 3);
    }
    
    public function the_shortcode($atts, $content="", $shortcode_name) {
		// merge and extract attributes
		extract(shortcode_atts(array(
			'wrap' => '%s',
			'label' => '',
			'strip_html'	=> 'false',
		), $atts, $shortcode_name));
		
		// need to decide ordering - currently makes sense to process the specific filter first
		$content = apply_filters('arlo_shortcode_content_'.$shortcode_name, $content, $atts, $shortcode_name);
		$content = apply_filters('arlo_shortcode_content', $content, $atts, $shortcode_name);
		
		// run any shortcodes prior to conituning
		$content = do_shortcode($content);
		
		// if not empty, process labels and wrapping
		if(trim($content) != '') {
			//strip html, if neccessary 
			if ($strip_html !== 'false') {
				if ($strip_html == 'true') {
					$content = strip_tags($content);
				} else {
					$content = strip_tags($content, $strip_html);
				}
			}
			
			// prepend label
            if (!empty($label)) {
                $content = '<label>' . $label . '</label> ' . $content;
            }
			
			
			// wrap content			
			$content = sprintf($wrap, $content);                        
		}
		
		return do_shortcode($content);
    }
}
