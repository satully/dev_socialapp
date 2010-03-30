<?php
/**
 * クラスの情報を扱う
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class InfoClass extends Object{
	static protected $__mixin_object__ = "type=string{}";
	static protected $__properties__ = "type=InfoAt{}";	
	static protected $__methods__ = "type=InfoMethod{}";
	static protected $__setup__ = "type=InfoMethod{}";
	static protected $__init__ = "type=InfoMethod";
	static protected $__def__ = "type=InfoAt{}";
	static protected $__updated__ = "type=timestamp";
	static protected $__valid_line__ = "type=number";
	static protected $__tasks__ = "type=string[]";
	static protected $__class_method__ = "type=InfoMethod";
	protected $name; # クラス名
	protected $path; # ファイルパス
	protected $package; # パッケージパス
	protected $module; # モジュールパス
	protected $extends; # 親クラスのパッケージ
	protected $mixin_object = array(); #mixinしているクラス名
	protected $document; #説明
	protected $def = array(); # def定義
	protected $init; # __init__情報
	protected $setup = array(); # __setup_***__情報
	protected $methods = array(); # メソッド情報
	protected $properties = array(); # プロパティ情報
	protected $updated; # 更新時間
	protected $valid_line; # コメントを除いた行数
	protected $author; # 作成者
	protected $tasks = array(); # 残タスク
	protected $class_method; # クラスのdoctestが格納される暗黙的なメソッド情報

	/**
	 * パッケージの最終更新時間
	 * @param string $path パッケージのパス
	 * @return integer
	 */
	static public function last_update($path){
		list($ref,$class,$path) = self::reflection_class($path);
		if(basename(dirname($ref->getFileName())) === $class){
			return File::last_update(dirname($ref->getFileName()));
		}
		return File::last_update($ref->getFileName());
	}
	static private function reflection_class($path){
		if(is_object($path)) $path = get_class($path);
		if(strpos($path,'::') !== false){
			list($package,$path) = explode('::',$path,2);
			Lib::import($package);
			$path = substr($path,($p=strrpos($path,"."))+(($p===false) ? 0 : 1));
		}
		$class = Lib::import($path);
		$ref = new ReflectionClass($class);
		return array($ref,$class,$path);
	}
	protected function __new__($path,$detail=true){
		list($ref,$class,$path) = self::reflection_class($path);
		$parent = $ref->getParentClass();
		if($parent !== false && $parent->getName() !== "stdClass"){
			$i = new self($parent->getName(),false);
			$this->extends($i->fm_package());
		}
		$this->name($ref->getName());
		$this->updated(File::last_update($ref->getFileName()));
		$this->path(str_replace("\\","/",$ref->getFileName()));		

		try{
			$this->package(Lib::package_path($this->path()));
			if($ref->getName() != substr($this->package,($p=strrpos($this->package,"."))+(($p===false) ? 0 : 1))){
				$package_path = str_replace('.','/',$this->package).'/';
				list($module,$null) = explode('.',substr($this->path,strpos($this->path,$package_path) + strlen($package_path)));
				$this->module = str_replace('/','.',$module);
			}
		}catch(RuntimeException $e){}
		if($this->is_path()){
			$class_src = str_replace(array("\r\n","\r"),"\n",File::read($this->path()));
			$this->valid_line(sizeof(explode("\n",preg_replace("/^[\s\t\n\r]*"."/sm","",preg_replace("/\/\*.*?\*\//s","",$class_src)))));
	
			if(preg_match_all('/module_const\((["\'])(.+?)\\1/',$class_src,$match)){				
				foreach($match[2] as $n) $this->def[$this->package().'@'.$n] = new InfoAt($this->package().'@'.$n,$this->package());
			}
			if(preg_match_all("/@const[\s]+(.+)/",$class_src,$match)){
				foreach($match[1] as $m){
					InfoAt::merge($this->def,$m,$this->package(),$this->package().'@');
				}
			}
			if(preg_match_all("/(\/\/|#)[\s]*TODO[\t\040]+(.+)/",$class_src,$todos)){
				foreach($todos[2] as $v) $this->tasks[] = trim($v);
			}
			if($detail){
				$pure_class_src = explode("\n",$class_src);
				foreach($ref->getMethods() as $method){
					$mname = $method->getName();
					if(($mname[0] != '_' || $mname == '__init__' || strpos($mname,"__setup_") === 0)
						&& (!$this->is_methods($mname) && !$this->is_setup($mname))
					){
						$package = $this->package();
						if(empty($package)) $package = $this->name();
						$mobj = new InfoMethod($package,$this->name(),$method);
						if($mobj->is_static() && $mobj->pure_perm() == "public" && strpos($mname,"__setup_") === 0 && substr($mname,-2) === "__"){
							$this->setup($mobj->name(),$mobj);
						}else if($mobj->name() == '__init__'){
							if(!$this->is_init()) $this->init($mobj);
						}else{
							$this->methods($mobj->name(),$mobj);
						}
						if(!$mobj->is_extends()){
							for($i=$method->getStartLine()-1;$i<$method->getEndLine();$i++) $pure_class_src[$i] = "";
						}
					}
				}
				ksort($this->methods);

				$this->class_method = new InfoMethod($this->package(),$this->name(),null);
				foreach(InfoDoctest::get(implode("\n",$pure_class_src),$this->name(),$this->package()) as $k => $v) $this->class_method->test($k,$v);
				
				foreach($ref->getDefaultProperties() as $k => $v){
					if($k == "_mixin_" && !empty($v)){
						foreach(explode(",",$v) as $o){
							$r = new self($o);
							$this->mixin_object[] = ($r->package() == "") ? $r->name() : $r->package();
						}
						break;
					}
				}
				if(is_subclass_of($class,"Flow") && $this->is_init()){
					foreach($this->methods() as $k => $v){
						$this->methods[$k]->context(array_merge($this->init()->context(),$v->context()));
						$this->methods[$k]->request(array_merge($this->init()->request(),$v->request()));
					}
				}			
				if(is_subclass_of($class,"Object")){
					$ex_src = array();
					$search_src = null;
					$default_properties = $ref->getDefaultProperties();
					foreach($ref->getProperties() as $prop){
						if(!$prop->isPrivate()){
							$name = $prop->getName();
	
							if($name[0] != "_" && !$prop->isStatic()){
								$this->properties[$name] = new InfoAt($name,$this->package());
								if($prop->getDocComment() != ""){
									$this->properties[$name]->document(str_replace(array("\r","\n"),"",trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$prop->getDocComment())))));
								}else{
									if($prop->getDeclaringClass()->getName() == $class){
										$search_src = $class_src;
									}else{
										$class_name = $prop->getDeclaringClass()->getName();
										if(!isset($ex_src[$class_name])) $ex_src[$class_name] = File::read($prop->getDeclaringClass()->getFileName());
										$search_src = $ex_src[$class_name];
									}
									if(preg_match("/[\s]+(public|protected)[\s]+\\$".$name.".*;.*#(.+)/",$search_src,$match)){
										$this->properties[$name]->document(trim($match[2]));
									}
								}
								if(isset($default_properties['__'.$name.'__'])) $this->properties[$name]->set_anon($default_properties['__'.$name.'__']);
							}
						}
					}
					unset($search_src);
				}
			}
		}
		$this->document(trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$ref->getDocComment()))));
	}
	protected function __fm_package__(){
		return ((empty($this->package)) ? $this->name() : $this->package()).((empty($this->module) ? '' : '::'.$this->module));
	}
	protected function __set_document__($doc){
		$this->author = (preg_match("/@author[\s](.+)/",$doc,$match)) ? trim($match[1]) : null;
		$this->document = trim(preg_replace("/@.+/","",$doc));
	}
	/**
	 * publicのmethod一覧
	 * @return InfoMethod{}
	 */
	public function public_methods(){
		$list = array();
		foreach($this->methods() as $k => $m){
			if($m->is_public() && !$m->is_static()) $list[$k] = $m;
		}
		return $list;
	}
	/**
	 * 自身に定義されたメソッドの一覧
	 * @return InfoMethod{}
	 */
	public function self_methods(){
		$list = array();
		foreach($this->methods() as $k => $m){
			if(!$m->is_extends() && $m->is_public() && !$m->is_static()) $list[$k] = $m;
		}
		return $list;
	}
	/**
	 * 自身に定義されたすべてのメソッドの一覧
	 * @return InfoMethod{}
	 */
	public function self_methods_all(){
		$list = array();
		foreach($this->methods() as $k => $m){
			if(!$m->is_extends()) $list[$k] = $m;
		}
		return $list;
	}
	/**
	 * 自身に定義されたスタティックメソッドの一覧
	 * @return InfoMethod{}
	 */
	public function self_static_methods(){
		$list = array();
		foreach($this->methods() as $k => $m){
			if(!$m->is_extends() && $m->is_public() && $m->is_static()) $list[$k] = $m;
		}
		return $list;		
	}
	/**
	 * 概要
	 * @return string
	 */
	public function summary(){
		list($line) = explode("\n",trim($this->document));
		return $line;
	}
	/**
	 * ヘルプを表示
	 *
	 * @param string $class クラス名
	 * @param string $method メソッド名
	 * @param boolean $flush 出力するか
	 * @return string
	 */
	final public static function help($class,$method=null,$flush=true){
		$ref = new self($class);
		$doc = "\nHelp in class ".$ref->name()." ";
		$docs = array();
		$tab = "  ";

		if(empty($method)){
			$doc .= ":\n";
			$doc .= $tab.str_replace("\n","\n".$tab,$ref->document())."\n\n";

			if($ref->is_extends()){
				$doc .= $tab."Extends:\n";
				$doc .= $tab.$tab.$ref->extends()."\n\n";
			}
			if($ref->is_mixin_object()){
				$doc .= $tab."Mixin:\n";
				foreach($ref->mixin_object() as $o){
					$doc .= $tab.$tab.$o."\n";
				}
				$doc .= "\n";
			}
			$doc .= $tab."Author:\n";
			$doc .= $tab.$tab.$ref->author()."\n\n";
			$doc .= $tab."Valid line: \n";
			$doc .= $tab.$tab.$ref->valid_line()." lines";
			$doc .= "\n\n";

			$doc .= $tab."Const: \n";
			foreach($ref->def() as $d){
				$doc .= $tab.$tab."[".$d->name()."]".$tab.$d->document()."\n";
			}
			$doc .= "\n";

			$public = $static = $protected = $methods = array();
			foreach($ref->methods() as $name => $m){
				if(!$m->is_extends()){
					if($m->is_public()){
						if($m->is_static()){
							$static[$name] = $m;
						}else{
							$public[$name] = $m;
						}
					}else if($m->is_protected() && !$m->is_final() && !$m->is_static()){
						$protected[$name] = $m;
					}else if(!$m->is_private() && substr($m->name(),0,1) != "_"){
						$methods[$name] = $m;
					}
				}
			}
			$doc .= $tab."Static methods defined here:\n";
			$len = Text::length(array_keys($static));
			foreach($static as $m){
				$doc .= $tab.$tab.str_pad($m->name(),$len).": ".$m->summary()."\n";
			}
			$doc .= "\n\n";

			$doc .= $tab."Methods defined here:\n";
			$len = Text::length(array_keys($public));
			foreach($public as $m){
				$doc .= $tab.$tab.str_pad($m->name(),$len).": ".$m->summary()."\n";
			}
			$doc .= "\n\n";
			
			$doc .= $tab."Methods list you can override:\n";
			$len = Text::length(array_keys($protected));
			foreach($protected as $m){
				$doc .= $tab.$tab.str_pad($m->name(),$len).": ".$m->summary()."\n";
			}
			$doc .= "\n\n";
			
			$doc .= $tab."Methods list:\n";
			foreach($methods as $m){
				$doc .= $tab.$tab.$m->str()."\n";
			}
		}else if($ref->is_methods($method)){
			$m = $ref->in_methods($method);
			$doc .= "in method ".$method.":\n";
			$doc .= $tab.str_replace("\n","\n".$tab,$m->document())."\n\n";
			$doc .= "\n";
			
			if($m->is_author()){
				$doc .= $tab."Author:\n";
				$doc .= $tab.$tab.$m->author()."\n\n";
			}
			$doc .= $tab."Parameter:\n";
			$len = Text::length(array_keys($m->param()));
			foreach($m->param() as $name => $o){
				$doc .= $tab.$tab.str_pad($name,$len)." : [".$o->type()."] ".(($o->require()) ? "" : "(default: ".$o->default().") ").$o->document()."\n";
			}
			$doc .= "\n";
			
			$doc .= $tab."Return:\n";
			$doc .= $tab.$tab.$m->return()."\n\n";

			if($m->is_request()){
				$doc .= $tab."Request:\n";
				$len = Text::length(array_keys($m->request()));
				foreach($m->request() as $name => $d){
					$doc .= $tab.$tab.str_pad($name,$len)." : ".$d."\n";
				}
				$doc .= "\n";
			}
			if($m->is_module()){
				$doc .= $tab."Module Method:\n";
				$len = Text::length(array_keys($m->module()));
				foreach($m->module() as $name => $d){
					$doc .= $tab.$tab.str_pad($name,$len)." : ".$d."\n";
				}
				$doc .= "\n";
			}
		}else{
			throw new InvalidArgumentException($class." ".$method." not found");
		}
		if($flush) print($doc);
		return $doc;
	}
}