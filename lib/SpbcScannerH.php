<?php

class SpbcScannerH
{
	// Constants
	const FILE_MAX_SIZE = 1048576; // 1 MB
	
	// Current file atributes
	public $is_text        = false;
	
	public $ext            = null; // File extension
	public $path           = null; // File path
	public $root_path      = null; // Root path
	public $curr_dir       = null; // File path
	public $file_size      = 0;    // File size
	
	public $variables      = array();
	public $variables_bad  = array();
	public $file_lexems    = array(); // Array with file lexems
	
	public $file_content   = '';   // Original
	public $file_work      = '';   // Work copy
	public $file_stamp     = '';
	
	public $includes       = array();
	
	public $error = array();
	
	public $verdict = array(); // Scan results
	
	private $debug = array();
	
	private $variables_bad_default = array(
		'$_POST',
		'$_GET',
	);
	
	static $bad_constructs = array(
		'CRITICAL' => array(
			'eval',
		),
		'DANGER' => array(
			'system',
			'passthru',
			'proc_open',
			'exec',
		),
		'SISPICIOUS' => array(
			'base64_encode',
			'str_rot13',
			'syslog',
		),
	);
	
	public $usless_lexems  = array(
		'T_INLINE_HTML',
		'T_COMMENT',
		'T_DOC_COMMENT',
		// 'T_WHITESPACE',
	);
	
	public $strip_whitespace_lexems  = array(
		'T_WHITESPACE', // /\s*/
		'T_CLOSE_TAG',
		'T_CONSTANT_ENCAPSED_STRING', // String in quotes
		// Equals
		'T_DIV_EQUAL',
		'T_BOOLEAN_OR',
		'T_BOOLEAN_AND',
		'T_IS_EQUAL',
		'T_IS_GREATER_OR_EQUAL',
		'T_IS_IDENTICAL',
		'T_IS_NOT_EQUAL',
		'T_IS_SMALLER_OR_EQUAL',
		'T_SPACESHIP',
		// Assignments
		'T_CONCAT_EQUAL',
		'T_MINUS_EQUAL',
		'T_MOD_EQUAL',
		'T_MUL_EQUAL',
		'T_AND_EQUAL',
		'T_OR_EQUAL',
		'T_PLUS_EQUAL',
		'T_POW_EQUAL',
		'T_SL_EQUAL',
		'T_SR_EQUAL',
		'T_XOR_EQUAL',
		// Bit
		'T_SL', // <<
		'T_SR', // >>
		// Uno
		'T_INC', // ++
		'T_DEC', // --
		'T_POW', // **
		// Cast type
		'T_ARRAY_CAST',
		'T_BOOL_CAST',
		'T_DOUBLE_CAST',
		'T_OBJECT_CAST',
		'T_STRING_CAST',
		// Different
		'T_START_HEREDOC', // <<<
		'T_NS_SEPARATOR', // \
		'T_ELLIPSIS', // ...
		'T_OBJECT_OPERATOR', // ->
		'T_DOUBLE_ARROW', // =>
		'T_DOUBLE_COLON', // ::
		'T_PAAMAYIM_NEKUDOTAYIM', // ::
	);
	
	public $equals_lexems = array(
		'=',
		'T_CONCAT_EQUAL',
		'T_MINUS_EQUAL',
		'T_MOD_EQUAL',
		'T_MUL_EQUAL',
		'T_AND_EQUAL',
		'T_OR_EQUAL',
		'T_PLUS_EQUAL',
		'T_POW_EQUAL',
		'T_SL_EQUAL',
		'T_SR_EQUAL',
		'T_XOR_EQUAL',
	);
	
	public $dont_trim_lexems = array(
		'T_ENCAPSED_AND_WHITESPACE',
		'T_OPEN_TAG',
	);
	
	public $varibles_types_to_concat = array(
		'T_CONSTANT_ENCAPSED_STRING',
		// 'T_ENCAPSED_AND_WHITESPACE',
		'T_LNUMBER',
		'T_DNUMBER',
	);
	
	public $whitespace_lexem = array(
		'T_WHITESPACE',
		' ',
		null,
	);
	
