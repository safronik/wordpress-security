<?php
function spbc_perform_scan_wrapper($end = false){
	
	global $spbc;
	$total_scanned = 0;
	
	// Call from cron
	if(!$end){
		
		// Getting remote hashes
		$result = spbc_scanner_get_remote_hashes(true);
		if(!empty($result['error']))
			return $result;
		SpbcHelper::deleteError('get_hashs', 'save_data');
		
		// Clearing table
		spbc_scanner_clear_table(true);
	}	
	
	// CORE check
		// Surface
		$result = spbc_scanner_scan_surface(ABSPATH);
		if(!empty($result['error']))	return $result;
		$total_scanned += $result;
		
		// Deep
		$result = spbc_scanner_scan_deep(ABSPATH, $end);
		$completed = !empty($result['completed']) ? true : false;
		if(!empty($result['error']))	return $result;
		
	// PLUGIN check
	if(!empty($spbc->settings['heuristic_analysis'])){
		// Surface
		$result = spbc_scanner_scan_surface(WP_CONTENT_DIR);
		if(!empty($result['error'])) return $result;
		$total_scanned += $result;
		
		// Deep
		$result = spbc_scanner_scan_deep(WP_CONTENT_DIR, $end);
		$completed = !empty($result['completed']) ? true : false;
		if(!empty($result['error']))	return $result;
	}
	
	if(!empty($completed)){
		$spbc->data['scanner']['last_scan'] = current_time('timestamp');
		spbc_scanner_send_results(true, $total_scanned);
	}
	
	// LINKS check
	// if(!empty($spbc->settings['scan_outbound_links'])){
		// $offset = 0;
		// do{
			// $result = spbc_scanner_scan_links(true, $offset);
			// $offset += 10;
		// }while($result['scanned'] === 0);
	// }
	
	// SENDING RESULTS
	
}

function spbc_scanner_scan_surface($path = ABSPATH){
	
	// Surface scan CORE
	$offset = 0;
	$total_scanned = 0;
	do{
		$result = spbc_scanner_scan(true, $offset, SPBC_SCAN_SURFACE_AMOUNT, $path);
		if(!empty($result['error']))
			return $result;
		
		$offset += SPBC_SCAN_SURFACE_AMOUNT;
		$total_scanned += $result['files_count'];
	}while($result['files_count'] != 0 && $result['files_count'] >= SPBC_SCAN_SURFACE_AMOUNT);
	
	SpbcHelper::deleteError('scan', 'save_data');
	
	return $total_scanned;
}

function spbc_scanner_scan_deep($path = ABSPATH, $end = false){
	
	$source = realpath($path) == realpath(ABSPATH) ? 'core' : 'plugin';
	
	//Checking modified
	$result = spbc_scanner_scan_modified(true, 'UNKNOWN,COMPROMISED', SPBC_SCAN_MODIFIED_AMOUNT, $path);
	if($result['success'] === false)
		return array('error' => true, 'error_string' => 'Can not scan modified files');
	SpbcHelper::deleteError('scan_modified', 'save_data');
	
	if($result['scanned'] == SPBC_SCAN_MODIFIED_AMOUNT){
		SpbcCron::updateTask('scanner_scan_deep_'.$source, 'spbc_scanner_scan_deep', SPBC_SCAN_MODIFIED_PERIOD, null, array($path, false));
	}else{
		SpbcCron::removeTask('scanner_scan_deep_'.$source);
		if(!$end) spbc_perform_scan_wrapper(true);
		return array('completed' => true);
	}
}

