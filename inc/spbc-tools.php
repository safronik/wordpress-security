<?php

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