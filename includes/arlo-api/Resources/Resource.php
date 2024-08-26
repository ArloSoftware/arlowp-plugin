<?php

namespace ArloAPI\Resources;

class Resource
{
	/**  Location for overloaded data.  */
    protected $data = array();
    
	public function __construct($platform_name, $transport, $plugin_version)
	{
		$this->__set('platform_name', $platform_name);
		$this->__set('transport', $transport);
		$this->__set('api_path', $this->apiPath);
		$this->__set('plugin_version', $plugin_version);
	}
	
	/**  Local Setter  */
	public function __set($name, $value)
    {
        //echo "Setting '$name' to '$value'\n";
        $this->data[$name] = $value;
    }
    
    /**  Local Getter  */
    public function __get($name)
    {
        //echo "Getting '$name'\n";
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        /*
        if (WP_DEBUG) {
            $trace = debug_backtrace();
            trigger_error(
                'Undefined property via __get(): ' . $name .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE);    
        }
        */

        return null;
    }
    
    protected function request($get_data = null, $post_data = null, $public = true, $force_ssl = true) {
    	$platform_name = $this->__get('platform_name');
    	$transport = $this->__get('transport');
    	$path = $this->__get('api_path');
    	$plugin_version = $this->__get('plugin_version');
    	
    	if($get_data) $path .= '?' . $get_data;
    	
    	$response = $transport->request($platform_name, $path, $post_data, $public, $plugin_version, $force_ssl);
    
		// reset api_path if it has been overidden
		$this->__set('api_path', $this->apiPath);
    
	    return $response;
    }
}