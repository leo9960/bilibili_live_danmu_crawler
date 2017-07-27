<?php
include("bilibili_live.php");
/*
* 输出里的code含义：
* 10001房间号，10002正在链接弹幕，10003进入房间成功，10004链接弹幕成功，10005正在重连
* 10010房间人数，10011直播开始，10012房主准备中
* 10020弹幕，10021礼物，10022观众老爷进入房间
*
*/
$m_roomId="5441";
$Connection = new bili($m_roomId);
$Connection->Connect();
//$Connection->reconnect();//重连
//$Connection->changeroomid("801121");//更换roomid
?>