	// Getting common info about file|text and it's content
	function __construct($path, $params = array())
	{
		// Exept file as a plain text|array
		if(isset($params['content'])){
			$this->is_text = true;
			$this->file_size    = strlen($params['content']);
			if($this->file_size == 0){
				if($this->file_size < self::FILE_MAX_SIZE){
					$this->file_work = $params['content'];
					$this->file_content = $this->file_work;
					$this->text_check = true;
					unset($params['content']);
				}else
					return $this->error = array('error' => true, 'error_string' =>'FILE_SIZE_TO_LARGE');
			}else
				return $this->error = array('error' => true, 'error_string' =>'FILE_SIZE_ZERO');
			
		// Exept file as a path
		}elseif(!empty($path)){
			$this->root_path = isset($params['root_path']) ? $params['root_path'] : self::get_root_path();
			// Path
			$this->path     = $this->root_path.$path;
			$this->curr_dir = dirname($this->path);
			// Exstension
			$tmp = explode('/', $path);
			$tmp = explode('.', $tmp[count($tmp)-1]);
			$this->ext = $tmp[count($tmp)-1];
			
			if(file_exists($this->path)){
				if(is_readable($this->path)){
					$this->file_size = filesize($this->path);
					if($this->file_size > 0){
						if($this->file_size < self::FILE_MAX_SIZE){
							$this->file_work    = file_get_contents($this->path);
							$this->file_content = $this->file_work;
						}else
							$this->error = array('error' => true, 'error_string' =>'FILE_SIZE_TO_LARGE');
					}else
						return $this->error = array('error' => true, 'error_string' =>'FILE_SIZE_ZERO');
				}else
					return $this->error = array('error' => true, 'error_string' =>'FILE_NOT_READABLE');
			}else
				return $this->error = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}
	}
		
	public function process_file()
	{
		
		$this->file_lexems = token_get_all($this->file_work);
		
		// Preparing file
		$this->lexems_getAll();
		$this->lexems_stripUseless();
		
		// Simplifying
		do{
			$this->file_stamp = $this->md5_file_lexems();
			$this->lexems_stripWhitespaces();
			
			$this->strings_convert();
			$this->strings_concatenate();
			
			$this->variables_getAll();
			$this->variables_replace();
			
		}while( $this->file_stamp !== $this->md5_file_lexems() );
		
		//* Getting construction
		
		// Detecting bad variables
		$this->variables_detectBad();
		
		// Getting all include constructions and detecting bad
		$this->includes_standartize();
		$this->includes_getAll();
		
		$this->make_verdict();
		
		// $this->file_work = $this->gather_file();
		
	}
	
	// Strips Usless lexems. T_INLINE_HTML, T_COMMENT, T_DOC_COMMENT
	public function lexems_getAll()
	{
		foreach($this->file_lexems as $key => $lexem){
			$this->file_lexems[$key][0] = is_array($lexem) ? token_name($lexem[0]) : $lexem;
		}
	}
	
