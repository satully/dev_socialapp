<?php
/**
 * Tagイテレータ
 * @author Kazutaka Tokushima
 * @license New BSD License
 */
class TagIterator implements Iterator{
	private $name = null;
	private $plain = null;
	private $tag = null;
	private $offset = 0;
	private $length = 0;
	private $count = 0;

	public function __construct($tag_name,$value,$offset,$length){
		$this->name = $tag_name;
		$this->plain = $value;
		$this->offset = $offset;
		$this->length = $length;
		$this->count = 0;
	}
	/**
	 * @see Iterator
	 */
	public function key(){
		$this->tag->name();
	}
	/**
	 * @see Iterator
	 */
	public function current(){
		$this->plain = substr($this->plain,0,$this->tag->pos()).substr($this->plain,$this->tag->pos() + strlen($this->tag->plain()));
		$this->count++;
		return $this->tag;
	}
	/**
	 * @see Iterator
	 */
	public function valid(){
		if($this->length > 0 && ($this->offset + $this->length) <= $this->count) return false;
		if(is_array($this->name)){
			$tags = array();
			foreach($this->name as $name){
				if(Tag::setof($get_tag,$this->plain,$name)) $tags[$get_tag->pos()] = $get_tag;
			}
			if(empty($tags)) return false;
			ksort($tags,SORT_NUMERIC);
			foreach($tags as $this->tag) return true;
		}
		return Tag::setof($this->tag,$this->plain,$this->name);
	}
	/**
	 * @see Iterator
	 */
	public function next(){
	}
	/**
	 * @see Iterator
	 */
	public function rewind(){
		for($i=0;$i<$this->offset;$i++){
			$this->valid();
			$this->current();
		}
	}
}