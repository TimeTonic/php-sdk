<?php
	//namespace sdkTT;
	require __DIR__ . '/../vendor/autoload.php';
	include_once('websocket-client/vendor/autoload.php');

	
	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\RequestException;

class TTClient {
	# General queries credentials
	private $host;		# Timetonic API Server URL
	private $o_u;	    # oauth_user  
	private $u_c;	 	# user 
	private $pwd;	 	# password 
	private $appkey;	 	# app key : generated on construct with app name
	private $oauthkey;	 	# oauth key : generated on construct with app key
	private $sesskey;	 # session : generated on construct  with oauth key
	private $appname;
	private $websocketClient;
	
	# Table queries credentials
	private $catId; 			# category/table id 
	private $tabId; 		# (optional) tab id (tabId):
	
	#Interlocutor 
	private $creator_u_c;
	
	# Constructor
	function __construct($url,$o_u,$pwd){
		$this->host = $url;
		$this->o_u = $o_u;
		$this->u_c = $o_u; //similar for now 
		$login = $o_u; //similar for now
		$this->pwd = $pwd;
		$this->appname = 'chatbot';
		
		$this->Client = new Client();
		$this->websocketClient = null;
		
		# Get app key 
		$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=createAppkey' . '&appname='. $this->appname);
		$results = json_decode($response->getBody());
		$this->appkey = $results->appkey;
		
		# Get oauth key 
		$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=createOauthkey' . '&login=' . $login .  '&pwd=' . $pwd . '&appkey='. $this->appkey);
		$results = json_decode($response->getBody());
		$this->oauthkey = $results->oauthkey;
		
		# Get session key 
		$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=createSesskey' . '&o_u=' . $this->o_u .  '&oauthkey=' . $this->oauthkey);
		$results = json_decode($response->getBody());
		$this->sesskey = $results->sesskey;

	}
	
	# Generating a socket for the active user
	# Attach user to a socket
	function attachUser(){
	
		$sckt = array( 
				'type'=>4,
				'o_u' => $this->o_u,
				'u_c' => $this->u_c,
				'sesskey' => $this->sesskey
		);
		
		$sckt = json_encode($sckt);
		var_dump($sckt);
		try {
			$this->websocketClient = $this->websocketClient==null ? new WebSocket\Client("wss://timetonic.com:10100") : $this->websocketClient;
			$this->websocketClient->send($sckt);
			$retCode = true;
			$this->websocketClient->receive();
		}
		catch (WebSocket\ConnectionException $e) {
				echo 'except catch socket connection' . $e;
		}	
	}

	#Get user information (@ only lang for now)
	function getUserLang($u_c){
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getUserInfo' . '&o_u='. $this->o_u . '&u_c='. $u_c .'&sesskey=' . $this->sesskey);
			$results = json_decode($response->getBody());
			return $results->userInfo->userPrefs->lang;
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Send message http (do not use)
	function sendMsg($msg,$bkcde,$bkownr) { 	
	
		$msg = urlencode($msg); 
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=sendMsg' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&b_c='. $bkcde. '&b_o='. $bkownr . '&msg='. $msg . '&sesskey=' . $this->sesskey);
			var_dump($results = json_decode($response->getBody()));
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	
	# Send message socket
	function sendMsgSck($msg,$bkcde,$bkownr,$smid=0,$type=1) { 	
		if (!empty($msg)) {
	
			$uuid = uniqid (('websocket-client-demo-'.time().'-'));
			$sckt = array( 
				'type'=>$type,
				'sesskey' => $this->sesskey,
				'o_u' => $this->o_u,
				'u_c' => $this->u_c,
				'b_c' => $bkcde,
				'b_o' => $bkownr,
				'msg' => $msg,
				'smid'=> $smid, 
				'uuid'=> $uuid
				);
						
			$sckt = json_encode($sckt);
				
			try {
				$this->websocketClient = $this->websocketClient==null ? new WebSocket\Client("wss://timetonic.com:10100") : $this->websocketClient;
				$this->websocketClient->send($sckt);
				$retCode = true;
				$this->websocketClient->receive();
				return true;
			}
			catch (WebSocket\ConnectionException $e) {
					echo 'execpt catch socket connection' . $e;
			}
		}
	}
	
	# Update smart table value socket
	function updateTable($value,$bkcde,$bkownr,$rowId,$fieldId,$type=13,$ownr) { 	
		
		//$uuid = uniqid(('websocket-client-demo-'.time().'-'));
		$fieldValues = array(
			$fieldId => $value
		);

		$fieldValues = json_encode($fieldValues, JSON_FORCE_OBJECT);

		$data = array( 
			'version' => '1.47',
			'req'=> 'createOrUpdateTableRow',
			'o_u' => $this->o_u,
			'u_c' =>  $this->u_c, // the change won't be saved as sam but as the one who asked for 
			'sesskey' => $this->sesskey,
			'b_c' => $bkcde,
			'b_o' => $bkownr,
			'rowId' => $rowId,
			'fieldValues' => $fieldValues,
			'updatedAt' => time()
			);

		$postdata = http_build_query($data);

		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => $postdata
		    )
		);

