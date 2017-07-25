<?php  

	

class WebSocket{
	var $master;
	var $sockets = array();
	var $users = array();

	// Create me
	function __construct($address,$port){
		global $botNames;
		$this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
		socket_bind($this->master, $address, $port)                    or die("socket_bind() failed");
		socket_listen($this->master,20)                                or die("socket_listen() failed");
		$this->say("Server Started");
		$this->say("Listening on   : ".$address." port ".$port);
		$this->say("Master socket  : ".$this->master);
		$this->sockets[]=$this->master;
		
		while(true) {
			$changed = $this->sockets;
			$write = array();
			$except = array();
			socket_select($changed,$write,$except,0,40);
			foreach($changed as $socket){
				if($socket==$this->master){
					$client=socket_accept($this->master);
					if($client>=0) $this->connect($client); 
				} else{
					$bytes = @socket_recv($socket,$buffer,512,0);
					if($bytes==0) {
						$user = $this->getuserbysocket($socket);
						$this->disconnect($socket); 
					} else {
						$user = $this->getuserbysocket($socket);
						if(!$user->handshake) {
							if (!$this->dohandshake($user,$buffer)) {
								$this->disconnect($socket);
							}; 
						} else $user->processMessage($buffer); 
					}
				}
			}
			foreach($except as $socket){
				$user = $this->getuserbysocket($socket);
				$this->disconnect($socket); 
			}
		}
	}
	
	function broadcast($msg, $ignore) {
		foreach ($this->users as $user)
			if ($user->socket!=$ignore)
				$user->send($msg);
	}
		
	// New connection handler
	function connect($socket){
		$user = new User();
        $user->server = $this;
		$user->socket = $socket;
		@socket_set_option($socket,SOL_SOCKET, TCP_NODELAY, 1);
		@socket_set_option($socket,getprotobyname("tcp"), TCP_NODELAY, 1);

		array_push($this->users,$user);
		array_push($this->sockets,$socket);
	}

	// Disconnection
	function disconnect($socket,$disconnectit=true){
		$found=null;
		$n=count($this->users);
		for($i=0;$i<$n;$i++){
			if($this->users[$i]->socket==$socket){ 
				$found=$i; 
                break; 
			}
		}
		if(!is_null($found)) array_splice($this->users,$found,1); 
		$index=array_search($socket,$this->sockets);
		if ($disconnectit) socket_close($socket);		
		if($index>=0) array_splice($this->sockets,$index,1); 
	}

