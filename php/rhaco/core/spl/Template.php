<?php
/**
 * テンプレートを処理する
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Template extends Object{
	static protected $__vars__ = 'type=mixed{}';
	static protected $__statics__ = 'type=string{}';
	static protected $__base_path__ = 'type=string';
	static protected $__media_url__ = 'type=string';
	static protected $__filename__ = 'type=string';

	static private $base_media_url;
	static private $base_template_path;
	static private $exception_str;
	static private $is_cache = false;

	protected $base_path; # テンプレートファイルのベースパス
	protected $media_url; # メディアファイルのベースパス

	protected $statics = array(); # スタティックでアクセスするコンテキストとなる値
	protected $vars = array(); # コンテキストとなる値
	protected $filename; # テンプレートファイル名
	private $selected_template;

	/**
	 * ベースパスの定義
	 * @param string $template_path テンプレートファイルのベースパス
	 * @param string $media_url メディアURLのベースパス
	 */
	static public function config_path($template_path,$media_url=null){
		self::$base_template_path = preg_replace("/^(.+)\/$/","\\1",str_replace("\\","/",$template_path))."/";
		self::$base_media_url = preg_replace("/^(.+)\/$/","\\1",$media_url)."/";
	}
	/**
	 * 例外時に表示する文字列の定義
	 * @param string $str 例外時に表示する文字列
	 */
	static public function config_exception($str){
		self::$exception_str = $str;
	}
	/**
	 * キャッシュするかの定義
	 * @param boolean $bool キャッシュするか
	 */
	static public function config_cache($bool){
		self::$is_cache = (boolean)$bool;
	}
	/**
	 * キャッシュが有効か
	 * @return boolean
	 */
	static public function is_cache(){
		return self::$is_cache;
	}
	/**
	 * テンプレートのパス
	 * @return string
	 */
	static public function base_template_path(){
		return isset(self::$base_template_path) ? self::$base_template_path : App::path('resources/templates').'/';
	}
	/**
	 * メディアURL
	 * @return string
	 */
	static public function base_media_url(){
		return isset(self::$base_media_url) ? self::$base_media_url : App::url('resources/media',false).'/';
	}
	protected function __set_statics__($name,$class){
		$this->statics[$name] = Lib::import($class);
	}
	protected function __new__($media_url=null,$base_path=null){
		$this->media_url($media_url);
		$this->base_path($base_path);
	}
	protected function __init__(){
		$this->vars('_t_',new Templf());
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}else{
				throw new InvalidArgumentException('cp');
			}
		}
	}
	protected function __set_base_path__($path){
		$this->base_path = File::absolute(self::base_template_path(),File::path_slash($path,null,true));
	}
	protected function __set_media_url__($url){
		$this->media_url = File::absolute(self::base_media_url(),File::path_slash($url,null,true));
	}
	protected function __get_filename__(){
		return File::absolute($this->base_path,$this->filename);
	}
	protected function __fm_filename__($path=null){
		return ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
	}
	protected function __is_filename__($path=null){
		$path = ($path === null) ? $this->filename() : File::absolute($this->base_path,$path);
		return (!empty($path) && is_file($path));
	}
	/**
	 * ファイルから生成する
	 * @param string $filename テンプレートファイルパス
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function read($filename=null,$template_name=null){
		if(!empty($filename)) $this->filename($filename);
		$this->selected_template = $template_name;
		$cfilename = $this->filename().$this->selected_template;

		if(!self::$is_cache || !Store::has($cfilename,true)){
			if(strpos($filename,'://') === false){
				$src = $this->parse(File::read($this->filename()));
			}else{
				if(empty($this->media_url)) $this->media_url($this->filename());
				$src = $this->parse(Http::read($this->filename()));
			}
			if(self::$is_cache) Store::set($cfilename,$src);
		}else{
			$src = Store::get($cfilename);
		}
		$src = $this->html_reform($this->exec($src));
		$this->call_module('after_read_template',$src,$this);
		return $this->replace_ptag($src);
	}
	/**
	 * 標準出力に出力する
	 *
	 * @param string $filename テンプレートファイルパス
	 * @param string $template_name 対象となるテンプレート名
	 */
	public function output($filename=null,$template_name=null){
		print($this->read($filename,$template_name));
		exit;
	}
	/**
	 * 文字列から生成する
	 * @param string $src テンプレート文字列
	 * @param string $template_name 対象となるテンプレート名
	 * @return string
	 */
	public function execute($src,$template_name=null){
		$this->selected_template = $template_name;
		$src = $this->replace_ptag($this->html_reform($this->exec($this->parse($src))));
		return $src;
		/***
			$src = text('
				<body>
					{$abc}{$def}
						{$ghi}	{$hhh["a"]}
					<a href="./hoge.html">{$abc}</a>
					<img src="../img/abc.png"> {$ooo.yyy}
					<form action="{$ooo.xxx}">
					</form>
				</body>
			');
			$result = text('
				<body>
					AAA
						B	1
					<a href="http://rhaco.org/tmp/hoge.html">AAA</a>
					<img src="http://rhaco.org/img/abc.png"> fuga
					<form action="index.php">
					</form>
				</body>
			');
			$obj = new stdClass();
			$obj->xxx = "index.php";
			$obj->yyy = "fuga";

			$template = new Template("http://rhaco.org/tmp");
			$template->vars("abc","AAA");
			$template->vars("def",null);
			$template->vars("ghi","B");
			$template->vars("ooo",$obj);
			$template->vars("hhh",array("a"=>1,"b"=>2));
			eq($result,$template->execute($src));
		*/
		/***
			#exception
			
			self::config_exception("EXCEPTION");
			$src = text('
						<html><body><input type="text" name="query" rt:ref="true" /></body></html>
					');
			$template = new Template();
			$result = '<html><body><input type="text" name="query" value="EXCEPTION" /></body></html>';
			eq($result,$template->execute($src));
		 */
	}
	private function replace_ptag($src){
		return str_replace(array('__PHP_TAG_ESCAPE_START__','__PHP_TAG_ESCAPE_END__'),array('<?','?>'),$src);
	}
	private function replace_xtag($src){
		if(preg_match_all("/<\?(?!php[\s\n])[\w]+ .*?\?>/s",$src,$null)){
			foreach($null[0] as $value) $src = str_replace($value,'__PHP_TAG_ESCAPE_START__'.substr($value,2,-2).'__PHP_TAG_ESCAPE_END__',$src);
		}
		return $src;
	}
	private function parse($src){
		$src = preg_replace("/([\w])\->/","\\1__PHP_ARROW__",$src);
		$src = $this->replace_xtag($src);
		$this->call_module('init_template',$src,$this);
		$src = $this->rtinvalid($this->rtcomment($this->rtblock($this->rttemplate($src),$this->filename())));
		$this->call_module('before_template',$src,$this);
		$src = $this->rtif($this->rtloop($this->rtunit($this->rtpager($this->html_form($this->html_list($src))))));
		$this->call_module('after_template',$src,$this);
		$src = str_replace('__PHP_ARROW__','->',$src);
		$src = $this->parse_message($src);
		$src = $this->parse_print_variable($src);
		foreach($this->statics as $key => $value) $src = $this->to_static_variable($value,$key,$src);
		$php = array(' ?>','<?php ','->');
		$str = array('PHP_TAG_END','PHP_TAG_START','PHP_ARROW');
		return str_replace($str,$php,$this->parse_url(str_replace($php,$str,$src),$this->media_url));
		/***
			$filter = create_class('
				public function init_template($src){
					$src = "====\n".$src."\n====";
				}
				public function before_template($src){
					$src = "____\n".$src."\n____";
				}
				public function after_template($src){
					$src = "####\n".$src."\n####";
				}
			');
			$src = text('
					hogehoge
				');
			$result = text('
					####
					____
					====
					hogehoge
					====
					____
					####
				');
			$template = new Template();
			$template->add_module(new $filter());
			eq($result,$template->execute($src));
		 */
	}
	final private function parse_url($src,$base){
		if(substr($base,-1) !== '/') $base = $base.'/';
		if(preg_match_all("/<[^<\n]+?[\s](src|href|background)[\s]*=[\s]*([\"\'])([^\\2\n]+?)\\2[^>]*?>/i",$src,$match)){
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$base,$match[0][$key],$match[3][$key]);
		}
		if(preg_match_all("/[^:]:[\040]*url\(([^\n]+?)\)/",$src,$match)){
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$base,$match[0][$key],$match[1][$key]);
		}
		return $src;
	}
	final private function replace_parse_url($src,$base,$dep,$rep){
		if(!preg_match("/(^[\w]+:\/\/)|(^PHP_TAG_START)|(^\{\\$)|(^javascript:)|(^mailto:)|(^[#\?])/",$rep)){
			$src = str_replace($dep,str_replace($rep,File::absolute($base,$rep),$dep),$src);
		}
		return $src;
	}
	final private function rttemplate($src){
		$values = array();
		$bool = false;
		while(Tag::setof($tag,$src,'rt:template')){
			$src = str_replace($tag->plain(),'',$src);
			$values[$tag->in_param('name')] = $tag->value();
			$src = str_replace($tag->plain(),'',$src);
			$bool = true;
		}
		if(!empty($this->selected_template)){
			if(!array_key_exists($this->selected_template,$values)) throw new LogicException('undef rt:template '.$this->selected_template);
			return $values[$this->selected_template];
		}
		return ($bool) ? implode($values) : $src;
		/***
			$template = new Template();
			$src = text('
				AAAA
				<rt:template name="aa">
					aa
				</rt:template>
				BBBB
				<rt:template name="bb">
					bb
				</rt:template>
				CCCC
				<rt:template name="cc">
					cc
				</rt:template>
			');
			eq("	bb\n",$template->execute($src,"bb"));
		 */
	}
	final private function rtblock($src,$filename){
		if(strpos($src,'rt:block') !== false || strpos($src,'rt:extends') !== false){
			$blocks = $paths = array();
			while(Tag::setof($xml,$this->rtcomment($src),'rt:extends')){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block){
					if(strtolower($block->name()) == 'rt:block'){
						$name = $block->in_param('name');
						if(!empty($name) && !array_key_exists($name,$blocks)){
							$blocks[$name] = $block->value();
							$paths[$name] = $filename;
						}
					}
				}
				if($xml->is_param('href')){
					$src = File::read($filename = File::absolute(dirname($filename),$xml->in_param('href')));
					$this->filename = $filename;
				}else{
					$src = File::read($this->filename());
				}
				$this->selected_template = $xml->in_param('name');
				$src = $this->rttemplate($this->replace_xtag($src));
			}
			if(empty($blocks)){
				$bxml = Tag::anyhow($src);
				foreach($bxml->in('rt:block') as $block) $src = str_replace($block->plain(),$block->value(),$src);
			}else{
				while(Tag::setof($xml,$src,'rt:block')){
					$xml = Tag::anyhow($src);
					foreach($xml->in('rt:block') as $block){
						$name = $block->in_param('name');
						$src = str_replace($block->plain(),(array_key_exists($name,$blocks) ? $blocks[$name] : $block->value()),$src);
					}
				}
			}
		}
		return $src;
		/***
			ftmp("template/base.html",'
					=======================
					<rt:block name="aaa">
					base aaa
					</rt:block>
					<rt:block name="bbb">
					base bbb
					</rt:block>
					<rt:block name="ccc">
					base ccc
					</rt:block>
					<rt:block name="ddd">
					base ddd
					</rt:block>
					=======================
				');
			ftmp("template/extends1.html",'
					<rt:extends href="base.html" />

					<rt:block name="aaa">
					extends1 aaa
					</rt:block>

					<rt:block name="ddd">
					extends1 ddd
					<rt:loop param="abc" var="ab" loop_counter="loop_counter" key="loop_key">
						{$loop_key}:{$loop_counter} {$ab}
					</rt:loop>
					<rt:if param="abc">
					aa
					</rt:if>
					<rt:if param="aa" value="1">
					bb
					</rt:if>
					<rt:if param="aa" value="2">
					bb
					<rt:else />
					cc
					</rt:if>
					<rt:if param="zz">
					zz
					</rt:if>
					<rt:if param="aa">
					aa
					</rt:if>
					<rt:if param="tt">
					true
					</rt:if>
					<rt:if param="ff">
					false
					</rt:if>
					</rt:block>
				');
			ftmp("template/sub/extends2.html",'
					<rt:extends href="../extends1.html" />

					<rt:block name="aaa">
					<a href="hoge/fuga.html">fuga</a>
					<a href="{$newurl}/abc.html">abc</a>
					sub extends2 aaa
					</rt:block>

					<rt:block name="ccc">
					sub extends2 ccc
					</rt:block>
				');

			$template = new Template("http://rhaco.org",tmp_path("template"));
			$template->vars("newurl","http://hoge.ho");
			$template->vars("abc",array(1,2,3));
			$template->vars("aa",1);
			$template->vars("zz",null);
			$template->vars("ff",false);
			$template->vars("tt",true);
			$result = $template->read("sub/extends2.html");
			$ex = text('
						=======================

						<a href="http://rhaco.org/hoge/fuga.html">fuga</a>
						<a href="http://hoge.ho/abc.html">abc</a>
						sub extends2 aaa


						base bbb


						sub extends2 ccc


						extends1 ddd
							0:1 1
							1:2 2
							2:3 3
						aa
						bb
						cc
						aa
						true

						=======================
					');
			eq($ex,$result);
		 */
	}
	final private function rtcomment($src){
		while(Tag::setof($tag,$src,'rt:comment')) $src = str_replace($tag->plain(),'',$src);
		return $src;
	}
	final private function rtunit($src){
		if(strpos($src,'rt:unit') !== false){
			while(Tag::setof($tag,$src,'rt:unit')){
				$uniq = uniqid('');
				$param = $tag->in_param('param');
				$offset = $tag->in_param('offset',1);
				$cols = ($tag->is_param('cols')) ? (ctype_digit($tag->in_param('cols')) ? $tag->in_param('cols') : $this->variable_string($this->parse_plain_variable($tag->in_param('cols')))) : 1;
				$rows = ($tag->is_param('rows')) ? (ctype_digit($tag->in_param('rows')) ? $tag->in_param('rows') : $this->variable_string($this->parse_plain_variable($tag->in_param('rows')))) : 0;
				$total = $tag->in_param('total','_total_'.$uniq);
				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$row_counter = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$first_value = $tag->in_param('first_value','first');
				$first = $tag->in_param('first','_first_'.$uniq);
				$last_value = $tag->in_param('last_value','last');
				$last = $tag->in_param('last','_last_'.$uniq);
				$cols_total = '$'.$tag->in_param('cols_total','_cols_total_'.$uniq);
				$cols_shortfall = '$'.$tag->in_param('cols_shortfall','_cols_shortfall_'.$uniq);
				$shortfall = '$'.$tag->in_param('shortfall','_defi_'.$uniq);
				$rows_total = '$'.$tag->in_param('rows_total','_rows_total_'.$uniq);

				$value = $tag->value();
				$header_value = $footer_value = '';

				$ucols = '$_ucols_'.$uniq;
				$urows = '$_urows_'.$uniq;
				$ulimit = '$_ulimit_'.$uniq;
				$ucount = '$_ucount_'.$uniq;
				$ufvalue = '$_ufvalue_'.$uniq;
				$urowcount = '$_urowcount_'.$uniq;

				$pukey = '_ukey_'.$uniq;
				$puvar = '_uvar_'.$uniq;

				$src = str_replace(
							$tag->plain(),
							sprintf('<?php %s=%s; %s=%s; %s=1; %s=%s*%s; %s=1; %s=null; %s=0; ?>'
									.'<rt:loop param="%s" var="%s" key="%s" first="%s" first_value="%s" last="%s" last_value="%s" total="%s" offset="%s"'.($tag->is_param("rows") ? ' limit="{'.$ulimit.'}"' : '').'>'
										.'<?php if(%s <= %s){ %s[$%s]=$%s; } ?>'
										.'<rt:first><?php %s=$%s; ?></rt:first>'
										.'<rt:last><?php %s=%s; %s=(%s == 0) ? 0 : (%s-%s);?></rt:last>'
										.'<?php if(%s===%s){ ?>'
											.'<?php %s=%s; ?>'
											.'<?php if(%s === 1){ $%s=%s; } ?>'
											.'<?php %s=sizeof(%s); %s=%s-%s; ?>'
											.'<?php %s=ceil($%s/%s); ?>'
											.'%s'
											.'<?php %s=array(); %s=1; %s++; ?>'
										.'<?php }else{ %s++; } ?>'
									.'</rt:loop>'
									,$ucols,$cols,$urows,$rows,$ucount,$ulimit,$ucols,$urows,$urowcount,$ufvalue,$shortfall
									,$param,$puvar,$pukey,$first,$first_value,$last,$last_value,$total,$offset
									,$ucount,$ucols,$var,$pukey,$puvar
									,$ufvalue,$first
									,$ucount,$ucols,$shortfall,$rows,$rows,$urowcount
									,$ucount,$ucols
									,$row_counter,$urowcount
									,$urowcount,$first,$ufvalue
									,$cols_total,$var,$cols_shortfall,$ucols,$cols_total
									,$rows_total,$total,$ucols
									,$value
									,$var,$ucount,$urowcount
									,$ucount
							),$src
						);
			}
		}
		return $src;
		/***
			$src = text('
						<rt:unit param="abc" var="unit_list" cols="3" rows="5" offset="2" counter="counter" shortfall="difi">
						<rt:first>FIRST</rt:first>{$counter}{
						<rt:loop param="unit_list" var="a"><rt:first>first</rt:first>{$a}<rt:last>last</rt:last></rt:loop>
						}
						<rt:last>LAST {$difi}</rt:last>
						</rt:unit>
					');
			$result = text('
							FIRST1{
							first234last}
							2{
							first567last}
							3{
							first8910last}
							LAST 2
						');
			$template = new Template();
			$template->vars("abc",array(1,2,3,4,5,6,7,8,9,10));
			eq($result,$template->execute($src));
		*/
		/***
			$src = text('<rt:unit cols_shortfall="cshort" cols_total="ctotal" param="abc" cols="5" rows_total="rtotal">{$rtotal}{$ctotal}{$cshort} </rt:unit>');
			$result = '250 232 ';
			$template->vars("abc",array(1,2,3,4,5,6,7,8));
			eq($result,$template->execute($src));
		 */
	}
	final private function rtloop($src){
		if(strpos($src,'rt:loop') !== false){
			while(Tag::setof($tag,$src,'rt:loop')){
				$param = ($tag->is_param('param')) ? $this->variable_string($this->parse_plain_variable($tag->in_param('param'))) : null;
				$offset = ($tag->is_param('offset')) ? (ctype_digit($tag->in_param('offset')) ? $tag->in_param('offset') : $this->variable_string($this->parse_plain_variable($tag->in_param('offset')))) : 1;
				$limit = ($tag->is_param('limit')) ? (ctype_digit($tag->in_param('limit')) ? $tag->in_param('limit') : $this->variable_string($this->parse_plain_variable($tag->in_param('limit')))) : 0;
				if(empty($param) && $tag->is_param('range')){
					list($range_start,$range_end) = explode(',',$tag->in_param('range'),2);
					$range = ($tag->is_param('range_step')) ? sprintf('range(%d,%d,%d)',$range_start,$range_end,$tag->in_param('range_step')) :
																sprintf('range("%s","%s")',$range_start,$range_end);
					$param = sprintf('array_combine(%s,%s)',$range,$range);
				}
				$uniq = uniqid('');
				$even = $tag->in_param('even_value','even');
				$odd = $tag->in_param('odd_value','odd');
				$evenodd = '$'.$tag->in_param('evenodd','loop_evenodd');

				$first_value = $tag->in_param('first_value','first');
				$first = '$'.$tag->in_param('first','_first_'.$uniq);
				$first_flg = '$_IS_FIRST_'.$uniq;
				$last_value = $tag->in_param('last_value','last');
				$last = '$'.$tag->in_param('last','_last_'.$uniq);
				$last_flg = '$_IS_LAST_'.$uniq;
				$shortfall = '$'.$tag->in_param('shortfall','_DEFI_'.$uniq);

				$var = '$'.$tag->in_param('var','_var_'.$uniq);
				$key = '$'.$tag->in_param('key','_key_'.$uniq);
				$total = '$'.$tag->in_param('total','_total_'.$uniq);
				$counter = '$'.$tag->in_param('counter','_counter_'.$uniq);
				$loop_counter = '$'.$tag->in_param('loop_counter','_loop_counter_'.$uniq);
				$reverse = (strtolower($tag->in_param('reverse') === 'true'));

				$varname = '$_'.$uniq;
				$countname = '$_COUNT_'.$uniq;
				$lcountname = '$_VCOUNT_'.$uniq;
				$offsetname	= '$_OFFSET_'.$uniq;
				$limitname = '$_LIMIT_'.$uniq;

				$value = $tag->value();
				$empty_value = null;
				while(Tag::setof($subtag,$value,'rt:loop')){
					$value = $this->rtloop($value);
				}
				while(Tag::setof($subtag,$value,'rt:first')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$first
					,(($subtag->in_param('last') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:middle')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(!isset(%s) && !isset(%s)){ ?>%s<?php } ?>',$first,$last
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Tag::setof($subtag,$value,'rt:last')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$last
					,(($subtag->in_param('first') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				$value = $this->rtif($value);
				if(preg_match("/^(.+)<rt\:else[\s]*\/>(.+)$/ims",$value,$match)){
					list(,$value,$empty_value) = $match;
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php try{ ?>"
									."<?php "
										." %s=%s;"
										." if(is_array(%s)){"
											." if(%s){ krsort(%s); } %s=sizeof(%s); %s=%s=1; %s=%s; %s=((%s>0) ? (%s + %s) : 0); "
											." %s=%s=false; %s=0; %s=%s=null;"
											." foreach(%s as %s => %s){"
												." if(%s <= %s){"
													." if(!%s){ %s=true; %s='%s'; }"
													." if((%s > 0 && (%s+1) == %s) || %s===%s){ %s='%s'; %s=true; %s=(%s-%s+1) * -1;}"
													." %s=((%s %% 2) === 0) ? '%s' : '%s';"
													." %s=%s; %s=%s;"
													." ?>%s<?php "
													." %s=%s=null;"
													." %s++;"
												." }"
												." %s++;"
												." if(%s > 0 && %s >= %s){ break; }"
											." }"
											." if(!%s){ ?>%s<?php } "
											." unset(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);"
										." }"
									." ?>"
									."<?php }catch(Exception \$e){ print('".self::$exception_str."'); } ?>"
									,$varname,$param
									,$varname
										,(($reverse) ? 'true' : 'false'),$varname,$total,$varname,$countname,$lcountname,$offsetname,$offset,$limitname,$limit,$offset,$limit
										,$first_flg,$last_flg,$shortfall,$first,$last
										,$varname,$key,$var
											,$offsetname,$lcountname
												,$first_flg,$first_flg,$first,str_replace("'","\\'",$first_value)
												,$limitname,$lcountname,$limitname,$lcountname,$total,$last,str_replace("'","\\'",$last_value),$last_flg,$shortfall,$lcountname,$limitname
												,$evenodd,$countname,$even,$odd
												,$counter,$countname,$loop_counter,$lcountname
												,$value
												,$first,$last
												,$countname
											,$lcountname
											,$limitname,$lcountname,$limitname
									,$first_flg,$empty_value
									,$var,$counter,$key,$countname,$lcountname,$offsetname,$limitname,$varname,$first,$first_flg,$last,$last_flg
							)
							,$src
						);
			}
		}
		return $src;
		/***
			$src = text('
						<rt:loop param="abc" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							1: A => 456
							2: B => 789
							3: C => 010
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010"));
			eq($result,$template->execute($src));
		*/
		/***
			$template = new Template();
			$src = text('
						<rt:loop param="abc" offset="2" limit="2" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							2: B => 789
							3: C => 010
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010","D"=>"999"));
			eq($result,$template->execute($src));
		*/
		/***
			# limit
			$template = new Template();
			$src = text('
						<rt:loop param="abc" offset="{$offset}" limit="{$limit}" loop_counter="loop_counter" key="loop_key" var="loop_var">
						{$loop_counter}: {$loop_key} => {$loop_var}
						</rt:loop>
						hoge
					');
			$result = text('
							2: B => 789
							3: C => 010
							4: D => 999
							hoge
						');
			$template = new Template();
			$template->vars("abc",array("A"=>"456","B"=>"789","C"=>"010","D"=>"999","E"=>"111"));
			$template->vars("offset",2);
			$template->vars("limit",3);
			eq($result,$template->execute($src));
		*/
		/***
			# range
			$template = new Template();
			$src = text('<rt:loop range="0,5" var="var">{$var}</rt:loop>');
			$result = text('012345');
			eq($result,$template->execute($src));

			$src = text('<rt:loop range="0,6" range_step="2" var="var">{$var}</rt:loop>');
			$result = text('0246');
			eq($result,$template->execute($src));

			$src = text('<rt:loop range="A,F" var="var">{$var}</rt:loop>');
			$result = text('ABCDEF');
			eq($result,$template->execute($src));
		 */
		/***
			# multi
			$template = new Template();
			$src = text('<rt:loop range="1,2" var="a"><rt:loop range="1,2" var="b">{$a}{$b}</rt:loop>-</rt:loop>');
			$result = text('1112-2122-');
			eq($result,$template->execute($src));
		 */
		/***
			# empty
			$template = new Template();
			$src = text('<rt:loop param="abc">aaa</rt:loop>');
			$result = text('');
			$template->vars("abc",array());
			eq($result,$template->execute($src));
		 */
		/***
			# total
			$template = new Template();
			$src = text('<rt:loop param="abc" total="total">{$total}</rt:loop>');
			$result = text('4444');
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" total="total" offset="2" limit="2">{$total}</rt:loop>');
			$result = text('44');
			$template->vars("abc",array(1,2,3,4));
			eq($result,$template->execute($src));
		 */
		/***
			# evenodd
			$template = new Template();
			$src = text('<rt:loop range="0,5" evenodd="evenodd" counter="counter">{$counter}[{$evenodd}]</rt:loop>');
			$result = text('1[odd]2[even]3[odd]4[even]5[odd]6[even]');
			eq($result,$template->execute($src));
		 */
		/***
			# first_last
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" first="first" last="last">{$first}{$var}{$last}</rt:loop>');
			$result = text('first12345last');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" first="first" last="last" offset="2" limit="2">{$first}{$var}{$last}</rt:loop>');
			$result = text('first23last');
			$template->vars("abc",array(1,2,3,4,5));
			eq($result,$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" offset="2" limit="3"><rt:first>F</rt:first>[<rt:middle>{$var}</rt:middle>]<rt:last>L</rt:last></rt:loop>');
			$result = text('F[][3][]L');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		*/
		/***
			# first_last_block
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var" offset="2" limit="3"><rt:first>F<rt:if param="var" value="1">I<rt:else />E</rt:if><rt:else />nf</rt:first>[<rt:middle>{$var}<rt:else />nm</rt:middle>]<rt:last>L<rt:else />nl</rt:last></rt:loop>');

			$result = text('FE[nm]nlnf[3]nlnf[nm]L');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		 */
		/***
			# first_in_last
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:last>L</rt:last></rt:loop>');
			$template->vars("abc",array(1));
			eq("L",$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:last first="false">L</rt:last></rt:loop>');
			$template->vars("abc",array(1));
			eq("",$template->execute($src));
		 */
		/***
			# last_in_first
			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:first>F</rt:first></rt:loop>');
			$template->vars("abc",array(1));
			eq("F",$template->execute($src));

			$template = new Template();
			$src = text('<rt:loop param="abc" var="var"><rt:first last="false">F</rt:first></rt:loop>');
			$template->vars("abc",array(1));
			eq("",$template->execute($src));
		 */
		/***
			# difi
			$template = new Template();
			$src = text('<rt:loop param="abc" limit="10" shortfall="difi" var="var">{$var}{$difi}</rt:loop>');
			$result = text('102030405064');
			$template->vars("abc",array(1,2,3,4,5,6));
			eq($result,$template->execute($src));
		*/
		/***
			# empty
			$template = new Template();
			$src = text('<rt:loop param="abc">aaaaaa<rt:else />EMPTY</rt:loop>');
			$result = text('EMPTY');
			$template->vars("abc",array());
			eq($result,$template->execute($src));
		*/
	}
	final private function rtif($src){
		if(strpos($src,'rt:if') !== false){
			while(Tag::setof($tag,$src,'rt:if')){
				if(!$tag->is_param('param')) throw new LogicException('if');
				$arg1 = $this->variable_string($this->parse_plain_variable($tag->in_param('param')));

				if($tag->is_param('value')){
					$arg2 = $this->parse_plain_variable($tag->in_param('value'));
					if($arg2 == 'true' || $arg2 == 'false' || ctype_digit(Text::str($arg2))){
						$cond = sprintf('<?php if(%s === %s || %s === "%s"){ ?>',$arg1,$arg2,$arg1,$arg2);
					}else{
						if($arg2 === '' || $arg2[0] != '$') $arg2 = '"'.$arg2.'"';
						$cond = sprintf('<?php if(%s === %s){ ?>',$arg1,$arg2);
					}
				}else{
					$uniq = uniqid('$I');
					$cond = sprintf('<?php %s=%s; ?>',$uniq,$arg1)
								.sprintf('<?php if(%s !== null && %s !== false && ( (!is_string(%s) && !is_array(%s)) || (is_string(%s) && %s !== "") || (is_array(%s) && !empty(%s)) ) ){ ?>',$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq);
				}
				$src = str_replace(
							$tag->plain()
							,'<?php try{ ?>'.$cond
								.preg_replace("/<rt\:else[\s]*\/>/i","<?php }else{ ?>",$tag->value())
							."<?php } ?>"."<?php }catch(Exception \$e){ print('".self::$exception_str."'); } ?>"
							,$src
						);
			}
		}
		return $src;
		/***
			$src = text('<rt:if param="abc">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc",true);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="xyz">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc","xyz");
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">hoge</rt:if>');
			$result = text('hoge');
			$template = new Template();
			$template->vars("abc",1);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">bb<rt:else />aa</rt:if>');
			$result = text('bb');
			$template = new Template();
			$template->vars("abc",1);
			eq($result,$template->execute($src));

			$src = text('<rt:if param="abc" value="1">bb<rt:else />aa</rt:if>');
			$result = text('aa');
			$template = new Template();
			$template->vars("abc",2);
			eq($result,$template->execute($src));
		*/
	}
	final private function rtpager($src){
		if(strpos($src,'rt:pager') !== false){
			while(Tag::setof($tag,$src,'rt:pager')){
				$param = $this->variable_string($this->parse_plain_variable($tag->in_param('param','paginator')));
				$func = sprintf('<?php try{ ?><?php if(%s instanceof Paginator){ ?>',$param);
				if($tag->is_value()){
					$func .= $tag->value();
				}else{
					$uniq = uniqid('');
					$name = '$_PAGER_'.$uniq;
					$counter_var = '$_COUNTER_'.$uniq;
					$tagtype = $tag->in_param('tag');
					$href = $tag->in_param('href','?');
					$stag = (empty($tagtype)) ? '' : '<'.$tagtype.'>';
					$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';
					$navi = array_change_key_case(array_flip(explode(',',$tag->in_param('navi','prev,next,first,last,counter'))));
					$counter = $tag->in_param('counter',50);
					$total = '$_PAGER_TOTAL_'.$uniq;
					if(isset($navi['prev'])) $func .= sprintf('<?php if(%s->is_prev()){ ?>%s<a href="%s{%s.query_prev()}">%s</a>%s<?php } ?>',$param,$stag,$href,$param,App::trans('prev'),$etag);
					if(isset($navi['first'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_first(%d)){ ?>%s<a href="%s{%s.query(%s.first())}">{%s.first()}</a>%s%s...%s<?php } ?>',$param,$param,$counter,$stag,$href,$param,$param,$param,$etag,$stag,$etag);
					if(isset($navi['counter'])){
						$func .= sprintf('<?php if(!%s->is_dynamic()){ ?>',$param);
						$func .= sprintf('<?php %s = %s; if(!empty(%s)){ ?>',$total,$param,$total);
						$func .= sprintf('<?php for(%s=%s->which_first(%d);%s<=%s->which_last(%d);%s++){ ?>',$counter_var,$param,$counter,$counter_var,$param,$counter,$counter_var);
						$func .= sprintf('%s<?php if(%s == %s->current()){ ?><strong>{%s}</strong><?php }else{ ?><a href="%s{%s.query(%s)}">{%s}</a><?php } ?>%s',$stag,$counter_var,$param,$counter_var,$href,$param,$counter_var,$counter_var,$etag);
						$func .= '<?php } ?>';
						$func .= '<?php } ?>';
						$func .= '<?php } ?>';
					}
					if(isset($navi['last'])) $func .= sprintf('<?php if(!%s->is_dynamic() && %s->is_last(%d)){ ?>%s...%s%s<a href="%s{%s.query(%s.last())}">{%s.last()}</a>%s<?php } ?>',$param,$param,$counter,$stag,$etag,$stag,$href,$param,$param,$param,$etag);
					if(isset($navi['next'])) $func .= sprintf('<?php if(%s->is_next()){ ?>%s<a href="%s{%s.query_next()}">%s</a>%s<?php } ?>',$param,$stag,$href,$param,App::trans('next'),$etag);
				}
				$func .= '<?php } ?><?php }catch(Exception $e){ print("'.self::$exception_str.'"); } ?>';
				$src = str_replace($tag->plain(),$func,$src);
			}
		}
		return $this->rtloop($src);
		/***
			$template = new Template();

			$template->vars("paginator",new Paginator(10,2,100));
			$src = '<rt:pager param="paginator" counter="3" tag="span" />';
			$result = text('<span><a href="?page=1&">prev</a></span><span><a href="?page=1&">1</a></span><span>...</span><span><a href="?page=1&">1</a></span><span><strong>2</strong></span><span><a href="?page=3&">3</a></span><span>...</span><span><a href="?page=10&">10</a></span><span><a href="?page=3&">next</a></span>');
			eq($result,$template->execute($src));

			$template->vars("paginator",new Paginator(10,1,100));
			$src = '<rt:pager param="paginator" counter="3" />';
			$result = text('<strong>1</strong><a href="?page=2&">2</a><a href="?page=3&">3</a>...<a href="?page=10&">10</a><a href="?page=2&">next</a>');
			eq($result,$template->execute($src));

			$template->vars("paginator",new Paginator(10,10,100));
			$src = '<rt:pager param="paginator" counter="3" tag="span" />';
			$result = text('<span><a href="?page=9&">prev</a></span><span><a href="?page=1&">1</a></span><span>...</span><span><a href="?page=8&">8</a></span><span><a href="?page=9&">9</a></span><span><strong>10</strong></span>');
			eq($result,$template->execute($src));
		*/
	}
	final private function rtinvalid($src){
		if(strpos($src,'rt:invalid') !== false){
			while(Tag::setof($tag,$src,'rt:invalid')){
				$param = $this->parse_plain_variable($tag->in_param('param','exceptions'));
				$var = $this->parse_plain_variable($tag->in_param('var','rtinvalid_var'.uniqid('')));
				$messages = $this->parse_plain_variable($tag->in_param('messages','rtinvalid_mes'.uniqid('')));
				if($param[0] !== '$') $param = '"'.$param.'"';
				$value = $tag->value();
				$tagtype = $tag->in_param('tag');
				$stag = (empty($tagtype)) ? '' : '<'.$tagtype.'>';
				$etag = (empty($tagtype)) ? '' : '</'.$tagtype.'>';

				if(empty($value)){
					$varnm = 'rtinvalid_varnm'.uniqid('');
					$value = sprintf("<rt:loop param=\"%s\" var=\"%s\">\n"
										."%s{\$%s}%s"
									."</rt:loop>\n",$messages,$varnm,$stag,$varnm,$etag);
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php if(Exceptions::invalid(%s)){ ?>"
										."<?php \$%s = Exceptions::gets(%s); ?>"
										."<?php \$%s = Exceptions::messages(%s); ?>"
										."%s"
									."<?php } ?>",$param,$var,$param,$messages,$param,$value),
							$src);
			}
		}
		return $src;
	}
	final private function parse_message($src){
		if(preg_match_all("/__\((.+)\)/",$src,$match)){
			$stringList = array();
			foreach($match[1] as $key => $value) $stringList[$match[0][$key]] = sprintf("<?php try{ ?>"."<?php @print(App::trans(%s)); ?>"."<?php }catch(Exception \$e){ print('".self::$exception_str."'); } ?>",$value);
			foreach($stringList as $baseString => $string) $src = str_replace($baseString,$string,$src);
			unset($stringList,$match);
		}
		return $src;
	}
	final private function parse_print_variable($src){
		foreach($this->match_variable($src) as $variable){
			$name = $this->parse_plain_variable($variable);
			$value = "<?php try{ ?>"."<?php @print(".$name."); ?>"."<?php }catch(Exception \$e){ print('".self::$exception_str."'); } ?>";
			$src = str_replace(array($variable."\n",$variable),array($value."\n\n",$value),$src);
		}
		return $src;
	}
	final private function to_static_variable($class,$var,$src){
		return str_replace('$'.$var.'->',$class.'::',$src);
	}
	final private function match_variable($src){
		$hash = array();
		while(preg_match("/({(\\$[\$\w][^\t]*)})/s",$src,$vars,PREG_OFFSET_CAPTURE)){
			list($value,$pos) = $vars[1];
			if($value == "") break;
			if(substr_count($value,'}') > 1){
				for($i=0,$start=0,$end=0;$i<strlen($value);$i++){
					if($value[$i] == '{'){
						$start++;
					}else if($value[$i] == '}'){
						if($start == ++$end){
							$value = substr($value,0,$i+1);
							break;
						}
					}
				}
			}
			$length	= strlen($value);
			$src = substr($src,$pos + $length);
			$hash[sprintf('%03d_%s',$length,$value)] = $value;
		}
		krsort($hash,SORT_STRING);
		return $hash;
	}
	final private function parse_plain_variable($src){
		while(true){
			$array = $this->match_variable($src);
			if(sizeof($array) <= 0)	break;
			foreach($array as $variable){
				$tmp = $variable;
				if(preg_match_all("/([\"\'])([^\\1]+?)\\1/",$variable,$match)){
					foreach($match[2] as $value) $tmp = str_replace($value,str_replace('.','__PERIOD__',$value),$tmp);
				}
				$src = str_replace($variable,str_replace('.','->',substr($tmp,1,-1)),$src);
			}
		}
		return str_replace('[]','',str_replace('__PERIOD__','.',$src));
	}

	final private function variable_string($src){
		return (empty($src) || isset($src[0]) && $src[0] == '$') ? $src : '$'.$src;
	}
	final private function html_reform($src){
		$bool = false;
		foreach(Tag::anyhow($src)->in('form') as $obj){
			if(($obj->in_param('rt:aref') === 'true')){
				$form = $obj->value();
				foreach($obj->in(array('input','select')) as $tag){
					if($tag->is_param('name') || $tag->is_param('id')){
						$name = $this->parse_plain_variable($this->form_variable_name($tag->in_param('name',$tag->in_param('id'))));
						switch(strtolower($tag->name())){
							case 'input':
								switch(strtolower($tag->in_param('type'))){
									case 'radio':
									case 'checkbox':
										$tag->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($tag->in_param('value','true'))),'checked'));
										$form = str_replace($tag->plain(),$tag->get(),$form);
										$bool = true;
								}
								break;
							case 'select':
								$select = $tag->value();
								foreach($tag->in('option') as $option){
									$option->attr($this->check_selected($name,sprintf("'%s'",$this->parse_plain_variable($option->in_param('value'))),'selected'));
									$select = str_replace($option->plain(),$option->get(),$select);
								}
								$tag->value($select);
								$form = str_replace($tag->plain(),$tag->get(),$form);
								$bool = true;
						}
					}
				}
				$obj->rm_param('rt:aref');
				$obj->value($form);
				$src = str_replace($obj->plain(),$obj->get(),$src);
			}
		}
		return ($bool) ? $this->exec($src) : $src;
	}
	final private function html_form($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in('form') as $obj){
			if($this->is_reference($obj)){
				foreach($obj->in(array('input','select','textarea')) as $tag){
					if(!$tag->is_param('rt:ref') && ($tag->is_param('name') || $tag->is_param('id'))){
						switch(strtolower($tag->in_param('type','text'))){
							case 'button':
							case 'submit':
								break;
							case 'file':
								$obj->param('enctype','multipart/form-data');
								$obj->param('method','post');
								break;
							default:
								$tag->param('rt:ref','true');
								$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
						}
					}
				}
			}
			$src = str_replace($obj->plain(),$obj->get(),$src);
		}
		return $this->html_input($src);
	}
	final private function html_input($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in(array('input','textarea','select')) as $obj){
			if('' != ($originalName = $obj->in_param('name',$obj->in_param('id','')))){
				$type = strtolower($obj->in_param('type','text'));
				$name = $this->parse_plain_variable($this->form_variable_name($originalName));
				$lname = strtolower($obj->name());
				$change = false;
				$uid = uniqid();

				if(substr($originalName,-2) !== '[]'){
					if($type == 'checkbox'){
						if($obj->in_param('rt:multiple','true') === 'true') $obj->param('name',$originalName.'[]');
						$obj->rm_param('rt:multiple');
						$change = true;
					}else if($obj->is_attr('multiple') || $obj->in_param('multiple') === 'multiple'){
						$obj->param('name',$originalName.'[]');
						$obj->rm_attr('multiple');
						$obj->param('multiple','multiple');
						$change = true;
					}
				}else if($obj->in_param('name') !== $originalName){
					$obj->param('name',$originalName);
					$change = true;
				}
				if($obj->is_param('rt:param') || $obj->is_param('rt:range')){
					switch($lname){
						case 'select':
							$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" key="%s" offset="%s" limit="%s" reverse="%s" evenodd="%s" even="%s" odd="%s" range="%s" range_step="%s">'
											.'<option value="{$_t_.primary($%s,$%s)}">{$%s}</option>'
											.'</rt:loop>'
											,$obj->in_param('rt:param'),$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:counter','loop_counter'.$uid)
											,$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
											,$obj->in_param('rt:reverse','false')
											,$obj->in_param('rt:evenodd','loop_evenodd'.$uid),$obj->in_param('rt:even','even'),$obj->in_param('rt:odd','odd')
											,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
											,$obj->in_param('rt:var','loop_var'.$uid),$obj->in_param('rt:key','loop_key'.$uid),$obj->in_param('rt:var','loop_var'.$uid)
							);
							$obj->value($this->rtloop($value));
							if($obj->is_param('rt:null')) $obj->value('<option value="">'.$obj->in_param('rt:null').'</option>'.$obj->value());
					}
					$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd'
									,'rt:range','rt:range_step','rt:even','rt:odd');
					$change = true;
				}
				if($this->is_reference($obj)){
					switch($lname){
						case 'textarea':
							$obj->value(sprintf('{$_t_.htmlencode(%s)}',((preg_match("/^{\$(.+)}$/",$originalName,$match)) ? '{$$'.$match[1].'}' : '{$'.$originalName.'}')));
							break;
						case 'select':
							$select = $obj->value();
							foreach($obj->in('option') as $option){
								$value = $this->parse_plain_variable($option->in_param('value'));
								if(empty($value) || $value[0] != '$') $value = sprintf("'%s'",$value);
								$option->rm_attr('selected');
								$option->rm_param('selected');
								$option->attr($this->check_selected($name,$value,'selected'));
								$select = str_replace($option->plain(),$option->get(),$select);
							}
							$obj->value($select);
							break;
						case 'input':
							switch($type){
								case 'checkbox':
								case 'radio':
									$value = $this->parse_plain_variable($obj->in_param('value','true'));
									$value = (substr($value,0,1) != '$') ? sprintf("'%s'",$value) : $value;
									$obj->rm_attr('checked');
									$obj->rm_param('checked');
									$obj->attr($this->check_selected($name,$value,'checked'));
									break;
								case 'text':
								case 'hidden':
								case 'password':
									$obj->param('value',sprintf('{$_t_.htmlencode(%s)}',
																((preg_match("/^\{\$(.+)\}$/",$originalName,$match)) ?
																	'{$$'.$match[1].'}' :
																	'{$'.$originalName.'}')));
							}
							break;
					}
					$change = true;
				}else if($obj->is_param('rt:ref')){
					$obj->rm_param('rt:ref');
					$change = true;
				}
				if($change){
					switch($lname){
						case 'textarea':
						case 'select':
							$obj->close_empty(false);
					}
					$src = str_replace($obj->plain(),$obj->get(),$src);
				}
			}
		}
		return $src;
		/***
			#input
			$src = text('
						<form rt:ref="true">
							<input type="text" name="aaa" />
							<input type="checkbox" name="bbb" value="hoge" />hoge
							<input type="checkbox" name="bbb" value="fuga" checked="checked" />fuga
							<input type="checkbox" name="eee" value="true" checked />foo
							<input type="checkbox" name="fff" value="false" />foo
							<input type="submit" />
							<textarea name="aaa"></textarea>

							<select name="ddd" size="5" multiple>
								<option value="123" selected="selected">123</option>
								<option value="456">456</option>
								<option value="789" selected>789</option>
							</select>
							<select name="XYZ" rt:param="xyz"></select>
						</form>
					');
			$result = text('
						<form>
							<input type="text" name="aaa" value="hogehoge" />
							<input type="checkbox" name="bbb[]" value="hoge" checked="checked" />hoge
							<input type="checkbox" name="bbb[]" value="fuga" />fuga
							<input type="checkbox" name="eee[]" value="true" checked="checked" />foo
							<input type="checkbox" name="fff[]" value="false" checked="checked" />foo
							<input type="submit" />
							<textarea name="aaa">hogehoge</textarea>

							<select name="ddd[]" size="5" multiple="multiple">
								<option value="123">123</option>
								<option value="456" selected="selected">456</option>
								<option value="789" selected="selected">789</option>
							</select>
							<select name="XYZ"><option value="A">456</option><option value="B" selected="selected">789</option><option value="C">010</option></select>
						</form>
						');
			$template = new Template();
			$template->vars("aaa","hogehoge");
			$template->vars("bbb","hoge");
			$template->vars("XYZ","B");
			$template->vars("xyz",array("A"=>"456","B"=>"789","C"=>"010"));
			$template->vars("ddd",array("456","789"));
			$template->vars("eee",true);
			$template->vars("fff",false);
			eq($result,$template->execute($src));

			$src = text('
						<form rt:ref="true">
							<select name="ddd" rt:param="abc">
							</select>
						</form>
					');
			$result = text('
						<form>
							<select name="ddd"><option value="123">123</option><option value="456" selected="selected">456</option><option value="789">789</option></select>
						</form>
						');
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456,789=>789));
			$template->vars("ddd","456");
			eq($result,$template->execute($src));

			$src = text('
						<form rt:ref="true">
						<rt:loop param="abc" var="v">
						<input type="checkbox" name="ddd" value="{$v}" />
						</rt:loop>
						</form>
					');
			$result = text('
							<form>
							<input type="checkbox" name="ddd[]" value="123" />
							<input type="checkbox" name="ddd[]" value="456" checked="checked" />
							<input type="checkbox" name="ddd[]" value="789" />
							</form>
						');
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456,789=>789));
			$template->vars("ddd","456");
			eq($result,$template->execute($src));

		*/
		/***
			# textarea
			$src = text('
							<form>
								<textarea name="hoge"></textarea>
							</form>
						');
			$template = new Template();
			eq($src,$template->execute($src));
		 */
		/***
			#select
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(123=>123,456=>456));
			eq('<form><select name="abc"><option value="123">123</option><option value="456">456</option></select></form>',$template->execute($src));
		 */
		/***
			#select_obj
			
			$name1 = create_class('
				static protected $__abc__ = "type=serial";
				protected $abc;
				protected function __str__(){
					return "s".$this->abc;
				}
			');
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(new $name1("abc=123"),new $name1("abc=456")));
			eq('<form><select name="abc"><option value="123">s123</option><option value="456">s456</option></select></form>',$template->execute($src));
			
			$name1 = create_class('
				static protected $__abc__ = "type=integer,primary=true";
				protected $abc;
				static protected $__def__ = "type=string,primary=true";
				protected $def;
				protected function __str__(){
					return "s".$this->abc;
				}
			');
			$src = '<form><select name="abc" rt:param="abc"></select></form>';
			$template = new Template();
			$template->vars("abc",array(new $name1("abc=123,def=D"),new $name1("abc=456,def=E")));
			eq('<form><select name="abc"><option value="123_D">s123</option><option value="456_E">s456</option></select></form>',$template->execute($src));			
		 */
		/***
			#multiple
			$src = '<form><input name="abc" type="checkbox" /></form>';
			$template = new Template();
			eq('<form><input name="abc[]" type="checkbox" /></form>',$template->execute($src));

			$src = '<form><input name="abc" type="checkbox" rt:multiple="false" /></form>';
			$template = new Template();
			eq('<form><input name="abc" type="checkbox" /></form>',$template->execute($src));
		 */
	}
	final private function check_selected($name,$value,$selected){
		return sprintf('<?php if('
					.'isset(%s) && (%s === %s '
										.' || (ctype_digit(Text::str(%s)) && %s == %s)'
										.' || ((%s == "true" || %s == "false") ? (%s === (%s == "true")) : false)'
										.' || in_array(%s,((is_array(%s)) ? %s : (is_null(%s) ? array() : array(%s))),true) '
									.') '
					.'){print(" %s=\"%s\"");} ?>'
					,$name,$name,$value
					,$name,$name,$value
					,$value,$value,$name,$value
					,$value,$name,$name,$name,$name
					,$selected,$selected
				);
	}
	final private function html_list($src){
		$tag = Tag::anyhow($src);
		foreach($tag->in(array('table','ul','ol')) as $obj){
			if($obj->is_param('rt:param') || $obj->is_param('rt:range')){
				$name = strtolower($obj->name());
				$param = $obj->in_param('rt:param');
				$null = strtolower($obj->in_param('rt:null'));
				$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" '
									.'key="%s" offset="%s" limit="%s" '
									.'reverse="%s" '
									.'evenodd="%s" even="%s" odd="%s" '
									.'range="%s" range_step="%s" '
									.'shortfall="%s">'
								,$param,$obj->in_param('rt:var','loop_var'),$obj->in_param('rt:counter','loop_counter')
								,$obj->in_param('rt:key','loop_key'),$obj->in_param('rt:offset','0'),$obj->in_param('rt:limit','0')
								,$obj->in_param('rt:reverse','false')
								,$obj->in_param('rt:evenodd','loop_evenodd'),$obj->in_param('rt:even','even'),$obj->in_param('rt:odd','odd')
								,$obj->in_param('rt:range'),$obj->in_param('rt:range_step',1)
								,$tag->in_param('rt:shortfall','_DEFI_'.uniqid())
							);
				$rawvalue = $obj->value();
				if($name == 'table' && Tag::setof($t,$rawvalue,'tbody')){
					$t->value($value.$this->table_tr_even_odd($t->value(),(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>');
					$value = str_replace($t->plain(),$t->get(),$rawvalue);
				}else{
					$value = $value.$this->table_tr_even_odd($rawvalue,(($name == 'table') ? 'tr' : 'li'),$obj->in_param('rt:evenodd','loop_evenodd')).'</rt:loop>';
				}
				$obj->value($this->html_list($value));
				$obj->rm_param('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd','rt:range'
								,'rt:range_step','rt:even','rt:odd','rt:shortfall');
				$src = str_replace($obj->plain(),
						($null === 'true') ? $this->rtif(sprintf('<rt:if param="%s">',$param).$obj->get().'</rt:if>') : $obj->get(),
						$src);
			}
		}
		return $src;
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o">
						<tr class="odd"><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr class="odd"><td>222</td></tr>
							<tr class="even"><td>444</td></tr>
							<tr class="odd"><td>666</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr><td>222</td></tr>
							<tr><td>444</td></tr>
							<tr><td>666</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o" rt:offset="1" rt:limit="1">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$result = text('
							<table><tr><td>222</td></tr>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:var="o" rt:offset="1" rt:limit="1">
						<thead>
							<tr><th>hoge</th></tr>
						</thead>
						<tbody>
							<tr><td>{$o["B"]}</td></tr>
						</tbody>
						</table>
					');
			$result = text('
							<table>
							<thead>
								<tr><th>hoge</th></tr>
							</thead>
							<tbody>	<tr><td>222</td></tr>
							</tbody>
							</table>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
		 	$src = text('
						<table rt:param="xyz" rt:null="true">
						<tr><td>{$o["B"]}</td></tr>
						</table>
					');
			$template = new Template();
			$template->vars("xyz",array());
			eq("",$template->execute($src));
		*/
		/***
		 	$src = text('
						<ul rt:param="xyz" rt:var="o">
							<li class="odd">{$o["B"]}</li>
						</ul>
					');
			$result = text('
							<ul>	<li class="odd">222</li>
								<li class="even">444</li>
								<li class="odd">666</li>
							</ul>
						');
			$template = new Template();
			$template->vars("xyz",array(array("A"=>"111","B"=>"222"),array("A"=>"333","B"=>"444"),array("A"=>"555","B"=>"666")));
			eq($result,$template->execute($src));
		*/
		/***
			# abc
		 	$src = text('
						<rt:loop param="abc" var="a">
						<ul rt:param="{$a}" rt:var="b">
						<li>
						<ul rt:param="{$b}" rt:var="c">
						<li>{$c}<rt:loop param="xyz" var="z">{$z}</rt:loop></li>
						</ul>
						</li>
						</ul>
						</rt:loop>
					');
			$result = text('
							<ul><li>
							<ul><li>A12</li>
							<li>B12</li>
							</ul>
							</li>
							</ul>
							<ul><li>
							<ul><li>C12</li>
							<li>D12</li>
							</ul>
							</li>
							</ul>

						');
			$template = new Template();
			$template->vars("abc",array(array(array("A","B")),array(array("C","D"))));
			$template->vars("xyz",array(1,2));
			eq($result,$template->execute($src));
		*/
		/***
			# range
		 	$src = text('<ul rt:range="1,3" rt:var="o"><li>{$o}</li></ul>');
			$result = text('<ul><li>1</li><li>2</li><li>3</li></ul>');
			$template = new Template();
			eq($result,$template->execute($src));
		*/
		/***
			# nest_table
			$src = text('<table rt:param="object_list" rt:var="obj"><tr><td><table rt:param="obj" rt:var="o"><tr><td>{$o}</td></tr></table></td></tr></table>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<table><tr><td><table><tr><td>A1</td></tr><tr><td>A2</td></tr><tr><td>A3</td></tr></table></td></tr><tr><td><table><tr><td>B1</td></tr><tr><td>B2</td></tr><tr><td>B3</td></tr></table></td></tr></table>',$template->execute($src));
		*/
		/***
			# nest_ul
			$src = text('<ul rt:param="object_list" rt:var="obj"><li><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></li></ul>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ul><li><ul><li>A1</li><li>A2</li><li>A3</li></ul></li><li><ul><li>B1</li><li>B2</li><li>B3</li></ul></li></ul>',$template->execute($src));
		*/
		/***
			# nest_ol
			$src = text('<ol rt:param="object_list" rt:var="obj"><li><ol rt:param="obj" rt:var="o"><li>{$o}</li></ol></li></ol>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ol><li><ol><li>A1</li><li>A2</li><li>A3</li></ol></li><li><ol><li>B1</li><li>B2</li><li>B3</li></ol></li></ol>',$template->execute($src));
		*/
		/***
			# nest_olul
			$src = text('<ol rt:param="object_list" rt:var="obj"><li><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></li></ol>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<ol><li><ul><li>A1</li><li>A2</li><li>A3</li></ul></li><li><ul><li>B1</li><li>B2</li><li>B3</li></ul></li></ol>',$template->execute($src));
		*/
		/***
			# nest_tableul
			$src = text('<table rt:param="object_list" rt:var="obj"><tr><td><ul rt:param="obj" rt:var="o"><li>{$o}</li></ul></td></tr></table>');
			$template = new Template();
			$template->vars("object_list",array(array("A1","A2","A3"),array("B1","B2","B3")));
			eq('<table><tr><td><ul><li>A1</li><li>A2</li><li>A3</li></ul></td></tr><tr><td><ul><li>B1</li><li>B2</li><li>B3</li></ul></td></tr></table>',$template->execute($src));
		*/
	}
	final private function table_tr_even_odd($src,$name,$even_odd){
		$tag = Tag::anyhow($src);
		foreach($tag->in($name) as $tr){
			$class = ' '.$tr->in_param('class').' ';
			if(preg_match('/[\s](even|odd)[\s]/',$class,$match)){
				$tr->param('class',trim(str_replace($match[0],' {$'.$even_odd.'} ',$class)));
				$src = str_replace($tr->plain(),$tr->get(),$src);				
			}
		}
		return $src;
	}
	final private function form_variable_name($name){
		return (strpos($name,'[') && preg_match("/^(.+)\[([^\"\']+)\]$/",$name,$match)) ?
			'{$'.$match[1].'["'.$match[2].'"]'.'}' : '{$'.$name.'}';
	}
	final private function is_reference(&$tag){
		$bool = ($tag->in_param('rt:ref') === 'true');
		$tag->rm_param('rt:ref');
		return $bool;
	}
	private function exec($src){
		$__template_eval_src__ = $src;
		ob_start();
			if(is_array($this->vars) && !empty($this->vars)) extract($this->vars);
			eval('?>'.$__template_eval_src__);
			unset($__template_eval_src__);
		$result = ob_get_clean();
		if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)) throw new ErrorException(trim($match[0]));
		return $result;
	}
}