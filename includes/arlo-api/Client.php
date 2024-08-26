<?php

namespace ArloAPI;

class Client
{
	/**  Location for overloaded data.  */
    private $data = array();
	
	public function __construct($platform_name, $transport = null, $plugin_version = '')
	{
		/*
		// for future use
		if(!$transport) {
			use \ArloAPI\Transports\Guzzle;
			$transport = new Guzzle();
		}*/
		
		$this->__set('platform_name', $platform_name);
		$this->__set('plugin_version', $plugin_version);
		$this->__set('transport', $transport);
	}
	
	public function __set($name, $value)
    {
        //echo "Setting '$name' to '$value'\n";
        $this->data[$name] = $value;
    }
    
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
	
	public function __call($name, $arguments)
    {
        /*// Note: value of $name is case sensitive.
        echo "Calling object method '$name' " . implode(', ', $arguments). "\n";
        */
        
        if(!$this->__get($name)) {
       		require_once __DIR__ . '/Resources/' . $name . '.php';
       		$class = "ArloAPI\\Resources\\$name";
       		$this->__set($name, new $class($this->__get('platform_name'), $this->__get('transport'), $this->__get('plugin_version')));
        }
        
        return $this->__get($name);    
    }
	
}