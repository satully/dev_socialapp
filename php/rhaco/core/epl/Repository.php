<?php
/**
 * repositoryサーバ
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Repository extends Object{
	static private $server_alias = array();
	static private $base_path;
	private $name;
	private $xml;
	private $lasted = 0;
	private $create = false;
	private $names = array();

	protected function __init__(){
		$this->xml = new Tag("repository");
	}
	protected function __add__($package,$name,$updated,$description,$summary=null){
		$description = $this->comment($description);
		if(empty($summary)){
			$summary = (preg_match("/@summary[\s](.+)/",$description,$match)) ? trim($match[1]) : null;
			if(empty($summary)) list($summary) = explode("\n",preg_replace("/@.+/","",$description));
		}		
		$xml = new Tag("package");
		$xml->add("type",$this->name)
					->add("name",empty($name) ? $package : $name)
					->add("path",$package)					
					->add("updated",date("Y-m-d H:i:s",$updated))
					->add("summary",trim($summary))
					->add(Tag::xmltext(trim(preg_replace("/@.+/","",$description))));
		$this->xml->add($xml);
		if($this->lasted < $updated) $this->lasted = $updated;
		
		$tgz_filename = $this->tgz_path($package);
		$type = $this->name;
		Object::C(__CLASS__)->call_module("repository_add_package",$type,$package,$name,$updated,$description,$summary,$tgz_filename);
	}
	private function comment($doc){
		return trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$doc)));
	}
	protected function __del__(){
		$this->end();
	}
	/**
	 * リポジトリ情報のxmlの作成を開始する
	 * @param alnum $name リポジトリ名
	 * @return boolean xmlの作成を行うか
	 */
	public function start($name){
		if(!empty($this->name)) $this->end();
		$this->name = $name;
		$this->names[] = $name;
		$this->xml = new Tag("repository");
		return $this->create;
	}
	/**
	 * リポジトリ情報のxmlを書き出す
	 */
	public function end(){
		if(!empty($this->name) && $this->create){
			$xml_filename = self::path("repository_".$this->name.".xml");
			$this->xml->param("updated",date("Y-m-d H:i:s",($this->lasted > 0) ? $this->lasted : time()));
			File::write($xml_filename,$this->xml->get("UTF-8"));
			if($this->lasted > 0) touch($xml_filename,$this->lasted);
		}
		$this->name = null;
		$this->lasted = 0;
	}
	/**
	 * パッケージ名からファイルパスの作成
	 * @param string $package パッケージ名
	 * @return string
	 */
	public function tgz_path($package){
		return self::path(str_replace(array("/","."),"_",$this->name."_".$package).".tgz");
	}
	/**
	 * ベースパスの設定
	 * @param string $base_path リポジトリ情報のxmlのファイルのパス
	 */
	static public function config_path($base_path){
		if(!empty($base_path)) self::$base_path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$base_path))."/";
	}
	/**
	 * エクスポートする
	 * @param string $path エクスポート先のパス
	 */
	static public function export($path=null){
		if(empty($path)) $path = self::is_remote() ? App::work("repository") : self::path();
		$path = File::absolute(getcwd(),$path);
		$pre = self::$base_path;
		self::config_path($path);

		$repository = new Repository();
		$repository->create = true;
		self::lib($repository);
		self::app($repository);
		Object::C(__CLASS__)->call_module("repository",$repository);
		if($repository instanceof self) $repository->end();

		self::config_path($pre);
	}
	/**
	 * リポジトリサーバの実行
	 */
	static public function handler(){
		Log::disable_display();
		$request = new Request("_inc_session_=false");
		if(strpos($request->args(),"/check") === 0) exit;
		$repository = new Repository();
		self::lib($repository);
		self::app($repository);
		Object::C(__CLASS__)->call_module("repository",$repository);
		
		foreach($repository->names as $type){
			if(preg_match("/^\/".$type."\/download\/(.+)$/",$request->args(),$match)){
				if(self::is_tgz($type,$match[1],$filename)) self::dl($filename);
			}
			if(preg_match("/^\/".$type."\/state\/(.+)$/",$request->args(),$match)){
				if(self::is_tgz($type,$match[1],$filename)) exit;
			}
			if(preg_match("/^\/".$type."\/list$/",$request->args(),$match)){
				print(self::read_xml($type));
				exit;
			}
			if(preg_match("/^\/".$type."\/list\/json$/",$request->args(),$match)){
				if(Tag::setof($tag,self::read_xml($type))) Text::output_jsonp($tag,$request->in_vars("callback"));
			}
		}
		Http::status_header(403);
		exit;
	}
	static private function path($path=null){
		return File::absolute(((empty(self::$base_path)) ? App::work("repository") : self::$base_path),$path);
	}
	static private function is_remote(){
		return (strpos(self::$base_path,"://") !== false);
	}
	static private function is_tgz($type,$package,&$filename){
		$filename = self::path(str_replace(array("/","."),"_",$type."_".$package).".tgz");
		if(self::is_remote()){
			if(Http::is_url($filename)) return true;
			$b = new Http();
			return ($b->do_get($filename)->status() == 200);
		}
		if(is_file($filename)) return true;
	}
	static private function read_xml($type){
		$path = "repository_".$type.".xml";
		return (self::is_remote()) ? Http::read(self::path($path)) : File::read(self::path($path));
	}	
	static private function dl($filename){
		Object::C(__CLASS__)->call_module("repository_download",$filename);
		if(self::is_remote()) Http::redirect($filename);
		Http::attach(new File($filename));
	}
	static private function lib($repository){
		if(!$repository->start("lib")) return;

		foreach(Lib::classes(true) as $class_path => $class){
			$i = new InfoClass($class_path,false);
			$base_dir = Lib::path();
			$search_path = File::absolute($base_dir,str_replace(".","/",$i->package()));
			$target = is_file($search_path.".php") ? $search_path.".php" : (is_file($search_path."/".basename($search_path).".php") ? $search_path : null);
			if($target !== null){
				$tgz_filename = $repository->tgz_path($i->package());
				File::tgz($tgz_filename,$target,$base_dir);
				touch($tgz_filename,File::last_update($target));
				$repository->add($i->package(),$i->name(),File::last_update($i->path()),$i->document());
			}
		}
	}
	static private function app($repository){
		if(!$repository->start("app")) return;

		if(is_dir(App::path("apps"))){
			$list = array();
			foreach(File::dir(App::path("apps"),true) as $dir){
				$bool = true;
				foreach($list as $p){
					if(strpos($dir,$p) === 0){
						$bool = false;
						break;
					}
				}
				if($bool){
					$package = str_replace(array(App::path("apps/"),"/"),array("","."),$dir);
					$info = App::info($dir);
					if(!empty($info["name"])){
						$tgz_filename = $repository->tgz_path($package);
						File::tgz($tgz_filename,$dir);
						touch($tgz_filename,File::last_update($dir));
						$repository->add($package,$info["name"],File::last_update($dir),$info["description"],$info["summary"]);
						$list[] = $dir;
					}
				}
			}
		}
	}
	/**
	 * リポジトリサーバのマッピングファイルを読み込む
	 */
	static public function load_map(){
		$path = App::path("__repository__.xml");
		if(Tag::load($tag,$path,"map")){
			foreach($tag->in("repository") as $rep){
				if($rep->is_param("domain")){
					$url = $rep->in_param("url","http://".$rep->in_param("domain"));
					if(substr($url,-1) === "/") $url = substr($url,0,-1);
					self::$server_alias[$rep->in_param("domain")] = $url;
				}
			}
		}
	}
	/**
	 * 定義済みのrepository serverのaliasを取得
	 * @return string
	 */
	static public function server_alias(){
		return self::$server_alias;
	}
	/**
	 * repository serverからダウンロードして展開する
	 * @param string $repository_name リポジトリ名
	 * @param string $package パッケージ名
	 * @param string $download_path 展開先のパス
	 */
	static public function download($repository_name,$package,$download_path){
		$path_list = explode(".",$package);
		$domain = null;
		if(sizeof($path_list) > 1){
			$domain = $path_list[1].".".$path_list[0];
			unset($path_list[1],$path_list[0]);
		}
		if(strpos($domain,'.') !== false || ctype_upper($domain[0])){
			while(true){
				try{
					$server = self::server_address($domain);
					$http = new Http();
					$uri = str_replace(".","/",$package);
					if($http->do_get($server."/__repository__.php/".$repository_name."/state/".$uri)->status() === 200){
						File::untgz($server."/__repository__.php/".$repository_name."/download/".$uri,$download_path);
						return;
					}
				}catch(InvalidArgumentException $e){}
				if(empty($path_list)) break;
				$domain = array_shift($path_list).".".$domain;
			}
		}
		throw new InvalidArgumentException("package `".$package."` not found");
	}
	/**
	 * リポジトリ情報のxml表現のurl
	 * @param string $repository_name リポジトリ名
	 * @param string $domain ドメイン名
	 * @return string
	 */
	static public function xml_url($repository_name,$domain){
		return self::server_address($domain)."/__repository__.php/".$repository_name."/list";
	}
	/**
	 * リポジトリ情報のjson表現のurl
	 * @param string $repository_name リポジトリ名
	 * @param string $domain ドメイン名
	 * @return string
	 */
	static public function json_url($repository_name,$domain){
		return self::server_address($domain)."/__repository__.php/".$repository_name."/list/json";
	}
	static private function server_address($url){
		$server = $url;
		if(strpos($server,"://") === false) $server = isset(self::$server_alias[$server]) ? self::$server_alias[$server] : "http://".$server;
		if(substr($server,-1) == "/") $server = substr($server,0,-1);

		try{
			$http = new Http();
			if($http->do_get($server."/__repository__.php/check")->status() === 200 && $http->body() == "") return $server;
			if($http->do_get($server."/__repository__.xml")->status() === 200){
				if(Tag::setof($tag,$http->body(),"map")){
					foreach($tag->in("repository") as $rep){
						try{
							if(!$rep->is_param("domain")) return self::server_address($rep->in_param("url"));
						}catch(InvalidArgumentException $e){}
					}
				}
			}
		}catch(InvalidArgumentException $e){}
		throw new InvalidArgumentException("server `".$url."` not found");
	}
}