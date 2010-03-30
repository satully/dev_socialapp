<?php
/**
 * setup制御
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Setup extends Object{
	static private $cmds = array();
	/**
	 * poファイルからmoファイルを作成する
	 * @param string $po_filename 読み込むpoファイルのパス
	 * @param string $mo_filename 出力するmoファイルのパス
	 * @return string output path
	 */
	static public function generate_mo($po_filename,$mo_filename=null){
		if(!is_file($po_filename)) throw new InvalidArgumentException($po_filename.": No such file");
		$file = new File($po_filename);
		$output_path = (empty($mo_filename)) ? $file->directory().$file->oname().".mo" : $mo_filename;
		$po_list = self::po_read($po_filename);
		$count = sizeof($po_list);
		$ids = implode("\0",array_keys($po_list))."\0";
		$keyoffset = 28 + 16 * $count;
		$valueoffset = $keyoffset + strlen($ids);
		$value_src = "";

		$output_src = pack('Lllllll',0x950412de,0,$count,28,(28 + ($count * 8)),0,0);
		$output_values = array();
		foreach($po_list as $id => $values){
			$len = strlen($id);
			$output_src .= pack("l",$len);
			$output_src .= pack("l",$keyoffset);
			$keyoffset += $len + 1;

			$value = implode("\0",$values);
			$len = strlen($value);
			$value_src .= pack("l",$len);
			$value_src .= pack("l",$valueoffset);
			$valueoffset += $len + 1;

			$output_values[] = $value;
		}
		$output_src .= $value_src;
		$output_src .= $ids;
		$output_src .= implode("\0",$output_values)."\0";
		File::write($output_path,$output_src);
		return $output_path;
	}
	static private function po_read($po_filename){
		$file = new File($po_filename);
		$po_list = array();
		$msgId = "";
		$isId = false;
		$plural_no = 0;

		foreach(explode("\n",$file->get()) as $line){
			if(!preg_match("/^[\s]*#/",$line)){
				if(preg_match("/msgid_plural[\s]+([\"\'])(.+)\\1/",$line,$match)){
					$msgId = str_replace("\\n","\n",$match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgid[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$msgId = str_replace("\\n","\n",$match[2]);
					$isId = true;
					$plural_no = 0;
				}else if(preg_match("/msgstr\[(\d+)\][\s]+([\"\'])(.*?)\\2/",$line,$match)){
					$plural_no = (int)$match[1];
					$po_list[$msgId][$plural_no] = str_replace("\\n","\n",$match[3]);
					$isId = false;
					ksort($po_list[$msgId]);
				}else if(preg_match("/msgstr[\s]+([\"\'])(.*?)\\1/",$line,$match)){
					$po_list[$msgId][$plural_no] = str_replace("\\n","\n",$match[2]);
					$isId = false;
				}else if(preg_match("/([\"\'])(.+)\\1/",$line,$match)){
					if($isId){
						$msgId .= str_replace("\\n","\n",$match[2]);
					}else{
						if(!isset($po_list[$msgId][$plural_no])) $po_list[$msgId][$plural_no] = "";
						$po_list[$msgId][$plural_no] .= str_replace("\\n","\n",$match[2]);
					}
				}
			}
		}
		ksort($po_list,SORT_STRING);
		return $po_list;
	}
	/**
	 * potファイルを作成する
	 * @param string $path potを生成する対象のルートパス
	 * @param string $lc_messages_path potファイルを出力するパス
	 * @return string potファイルを出力したパス
	 */
	static public function generate_pot($path,$lc_messages_path=null){
		if(empty($lc_messages_path)) $lc_messages_path = "messages.pot";
		$messages = array();
		foreach(File::ls($path,true) as $file){
			if($file->size() < (1024 * 1024)){
				$src = File::read($file);
				foreach(explode("\n",$src) as $line => $value){
					if(preg_match_all("/__\(([\"\'])(.+?)\\1/",$value,$match)){
						foreach($match[2] as $msg) $messages[$msg]["#: ".str_replace($path,"",$file->fullname()).":".($line + 1)] = true;
					}
					if(preg_match_all("/App::trans\(([\"\'])(.+?)\\1/",$value,$match)){
						foreach($match[2] as $msg) $messages[$msg]["#: ".str_replace($path,"",$file->fullname()).":".($line + 1)] = true;
					}
				}
			}
		}
		ksort($messages,SORT_STRING);
		$output_src = sprintf(Text::plain('
						# SOME DESCRIPTIVE TITLE.
						msgid ""
						msgstr ""
						"Project-Id-Version: PACKAGE VERSION\n"
						"Report-Msgid-Bugs-To: \n"
						"POT-Creation-Date: %s\n"
						"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"
						"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
						"Language-Team: LANGUAGE <team@exsample.com>\n"
						"Plural-Forms: nplurals=1; plural=0;\n"
				'),date("Y-m-d H:iO"))."\n\n";
		foreach($messages as $str => $lines){
			$output_src .= "\n".implode("\n",array_keys($lines))."\n";
			$output_src .= "msgid \"".$str."\"\n";
			$output_src .= "msgstr \"\"\n";
		}
		if(is_file($lc_messages_path)) throw new InvalidArgumentException($lc_messages_path.": File exists");
		File::write($lc_messages_path,$output_src);
		return $lc_messages_path;
	}
	/**
	 * アプリケーションをinstallする
	 * @param string $package パッケージパス
	 * @param string $output_path install先
	 */
	static public function download($package,$output_path=null){
		if(empty($output_path)) $output_path = App::path();
		Repository::download("app",$package,$output_path);
	}
	/**
	 * setupを開始する
	 */
	static public function start(){
		Repository::load_map(getcwd()."/__repository__.xml");
		$req = new Request("_inc_session_=false");
		
		if(!$req->is_cli()) exit;
		if(!is_file(File::absolute(getcwd(),"__settings__.php"))){
			$app_install = Command::stdin("install application");
			if(!empty($app_install)){
				try{
					self::download($app_install);
				}catch(RuntimeException $e){
					self::error_print("not foud application ".$app_install);
					exit;
				}
			}
		}
		$settings_path = File::absolute(getcwd(),"__settings__.php");
		if(!is_file($settings_path)){
			$ref = new ReflectionClass("Object");
			$jump_path = str_replace("\\","/",dirname(dirname($ref->getFileName())));				
			$pwd = str_replace("\\","/",getcwd());
			$url = Command::stdin("application url","http://localhost/".basename(getcwd()));
			if(!empty($url) && substr($url,-1) != "/") $url .= "/";
			$work = Command::stdin("working directory",App::work());
			$mode = Command::stdin("application mode");
			App::config_path($pwd,$url,$work,$mode);

			$config = sprintf(Text::plain('
								<?php
								require_once("%s/jump.php");
								App::config_path(__FILE__,"%s","%s","%s");
							'),$jump_path,$url,$work,$mode
						);
			File::write($settings_path,$config."\n");
		}else{
			$maxlen = 0;
			$cmd = $value = null;

			if($req->is_vars()){
				$keys = array_keys($req->vars());
				$cmd = array_shift($keys);
				$value = $req->in_vars($cmd);
			}
			self::search_cmd($req,$cmd,$value,$maxlen,__CLASS__);
			foreach(Lib::classes(true,true) as $path => $name){				
				self::search_cmd($req,$cmd,$value,$maxlen,$path);
			}
			self::info($maxlen);
		}
	}
	static private function cmd_info($cmd){
		self::println("Usage:");
		self::println("  ".trim(str_replace("\n","\n  ",self::$cmds[$cmd][3])));
		exit;
	}
	static private function info($maxlen){
		ksort(self::$cmds);
		$app_info = App::info();
		self::println(self::fcolor($app_info["name"],"1;35").((empty($app_info["summary"]) ? "" : ", ".$app_info["summary"]."."))."\n");
		
		$desc = Text::plain($app_info["description"]);
		if(!empty($desc)){
			self::println(str_repeat("=",50));
			self::println($desc);
			self::println(str_repeat("=",50));
			self::println("");
		}
		self::info_print("try 'php setup.php -h *****' for more information");
		foreach(self::$cmds as $name => $m){
			list($line) = explode("\n",$m[2]);
			self::println("  ".str_pad($name,$maxlen)." : ".$line);
		}
	}
	static private function search_cmd($req,$cmd,$value,&$maxlen,$class_name){
		$ref = new InfoClass($class_name);
		foreach($ref->setup() as $n => $m){
			$op = substr($n,8,-2);
			self::$cmds[$op] = array($class_name,$n,$m->summary(),$m->document());
			if(strlen($op) > $maxlen) $maxlen = strlen($op);
		}
		if(isset($cmd) && (isset(self::$cmds[$cmd]) || ($cmd === "h" && isset(self::$cmds[$value])))){
			if($cmd === "h"){
				self::cmd_info($value);
			}else{
				try{
					call_user_func_array(array(Lib::import(self::$cmds[$cmd][0]),self::$cmds[$cmd][1]),array($req,$value));
				}catch(Exception $e){
					self::error_print("  ".$e->getMessage());
				}
			}
			exit;
		}
	}
	/**
	 * potファイルを生成する
	 * -pot [search path] [-o [output path]]
	 */
	static public function __setup_pot__(Request $req,$value){
		if(empty($value)) $value = App::path();
		self::info_print(" generate pot file ".self::generate_pot($value,$req->in_vars("o")));
	}
	/**
	 * moファイルを生成する
	 * -mo ****.po  [-o [output path]]
	 */
	static public function __setup_mo__(Request $req,$value){
		self::info_print(" generate mo file ".self::generate_mo($value,$req->in_vars("o")));
	}
	/**
	 * testを実行する
	 *  -test クラス名
	 *  option:
	 *  	-m メソッド名
	 *  	-m メソッド名 -b ブロック名
	 *  	-fail failを表示する
	 *  	-succes succesを表示する
	 *  	-none noneを表示する 
	 * @summary execute test
	 */
	static public function __setup_test__(Request $req,$value){
		$level = ($req->is_vars('fail') ? Test::FAIL : 0) | ($req->is_vars('success') ? Test::SUCCESS : 0) | ($req->is_vars('none') ? Test::NONE : 0);
		if($level === 0) $level = (Test::FAIL|Test::SUCCESS|Test::NONE);
		Test::exec_type($level|Test::COUNT);
		if(empty($value)){
			Test::verifies();
		}else{
			Test::verify($value,$req->in_vars("m"),$req->in_vars("b"));
		}
		Test::flush();
	}
	/**
	 * vendorsをすべて更新する
	 * -cでcoreも更新する
	 * 
	 * -up [-c]
	 */
	static public function __setup_up__(Request $req,$value){
		if($req->is_vars("c") && defined("_JUMP_PATH_")){
			if(empty($value) && defined("_CORE_URL_")) $value = constant("_CORE_URL_");
			if(Http::request_status($value) === 404) throw new InvalidArgumentException($value." not found");
			File::rm(constant("_JUMP_PATH_"),false);
			File::untgz($value,constant("_JUMP_PATH_"));
			self::info_print('core updated');
		}
		Lib::vendors_update();
		self::info_print('vendors updated');
	}
	/**
	 * Repositoryからライブラリパッケージをimportする
	 * -import org.rhaco.storage.db.Dao
	 */
	static public function __setup_import__(Request $req,$value){
		if(empty($value)) self::cmd_info("import");
		try{
			Lib::import($value);
			self::info_print('imported '.$value);
		}catch(RuntimeException $e){
			self::error_print($e->getMessage());
		}
	}
	/**
	 * Repositoryからアプリケーションをインストールする
	 * -install org.rhaco.sample.hello_world
	 */
	static public function __setup_install__(Request $req,$value){
		if(empty($value)) self::cmd_info("install");
		try{
			Setup::download($value);
			self::info_print('installed '.$value);
		}catch(RuntimeException $e){
			self::error_print($e->getMessage());
		}
	}
	/**
	 * repositoryのtgzをexportする
	 * -re [output_path]
	 */
	static public function __setup_re__(Request $req,$value){
		Repository::export($req->in_vars("re",App::work("repository")));
	}
	/**
	 * ソースドキュメントを表示する
	 * -man クラス名 [-m メソッド名]
	 */
	static public function __setup_man__(Request $req,$value){
		if(empty($value)){
			$libs = array_keys(Lib::classes(true,true));
			asort($libs);
			$len = Text::length($libs);
	
			self::info_print("  Imported classes::");
			foreach($libs as $path){
				$ref = new InfoClass($path,false);
				self::println("    ".str_pad($path,$len)." : ".$ref->summary());
			}
		}else{
			self::println(InfoClass::help($value,$req->in_vars("m"),false));
		}
	}
	/**
	 * 定義名のリスト
	 * @param Request $req
	 * @param string $value
	 */
	static public function __setup_def__(Request $req,$value){
		$libs = array_keys(Lib::classes(true,true));
		$list = array();
		foreach($libs as $path){
			$ref = new InfoClass($path,false);
			foreach($ref->def() as $def) $list[$def->name()] = '('.$def->fm_type().') '.$def->document();
		}
		$len = Text::length(array_keys($list));

		self::info_print("Define list:");		
		foreach($list as $k => $v){
			self::println("    ".str_pad($k,$len)." : ".$v);
		}
	}
	/**
	 * Repository serverファイルを生成する
	 */
	static public function __setup_write_re__(Request $req,$value){
		$pwd = str_replace("\\","/",getcwd());
		File::write($pwd."/__repository__.php"
						,"<?php require_once(\"".$pwd."/__settings__.php\"); Repository::handler();"
					);
	}
	/**
	 * .htaccess (RewriteBase)を生成する
	 * -htaccess [base_url]
	 * -htaccess -add map
	 */
	static public function __setup_htaccess__(Request $req,$value){
		$path = str_replace("\\","/",getcwd())."/.htaccess";
		if(substr($value,0,1) !== "/") $value = "/".$value;
		if(!is_file($path) || !$req->is_vars("add")){
			File::write($path
								,"RewriteEngine On\n"
								."RewriteBase ".$value."\n"
								
								."RewriteCond %{REQUEST_FILENAME} !-f\n"
								."RewriteRule ^(.+)\$ index.php/\$1?%{QUERY_STRING} [L]\n"
						);
			self::info_print('write .htaccess');
		}
		if($req->is_vars("add")){
			$rules = array("RewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^"
							.$req->in_vars("add")."[/]{0,1}(.*)\$ "
							.$req->in_vars("add").".php/\$1?%{QUERY_STRING}&application_branch="
							.$req->in_vars("add")." [L]");
			$src = File::read($path);
			if(preg_match_all("/Rewrite(Cond|Rule).+/",$src,$match)){
				foreach($match[0] as $rule){
					$rules[] = $rule;
					$src = str_replace($rule,"",$src);
				}
				$src = trim($src);
			}
			$src .= "\n\n".implode("\n",$rules);
			File::write($path,$src);
			self::info_print('added .htaccess');
		}
	}
	/**
	 * アプリケーションXMLのひな形の作成
	 * -app [index]
	 */
	static public function __setup_app__(Request $req,$value){
		if(empty($value)) $value = "index";
		$path = str_replace("\\","/",getcwd())."/".$value.".php";
		if(is_file($path)) throw new InvalidArgumentException($path.": File exists");
		File::write($path
						,"<?php require dirname(__FILE__).\"/__settings__.php\"; app(); ?>\n"
						."<app name=\"application name\" summary=\"summary\">\n"
							."\t<description>description</description>\n"
							."\t<handler>\n"
							."\t<map url=\"\" template=\"index.html\" summary=\"map summary\" />\n"
							."\t</handler>\n"
						."</app>\n"
					);
		self::info_print('write '.$path);
		if($req->is_vars('htaccess')){
			$req->vars('add',$value);
			self::__setup_htaccess__($req,$req->in_vars('htaccess'));
		}
	}
	/**
	 * Repositoryが提供するアプリケーションとライブラリの一覧を表示する
	 * -rep rhaco.org
	 */
	static public function __setup_rep__(Request $req,$value){
		$http = new Http();
		$q = $req->in_vars("q");

		foreach(array("lib"=>"Libraries:","app"=>"\nApplications:") as $type => $label){
			if(Tag::setof($tag,$http->do_get(Repository::xml_url($type,$value)),"repository")){
				$list = array();
				$max_length = 0;
				foreach($tag->in("package") as $p){
					if(empty($q) || Text::imatch($p->in_param("name")." ".$p->in_param("path")." ".$p->in_param("summary")." ".$p->value(),$q)){
						$list[] = $p;
						if($max_length < strlen($p->in_param("path"))) $max_length = strlen($p->in_param("path"));
					}
				}
				if(!empty($list)){
					self::info_print($label);
					foreach($list as $p){
						self::println("  ".str_pad($p->in_param("path"),$max_length)." ".$p->in_param("summary"));
					}
				}
			}
		}
	}
	/**
	 * デバッグアウトを制御する
	 * on / off
	 * -level debug / info / warn /error
	 * -exception on / off
	 */
	static public function __setup_log__(Request $req,$value){
		$src = File::read(App::path("__settings__.php"));

		$level = $req->in_vars("level",Log::current_level());
		$log = 'Log::config_level("'.$level.'",'.(($value == "on") ? "true" : "false").');';
		if(preg_match("/Log::config_level\(.+;/",$src,$match)){
			$src = str_replace($match[0],$log,$src);
		}else{
			$src = $src."\n".$log;
		}
		if($req->is_vars("exception")){
			$log = 'Log::config_exception_trace('.(($req->in_vars("exception") == "on") ? "true" : "false").');';
			if(preg_match("/Log::config_exception_trace\(.+;/",$src,$match)){
				$src = str_replace($match[0],$log,$src);
			}else{
				$src = $src."\n".$log;
			}
		}
		self::update_settings($src);
	}
	/**
	 * エラーを標準出力に出力するようにする
	 * -display_errors on
	 * -display_errors off
	 * 
	 * @param $req
	 * @param $value
	 */
	static public function __setup_display_errors__(Request $req,$value){
		$src = File::read(App::path("__settings__.php"));
		$disp = "display_errors();\n";
		if(preg_match("/display_errors\(.+;/",$src,$match)){
			$src = str_replace($match[0],(($value == 'on') ? $disp : ""),$src);
		}else{
			$src = $src."\n".$disp;
		}
		self::update_settings($src);
	}
	
	static private function update_settings($src){
		File::write(App::path("__settings__.php"),$src);
		self::info_print('update __settings__.php');
	}
	/**
	 * エラー出力を制御する
	 * -error on [-html [on/off]]
	 * 
	 * @param Request $req
	 * @param string $value on/off
	 */
	static public function __setup_error__(Request $req,$value){
		$src = File::read(App::path("__settings__.php"));
		$log = 'display_errors('.(($req->in_vars('html') == 'on') ? 'true' : 'false').');';
		if(preg_match("/display_errors\(.+;/",$src,$match)){
			$src = str_replace($match[0],(($value == 'on') ? $log : ''),$src);
		}else if($value == 'on'){
			$src = $src."\n".$log;
		}
		self::update_settings($src);
	}
	/**
	 * モードを変更する
	 * -mode モード名
	 * @param Request $req
	 * @param string $value mode
	 */
	static public function __setup_mode__(Request $req,$value){
		self::app_config_update(App::url(),App::work(),$value);
		self::info_print('mode changed `'.$value.'`');
	}
	/**
	 * アプリケーションURLを変更する
	 * -url URL
	 * @param Request $req
	 * @param string $value url
	 */
	static public function __setup_url__(Request $req,$value){
		$path = File::absolute(App::url().'/',$value);
		self::app_config_update($path,App::work(),App::mode());
		self::info_print('url changed `'.$path.'`');
	}
	/**
	 * work dirを変更する
	 * -work PATH
	 * @param Request $req
	 * @param string $value PATH
	 */
	static public function __setup_work__(Request $req,$value){
		$path = File::absolute(App::work().'/',$value);
		self::app_config_update(App::url(),$path,App::mode());
		self::info_print('work dir changed `'.$path.'`');
	}
	static private function app_config_update($url,$work,$mode){
		$src = File::read(App::path("__settings__.php"));
		if(preg_match("/App::config_path\(.+/",$src,$match)){
			$src = str_replace($match[0],sprintf('App::config_path(__FILE__,"%s","%s","%s");',$url,$work,$mode),$src);
		}
		self::update_settings($src);
	}
	/**
	 * クラスモジュールを設定する
	 * ex.
	 *  -add_module Log -module org.rhaco.io.log.LogFile
	 * 
	 * @param Request $req
	 * @param string $value 設定するクラス
	 */
	static public function __setup_add_module__(Request $req,$value){
		$module = $req->in_vars("module");
		if(empty($module) || empty($value)) self::cmd_info("add_module");
		$src = File::read(App::path("__settings__.php"));

		Lib::import($value);
		Lib::import($module);
		if(preg_match("/C(R([\"']".$value."[\"']))->add_module(R([\"']".$module."[\"']))/",$src,$match)){
			$src = str_replace($match[0],sprintf('C(R("%s"))->add_module(R("%s"));',$value,$module),$src);
		}else{
			$src .= "\n".sprintf('C(R("%s"))->add_module(R("%s"));',$value,$module);
		}
		self::update_settings($src);
	}	
	static private function println($str){
		print($str."\n");
	}
	static private function fcolor($msg,$color="1;31"){
		return (php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg;
	}
	static private function info_print($msg){
		self::println(self::fcolor($msg,"1;34"));
	}
	static private function error_print($msg){
		self::println(self::fcolor($msg,"1;31"));
	}
}
