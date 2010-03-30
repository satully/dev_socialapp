<?php
/**
 * コメントから情報を抽出する
 * @author tokushima
 */
class InfoAt extends Object{
	static protected $__array__ = 'type=integer';
	static protected $__name__ = 'type=string';
	static protected $__choices__ = 'type=string[]';
	static protected $__document__ = 'type=text';
	static protected $__module__ = 'type=string';
	static protected $__default__ = 'type=mixed';
	static protected $__require__ = 'type=boolean';
	static protected $__reference__ = 'type=boolean';

	protected $type = 'mixed'; # 型
	protected $choices = array(); # 選択型の場合の選択値
	protected $name; # 変数名
	protected $document; # 説明
	protected $class; # 定義されているクラス名
	protected $reference; # 参照型か

	protected $array = 0; # 型が配列か (0:配列ではない,1:配列,2:連想配列)
	protected $package; # 定義されているパッケージ名
	protected $module; # 定義されているモジュール名

	protected $default = null; # デフォルト値
	protected $require = true; # 必須か

	protected function __new__($name=null,$class=null){
		$this->name = $name;
		$this->class = $class;
	}
	/**
	 * $objectsに同名のキーがあればマージし無ければ追加する
	 * @param self{} $objects 対象の連想配列
	 * @param string $doc 値の情報を表す文字列
	 * @param string $class 定義されているクラス名
	 * @param string $prefix 名称につく接頭辞
	 */
	static public function merge(array &$objects,$doc,$class,$prefix=null){
		$self = new self();
		$self->set($doc,$class);
		$name = $prefix.$self->name();
	
		if($self->is_name() && isset($objects[$name])){
			$objects[$name]->type($self->type());
			$objects[$name]->array($self->array());
			$objects[$name]->module($self->module());
			if($self->is_document()) $objects[$name]->document($self->document());
		}
	}
	/**
	 * 文字列をパースして値をセットする
	 * @param string $m 値の情報を表す文字列
	 * @param string $class 定義されているクラス
	 * @return self
	 */
	public function set($m,$class){
		$this->class($class);
		$t = $n = $d = '';
		$ds = preg_split("/[\s]+/",trim(preg_replace("/^(\\\$this|self)([^\w]*)$/",$class."\\2",$m)),2);

		if(!empty($ds[0])){
			if(sizeof($ds) == 2){
				if($ds[0][0] == '$'){
					$n = substr($ds[0],1);
					if(isset($ds[1])) $d = $ds[1];
				}else if(!empty($ds[1]) && $ds[1][0] == '$'){
					$vs = preg_split("/[\s]+/",$ds[1],2);
					$t = $ds[0];
					$n = substr($vs[0],1);
					if(isset($vs[1])) $d = $vs[1];
				}else if($ds[0][0] != '$' && preg_match("/^[\w\[\]\{\}]+$/",$ds[0])){
					if($this->check_type($ds[0])){
						$t = $ds[0];
						if(isset($ds[1])) $d = $ds[1];
					}else{
						$d = $ds[0];
					}
				}
			}else if($ds[0][0] == '$'){
				$n = substr($ds[0],1);
			}else if($this->check_type($ds[0])){
				$t = $ds[0];				
			}else{
				$d = $ds[0];
			}
		}
		$this->type($t);
		$this->name($n);
		$this->document($d);
		return $this;
		/***
			#all
			$self = new self();
			$self->set('Http $variable comment comment ','DummyClass');
			eq('variable',$self->name());
			eq('Http',$self->type());
			eq('comment comment',$self->document());
		*/
		/***
			#type_name
			$self = new self();
			$self->set('Http $variable','DummyClass');
			eq('variable',$self->name());
			eq('Http',$self->type());
			eq('',$self->document());
		*/
		/***
			#name_doc
			$self = new self();
			$self->set('$variable comment comment ','DummyClass');
			eq('variable',$self->name());
			eq('mixed',$self->type());
			eq('comment comment',$self->document());
		*/
		/***
			#type_doc
			$self = new self();
			$self->set('Http commentcomment','DummyClass');
			eq('',$self->name());
			eq('Http',$self->type());
			eq('commentcomment',$self->document());
		*/
		/***
			#name_only
			$self = new self();
			$self->set('$variable','DummyClass');
			eq('variable',$self->name());
			eq('mixed',$self->type());
			eq('',$self->document());
		 */
		/***
			#doc_only
			$self = new self();
			$self->set('commentcomment','DummyClass');
			eq('',$self->name());
			eq('mixed',$self->type());
			eq('commentcomment',$self->document());
		*/
		/***
			#type_only
			$self = new self();
			$self->set('Http','DummyClass');
			eq('',$self->name());
			eq('Http',$self->type());
			eq('',$self->document());
		*/
		/***
			#type_only_array
			$self = new self();
			$self->set('Http[]','DummyClass');
			eq('',$self->name());
			eq('Http',$self->type());
			eq(1,$self->array());
			eq('',$self->document());
		*/
		/***
			#type_self
			$self = new self();
			$self->set('self','InfoAt');
			eq('',$self->name());
			eq('InfoAt',$self->type());
			eq('',$self->document());
		*/
		/***
			#type_self_array
			$self = new self();
			$self->set('self{}','InfoAt');
			eq('',$self->name());
			eq('InfoAt',$self->type());
			eq('',$self->document());
		*/
	}
	/**
	 * アノテーションを適用する
	 * @param string $dict アノテーション
	 * @return self
	 */
	public function set_anon($dict){
		$anon = Text::dict($dict);
		if(isset($anon['type'])) $this->type($anon['type']);
		return $this;
	}
	static private function parse_type($type){
		if(empty($type)) return array('type'=>'mixed','package'=>null,'module'=>null,'array'=>0);

		$array = 0;
		$module = $package = null;
		switch(substr($type,-2)){
			case '[]': $array = 1; break;
			case '{}': $array = 2; break;
			default: $array = 0;
		}
		if($array != 0) $type = substr($type,0,-2);
		try{
			list($package,$module) = Lib::module_path($type);
			$type = $package;
		}catch(Exception $e){}
		return array('type'=>$type,'package'=>$package,'module'=>$module,'array'=>$array);
	}
	protected function __set_default__($d){
		$this->default = var_export($d,true);
		$this->require = false;
	}
	protected function __set_type__($t){
		$p = self::parse_type(preg_replace("/^(\\\$this|self)([^\w]*)$/",$this->class()."\\2",$t));
		if(preg_match("/^choice\((.+?)\)/",$p['type'],$m)){
			$p['type'] = "choice";
			$this->choices = explode(",",$m[1]);
		}
		$this->type = $p['type'];
		$this->array = $p['array'];
		$this->module = $p['module'];
	}
	protected function __fm_type__(){
		return $this->type.(empty($this->choices) ? '' : '('.implode(',',$this->choices).')').((empty($this->module) ? '' : '::'.$this->module)).(($this->array == 1) ? '[]' : (($this->array == 2) ? '{}' : ''));
	}
	protected function __fm_package__(){
		return (empty($this->package) ? $this->type : $this->package).((empty($this->module) ? '' : '::'.$this->module));
	}
	private function check_type($type){
		switch(substr($type,-2)){
			case "[]":
			case "{}":
				$type = substr($type,0,-2);
		}
		switch($type){
			case "string":
			case "text":
			case "number":
			case "serial":
			case "integer":
			case "boolean":
			case "timestamp":
			case "date":
			case "time":
			case "strdate":
			case "intdate":
			case "email":
			case "alnum":
			case "mixed":
				return true;
		}
		if(class_exists($type)) return true;
		if(preg_match("/^choice\((.+?)\)/",$type)) return true;
		return false;
	}
}