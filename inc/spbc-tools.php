<?php

//
// Do updates in SQL database after plugin update.
//
function spbc_run_update_actions ($version = null) {
    global $wpdb, $spbc_auth_logs_table_label;
	
   $spbc_auth_logs_table = $wpdb->prefix . $spbc_auth_logs_table_label;
    
	$current_version = explode('.', $current_version);
	$new_version = explode('.', $new_version);
	
	if(intval($current_version[0]) == 1){
		if(isset($current_version[1]) && intval($current_version[1]) < 4){
			$sql = "ALTER TABLE `$spbc_auth_logs_table` 
				CHANGE `event`
				`event` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;";
			$wpdb->query($sql);
			if(intval($current_version[2]) == 1){
				
			}
		}
		if(isset($current_version[1]) && intval($current_version[1]) < 5){
			if(intval($current_version[2]) == 0){
				$sql = "ALTER TABLE `$spbc_auth_logs_table`
					ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
					ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
				$wpdb->query($sql);
			}
		}
	}
	
	/*
    $fix_auth_logs_table = false;
    if ($current_version){ 
        $pv_levels = explode('.', $current_version);
        if (isset($pv_levels[0])) {
            switch ($pv_levels[0]) {
                case 1:
                    // Version above 1.4.
                    if (isset($pv_levels[1]) && $pv_levels[1] < 4) {
                        $fix_auth_logs_table = true;
                    }
					$sql = "ALTER TABLE `$spbc_auth_logs_table`
								ADD COLUMN `page` VARCHAR(500) NULL AFTER `event`,
								ADD COLUMN `page_time` VARCHAR(10) NULL AFTER `page`;";
					$wpdb->query($sql);
					break;
            }
        }
    } else {
        // Version above 1.4.
        $fix_auth_logs_table = true;
    }
    if ($fix_auth_logs_table) {
        $sql = sprintf('ALTER TABLE `%s` CHANGE `event` `event` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;',
            $spbc_auth_logs_table
        ); 

        $wpdb->query($sql);
    }
	//*/

    return null;
}

//
// Returns country part for emails
//
function spbc_report_country_part($ips_c = null, $ip) {
    $country_part_tpl = '<img src="https://cleantalk.org/images/flags/%s.png" alt="%s" />&nbsp;%s';

    $country_part = '&nbsp;-';

    if (isset($ips_c[$ip]['country_code'])) {
        $country_code = strtolower($ips_c[$ip]['country_code']);
        $country_name = '-'; 
        if (isset($ips_c[$ip]['country_name'])) {
            $country_name = $ips_c[$ip]['country_name'];
        }
        $country_part = sprintf($country_part_tpl,
            $country_code,
            $country_code,
            $country_name
        );
    }
    

    return $country_part;
}

//
// Sends a HTTP request.
//
function sendRawRequest($url,$data,$isJSON=false,$timeout=3)
{
	$result=null;
	if(!$isJSON)
	{
		$data=http_build_query($data);
		$data=str_replace("&amp;", "&", $data);
	}
	else
	{
		$data= json_encode($data);
	}
	$curl_exec=false;
	if (function_exists('curl_init') && function_exists('json_decode'))
	{
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		// receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// resolve 'Expect: 100-continue' issue
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		$result = @curl_exec($ch);
		if($result!==false)
		{
			$curl_exec=true;
		}
		@curl_close($ch);
	}
	if(!$curl_exec)
	{
		$opts = array(
		    'http'=>array(
		        'method' => "POST",
		        'timeout'=> $timeout,
		        'content' => $data
            )
		);
		$context = stream_context_create($opts);
		$result = @file_get_contents($url, 0, $context);
	}
	return $result;
}

?>
