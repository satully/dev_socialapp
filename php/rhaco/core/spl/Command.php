<?php
/**
 * コマンドを実行する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Command extends Object{
	static protected $__stdout__ = "type=string";
	static protected $__stderr__ = "type=string";
	protected $resource; #リソース
	protected $stdout; # 実行結果
	protected $stderr; # 実行時のエラー
	protected $end_code; # 実行していたプロセスの終了状態
	private $proc;
	private $close = true;

	protected function __new__($command=null){
		if(!empty($command)){
			$this->open($command);
			$this->close();
		}
	}
	/**
	 * コマンドを実行しプロセスをオープする
	 * @param string $command 実行するコマンド
	 * @param string $out_file 結果を保存するファイルパス
	 * @param string $error_file エラー結果を保存するファイルパス
	 */
	public function open($command,$out_file=null,$error_file=null){
		Log::debug($command);
		$this->close();

		if(!empty($out_file)) File::write($out_file);
		if(!empty($error_file)) File::write($error_file);
		$out = (empty($out_file)) ? array("pipe","w") : array("file",$out_file,"w");
		$err = (empty($error_file)) ? array("pipe","w") : array("file",$error_file,"w");
		$this->proc = proc_open($command,array(array("pipe","r"),$out,$err),$this->resource);
		$this->close = false;
	}
	/**
	 * コマンドを実行し出力する
	 * @param string $command 実行するコマンド
	 */
	public function write($command){
		Log::debug($command);
		fwrite($this->resource[0],$command."\n");
	}
	/**
	 * 結果を取得する
	 * @return string
	 */
	public function gets(){
		if(isset($this->resource[1])){
			$value = fgets($this->resource[1]);
			$this->stdout .= $value;
			return $value;
		}
	}
	/**
	 * 結果から１文字取得する
	 * @return string
	 */
	public function getc(){
		if(isset($this->resource[1])){
			$value = fgetc($this->resource[1]);
			$this->stdout .= $value;
			return $value;
		}
	}
	/**
	 * 閉じる
	 */
	public function close(){
		if(!$this->close){
			if(isset($this->resource[0])) fclose($this->resource[0]);
			if(isset($this->resource[1])){
				while(!feof($this->resource[1])) $this->stdout .= fgets($this->resource[1]);
				fclose($this->resource[1]);
			}
			if(isset($this->resource[2])){
				while(!feof($this->resource[2])) $this->stderr .= fgets($this->resource[2]);
				fclose($this->resource[2]);
			}
			$this->end_code = proc_close($this->proc);
			$this->close = true;
		}
	}
	protected function __del__(){
		$this->close();
	}
	protected function __str__(){
		return $this->out;
	}
	/**
	 * コマンドを実行し結果を取得
	 * @param string $command
	 * @return unknown_type
	 */
	static public function out($command){
		$self = new self($command);
		return $self->stdout();
	}
	/**
	 * コマンドを実行してエラー結果を取得
	 * @param string $command 実行するコマンド
	 * @return string
	 */
	static public function error($command){
		$self = new self($command);
		return $self->stderr();
	}
	/**
	 * 標準入力からの入力を取得する
	 * @param string $msg 入力待ちのメッセージ
	 * @param string $default 入力が空だった場合のデフォルト値
	 * @param string[] $choice 入力を選択式で求める
	 * @param boolean $multiline 複数行の入力をまつ、終了は行頭.(ドット)
	 * @return string
	 */
	static public function stdin($msg,$default=null,$choice=array(),$multiline=false){
		$result = null;
		print($msg.(empty($choice) ? "" : " (".implode(" / ",$choice).")").(empty($default) ? "" : " [".$default."]").": ");

		while(true){
			fscanf(STDIN,"%s",$b);
			if($multiline && $b == ".") break;
			$result .= $b."\n";
			if(!$multiline) break;
		}
		$result = substr(str_replace(array("\r\n","\r","\n"),"\n",$result),0,-1);
		if(empty($result)) $result = $default;
		if(empty($choice) || in_array($result,$choice)) return $result;
	}
}