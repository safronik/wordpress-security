<?php

class SpbcScannerLinks
{
	// Counters
	public $posts_total   = 0;
	public $posts_checked = 0;	
	public $links_found   = 0;
	
	// Params
	public $check_default = false;
	
	// Work's stuff
	public $content = array();
	public $hosts   = array();
	public $links   = array();
	
	public $internal_hostnames = array(); // ????
	
	// Default pages to check
	private $default_pages = array(
		'/index.php',
		'/wp-signup.php',
		'/wp-login.php',
	);
	
	function __construct($params = array())
	{	
		// Setting params
		$offset = isset($params['offset']) ? $params['offset'] : 0;
		$amount = isset($params['amount']) ? $params['amount'] : 10;
		
		$this->hosts = !empty($params['mirrors']) ? $this->process_filter($params['mirrors']) : array();
		
		$this->check_default = isset($params['check_default']) ? $params['check_default'] : false;
		
		// Only count all posts + default pages
		if(!empty($params['count'])){
			$this->count_all_posts();
			return;
		}
		
		// Do stuff
		$this->get_posts($offset,$amount);
		$this->get_all_hostnames();
		$this->get_links();
		
		// Count everything
		$this->posts_checked = count($this->content);
		$this->links_found   = count($this->links);
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
	
	public function count_all_posts()
	{
		global $wpdb;

		$sql = "SELECT COUNT(id) AS cnt
			FROM " .$wpdb->posts." 
			WHERE 
				post_status = 'publish'
				AND post_type IN ('post', 'page')";
		$result = $wpdb->get_results($sql, ARRAY_A);
		
		$this->posts_total = $result[0]['cnt'] + count($this->default_pages);	
	}
	
	public function get_posts($offset, $amount)
	{
		
		//*/ Getting POSTS range with all approved comments
		global $wpdb;
		
		$post_ids = array();
		
		$sql = "SELECT id,post_content
			FROM " .$wpdb->posts." 
			WHERE 
				post_status = 'publish'
				AND post_type IN ('post', 'page') LIMIT $offset, $amount";
		$posts = $wpdb->get_results($sql, ARRAY_A);
		
		if(!empty($posts)){
			foreach ($posts as $post){
				// if (!empty($post['post_content'])){ 
					$this->content[] = array (
						'post_type'    => 'post_page',
						'post_id'      => $post['id'],
						'post_content' => $post['post_content']
					);
					$post_ids[] = $post['id'];
				// }
			} unset($post);
			
			$sql = "SELECT comment_post_id, comment_content
				FROM " .$wpdb->comments." 
				WHERE 
					comment_approved = 1
					AND comment_post_id IN (".implode(',', $post_ids).")";
			$result = $wpdb->get_results($sql, ARRAY_A);
			
			foreach ($result as $comment){
				// if (!empty($comment['comment_content'])){
					for ($i = 0; isset($this->content[$i]); $i++){
						if ($this->content[$i]['post_id'] == $comment['comment_post_id']){
							$this->content[$i]['post_content'] = $this->content[$i]['post_content'].' '.$comment['comment_content'];
						}
					}
				// }
			} unset($comment);
		}
		//*/		
		
		//*/ Getting default pages
		if($this->check_default){			
			if (!count($this->content)){
				foreach ($this->default_pages as $page){
					$HTTPHeaders = self::getHTTPStatus(get_site_url().$page);
					if(strpos($HTTPHeaders, '200') !== false) {
						$this->content[] = array(
							'post_type'    => 'default',
							'post_id'      => get_site_url().$page,
							'post_content' => file_get_contents(get_site_url().$page),
						);
					}
				} unset($page);
			}
		}
		//*/
	}
	
	public function get_links()
	{
		for ($i = 0; isset($this->content[$i]); $i++){
			$current = $this->content[$i];
			if ($current['post_type'] === 'post_page'){
				// Links in tags
				preg_match_all (
					"/<a\shref=\"(\S+:\/\/\S+)\".*?>(.*?)<\/a>/",
					$current['post_content'],
					$matches_tags
				);
				// Cutting founded
				$current['post_content'] = preg_replace(
					"/<a\shref=\"(\S+:\/\/\S+)\".*?>(.*?)<\/a>/",
					'',
					$current['post_content']
				);
				// Naked links
				preg_match_all (
					"/([a-zA-Z]{1,5}:\/\/[a-zA-Z0-9_\.\-\~]+\.[a-zA-Z0-9_\.\-\~]{2,4}\/?[a-zA-Z0-9_.\-~!*'();:@&=+$,\/?#[%]*)/",
					$current['post_content'],
					$matches_naked
				);
				$matches_naked[2] = $matches_naked[1];
				// Merging found
				$matches = array(
					array_merge($matches_tags[1], $matches_naked[1]),
					array_merge($matches_tags[2], $matches_naked[2]),
				);
				foreach ($matches[0] as $key => $match){
					if (!in_array(parse_url($match, PHP_URL_HOST), $this->hosts)){
						$this->links[$match]['page_url'] = $this->get_page_url_by_id($current['post_id']);
						$this->links[$match]['link_text'] = trim($matches[1][$key]);
					}
				}					
			}
			
			if ($current['post_type'] === 'default'){
				$dom = new DOMDocument();
				@$dom->loadHTML($current['post_content']);
				$xpath = new DOMXPath($dom);
				$hrefs = $xpath->evaluate("/html/body//a");
				for($j = 0; $j < $hrefs->length; $j++){
					$href = $hrefs->item($j);
					$url = $href->getAttribute('href');
					$url = filter_var($url, FILTER_SANITIZE_URL);
					// Validate url
					if(!filter_var($url, FILTER_VALIDATE_URL) === false){	
						if (!in_array(parse_url($url, PHP_URL_HOST), $this->hosts)){
							$this->links[$url]['page_url'] = $current['post_id'];
							$this->links[$url]['link_text'] = trim($href->nodeValue);					    		
						}			    			    				    		
					}
				}					
			}
		}
	}
	
	public function get_all_hostnames()
	{
		global $wpdb;
		
		$result = $wpdb->get_results("SELECT guid
			FROM " .$wpdb->posts." 
			WHERE 
				post_status = 'publish' 
				AND (post_type='post' OR post_type = 'page')", ARRAY_A);
				
		foreach ($result as $host){
			$filtred_host = parse_url($host['guid'], PHP_URL_HOST);
			if (!in_array($filtred_host, $this->hosts)){
				$this->hosts[] = $filtred_host;
			}
		}
	}
	
	public function get_page_url_by_id($id)
	{
		global $wpdb;
		$result = $wpdb->get_results("SELECT guid
			FROM ".$wpdb->posts." 
			WHERE ID = $id
			LIMIT 1", ARRAY_A);
		return $result[0]['guid'];		
	}
	
	//	Getting HTTP status
	public static function getHTTPStatus($url){ 
		$c = curl_init($url); 
		curl_setopt_array($c, array( 
			CURLOPT_NOBODY => true, 
			CURLOPT_FOLLOWLOCATION => true 
		)); 
		curl_exec($c); 
		$code = curl_getinfo($c, CURLINFO_HTTP_CODE); 
		curl_close($c); 
		return $code; 
	} 
}