	// Manage handshake   // Request is event|socket/gameid/sessionid
	function dohandshake($user,$buffer){
		list($resource,$host,$origin,$key1,$key2,$key3,$socketsversion,$l8b) = $this->getheaders($buffer);
        echo count($resource);
		if (strpos($buffer,"/phone")>0) {

			$d = file_get_contents("phone.html");
			$user->writedata("HTTP/1.0 200 OK\nContent-type: text/html\nconnection: close\ncontent-length: ".strlen($d)."\n\n".$d);
			return false;
		}
		
		$user->log("Handshaking...");
		$user->websocketVersion = $socketsversion;
		if (strlen($key3)>0) $upgrade  = "HTTP/1.1 101 Switching Protocols\r\n"; else 
							 $upgrade  = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n"; 
		$upgrade.="Upgrade: WebSocket\r\n" .
				  "Connection: Upgrade\r\n" .
				   "Sec-WebSocket-Origin: " . $origin . "\r\n" .
					"Sec-WebSocket-Location: ws://" . $host . "/\r\n";
				   
		if ((strlen($key1)>0) && (strlen($key2)>0)) {
			$upgrade = "HTTP/1.1 400 Bad Request\r\nSec-WebSocket-Version: 13, 8\r\n\r\n";
			$user->writedata($upgrade); 
			$user->log("Web-Socket invalid version: ");
			return false;
		} else
		if (strlen($key3)>0) {
			$upgrade.="Sec-WebSocket-Accept: ".$this->calcKeyOld($key3)."\r\n\r\n";
			$user->websocketMode = $user->WEBSOCKET_MODE_HYBE;  // 13, New Method
			$user->log("Web-Socket Connected");
		} else {
			// Unknown protocol
			$upgrade = "HTTP/1.1 400 Bad Request\r\nSec-WebSocket-Version: 13, 8\r\n\r\n";
			$user->writedata($upgrade); 
			$user->log("Web-Socket invalid version (unknown)");
			return false;
		}
		$user->writedata($upgrade);    			
		$user->handshake=true;
		return true;
	}
	
	
	// Calc key used by Hibie 
	function calcKeyOld($key) {
		return base64_encode(SHA1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
	}

	/**
	 * WebSocket draft 76 handshake by Andrea Giammarchi
	 * see http://webreflection.blogspot.com/2010/06/websocket-handshake-76-simplified.html
	 */
	 function keyToBytes($key) {
		return preg_match_all('#[0-9]#', $key, $number) && preg_match_all('# #', $key, $space) ?
			implode('', $number[0]) / count($space[0]) :
			'';
	}

	function securityDigest($key1, $key2, $key3) {
		return md5(
			pack('N', $this->keyToBytes($key1)) .
			pack('N', $this->keyToBytes($key2)) .
			$key3, true);
	}
	
	// Get neded headers
	function getheaders($req){
		$r=$h=$o=$sk1=$sk2=$sk3=$ver=$l8b=null;
		if(preg_match("/GET (.*) HTTP/"               ,$req,$match)){ $r=$match[1]; } else
		if(preg_match("/POST (.*) HTTP/"               ,$req,$match)){ $r=$match[1]; }
		if(preg_match("/Host: (.*)\r\n/"              ,$req,$match)){ $h=$match[1]; }
		if(preg_match("/Origin: (.*)\r\n/"            ,$req,$match)){ $o=$match[1]; } else
		if(preg_match("/Sec-WebSocket-Origin: (.*)\r\n/"            ,$req,$match)){ $o=$match[1]; } 
		if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)){ $sk3=$match[1];  }
		if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/",$req,$match)){ $ver==$match[1];  }
		if(preg_match("/Sec-WebSocket-Key2: (.*)\r\n/",$req,$match)){ $sk2=$match[1];  }
		if(preg_match("/Sec-WebSocket-Key1: (.*)\r\n/",$req,$match)){ $sk1=$match[1];  }
		if(preg_match("/\r\n(.*?)\$/", $req, $match))  {
			$l8b=$match[1];
		}
		return array($r,$h,$o,$sk1,$sk2,$sk3,$ver,$l8b);
	}

	// Returns the user from a socket
	function getuserbysocket($socket){
		foreach($this->users as $user)
			if($user->socket==$socket) 
				return $user;
		return null;
	}

	function say($msg=""){ 
		echo date('Y-m-d H:i:s')." ".$msg."\n"; 
    }
}

class User{
    var $WEBSOCKET_MODE_HYBE   = 1;
    var $WEBSOCKET_MODE_HIXIE  = 2;
    
    var $server;
    var $websocketMode=0;
	var $handshake = false;
	var $websocketVersion;
	var $socket;
	var $messagedata = "";
    		
	// Process a single message sent from the client
	function processMessage($message) {
		
		switch ($this->websocketMode) {
		  case $this->WEBSOCKET_MODE_HYBE   : $message = $this->decodeHybiMessage($message); break;
		  case $this->WEBSOCKET_MODE_HIXIE  : $message = $this->decodeHixieMessage($message); break;
		  default: return;
		}            
        
		if ($message=="BEAT") {
			$this->server->say("Beat Received");
			$this->server->broadcast("Beat",$this->socket);	
		}
	}
		
        
	// Send a message to the other end.  
	function send($msg) { 
		switch ($this->websocketMode) {
		  case $this->WEBSOCKET_MODE_HYBE   : $msg = $this->encodeHybiMessage($msg,false); break;
		  case $this->WEBSOCKET_MODE_HIXIE  : $msg = $this->encodeHixieMessage($msg); break;
		}
		$this->writeData($msg);
	} 

	// [raw] Write data to the buffer and send
	function writeData($data) {
		$this->messagedata.=$data;	
		$this->flush();
	}

	// Flush any remaining data in the buffer
	function flush() {
		if (strlen($this->messagedata)) {
			$d = @socket_write($this->socket,$this->messagedata);
			if ($d>0) $this->messagedata=substr($this->messagedata,$d);  // trim off what was sent
		}
	}

