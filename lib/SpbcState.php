<?php
/**
 * Spbc state class
 *
 * @version 1.1
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
		'spbc_key'                         => '',
		'traffic_control_enabled'          => true,
		'traffic_control_autoblock_amount' => 1000,
		'show_link_in_login_form'          => true,
		'set_cookies'                      => true,
		'complete_deactivation'            => false,
		'scan_outbound_links'			   => true,
		'heuristic_analysis'			   => false,
	);
	public $def_data = array(
		'user_token'               => '',
		'key_is_ok'                => false,
		'errors'                   => array(),
		'logs_last_sent'           => null,
		'last_sent_events_count'   => null,
		'last_firewall_updated'    => null,
		'firewall_entries'         => null,
		'last_firewall_send'       => null,
		'last_firewall_send_count' => null,
		'notice_show'              => null,
		'notice_renew'             => false,
		'notice_trial'             => false,
		'notice_were_updated'      => false,
		'service_id'               => '',
		'cdn'                      => array(
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'104.16.0.0/12',
			'108.162.192.0/18',
			'131.0.72.0/22',
			'141.101.64.0/18',
			'162.158.0.0/15',
			'172.64.0.0/13',
			'173.245.48.0/20',
			'188.114.96.0/20',
			'190.93.240.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
		),
	);
	public $def_network_settings = array(
		'allow_custom_key'   => false,
		'allow_cleantalk_cp' => false,
		'key_is_ok'          => false,
		'spbc_key'           => '',
		'user_token'         => '',
		'service_id'         => '',
	);
	
	public function __construct($option_prefix, $options = array('settings'), $wpms = false)
	{
		$this->option_prefix = $option_prefix;
		
		if($wpms){
			$option = get_site_option($this->option_prefix.'_network_settings');			
			$option = is_array($option) ? $option : $this->def_network_settings;
			$this->network_settings = new ArrayObject($option);
		}
		
		foreach($options as $option_name){
			
			$option = get_option($this->option_prefix.'_'.$option_name);
			
			// Setting default options
			if($this->option_prefix.'_'.$option_name === 'spbc_settings'){
				$option = is_array($option) ? array_merge($this->def_settings, $option) : $this->def_settings;
			}
			
			if($this->option_prefix.'_'.$option_name === 'spbc_data')
				$option = is_array($option) ? array_merge($this->def_data,     $option) : $this->def_data;
			
			$this->$option_name = is_array($option) ? new ArrayObject($option) : $option;
		}
	}
	
	private function getOption($option_name)
	{
		$option = get_option($option_name);
		
		if($option === false)
			$this->$option_name = false;
		elseif(gettype($option) === 'array')
			$this->$option_name = new ArrayObject($option);
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
	
	public function saveNetworkSettings()
	{		
		update_site_option($this->option_prefix.'_network_settings', $this->network_settings);
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
