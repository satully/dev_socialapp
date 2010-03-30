<?php
/**
 * ページを管理するモデル
 *
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class Paginator extends Object{
	static protected $__offset__ = "type=integer";
	static protected $__limit__ = "type=integer";
	static protected $__current__ = "type=integer";
	static protected $__total__ = "type=integer";
	static protected $__first__ = "type=integer,set=false";
	static protected $__last__ = "type=integer,set=false";
	static protected $__vars__ = "type=mixed{}";
	protected $offset; # 開始位置
	protected $limit; # 終了位置
	protected $current; # 現在位置
	protected $total; # 合計
	protected $first = 1; # 最初のページ番号
	protected $last; # 最後のページ番号
	protected $vars = array(); # query文字列とする値

	static protected $__dynamic__ = "type=boolean,set=false";
	static protected $__contents__ = "type=mixed[]";
	static protected $__marker__ = "type=string,set=false";
	protected $dynamic = false; # ダイナミックページネーションとするか
	protected $contents = array(); # ダイナミックページネーションの１ページ分の内容
	protected $marker; # 現在の基点値

	private $asc = true;
	private $prop;
	private $next_c;
	private $prev_c;
	private $count_c = 0;
	private $count_p = null;
	
	/**
	 * 現在のページの最初の位置
	 * @return integer
	 */
	public function page_first(){
		return $this->offset + 1;
	}
	/**
	 * 現在のページの最後の位置
	 * @return integer
	 */
	public function page_last(){
		return (($this->offset + $this->limit) < $this->total) ? ($this->offset + $this->limit) : $this->total;
	}
	/**
	 * 動的コンテンツのPaginater
	 * @param integer $paginate_by １ページの要素数
	 * @param string $marker 基点となる値
	 * @param string $prop 対象とするプロパティ名
	 * @return self
	 */
	static public function dynamic_contents($paginate_by=20,$marker=null,$prop=null){
		$self = new self($paginate_by);
		$self->prop = $prop;
		$self->marker = $marker;
		$self->dynamic = true;

		if(!empty($marker) && $marker[0] == '-'){
			$self->asc = false;
			$self->marker = substr($marker,1);
		}
		return $self;
	}
	protected function __new__($paginate_by=20,$current=1,$total=0){
		$this->limit($paginate_by);
		$this->total($total);
		$this->current($current);
		/***
			$paginator = new Paginator(10);
			eq(10,$paginator->limit());
			eq(1,$paginator->first());
			$paginator->total(100);
			eq(100,$paginator->total());
			eq(10,$paginator->last());
			eq(1,$paginator->which_first(3));
			eq(3,$paginator->which_last(3));

			$paginator->current(3);
			eq(20,$paginator->offset());
			eq(true,$paginator->is_next());
			eq(true,$paginator->is_prev());
			eq(4,$paginator->next());
			eq(2,$paginator->prev());
			eq(1,$paginator->first());
			eq(10,$paginator->last());
			eq(2,$paginator->which_first(3));
			eq(4,$paginator->which_last(3));

			$paginator->current(1);
			eq(0,$paginator->offset());
			eq(true,$paginator->is_next());
			eq(false,$paginator->is_prev());

			$paginator->current(6);
			eq(5,$paginator->which_first(3));
			eq(7,$paginator->which_last(3));

			$paginator->current(10);
			eq(90,$paginator->offset());
			eq(false,$paginator->is_next());
			eq(true,$paginator->is_prev());
			eq(8,$paginator->which_first(3));
			eq(10,$paginator->which_last(3));
		 */
	}
	protected function __cp__($obj){
		if(!empty($obj)){
			if($obj instanceof Object){
				foreach($obj->prop_values() as $name => $value) $this->vars[$name] = $obj->{'fm_'.$name}();
			}else if(is_array($obj)){
				foreach($obj as $name => $value){
					if(ctype_alpha($name[0])) $this->vars[$name] = $value;
				}
			}
		}
	}
	/**
	 * 次のページ番号
	 * @return integer
	 */
	public function next(){
		if($this->dynamic) return $this->next_c;
		return $this->current + 1;
		/***
			$paginator = new Paginator(10,1,100);
			eq(2,$paginator->next());
		*/
	}
	/**
	 * 前のページ番号
	 * @return integer
	 */
	public function prev(){
		if($this->dynamic) return $this->prev_c;
		return $this->current - 1;
		/***
			$paginator = new Paginator(10,2,100);
			eq(1,$paginator->prev());
		*/
	}
	/**
	 * 次のページがあるか
	 * @return boolean
	 */
	public function is_next(){
		if($this->dynamic) return isset($this->next_c);
		return ($this->last > $this->current);
		/***
			$paginator = new Paginator(10,1,100);
			eq(true,$paginator->is_next());
			$paginator = new Paginator(10,9,100);
			eq(true,$paginator->is_next());
			$paginator = new Paginator(10,10,100);
			eq(false,$paginator->is_next());
		*/
	}
	/**
	 * 前のページがあるか
	 * @return boolean
	 */
	public function is_prev(){
		if($this->dynamic) return isset($this->prev_c);
		return ($this->current > 1);
		/***
			$paginator = new Paginator(10,1,100);
			eq(false,$paginator->is_prev());
			$paginator = new Paginator(10,9,100);
			eq(true,$paginator->is_prev());
			$paginator = new Paginator(10,10,100);
			eq(true,$paginator->is_prev());
		*/
	}
	/**
	 * 前のページを表すクエリ
	 * @return string
	 */
	public function query_prev(){
		$this->vars("page",($this->dynamic) ? (isset($this->prev_c) ? "-".$this->prev_c : null) : $this->prev());
		return Http::query($this->ar_vars());
	}
	/**
	 * 次のページを表すクエリ
	 * @return string
	 */
	public function query_next(){
		$this->vars("page",($this->dynamic) ? $this->next_c : $this->next());
		return Http::query($this->ar_vars());
	}
	/**
	 * 指定のページを表すクエリ
	 * @param integer $current 現在のページ番号
	 * @return string
	 */
	public function query($current){
		$this->vars("page",$current);
		return Http::query($this->ar_vars());
		/***
			$paginator = new Paginator(10,1,100);
			eq("page=3&",$paginator->query(3));
		 */
	}
	protected function __set_current__($value){
		$value = intval($value);
		$this->current = ($value === 0) ? 1 : $value;
		$this->offset = $this->limit * round(abs($this->current - 1));
	}
	protected function __set_total__($total){
		$this->total = intval($total);
		$this->last = ($this->total == 0 || $this->limit == 0) ? 0 : intval(ceil($this->total / $this->limit));
	}
	protected function __which__($args,$param){
		return null;
	}
	protected function __is_first__($paginate){
		return ($this->which_first($paginate) !== $this->first);
	}
	protected function __is_last__($paginate){
		return ($this->which_last($paginate) !== $this->last());
	}
	protected function __which_first__($paginate=null){
		if($paginate === null) return $this->first;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		$last = ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
		return (($last - $paginate) > 0) ? ($last - $paginate) : $first;
	}
	protected function __which_last__($paginate=null){
		if($paginate === null) return $this->last;
		$paginate = $paginate - 1;
		$first = ($this->current > ($paginate/2)) ? @ceil($this->current - ($paginate/2)) : 1;
		return ($this->last > ($first + $paginate)) ? ($first + $paginate) : $this->last;
	}
	/**
	 * ページとして有効な範囲のページ番号を有する配列を作成する
	 * @param integer $counter ページ数
	 * @return integer[]
	 */
	public function range($counter=10){
		if($this->which_last($counter) > 0) return range($this->which_first($counter),$this->which_last($counter));
		return array(1);
	}
	/**
	 * limit分のコンテンツがあるか
	 * @return boolean
	 */
	public function is_filled(){
		if($this->count_c >= $this->limit) return true;
		return false;
	}
	protected function __add__($mixed){
		$this->contents($mixed);
	}
	protected function __set_contents__($mixed){
		if($this->count_c <= $this->limit){
			$this->count_c++;

			if($this->count_c > $this->limit){
				$this->finish_c();
			}else{
				if($this->asc){
					array_push($this->contents,$mixed);
				}else{
					array_unshift($this->contents,$mixed);
				}
			}
		}
	}
	/**
	 * order by asc
	 * @return boolean
	 */
	public function is_asc(){
		return $this->asc;
	}
	/**
	 * order by desc
	 * @return boolean
	 */
	public function is_desc(){
		return !$this->asc;
	}
	/**
	 * n > marker 
	 * @return boolean
	 */
	public function is_gt(){
		return $this->asc;		
	}
	/**
	 * n < marker
	 * @return boolean
	 */
	public function is_lt(){
		return !$this->asc;
	}
	/**
	 * contentsがlimitに達していない場合にさらに要求をするか
	 * @return boolean
	 */
	public function more(){
		if(!$this->dynamic) return false;
		if($this->count_c > $this->limit) return false;		
		if($this->count_p !== null){
			if($this->count_p === $this->count_c){
				$this->finish_c();
				return false;
			}
			$this->offset = $this->offset + $this->limit;
		}
		$this->count_p = $this->count_c;
		return true;
		/***
			$paginator = self::dynamic_contents(4);
			foreach(array(range(3,8),range(21,50)) as $list){
				foreach($list as $v){
					if($v % 3 === 0){
						if($paginator->add($v)->is_filled()) break;
					}
				}
				if(!$paginator->more()) break;
			}
			eq(array(3,6,21,24),$paginator->contents());

			$paginator = self::dynamic_contents(4,"20");
			$list = range(1,50);
			if($paginator->is_desc()) krsort($list);
			foreach($list as $v){
				if(($paginator->is_gt() && $v > $paginator->marker())
					|| ($paginator->is_lt() && $v < $paginator->marker())
				){
					if($v % 3 === 0){
						if($paginator->add($v)->is_filled()) break;
					}
				}
			}
			eq(array(21,24,27,30),$paginator->contents());
			
			$paginator = self::dynamic_contents(4,"-20");
			$list = range(1,50);
			if($paginator->is_desc()) krsort($list);
			foreach($list as $v){
				if(($paginator->is_gt() && $v > $paginator->marker())
					|| ($paginator->is_lt() && $v < $paginator->marker())
				){
					if($v % 3 === 0){
						if($paginator->add($v)->is_filled()) break;
					}
				}
			}
			eq(array(9,12,15,18),$paginator->contents());
		 */
	}
	private function finish_c(){
		if(isset($this->contents[$this->limit-1])) $this->next_c = $this->mn($this->contents[$this->limit-1]);		
		if(isset($this->contents[0]) && ((!$this->asc && $this->count_c > $this->limit) || ($this->asc && $this->is_marker()))) $this->prev_c = $this->mn($this->contents[0]);
	}
	private function mn($v){
		return isset($this->prop) ? 
				(is_array($v) ? $v[$this->prop] : (is_object($v) ? (($v instanceof Object) ? $v->{$this->prop}() : $v->{$this->prop}) : null)) :
				$v;
	}
}