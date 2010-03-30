<?php
/**
 * ファイル処理
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class File extends Object{
	const TAR_TYPE_FILE = 0;
	const TAR_TYPE_DIR = 5;
	static private $src_list = array('://','/./','//');
	static private $dst_list = array('#REMOTEPATH#','/','/');
	static private $p_src_list = array('/^\/(.+)$/','/^(\w):\/(.+)$/');
	static private $p_dst_list = array('#ROOT#\\1','\\1#WINPATH#\\2','');
	static private $r_src_list = array('#REMOTEPATH#','#ROOT#','#WINPATH#');
	static private $r_dst_list = array('://','/',':/');
	static protected $__size__ = 'type=integer';
	static protected $__update__ = 'type=timestamp';
	static protected $__error__ = 'type=integer';
	static protected $__directory__ = 'type=string';
	static protected $__fullname__ = 'type=string';	
	static protected $__name__ = 'type=string';
	static protected $__oname__ = 'type=string';
	static protected $__ext__ = 'type=string';
	static protected $__mime__ = 'type=string';
	static protected $__tmp__ = 'type=string';
	
	protected $directory; # フォルダパス
	protected $fullname; # ファイルパス
	protected $name; # ファイル名
	protected $oname; # 拡張子がつかないファイル名
	protected $ext; # 拡張子
	protected $size; # ファイルサイズ
	protected $update; # ファイルの更新時間
	protected $mime; # ファイルのコンテントタイプ
	protected $value; # 内容
	protected $tmp; # 一時ファイルパス
	protected $error;  # エラーコード

	final protected function __new__($fullname=null,$value=null){
		$this->fullname	= str_replace("\\","/",$fullname);
		$this->value = $value;
		$this->parse_fullname();
	}
	final protected function __cp__($dest){
		return self::copy($this,$dest);
	}
	final protected function __str__(){
		return $this->fullname;
	}
	final protected function __is_ext__($ext){
		return ('.'.strtolower($ext) === strtolower($this->ext()));
	}
	final protected function __is_fullname__(){
		return is_file($this->fullname);
	}
	final protected function __is_tmp__(){
		return is_file($this->tmp);
	}
	final protected function __is_error__(){
		return (intval($this->error) > 0);
	}
	final protected function __set_value__($value){
		$this->value = $value;
		$this->size = sizeof($value);
	}
	/**
	 * 一時ファイルから移動する
	 * HTMLでのファイル添付の場合に使用
	 * @param string $filename ファイルパス
	 * @return $this
	 */
	public function generate($filename){
		if(self::copy($this->tmp,$filename)){
			if(unlink($this->tmp)){
				$this->fullname = $filename;
				$this->parse_fullname();
				return $this;
			}
		}
		throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
	}
	/**
	 * 標準出力に出力する
	 */
	public function output(){
		if(empty($this->value) && @is_file($this->fullname)){
			readfile($this->fullname);
		}else{
			print($this->value);
		}
		exit;
	}
	/**
	 * 取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	public function get(){
		if($this->value !== null) return $this->value;
		if(!is_file($this->fullname)) throw new RuntimeException($this->fullname.' not found');
		return file_get_contents($this->fullname);
	}
	private function parse_fullname(){
		$fullname = str_replace("\\","/",$this->fullname);
		if(preg_match("/^(.+[\/]){0,1}([^\/]+)$/",$fullname,$match)){
			$this->directory = empty($match[1]) ? "./" : $match[1];
			$this->name = $match[2];
		}
		if(false !== ($p = strrpos($this->name,'.'))){
			$this->ext = '.'.substr($this->name,$p+1);
			$filename = substr($this->name,0,$p);
		}
		$this->oname = @basename($this->name,$this->ext);

		if(@is_file($this->fullname)){
			$this->update(@filemtime($this->fullname));
			$this->size(sprintf('%u',@filesize($this->fullname)));
		}else{
			$this->size = strlen($this->value);
		}
		if(empty($this->mime)){
			$ext = strtolower(substr($this->ext,1));
			switch($ext){
				case 'jpg':
				case 'png':
				case 'gif':
				case 'bmp':
				case 'tiff': $this->mime = 'image/'.$ext; break;
				case 'css': $this->mime = 'text/css'; break;
				case 'txt': $this->mime = 'text/plain'; break;
				case 'html': $this->mime = 'text/html'; break;
				case 'xml': $this->mime = 'application/xml'; break;
				case 'js': $this->mime = 'text/javascript'; break;
				case 'flv':
				case 'swf':  $this->mime = 'application/x-shockwave-flash'; break;
				case 'gz':
				case 'tgz':
				case 'tar':
				case 'gz':  $this->mime = 'application/x-compress'; break;
				default:
					$this->mime = (Object::C(__CLASS__)->call_module('parse_mime_type',$this));
					if(empty($this->mime)) $this->mime = 'application/octet-stream';
			}
		}
	}
	/**
	 * クラスファイルか
	 * @return boolean
	 */
	final public function is_class(){
		return (!empty($this->oname) && $this->is_ext('php') && ctype_upper($this->oname[0]));
	}
	/**
	 * 不過視ファイルか
	 * @return boolean
	 */
	final public function is_invisible(){
		return (!empty($this->oname) && ($this->oname[0] == '.' || strpos($this->fullname,'/.') !== false));
	}
	/**
	 * privateファイルか
	 * @return boolean
	 */
	final public function is_private(){
		return (!empty($this->oname) && $this->oname[0] == '_');
	}
	/**
	 * ファイルパスを生成する
	 * @param string $base ベースとなるファイルパス
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function path($base,$path=''){
		/***
		 * eq("/abc/def/hig.php",File::path("/abc/def","hig.php"));
		 * eq("/xyz/abc/hig.php",File::path("/xyz/","/abc/hig.php"));
		 */
		if(!empty($path)){
			$path = self::parse_filename($path);
			if(preg_match("/^[\/]/",$path,$null)) $path = substr($path,1);
		}
		return self::absolute(self::parse_filename($base),self::parse_filename($path));
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 */
	static public function mkdir($source,$permission=null){
		if(empty($source)) throw new InvalidArgumentException(sprintf("permission denied (undef firname)",$source));
		$source = self::parse_filename($source);
		if(!(is_readable($source) && is_dir($source))){
			$path = $source;
			$dirstack = array();
			while(!is_dir($path) && $path != DIRECTORY_SEPARATOR){
				array_unshift($dirstack,$path);
				$path = dirname($path);
			}
			while($path = array_shift($dirstack)){
				try{
					mkdir($path);
					if(isset($permission)) chmod($path,$permission);
				}catch(ErrorException $e){
					throw new InvalidArgumentException(sprintf("permission denied[%s]",$path));
				}
			}
		}
	}
	/**
	 * ファイル、またはフォルダが存在しているか
	 * @param string $filename ファイルパス
	 * @return boolean
	 */
	static public function exist($filename){
		return (is_readable($filename) && (is_file($filename) || is_dir($filename) || is_link($filename)));
	}
	/**
	 * 移動
	 * @param string $source 移動もとのファイルパス
	 * @param string $dest 移動後のファイルパス
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 * @return boolean 移動に成功すればtrue
	 */
	static public function mv($source,$dest,$permission=null){
		$source = self::parse_filename($source);
		$dest = self::parse_filename($dest);
		if(self::exist($source)){
			self::mkdir(dirname($dest),$permission);
			return rename($source,$dest);
		}
		throw new InvalidArgumentException(sprintf("permission denied[%s]",$source));
	}
	/**
	 * 最終更新時間を取得
	 * @param string $filename ファイルパス
	 * @param boolean $clearstatcache ファイルのステータスのキャッシュをクリアするか
	 * @return integer
	 */
	static public function last_update($filename,$clearstatcache=false){
		if($clearstatcache) clearstatcache();
		if(is_dir($filename)){
			$last_update = -1;
			foreach(File::ls($filename,true) as $file){
				if($last_update < $file->update()) $last_update = $file->update();
			}
			return $last_update;
		}
		return (is_readable($filename) && is_file($filename)) ? filemtime($filename) : -1;
	}
	/**
	 * 削除
	 * $sourceが削除の場合はそれ以下も全て削除
	 * @param string $source 削除するパス
	 * @param boolean $inc_self $sourceも削除するか
	 * @return boolean
	 */
	static public function rm($source,$inc_self=true){
		if($source instanceof self) $source = $source->fullname();
		$source	= self::parse_filename($source);

		if(!$inc_self){
			foreach(self::dir($source) as $d) self::rm($d);
			foreach(self::ls($source) as $f) self::rm($f);
			return true;
		}
		if(!self::exist($source)) return true;
		if(is_writable($source)){
			if(is_dir($source)){
				if($handle = opendir($source)){
					$list = array();
					while($pointer = readdir($handle)){
						if($pointer != '.' && $pointer != '..'){
							$list[] = sprintf('%s/%s',$source,$pointer);
						}
					}
					closedir($handle);
					foreach($list as $path){
						if(!self::rm($path)) return false;
					}
				}
				if(rmdir($source)){
					clearstatcache();
					return true;
				}
			}else if(is_file($source) && unlink($source)){
				clearstatcache();
				return true;
			}
		}
		throw new InvalidArgumentException(sprintf("permission denied[%s]",$source));
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 * @param string $source コピー元のファイルパス
	 * @param string $dest コピー先のファイルパス
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 * @return boolean 成功時true
	 */
	static public function copy($source,$dest,$permission=null){
		$source	= self::parse_filename($source);
		$dest = self::parse_filename($dest);
		$dir = (preg_match("/^(.+)\/[^\/]+$/",$dest,$tmp)) ? $tmp[1] : $dest;

		if(!self::exist($source)) throw new InvalidArgumentException($source.' not found');
		self::mkdir($dir,$permission);
		if(is_dir($source)){
			$boo = true;
			if($handle = opendir($source)){
				while($pointer = readdir($handle)){
					if($pointer != '.' && $pointer != '..'){
						$srcname = sprintf('%s/%s',$source,$pointer);
						$destname = sprintf('%s/%s',$dest,$pointer);
						if(false === ($bool = self::copy($srcname,$destname))) break;
					}
				}
				closedir($handle);
			}
			return $bool;
		}else{
			$filename = (preg_match("/^.+(\/[^\/]+)$/",$source,$tmp)) ? $tmp[1] : '';
			$dest = (is_dir($dest))	? $dest.$filename : $dest;
			if(is_writable(dirname($dest))) copy($source,$dest);
			return self::exist($dest);
		}
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function read($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(!is_readable($filename) || !is_file($filename)) throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
		return file_get_contents($filename);
	}
	/**
	 * ファイルから行分割して配列で返す
	 *
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function lines($filename){
		return explode("\n",str_replace(array("\r\n","\r"),"\n",self::read($filename)));
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 * @param integer $updated 変更する更新時間
	 */
	static public function write($filename,$src=null,$permission=null,$updated=null){
		if($filename instanceof self) $filename = $filename->fullname;
		if(empty($filename)) throw new InvalidArgumentException(sprintf("permission denied (undef filename)",$filename));
		self::mkdir(dirname($filename),$permission);
		if(false === file_put_contents($filename,Text::str($src),LOCK_EX)) throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
		if(isset($permission)) chmod($filename,$permission);
		if(isset($updated)) touch($filename,$updated);
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 追加する内容
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 */
	static public function append($filename,$src,$permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$permission);
		if(false === file_put_contents($filename,Text::str($src),FILE_APPEND|LOCK_EX)) throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function gzread($filename){
		if($filename instanceof self) $filename = ($filename->is_fullname()) ? $filename->fullname() : $filename->tmp();
		if(strpos($filename,"://") === false && (!is_readable($filename) || !is_file($filename))) throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
		try{
			$fp = gzopen($filename,"rb");
			$buf = null;
			while(!gzeof($fp)) $buf .= gzread($fp,4096);
			gzclose($fp);
			return $buf;
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
		}
	}
	/**
	 * gz圧縮でファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 */
	static public function gzwrite($filename,$src,$permission=null){
		if($filename instanceof self) $filename = $filename->fullname;
		self::mkdir(dirname($filename),$permission);
		try{
			$fp = gzopen($filename,"wb9");
			gzwrite($fp,$src);
			gzclose($fp);
			if(isset($permission)) chmod($filename,$permission);
		}catch(Exception $e){
			throw new InvalidArgumentException(sprintf("permission denied[%s]",$filename));
		}
	}
	/**
	 * ファイル、またはディレクトリからtar圧縮のデータを作成する
	 * @param string $path 圧縮するファイルパス
	 * @param string $base_dir tarのヘッダ情報をこのファイルパスを除く相対パスとして作成する
	 * @param string $ignore_pattern 除外パターン
	 * @param boolean $endpoint エンドポイントとするか
	 * @return string
	 */
	static public function tar($path,$base_dir=null,$ignore_pattern=null,$endpoint=true){
		$result = null;
		$files = array();
		$path = self::parse_filename($path);
		$base_dir = self::parse_filename(empty($base_dir) ? (is_dir($path) ? $path : dirname($path)) : $base_dir);
		$ignore = (!empty($ignore_pattern));
		if(substr($base_dir,0,-1) != "/") $base_dir .= "/";
		$filepath = self::absolute($base_dir,$path);

		if(is_dir($filepath)){
			foreach(self::dir($filepath,true) as $dir) $files[$dir] = self::TAR_TYPE_DIR;
			foreach(self::ls($filepath,true) as $file) $files[$file->fullname()] = self::TAR_TYPE_FILE;
		}else{
			$files[$filepath] = self::TAR_TYPE_FILE;
		}
		foreach($files as $filename => $type){
			$target_filename = str_replace($base_dir,"",$filename);
			$bool = true;
			if($ignore){
				$ignore_pattern = (is_array($ignore_pattern)) ? $ignore_pattern : array($ignore_pattern);
				foreach($ignore_pattern as $p){
					if(preg_match("/".str_replace(array("\/","/","__SLASH__"),array("__SLASH__","\/","\/"),$p)."/",$target_filename)){
						$bool = false;
						break;
					}
				}
			}
			if(!$ignore || $bool){
				switch($type){
					case self::TAR_TYPE_FILE:
						$info = stat($filename);
						$rp = fopen($filename,"rb");
							$result .= self::tar_head($type,$target_filename,filesize($filename),fileperms($filename),$info[4],$info[5],filemtime($filename));
							while(!feof($rp)){
								$buf = fread($rp,512);
								if($buf !== "") $result .= pack("a512",$buf);
							}
						fclose($rp);
						break;
					case self::TAR_TYPE_DIR:
						$result .= self::tar_head($type,$target_filename);
						break;
				}
			}
		}
		if($endpoint) $result .= pack("a1024",null);
		return $result;
	}
	static private function tar_head($type,$filename,$filesize=0,$fileperms=0744,$uid=0,$gid=0,$update_date=null){
		if(strlen($filename) > 99) throw new InvalidArgumentException("Invalid filename (max length 100)".$filename);
		if($update_date === null) $update_date = time();
		$checksum = 256;
		$first = pack("a100a8a8a8a12A12",$filename,
						sprintf("%06s ",decoct($fileperms)),sprintf("%06s ",decoct($uid)),sprintf("%06s ",decoct($gid)),
						sprintf("%011s ",decoct(($type === 0) ? $filesize : 0)),sprintf("%11s",decoct($update_date)));
		$last = pack("a1a100a6a2a32a32a8a8a155a12",$type,null,null,null,null,null,null,null,null,null);
		for($i=0;$i<strlen($first);$i++) $checksum += ord($first[$i]);
		for($i=0;$i<strlen($last);$i++) $checksum += ord($last[$i]);
		return $first.pack("a8",sprintf("%6s ",decoct($checksum))).$last;
	}
	/**
	 * tarを解凍する
	 * @param string $src tar文字列
	 * @param string $outpath 展開先のファイルパス
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 * @return string{} 展開されたファイル情報
	 */
	static public function untar($src,$outpath=null,$permission=null){
		$result = array();
		$isout = !empty($outpath);
		for($pos=0,$vsize=0,$cur="";;){
			$buf = substr($src,$pos,512);
			if(strlen($buf) < 512) break;
			$data = unpack("a100name/a8mode/a8uid/a8gid/a12size/a12mtime/"
							."a8chksum/"
							."a1typeflg/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix",
							 $buf);
			$pos += 512;
			if(!empty($data["name"])){
				$obj = new stdClass();
				$obj->type = (int)$data["typeflg"];
				$obj->path = $data["name"];
				$obj->update = base_convert($data["mtime"],8,10);

				switch($obj->type){
					case self::TAR_TYPE_FILE:
						$obj->size = base_convert($data["size"],8,10);
						$obj->content = substr($src,$pos,$obj->size);
						$pos += (ceil($obj->size / 512) * 512);
						if($isout) self::write(self::absolute($outpath,$obj->path),$obj->content,$permission,$obj->update);
						break;
					case self::TAR_TYPE_DIR:
						if($isout) self::mkdir(self::absolute($outpath,$obj->path),$permission);
						break;
				}
				if(!$isout) $result[$obj->path] = $obj;
			}
		}
		return $result;
	}
	/**
	 * tar.gz(tgz)圧縮してファイル書き出しを行う
	 *
	 * @param string $tgz_filename
	 * @param string $path
	 * @param string $base_dir
	 */
	static public function tgz($tgz_filename,$path,$base_dir=null,$ignore_pattern=null,$permission=null){
		self::gzwrite($tgz_filename,self::tar($path,$base_dir,$ignore_pattern),$permission);
	}
	/**
	 * tar.gz(tgz)を解凍してファイル書き出しを行う
	 * @param string $inpath 解凍するファイルパス
	 * @param string $outpath 解凍先のファイルパス
	 * @param integer $permission フォルダを作成する際のアクセス権限、8進数(0755)で入力する
	 */
	static public function untgz($inpath,$outpath,$permission=null){
		$tmp = false;
		if(strpos($inpath,"://") !== false && (boolean)ini_get("allow_url_fopen")){
			$tmpname = self::absolute($outpath,self::temp_path($outpath));
			$http = new Http();
			try{
				$http->do_download($inpath,$tmpname);
				if($http->status() !== 200) throw new InvalidArgumentException($inpath.' not found');
			}catch(ErrorException $e){
				 throw new InvalidArgumentException(sprintf("permission denied[%s]",$tmpname));
			}
			$inpath = $tmpname;
			$tmp = true;
		}
		self::untar(self::gzread($inpath),$outpath,$permission);
		if($tmp) self::rm($inpath);
	}
	private static function parse_filename($filename){
		$filename = preg_replace("/[\/]+/","/",str_replace("\\","/",trim($filename)));
		return (substr($filename,-1) == "/") ? substr($filename,0,-1) : $filename;
	}
	/**
	 * 絶対パスを取得
	 * @param string $baseUrl ベースとなるパス
	 * @param string $targetUrl 対象となる相対パス
	 * @return string
	 */
	static public function absolute($baseUrl,$targetUrl){
		/***
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","/doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","../doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/","./doc/ja/index.html"));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute("http://www.rhaco.org/doc/ja/","./index.html"));
			eq("http://www.rhaco.org/doc/index.html",File::absolute("http://www.rhaco.org/doc/ja","./index.html"));
			eq("http://www.rhaco.org/doc/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja/","../././.././index.html"));
			eq("/www.rhaco.org/doc/index.html",File::absolute("/www.rhaco.org/doc/ja/","../index.html"));
			eq("/www.rhaco.org/index.html",File::absolute("/www.rhaco.org/doc/ja/","../../index.html"));
			eq("/www.rhaco.org/index.html",File::absolute("/www.rhaco.org/doc/ja/","../././.././index.html"));
			eq("c:/www.rhaco.org/doc/index.html",File::absolute("c:/www.rhaco.org/doc/ja/","../index.html"));
			eq("http://www.rhaco.org/index.html",File::absolute("http://www.rhaco.org/doc/ja","/index.html"));

			eq("/www.rhaco.org/doc/ja/action.html/index.html",File::absolute('/www.rhaco.org/doc/ja/action.html', 'index.html'));
			eq("http://www.rhaco.org/doc/ja/index.html",File::absolute('http://www.rhaco.org/doc/ja/action.html', 'index.html'));
			eq("http://www.rhaco.org/doc/ja/sample.cgi?param=test",File::absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key', '?param=test'));
			eq("http://www.rhaco.org/doc/index.html",File::absolute('http://www.rhaco.org/doc/ja/action.html', '../../index.html'));
			eq("http://www.rhaco.org/?param=test",File::absolute('http://www.rhaco.org/doc/ja/sample.cgi?query=key', '../../../?param=test'));
			eq("/doc/ja/index.html",File::absolute("/","/doc/ja/index.html"));
			eq("/index.html",File::absolute("/","index.html"));
			eq("http://www.rhaco.org/login",File::absolute("http://www.rhaco.org","/login"));
			eq("http://www.rhaco.org/login",File::absolute("http://www.rhaco.org/login",""));
			eq("http://www.rhaco.org/login.cgi",File::absolute("http://www.rhaco.org/logout.cgi","login.cgi"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/logout.cgi","login.cgi"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/#abc/aa","login.cgi"));
			eq("http://www.rhaco.org/hoge/abc.html#login",File::absolute("http://www.rhaco.org/hoge/abc.html","#login"));
			eq("http://www.rhaco.org/hoge/abc.html#login",File::absolute("http://www.rhaco.org/hoge/abc.html#logout","#login"));
			eq("http://www.rhaco.org/hoge/abc.html?abc=aa#login",File::absolute("http://www.rhaco.org/hoge/abc.html?abc=aa#logout","#login"));
			eq("http://www.rhaco.org/hoge/abc.html",File::absolute("http://www.rhaco.org/hoge/abc.html","javascript::alert('')"));
			eq("http://www.rhaco.org/hoge/abc.html",File::absolute("http://www.rhaco.org/hoge/abc.html","mailto::hoge@rhaco.org"));
			eq("http://www.rhaco.org/hoge/login.cgi",File::absolute("http://www.rhaco.org/hoge/?aa=bb/","login.cgi"));
			eq("http://www.rhaco.org/login",File::absolute("http://rhaco.org/hoge/hoge","http://www.rhaco.org/login"));
			eq("http://localhost:8888/spec/css/style.css",File::absolute("http://localhost:8888/spec/","./css/style.css"));
		 */
		$targetUrl = str_replace("\\","/",$targetUrl);
		if(empty($targetUrl)) return $baseUrl;
		$baseUrl = str_replace("\\","/",$baseUrl);
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$targetUrl)) return $targetUrl;
		$isnet = preg_match("/^[\w]+\:\/\/[^\/]+/",$baseUrl,$basehost);
		$isroot = (substr($targetUrl,0,1) == "/");
		if($isnet){
			if(strpos($targetUrl,"javascript:") === 0 || strpos($targetUrl,"mailto:") === 0) return $baseUrl;
			$preg_cond = ($targetUrl[0] === "#") ? "#" : "#\?";
			$baseUrl = preg_replace("/^(.+?)[".$preg_cond."].*$/","\\1",$baseUrl);
			if($targetUrl[0] === "#" || $targetUrl[0] === "?") return $baseUrl.$targetUrl;
			if(substr($baseUrl,-1) !== "/"){
				if(substr($targetUrl,0,2) === "./"){
					$targetUrl = ".".$targetUrl;
				}else if($targetUrl[0] !== "." && $targetUrl[0] !== "/"){
					$targetUrl = "../".$targetUrl;
				}
			}
		}
		if(empty($baseUrl) || preg_match("/^[a-zA-Z]\:/",$targetUrl) || (!$isnet && $isroot) || preg_match("/^[\w]+\:\/\/[^\/]+/",$targetUrl)) return $targetUrl;
		if($isnet && $isroot && isset($basehost[0])) return $basehost[0].$targetUrl;

		$baseUrl = preg_replace(self::$p_src_list,self::$p_dst_list,str_replace(self::$src_list,self::$dst_list,$baseUrl));
		$targetUrl = preg_replace(self::$p_src_list,self::$p_dst_list,str_replace(self::$src_list,self::$dst_list,$targetUrl));
		$basedir = $targetdir = $rootpath = "";

		if(strpos($baseUrl,"#REMOTEPATH#")){
			list($rootpath)	= explode("/",$baseUrl);
			$baseUrl = substr($baseUrl,strlen($rootpath));
			$targetUrl = str_replace("#ROOT#","",$targetUrl);
		}
		$baseList = preg_split("/\//",$baseUrl,-1,PREG_SPLIT_NO_EMPTY);
		$targetList = preg_split("/\//",$targetUrl,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($baseList)-substr_count($targetUrl,"../");$i++){
			if($baseList[$i] != "." && $baseList[$i] != "..") $basedir .= $baseList[$i]."/";
		}
		for($i=0;$i<sizeof($targetList);$i++){
			if($targetList[$i] != "." && $targetList[$i] != "..") $targetdir .= "/".$targetList[$i];
		}
		$targetdir = (!empty($basedir)) ? substr($targetdir,1) : $targetdir;
		$basedir = (!empty($basedir) && substr($basedir,0,1) != "/" && substr($basedir,0,6) != "#ROOT#" && !strpos($basedir,"#WINPATH#")) ? "/".$basedir : $basedir;
		return str_replace(self::$r_src_list,self::$r_dst_list,$rootpath.$basedir.$targetdir);
	}

	/**
	 * 相対パスを取得
	 * @param string $baseUrl ベースのファイルパス
	 * @param string $targetUrl ファイルパス
	 * @return string
	 */
	static public function relative($baseUrl,$targetUrl){
		/***
			eq("./overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("../overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/overview.html"));
			eq("../../overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/overview.html"));
			eq("../en/overview.html",File::relative("http://www.rhaco.org/doc/ja/","http://www.rhaco.org/doc/en/overview.html"));
			eq("./doc/ja/overview.html",File::relative("http://www.rhaco.org/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("./ja/overview.html",File::relative("http://www.rhaco.org/doc/","http://www.rhaco.org/doc/ja/overview.html"));
			eq("http://www.goesby.com/user.php/rhaco",File::relative("http://www.rhaco.org/doc/ja/","http://www.goesby.com/user.php/rhaco"));
			eq("./doc/ja/overview.html",File::relative("/www.rhaco.org/","/www.rhaco.org/doc/ja/overview.html"));
			eq("./ja/overview.html",File::relative("/www.rhaco.org/doc/","/www.rhaco.org/doc/ja/overview.html"));
			eq("/www.goesby.com/user.php/rhaco",File::relative("/www.rhaco.org/doc/ja/","/www.goesby.com/user.php/rhaco"));
			eq("./ja/overview.html",File::relative("c:/www.rhaco.org/doc/","c:/www.rhaco.org/doc/ja/overview.html"));
			eq("c:/www.goesby.com/user.php/rhaco",File::relative("c:/www.rhaco.org/doc/ja/","c:/www.goesby.com/user.php/rhaco"));
			eq("./Documents/workspace/prhagger/__settings__.php",File::relative("/Users/kaz/","/Users/kaz/Documents/workspace/prhagger/__settings__.php"));
			eq("./",File::relative("C:/xampp/htdocs/rhaco/test/template/sub","C:/xampp/htdocs/rhaco/test/template/sub"));
			eq("./",File::relative('C:\xampp\htdocs\rhaco\test\template\sub','C:\xampp\htdocs\rhaco\test\template\sub'));
		 */
		$baseUrl = preg_replace(self::$p_src_list,self::$p_dst_list,str_replace(self::$src_list,self::$dst_list,str_replace("\\","/",$baseUrl)));
		$targetUrl = preg_replace(self::$p_src_list,self::$p_dst_list,str_replace(self::$src_list,self::$dst_list,str_replace("\\","/",$targetUrl)));
		$filename = $url = "";
		$counter = 0;

		if(preg_match("/^(.+\/)[^\/]+\.[^\/]+$/",$baseUrl,$null)) $baseUrl = $null[1];
		if(preg_match("/^(.+\/)([^\/]+\.[^\/]+)$/",$targetUrl,$null)) list($tmp,$targetUrl,$filename) = $null;
		if(substr($baseUrl,-1) == "/") $baseUrl = substr($baseUrl,0,-1);
		if(substr($targetUrl,-1) == "/") $targetUrl = substr($targetUrl,0,-1);
		$baseList = explode("/",$baseUrl);
		$targetList = explode("/",$targetUrl);
		$baseSize = sizeof($baseList);

		if($baseList[0] != $targetList[0]) return str_replace(self::$r_src_list,self::$r_dst_list,$targetUrl);
		foreach($baseList as $key => $value){
			if(!isset($targetList[$key]) || $targetList[$key] != $value) break;
			$counter++;
		}
		for($i=sizeof($targetList)-1;$i>=$counter;$i--) $filename = $targetList[$i]."/".$filename;
		if($counter == $baseSize) return sprintf("./%s",$filename);
		return sprintf("%s%s",str_repeat("../",$baseSize - $counter),$filename);
	}
	/**
	 * フォルダ名の配列を取得
	 * @param string $directory  検索対象のファイルパス
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return string[]
	 */
	static public function dir($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)) return new FileIterator($directory,0,$recursive,$a);
		throw new InvalidArgumentException("invalid path ".$directory);
	}
	/**
	 * 指定された$directory内のファイル情報をFileとして配列で取得
	 * @param string $directory  検索対象のファイルパス 
	 * @param boolean $recursive 階層を潜って取得するか
	 * @param boolean $a 隠しファイルも参照するか
	 * @return File[]
	 */
	static public function ls($directory,$recursive=false,$a=false){
		$directory = self::parse_filename($directory);
		if(is_file($directory)) $directory = dirname($directory);
		if(is_readable($directory) && is_dir($directory)){
			return new FileIterator($directory,1,$recursive,$a);
		}
		throw new InvalidArgumentException("invalid path ".$directory);
	}
	/**
	 * ファイルパスからディレクトリ名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function dirname($path){
		$dir_name = dirname(str_replace("\\","/",$path));
		$len = strlen($dir_name);
		return ($len === 1 || ($len === 2 && $dir_name[1] === ":")) ? null : $dir_name;
	}
	/**
	 * フルパスからファイル名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function basename($path){
		$basename = basename($path);
		$len = strlen($basename);
		return ($len === 1 || ($len === 2 && $basename[1] === ":")) ? null : $basename;
	}
	/**
	 * ディレクトリでユニークなファイル名を返す
	 * @param $dir
	 * @param $prefix
	 * @return string
	 */
	static public function temp_path($dir,$prefix=null){
		if(is_dir($dir)){
			if(substr(str_replace("\\","/",$dir),-1) != "/") $dir .= "/";
			while(is_file($dir.($path = uniqid($prefix,true))));
			return $path;
		}
		return uniqid($prefix,true);
	}
	/**
	 * パスの前後にスラッシュを追加／削除を行う
	 * @param string $path ファイルパス
	 * @param boolean $prefix 先頭にスラッシュを存在させるか
	 * @param boolean $postfix 末尾にスラッシュを存在させるか
	 * @return string
	 */	
	static public function path_slash($path,$prefix,$postfix){
		if(!empty($path)){
			if($prefix === true){
				if($path[0] != '/') $path = '/'.$path;
			}else if($prefix === false){
				if($path[0] == '/') $path = substr($path,1);
			}
			if($postfix === true){
				if(substr($path,-1) != '/') $path = $path.'/';
			}else if($postfix === false){
				if(substr($path,-1) == '/') $path = substr($path,0,-1);
			}
		}
		return $path;
		/***
			eq("/abc/",self::path_slash("/abc/",null,null));
			eq("/abc/",self::path_slash("abc",true,true));
			eq("/abc/",self::path_slash("/abc/",true,true));
			eq("abc/",self::path_slash("/abc/",false,true));			
			eq("/abc",self::path_slash("/abc/",true,false));
			eq("abc",self::path_slash("/abc/",false,false));
		 */
	}
}