<?php
$url = "http://twitter.com/statuses/public_timeline.xml";
$ch = curl_init($url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
$result = curl_exec($ch);
curl_close($ch);
//echo($result);
$result2 = simplexml_load_string($result);
echo("<pre>");
var_dump($result2);
echo("</pre>");
