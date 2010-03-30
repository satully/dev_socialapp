<?php
error_reporting(E_ALL|E_STRICT);
@date_default_timezone_set((@date_default_timezone_get() == "") ? "Asia/Tokyo" : @date_default_timezone_get());
if("neutral" == mb_language()) @mb_language("Japanese");
/**
 * set_error_handlerされる関数
 * @param integer $errno
 * @param string $errstr
 * @param string $errfile
 * @param integer $errline
 */
function error_handler($errno,$errstr,$errfile,$errline){
	if(strpos($errstr," should be compatible with that of") !== false || strpos($errstr,"Strict Standards") !== false) return true;
	if(strpos($errstr,"Use of undefined constant") !== false && preg_match("/\'(.+?)\'/",$errstr,$m) && class_exists($m[1])) return define($m[1],$m[1]);
	throw new ErrorException($errstr,0,$errno,$errfile,$errline);
}
define("_JUMP_PATH_",dirname(__FILE__));
require(constant("_JUMP_PATH_")."/spl/Object.php");
require(constant("_JUMP_PATH_")."/__funcs__.php");
register_shutdown_function("restore_error_handler");
set_error_handler("error_handler",E_ALL|E_STRICT);
/**
 * spl_autoload_registerされる関数
 * @param string $class_name
 */
function autoload_handler($class_name){
	switch($class_name){
		case "Log":
			require(constant("_JUMP_PATH_")."/spl/Log.php");
			register_shutdown_function(array("Log","__shutdown__"));
			Log::__import__();
			break;
		case "Exceptions": require(constant("_JUMP_PATH_")."/spl/Exceptions.php"); break;
		case "App":
			require(constant("_JUMP_PATH_")."/spl/App.php");
			register_shutdown_function(array("App","shutdown"));
			break;
		case "Text": require(constant("_JUMP_PATH_")."/spl/Text.php"); break;
		case "File":
			require(constant("_JUMP_PATH_")."/spl/FileIterator.php");
			require(constant("_JUMP_PATH_")."/spl/File.php");
			break;
		case "Store": require(constant("_JUMP_PATH_")."/spl/Store.php"); break;
		case "Command": require(constant("_JUMP_PATH_")."/spl/Command.php"); break;
		case "Request":
			require(constant("_JUMP_PATH_")."/spl/Request.php");
			register_shutdown_function(array("Request","__shutdown__"));
			break;
		case "Tag":
			require(constant("_JUMP_PATH_")."/spl/TagIterator.php");
			require(constant("_JUMP_PATH_")."/spl/Tag.php");
			break;
		case "Http": require(constant("_JUMP_PATH_")."/spl/Http.php"); break;
		case "Lib": require(constant("_JUMP_PATH_")."/spl/Lib.php"); break;
		case "Paginator": require(constant("_JUMP_PATH_")."/spl/Paginator.php"); break;
		case "Template": require(constant("_JUMP_PATH_")."/spl/Template.php"); break;
		case "Templf": require(constant("_JUMP_PATH_")."/spl/Templf.php"); break;
		case "Flow": require(constant("_JUMP_PATH_")."/spl/Flow.php"); break;
		case "Repository": require(constant("_JUMP_PATH_")."/epl/Repository.php"); break;
		case "InfoClass": require(constant("_JUMP_PATH_")."/epl/InfoClass.php"); break;
		case "InfoMethod": require(constant("_JUMP_PATH_")."/epl/InfoMethod.php"); break;
		case "InfoAt": require(constant("_JUMP_PATH_")."/epl/InfoAt.php"); break;
		case "InfoDoctest": require(constant("_JUMP_PATH_")."/epl/InfoDoctest.php"); break;
		case "Test":
			require(constant("_JUMP_PATH_")."/epl/Test.php");
			register_shutdown_function(array("Test","__shutdown__"));
			Test::__import__();
			require(constant("_JUMP_PATH_")."/__tfuncs__.php");
			break;
		case "Setup": require(constant("_JUMP_PATH_")."/epl/Setup.php"); break;
	}
}
spl_autoload_register('autoload_handler');
