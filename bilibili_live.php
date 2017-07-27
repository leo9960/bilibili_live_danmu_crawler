<?php
header("Content-Type: text/html;charset=utf-8"); 
ob_end_clean();
ob_implicit_flush(1);
class bili{
	var $m_socket=null;
	var $m_host="";
	var $m_port=0;
	var $m_roomid="";
	var $m_protocolversion = 1;
	var $TURN_WELCOME = 1;
	var $TURN_GIFT = 1;
	var $recv_nop = 0;
	function bili($roomid,$host="livecmt-2.bilibili.com", $port=788){
		$this->m_host = $host;
		$this->m_port = $port;
		$this->m_roomid = $roomid;
	}
	function Connect(){
		$this->m_socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$con=socket_connect($this->m_socket, gethostbyname($this->m_host), $this->m_port);
		if($con){
			print ($this->output(10001,'房间号：'.$this->m_roomid).'<br/>');
			print ($this->output(10002,'链接弹幕中。。。。。').'<br/>');
			if ($this->SendJoinChannel($this->m_roomid) == True){
				$connected = True;
				print ($this->output(10003,'进入房间成功。。。。。').'<br/>');
				print ($this->output(10004,'链接弹幕成功。。。。。').'<br/>');
				$this->ReceiveMessageLoop($connected);
			}
		}
		else{
			echo $this->output(10005,"连接失败。。。")."<br/>";
			$this->reconnect();
		}
	}
	function reader($length){
		return socket_read($this->m_socket,$length);
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
			$tmp=$this->reader(2);
			$tmp=$this->reader(2);
			$tmp=$this->reader(4);
			$num=unpack('N', $tmp)[1];
			$tmp=$this->reader(4);
			$num2=$expr-16;
			if ($num2 != 0){
				$num -= 1;
				if ($num==0 || $num==1 || $num==2){
					$tmp=$this->reader(4);
					$num3= unpack('N', $tmp)[1];
					print ($this->output(10010,'房间人数为 ' . $num3).'<br/>');
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
					continue;
				}
				else{
					if ($num != 16){
						$tmp=$this->reader($num2);
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
			$dic = json_decode($messages,true,512, JSON_BIGINT_AS_STRING);
			unset($messages);
			//print("m_useage:".$this->convert(memory_get_usage())."\t");
			$cmd = $dic['cmd'];
			if ($cmd == 'LIVE'){
				print ($this->output(10011,'直播开始。。。').'<br/>');
				unset($dic);
				unset($cmd);
				ob_end_flush();
				ob_flush();
				flush();
				return;
			}
			if ($cmd == 'PREPARING'){
				print ($this->output(10012,'房主准备中。。。').'<br/>');
				unset($dic);
				unset($cmd);
				ob_end_flush();
				ob_flush();
				flush();
				return;
			}
			if ($cmd == 'DANMU_MSG'){
				$commentText = $dic['info'][1];
				$commentUser = $dic['info'][2][1];
				$isAdmin = $dic['info'][2][2] == '1';
				$isVIP = $dic['info'][2][3] == '1';
				if ($isAdmin){
					$commentUser = '管理员 ' . $commentUser;
				}
				if ($isVIP){
					$commentUser = 'VIP ' . $commentUser;
				}
				print ($this->output(10020,$commentUser . ' 说: ' . $commentText).'<br/>');
				unset($dic);
				unset($cmd);
				ob_end_flush();
				ob_flush();
				flush();
				return;
			}
			if ($cmd == 'SEND_GIFT' && $this->TURN_GIFT == 1){
				$GiftName = $dic['data']['giftName'];
				$GiftUser = $dic['data']['uname'];
				$Giftrcost = $dic['data']['rcost'];
				$GiftNum = $dic['data']['num'];
				print($this->output(10021,$GiftUser.' 送出了 '.$GiftNum.' 个 '.$GiftName).'<br/>');
				unset($dic);
				unset($cmd);
				ob_end_flush();
				ob_flush();
				flush();
				return;
			}
			if ($cmd == 'WELCOME' && $this->TURN_WELCOME == 1){
				$commentUser = $dic['data']['uname'];
				print ($this->output(10022,'欢迎 '.$commentUser.' 进入房间。。。。').'<br/>');
				unset($dic);
				unset($cmd);
				ob_end_flush();
				ob_flush();
				flush();
				return;
			}
			return;
	}

	function kill(){
		socket_shutdown($this->m_socket);
		socket_close($this->m_socket);
	}
	
	function output($code=10000,$content=""){
		list($t1, $t2) = explode(' ', microtime());
		$timenow=(float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
		$arr=["time"=>$timenow,"code"=>$code,"content"=>$content];
		return json_encode($arr,JSON_UNESCAPED_UNICODE);
	}
	function reconnect(){
		usleep(300000);
		$this->kill();
		$this->recv_nop=0;
		echo $this->output(10005,"正在重连。。。")."<br/>";
		$this->Connect();
	}
	function changeroomid($roomid){
		$this->roomid=$roomid;
		$this->reconnect();
	}
}

?>