	// Strips Usless lexems. T_INLINE_HTML, T_COMMENT, T_DOC_COMMENT
	public function lexems_StripUseless()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++){
			if(in_array($this->file_lexems[$key][0], $this->usless_lexems)){
				unset($this->file_lexems[$key]);
				// $this->file_lexems[$key] = $this->whitespace_lexem;
				// $this->file_lexems[$key][2] = $lexem[2];
			}
		}
		$this->file_lexems = array_values($this->file_lexems);
	}
	
	// Strips T_WHITESPACE around (array)strip_whitespace_lexems and single lexems
	public function lexems_stripWhitespaces()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current && $current[0] == 'T_WHITESPACE'){
				
				$next = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
				$prev = isset($this->file_lexems[$key-1]) ? $this->file_lexems[$key-1] : null;
				
				if(($next && !is_array($next)) || ($prev && !is_array($prev))){
					unset($this->file_lexems[$key]);
				}elseif(($next && in_array($next[0], $this->strip_whitespace_lexems)) || ($prev && in_array($prev[0], $this->strip_whitespace_lexems))){
					unset($this->file_lexems[$key]);
				}else{
					$this->file_lexems[$key][1] = ' ';
				}
			}else{
				if(!in_array($this->file_lexems[$key][0], $this->dont_trim_lexems) && is_array($this->file_lexems[$key]))
					$this->file_lexems[$key][1] = trim($this->file_lexems[$key][1]);
			}
		}
		$this->file_lexems = array_values($this->file_lexems);		
	}
	
	// Coverts T_ENCAPSED_AND_WHITESPACE to T_CONSTANT_ENCAPSED_STRING if could
	public function strings_convert()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current && $current[0] === 'T_ENCAPSED_AND_WHITESPACE'){
				$next = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
				$prev = isset($this->file_lexems[$key-1]) ? $this->file_lexems[$key-1] : null;
				if($prev == '"' && $next == '"'){
					unset($this->file_lexems[$key-1]);
					unset($this->file_lexems[$key+1]);
					$this->file_lexems[$key] = array(
						'T_CONSTANT_ENCAPSED_STRING',
						'\''.$current[1].'\'',
						$current[2],
					);
				}
			}
		}
	}
	
	// Concatenates T_CONSTANT_ENCAPSED_STRING if could
	public function strings_concatenate()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current && $current[0] === 'T_ENCAPSED_AND_WHITESPACE'){
				$next = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
				if($next && $next[0] === 'T_ENCAPSED_AND_WHITESPACE'){
					$this->file_lexems[$key+1] = array(
						'T_ENCAPSED_AND_WHITESPACE',
						$current[1].$next[1],
						$current[2],
					);
					unset($this->file_lexems[$key]);
				}
			}
			elseif($current && $current === '.'){	
				$next = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
				$prev = isset($this->file_lexems[$key-1]) ? $this->file_lexems[$key-1] : null;
				if(is_array($prev) && is_array($next) && $prev[0] == 'T_CONSTANT_ENCAPSED_STRING' && $next[0] == 'T_CONSTANT_ENCAPSED_STRING'){
					unset($this->file_lexems[$key-1]);
					unset($this->file_lexems[$key]);
					$prev[1] = $prev[1][0] === '"' ?  '\''.preg_replace("/'/", '\'', substr($prev[1], 1, -1))      : substr($prev[1], 0, -1);
					$next[1] = $next[1][0] === '"' ?       preg_replace("/'/", '\'', substr($next[1], 1, -1)).'\'' : substr($next[1], 1);
					$this->file_lexems[$key+1] = array(
						'T_CONSTANT_ENCAPSED_STRING',
						$prev[1].$next[1],
						$prev[2],
					);
				}
			}
		}
		$this->file_lexems = array_values($this->file_lexems);
	}
	
	//* Variable control // Add T_CONCAT_EQUAL!
	public function variables_getAll()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current && is_array($current) && $current[0] === 'T_VARIABLE'){
				$next = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
				if($next === '='){
					$variable_end = $this->lexem_getNext($key, ';')-1;
					if($variable_end){
						$var_temp = $this->lexem_getRange($key+2, $variable_end);
						if(count($var_temp) == 3 && $var_temp[0] === '"' &&  $var_temp[0] === 'T_ENCAPSED_AND_WHITESPACE' && $var_temp[2] === '"'){
							$var_temp = array(array(
								'T_CONSTANT_ENCAPSED_STRING',
								'\''.$var_temp[1][1].'\'',
								$var_temp[1][2]
							));
						}
						$this->variables[$current[1]] = $var_temp;
					}					
				}
			}
		}
	}
	
	//* Replace variables with it's content
	public function variables_replace()
	{
		$in_quotes = false;
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current == '"')
				$in_quotes = !$in_quotes ? true : false;		
			if(is_array($current) && $current[0] === 'T_VARIABLE'){
				if(isset($this->variables[$current[1]]) && count($this->variables[$current[1]]) == 1 && in_array($this->variables[$current[1]][0][0], $this->varibles_types_to_concat)){
					$next  = isset($this->file_lexems[$key+1]) ? $this->file_lexems[$key+1] : null;
					$next2 = isset($this->file_lexems[$key+2]) ? $this->file_lexems[$key+2] : null;
					if($next === '('){ // Variables function
						$this->file_lexems[$key][0] = 'T_STRING';
						$this->file_lexems[$key][1] = substr($this->variables[$current[1]][0][1], 1, -1);
						$this->file_lexems[$key][2] = $current[2];
					}elseif(!in_array($next[0], $this->equals_lexems)){ // Variables in double/single quotes
						$this->file_lexems[$key][0] = !$in_quotes ? 'T_CONSTANT_ENCAPSED_STRING'  : 'T_ENCAPSED_AND_WHITESPACE';
						$this->file_lexems[$key][1] = !$in_quotes ? $this->variables[$current[1]][0][1] : substr($this->variables[$current[1]][0][1], 1, -1);
						$this->file_lexems[$key][2] = $current[2];
					}
				}
			}
		}
	}
	
	public function variables_detectBad()
	{
		do{
			$bad_vars_ccount = count($this->variables_bad);
			
			foreach($this->variables as $var_name => $variable){
				
				foreach($variable as $var_part){
					
					if($var_part[0] === 'T_VARIABLE' && (in_array($var_part[1], $this->variables_bad_default) || isset($this->variables_bad[$var_part[1]]))){
						$this->variables_bad[$var_name] = $variable;
						continue(2);
					}
					
				} unset($var_part);
				
			} unset($var_name, $variable);
			
		}while($bad_vars_ccount != count($this->variables_bad));
	}
	
	// Brings all such constructs to include'path';
	public function includes_standartize()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if($current && strpos($current[0], 'INCLUDE') !== false || strpos($current[0], 'REQUIRE') !== false){
				if($this->file_lexems[$key+1] === '('){
					$next_bracket = $this->lexem_getNext($key, ')');
					if($next_bracket !== false)
						unset($this->file_lexems[$key+1]);
						unset($this->file_lexems[$next_bracket]);
				}
				$this->file_lexems = array_values($this->file_lexems);
			}
		}
	}
	
	// Gets all of the include and require constructs. Checks for file extension and checks the path.
	public function includes_getAll()
	{
		for($key = 0, $arr_size = count($this->file_lexems); $key < $arr_size; $key++, $current = isset($this->file_lexems[$key]) ? $this->file_lexems[$key] : null){
			if(strpos($current[0], 'INCLUDE') !== false || strpos($current[0], 'REQUIRE') !== false){
				$include_end = $this->lexem_getNext($key, ';')-1;
				if($include_end){
					$include = $this->lexem_getRange($key+1, $include_end);		
					$this->includes_processsAndSave($include, $key);
				}
			}
		}
	}
	
	public function includes_processsAndSave($include, $key, $unknown = true, $good = true)
	{
		// Checking for bad variables in include
		foreach($include as $value){
			if($value[0] === 'T_VARIABLE' && (in_array($value[1], $this->variables_bad_default) || isset($this->variables_bad[$value[1]]))){
				$good = false;
				break;
			}
		} unset($value);
		
		// Checking for @ before include
		$error_free = $this->file_lexems[$key-1] === '@' ? false : true;
		
		if(count($include) == 1 && $include[0][0] === 'T_CONSTANT_ENCAPSED_STRING'){
			
			$path = substr($include[0][1], 1, -1); // Cutting quotes
			preg_match('/^(((\S:\\{1,2})|(\S:\/{1,2}))|\/)?.*/', $path, $matches); // Reconizing if path is absolute.
			preg_match('/.*\.(\S*)$/', $path, $matches2); // Reconizing extension.
			var_dump($matches2);
			$not_url  = !filter_var($path, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED) ? true : false; // Checks if it is URL
			$path     = empty($matches[1]) && $not_url ? $this->curr_dir.'/'.$path : $path; // Make path absolute
			$exists   = $this->is_text ? null : (realpath($path) ? true : false); // Checks for existence. null if text check.
			$ext_good = $matches2[1] === 'php' ? true : false;
			$unknown  = false;
		}
		
		
		$status = !$good ? false : ($error_free ? ($unknown ? null : (!$not_url || !$exists || !$ext_good ? false : true)) : false);
		
		$this->includes[] = array(
			'include'    => $include,
			'good'       => $good,
			'status'     => $status,
			'not_url'    => $not_url,
			'path'       => $path,
			'exists'     => $exists,
			'ext_good'   => $ext_good,
			'error_free' => $error_free,
			'ext'        => $matches2[1],
			'srting'     => $include[0][2],
		);
	}
	
	public function make_verdict()
	{
		// Detecting bad functions
		foreach($this->file_lexems as $lexem){
			if(is_array($lexem)){
				foreach(self::$bad_constructs as $severity => $set_of_functions){
					foreach($set_of_functions as $bad_function){
						if($lexem[1] === $bad_function){
							$this->verdict[$severity][$lexem[2]] = $bad_function;
						}
					} unset($bad_function);
				} unset($severity, $set_of_functions);
			}
		}
		// Adding bad includes to $verdict
		foreach($this->includes as $include){
			if($include['status']){
				$this->verdict['CRITICAL'][$include['string']] = $nclude['include'][1];
			}
		}
	}
	
	// Getting next setted lexem, Search for needle === if needle is set
	public function lexem_getNext($start, $needle = null)
	{
		for($i = 0, $key = $start+1; $i < 100; $i++, $key++){
			if(isset($this->file_lexems[$key])){
				$current = $this->file_lexems[$key];
				if($needle === null)
					return $key;
				elseif(!is_array($current) && $current === $needle || is_array($current) && $current[1] === $needle)
					return $key;
			}
		}
		return false;
	}
	
	// Getting prev setted lexem, Search for needle === if needle is set
	public function lexem_getPrev($start, $needle = null)
	{
		for($i = 0, $key = $start-1; $i < 100 && $key > 0; $i--, $key--){
			if(isset($this->file_lexems[$key])){
				$current = $this->file_lexems[$key];
				if($needle === null)
					return $key;
				elseif(!is_array($current) && $current === $needle || is_array($current) && $current[1] === $needle)
					return $key;
			}
		}
		return false;
	}
	
	// Getting prev setted lexem, Search for needle === if needle is set
	public function lexem_getRange($start, $end)
	{
		return array_slice($this->file_lexems, $start, $end - $start + 1);
	}
	
	// Strips start output before <? or <?php
	// public function (){
		// $this->file_stripped = preg_replace('/[\s\S]*?<\?(?:php)?/', '', 1,	$this->file_content);
	// }
	
	// Scans file for bad stuctures
	public function surface_scan($detected = false)
	{
		foreach($this->bad_constructs as $type => $constructs){
			if($type === 'functions'){
				foreach($constructs as $function){
					if(strpos($this->file_content, $function) !== false) 
						return array('result' => true, 'type' => $type, 'found' => $function);
				}				
			}
		}
		return $detected;
	}
	
	static public function scan_file_string($string, $string_num, $flags = null)
	{
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
	
	static function get_root_path($end_slah = false){
		return $end_slah ? ABSPATH : substr(ABSPATH, 0, -1);
	}
	
	// Gathering file back
	public function gather_file($input = null, $out = '')
	{
		$input = $input ? $input : $this->file_lexems;
		foreach($input as $key => $lexem)
			$out .= is_array($lexem) ? $input[$key][1] : $input[$key];
		
		return $out;
	}
	
	// MD5 current lexems
	public function md5_file_lexems()
	{		
		return md5($this->gather_file());
	}
	
	static function debug($data, $msg = 'empty', $file = 'Unknown', $line = '?', $func = '?', $start = null, $end = null){
		if(is_array($data)){
			$data = array_slice($data, $start ?: 0, $end ?: null, true);
			$data = array_values($data);
		}
		error_log("$msg | $file:$line: ".($func ?: 'GLOBAL')."\n".print_r($data, true));
	}
	/*
	$this->file_stripped = preg_replace(
		array(
			'/\/\*[\s\S]*?\*\//',       // strips / * * / comments
			'/\/\/.*?(?=\?>|\n)/',      // strips // comments without \n
			'/#[\s\S]*?(?=\?>|\n)/',    // strips #comments without \n
			'/\?>[\s\S]*?<\?(?:php)?/', // strips non php code with tags
		),
		'',
		$this->file_content
	);
	//*/
	
}
