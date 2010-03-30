<?php
/**
 * テスト処理
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Test extends Object{
	const SUCCESS = 2;
	const NONE = 4;
	const FAIL = 8;
	const COUNT = 16;
	static private $exec_type;
	static private $each_flush = false;
	static private $result = array();
	static private $current_class;
	static private $current_method;
	static private $current_file;
	static private $in_test = false;
	static private $path;
	static private $maps = array();
	static private $current_map_test_file;

	/**
	 * アプリケーションのテストファイルの場所を設定する
	 * @param string $path テストファイルのあるフォルダパス
	 */
	final static public function config_path($path){
		self::$path = $path;
	}
	final static private function path($path=null){
		return File::absolute(empty(self::$path) ? App::path("tests") : self::$path,$path);
	}
	/**
	 * 表示種類の定義
	 * @param int $type Test::NONE Test::FAIL Test::SUCCESSによる論理和
	 */
	final static public function exec_type($type){
		self::$exec_type = decbin($type);
	}
	/**
	 * Httpインスタンスを返す
	 * @return Http
	 */
	final static public function browser(){
		File::mkdir(self::tmp_path());
		return new Http("_base_url_=".App::url().'/');
	}
	/**
	 * テストの実行毎にflushさせるようにする
	 */
	final static public function each_flush(){
		self::$each_flush = true;
	}
	/**
	 * 結果を取得する
	 * @return string{}
	 */
	final public static function get(){
		return self::$result;
	}
	/**
	 * 結果をクリアする
	 */
	final public static function clear(){
		self::$result = array();
	}
	/**
	 * 結果を出力しバッファをクリアする
	 */
	final public static function flush(){
		print(new self());
		self::clear();
	}
	/**
	 * ディエクトリパスを指定してテストを実行する
	 * @param string $path
	 * @return Test
	 */
	final public static function verifies(){
		foreach(Lib::classes(true) as $path => $class) self::verify($path);
		if(is_dir(self::path())){
			foreach(File::ls(self::path(),true) as $f){
				$dir = str_replace(self::path(),"",$f->directory());
				if(substr($dir,0,1) == "/") $dir = substr($dir,1);
				if(substr($dir,-1) == "/") $dir = substr($dir,0,-1);
				self::verify(str_replace("/",".",(empty($dir) ? "" : $dir.".")).preg_replace("/_test$/","",$f->oname()));
			}
		}
		return new self();
	}
	/**
	 * テストを実行する
	 * @param string $class_path パッケージパス
	 * @param string $method メソッド名
	 * @param string $block_name ブロック名
	 */
	final public static function verify($class_path,$method=null,$block_name=null){		
		if(is_file(self::path(str_replace(".","/",$class_path)."_test.php"))){
			$file = App::path(str_replace(".","/",$class_path).".php");
			$test  = self::path(str_replace(".","/",$class_path)."_test.php");
			if(is_file($file)){
				if(empty($block_name)){
					include($test);
				}else{
					$read = File::read($test);
					$block = $init = "";
					foreach(preg_split("/(#\s*[\w_]+)\n/",$read,null,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_OFFSET_CAPTURE) as $v){
						if($v[0][0] == "#"){
							$block = trim(substr($v[0],1));
						}else if(empty($block)){
							$init = $v[0];
						}else{
							$blocks[$block] = $v;
						}
					}
					if(isset($blocks[$block_name])){
						self::$current_map_test_file = $test;
						self::$current_file = $test;
						$line = sizeof(explode("\n",substr($read,0,$blocks[$block_name][1]))) - sizeof(explode("\n",$init));
						ob_start();
							eval("?>".$init.str_repeat("\n",$line).$blocks[$block_name][0]);
						$result = ob_get_clean();
						if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)) throw new ErrorException($match[0]);
						self::$current_map_test_file = null;
					}
				}
			}else{
				throw new InvalidArgumentException($file." not found (".$test.")");
			}
		}else{
			$class = (!class_exists($class_path) && !interface_exists($class_path)) ? Lib::import($class_path) : $class_path;
			$cname = str_replace(".","_",$class_path);			
			$ref = new InfoClass($class);
			$is_setup = false;
			$is_teardown = false;
			if(method_exists($ref->name(),"__test_setup__")){
				$m = new ReflectionMethod($ref->name(),"__test_setup__");
				$is_setup = $m->isStatic();
			}
			if(method_exists($ref->name(),"__test_teardown__")){
				$m = new ReflectionMethod($ref->name(),"__test_teardown__");
				$is_teardown = $m->isStatic();
			}			
			self::$current_file = $ref->path();
			self::$current_class = $ref->name();
			foreach(array_merge(array("class"=>$ref->class_method()),$ref->self_methods_all()) as $name => $m){
				self::$current_method = $name;
				if($method === null || $method == $name){
					self::execute($m,$block_name,$class,$is_setup,$is_teardown);
				}
			}
			self::$current_class = null;
			self::$current_file = null;
			self::$current_method = null;
		}
		return new self();
	}
	final private static function execute(InfoMethod $m,$block_name,$class,$is_setup,$is_teardown){
		if(!$m->is_test() && $m->is_public()) return self::$result[self::$current_file][self::$current_class][self::$current_method][$m->line()][] = array("none");
		$result = $line = "";
		try{
			foreach($m->test() as $line => $test){
				if($block_name === null || $test->name() === $block_name){
					ob_start();
						$exception = null;
						if($is_setup) call_user_func(array($class,"__test_setup__"));
						try{
							eval(str_repeat("\n",$line).$test->test());
						}catch(Exception $e){
							$exception = $e;
						}
						if($is_teardown) call_user_func(array($class,"__test_teardown__"));
						if(isset($exception)) throw $exception;
					$result = ob_get_clean();
					if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)) throw new ErrorException($match[0]);
				}
				Exceptions::clear();
			}
			print($result);
		}catch(Exception $e){
			if(ob_get_level() > 0) $result = ob_get_clean();
			if(preg_match("/^[\s]+/ms",$test->test(),$match)) $line = $line + substr_count($match[0],"\n");
			self::$result[self::$current_file][self::$current_class][self::$current_method][$line][] = array("exception",$e->getMessage(),$e->getFile(),$e->getLine());
			Log::warn("[".$e->getFile().":".$e->getLine()."] ".$e->getMessage());
		}
	}
	final static private function expvar($var){
		if(is_numeric($var)) return strval($var);
		if(is_object($var)) $var = get_object_vars($var);
		if(is_array($var)){
			foreach($var as $key => $v){
				$var[$key] = self::expvar($v);
			}
		}
		return $var;
	}
	/**
	 * 判定を行う
	 * @param mixed $arg1 望んだ値
	 * @param mixed $arg2 実行結果
	 * @param boolean 真偽どちらで判定するか
	 * @param int $line 行番号
	 * @param string $file ファイル名
	 * @return boolean
	 */
	final public static function equals($arg1,$arg2,$eq,$line,$file=null){
		$result = ($eq) ? (self::expvar($arg1) === self::expvar($arg2)) : (self::expvar($arg1) !== self::expvar($arg2));
		self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = ($result) ? array() : array(var_export($arg1,true),var_export($arg2,true));
		if(self::$each_flush) print(new Test());
		return $result;
	}
	static private function fcolor($msg,$color="30"){
		return (php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg;
	}
	protected function __str__(){
		$result = "";
		$tab = "  ";
		$success = $fail = $none = 0;
		$cli = (isset($_SERVER['argc']) && !empty($_SERVER['argc']) && substr(PHP_OS,0,3) != 'WIN');

		foreach(self::$result as $file => $f){
			foreach($f as $class => $c){
				$result .= (empty($class) ? "*****" : $class)." [ ".$file." ]\n";
				$result .= str_repeat("-",80)."\n";

				foreach($c as $method => $m){
					foreach($m as $line => $r){
						foreach($r as $l){
							switch(sizeof($l)){
								case 0:
									$success++;
									if(substr(self::$exec_type,-2,1) != "1") break;
									$result .= "[".$line."]".$method.": ".self::fcolor("success","32")."\n";
									break;
								case 1:
									$none++;
									if(substr(self::$exec_type,-3,1) != "1") break;
									$result .= "[".$line."]".$method.": ".self::fcolor("none","1;35")."\n";
									break;
								case 2:
									$fail++;
									if(substr(self::$exec_type,-4,1) != "1") break;
									$result .= "[".$line."]".$method.": ".self::fcolor("fail","1;31")."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									ob_start();
										var_dump($l[0]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"33");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";

									ob_start();
										var_dump($l[1]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"31");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
								case 4:
									$fail++;
									if(substr(self::$exec_type,-4,1) != "1") break;
									$result .= "[".$line."]".$method.": ".self::fcolor("exception","1;31")."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									$result .= self::fcolor($tab.$l[1]."\n\n".$tab.$l[2].":".$l[3],"31");
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
							}
						}
					}
				}
			}
			$result .= "\n";
		}
		Test::clear();
		if(substr(self::$exec_type,-5,1) == "1") $result .= self::fcolor(" success: ".$success." ","7;32")." ".self::fcolor(" fail: ".$fail." ","7;31")." ".self::fcolor(" none: ".$none." ","7;35")."\n";
		return $result;
	}
	/**
	 * テンポラリファイルを作成する
	 * デストラクタで削除される
	 * @param string $path ファイルパス
	 * @param string $body 内容
	 */
	static public function ftmp($path,$body){
		File::write(self::tmp_path($path),Text::plain($body));
	}
	/**
	 * テンポラリファイルを保存するパスを返す
	 * @param string $path テンポラリからの相対ファイルパス
	 * @return string
	 */
	static public function tmp_path($path=null){
		return File::absolute(App::work("test_tmp"),$path);
	}
	static public function __import__(){
		self::exec_type(self::SUCCESS|self::FAIL|self::NONE|self::COUNT);
		if(is_dir(self::tmp_path())){
			Object::C(Flow)->add_module(new self());
			self::$in_test = true;
		}
	}
	static public function __shutdown__(){
		if(!self::$in_test) File::rm(self::tmp_path());
	}
	/**
	 * @see Flow
	 */
	public function flow_handle_result($vars,$url){
		if(self::$in_test) File::write(self::tmp_path(sha1(md5($url))),serialize($vars));
	}
	/**
	 * 指定のURL実行時のFlowの結果から値を取得する
	 * @param string $url 取得したいURL
	 * @param string $name コンテキスト名
	 * @return mixed
	 */
	static public function handled_var($url,$name){
		$path = self::tmp_path(sha1(md5($url)));
		$vars = (is_file($path)) ? unserialize(File::read($path)) : array();
		return array_key_exists($name,$vars) ? $vars[$name] : null;
	}
	/**
	 * xmlのmapのnameからurlを返す
	 * @param string $test_file テストファイルパス
	 * @param string $map_name テストファイルにひも付くアプリケーションXMLのMAP名
	 * @return string
	 */
	static public function map_url($test_file,$map_name){
		$args = func_get_args();
		array_shift($args);
		array_shift($args);

		if(!empty(self::$current_map_test_file)) $test_file = self::$current_map_test_file;
		if(!isset(self::$maps[$test_file]) && preg_match("/^(.+)\/([^\/]+)_test\.php$/",$test_file,$match)){
			list(,$dir,$file) = $match;
			$dir = str_replace(self::path(),"",$dir);
			if(substr($dir,0,1) == "/") $dir = substr($dir,1);
			if(substr($dir,-1) == "/") $dir = substr($dir,0,-1);
			$target_file = App::path($dir."/".$file.".php");

			if(is_file($target_file)){
				$parse_app = Flow::parse_app($target_file);
				foreach($parse_app['apps'] as $app){
					if($app['type'] == 'handle'){
						foreach($app['maps'] as $p => $c){
							$count = 0;
							if(!empty($p)) $p = substr(preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/",create_function('$m','return $m[1]."%s";')," ".$p,-1,$count),1);
							if(!empty($c['name'])) self::$maps[$test_file][$c['name']][$count] = $p;
						}
					}
				}
			}
		}
		if(!isset(self::$maps[$test_file])) throw new InvalidArgumentException($test_file." is not app");
		if(!isset(self::$maps[$test_file][$map_name]) || !isset(self::$maps[$test_file][$map_name][sizeof($args)])){
			throw new InvalidArgumentException($test_file."[".$map_name."](".sizeof($args).") not found");
		}
		return App::url(vsprintf(self::$maps[$test_file][$map_name][sizeof($args)],$args));
	}
}