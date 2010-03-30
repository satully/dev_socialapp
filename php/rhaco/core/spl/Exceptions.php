<?php
/**
 * 例外
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Exceptions extends Exception{
	static private $self;
	private $messages = array();

	/**
	 * Exceptionを追加する
	 * @param Exception $exception 例外
	 * @param string $name グループ名
	 */
	static public function add(Exception $exception,$name=null){
		if(self::$self === null) self::$self = new self();
		if($exception instanceof self){
			foreach($exception->messages as $key => $es){
				foreach($es as $e) self::$self->messages[$key][] = $e;
			}
		}else{
			self::$self->messages["exceptions"][] = $exception;
			if($name !== null) self::$self->messages[$name][] = $exception;
		}
	}
	/**
	 * 追加されたExceptionのクリア
	 */
	static public function clear(){
		self::$self = null;
	}
	/**
	 * 追加されたExceptionからメッセージ配列を取得
	 * @param string $name グループ名
	 * @return string[]
	 */
	static public function messages($name="exceptions"){
		$result = array();
		foreach(self::gets($name) as $m) $result[] = $m->getMessage();
		return $result;
	}
	/**
	 * 追加されたExceptionからException配列を取得
	 * @param string $name グループ名
	 * @return Exception[]
	 */
	static public function gets($name="exceptions"){
		return (self::invalid($name)) ? self::$self->messages[$name] : array();
	}
	/**
	 * Exceptionが追加されているか
	 * @param string $name グループ名
	 * @return boolean
	 */
	static public function invalid($name="exceptions"){
		return (isset(self::$self) && isset(self::$self->messages[$name]));
	}
	/**
	 * Exceptionが追加されていればthrowする
	 * @param string $name グループ名
	 */
	static public function validation($name=null){
		if(self::$self !== null && (($name === null && !empty(self::$self->messages)) || isset(self::$self->messages[$name]))) throw self::$self;
	}
	public function __toString(){
		if(self::$self === null || empty(self::$self->messages)) return null;
		$result = count(self::$self->messages["exceptions"])." exceptions: ";
		foreach(self::$self->messages["exceptions"] as $e){
			$result .= "\n ".$e->getMessage();
		}
		return $result;
	}
}