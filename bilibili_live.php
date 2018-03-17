<?php
header("Content-Type: text/html;charset=utf-8"); 
ini_set('memory_limit','256M');
ob_end_clean();
ob_implicit_flush(1);
class bili{
	var $m_socket=null;
	var $m_host="";
	var $m_port=0;
	var $m_roomid="";
	var $m_protocolversion = 1;
	var $TURN_WELCOME = 1;	//是否显示观众老爷进房间
	var $TURN_GIFT = 1;	//是否显示送礼物
	var $recv_nop = 0;
	var $gift_arr=array();
	function bili($roomid=1,$host="livecmt-2.bilibili.com", $port=788){
		$this->m_host = $host;
		$this->m_port = $port;
		$id_list=$this->getrealid($roomid);
		$this->m_roomid = $id_list["raw_id"];
		print ($this->output(50001,'输入房间号：'.$roomid)."\n");
		print ($this->output(50002,'原始房间号：'.$id_list["raw_id"])."\n");
		print ($this->output(50003,'短房间号：'.$id_list["short_id"])."\n");
		print ($this->output(50004,'主播uid：'.$id_list["uid"])."\n");
		$room_info=$this->getroominfo();
		print ($this->output(50005,'主播名称：'.$room_info["uname"])."\n");
		print ($this->output(60001,'房间名称：'.$room_info["title"])."\n");
		print ($this->output(60002,'房间标签：'.$room_info["tags"])."\n");
		print ($this->output(60003,'分区名称：'.$room_info["parent_area_name"])."\n");
		print ($this->output(60004,'分类名称：'.$room_info["area_name"])."\n");
		$this->Connect();
	}
	function getrealid($id){
		$url="https://api.live.bilibili.com/room/v1/Room/room_init?id=".$id;
		$output = $this->curl_get($url);
		$this->output(80001,$output);
		$arr=json_decode($output,true);
		$result=array();
		$result["raw_id"]=$arr["data"]["room_id"];
		$result["short_id"]=$arr["data"]["short_id"];
		$result["uid"]=$arr["data"]["uid"];
		return $result;
	}
	function curl_get($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	function curl_post($url,$data,$header=""){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	function getroominfo(){
		$url="https://api.live.bilibili.com/room/v1/Room/get_info?from=room&room_id=".$this->m_roomid;
		$output = $this->curl_get($url);
		$this->output(80002,$output);
		$arr=json_decode($output,true);
		$result=array();
		$result["live_status"]=$arr["data"]["live_status"];	//直播状态
		$result["title"]=$arr["data"]["title"];	//房间名称
		$result["tags"]=$arr["data"]["tags"];	//房间标签
		$result["area_name"]=$arr["data"]["area_name"];	//分类名称
		$result["parent_area_name"]=$arr["data"]["parent_area_name"];	//分区名称
		$result["area_id"]=$arr["data"]["area_id"];	//分类id
		$result["parent_area_id"]=$arr["data"]["parent_area_id"];	//分区id
		$url="https://api.live.bilibili.com/live_user/v1/UserInfo/get_anchor_in_room?roomid=".$this->m_roomid;
		$output = $this->curl_get($url);
		$this->output(80003,$output);
		$arr=json_decode($output,true);
		$result["uname"]=$arr["data"]["info"]["uname"];	//主播名称
		$result["cost"]=$arr["data"]["level"]["cost"];	//
		$result["rcost"]=$arr["data"]["level"]["rcost"];	//瓜子数量
		return $result;
	}
	function getuserinfo($uid){
		$url="https://space.bilibili.com/ajax/member/GetInfo";
		$header=array('Referer:https://space.bilibili.com/','Content-Type:application/x-www-form-urlencoded');
		$output=$this->curl_post($url,"mid=".$uid,$header);
		$this->output(80004,$output,$uid);
	}
	function Connect(){
		$this->m_socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$con=socket_connect($this->m_socket, gethostbyname($this->m_host), $this->m_port);
		if($con){
			print ($this->output(10001,'房间号：'.$this->m_roomid)."\n");
			print ($this->output(10002,'链接弹幕中。。。。。')."\n");
			if ($this->SendJoinChannel($this->m_roomid) == True){
				$connected = True;
				print ($this->output(10003,'进入房间成功。。。。。')."\n");
				print ($this->output(10004,'链接弹幕成功。。。。。')."\n");
				$this->ReceiveMessageLoop($connected);
			}
		}
		else{
			echo $this->output(10005,"连接失败。。。")."\n";
			$this->reconnect();
		}
	}
	function reader($length){
		$errorcode = socket_last_error($this->m_socket);
		if(!$errorcode){
			if($length<65535&&$length>0){
				if($buffer=socket_read($this->m_socket,$length,PHP_BINARY_READ)){
					if($length!=4&&$length!=strlen($buffer)){print ($this->output(90002,'msg长度：'.$length." 实际msg长度：".strlen($buffer))."\n");}
					return $buffer;
				}
			}
			else{
				print ($this->output(99001,"socket read length error：".$length)."\n");
				$this->reconnect();
			}
		   
		}else{
			print($this->output("999".$errorcode,"socket_error:".socket_strerror($errorcode)."\n"));
			$this->reconnect();
		}
	}
	function writer($buffer){
		return socket_write($this->m_socket,$buffer);
	}
	function SendJoinChannel($channelId){
		$_uid = "113621471254".(rand(100,200));
		$body = '{"roomid":'.$channelId.',"uid":'.$_uid.'}';
		$this->SendSocketData(0, 16, $this->m_protocolversion, 7, 1, $body);
		unset($_uid);
		unset($body);
		return True;
	}
	function SendSocketData($packetlength, $magic, $ver, $action, $param, $body){
		$bytearr=utf8_encode($body);
		if ($packetlength == 0){
			$packetlength = strlen($bytearr) + 16;
		}
		$sendbytes = pack('NnnNN', $packetlength, $magic, $ver, $action, $param);
		if (strlen($bytearr) != 0){
			$sendbytes = $sendbytes . $bytearr;
		}
		$this->writer($sendbytes);
		unset($sendbytes);
	}
	function ReceiveMessageLoop($con){
		while($con){
			usleep(50000);
			unset($tmp);
			unset($expr);
			unset($num);
			unset($num2);
			unset($num3);
			$tmp=$this->reader(4);
			$len=strlen($tmp);
			if($len==0){
				$this->reconnect();
				continue;
			}
			$expr=unpack('N', $tmp)[1];
			$tmp=$this->reader(4);
			$tmp=$this->reader(4);
			$num=unpack('N', $tmp)[1];
			$tmp=$this->reader(4);
			$num2=$expr-16;
			print($this->output(90001,"m_useage:".$this->convert(memory_get_usage()))."\n");
			if ($num2 > 0){
				$num -= 1;
				if ($num==0 || $num==1 || $num==2){
					$tmp=$this->reader(4);
					$num3= unpack('N', $tmp)[1];
					print ($this->output(10010,'房间人数为 ' . $num3)."\n");
					ob_end_flush();
					ob_flush();
					flush();
					$_UserCount = $num3;
					continue;
				}
				else if ($num==3 || $num==4){
					$tmp=$this->reader($num2);
					$messages = $tmp;
					$this->parseDanMu($messages);
					continue;
				}
				else if ($num==5 || $num==6 || $num==7){
					$tmp=$this->reader($num2);
					$this->output(30001,$tmp);
					continue;
				}
				else{
					if ($num != 16){
						$tmp=$this->reader($num2);
						$this->output(30002,$tmp);
					}
					else{
						continue;
					}
				}
			}
		}
				
	}
	function convert($size)
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}
	function parseDanMu($messages){
		$uid="";
			list($t1, $t2) = explode(' ', microtime());
			$timenow=(float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
			//print ($this->output(80001,$messages)."\n");
			$dic = json_decode($messages,true,512, JSON_BIGINT_AS_STRING);
			//print("m_useage:".$this->convert(memory_get_usage())."\t");
			$cmd = $dic['cmd'];
			if ($cmd == 'LIVE'){
				print ($this->output(10011,'直播开始。。。')."\n");
			}
			elseif ($cmd == 'PREPARING'){
				print ($this->output(10012,'房主准备中。。。')."\n");
			}
			elseif ($cmd == 'DANMU_MSG'){
				$commentText = $dic['info'][1];
				$uid = $dic['info'][2][0];
				$commentUser = $dic['info'][2][1];
				$isAdmin = $dic['info'][2][2] == '1';
				$isVIP = $dic['info'][2][3] == '1';
				if ($isAdmin){
					$commentUser = '管理员 ' . $commentUser;
					print ($this->output(10021,$commentUser . ' 说: ' . $commentText,$uid)."\n");
				}
				if ($isVIP){
					$commentUser = 'VIP ' . $commentUser;
					print ($this->output(10022,$commentUser . ' 说: ' . $commentText,$uid)."\n");
					$this->getuserinfo($uid);
				}
				if($isAdmin==false&&$isVIP==false){
					print ($this->output(10020,$commentUser . ' 说: ' . $commentText,$uid)."\n");
				}
			}
			elseif ($cmd == 'SEND_GIFT' && $this->TURN_GIFT == 1){
				$uid = $dic['data']['uid'];
				$GiftName = $dic['data']['giftName'];
				$GiftUser = $dic['data']['uname'];
				$Giftrcost = $dic['data']['rcost'];
				$GiftNum = $dic['data']['num'];
				print($this->output(10030,$GiftUser.' 送出了 '.$GiftNum.' 个 '.$GiftName,$uid)."\n");
				$gift_check=1;
				foreach($this->gift_arr as $tmp_gift){
					if($tmp_gift==$uid){
						$gift_check=0;
						break;
					}
				}
				if($gift_check==1){
					$this->gift_arr[]=$uid;
					$this->getuserinfo($uid);
				}
			}
			elseif ($cmd == 'WELCOME' && $this->TURN_WELCOME == 1){
				$commentUser = $dic['data']['uname'];
				$uid = $dic['data']['uid'];
				print ($this->output(10040,'欢迎 '.$commentUser.' 进入房间。。。。',$uid)."\n");
				$this->getuserinfo($uid);
			}
			file_put_contents("./log/".$timenow."_".$cmd."_".$this->m_roomid.".log",$messages);
			unset($dic);
			unset($cmd);
			@ob_end_flush();
			@ob_flush();
			@flush();
			return;
	}

	function kill(){
		socket_shutdown($this->m_socket);
		socket_close($this->m_socket);
	}
	
	function output($code=10000,$content="",$uid=""){
		list($t1, $t2) = explode(' ', microtime());
		$timenow=(float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
		$arr=["time"=>$timenow,"code"=>$code,"content"=>$content];
		if($uid!=""){
			$arr["uid"]=$uid;
		}
		$str=json_encode($arr,JSON_UNESCAPED_UNICODE);
		file_put_contents("./output_log/".$timenow."_".$this->m_roomid."_".$code.".log",$str);
		return $str;
	}
	function reconnect(){
		usleep(300000);
		$this->kill();
		$this->recv_nop=0;
		echo $this->output(10005,"正在重连。。。")."\n";
		$this->Connect();
	}
	function changeroomid($roomid){
		$this->roomid=$roomid;
		$this->reconnect();
	}
}

?>