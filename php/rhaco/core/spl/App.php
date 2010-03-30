<?php
/**
 * アプリケーション定義
 * @author tokushima
 */
class App{
	static private $def = array();
	static private $shutdown = array();
	static private $secure = true;

	static private $path;
	static private $work;
	static private $mode = 'noname';
	static private $url;
	static private $surl;

	static private $branch;
	
	static private $lang;
	static private $messages = array();
	static private $messages_path = array();
	static private $message_head = array();

	/**
	 * LANGを設定、取得する
	 * @param string $lang 言語コード
	 * @return string
	 */
	static public function lang($lang=null){
		if(!empty($lang)){
			self::$lang = $lang;
			self::$messages = array();
			self::$message_head = array();
			foreach(self::$messages_path as $dir_name => $null) self::set_messages($dir_name);
		}
		return self::$lang;
	}
	/**
	 * パッケージの国際化メッセージを設定する
	 * @param string $dir_name メッセージファイルのあるフォルダ
	 */
	static public function set_messages($dir_name){
		self::$messages_path[$dir_name] = true;
		self::load_messages(File::absolute($dir_name,'resources/locale/messages/message-'.self::$lang.'.mo'));
	}
	static private function load_messages($mo_filename){
		if(!is_file($mo_filename)) return;
		$bin = File::read($mo_filename);
		$values = array();
		$head_no = sizeof(self::$message_head) + 1;
		self::$message_head[$head_no] = null;

		list(,$magick) = unpack('L',substr($bin,0,4));
		list(,$count) = unpack('l',substr($bin,8,4));
		list(,$id_length) = unpack('l',substr($bin,16,4));

		for($i=0,$y=28,$z=$id_length;$i<$count;$i++,$y+=8,$z+=8){
			list(,$key_len) = unpack('l',substr($bin,$y,4));
			list(,$key_offset) = unpack('l',substr($bin,$y+4,4));

			list(,$value_len) = unpack('l',substr($bin,$z,4));
			list(,$value_offset) = unpack('l',substr($bin,$z+4,4));

			$key = substr($bin,$key_offset,$key_len);
			if($key === ''){
				$header = explode("\n",substr($bin,$value_offset,$value_len));
				foreach($header as $head){
					list($name,$value) = explode(':',$head,2);
					if(strtolower(trim($name)) === 'plural-forms'){
						self::$message_head[$head_no] = str_replace("n","\$n",preg_replace("/^.*plural[\s]*=(.*)[;]*$/","\\1",$value));
						break;
					}
				}
			}else{
				$values[$key][0] = $head_no;
				$values[$key][1] = explode("\0",substr($bin,$value_offset,$value_len));
			}
		}
		foreach($values as $key => $value){
			if(!isset(self::$messages[$key])) self::$messages[$key] = $value;
		}
	}
	/**
	 * 国際化文字列を返す
	 * @param string $key 国際化する文字列
	 * @return string
	 */
	static public function trans($key){
		$args = func_get_args();
		$argsize = func_num_args();
		$key = array_shift($args);
		$message = $key;

		if(isset(self::$messages[$key])){
			$message = self::$messages[$key][1][0];
			if(!empty($args) && sizeof(self::$messages[$key][1]) > 1){
				$plural_param = (int)array_shift($args);
				if(isset(self::$message_head[self::$messages[$key][0]])){
					$n = $plural_param;
					$message = self::$messages[$key][1][(int)self::$message_head[self::$messages[$key][0]]];
				}
			}
		}
		return Text::fstring($message,$args);
		/***
			eq("hoge",self::trans("hoge"));
		 */
	}

