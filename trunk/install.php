<?php
#///////////////////////////////////////////////////////////////////////////////
#//              Z-BlogPHP 在线安装程序
#///////////////////////////////////////////////////////////////////////////////

error_reporting(0);
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="zh-cn" lang="zh-cn">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Language" content="zh-cn" />
	<title>Z-BlogPHP 在线安装程序</title>
<style type="text/css">
<!--
*{
	font-size:14px;
}
body{
	margin:0;
	padding:0;
	color: #000000;
	background:#fff;
	font-family:"微软雅黑","宋体";
}
h1,h2,h3,h4,h5,h6{
	font-size:18px;
	padding:0;
	margin:0;
}
h1{
font-size:28px;
}
button{
	padding:15px 80px;
}
div{
	position:absolute;
	left: 50%;
	top: 50%;
	margin: -150px 0px 0px -150px;
	padding:0;
	overflow:hidden;
	width:300px;
	background-color:white;
	text-align:center;
}
-->
</style>
</head>
<body>
<div>
<h1>Z-BlogPHP 在线安装</h1>
<p><img src="http://update.rainbowsoft.org/zblog2/loading.gif" alt=""></p>

<?php


if(strpos($_SERVER['QUERY_STRING'],'begin') !== false){

install();
install2();
install3();

}else{
?>
<p><button onclick="location='?begin'" value="" />开始安装</button></p>
<?php
}
?>

<?php



$s=null;

function install(){

	echo "<p>正在努力地下载数据包...</p>";
	ob_flush();
	sleep(1);
	$GLOBALS['s']=file_get_contents('compress.zlib://' . 'http://update.rainbowsoft.org/zblogphp/?install');
	//file_put_contents('release.xml',$a);

}

function install2(){

	echo "<p>正在解压和安装文件...</p>";
	ob_flush();
	sleep(1);
	if ($GLOBALS['s']) {
		$xml = simplexml_load_string($GLOBALS['s']);
		$old = umask(0);
		foreach ($xml->file as $f) {
			$filename=str_replace('\\','/',$f->attributes());
			$dirname= dirname($filename);
			mkdir($dirname,'0777',true);
			file_put_contents(iconv("UTF-8","GBK",$filename),base64_decode($f));
		}
		umask($old);
	} else {
		exit('release.xml不存在!');
	}

}

function install3(){

	unlink('release.xml');
	unlink('install.php');
	echo '<script type="text/javascript">location="./zb_install/"</script>';
	
}

?>
</div>
</body>
</html>