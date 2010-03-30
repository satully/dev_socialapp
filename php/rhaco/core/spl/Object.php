<?php
/**
 * 基底クラス
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Object extends stdClass{
	static private $_anon_ = array();
	static private $_static_modules_ = array();
	private $_objects_ = array();
	private $_modules_ = array();
	private $_props_ = array();	
	private $_static_ = false;
	private $_init_ = true;
	protected $_class_;
	protected $_mixin_;

	/**
	 * モジュールがあるか
	 * @param string $method
	 * @return boolean
	 */
	final protected function has_module($method){
		foreach((($this->_static_) ? (isset(self::$_static_modules_[$this->_class_]) ? self::$_static_modules_[$this->_class_] : array()) : $this->_modules_) as $obj){
			if(method_exists($obj,$method)) return true;
		}
		return false;
	}
	/**
	 * モジュールの実行
	 * @param string $method
	 * @param mixed $p 0..9
	 * @return mixed
	 */
	final protected function call_module($method,&$p0=null,&$p1=null,&$p2=null,&$p3=null,&$p4=null,&$p5=null,&$p6=null,&$p7=null,&$p8=null,&$p9=null){
		$result = null;
		if($this->has_module($method)){
			foreach((($this->_static_) ? self::$_static_modules_[$this->_class_] : $this->_modules_) as $obj){
				if(method_exists($obj,$method)) $result = call_user_func_array(array($obj,$method),array(&$p0,&$p1,&$p2,&$p3,&$p4,&$p5,&$p6,&$p7,&$p8,&$p9));
			}
		}
		return $result;
	}
	/**
	 * モジュールを追加する
	 * 
	 * @param object $obj モジュールに追加するインスタンス
	 * @param boolean $recursive すべてのmixinにも適用するか (インスタンスとして呼ばれた場合）
	 * @param boolean $only_modules $objのmoduleコピーか、falseなら$objをmoduleにする
	 */
	final public function add_module($obj,$recursive=false,$only_modules=false){
		if(!is_object($obj)) throw new InvalidArgumentException('invalid argument');
		if($this->_static_){
			self::$_static_modules_[$this->_class_][] = $obj;
		}else{
			if($only_modules){
				foreach($obj->_modules_ as $m) $this->add_module($m,$recursive);
			}else{
				if(get_class($this) === get_class($obj)) return;
				$this->_modules_[] = clone($obj);
				if($recursive){
					foreach($this->_objects_ as $mixin_obj){
						if($mixin_obj instanceof self) $mixin_obj->add_module($obj);
					}
				}
			}
		}
		return $this;
	}	/**
	 * ハッシュとしての値を返す
	 * @return array
	 */
	final public function hash(){
		$args = func_get_args();
		if(method_exists($this,'__hash__')) return call_user_func_array(array($this,'__hash__'),$args);
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false && $this->a($name,'hash') !== false) $result[$name] = $this->{'ha_'.$name}();
		}
		return $result;
		/***
			$name1 = create_class('
				protected $aaa = "hoge";
				protected $bbb = 1;
				protected $ccc = 123;
			');
			$obj1 = new $name1();
			eq(array("aaa"=>"hoge","bbb"=>"1","ccc"=>"123"),$obj1->hash());

			$name2 = create_class('
				static protected $__aaa__ = "type=serial,hash=false";
				static protected $__bbb__ = "type=number";

				protected function __ha_ccc__(){
					return "[".$this->ccc."]";
				}
			',$name1);
			$obj2 = new $name2();
			eq(array("bbb"=>1,"ccc"=>"[123]"),$obj2->hash());
		*/
	}
	/**
	 * 加算
	 * @param mixed $arg 加算する値
	 * @return $this
	 */
	final public function add($arg){
		$args = func_get_args();
		if(method_exists($this,'__add__')) call_user_func_array(array($this,'__add__'),$args);
		return $this;
		/***
			$name1 = create_class('
				public $aaa;
				protected function __add__($arg){
					if($arg instanceof self){
						$this->aaa .= $arg->aaa();
					}
				}
			');
			$obj1 = new $name1("aaa=hoge");
			$obj2 = new $name1("aaa=fuga");
			eq("hoge",$obj1->aaa());
			eq("hogefuga",$obj1->add($obj2)->aaa());
		*/
	}
	/**
	 * 減算
	 * @param mixed $arg 減算する値
	 * @return $this
	 */
	final public function sub($arg){
		$args = func_get_args();
		if(method_exists($this,'__sub__')) call_user_func_array(array($this,'__sub__'),$args);
		return $this;
		/***
			$name1 = create_class('
				public $aaa;
				protected function __sub__($arg){
					if($arg instanceof self){
						$this->aaa = str_replace($arg->aaa(),"",$this->aaa);
					}
				}
			');
			$obj1 = new $name1("aaa=hogefuga");
			$obj2 = new $name1("aaa=fuga");
			eq("hogefuga",$obj1->aaa());
			eq("hoge",$obj1->sub($obj2)->aaa());
		*/
	}
	/**
	 * 乗算
	 * @param mixed $arg 乗算する値
	 * @return $this
	 */
	final public function mul($arg){
		$args = func_get_args();
		if(method_exists($this,'__mul__')) call_user_func_array(array($this,'__mul__'),$args);
		return $this;
		/***
			$name1 = create_class('
				public $aaa;
				protected function __mul__($arg){
					if($arg instanceof self){
						$this->aaa .= $arg->aaa();
					}
				}
			');
			$obj1 = new $name1("aaa=hoge");
			$obj2 = new $name1("aaa=fuga");
			eq("hoge",$obj1->aaa());
			eq("hogefuga",$obj1->mul($obj2)->aaa());
		*/
	}
	/**
	 * 除算
	 * @param mixed $arg 除算する値
	 * @return $this
	 */
	final public function div($arg){
		$args = func_get_args();
		if(method_exists($this,'__div__')) call_user_func_array(array($this,'__div__'),$args);
		return $this;
		/***
			$name1 = create_class('
				public $aaa;
				protected function __div__($arg){
					if($arg instanceof self){
						$this->aaa = str_replace($arg->aaa(),"",$this->aaa);
					}
				}
			');
			$obj1 = new $name1("aaa=hogefuga");
			$obj2 = new $name1("aaa=fuga");
			eq("hogefuga",$obj1->aaa());
			eq("hoge",$obj1->div($obj2)->aaa());
		*/
	}
	/**
	 * 値をコピーする
	 * @param Object $arg コピーする値
	 * @return $this
	 */
	final public function cp($arg){
		$args = func_get_args();
		if(method_exists($this,'__cp__')){
			call_user_func_array(array($this,'__cp__'),$args);
		}else if(isset($args[0])){
			$vars = $this->prop_values();
			if($args[0] instanceof self){
				foreach($args[0]->prop_values() as $name => $value){
					if(array_key_exists($name,$vars)) $this->{$name}($value);
				}
			}else if(is_array($args[0])){
				foreach($args[0] as $name => $value){
					if(array_key_exists($name,$vars)) $this->{$name}($value);
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
		return $this;
		/***
			$name1 = create_class('public $aaa;');
			$name2 = create_class('public $aaa;');
			$name3 = create_class('public $ccc;');

			$obj1 = new $name1();
			$obj2 = new $name2("aaa=hoge");
			$obj3 = new $name3("ccc=fuga");

			eq("hoge",$obj1->cp($obj2)->aaa());
			eq("hoge",$obj1->cp($obj3)->aaa());

			$obj1 = new $name1();
			eq("hoge",$obj1->cp(array("aaa"=>"hoge"))->aaa());
		*/
	}
	/**
	 * objectをmixinさせる
	 * @param object $object mixinさせるインスタンス
	 * @return $this
	 */
	final public function add_object($object){
		$object = (is_object($object)) ? clone($object) : new $object;
		$name = get_class($object);
		if(get_class($object) === $this->_class_) throw new InvalidArgumentException("Object not permitted");
		$this->_objects_ = array_reverse(array_merge(array_reverse($this->_objects_,true),array($name=>$object)),true);
		return $this;
		/***
			$name1 = create_class('
				public $aaa = "AAA";
				public function xxx(){
					return "xxx";
				}
			');
			$name2 = create_class('
				public $bbb = "BBB";
				protected $ccc = "CCC";
				public function zzz(){
					return "zzz";
				}
			');
			$aa = new $name1();
			eq("xxx",$aa->xxx());
			try{
				$aa->zzz();
				fail();
			}catch(Exception $e){
				success();
			}
			$aa->add_object(new $name2());
			eq("zzz",$aa->zzz());

			$name3 = create_class('protected $_mixin_ = "'.$name2.'";',$name2);
			$obj3 = new $name3();
			eq("BBB",$obj3->bbb());
			eq("CCC",$obj3->ccc());
			eq("zzz",$obj3->zzz());
			eq(true,($obj3 instanceof $name3));

			$name4 = create_class('
				public $eee = "EEE";
				protected $fff = "FFF";
				private $ggg = "GGG";
				public $hhh = "hhh";
				public function hhh(){
					return "HHH";
				}
			',"stdClass");
			$obj4 = new $name1;
			$obj4->add_object(new $name4);
			eq("AAA",$obj4->aaa());

			try{
				$obj4->eee();
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj4->fff();
				fail();
			}catch(Exception $e){
				success();
			}

			try{
				$obj4->ggg();
				fail();
			}catch(ErrorException $e){
				success();
			}
			eq("HHH",$obj4->hhh());

			$obj2 = new $name2();
			try{
				$obj2->add_object($obj2);
				fail();
			}catch(LogicException $e){
				success();
			}
		 */
	}
	public function __set($name,$value){
		if($name[0] == "_"){
			$this->{$name} = $value;
		}else if(isset($this->{$name})){
			$this->{$name} = $this->__set__(array($value),$this->_prop_a_($name));
		}else if(!in_array($name,$this->_props_)){
			$this->{$name} = $value;
			$this->_props_[] = $name;
		}
	}
	public function __get($name){
		return $this->__get__($this->{$name},$this->_prop_a_($name));
	}
	final public function __call($method,$args){
		if(in_array($method,$this->_props_)){
			$param = $this->_prop_a_($method);
			if(empty($args)) return ($this->_call_attr_('get',$method,$args,$result)) ? $result : $this->__get__($this->{$method},$param);
			if($this->_call_attr_('set',$method,$args,$result)) return $result;
			switch($param->attr){
				case 'a':
					$args = (is_array($args[0])) ? $args[0] : array($args[0]);
					foreach($args as $v) $this->{$method}[] = $this->__set__(array($v),$param);
					break;
				case 'h':
					$args = (sizeof($args) === 2) ? array($args[0]=>$args[1]) : (is_array($args[0]) ? $args[0] : array((($args[0] instanceof self) ? $args[0]->str() : $args[0])=>$args[0]));
					foreach($args as $k => $v) $this->{$method}[$k] = $this->__set__(array($v),$param);
					break;
				default:
					$this->{$method} = $this->__set__($args,$param);
			}
			return $this->{$method};
		}
		if(!empty($this->_objects_)){
			foreach($this->_objects_ as $mixin_obj){
				try{
					$result = call_user_func_array(array($mixin_obj,$method),$args);
					if(method_exists($this,'__mixin_'.$method.'__')) call_user_func_array(array($this,'__mixin_'.$method.'__'),$args);
					return $result;
				}catch(ErrorException $e){}
			}
		}
		if(preg_match("/^([a-z]+)_([a-zA-Z].*)$/",$method,$match)){
			list(,$call,$name) = $match;
			$call_method = '__'.$call.'__';
			if(method_exists($this,$call_method)){
				if(in_array($name,$this->_props_)){
					$param = $this->_prop_a_($name);
					if($this->_call_attr_($call,$name,$args,$result)) return $result;
					return call_user_func_array(array($this,$call_method),array($args,$param));
				}
			}
		}
		throw new ErrorException($this->_class_."::".$method." method not found");
		/***
			$class1 = create_class('
				static protected $__aaa__ = "type=number";
				static protected $__bbb__ = "type=number[]";
				static protected $__ccc__ = "type=string{}";
				static protected $__eee__ = "type=timestamp";
				static protected $__fff__ = "type=string,column=Acol,table=BTbl";
				static protected $__ggg__ = "type=string,set=false";
				static protected $__hhh__ = "type=boolean";
				public $aaa;
				public $bbb;
				public $ccc;
				public $ddd;
				public $eee;
				public $fff;
				protected $ggg = "hoge";
				public $hhh;

				protected function __set_ddd__($a,$b){
					$this->ddd = $a.$b;
				}
				public function nextDay(){
					return date("Y/m/d H:i:s",$this->eee + 86400);
				}
				protected function __cn__($args,$param){
					if(!isset($param->column) || !isset($param->table)) throw new Exception();
					return array($param->table,$param->column);
				}
			');
			$hoge = new $class1();
			eq(null,$hoge->aaa());
			eq(false,$hoge->is_aaa());
			$hoge->aaa("123");
			eq(123,$hoge->aaa());
			eq(true,$hoge->is_aaa());
			eq(array(123),$hoge->ar_aaa());
			$hoge->rm_aaa();
			eq(false,$hoge->is_aaa());
			eq(null,$hoge->aaa());

			eq(array(),$hoge->bbb());
			$hoge->bbb("123");
			eq(array(123),$hoge->bbb());
			$hoge->bbb(456);
			eq(array(123,456),$hoge->bbb());
			eq(456,$hoge->in_bbb(1));
			eq("hoge",$hoge->in_bbb(5,"hoge"));
			$hoge->bbb(789);
			$hoge->bbb(10);
			eq(array(123,456,789,10),$hoge->bbb());
			eq(array(1=>456,2=>789),$hoge->ar_bbb(1,2));
			eq(array(1=>456,2=>789,3=>10),$hoge->ar_bbb(1));
			$hoge->rm_bbb();
			eq(array(),$hoge->bbb());

			eq(array(),$hoge->ccc());
			eq(false,$hoge->is_ccc());
			$hoge->ccc("AaA");
			eq(array("AaA"=>"AaA"),$hoge->ccc());
			eq(true,$hoge->is_ccc());
			eq(true,$hoge->is_ccc("AaA"));
			eq(false,$hoge->is_ccc("bbb"));
			$hoge->ccc("bbb");
			eq(array("AaA"=>"AaA","bbb"=>"bbb"),$hoge->ccc());
			$hoge->ccc(123);
			eq(array("AaA"=>"AaA","bbb"=>"bbb","123"=>"123"),$hoge->ccc());
			$hoge->rm_ccc("bbb");
			eq(array("AaA"=>"AaA","123"=>"123"),$hoge->ccc());
			$hoge->ccc("ddd");
			eq(array("AaA"=>"AaA","123"=>"123","ddd"=>"ddd"),$hoge->ccc());
			eq(array("123"=>"123"),$hoge->ar_ccc(1,1));
			$hoge->rm_ccc("AaA","ddd");
			eq(array("123"=>"123"),$hoge->ccc());
			$hoge->rm_ccc();
			eq(array(),$hoge->ccc());
			$hoge->ccc("abc","def");
			eq(array("abc"=>"def"),$hoge->ccc());

			eq(null,$hoge->ddd());
			$hoge->ddd("hoge","fuga");
			eq("hogefuga",$hoge->ddd());

			$hoge->eee("1976/10/04");
			eq("1976/10/04",date("Y/m/d",$hoge->eee()));
			eq("1976/10/05 00:00:00",$hoge->nextDay());

			try{
				$hoge->eee("ABC");
				eq(false,$hoge->eee());
			}catch(InvalidArgumentException $e){
				success();
			}
			try{
				$hoge->eee(null);
				success();
			}catch(InvalidArgumentException $e){
				fail();
			}
			eq(array("BTbl","Acol"),$hoge->cn_fff());

			eq("hoge",$hoge->ggg());
			try{
				$hoge->ggg("fuga");
				fail();
			}catch(Exception $e){
				success();
			}
			$hoge->hhh(true);
			eq(true,$hoge->hhh());
			$hoge->hhh(false);
			eq(false,$hoge->hhh());
			try{
				$hoge->hhh("hoge");
				fail();
			}catch(Exception $e){
				success();
			}
		*/
		/***
			$name1 = create_class('
				static protected $__aa__ = "type=mixed";
				static protected $__aaa__ = "type=mixed";
				static protected $__bb__ = "type=string";
				static protected $__cc__ = "type=serial";
				static protected $__dd__ = "type=number";
				static protected $__ee__ = "type=boolean";
				static protected $__ff__ = "type=timestamp";
				static protected $__gg__ = "type=time";
				static protected $__hh__ = "type=choice(abc,def)";
				static protected $__ii__ = "type=string{}";
				static protected $__jj__ = "type=string[]";
				static protected $__kk__ = "type=email";
				static protected $__ll__ = "type=strdate";
				static protected $__mm__ = "type=alnum";
				static protected $__nn__ = "type=intdate";
				static protected $__oo__ = "type=integer";
				static protected $__pp__ = "type=text";
				protected $aa;
				protected $aaa;
				protected $bb;
				protected $cc;
				protected $dd;
				protected $ee;
				protected $ff;
				protected $gg;
				protected $hh;
				protected $ii;
				protected $jj;
				protected $kk;
				protected $ll;
				protected $mm;
				protected $nn;
				protected $oo;
				protected $pp;
				
				protected function __set_aaa__($value){
					$this->aaa = "ABC".$value;
				}
				protected function __get_aaa__(){
					return "[".$this->aaa."]";
				}
			');
			$obj = new $name1();
			eq(false,$obj->is_aa());
			$obj->aa("hoge");
			eq(true,$obj->is_aa());
			$obj->aa("");
			eq(null,$obj->aa());

			eq(false,$obj->is_aaa());
			$obj->aaa("hoge");
			eq(true,$obj->is_aaa());
			eq("[ABChoge]",$obj->aaa());

			eq(false,$obj->is_bb());
			$obj->bb("hoge");
			eq("hoge",$obj->bb());
			eq(true,$obj->is_bb());
			$obj->bb("");
			eq(false,$obj->is_bb());			
			$obj->bb("");
			eq("",$obj->bb());
			$obj->bb(null);
			eq(null,$obj->bb());

			eq(false,$obj->is_pp());
			$obj->pp("hoge");
			eq("hoge",$obj->pp());
			eq(true,$obj->is_pp());
			$obj->pp("");
			eq(false,$obj->is_pp());			
			$obj->pp("");
			eq("",$obj->pp());
			$obj->pp(null);
			eq(null,$obj->pp());

			eq(false,$obj->is_cc());
			$obj->cc(1);
			eq(true,$obj->is_cc());
			$obj->cc(0);
			eq(true,$obj->is_cc());
			$obj->cc("");
			eq(null,$obj->cc());

			eq(false,$obj->is_dd());
			$obj->dd(1);
			eq(true,$obj->is_dd());
			$obj->dd(0);
			eq(true,$obj->is_dd());

			eq(false,$obj->is_ee());
			$obj->ee(true);
			eq(true,$obj->is_ee());
			$obj->ee(false);
			eq(false,$obj->is_ee());

			eq(false,$obj->is_ff());
			$obj->ff("2009/04/27 12:00:00");
			eq(true,$obj->is_ff());

			eq(false,$obj->is_gg());
			$obj->gg("12:00:00");
			eq(true,$obj->is_gg());
			eq(43200,$obj->gg());
			$obj->gg("12:00");
			eq(720,$obj->gg());
			eq("12:00",$obj->fm_gg());

			$obj->gg("12:00.345");
			eq(720.345,$obj->gg());
			eq("12:00.345",$obj->fm_gg());
			try{
				$obj->gg("1:2:3:4");
				fail();
			}catch(Exception $e){
				success();
			}
			$obj->gg("20時40分50秒");
			eq("20:40:50",$obj->fm_gg());

			eq(false,$obj->is_hh());
			$obj->hh("abc");
			eq(true,$obj->is_hh());

			eq(false,$obj->is_ii());
			eq(false,$obj->is_ii("hoge"));
			$obj->ii("hoge","abc");
			eq(true,$obj->is_ii("hoge"));
			$obj->ii(array("A"=>"def","B"=>"ghi"));
			eq(true,$obj->is_ii("A"));
			eq(true,$obj->is_ii("B"));
			eq("ghi",$obj->in_ii("B"));

			eq(false,$obj->is_jj());
			eq(false,$obj->is_jj(0));
			$obj->jj("abc");
			eq(true,$obj->is_jj(0));
			$obj->jj(array("def","ghi"));
			eq("def",$obj->in_jj(1));
			eq(true,$obj->is_jj(1));
			eq(true,$obj->is_jj(2));

			eq(false,$obj->is_kk());
			$obj->kk("hoge@rhaco.org");
			eq(true,$obj->is_kk());
			try{
				$obj->kk("hoge");
				fail();
			}catch(Exception $e){
				success();
			}
			eq(null,$obj->ll());
			eq("2009-10-04",$obj->ll("2009/10/04"));
			eq("2009-10-04",$obj->ll("2009/10/4"));
			eq("2009-01-04",$obj->ll("2009/1/4"));
			eq("1900-01-04",$obj->ll("1900/1/4"));
			eq("645-01-04",$obj->ll("645 1 4"));
			eq("645-01-04",$obj->ll("645年1月4日"));
			eq("645/01/04",$obj->fm_ll());
			eq("645",$obj->fm_ll("Y"));
			eq("6450104",$obj->fm_ll("Ymd"));
			eq("645年01月04日",$obj->fm_ll("Y年m月d日"));
			eq("1981-02-04",$obj->ll("1981-02-04"));

			eq(null,$obj->nn());
			eq("20091004",$obj->nn("2009/10/04"));
			eq("20091004",$obj->nn("2009/10/4"));
			eq("20090104",$obj->nn("2009/1/4"));
			eq("19000104",$obj->nn("1900/1/4"));
			eq("6450104",$obj->nn("645 1 4"));
			eq("6450104",$obj->nn("645年1月4日"));
			eq("645/01/04",$obj->fm_nn());
			eq("645",$obj->fm_nn("Y"));
			eq("6450104",$obj->fm_nn("Ymd"));
			eq("645年01月04日",$obj->fm_nn("Y年m月d日"));
			eq("19810204",$obj->nn("1981-02-04"));

			eq(false,$obj->is_mm());
			$obj->mm("abc123_");
			eq(true,$obj->is_mm());
			try{
				$obj->mm("/abc");
				fail();
			}catch(Exception $e){
				success();
			}
			eq(false,$obj->is_oo());
			$obj->oo(123);
			eq(123,$obj->oo());
			$obj->oo("456");
			eq(456,$obj->oo());
			
			try{
				$obj->oo("123F");
				fail();
			}catch(Exception $e){
				success();
			}			
			try{
				$obj->oo(123.45);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo("123.0");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo("123.000000001");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.000000001);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo("123.0000000001");
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.0000000001);
				fail();
			}catch(Exception $e){
				success();
			}
			try{
				$obj->oo(123.0);
				fail();
			}catch(Exception $e){
				success();
			}
		*/
	}
	final public function __construct(){
		$this->_class_ = get_class($this);		
		if(!empty($this->_mixin_)){
			foreach(explode(',',$this->_mixin_) as $type) $this->add_object($type);
		}
		$private = array();
		foreach(array_keys(get_object_vars($this)) as $name){
			if($name[0] == "_"){
				$private[] = $name;
			}else{
				$ref = new ReflectionProperty($this->_class_,$name);
				if(!$ref->isPrivate()) $this->_props_[] = $name;
			}
		}
		$a = (func_num_args() > 0) ? func_get_arg(0) : null;
		$dict = array();

		if(!empty($a) && is_string($a) && preg_match_all("/.+?[^\\\],|.+?$/",$a,$m)){
			foreach($m[0] as $g){
				if(strpos($g,'=') !== false){
					list($n,$v) = explode('=',$g,2);
					if(substr($v,-1) == ',') $v = substr($v,0,-1);
					$dict[$n] = ($v === '') ? null : str_replace("\\,",",",preg_replace("/^([\"\'])(.*)\\1$/","\\2",$v));
				}
			}
		}
		if(isset($dict["_static_"]) && $dict["_static_"] === "true"){
			$this->_static_ = true;
		}else if(method_exists($this,'__new__')){
			$args = func_get_args();
			call_user_func_array(array($this,'__new__'),$args);
		}else{
			foreach($dict as $n => $v){
				if(in_array($n,$this->_props_)){
					$this->{$n}($v);
				}else if(in_array($n,$private)){
					$this->{$n} = ($v === "true") ? true : (($v === "false") ? false : $v);
				}else{
					throw new ErrorException($this>_class_."::".$n." property not found");
				}
			}
		}
		if(!$this->_static_ && $this->_init_ && method_exists($this,'__init__')) $this->__init__();
		/***
			$name1 = create_class('protected $aaa="A";');
			$name2 = create_class('
						protected $_mixin_ = "'.$name1.'";
						protected $bbb="B";
					');
			$obj2 = new $name2;
			eq("A",$obj2->aaa());
			eq("B",$obj2->bbb());

			$name1 = create_class('protected $aaa="a";');
			$name2 = create_class('
						protected $_mixin_ = "'.$name1.'";
						protected $bbb="B";
						protected $aaa="A";

						public function aaa2(){
							return $this->aaa;
						}
					');
			$obj2 = new $name2;

			eq("A",$obj2->aaa());
			eq("B",$obj2->bbb());
			eq("A",$obj2->aaa2());

			$obj2->aaa("Z");
			eq("Z",$obj2->aaa());
			eq("B",$obj2->bbb());
			eq("Z",$obj2->aaa2());
			
			$name = create_class('
					static protected $__ccc__ = "type=boolean";
					static protected $__ddd__ = "type=number";
					public $aaa;
					public $bbb;
					public $ccc;
					public $ddd;
				');
			$hoge = new $name("aaa=hoge");
			eq("hoge",$hoge->aaa());
			eq(null,$hoge->bbb());
			$hoge = new $name("ccc=true");
			eq(true,$hoge->ccc());
			$hoge = new $name("ddd=123");
			eq(123,$hoge->ddd());
			$hoge = new $name("ddd=123.45");
			eq(123.45,$hoge->ddd());

			$hoge = new $name("bbb=fuga,aaa=hoge");
			eq("hoge",$hoge->aaa());
			eq("fuga",$hoge->bbb());
		*/
	}
	final public function __destruct(){
		if(method_exists($this,'__del__')) $this->__del__();
	}
	final public function __toString(){
		return (string)$this->__str__();
	}
	final public function __clone(){
		if(method_exists($this,'__clone__')){
			$this->__clone__();
		}else{
			foreach($this->prop_values() as $name => $value){
				if(is_object($value)) $this->{$name} = clone($this->{$name});
			}
		}
	}
	final private function _get_a_($name){
		$result = array();
		try{
			$ref = new ReflectionClass($this->_class_);
			$pref = $ref->getProperty($name);
			if($pref->isStatic()){
				$p = $ref->getStaticProperties();
				$str = $p[$name];
			}else{
				$str = $this->{$name};
			}
		}catch(ReflectionException $e){
			$vars = get_object_vars($this);
			$str = (isset($vars[$name])) ? $vars[$name] : null;
		}
		if(strpos($str,'=') !== false){
			$str = preg_replace("/(\(.+\))|(([\"\']).+?\\3)/e",'stripcslashes(str_replace(",","__ANNON_COMMA__","\\0"))',$str);
			foreach(explode(',',$str) as $arg){
				if($arg != ''){
					$exp = explode('=',$arg,2);
					if(sizeof($exp) !== 2) throw new ErrorException('syntax error annotation `'.$name.'`');
					if(substr($exp[1],-1) == ',') $exp[1] = substr($exp[1],0,-1);
					$value = ($exp[1] === '') ? null : str_replace('__ANNON_COMMA__',',',$exp[1]);
					$result[$exp[0]] = ($value === 'true') ? true : (($value === 'false') ? false : $value);
				}
			}
		}
		return (object)$result;
	}
	final private function _prop_a_($name){
		if(isset(self::$_anon_[$this->_class_][$name])) return self::$_anon_[$this->_class_][$name];
		$param = (object)array('name'=>$name,'type'=>'mixed','attr'=>null,'primary'=>false,'get'=>true,'set'=>true);
		$param = (object)array_merge((array)$param,(array)$this->_get_a_('__'.$name.'__'));

		if(strpos($param->type,'choice') === 0){
			if($param->type === 'choice' && method_exists($this,'__choices_'.$name.'__')){
				foreach(call_user_func(array($this,'__choices_'.$name.'__')) as $arg) $param->choices[] = $this->_to_string_($arg);
			}else{
				$param->choices = array();
				foreach(explode(',',preg_replace("/([\"\']).+?\\1/e","str_replace(',','__CHOICE_COMMA__','\\0')",substr($param->type,strpos($param->type,"(") + 1,strpos($param->type,")") - strlen($param->type)))) as $arg){
					if($arg !== '') $param->choices[] = str_replace('__CHOICE_COMMA__',',',preg_replace("/^([\"\'])(.+)\\1$/","\\2",$arg));
				}
			}
			$param->type = 'choice';
		}else{
			switch(substr($param->type,-2)){
				case '[]': $param->attr = 'a'; break;
				case '{}': $param->attr = 'h'; break;
			}
			if(isset($param->attr)) $param->type = substr($param->type,0,-2);
			if($param->type === 'self') $param->type = get_class($this);
			if($param->type === 'serial') $param->primary = true;
		}
		self::$_anon_[$this->_class_][$name] = $param;
		return self::$_anon_[$this->_class_][$name];
	}
	final static private function invalid_argument($param,$arg){
		throw new InvalidArgumentException(sprintf('%s `%s` is not a %s value',$param->name,$arg,$param->type));
	}
	/**
	 * プロパティ名を返す
	 * @return string{}
	 */
	final public function props(){
		return $this->_props_;
		/***
			$name1 = create_class('
				public $public;
				protected $protected;
				private $private;
				
				protected function __init__(){
					$this->public = "public";
					$this->protected = "protected";
					$this->private = "private";
				}
			');
			
			$obj = new $name1();
			eq("public",$obj->public());
			eq("protected",$obj->protected());
			try{
				$obj->private();
				fail();
			}catch(ErrorException $e){
				success();
			}
		 */
	}
	/**
	 * get可能なオブジェクトのプロパティを返す
	 * @return mixed{} (name => value)
	 */
	final public function prop_values(){
		$result = array();
		foreach($this->props() as $name){
			if($this->a($name,'get') !== false) $result[$name] = $this->{$name}();
		}
		return $result;
		/***
			$name1 = create_class('
						public $public_var = 1;
						protected $protected_var = 2;
						private $private_var = 3;

						public function vars(){
							$result = array();
							foreach($this->prop_values() as $k => $v) $result[$k] = $v;
							return $result;
						}
					');
			$obj = new $name1();
			eq(array("public_var"=>1,"protected_var"=>2),$obj->vars());
			$obj->add_var = 4;
			eq(array("public_var"=>1,"protected_var"=>2,"add_var"=>4),$obj->vars());

			$name2 = create_class('
						public $e_public_var = 1;
						protected $e_protected_var = 2;
						private $e_private_var = 3;
					',$name1);
			$obj2 = new $name2();
			eq(array("e_public_var"=>1,"e_protected_var"=>2,"public_var"=>1,"protected_var"=>2),$obj2->vars());
			$obj2->add_var = 4;
			eq(array("e_public_var"=>1,"e_protected_var"=>2,"public_var"=>1,"protected_var"=>2,"add_var"=>4),$obj2->vars());
		*/
	}
	final private function _call_attr_($call,$name,array &$args,&$result){
		$call_name = "__".$call."_".$name."__";
		if(!method_exists($this,$call_name)) return false;
		$result = call_user_func_array(array($this,$call_name),$args);
		return true;
	}
	final private function _to_string_($obj){
		if(is_bool($obj)) return ($obj) ? "true" : "false";
		if(!is_object($obj)) return (string)$obj;
		return (string)$obj;
	}
	protected function __str__(){
		return $this->_class_;
	}
	protected function __set__($args,$param){
		if(!$param->set) throw new InvalidArgumentException('Processing not permitted [set]');
		$arg = $args[0];
		if($arg === null) return null;
		switch($param->type){
			case 'string':
			case 'text': return $this->_to_string_($arg);
			default:
				if($arg === '') return null;
				switch($param->type){
					case 'number':
						if(!is_numeric($arg)) $this->invalid_argument($param,$arg);
						return (float)$arg;
					case 'serial':
					case 'integer':
						if(!is_int($arg)){
							if(is_float($arg)) $this->invalid_argument($param,$arg);
							$arg = (string)$arg;
							if(!ctype_digit($arg)) $this->invalid_argument($param,$arg);
						}
						return (int)$arg;
					case 'boolean':
						if(is_string($arg)){
							$arg = ($arg === 'true' || $arg === '1') ? true : (($arg === 'false' || $arg === '0') ? false : $arg);
						}else if(is_int($arg)){
							$arg = ($arg === 1) ? true : (($arg === 0) ? false : $arg);
						}
						if(!is_bool($arg)) $this->invalid_argument($param,$arg);
						return (boolean)$arg;
					case 'timestamp':
					case 'date':
						if(ctype_digit($this->_to_string_($arg))) return (int)$arg;
						if(((int)preg_replace("/[^\d]/",'',$arg)) === 0) $this->invalid_argument($param,$arg);
						$time = strtotime($arg);
						if($time === false) $this->invalid_argument($param,$arg);
						return $time;
					case 'time':
						if(is_numeric($arg)) return $arg;
						$list = array_reverse(preg_split("/[^\d\.]+/",$arg));
						if($list[0] === '') array_shift($list);
						list($s,$m,$h) = array((isset($list[0]) ? (float)$list[0] : 0),(isset($list[1]) ? (float)$list[1] : 0),(isset($list[2]) ? (float)$list[2] : 0));
						if(sizeof($list) > 3 || $m > 59 || $s > 59 || strpos($h,'.') !== false || strpos($m,'.') !== false) $this->invalid_argument($param,$arg);
						return ($h * 3600) + ($m*60) + ((int)$s) + ($s-((int)$s));
					case 'strdate':
					case 'intdate':
						$list = preg_split("/[^\d]+/",$arg);
						if(sizeof($list) < 3) $this->invalid_argument($param,$arg);
						list($y,$m,$d) = array((int)$list[0],(int)$list[1],(int)$list[2]);
						if($m < 1 || $m > 12 || $d < 1 || $d > 31 || (in_array($m,array(4,6,9,11)) && $d > 30) || (in_array($m,array(1,3,5,7,8,10,12)) && $d > 31)
							|| ($m == 2 && ($d > 29 || (!(($y % 4 == 0) && (($y % 100 != 0) || ($y % 400 == 0)) ) && $d > 28)))
						) throw new InvalidArgumentException(sprintf('%s `%s` is not a %s value',$param->name,$arg,$param->type));
						return ($param->type === 'strdate') ? sprintf('%d-%02d-%02d',$y,$m,$d) : sprintf('%d%02d%02d',$y,$m,$d);
					case 'email':
						if(!preg_match("/^[\x01-\x7F]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,6}$/i",$arg) || strlen($arg) > 255) $this->invalid_argument($param,$arg);
						return $arg;
					case 'alnum':
						if(!ctype_alnum(str_replace('_','',$arg))) $this->invalid_argument($param,$arg);
						return $arg;
					case 'choice':
						$arg = $this->_to_string_($arg);
						if(!in_array($arg,$param->choices,true)) $this->invalid_argument($param,$arg);
						return $arg;
					case 'mixed': return $arg;
					default:
						if(!($arg instanceof $param->type)) $this->invalid_argument($param,$arg);
						return $arg;
				}
		}
	}
	protected function __get__($arg,$param){
		if(!$param->get) throw new InvalidArgumentException('Processing not permitted [get]');
		if($param->attr == 'a' || $param->attr == 'h') return (is_array($this->{$param->name})) ? $this->{$param->name} : (is_null($this->{$param->name}) ? array() : array($this->{$param->name}));
		return $arg;
	}
	protected function __in__($args,$param){
		$arg = $args[0];
		$default = (isset($args[1])) ? $args[1] : null;
		return (is_object($this->{$param->name})) ? ((isset($this->{$param->name})) ? $this->{$param->name}->{$args[0]}() : $default) :
											((isset($this->{$param->name}[$args[0]])) ? $this->{$param->name}[$args[0]] : $default);

	}
	protected function __rm__($args,$param){
		if(!$param->set) throw new BadMethodCallException('Processing not permitted [rm]');
		switch($param->attr){
			case 'h':
				if(!empty($args)){
					foreach($args as $arg){
						if($arg instanceof self) $arg = $arg->str();
						if(isset($this->{$param->name}[$arg])) unset($this->{$param->name}[$arg]);
					}
					return;
				}
			default:
				return $this->{$param->name} = null;
		}
	}
	protected function __ar__($args,$param){
		$list = (is_array($this->{$param->name})) ? $this->{$param->name} : (($this->{$param->name} === null) ? array() : array($this->{$param->name}));
		if(isset($args[0])){
			$current = 0;
			$limit = ((isset($args[1]) ? $args[1] : sizeof($list)) + $args[0]);
			$result = array();
			foreach($list as $key => $value){
				if($args[0] <= $current && $limit > $current) $result[$key] = $value;
				$current++;
			}
			return $result;
		}
		return $list;
	}
	protected function __fm__($args,$param){
		switch($param->type){
			case 'timestamp': return ($this->{$param->name} === null) ? null : (date((empty($args) ? 'Y/m/d H:i:s' : $args[0]),(int)$this->{$param->name}));
			case 'date': return ($this->{$param->name} === null) ? null : (date((empty($args) ? 'Y/m/d' : $args[0]),(int)$this->{$param->name}));
			case 'time':
				if($this->{$param->name} === null) return 0;
				$h = floor($this->{$param->name} / 3600);
				$i = floor(($this->{$param->name} - ($h * 3600)) / 60);
				$s = floor($this->{$param->name} - ($h * 3600) - ($i * 60));
				$m = str_replace(' ','0',rtrim(str_replace('0',' ',(substr(($this->{$param->name} - ($h * 3600) - ($i * 60) - $s),2,12)))));
				return (($h == 0) ? '' : $h.':').(sprintf('%02d:%02d',$i,$s)).(($m == 0) ? '' : '.'.$m);
			case 'intdate': if($this->{$param->name} === null) return null;
							preg_match("/^([\d]+)([\d]{2})([\d]{2})$/",$this->{$param->name},$match);
							return str_replace(array('Y','m','d'),array($match[1],$match[2],$match[3]),(isset($args[0]) ? $args[0] : 'Y/m/d'));
			case 'strdate':  if($this->{$param->name} === null) return null;
							list($y,$m,$d) = explode('-',$this->{$param->name});
							return str_replace(array('Y','m','d'),array($y,$m,$d),(isset($args[0]) ? $args[0] : 'Y/m/d'));
			case 'boolean': return ($this->{$param->name}) ? (isset($args[1]) ? $args[1] : '') : (isset($args[0]) ? $args[0] : 'false');
			default: return $this->{$param->name}();
		}
		return $this->{$param->name};
	}
	protected function __is__($args,$param){
		$value = $this->{$param->name};
		if($param->attr === 'h' || $param->attr === 'a'){
			if(sizeof($args) !== 1) return !empty($this->{$param->name});
			$value = isset($this->{$param->name}[$args[0]]) ? $this->{$param->name}[$args[0]] : null;
		}
		switch($param->type){
			case 'string':
			case 'text': return (isset($value) && $value !== '');
		}
		return (boolean)(($param->type == 'boolean') ? $value : isset($value));
	}
	protected function __ha__($args,$param){
		switch($param->type){
			case 'choice':
			case 'time':
			case 'date':
			case 'timestamp': return $this->{'fm_'.$param->name}();
		}
		return $this->{$param->name}();
	}
	/**
	 * 文字列表現を返す
	 * @return string
	 */
	final public function str(){
		return (string)$this->__str__();
	}
	/**
	 * アノテーションの値を取得/設定
	 * @param string $var_name 変数名
	 * @param string $anon_name アノテーション名
	 * @param mixed $value 設定する値
	 * @param boolean $force 強制的に設定するか
	 * @return mixed
	 */
	final public function a($var_name,$anon_name,$value=null,$force=false){
		$this->_prop_a_($var_name);
		if($force || ($value !== null && !isset(self::$_anon_[$this->_class_][$var_name]->{$anon_name}))){
			self::$_anon_[$this->_class_][$var_name]->{$anon_name} = $value;
		}
		return (isset(self::$_anon_[$this->_class_][$var_name]->{$anon_name})) ? self::$_anon_[$this->_class_][$var_name]->{$anon_name} : null;
		/***
			$class1 = create_class('
				static protected $__aaa__ = "type=choice(AA,BB,CC)";
				static protected $__bbb__ = "type=choice(\'aaa\',\'bbb\',\'cc,c\')";
				static protected $__ccc__ = "type=choice";
				protected $aaa;
				protected $bbb;
				protected $ccc;

				protected function __choices_ccc__(){
					return array("111","222",333);
				}
			');
			$obj = new $class1();
			$obj->aaa("BB");
			$obj->bbb("bbb");
			$obj->ccc("222");

			eq(array("AA","BB","CC"),$obj->a("aaa","choices"));
			eq(array("aaa","bbb","cc,c"),$obj->a("bbb","choices"));
			eq(array("111","222","333"),$obj->a("ccc","choices"));
		 */
	}
	/**
	 * mixinしたObjectを参照する
	 * @param string $name
	 * @return object
	 */
	final protected function o($name){
		return $this->_objects_[$name];
	}
	/**
	 * クラスアクセスとして返す
	 * @param string $class_name クラス名
	 * @return object
	 */
	final static public function c($class_name){
		if(!is_subclass_of($class_name,__CLASS__)) throw new BadMethodCallException('Processing not permitted static');
		$obj = new $class_name('_static_=true');
		if(!$obj->_static_) throw new BadMethodCallException('Processing not permitted static');
		return $obj;
	}
	/**
	 * クラス名を返す
	 * @return string
	 */
	final public function get_called_class(){
		if(!$this->_static_) throw new BadMethodCallException('Processing not permitted static');
		return $this->_class_;
	}
}