	/**
	 * 定義情報を設定/取得
	 * @param string $name 定義名
	 * @param mixed $value 値
	 * @return mixed
	 */
	static public function def($name,$value=null){
		if($value !== null && !isset(self::$def[$name])){
			if(func_num_args() > 2){
				$args = func_get_args();
				array_shift($args);
				$value = $args;
			}
			self::$def[$name] = $value;
			return self::$def[$name];
		}
		return (isset(self::$def[$name])) ? self::$def[$name] : null;
	}
	/**
	 * 定義情報があるか
	 * @param $name 定義名
	 * @return boolean
	 */
	static public function defined($name){
		return isset(self::$def[$name]);
	}
	/**
	 * 特定キーワードの定義情報一覧を返す
	 * @param string $key キーワード
	 * @return string{}
	 */
	static public function constants($key){
		$result = array();
		foreach(self::$def as $k => $value){
			if(strpos($k,$key) === 0) $result[$k] = $value;
		}
		return $result;
	}
	/**
	 * 終了処理するクラスを登録する
	 * @param Object $object 登録するインスタンス
	 */
	static public function register_shutdown($object){
		self::$shutdown[] = array($object,'__shutdown__');
	}
	/**
	 * 終了処理を実行する
	 */
	static public function shutdown(){
		krsort(self::$shutdown,SORT_NUMERIC);
		foreach(self::$shutdown as $s) call_user_func($s);
	}
	/**
	 * 初期定義
	 *
	 * @param string $path アプリケーションのルートパス
	 * @param string $url アプリケーションのURL
	 * @param string $work 一時ファイルを書き出すパス
	 * @param string $mode モード
	 */
	static public function config_path($path,$url=null,$work=null,$mode=null){
		if(empty($path)){
			$debug = debug_backtrace(false);
			$debug = array_pop($debug);
			$path = $debug['file'];
		}
		if(is_file($path)) $path = dirname($path);
		self::$path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$path))."/";

		if(isset($work)){
			if(is_file($work)) $work = dirname($work);
			self::$work = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$work))."/";
		}else{
			self::$work = self::$path.'work/';
		}
		if(!empty($url)){
			self::$url = preg_replace("/^(.+)\/$/","\\1",$url)."/";
			self::$surl = preg_replace("/^http:\/\/(.+)$/","https://$1",self::$url);
		}
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			list($lang)	= explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
			list($lang)	= explode('-',$lang);
			self::lang($lang);
			self::set_messages(self::$path);
		}
		self::$mode = (empty($mode)) ? 'noname' : $mode;
		if(is_file(App::path('__repository__.xml'))) Repository::load_map(App::path('__repository__.xml'));
		if(is_file(App::path('__common__.php'))) require_once(App::path('__common__.php'));
		if(is_file(App::path('__common_'.$mode).'__.php')) require_once(App::path('__common_'.$mode.'__.php'));
	}
	/**
	 * surl呼出し時にhttpsにするか
	 * @param boolean $bool httpsにする場合はtrue
	 */
	static public function config_secure($bool){
		self::$secure = $bool;
	}
	/**
	 * アプリケーションのブランチ名
	 * @param string $branch セットするブランチ名
	 */
	static public function branch($branch=null){
		if(isset($branch) && !isset(self::$branch)) self::$branch = $branch;
		return self::$branch;
	}
	/**
	 * アプリケーションパスとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @return string
	 */
	static public function path($path=null){
		if(!isset(self::$path)) self::$path = dirname(self::called_filename()).'/';
		if(isset($path[0]) && $path[0] == '/') $path = substr($path,1);
		return self::$path.$path;
	}
	/**
	 * workパスとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @return string
	 */
	static public function work($path=null){
		if(!isset(self::$work)) self::$work = self::path('work').'/';
		if(isset($path[0]) && $path[0] == '/') $path = substr($path,1);
		return self::$work.$path;
	}
	/**
	 * アプリケーションURLとの絶対パスを返す
	 * @param string $path 追加のパス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	static public function url($path=null,$branch=true){
		if(!isset(self::$url)) return null;
		if(isset($path[0]) && $path[0] == '/') $path = substr($path,1);
		$path = self::$url.(($branch && !empty(self::$branch)) ? self::$branch.'/' : '').$path;
		if(substr($path,-1) == '/') $path = substr($path,0,-1);
		return $path;
	}
	/**
	 * アプリケーションURLとの絶対パスをhttpsとして返す
	 * @param string $path 追加のパス
	 * @param boolean $branch ブランチ名を結合するか
	 * @return string
	 */
	static public function surl($path=null,$branch=true){
		if(!self::$secure) return self::url($path,$branch);
		if(!isset(self::$surl)) return null;
		if(isset($path[0]) && $path[0] == '/') $path = substr($path,1);
		$path = self::$surl.(($branch && !empty(self::$branch)) ? self::$branch.'/' : '').$path;
		if(substr($path,-1) == '/') $path = substr($path,0,-1);
		return $path;
	}
	/**
	 * 現在のアプリケーションモードを取得
	 * @return string
	 */
	static public function mode(){
		return self::$mode;
	}
	/**
	 * 呼び出しもとのファイル名を返す
	 * @return string
	 */
	static public function called_filename(){
		$debug = debug_backtrace(false);
		$root = array_pop($debug);
		return (isset($root['file'])) ? str_replace("\\","/",$root['file']) : null;
	}
	/**
	 * アプリケーションの説明
	 * @param string $path アプリケーションXMLのファイルパス
	 * @return string{} "title"=>"..","summary"=>"..","description"=>"..","installation"=>".."
	 */
	static public function info($path=null){
		$name = $summary = $description = $installation = '';
		if(empty($path)) $path = self::path();
		$app = empty(self::$branch) ? 'index' : self::$branch;
		$filename = is_file(File::absolute($path,$app.'.php')) ?
						File::absolute($path,$app.'.php') :
						(is_file(File::absolute($path,basename($path).'.php')) ? File::absolute($path,basename($path).'.php') : null);
		if(is_file($filename)){
			$name = basename(dirname($filename));
			$src = File::read($filename);
			if(Tag::setof($t,$src,'app')){
				$summary = $t->in_param('summary');
				$name = $t->in_param('name',$t->in_param('label',$name));
				$description = $t->f('description.value()');
				$installation = $t->f('installation.value()');
			}else if(preg_match("/\/"."\*\*(.+?)\*\//ms",$src,$match)){
				$description = trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$match[0])));
				if(preg_match("/@name[\s]+(.+)/",$description,$match)){
					$description = str_replace($match[0],"",$description);
					$name = trim($match[1]);
				}
				if(preg_match("/@summary[\s]+(.+)/",$description,$match)){
					$description = str_replace($match[0],"",$description);
					$summary = trim($match[1]);
				}
			}
		}
		return array('name'=>$name,'summary'=>$summary,'description'=>$description,'installation'=>$installation,'filename'=>$filename);
	}
	/**
	 * coreクラスのあるファイルパス
	 * @return string
	 */
	static public function core_path(){
		return constant("_JUMP_PATH_");
	}
}