    function decodeHixieMessage(&$buffer) {
        $ret = NULL;
        if (strlen($buffer) > 0){
            $type = ord($buffer[0]);
            if (($type & 0x80) == 0x80 && isset($buffer[1])) {
                $length = 0;
                $byteNum=1;
                do {
                    $lenByte=ord($buffer[$byteNum++]);
                    $length = ($length*128)+($lenByte&0x7F);
                } while (isset($buffer[$byteNum]) && ($lenByte&0x80)==0x80);
                if ($type == 0xFF && $length == 0){     
                    $buffer = substr($buffer, min(strlen($buffer),2));
                    return "";
                } else if ($length > 0){
                    $length = min(strlen($buffer),$length);
                    $buffer = substr($buffer, $length);
                }
            } else 
            if (($type&0x80) == 0){
                $payload=array();
                $byteNum=1;
                while (isset($buffer[$byteNum]) && ord($buffer[$byteNum]) != 0xFF){
                    $payload[] = $buffer[$byteNum++];
                }
                if ($type == 0){
                    $buffer = substr($buffer, $byteNum+1);
                    return implode('', $payload);
                }
            }
        }
        return $ret;
    }    
   
    function decodeHybiMessage(&$buffer){
        $ret = null;
        do {
            if (strlen($buffer) > 2){
                $bytes = substr($buffer, 0, 2);
                $buffer = substr($buffer, 2);
                $byte = ord($bytes[0]);
                $flags = ($byte&0xF0)>>4;
                $opcode = $byte&0x0F;
                $byte = ord($bytes[1]);
                $ismasked = ($byte&0x80)>>7;
                $payloadLen = $byte&0x7F;

                $validLen=false;
                switch ($payloadLen){
                    case 126:if (strlen($buffer) > 2) {
                                $payloadLenBytes = substr($buffer, 0, 2);
                                $buffer = substr($buffer, 2);
                                $payloadLen = unpack('nlen', $data);
                                $payloadLen = $payloadLen['len'];
                                $validLen=true;
                            }
                            break;
                    case 127:if (strlen($buffer) > 8){
                                $payloadLenBytes = substr($buffer, 0, 8);
                                $buffer = substr($buffer, 8);
                                $data = unpack("Nhi/Nlo", $payloadLenBytes);
                                $payloadLen = ($data['hi']<<32)|$data['lo'];
                                $validLen=true;
                            }
                            break;
                    default: /* $payloadLen is itself. */
                }
                
                if ($opcode!=1) return "";

                if ($opcode == 0x8){
                    //Disable mask on closing frames
                    $ismasked=0;
                }

                if ($ismasked && strlen($buffer) > 4){
                    $maskKey = substr($buffer, 0, 4);
                    $buffer = substr($buffer, 4);
                } else if ($ismasked){
                    break; //No mask given, but one should be given.
                }

                if (strlen($buffer) >= $payloadLen){
                    $payload = substr($buffer, 0, $payloadLen);
                    $buffer = substr($buffer, $payloadLen);

                    if ($ismasked) $payload = $this->applyMask($payload, $payloadLen, $maskKey);
                    
                    return $payload;
                }
            } else return $ret;
        } while (false);
        return $ret;
    }   

    function applyMask($payload, $payloadLen, $mask){
        $newPayload='';
        for ($i=0; $i<$payloadLen; $i++){
            $keyByte = unpack('cchar', $mask[$i%4]);
            $keyByte = $keyByte['char'];
            $newPayload .= chr(ord($payload[$i]) ^ $keyByte);
        }
        return $newPayload;
    }

    function encodeHybiMessage($msg,$close=false){
        $len = strlen($msg); 
        if ($len > 125) $len = 126;   // 1=Text, 2=Binary, 8=Close
        $packet = pack('cc', 0x80 | 1 , 0x00 | $len);
        if ($len == 126) $packet .= pack('n', strlen($msg));
        $packet .= $msg;
        return $packet;
    }

    function encodeHixieMessage($msg){
        $packet = chr(0).$msg.chr(0xFF);
        return $msg;
    }    

	function log($msg) {
		$this->server->say("User ".$this->socket.": ".$msg);
	}		
}

$d = new WebSocket("192.168.0.2",500);

?>