<?php

class SpbcScaner
{
	public $path         = ''; // Main path
	public $path_lenght  = 0;
	
	public $ext          = array(); // Extensions to check
	public $ext_except   = array(); // Exception for extensions
	public $files_except = array(); // Exception for files paths
	public $dirs_except  = array(); // Exception for directories
	
	public $files = array();
	public $dirs  = array();
	public $content = array();
	public $internal_hostnames = array();
	public $links = array();	
	public $files_count = 0;
	public $dirs_count  = 0;
	private $file_start = 0;
	private $file_curr  = 0;
	private $file_max   = 1000000;
	private $debug = array();
	
	static $bad_constructs = array(
		'CRITICAL' => array(
			'eval(',
		),
		'DANGER' => array(
			'system(',
			'passthru(',
			'proc_open(',
			'exec(',
		),
		'SISPICIOUS' => array(
			'base64_encode(',
			'str_rot13(',
			'syslog(',
		),
	);
	
	static $special_bad_constructs = array(
		'allow_url_include' => array(
			'reqire',
			'include',
		),
	);
	
	function __construct($path, $params = array('count' => true))
	{
		
		// INITILAZING PARAMS
		
		// Main directory
		$path = realpath($path);
		if(!is_dir($path))
			die("'$path' isn't directory");
		$this->path_lenght = strlen($path);
		$this->scan_links =   !empty($params['scan_links'])? true : false;
		//Links
		if ($this->scan_links)
		{		
			$offset = !empty($params['offset']) ? $params['offset'] : 0;
			$amount = !empty($params['amount']) ? $params['amount'] : 30;
			$this->content=$this->get_all_content($offset,$amount);
			$this->get_links($offset,$amount);
			return;
		}		
		
		// Processing filters		
		$this->ext          = !empty($params['extensions'])            ? $this->process_filter($params['extensions'])             : array();
		$this->ext_except   = !empty($params['extensions_exceptions']) ? $this->process_filter($params['extensions_exceptions'])  : array();
		$this->files_except = !empty($params['file_exceptions'])       ? $this->process_filter($params['file_exceptions'])        : array();
		$this->dirs_except  = !empty($params['dir_exceptions'])        ? $this->process_filter($params['dir_exceptions'])         : array();
				
		// Initilazing counters
		$this->file_start =   !empty($params['offset']) ? $params['offset'] : 0;
		$this->file_max   =   !empty($params['offset']) && !empty($params['amount']) ? $params['offset'] + $params['amount'] : 1000000;
		
		// Initilazing misc parameters
		$this->fast_hash =    !empty($params['fast_hash']) ? true : false;
		$this->full_hash =    !empty($params['full_hash']) ? true : false;	
		// DO STUFF
		
		// Only count files
		if(!empty($params['count'])){
			$this->count_files_in_dir($path);
			return;
		}
		// Getting files and dirs considering filters
		$this->get_file_structure($path);
		// Files
		$this->files_count = count($this->files);
		$this->get_file_details($this->files, $this->path_lenght);
		
		// Directories
		// $this->dirs[]['path'] = $path;
		// $this->dirs_count = count($this->dirs);
		// $this->get_dir_details($this->dirs, $this->path_lenght);

		
	}
	public function get_all_content($offset=0,$amount=30)
	{
		global $wpdb;
		$page_urls=array();
		$sql = "SELECT id,post_content
		FROM " .$wpdb->posts." 
		WHERE post_status = 'publish' AND (post_type='post' OR post_type = 'page')";
		$the_query = $wpdb->get_results($sql, ARRAY_A);
		foreach ($the_query as $key=>$url){
			if (!empty($url['post_content']))
				$page_urls[] = array ('post_type'=>'post_page','post_id'=>$url['id'],'post_content'=>$url['post_content']);
		}
		$sql = "SELECT comment_post_id, comment_content
		FROM " .$wpdb->comments." 
		WHERE comment_approved = 1";
		$the_query = $wpdb->get_results($sql, ARRAY_A);
		foreach ($the_query as $key1=>$url){
			if (!empty($url['comment_content'])){
				foreach ($page_urls as $key2=>$value)
				{
					if ($value['post_id'] === $url['comment_post_id'])
						$page_urls[$key2]['post_content']=$page_urls[$key2]['post_content'].' '.$url['comment_content'];
				}					
			}
		}
		if (($offset+$amount)>=count($page_urls))
		{
			$default_pages = array(
				'/index.php',
				'/wp-signup.php',
				'/wp-login.php',
			);
			foreach ($default_pages as $page)
				$page_urls[] = array('post_type'=>'default','post_id'=>get_site_url().$page,'post_content'=>@file_get_contents(get_site_url().$page));			
		}

		return $page_urls;	
	}
	public function get_all_hostnames()
	{
		global $wpdb;
		$host_names=array();
		$sql = "SELECT guid
		FROM " .$wpdb->posts." 
		WHERE post_status = 'publish' AND (post_type='post' OR post_type = 'page')";
		$the_query = $wpdb->get_results($sql, ARRAY_A);	
		foreach ($the_query as $key=>$url)
		{
			if (!in_array(parse_url($url['guid'],PHP_URL_SCHEME)."://".parse_url($url['guid'],PHP_URL_HOST), $host_names))
				$host_names[]=parse_url($url['guid'],PHP_URL_SCHEME)."://".parse_url($url['guid'],PHP_URL_HOST);		
		}
		return $host_names;
	}
	public function get_page_url_by_id($id)
	{
		global $wpdb;
		$sql = "SELECT guid
		FROM " .$wpdb->posts." 
		WHERE ID = ".$id." LIMIT 1";
		$the_query = $wpdb->get_results($sql, ARRAY_A);	
		return $the_query[0]['guid'];		
	}
	public function get_links($offset,$amount)
	{
		$host_names = $this->get_all_hostnames();
		$content = $this->content;
		$links = array();
		for ($i=$offset;$i<($offset+$amount);$i++)
		{
			if (isset($content[$i]))
			{
				if ($content[$i]['post_type'] === 'post_page')
				{
					preg_match_all ("#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#", 
	                    $content[$i]['post_content'], $matches);
					foreach ($matches[0] as $match)
					{
						if (!in_array(parse_url($match,PHP_URL_SCHEME)."://".parse_url($match,PHP_URL_HOST), $host_names))
							{
								$links[$match]['page_url'] = $this->get_page_url_by_id($content[$i]['post_id']);
								$links[$match]['link_text'] = trim($match);		
							}					
					}					
				}
				if ($content[$i]['post_type'] === 'default')
				{
					$dom = new DOMDocument();
					@$dom->loadHTML($content[$i]['post_content']);
					$xpath = new DOMXPath($dom);
					$hrefs = $xpath->evaluate("/html/body//a");
					for($j = 0; $j < $hrefs->length; $j++){
					    $href = $hrefs->item($j);
					    $url = $href->getAttribute('href');
					    $url = filter_var($url, FILTER_SANITIZE_URL);
					    // validate url
					    if(!filter_var($url, FILTER_VALIDATE_URL) === false){	
					    	if (!in_array(parse_url($url,PHP_URL_SCHEME)."://".parse_url($url,PHP_URL_HOST), $host_names))
					    	{
				    			$links[$url]['page_url'] = $content[$i]['post_id'];
				        		$links[$url]['link_text'] = trim($href->nodeValue);					    		
					    	}				    			    				    		
					    }
					}					
				}

			}
		}
		$this->links=$links;
	}	
	// Processing filters
	public function process_filter($filter)
	{
		if(!empty($filter)){
			if(!is_array($filter)){
				if(strlen($filter)){
					$filter = explode(',', $filter);
				}
			}
			foreach($filter as $key => $val){
				$filter[$key] = trim($val);
			}
			return $filter;
		}else{
			return null;
		}
	}
	// Count files in directory
	public function count_files_in_dir($main_path)
	{
		foreach(glob($main_path.'/*', GLOB_NOSORT) as $path){				
			if(is_file($path)){
				
				// Extensions filter
				if(!empty($this->ext_except)){
					$tmp = explode('.', $path);
					if(in_array($tmp[count($tmp)-1], $this->ext_except))
						continue;
				}
				// Extensions filter
				if(!empty($this->ext)){
					$tmp = explode('.', $path);
					if(!in_array($tmp[count($tmp)-1], $this->ext))
						continue;
				}
				// Filenames exception filter
				if(!empty($this->files_except)){
					if(in_array(basename($path), $this->files_except))
						continue;
				}
				$this->files_count++;
				
			}elseif(is_dir($path)){
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(strpos($path, $dir_except) !== false){
						continue(2);
					}
				}
				$this->count_files_in_dir($path);
			}
		}
	}
	
	// Get all files from directory
	public function get_file_structure($main_path)
	{
		foreach(glob($main_path.'/*', GLOB_NOSORT) as $path){
			
			// Return if file limit is reached
			if($this->file_curr >= $this->file_max)
				return;
			
			if(is_file($path)){
				
				// Extensions filter
				if(!empty($this->ext)){
					$tmp = explode('.', $path);
					if(!in_array($tmp[count($tmp)-1], $this->ext))
						continue;
				}
				
				// Extensions exception filter
				if(!empty($this->ext_except)){
					$tmp = explode('.', $path);
					if(in_array($tmp[count($tmp)-1], $this->ext_except))
						continue;
				}
				
				// Filenames exception filter
				if(!empty($this->files_except)){
					if(in_array(basename($path), $this->files_except))
						continue;
				}
				
				$this->file_curr++;
				
				// Skip if start is not reached
				if($this->file_curr-1 < $this->file_start)
					continue;
				
				$this->files[]['path'] = $path;
				
			}elseif(is_dir($path)){
				
				// Dirnames filter
				foreach($this->dirs_except as $dir_except){
					if(strpos($path, $dir_except) !== false)
						continue(2);
				}
				
				$this->get_file_structure($path);
				if($this->file_curr > $this->file_start)
					$this->dirs[]['path'] = $path;
				
			}elseif(is_link($path)){
				error_log('LINK FOUND: ' . $path);
			}
		}
	}
	
