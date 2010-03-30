<?php
	$mongo = new Mongo();
	$db = $mongo->selectDB("test");
	$col = $db->createCollection("test");
	$col->insert(array("test"=>3));
	$col = $db->createCollection("yamada");
	$col->insert(array("yamada" => array("first"=>"naoyuki","last"=>"yamada")));
	$col = $db->selectCollection("test");
	$cursor = $col->findOne();
	echo("<pre>");
	var_dump($cursor);
	$col = $db->selectCollection("yamada");	
	$cursor = $col->findOne();
	var_dump($cursor);
	echo("</pre>");