function spbc_scanner_get_remote_hashes($direct_call = false){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $spbc, $wp_version, $wpdb;
	
	if(preg_match('/^[\d\.]*$/', $wp_version) === 1){
		
		if(ini_get('allow_url_fopen')){
			
			if(!isset($spbc->data['scanner']['last_wp_version']) || (isset($spbc->data['scanner']['last_wp_version']) && $spbc->data['scanner']['last_wp_version'] != $wp_version)){
				
				$result = spbc_scanner_clear(true); // Clear scan results
				
				if(empty($result['error'])){
					
					// Getting hashs				
				$start = microtime(true);
					
					$result = SpbcScaner::get_hashs('path', 'wordpress', $wp_version);
					
					if(empty($result['error'])){
						$is_windows = spbc_is_windows() ? true : false;
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
						SpbcHelper::deleteError('get_hashs', 'save_data');
						$spbc->save('data');
						
					}else
						$output = $result;
				}else
					$output  = array('error' => true, 'error_string' => 'Can not clear scan table.',);
			}else
				$output = array('success' => true, 'error_string' => 'Already up to date.',);
		}else
			$output = array('success' => true, 'error_string' => 'allow_url_fopen is disabled',);
	}else
		$output = array('error' => true, 'error_string' => 'Your Wordpress version is not supported');
		
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_clear_table($direct_call = false, $offset = 0, $amount = 25000){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	if(!$direct_call){
		$offset = !empty($_POST['offset']) ? $_POST['offset'] : $offset;
		$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
	}
	
	global $spbc, $wpdb;
	
	$time_start = microtime(true);
	
	$result = $wpdb->get_results(
		'SELECT path, fast_hash'
			.' FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
			." LIMIT $offset, $amount;",
		ARRAY_A
	);
	
	$root_path = spbc_get_root_path();
	
	$to_delete = "''";
	
	foreach($result as $value){
		if(!file_exists($root_path.$value['path']))
			$to_delete .= ",'{$value['fast_hash']}'";
	} unset($value);
	
	$sql = 'DELETE FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
			." WHERE fast_hash IN ($to_delete);";
	
	$result = $wpdb->query($sql	
	);
	
	$output = array(
		'succes'    => $result !== false ? true : false,
		'deleted'   => (int)$result,
		'exec_time' => microtime(true) - $time_start,
	);
	
	if(!$direct_call) die(json_encode($output)); else return $output;
	
}

function spbc_scanner_clear($direct_call = false){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
	$output  = array(
		'error' => $wpdb->query('DELETE FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS . ' WHERE 1') === false ? true : false,
	);
	
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_count_files($direct_call = false, $path = ABSPATH){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	if(!$direct_call){
		$path = !empty($_POST['path']) ? $_POST['path'] : $path;
	}
	
	$path_to_scan = realpath($path);
	$root_path    = realpath(ABSPATH);
	$init_params  = array(
		'count'          => true,
		'file_exceptions' => 'wp-config.php',
		'extensions'      => 'php',
		'dir_exceptions'  => $path_to_scan !== realpath(ABSPATH)
			? array()
			: array(
				preg_quote($path_to_scan, '/') . '(\/|\\\\)wp-content(\/|\\\\)?.+',
			)
	);
	
	$scaner = new SpbcScaner($path_to_scan, $root_path, $init_params);
	
	$output = array(
		'files_total' => $scaner->files_count,
	);
	
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_count_modified_files($status = null, $direct_call = false, $path = ABSPATH){
	
	if($direct_call === false) check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb;
	
	$status = !empty($_POST['status']) ? stripslashes($_POST['status']) : $status;
	if(is_string($status)) if(explode(',',$status)) $status = explode(',',$status);
	if(is_array($status))                           $status = implode('\',\'', $status);
		
	$path   = !empty($_POST['path'])   ? $_POST['path']   : $path;
	$path   = realpath($path);
	
	$source = realpath($path) == realpath(ABSPATH) ? 'CORE' : 'PLUGIN';
	
	$sql = 'SELECT COUNT(fast_hash) AS cnt'
		.' FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
		." WHERE source = '$source'".($status ? " AND status IN ('$status')" : '');
	$files_count = $wpdb->get_results($sql, ARRAY_A);
	
	$success = $files_count !== null ? true : false;
		
	$output = array(
		'success'     => $success,
		'files_total' => $success ? $files_count[0]['cnt'] : 0,
	);
	
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_count_links($direct_call = false){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	
	$links_scanner = new SpbcScannerLinks(array('count' => true));
	
	$output  = array(		
		'posts_total' => $links_scanner->posts_total,
	);
		
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_scan($direct_call = false, $offset = 0, $amount = 1500, $path = ABSPATH){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	if(!$direct_call){
		$offset = !empty($_POST['offset']) ? $_POST['offset'] : $offset;
		$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
		$path   = !empty($_POST['path'])   ? $_POST['path']   : $path;
	}
	
	global $spbc, $wpdb;
	
	$source = realpath($path) == realpath(ABSPATH) ? 'CORE' : 'PLUGIN';
	
	$path_to_scan = realpath($path);
	$root_path    = realpath(ABSPATH);
	$init_params = array(
		'fast_hash'        		=> true,
		'full_hash'       		=> true,
		'offset'                => $offset,
		'amount'                => $amount,
		'extensions'            => 'php',
		'extensions_exceptions' => '', //array('jpg', 'jpeg', 'png', 'gif', 'css', 'txt', 'zip', 'xml', 'json')
		'file_exceptions'       => 'wp-config.php',
		'dir_exceptions'  => $source === 'PLUGIN'
			? array()
			: array(
				preg_quote($path_to_scan, '/') . '(\/|\\\\)wp-content(\/|\\\\)?.+',
			)
	);
	
	$time_start = microtime(true);

	$scaner = new SpbcScaner($path_to_scan, $root_path, $init_params);
		
	if($scaner->files_count){
		
		$sql = 'INSERT INTO ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS 
			. ' (`path`, `size`, `perms`, `mtime`, `source`,`status`,`fast_hash`, `full_hash`) VALUES ';
		foreach($scaner->files as $key => $file){
			$file['path'] = addslashes($file['path']);
			$sql .="('{$file['path']}','{$file['size']}','{$file['perms']}','{$file['mtime']}','$source','UNKNOWN','{$file['fast_hash']}','{$file['full_hash']}'),"; 
		} unset($key, $file);
		$sql = substr($sql, 0, -1);
		$sql .= " ON DUPLICATE KEY UPDATE
			
			size      = VALUES(`size`),
			perms     = VALUES(`perms`),
			
			severity  = severity,
			fast_hash = fast_hash,
			full_hash = VALUES(`full_hash`),
			real_full_hash = real_full_hash,
			
			checked = 
				IF(real_full_hash IS NOT NULL AND real_full_hash = VALUES(`full_hash`),
					'YES',
					IF(mtime <> VALUES(`mtime`) OR mtime IS NULL,
						'NO',
						checked
					)
				),
			
			status = 
				IF(mtime <> VALUES(`mtime`) OR mtime IS NULL,
					IF(real_full_hash IS NULL,
						IF(checked = 'YES',
							status,
							'UNKNOWN'
						),
						IF(real_full_hash = VALUES(`full_hash`),
							'OK',
							'COMPROMISED'
						)
					),
					status
				),
			
			mtime     = VALUES(`mtime`);";
		
		$success = $wpdb->query($sql);
	}
		
	if(isset($success) && $success === false){
		$output  = array('error' => true, 'error_string'  => 'DataBase write error while scanning files.',);
	}else{
		$output  = array(
			'files_count' => $scaner->files_count,
			'scanned'     => $scaner->files_count,
			'dirs_count'  => $scaner->dirs_count,
			'exec_time'   => microtime(true) - $time_start,
			'offset'      => $offset,
			'success'     => isset($success),
		);
	}

	if(!$direct_call) die(json_encode($output)); else return $output;

}

function spbc_scanner_scan_modified($direct_call = false, $status = 'COMPROMISED', $amount = 10, $path = ABSPATH){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb, $wp_version;
	
	$status = !empty($_POST['status']) ? stripslashes($_POST['status']) : $status;
	if(is_string($status)) if(explode(',',$status)) $status = explode(',',$status);
	if(is_array($status))                           $status = implode('\',\'', $status);
	
	$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
	$path   = !empty($_POST['path'])   ? $_POST['path']   : $path;
	$path   = realpath($path);
	
	$source = $path == realpath(ABSPATH) ? 'CORE' : 'PLUGIN';
	
	$time_start = microtime(true);
	
	$sql = 'SELECT path, source, status, fast_hash, real_full_hash'
		.' FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_RESULTS
		." WHERE checked = 'NO' AND source = '$source'".($status ? " AND status IN ('$status')" : '')
		." LIMIT $amount";
	$files_to_check = $wpdb->get_results($sql, ARRAY_A);
	
	if(count($files_to_check)){
		
		$scan_result = array('CRITICAL' => 0, 'DANGER' => 0, 'SUSPICIOUS' => 0, 'NONE' => 0);
		
		$root_path = spbc_get_root_path();
		
		foreach($files_to_check as $file){
			
			$result = SpbcScaner::scan_file($root_path, $file, $wp_version);
			
			if($file['status'] === 'UNKNOWN' && $source == 'CORE'){
				$result['status'] = 'UNKNOWN';
			}
			
			$wpdb->update(
				SPBC_DB_PREFIX . SPBC_SCAN_RESULTS,
				array(
					'checked'    => 'YES',
					'status'     => $result['status'],
					'severity'   => $result['severity'],
					'weak_spots' => $result['weak_spots'],
					'difference' => $result['difference'],
				),
				array( 'fast_hash' => $file['fast_hash'] ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%s' )
			);
			
			$scan_result[$result['severity']]++;
			
		}
	}
	
	$output  = array(
		'scanned'   => count($files_to_check),
		'exec_time' => microtime(true) - $time_start,
	);
	
	if(!$direct_call) die(json_encode($output)); else return $output;

}

function spbc_scanner_scan_links($direct_call = false, $offset = 0, $amount = 10){
	
	if(!$direct_call) check_ajax_referer('spbc_secret_nonce', 'security');
	if(!$direct_call){
		$offset = isset($_POST['offset']) ? $_POST['offset'] : $offset;
		$amount = isset($_POST['amount']) ? $_POST['amount'] : $amount;
	}
	
	global $wpdb, $spbc;
	$time_start = microtime(true);
	
	$init_params = array(
		'offset' =>$offset,
		'amount' =>$amount,
		'check_default' => false,
		'mirrors' => !empty($spbc->settings['scan_outbound_links_mirrors']) ? $spbc->settings['scan_outbound_links_mirrors'] : '',
	);
	$scanner = new SpbcScannerLinks($init_params);
		
	if (!empty($scanner->links)){
		
		$prev_scanned_links = spbc_scanner_get_scanned_links();	
		$new_links = array_diff_key($scanner->links, $prev_scanned_links);
		
		if (count($new_links)>0){
			
			$success = $wpdb->insert(
				SPBC_DB_PREFIX . SPBC_SCAN_LINKS_LOG,
				array(
					'user_id'           => null,
					'service_id'        => null,
					'submited'          => date('Y-m-d H:i:s'),
					'total_links_found' => count($new_links),
					'links_list'        => json_encode($new_links),
				)
			);
		}								
	}
	
	$output  = array(		
		'links_found' => $scanner->links_found,
		'scanned'     => $scanner->posts_checked,
		'exec_time'   => microtime(true) - $time_start,
	);
		
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_scanner_send_results($direct_call = false, $total_scanned = 0){
	
	global $spbc, $wpdb;
	
	// Getting unknown and modified files.
	$sql = 'SELECT path, size, mtime, status, full_hash
		FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
		WHERE status IN ("COMPROMISED", "INFECTED", "UNKNOWN")';
	$sql_result = $wpdb->get_results($sql, ARRAY_A);
	$rows_count = count($sql_result);
	
	$unknown  = array();
	$modified = array();
	$is_windows = spbc_is_windows() ? true : false;
	if($rows_count){
		foreach($sql_result as $row){
			$row['path'] = $is_windows ? str_replace('\\', '/', $row['path']) : $row['path'];
			if($row['status'] == 'UNKNOWN'){
				$unknown[$row['path']] = array($row['full_hash'], $row['mtime'], $row['size']);
			}else{
				$modified[$row['path']] = array($row['full_hash'], $row['mtime'], $row['size']);
			}
		}
	}
	
	// Count files to scan
	$scanned_total = spbc_scanner_count_files(true);
	$scanned_total = $scanned_total['files_total'];
	$scanned_links = spbc_scanner_count_found_links(true);	
	
	// API call
	$result = SpbcHelper::securityMscanLogs(
		$spbc->settings['spbc_key'],
		$spbc->service_id,
		current_time('Y-m-d H:i:s'),
		$rows_count ? 'warning' : 'passed',
		$scanned_total,
		$modified,
		$unknown
	);
	
	if(empty($result['error'])){
		$spbc->data['scanner']['last_sent']        = current_time('timestamp');
		$spbc->data['scanner']['last_scan']        = current_time('timestamp');
		$spbc->data['scanner']['last_scan_amount'] = isset($_POST['total_scanned']) ? $_POST['total_scanned'] : $total_scanned;
		$spbc->data['scanner']['last_scan_links_amount'] = $scanned_links;
		SpbcHelper::deleteError('scanner_result_send');
	}else{
		SpbcHelper::addError('scanner_result_send', $result);
	}
	$spbc->save('data');
	
	if($direct_call) return $result; else die(json_encode($result));
}

function spbc_scanner_file_send($direct_call = false, $file_id = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	$time_start = microtime(true);
	
	global $spbc, $wpdb;
	
	$root_path = spbc_get_root_path();
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
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					if(filesize($root_path.$file_info['path']) > 0){					
						if(filesize($root_path.$file_info['path']) < 1048570){
					
							// Getting file && API call
							$file = file_get_contents($root_path.$file_info['path']);
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
	
	global $wpdb;
	
	$root_path = spbc_get_root_path();
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
			if(file_exists($root_path.$file_info['path'])){
				if(is_writable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$result = unlink($root_path.$file_info['path']);
					
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
	
	$root_path = spbc_get_root_path();
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
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$md5 = md5_file($root_path.$file_info['path']);
					
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
	
	$root_path = spbc_get_root_path();
	$file_id = $direct_call
		? $file_id ? $file_id : false
		: !empty($_POST['file_id']) ? $_POST['file_id'] : false;
	
	if($file_id){
		
		// Getting file info.
		$sql = 'SELECT path, status, severity, difference, weak_spots
			FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS.'
			WHERE fast_hash = "'.$file_id.'"
			LIMIT 1';
		$sql_result = $wpdb->get_results($sql, ARRAY_A);
		$file_info = $sql_result[0];
		
		if(!empty($file_info)){
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$file = file($root_path.$file_info['path']);
					
					if(count($file)){
						
						$file_text = array();
						for($i=0; isset($file[$i]); $i++){
							$file_text[$i+1] = htmlspecialchars($file[$i]);
							$file_text[$i+1] = preg_replace("/[^\S]{4}/", "&nbsp;", $file_text[$i+1]);
						}
							
						if(!empty($file_text)){
							$output = array('success' => true, 'file' => $file_text, 'file_path' => $root_path.$file_info['path'], 'difference' => $file_info['difference'], 'weak_spots' => $file_info['weak_spots']);
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
	$root_path = spbc_get_root_path();
	
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
			if(file_exists($root_path.$file_info['path'])){
				if(is_readable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$file = file($root_path.$file_info['path']);
					
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
									$output = array('success' => true, 'file' => $file_text, 'file_original' => $file_original_text, 'file_path' => $root_path.$file_info['path'], 'weak_spots' => $file_info['weak_spots'], 'difference' => $file_info['difference']);
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
	$root_path = spbc_get_root_path();
	
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
			if(file_exists($root_path.$file_info['path'])){
				if(is_writable($root_path.$file_info['path'])){
					
					// Getting file && API call
					$original_file = file_get_contents('http://cleantalk-security.s3.amazonaws.com/cms_sources/'.$platform.'/'.$cms_version.str_replace('\\', '/', $file_info['path']));
					
					if($original_file){
						
						$file_desc = fopen($root_path.$file_info['path'], 'w');
						
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
	$output['exec_time'] = $exec_time;
	
	if($direct_call)
		return $output;
	else
		die(json_encode($output));
}

function spbc_scanner_list_results($direct_call = false, $offset = 0, $amount = 20, $type = null){
	
	if(!$direct_call)
		check_ajax_referer('spbc_secret_nonce', 'security');
	
	global $wpdb,$spbc;
	
	$offset = !empty($_POST['offset']) ? $_POST['offset'] : $offset;
	$amount = !empty($_POST['amount']) ? $_POST['amount'] : $amount;
	$type   = !empty($_POST['type'])   ? $_POST['type']   : $type;
	
	$sql_template_amount  = 'SELECT COUNT(fast_hash) as cnt FROM '.SPBC_DB_PREFIX.SPBC_SCAN_RESULTS.' WHERE %s = "%s"';
	$sql_template_entries = 'SELECT fast_hash, path, size, perms, mtime, DATE_FORMAT(FROM_UNIXTIME(mtime), \'%%b %%d %%Y %%H:%%i:%%s\') AS mtime_str, last_sent, real_full_hash'
		.' FROM '.SPBC_DB_PREFIX.SPBC_SCAN_RESULTS
		.' WHERE %s = "%s" LIMIT %d, %d';
	
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
	if((!$type || $type == 'outbound links') && $spbc->settings['scan_outbound_links']){
			$links = spbc_scanner_get_scanned_links($offset,$amount);
			$links_amount = spbc_scanner_count_found_links(true);
			$output['data']['outbound links'] = array (
				'list'  => $links,
				'amount'=> $links_amount,
			);
	}	
	if(!$type){
		$total_amount = $wpdb->get_results('SELECT COUNT(fast_hash) as cnt FROM '.SPBC_DB_PREFIX . SPBC_SCAN_RESULTS, ARRAY_A.' WHERE status IN (\'UNKNOWN\', \'COMPROMISED\') AND severity IS NOT NULL');
		$output['bad_amount']   = $critical_amount[0]['cnt'] + $dangerous_amount[0]['cnt'] + $suspicious_amount[0]['cnt'] + $compromised_amount[0]['cnt'] + $unknown_amount[0]['cnt'];
		$output['total_amount'] = $total_amount[0]['cnt'];
	}
	
	$output['success'] = !empty($critical_files) || !empty($dangerous_files) || !empty($suspicious_files) || !empty($unknown_files) || !empty($compromised_files) || !empty($links) ? true : false;
	
	// Filtering and preparing for layout paths
	$root_path = spbc_get_root_path();
	foreach($output['data'] as $key => $value){
		if ($key != 'outbound links'){
			foreach($value['list'] as $key2 => $value2){
				$output['data'][$key]['list'][$key2]['size_str']  = spbc_size_to_string($value2['size']);
				$output['data'][$key]['list'][$key2]['path'] = strlen($root_path.$value2['path']) >= 60
					? '<span class="spbcShortText">...'.$value2['path'].'</span><span class="spbcFullText spbc_hide">'.$root_path.$value2['path'].'</span>'
					: $root_path.$value2['path'];
			} unset($key2, $value2);
		}
		if ($key == 'outbound links'){
			foreach($value['list'] as $key2 => $value2){
				$output['data'][$key]['list'][$key2]['url'] = $key2;
				$output['data'][$key]['list'][$key2]['url_text'] = (strlen($key2) >= 40)
					? '<span class = "spbcShortText">'.substr($key2, 0,40).'...</span><span class="spbcFullText spbc_hide">'.$key2.'</span>'
					: $key2;
				$output['data'][$key]['list'][$key2]['page'] = $value2['page_url'];
				$output['data'][$key]['list'][$key2]['page_text'] = (strlen($value2['page_url']) >= 40)
					? '<span class = "spbcShortText">'.substr($value2['page_url'], 0,40).'...</span><span class="spbcFullText spbc_hide">'.$value2['page_url'].'</span>'
					: $value2['page_url'];
				$output['data'][$key]['list'][$key2]['link_text'] = (strlen($value2['link_text']) >= 40) 
					? '<span class = "spbcShortText">'.substr($value2['link_text'], 0, 40).'...</span><span class="spbcFullText spbc_hide">'.$value2['link_text'].'</span>'
					: $value2['link_text'];
			} unset($key2, $value2);
		}
	}
	
	if($direct_call) return $output; else die(json_encode($output));
}

function spbc_size_to_string($size){
	$size = strrev((string)$size);
	$result = '';
	for($i=0; isset($size[$i]); $i++)
		$result .= ($i%3 == 0 ? ' ' : '') . $size[$i];
	
	return strrev($result);
}

function spbc_is_windows(){
	return strpos(strtolower(php_uname('s')), 'windows') !== false ? true : false;
}

function spbc_scanner_get_scanned_links($offset = null, $amount = null, $scanned_links = array()){
	
	global $wpdb;
	
	$sql = 'SELECT links_list 
		FROM ' . SPBC_DB_PREFIX . SPBC_SCAN_LINKS_LOG;
	$sql_result = $wpdb->get_results($sql, ARRAY_A);	
	
	foreach ($sql_result as $value){
		$links_array = json_decode($value['links_list'],true);
		foreach ($links_array as $url => $url_details){
			$scanned_links[$url] = $url_details;
		}
	}
	
	return empty($amount) ? $scanned_links : array_slice($scanned_links, $offset, $amount);
}

function spbc_scanner_count_found_links($total = true)
{
	global $wpdb;
	$count=0;
	if ($total)
		$sql = 'SELECT SUM(total_links_found) as cnt
				FROM '. SPBC_DB_PREFIX . SPBC_SCAN_LINKS_LOG;
	else
		$sql = 'SELECT log_id, total_links_found as cnt
				FROM '. SPBC_DB_PREFIX . SPBC_SCAN_LINKS_LOG .' 
				ORDER BY log_id DESC 
				LIMIT 1';
	$sql_result = $wpdb->get_results($sql,ARRAY_A);
	if ($sql_result)
		$count = $sql_result[0]['cnt'] == null ? 0 : $sql_result[0]['cnt'];
	return $count;
}
