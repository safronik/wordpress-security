<?php
function spbc_perform_scan_wrapper($direct_call = true){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $spbc, $wp_version;
	
	if(preg_match('/^[\d\.]*$/', $wp_version) !== 1){
		SpbcCron::updateTask('perform_scan_wrapper', 'spbc_perform_scan_wrapper', 86400, time()+86400);
		return;
	}
	
	// Getting hashs
	if(empty($spbc->data['scanner']['last_wp_version']) || $spbc->data['scanner']['last_wp_version'] != $wp_version){
		$result = spbc_scanner_get_remote_hashs(true);
		if($result['success'] === false){
			$output  = array('error' => true, 'error_string' => 'Can not get remote hashs', 'error_time' => current_time('timestamp'));
			$spbc->data['errors']['get_hashs'] = $output;
			$spbc->save('data');
			if($direct_call) return; else die(json_encode($output, true));
		}else{
			unset($spbc->data['errors']['get_hashs']);
		}
	}
	
	// Surface scan
	$amount = 0;
	$offset = 0;
	do{
		$result = spbc_scanner_scan(true, false, $offset, $amount);
		if($result['success'] === false){
			$output  = array('error' => true, 'error_string' => 'Can not scan', 'error_time' => current_time('timestamp'));
			$spbc->data['errors']['scan'] = $output;
			$spbc->save('data');
			if($direct_call) return; else die(json_encode($output, true));
		}
		$offset += 1000;
	}while($result['files_count'] != 0 && $result['files_count'] >= $amount);
	unset($spbc->data['errors']['scan']);
	
	// Counting modified
	$result = spbc_scanner_count_files_unckecked('COMPROMISED', true);
	if($result['success'] === false){
		$output  = array('error' => true, 'error_string' => 'Can not count unchecked', 'error_time' => current_time('timestamp'));
		$spbc->data['errors']['count_unchecked'] = $output;
		unset($spbc->data['errors']['count_unchecked']);
		$spbc->save('data');
		if($direct_call) return; else die(json_encode($output, true));
	}else{
		unset($spbc->data['errors']['count_unchecked']);
		$amount_to_check = $result['files_total'];
	}
	
	//Checking modified
	if($amount_to_check != 0 && $amount_to_check < 30){
		$result = spbc_scanner_scan_modified(true, 'COMPROMISED');
		if($result['success'] === false){
			$output  = array('error' => true, 'error_string' => 'Can not scan modified files', 'error_time' => current_time('timestamp'));
			$spbc->data['errors']['scan_modified'] = $output;
			$spbc->save('data');
			if($direct_call) return; else die(json_encode($output, true));
		}else{
			$spbc->data['last_scanned'] = current_time('timestamp');
			unset($spbc->data['errors']['scan_modified']);
			spbc_scanner_send_results(true);
			if($direct_call) return; else die(json_encode($output, true));
		}
	}else{
		SpbcCron::addTask('scanner_scan_modified', 'spbc_scanner_scan_modified', 1800);
	}
	
	$output = array(
		'success' => true,
	);
	
	if($direct_call)
		return $output; 
	else
		die(json_encode($output, true));
	
}

function spbc_scanner_scan_modified_wrapper(){
	
	global $spbc;
	
	$result = spbc_scanner_scan_modified(true, 'COMPROMISED');
	if($result['success'] === false){
		$output  = array('error' => true, 'error_string' => 'Can not scan', 'error_time' => current_time('timestamp'));
		$spbc->data['errors']['scan_modified'] = $output;
		$spbc->save('data');
		return;
	}
	
	$result = spbc_scanner_count_files_unckecked('COMPROMISED', true);
	if($result['success'] === false){
		$output  = array('error' => true, 'error_string' => 'Can not scan', 'error_time' => current_time('timestamp'));
		$spbc->data['errors']['count_unchecked'] = $output;
		$spbc->save('data');
		return;
	}
	
	SpbcCron::removeTask('scanner_scan_modified');
	
	if($result['files_total'] > 0){
		SpbcCron::addTask('scanner_scan_modified', 'spbc_scanner_scan_modified', 1800);
	}
}