		$context  = stream_context_create($opts);

		$result = file_get_contents('https://timetonic.com/live/api.php', false, $context);
	
	}
	
	# Get input : Receive socket message 
	function getInput(){	
		$ping = false;
		while (!$ping) {
			try{
				$this->websocketClient = $this->websocketClient==null ? new WebSocket\Client("wss://timetonic.com:10100") : $this->websocketClient;
				$newmsg = json_decode($this->websocketClient->receive());
				$ping = true;
			}
			catch(WebSocket\ConnectionException $e){
				#no message receive 
				$ping = false;
			}
			
		}
		
		return $newmsg;
	}
	
	# Get All Books 
	function getAllBooks(){
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getAllBooks' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey );
			$results = json_decode($response->getBody());
	
			#Access to books : format = array 
			$books_table =  $results->{'allBooks'}->{'books'};
			return $books_table ; //array format
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	#Get TableID (catId , tabId) : i.e the first table of the book wich is not a summary
	function getTableId($b_c, $b_o){
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getBookTables' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey . '&b_c=' . $b_c . '&b_o=' . $b_o);
			
			$results = json_decode($response->getBody());
			
			#Access to tabs table : format = array 
			$tabs = $results->bookTables->tabs;
			
			#Access to categories table : format = array 
			$categories = $results->bookTables->categories;
			$i =0;
			
			#seek for the first table of the book(where pivot_id = null)
			while($categories[$i]->pivot_id != null) {
				$i++;
			}
			$t_c = $categories[$i]->name; //get name of the table
			#array [ catId , tabId]
			$tableID = array($tabs[$i]->category_id, $tabs[$i]->id,$t_c);
			return $tableID ; //array format
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Get FieldId(id of the column)  
	function getFieldID($catId  ,$tabId,$b_c, $b_o, $field){ 	
		$this->catId = $catId; 
		$this->tabId = $tabId; 
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getTableValues' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey . '&catId='. $this->catId . '&tabId='. $this->tabId);
			$results = json_decode($response->getBody());
			
			#Access to field : format = array 
			$fields = $results->{'tableValues'}->{'fields'};  
			
			#Loop to find $field in the array
			foreach($fields as $field_tt){
				if($field_tt->name == $field){
					return $field_tt->id;
				}
			}
			return 'Error : $field doesnt exist in this table ';
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Get Table Value
	function getTableValues($catId ,$tabId) { 
		
		$this->catId = $catId; 
		$this->tabId = $tabId; 
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getTableValues' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey . '&catId='. $this->catId . '&tabId='. $this->tabId);
			$results = json_decode($response->getBody());
	
			#Access to field : format = array 
			$fields_table =  $results->{'tableValues'}->{'fields'};
			return $fields_table ; //array format
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Get Key Column 
	function getKeyColumn($catId  ,$tabId) { 	
		$this->catId = $catId; 
		$this->tabId = $tabId; 
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getTableValues' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey . '&catId='. $this->catId . '&tabId='. $this->tabId);
			$results = json_decode($response->getBody());
			
			#Access to key column : fields[0]
			return $results->{'tableValues'}->{'fields'}[0]->{'values'};  // array format
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	
	# Get Case Value and all the informations of the field 
	function getCaseValue($column, $roid, $catId ,$tabId)  { 
		
		$this->catId = $catId; 
		$this->tabId = $tabId; 
		
		try{
			$response = $this->Client->post($this->host . '/dev/' . '/api.php' . '?req=getTableValues' . '&o_u='. $this->o_u . '&u_c='. $this->u_c . '&sesskey='. $this->sesskey . '&catId='. $this->catId . '&tabId='. $this->tabId);
			$results = json_decode($response->getBody());
	
			#Access to field : format = array 
			$fields_table =  $results->{'tableValues'}->{'fields'};
			
			#***** FIND WAY TO HASH IN  O(1)*** (other function in API ?) ****
			#Seek for right column O(n)
			foreach($fields_table as $field) {
				if($field->{'name'} == $column) {
					$column_table =  $field->{'values'};
					break;
				}
			}
			if(isset($column_table)){ 
			   //Seek for right row O(m)
				foreach($column_table as $row) {
					if($row->{'id'} == $roid) {
						if(isset($row->text)){
							$result = $row->text;
						}else {
							$result =  $row->{'value'};
						}
						break;
					}
				}
				if(isset($result)){ 
					return array($result, $r_field) ;
				}
			} else return false;				//array format [value, field]
		}catch(RequestException $e){
			return json_decode($e->getResponse()->getBody());
		}
	}
	

}
?>