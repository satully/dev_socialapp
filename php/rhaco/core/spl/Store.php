<?php
/**
 * Storeコントローラ
 * @author tokushima
 */
class Store extends Object{
	/**
	 * $keyが存在するか
	 * @param string $key キー名
	 * @param boolean $ignore_time 有効期限
	 * @return boolean
	 */
	static public function has($key,$ignore_time=false){
		$id = self::id($key);
		if(Object::C(__CLASS__)->has_module("store_has")) return Object::C(__CLASS__)->call_module("store_has",$id,$ignore_time);
		$path = File::absolute(App::work("store"),$id);
		$path = (is_file($path)) ? $path : ((is_file($path."_s") ? $path."_s" : null));
		if($ignore_time && $path !== null) return true;
		return (isset($path) && File::last_update($path) > time());
	}
	/**
	 * Storeへセットする
	 * @param string $key キー名
	 * @param mixed $value 内容
	 * @param integer $expiry_time 有効期限
	 * @return mixed 内容を返す
	 */
	static public function set($key,$value,$expiry_time=2592000){
		$id = self::id($key);
		if(Object::C(__CLASS__)->has_module("store_set")) return Object::C(__CLASS__)->call_module("store_set",$id,$value,$expiry_time);
		$path = File::absolute(App::work("store"),$id);
		if(!is_string($value)) list($value,$path) = array(serialize($value),$path."_s");
		File::gzwrite($path,$value);
		touch($path,time()+$expiry_time,time()+$expiry_time);
		return $value;
	}
	/**
	 * セット時にセット時間も記録する
	 * lt_getと対で利用する
	 * set record creation time
	 * 
	 * @param string $key キー名
	 * @param mixed $value 内容
	 * @param integer $expiry_time 有効期限
	 * @return mixed 内容を返す
	 */
	static public function set_rct($key,$value,$expiry_time=2592000){
		self::set($key,$value,$expiry_time);
		self::set(__CLASS__.'_settime_'.$key,time(),$expiry_time);
		return $value;
	}
	/**
	 * Storeから取得する
	 * @param string $key キー名
	 * @return string
	 */
	static public function get($key){
		$id = self::id($key);
		if(Object::C(__CLASS__)->has_module("store_get")) return Object::C(__CLASS__)->call_module("store_get",$id);
		$path = File::absolute(App::work("store"),$id);
		if(is_file($path)) return File::gzread($path);
		if(is_file($path."_s")) return unserialize(File::gzread($path."_s"));
		return null;
	}
	/**
	 * set時間より$timeが小さければ取得
	 * set_rctと対で利用する
	 * @param mixed $value 取得された値のはいる変数
	 * @param string $key キー名
	 * @param integer $time 指定時間
	 * @return boolean 取得できたか
	 */
	static public function lt_get(&$value,$key,$time){
		$args = func_get_args();
		$args[0] = $args[1] = 0;
		$max = call_user_func_array('max',$args);
		if(((int)self::get(__CLASS__.'_settime_'.$key)) < $max) return false;
		$value = self::get($key);
		return true;
	}
	/**
	 * Storeから削除する
	 * @param string $key キー名
	 * @return boolean
	 */
	static public function delete($key=null){
		$id = self::id($key);
		if(Object::C(__CLASS__)->has_module("store_delete")) return Object::C(__CLASS__)->call_module("store_delete",$id);
		return self::rm(File::absolute(App::work("store"),$id));
	}
	static private function id($key){
		return md5(implode("",(is_array($key)) ? $key : array($key)));
	}
}