// Getting file details
	public function get_file_details($file_list, $path_offset)
	{
		foreach($file_list as $key => $val){
			$this->files[$key]['path']  = substr(self::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->files[$key]['mtime'] = filemtime($val['path']);
			$this->files[$key]['perms'] = substr(decoct(fileperms($val['path'])), 3);
			$this->files[$key]['size']  = filesize($val['path']);
			
			// Fast hash
			// if($this->fast_hash)
				$this->files[$key]['fast_hash']  = md5($this->files[$key]['path']);
			
			// Full hash
			// if($this->full_hash)
				$this->files[$key]['full_hash'] = md5_file($val['path']);
		}
		return $file_list;
	}

// Getting dir details
	public function get_dir_details($dir_list, $path_offset)
	{
		foreach($dir_list as $key => $val){
			$this->dirs[$key]['path']  = substr(self::is_windows() ? str_replace('/', '\\', $val['path']) : $val['path'], $path_offset);
			$this->dirs[$key]['mtime'] = filemtime($val['path']);
			$this->dirs[$key]['perms'] = substr(decoct(fileperms($val['path'])), 2);
		}
	}

// Getting real hashs
	static function get_hashs($path, $cms, $version)
	{
		
		$file_path = 'https://cleantalk-security.s3.amazonaws.com/cms_checksums/'.$cms.'/'.$version.'/'.$cms.'_'.$version.'.json.gz';
		
		$urlHeaders = @get_headers($file_path);
		if(strpos($urlHeaders[0], '200')) {
			// $gzfile = gzfile(SPBC_PLUGIN_DIR . '/hash.json.gz');
			$gzfile = gzfile($file_path);
				if(!empty($gzfile)){
				$result = '';
				foreach($gzfile as $gzline){
					$result .= $gzline;
				} unset($gzline);
				
				$result = json_decode($result, true);
				$result = $result['data'];
				
				if(count($result['checksums']) == $result['checksums_count'])
					return $result;
				else
					return array('error' => true, 'error_string' =>'FILE_DOESNT_MATHCES');
			}else
				return array('error' => true, 'error_string' =>'FILE_EMPTY');
		}else
			return array('error' => true, 'error_string' =>'REMOTE_FILE_NOT_FOUND_OR_VERSION_IS_NOT_SUPPORTED');
	}

	static public function scan_file($root_path, $file_info, $cms_version, $platform = 'wordpress'){
		
		$output     = array();
		$difference = array();
		
		if(file_exists($root_path.$file_info['path'])){
			if(is_readable($root_path.$file_info['path'])){
				
				$flags = array(
					'allow_url_include' => (bool) ini_get('allow_url_include'),
				);
				
				$file = file($root_path.$file_info['path']);
				
				// Getting remote file if it exists
				if($file_info['real_full_hash'] !== NULL){
					$user_agent = ini_get('user_agent');
					ini_set('user_agent', 'Secuirty Plugin by CleanTalk');
					$file_original = file('http://cleantalk-security.s3.amazonaws.com/cms_sources/'.$platform.'/'.$cms_version.str_replace('\\', '/', $file_info['path']));
					ini_set('user_agent', $user_agent);
				// Filling array with empty strings if the file is unreachable
				}else{
					$file_original = array_fill(0, count($file), '');
				}
				
				// Comparing files strings
				for($row = 0; !empty($file[$row]); $row++){
					if(isset($file[$row]) || isset($file_original[$row])){
						if(!isset($file[$row]))          $file[$row] = '';
						if(!isset($file_original[$row])) $file_original[$row] = '';
						if(strcmp(trim($file[$row]), trim($file_original[$row])) != 0){
							$result = self::scan_file_string($file[$row], $row, $flags);
							if($result){
								$output = array_merge_recursive($result, $output);
								$difference[] = $row+1;
							}
						}
					}
				}
				
				if(!empty($output)){
					$output['weak_spots'] = json_encode($output, true);
					$output['difference'] = json_encode($difference, true);
					$output['status'] = $file_info['status'] == 'UNKNOWN' ? 'UNKNOWN' : 'INFECTED';
					$output['severity'] = array_key_exists('CRITICAL', $output) ? 'CRITICAL' : (array_key_exists('DANGER', $output) ? 'DANGER' : 'SUSPICIOUS');
				}else{
					$output['weak_spots'] = 'NULL';
					$output['difference'] = 'NULL';
					$output['status'] = $file_info['status'] == 'UNKNOWN' ? 'UNKNOWN' : 'OK';
					$output['severity'] = 'NULL';
				}
				
			}else{
				$output = array('error' => 'NOT_READABLE');
			}
		}else{
			$output = array('error' => 'NOT_EXISTS');
		}
		
		return !empty($output) ? $output : false;
		
	}
	
	static public function scan_file_string($string, $string_num, $flags = null){
		
		$output = array();
		
		foreach(self::$bad_constructs as $severity => $set_of_functions){
			foreach($set_of_functions as $bad_function){
				$detected = strpos($string, $bad_function);
				if($detected !== false){
					$output[$severity][$string_num] = $bad_function;
				}
			}
		}
		
		if($flags['allow_url_include'] === true){
			foreach(self::$special_bad_constructs['special_bad_constructs'] as $bad_function){
				$detected = strpos($string, $bad_function);
				if($detected !== false){
					$output['critical'][$string_num] = $bad_function;
				}
			}
		}
				
		return !empty($output) ? $output : false;
		
	}
	
	static function is_windows(){
		return strpos(strtolower(php_uname('s')), 'windows') !== false ? true : false;
	}
	
// Creating directory structure
	public function create_dir_structure($file_list_filtred, $result_dir)
	{
		foreach($file_list_filtred as $key => $val){
			if(is_array($val)){
				if(is_dir($result_dir . "/$key"))
					continue;
				else{
					mkdir($result_dir . "/$key", 777, true);
					$this->create_dir_structure($val, $result_dir . "/$key");
				}
			}
		}
		return true;
	}
}
