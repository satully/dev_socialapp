<?php
/**
 * リクエストを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Request extends Object{
	static protected $__vars__ = 'type=mixed{}';
	static protected $__sessions__ = 'type=mixed{}';
	static protected $__files__ = 'type=File[]';
	static protected $__args__ = 'type=string';

	static private $session_save_path;
	static private $session_start = false;
	private $_inc_session_ = true;
	private $_scope_;

	protected $vars = array(); # リクエストされた値 
	protected $sessions = array(); # セッション値
	protected $files = array(); # アップロードされたファイル
	protected $args; # pathinfo または argv
	private $expire = 1209600;

	static private $session_limiter = 'nocache';
	static private $session_expire = 2592000;
	static private $session_gc_divisor = 100;
	static private $session_name = 'SID';

	/**
	 * セッションに関する設定
	 * @param alnum $name セッション名
	 * @param choice(none,nocache,private,private_no_expire,public) $limiter キャッシュリミッタ
	 * @param integer $expire 有効期間
	 * @param integer $gc_divisor GCの実行タイミング
	 */
	static public function config_session($name,$limiter=null,$expire=null,$gc_divisor=null){
		if(!empty($name)) self::$session_name = $name;
		if(isset($limiter)) self::$session_limiter = $limiter;
		if(isset($expire)) self::$session_expire = $expire;
		if(isset($gc_divisor)) self::$session_gc_divisor = $gc_divisor;
		if(!ctype_alpha(self::$session_name)) throw new InvalidArgumentException('session name is is not a alpha value');
	}	
	protected function __new__(){
		if((func_num_args() > 0)){
			foreach(Text::dict(func_get_arg(0)) as $n => $v){
				switch($n){
					case "_scope_":
					case "_inc_session_":
					case "_init_":
						$this->{$n} = $v;
				}
			}
		}
		if(isset($_GET) && is_array($_GET)){
			foreach($_GET as $key => $value) $this->vars[$key] = $this->mq_off($value);
		}
		if(isset($_POST) && is_array($_POST)){
			foreach($_POST as $key => $value) $this->vars[$key] = $this->mq_off($value);
		}
		if(empty($this->vars) && isset($_SERVER['argv'])){
			$argv = $_SERVER['argv'];
			array_shift($argv);
			if(isset($argv[0]) && $argv[0][0] != '-'){
				$this->args = implode(' ',$argv);
			}else{
				$size = sizeof($argv);
				for($i=0;$i<$size;$i++){
					if($argv[$i][0] == '-'){
						if(isset($argv[$i+1]) && $argv[$i+1][0] != '-'){
							$this->vars[substr($argv[$i],1)] = $argv[$i+1];
							$i++;
						}else{
							$this->vars[substr($argv[$i],1)] = '';
						}
					}
				}
			}
		}
		if('' != ($pathinfo = (array_key_exists('PATH_INFO',$_SERVER)) ?
			( (empty($_SERVER['PATH_INFO']) && array_key_exists('ORIG_PATH_INFO',$_SERVER)) ?
					$_SERVER['ORIG_PATH_INFO'] : $_SERVER['PATH_INFO'] ) : (isset($this->vars['pathinfo']) ? $this->vars['pathinfo'] : null))
		){
			if($pathinfo[0] != '/') $pathinfo = '/'.$pathinfo;
			$this->args = preg_replace("/(.*?)\?.*/","\\1",$pathinfo);
		}
		if(isset($this->vars['application_branch'])){
			App::branch($this->vars['application_branch']);
			unset($this->vars['application_branch']);
		}
		if(isset($_COOKIE) && is_array($_COOKIE)){
			foreach($_COOKIE as $key => $value) $this->vars[$key] = $this->mq_off($value);
		}
		if(isset($_FILES) && is_array($_FILES)){
			foreach($_FILES as $key => $files) $this->files($key,$files);
		}
		if($this->_inc_session_){
			if(!self::$session_start){
				ini_set('session.gc_probability','1');
				ini_set('session.gc_divisor',self::$session_gc_divisor);
				session_cache_limiter(self::$session_limiter);
				session_cache_expire(self::$session_expire);
				session_name(self::$session_name);

				if(Object::C(__CLASS__)->has_module('session_read')){
					ini_set('session.save_handler','user');
					session_set_save_handler(
						array($this,'__session_open__'),array($this,'__session_close__'),array($this,'__session_read__'),
						array($this,'__session_write__'),array($this,'__session_destroy__'),array($this,'__session_gc__')
					);
					if(isset($this->vars[self::$session_name])){
						list($session_name,$id,$path) = array(self::$session_name,$this->vars[self::$session_name],session_save_path());
						if(Object::C(__CLASS__)->call_module('session_verify',$session_name,$id,$path) !== true){
							session_regenerate_id(true);
						}
					}
				}else{
					if(isset($this->vars[self::$session_name]) 
						&& is_dir(session_save_path())
						&& !is_file(File::absolute(session_save_path(),'sess_'.$this->vars[self::$session_name]))
					){
						session_regenerate_id(true);
					}
				}
				session_start();
				self::$session_start = true;
			}
			if(empty($this->_scope_)) $this->_scope_ = $this->_class_;
			$this->session_init();
		}
	}
	final protected function scope(){
		return $this->_scope_;
	}
	final protected function __set_files__($key,$req){
		$file = new File($req['name']);
		$file->tmp(isset($req['tmp_name']) ? $req['tmp_name'] : '');
		$file->size(isset($req['size']) ? $req['size'] : '');
		$file->error($req['error']);
		$this->files[$key] = $file;
	}
	/**
	 * クッキーへの書き出し
	 * @param string $name 書き込む変数名
	 * @param int $expire 有効期限 (+ time)
	 * @param string $path パスの有効範囲
	 * @param boolean $subdomain サブドメインでも有効とするか
	 * @param boolean $secure httpsの場合のみ書き出しを行うか
	 */
	public function write_cookie($name,$expire=null,$path=null,$subdomain=false,$secure=false){
		if(empty($expire)) $expire = 1209600;
		$server = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');	
		if($subdomain && substr_count($server,'.') >= 2) $server = preg_replace("/.+(\.[^\.]+\.[^\.]+)$/","\\1",$server);
		if(empty($path)) $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		setcookie($name,$this->in_vars($name),time() + $expire,$path,$server,$secure);
	}
	/**
	 * クッキーから削除
	 * @param string $name クッキー名
	 */
	public function delete_cookie($name){
		setcookie($name,false,time() - 3600);
	}
	static public function __shutdown__(){
		if(self::$session_start) session_write_close();
	}
	protected function __cp__($obj){
			if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	/**
	 * 現在のURLを返す
	 * @return string
	 */
	static public function current_url(){
		$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
		$server = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
		$path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		if(!($port == 80 || $port == 443)) $server = $server.':'.$port;
		return (($port === 443) ? 'https' : 'http').'://'.preg_replace("/^(.+?)\?.*/","\\1",$server).$path;
	}
	/**
	 * 現在のリクエストクエリを返す
	 * @return string
	 */
	static public function query_string(){
		return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
	}
	/**
	 * 現在のリクエストクエリを含まないURLを返す
	 * @return string
	 */
	static public function current_script(){
		$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
		$server = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
		$path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
		if(!($port == 80 || $port == 443)) $server = $server.':'.$port;
		return (($port === 443) ? 'https' : 'http').'://'.$server.$path;
	}
	/**
	 * POSTされたか
	 * @return boolean
	 */
	public function is_post(){
		return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST');
	}
	/**
	 * CLIで実行されたか
	 * @return boolean
	 */
	public function is_cli(){
		return (php_sapi_name() == 'cli');
	}
	private function mq_off($value){
		return (get_magic_quotes_gpc() && is_string($value)) ? stripslashes($value) : $value;
	}
	private function sess_name($name){
		return $this->_scope_."__".$name;
	}
	protected function __rm_sessions__(){
		$args = func_get_args();
		if(!empty($args)){
			foreach($args as $arg){
				if($arg instanceof self) $arg = $arg->str();
				if(isset($this->sessions[$this->sess_name($arg)])) unset($this->sessions[$this->sess_name($arg)],$_SESSION[$this->sess_name($arg)]);
			}
		}
	}
	protected function __set_sessions__($key,$value){
		if(is_object($value)){
			$ref = new ReflectionClass(get_class($value));
			if(substr($ref->getFileName(),-4) !== ".php") throw new InvalidArgumentException($key.' is not permitted');
		}
		$this->sessions[$this->sess_name($key)] = $value;
	}
	protected function __in_sessions__($key,$default=null){
		return isset($this->sessions[$this->sess_name($key)]) ? $this->sessions[$this->sess_name($key)] : $default;
	}
	protected function __is_sessions__($key){
		return isset($this->sessions[$this->sess_name($key)]);
	}
	private function session_init(){
		$this->login_id = __CLASS__.'_LOGIN_';
		$this->sessions = &$_SESSION;
		$vars = $this->in_sessions('_saved_vars_');
		if(is_array($vars)){
			foreach($vars as $key => $value) $this->vars($key,$value);
		}
		$this->rm_sessions('_saved_vars_');
		$exceptions = $this->in_sessions('_saved_exceptions_');
		if(is_array($exceptions)){
			foreach($exceptions as $e) Exceptions::add($e[0],$e[1]);
		}
		$this->rm_sessions('_saved_exceptions_');
	}
	/**
	 * Exceptionを保存する
	 * @param Exception $exception
	 * @param string $name
	 */
	protected function save_exception(Exception $exception,$name=null){
		$exceptions = $this->in_sessions('_saved_exceptions_');
		if(!is_array($exceptions)) $exceptions = array();
		$exceptions[] = array($exception,$name);
		$this->sessions('_saved_exceptions_',$exceptions);
	}
	/**
	 * 現在のvarsを保存する
	 */
	protected function save_current_vars(){
		foreach($this->vars() as $k => $v){
			if(is_object($v)){
				$ref = new ReflectionClass(get_class($v));
				if(substr($ref->getFileName(),-4) !== ".php") throw new InvalidArgumentException($k.' is not permitted');
			}
		}		
		$this->sessions('_saved_vars_',$this->vars());
	}
	/**
	 * ユーザエージェント
	 * @return string
	 */
	public function user_agent(){
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}
	/**
	 * ログインユーザ
	 * @return mixed
	 */
	public function user(){
		$args = func_get_args();
		if(!empty($args) && isset($args[0])){
			if(!isset($_SESSION)) throw LogicException('no session');
			$this->sessions($this->login_id.'USER',$args[0]);
			$this->sessions($this->login_id,$this->login_id);
			session_regenerate_id(true);
		}
		return $this->in_sessions($this->login_id.'USER');
	}
	/**
	 * ログインする
	 * @return boolean
	 */
	public function login(){
		if(!isset($_SESSION)) throw new LogicException('no session');
		if($this->is_login()) return true;
		if($this->call_module('login_condition',$this) === false){
			$this->call_module('login_invalid',$this);
			$this->logout();
			return false;
		}
		$this->sessions($this->login_id,$this->login_id);
		$this->call_module('after_login',$this);
		return true;
	}
	/**
	 * ログイン済みか
	 * @return boolean
	 */
	public function is_login(){
		return $this->is_sessions($this->login_id);
	}
	/**
	 * 後処理、失敗処理の無いログイン
	 * @return boolean
	 */
	public function silent(){
		if($this->is_login()) return true;
		if($this->call_module('login_condition',$this) === false){
			$this->logout();
			return false;
		}
		$this->sessions($this->login_id,$this->login_id);
		return true;
	}
	/**
	 * ログアウトする
	 */
	public function logout(){
		if(!isset($_SESSION)) throw LogicException('no session');
		$this->call_module('before_logout',$this);
		$this->rm_sessions($this->login_id.'USER');
		$this->rm_sessions($this->login_id);
	}
	final public function __session_open__($save_path,$session_name){
		if(Object::C(__CLASS__)->has_module('session_close')) return Object::C(__CLASS__)->call_module('session_open',$save_path,$session_name);
		return true;
	}
	final public function __session_close__(){
		if(Object::C(__CLASS__)->has_module('session_close')) return Object::C(__CLASS__)->call_module('session_close');
		return true;
	}
	final public function __session_read__($id){
		return Object::C(__CLASS__)->call_module('session_read',$id);
	}
	final public function __session_write__($id,$sess_data){
		if(Object::C(__CLASS__)->has_module('session_write')) return Object::C(__CLASS__)->call_module('session_write',$id,$sess_data);
		return true;
	}
	final public function __session_destroy__($id){
		if(Object::C(__CLASS__)->has_module('session_destroy')) return Object::C(__CLASS__)->call_module('session_destroy',$id);
		return true;
	}
	final public function __session_gc__($maxlifetime){
		if(Object::C(__CLASS__)->has_module('session_gc')) return Object::C(__CLASS__)->call_module('session_gc',$maxlifetime);
		return true;
	}
}