<?php
/**
 * リクエスト/テンプレートを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Flow extends Request{
	static protected $__pattern__ = 'set=false';
	protected $_mixin_ = 'Template';
	protected $_login_required_ = false;

	protected $pattern; # マッチしたパターン
	protected $name; # マッチしたマッピング名
	protected $template; # テンプレートパス
	private $redirect;
	private $map_args = array();
	private $method_url_patterns = array();
	private $name_url_patterns = array();
	private $match_params = array();
	private $handled_map = array();

	private $request_url;
	private $request_query;
	static private $is_app_cache = false;
	static private $package_media_url = "package/resources/media";

	/**
	 * キャッシュをするかを定義する
	 * @param boolean $bool キャッシュを作成するか
	 */
	final static public function config_cache($bool){
		self::$is_app_cache = (boolean)$bool;
	}
	/**
	 * パッケージのメディアへのURLを定義する
	 * @param string $url パッケージのメディアと認識されるURL
	 */
	final static public function config_package_media_url($url){
		if(substr($url,0,1) == "/") $url = substr($url,1);
		if(substr($url,-1) == "/") $url = substr($url,0,-1);
		self::$package_media_url = $url;
	}
	final protected function __new__(){
		parent::__new__((func_num_args() > 0) ? func_get_arg(0) : null);
		$this->o('Template')->cp($this->vars);
		$this->request_url = parent::current_url();
		$this->request_query = (parent::query_string() == null) ? null : '?'.parent::query_string();
	}
	final protected function __cp__($obj){
		$this->o('Template')->cp($obj);
	}
	final protected function __set_vars__($key,$value){
		$this->o('Template')->vars($key,$value);
	}
	/**
	 * 現在のvarsを保存する
	 */
	protected function save_current_vars(){
		$this->sessions('_saved_vars_',$this->o('Template')->vars());
	}
	/**
	 * mapで定義されたarg値
	 * @param string $name
	 * @param string $default
	 * @return string
	 */
	final protected function map_arg($name,$default=null){
		return (array_key_exists($name,$this->map_args)) ? $this->map_args[$name] : $default;
	}
	/**
	 * 自身のメソッドを呼び出しているURLにリダイレクト
	 * @param string $method_name メソッド名
	 */
	final protected function redirect_method($method_name){
		$args = func_get_args();
		array_unshift($args,'method_url');
		return call_user_func_array(array($this,'redirect_by_map_urls'),$args);
	}
	/**
	 * mapで定義されたarg値の名前をもとにredirectする
	 * @param string $name
	 */
	final protected function redirect_by_map($name){
		$args = func_get_args();
		$name = array_shift($args);
		$arg = $this->map_arg($name);

		if(isset($arg)){
			array_unshift($args,$arg);
			array_unshift($args,'map_url');
			return call_user_func_array(array($this,'redirect_by_map_urls'),$args);
		}
	}
	final private function redirect_by_map_urls($func){
		$args = func_get_args();
		$func = array_shift($args);
		$vars = $params = array();
		foreach($args as $arg){
			if(is_array($arg)){
				$vars = array_merge($vars,$arg);
			}else{
				$params[] = $arg;
			}
		}
		$this->save_current_vars();
		$this->sessions("_redirect_by_map_urls_",true);
		$this->redirect(call_user_func_array(array($this,$func),$params).(empty($vars) ? '' : '?'.Http::query($vars)));		
	}
	final protected function redirect($url=null){
		if($url === null) $url = $this->redirect;
		$url = File::absolute(App::url().'/',$url);		
		$this->call_module('redirect_url',$url);
		Http::redirect($url);
	}
	protected function __set_template__($path){
		$this->template = $path;
		$this->filename($path);
	}
	protected function __get_template__(){
		return $this->fm_filename($this->template);
	}
	/**
	 * phpinfoからattachする
	 * @param string $path
	 */
	final protected function attach_self($path){
		$this->media_url(Request::current_url().'/');
		if($this->args() != null){
			Log::disable_display();
			Http::attach(new File($path.$this->args()));
			exit;
		}
	}
	/**
	 * ファイルからattachする
	 * @param string $path
	 */
	final protected function attach_file($path){
		Http::attach(new File($path));
		exit;
	}
	/**
	 * 文字列からattachする
	 * @param string $path
	 * @param string $filename
	 */
	final protected function attach_text($src,$filename=null){
		Http::attach(new File($filename,$src));
		exit;
	}
	/**
	 * リクエストされたURLにリダイレクトする
	 */
	final protected function redirect_self($query=true){
		$this->redirect($this->request_url($query));
	}
	/**
	 * リファラにリダイレクトする
	 */
	final protected function redirect_referer(){
		$this->redirect(Http::referer());
	}
	/**
	 * リクエストされたURLを返す
	 *
	 * @param boolean $query
	 * @return string
	 */
	final protected function request_url($query=true){
		return $this->request_url.(($query) ? $this->request_query : '');
	}
	/**
	 * 指定済みのファイルから生成する
	 * @param string $template テンプレートファイルパス
	 * @param string $template_name テンプレート名
	 * @module flow_handle_result ハンドリング後に実行する (mixed{} $vars,string $url)
	 * @request Templf $t テンプレートフィルタ
	 * @return string
	 */
	final public function read($template=null,$template_name=null){
		if($template !== null) $this->template($template);
		list($result_vars,$result_url) = array($this->o('Template')->vars(),App::url($this->args()));
		Object::C(__CLASS__)->call_module('flow_handle_result',$result_vars,$result_url);
		if(!$this->is_vars('t') || !($this->in_vars('t') instanceof Templf)) $this->vars('t',new Templf($this));
		return $this->o('Template')->read(null,$template_name);
	}
	/**
	 * 出力する
	 *
	 * @param string $template テンプレートファイルパス
	 * @param string $template_name テンプレート名
	 */
	final public function output($template=null,$template_name=null){
		print($this->read($template,$template_name));
		exit;
	}
	/**
	 * モジュールで検証を行う
	 * @module flow_verify (self)
	 * @return boolean
	 */
	final protected function verify(){
		return ($this->has_module('flow_verify')) ? $this->call_module('flow_verify',$this) : true;
	}
	/**
	 * not found (http status 404)
	 */
	protected function not_found(){
		Http::status_header(404);
		exit;
	}
	/**
	 * ログインを必須とする
	 * @param string $redirect_to リダイレクト先
	 */
	protected function login_required($redirect_to=null){
		if(!$this->is_login()){
			if(!isset($redirect_to)) $redirect_to = $this->in_sessions('logined_redirect_to',Http::referer());
			$this->sessions('logined_redirect_to',App::url($redirect_to));
			$this->redirect_method('do_login');
		}
	}
	/**
	 * ログイン
	 * @arg string $login_redirect ログイン後にリダイレクトされるマップ名
	 */
	public function do_login(){
		if($this->is_login() || ($this->is_post() && $this->login())){
			$redirect_to = $this->in_sessions('logined_redirect_to');
			$this->rm_sessions('logined_redirect_to');
			if($this->map_arg('login_redirect') !== null) $this->redirect_by_map('login_redirect');
			if(!empty($redirect_to)) $this->redirect($redirect_to);
		}
	}
	/**
	 * ログアウト
	 * @arg string $logout_redirect ログアウト後にリダイレクトされるマップ名
	 */
	public function do_logout(){
		$this->logout();
		if($this->map_arg('logout_redirect') !== null) $this->redirect_by_map('logout_redirect');
	}
	/**
	 * 何もしない
	 */
	final public function noop(){
	}
	/**
	 * redirect_by_mapかredirect_methodからのみ呼べる
	 */
	final public function rg(){
		if($this->in_sessions('_redirect_by_map_urls_') !== true) throw new RuntimeException('rg not permitted');
		$this->rm_sessions('_redirect_by_map_urls_');
	}
	/**
	 * テンプレートパスからの絶対パスを返す
	 * @param string $path
	 * @return string
	 */
	final protected function template_path($path){
		return $this->fm_filename($path);
	}
	/**
	 * ハンドリングされたmap
	 * @return string{}
	 */
	final public function handled_map(){
		return $this->handled_map;
	}
	/**
	 * handlerのマップ名を呼び出しているURLを生成する
	 * @param string $map_name マップ名
	 * @return string
	 */
	final public function map_url($map_name){
		$args = func_get_args();
		array_shift($args);
		for($i=sizeof($args);$i>=0;$i--){
			if(isset($this->name_url_patterns[$map_name][$i])){
				$m = $this->name_url_patterns[$map_name][$i];
				if($m['secure']) return App::surl(vsprintf($m['url'],$args));
				return App::url(vsprintf($m['url'],$args));
			}
		}
		throw new LogicException('undef name `'.$map_name.'` url ['.sizeof($args).']');
	}
	final protected function method_url($method_name){
		$args = func_get_args();
		array_shift($args);
		for($i=sizeof($args);$i>=0;$i--){
			if(isset($this->method_url_patterns[$method_name][$i])){
				$m = $this->method_url_patterns[$method_name][$i];
				if($m['secure']) return App::surl(vsprintf($m['url'],$args));
				return App::url(vsprintf($m['url'],$args));
			}
		}
		throw new LogicException('undef method `'.$method_name.'` url ['.sizeof($args).']');
	}
	/**
	 * URLパターンからハンドリング
	 * @param mixed{} $urls
	 * @param int $index
	 * @return $this
	 */
	private function handler(array $urls=array(),$index=0){
		if(preg_match("/^\/".str_replace("/","\\/",self::$package_media_url)."\/(\d+)\/(\d+)\/(.+)$/",$this->args(),$match)){
			if($match[1] == $index){
				foreach($urls as $args){
					if($match[2] == $args['map_index'] && isset($args['class'])){
						$this->attach_file(File::absolute(Lib::module_root_path(Lib::imported_path(Lib::import($args['class']))).'/resources/media',$match[3]));
					}
				}
				Http::status_header(404);
				exit;
			}
			return $this;
		}
		foreach(array_keys($urls) as $pattern){
			if(preg_match("/^".(empty($pattern) ? "" : "\/").str_replace(array("\/","/","__SLASH__"),array("__SLASH__","\/","\/"),$pattern).'[\/]{0,1}$/',$this->args(),$params)){
				Log::debug("match pattern `".$pattern."` ".(empty($urls[$pattern]['name']) ? '' : '['.$urls[$pattern]['name'].']'));
				array_shift($params);
				$this->pattern = $pattern;
				$map = $urls[$pattern];
				$action = null;

				if(!empty($map['redirect']) && empty($map['class'])){
					$this->redirect(($map['redirect'][0] == '/') ? substr($map['redirect'],1) : $map['redirect']);
				}
				if(!empty($map['template']) && empty($map['method'])){
					$action = new self('_scope_='.$map['scope']);
					$action->set($this,$map,$pattern,$params,$urls);
				}else{
					if(empty($map['class'])) throw new RuntimeException('Invalid map');
					$class = class_exists($map['class']) ? $map['class'] : Lib::import($map['class']);
					if(!method_exists($class,$map['method'])) throw new RuntimeException($map['class'].'::'.$map['method'].' not found');
					if(!is_subclass_of($class,__CLASS__)) throw new RuntimeException("class is not ".__CLASS__);

					$action = new $class('_scope_='.$map['scope'].',_init_=false');
					foreach(array('redirect','name') as $k) $action->{$k} = $map[$k];

					$action->set($this,$map,$pattern,$params,$urls);
					call_user_func_array(array($action,$map['method']),$params);
					if(!$action->is_filename()){
						$ref = new ReflectionObject($action);
						$file = dirname($ref->getFileName()).'/resources/templates/'.$map['method'].'.html';
						if(is_file($file)){
							$action->template($file);
							$action->media_url(App::url('/'.self::$package_media_url.'/'.$index.'/'.$urls[$pattern]['map_index']));
						}else if($action->is_filename($map['method'].'.html')){
							$action->template($map['method'].'.html');
						}
					}
				}
				$action->call_module('after_flow',$action);
				$this->add_object($action->o('Template'));
				$this->cp(self::execute_var($map['vars']));
				$this->vars('t',new Templf($action));
				break;
			}
		}
		return $this;
	}
	private function set($module_obj,$map,$pattern,$params,$urls){
		$this->add_module($module_obj,true,true);
		$this->pattern = $pattern;
		$this->match_params = $params;
		$this->handled_map = $map;
		$this->template($map['template']);
		$this->name = $map['name'];
		$this->map_args = $map['args'];
		
		foreach($urls as $p => $c){
			$count = 0;
			if(!empty($p)) $p = substr(preg_replace_callback("/([^\\\\])(\(.*?[^\\\\]\))/",create_function('$m','return $m[1]."%s";')," ".$p,-1,$count),1);
			if($c['class'] === $map['class'] && isset($c['method'])) $this->method_url_patterns[$c['method']][$count] = array('url'=>$p,'secure'=>$c['secure']);
			if(!empty($c['name'])) $this->name_url_patterns[$c['name']][$count] = array('url'=>$p,'secure'=>$c['secure']);
		}
		foreach($map['modules'] as $module) $this->add_module(self::import_instance($module),true);
		$this->call_module('init_flow',$this);
		if(method_exists($this,'__init__')) $this->__init__();
		if(!$this->is_login() && $this->_login_required_ && $map['method'] !== 'do_login') $this->login_required();
		$this->call_module('before_flow',$this);
	}	
	/**
	 * xml定義からhandlerを処理する
	 * @param string $file アプリケーションXMLのファイルパス
	 */
	final static public function load($file=null){
		if(!isset($file)) $file = App::mode().App::called_filename();
		if(!self::$is_app_cache || !Store::has($file)){
			$parse_app = self::parse_app($file,false);
			if(self::$is_app_cache) Store::set($file,$parse_app);
		}
		if(self::$is_app_cache) $parse_app = Store::get($file);		
		if(empty($parse_app['apps'])) throw new RuntimeException('undef app');
		$app_result = null;
		$in_app = $match_handle = false;
		$app_index = 0;

		try{
			foreach($parse_app['apps'] as $app){
				switch($app['type']){
					case 'handle':
						$self = new self('_inc_session_=false');
						foreach($app['modules'] as $module) $self->add_module(self::import_instance($module));
						if($self->has_module('flow_handle_begin')) $self->call_module('flow_handle_begin',$self);
						try{
							if($self->handler($app['maps'],$app_index++)->is_pattern()){
								$self->cp(self::execute_var($app['vars']));
								$src = $self->read();
								if($self->has_module('flow_handle_end')) $self->call_module('flow_handle_end',$src,$self);
								print($src);
	
								$in_app = true;
								$match_handle = true;
								if(!$parse_app["handler_multiple"]) exit;
							}
						}catch(Exception $e){
							Log::warn($e);
							if(isset($app['on_error']['status'])) Http::status_header((int)$app['on_error']['status']);
							if(isset($app['on_error']['redirect'])){
								$this->save_exception($e);
								$this->redirect($app['on_error']['redirect']);
							}else if(isset($app['on_error']['template'])){
								if(!($e instanceof Exceptions)) Exceptions::add($e);
								$self->output($app['on_error']['template']);
							}else{
								throw $e;
							}
						}
						break;
					case 'invoke':
						$class_name = isset($app['class']) ? Lib::import($app['class']) : get_class($app_result);
						$ref_class = new ReflectionClass($class_name);

						foreach($app['methods'] as $method){
							$invoke_class = ($ref_class->getMethod($method['method'])->isStatic()) ? $class_name : (isset($app['class']) ? new $class_name() : $app_result);
							$args = array();
							foreach($method['args'] as $arg){
								if($arg['type'] === 'result'){
									$args[] = &$app_result;
								}else{
									$args[] = $arg['value'];
								}
							}
							if(is_object($invoke_class)){
								foreach($app['modules'] as $module) $invoke_class->add_module(self::import_instance($module));
							}
							$app_result = call_user_func_array(array($invoke_class,$method['method']),$args);
							$in_app = true;
						}
						break;
				}
			}
			if(!$match_handle){
				Log::debug("nomatch");
				if($parse_app["nomatch_redirect"] !== null) Http::redirect(App::url($parse_app["nomatch_redirect"]));
				if($parse_app["nomatch_template"] !== null){
					Http::status_header(404);
					$self = new self();
					$self->output($parse_app["nomatch_template"]);
				}
			}
			if(!$in_app) Http::status_header(404);
		}catch(Exception $e){
			if(!($e instanceof Exceptions)) Exceptions::add($e);
		}
		exit;
	}
	/**
	 * application アプリケーションXMLをパースする
	 * 
	 * 基本情報
	 * ========================================================================
	 * <app name="アプリケーションの名前" summary="アプリケーションのサマリ">
	 * 	<description>
	 * 		アプリケーションの説明
	 * 	</description>
	 * </app>
	 * ========================================================================
	 * 
	 * アプリケーションの順次処理を行う場合	
	 * ========================================================================
	 * <app>
	 * 	<invoke class="org.rhaco.net.xml.Feed" method="do_read">
	 * 		<arg value="http://ameblo.jp/nakagawa-shoko/" />
	 * 		<arg value="http://ameblo.jp/kurori1985/" />
	 * 	</invoke>
	 * 	<invoke class="org.rhaco.net.xml.FeedConverter" method="strip_tags" />
	 * 	<invoke method="output" />
	 * </app>
	 * ========================================================================
	 * 
	 * URLマッピングでアプリケーションを作成する
	 * ========================================================================
	 * <app>
	 * 	<handler>
	 * 		<map url="/hello" class="FlowSampleHello" method="hello" template="display.html" summary="ハローワールド" name="hello_world" />
	 * 	</handler>
	 * </app>
	 * ========================================================================
	 * 
	 * @param string $file アプリケーションXMLのファイルパス
	 * @param boolean $get_meta 詳細な情報を取得する
	 * @return string{}
	 */
	static public function parse_app($file,$get_meta=true){
		$apps = array();
		$handler_multiple = false;
		$app_nomatch_redirect = $app_nomatch_template = null;

		if(Tag::setof($tag,Tag::uncomment(File::read($file)),'app')){
			$app_ns = $tag->in_param('ns');
			$app_nomatch_redirect = File::path_slash($tag->in_param('nomatch_redirect'),false,null);
			$app_nomatch_template = File::path_slash($tag->in_param('nomatch_template'),false,null);
			$handler_multiple = ($tag->in_param('multiple',false) === "true");
			$handler_count = 0;
			$invoke_count = 0;

			foreach($tag->in(array('invoke','handler')) as $handler){
				switch(strtolower($handler->name())){
					case 'handler':
						if($handler->in_param(App::mode(),"true") === "true"){
							if($handler->is_param('class')){
								$class = Lib::import($handler->in_param('class'));
								$maps = new Tag('maps');
								$maps->add('class',$handler->in_param('class'));
								$ref = new ReflectionClass($class);
								$url = File::path_slash($handler->in_param('url',strtolower(substr($ref->getName(),0,1)).substr($ref->getName(),1)),false,false);
								$handler->param('url',$url);
								$var = new Tag('var');
								$handler->add($var->add('name','module_url')->add('value',$url));
	
								foreach($ref->getMethods() as $method){
									if($method->isPublic() && is_subclass_of($method->getDeclaringClass()->getName(),__CLASS__)){
										if(!$method->isStatic()){
											$url = ($method->getName() == 'index' && $method->getNumberOfParameters() == 0) ? '' : $method->getName().str_repeat("/(.+)",$method->getNumberOfRequiredParameters());
											for($i=0;$i<=$method->getNumberOfParameters()-$method->getNumberOfRequiredParameters();$i++){
												$map = new Tag('map');
												$map->add('url',$url);
												$map->add('method',$method->getName());
												$maps->add($map);
												$url .= '/(.+)';
											}
										}
									}
								}
								$handler->add($maps);
							}
							$handler_name = (empty($app_ns)) ? str_replace(App::path(),'',$file).$handler_count++ : $app_ns;
							$maps = $modules = $vars = array();
							$handler_url = File::path_slash(App::branch(),false,true).File::path_slash($handler->in_param('url'),false,true);
							$map_index = 0;
							$base_path = File::path_slash($handler->in_param("template_path"),false,false);
	
							foreach($handler->in(array('maps','map','var','module')) as $tag){
								switch(strtolower($tag->name())){
									case 'map':
										$url =  File::path_slash($handler_url.File::path_slash($tag->in_param('url'),false,false),false,false);
										$maps[$url] = self::parse_map($tag,$url,$base_path,$handler_name,null,null,null,$map_index++,$get_meta); break;
									case 'maps':
										$maps_map = $maps_module = array();
										$maps_base_path = File::path_slash($base_path,false,true).File::path_slash($tag->in_param('template_path'),false,false);

										foreach($tag->in(array('map','module')) as $m){
											if($m->name() == 'map'){
												$url = File::path_slash($handler_url.File::path_slash($tag->in_param('url'),false,true).File::path_slash($m->in_param('url'),false,false),false,false);
												$map = self::parse_map($m,$url,$maps_base_path,$handler_name,$tag->in_param('class'),$tag->in_param('secure'),$tag->in_param('update'),$map_index++,$get_meta);
												$maps_map[$url] = $map;
											}else{
												$maps_module[] = self::parse_module($m);
											}
										}
										if(!empty($maps_module)){
											foreach($maps_map as $k => $v) $maps_map[$k]['modules'] = array_merge($maps_map[$k]['modules'],$maps_module);
										}
										$maps = array_merge($maps,$maps_map);
										break;
									case 'var': $vars[] = self::parse_var($tag); break;
									case 'module': $modules[] = self::parse_module($tag); break;
								}
							}
							
							$verify_maps = array();
							foreach($maps as $m){
								if(!empty($m['name'])){
									if(isset($verify_maps[$m['name']])) Exceptions::add(new RuntimeException("name `".$m['name']."` with this map already exists."));
									$verify_maps[$m['name']] = true;
								}
							}
							Exceptions::validation();
	
							$urls = $maps;
							krsort($urls);
							$sort_maps = $surls = array();
							foreach(array_keys($urls) as $url) $surls[$url] = strlen(preg_replace("/[\W]/","",$url));
							arsort($surls);
							krsort($surls);
							foreach(array_keys($surls) as $url) $sort_maps[$url] = $maps[$url];
							$apps[] = array('type'=>'handle'
											,'maps'=>$sort_maps
											,'modules'=>$modules
											,'vars'=>$vars
											,'on_error'=>array('status'=>$handler->in_param('error_status',403)
																,'template'=>$handler->in_param('error_template')
																,'redirect'=>$handler->in_param('error_redirect'))
											);
						}
						break;
					case 'invoke':
						$targets = $methods = $args = $modules = array();
						if($handler->is_param('method')){
							$targets[] = $handler->add('name',$handler->in_param('method'));
						}else{
							$targets = $handler->in_all('method');
						}
						foreach($targets as $method_tag){
							foreach($method_tag->in(array('arg','result')) as $arg) $args[] = array('type'=>$arg->name(),'value'=>$arg->in_param('value',Text::plain($arg->value())));
							$methods[] = array('method'=>$method_tag->in_param('name'),'args'=>((empty($args) && $handler->is_param('class') && $invoke_count > 0) ? array(array('type'=>'result','value'=>null)) : $args));
						}
						foreach($handler->in('module') as $m) $modules[] = self::parse_module($m);
						$apps[] = array('type'=>'invoke','class'=>$handler->in_param('class'),'methods'=>$methods,'modules'=>$modules);
						$invoke_count++;
						break;
				}
			}
		}
		return array("nomatch_redirect"=>$app_nomatch_redirect,"nomatch_template"=>$app_nomatch_template,"handler_multiple"=>$handler_multiple,"apps"=>$apps);
	}
	static private function parse_map(Tag $map_tag,$url,$path,$scope,$base_class,$secure,$update,$map_index,$get_meta){
		$params = $args = $vars = $modules = $meta = array();
		if(!$map_tag->is_param('class')) $map_tag->param('class',$base_class);
		$params['url'] = $url;
		$params['scope'] = $scope;
		$params['map_index'] = $map_index;
		$params['redirect'] = File::path_slash($map_tag->in_param('redirect'),false,false);
		$params['template'] = File::path_slash($map_tag->in_param('template'),false,false);
		$params['secure'] = ($map_tag->in_param('secure',$secure) === 'true');
		$params['update'] = ($map_tag->in_param('update',$update) === 'true');
		if(!empty($params['template']) && !empty($path)) $params['template'] = $path.'/'.$params['template'];

		foreach(array('class','method','name') as $c) $params[$c] = $map_tag->in_param($c);
		foreach($map_tag->in('module') as $t) $modules[] = self::parse_module($t);
		foreach($map_tag->in('var') as $t) $vars[] = self::parse_var($t);
		foreach($map_tag->in('arg') as $a) $args[$a->in_param('name')] = $a->in_param('value',$a->value());
		if($get_meta){
			$meta['summary'] = $map_tag->in_param('summary');
		}
		list($params['vars'],$params['modules'],$params['args'],$params['meta']) = array($vars,$modules,$args,$meta);
		if(!empty($params['class']) && empty($params['method'])) Exceptions::add(new InvalidArgumentException('map `'.$map_tag->plain().'` method not found'));
		if(!empty($params['method']) && empty($params['class'])) Exceptions::add(new InvalidArgumentException('map `'.$map_tag->plain().'` class not found'));
		return $params;
	}
	static private function parse_module(Tag $tag){
		if(!$tag->is_param('class')) throw new RuntimeException('module class not found');
		return $tag->in_param('class');
	}
	static private function import_instance($class_path){
		$class_name = Lib::import($class_path);
		return new $class_name();
	}
	static private function parse_var(Tag $tag){
		if($tag->is_param('class')){
			$var_value = array();
			foreach($tag->in('arg') as $arg) $var_value[] = $arg->in_param('value',Text::plain($arg->value()));
		}else{
			$var_value = $tag->in_param('value',Text::plain($tag->value()));
		}
		return array('name'=>$tag->in_param('name'),'value'=>$var_value,'class'=>$tag->in_param('class'),'method'=>$tag->in_param('method'));
	}
	static private function execute_var($vars){
		$results = array();
		foreach($vars as $var){
			$name = $var['name'];
			$var_value = $var['value'];

			if(isset($var['class'])){
				$r = new ReflectionClass(Lib::import($var['class']));
				$var_value = (empty($var['method'])) ? 
					(empty($var_value) ? $r->newInstance() : $r->newInstance($var_value)) :
					call_user_func_array(array(($r->getMethod($var['method'])->isStatic()) ? $r->getName() : $r->newInstance(),$var['method']),$var_value);
			}
			if(isset($results[$name])){
				if(!is_array($results[$name])) $results[$name] = array($results[$name]);
				$results[$name][] = $var_value;
			}else{
				$results[$name] = $var_value;
			}
		}
		return $results;
	}
}