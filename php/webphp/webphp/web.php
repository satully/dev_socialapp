<?php
/**
* @package Web.php
* @version $Id: Web.php,v 1.1.1.1 2006/10/14 13:49 gareth Exp $
*/
/**
* Web.php
* Mini web framework based loosely on Web.py
* @access public
* @package Web.php
*/

class web {
	
	/**
    * Initialise Web.php, mainly dealing with routing requests
    * @param array the routing table for this application
    * @param array parameters for initialising a database connection i required
    * @return string
    * @access public
    */
	function run($urls,$db_parameters='') {
		error_reporting(0);
		require('pathvars.class.php');
		if (is_array($db_parameters)) { 
		    require_once('mysql.class.php');
			$db = &new mysql('localhost',$db_parameters['user'],$db_parameters['pw'],$db_parameters['db']);}
		if (count($_POST) !== 0) { $method = 'POST'; } else { $method = 'GET'; }
		$path = new PathVars($_SERVER['SCRIPT_NAME']);
		$fullpath = $path->fetchAll();
		if (count($fullpath) > 1) {
		    array_pop($fullpath);
		    $fullpath[] = '*';
        }
        foreach ($fullpath as $segment) {
            $printpath .= '/'.$segment;              
        }
		if (array_key_exists($printpath,$urls)) {
			if (class_exists($urls[$printpath])) {
				$command = $urls[$printpath].'::'.$method.'($path,$db);';
			} else { web::render('error.html'); }
		} else { $command = $urls['/'].'::'.$method.'($path,$db);'; }	
        if (FALSE === eval($command)) { echo web::render('error.html'); return false; } else { return true; }
    }

	/**
    * Prints the page based on a data array and a template file
    * @param string the name of the template to use
    * @param array an array of data for the template engine
    * @return string
    * @access public
    */
	function render($file,$data='') {
		require_once('template.class.php');
		$template = new Template('templates/' . $file);
		if (is_array($data)) {
			$template->replaceTags($data);
		}
		return $template->output();
	}
	
	/**
    * Redirect the request to the URL
    * @param string URL to rediret to
    * @return string
    * @access public
    */
	function redirect($location) {
		header("Location: $location");
	}
	
	/**
    * Load a page under a masked URL
    * @param string URL to mask
    * @return string
    * @access public
    */
	function mask($location) {
		require_once('HTTP/Request.php');
		$req =& new HTTP_Request($location);
		if (!PEAR::isError($req->sendRequest())) {
			echo $req->getResponseBody();
		} else {
			echo web::render('error.html');
		}
	}
}

?>
