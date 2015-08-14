<?php
namespace Arlo;

// load main Transport class for extending
require_once 'arlo-singleton.php';

use Arlo\Singleton;

class Shortcodes extends Singleton {
	private $shortcodes = array();
	
    public function __construct() {
		//add_filter('su/data/shortcodes', array($this, 'init_shortcodes_ultimate'));
		//add_filter('plugins_loaded', array($this, 'init_shortcodes'));
    }
    
    /*public function init_shortcodes() {
	    foreach($this->shortcodes as $shortcode) {
		    $name = $shortcode['name'];
		    $function = $shortcode['function'];
		    
		    $shortcode_name = 'arlo_' . $name;
    	
		    // add the shortcode
		    add_shortcode($shortcode_name, array($this, 'the_shortcode'));
		    
		    // assign the passed function to a filter
		    // all shortcodes are run through filters to allow external manipulation if required, however we also need a means of running the passed function
		    add_filter('arlo_shortcode_content_' . $shortcode_name, $function, 10, 3);
	    }
    }*/
    
    /*public function init_shortcodes_ultimate($shortcodes) {
	    foreach($this->shortcodes as $shortcode) {
		    $name = $shortcode['name'];
		    
		    $shortcode_name = 'arlo_' . $name;
		    
		    // Add new shortcode
			$shortcodes[$name] = array(
				// Shortcode name
				'name' => isset($options['title']) ? $options['title'] : $name,
				// Shortcode type. Can be 'wrap' or 'single'
				// Example: [b]this is wrapped[/b], [this_is_single]
				'type' => isset($options['type']) ? $options['type'] : 'single',
				// Shortcode group.
				// Can be 'content', 'box', 'media' or 'other'.
				// Groups can be mixed, for example 'content box'
				'group' => isset($options['group']) ? $options['group'] : 'arlo',
				// List of shortcode params (attributes)
				'atts' => isset($options['atts']) ? $options['atts'] : array(),
				// Default content for generator (for wrap-type shortcodes)
				'content' => isset($options['content']) ? $options['content'] : '',
				// Shortcode description for cheatsheet and generator
				'desc' => isset($options['desc']) ? $options['desc'] : '',
				// Custom icon (font-awesome)
				'icon' => isset($options['icon']) ? $options['icon'] : 'plus',
				'prefix' => 'arlo_'
			);
		}
		
		// Return modified data
		return $shortcodes;
    }*/
    
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
	    
	    /*$this->shortcodes[] = array(
		    'name' => $name,
		    'function' => $function
	    );*/
	    
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
			'label' => ''
		), $atts, $shortcode_name));
                	
		// need to decide ordering - currently makes sense to process the specific filter first
		$content = apply_filters('arlo_shortcode_content_'.$shortcode_name, $content, $atts, $shortcode_name);
		$content = apply_filters('arlo_shortcode_content', $content, $atts, $shortcode_name);
		
		// run any shortcodes prior to conituning
		$content = do_shortcode($content);
		
		// if not empty, process labels and wrapping
		if(trim($content) != '') {
			// labels will be passed through an attribute, however do we want to be able to filter them? Not for now.
			//$content = apply_filters('arlo_shortcode_label', $atts, $content, $shortcode_name);
			//$content = apply_filters('arlo_shortcode_label_'.$shortcode_name, $atts, $content, $shortcode_name);
			
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
