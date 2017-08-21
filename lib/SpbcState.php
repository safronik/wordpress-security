<?php
/**
 * Spbc state class
 *
 * @version 1.0
 * @package Spbc
 * @subpackage State
 * @author Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see https://github.com/CleanTalk/php-antispam 
 *
 */

class SpbcState
{	
	public $option_prefix = '';
	public $storage = array();
	public $def_settings = array(
		'spbc_key' => '',
		'traffic_control_enabled' => 0,
		'traffic_control_autoblock_amount' => 1000,
		'set_cookies' => 1,
		'show_link_in_login_form' => 1,
		'complete_deactivation' => 1,
	);
	public $def_data = array(
		'plugin_version' => SPBC_VERSION
	);
	public $def_network_settings = array(
		'allow_custom_key' => 0,
	);
	
	public function __construct($option_prefix, $options = array('settings'))
	{
		$this->option_prefix = $option_prefix;
		
		foreach($options as $option_name){
			
			$option = get_option($this->option_prefix.'_'.$option_name);
			
			// Setting default options
			if($this->option_prefix.'_'.$option_name === 'spbc_settings')
				$option = $option ? array_merge($this->def_settings, $option) : $this->def_settings;
			if($this->option_prefix.'_'.$option_name === 'spbc_network_settings')
				$option = $option ? array_merge($this->def_network_settings, $option) : $this->def_network_settings;
			if($this->option_prefix.'_'.$option_name === 'spbc_data')
				$option = $option ? array_merge($this->def_data, $option) : $this->def_data;
			
			if(is_array($option)){	
				$this->$option_name = new ArrayObject($option);
			}else{
				$this->$option_name = $option;
			}
		}
	}
	
	private function getOption($option_name)
	{
		$option = get_option($option_name);
		
		if($option === false)
			$this->$option_name = false;
		elseif(gettype($option) === 'array')
			$this->$option_name = new \ArrayObject($option);
		else
			$this->$option_name = $option;
	}
	
	public function save($option_name, $use_perfix = true)
	{	
		$option_name_to_save = $use_perfix ? $this->option_prefix.'_'.$option_name : $option_name;
		$arr = array();
		foreach($this->$option_name as $key => $value){
			$arr[$key] = $value;
		}
		update_option($option_name_to_save, $arr);
	}
	
	public function saveSettings()
	{
		update_option($this->option_prefix.'_settins', $this->settings);
	}
	
	public function saveData()
	{		
		update_option($this->option_prefix.'_data', $this->data);
	}
	
	public function deleteOption($option_name, $use_prefix = false)
	{
		$this->__isset($option_name);
		$this->__unset($option_name);
		delete_option( ($use_prefix ? $this->option_prefix.'_' : '') . $option_name);
	}
	
	public function __set($name, $value) 
    {
        $this->storage[$name] = $value;
    }

    public function __get($name) 
    {
        if (array_key_exists($name, $this->storage)){
            return $this->storage[$name];
        }else{
			$this->getOption($name);
			return $this->storage[$name];
		}
	
		// return !empty($this->storage[$name]) ? $this->storage[$name] : null;
    }
	
    public function __isset($name) 
    {
        return isset($this->storage[$name]);
    }
	
    public function __unset($name) 
    {
        unset($this->storage[$name]);
    }
	
	public function __call($name, $arguments)
	{
        error_log ("Calling method '$name' with arguments: " . implode(', ', $arguments). "\n");
    }
	
    public static function __callStatic($name, $arguments)
	{
        error_log("Calling static method '$name' with arguments: " . implode(', ', $arguments). "\n");
    }
}