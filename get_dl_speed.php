<?php
$url = "http://www.socialapplication.jp/";
$ch = curl_init($url);

curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,2);
curl_setopt($ch,CURLOPT_TIMEOUT,60);

set_time_limit(65);

$execute = curl_exec($ch);
$info = curl_getinfo($ch);

$time = $info['total_time']
	- $info['namelookup_time']
	- $info['connect_time']
	- $info['pretransfer_time']
	- $info['starttransfer_time']
	- $info['redirect_time'];

header("Content-Type:text/plain");
printf("Downloaded %d bytes in %0.4f seconds.\n",$info["size_download"],$time);
printf("Which is %0.4f mbps\n",$info["size_download"]*8 / $time /1024 /1024);
printf("CURL said %0.4d mbps\n",$info["speed_download"] * 8 /1024/1024);

echo "\n\ncurl_getinfo() said:\n",str_repeat("-",31+strlen($url)),"\n";
foreach($info as $label => $value){
	printf("%-30s %s\n",$label,$value);
}

