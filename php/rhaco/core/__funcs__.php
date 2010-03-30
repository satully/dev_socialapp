<?php
/**
 * extensionを読み込む
 *
 * @param string $module_name
 * @param string $doc
 */
function extension_load($module_name,$doc=null){
	if(!extension_loaded($module_name)){
		try{
			dl($module_name.".".PHP_SHLIB_SUFFIX);
		}catch(Exception $e){
			throw new RuntimeException("undef ".$module_name."\n".$doc);
		}
	}
}
/**
 * エラーを標準出力に出力するようにする
 */
function display_errors($html=false){
	error_reporting((version_compare(phpversion(),"5") == -1) ? E_ALL : E_ALL | E_STRICT);
	ini_set("display_errors","On");
	ini_set("display_startup_errors","On");
	ini_set("html_errors",($html) ? "On" : "Off");
}
/**
 * ユニークな名前でクラスを作成する
 * @param string $code
 * @param string $extends
 * @return string $class_name
 */
function create_class($code='',$extends=null){
	if(empty($extends)) $extends = 'Object';
	while(true){
		$class_name = 'U'.md5(uniqid().uniqid('',true));
		if(!class_exists($class_name)) break;
	}
	call_user_func(create_function('','class '.$class_name.' extends '.$extends.'{ '.$code.' }'));
	return $class_name;
}
/**
 * referenceを返す
 *
 * @param object $obj
 * @return object
 */
function R($obj){
	if(is_string($obj)){
		$class_name = import($obj);
		return new $class_name;
	}
	return $obj;
}
/**
  クラスアクセス
 *
 * @param string $class_name
 * @return object
 */
function C($class_name){
	return Object::c(is_object($class_name) ? get_class($class_name) : Lib::import($class_name));
}
/**
 * あるオブジェクトが指定したインタフェースをもつか調べる
 *
 * @param mixed $object
 * @param string $interface
 * @return boolean
 */
function is_implements_of($object,$interface){
	$class_name = (is_object($object)) ? get_class($object) : $object;
	return in_array($interface,class_implements($class_name));
}
/**
 * $classがclassか(interfaceやabstractではなく）
 * @param $class
 * @return boolean
 */
function is_class($class){
	if(!class_exists($class)) return false;
	$ref = new ReflectionClass($class);
	return (!$ref->isInterface() && !$ref->isAbstract());
}

/**
 * Content-Type: text/plain
 */
function header_output_text(){
	header("Content-Type: text/plain;");
}
/**
 * 改行付きで出力
 *
 * @param string $value
 */
function println($value){
	print($value."\n");
}
/**
 * ライブラリのクラス一覧を返す
 * @param $in_vendor
 * @return array
 */
function get_classes($in_vendor=false){
	return Lib::classes(true,$in_vendor);
}
/**
 * importし、クラス名を返す
 * @param string $class_path
 * @return string
 */
function import($class_path){
	return Lib::import($class_path);
}
/**
 * パッケージをimportする
 * @param string $path
 * @return string
 */
function module($path){
	return Lib::module($path,true);
}
/**
 * パッケージのパスを返す
 * @return string
 */
function module_path($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"]);
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージのテンプレートのパスを返す
 * @param string $path ベースパスに続くテンプレートのパス
 * @return string
 */
function module_templates($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"])."/resources/templates";
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージのmediaのパスを返す
 * @param strng $path ベースパスに続くメディアのパス
 * @return string
 */
function module_media($path=null){
	list($file) = debug_backtrace(false);
	$root = Lib::module_root_path($file["file"])."/resources/media";
	return (empty($path)) ? $root : File::absolute($root,$path);
}
/**
 * パッケージ名を返す
 * @return string
 */
function module_package(){
	list($debug) = debug_backtrace(false);
	return Lib::package_path($debug["file"]);
}
/**
 * パッケージルートのクラス名を返す
 * @return string
 */
function module_package_class(){
	list($debug) = debug_backtrace(false);
	$package = Lib::package_path($debug["file"]);
	return substr($package,strrpos($package,".")+1);
}
/**
 * モジュールの定数を取得する
 * def()と対で利用する
 * 
 * @param string $name 設定名
 * @param mixed $default 未設定の場合に返す値
 * @return mixed
 */
function module_const($name,$default=null){
	$packege = null;
	list($debug) = debug_backtrace(false);
	$package = Lib::package_path($debug["file"]);
	return App::def($package."@".$name,$default);
}
/**
 * 文字列表現を返す
 * @param $obj
 * @return string
 */
function str($obj){
	return Text::str($obj);
}
/**
 * 定義情報を設定
 * module_const() と対で利用する
 * 
 * @param string $name パッケージ名@設定名
 * @param mixed $value 値
 */
function def($name,$value){
	$args = func_get_args();
	call_user_func_array(array("App","def"),$args);
}
/**
 * アプリケーションのurlを取得する
 *
 * @param string $path
 * @return string
 */
function url($path=null){
	return App::url($path);
}
/**
 * アプリケーションのファイルパスを取得する
 *
 * @param string $path
 * @return string
 */
function path($path=null){
	return App::path($path);
}
/**
 * アプリケーションのワーキング(テンポラリ)ファイルパスを取得する
 *
 * @param string $path
 * @return string
 */
function work_path($path=null){
	return App::work($path);
}
/**
 * gettext
 * @param $msg
 * @return string
 */
function __($msg){
	$args = func_get_args();
	return call_user_func_array(array("App","trans"),$args);
}
/**
 * ヒアドキュメントのようなテキストを生成する
 * １行目のインデントに合わせてインデントが消去される
 * @param string $text
 * @return string
 */
function text($text){
	return Text::plain($text);
}
/**
 * application xmlを実行する
 * @param string $path xmlファイルのパス
 */
function app($path=null){
	if($path === null){
		list($debug) = debug_backtrace(false);
		$path = $debug["file"];
	}
	Flow::load($path);
}