function spbc_scanner_count_files($direct_call = false){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	require_once(SPBC_PLUGIN_DIR . 'lib/SpbcScaner.php');
	
	$path_to_scan = realpath(SPBC_PLUGIN_DIR . '/../../..');
	$init_params  = array(
		'count'          => true,
		'dir_exceptions' => array(
			$path_to_scan . '/wp-content/cache',
			$path_to_scan . '/wp-content/themes',
			$path_to_scan . '/wp-content/plugins',
			$path_to_scan . '/wp-content/uploads',
			$path_to_scan . '/wp-content/languages',
		),
		'extensions' => 'php',
	);
$time_start = microtime(true);
	$scaner = new SpbcScaner($path_to_scan, $init_params);
$exec_time = microtime(true) - $time_start;

	$output = array(
		'files_total' => $scaner->files_count,
		'success'     => (bool) $scaner->files_count,
		'exec_time'   => $exec_time,
	);
	
	if($direct_call){
		return $output;
	}else{
		die(json_encode($output));		
	}
}

function spbc_scanner_count_files_unckecked($status = null, $direct_call = false){
	
	if($direct_call === false)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
$time_start = microtime(true);
	$sql = 'SELECT COUNT(fast_hash) AS cnt FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.($status ? ' WHERE' : '').($status ? " status = '$status'" : '');
	$files_count = $wpdb->get_results($sql, ARRAY_A);
$exec_time = microtime(true) - $time_start;
	
	$output = array(
		'files_total' => $files_count[0]['cnt'],
		'success'     => $files_count[0]['cnt'] ? true : false,
		'exec_time'   => $exec_time,
	);
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
	
	
}

function spbc_scanner_scan($direct_call = false, $count = false, $offset = 0, $amount = 1500){
	
	if(!$direct_call){
		check_ajax_referer('spbc_secret_nonce', 'security');
		$count  = !empty($_POST['count'])  ? $_POST['count']  : $count;
		$offset = !empty($_POST['offset']) ? $_POST['offset'] : $offset;
		$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
	}
	
	if($count)
		spbc_scanner_count_files(true);
	
	global $spbc, $wpdb;
	
	require_once(SPBC_PLUGIN_DIR . 'lib/SpbcScaner.php');   // Scaner
	
	$path_to_scan  = realpath(SPBC_PLUGIN_DIR . '/../../..');
	$init_params = array(
		'fast_hash'        => true,
		'full_hash'        => true,
		'offset'                => $offset,
		'amount'                => $amount,
		'extensions'            => 'php',
		'extensions_exceptions' => '', //array('jpg', 'jpeg', 'png', 'gif', 'css', 'txt', 'zip', 'xml', 'json')
		'file_exceptions'       => '',
		'dir_exceptions'        => array(
			$path_to_scan . '/wp-content/cache',
			$path_to_scan . '/wp-content/themes',
			$path_to_scan . '/wp-content/plugins',
			$path_to_scan . '/wp-content/uploads',
			$path_to_scan . '/wp-content/languages',
		),
	);
	
$time_start = microtime(true);

	$scaner = new SpbcScaner($path_to_scan, $init_params);
		
	if($scaner->files_count){
		$sql = 'INSERT INTO ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS . ' (`fast_hash`, `full_hash`, `path`, `size`, `perms`, `mtime`, `status`, `severity`) VALUES ';
		foreach($scaner->files as $key => $file){
			$file['path'] = addslashes($file['path']);
			$sql .="('{$file['fast_hash']}','{$file['full_hash']}','{$file['path']}','{$file['size']}','{$file['perms']}','{$file['mtime']}','UNKNOWN',NULL),"; 
		} unset($key, $file);
		$sql = substr($sql, 0, -1);
		$sql .= " ON DUPLICATE KEY UPDATE

			fast_hash      = fast_hash,
			path           = path,
			real_full_hash = real_full_hash,

			status = 
				IF(status IN ('NOT_CHECKED') OR mtime <> values(`mtime`) OR mtime IS NULL,
					IF(real_full_hash <> VALUES(`full_hash`) OR real_full_hash IS NULL,
						IF(real_full_hash IS NULL,
							'UNKNOWN',
							'COMPROMISED'
					   ),
					   'OK'
				   ),
				   status
				),
				
			severity  = severity,
				# IF(severity IS NULL,
					# IF(status IN ('NOT_CHECKED', 'COMPROMISED'),
						# 'SUSPICIOUS',
						# NULL
					# ),
					# severity
				# ),
			
			full_hash = VALUES(`full_hash`),
			size      = VALUES(`size`),
			perms     = VALUES(`perms`),
			mtime     = VALUES(`mtime`);";
			
		$success = $wpdb->query($sql);
				
	}
	
$exec_time = microtime(true) - $time_start;
	
	$output  = array(
		'files_count' => $scaner->files_count,
		'dirs_count'  => $scaner->dirs_count,
		'exec_time'   => $exec_time,
		'offset'      => $init_params['offset'],
		'success'     => isset($success) ? $success : 0,
	);
	
	if(!$direct_call)
		die(json_encode($output));
	else
		return $output;

}

