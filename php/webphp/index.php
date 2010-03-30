<?php

require('webphp/web.php');

$urls = array(
	'/' => 'index',
    '/index/test/*' => 'hello',
);

class index {
    function GET($path) {
       echo web::render('index.php');
    }
}

class hello {
	function GET($path) {
	echo web::render("index.php");
    }
}

web::run($urls);

?>
