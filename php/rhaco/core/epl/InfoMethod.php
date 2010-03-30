<?php
/**
 * メソッド情報を扱う
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class InfoMethod extends Object{
	static protected $__test__ = "type=InfoDoctest{}";
	static protected $__param__ = "type=InfoAt{}";
	static protected $__arg__ = "type=InfoAt{}";	
	static protected $__context__ = "type=InfoAt{}";
	static protected $__request__ = "type=InfoAt{}";
	static protected $__module__ = "type=InfoAt{}";
	static protected $__exception__ = "type=InfoAt{}";
	static protected $__return__ = "type=InfoAt";
	static protected $__output__ = "type=string";
	static protected $__tasks__ = "type=string[]";
	static protected $__perm__ = "type=choice(public,protected,private)";
	static protected $__pure_perm__ = "type=choice(public,protected,private)";
	static protected $__static__ = "type=boolean";
	static protected $__final__ = "type=boolean";
	static protected $__line__ = "type=integer";
	static protected $__extends__ = "type=boolean";
	static private $source = array();
	protected $name; # メソッド名
	protected $document; # 説明
	protected $test = array(); # doctest
	protected $exception = array(); #　発生する例外
	protected $arg = array(); # 定義されているmap arg
	protected $context = array(); # 定義されているコンテキスト
	protected $request = array(); # 定義されているリクエスト
	protected $param = array(); # 定義されている引数
	protected $perm = "private"; # アクセス権限
	protected $pure_perm = "private"; # 実際のアクセス権減
	protected $static = false; # スタティックか
	protected $final = false; # ファイナルか
	protected $author; # 作成者
	protected $return; # 返り値
	protected $output; # 出力方法
	protected $line; # 定義されている行番号
	protected $extends = false; # 親で定義されているものか
	protected $module = array(); # 利用しているモジュール
	protected $tasks = array(); # 残タスク
	private $class_package;

	protected function __new__($class_package,$class_name,ReflectionMethod $method=null){
		if(isset($method)){
			$self_class = $method->getDeclaringClass()->getName();
			$this->name($method->getName());
			$this->pure_perm(($method->isPublic() ? "public" : ($method->isProtected() ? "protected" : "private")));
			$this->perm(($this->name[0] == "_") ? "private" : ($method->isPublic() ? "public" : ($method->isProtected() ? "protected" : "private")));
			$this->static($method->isStatic());
			$this->final($method->isFinal());
			$this->extends($class_name !== $self_class);
			$this->class_package = $class_package;

			foreach($method->getParameters() as $p){
				$this->param[$p->getName()] = new InfoAt($p->getName(),$this->class_package);
				$this->param[$p->getName()]->reference($p->isPassedByReference());
				if($p->isDefaultValueAvailable()) $this->param[$p->getName()]->default($p->getDefaultValue());
			}
			$class_path = $method->getDeclaringClass()->getFileName();
			if($class_path !== false){
				$this->line($method->getStartLine());
				if(!isset($source[$class_path])) $source[$class_path] = file($class_path);
				$msrc = implode(array_slice($source[$class_path],$method->getStartLine(),($method->getEndLine() - $method->getStartLine())));
	
				if(is_subclass_of($self_class,'Flow') || $self_class == 'Flow'){
					if(preg_match_all('/\$this->in_vars\((["\'])(.+?)\\1/',$msrc,$match)){				
						foreach($match[2] as $n){
							$this->request[$n] = new InfoAt($n,$this->class_package);					
							$this->context[$n] = new InfoAt($n,$this->class_package);
						}
					}
					if(preg_match_all('/\$this->rm_vars\((["\'])(.+?)\\1/',$msrc,$match)){
						foreach($match[2] as $n) $this->rm_context($n);
					}
					if(preg_match_all('/\$this->vars\((["\'])(.+?)\\1/',$msrc,$match)){				
						foreach($match[2] as $n) $this->context[$n] = new InfoAt($n,$this->class_package);
					}
					if(preg_match_all('/\$this->(map_arg|redirect_by_map)\((["\'])(.+?)\\2/',$msrc,$match)){					
						foreach($match[3] as $n) $this->arg[$n] = new InfoAt($n,$this->class_package);
					}
				}
				if(preg_match_all('/throw new[\s]+([\w]+)/',$msrc,$match)){
					foreach($match[1] as $n){
						$p = new InfoAt();
						$p->set($n,$this->class_package);
						$this->exception[$p->type()] = $p;
					}
				}
				if(preg_match_all("/->call_module\(([\"'])(.+?)\\1/",$msrc,$match)){
					foreach($match[2] as $v) $this->module[$v] = new InfoAt($v,$this->class_package);
				}
				$this->test = InfoDoctest::get($msrc,$self_class,$this->class_package,$method->getStartLine(),$this->name());

				if(preg_match_all("/(\/\/|#)[\s]*TODO[\t\040]+(.+)/",$msrc,$todos)){
					foreach($todos[2] as $v) $this->tasks[] = trim($v);
				}
			}
			$this->document(trim(preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$method->getDocComment()))));
		}
	}
	protected function __set_document__($doc){
		$this->document = (strpos($doc,"\n") === false && preg_match("/@see[\s](.+)/",$doc)) ? trim($doc) : trim(preg_replace("/@.+/","",$doc));
		$this->author = (preg_match("/@author[\s](.+)/",$doc,$match)) ? trim($match[1]) : null;	

		if(preg_match_all("/@request[\s]+(.+)/",$doc,$match)){
			foreach($match[1] as $m){
				InfoAt::merge($this->request,$m,$this->class_package);
				InfoAt::merge($this->context,$m,$this->class_package);
			}
		}
		if(preg_match_all("/@context[\s]+(.+)/",$doc,$match)){
			foreach($match[1] as $m) InfoAt::merge($this->context,$m,$this->class_package);
		}
		if(preg_match("/@return[\s](.+)/",$doc,$match)){
			$r = new InfoAt();
			$this->return = $r->set(trim($match[1]),$this->class_package);
		}
		if(preg_match("/@output[\s](.+)/",$doc,$match)){
			$this->output = trim($match[1]);
		}
		if(preg_match_all("/@param[\s]+(.+)/",$doc,$match)){
			foreach($match[1] as $m) InfoAt::merge($this->param,$m,$this->class_package);
		}
		if(preg_match_all("/@module[\s]+(.+)/",$doc,$match)){
			foreach($match[1] as $m) InfoAt::merge($this->module,$m,$this->class_package);
		}
		if(preg_match_all("/@arg[\s]+(.+)/",$doc,$match)){
			foreach($match[1] as $m) InfoAt::merge($this->arg,$m,$this->class_package);
		}
	}
	/**
	 * publicメソッドか
	 * @return boolean
	 */
	public function is_public(){
		return ($this->perm === "public");
	}
	/**
	 * protectedメソッドか
	 * @return boolean
	 */
	public function is_protected(){
		return ($this->perm === "protected");
	}
	/**
	 * privateメソッドか
	 * @return boolean
	 */
	public function is_private(){
		return ($this->perm === "private");
	}
	protected function __str__(){
		return (($this->final) ? "final " : "").(($this->static) ? "static " : "").$this->perm()." ".$this->name();
	}
	protected function __fm_name__(){
		$param = array();
		foreach($this->param() as $name => $o){
			$param[] = $o->fm_type()." ".($o->is_reference() ? '&' : '').$name.(($o->require()) ? "" : "[=".$o->default()."] ");
		}
		return ((($this->final) ? "final " : "").(($this->static) ? "static " : "").$this->perm()." ".$this->name())."(".implode(",",$param).")";
	}
	/**
	 * 概要
	 * @return string
	 */
	public function summary(){
		list($line) = explode("\n",trim($this->document));
		return $line;
	}
}