function spbc_scanner_scan_modified($direct_call = false, $status = 'COMPROMISED', $count = false, $offset = 0, $amount = 30){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb, $wp_version;
	
	$status   = !empty($_POST['status'])   ? $_POST['status']   : $status;
	$count    = !empty($_POST['count'])    ? true               : false;
	$offset   = !empty($_POST['offset'])   ? $_POST['offset']   : 0;
	$amount   = !empty($_POST['amount'])   ? $_POST['amount']   : 30;
	
	if($count)
		spbc_scanner_count_files_unckecked($status);
	
$time_start = microtime(true);
	
	$sql = 'SELECT path, status, fast_hash, real_full_hash 
		FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
		.($status ? ' WHERE' : '').($status ? " status = '$status'" : '')
		." LIMIT $offset, $amount";
	$files_to_check = $wpdb->get_results($sql, ARRAY_A);
	
	if(count($files_to_check)){
	
		require_once(SPBC_PLUGIN_DIR . 'lib/SpbcScaner.php');   // Scaner
		
		$scan_result = array('CRITICAL' => 0, 'DANGER' => 0, 'SUSPICIOUS' => 0, 'NULL' => 0);
		
		foreach($files_to_check as $file){
			
			$result = SpbcScaner::scan_file($file, $wp_version);
			
			$sql = 'UPDATE '. SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
				." SET
				status = '{$result['status']}',
				severity = '{$result['severity']}',
				weak_spots = '{$result['weak_spots']}',
				difference = '{$result['difference']}'
				WHERE fast_hash = '{$file['fast_hash']}'";
			$succes = $wpdb->query($sql, ARRAY_A);
			
			$scan_result[$result['severity']]++;
			
		}
	
	}
	
$exec_time = microtime(true) - $time_start;
	
	$output  = array(
		'scanned'   => count($files_to_check),
		'success'   => isset($scan_result) ? $scan_result : null,
		'exec_time' => $exec_time,
	);
	
	if(!$direct_call)
		die(json_encode($output));
	else
		return $output;

}

function spbc_scanner_send_results($direct_call = false){
	
	global $spbc, $wpdb;
	
	// Getting unknown and modified files.
	$sql = 'SELECT path, full_hash, mtime, status
		FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
		WHERE status IN ("COMPROMISED", "INFECTED", "UNKNOWN")';
	$sql_result = $wpdb->get_results($sql, ARRAY_A);
	$rows_count = count($sql_result);
	
	$unknown  = array();
	$modified = array();
	$is_windows = strpos(php_uname('s'), 'Windows') !== false ? true : false;
	if($rows_count){
		foreach($sql_result as $row){
			$row['path'] = $is_windows ? str_replace('\\', '/', $row['path']) : $row['path'];
			if($row['status'] == 'UNKNOWN'){
				$unknown[$row['path']] = array($row['full_hash']);
			}else{
				$modified[$row['path']] = array($row['full_hash'], $row['mtime']);
			}
		}
	}
	
	// Count files to scan
	$scanned_total = spbc_scanner_count_files(true);
	$scanned_total = $scanned_total['files_total'];
	
	// API call
	$result = SpbcHelper::securityMscanLogs(
		$spbc->settings['spbc_key'],
		$spbc->service_id,
		current_time('Y-m-d H:i:s'),
		$rows_count ? 'failed' : 'passed',
		$scanned_total,
		$modified,
		$unknown
	);
	
	if(empty($result['error'])){
		$spbc->data['last_scan_results_sent'] = current_time('timestamp');
		$spbc->data['last_scanned'] = current_time('timestamp');
		unset($spbc->data['errors']['scanner_result_send']);
	}else{
		$spbc->data['errors']['scanner_result_send'] = $result;
	}
	$spbc->save('data');
	
	if($direct_call){
		return $result;
	}else{
		die(json_encode($result));		
	}
}

