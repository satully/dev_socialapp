<?php
/**
 * doctestを抽出する
 * @author tokushima
 *
 */
class InfoDoctest extends Object{
	static protected $__test__ = 'type=text';
	static protected $__name__ = 'type=string';
	static protected $__class__ = 'type=string';

	protected $name; # 変数名
	protected $class; # 定義されているクラス名	
	protected $test; # doctest

	protected function __new__($name=null,$class=null){
		$this->name = $name;
		$this->class = $class;
	}
	
	/**
	 * doctestを取得
	 * @param string $src doctestを含む文字列
	 * @param string $self_class 定義されているクラス名
	 * @param string $class_package 定義されているパッケージ名
	 * @param integer $offset 開始行
	 * @return self{}
	 */
	static public function get($src,$self_class,$class_package,$offset=0){
		$doctest = array();
		if(preg_match_all("/\/\*\*\*.+?\*\//s",$src,$comments,PREG_OFFSET_CAPTURE)){
			foreach($comments[0] as $value){
				if(isset($value[0][5]) && $value[0][5] != "*"){
					$test_block = str_replace(array("self::","new self("),array($self_class."::","new ".$self_class."("),preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."***","*"."/"),"",$value[0])));
					$test_object = new self((preg_match("/^[\s]*#(.+)/",$test_block,$match) ? trim($match[1]) : null),$class_package);
					$test_object->test(Text::plain($test_block));
					$doctest[$offset + substr_count(substr($src,0,$value[1]),"\n")] = $test_object;
				}
			}
		}
		return $doctest;
	}	
}