function spbc_scanner_file_send($direct_call = false, $file_id = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, full_hash
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
				
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_readable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					if(filesize($_SERVER['DOCUMENT_ROOT'].$file_info['path']) > 0){					
						if(filesize($_SERVER['DOCUMENT_ROOT'].$file_info['path']) < 1048570){
					
							// Getting file && API call
							$file = file_get_contents($_SERVER['DOCUMENT_ROOT'].$file_info['path']);
							$result = SpbcHelper::securityMscanFiles($spbc->settings['spbc_key'], $file_info['path'], $file, $file_info['full_hash']);
							
							if(empty($result['error'])){
								if($result['result']){
									
									// Updating "last_sent"
									$sql_result = $wpdb->query('UPDATE '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.' SET last_sent = '.current_time('timestamp').' WHERE fast_hash = "'.$file_id.'"');
									
									if($sql_result !== false){
										$output = array('success' => true, 'result' => $result);
									}else
										$output = array('error' => true, 'error_string' =>'DB_COULDNT_UPDATE_ROW');
								}else
									$output = array('error' => true, 'error_string' =>'API_RESULT_IS_NULL');
							}else
								$output = $result;
						}else
							$output = array('error' => true, 'error_string' =>'FILE_SIZE_TO_LARGE');
					}else
						$output = array('error' => true, 'error_string' =>'FILE_SIZE_ZERO');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
		
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_file_delete($direct_call = false, $file_id = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, full_hash
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_writable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					
					// Getting file && API call
					$result = unlink($_SERVER['DOCUMENT_ROOT'].$file_info['path']);
					
					if($result){
						
						// Deleting row from DB
						$sql_result = $wpdb->query('DELETE FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.' WHERE fast_hash = "'.$file_id.'"');
							
						if($sql_result !== false){
							$output = array('success' => true);
						}else
							$output = array('error' => true, 'error_string' =>'DB_COULDNT_DELETE_ROW');
					}else
						$output = array('error' => true, 'error_string' =>'FILE_COULDNT_DELETE');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_WRITABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_file_approve($direct_call = false, $file_id = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, full_hash
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_readable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					
					// Getting file && API call
					$md5 = md5_file($_SERVER['DOCUMENT_ROOT'].$file_info['path']);
					
					if($md5){
						
						$sql = 'UPDATE '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
							SET status = "OK", severity = NULL, real_full_hash = "'.$md5.'"
							WHERE fast_hash = "'.$file_id.'"';
						$sql_result = $wpdb->get_results($sql, ARRAY_A);
							
						if($sql_result !== false){
							$output = array('success' => true);
						}else
							$output = array('error' => true, 'error_string' =>'DB_COULDNT_UPDATE_ROW_APPROVE');
					}else
						$output = array('error' => true, 'error_string' =>'FILE_COULDNT_MD5');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_file_view($direct_call = false, $file_id = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, status, severity
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_readable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					
					// Getting file && API call
					$file = file($_SERVER['DOCUMENT_ROOT'].$file_info['path']);
					
					if(count($file)){
						
						$file_text = array();
						for($i=0; isset($file[$i]); $i++){
							$file_text[$i+1] = htmlspecialchars($file[$i]);
							$file_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_text[$i+1]);
						}
							
						if(!empty($file_text)){
							$output = array('success' => true, 'file' => $file_text, 'file_path' => $_SERVER['DOCUMENT_ROOT'].$file_info['path']);
						}else
							$output = array('error' => true, 'error_string' =>'FILE_TEXT_EMPTY');
					}else
						$output = array('error' => true, 'error_string' =>'FILE_EMPTY');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_file_compare($direct_call = false, $file_id = null, $platform = 'wordpress'){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb, $wp_version;
	
	$cms_version = $wp_version;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, status, severity, weak_spots, difference
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_readable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					
					// Getting file && API call
					$file = file($_SERVER['DOCUMENT_ROOT'].$file_info['path']);
					
					if(count($file)){
						
						$file_text = array();
						for($i=0; isset($file[$i]); $i++){
							$file_text[$i+1] = htmlspecialchars($file[$i]);
							$file_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_text[$i+1]);
						}
						if(!empty($file_text)){
							
							$file_original = file('http://cleantalk-security.s3.amazonaws.com/cms_sources/'.$platform.'/'.$cms_version.str_replace('\\', '/', $file_info['path']));
							
							if($file_original){
								
								$file_original_text = array();
								for($i=0; isset($file_original[$i]); $i++){
									$file_original_text[$i+1] = htmlspecialchars($file_original[$i]);
									$file_original_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_original_text[$i+1]);
								}
								if(!empty($file_original_text)){
									$output = array('success' => true, 'file' => $file_text, 'file_original' => $file_original_text, 'file_path' => $_SERVER['DOCUMENT_ROOT'].$file_info['path'], 'weak_spots' => $file_info['weak_spots'], 'difference' => $file_info['difference']);
								}else
									$output = array('error' => true, 'error_string' =>'FILE_ORIGINAL_TEXT_EMPTY');
							}else
								$output = array('error' => true, 'error_string' =>'GET_FILE_REMOTE_FAILED');
						}else
							$output = array('error' => true, 'error_string' =>'FILE_TEXT_EMPTY');
					}else
						$output = array('error' => true, 'error_string' =>'FILE_EMPTY');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_READABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_file_replace($direct_call = false, $file_id = null, $platform = 'wordpress'){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb, $wp_version;
	
	$cms_version = $wp_version;
	
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, status, severity
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
				if(is_writable($_SERVER['DOCUMENT_ROOT'].$file_info['path'])){
					
					// Getting file && API call
					$original_file = file_get_contents('http://cleantalk-security.s3.amazonaws.com/cms_sources/'.$platform.'/'.$cms_version.str_replace('\\', '/', $file_info['path']));
					
					if($original_file){
						
						$file_desc = fopen($_SERVER['DOCUMENT_ROOT'].$file_info['path'], 'w');
						
						if($file_desc){
							
							$res_fwrite = fwrite($file_desc, $original_file);
							
							if($res_fwrite){
								
								$res_fclose = fclose($file_desc);
								
								if($res_fclose){
									$output = array('success' => true,);
								}else
									$output = array('error' => true, 'error_string' =>'FILE_COULDNT_CLOSE');
							}else
								$output = array('error' => true, 'error_string' =>'FILE_COULDNT_WRITE');
						}else
							$output = array('error' => true, 'error_string' =>'FILE_COULDNT_OPEN');
					}else
						$output = array('error' => true, 'error_string' =>'GET_FILE_FAILED');
				}else
					$output = array('error' => true, 'error_string' =>'FILE_NOT_WRITABLE');
			}else
				$output = array('error' => true, 'error_string' =>'FILE_NOT_EXISTS');
		}else
			$output = array('error' => true, 'error_string' =>'FILE_NOT_FOUND');
	}else
		$output = array('error' => true, 'error_string' =>'WRONG_FILE_ID');
	
	$exec_time = microtime(true) - $time_start;
	if(!empty($output['error']))
		$output['error_time'] = current_time('timestamp');
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_get_remote_hashs($direct_call = false){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $spbc, $wp_version;
	
	if(empty($spbc->data['scanner']['last_wp_version']) || $spbc->data['scanner']['last_wp_version'] != $wp_version){
	
		// Clear scan results
		$result = spbc_scanner_clear(true);
		
		if(empty($result['error'])){
			
			// Getting hashs
			require_once(SPBC_PLUGIN_DIR . 'lib/SpbcScaner.php');   // Scaner
			
			global $wpdb, $wp_version;
			
		$start = microtime(true);
			
			$result = SpbcScaner::get_hashs('path', 'wordpress', $wp_version);
			
			if(empty($error)){
				$is_windows = strpos(strtolower(php_uname('s')), 'windows') !== false ? true : false;
				$sql = 'INSERT INTO ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS . ' (`fast_hash`, `path`, `real_full_hash`) VALUES ';
				foreach($result['checksums'] as $path => $real_full_hash){
					$path = $is_windows ? str_replace('/', '\\', $path) : $path;
					$fast_hash = md5($path);
					$path = addslashes($path);
					$sql .= "('$fast_hash', '$path', '$real_full_hash'),";
				} unset($path, $real_full_hash);
				$sql = substr($sql, 0, -1);
				
				$success = $wpdb->query($sql);
				
		$exec_time = microtime(true) - $start;
				
				$output  = array(
					'files_count' => $result['checksums_count'],
					'exec_time'    => $exec_time,
					'success'      => $success,
				);
				$spbc->data['scanner']['last_wp_version'] = $wp_version;
				unset($spbc->data['errors']['get_hashs']);
			}else
				$output = $result;
		}else
			$output  = array('error' => true, 'error_string' => 'Can not clear scan table.',);
	}else
		$output  = array('success' => true,	'msg' => 'Already up to date.',);
	
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_clear($direct_call = false){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
	$success = $wpdb->query('DELETE FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS . ' WHERE 1');
	
	$output  = array(
		'success' => $success,
	);
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_list_results($direct_call = false, $offset = 0, $amount = 20){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
	$offset = !empty($_POST['offset']) ? $_POST['offset'] : $offset;
	$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
	$type   = !empty($_POST['type'])   ? $_POST['type']   : null;
	
	$sql_template_amount  = 'SELECT COUNT(fast_hash) as cnt FROM '.SPBC_DB_PREFIX.SPBC_SCAN_RESULTS.' WHERE %s = "%s"';
	$sql_template_entries = 'SELECT fast_hash, path, size, perms, mtime, DATE_FORMAT(FROM_UNIXTIME(mtime), \'%%b %%d %%Y %%H:%%i:%%s\') AS mtime_str, last_sent, status FROM '.SPBC_DB_PREFIX.SPBC_SCAN_RESULTS.' WHERE %s = "%s" LIMIT %d, %d';
	
	$output  = array(
		'data' => array(),
	);
		
	if(!$type || $type == 'unknown'){
		$unknown_files  = $wpdb->get_results(sprintf($sql_template_entries, 'status', 'UNKNOWN', $offset, $amount), ARRAY_A);
		$unknown_amount = $wpdb->get_results(sprintf($sql_template_amount,  'status', 'UNKNOWN'                  ), ARRAY_A);
		$output['data']['unknown'] = array(
			'list'   => $unknown_files,
			'amount' => $unknown_amount[0]['cnt'],
		);
	}
	
	if(!$type || $type == 'compromised'){
		$compromised_files  = $wpdb->get_results(sprintf($sql_template_entries, 'status', 'COMPROMISED', $offset, $amount), ARRAY_A);
		$compromised_amount = $wpdb->get_results(sprintf($sql_template_amount,  'status', 'COMPROMISED'                  ), ARRAY_A);
		$output['data']['compromised'] = array(
			'list'   => $compromised_files,
			'amount' => $compromised_amount[0]['cnt'],
		);
	}
	
	if(!$type || $type == 'critical'){
		$critical_files  = $wpdb->get_results(sprintf($sql_template_entries, 'severity', 'CRITICAL', $offset, $amount), ARRAY_A);
		$critical_amount = $wpdb->get_results(sprintf($sql_template_amount,  'severity', 'CRITICAL'                  ), ARRAY_A);
		$output['data']['critical'] = array(
			'list'   => $critical_files,
			'amount' => $critical_amount[0]['cnt'],
		);
	}
	
	if(!$type || $type == 'danger'){
		$dangerous_files  = $wpdb->get_results(sprintf($sql_template_entries, 'severity', 'DANGER', $offset, $amount), ARRAY_A);
		$dangerous_amount = $wpdb->get_results(sprintf($sql_template_amount,  'severity', 'DANGER'                  ), ARRAY_A);
		$output['data']['dangerous'] = array(
			'list'   => $dangerous_files,
			'amount' => $dangerous_amount[0]['cnt'],
		);
	}
	
	if(!$type || $type == 'suspicious'){
		$suspicious_files  = $wpdb->get_results(sprintf($sql_template_entries, 'severity', 'SUSPICIOUS', $offset, $amount), ARRAY_A);
		$suspicious_amount = $wpdb->get_results(sprintf($sql_template_amount,  'severity', 'SUSPICIOUS'                  ), ARRAY_A);
		$output['data']['suspicious'] = array(
			'list'   => $suspicious_files,
			'amount' => $suspicious_amount[0]['cnt'],
		);
	}
	
	if(!$type){
		$total_amount = $wpdb->get_results('SELECT COUNT(fast_hash) as cnt FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS, ARRAY_A.' WHERE status IN (\'UNKNOWN\', \'COMPROMISED\') AND severity IS NOT NULL');
		$output['bad_amount']   = $critical_amount[0]['cnt'] + $dangerous_amount[0]['cnt'] + $suspicious_amount[0]['cnt'] + $compromised_amount[0]['cnt'] + $unknown_amount[0]['cnt'];
		$output['total_amount'] = $total_amount[0]['cnt'];
	}
	
	$output['success'] = !empty($critical_files) || !empty($dangerous_files) || !empty($suspicious_files) || !empty($unknown_files) || !empty($compromised_files) ? true : false;
	
	// Changing '123456789' to '123 456 789'
	foreach($output['data'] as $key => $value){
		foreach($value['list'] as $key2 => $value2){
			$output['data'][$key]['list'][$key2]['size_str'] = spbc_size_to_string($value2['size']);
		}
	}
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_get_cms_hash(){
	
	require_once(SPBC_PLUGIN_DIR . 'lib/SpbcScaner.php');   // Scaner
	
	// $path_to_scan  = realpath(SPBC_PLUGIN_DIR . '/../../..');
	$path_to_scan  = realpath(SPBC_PLUGIN_DIR);
	$init_params = array(
		'fast_hash'        => true,
		'full_hash'        => true,
		'offset'                => 0,
		'amount'                => 100000,
		'extensions'            => 'php',
		'extensions_exceptions' => '', //array('jpg', 'jpeg', 'png', 'gif', 'css', 'txt', 'zip', 'xml', 'json')
		'file_exceptions'       => '',
		'dir_exceptions' => array(
			$path_to_scan . 'wp-content/cache',
			$path_to_scan . 'wp-content/themes',
			$path_to_scan . 'wp-content/plugins',
			$path_to_scan . 'wp-content/uploads',
			$path_to_scan . 'wp-content/languages',
		),
	);
		
$time_start = microtime(true);
	// $scaner = new SpbcScaner($path_to_scan); // Count
	$scaner = new SpbcScaner($path_to_scan, $init_params); // Scan
$exec_time = microtime(true) - $time_start;
	
	// Creating file with checksums
	$result = array(
		'checksums_count' => $scaner->files_count,
		'checksums' => array(),
	);
		
	foreach($scaner->files as $file){
		$result['checksums'][$file['path']] = $file['full_hash'];
	}
	
	$result = json_encode(array('data' => $result));
	
	$fd = fopen(SPBC_PLUGIN_DIR . '/hash.json', 'w+');
	fwrite($fd, $result);
	fclose($fd);
}

function spbc_size_to_string($size){
	$size = strrev((string)$size);
	$result = '';
	for($i=0; isset($size[$i]); $i++)
		$result .= ($i%3 == 0 ? ' ' : '') . $size[$i];
	
	return strrev($result);
}

function spbc_recognize_path_style(){
	
	return;
}